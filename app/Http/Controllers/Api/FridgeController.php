<?php

namespace App\Http\Controllers\Api;

use App\Models\Fridge;
use App\Models\FridgeStock;
use App\Models\FridgeStockMovement;
use App\Models\Product;
use App\Models\SaleItemFridgeAllocation;
use App\Services\BusinessResolver;
use App\Services\FridgeAlertService;
use App\Services\FridgeStockMath;
use App\Services\Roles;
use App\Services\Supabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Fridge / Freezer inventory API.
 *
 * Every mutation is scoped by the JWT business id and, when the business uses
 * branches, by the selected branch. The invariant is:
 *
 *   products.stock = Outside Stock + SUM(fridge_stock.quantity)
 *
 * Moving stock into/out of a fridge calls an atomic RPC and never touches
 * products.stock. Sales are handled by SaleController -> create_pos_sale.
 */
class FridgeController extends BaseController
{
    public function index(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        try {
            $businessId = $this->businessId($request);
            if (! $businessId) {
                return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
            }

            $includeArchived = filter_var($request->query('include_archived', false), FILTER_VALIDATE_BOOLEAN);

            $fridgeQuery = Fridge::where('business_id', $businessId);
            $fridgeQuery = $this->scopeBranch($fridgeQuery, $request);
            if (! $includeArchived) {
                $fridgeQuery->where('status', 'active');
            }
            $fridges = $fridgeQuery->orderBy('name')->get()->toArray();

            $fridgeIds = array_column($fridges, 'id');
            $stockQuery = FridgeStock::where('business_id', $businessId);
            $stockQuery = $this->scopeBranch($stockQuery, $request);
            if ($fridgeIds !== []) {
                $stockQuery->whereIn('fridge_id', $fridgeIds);
            } else {
                $stockQuery->whereRaw('id = ?', ['00000000-0000-0000-0000-000000000000']);
            }
            $stockRows = $stockQuery->get()->toArray();

            $products = $this->businessProducts($request, true);
            $productById = [];
            foreach ($products as $product) {
                $productById[(string) $product['id']] = $product;
            }

            $rowsByFridge = [];
            $insideByProduct = [];
            $fridgeById = [];
            foreach ($fridges as $fridge) {
                $fridgeById[(string) $fridge['id']] = $fridge;
            }
            foreach ($stockRows as $row) {
                $fid = (string) ($row['fridge_id'] ?? '');
                $pid = (string) ($row['product_id'] ?? '');
                $rowsByFridge[$fid][] = $row;
                if (($fridgeById[$fid]['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $insideByProduct[$pid] = ($insideByProduct[$pid] ?? 0.0) + (float) ($row['quantity'] ?? 0);
            }

            $alerts = FridgeAlertService::sync($businessId, $products, array_values(array_filter(
                $fridges,
                fn ($f) => ($f['status'] ?? 'active') === 'active'
            )), $stockRows);

            // Notification preferences control what is PRESENTED, never whether
            // monitoring runs: sync() above still opens/resolves every history
            // row. A disabled family is simply not surfaced in this response.
            $notificationPrefs = \App\Services\BusinessSettings::forBusiness($businessId);
            if (! $notificationPrefs['notify_low_stock']) {
                $alerts = array_values(array_filter(
                    $alerts,
                    fn ($alert) => ! in_array($alert['alert_type'] ?? '', ['business_low', 'outside_low'], true)
                ));
            }
            if (! $notificationPrefs['notify_fridge_stock']) {
                $alerts = array_values(array_filter(
                    $alerts,
                    fn ($alert) => ($alert['alert_type'] ?? '') !== 'fridge_low'
                ));
            }

            $fridgeCards = [];
            foreach ($fridges as $fridge) {
                $fid = (string) $fridge['id'];
                $rows = $rowsByFridge[$fid] ?? [];
                $units = 0.0;
                $productCount = 0;
                $lowCount = 0;
                foreach ($rows as $row) {
                    $qty = (float) ($row['quantity'] ?? 0);
                    $units += $qty;
                    if ($qty > 0) {
                        $productCount++;
                    }
                    if ((float) ($row['min_stock'] ?? 0) > 0 && $qty <= (float) $row['min_stock']) {
                        $lowCount++;
                    }
                }

                $fridgeCards[] = array_merge($fridge, [
                    'total_units' => round($units, 3),
                    'product_count' => $productCount,
                    'low_stock_count' => $lowCount,
                ]);
            }

            $businessUnits = 0.0;
            foreach ($products as $product) {
                $businessUnits += (float) ($product['stock'] ?? 0);
            }
            $insideUnits = 0.0;
            foreach ($insideByProduct as $qty) {
                $insideUnits += (float) $qty;
            }

            $counts = ['business_low' => 0, 'outside_low' => 0, 'fridge_low' => 0];
            foreach ($alerts as $alert) {
                $type = (string) ($alert['alert_type'] ?? '');
                if (isset($counts[$type])) {
                    $counts[$type]++;
                }
            }

            $movementQuery = FridgeStockMovement::where('business_id', $businessId);
            $movementQuery = $this->scopeBranch($movementQuery, $request);
            $movements = $movementQuery->orderByDesc('created_at')->limit(25)->get()->toArray();

            return $this->json([
                'success' => true,
                'fridges' => $fridgeCards,
                'summary' => [
                    'business_units' => round($businessUnits, 3),
                    'inside_units' => round($insideUnits, 3),
                    'outside_units' => round($businessUnits - $insideUnits, 3),
                    'fridge_count' => count(array_filter($fridges, fn ($f) => ($f['status'] ?? 'active') === 'active')),
                    'product_count' => count($products),
                ],
                'alerts' => $alerts,
                'alert_counts' => $counts,
                'recent_movements' => $this->enrichMovements($movements, $fridgeById, $productById),
            ]);
        } catch (\Throwable $e) {
            return $this->dbError($e, 'Imeshindikana kupakia taarifa za friji');
        }
    }

    public function store(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        try {
            $businessId = $this->businessId($request);
            if (! $businessId) {
                return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
            }

            [$writeBranchId, $branchError] = $this->requireWriteBranch($request);
            if ($branchError) {
                return $branchError;
            }

            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                return $this->json(['success' => false, 'error' => 'Jina la friji linahitajika', 'code' => 'NAME_REQUIRED'], 422);
            }
            if (mb_strlen($name) > 80) {
                return $this->json(['success' => false, 'error' => 'Jina la friji ni refu mno', 'code' => 'NAME_TOO_LONG'], 422);
            }

            $existing = Fridge::where('business_id', $businessId)
                ->where('status', 'active')
                ->get()
                ->toArray();
            foreach ($existing as $row) {
                if ((string) ($row['branch_id'] ?? '') === (string) ($writeBranchId ?? '')
                    && mb_strtolower(trim((string) $row['name'])) === mb_strtolower($name)) {
                    return $this->json(['success' => false, 'error' => 'Friji yenye jina hili ipo tayari', 'code' => 'DUPLICATE_NAME'], 422);
                }
            }

            $now = $this->isoNow();
            $row = Fridge::create([
                'id' => (string) Str::uuid(),
                'business_id' => $businessId,
                'branch_id' => $writeBranchId,
                'name' => $name,
                'status' => 'active',
                'created_by' => $this->userId($request),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->json(['success' => true, 'fridge' => $row->toArray()], 201);
        } catch (\Throwable $e) {
            return $this->dbError($e, 'Imeshindikana kuongeza friji');
        }
    }

    public function update(Request $request, $id)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        try {
            [$fridge, $error] = $this->findFridge($request, $id);
            if ($error) {
                return $error;
            }

            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                return $this->json(['success' => false, 'error' => 'Jina la friji linahitajika', 'code' => 'NAME_REQUIRED'], 422);
            }

            $existing = Fridge::where('business_id', $this->businessId($request))
                ->where('status', 'active')
                ->get()
                ->toArray();
            foreach ($existing as $row) {
                if ((string) $row['id'] === (string) $fridge['id']) {
                    continue;
                }
                if ((string) ($row['branch_id'] ?? '') === (string) ($fridge['branch_id'] ?? '')
                    && mb_strtolower(trim((string) $row['name'])) === mb_strtolower($name)) {
                    return $this->json(['success' => false, 'error' => 'Friji yenye jina hili ipo tayari', 'code' => 'DUPLICATE_NAME'], 422);
                }
            }

            Fridge::where('id', $fridge['id'])->update([
                'name' => $name,
                'updated_at' => $this->isoNow(),
            ]);

            return $this->json(['success' => true, 'fridge' => array_merge($fridge, ['name' => $name])]);
        } catch (\Throwable $e) {
            return $this->dbError($e, 'Imeshindikana kubadilisha friji');
        }
    }

    public function destroy(Request $request, $id)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        try {
            [$fridge, $error] = $this->findFridge($request, $id);
            if ($error) {
                return $error;
            }

            $rows = FridgeStock::where('fridge_id', $fridge['id'])
                ->where('business_id', $this->businessId($request))
                ->get();

            // Deleting a fridge never discards stock: everything still inside is
            // returned to outside stock (products.stock), and each product gets
            // an audit movement so the history stays balanced.
            $dumped = 0.0;
            foreach ($rows as $row) {
                $qty = (float) ($row->quantity ?? 0);
                $productId = (string) ($row->product_id ?? '');
                if ($qty <= 0.0005) {
                    continue;
                }

                $product = Product::where('id', $productId)->first();
                $currentStock = (float) ($product->stock ?? 0);
                Product::where('id', $productId)->update([
                    'stock' => $currentStock + $qty,
                    'updated_at' => $this->isoNow(),
                ]);

                FridgeStockMovement::create([
                    'business_id' => $fridge['business_id'],
                    'branch_id' => $fridge['branch_id'] ?? null,
                    'fridge_id' => $fridge['id'],
                    'product_id' => $productId,
                    'movement_type' => 'transfer_out',
                    'quantity' => $qty,
                    'source_location' => 'fridge',
                    'destination_location' => 'outside',
                    'reference_type' => 'fridge',
                    'reference_id' => $fridge['id'],
                    'reason' => 'fridge_deleted',
                    'notes' => 'Stock ilirudishwa nje kwa sababu friji ilifutwa',
                    'created_by' => $this->userId($request),
                    'created_at' => $this->isoNow(),
                ]);

                $dumped += $qty;
            }

            // Remove all stock rows for this fridge, then archive it.
            FridgeStock::where('fridge_id', $fridge['id'])
                ->where('business_id', $this->businessId($request))
                ->delete();

            Fridge::where('id', $fridge['id'])->update([
                'status' => 'archived',
                'updated_at' => $this->isoNow(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Friji imefutwa na stock yake imehamishwa nje',
                'dumped_units' => round($dumped, 3),
            ]);
        } catch (\Throwable $e) {
            return $this->dbError($e, 'Imeshindikana kufuta friji');
        }
    }

    public function show(Request $request, $id)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        try {
            [$fridge, $error] = $this->findFridge($request, $id);
            if ($error) {
                return $error;
            }

            $products = $this->businessProducts($request, true);
            $productById = [];
            foreach ($products as $product) {
                $productById[(string) $product['id']] = $product;
            }

            $stockRows = FridgeStock::where('fridge_id', $fridge['id'])
                ->where('business_id', $this->businessId($request))
                ->get()
                ->toArray();

            $stock = [];
            foreach ($stockRows as $row) {
                $product = $productById[(string) ($row['product_id'] ?? '')] ?? null;
                $stock[] = array_merge($row, [
                    'product_name' => $product['name'] ?? null,
                    'category' => $product['category'] ?? null,
                    'selling_price' => $product['expected_selling_price'] ?? null,
                    'business_stock' => (float) ($product['stock'] ?? 0),
                ]);
            }

            $movementQuery = FridgeStockMovement::where('fridge_id', $fridge['id'])
                ->where('business_id', $this->businessId($request));
            $movements = $movementQuery->orderByDesc('created_at')->limit(40)->get()->toArray();

            $fridgeById = [(string) $fridge['id'] => $fridge];
            $allFridgeStock = FridgeStock::where('business_id', $this->businessId($request))
                ->where('fridge_id', $fridge['id'])
                ->get()
                ->toArray();
            $alerts = FridgeAlertService::sync(
                $this->businessId($request),
                $products,
                [$fridge],
                $allFridgeStock
            );

            return $this->json([
                'success' => true,
                'fridge' => $fridge,
                'stock' => $stock,
                'movements' => $this->enrichMovements($movements, $fridgeById, $productById),
                'alerts' => $alerts,
            ]);
        } catch (\Throwable $e) {
            return $this->dbError($e, 'Imeshindikana kupakia friji');
        }
    }

    public function movements(Request $request, $id)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        try {
            [$fridge, $error] = $this->findFridge($request, $id);
            if ($error) {
                return $error;
            }

            $limit = min(200, max(1, (int) $request->query('limit', 50)));
            $offset = max(0, (int) $request->query('offset', 0));

            $query = FridgeStockMovement::where('fridge_id', $fridge['id'])
                ->where('business_id', $this->businessId($request));
            $rows = $query->orderByDesc('created_at')->limit($limit)->get()->toArray();

            return $this->json([
                'success' => true,
                'movements' => $this->enrichMovements($rows, [(string) $fridge['id'] => $fridge], $this->productMap($request)),
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (\Throwable $e) {
            return $this->dbError($e, 'Imeshindikana kupakia historia ya friji');
        }
    }

    public function addStock(Request $request, $id)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        return $this->transfer($request, $id, 'in');
    }

    public function removeStock(Request $request, $id)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        return $this->transfer($request, $id, 'out');
    }

    public function setThreshold(Request $request, $id, $productId)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        try {
            [$fridge, $error] = $this->findFridge($request, $id);
            if ($error) {
                return $error;
            }

            $minStock = $request->input('min_stock');
            if (! is_numeric($minStock) || (float) $minStock < 0) {
                return $this->json(['success' => false, 'error' => 'Kiwango cha chini si sahihi', 'code' => 'INVALID_THRESHOLD'], 422);
            }

            $row = FridgeStock::where('fridge_id', $fridge['id'])
                ->where('product_id', $productId)
                ->where('business_id', $this->businessId($request))
                ->first();
            if (! $row) {
                return $this->json(['success' => false, 'error' => 'Bidhaa haipo kwenye friji hii', 'code' => 'NOT_FOUND'], 404);
            }

            Supabase::callFunction('fridge_set_threshold', [
                'p_fridge_id' => $fridge['id'],
                'p_product_id' => $productId,
                'p_min_stock' => (float) $minStock,
                'p_business_id' => $this->businessId($request),
                'p_branch_id' => $fridge['branch_id'] ?? null,
            ]);

            return $this->json(['success' => true, 'min_stock' => (float) $minStock]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'THRESHOLD_FAILED',
            ], 422);
        }
    }

    public function setOutsideThreshold(Request $request, $productId)
    {
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        try {
            $product = $this->findProduct($request, $productId);
            if (! $product) {
                return $this->json(['success' => false, 'error' => 'Bidhaa haipatikani', 'code' => 'NOT_FOUND'], 404);
            }

            $minStock = $request->input('min_outside_stock');
            if (! is_numeric($minStock) || (float) $minStock < 0) {
                return $this->json(['success' => false, 'error' => 'Kiwango cha chini si sahihi', 'code' => 'INVALID_THRESHOLD'], 422);
            }

            Product::where('id', $productId)->update([
                'min_outside_stock' => (float) $minStock,
                'updated_at' => $this->isoNow(),
            ]);

            return $this->json(['success' => true, 'min_outside_stock' => (float) $minStock]);
        } catch (\Throwable $e) {
            return $this->dbError($e, 'Imeshindikana kuhifadhi kiwango cha nje');
        }
    }

    public function availability(Request $request)
    {
        // Selling must keep working when the fridge module is off. Treating
        // "disabled" as "no inside stock" lets the POS sell from outside
        // stock instead of failing or silently allocating fridge quantities
        // the seller can no longer see or manage.
        if (! \App\Services\BusinessSettings::enabled('fridge', $this->businessId($request))) {
            return $this->json(['success' => true, 'features' => ['fridge' => false], 'products' => []]);
        }

        try {
            $businessId = $this->businessId($request);
            if (! $businessId) {
                return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
            }

            $rawIds = $request->query('product_ids', '');
            $requested = [];
            if (is_array($rawIds)) {
                $requested = $rawIds;
            } elseif (is_string($rawIds) && trim($rawIds) !== '') {
                $requested = array_filter(array_map('trim', explode(',', $rawIds)));
            }

            $products = $this->businessProducts($request, true);
            if ($requested !== []) {
                $wanted = array_fill_keys(array_map('strval', $requested), true);
                $products = array_values(array_filter($products, function ($product) use ($wanted) {
                    return isset($wanted[(string) ($product['id'] ?? '')]);
                }));
            }

            $fridgeQuery = Fridge::where('business_id', $businessId)->where('status', 'active');
            $fridgeQuery = $this->scopeBranch($fridgeQuery, $request);
            $fridges = $fridgeQuery->orderBy('name')->get()->toArray();
            $fridgeIds = array_column($fridges, 'id');

            $stockRows = [];
            if ($fridgeIds !== []) {
                $stockQuery = FridgeStock::where('business_id', $businessId)->whereIn('fridge_id', $fridgeIds);
                $stockRows = $stockQuery->get()->toArray();
            }

            $fridgeById = [];
            foreach ($fridges as $fridge) {
                $fridgeById[(string) $fridge['id']] = $fridge;
            }
            $stockByProduct = [];
            foreach ($stockRows as $row) {
                $stockByProduct[(string) ($row['product_id'] ?? '')][] = $row;
            }

            $out = [];
            foreach ($products as $product) {
                $pid = (string) ($product['id'] ?? '');
                $rows = $stockByProduct[$pid] ?? [];
                $inside = 0.0;
                $fridgeList = [];
                foreach ($rows as $row) {
                    $qty = (float) ($row['quantity'] ?? 0);
                    $inside += $qty;
                    if ($qty > 0) {
                        $fridgeList[] = [
                            'fridge_id' => $row['fridge_id'],
                            'name' => $fridgeById[(string) $row['fridge_id']]['name'] ?? 'Friji',
                            'quantity' => round($qty, 3),
                            'min_stock' => round((float) ($row['min_stock'] ?? 0), 3),
                        ];
                    }
                }
                usort($fridgeList, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

                $stock = (float) ($product['stock'] ?? 0);
                $out[] = [
                    'product_id' => $pid,
                    'name' => $product['name'] ?? null,
                    'stock' => round($stock, 3),
                    'inside_stock' => round($inside, 3),
                    'outside_stock' => round(FridgeStockMath::outsideStock($stock, [$inside]), 3),
                    'fridges' => $fridgeList,
                ];
            }

            return $this->json(['success' => true, 'products' => $out]);
        } catch (\Throwable $e) {
            return $this->dbError($e, 'Imeshindikana kupakia stock ya friji');
        }
    }

    /* ------------------------------------------------------------------ */

    private function transfer(Request $request, $id, string $direction)
    {
        try {
            [$fridge, $error] = $this->findFridge($request, $id);
            if ($error) {
                return $error;
            }

            $productId = (string) $request->input('product_id', '');
            $quantity = $request->input('quantity');
            if ($productId === '' || ! is_numeric($quantity) || (float) $quantity <= 0) {
                return $this->json(['success' => false, 'error' => 'Bidhaa na kiasi sahihi vinahitajika', 'code' => 'INVALID_TRANSFER'], 422);
            }

            $product = $this->findProduct($request, $productId);
            if (! $product) {
                return $this->json(['success' => false, 'error' => 'Bidhaa haipatikani', 'code' => 'PRODUCT_NOT_FOUND'], 404);
            }
            if (($fridge['branch_id'] ?? null) !== null
                && (string) ($product['branch_id'] ?? '') !== (string) $fridge['branch_id']) {
                return $this->json(['success' => false, 'error' => 'Bidhaa ni ya tawi lingine', 'code' => 'BRANCH_MISMATCH'], 422);
            }

            $params = [
                'p_fridge_id' => $fridge['id'],
                'p_product_id' => $productId,
                'p_quantity' => (float) $quantity,
                'p_business_id' => $this->businessId($request),
                'p_branch_id' => $fridge['branch_id'] ?? null,
                'p_user_id' => $this->userId($request),
                'p_notes' => (string) $request->input('notes', ''),
            ];

            if ($direction === 'in') {
                $minStock = $request->input('min_stock');
                $params['p_min_stock'] = is_numeric($minStock) ? max(0, (float) $minStock) : 0;
                $result = Supabase::callFunction('fridge_transfer_in', $params);

                // Older deployments of the function inserted 0 and reported
                // success. Refuse that silent no-op instead of telling the
                // seller the stock was moved when the fridge is still empty.
                $payload = is_array($result) && isset($result[0]) && is_array($result[0]) ? $result[0] : $result;
                $fridgeQuantity = is_array($payload) ? ($payload['fridge_quantity'] ?? null) : null;
                if (! is_numeric($fridgeQuantity) || (float) $fridgeQuantity < (float) $quantity) {
                    throw new \RuntimeException(
                        'Stock haijaingizwa kwenye friji: database ina toleo la zamani la fridge_transfer_in. Msimamizi aendeshe migration 20261114000005_fix_fridge_transfer_in_quantity.sql kwenye Supabase SQL Editor.'
                    );
                }
            } else {
                $result = Supabase::callFunction('fridge_transfer_out', $params);
            }

            return $this->json(['success' => true, 'result' => $result]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'TRANSFER_FAILED',
            ], 422);
        }
    }

    private function findFridge(Request $request, $id): array
    {
        $businessId = $this->businessId($request);
        if (! $businessId) {
            return [null, $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400)];
        }

        $query = Fridge::where('id', $id)->where('business_id', $businessId);
        $query = $this->scopeBranch($query, $request);
        $fridge = $query->first();
        if (! $fridge) {
            return [null, $this->json(['success' => false, 'error' => 'Friji haipatikani', 'code' => 'NOT_FOUND'], 404)];
        }

        return [$fridge->toArray(), null];
    }

    private function findProduct(Request $request, string $productId): ?array
    {
        foreach ($this->businessProducts($request, true) as $product) {
            if ((string) ($product['id'] ?? '') === $productId) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function businessProducts(Request $request, bool $activeOnly = true): array
    {
        $userId = $this->userId($request);
        $businessId = $this->businessId($request);

        $sellerIds = [];
        if ($businessId) {
            $sellerIds = BusinessResolver::memberIds($businessId, Roles::BUSINESS_MEMBERS, 'approved');
        }
        if ($sellerIds === [] && $userId) {
            $sellerIds = [$userId];
        }
        if ($sellerIds === []) {
            return [];
        }

        $query = Product::whereIn('seller_id', $sellerIds);
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        $query = $this->scopeBranch($query, $request);

        return $query->select([
            'id', 'name', 'category', 'stock', 'price', 'cost_price',
            'expected_selling_price', 'min_stock_level', 'min_outside_stock',
            'seller_id', 'branch_id', 'is_active', 'created_at', 'updated_at',
        ])->orderBy('name')->get()->toArray();
    }

    private function productMap(Request $request): array
    {
        $map = [];
        foreach ($this->businessProducts($request, false) as $product) {
            $map[(string) $product['id']] = $product;
        }

        return $map;
    }

    private function enrichMovements(array $movements, array $fridgeById, array $productById): array
    {
        $out = [];
        foreach ($movements as $movement) {
            $fid = (string) ($movement['fridge_id'] ?? '');
            $pid = (string) ($movement['product_id'] ?? '');
            $out[] = array_merge($movement, [
                'fridge_name' => $fridgeById[$fid]['name'] ?? null,
                'product_name' => $productById[$pid]['name'] ?? null,
            ]);
        }

        return $out;
    }
}
