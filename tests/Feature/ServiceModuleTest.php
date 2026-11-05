<?php

namespace Tests\Feature;

use App\Services\JwtToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Huduma (service) module guard rails.
 *
 * These tests drive the real HTTP stack with Supabase faked at the HTTP layer,
 * so they assert the contract the web page and the mobile app depend on:
 * the routes exist, only an admin may manage services, product ids from another
 * business are refused, and the sale call sends server-derived values.
 */
class ServiceModuleTest extends TestCase
{
    private const BUSINESS = 'b-1';
    private const SELLER = 'u-1';
    private const SERVICE = '11111111-1111-4111-8111-111111111111';
    private const PRODUCT = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'supabase.url' => 'http://supabase.test',
            'supabase.service_role_key' => 'service-role-key',
            'jwt.secret' => 'test-secret',
        ]);
    }

    public function test_service_routes_are_registered_behind_auth(): void
    {
        $expected = [
            ['api/services', 'GET'],
            ['api/services', 'POST'],
            ['api/services/{id}', 'GET'],
            ['api/services/{id}', 'PUT'],
            ['api/services/{id}', 'DELETE'],
            ['api/services/{id}/consumables', 'POST'],
            ['api/services/{id}/consumables/{productId}', 'PUT'],
            ['api/services/{id}/consumables/{productId}', 'DELETE'],
            ['api/service-sales/my', 'GET'],
            ['api/service-sales', 'POST'],
            ['api/admin/service-sales', 'GET'],
        ];

        $routes = collect(Route::getRoutes()->getRoutes());

        foreach ($expected as [$uri, $method]) {
            $match = $routes->first(function ($route) use ($uri, $method) {
                return $route->uri() === $uri && in_array($method, $route->methods(), true);
            });

            $this->assertNotNull($match, "{$method} {$uri} must be registered.");
            $this->assertStringContainsString(
                'auth.jwt',
                implode(',', $match->gatherMiddleware()),
                "{$method} {$uri} must require a signed-in user."
            );
        }
    }

    public function test_service_endpoints_reject_anonymous_callers(): void
    {
        Http::fake();

        $this->getJson('/api/services')->assertStatus(401);
        $this->postJson('/api/service-sales', [])->assertStatus(401);
        $this->getJson('/api/admin/service-sales')->assertStatus(401);
    }

    public function test_a_seller_cannot_create_services(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', $this->bearer('seller'))
            ->postJson('/api/services', [
                'name' => 'Kusuka nywele',
                'consumables' => [['product_id' => self::PRODUCT, 'required_quantity' => 1]],
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN']);

        Http::assertNotSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/services'));
    }

    public function test_service_creation_refuses_another_businesss_product(): void
    {
        $this->fakeSupabase([
            'user' => $this->user(['role' => 'admin']),
            'members' => [['id' => self::SELLER]],
            'products' => [[
                'id' => self::PRODUCT,
                'name' => 'Uzi wa kusuka',
                'unit' => 'pcs',
                'price' => 2000,
                'expected_selling_price' => 3000,
                'stock' => 20,
                'is_active' => true,
                // Owned by someone outside this business: must be rejected.
                'seller_id' => 'intruder-9',
                'category' => 'Vifaa',
            ]],
        ]);

        $this->withHeader('Authorization', $this->bearer('admin'))
            ->postJson('/api/services', [
                'name' => 'Kusuka nywele',
                'consumables' => [['product_id' => self::PRODUCT, 'required_quantity' => 1]],
            ])
            ->assertStatus(400)
            ->assertJson(['code' => 'INVALID_PRODUCTS']);
    }

    public function test_service_sale_uses_the_jwt_business_and_server_prices(): void
    {
        $this->fakeSupabase([
            'services' => [$this->service()],
            'products' => [$this->product(['stock' => 10, 'price' => 2000])],
            'rpc' => [
                'duplicate' => false,
                'sale' => [
                    'id' => 'sale-1',
                    'invoice_number' => 'SRV-20261105-ABCDEF12',
                    'selling_price' => 30000,
                    'total_cost' => 4000,
                    'profit' => 26000,
                ],
                'items' => [],
            ],
        ]);

        $this->withHeader('Authorization', $this->bearer('seller'))
            ->postJson('/api/service-sales', [
                'service_id' => self::SERVICE,
                // Client-supplied tenancy must be ignored.
                'business_id' => 'b-evil',
                'service_quantity' => 1,
                'selling_price' => 30000,
                'consumables' => [['product_id' => self::PRODUCT, 'quantity' => 2]],
                'payment_method' => 'cash',
                'clientSaleKey' => 'uza-huduma-test-1',
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true, 'duplicate' => false])
            ->assertJsonPath('sale.invoice_number', 'SRV-20261105-ABCDEF12');

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/rpc/create_service_sale')) {
                return false;
            }

            $body = $request->data();

            return $body['p_business_id'] === self::BUSINESS
                && $body['p_seller_id'] === self::SELLER
                && $body['p_service_id'] === self::SERVICE
                && $body['p_consumables'] === [['product_id' => self::PRODUCT, 'quantity' => 2.0]]
                && !isset($body['business_id']);
        });
    }

    public function test_short_stock_fails_before_the_atomic_rpc_runs(): void
    {
        $this->fakeSupabase([
            'services' => [$this->service()],
            'products' => [$this->product(['stock' => 1])],
        ]);

        $this->withHeader('Authorization', $this->bearer('seller'))
            ->postJson('/api/service-sales', [
                'service_id' => self::SERVICE,
                'selling_price' => 30000,
                'consumables' => [['product_id' => self::PRODUCT, 'quantity' => 2]],
            ])
            ->assertStatus(409)
            ->assertJson(['code' => 'STOCK_ISSUES']);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rpc/create_service_sale'));
    }

    public function test_inactive_service_is_refused(): void
    {
        $this->fakeSupabase([
            'services' => [$this->service(['status' => 'inactive'])],
            'products' => [$this->product()],
        ]);

        $this->withHeader('Authorization', $this->bearer('seller'))
            ->postJson('/api/service-sales', [
                'service_id' => self::SERVICE,
                'selling_price' => 30000,
                'consumables' => [['product_id' => self::PRODUCT, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJson(['code' => 'SERVICE_INACTIVE']);
    }

    public function test_missing_database_function_is_reported_as_not_installed(): void
    {
        $this->fakeSupabase([
            'services' => [$this->service()],
            'products' => [$this->product(['stock' => 10])],
            'rpc' => ['message' => 'Could not find the function public.create_service_sale(p_business_id, ...) in the schema cache'],
            'rpc_status' => 404,
        ]);

        $this->withHeader('Authorization', $this->bearer('seller'))
            ->postJson('/api/service-sales', [
                'service_id' => self::SERVICE,
                'selling_price' => 30000,
                'consumables' => [['product_id' => self::PRODUCT, 'quantity' => 1]],
            ])
            ->assertStatus(503)
            ->assertJson(['code' => 'SERVICE_SALE_NOT_INSTALLED']);
    }

    public function test_replayed_sale_key_returns_the_original_sale(): void
    {
        $this->fakeSupabase([
            'services' => [$this->service()],
            'products' => [$this->product(['stock' => 10])],
            'rpc' => [
                'duplicate' => true,
                'sale' => [
                    'id' => 'sale-1',
                    'invoice_number' => 'SRV-20261105-ABCDEF12',
                    'selling_price' => 30000,
                    'total_cost' => 2000,
                    'profit' => 28000,
                ],
                'items' => [],
            ],
        ]);

        $this->withHeader('Authorization', $this->bearer('seller'))
            ->postJson('/api/service-sales', [
                'service_id' => self::SERVICE,
                'selling_price' => 30000,
                'consumables' => [['product_id' => self::PRODUCT, 'quantity' => 1]],
                'clientSaleKey' => 'uza-huduma-test-1',
            ])
            // A replay is a success for the client, but not a new sale.
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'duplicate' => true,
                'invoice_number' => 'SRV-20261105-ABCDEF12',
            ]);
    }

    /* ------------------------------------------------------------------ */

    private function bearer(string $role): string
    {
        return 'Bearer ' . JwtToken::encode(['userId' => self::SELLER, 'role' => $role]);
    }

    private function user(array $overrides = []): array
    {
        return array_merge([
            'id' => self::SELLER,
            'email' => 'seller@example.com',
            'role' => 'seller',
            'full_name' => 'Seller One',
            'business_name' => 'Shop One',
            'business_id' => self::BUSINESS,
            'status' => 'approved',
            'is_online' => true,
            'last_seen' => null,
            'language' => 'sw',
        ], $overrides);
    }

    private function service(array $overrides = []): array
    {
        return array_merge([
            'id' => self::SERVICE,
            'business_id' => self::BUSINESS,
            'name' => 'Kusuka nywele',
            'description' => null,
            'status' => 'active',
            'created_at' => '2026-11-01T00:00:00Z',
            'updated_at' => '2026-11-01T00:00:00Z',
        ], $overrides);
    }

    private function product(array $overrides = []): array
    {
        return array_merge([
            'id' => self::PRODUCT,
            'name' => 'Uzi wa kusuka',
            'unit' => 'pcs',
            'price' => 2000,
            'expected_selling_price' => 3000,
            'stock' => 10,
            'is_active' => true,
            'seller_id' => self::SELLER,
            'category' => 'Vifaa',
        ], $overrides);
    }

    /**
     * Fake the whole PostgREST surface by table, which keeps the tests about
     * controller behaviour instead of URL string matching.
     */
    private function fakeSupabase(array $overrides = []): void
    {
        $data = array_merge([
            'user' => $this->user(),
            'members' => [['id' => self::SELLER]],
            'services' => [],
            'consumables' => [],
            'products' => [],
            'rpc' => ['sale' => ['id' => 'sale-1'], 'items' => []],
            'rpc_status' => 200,
        ], $overrides);

        Http::fake(function ($request) use ($data) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (str_contains($path, '/rpc/')) {
                return Http::response($data['rpc'], $data['rpc_status']);
            }

            switch (basename($path)) {
                case 'users':
                    // Member listing carries a business filter; the middleware's
                    // own lookup asks for one user by id.
                    return Http::response(isset($query['business_id']) ? $data['members'] : [$data['user']]);
                case 'products':
                    return Http::response($data['products']);
                case 'services':
                    return $request->method() === 'GET'
                        ? Http::response($data['services'])
                        : Http::response([], 201);
                case 'service_consumables':
                    return $request->method() === 'GET'
                        ? Http::response($data['consumables'])
                        : Http::response([], 201);
                default:
                    return Http::response([], $request->method() === 'GET' ? 200 : 201);
            }
        });
    }
}
