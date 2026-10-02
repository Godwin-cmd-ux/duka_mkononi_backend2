<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Shared order engine used by the website, the mobile app and the seller /
 * system-admin endpoints. This is the ONLY place order prices and totals are
 * computed: a client-submitted price or total is never trusted.
 *
 * Business rule: one order belongs to exactly one business. A cart spanning
 * several businesses is split into one order per business (never silently
 * merged into an incoherent order).
 */
class OrderService
{
    /** Allowed forward transitions for the order lifecycle. */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready', 'cancelled'],
        'ready' => ['out_for_delivery', 'delivered', 'cancelled'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public const STATUSES = [
        'pending', 'confirmed', 'processing', 'ready',
        'out_for_delivery', 'delivered', 'completed', 'cancelled',
    ];

    /**
     * Validate the requested line items against the database and price them.
     *
     * @param  array<int,array{product_id?:string,quantity?:mixed}>  $items
     * @return array{groups:array<string,array>, errors:array<int,string>}
     */
    public static function priceItems(array $items): array
    {
        $errors = [];
        $groups = []; // business_id => ['items'=>[], 'subtotal'=>...]

        foreach ($items as $index => $item) {
            $productId = is_array($item) ? ($item['product_id'] ?? null) : null;
            $quantity = is_array($item) ? ($item['quantity'] ?? null) : null;

            $quantity = is_numeric($quantity) ? (int) $quantity : 0;

            if (! $productId || ! self::isUuid((string) $productId)) {
                $errors[] = "item_{$index}_missing_product";
                continue;
            }
            if ($quantity <= 0) {
                $errors[] = "item_{$index}_invalid_quantity";
                continue;
            }

            try {
                $product = Product::where('id', $productId)->first();
            } catch (\Throwable $e) {
                $errors[] = "item_{$index}_unavailable";
                continue;
            }
            if (! $product || ! $product->is_active) {
                $errors[] = "item_{$index}_unavailable";
                continue;
            }

            $selling = $product->expected_selling_price;
            if ($selling === null || $selling === '' || (float) $selling <= 0) {
                $errors[] = "item_{$index}_no_price";
                continue;
            }

            $stock = (int) ($product->stock ?? 0);
            if ($quantity > $stock) {
                $errors[] = "item_{$index}_insufficient_stock";
                continue;
            }

            $businessId = BusinessResolver::idForUser($product->seller_id);
            if (! $businessId) {
                $errors[] = "item_{$index}_no_business";
                continue;
            }

            $unitPrice = round((float) $selling, 2);
            $lineTotal = round($unitPrice * $quantity, 2);

            if (! isset($groups[$businessId])) {
                $groups[$businessId] = ['items' => [], 'subtotal' => 0.0];
            }

            $groups[$businessId]['items'][] = [
                'product_id' => $product->id,
                'business_id' => $businessId,
                'seller_id' => $product->seller_id,
                'product_name' => $product->name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
            $groups[$businessId]['subtotal'] += $lineTotal;
        }

        return ['groups' => $groups, 'errors' => $errors];
    }

    /**
     * Persist an order for one business. Returns the created order row as array.
     *
     * @param  array{items:array,subtotal:float}  $group
     */
    public static function createOrder(array $group, array $customer, ?string $clientKey = null): array
    {
        $now = self::nowIso();
        $reference = self::reference();
        $orderId = (string) Str::uuid();

        $subtotal = round((float) $group['subtotal'], 2);
        $shipping = round((float) ($customer['shipping_cost'] ?? 0), 2);
        $total = round($subtotal + $shipping, 2);

        $order = Order::create([
            'id' => $orderId,
            'order_reference' => $reference,
            'business_id' => $group['items'][0]['business_id'],
            'seller_id' => $group['items'][0]['seller_id'],
            'customer_name' => $customer['name'] ?? null,
            'customer_phone' => $customer['phone'],
            'customer_phone_normalized' => PhoneNumber::normalize($customer['phone']),
            'customer_email' => $customer['email'] ?? null,
            'customer_note' => $customer['note'] ?? null,
            'status' => 'pending',
            'source' => $customer['source'] ?? 'website',
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'total_amount' => $total,
            'currency' => 'TZS',
            'payment_status' => 'unpaid',
            'client_order_key' => $clientKey,
            'placed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($group['items'] as $item) {
            OrderItem::create([
                'id' => (string) Str::uuid(),
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'business_id' => $item['business_id'],
                'product_name' => $item['product_name'],
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'line_total' => $item['line_total'],
                'created_at' => $now,
            ]);
        }

        self::recordStatus($orderId, 'pending', null, null, $customer['placed_by'] ?? null);

        return $order->toArray();
    }

    /** Append a status history row. */
    public static function recordStatus(string $orderId, string $status, ?string $note, ?string $internalNote, ?string $changedBy): void
    {
        OrderStatusHistory::create([
            'id' => (string) Str::uuid(),
            'order_id' => $orderId,
            'status' => $status,
            'note' => $note,
            'internal_note' => $internalNote,
            'changed_by' => $changedBy,
            'created_at' => self::nowIso(),
        ]);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** The backend uses UUID primary keys; reject anything else before it reaches PostgREST. */
    public static function isUuid(?string $value): bool
    {
        return is_string($value)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    public static function nowIso(): string
    {
        return substr(now()->toISOString(true), 0, 23) . '';
    }

    /** Human-friendly, unique order reference: DM-YYYYMMDD-XXXXXX. */
    public static function reference(): string
    {
        return 'DM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }

    /** All line items for a set of order ids, grouped by order id. */
    public static function itemsFor(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $rows = OrderItem::whereIn('order_id', $orderIds)->get()->toArray();
        $byOrder = [];
        foreach ($rows as $row) {
            $byOrder[$row['order_id']][] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'unit_price' => (float) $row['unit_price'],
                'quantity' => (int) $row['quantity'],
                'line_total' => (float) $row['line_total'],
            ];
        }

        return $byOrder;
    }
}
