<?php

namespace Tests\Feature;

use App\Services\BranchContext;
use App\Services\JwtToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Scanner behaviour is asserted end-to-end through the HTTP API, against a
 * stateful fake of PostgREST + the QR database functions. The fake enforces the
 * same rules as the SQL (unique token, one location, reserved/sold states,
 * quantity == scanned units, business/branch scope), so a controller that
 * forgets a check fails here.
 *
 * The real enforcement lives in
 * supabase/migrations/20261115000001_create_product_items_and_scanner.sql.
 */
class QrScannerTest extends TestCase
{
    use RefreshDatabase;

    private const BUSINESS = 'b-1';
    private const BRANCH = 'branch-1';
    private const ADMIN = 'admin-1';
    private const SELLER = 'seller-1';
    private const CLIENT_REF = 'cart-ref-1';

    /** @var list<array<string,mixed>> */
    private array $users = [];
    /** @var list<array<string,mixed>> */
    private array $products = [];
    /** @var list<array<string,mixed>> */
    private array $items = [];
    /** @var list<array<string,mixed>> */
    private array $sessions = [];
    /** @var list<array<string,mixed>> */
    private array $reservations = [];
    /** @var list<array<string,mixed>> */
    private array $fridges = [];
    /** @var list<array<string,mixed>> */
    private array $fridgeStock = [];
    /** @var list<array<string,mixed>> */
    private array $sales = [];
    /** @var list<array<string,mixed>> */
    private array $saleItems = [];
    /** @var list<array<string,mixed>> */
    private array $rpcCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'supabase.url' => 'http://supabase.test',
            'supabase.service_role_key' => 'service-role-key',
            'jwt.secret' => 'test-secret',
        ]);

        Cache::flush();
    }

    /* ------------------------------------------------------------------ */
    /* Fake Supabase                                                       */
    /* ------------------------------------------------------------------ */

    private function fakeSupabase(bool $scannerEnabled = true): void
    {
        $this->users = [
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
            [
                'id' => 'foreign-admin',
                'email' => 'foreign@elsewhere.test',
                'role' => 'admin',
                'full_name' => 'Foreign',
                'business_name' => 'Elsewhere',
                'business_id' => 'b-other',
                'branch_id' => null,
                'status' => 'approved',
                'language' => 'sw',
            ],
        ];

        $this->products = [
            [
                'id' => 'p-1',
                'seller_id' => self::ADMIN,
                'business_id' => self::BUSINESS,
                'name' => 'Coca-Cola',
                'category' => 'Drinks',
                'price' => 1000,
                'expected_selling_price' => 1500,
                'stock' => 10,
                'branch_id' => self::BRANCH,
                'is_active' => true,
            ],
            [
                'id' => 'p-2',
                'seller_id' => self::SELLER,
                'business_id' => self::BUSINESS,
                'name' => 'Water',
                'category' => 'Drinks',
                'price' => 2000,
                'expected_selling_price' => 2500,
                'stock' => 5,
                'branch_id' => self::BRANCH,
                'is_active' => true,
            ],
            [
                'id' => 'p-branch2',
                'seller_id' => self::ADMIN,
                'business_id' => self::BUSINESS,
                'name' => 'Branch Only',
                'category' => 'Drinks',
                'price' => 500,
                'expected_selling_price' => 700,
                'stock' => 3,
                'branch_id' => 'branch-2',
                'is_active' => true,
            ],
            [
                'id' => 'p-foreign',
                'seller_id' => 'foreign-admin',
                'business_id' => 'b-other',
                'name' => 'Foreign',
                'category' => 'Drinks',
                'price' => 100,
                'expected_selling_price' => 200,
                'stock' => 9,
                'branch_id' => self::BRANCH,
                'is_active' => true,
            ],
            [
                'id' => 'p-untracked',
                'seller_id' => self::ADMIN,
                'business_id' => self::BUSINESS,
                'name' => 'Untracked',
                'category' => 'Drinks',
                'price' => 100,
                'expected_selling_price' => 200,
                'stock' => 0,
                'branch_id' => self::BRANCH,
                'is_active' => true,
            ],
        ];

        $this->items = [
            $this->item('11111111-1111-4111-8111-111111111111', 'A001', 'p-1'),
            $this->item('22222222-2222-4222-8222-222222222222', 'A002', 'p-1'),
            $this->item('33333333-3333-4333-8333-333333333333', 'A003', 'p-1'),
            $this->item('44444444-4444-4444-8444-444444444444', 'B001', 'p-2'),
            $this->item('55555555-5555-4555-8555-555555555555', 'SOLD01', 'p-1', ['status' => 'sold', 'sale_id' => 'sale-old', 'sold_at' => '2026-01-01T00:00:00Z']),
            $this->item('66666666-6666-4666-8666-666666666666', 'FR001', 'p-1', ['location' => 'fridge', 'fridge_id' => 'fridge-1']),
            $this->item('77777777-7777-4777-8777-777777777777', 'OTHER01', 'p-1', ['business_id' => 'b-other']),
            $this->item('88888888-8888-4888-8888-888888888888', 'BR2-01', 'p-branch2', ['branch_id' => 'branch-2']),
        ];

        $this->sessions = [];
        $this->reservations = [];

        $this->fridges = [
            ['id' => 'fridge-1', 'business_id' => self::BUSINESS, 'branch_id' => self::BRANCH, 'name' => 'Main Fridge', 'status' => 'active'],
            ['id' => 'fridge-2', 'business_id' => self::BUSINESS, 'branch_id' => self::BRANCH, 'name' => 'Back Fridge', 'status' => 'active'],
        ];

        $this->fridgeStock = [
            ['id' => 'fs-1', 'fridge_id' => 'fridge-1', 'product_id' => 'p-1', 'business_id' => self::BUSINESS, 'branch_id' => self::BRANCH, 'quantity' => 1, 'min_stock' => 0],
        ];

        $this->sales = [];
        $this->saleItems = [];
        $this->rpcCalls = [];

        $settings = [[
            'business_id' => self::BUSINESS,
            'fridge_enabled' => true,
            'scanner_enabled' => $scannerEnabled,
            'services_enabled' => true,
            'notify_low_stock' => true,
            'notify_fridge_stock' => true,
        ]];

        $businesses = [[
            'id' => self::BUSINESS,
            'business_name' => 'Shop',
            'legacy_name_key' => 'shop',
            'business_structure' => 'branch',
        ]];

        $branches = [
            ['id' => self::BRANCH, 'business_id' => self::BUSINESS, 'branch_name' => 'Main', 'location' => 'Dar', 'status' => 'active'],
            ['id' => 'branch-2', 'business_id' => self::BUSINESS, 'branch_name' => 'Second', 'location' => 'Mwanza', 'status' => 'active'],
        ];

        Http::fake(function (Request $request) use ($settings, $businesses, $branches) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $method = strtoupper($request->method());

            if (preg_match('#/rpc/([a-z_]+)$#', $path, $m)) {
                $body = json_decode((string) $request->body(), true) ?? [];
                $this->rpcCalls[] = ['fn' => $m[1], 'params' => $body];
                [$payload, $status] = $this->handleRpc($m[1], $body);

                return Http::response($payload, $status);
            }

            $table = basename($path);

            $rows = match ($table) {
                'users' => $this->filter($this->users, $query),
                'businesses' => $this->filter($businesses, $query),
                'branches' => $this->filter($branches, $query),
                'business_settings' => $this->filter($settings, $query),
                'products' => $this->filter($this->products, $query),
                'product_items' => $this->filter($this->items, $query),
                'scanner_receiving_sessions' => $this->filter($this->sessions, $query),
                'product_item_reservations' => $this->filter($this->reservations, $query),
                'fridges' => $this->filter($this->fridges, $query),
                'fridge_stock' => $this->filter($this->fridgeStock, $query),
                'sales' => $this->filter($this->sales, $query),
                'sale_items' => $this->filter($this->saleItems, $query),
                'customers' => [],
                'business_blocks' => [],
                default => [],
            };

            if ($method === 'POST') {
                $body = json_decode((string) $request->body(), true) ?? [];

                if ($table === 'products') {
                    $this->products[] = $body;

                    return Http::response([$body], 201);
                }
                if ($table === 'sales') {
                    $this->sales[] = $body;

                    return Http::response([$body], 201);
                }
                if ($table === 'sale_items') {
                    foreach (is_array($body) && array_is_list($body) ? $body : [$body] as $row) {
                        $this->saleItems[] = $row;
                    }

                    return Http::response([], 201);
                }

                return Http::response([$body], 201);
            }

            if ($method === 'PATCH') {
                $body = json_decode((string) $request->body(), true) ?? [];
                foreach ($rows as $matched) {
                    foreach ($this->products as $i => $product) {
                        if ($table === 'products' && (string) $product['id'] === (string) $matched['id']) {
                            $this->products[$i] = array_merge($this->products[$i], $body);
                        }
                    }
                    foreach ($this->users as $i => $user) {
                        if ($table === 'users' && (string) $user['id'] === (string) $matched['id']) {
                            $this->users[$i] = array_merge($this->users[$i], $body);
                        }
                    }
                }

                return Http::response([], 204);
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

    /** @return array<string,mixed> */
    private function item(string $id, string $token, string $productId, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'business_id' => $overrides['business_id'] ?? self::BUSINESS,
            'branch_id' => self::BRANCH,
            'product_id' => $productId,
            'qr_token' => $token,
            'status' => 'in_stock',
            'location' => 'outside',
            'fridge_id' => null,
            'receiving_session_id' => null,
            'receipt_reference' => null,
            'sale_id' => null,
            'received_at' => '2026-01-01T00:00:00Z',
            'sold_at' => null,
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-01-01T00:00:00Z',
        ], $overrides);
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
                // The client turns '%' into '*' (PostgREST's wildcard), so both
                // spellings mean "contains".
                if (str_starts_with($raw, 'like.')) {
                    return stripos((string) $value, str_replace(['%', '*'], '', substr($raw, 5))) !== false;
                }
                if (str_starts_with($raw, 'ilike.')) {
                    $needle = str_replace(['%', '*'], '', substr($raw, 6));

                    return $needle === '' || stripos((string) $value, $needle) !== false;
                }
                if (str_starts_with($raw, 'in.(')) {
                    $list = array_map(
                        fn ($entry) => trim($entry, " \t\n\r\0\x0B\""),
                        explode(',', trim(substr($raw, 3), '()'))
                    );

                    return in_array((string) $value, $list, true);
                }

                return (string) $value === $raw;
            }));
        }

        return $rows;
    }

    /* ------------------------------------------------------------------ */
    /* Fake database functions (same rules as the SQL migration)           */
    /* ------------------------------------------------------------------ */

    /** @return array{0:array<string,mixed>|list<array<string,mixed>>,1:int} */
    private function handleRpc(string $fn, array $p): array
    {
        return match ($fn) {
            'qr_open_receiving_session' => $this->rpcOpenSession($p),
            'qr_receive_item' => $this->rpcReceiveItem($p),
            'qr_complete_receiving' => $this->rpcCompleteReceiving($p),
            'qr_cancel_receiving' => $this->rpcCancelReceiving($p),
            'qr_reserve_item' => $this->rpcReserveItem($p),
            'qr_release_sale_items' => $this->rpcReleaseItems($p),
            'qr_commit_sale' => $this->rpcCommitSale($p),
            'qr_move_item_to_fridge' => $this->rpcMoveToFridge($p),
            'qr_move_item_out_of_fridge' => $this->rpcMoveOutOfFridge($p),
            'qr_item_stock_summary' => $this->rpcSummary($p),
            'receive_product_stock' => $this->rpcReceiveStock($p),
            default => [['message' => 'Could not find the function public.' . $fn], 404],
        };
    }

    /** @return array{0:array<string,mixed>,1:int} */
    private function rpcError(string $message, int $status = 422): array
    {
        return [['message' => $message, 'code' => 'P0001'], $status];
    }

    private function userRow(string $id): ?array
    {
        foreach ($this->users as $user) {
            if ((string) $user['id'] === $id) {
                return $user;
            }
        }

        return null;
    }

    private function itemIndex(string $id): ?int
    {
        foreach ($this->items as $i => $item) {
            if ((string) $item['id'] === $id) {
                return $i;
            }
        }

        return null;
    }

    private function itemByToken(string $token): ?int
    {
        foreach ($this->items as $i => $item) {
            if (mb_strtolower((string) $item['qr_token']) === mb_strtolower(trim($token))) {
                return $i;
            }
        }

        return null;
    }

    private function productIndex(string $id): ?int
    {
        foreach ($this->products as $i => $product) {
            if ((string) $product['id'] === $id) {
                return $i;
            }
        }

        return null;
    }

    private function fridgeStockIndex(string $fridgeId, string $productId): ?int
    {
        foreach ($this->fridgeStock as $i => $row) {
            if ((string) $row['fridge_id'] === $fridgeId && (string) $row['product_id'] === $productId) {
                return $i;
            }
        }

        return null;
    }

    private function reservationFor(string $itemId): ?array
    {
        foreach ($this->reservations as $row) {
            if ((string) $row['product_item_id'] === $itemId && $row['released_at'] === null) {
                return $row;
            }
        }

        return null;
    }

    private function assertWritable(string $userId, string $businessId): ?array
    {
        $user = $this->userRow($userId);
        if (! $user || $user['status'] !== 'approved' || (string) $user['business_id'] !== $businessId) {
            return $this->rpcError('Huna ruhusa ya kufanya operesheni hii.', 42501);
        }

        return null;
    }

    private function rpcOpenSession(array $p): array
    {
        $businessId = (string) ($p['p_business_id'] ?? '');
        $userId = (string) ($p['p_user_id'] ?? '');
        $productId = (string) ($p['p_product_id'] ?? '');
        $branchId = $p['p_branch_id'] ?? null;

        if ($deny = $this->assertWritable($userId, $businessId)) {
            return $deny;
        }

        $user = $this->userRow($userId);
        if (! in_array($user['role'], ['admin', 'super_admin', 'system_admin'], true)) {
            return $this->rpcError('Huna ruhusa ya kupokea stock.', 42501);
        }

        $index = $this->productIndex($productId);
        if ($index === null) {
            return $this->rpcError('Bidhaa haipatikani.', 404);
        }
        if ($branchId !== null && (string) ($this->products[$index]['branch_id'] ?? '') !== (string) $branchId) {
            return $this->rpcError('Bidhaa ni ya tawi lingine.', 422);
        }

        foreach ($this->sessions as $session) {
            if ((string) $session['product_id'] === $productId
                && (string) $session['business_id'] === $businessId
                && (string) ($session['branch_id'] ?? '') === (string) ($branchId ?? '')
                && (string) $session['created_by'] === $userId
                && $session['status'] === 'open') {
                return [['success' => true, 'session' => $session, 'resumed' => true], 200];
            }
        }

        $session = [
            'id' => Str::uuid()->toString(),
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'status' => 'open',
            'scanned_count' => 0,
            'created_by' => $userId,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];
        $this->sessions[] = $session;

        return [['success' => true, 'session' => $session, 'resumed' => false], 200];
    }

    private function sessionIndex(string $id): ?int
    {
        foreach ($this->sessions as $i => $session) {
            if ((string) $session['id'] === $id) {
                return $i;
            }
        }

        return null;
    }

    private function rpcReceiveItem(array $p): array
    {
        $businessId = (string) ($p['p_business_id'] ?? '');
        $userId = (string) ($p['p_user_id'] ?? '');
        $sessionId = (string) ($p['p_session_id'] ?? '');
        $token = trim((string) ($p['p_qr_token'] ?? ''));

        if ($deny = $this->assertWritable($userId, $businessId)) {
            return $deny;
        }
        if ($token === '') {
            return $this->rpcError('QR code si sahihi.', 422);
        }

        $sIndex = $this->sessionIndex($sessionId);
        if ($sIndex === null) {
            return $this->rpcError('Kipindi hakipatikani.', 404);
        }
        $session = $this->sessions[$sIndex];
        if ((string) $session['business_id'] !== $businessId) {
            return $this->rpcError('Kipindi ni cha biashara nyingine.', 403);
        }
        if ((string) ($session['branch_id'] ?? '') !== (string) ($p['p_branch_id'] ?? '')) {
            return $this->rpcError('Kipindi ni cha tawi lingine.', 403);
        }
        if ($session['status'] !== 'open') {
            return $this->rpcError('Kipindi hiki kimeshakamilika au kimeghairiwa.', 409);
        }
        if ((string) $session['product_id'] !== (string) ($p['p_product_id'] ?? '')) {
            return $this->rpcError('Kipande hiki ni cha bidhaa tofauti.', 422);
        }

        if ($this->itemByToken($token) !== null) {
            return $this->rpcError('QR hii tayari imesajiliwa kwenye mfumo.', 409);
        }

        $item = $this->item(Str::uuid()->toString(), $token, (string) $session['product_id'], [
            'status' => 'pending',
            'receiving_session_id' => $sessionId,
            'received_at' => null,
        ]);
        $this->items[] = $item;

        $this->sessions[$sIndex]['scanned_count'] = count($this->pendingOf($sessionId));

        return [[
            'success' => true,
            'item' => $item,
            'scanned_count' => $this->sessions[$sIndex]['scanned_count'],
        ], 200];
    }

    /** @return list<array<string,mixed>> */
    private function pendingOf(string $sessionId): array
    {
        return array_values(array_filter($this->items, fn ($item) => (string) ($item['receiving_session_id'] ?? '') === $sessionId && $item['status'] === 'pending'));
    }

    private function rpcCompleteReceiving(array $p): array
    {
        $businessId = (string) ($p['p_business_id'] ?? '');
        $userId = (string) ($p['p_user_id'] ?? '');
        $sessionId = (string) ($p['p_session_id'] ?? '');

        if ($deny = $this->assertWritable($userId, $businessId)) {
            return $deny;
        }

        $sIndex = $this->sessionIndex($sessionId);
        if ($sIndex === null) {
            return $this->rpcError('Kipindi hakipatikani.', 404);
        }
        $session = $this->sessions[$sIndex];
        if ($session['status'] !== 'open') {
            return $this->rpcError('Kipindi hiki kimeshakamilika.', 409);
        }

        $pending = $this->pendingOf($sessionId);
        if ($pending === []) {
            return $this->rpcError('Hakuna kipande kilichoskaniwa kwenye kipindi hiki.', 422);
        }

        $pIndex = $this->productIndex((string) $session['product_id']);
        if ($pIndex === null) {
            return $this->rpcError('Bidhaa haipatikani.', 404);
        }

        $quantity = count($pending);
        $before = (int) $this->products[$pIndex]['stock'];
        $after = $before + $quantity;
        $this->products[$pIndex]['stock'] = $after;

        foreach ($this->items as $i => $item) {
            if ((string) ($item['receiving_session_id'] ?? '') === $sessionId && $item['status'] === 'pending') {
                $this->items[$i]['status'] = 'in_stock';
                $this->items[$i]['location'] = 'outside';
                $this->items[$i]['received_at'] = now()->toISOString();
            }
        }

        $this->sessions[$sIndex]['status'] = 'completed';

        return [[
            'success' => true,
            'session_id' => $sessionId,
            'product_id' => $session['product_id'],
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $after,
            'receiving_reference' => 'RCV-TEST-' . $sessionId,
        ], 200];
    }

    private function rpcCancelReceiving(array $p): array
    {
        $businessId = (string) ($p['p_business_id'] ?? '');
        $userId = (string) ($p['p_user_id'] ?? '');

        if ($deny = $this->assertWritable($userId, $businessId)) {
            return $deny;
        }

        $sIndex = $this->sessionIndex((string) ($p['p_session_id'] ?? ''));
        if ($sIndex === null) {
            return $this->rpcError('Kipindi hakipatikani.', 404);
        }
        if ($this->sessions[$sIndex]['status'] === 'completed') {
            return $this->rpcError('Kipindi hiki kimeshakamilika; stock haiwezi kughairiwa.', 409);
        }

        $sessionId = (string) $this->sessions[$sIndex]['id'];
        $removed = 0;
        foreach ($this->items as $i => $item) {
            if ((string) ($item['receiving_session_id'] ?? '') === $sessionId && $item['status'] === 'pending') {
                unset($this->items[$i]);
                $removed++;
            }
        }
        $this->items = array_values($this->items);

        $this->sessions[$sIndex]['status'] = 'cancelled';

        return [['success' => true, 'removed' => $removed], 200];
    }

    private function rpcReserveItem(array $p): array
    {
        $businessId = (string) ($p['p_business_id'] ?? '');
        $userId = (string) ($p['p_user_id'] ?? '');
        $productId = (string) ($p['p_product_id'] ?? '');
        $branchId = $p['p_branch_id'] ?? null;
        $clientRef = (string) ($p['p_client_ref'] ?? '');
        $token = trim((string) ($p['p_qr_token'] ?? ''));

        if ($deny = $this->assertWritable($userId, $businessId)) {
            return $deny;
        }
        if ($clientRef === '') {
            return $this->rpcError('Kumbukumbu ya mauzo inahitajika.', 422);
        }

        $index = $this->itemByToken($token);
        if ($index === null) {
            return $this->rpcError('QR hii haijasajiliwa kwenye mfumo.', 404);
        }
        $item = $this->items[$index];

        if ((string) $item['business_id'] !== $businessId) {
            return $this->rpcError('Kipande ni cha biashara nyingine.', 403);
        }
        if ((string) $item['product_id'] !== $productId) {
            $name = $this->products[$this->productIndex((string) $item['product_id']) ?? 0]['name'] ?? '';

            return $this->rpcError('Kipande hiki ni cha bidhaa tofauti (' . $name . ').', 422);
        }
        if ($branchId !== null && (string) ($item['branch_id'] ?? '') !== (string) $branchId) {
            return $this->rpcError('Kipande hiki ni cha tawi lingine.', 403);
        }

        switch ($item['status']) {
            case 'sold':
                return $this->rpcError('Kipande hiki kimeshauzwa.', 422);
            case 'pending':
                return $this->rpcError('Kipande hiki hakijakamilika kupokelewa.', 422);
            case 'void':
                return $this->rpcError('Kipande hiki kimeondolewa kwenye mfumo.', 422);
            case 'reserved':
                $reservation = $this->reservationFor((string) $item['id']);
                if ($reservation && (string) $reservation['client_ref'] === $clientRef) {
                    return $this->rpcError('Kipande hiki tayari kiko kwenye mauzo haya.', 409);
                }

                return $this->rpcError('Kipande hiki kimehifadhiwa kwenye mauzo mengine.', 409);
        }

        $this->reservations[] = [
            'id' => Str::uuid()->toString(),
            'product_item_id' => (string) $item['id'],
            'business_id' => $businessId,
            'client_ref' => $clientRef,
            'expires_at' => now()->addMinutes(30)->toISOString(),
            'released_at' => null,
        ];
        $this->items[$index]['status'] = 'reserved';

        return [[
            'success' => true,
            'item_id' => (string) $item['id'],
            'location' => $item['location'],
            'fridge_id' => $item['fridge_id'],
            'expires_at' => now()->addMinutes(30)->toISOString(),
        ], 200];
    }

    private function rpcReleaseItems(array $p): array
    {
        $released = 0;
        foreach ((array) ($p['p_item_ids'] ?? []) as $itemId) {
            $index = $this->itemIndex((string) $itemId);
            if ($index === null) {
                continue;
            }
            if (($this->items[$index]['status'] ?? '') !== 'reserved') {
                continue;
            }
            foreach ($this->reservations as $r => $row) {
                if ((string) $row['product_item_id'] === (string) $itemId && $row['released_at'] === null) {
                    $this->reservations[$r]['released_at'] = now()->toISOString();
                    $released++;
                }
            }
            $this->items[$index]['status'] = 'in_stock';
        }

        return [['success' => true, 'released' => $released], 200];
    }

    private function rpcCommitSale(array $p): array
    {
        $businessId = (string) ($p['p_business_id'] ?? '');
        $userId = (string) ($p['p_seller_id'] ?? '');
        $clientRef = (string) ($p['p_client_ref'] ?? '');
        $itemIds = array_values(array_map('strval', (array) ($p['p_item_ids'] ?? [])));

        if ($deny = $this->assertWritable($userId, $businessId)) {
            return $deny;
        }
        if ($itemIds === []) {
            return $this->rpcError('Hakuna kipande kilichoskaniwa.', 422);
        }
        if ($clientRef === '') {
            return $this->rpcError('Kumbukumbu ya mauzo inahitajika.', 422);
        }
        if (count($itemIds) !== count(array_unique($itemIds))) {
            return $this->rpcError('Kipande hiki kimeskaniwa mara zaidi ya moja.', 422);
        }

        $lines = [];
        foreach ((array) ($p['p_items'] ?? []) as $line) {
            $lines[(string) ($line['product_id'] ?? '')] = $line;
        }

        $grouped = [];
        foreach ($itemIds as $itemId) {
            $index = $this->itemIndex($itemId);
            if ($index === null) {
                return $this->rpcError('QR hii haijasajiliwa kwenye mfumo.', 404);
            }
            $item = $this->items[$index];

            if ((string) $item['business_id'] !== $businessId) {
                return $this->rpcError('Kipande ni cha biashara nyingine.', 403);
            }
            if ($item['status'] === 'sold' || ! empty($item['sale_id'])) {
                return $this->rpcError('Kipande hiki kimeshauzwa.', 422);
            }
            if ($item['status'] === 'pending') {
                return $this->rpcError('Kipande hiki hakijakamilika kupokelewa.', 422);
            }
            if ($item['status'] === 'void') {
                return $this->rpcError('Kipande hiki kimeondolewa kwenye mfumo.', 422);
            }
            if ($item['status'] === 'reserved') {
                $reservation = $this->reservationFor($itemId);
                if (! $reservation || (string) $reservation['client_ref'] !== $clientRef) {
                    return $this->rpcError('Kipande hiki kimehifadhiwa kwenye mauzo mengine.', 409);
                }
            }
            if (! isset($lines[(string) $item['product_id']])) {
                return $this->rpcError('Kipande hiki ni cha bidhaa tofauti.', 422);
            }

            $grouped[(string) $item['product_id']][] = ['index' => $index, 'item' => $item];
        }

        foreach ($grouped as $productId => $group) {
            if ((float) $lines[$productId]['quantity'] !== (float) count($group)) {
                return $this->rpcError('Kiasi cha bidhaa hakiendani na vipande vilivyoskaniwa.', 422);
            }
        }

        foreach ($grouped as $productId => $group) {
            $pIndex = $this->productIndex((string) $productId);
            if ($pIndex === null) {
                return $this->rpcError('Bidhaa haipatikani.', 404);
            }
            if ((float) $this->products[$pIndex]['stock'] < (float) $lines[$productId]['quantity']) {
                return $this->rpcError('Stock haitoshi kwa ' . $this->products[$pIndex]['name'] . '.', 422);
            }

            $perFridge = [];
            foreach ($group as $entry) {
                if (($entry['item']['location'] ?? 'outside') === 'fridge') {
                    $perFridge[(string) $entry['item']['fridge_id']] = ($perFridge[(string) $entry['item']['fridge_id']] ?? 0) + 1;
                }
            }
            foreach ($perFridge as $fridgeId => $count) {
                $fsIndex = $this->fridgeStockIndex((string) $fridgeId, (string) $productId);
                if ($fsIndex === null || (float) $this->fridgeStock[$fsIndex]['quantity'] < $count) {
                    return $this->rpcError('Stock ya friji haitoshi.', 422);
                }
            }
        }

        $saleId = (string) $p['p_sale_id'];
        $now = now()->toISOString();
        $total = 0.0;
        $lineRows = [];

        foreach ($grouped as $productId => $group) {
            $line = $lines[$productId];
            $quantity = (float) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];
            $total += $quantity * $unitPrice;

            $pIndex = $this->productIndex((string) $productId);
            $this->products[$pIndex]['stock'] = (float) $this->products[$pIndex]['stock'] - $quantity;

            $perFridge = [];
            foreach ($group as $entry) {
                if (($entry['item']['location'] ?? 'outside') === 'fridge') {
                    $perFridge[(string) $entry['item']['fridge_id']] = ($perFridge[(string) $entry['item']['fridge_id']] ?? 0) + 1;
                }
            }
            foreach ($perFridge as $fridgeId => $count) {
                $fsIndex = $this->fridgeStockIndex((string) $fridgeId, (string) $productId);
                $this->fridgeStock[$fsIndex]['quantity'] -= $count;
            }

            $lineRows[] = [
                'id' => Str::uuid()->toString(),
                'sale_id' => $saleId,
                'product_id' => (string) $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $quantity * $unitPrice,
                'created_at' => $now,
            ];

            foreach ($group as $entry) {
                $this->items[$entry['index']]['status'] = 'sold';
                $this->items[$entry['index']]['sale_id'] = $saleId;
                $this->items[$entry['index']]['sold_at'] = $now;
            }
        }

        $this->sales[] = [
            'id' => $saleId,
            'seller_id' => $userId,
            'branch_id' => $p['p_branch_id'] ?? null,
            'customer_id' => $p['p_customer_id'] ?? null,
            'sale_date' => $p['p_sale_date'] ?? gmdate('Y-m-d'),
            'total_amount' => $total,
            'payment_method' => $p['p_payment_method'] ?? 'cash',
            'notes' => $p['p_notes'] ?? null,
            'invoice_number' => $p['p_invoice_number'] ?? 'INV-TEST',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        foreach ($lineRows as $row) {
            $this->saleItems[] = $row;
        }

        foreach ($itemIds as $itemId) {
            foreach ($this->reservations as $r => $row) {
                if ((string) $row['product_item_id'] === $itemId && $row['released_at'] === null) {
                    $this->reservations[$r]['released_at'] = $now;
                }
            }
        }

        return [[
            'success' => true,
            'sale_id' => $saleId,
            'invoice_number' => $p['p_invoice_number'] ?? 'INV-TEST',
            'total_amount' => $total,
            'items_count' => count($lineRows),
            'items_sold' => count($itemIds),
        ], 200];
    }

    private function rpcMoveToFridge(array $p): array
    {
        $businessId = (string) ($p['p_business_id'] ?? '');
        $userId = (string) ($p['p_user_id'] ?? '');
        $fridgeId = (string) ($p['p_fridge_id'] ?? '');
        $productId = (string) ($p['p_product_id'] ?? '');

        if ($deny = $this->assertWritable($userId, $businessId)) {
            return $deny;
        }

        $index = $this->itemByToken((string) ($p['p_qr_token'] ?? ''));
        if ($index === null) {
            return $this->rpcError('QR hii haijasajiliwa kwenye mfumo.', 404);
        }
        $item = $this->items[$index];

        if ((string) $item['business_id'] !== $businessId) {
            return $this->rpcError('Kipande ni cha biashara nyingine.', 403);
        }
        if ((string) $item['product_id'] !== $productId) {
            return $this->rpcError('Kipande hiki ni cha bidhaa tofauti.', 422);
        }
        if ($item['status'] !== 'in_stock') {
            return $this->rpcError($item['status'] === 'sold' ? 'Kipande hiki kimeshauzwa.' : 'Kipande hiki hakipatikani.', 422);
        }
        if (($item['location'] ?? 'outside') === 'fridge') {
            return $this->rpcError('Kipande hiki kiko kwenye friji; hakinaweza kuhamishwa kwenye friji nyingine bila kutolewa kwanza.', 422);
        }

        $fridge = null;
        foreach ($this->fridges as $row) {
            if ((string) $row['id'] === $fridgeId && (string) $row['business_id'] === $businessId) {
                $fridge = $row;
            }
        }
        if (! $fridge) {
            return $this->rpcError('Friji haipatikani.', 404);
        }

        $fsIndex = $this->fridgeStockIndex($fridgeId, $productId);
        if ($fsIndex === null) {
            $this->fridgeStock[] = [
                'id' => Str::uuid()->toString(),
                'fridge_id' => $fridgeId,
                'product_id' => $productId,
                'business_id' => $businessId,
                'branch_id' => $p['p_branch_id'] ?? null,
                'quantity' => 0,
                'min_stock' => 0,
            ];
            $fsIndex = count($this->fridgeStock) - 1;
        }
        $this->fridgeStock[$fsIndex]['quantity'] = (float) $this->fridgeStock[$fsIndex]['quantity'] + 1;

        $this->items[$index]['location'] = 'fridge';
        $this->items[$index]['fridge_id'] = $fridgeId;

        $pIndex = $this->productIndex($productId);

        return [[
            'success' => true,
            'item_id' => (string) $item['id'],
            'qr_token' => (string) $item['qr_token'],
            'location' => 'fridge',
            'fridge_id' => $fridgeId,
            'fridge_quantity' => (float) $this->fridgeStock[$fsIndex]['quantity'],
            'business_stock' => (float) ($this->products[$pIndex]['stock'] ?? 0),
        ], 200];
    }

    private function rpcMoveOutOfFridge(array $p): array
    {
        $businessId = (string) ($p['p_business_id'] ?? '');
        $userId = (string) ($p['p_user_id'] ?? '');
        $fridgeId = (string) ($p['p_fridge_id'] ?? '');
        $productId = (string) ($p['p_product_id'] ?? '');

        if ($deny = $this->assertWritable($userId, $businessId)) {
            return $deny;
        }

        $index = $this->itemByToken((string) ($p['p_qr_token'] ?? ''));
        if ($index === null) {
            return $this->rpcError('QR hii haijasajiliwa kwenye mfumo.', 404);
        }
        $item = $this->items[$index];

        if ((string) $item['business_id'] !== $businessId) {
            return $this->rpcError('Kipande ni cha biashara nyingine.', 403);
        }
        if ((string) $item['product_id'] !== $productId) {
            return $this->rpcError('Kipande hiki ni cha bidhaa tofauti.', 422);
        }
        if (($item['location'] ?? 'outside') !== 'fridge') {
            return $this->rpcError('Kipande hiki hakiko kwenye friji.', 422);
        }
        if ((string) ($item['fridge_id'] ?? '') !== $fridgeId) {
            return $this->rpcError('Kipande hiki hakiko kwenye friji uliyochagua.', 422);
        }

        $fsIndex = $this->fridgeStockIndex($fridgeId, $productId);
        if ($fsIndex === null || (float) $this->fridgeStock[$fsIndex]['quantity'] < 1) {
            return $this->rpcError('Stock ya friji haitoshi.', 422);
        }
        $this->fridgeStock[$fsIndex]['quantity'] = (float) $this->fridgeStock[$fsIndex]['quantity'] - 1;

        $this->items[$index]['location'] = 'outside';
        $this->items[$index]['fridge_id'] = null;

        $pIndex = $this->productIndex($productId);

        return [[
            'success' => true,
            'item_id' => (string) $item['id'],
            'qr_token' => (string) $item['qr_token'],
            'location' => 'outside',
            'fridge_id' => null,
            'fridge_quantity' => (float) $this->fridgeStock[$fsIndex]['quantity'],
            'business_stock' => (float) ($this->products[$pIndex]['stock'] ?? 0),
        ], 200];
    }

    private function rpcSummary(array $p): array
    {
        $businessId = (string) ($p['p_business_id'] ?? '');
        $rows = [];

        foreach ($this->products as $product) {
            if ((string) ($product['business_id'] ?? '') !== $businessId) {
                continue;
            }
            $outside = 0;
            $fridge = 0;
            $sold = 0;
            foreach ($this->items as $item) {
                if ((string) $item['product_id'] !== (string) $product['id']
                    || (string) $item['business_id'] !== $businessId) {
                    continue;
                }
                if ($item['status'] === 'sold') {
                    $sold++;
                } elseif ($item['status'] === 'in_stock' && ($item['location'] ?? 'outside') === 'fridge') {
                    $fridge++;
                } elseif ($item['status'] === 'in_stock') {
                    $outside++;
                }
            }
            $rows[] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'business_stock' => (float) $product['stock'],
                'outside_items' => $outside,
                'fridge_items' => $fridge,
                'sold_items' => $sold,
            ];
        }

        return [$rows, 200];
    }

    private function rpcReceiveStock(array $p): array
    {
        $index = $this->productIndex((string) ($p['p_product_id'] ?? ''));
        if ($index === null) {
            return $this->rpcError('Bidhaa haipatikani.', 404);
        }

        $before = (int) $this->products[$index]['stock'];
        $quantity = (int) ($p['p_quantity'] ?? 0);
        $this->products[$index]['stock'] = $before + $quantity;

        return [[
            'success' => true,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $before + $quantity,
            'receiving_reference' => 'RCV-MANUAL',
        ], 200];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

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

    private function stockOf(string $id): float
    {
        $index = $this->productIndex($id);

        return $index === null ? -1.0 : (float) $this->products[$index]['stock'];
    }

    private function itemStatus(string $id): string
    {
        $index = $this->itemIndex($id);

        return $index === null ? 'missing' : (string) $this->items[$index]['status'];
    }

    private function itemLocation(string $id): string
    {
        $index = $this->itemIndex($id);

        return $index === null ? 'missing' : (string) ($this->items[$index]['location'] ?? 'outside');
    }

    private function fridgeQty(string $fridgeId, string $productId): float
    {
        $index = $this->fridgeStockIndex($fridgeId, $productId);

        return $index === null ? 0.0 : (float) $this->fridgeStock[$index]['quantity'];
    }

    private function countItems(): int
    {
        return count($this->items);
    }

    /* ------------------------------------------------------------------ */
    /* 3. Administrator receiving                                          */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function a_seller_cannot_start_a_scanner_receiving_session(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/receiving/start', ['product_id' => 'p-1'])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'STOCK_RECEIVING_FORBIDDEN']);

        $this->assertSame([], $this->sessions);
        $this->assertSame([], $this->rpcCalls);
        $this->assertSame(10.0, $this->stockOf('p-1'));
    }

    #[Test]
    public function one_scan_adds_exactly_one_unit_only_after_completion(): void
    {
        $this->fakeSupabase();

        $start = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/start', ['product_id' => 'p-1'])
            ->assertStatus(201)
            ->assertJsonPath('product.name', 'Coca-Cola')
            ->assertJsonPath('session.scanned_count', 0);

        $sessionId = $start->json('session.id');

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/scan', ['session_id' => $sessionId, 'qr_token' => 'NEW-001'])
            ->assertStatus(201)
            ->assertJsonPath('scanned_count', 1)
            ->assertJsonPath('item.status', 'pending');

        // A pending session is NOT stock yet.
        $this->assertSame(10.0, $this->stockOf('p-1'));

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/complete', ['session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('quantity', 1)
            ->assertJsonPath('stock_before', 10)
            ->assertJsonPath('stock_after', 11);

        $this->assertSame(11.0, $this->stockOf('p-1'));
        $newItem = null;
        foreach ($this->items as $item) {
            if ($item['qr_token'] === 'NEW-001') {
                $newItem = $item;
            }
        }
        $this->assertNotNull($newItem);
        $this->assertSame('in_stock', $newItem['status']);
        $this->assertSame('outside', $newItem['location']);
        $this->assertSame('p-1', $newItem['product_id'], 'The scan must link the unit to the selected product, not create a product.');
        $this->assertCount(5, $this->products, 'Scanning must never register products.');
    }

    #[Test]
    public function multiple_scans_add_the_scanned_number_of_units(): void
    {
        $this->fakeSupabase();

        $sessionId = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/start', ['product_id' => 'p-1'])
            ->json('session.id');

        foreach (['NEW-001', 'NEW-002', 'NEW-003'] as $token) {
            $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
                ->postJson('/api/scanner/receiving/scan', ['session_id' => $sessionId, 'qr_token' => $token])
                ->assertStatus(201);
        }

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/complete', ['session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('quantity', 3);

        $this->assertSame(13.0, $this->stockOf('p-1'));
    }

    #[Test]
    public function an_already_registered_qr_is_rejected_and_never_counted(): void
    {
        $this->fakeSupabase();

        $sessionId = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/start', ['product_id' => 'p-1'])
            ->json('session.id');

        // 'a001' is an existing unit in a different case: the token space is
        // case-insensitive, so it must be refused.
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/scan', ['session_id' => $sessionId, 'qr_token' => 'a001'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'QR_ALREADY_REGISTERED']);

        $before = $this->countItems();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/complete', ['session_id' => $sessionId])
            ->assertStatus(422);

        $this->assertSame($before, $this->countItems());
        $this->assertSame(10.0, $this->stockOf('p-1'));
    }

    #[Test]
    public function cancelling_a_receiving_session_leaves_no_stock_and_frees_the_token(): void
    {
        $this->fakeSupabase();

        $sessionId = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/start', ['product_id' => 'p-1'])
            ->json('session.id');

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/scan', ['session_id' => $sessionId, 'qr_token' => 'NEW-001'])
            ->assertStatus(201);

        $before = $this->countItems();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/cancel', ['session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('removed', 1);

        $this->assertSame(10.0, $this->stockOf('p-1'), 'A cancelled session must not add stock.');
        $this->assertSame($before - 1, $this->countItems(), 'No orphaned pending item may survive.');
        $this->assertSame('cancelled', $this->sessions[0]['status']);

        // The token was never registered, so it can be used by a new session.
        $newSession = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/start', ['product_id' => 'p-1'])
            ->json('session.id');

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/scanner/receiving/scan', ['session_id' => $newSession, 'qr_token' => 'NEW-001'])
            ->assertStatus(201);
    }

    #[Test]
    public function manual_receiving_still_works(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/products/p-1/stock', ['quantity' => 3])
            ->assertStatus(201)
            ->assertJsonPath('product.stock', 13);

        $this->assertSame(13.0, $this->stockOf('p-1'));
    }

    /* ------------------------------------------------------------------ */
    /* 4. Seller sales                                                     */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function a_valid_scan_adds_one_unit_and_claims_the_item(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', [
                'product_id' => 'p-1',
                'qr_token' => 'A001',
                'client_ref' => self::CLIENT_REF,
            ])
            ->assertStatus(201)
            ->assertJsonPath('item.product_name', 'Coca-Cola')
            ->assertJsonPath('item.location', 'outside');

        $this->assertSame('reserved', $this->itemStatus('11111111-1111-4111-8111-111111111111'));
        $this->assertSame(10.0, $this->stockOf('p-1'), 'Scanning into a cart must not deduct stock.');

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', [
                'product_id' => 'p-1',
                'qr_token' => 'A002',
                'client_ref' => self::CLIENT_REF,
            ])
            ->assertStatus(201);

        $this->assertSame('reserved', $this->itemStatus('22222222-2222-4222-8222-222222222222'));
        $this->assertSame(2, count(array_filter($this->reservations, fn ($r) => $r['released_at'] === null)));
    }

    #[Test]
    public function scanning_the_same_item_twice_in_one_cart_is_rejected(): void
    {
        $this->fakeSupabase();

        $payload = ['product_id' => 'p-1', 'qr_token' => 'A001', 'client_ref' => self::CLIENT_REF];

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', $payload)
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'ITEM_DUPLICATE']);

        $this->assertSame(1, count($this->reservations));
    }

    #[Test]
    public function an_item_claimed_by_another_cart_is_rejected(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'A001', 'client_ref' => 'cart-A'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'A001', 'client_ref' => 'cart-B'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'ITEM_RESERVED']);
    }

    #[Test]
    public function an_item_of_another_product_is_rejected(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'B001', 'client_ref' => self::CLIENT_REF])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'PRODUCT_MISMATCH']);

        $this->assertSame('in_stock', $this->itemStatus('44444444-4444-4444-8444-444444444444'));
    }

    #[Test]
    public function an_already_sold_item_is_rejected(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'SOLD01', 'client_ref' => self::CLIENT_REF])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'ITEM_SOLD']);

        $this->assertSame('sold', $this->itemStatus('55555555-5555-4555-8555-555555555555'));
    }

    #[Test]
    public function a_scanned_sale_requires_a_client_reference(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'A001'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'CLIENT_REF_REQUIRED']);
    }

    #[Test]
    public function releasing_a_cancelled_cart_leaves_stock_untouched(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'A001', 'client_ref' => self::CLIENT_REF])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/release', ['item_ids' => ['11111111-1111-4111-8111-111111111111'], 'client_ref' => self::CLIENT_REF])
            ->assertOk()
            ->assertJsonPath('released', 1);

        $this->assertSame('in_stock', $this->itemStatus('11111111-1111-4111-8111-111111111111'));
        $this->assertSame(10.0, $this->stockOf('p-1'));

        // The released unit can be sold by a new cart.
        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'A001', 'client_ref' => 'cart-new'])
            ->assertStatus(201);
    }

    #[Test]
    public function committing_a_scanned_sale_marks_the_units_sold_and_deducts_stock_once(): void
    {
        $this->fakeSupabase();

        foreach (['A001', 'A002'] as $token) {
            $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
                ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => $token, 'client_ref' => self::CLIENT_REF])
                ->assertStatus(201);
        }

        $payload = [
            'clientSaleKey' => self::CLIENT_REF,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => 'p-1',
                'quantity' => 2,
                'unit_price' => 1500,
                'item_ids' => ['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222'],
            ]],
        ];

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/sales', $payload)
            ->assertStatus(201)
            ->assertJsonPath('scanned_items_sold', 2);

        $this->assertSame('sold', $this->itemStatus('11111111-1111-4111-8111-111111111111'));
        $this->assertSame('sold', $this->itemStatus('22222222-2222-4222-8222-222222222222'));
        $this->assertSame(8.0, $this->stockOf('p-1'), 'Exactly the sold units are deducted.');
        $this->assertCount(1, $this->sales);

        // A retried submission returns the original sale: no second deduction,
        // no double sale of the same physical units.
        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/sales', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(8.0, $this->stockOf('p-1'));
        $this->assertCount(1, $this->sales);

        // The same units can never be sold again by another cart.
        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'A001', 'client_ref' => 'cart-later'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'ITEM_SOLD']);
    }

    #[Test]
    public function selling_a_fridge_item_deducts_that_fridge_and_keeps_the_invariant(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'FR001', 'client_ref' => self::CLIENT_REF])
            ->assertStatus(201)
            ->assertJsonPath('item.fridge_name', 'Main Fridge');

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/sales', [
                'clientSaleKey' => self::CLIENT_REF,
                'items' => [[
                    'product_id' => 'p-1',
                    'quantity' => 1,
                    'unit_price' => 1500,
                    'item_ids' => ['66666666-6666-4666-8666-666666666666'],
                ]],
            ])
            ->assertStatus(201);

        $this->assertSame('sold', $this->itemStatus('66666666-6666-4666-8666-666666666666'));
        $this->assertSame(9.0, $this->stockOf('p-1'));
        $this->assertSame(0.0, $this->fridgeQty('fridge-1', 'p-1'), 'The unit came out of the fridge it was in.');
    }

    #[Test]
    public function a_line_quantity_that_does_not_match_the_scanned_units_is_rejected(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'A001', 'client_ref' => self::CLIENT_REF])
            ->assertStatus(201);

        // Manual 2 + one scanned unit would count the same physical item twice.
        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/sales', [
                'clientSaleKey' => self::CLIENT_REF,
                'items' => [[
                    'product_id' => 'p-1',
                    'quantity' => 2,
                    'unit_price' => 1500,
                    'item_ids' => ['11111111-1111-4111-8111-111111111111'],
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'QUANTITY_MISMATCH']);

        $this->assertSame(10.0, $this->stockOf('p-1'));
        $this->assertSame('reserved', $this->itemStatus('11111111-1111-4111-8111-111111111111'));
        $this->assertSame([], $this->sales);
    }

    #[Test]
    public function a_manual_sale_without_scanned_items_still_works(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/sales', [
                'items' => [[
                    'product_id' => 'p-2',
                    'quantity' => 1,
                    'unit_price' => 2500,
                ]],
            ])
            ->assertStatus(201);

        $this->assertSame(4.0, $this->stockOf('p-2'));
    }

    /* ------------------------------------------------------------------ */
    /* 5-6. Fridge / freezer transfers                                     */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function scanning_into_a_fridge_moves_one_item_without_changing_business_stock(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/fridge/in', ['fridge_id' => 'fridge-1', 'product_id' => 'p-1', 'qr_token' => 'A001'])
            ->assertStatus(201)
            ->assertJsonPath('result.location', 'fridge')
            ->assertJsonPath('result.fridge_quantity', 2);

        $this->assertSame('fridge', $this->itemLocation('11111111-1111-4111-8111-111111111111'));
        $this->assertSame(2.0, $this->fridgeQty('fridge-1', 'p-1'));
        $this->assertSame(10.0, $this->stockOf('p-1'), 'A transfer never changes Business Stock.');
    }

    #[Test]
    public function scanning_out_of_a_fridge_returns_the_item_to_outside_stock(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/fridge/out', ['fridge_id' => 'fridge-1', 'product_id' => 'p-1', 'qr_token' => 'FR001'])
            ->assertStatus(201)
            ->assertJsonPath('result.location', 'outside')
            ->assertJsonPath('result.fridge_quantity', 0);

        $this->assertSame('outside', $this->itemLocation('66666666-6666-4666-8666-666666666666'));
        $this->assertSame(10.0, $this->stockOf('p-1'));
    }

    #[Test]
    public function an_item_cannot_be_removed_from_a_fridge_it_is_not_in(): void
    {
        $this->fakeSupabase();

        // 66666666-6666-4666-8666-666666666666 lives in fridge-1, not fridge-2.
        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/fridge/out', ['fridge_id' => 'fridge-2', 'product_id' => 'p-1', 'qr_token' => 'FR001'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'FRIDGE_MISMATCH']);

        // An outside item cannot be removed from a fridge either.
        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/fridge/out', ['fridge_id' => 'fridge-1', 'product_id' => 'p-1', 'qr_token' => 'A002'])
            ->assertStatus(422);

        $this->assertSame(1.0, $this->fridgeQty('fridge-1', 'p-1'));
        $this->assertSame(10.0, $this->stockOf('p-1'));
    }

    #[Test]
    public function an_item_already_in_one_fridge_cannot_be_added_to_another(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/fridge/in', ['fridge_id' => 'fridge-2', 'product_id' => 'p-1', 'qr_token' => 'FR001'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'FRIDGE_MISMATCH']);

        $this->assertSame('fridge-1', $this->items[$this->itemIndex('66666666-6666-4666-8666-666666666666')]['fridge_id']);
        $this->assertSame(0.0, $this->fridgeQty('fridge-2', 'p-1'));
    }

    #[Test]
    public function two_fridges_are_tracked_separately(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/fridge/in', ['fridge_id' => 'fridge-2', 'product_id' => 'p-1', 'qr_token' => 'A001'])
            ->assertStatus(201);

        $this->assertSame(1.0, $this->fridgeQty('fridge-1', 'p-1'));
        $this->assertSame(1.0, $this->fridgeQty('fridge-2', 'p-1'));
        $this->assertSame(10.0, $this->stockOf('p-1'));
    }

    #[Test]
    public function a_sold_item_cannot_be_transferred_into_a_fridge(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/fridge/in', ['fridge_id' => 'fridge-1', 'product_id' => 'p-1', 'qr_token' => 'SOLD01'])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'ITEM_SOLD']);

        $this->assertSame(1.0, $this->fridgeQty('fridge-1', 'p-1'));
    }

    /* ------------------------------------------------------------------ */
    /* 7. Order page global scanning                                       */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function order_scanning_identifies_the_product_without_any_selection(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'A001'])
            ->assertOk()
            ->assertJsonPath('product.id', 'p-1')
            ->assertJsonPath('product.name', 'Coca-Cola')
            ->assertJsonPath('product.expected_selling_price', 1500)
            ->assertJsonPath('item.location', 'outside');

        $this->assertSame('in_stock', $this->itemStatus('11111111-1111-4111-8111-111111111111'), 'Adding to an order cart must not reserve or sell.');
        $this->assertSame(10.0, $this->stockOf('p-1'), 'Order scanning never moves stock.');
    }

    #[Test]
    public function order_scanning_maps_each_item_to_its_own_product_line(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'A001'])
            ->assertOk()
            ->assertJsonPath('product.id', 'p-1');

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'A002'])
            ->assertOk()
            ->assertJsonPath('product.id', 'p-1');

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'B001'])
            ->assertOk()
            ->assertJsonPath('product.id', 'p-2')
            ->assertJsonPath('product.name', 'Water');
    }

    #[Test]
    public function order_scanning_rejects_an_item_already_in_the_cart(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'A001', 'cart_item_ids' => ['11111111-1111-4111-8111-111111111111']])
            ->assertStatus(409)
            ->assertJsonFragment(['code' => 'ITEM_DUPLICATE']);
    }

    #[Test]
    public function order_scanning_rejects_unknown_sold_reserved_and_foreign_items(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'NO-SUCH-CODE'])
            ->assertStatus(404)
            ->assertJsonFragment(['code' => 'QR_UNKNOWN']);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'SOLD01'])
            ->assertStatus(409)
            ->assertJsonFragment(['code' => 'ITEM_SOLD']);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'OTHER01'])
            ->assertStatus(404)
            ->assertJsonFragment(['code' => 'QR_UNKNOWN']);

        // Claimed by an open sale cart elsewhere.
        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'A003', 'client_ref' => 'other-cart'])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'A003'])
            ->assertStatus(409)
            ->assertJsonFragment(['code' => 'ITEM_RESERVED']);
    }

    /* ------------------------------------------------------------------ */
    /* 12-13. Security, scope and the scanner setting                      */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function item_tracking_is_scoped_to_the_business_and_branch(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'OTHER01'])
            ->assertStatus(404)
            ->assertJsonFragment(['code' => 'QR_UNKNOWN']);

        // Tracked unit of a product that lives in another branch: the item is
        // known, but it is not sellable in this branch context.
        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/order/scan', ['qr_token' => 'BR2-01'])
            ->assertStatus(404)
            ->assertJsonFragment(['code' => 'PRODUCT_UNAVAILABLE']);
    }

    #[Test]
    public function scanner_operations_are_refused_when_the_feature_is_off(): void
    {
        $this->fakeSupabase(false);

        $calls = [
            ['post', '/api/scanner/receiving/start', ['product_id' => 'p-1'], $this->adminToken()],
            ['post', '/api/scanner/sale/scan', ['product_id' => 'p-1', 'qr_token' => 'A001', 'client_ref' => self::CLIENT_REF], $this->sellerToken()],
            ['post', '/api/scanner/fridge/in', ['fridge_id' => 'fridge-1', 'product_id' => 'p-1', 'qr_token' => 'A001'], $this->sellerToken()],
            ['post', '/api/scanner/order/scan', ['qr_token' => 'A001'], $this->sellerToken()],
            ['get', '/api/scanner/identify?qr_token=A001', [], $this->sellerToken()],
        ];

        foreach ($calls as [$verb, $url, $body, $token]) {
            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->json($verb, $url, $body)
                ->assertStatus(403)
                ->assertJsonFragment(['code' => 'FEATURE_DISABLED']);
        }

        $this->assertSame([], $this->rpcCalls, 'Nothing may reach the database while the module is off.');
        $this->assertSame('in_stock', $this->itemStatus('11111111-1111-4111-8111-111111111111'));
        $this->assertSame(10.0, $this->stockOf('p-1'));
    }

    #[Test]
    public function a_scanned_sale_is_refused_when_the_feature_is_off(): void
    {
        $this->fakeSupabase(false);

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/sales', [
                'items' => [[
                    'product_id' => 'p-1',
                    'quantity' => 1,
                    'unit_price' => 1500,
                    'item_ids' => ['11111111-1111-4111-8111-111111111111'],
                ]],
            ])
            ->assertStatus(403)
            ->assertJsonFragment(['code' => 'FEATURE_DISABLED']);

        $this->assertSame(10.0, $this->stockOf('p-1'));
        $this->assertSame([], $this->sales);
    }

    #[Test]
    public function a_product_of_another_business_cannot_be_scanned(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->postJson('/api/scanner/sale/scan', [
                'product_id' => 'p-foreign',
                'qr_token' => 'A001',
                'client_ref' => self::CLIENT_REF,
            ])
            ->assertStatus(404)
            ->assertJsonFragment(['code' => 'PRODUCT_NOT_FOUND']);
    }

    #[Test]
    public function verification_reconciles_tracked_units_with_the_stock_ledger(): void
    {
        $this->fakeSupabase();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->getJson('/api/scanner/verify')
            ->assertOk();

        $rows = collect($response->json('products'))->keyBy('product_id');

        // Tracked units: A001-A003 outside + FR001 in a fridge = 4 of 10 stock.
        $this->assertSame(3, $rows['p-1']['outside_items']);
        $this->assertSame(1, $rows['p-1']['fridge_items']);
        $this->assertSame(4, $rows['p-1']['tracked_items']);
        $this->assertFalse($rows['p-1']['consistent']);

        // An untracked product is not flagged: it legitimately has no item rows.
        $this->assertTrue($rows['p-untracked']['consistent']);
        $this->assertSame(0, $rows['p-untracked']['tracked_items']);
    }

    #[Test]
    public function identifying_a_token_returns_the_unit_and_its_location(): void
    {
        $this->fakeSupabase();

        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->getJson('/api/scanner/identify?qr_token=FR001')
            ->assertOk()
            ->assertJsonPath('item.location', 'fridge')
            ->assertJsonPath('item.fridge_name', 'Main Fridge')
            ->assertJsonPath('product.id', 'p-1');

        // The dmk1: prefix and a printed label URL both resolve to the token.
        $this->withHeader('Authorization', 'Bearer ' . $this->sellerToken())
            ->getJson('/api/scanner/identify?qr_token=dmk1:A001')
            ->assertOk()
            ->assertJsonPath('item.id', '11111111-1111-4111-8111-111111111111');
    }
}
