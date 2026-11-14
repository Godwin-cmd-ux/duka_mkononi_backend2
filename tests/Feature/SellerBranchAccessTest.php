<?php

namespace Tests\Feature;

use App\Services\BranchContext;
use App\Services\JwtToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Seller branch registration and login must be strict.
 *
 * A seller belongs to exactly one branch of an existing business:
 *
 *   TEST A  - a seller registering with a business id and an active branch id
 *             persists both, and (once approved) logs straight into that branch;
 *   TEST B  - a seller whose assigned branch is inactive is refused at login;
 *   TEST C  - a seller never sees another branch's rows;
 *   TEST D  - a shared (branch_id NULL) row is not a bypass into other branches;
 *   TEST G  - the whole flow never breaks an existing non-branch seller.
 *
 * The fake below reproduces PostgREST's query contract (eq./ilike./in.(...)),
 * not just bare values, so a production filter that stops working shows up as
 * a failing test instead of passing against a fake that ignored it.
 */
class SellerBranchAccessTest extends TestCase
{
    use RefreshDatabase;

    private const THE_BUSINESS = 'b-1';
    private const KINONDI = 'kin-doni';
    private const MLIMANI = 'mlimani';
    private const NON_BRANCH_BUSINESS = 'b-2';

    /** @var list<array<string,mixed>> */
    private array $userRows = [];

    /** @var list<array<string,mixed>> */
    private array $businessRows = [];

    /** @var list<array<string,mixed>> */
    private array $branchRows = [];

    /** @var array<string,list<array<string,mixed>>> */
    private array $tableRows = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'supabase.url' => 'http://supabase.test',
            'supabase.service_role_key' => 'service-role-key',
            'jwt.secret' => 'test-secret',
        ]);

        Mail::fake();
    }

    private function makeUser(array $overrides = []): array
    {
        return array_merge([
            'id' => 'admin-1',
            'email' => 'admin@dupekononi.test',
            'role' => 'admin',
            'full_name' => 'Admin Mdu',
            'phone' => '',
            'business_name' => 'Kinondoni Supermarket',
            'business_location' => '',
            'status' => 'approved',
            'business_id' => self::THE_BUSINESS,
            'branch_id' => null,
            'is_online' => true,
            'language' => 'sw',
        ], $overrides);
    }

    private function sellerRow(string $id, string $branchId, string $email, array $overrides = []): array
    {
        return $this->makeUser(array_merge([
            'id' => $id,
            'role' => 'seller',
            'full_name' => 'Seller ' . $id,
            'email' => $email,
            'business_name' => 'Kinondoni Supermarket',
            'business_id' => self::THE_BUSINESS,
            'branch_id' => $branchId,
            'status' => 'approved',
            'password' => password_hash('StrongPass1', PASSWORD_BCRYPT),
        ], $overrides));
    }

    /**
     * Serve the rows the app would read from Supabase.
     *
     * @param array<string,list<array<string,mixed>>> $tables operational rows
     * @param string $branchStatus status of the Kinondoni branch
     */
    private function fakeSupabase(array $tables = [], string $branchStatus = 'active'): void
    {
        $this->businessRows = [
            [
                'id' => self::THE_BUSINESS,
                'legacy_name_key' => 'kinondoni supermarket',
                'business_name' => 'Kinondoni Supermarket',
                'business_structure' => 'branch',
            ],
            [
                'id' => self::NON_BRANCH_BUSINESS,
                'legacy_name_key' => 'non branch shop',
                'business_name' => 'Non Branch Shop',
                'business_structure' => 'non_branch',
            ],
        ];

        $this->branchRows = [
            [
                'id' => self::KINONDI,
                'business_id' => self::THE_BUSINESS,
                'branch_name' => 'Kinondoni Branch',
                'location' => 'Kinondoni',
                'latitude' => null,
                'longitude' => null,
                'status' => $branchStatus,
            ],
            [
                'id' => self::MLIMANI,
                'business_id' => self::THE_BUSINESS,
                'branch_name' => 'Mlimani Branch',
                'location' => 'Mlimani',
                'latitude' => null,
                'longitude' => null,
                'status' => 'active',
            ],
        ];

        $this->userRows = [
            $this->makeUser(),
            $this->sellerRow(self::KINONDI, self::KINONDI, 'seller@kinondoni.test'),
            $this->sellerRow(self::MLIMANI, self::MLIMANI, 'seller@mlimani.test'),
            $this->makeUser([
                'id' => 'legacy-admin',
                'email' => 'admin@nonbranch.test',
                'full_name' => 'Legacy Admin',
                'business_name' => 'Non Branch Shop',
                'business_id' => self::NON_BRANCH_BUSINESS,
            ]),
            $this->makeUser([
                'id' => 'legacy-seller',
                'role' => 'seller',
                'full_name' => 'Legacy',
                'email' => 'legacy-seller@example.com',
                'business_name' => 'Non Branch Shop',
                'business_id' => self::NON_BRANCH_BUSINESS,
                'branch_id' => null,
                'status' => 'approved',
                'password' => password_hash('StrongPass1', PASSWORD_BCRYPT),
            ]),
        ];

        $this->tableRows = $tables;

        Http::fake(function ($request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (str_contains($path, '/rpc/')) {
                return Http::response([
                    'created' => true,
                    'business' => $this->businessRows[0],
                    'branches' => [$this->branchRows[0]],
                ], 200);
            }

            $table = basename($path);

            if ($table === 'users' && $request->method() === 'POST') {
                $row = json_decode((string) $request->body(), true) ?? [];
                $row = $row['user'] ?? $row;

                if (is_array($row) && isset($row['id'])) {
                    $this->userRows = array_values(array_filter(
                        $this->userRows,
                        fn ($existing) => (string) ($existing['id'] ?? '') !== (string) $row['id']
                    ));
                    $this->userRows[] = $row;

                    return Http::response([$row], 201);
                }

                return Http::response([], 204);
            }

            $rows = match ($table) {
                'users' => $this->userRows,
                'businesses' => $this->businessRows,
                'branches' => $this->branchRows,
                default => $this->tableRows[$table] ?? [],
            };

            $rows = $this->applyPostgrestFilters($rows, $query);

            if (isset($query['select']) && is_string($query['select'])) {
                $columns = array_filter(array_map('trim', explode(',', $query['select'])));
                if ($columns !== [] && $columns !== ['*']) {
                    $rows = array_map(
                        fn ($row) => array_intersect_key($row, array_flip($columns)),
                        $rows
                    );
                }
            }

            return Http::response(array_values($rows), 200);
        });
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $query
     * @return list<array<string,mixed>>
     */
    private function applyPostgrestFilters(array $rows, array $query): array
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
                    if (is_bool($value) && in_array($expected, ['true', 'false', '1', '0'], true)) {
                        return $value === in_array($expected, ['true', '1'], true);
                    }

                    return (string) $value === $expected;
                }
                if (str_starts_with($raw, 'neq.')) {
                    return (string) $value !== substr($raw, 4);
                }
                if (str_starts_with($raw, 'not.is.null')) {
                    return $value !== null;
                }
                if (str_starts_with($raw, 'is.null')) {
                    return $value === null;
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
                if (str_starts_with($raw, 'not.in.(')) {
                    $list = array_map(
                        fn ($item) => trim($item, " \t\n\r\0\x0B\""),
                        explode(',', trim(substr($raw, 7), '()'))
                    );

                    return ! in_array((string) $value, $list, true);
                }

                return (string) $value === $raw;
            }));
        }

        return $rows;
    }

    private function tokenFor(string $userId, string $email, string $role, ?string $businessId, string $structure, ?string $branchId, string $context): string
    {
        return JwtToken::encode([
            'userId' => $userId,
            'email' => $email,
            'role' => $role,
            'business_id' => $businessId,
            'business_structure' => $structure,
            'branch_id' => $branchId,
            'branch_context' => $context,
        ]);
    }

    #[Test]
    public function a_new_seller_is_bound_to_one_active_branch_of_an_existing_business(): void
    {
        $this->fakeSupabase();

        $initiate = $this->postJson('/api/register/initiate', [
            'email' => 'new-seller@kinondoni.test',
            'password' => 'StrongPass1',
            'role' => 'seller',
            'full_name' => 'New Kin',
            'phone' => '255700000001',
            'business_id' => self::THE_BUSINESS,
            'branch_id' => self::KINONDI,
            'business_name' => 'Kinondoni Supermarket',
            'business_structure' => 'branch',
        ]);

        $initiate->assertStatus(200)->assertJsonFragment(['requiresOtp' => true]);

        $pending = Cache::get('registration_otp:new-seller@kinondoni.test:seller');
        $this->assertNotNull($pending, 'Registration OTP should be cached for the seller');

        $verified = $this->postJson('/api/register/verify-otp', [
            'email' => 'new-seller@kinondoni.test',
            'role' => 'seller',
            'otp' => $pending['otp'],
        ]);

        $verified->assertStatus(200);
        $registrationToken = $verified->json('registrationToken');
        $this->assertNotEmpty($registrationToken);

        $registered = $this->postJson('/api/register', [
            'verificationToken' => $registrationToken,
        ]);

        $registered->assertStatus(201);

        $persisted = collect($this->userRows)->firstWhere('email', 'new-seller@kinondoni.test');
        $this->assertNotNull($persisted, 'Registration should persist the seller row');
        $this->assertSame(self::THE_BUSINESS, $persisted['business_id']);
        $this->assertSame(self::KINONDI, $persisted['branch_id']);

        // A new seller is pending until their administrator approves them.
        $this->postJson('/api/login', [
            'email' => 'new-seller@kinondoni.test',
            'password' => 'StrongPass1',
            'role' => 'seller',
        ])->assertStatus(401);

        // Approve the seller, then login must land straight in their branch.
        $this->userRows = array_map(function ($row) {
            if (($row['email'] ?? '') === 'new-seller@kinondoni.test') {
                $row['status'] = 'approved';
            }

            return $row;
        }, $this->userRows);

        $login = $this->postJson('/api/login', [
            'email' => 'new-seller@kinondoni.test',
            'password' => 'StrongPass1',
            'role' => 'seller',
        ]);

        $login->assertStatus(200)
            ->assertJsonFragment(['business_id' => self::THE_BUSINESS])
            ->assertJsonFragment(['branch_id' => self::KINONDI])
            ->assertJsonFragment(['branch_name' => 'Kinondoni Branch'])
            ->assertJsonFragment(['branch_context' => BranchContext::INDIVIDUAL]);
    }

    #[Test]
    public function an_inactive_or_missing_seller_branch_is_rejected_at_login(): void
    {
        $this->fakeSupabase(branchStatus: 'inactive');

        $response = $this->postJson('/api/login', [
            'email' => 'seller@kinondoni.test',
            'password' => 'StrongPass1',
            'role' => 'seller',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('BRANCH_UNAVAILABLE', $response->getContent());
        $this->assertStringContainsString('Tawi ulilopewa halipatikani au limezimwa', $response->getContent());
    }

    #[Test]
    public function a_seller_cannot_read_another_branches_records(): void
    {
        $this->fakeSupabase([
            'products' => [
                ['id' => 'p-1', 'name' => 'Kinondoni Product', 'is_active' => true, 'seller_id' => self::KINONDI, 'branch_id' => self::KINONDI],
                ['id' => 'p-2', 'name' => 'Mlimani Product', 'is_active' => true, 'seller_id' => self::MLIMANI, 'branch_id' => self::MLIMANI],
            ],
        ]);

        $token = $this->tokenFor(
            self::KINONDI,
            'seller@kinondoni.test',
            'seller',
            self::THE_BUSINESS,
            'branch',
            self::KINONDI,
            BranchContext::INDIVIDUAL
        );

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/products/my')
            ->assertOk()
            ->assertJsonFragment(['id' => 'p-1'])
            ->assertJsonMissing(['id' => 'p-2']);
    }

    #[Test]
    public function a_seller_receives_businesswide_rows_only_for_their_own_branch(): void
    {
        $this->fakeSupabase([
            'products' => [
                ['id' => 'p-1', 'name' => 'Kinondoni Product', 'is_active' => true, 'seller_id' => self::KINONDI, 'branch_id' => self::KINONDI],
                ['id' => 'p-shared', 'name' => 'Shared Product', 'is_active' => true, 'seller_id' => self::KINONDI, 'branch_id' => null],
            ],
        ]);

        $token = $this->tokenFor(
            self::KINONDI,
            'seller@kinondoni.test',
            'seller',
            self::THE_BUSINESS,
            'branch',
            self::KINONDI,
            BranchContext::INDIVIDUAL
        );

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/products/my')
            ->assertOk();

        $ids = array_column($response->json(), 'id');
        $this->assertContains('p-1', $ids);
        $this->assertNotContains('p-shared', $ids, 'A NULL branch_id must not bypass branch scoping');
    }

    #[Test]
    public function a_seller_cannot_escalate_to_a_cross_branch_context(): void
    {
        $this->fakeSupabase([
            'products' => [
                ['id' => 'p-1', 'name' => 'Kinondoni Product', 'is_active' => true, 'seller_id' => self::KINONDI, 'branch_id' => self::KINONDI],
                ['id' => 'p-2', 'name' => 'Mlimani Product', 'is_active' => true, 'seller_id' => self::MLIMANI, 'branch_id' => self::MLIMANI],
            ],
        ]);

        // Even a signed token claiming cross_branch must be reduced to the
        // seller's own assignment: sellers never choose a branch.
        $token = $this->tokenFor(
            self::KINONDI,
            'seller@kinondoni.test',
            'seller',
            self::THE_BUSINESS,
            'branch',
            null,
            BranchContext::CROSS
        );

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/products/my')
            ->assertOk()
            ->assertJsonFragment(['id' => 'p-1'])
            ->assertJsonMissing(['id' => 'p-2']);
    }

    #[Test]
    public function an_existing_non_branch_account_is_not_touched_by_branch_rules(): void
    {
        $this->fakeSupabase();

        $response = $this->postJson('/api/register/initiate', [
            'email' => 'new-legacy-seller@example.com',
            'password' => 'StrongPass1',
            'role' => 'seller',
            'full_name' => 'Legacy',
            'phone' => '',
            'business_name' => 'Non Branch Shop',
            'business_structure' => 'non_branch',
        ]);

        $response->assertStatus(200)->assertJsonFragment(['requiresOtp' => true]);

        $token = $this->tokenFor(
            'legacy-seller',
            'legacy-seller@example.com',
            'seller',
            null,
            'non_branch',
            null,
            BranchContext::NON_BRANCH
        );

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/products/my')
            ->assertOk();
    }
}
