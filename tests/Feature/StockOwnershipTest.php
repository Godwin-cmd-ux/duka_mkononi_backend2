<?php

namespace Tests\Feature;

use App\Services\BranchContext;
use App\Services\JwtToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Stock ownership is a security boundary, not a hidden-button rule.
 *
 *   - only an approved administrator can increase products.stock;
 *   - every increase goes through receive_product_stock (ledger entry);
 *   - sellers can sell/transfer existing stock but cannot create any;
 *   - a barcode scan resolves an existing row or fails, never creates one.
 */
class StockOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const BUSINESS = 'b-1';
    private const BRANCH = 'branch-1';
    private const ADMIN = 'admin-1';
    private const SELLER = 'seller-1';

    /** @var list<array<string,mixed>> */
    private array $products = [];

    /** @var list<array<string,mixed>> */
    private array $rpcCalls = [];

    private int $productWrites = 0;

    private bool $scannerEnabled = true;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'supabase.url' => 'http://supabase.test',
            'supabase.service_role_key' => 'service-role-key',
            'jwt.secret' => 'test-secret',
        ]);

        \Illuminate\Support\Facades\Cache::flush();
    }

    private function fakeSupabase(bool $scannerEnabled = true): void
    {
        $this->scannerEnabled = $scannerEnabled;

        $users = [
            [
                'id' => self::ADMIN,
                'email' => 'admin@shop.test',
                'role' => 'admin',
                'full_name' => 'Admin',
                'business_name' => 'Shop',
                'business_id' => self::BUSINESS,
                'branch_id' => null,
                'status' => 'approved',
                'language' => 'sw',
            ],
            [
                'id' => self::SELLER,
                'email' => 'seller@shop.test',
                'role' => 'seller',
                'full_name' => 'Seller',
                'business_name' => 'Shop',
                'business_id' => self::BUSINESS,
                'branch_id' => self::BRANCH,
                'status' => 'approved',
                'language' => 'sw',
            ],
        ];

        $businesses = [[
            'id' => self::BUSINESS,
            'business_name' => 'Shop',
            'legacy_name_key' => 'shop',
            'business_structure' => 'branch',
        ]];

        $branches = [[
            'id' => self::BRANCH,
            'business_id' => self::BUSINESS,
            'branch_name' => 'Main',
            'location' => 'Dar',
            'status' => 'active',
        ]];

        $settings = [[
            'business_id' => self::BUSINESS,
            'fridge_enabled' => true,
            'scanner_enabled' => $scannerEnabled,
            'services_enabled' => true,
            'notify_low_stock' => true,
            'notify_fridge_stock' => true,
        ]];

        $this->products = [
            [
                'id' => 'p-1',
                'seller_id' => self::ADMIN,
                'name' => 'Admin Soap',
                'category' => 'Home',
                'price' => 1000,
                'expected_selling_price' => 1500,
                'stock' => 10,
                'barcode' => '111111',
                'branch_id' => self::BRANCH,
                'is_active' => true,
            ],
            [
                'id' => 'p-2',
                'seller_id' => self::SELLER,
                'name' => 'Seller Rice',
                'category' => 'Food',
                'price' => 2000,
                'expected_selling_price' => 2500,
                'stock' => 5,
                'barcode' => '222222',
                'branch_id' => self::BRANCH,
                'is_active' => true,
            ],
            [
                'id' => 'p-other',
                'seller_id' => self::ADMIN,
                'name' => 'Other Branch',
                'category' => 'Home',
                'price' => 100,
                'expected_selling_price' => 200,
                'stock' => 9,
                'barcode' => '999999',
                'branch_id' => 'branch-2',
                'is_active' => true,
            ],
            [
                'id' => 'p-foreign',
                'seller_id' => 'foreign-admin',
                'name' => 'Foreign Product',
                'category' => 'Home',
                'price' => 100,
                'expected_selling_price' => 200,
                'stock' => 9,
                'barcode' => '888888',
                'branch_id' => self::BRANCH,
                'is_active' => true,
            ],
        ];

        $this->rpcCalls = [];
        $this->productWrites = 0;

        Http::fake(function (Request $request) use ($users, $businesses, $branches, $settings) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $method = strtoupper($request->method());

            if (str_contains($path, '/rpc/receive_product_stock')) {
                $body = json_decode((string) $request->body(), true) ?? [];
                $this->rpcCalls[] = $body;

                $index = null;
                foreach ($this->products as $i => $product) {
                    if ((string) $product['id'] === (string) ($body['p_product_id'] ?? '')) {
                        $index = $i;
                        break;
                    }
                }
                if ($index === null) {
                    return Http::response(['message' => 'Bidhaa haipatikani'], 404);
                }

                $qty = (int) ($body['p_quantity'] ?? 0);
                $before = (int) $this->products[$index]['stock'];
                $after = $before + $qty;
                $this->products[$index]['stock'] = $after;

                return Http::response([
                    'success' => true,
                    'product_id' => $body['p_product_id'],
                    'quantity' => $qty,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'source' => $body['p_source'] ?? 'manual',
                    'receiving_reference' => $body['p_reference'] ?? 'RCV-TEST',
                ], 200);
            }

            $table = basename($path);

            if ($table === 'users') {
                $rows = $this->filter($users, $query);
            } elseif ($table === 'businesses') {
                $rows = $this->filter($businesses, $query);
            } elseif ($table === 'branches') {
                $rows = $this->filter($branches, $query);
            } elseif ($table === 'products') {
                $rows = $this->filter($this->products, $query);

                if ($method === 'PATCH') {
                    $this->productWrites++;
                    $body = json_decode((string) $request->body(), true) ?? [];
                    foreach ($this->products as $i => $product) {
                        foreach ($rows as $matched) {
                            if ((string) $product['id'] === (string) $matched['id']) {
                                $this->products[$i] = array_merge($this->products[$i], $body);
                            }
                        }
                    }

                    return Http::response([], 204);
                }

                if ($method === 'POST') {
                    $this->productWrites++;
                    $body = json_decode((string) $request->body(), true) ?? [];
                    $this->products[] = $body;

                    return Http::response([$body], 201);
                }
            } elseif ($table === 'business_settings') {
                $rows = $this->filter($settings, $query);
            } elseif ($table === 'business_blocks') {
                $rows = [];
            } else {
                $rows = [];
            }

            if ($method === 'POST') {
                $body = json_decode((string) $request->body(), true) ?? [];

                return Http::response([$body], 201);
            }

            if (isset($query['select']) && is_string($query['select']) && $query['select'] !== '*') {
                $columns = array_filter(array_map('trim', explode(',', $query['select'])));
                if ($columns !== []) {
                    $rows = array_map(fn ($row) => array_intersect_key($row, array_flip($columns)), $rows);
                }
            }

            return Http::response(array_values($rows), 200);
        });
    }

    /** @param list<array<string,mixed>> $rows */
    private function filter(array $rows, array $query): array
    {
        foreach ($query as $column => $raw) {
            if (! is_string($raw) || in_array($column, ['select', 'order', 'limit', 'offset', 'or'], true)) {
                continue;
            }
            $raw = urldecode($raw);

            $rows = array_values(array_filter($rows, function ($row) use ($column, $raw) {
                $value = $row[$column] ?? null;

                if (str_starts_with($raw, 'eq.')) {
                    $expected = substr($raw, 3);
                    if (is_bool($value)) {
                        return $value === in_array($expected, ['true', '1'], true);
                    }

                    return (string) $value === $expected;
                }
                if (str_starts_with($raw, 'is.null')) {
                    return $value === null;
                }
                if (str_starts_with($raw, 'not.is.null')) {
                    return $value !== null;
                }
                if (str_starts_with($raw, 'ilike.')) {
                    return stripos((string) $value, substr($raw, 6)) !== false;
                }
                if (str_starts_with($raw, 'in.(')) {
                    $list = array_map(
                        fn ($item) => trim($item, " \t\n\r\0\x0B\""),
                        explode(',', trim(substr($raw, 3), '()'))
                    );

                    return in_array((string) $value, $list, true);
                }

                return (string) $value === $raw;
            }));
        }

        return $rows;
    }

    private function token(string $userId, string $email, string $role, ?string $branchId, string $context): string
    {
        return JwtToken::encode([
            'userId' => $userId,
            'email' => $email,
            'role' => $role,
            'business_id' => self::BUSINESS,
            'business_structure' => 'branch',
            'branch_id' => $branchId,
            'branch_context' => $context,
        ]);
    }

    private function sellerToken(): string
    {
        return $this->token(self::SELLER, 'seller@shop.test', 'seller', self::BRANCH, BranchContext::INDIVIDUAL);
    }

    private function adminToken(): string
    {
        return $this->token(self::ADMIN, 'admin@shop.test', 'admin', self::BRANCH, BranchContext::INDIVIDUAL);
    }

    private function stockOf(string $id): int
    {
        foreach ($this->products as $product) {
            if ((string) $product['id'] === $id) {
                return (int) $product['stock'];
            }
        }

        return -1;
    }

    #[Test]
    public function a_seller_cannot_call_the_stock_receiving_endpoint(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/products/p-1/stock', ['quantity' => 50])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'STOCK_RECEIVING_FORBIDDEN']);

        $this->assertSame(10, $this->stockOf('p-1'));
        $this->assertSame([], $this->rpcCalls, 'A refused seller must never reach the receiving RPC.');
        $this->assertSame(0, $this->productWrites);
    }

    #[Test]
    public function a_seller_cannot_increase_stock_through_a_product_update(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->putJson('/api/products/p-2', [
                'name' => 'Seller Rice',
                'category' => 'Food',
                'stock' => 500,
            ])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'PRODUCT_MANAGEMENT_FORBIDDEN']);

        $this->assertSame(5, $this->stockOf('p-2'));
        $this->assertSame(0, $this->productWrites);
    }

    #[Test]
    public function a_seller_cannot_register_a_product_with_initial_stock(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/products', [
                'name' => 'New Stock',
                'category' => 'Food',
                'price' => 1000,
                'expected_selling_price' => 1200,
                'stock' => 25,
            ])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'PRODUCT_MANAGEMENT_FORBIDDEN']);

        $this->assertSame(0, $this->productWrites);
        $this->assertSame([], $this->rpcCalls);
    }

    #[Test]
    public function an_admin_receives_stock_through_the_atomic_ledger(): void
    {
        $this->fakeSupabase();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/products/p-1/stock', [
                'quantity' => 7,
                'source' => 'scanner',
                'reference' => 'DELIVERY-77',
                'notes' => 'Supplier delivery',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('product.stock', 17)
            ->assertJsonPath('receipt.stock_before', 10)
            ->assertJsonPath('receipt.stock_after', 17)
            ->assertJsonPath('receipt.receiving_reference', 'DELIVERY-77');

        $this->assertSame(17, $this->stockOf('p-1'));
        $this->assertCount(1, $this->rpcCalls);
        $this->assertSame('scanner', $this->rpcCalls[0]['p_source']);
        $this->assertSame(7, $this->rpcCalls[0]['p_quantity']);
        $this->assertSame(self::BRANCH, $this->rpcCalls[0]['p_branch_id']);
    }

    #[Test]
    public function an_admin_cannot_edit_or_delete_another_businesses_product(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->putJson('/api/products/p-foreign', ['price' => 1])
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->deleteJson('/api/products/p-foreign')
            ->assertStatus(404);

        $this->assertSame(9, $this->stockOf('p-foreign'));
    }

    #[Test]
    public function an_admin_registers_initial_stock_through_the_ledger(): void
    {
        $this->fakeSupabase();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/products', [
                'name' => 'New Product',
                'category' => 'General',
                'price' => 100,
                'expected_selling_price' => 150,
                'stock' => 7,
                'barcode' => '999-REGISTER',
            ])
            ->assertStatus(201)
            ->assertJsonPath('product.stock', 7);

        $this->assertNotEmpty($this->rpcCalls);
        $this->assertSame(
            ['p_quantity' => 7, 'p_source' => 'manual'],
            [
                'p_quantity' => $this->rpcCalls[0]['p_quantity'] ?? null,
                'p_source' => $this->rpcCalls[0]['p_source'] ?? null,
            ]
        );
        $this->assertSame(7, $this->stockOf($response->json('product.id')));
    }

    #[Test]
    public function an_admin_cannot_change_stock_with_a_plain_product_update(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->putJson('/api/products/p-1', ['stock' => 11])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'STOCK_RECEIPT_REQUIRED']);

        $this->assertSame(10, $this->stockOf('p-1'));
        $this->assertSame([], $this->rpcCalls);
        $this->assertSame(0, $this->productWrites);
    }

    #[Test]
    public function an_unknown_barcode_never_creates_a_product(): void
    {
        $this->fakeSupabase();
        $before = count($this->products);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->getJson('/api/products/lookup?barcode=NO-SUCH-CODE')
            ->assertStatus(404)
            ->assertJsonFragment(['code' => 'BARCODE_NOT_FOUND']);

        $this->assertCount($before, $this->products);
        $this->assertSame(0, $this->productWrites);
        $this->assertSame([], $this->rpcCalls);
    }

    #[Test]
    public function a_barcode_is_resolved_only_inside_the_sellers_branch(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->getJson('/api/products/lookup?barcode=111111')
            ->assertOk()
            ->assertJsonPath('product.id', 'p-1')
            ->assertJsonPath('product.stock', 10);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->getJson('/api/products/lookup?barcode=999999')
            ->assertStatus(404)
            ->assertJsonFragment(['code' => 'BARCODE_NOT_FOUND']);
    }

    #[Test]
    public function scanner_operations_are_refused_when_the_feature_is_off(): void
    {
        $this->fakeSupabase(false);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->getJson('/api/products/lookup?barcode=111111')
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'FEATURE_DISABLED']);

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/products/p-1/stock', ['quantity' => 1, 'source' => 'scanner'])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'FEATURE_DISABLED']);

        $this->assertSame(10, $this->stockOf('p-1'));
        $this->assertSame([], $this->rpcCalls);
    }

    #[Test]
    public function a_seller_cannot_use_inventory_photo_import(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/inventory/ai-import', ['images' => []])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'STOCK_RECEIVING_FORBIDDEN']);
    }
}
