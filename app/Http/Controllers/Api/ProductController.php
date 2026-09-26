<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\User;
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
                $products = Product::where('seller_id', $userId)
                    ->where('is_active', true)
                    ->select(['id', 'name', 'category', 'description', 'price', 'expected_selling_price', 'cost_price', 'stock', 'is_active', 'seller_id', 'created_at', 'updated_at'])
                    ->orderBy('name')
                    ->get()
                    ->toArray();

                $processedProducts = array_map(function ($product) {
                    $product['expected_selling_price'] = $product['expected_selling_price'] ?: ($product['price'] ?: 0);
                    return $product;
                }, $products);

                $this->log($userId, 'PRODUCTS_VIEW', '/api/products/my', [
                    'count' => count($processedProducts),
                    'role' => 'admin',
                    'personal' => true,
                ], $this->ip($request), 'success');

                return $this->json($processedProducts);
            }

            $adminUser = User::where('business_name', $businessName)
                ->where('role', 'admin')
                ->where('status', 'approved')
                ->select(['id'])
                ->first();

            if (!$adminUser) {
                return $this->json([]);
            }

            $businessUsers = User::where('business_name', $businessName)
                ->whereIn('role', ['admin', 'seller'])
                ->where('status', 'approved')
                ->select(['id', 'email', 'full_name', 'role', 'status'])
                ->get()
                ->toArray();

            $sellerIds = array_column($businessUsers, 'id');

            if (count($sellerIds) === 0) {
                return $this->json([]);
            }

            $allProducts = Product::whereIn('seller_id', $sellerIds)
                ->where('is_active', true)
                ->select(['id', 'name', 'category', 'description', 'price', 'expected_selling_price', 'cost_price', 'stock', 'is_active', 'seller_id', 'created_at', 'updated_at'])
                ->orderBy('name')
                ->get()
                ->toArray();

            $processedProducts = array_map(function ($product) {
                $product['expected_selling_price'] = $product['expected_selling_price'] ?: ($product['price'] ?: 0);
                return $product;
            }, $allProducts);

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
        try {
            $userId = $this->userId($request);
            $name = $request->input('name');
            $category = $request->input('category');
            $price = $request->input('price');
            $stock = $request->input('stock');
            $description = $request->input('description');
            $cost_price = $request->input('cost_price');
            $expected_selling_price = $request->input('expected_selling_price');

            $this->touchLastSeen($userId);

            if (!$name || !$category || !$price || !$stock || !$expected_selling_price) {
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

            if ($stockValue < 0) {
                $this->log($userId, 'PRODUCT_CREATE_FAILED', '/api/products', [
                    'reason' => 'Invalid stock',
                    'stock' => $stock,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Idadi ya bidhaa si sahihi (lazima iwe namba isiyo hasi)',
                ], 400);
            }

            $now = $this->isoNow();
            $productData = [
                'seller_id' => $userId,
                'name' => trim((string) $name),
                'description' => trim((string) ($description ?? '')),
                'category' => trim((string) ($category ?? '')),
                'price' => $priceValue,
                'cost_price' => $cost_price ? (float) $cost_price : null,
                'expected_selling_price' => $expectedPriceValue,
                'stock' => $stockValue,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

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

            $newProduct = Product::where('id', $newProductId)
                ->select(['id', 'name', 'category', 'description', 'price', 'expected_selling_price', 'cost_price', 'stock', 'is_active', 'seller_id', 'created_at', 'updated_at'])
                ->first()
                ->toArray();

            $this->log($userId, 'PRODUCT_CREATE', '/api/products', [
                'product_id' => $newProduct['id'],
                'name' => $newProduct['name'],
                'price' => $newProduct['price'],
                'expected_selling_price' => $newProduct['expected_selling_price'],
                'stock' => $newProduct['stock'],
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Bidhaa imeongezwa kikamilifu! Bei ya kuuzia imewekwa.',
                'product' => $newProduct,
            ], 201);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'PRODUCT_CREATE_ERROR', '/api/products', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $productId = $id;
            $userId = $this->userId($request);
            $name = $request->input('name');
            $category = $request->input('category');
            $price = $request->input('price');
            $stock = $request->input('stock');
            $description = $request->input('description');
            $cost_price = $request->input('cost_price');
            $expected_selling_price = $request->input('expected_selling_price');

            $this->touchLastSeen($userId);

            if (!$expected_selling_price) {
                $this->log($userId, 'PRODUCT_UPDATE_FAILED', '/api/products/:id', [
                    'reason' => 'Missing expected selling price',
                    'product_id' => $productId,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Bei ya kuuzia inahitajika',
                ], 400);
            }

            $product = Product::where('id', $productId)
                ->select(['seller_id', 'expected_selling_price'])
                ->first();

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

            if ($product->seller_id !== $userId) {
                $this->log($userId, 'PRODUCT_UPDATE_FAILED', '/api/products/:id', [
                    'reason' => 'Unauthorized access',
                    'product_id' => $productId,
                    'owner_id' => $product->seller_id,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => 'Huna ruhusa ya kuhariri bidhaa hii',
                ], 403);
            }

            $expectedPriceValue = (float) $expected_selling_price;

            if ($expectedPriceValue <= 0) {
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

            $updateData = [
                'name' => trim((string) $name),
                'category' => trim((string) ($category ?? '')),
                'description' => trim((string) ($description ?? '')),
                'price' => (float) $price,
                'cost_price' => $cost_price ? (float) $cost_price : null,
                'expected_selling_price' => $expectedPriceValue,
                'stock' => ((int) $stock) ?: 0,
                'updated_at' => $this->isoNow(),
            ];

            Product::where('id', $productId)->update($updateData);

            $updatedProduct = Product::where('id', $productId)
                ->select(['id', 'name', 'category', 'description', 'price', 'expected_selling_price', 'cost_price', 'stock', 'is_active', 'seller_id', 'created_at', 'updated_at'])
                ->first()
                ->toArray();

            $priceChange = $product->expected_selling_price == $expectedPriceValue ? null : [
                'old' => $product->expected_selling_price,
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
        try {
            $productId = $id;
            $userId = $this->userId($request);

            $this->touchLastSeen($userId);

            $product = Product::where('id', $productId)
                ->select(['seller_id', 'name'])
                ->first();

            if (!$product) {
                $this->log($userId, 'PRODUCT_DELETE_FAILED', '/api/products/:id', [
                    'reason' => 'Product not found',
                    'product_id' => $productId,
                ], $this->ip($request), 'failed');

                return $this->json(['error' => 'Bidhaa haipo'], 404);
            }

            if ($product->seller_id !== $userId) {
                $this->log($userId, 'PRODUCT_DELETE_FAILED', '/api/products/:id', [
                    'reason' => 'Unauthorized access',
                    'product_id' => $productId,
                    'owner_id' => $product->seller_id,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'error' => 'Huna ruhusa ya kufuta bidhaa hii',
                ], 403);
            }

            Product::where('id', $productId)->update([
                'is_active' => false,
                'updated_at' => $this->isoNow(),
            ]);

            $this->log($userId, 'PRODUCT_DELETE', '/api/products/:id', [
                'product_id' => $productId,
                'product_name' => $product->name,
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

    public function businessAllProducts(Request $request, $businessName)
    {
        try {
            $userId = $this->userId($request);

            $businessUsers = User::where('business_name', $businessName)
                ->whereIn('role', ['admin', 'seller'])
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

            $allProducts = Product::whereIn('seller_id', $sellerIds)
                ->where('is_active', true)
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
                $adminUsers = User::where('business_name', $businessName)
                    ->where('role', 'admin')
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