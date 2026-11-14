<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\User;
use App\Services\BusinessResolver;
use App\Services\Roles;
use App\Services\StockReceiving;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends BaseController
{
    public function my(Request $request)
    {
        try {
            $userId = $this->userId($request);
            $userRole = $this->role($request);
            $businessName = $this->businessName($request);

            $this->touchLastSeen($userId);

            if ($userRole === 'admin') {
                $productsQuery = Product::where('seller_id', $userId)
                    ->where('is_active', true);
                $productsQuery = $this->scopeBranch($productsQuery, $request);
                $products = $productsQuery
                    ->select(['id', 'name', 'category', 'description', 'price', 'expected_selling_price', 'cost_price', 'stock', 'barcode', 'is_active', 'seller_id', 'created_at', 'updated_at'])
                    ->orderBy('name')
                    ->get()
                    ->toArray();

                // Do NOT invent a selling price. `price` is the buying price,
                // so defaulting expected_selling_price to it reported a profit
                // of exactly 0 for every product that has no selling price
                // yet. Leave it null so the report can say "unknown".
                $processedProducts = $products;

                $this->log($userId, 'PRODUCTS_VIEW', '/api/products/my', [
                    'count' => count($processedProducts),
                    'role' => 'admin',
                    'personal' => true,
                ], $this->ip($request), 'success');

                return $this->json($processedProducts);
            }

            // A business keeps its data even after its admin is promoted to a
            // platform admin (super_admin / system_admin): the owner-role check
            // and the member scope below accept those spellings too, so changing
            // X's role never blanks the seller dashboard for their team.
            $adminUser = User::where('business_name', $businessName)
                ->whereIn('role', Roles::BUSINESS_OWNERS)
                ->where('status', 'approved')
                ->select(['id'])
                ->first();

            if (!$adminUser) {
                return $this->json([]);
            }

            $businessUsers = User::where('business_name', $businessName)
                ->whereIn('role', Roles::BUSINESS_MEMBERS)
                ->where('status', 'approved')
                ->select(['id', 'email', 'full_name', 'role', 'status'])
                ->get()
                ->toArray();

            $sellerIds = array_column($businessUsers, 'id');

            if (count($sellerIds) === 0) {
                return $this->json([]);
            }

            $allProductsQuery = Product::whereIn('seller_id', $sellerIds)
                ->where('is_active', true)
                ->select(['id', 'name', 'category', 'description', 'price', 'expected_selling_price', 'cost_price', 'stock', 'barcode', 'is_active', 'seller_id', 'branch_id', 'created_at', 'updated_at']);
            $allProductsQuery = $this->scopeBranch($allProductsQuery, $request);
            $allProducts = $allProductsQuery
                ->orderBy('name')
                ->get()
                ->toArray();

            // See the note in my(): never derive a selling price from the
            // buying price. A missing selling price stays missing.
            $processedProducts = $allProducts;

            $productsWithSellerInfo = array_map(function ($product) use ($businessUsers) {
                $seller = null;
                foreach ($businessUsers as $bu) {
                    if ($bu['id'] === $product['seller_id']) {
                        $seller = $bu;
                        break;
                    }
                }

                $seller_name = 'Unknown';
                if ($seller) {
                    if ($seller['full_name']) {
                        $seller_name = $seller['full_name'];
                    } elseif ($seller['email']) {
                        $seller_name = explode('@', $seller['email'])[0] ?: $seller['email'];
                    }
                }

                $product['seller_name'] = $seller_name;
                $product['seller_role'] = $seller['role'] ?? 'unknown';
                $product['seller_email'] = $seller['email'] ?? null;
                return $product;
            }, $processedProducts);

            $this->log($userId, 'PRODUCTS_VIEW', '/api/products/my', [
                'count' => count($productsWithSellerInfo),
                'role' => 'seller',
                'business' => $businessName,
                'total_sellers' => count($businessUsers),
            ], $this->ip($request), 'success');

            return $this->json($productsWithSellerInfo);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'PRODUCTS_VIEW_ERROR', '/api/products/my', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json([
                'error' => 'Hitilafu ya ndani ya server',
                'details' => $e->getMessage(),
                'code' => 'PRODUCTS_FETCH_ERROR',
            ], 500);
        }
    }

    public function dummy(Request $request)
    {
        try {
            $userId = $this->userId($request);
            $userRole = $this->role($request);

            $this->touchLastSeen($userId);

            $now = $this->isoNow();

            $dummyProducts = [
                [
                    'id' => 'prod-001',
                    'name' => 'Maji ya Kunywa',
                    'category' => 'Vinywaji',
                    'description' => 'Maji safi ya kunywa',
                    'price' => 1500,
                    'expected_selling_price' => 2000,
                    'cost_price' => 1000,
                    'stock' => 50,
                    'is_active' => true,
                    'seller_id' => $userId,
                    'seller_name' => $userRole === 'admin' ? 'Admin' : 'Muuzaji',
                    'seller_role' => $userRole,
                    'seller_email' => $this->user($request)->email,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => 'prod-002',
                    'name' => 'Sukari',
                    'category' => 'Chakula',
                    'description' => 'Sukari nyeupe',
                    'price' => 3000,
                    'expected_selling_price' => 3500,
                    'cost_price' => 2500,
                    'stock' => 30,
                    'is_active' => true,
                    'seller_id' => $userId,
                    'seller_name' => $userRole === 'admin' ? 'Admin' : 'Muuzaji',
                    'seller_role' => $userRole,
                    'seller_email' => $this->user($request)->email,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => 'prod-003',
                    'name' => 'Mafuta ya Kupikia',
                    'category' => 'Chakula',
                    'description' => 'Mafuta ya alizeti',
                    'price' => 5000,
                    'expected_selling_price' => 6000,
                    'cost_price' => 4500,
                    'stock' => 20,
                    'is_active' => true,
                    'seller_id' => $userId,
                    'seller_name' => $userRole === 'admin' ? 'Admin' : 'Muuzaji',
                    'seller_role' => $userRole,
                    'seller_email' => $this->user($request)->email,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];

            $this->log($userId, 'DUMMY_PRODUCTS', '/api/products/dummy', [
                'count' => count($dummyProducts),
            ], $this->ip($request), 'success');

            return $this->json($dummyProducts);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'DUMMY_PRODUCTS_ERROR', '/api/products/dummy', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json([
                'error' => 'Hitilafu ya ndani ya server',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Product registration and initial inventory are administrator work by
        // default. A seller may only do it when the business has explicitly
        // enabled "seller can manage products stock".
        if (! $this->canManageProducts($request)) {
            return $this->json([
                'success' => false,
                'error' => 'Ni msimamizi pekee anayeweza kusajili bidhaa na kuongeza stock.',
                'code' => 'PRODUCT_MANAGEMENT_FORBIDDEN',
            ], 403);
        }

        try {
            $userId = $this->userId($request);
            $name = $request->input('name');
            $category = $request->input('category');
            $price = $request->input('price');
            $stock = $request->input('stock');
            $description = $request->input('description');
            $expected_selling_price = $request->input('expected_selling_price');
            // Optional product photo. Stored as a URL (Cloudinary). Not part
            // of the required-field check: a product may be saved without one.
            $image_url = $request->input('image_url');
            $barcode = $this->normalizeBarcode($request->input('barcode'));
            if ($barcode !== null && mb_strlen($barcode) > 120) {
                return $this->json(['success' => false, 'error' => 'Barcode ni ndefu mno', 'code' => 'BARCODE_TOO_LONG'], 422);
            }

            [$writeBranchId, $branchError] = $this->requireWriteBranch($request);
            if ($branchError) {
                return $branchError;
            }

            $this->touchLastSeen($userId);

            if (!$name || !$category || !$price || $stock === null || $stock === '' || !$expected_selling_price) {
                $this->log($userId, 'PRODUCT_CREATE_FAILED', '/api/products', [
                    'reason' => 'Missing required fields',
                    'missing_fields' => [
                        'name' => !$name,
                        'category' => !$category,
                        'price' => !$price,
                        'expected_selling_price' => !$expected_selling_price,
                        'stock' => !$stock,
                    ],
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Jina, kategoria, bei ya sasa, bei ya kuuzia na idadi ya bidhaa zinahitajika',
                ], 400);
            }

            $priceValue = (float) $price;
            $expectedPriceValue = (float) $expected_selling_price;
            $stockValue = (int) $stock;

            if (is_nan((float) $priceValue) || $priceValue <= 0) {
                $this->log($userId, 'PRODUCT_CREATE_FAILED', '/api/products', [
                    'reason' => 'Invalid price',
                    'price' => $price,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Bei ya sasa si sahihi (lazima iwe namba kubwa kuliko 0)',
                ], 400);
            }

            if ($expectedPriceValue <= 0) {
                $this->log($userId, 'PRODUCT_CREATE_FAILED', '/api/products', [
                    'reason' => 'Invalid expected selling price',
                    'expected_selling_price' => $expected_selling_price,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Bei ya kuuzia si sahihi (lazima iwe namba kubwa kuliko 0)',
                ], 400);
            }

            if (!is_numeric($stock) || (float) $stock != (int) $stock || $stockValue < 0) {
                $this->log($userId, 'PRODUCT_CREATE_FAILED', '/api/products', [
                    'reason' => 'Invalid stock',
                    'stock' => $stock,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Idadi ya bidhaa si sahihi (lazima iwe namba isiyo hasi)',
                ], 400);
            }

            if ($barcode !== null) {
                if ($conflict = $this->barcodeConflict($request, $barcode)) {
                    return $conflict;
                }
            }

            $now = $this->isoNow();
            $productData = [
                'seller_id' => $userId,
                'name' => trim((string) $name),
                'description' => trim((string) ($description ?? '')),
                'category' => trim((string) ($category ?? '')),
                'price' => $priceValue,
                'expected_selling_price' => $expectedPriceValue,
                // Stock is never accepted as a side effect of registration.
                // It is created by the receiving RPC below, which writes the
                // ledger row and re-checks this administrator's role in SQL.
                'stock' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($barcode !== null) {
                $productData['barcode'] = $barcode;
            }

            if ($image_url !== null && trim((string) $image_url) !== '') {
                $productData['image_url'] = trim((string) $image_url);
            }

            // Branch ownership is stamped from the verified context, never
            // from the request body.
            $productData['branch_id'] = $writeBranchId;

            $newProductId = Str::uuid()->toString();

            try {
                Product::create(array_merge($productData, ['id' => $newProductId]));
            } catch (QueryException $e) {
                if ($e->getCode() === '23505') {
                    $this->log($userId, 'PRODUCT_CREATE_FAILED', '/api/products', [
                        'reason' => 'Duplicate product name',
                        'name' => $name,
                    ], $this->ip($request), 'failed');

                    return $this->json([
                        'success' => false,
                        'error' => 'Bidhaa na jina hili tayari ipo. Tafadhali tumia jina tofauti.',
                    ], 400);
                }
                throw $e;
            }

            $initialReceipt = null;
            if ($stockValue > 0) {
                try {
                    $initialReceipt = StockReceiving::receive(
                        (string) $this->businessId($request),
                        $writeBranchId,
                        $newProductId,
                        $stockValue,
                        (string) $userId,
                        'manual',
                        null,
                        'Initial stock at product registration'
                    );
                } catch (\Throwable $receiptError) {
                    // Never leave a product that appears stocked but has no
                    // receipt. The product was created at 0, so deactivating
                    // it is safe and the administrator can retry.
                    Product::where('id', $newProductId)->update([
                        'is_active' => false,
                        'updated_at' => $this->isoNow(),
                    ]);

                    $this->log($userId, 'PRODUCT_CREATE_STOCK_FAILED', '/api/products', [
                        'product_id' => $newProductId,
                        'error' => $receiptError->getMessage(),
                    ], $this->ip($request), 'failed');

                    if (StockReceiving::isUnavailable($receiptError)) {
                        return $this->json([
                            'success' => false,
                            'error' => 'Mfumo wa kupokea stock haujasakinishwa. Wasiliana na msimamizi wa mfumo.',
                            'code' => 'STOCK_RECEIVING_NOT_INSTALLED',
                        ], 503);
                    }

                    return $this->json([
                        'success' => false,
                        'error' => 'Bidhaa imesajiliwa lakini stock haikupokelewa: ' . $receiptError->getMessage(),
                        'code' => 'STOCK_RECEIPT_FAILED',
                    ], 422);
                }
            }

            $newProduct = Product::where('id', $newProductId)
                ->select(['id', 'name', 'category', 'description', 'price', 'expected_selling_price', 'cost_price', 'stock', 'barcode', 'is_active', 'seller_id', 'image_url', 'created_at', 'updated_at'])
                ->first()
                ->toArray();

            $this->log($userId, 'PRODUCT_CREATE', '/api/products', [
                'product_id' => $newProduct['id'],
                'name' => $newProduct['name'],
                'price' => $newProduct['price'],
                'expected_selling_price' => $newProduct['expected_selling_price'],
                'stock' => $newProduct['stock'],
                'stock_receipt' => $initialReceipt,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Bidhaa imeongezwa kikamilifu! Bei ya kuuzia imewekwa.',
                'product' => $newProduct,
                'stock_receipt' => $initialReceipt,
            ], 201);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'PRODUCT_CREATE_ERROR', '/api/products', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function update(Request $request, $id)
    {
        // Sellers must never change the catalogue or its stock. They may only
        // sell existing products and transfer existing stock.
        if (! $this->canManageProducts($request)) {
            return $this->json([
                'success' => false,
                'error' => 'Ni msimamizi pekee anayeweza kubadilisha bidhaa au stock.',
                'code' => 'PRODUCT_MANAGEMENT_FORBIDDEN',
            ], 403);
        }

        try {
            $productId = $id;
            $userId = $this->userId($request);
            [$writeBranchId, $branchError] = $this->requireWriteBranch($request);
            if ($branchError) {
                return $branchError;
            }
            $name = $request->input('name');
            $category = $request->input('category');
            $price = $request->input('price');
            $stock = $request->input('stock');
            $description = $request->input('description');
            $expected_selling_price = $request->input('expected_selling_price');
            $image_url = $request->input('image_url');
            $barcode = $request->has('barcode') ? $this->normalizeBarcode($request->input('barcode')) : null;
            if ($request->has('barcode') && $barcode !== null && mb_strlen($barcode) > 120) {
                return $this->json(['success' => false, 'error' => 'Barcode ni ndefu mno', 'code' => 'BARCODE_TOO_LONG'], 422);
            }

            $this->touchLastSeen($userId);

            // A partial update (e.g. the "add stock" button) only sends
            // name/category/stock. Requiring the selling price there forced
            // the client to send buying price as a stand-in, which silently
            // set selling == buying. Only validate it when it is supplied.
            $hasExpected = $request->has('expected_selling_price');
            if ($hasExpected && ($expected_selling_price === null || $expected_selling_price === '')) {
                $hasExpected = false;
            }

            // An administrator manages the whole business catalogue, not only
            // rows they personally registered. This helper enforces business
            // membership and branch scope before anything is written.
            $product = $this->findBusinessProduct($request, (string) $productId);

            if (!$product) {
                $this->log($userId, 'PRODUCT_UPDATE_FAILED', '/api/products/:id', [
                    'reason' => 'Product not found',
                    'product_id' => $productId,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Bidhaa haipo',
                ], 404);
            }

            if ($request->has('stock')) {
                if (! is_numeric($stock) || (float) $stock != (int) $stock) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Idadi ya bidhaa si sahihi (lazima iwe namba nzima)',
                        'code' => 'INVALID_STOCK',
                    ], 422);
                }

                if ((int) $stock !== (int) ($product['stock'] ?? 0)) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Stock haiwezi kubadilishwa moja kwa moja. Tumia kupokea stock ili kuhifadhi kumbukumbu.',
                        'code' => 'STOCK_RECEIPT_REQUIRED',
                    ], 422);
                }
            }

            if ($barcode !== null) {
                if ($conflict = $this->barcodeConflict($request, $barcode, $productId)) {
                    return $conflict;
                }
            }

            $expectedPriceValue = $hasExpected ? (float) $expected_selling_price : null;

            if ($hasExpected && $expectedPriceValue <= 0) {
                $this->log($userId, 'PRODUCT_UPDATE_FAILED', '/api/products/:id', [
                    'reason' => 'Invalid expected selling price',
                    'product_id' => $productId,
                    'expected_selling_price' => $expected_selling_price,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Bei ya kuuzia si sahihi (lazima iwe namba kubwa kuliko 0)',
                ], 400);
            }

            $updateData = ['updated_at' => $this->isoNow()];

            // A partial update must never blank fields the client did not send.
            // Stock is intentionally absent: it only changes through the
            // authorized receiving endpoint.
            if ($request->has('name')) {
                $trimmedName = trim((string) $name);
                if ($trimmedName === '') {
                    return $this->json(['success' => false, 'error' => 'Jina la bidhaa linahitajika'], 422);
                }
                $updateData['name'] = $trimmedName;
            }
            if ($request->has('category')) {
                $updateData['category'] = trim((string) ($category ?? ''));
            }
            if ($request->has('description')) {
                $updateData['description'] = trim((string) ($description ?? ''));
            }
            if ($request->has('barcode')) {
                $updateData['barcode'] = $barcode;
            }

            // Selling price is written only when supplied, so an add-stock
            // call cannot invent one. An explicit null clears it.
            if ($hasExpected) {
                $updateData['expected_selling_price'] = $expectedPriceValue;
            }

            // `price` IS the buying price ("Bei ya Kununua"). It must only be
            // written when the request carries the key. The previous
            // unconditional `'price' => (float) $price` turned a client that
            // omitted the field into `(float) null`, i.e. 0, which silently
            // destroyed the buying price and made every product look free.
            if ($request->has('price')) {
                $priceValue = $price === null || $price === '' ? null : (float) $price;
                if ($priceValue === null || $priceValue <= 0) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Bei ya kununua si sahihi (lazima iwe namba kubwa kuliko 0)',
                    ], 400);
                }
                $updateData['price'] = $priceValue;
            }

            // Optional product photo. Written only when the caller sends the
            // key, so a partial update (add stock) cannot wipe an existing
            // photo. An explicit empty value clears it.
            if ($request->has('image_url')) {
                $updateData['image_url'] = ($image_url === null || trim((string) $image_url) === '')
                    ? null
                    : trim((string) $image_url);
            }

            // cost_price is intentionally NOT written any more. "Bei ya
            // Kununua" is stored in `products.price`; writing a second buying
            // price into cost_price is what let the two disagree and produce
            // a product whose buying and selling price both read 55000.
            // Existing cost_price values are left untouched as a legacy copy.

            Product::where('id', $productId)->update($updateData);

            $updatedProduct = Product::where('id', $productId)
                ->select(['id', 'name', 'category', 'description', 'price', 'expected_selling_price', 'cost_price', 'stock', 'barcode', 'is_active', 'seller_id', 'image_url', 'created_at', 'updated_at'])
                ->first()
                ->toArray();

            $priceChange = $product['expected_selling_price'] == $expectedPriceValue ? null : [
                'old' => $product['expected_selling_price'],
                'new' => $expectedPriceValue,
            ];

            $this->log($userId, 'PRODUCT_UPDATE', '/api/products/:id', [
                'product_id' => $productId,
                'fields_updated' => array_keys($updateData),
                'price_change' => $priceChange,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Bidhaa imesasishwa kikamilifu! Bei ya kuuzia imesasishwa.',
                'product' => $updatedProduct,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'PRODUCT_UPDATE_ERROR', '/api/products/:id', [
                'error' => $e->getMessage(),
                'product_id' => $id,
            ], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function destroy(Request $request, $id)
    {
        if (! $this->canManageProducts($request)) {
            return $this->json([
                'success' => false,
                'error' => 'Ni msimamizi pekee anayeweza kufuta bidhaa.',
                'code' => 'PRODUCT_MANAGEMENT_FORBIDDEN',
            ], 403);
        }

        try {
            $productId = $id;
            $userId = $this->userId($request);

            [$writeBranchId, $branchError] = $this->requireWriteBranch($request);
            if ($branchError) {
                return $branchError;
            }

            $this->touchLastSeen($userId);

            // Admin manages the whole business catalogue, but never another
            // business's product. The helper enforces business and branch.
            $product = $this->findBusinessProduct($request, (string) $productId);

            if (!$product) {
                $this->log($userId, 'PRODUCT_DELETE_FAILED', '/api/products/:id', [
                    'reason' => 'Product not found',
                    'product_id' => $productId,
                ], $this->ip($request), 'failed');

                return $this->json(['error' => 'Bidhaa haipo'], 404);
            }

            Product::where('id', $productId)->update([
                'is_active' => false,
                'updated_at' => $this->isoNow(),
            ]);

            $this->log($userId, 'PRODUCT_DELETE', '/api/products/:id', [
                'product_id' => $productId,
                'product_name' => $product['name'] ?? null,
            ], $this->ip($request), 'success');

            return $this->json([
                'message' => 'Bidhaa imedhibitishwa kikamilifu!',
                'productId' => $productId,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'PRODUCT_DELETE_ERROR', '/api/products/:id', [
                'error' => $e->getMessage(),
                'product_id' => $id,
            ], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    /**
     * POST /api/products/{id}/stock
     *
     * Administrator-only manual or scanner receiving. The quantity is a delta,
     * never an absolute stock value, and every unit gets a stock_receipts row.
     */
    public function receiveStock(Request $request, $id)
    {
        if (! $this->canManageProducts($request)) {
            return $this->json([
                'success' => false,
                'error' => 'Muuzaji hawezi kuongeza stock mpya. Stock hupokelewa na msimamizi pekee.',
                'code' => 'STOCK_RECEIVING_FORBIDDEN',
            ], 403);
        }

        try {
            $userId = $this->userId($request);
            $businessId = $this->businessId($request);
            if (! $businessId || ! $userId) {
                return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
            }

            [$writeBranchId, $branchError] = $this->requireWriteBranch($request);
            if ($branchError) {
                return $branchError;
            }

            $quantity = $request->input('quantity');
            if (! is_numeric($quantity) || (float) $quantity != (int) $quantity || (int) $quantity <= 0) {
                return $this->json([
                    'success' => false,
                    'error' => 'Kiasi cha stock kinapaswa kuwa namba nzima kubwa kuliko sifuri.',
                    'code' => 'INVALID_STOCK_QUANTITY',
                ], 422);
            }

            $source = strtolower(trim((string) $request->input('source', 'manual')));
            if (! in_array($source, ['manual', 'scanner'], true)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Chanzo cha kupokea stock si sahihi.',
                    'code' => 'INVALID_STOCK_SOURCE',
                ], 422);
            }

            if ($source === 'scanner'
                && ! \App\Services\BusinessSettings::enabled('scanner', $businessId)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Kipengele cha Scanner kimezimwa na msimamizi wa biashara.',
                    'code' => 'FEATURE_DISABLED',
                    'feature' => 'scanner',
                ], 403);
            }

            $reference = trim((string) $request->input('reference', ''));
            if (mb_strlen($reference) > 80) {
                return $this->json(['success' => false, 'error' => 'Kumbukumbu ya kupokea ni ndefu mno', 'code' => 'INVALID_REFERENCE'], 422);
            }

            $notes = trim((string) $request->input('notes', ''));
            if (mb_strlen($notes) > 500) {
                $notes = mb_substr($notes, 0, 500);
            }

            $product = $this->findBusinessProduct($request, (string) $id);
            if (! $product) {
                return $this->json(['success' => false, 'error' => 'Bidhaa haipatikani kwenye biashara hii', 'code' => 'PRODUCT_NOT_FOUND'], 404);
            }

            if ($writeBranchId !== null && (string) ($product['branch_id'] ?? '') !== (string) $writeBranchId) {
                return $this->json(['success' => false, 'error' => 'Bidhaa ni ya tawi lingine', 'code' => 'BRANCH_MISMATCH'], 404);
            }

            $receipt = StockReceiving::receive(
                (string) $businessId,
                $writeBranchId,
                (string) $product['id'],
                (int) $quantity,
                (string) $userId,
                $source,
                $reference !== '' ? $reference : null,
                $notes !== '' ? $notes : null
            );

            $this->log($userId, 'STOCK_RECEIVED', '/api/products/' . $product['id'] . '/stock', [
                'product_id' => $product['id'],
                'product_name' => $product['name'] ?? null,
                'quantity' => (int) $quantity,
                'source' => $source,
                'receipt' => $receipt,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Stock imepokelewa kikamilifu.',
                'product' => [
                    'id' => $product['id'],
                    'name' => $product['name'] ?? null,
                    'stock' => $receipt['stock_after'] ?? ((int) ($product['stock'] ?? 0) + (int) $quantity),
                ],
                'receipt' => $receipt,
            ], 201);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'STOCK_RECEIVE_FAILED', '/api/products/' . $id . '/stock', [
                'product_id' => $id,
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            if (StockReceiving::isUnavailable($e)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Mfumo wa kupokea stock haujasakinishwa. Wasiliana na msimamizi wa mfumo.',
                    'code' => 'STOCK_RECEIVING_NOT_INSTALLED',
                ], 503);
            }

            return $this->json([
                'success' => false,
                'error' => 'Stock haikupokelewa: ' . $e->getMessage(),
                'code' => 'STOCK_RECEIPT_FAILED',
            ], 422);
        }
    }

    /**
     * GET /api/products/lookup?barcode=...
     *
     * Resolve an existing product inside the current business/branch. It never
     * creates a product and never changes stock. An unknown code is a hard
     * failure so a selling scan cannot silently register inventory.
     */
    public function lookup(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }

        try {
            $barcode = $this->normalizeBarcode($request->query('barcode'));
            if ($barcode === null) {
                return $this->json(['success' => false, 'error' => 'Barcode inahitajika', 'code' => 'BARCODE_REQUIRED'], 422);
            }
            if (mb_strlen($barcode) > 120) {
                return $this->json(['success' => false, 'error' => 'Barcode ni ndefu mno', 'code' => 'BARCODE_TOO_LONG'], 422);
            }

            $businessId = $this->businessId($request);
            $sellerIds = $businessId
                ? BusinessResolver::memberIds($businessId, Roles::BUSINESS_MEMBERS, 'approved')
                : [];
            if ($sellerIds === []) {
                return $this->json(['success' => false, 'error' => 'Bidhaa haipatikani', 'code' => 'BARCODE_NOT_FOUND'], 404);
            }

            $query = Product::whereIn('seller_id', $sellerIds)
                ->where('is_active', true)
                ->whereRaw('LOWER(barcode) = ?', [mb_strtolower($barcode)]);
            $query = $this->scopeBranch($query, $request);
            $rows = $query->select([
                'id', 'name', 'category', 'price', 'expected_selling_price',
                'stock', 'barcode', 'branch_id', 'seller_id', 'image_url', 'is_active',
            ])->get()->toArray();

            $key = mb_strtolower(trim($barcode));
            $matches = array_values(array_filter(
                $rows,
                fn ($row) => mb_strtolower(trim((string) ($row['barcode'] ?? ''))) === $key
            ));

            if ($matches === []) {
                return $this->json([
                    'success' => false,
                    'error' => 'Barcode hii haijasajiliwa kwenye bidhaa zako. Bidhaa mpya haiundwi kupitia mauzo.',
                    'code' => 'BARCODE_NOT_FOUND',
                ], 404);
            }

            if (count($matches) > 1) {
                return $this->json([
                    'success' => false,
                    'error' => 'Barcode hii inatumika kwenye bidhaa zaidi ya moja. Rekebisha barcode kwenye bidhaa kabla ya kuendelea.',
                    'code' => 'BARCODE_AMBIGUOUS',
                ], 409);
            }

            $this->log($this->userId($request), 'PRODUCT_BARCODE_LOOKUP', '/api/products/lookup', [
                'barcode' => $barcode,
                'product_id' => $matches[0]['id'],
                'purpose' => substr(trim((string) $request->query('purpose', '')), 0, 40),
            ], $this->ip($request), 'success');

            return $this->json(['success' => true, 'product' => $matches[0]]);
        } catch (\Throwable $e) {
            return $this->dbError($e, 'Imeshindikana kutafuta bidhaa kwa barcode');
        }
    }

    /** Normalize a scanner code without accepting arrays/objects. */
    /**
     * Whether the caller may manage products for this business.
     *
     * Administrators always can; a seller only can when the business has
     * explicitly enabled "seller can manage products stock".
     */
    private function canManageProducts(Request $request): bool
    {
        if ($this->isAdmin($request)) {
            return true;
        }

        if ($this->role($request) !== 'seller') {
            return false;
        }

        return \App\Services\BusinessSettings::enabled('product_management', $this->businessId($request));
    }

    private function normalizeBarcode($raw): ?string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $barcode = trim((string) $raw);

        return $barcode === '' ? null : $barcode;
    }

    /**
     * A barcode must identify one product in the current business+branch.
     * Duplicate codes are refused at registration, before a seller can hit an
     * ambiguous scan at the till.
     */
    private function barcodeConflict(Request $request, string $barcode, ?string $excludeId = null): ?JsonResponse
    {
        $businessId = $this->businessId($request);
        $sellerIds = $businessId
            ? BusinessResolver::memberIds($businessId, Roles::BUSINESS_MEMBERS, 'approved')
            : [];
        if ($sellerIds === []) {
            return null;
        }

        $query = Product::whereIn('seller_id', $sellerIds)
            ->where('is_active', true)
            ->whereRaw('LOWER(barcode) = ?', [mb_strtolower($barcode)]);
        $query = $this->scopeBranch($query, $request);

        $key = mb_strtolower(trim($barcode));
        foreach ($query->select(['id', 'name', 'barcode'])->get()->toArray() as $row) {
            if ($excludeId !== null && (string) ($row['id'] ?? '') === $excludeId) {
                continue;
            }
            if (mb_strtolower(trim((string) ($row['barcode'] ?? ''))) !== $key) {
                continue;
            }

            return $this->json([
                'success' => false,
                'error' => 'Barcode hii tayari inatumika kwenye bidhaa nyingine.',
                'code' => 'BARCODE_TAKEN',
                'product_id' => $row['id'] ?? null,
            ], 409);
        }

        return null;
    }

    /** An active product belonging to the caller's business and branch. */
    private function findBusinessProduct(Request $request, string $productId): ?array
    {
        $businessId = $this->businessId($request);
        $sellerIds = $businessId
            ? BusinessResolver::memberIds($businessId, Roles::BUSINESS_MEMBERS, 'approved')
            : [];
        if ($sellerIds === []) {
            return null;
        }

        $query = Product::where('id', $productId)
            ->whereIn('seller_id', $sellerIds)
            ->where('is_active', true);
        $query = $this->scopeBranch($query, $request);
        $row = $query->first();

        return $row ? $row->toArray() : null;
    }

    public function businessAllProducts(Request $request, $businessName = null)
    {
        try {
            $userId = $this->userId($request);

            // Business scoping after the business_id migration. The scope is
            // taken from the verified JWT, never from the URL, so one admin can
            // no longer read another business's catalogue by naming it in the
            // path. system_admin keeps the cross-business lookup used by the
            // platform pages, via ?business_id= or the legacy ?business=<name>
            // resolved through BusinessResolver so spelling variants collapse.
            $callerBusinessId = $this->businessId($request);
            // Opt-in only (?scope=platform): the msimamizi products module
            // stays business-scoped even for a promoted super admin.
            $isPlatformAdmin = $this->isSuperAdmin($request) && $request->query('scope') === 'platform';
            $scopeBusinessId = null;

            if ($isPlatformAdmin) {
                $idFilter = trim((string) $request->query('business_id', ''));
                $nameFilter = trim((string) ($request->query('business', '') ?: $request->query('business_name', '') ?: (string) $businessName));

                if (preg_match('/^[0-9a-f-]{36}$/i', $idFilter)) {
                    $scopeBusinessId = $idFilter;
                } elseif ($nameFilter !== '' && strcasecmp($nameFilter, 'my') !== 0) {
                    $scopeBusinessId = BusinessResolver::findByName($nameFilter)?->id;
                }
            } else {
                $scopeBusinessId = $callerBusinessId;
            }

            if (!$scopeBusinessId) {
                $this->log($userId, 'BUSINESS_PRODUCTS_FAILED', '/api/business/' . $businessName . '/all-products', [
                    'reason' => $isPlatformAdmin ? 'No business resolved for platform admin' : 'Caller has no business_id',
                    'business_name' => $businessName,
                ], $this->ip($request), 'failed');

                return $this->json([]);
            }

            $businessUsers = User::where('business_id', $scopeBusinessId)
                ->whereIn('role', Roles::BUSINESS_MEMBERS)
                ->where('status', 'approved')
                ->select(['id', 'email', 'full_name', 'role', 'status'])
                ->get()
                ->toArray();

            if (count($businessUsers) === 0) {
                $this->log($userId, 'BUSINESS_PRODUCTS_FAILED', '/api/business/' . $businessName . '/all-products', [
                    'reason' => 'No approved users found',
                    'business_name' => $businessName,
                ], $this->ip($request), 'failed');

                return $this->json([]);
            }

            $sellerIds = array_column($businessUsers, 'id');

            $allProductsQuery = Product::whereIn('seller_id', $sellerIds)
                ->where('is_active', true);
            $allProductsQuery = $this->scopeBranch($allProductsQuery, $request);
            $allProducts = $allProductsQuery
                ->orderBy('name')
                ->get()
                ->toArray();

            $productsWithSellerInfo = array_map(function ($product) use ($businessUsers) {
                $seller = null;
                foreach ($businessUsers as $bu) {
                    if ($bu['id'] === $product['seller_id']) {
                        $seller = $bu;
                        break;
                    }
                }

                $product['seller_name'] = ($seller['full_name'] ?? null) ?: ($seller['email'] ?? 'Unknown');
                $product['seller_role'] = $seller['role'] ?? 'unknown';
                $product['seller_email'] = $seller['email'] ?? null;
                return $product;
            }, $allProducts);

            $this->log($userId, 'BUSINESS_PRODUCTS_VIEW', '/api/business/' . $businessName . '/all-products', [
                'business_name' => $businessName,
                'product_count' => count($productsWithSellerInfo),
                'seller_count' => count($businessUsers),
            ], $this->ip($request), 'success');

            return $this->json($productsWithSellerInfo);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'BUSINESS_PRODUCTS_ERROR', '/api/business/' . $businessName . '/all-products', [
                'error' => $e->getMessage(),
                'business_name' => $businessName,
            ], $this->ip($request), 'failed');

            try {
                $adminUsers = User::where('business_id', $scopeBusinessId)
                    ->whereIn('role', Roles::BUSINESS_OWNERS)
                    ->where('status', 'approved')
                    ->select(['id'])
                    ->limit(1)
                    ->get()
                    ->toArray();

                if (count($adminUsers) > 0) {
                    $adminId = $adminUsers[0]['id'];

                    $adminProducts = Product::where('seller_id', $adminId)
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                        ->toArray();

                    $productsWithSellerInfo = array_map(function ($product) {
                        $product['seller_name'] = 'Admin';
                        $product['seller_role'] = 'admin';
                        $product['seller_email'] = null;
                        return $product;
                    }, $adminProducts);

                    $this->log($this->userId($request), 'BUSINESS_PRODUCTS_FALLBACK', '/api/business/' . $businessName . '/all-products', [
                        'business_name' => $businessName,
                        'fallback_product_count' => count($productsWithSellerInfo),
                    ], $this->ip($request), 'success');

                    return $this->json($productsWithSellerInfo);
                }
            } catch (\Throwable $fallbackError) {
            }

            return $this->json([
                'error' => 'Hitilafu ya kupata bidhaa za biashara',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}