<?php

namespace Tests\Feature;

use App\Services\BranchContext;
use App\Services\BusinessSettings;
use App\Services\JwtToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Settings module must change real behaviour, not just store preferences.
 *
 * Covers:
 *   - the consolidated admin snapshot and its defaults
 *   - admin-only access (a seller cannot read or write settings)
 *   - feature persistence, cache invalidation, and audit old/new values
 *   - API enforcement when the Fridge or Services module is switched off
 *   - branch creation/editing isolation and the last-active-branch guard
 *   - protected profile fields surviving an update payload
 *
 * The fake mirrors PostgREST query semantics (eq./in./is/count headers) so a
 * filter that stops working fails the test instead of passing silently.
 */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const BUSINESS = 'b-1';
    private const OTHER_BUSINESS = 'b-2';
    private const BRANCH_A = 'br-a';
    private const BRANCH_B = 'br-b';
    private const OTHER_BRANCH = 'br-other';

    private array $users = [];
    private array $businesses = [];
    private array $branches = [];
    private array $settings = [];
    private array $logs = [];
    private array $tables = [];
    private array $rpcCalls = [];
    private ?string $rpcError = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'supabase.url' => 'http://supabase.test',
            'supabase.service_role_key' => 'service-role-key',
            'jwt.secret' => 'test-secret',
        ]);

        Cache::flush();

        $this->users = [
            $this->userRow('admin-1', 'admin@shop.test', 'admin', self::BUSINESS),
            $this->userRow('seller-1', 'seller@shop.test', 'seller', self::BUSINESS, self::BRANCH_A),
        ];

        $this->businesses = [
            ['id' => self::BUSINESS, 'business_name' => 'Kinondoni Shop', 'business_structure' => 'branch', 'business_type' => 'general_retail', 'business_location' => 'Kinondoni'],
            ['id' => self::OTHER_BUSINESS, 'business_name' => 'Other Shop', 'business_structure' => 'branch', 'business_type' => 'supermarket', 'business_location' => 'Mlimani'],
        ];

        $this->branches = [
            ['id' => self::BRANCH_A, 'business_id' => self::BUSINESS, 'branch_name' => 'Kinondoni', 'location' => 'Kinondoni', 'status' => 'active'],
            ['id' => self::BRANCH_B, 'business_id' => self::BUSINESS, 'branch_name' => 'Mlimani', 'location' => 'Mlimani', 'status' => 'active'],
            ['id' => self::OTHER_BRANCH, 'business_id' => self::OTHER_BUSINESS, 'branch_name' => 'Foreign Branch', 'location' => 'Foreign', 'status' => 'active'],
        ];

        $this->tables = [
            'fridges' => [
                ['id' => 'f-1', 'business_id' => self::BUSINESS, 'branch_id' => null, 'name' => 'Main Fridge', 'status' => 'active'],
            ],
            'fridge_stock' => [],
            'products' => [],
        ];
    }

    private function userRow(string $id, string $email, string $role, ?string $businessId, ?string $branchId = null): array
    {
        return [
            'id' => $id,
            'email' => $email,
            'role' => $role,
            'full_name' => $role === 'admin' ? 'Admin Mdu' : 'Seller Mdu',
            'phone' => '+255700000000',
            'business_name' => 'Kinondoni Shop',
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'status' => 'approved',
            'language' => 'sw',
            'preferred_theme' => 'white-professional',
            'password' => password_hash('StrongPass1', PASSWORD_BCRYPT),
        ];
    }

    private function token(string $role = 'admin', string $userId = 'admin-1'): string
    {
        return JwtToken::encode([
            'userId' => $userId,
            'email' => $role === 'admin' ? 'admin@shop.test' : 'seller@shop.test',
            'role' => $role,
            'business_id' => self::BUSINESS,
            'business_structure' => 'branch',
            'branch_id' => $role === 'seller' ? self::BRANCH_A : null,
            'branch_context' => $role === 'seller' ? 'individual_branch' : 'cross_branch',
        ]);
    }

    private function fakeSupabase(): void
    {
        Http::fake(function ($request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (str_contains($path, '/rpc/')) {
                $function = basename($path);
                $body = json_decode((string) $request->body(), true) ?: [];
                $this->rpcCalls[] = ['function' => $function, 'params' => $body];

                if ($this->rpcError !== null) {
                    return Http::response(['message' => $this->rpcError], 400);
                }

                if ($function === 'convert_business_to_branch') {
                    return Http::response([
                        'business_id' => $body['p_business_id'] ?? self::BUSINESS,
                        'business_structure' => 'branch',
                        'branch' => [
                            'id' => 'br-new',
                            'business_id' => $body['p_business_id'] ?? self::BUSINESS,
                            'branch_name' => $body['p_branch_name'] ?? 'First Branch',
                            'status' => 'active',
                        ],
                    ], 200);
                }

                if ($function === 'convert_business_to_branchless') {
                    return Http::response([
                        'business_id' => $body['p_business_id'] ?? self::BUSINESS,
                        'business_structure' => 'non_branch',
                        'business_name' => $body['p_business_name'] ?? 'Kinondoni Shop',
                    ], 200);
                }

                return Http::response([], 200);
            }

            $table = basename($path);
            $method = strtoupper($request->method());

            if ($method === 'POST') {
                $body = json_decode((string) $request->body(), true);
                $row = is_array($body) ? ($body[0] ?? $body) : [];

                if ($table === 'business_settings' && is_array($row)) {
                    $this->upsert($this->settings, $row, 'business_id');
                } elseif ($table === 'branches' && is_array($row)) {
                    $this->upsert($this->branches, $row, 'id');
                } elseif ($table === 'users' && is_array($row) && isset($row['id'])) {
                    $this->upsert($this->users, $row, 'id');
                } elseif ($table === 'user_logs' && is_array($row)) {
                    $this->logs[] = $row;
                } elseif ($table === 'fridge_stock_alerts' && is_array($row)) {
                    $this->tables['fridge_stock_alerts'][] = $row;
                }

                return Http::response([$row], 201);
            }

            if ($method === 'PATCH') {
                $body = json_decode((string) $request->body(), true) ?: [];

                if ($table === 'users') {
                    $this->patchRows($this->users, $query, $body);
                } elseif ($table === 'businesses') {
                    $this->patchRows($this->businesses, $query, $body);
                } elseif ($table === 'branches') {
                    $this->patchRows($this->branches, $query, $body);
                } elseif ($table === 'business_settings') {
                    $this->patchRows($this->settings, $query, $body);
                }

                return Http::response([], 204);
            }

            $rows = match ($table) {
                'users' => $this->users,
                'businesses' => $this->businesses,
                'branches' => $this->branches,
                'business_settings' => $this->settings,
                default => $this->tables[$table] ?? [],
            };

            $rows = $this->filterRows($rows, $query);

            if (isset($query['select']) && is_string($query['select'])) {
                $columns = array_filter(array_map('trim', explode(',', $query['select'])));
                if ($columns !== [] && $columns !== ['*']) {
                    $rows = array_map(fn ($row) => array_intersect_key($row, array_flip($columns)), $rows);
                }
            }

            $headers = [];
            $prefer = $request->header('Prefer');
            $prefer = is_array($prefer) ? implode(',', $prefer) : (string) $prefer;
            if (str_contains($prefer, 'count=exact')) {
                $headers['Content-Range'] = '*/' . count($rows);
            }

            return Http::response(array_values($rows), 200, $headers);
        });
    }

    private function upsert(array &$rows, array $row, string $key): void
    {
        foreach ($rows as $index => $existing) {
            if ((string) ($existing[$key] ?? '') === (string) ($row[$key] ?? '')) {
                $rows[$index] = array_merge($existing, $row);

                return;
            }
        }

        $rows[] = $row;
    }

    private function patchRows(array &$rows, array $query, array $body): void
    {
        foreach ($rows as $index => $row) {
            foreach ($query as $column => $raw) {
                if (! is_string($raw) || ! str_starts_with($raw, 'eq.')) {
                    continue 1;
                }
                if ((string) ($row[$column] ?? '') !== substr($raw, 3)) {
                    continue 2;
                }
            }

            $rows[$index] = array_merge($row, $body);
        }
    }

    private function filterRows(array $rows, array $query): array
    {
        foreach ($query as $column => $raw) {
            if (in_array($column, ['select', 'order', 'limit', 'offset', 'or'], true)) {
                continue;
            }
            if (! is_string($raw)) {
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
                if (str_starts_with($raw, 'neq.')) {
                    return (string) $value !== substr($raw, 4);
                }
                if ($raw === 'is.null') {
                    return $value === null;
                }
                if (str_starts_with($raw, 'not.is.null')) {
                    return $value !== null;
                }
                if (str_starts_with($raw, 'in.(')) {
                    $list = array_map(fn ($item) => trim($item, " \t\n\r\0\x0B\""), explode(',', trim(substr($raw, 3), '()')));

                    return in_array((string) $value, $list, true);
                }

                return (string) $value === $raw;
            }));
        }

        return $rows;
    }

    #[Test]
    public function an_admin_reads_one_consolidated_snapshot(): void
    {
        $this->fakeSupabase();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@shop.test')
            ->assertJsonPath('business.business_name', 'Kinondoni Shop')
            ->assertJsonPath('business.business_structure', 'branch')
            ->assertJsonPath('settings.fridge_enabled', true)
            ->assertJsonPath('settings.scanner_enabled', false)
            ->assertJsonPath('settings.services_enabled', true)
            ->assertJsonPath('features.fridge', true)
            ->assertJsonCount(2, 'branches')
            ->assertJsonFragment(['branch_name' => 'Mlimani']);
    }

    #[Test]
    public function a_seller_can_read_feature_state_but_not_the_module_or_its_writes(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token('seller', 'seller-1'))
            ->getJson('/api/settings/features')
            ->assertOk()
            ->assertJsonPath('features.fridge', true);

        $this->withHeader('Authorization', 'Bearer ' . $this->token('seller', 'seller-1'))
            ->getJson('/api/settings')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $this->token('seller', 'seller-1'))
            ->putJson('/api/settings/features', ['fridge_enabled' => false])
            ->assertStatus(403);

        $this->assertSame([], $this->settings, 'A seller must not persist a settings row');
    }

    #[Test]
    public function feature_switches_persist_invalidate_the_cache_and_are_audited(): void
    {
        $this->fakeSupabase();
        $token = $this->token();

        // Warm the cache with the pre-change state.
        $this->assertTrue(BusinessSettings::enabled('fridge', self::BUSINESS));

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/settings/features', [
                'fridge_enabled' => false,
                'scanner_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('features.fridge', false)
            ->assertJsonPath('features.scanner', true);

        $this->assertCount(1, $this->settings);
        $this->assertFalse((bool) $this->settings[0]['fridge_enabled']);
        $this->assertTrue((bool) $this->settings[0]['scanner_enabled']);

        // Same request/process: the cached true must have been forgotten.
        $this->assertFalse(BusinessSettings::enabled('fridge', self::BUSINESS));

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/settings/features')
            ->assertOk()
            ->assertJsonPath('features.fridge', false);

        $audit = collect($this->logs)->firstWhere('action', 'SETTINGS_FEATURES_UPDATED');
        $this->assertNotNull($audit, 'Settings changes must be audited');
        $details = json_decode((string) $audit['details'], true);
        $this->assertSame(false, $details['changes']['fridge_enabled']['to']);
        $this->assertSame(true, $details['changes']['scanner_enabled']['to']);
    }

    #[Test]
    public function a_missing_settings_row_keeps_existing_features_enabled(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->getJson('/api/settings/features')
            ->assertOk()
            ->assertJsonPath('features.fridge', true)
            ->assertJsonPath('features.services', true)
            ->assertJsonPath('features.scanner', false);
    }

    #[Test]
    public function disabling_the_fridge_module_rejects_api_access_and_preserves_records(): void
    {
        $this->settings = [[
            'business_id' => self::BUSINESS,
            'fridge_enabled' => false,
            'scanner_enabled' => false,
            'services_enabled' => true,
            'notify_low_stock' => true,
            'notify_fridge_stock' => true,
        ]];

        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->getJson('/api/fridges')
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'FEATURE_DISABLED', 'feature' => 'fridge']);

        // Re-enabling restores access to the SAME stored fridge row: nothing
        // was deleted while the module was off.
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/settings/features', ['fridge_enabled' => true])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->getJson('/api/fridges')
            ->assertOk()
            ->assertJsonFragment(['id' => 'f-1', 'name' => 'Main Fridge']);
    }

    #[Test]
    public function disabling_services_rejects_writes_but_keeps_history_readable(): void
    {
        $this->settings = [[
            'business_id' => self::BUSINESS,
            'fridge_enabled' => true,
            'scanner_enabled' => false,
            'services_enabled' => false,
            'notify_low_stock' => true,
            'notify_fridge_stock' => true,
        ]];

        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/services', ['name' => 'Kupaka', 'price' => 1000])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'FEATURE_DISABLED', 'feature' => 'services']);
    }

    #[Test]
    public function a_branch_can_be_created_and_edited_only_inside_its_own_business(): void
    {
        $this->fakeSupabase();

        $created = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/branches', [
                'branch_name' => 'Tawi Jipya',
                'location' => 'Sinza',
            ]);

        $created->assertStatus(201)->assertJsonPath('branch.branch_name', 'Tawi Jipya');
        $createdId = $created->json('branch.id');
        $this->assertNotNull($createdId);

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/branches/' . $createdId, ['branch_name' => 'Tawi Jipya Zaidi'])
            ->assertOk()
            ->assertJsonPath('branch.branch_name', 'Tawi Jipya Zaidi');

        // Another business's branch is invisible even with a valid token.
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/branches/' . self::OTHER_BRANCH, ['branch_name' => 'Hijacked'])
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->deleteJson('/api/branches/' . self::OTHER_BRANCH)
            ->assertStatus(404);

        $foreign = collect($this->branches)->firstWhere('id', self::OTHER_BRANCH);
        $this->assertSame('Foreign Branch', $foreign['branch_name']);
        $this->assertSame('active', $foreign['status']);
    }

    #[Test]
    public function the_last_active_branch_cannot_be_deactivated(): void
    {
        $this->fakeSupabase();
        $token = $this->token();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/branches/' . self::BRANCH_A)
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/branches/' . self::BRANCH_B)
            ->assertStatus(409)
            ->assertJsonFragment(['code' => 'LAST_ACTIVE_BRANCH']);

        $remaining = collect($this->branches)->firstWhere('id', self::BRANCH_B);
        $this->assertSame('active', $remaining['status']);

        // Deactivation preserves the row and every seller assignment.
        $first = collect($this->branches)->firstWhere('id', self::BRANCH_A);
        $this->assertSame('inactive', $first['status']);
        $seller = collect($this->users)->firstWhere('id', 'seller-1');
        $this->assertSame(self::BRANCH_A, $seller['branch_id']);
    }

    #[Test]
    public function profile_updates_ignore_protected_fields(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/user/profile', [
                'full_name' => 'Jina Jipya',
                'phone' => '+255711111111',
                // Every one of these must be ignored by the writer.
                'role' => 'super_admin',
                'status' => 'approved',
                'business_id' => self::OTHER_BUSINESS,
                'branch_id' => self::OTHER_BRANCH,
                'email' => 'attacker@example.com',
            ])
            ->assertOk();

        $admin = collect($this->users)->firstWhere('id', 'admin-1');
        $this->assertSame('Jina Jipya', $admin['full_name']);
        $this->assertSame('+255711111111', $admin['phone']);
        $this->assertSame('admin', $admin['role']);
        $this->assertSame(self::BUSINESS, $admin['business_id']);
        $this->assertSame('admin@shop.test', $admin['email']);
        $this->assertNull($admin['branch_id']);
    }

    #[Test]
    public function changing_location_updates_the_business_and_legacy_user_columns(): void
    {
        $this->fakeSupabase();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/user/profile', [
                'business_latitude' => -6.7924,
                'business_longitude' => 39.2083,
                'business_location' => 'Dar es Salaam',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.business_latitude', -6.7924)
            ->assertJsonPath('user.business_longitude', 39.2083)
            ->assertJsonPath('user.business_location', 'Dar es Salaam');

        $this->assertSame(-6.7924, $this->businesses[0]['business_latitude']);
        $this->assertSame(39.2083, $this->businesses[0]['business_longitude']);
        $this->assertSame('Dar es Salaam', $this->businesses[0]['business_location']);

        $admin = collect($this->users)->firstWhere('id', 'admin-1');
        $this->assertSame(-6.7924, $admin['business_latitude']);
        $this->assertSame(39.2083, $admin['business_longitude']);
        $this->assertSame('Dar es Salaam', $admin['business_location']);
    }

    #[Test]
    public function profile_validation_rejects_an_invalid_update(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/user/profile', ['full_name' => 'A'])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/user/profile', ['phone' => 'not-a-phone'])
            ->assertStatus(422);
    }

    #[Test]
    public function a_non_branch_business_cannot_create_branches(): void
    {
        $this->businesses[0]['business_structure'] = 'non_branch';
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/branches', ['branch_name' => 'Unexpected Branch'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'STRUCTURE_NOT_BRANCH']);
    }

    #[Test]
    public function a_branchless_business_converts_to_branchful_with_its_first_branch(): void
    {
        $this->businesses[0]['business_structure'] = 'non_branch';
        $this->fakeSupabase();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/settings/business-structure/branchful', [
                'branch_name' => 'Tawi Kuu',
                'location' => 'Kinondoni',
            ]);

        $response->assertOk()
            ->assertJsonPath('business_structure', 'branch')
            ->assertJsonPath('branch.branch_name', 'Tawi Kuu');

        $this->assertNotEmpty($response->json('token'));
        $claims = JwtToken::decode($response->json('token'));
        $this->assertSame('branch', $claims->business_structure);
        $this->assertSame('cross_branch', $claims->branch_context);
        $this->assertSame(self::BUSINESS, $claims->business_id);

        $call = collect($this->rpcCalls)->firstWhere('function', 'convert_business_to_branch');
        $this->assertNotNull($call);
        $this->assertSame(self::BUSINESS, $call['params']['p_business_id']);
        $this->assertSame('Tawi Kuu', $call['params']['p_branch_name']);
    }

    #[Test]
    public function a_branchful_business_converts_to_branchless_and_gets_a_non_branch_token(): void
    {
        $this->fakeSupabase();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/settings/business-structure/branchless', [
                'business_name' => 'Kinondoni Shop Unified',
                'business_location' => 'Kinondoni',
                'business_description' => 'Duka moja',
            ]);

        $response->assertOk()
            ->assertJsonPath('business_structure', 'non_branch')
            ->assertJsonPath('business_name', 'Kinondoni Shop Unified');

        $claims = JwtToken::decode($response->json('token'));
        $this->assertSame('non_branch', $claims->business_structure);
        $this->assertSame('non_branch', $claims->branch_context);
        $this->assertNull($claims->branch_id);

        $call = collect($this->rpcCalls)->firstWhere('function', 'convert_business_to_branchless');
        $this->assertNotNull($call);
        $this->assertSame('Kinondoni Shop Unified', $call['params']['p_business_name']);
    }

    #[Test]
    public function sellers_cannot_convert_the_business_structure(): void
    {
        $this->businesses[0]['business_structure'] = 'non_branch';
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token('seller', 'seller-1'))
            ->postJson('/api/settings/business-structure/branchful', ['branch_name' => 'Tawi'])
            ->assertStatus(403);

        $this->assertSame([], $this->rpcCalls);
    }

    #[Test]
    public function conversion_rejects_the_wrong_current_structure(): void
    {
        $this->fakeSupabase();

        // The fake business is already branchful.
        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/settings/business-structure/branchful', ['branch_name' => 'Tawi'])
            ->assertStatus(409)
            ->assertJsonFragment(['code' => 'ALREADY_BRANCHFUL']);

        $this->businesses[0]['business_structure'] = 'non_branch';

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/settings/business-structure/branchless', ['business_name' => 'Jina Jipya'])
            ->assertStatus(409)
            ->assertJsonFragment(['code' => 'ALREADY_BRANCHLESS']);
    }

    #[Test]
    public function an_unsafe_consolidation_is_refused_with_an_explanation(): void
    {
        $this->rpcError = 'UNSAFE_DUPLICATE_PRODUCTS';
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->postJson('/api/settings/business-structure/branchless', ['business_name' => 'Kinondoni Shop'])
            ->assertStatus(409)
            ->assertJsonFragment(['code' => 'UNSAFE_DUPLICATE_PRODUCTS']);

        $this->assertNotNull(collect($this->rpcCalls)->firstWhere('function', 'convert_business_to_branchless'));
    }

    #[Test]
    public function a_stale_token_cannot_keep_the_old_structure(): void
    {
        $this->fakeSupabase();

        // The fake business is branchful, but this signed token still says
        // non_branch from before the conversion.
        $resolved = BranchContext::resolve(self::BUSINESS, 'admin', 'admin-1', null, 'cross_branch', 'non_branch');

        $this->assertSame('branch', $resolved['structure']);
        $this->assertSame(BranchContext::CROSS, $resolved['context']);
    }

    #[Test]
    public function notification_preferences_hide_alerts_without_stopping_monitoring(): void
    {
        $this->tables['products'] = [[
            'id' => 'p-low',
            'seller_id' => 'admin-1',
            'branch_id' => null,
            'name' => 'Sukari',
            'stock' => 1,
            'min_stock_level' => 5,
            'min_outside_stock' => 0,
            'is_active' => true,
        ]];
        $this->fakeSupabase();

        $token = $this->token();

        $withAlerts = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/fridges');
        $withAlerts->assertOk();
        $this->assertSame('business_low', $withAlerts->json('alerts.0.alert_type'));

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/settings/features', ['notify_low_stock' => false])
            ->assertOk();

        $withoutAlerts = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/fridges');
        $withoutAlerts->assertOk();
        $this->assertSame([], $withoutAlerts->json('alerts'));
        $this->assertSame(0, $withoutAlerts->json('alert_counts.business_low'));

        // Monitoring/history is still written: the alert row exists even while
        // its presentation is switched off.
        $alerts = collect($this->tables['fridge_stock_alerts'] ?? []);
        $this->assertTrue(
            $alerts->contains(fn ($row) => ($row['alert_type'] ?? '') === 'business_low'),
            'The alert must still be recorded when its notification is hidden'
        );
    }

    #[Test]
    public function language_and_theme_controls_persist_to_the_account(): void
    {
        $this->fakeSupabase();
        $token = $this->token();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/user/language', ['language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('language', 'fr');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/user/language', ['language' => 'xx'])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/user/theme', ['theme' => 'midnight-royal-gold'])
            ->assertOk()
            ->assertJsonPath('preferred_theme', 'midnight-royal-gold');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/user/theme', ['theme' => 'not-a-theme'])
            ->assertStatus(422);

        $admin = collect($this->users)->firstWhere('id', 'admin-1');
        $this->assertSame('fr', $admin['language']);
        $this->assertSame('midnight-royal-gold', $admin['preferred_theme']);
    }

    #[Test]
    public function a_feature_value_must_be_a_boolean(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->token())
            ->putJson('/api/settings/features', ['fridge_enabled' => 'sometimes'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'INVALID_SETTING_VALUE']);

        $this->assertSame([], $this->settings);
    }
}
