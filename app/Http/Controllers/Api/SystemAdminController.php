<?php

namespace App\Http\Controllers\Api;

use App\Models\ActiveSession;
use App\Models\Advertisement;
use App\Models\AiCopilotConversation;
use App\Models\AiImportVerification;
use App\Models\AiWeeklyDigest;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\OfficeExpense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PasswordResetCode;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\UserLog;
use App\Models\UserPresenceHistory;
use App\Services\Ai\TrustSummary;
use App\Services\BusinessBlock;
use App\Services\BusinessResolver;
use Illuminate\Http\Request;

/**
 * Super Admin platform management that is not tied to a single business:
 * certification, the businesses directory, and business-level block/delete.
 */
class SystemAdminController extends BaseController
{
    private const MEMBER_COLUMNS = [
        'id', 'email', 'full_name', 'phone', 'role', 'status', 'business_id',
        'business_name', 'business_logo_url', 'is_online', 'last_seen', 'created_at',
    ];

    private const STAFF_ROLES = ['admin', 'seller', 'super_admin', 'system_admin'];

    /* ------------------------------------------------------------------ */
    /* Businesses directory                                                */
    /* ------------------------------------------------------------------ */

    public function businesses(Request $request)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        try {
            $businesses = Business::orderBy('business_name')->get()->toArray();
            $users = User::select(self::MEMBER_COLUMNS)->get()->toArray();
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Imeshindikana kupata biashara.'], 500);
        }

        [$byId, $byLegacy] = $this->groupMembers($users);
        $blockedIds = BusinessBlock::blockedIds(array_column($businesses, 'id'));
        $blocked = array_fill_keys($blockedIds, true);

        return $this->json(array_map(function ($business) use ($byId, $byLegacy, $blocked) {
            $members = $this->membersFromMaps($business, $byId, $byLegacy);
            $id = (string) ($business['id'] ?? '');

            return $this->businessSummary($business, $members, isset($blocked[$id]));
        }, $businesses));
    }

    public function show(Request $request, string $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        $business = $this->findBusiness($id);
        if (! $business) {
            return $this->json(['error' => 'Biashara haipatikani.'], 404);
        }

        try {
            $members = $this->membersForBusiness($business);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Imeshindikana kupata watumiaji wa biashara.'], 500);
        }

        $memberIds = array_values(array_filter(array_column($members, 'id')));
        $summary = $this->businessSummary($business, $members, BusinessBlock::isBlocked($id));

        $summary['counts'] = [
            'users' => count($members),
            'staff' => count(array_filter($members, fn ($u) => in_array($u['role'] ?? '', self::STAFF_ROLES, true))),
            'online' => count(array_filter($members, fn ($u) => ! empty($u['is_online']))),
            'products' => $this->safeCount(Product::whereIn('seller_id', $memberIds)),
            'customers' => $this->safeCount(Customer::whereIn('seller_id', $memberIds)),
            'sales' => $this->safeCount(Sale::whereIn('seller_id', $memberIds)),
            'sales_total' => $this->safeSum(Sale::whereIn('seller_id', $memberIds), 'total_amount'),
            'expenses' => $this->safeCount(OfficeExpense::whereIn('user_id', $memberIds)),
            'expenses_total' => $this->safeSum(OfficeExpense::whereIn('user_id', $memberIds), 'amount'),
            'orders' => $this->safeCount(Order::where('business_id', $id)),
        ];

        $summary['users'] = array_map(fn ($u) => [
            'id' => $u['id'] ?? null,
            'full_name' => $u['full_name'] ?? null,
            'email' => $u['email'] ?? null,
            'phone' => $u['phone'] ?? null,
            'role' => $u['role'] ?? null,
            'status' => $u['status'] ?? null,
            'is_online' => (bool) ($u['is_online'] ?? false),
            'last_seen' => $u['last_seen'] ?? null,
            'created_at' => $u['created_at'] ?? null,
        ], $members);

        return $this->json($summary);
    }

    public function activity(Request $request, string $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        $business = $this->findBusiness($id);
        if (! $business) {
            return $this->json(['error' => 'Biashara haipatikani.'], 404);
        }

        try {
            $members = $this->membersForBusiness($business);
            $memberIds = array_values(array_filter(array_column($members, 'id')));

            if ($memberIds === []) {
                return $this->json(['business_id' => $id, 'logs' => []]);
            }

            $logs = UserLog::whereIn('user_id', $memberIds)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Imeshindikana kupata shughuli za biashara.'], 500);
        }

        $usersById = [];
        foreach ($members as $member) {
            $usersById[(string) ($member['id'] ?? '')] = [
                'full_name' => $member['full_name'] ?? null,
                'email' => $member['email'] ?? null,
                'role' => $member['role'] ?? null,
            ];
        }

        $logs = array_map(function ($log) use ($usersById) {
            $log['user'] = $usersById[(string) ($log['user_id'] ?? '')] ?? null;

            return $log;
        }, $logs);

        return $this->json(['business_id' => $id, 'logs' => array_values($logs)]);
    }

    /**
     * Block or unblock every account in a business. Existing tokens stop
     * working immediately through AuthenticateJwt; new logins are refused in
     * AuthController.
     */
    public function updateStatus(Request $request, string $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        $business = $this->findBusiness($id);
        if (! $business) {
            return $this->json(['error' => 'Biashara haipatikani.'], 404);
        }

        $blocked = $request->has('blocked') ? $request->boolean('blocked') : true;

        if ($blocked) {
            if (! BusinessBlock::block($id, $this->userId($request))) {
                return $this->json(['error' => 'Imeshindikana kuzuia biashara.'], 500);
            }

            try {
                $memberIds = array_values(array_filter(array_column(
                    $this->membersForBusiness($business),
                    'id'
                )));

                if ($memberIds !== []) {
                    User::whereIn('id', $memberIds)->update([
                        'is_online' => false,
                        'updated_at' => $this->isoNow(),
                    ]);
                }
            } catch (\Throwable $e) {
                // The block itself is already stored; presence cleanup is
                // best-effort and must not undo it.
            }
        } elseif (! BusinessBlock::unblock($id)) {
            return $this->json(['error' => 'Imeshindikana kuondoa zuio la biashara.'], 500);
        }

        $this->log(
            $this->userId($request),
            $blocked ? 'BUSINESS_BLOCK' : 'BUSINESS_UNBLOCK',
            '/api/system-admin/businesses/' . $id . '/status',
            ['business_id' => $id, 'business_name' => $business['business_name'] ?? null],
            $this->ip($request),
            'success'
        );

        return $this->json([
            'success' => true,
            'is_blocked' => $blocked,
            'message' => $blocked
                ? 'Biashara imezuiwa. Watumiaji wake wote hawawezi kuingia.'
                : 'Zuio la biashara limeondolewa. Watumiaji wanaweza kuingia tena.',
        ]);
    }

    /**
     * Delete a business and every account + row owned by its staff. PostgREST
     * has no cross-table transaction, so each step is attempted in dependency
     * order and non-critical failures are returned as warnings. The business
     * row itself is only removed after the accounts are gone.
     */
    public function destroy(Request $request, string $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        $business = $this->findBusiness($id);
        if (! $business) {
            return $this->json(['error' => 'Biashara haipatikani.'], 404);
        }

        try {
            $members = $this->membersForBusiness($business);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Imeshindikana kupata watumiaji wa biashara.'], 500);
        }

        $memberIds = array_values(array_filter(array_column($members, 'id')));
        $adminId = $this->userId($request);

        if ($adminId && in_array($adminId, $memberIds, true)) {
            return $this->json([
                'error' => 'Huwezi kufuta biashara unayomiliki. Badilisha akaunti yako kwanza.',
            ], 400);
        }

        $warnings = [];
        $saleIds = $this->safePluck(Sale::whereIn('seller_id', $memberIds), 'id', $warnings);
        $orderIds = array_values(array_unique(array_merge(
            $this->safePluck(Order::where('business_id', $id), 'id', $warnings),
            $this->safePluck(Order::whereIn('seller_id', $memberIds), 'id', $warnings)
        )));
        $adIds = $this->safePluck(Advertisement::whereIn('user_id', $memberIds), 'id', $warnings);

        $productCount = $this->safeCount(Product::whereIn('seller_id', $memberIds));
        $expenseCount = $this->safeCount(OfficeExpense::whereIn('user_id', $memberIds));
        $customerCount = $this->safeCount(Customer::whereIn('seller_id', $memberIds));

        // Children first, then the rows they belong to.
        $this->deleteWhere(SaleItem::class, 'sale_id', $saleIds, $warnings);
        $this->deleteWhere(OrderItem::class, 'order_id', $orderIds, $warnings);
        $this->deleteWhere(OrderStatusHistory::class, 'order_id', $orderIds, $warnings);
        $this->deleteWhere(Reaction::class, 'matangazo_id', $adIds, $warnings);
        $this->deleteWhere(Reaction::class, 'user_id', $memberIds, $warnings);
        $this->deleteWhere(Payment::class, 'user_id', $memberIds, $warnings);
        $this->deleteWhere(Payment::class, 'matangazo_id', $adIds, $warnings);

        $this->deleteWhere(Advertisement::class, 'user_id', $memberIds, $warnings);
        $this->deleteWhere(Product::class, 'seller_id', $memberIds, $warnings);
        $this->deleteWhere(Customer::class, 'seller_id', $memberIds, $warnings);
        $this->deleteWhere(Sale::class, 'seller_id', $memberIds, $warnings);
        $this->deleteWhere(OfficeExpense::class, 'user_id', $memberIds, $warnings);
        $this->deleteWhere(Order::class, 'business_id', [$id], $warnings);
        $this->deleteWhere(Order::class, 'seller_id', $memberIds, $warnings);

        $this->deleteWhere(AiImportVerification::class, 'user_id', $memberIds, $warnings);
        $this->deleteWhere(AiCopilotConversation::class, 'business_id', [$id], $warnings);
        $this->deleteWhere(AiWeeklyDigest::class, 'business_id', [$id], $warnings);
        $this->deleteWhere(Notification::class, 'sender_id', $memberIds, $warnings);
        $this->deleteWhere(ActiveSession::class, 'user_id', $memberIds, $warnings);
        $this->deleteWhere(UserPresenceHistory::class, 'user_id', $memberIds, $warnings);
        $this->deleteWhere(PasswordResetCode::class, 'user_id', $memberIds, $warnings);
        $this->deleteWhere(UserLog::class, 'user_id', $memberIds, $warnings);

        try {
            if ($memberIds !== []) {
                User::whereIn('id', $memberIds)->delete();
            }
        } catch (\Throwable $e) {
            $this->log($adminId, 'BUSINESS_DELETE_FAILED', '/api/system-admin/businesses/' . $id, [
                'business_id' => $id,
                'stage' => 'users',
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Imeshindikana kufuta watumiaji wa biashara. Hakuna biashara iliyofutwa.',
                'warnings' => $warnings,
            ], 500);
        }

        try {
            Business::where('id', $id)->delete();
        } catch (\Throwable $e) {
            $this->log($adminId, 'BUSINESS_DELETE_FAILED', '/api/system-admin/businesses/' . $id, [
                'business_id' => $id,
                'stage' => 'business',
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Watumiaji wamefutwa, lakini rekodi ya biashara imeshindikana kufutwa.',
                'warnings' => $warnings,
            ], 500);
        }

        BusinessBlock::unblock($id);

        $this->log($adminId, 'BUSINESS_DELETE', '/api/system-admin/businesses/' . $id, [
            'business_id' => $id,
            'business_name' => $business['business_name'] ?? null,
            'deleted_users' => count($memberIds),
            'deleted_products' => $productCount,
            'deleted_sales' => count($saleIds),
            'deleted_orders' => count($orderIds),
            'deleted_expenses' => $expenseCount,
            'deleted_customers' => $customerCount,
            'warnings' => $warnings,
        ], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'message' => 'Biashara, watumiaji wake na data zao zimefutwa.',
            'business_id' => $id,
            'deleted' => [
                'users' => count($memberIds),
                'products' => $productCount,
                'sales' => count($saleIds),
                'orders' => count($orderIds),
                'expenses' => $expenseCount,
                'customers' => $customerCount,
            ],
            'warnings' => $warnings,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Certification                                                       */
    /* ------------------------------------------------------------------ */

    public function certify(Request $request, string $id)
    {
        if (! $this->isCertificationAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        $business = Business::where('id', $id)->first();
        if (! $business) {
            return $this->json(['error' => 'Biashara haipatikani.'], 404);
        }

        $certified = $request->boolean('is_certified', true);
        $now = now()->toISOString();

        Business::where('id', $id)->update([
            'is_certified' => $certified,
            'certified_at' => $certified ? ($business->certified_at ?: $now) : null,
            'certification_note' => $request->input('note'),
            'updated_at' => $now,
        ]);

        // Capability #12: certification is a summarized trust fact, so the
        // cached summary for this business must refresh immediately.
        TrustSummary::invalidateBusiness($id);

        $this->log($this->userId($request), $certified ? 'BUSINESS_CERTIFY' : 'BUSINESS_UNCERTIFY', '/api/system-admin/businesses/' . $id, [], $this->ip($request), 'success');

        return $this->json([
            'message' => $certified ? 'Biashara imethibitishwa.' : 'Uthibitisho umeondolewa.',
            'is_certified' => $certified,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function findBusiness(string $id): ?array
    {
        try {
            $business = Business::where('id', $id)->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $business ? $business->toArray() : null;
    }

    /**
     * @param  array<int,array<string,mixed>>  $users
     * @return array{0:array<string,array<int,array<string,mixed>>>,1:array<string,array<int,array<string,mixed>>>}
     */
    private function groupMembers(array $users): array
    {
        $byId = [];
        $byLegacy = [];

        foreach ($users as $user) {
            $businessId = trim((string) ($user['business_id'] ?? ''));

            if ($businessId !== '') {
                $byId[$businessId][] = $user;
                continue;
            }

            $key = BusinessResolver::key($user['business_name'] ?? '');
            if ($key !== '') {
                $byLegacy[$key][] = $user;
            }
        }

        return [$byId, $byLegacy];
    }

    /**
     * @param  array<string,mixed>  $business
     * @param  array<string,array<int,array<string,mixed>>>  $byId
     * @param  array<string,array<int,array<string,mixed>>>  $byLegacy
     * @return array<int,array<string,mixed>>
     */
    private function membersFromMaps(array $business, array $byId, array $byLegacy): array
    {
        $id = (string) ($business['id'] ?? '');
        $key = BusinessResolver::key($business['business_name'] ?? '');
        $members = $byId[$id] ?? [];
        $seen = [];

        foreach ($members as $member) {
            $seen[(string) ($member['id'] ?? '')] = true;
        }

        foreach ($key !== '' ? ($byLegacy[$key] ?? []) : [] as $member) {
            $memberId = (string) ($member['id'] ?? '');
            if ($memberId !== '' && ! isset($seen[$memberId])) {
                $members[] = $member;
                $seen[$memberId] = true;
            }
        }

        return $members;
    }

    /** @return array<int,array<string,mixed>> */
    private function membersForBusiness(array $business): array
    {
        $id = (string) ($business['id'] ?? '');
        $members = User::where('business_id', $id)->select(self::MEMBER_COLUMNS)->get()->toArray();

        $key = BusinessResolver::key($business['business_name'] ?? '');
        if ($key !== '') {
            $legacy = User::whereRaw('LOWER(business_name) = ?', [$key])
                ->select(self::MEMBER_COLUMNS)
                ->get()
                ->toArray();

            $seen = [];
            foreach ($members as $member) {
                $seen[(string) ($member['id'] ?? '')] = true;
            }

            foreach ($legacy as $member) {
                $memberId = (string) ($member['id'] ?? '');
                $linkedTo = trim((string) ($member['business_id'] ?? ''));

                // A name match must never pull a user out of a different,
                // explicitly linked business.
                if ($linkedTo !== '' && $linkedTo !== $id) {
                    continue;
                }

                if ($memberId !== '' && ! isset($seen[$memberId])) {
                    $members[] = $member;
                    $seen[$memberId] = true;
                }
            }
        }

        return $members;
    }

    /** @param array<int,array<string,mixed>> $members */
    private function businessSummary(array $business, array $members, bool $blocked): array
    {
        $staff = array_values(array_filter(
            $members,
            fn ($member) => in_array($member['role'] ?? '', self::STAFF_ROLES, true)
        ));

        $owner = null;
        foreach (['admin', 'super_admin', 'system_admin', 'seller'] as $role) {
            foreach ($staff as $member) {
                if (($member['role'] ?? '') === $role) {
                    $owner = $member;
                    break 2;
                }
            }
        }

        return [
            'id' => $business['id'] ?? null,
            'business_name' => $business['business_name'] ?? null,
            'business_location' => $business['business_location'] ?? null,
            'business_type' => $business['business_type'] ?? null,
            'business_logo_url' => $business['business_logo_url'] ?? null,
            'is_certified' => (bool) ($business['is_certified'] ?? false),
            'certified_at' => $business['certified_at'] ?? null,
            'is_public' => (bool) ($business['is_public'] ?? true),
            'created_at' => $business['created_at'] ?? null,
            'users_count' => count($members),
            'staff_count' => count($staff),
            'online_count' => count(array_filter($members, fn ($member) => ! empty($member['is_online']))),
            'owner_name' => $owner['full_name'] ?? $owner['email'] ?? null,
            'owner_email' => $owner['email'] ?? null,
            'is_blocked' => $blocked,
            'status' => $blocked ? 'blocked' : ((bool) ($business['is_certified'] ?? false) ? 'certified' : 'active'),
        ];
    }

    private function safeCount($query): ?int
    {
        try {
            return (int) $query->count();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function safeSum($query, string $column): ?float
    {
        try {
            return round((float) $query->sum($column), 2);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<int,string> */
    private function safePluck($query, string $column, array &$warnings): array
    {
        try {
            return array_values($query->pluck($column)->toArray());
        } catch (\Throwable $e) {
            $warnings[] = 'Haikuweza kusoma ' . $column . ': ' . $e->getMessage();

            return [];
        }
    }

    private function deleteWhere(string $modelClass, string $column, array $values, array &$warnings): void
    {
        if ($values === []) {
            return;
        }

        try {
            $modelClass::whereIn($column, $values)->delete();
        } catch (\Throwable $e) {
            $warnings[] = $modelClass::tableName() . '.' . $column . ': ' . $e->getMessage();
        }
    }

    /**
     * Certification historically accepted the business `admin` role too. The
     * directory and its destructive actions deliberately do not: they keep the
     * base controller's stricter platform-admin check.
     */
    private function isCertificationAdmin(Request $request): bool
    {
        return in_array($this->role($request), ['super_admin', 'system_admin', 'admin'], true);
    }
}
