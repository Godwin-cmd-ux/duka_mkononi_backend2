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