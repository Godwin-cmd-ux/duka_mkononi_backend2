<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SaleController extends BaseController
{
    public function my(Request $request)
    {
        try {
            $userId = $this->userId($request);

            $this->touchLastSeen($userId);

            $salesQuery = Sale::where('seller_id', $userId);

            // Optional server-side date range so pages like the seller
            // profile can request exactly the day they display instead of
            // every sale ever made by this seller.
            $dateFrom = trim((string) $request->query('date_from', ''));
            $dateTo = trim((string) $request->query('date_to', ''));
            if ($dateFrom !== '') {
                $salesQuery->where('sale_date', '>=', $dateFrom);
            }
            if ($dateTo !== '') {
                $salesQuery->where('sale_date', '<=', $dateTo);
            }

            $sales = $salesQuery->orderBy('created_at', 'desc')->get()->toArray();

            $hydrated = $this->attachRelations($sales);

            $this->log($userId, 'SALES_VIEW', '/api/sales/my', [
                'count' => count($hydrated),
            ], $this->ip($request), 'success');

            return $this->json($hydrated);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'SALES_VIEW_ERROR', '/api/sales/my', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function store(Request $request)
    {
        // A sale performs many sequential Supabase REST roundtrips. On
        // Windows the PHP max_execution_time counts WALL-CLOCK seconds, so
        // a slow network could kill the request mid-flight (PHP fatal →
        // non-JSON response → the app misreported "Hitilafu ya mtandao").
        // Give this request plenty of room to finish.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        try {
            $userId = $this->userId($request);
            $customer_id = $request->input('customer_id');
            $sale_date = $request->input('sale_date');
            $payment_method = $request->input('payment_method');
            $notes = $request->input('notes');
            $items = $request->input('items');

            $this->touchLastSeen($userId);

            if (!$items || !is_array($items) || count($items) === 0) {
                $this->log($userId, 'SALE_CREATE_FAILED', '/api/sales', [
                    'reason' => 'No items provided',
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Bidhaa za kuuza zinahitajika',
                    'code' => 'NO_ITEMS',
                ], 400);
            }

            $total_amount = 0;
            $invalidItems = [];

            // Idempotency: a slow network makes the sale button effectively
            // double-clickable, and both requests passed every stock check
            // (each saw the pre-sale stock), so selling 2 deducted 4. The
            // frontend now sends a clientSaleKey per attempt; a retried key
            // returns the ORIGINAL sale instead of creating a second one.
            $clientSaleKey = substr(trim((string) $request->input('clientSaleKey', '')), 0, 80);
            if ($clientSaleKey !== '') {
                try {
                    $existing = Sale::where('seller_id', $userId)
                        ->where('notes', 'like', 'ai_dup_' . $clientSaleKey . '%')
                        ->select(['id', 'invoice_number', 'total_amount'])
                        ->first();
                    if ($existing) {
                        $this->log($userId, 'SALE_DUPLICATE_SUPPRESSED', '/api/sales', [
                            'clientSaleKey' => $clientSaleKey,
                            'sale_id' => $existing->id,
                        ], $this->ip($request), 'success');
                        return $this->json([
                            'success' => true,
                            'duplicate' => true,
                            'message' => 'Mauzo haya yameshahifadhiwa tayari.',
                            'invoice_number' => $existing->invoice_number,
                            'sale' => $existing->toArray(),
                        ], 200);
                    }
                } catch (\Throwable $e) {
                }
            }

            foreach ($items as $item) {
                if (!($item['product_id'] ?? null) || !($item['quantity'] ?? null) || !($item['unit_price'] ?? null)) {
                    $invalidItems[] = ['item' => $item, 'reason' => 'Incomplete data'];
                }

                if (!is_numeric($item['quantity'] ?? null) || (float) $item['quantity'] <= 0) {
                    $invalidItems[] = ['item' => $item, 'reason' => 'Invalid quantity'];
                }

                if (!is_numeric($item['unit_price'] ?? null) || (float) $item['unit_price'] <= 0) {
                    $invalidItems[] = ['item' => $item, 'reason' => 'Invalid unit price'];
                }

                $total_amount += ((float) ($item['quantity'] ?? 0)) * ((float) ($item['unit_price'] ?? 0));
            }

            if (count($invalidItems) > 0) {
                $this->log($userId, 'SALE_CREATE_FAILED', '/api/sales', [
                    'reason' => 'Invalid items',
                    'invalid_items' => $invalidItems,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Taarifa za bidhaa hazijakamilika au si sahihi',
                    'code' => 'INVALID_ITEMS',
                ], 400);
            }

            $stockIssues = [];
            foreach ($items as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('is_active', true)
                    ->select(['stock', 'name', 'seller_id', 'is_active'])
                    ->first();

                if (!$product) {
                    $stockIssues[] = ['product_id' => $item['product_id'], 'reason' => 'Product not found'];
                    continue;
                }

                if ($product->seller_id !== $userId) {
                    $sellerUser = User::where('id', $userId)->select(['business_name', 'role'])->first();

                    if ($sellerUser && $sellerUser->role === 'seller') {
                        $adminUser = User::where('business_name', $sellerUser->business_name)
                            ->where('role', 'admin')
                            ->where('status', 'approved')
                            ->select(['id'])
                            ->first();

                        if (!$adminUser || $adminUser->id !== $product->seller_id) {
                            $stockIssues[] = [
                                'product_id' => $item['product_id'],
                                'product_name' => $product->name,
                                'reason' => 'Product access denied',
                            ];
                        }
                    } else {
                        $stockIssues[] = [
                            'product_id' => $item['product_id'],
                            'product_name' => $product->name,
                            'reason' => 'Product access denied',
                        ];
                    }
                }

                if ($product->stock < $item['quantity']) {
                    $stockIssues[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $product->name,
                        'reason' => 'Insufficient stock',
                        'available' => $product->stock,
                        'requested' => $item['quantity'],
                    ];
                }
            }

            if (count($stockIssues) > 0) {
                $this->log($userId, 'SALE_CREATE_FAILED', '/api/sales', [
                    'reason' => 'Stock issues',
                    'stock_issues' => $stockIssues,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Matatizo ya kiasi cha bidhaa',
                    'stockIssues' => $stockIssues,
                    'code' => 'STOCK_ISSUES',
                ], 400);
            }

            $invoice_number = $this->generateInvoiceNumber($userId);

            $now = $this->isoNow();
            $newSaleId = Str::uuid()->toString();

            $saleData = [
                'id' => $newSaleId,
                'seller_id' => $userId,
                'customer_id' => $customer_id ?: null,
                'sale_date' => $sale_date ?: gmdate('Y-m-d'),
                'total_amount' => $total_amount,
                'payment_method' => $payment_method ?: 'cash',
                'notes' => $notes ?: null,
                'invoice_number' => $invoice_number,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Stamp an idempotency marker into notes so a retried request
            // (same clientSaleKey) can be recognized even if the first
            // request's response never reached the client. The original
            // user-visible notes are preserved after the marker.
            if ($clientSaleKey !== '') {
                $saleData['notes'] = 'ai_dup_' . $clientSaleKey . ($saleData['notes'] ? ' | ' . $saleData['notes'] : '');
            }

            try {
                Sale::create($saleData);
            } catch (QueryException $e) {
                $this->log($userId, 'SALE_CREATE_FAILED', '/api/sales', [
                    'reason' => 'Database error',
                    'error' => $e->getMessage(),
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Hitilafu ya kuhifadhi rekodi ya mauzo',
                    'details' => $e->getMessage(),
                    'code' => 'SALE_SAVE_ERROR',
                ], 500);
            }

            $saleItems = [];
            foreach ($items as $item) {
                $saleItems[] = [
                    'id' => Str::uuid()->toString(),
                    'sale_id' => $newSaleId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => ((float) $item['quantity']) * ((float) $item['unit_price']),
                    'created_at' => $now,
                ];
            }

            try {
                SaleItem::insert($saleItems);
            } catch (QueryException $e) {
                Sale::where('id', $newSaleId)->delete();

                $this->log($userId, 'SALE_CREATE_FAILED', '/api/sales', [
                    'reason' => 'Sale items error',
                    'error' => $e->getMessage(),
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Hitilafu ya kuhifadhi bidhaa za mauzo',
                    'details' => $e->getMessage(),
                    'code' => 'SALE_ITEMS_SAVE_ERROR',
                ], 500);
            }

            $stockUpdates = [];
            foreach ($items as $item) {
                // Re-read immediately before writing. The write is a plain
                // absolute assignment of (fresh stock - sold qty); we never
                // write back a stock value captured before the sale rows were
                // inserted, so concurrent sales cannot resurrect old stock.
                $currentProduct = Product::where('id', $item['product_id'])->select(['stock'])->first();

                if ($currentProduct) {
                    $newStock = $currentProduct->stock - ((float) $item['quantity']);

                    try {
                        Product::where('id', $item['product_id'])->update([
                            'stock' => $newStock,
                            'updated_at' => $this->isoNow(),
                        ]);

                        $stockUpdates[] = [
                            'product_id' => $item['product_id'],
                            'old_stock' => $currentProduct->stock,
                            'new_stock' => $newStock,
                            'quantity_sold' => $item['quantity'],
                        ];
                    } catch (\Throwable $th) {
                    }
                }
            }

            if ($customer_id) {
                $currentCustomer = Customer::where('id', $customer_id)->select(['total_purchases', 'purchases_count'])->first();

                if ($currentCustomer) {
                    $newTotalPurchases = ($currentCustomer->total_purchases ?? 0) + $total_amount;
                    $newPurchasesCount = ($currentCustomer->purchases_count ?? 0) + 1;

                    Customer::where('id', $customer_id)->update([
                        'total_purchases' => $newTotalPurchases,
                        'purchases_count' => $newPurchasesCount,
                        'last_purchase_date' => $this->isoNow(),
                        'updated_at' => $this->isoNow(),
                    ]);
                }
            }

            $completeSaleRow = Sale::where('id', $newSaleId)->first();
            $hydrated = $completeSaleRow ? $this->attachRelations([$completeSaleRow->toArray()])[0] : null;

            $completeSale = $hydrated ?: ['id' => $newSaleId, 'invoice_number' => $invoice_number];

            $this->log($userId, 'SALE_CREATE', '/api/sales', [
                'sale_id' => $newSaleId,
                'invoice_number' => $invoice_number,
                'total_amount' => $total_amount,
                'items_count' => count($items),
                'customer_id' => $customer_id,
                'stock_updates' => $stockUpdates,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Mauzo yamekamilika kikamilifu!',
                'invoice_number' => $invoice_number,
                'unit_price_included' => true,
                'sale' => $completeSale,
            ], 201);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'SALE_CREATE_ERROR', '/api/sales', [
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ], $this->ip($request), 'failed');

            $userMessage = 'Hitilafu isiyotarajiwa katika kurekodi mauzo';
            $errorCode = 'UNEXPECTED_ERROR';

            if (strpos($e->getMessage(), 'violates foreign key constraint') !== false) {
                $userMessage = 'Bidhaa au mteja haipo kwenye mfumo';
                $errorCode = 'FOREIGN_KEY_VIOLATION';
            } elseif (strpos($e->getMessage(), 'invalid input syntax for type uuid') !== false) {
                $userMessage = 'Nambari ya bidhaa au mteja si sahihi';
                $errorCode = 'INVALID_UUID_SYNTAX';
            } elseif (strpos($e->getMessage(), 'connection') !== false) {
                $userMessage = 'Hitilafu ya muunganisho na database. Tafadhali jaribu tena.';
                $errorCode = 'DATABASE_CONNECTION_ERROR';
            }

            return $this->json([
                'success' => false,
                'error' => $userMessage,
                'details' => $e->getMessage(),
                'code' => $errorCode,
            ], 500);
        }
    }

    /**
     * PUT /api/sales/{id}
     *
     * Edit a sale. The sale already moved stock when it was created, so an
     * edit must move stock by the DIFFERENCE only:
     *   quantity increased -> sell the extra units (deduct the delta)
     *   quantity reduced   -> return those units to stock (credit the delta)
     * Re-applying the full quantity would double-count the original sale.
     *
     * Data integrity:
     *   - the sale total is recomputed server-side from its own lines, so the
     *     client's `total_amount` can never make the figures drift;
     *   - a sale may only be edited by the seller who recorded it;
     *   - increasing quantity past available stock is rejected before any write;
     *   - Supabase/PostgREST has no cross-table transaction, so each write is
     *     compensated if a later write fails, and the internal
     *     `ai_dup_<key>` idempotency marker in `notes` is preserved;
     *   - the customer's running totals are adjusted by the total's delta.
     */
    public function update(Request $request, $id)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $id = trim((string) $id);

        try {
            $userId = $this->userId($request);
            $this->touchLastSeen($userId);

            $sale = $id !== '' ? Sale::where('id', $id)->where('seller_id', $userId)->first() : null;

            if (!$sale) {
                $this->log($userId, 'SALE_UPDATE_FAILED', '/api/sales/' . $id, [
                    'reason' => 'Sale not found or not owned by caller',
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Mauzo hayakupatikana au huna ruhusa kuyahariri',
                    'code' => 'SALE_NOT_FOUND',
                ], 404);
            }

            // ---- 1. Validate the requested line changes -------------------
            $incomingItems = $request->input('sale_items');
            $incomingItems = (is_array($incomingItems) && count($incomingItems) > 0) ? $incomingItems : [];

            $changes = [];
            $invalid = [];
            foreach ($incomingItems as $raw) {
                if (!is_array($raw)) {
                    $invalid[] = ['reason' => 'Malformed sale item'];
                    continue;
                }

                $itemId = isset($raw['id']) ? trim((string) $raw['id']) : '';
                $qty = (isset($raw['quantity']) && is_numeric($raw['quantity'])) ? (float) $raw['quantity'] : 0;
                $unitPrice = (isset($raw['unit_price']) && is_numeric($raw['unit_price'])) ? (float) $raw['unit_price'] : 0;

                if ($itemId === '') {
                    $invalid[] = ['reason' => 'Missing sale item id'];
                    continue;
                }
                if ($qty <= 0) {
                    $invalid[] = ['id' => $itemId, 'reason' => 'Invalid quantity'];
                    continue;
                }
                if ($unitPrice <= 0) {
                    $invalid[] = ['id' => $itemId, 'reason' => 'Invalid unit price'];
                    continue;
                }

                // The line must belong to THIS sale (and therefore to the caller).
                $existing = SaleItem::where('id', $itemId)->where('sale_id', $sale->id)->first();
                if (!$existing) {
                    $invalid[] = ['id' => $itemId, 'reason' => 'Sale item not found'];
                    continue;
                }

                $oldQty = (float) ($existing->quantity ?? 0);
                $oldUnitPrice = (float) ($existing->unit_price ?? 0);

                $changes[] = [
                    'id' => $itemId,
                    'product_id' => $existing->product_id,
                    'old_quantity' => $oldQty,
                    'old_unit_price' => $oldUnitPrice,
                    'new_quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'delta' => $qty - $oldQty,
                ];
            }

            if (count($invalid) > 0) {
                $this->log($userId, 'SALE_UPDATE_FAILED', '/api/sales/' . $sale->id, [
                    'reason' => 'Invalid items',
                    'invalid_items' => $invalid,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Taarifa za bidhaa hazijakamilika au si sahihi',
                    'invalidItems' => $invalid,
                    'code' => 'INVALID_ITEMS',
                ], 400);
            }

            // ---- 2. Plan the stock movement per product -------------------
            // One product may appear on more than one line of the same sale,
            // so the deltas are aggregated first (see planStockDeltas(), which
            // is pure and unit tested). Increasing a quantity sells the extra
            // units; reducing it puts those units back on the shelf.
            $productIds = [];
            foreach ($changes as $change) {
                if (!empty($change['product_id'])) {
                    $productIds[(string) $change['product_id']] = true;
                }
            }
            $productIds = array_keys($productIds);

            $stockByProduct = [];
            $productNames = [];
            if (count($productIds) > 0) {
                // One lookup for every product on the sale instead of N queries.
                $products = Product::whereIn('id', $productIds)->select(['id', 'stock', 'name'])->get()->toArray();
                foreach ($products as $product) {
                    $pid = (string) ($product['id'] ?? '');
                    if ($pid === '') {
                        continue;
                    }
                    $stockByProduct[$pid] = (float) ($product['stock'] ?? 0);
                    $productNames[$pid] = $product['name'] ?? null;
                }
            }

            // Selling more than exists must fail BEFORE anything is written.
            $plan = self::planStockDeltas($changes, $stockByProduct);
            $stockDeltas = $plan['deltas'];
            $stockIssues = [];
            foreach ($plan['issues'] as $issue) {
                $issue['product_name'] = $productNames[(string) $issue['product_id']] ?? null;
                $stockIssues[] = $issue;
            }

            if (count($stockIssues) > 0) {
                $this->log($userId, 'SALE_UPDATE_FAILED', '/api/sales/' . $sale->id, [
                    'reason' => 'Stock issues',
                    'stock_issues' => $stockIssues,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Hakuna bidhaa za kutosha kwa kuongeza kiasi',
                    'stockIssues' => $stockIssues,
                    'code' => 'STOCK_ISSUES',
                ], 400);
            }

            // ---- 3. Recompute the sale total from its own lines -----------
            $existingItems = SaleItem::where('sale_id', $sale->id)->get()->toArray();
            $changesById = [];
            foreach ($changes as $change) {
                $changesById[(string) $change['id']] = $change;
            }

            $newTotal = 0.0;
            foreach ($existingItems as $line) {
                $lineId = (string) ($line['id'] ?? '');
                if (isset($changesById[$lineId])) {
                    $newTotal += $changesById[$lineId]['new_quantity'] * $changesById[$lineId]['unit_price'];
                } else {
                    $newTotal += (float) ($line['total_price'] ?? 0);
                }
            }
            $newTotal = round($newTotal, 2);

            $now = $this->isoNow();
            $oldTotal = (float) ($sale->total_amount ?? 0);
            $oldNotes = (string) ($sale->notes ?? '');
            // Kept so a compensated (rolled-back) edit leaves no trace - including
            // the `updated_at` that the admin preview uses as an edit marker.
            $oldUpdatedAt = (string) ($sale->updated_at ?? $now);

            // Never drop the internal double-submit marker store() wrote into notes.
            $marker = '';
            if (strncmp($oldNotes, 'ai_dup_', 7) === 0) {
                $separator = strpos($oldNotes, ' | ');
                $marker = ($separator === false) ? $oldNotes : substr($oldNotes, 0, $separator);
            }
            $incomingNotes = trim((string) $request->input('notes', ''));
            $incomingCustomerName = trim((string) $request->input('customer_name', ''));
            if ($incomingNotes === '') {
                $newNotes = $marker;
            } elseif ($marker !== '') {
                $newNotes = $marker . ' | ' . $incomingNotes;
            } else {
                $newNotes = $incomingNotes;
            }

            // ---- 4. Write the sale header (notes + recomputed total) ------
            try {
                Sale::where('id', $sale->id)->where('seller_id', $userId)->update([
                    'notes' => $newNotes !== '' ? $newNotes : null,
                    'total_amount' => $newTotal,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                $this->log($userId, 'SALE_UPDATE_FAILED', '/api/sales/' . $sale->id, [
                    'reason' => 'Sale header write failed',
                    'error' => $e->getMessage(),
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Imeshindikana kuhifadhi mabadiliko ya mauzo',
                    'code' => 'SALE_UPDATE_ERROR',
                ], 500);
            }

            // ---- 5. Write the sale lines ---------------------------------
            $appliedItems = [];
            foreach ($changes as $change) {
                try {
                    SaleItem::where('id', $change['id'])->where('sale_id', $sale->id)->update([
                        'quantity' => $change['new_quantity'],
                        'unit_price' => $change['unit_price'],
                        'total_price' => round($change['new_quantity'] * $change['unit_price'], 2),
                    ]);
                    $appliedItems[] = $change;
                } catch (\Throwable $e) {
                    $this->revertSaleItems($sale->id, $appliedItems, $now);
                    $this->revertSaleHeader($sale->id, $userId, $oldNotes, $oldTotal, $oldUpdatedAt);

                    $this->log($userId, 'SALE_UPDATE_FAILED', '/api/sales/' . $sale->id, [
                        'reason' => 'Sale item write failed',
                        'error' => $e->getMessage(),
                    ], $this->ip($request), 'failed');

                    return $this->json([
                        'success' => false,
                        'error' => 'Imeshindikana kuhifadhi bidhaa za mauzo',
                        'code' => 'SALE_ITEMS_UPDATE_ERROR',
                    ], 500);
                }
            }

            // ---- 6. Move stock by the difference ONLY --------------------
            $stockUpdates = [];
            foreach ($stockDeltas as $productId => $delta) {
                if (abs($delta) < 0.00001) {
                    continue;
                }

                // Re-read right before writing and store an absolute, fresh
                // value (same rule as store()) so a concurrent sale cannot be
                // resurrected from a stock number read earlier.
                $current = Product::where('id', $productId)->select(['stock'])->first();
                if (!$current) {
                    continue;
                }

                $oldStock = (float) ($current->stock ?? 0);
                $newStock = $oldStock - $delta; // increase => delta>0 => stock down
                if ($newStock < 0) {
                    $newStock = 0;
                }

                try {
                    Product::where('id', $productId)->update([
                        'stock' => $newStock,
                        'updated_at' => $now,
                    ]);

                    $stockUpdates[] = [
                        'product_id' => $productId,
                        'quantity_change' => $delta,
                        'old_stock' => $oldStock,
                        'new_stock' => $newStock,
                    ];
                } catch (\Throwable $e) {
                    foreach (array_reverse($stockUpdates) as $applied) {
                        try {
                            Product::where('id', $applied['product_id'])->update([
                                'stock' => $applied['old_stock'],
                                'updated_at' => $now,
                            ]);
                        } catch (\Throwable $rollbackError) {
                        }
                    }
                    $this->revertSaleItems($sale->id, $appliedItems, $now);
                    $this->revertSaleHeader($sale->id, $userId, $oldNotes, $oldTotal, $oldUpdatedAt);

                    $this->log($userId, 'SALE_UPDATE_FAILED', '/api/sales/' . $sale->id, [
                        'reason' => 'Stock write failed',
                        'error' => $e->getMessage(),
                    ], $this->ip($request), 'failed');

                    return $this->json([
                        'success' => false,
                        'error' => 'Imeshindikana kusasisha stoo',
                        'code' => 'STOCK_UPDATE_ERROR',
                    ], 500);
                }
            }

            // ---- 7. Keep the customer's running totals honest -------------
            if ($sale->customer_id) {
                $customerDelta = $newTotal - $oldTotal;
                if (abs($customerDelta) > 0.00001) {
                    $customer = Customer::where('id', $sale->customer_id)->select(['total_purchases'])->first();
                    if ($customer) {
                        $newTotalPurchases = max(0, (float) ($customer->total_purchases ?? 0) + $customerDelta);
                        try {
                            Customer::where('id', $sale->customer_id)->update([
                                'total_purchases' => $newTotalPurchases,
                                'updated_at' => $now,
                            ]);
                        } catch (\Throwable $e) {
                            // Best effort: the sale itself is already saved correctly.
                        }
                    }
                }
            }

            $updatedSale = Sale::where('id', $sale->id)->first();
            $hydrated = $updatedSale ? $this->attachRelations([$updatedSale->toArray()])[0] : null;

            // Rich details: the admin preview turns this log entry into a
            // "taarifa" (who edited which sale, from what to what).
            $itemChanges = [];
            foreach ($changes as $change) {
                $itemChanges[] = [
                    'product_id' => $change['product_id'],
                    'product_name' => $productNames[(string) $change['product_id']] ?? null,
                    'old_quantity' => $change['old_quantity'],
                    'new_quantity' => $change['new_quantity'],
                    'unit_price' => $change['unit_price'],
                ];
            }

            $this->log($userId, 'SALE_UPDATE', '/api/sales/' . $sale->id, [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'customer_name' => $incomingCustomerName,
                'old_total_amount' => $oldTotal,
                'total_amount' => $newTotal,
                'items_changed' => count($changes),
                'items' => $itemChanges,
                'stock_updates' => $stockUpdates,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Mauzo yamehaririwa kikamilifu!',
                'sale' => $hydrated,
                'stock_updates' => $stockUpdates,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'SALE_UPDATE_ERROR', '/api/sales/' . $id, [
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->dbError($e);
        }
    }

    /**
     * Compensating write: restore sale lines already applied in this request.
     * PostgREST has no transaction, so a failed later step must undo earlier ones.
     */
    private function revertSaleItems(string $saleId, array $appliedItems, string $now): void
    {
        foreach (array_reverse($appliedItems) as $applied) {
            try {
                SaleItem::where('id', $applied['id'])->where('sale_id', $saleId)->update([
                    'quantity' => $applied['old_quantity'],
                    'unit_price' => $applied['old_unit_price'],
                    'total_price' => round($applied['old_quantity'] * $applied['old_unit_price'], 2),
                ]);
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * Pure planning step for a sale edit (no I/O - unit tested).
     *
     * Aggregates each product's stock delta across every changed line and
     * flags a plan that would break stock:
     *   - the product does not exist, or
     *   - selling the extra units would exceed the stock on hand.
     * A reduced quantity (negative delta) is always safe: it credits stock back.
     *
     * @param  array<int, array{product_id?: mixed, delta?: mixed}>  $changes
     * @param  array<string, float|int>  $stockByProduct  product id => stock on hand
     * @return array{deltas: array<string, float>, issues: array<int, array<string, mixed>>}
     */
    public static function planStockDeltas(array $changes, array $stockByProduct): array
    {
        $deltas = [];
        foreach ($changes as $change) {
            $productId = $change['product_id'] ?? null;
            if ($productId === null || $productId === '') {
                continue;
            }

            $productId = (string) $productId;
            if (!isset($deltas[$productId])) {
                $deltas[$productId] = 0.0;
            }
            $deltas[$productId] += (float) ($change['delta'] ?? 0);
        }

        $issues = [];
        foreach ($deltas as $productId => $delta) {
            if ($delta <= 0) {
                continue; // reducing a quantity only ever puts stock back
            }

            if (!array_key_exists($productId, $stockByProduct)) {
                $issues[] = ['product_id' => $productId, 'reason' => 'Product not found'];
                continue;
            }

            $available = (float) $stockByProduct[$productId];
            if ($available < $delta) {
                $issues[] = [
                    'product_id' => $productId,
                    'reason' => 'Insufficient stock',
                    'available' => $available,
                    'additional_required' => $delta,
                ];
            }
        }

        return ['deltas' => $deltas, 'issues' => $issues];
    }

    /**
     * Compensating write: restore the sale header (notes + total).
     */
    private function revertSaleHeader(string $saleId, ?string $userId, string $oldNotes, float $oldTotal, string $oldUpdatedAt): void
    {
        try {
            Sale::where('id', $saleId)->where('seller_id', $userId)->update([
                'notes' => $oldNotes !== '' ? $oldNotes : null,
                'total_amount' => $oldTotal,
                // Restore the original timestamp, not now(): otherwise a failed
                // edit would still look like an edit to the admin preview.
                'updated_at' => $oldUpdatedAt,
            ]);
        } catch (\Throwable $e) {
        }
    }

    private function generateInvoiceNumber($seller_id = null)
    {
        try {
            $yearSuffix = substr((string) date('Y'), -2);

            // Only the most recent invoices matter for the next sequence
            // number. Fetching the ENTIRE history here made every sale
            // slower as the year's rows grew (and inflated request time
            // towards the PHP max_execution_time limit).
            $query = Sale::select(['invoice_number', 'created_at'])
                ->where('invoice_number', 'like', $yearSuffix . '-%')
                ->orderBy('created_at', 'desc')
                ->limit(200);

            if ($seller_id) {
                $query->where('seller_id', $seller_id);
            }

            $lastSales = $query->get()->toArray();

            if (count($lastSales) === 0) {
                return $yearSuffix . '-0001';
            }

            $maxSequence = 0;
            foreach ($lastSales as $sale) {
                if ($sale['invoice_number']) {
                    $parts = explode('-', $sale['invoice_number']);
                    if (count($parts) === 2) {
                        $sequence = (int) $parts[1];
                        if (is_numeric($parts[1]) && $sequence > $maxSequence) {
                            $maxSequence = $sequence;
                        }
                    }
                }
            }

            $newSequence = str_pad((string) ($maxSequence + 1), 4, '0', STR_PAD_LEFT);
            return $yearSuffix . '-' . $newSequence;
        } catch (\Throwable $e) {
            $yearSuffix = substr((string) date('Y'), -2);
            $timestamp = substr((string) (int) (microtime(true) * 1000), -4);
            return $yearSuffix . '-' . $timestamp;
        }
    }

    private function attachRelations(array $sales): array
    {
        $saleIds = array_column($sales, 'id');

        $items = $saleIds ? SaleItem::whereIn('sale_id', $saleIds)->get()->toArray() : [];

        $productIds = [];
        foreach ($items as $item) {
            if (!empty($item['product_id'])) {
                $productIds[] = $item['product_id'];
            }
        }
        $products = $productIds ? Product::whereIn('id', array_unique($productIds))->get()->keyBy('id')->toArray() : [];

        $custIds = [];
        foreach ($sales as $sale) {
            if (!empty($sale['customer_id'])) {
                $custIds[] = $sale['customer_id'];
            }
        }
        $customers = $custIds ? Customer::whereIn('id', array_unique($custIds))->get()->keyBy('id')->toArray() : [];

        $out = [];
        foreach ($sales as $sale) {
            $saleItems = array_values(array_filter($items, function ($item) use ($sale) {
                return (string) ($item['sale_id'] ?? '') === (string) $sale['id'];
            }));
            foreach ($saleItems as $k => $saleItem) {
                $saleItems[$k]['products'] = $products[$saleItem['product_id'] ?? null] ?? null;
            }
            $sale['sale_items'] = $saleItems;
            $sale['customers'] = $customers[$sale['customer_id'] ?? null] ?? null;
            $out[] = $sale;
        }

        return $out;
    }
}