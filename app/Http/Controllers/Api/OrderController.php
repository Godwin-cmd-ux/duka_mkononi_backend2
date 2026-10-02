<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\OrderService;
use App\Services\PhoneNumber;
use Illuminate\Http\Request;

class OrderController extends BaseController
{
    /* ------------------------------------------------------------------ */
    /* Public: place an order                                              */
    /* ------------------------------------------------------------------ */

    public function store(Request $request)
    {
        $items = $request->input('items');
        $phone = $request->input('customer_phone') ?: $request->input('phone');
        $name = $request->input('customer_name') ?: $request->input('name');
        $email = $request->input('customer_email') ?: $request->input('email');
        $note = $request->input('customer_note') ?: $request->input('note');
        $clientKey = $request->input('client_order_key');

        if (! is_array($items) || count($items) === 0) {
            return $this->json(['error' => 'Cart yako ni tupu.'], 422);
        }

        if (! PhoneNumber::isValid($phone)) {
            return $this->json(['error' => 'Namba ya simu si sahihi.'], 422);
        }

        if ($email !== null && $email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Barua pepe si sahihi.'], 422);
        }

        // Idempotency: a retried submission with the same client key must not
        // create duplicate orders.
        if (is_string($clientKey) && $clientKey !== '') {
            $clientKey = substr(trim($clientKey), 0, 80);
            $existing = Order::where('client_order_key', 'like', $clientKey . '%')
                ->orderByDesc('created_at')
                ->get()
                ->toArray();
            if ($existing !== []) {
                return $this->json([
                    'message' => 'Oda yako ilikuwa imeshatumwa.',
                    'orders' => array_map([$this, 'publicOrder'], $existing),
                    'duplicate' => true,
                ], 200);
            }
        } else {
            $clientKey = null;
        }

        $priced = OrderService::priceItems($items);

        if ($priced['errors'] !== []) {
            return $this->json([
                'error' => 'Baadhi ya bidhaa hazipatikani au idadi si sahihi.',
                'details' => $priced['errors'],
            ], 422);
        }

        $customer = [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'note' => $note,
            'source' => $request->input('source', 'website'),
        ];

        $created = [];
        $multi = count($priced['groups']) > 1;
        foreach ($priced['groups'] as $businessId => $group) {
            $key = $clientKey === null ? null : ($multi ? $clientKey . '#' . $businessId : $clientKey);
            try {
                $created[] = OrderService::createOrder($group, $customer, $key);
            } catch (\Throwable $e) {
                // Best-effort cleanup of any order already written for this
                // request so a partial multi-business submission is not left half-done.
                foreach ($created as $done) {
                    try {
                        Order::where('id', $done['id'])->delete();
                    } catch (\Throwable $ignore) {
                    }
                }

                return $this->json(['error' => 'Imeshindikana kutuma oda. Tafadhali jaribu tena.'], 500);
            }
        }

        $this->log(null, 'ORDER_CREATE', '/api/orders', [
            'count' => count($created),
        ], $this->ip($request), 'success');

        return $this->json([
            'message' => 'Oda yako imepokelewa kikamilifu!',
            'orders' => array_map([$this, 'publicOrder'], $created),
        ], 201);
    }

    /* ------------------------------------------------------------------ */
    /* Public: track orders by phone                                       */
    /* ------------------------------------------------------------------ */

    public function track(Request $request)
    {
        $phone = $request->input('phone') ?: $request->query('phone');
        $reference = $request->input('reference') ?: $request->query('reference');

        if (! PhoneNumber::isValid($phone)) {
            return $this->json(['error' => 'Namba ya simu si sahihi.'], 422);
        }

        $normalized = PhoneNumber::normalize($phone);

        try {
            $orders = Order::where('customer_phone_normalized', $normalized)
                ->orderByDesc('created_at')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Imeshindikana kupata oda zako.'], 500);
        }

        // Optional extra proof: an order reference narrows the result.
        if (is_string($reference) && $reference !== '') {
            $reference = mb_strtoupper(trim($reference));
            $orders = array_values(array_filter($orders, fn ($o) => mb_strtoupper((string) ($o['order_reference'] ?? '')) === $reference));
        }

        if ($orders === []) {
            return $this->json([
                'message' => 'Hakuna oda zilizopatikana kwa namba hii.',
                'orders' => [],
            ]);
        }

        $ids = array_column($orders, 'id');
        $itemsByOrder = OrderService::itemsFor($ids);
        $historyByOrder = $this->customerHistory($ids);
        $businessNames = $this->businessNames(array_filter(array_column($orders, 'business_id')));

        $out = [];
        foreach ($orders as $order) {
            $out[] = $this->trackingOrder(
                $order,
                $itemsByOrder[$order['id']] ?? [],
                $historyByOrder[$order['id']] ?? [],
                $businessNames[$order['business_id'] ?? ''] ?? null
            );
        }

        $this->log(null, 'ORDER_TRACK', '/api/orders/track', ['count' => count($out)], $this->ip($request), 'success');

        return $this->json(['orders' => $out]);
    }

    /* ------------------------------------------------------------------ */
    /* Seller / admin: manage orders                                       */
    /* ------------------------------------------------------------------ */

    public function sellerIndex(Request $request)
    {
        if (! $this->isOrderStaff($request)) {
            return $this->json(['error' => 'Huna ruhusa ya kuona oda.'], 403);
        }

        $status = $request->query('status');
        $search = $request->query('search');

        try {
            $query = Order::query();
            if (! $this->isPlatformAdmin($request)) {
                $businessId = $this->businessId($request);
                if (! $businessId) {
                    return $this->json(['data' => [], 'meta' => ['total' => 0, 'page' => 1, 'last_page' => 1]]);
                }
                $query->where('business_id', $businessId);
            }

            if (is_string($status) && in_array($status, OrderService::STATUSES, true)) {
                $query->where('status', $status);
            }

            $orders = $query->orderByDesc('created_at')->get()->toArray();
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Imeshindikana kupata oda.'], 500);
        }

        if (is_string($search) && $search !== '') {
            $needle = mb_strtolower(trim($search));
            $orders = array_values(array_filter($orders, function ($o) use ($needle) {
                $haystack = mb_strtolower(implode(' ', [
                    $o['order_reference'] ?? '',
                    $o['customer_name'] ?? '',
                    $o['customer_phone'] ?? '',
                ]));

                return str_contains($haystack, $needle);
            }));
        }

        $perPage = 20;
        $page = max(1, (int) $request->query('page', 1));
        $total = count($orders);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $slice = array_slice($orders, ($page - 1) * $perPage, $perPage);

        $ids = array_column($slice, 'id');
        $itemsByOrder = OrderService::itemsFor($ids);

        $data = array_map(function ($o) use ($itemsByOrder) {
            return $this->sellerOrder($o, $itemsByOrder[$o['id']] ?? []);
        }, $slice);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => $total, 'page' => $page, 'last_page' => $lastPage, 'per_page' => $perPage],
        ]);
    }

    public function sellerShow(Request $request, string $id)
    {
        if (! $this->isOrderStaff($request)) {
            return $this->json(['error' => 'Huna ruhusa ya kuona oda.'], 403);
        }

        $order = Order::where('id', $id)->first();
        if (! $order) {
            return $this->json(['error' => 'Oda haipatikani.'], 404);
        }
        if (! $this->canAccessOrder($request, $order->business_id)) {
            return $this->json(['error' => 'Huna ruhusa ya kuona oda hii.'], 403);
        }

        $items = OrderService::itemsFor([$id])[$id] ?? [];
        $history = $this->fullHistory([$id])[$id] ?? [];

        return $this->json([
            'order' => $this->sellerOrder($order->toArray(), $items),
            'history' => $history,
        ]);
    }

    public function sellerUpdateStatus(Request $request, string $id)
    {
        if (! $this->isOrderStaff($request)) {
            return $this->json(['error' => 'Huna ruhusa kubadilisha oda.'], 403);
        }

        $status = (string) $request->input('status');
        $note = $request->input('note');
        $internalNote = $request->input('internal_note');

        if (! in_array($status, OrderService::STATUSES, true)) {
            return $this->json(['error' => 'Hali ya oda si sahihi.'], 422);
        }

        $order = Order::where('id', $id)->first();
        if (! $order) {
            return $this->json(['error' => 'Oda haipatikani.'], 404);
        }
        if (! $this->canAccessOrder($request, $order->business_id)) {
            return $this->json(['error' => 'Huna ruhusa ya kubadilisha oda hii.'], 403);
        }
        if (! OrderService::canTransition($order->status, $status)) {
            return $this->json([
                'error' => 'Mabadiliko ya hali haya hayaruhusiwi.',
                'from' => $order->status,
                'to' => $status,
            ], 422);
        }

        $now = OrderService::nowIso();
        $update = ['status' => $status, 'updated_at' => $now];
        if ($note !== null && $note !== '') {
            $update['customer_visible_note'] = $note;
        }
        if ($status === 'confirmed' && ! $order->confirmed_at) {
            $update['confirmed_at'] = $now;
        }
        if ($status === 'completed') {
            $update['completed_at'] = $now;
        }
        if ($status === 'cancelled') {
            $update['cancelled_at'] = $now;
        }

        Order::where('id', $id)->update($update);
        OrderService::recordStatus($id, $status, $note ?: null, $internalNote ?: null, $this->userId($request));

        $this->log($this->userId($request), 'ORDER_STATUS_UPDATE', '/api/seller/orders/' . $id, [
            'from' => $order->status,
            'to' => $status,
        ], $this->ip($request), 'success');

        $fresh = Order::where('id', $id)->first();

        return $this->json(['message' => 'Hali ya oda imesasishwa.', 'order' => $this->sellerOrder($fresh->toArray(), [])]);
    }

    public function sellerAddNote(Request $request, string $id)
    {
        if (! $this->isOrderStaff($request)) {
            return $this->json(['error' => 'Huna ruhusa kuandika kwenye oda.'], 403);
        }

        $note = $request->input('note');
        $internalNote = $request->input('internal_note');

        if (($note === null || $note === '') && ($internalNote === null || $internalNote === '')) {
            return $this->json(['error' => 'Andika maoni kwanza.'], 422);
        }

        $order = Order::where('id', $id)->first();
        if (! $order) {
            return $this->json(['error' => 'Oda haipatikani.'], 404);
        }
        if (! $this->canAccessOrder($request, $order->business_id)) {
            return $this->json(['error' => 'Huna ruhusa kuandika kwenye oda hii.'], 403);
        }

        $now = OrderService::nowIso();
        $update = ['updated_at' => $now];
        if ($note !== null && $note !== '') {
            $update['customer_visible_note'] = $note;
        }
        if ($internalNote !== null && $internalNote !== '') {
            $update['internal_note'] = $internalNote;
        }

        Order::where('id', $id)->update($update);
        OrderService::recordStatus($id, $order->status, $note ?: null, $internalNote ?: null, $this->userId($request));

        return $this->json(['message' => 'Maoni yamehifadhiwa.']);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function isOrderStaff(Request $request): bool
    {
        return in_array($this->role($request), ['admin', 'seller', 'system_admin'], true);
    }

    private function isPlatformAdmin(Request $request): bool
    {
        return $this->role($request) === 'system_admin';
    }

    private function canAccessOrder(Request $request, ?string $businessId): bool
    {
        if ($this->isPlatformAdmin($request)) {
            return true;
        }

        $callerBusiness = $this->businessId($request);

        return $callerBusiness && $businessId && $callerBusiness === $businessId;
    }

    /** Latest customer-visible note + status timeline (never internal notes). */
    private function customerHistory(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $rows = OrderStatusHistory::whereIn('order_id', $orderIds)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        $byOrder = [];
        foreach ($rows as $row) {
            $byOrder[$row['order_id']][] = [
                'status' => $row['status'],
                'note' => $row['note'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $byOrder;
    }

    private function fullHistory(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $rows = OrderStatusHistory::whereIn('order_id', $orderIds)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        $byOrder = [];
        foreach ($rows as $row) {
            $byOrder[$row['order_id']][] = [
                'status' => $row['status'],
                'note' => $row['note'] ?? null,
                'internal_note' => $row['internal_note'] ?? null,
                'changed_by' => $row['changed_by'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $byOrder;
    }

    /** Safe shape for the customer (no internal notes, no internal ids beyond the reference). */
    private function businessNames(array $businessIds): array
    {
        $businessIds = array_values(array_unique(array_filter($businessIds)));
        if ($businessIds === []) {
            return [];
        }

        try {
            $rows = Business::whereIn('id', $businessIds)->get()->toArray();
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[$row['id']] = $row['business_name'] ?? null;
        }

        return $map;
    }

    private function trackingOrder(array $order, array $items, array $history, ?string $businessName = null): array
    {
        $latestNote = null;
        foreach ($history as $entry) {
            if (! empty($entry['note'])) {
                $latestNote = $entry['note'];
                break;
            }
        }

        return [
            'order_reference' => $order['order_reference'] ?? null,
            'order_date' => $order['placed_at'] ?? ($order['created_at'] ?? null),
            'status' => $order['status'] ?? null,
            'subtotal' => (float) ($order['subtotal'] ?? 0),
            'shipping_cost' => (float) ($order['shipping_cost'] ?? 0),
            'total_amount' => (float) ($order['total_amount'] ?? 0),
            'currency' => $order['currency'] ?? 'TZS',
            'business_name' => $businessName,
            'items' => $items,
            'latest_note' => $latestNote,
            'timeline' => array_map(function ($e) {
                return ['status' => $e['status'], 'note' => $e['note'] ?? null, 'at' => $e['created_at'] ?? null];
            }, array_slice($history, 0, 8)),
        ];
    }

    /** Minimal confirmation shape after placing an order. */
    private function publicOrder(array $order): array
    {
        $items = OrderService::itemsFor([$order['id']])[$order['id']] ?? [];

        return [
            'order_reference' => $order['order_reference'] ?? null,
            'status' => $order['status'] ?? 'pending',
            'total_amount' => (float) ($order['total_amount'] ?? 0),
            'currency' => $order['currency'] ?? 'TZS',
            'items' => $items,
        ];
    }

    /** Full seller-facing order with items. */
    private function sellerOrder(array $order, array $items): array
    {
        return [
            'id' => $order['id'] ?? null,
            'order_reference' => $order['order_reference'] ?? null,
            'status' => $order['status'] ?? null,
            'customer_name' => $order['customer_name'] ?? null,
            'customer_phone' => $order['customer_phone'] ?? null,
            'customer_email' => $order['customer_email'] ?? null,
            'customer_note' => $order['customer_note'] ?? null,
            'internal_note' => $order['internal_note'] ?? null,
            'customer_visible_note' => $order['customer_visible_note'] ?? null,
            'subtotal' => (float) ($order['subtotal'] ?? 0),
            'shipping_cost' => (float) ($order['shipping_cost'] ?? 0),
            'total_amount' => (float) ($order['total_amount'] ?? 0),
            'currency' => $order['currency'] ?? 'TZS',
            'payment_status' => $order['payment_status'] ?? 'unpaid',
            'business_id' => $order['business_id'] ?? null,
            'placed_at' => $order['placed_at'] ?? ($order['created_at'] ?? null),
            'items' => $items,
        ];
    }
}
