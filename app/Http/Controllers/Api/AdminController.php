<?php

namespace App\Http\Controllers\Api;

use App\Models\Advertisement;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\UserLog;
use App\Services\Supabase;
use Illuminate\Http\Request;

class AdminController extends BaseController
{
    private function jsIso(): string
    {
        return gmdate('Y-m-d\TH:i:s.v\Z');
    }

    private function fiveMinutesAgo(): string
    {
        return gmdate('Y-m-d\TH:i:s.v\Z', time() - 300);
    }

    private function todayUtc(): string
    {
        return gmdate('Y-m-d');
    }

    public function users(Request $request)
    {
        try {
            $userId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($userId, 'ADMIN_UNAUTHORIZED', '/api/admin/users', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            // Optional server-side business/role filters: pages like the
            // msimamizi dashboard pass ?business=&role=seller so only that
            // business's sellers leave the database (previously the whole
            // users table was sent and filtered in the browser — slow on
            // large tables). No params = all users (system admin view).
            $usersQuery = User::query()->orderByDesc('created_at');
            $businessFilter = trim((string) ($request->query('business', '') ?: $request->query('business_name', '')));
            if ($businessFilter !== '') {
                $usersQuery->where('business_name', $businessFilter);
            }
            $roleFilter = trim((string) $request->query('role', ''));
            if ($roleFilter !== '') {
                $roles = array_values(array_filter(array_map('trim', explode(',', $roleFilter)), fn ($r) => in_array($r, ['admin', 'seller', 'client', 'system_admin'], true)));
                if (count($roles) === 1) {
                    $usersQuery->where('role', $roles[0]);
                } elseif (count($roles) > 1) {
                    $usersQuery->whereIn('role', $roles);
                }
            }

            $users = $usersQuery->get()->toArray();
            $usersList = $users ?: [];

            $stats = [
                'totalUsers' => count($usersList),
                'totalAdmins' => count(array_filter($usersList, fn($u) => ($u['role'] ?? null) === 'admin')),
                'totalSellers' => count(array_filter($usersList, fn($u) => ($u['role'] ?? null) === 'seller')),
                'totalClients' => count(array_filter($usersList, fn($u) => ($u['role'] ?? null) === 'client')),
                'activeUsers' => count(array_filter($usersList, fn($u) => ($u['status'] ?? null) === 'approved')),
                'pendingUsers' => count(array_filter($usersList, fn($u) => ($u['status'] ?? null) === 'pending')),
                'suspendedUsers' => count(array_filter($usersList, fn($u) => ($u['status'] ?? null) === 'suspended')),
                'inactiveUsers' => count(array_filter($usersList, fn($u) => ($u['status'] ?? null) === 'inactive')),
                'onlineUsers' => count(array_filter($usersList, fn($u) => !empty($u['is_online']))),
                'connectedNow' => 0,
                'timestamp' => $this->jsIso(),
            ];

            $usersWithRealTime = array_map(function ($user) {
                $user['has_live_connection'] = false;
                $user['connection_age'] = null;
                $user['session_id'] = null;
                $user['ip_address'] = null;
                $user['is_currently_online'] = (bool)($user['is_online'] ?? false);
                $user['last_seen_formatted'] = !empty($user['last_seen']) ? date('d/m/Y, H:i:s', strtotime($user['last_seen'])) : 'Hajawahi';
                return $user;
            }, $usersList);

            $this->log($userId, 'ADMIN_USERS_VIEW', '/api/admin/users', [
                'count' => count($usersWithRealTime),
                'online_count' => $stats['onlineUsers'],
                'stats_summary' => $stats,
            ], $this->ip($request), 'success');

            return $this->json([
                'users' => array_values($usersWithRealTime),
                '_stats' => $stats,
                '_metadata' => [
                    'server' => 'DukaMkononi Backend',
                    'version' => '2.0.0',
                    'endpoint' => '/api/admin/users',
                    'includes_stats' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_USERS_VIEW_ERROR', '/api/admin/users', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function products(Request $request)
    {
        try {
            $userId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($userId, 'ADMIN_UNAUTHORIZED', '/api/admin/products', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            // Optional server-side business filter: when the caller passes a
            // business name, only products owned by that business's approved
            // admin/sellers are returned. Without it, behaviour is unchanged
            // (all products, for system-admin style usage).
            $query = Product::query();
            $businessName = trim((string) $request->query('business_name', ''));
            if ($businessName !== '') {
                $businessUserIds = User::where('business_name', $businessName)
                    ->whereIn('role', ['admin', 'seller'])
                    ->where('status', 'approved')
                    ->select(['id'])
                    ->get()
                    ->pluck('id')
                    ->all();

                $query->whereIn('seller_id', $businessUserIds ?: ['00000000-0000-0000-0000-000000000000']);
            }

            $products = $query->orderByDesc('created_at')->get()->toArray();

            $sellerIds = array_values(array_unique(array_filter(array_map(fn($p) => $p['seller_id'] ?? null, $products))));
            $usersById = [];
            if ($sellerIds) {
                foreach (User::whereIn('id', $sellerIds)->get() as $u) {
                    $usersById[$u->id] = $u->toArray();
                }
            }

            $result = array_map(function ($p) use ($usersById) {
                $p['users'] = isset($p['seller_id']) && isset($usersById[$p['seller_id']]) ? $usersById[$p['seller_id']] : null;
                return $p;
            }, $products);

            $this->log($userId, 'ADMIN_PRODUCTS_VIEW', '/api/admin/products', ['count' => count($result)], $this->ip($request), 'success');

            return $this->json(array_values($result));
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_PRODUCTS_VIEW_ERROR', '/api/admin/products', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function sales(Request $request)
    {
        try {
            $userId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($userId, 'ADMIN_UNAUTHORIZED', '/api/admin/sales', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            // Optional server-side business filter (see products()): restrict
            // sales to those made by the named business's approved members.
            $salesQuery = Sale::query();
            $businessName = trim((string) $request->query('business_name', ''));
            if ($businessName !== '') {
                $businessUserIds = User::where('business_name', $businessName)
                    ->whereIn('role', ['admin', 'seller'])
                    ->where('status', 'approved')
                    ->select(['id'])
                    ->get()
                    ->pluck('id')
                    ->all();

                $salesQuery->whereIn('seller_id', $businessUserIds ?: ['00000000-0000-0000-0000-000000000000']);
            }

            // Optional server-side date range so report pages can request
            // exactly the window they display instead of every sale ever made.
            $dateFrom = trim((string) ($request->query('date_from', '') ?: $request->query('start_date', '')));
            $dateTo = trim((string) ($request->query('date_to', '') ?: $request->query('end_date', '')));
            if ($dateFrom !== '') {
                $salesQuery->where('sale_date', '>=', $dateFrom);
            }
            if ($dateTo !== '') {
                $salesQuery->where('sale_date', '<=', $dateTo);
            }

            $sales = $salesQuery->orderByDesc('sale_date')->get()->toArray();

            $sellerIds = array_values(array_unique(array_filter(array_map(fn($s) => $s['seller_id'] ?? null, $sales))));
            $usersById = [];
            if ($sellerIds) {
                foreach (User::whereIn('id', $sellerIds)->select(['id', 'full_name', 'email', 'business_name', 'role', 'status'])->get() as $u) {
                    $usersById[$u->id] = $u->toArray();
                }
            }

            // Attach the customers relation so pages can show the recorded
            // customer name instead of the "Mteja" placeholder.
            $customerIds = array_values(array_unique(array_filter(array_map(fn($s) => $s['customer_id'] ?? null, $sales))));
            $customersById = [];
            if ($customerIds) {
                foreach (Customer::whereIn('id', $customerIds)->get() as $c) {
                    $customersById[$c->id] = $c->toArray();
                }
            }

            $saleIds = array_column($sales, 'id');
            $itemsBySaleId = [];
            $productIds = [];
            if ($saleIds) {
                foreach (SaleItem::whereIn('sale_id', $saleIds)->get() as $item) {
                    $itemsBySaleId[$item->sale_id][] = $item->toArray();
                    if (!empty($item->product_id)) {
                        $productIds[$item->product_id] = true;
                    }
                }
            }

            $productsById = [];
            $productKeyIds = array_keys($productIds);
            if ($productKeyIds) {
                foreach (Product::whereIn('id', $productKeyIds)->get() as $p) {
                    $productsById[$p->id] = $p->toArray();
                }
            }

            $result = array_map(function ($sale) use ($usersById, $itemsBySaleId, $productsById, $customersById) {
                $sale['users'] = isset($sale['seller_id']) && isset($usersById[$sale['seller_id']]) ? $usersById[$sale['seller_id']] : null;
                $sale['customers'] = isset($sale['customer_id']) && isset($customersById[$sale['customer_id']]) ? $customersById[$sale['customer_id']] : null;
                $saleItems = [];
                foreach ($itemsBySaleId[$sale['id']] ?? [] as $item) {
                    $item['products'] = isset($item['product_id']) && isset($productsById[$item['product_id']]) ? $productsById[$item['product_id']] : null;
                    $saleItems[] = $item;
                }
                $sale['sale_items'] = $saleItems;
                return $sale;
            }, $sales);

            $this->log($userId, 'ADMIN_SALES_VIEW', '/api/admin/sales', ['count' => count($result)], $this->ip($request), 'success');

            return $this->json(array_values($result));
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_SALES_VIEW_ERROR', '/api/admin/sales', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function customers(Request $request)
    {
        try {
            $userId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($userId, 'ADMIN_UNAUTHORIZED', '/api/admin/customers', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            // Optional server-side business filter (see products()).
            $query = Customer::query();
            $businessName = trim((string) $request->query('business_name', ''));
            if ($businessName !== '') {
                $businessUserIds = User::where('business_name', $businessName)
                    ->whereIn('role', ['admin', 'seller'])
                    ->where('status', 'approved')
                    ->select(['id'])
                    ->get()
                    ->pluck('id')
                    ->all();

                $query->whereIn('seller_id', $businessUserIds ?: ['00000000-0000-0000-0000-000000000000']);
            }

            $customers = $query->orderByDesc('total_purchases')->get()->toArray();

            $sellerIds = array_values(array_unique(array_filter(array_map(fn($c) => $c['seller_id'] ?? null, $customers))));
            $usersById = [];
            if ($sellerIds) {
                foreach (User::whereIn('id', $sellerIds)->get() as $u) {
                    $usersById[$u->id] = $u->toArray();
                }
            }

            $result = array_map(function ($c) use ($usersById) {
                $c['users'] = isset($c['seller_id']) && isset($usersById[$c['seller_id']]) ? $usersById[$c['seller_id']] : null;
                return $c;
            }, $customers);

            $this->log($userId, 'ADMIN_CUSTOMERS_VIEW', '/api/admin/customers', ['count' => count($result)], $this->ip($request), 'success');

            return $this->json(array_values($result));
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_CUSTOMERS_VIEW_ERROR', '/api/admin/customers', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function stats(Request $request)
    {
        try {
            $userId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($userId, 'ADMIN_UNAUTHORIZED', '/api/admin/stats', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $totalUsers = User::query()->count();
            $totalProducts = Product::query()->count();

            // Sum on the query builder (fetches only the total_amount column,
            // not every sale row).
            $totalSales = Sale::query()->sum('total_amount');

            $totalCustomers = Customer::query()->count();

            $today = $this->todayUtc();
            $todaySales = Sale::where('sale_date', $today)->sum('total_amount');

            $onlineUsers = User::where('last_seen', '>=', $this->fiveMinutesAgo())->where('is_online', true)->count();

            $usersByRole = User::query()->select('role', 'status')->get()->toArray();

            $totalAdmins = count(array_filter($usersByRole, fn($u) => ($u['role'] ?? null) === 'admin'));
            $totalSellers = count(array_filter($usersByRole, fn($u) => ($u['role'] ?? null) === 'seller'));
            $totalClients = count(array_filter($usersByRole, fn($u) => ($u['role'] ?? null) === 'client'));
            $activeUsers = count(array_filter($usersByRole, fn($u) => ($u['status'] ?? null) === 'approved'));
            $pendingUsers = count(array_filter($usersByRole, fn($u) => ($u['status'] ?? null) === 'pending'));
            $suspendedUsers = count(array_filter($usersByRole, fn($u) => ($u['status'] ?? null) === 'suspended'));

            $stats = [
                'totalUsers' => $totalUsers ?: 0,
                'totalProducts' => $totalProducts ?: 0,
                'totalSales' => round($totalSales, 2),
                'totalCustomers' => $totalCustomers ?: 0,
                'todaySales' => round($todaySales, 2),
                'onlineUsers' => $onlineUsers ?: 0,
                'connectedNow' => 0,
                'adminConnections' => 0,
                'totalAdmins' => $totalAdmins,
                'totalSellers' => $totalSellers,
                'totalClients' => $totalClients,
                'activeUsers' => $activeUsers,
                'pendingUsers' => $pendingUsers,
                'suspendedUsers' => $suspendedUsers,
                'webSocketConnected' => 0,
                'serverTime' => $this->jsIso(),
            ];

            $this->log($userId, 'ADMIN_STATS_VIEW', '/api/admin/stats', $stats, $this->ip($request), 'success');

            return $this->json($stats);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_STATS_VIEW_ERROR', '/api/admin/stats', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function logs(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'ADMIN_LOGS_UNAUTHORIZED', '/api/admin/logs', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $logs = UserLog::query()->orderByDesc('created_at')->limit(100)->get()->toArray();

            $logs = $this->embedLogUsers($logs);

            $this->log($adminId, 'ADMIN_LOGS_VIEW', '/api/admin/logs', ['log_count' => count($logs)], $this->ip($request), 'success');

            return $this->json(array_values($logs));
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_LOGS_VIEW_ERROR', '/api/admin/logs', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function logsSearch(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'ADMIN_LOGS_SEARCH_UNAUTHORIZED', '/api/admin/logs/search', ['reason' => 'Non-admin search attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $action = $request->query('action');
            $userId = $request->query('user_id');
            $status = $request->query('status');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            $ipAddress = $request->query('ip_address');

            $query = UserLog::query()->orderByDesc('created_at');

            if ($action) {
                $query->whereRaw('LOWER(action) LIKE ?', ['%' . mb_strtolower($action) . '%']);
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->where('created_at', '<=', $endDate);
            }

            if ($ipAddress) {
                $query->whereRaw('LOWER(ip_address) LIKE ?', ['%' . mb_strtolower($ipAddress) . '%']);
            }

            $logs = $query->get()->toArray();

            $logs = $this->embedLogUsers($logs);

            $this->log($adminId, 'ADMIN_LOGS_SEARCH', '/api/admin/logs/search', [
                'filters' => $request->query(),
                'result_count' => count($logs),
            ], $this->ip($request), 'success');

            return $this->json(array_values($logs));
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_LOGS_SEARCH_ERROR', '/api/admin/logs/search', [
                'error' => $e->getMessage(),
                'filters' => $request->query(),
            ], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    private function embedLogUsers(array $logs): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(fn($l) => $l['user_id'] ?? null, $logs))));
        $usersById = [];
        if ($userIds) {
            foreach (User::whereIn('id', $userIds)->select('email', 'full_name', 'role')->get() as $u) {
                $usersById[$u->id] = $u->toArray();
            }
        }
        return array_map(function ($log) use ($usersById) {
            $log['users'] = isset($log['user_id']) && isset($usersById[$log['user_id']]) ? $usersById[$log['user_id']] : null;
            return $log;
        }, $logs);
    }

    public function logsCleanup(Request $request)
    {
        try {
            $adminId = $this->userId($request);
            $days = $request->input('days', 30);

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'ADMIN_LOGS_CLEANUP_UNAUTHORIZED', '/api/admin/logs/cleanup', ['reason' => 'Non-admin cleanup attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $cutoff = \Illuminate\Support\Carbon::now()->subDays((int)$days);
            $cutoffIso = $cutoff->utc()->format('Y-m-d\TH:i:s.v\Z');

            $ids = UserLog::where('created_at', '<', $cutoffIso)->pluck('id')->all();

            if ($ids) {
                UserLog::whereIn('id', $ids)->delete();
            }

            $deletedCount = count($ids);

            $this->log($adminId, 'ADMIN_LOGS_CLEANUP', '/api/admin/logs/cleanup', [
                'deleted_count' => $deletedCount,
                'cutoff_days' => $days,
                'cutoff_date' => $cutoffIso,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Imefanikiwa kufuta rekodi ' . $deletedCount . ' za logi zilizozeeka zaidi ya siku ' . $days,
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffIso,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_LOGS_CLEANUP_ERROR', '/api/admin/logs/cleanup', [
                'error' => $e->getMessage(),
                'days' => $request->input('days'),
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Hitilafu katika kusafisha rekodi za logi',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroyUser(Request $request, string $id)
    {
        try {
            $adminId = $this->userId($request);
            $userId = $id;

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'ADMIN_UNAUTHORIZED', '/api/admin/users/:id', [
                    'reason' => 'Non-admin delete attempt',
                    'target_user_id' => $userId,
                ], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            if ($userId === $adminId) {
                $this->log($adminId, 'ADMIN_SELF_DELETE_ATTEMPT', '/api/admin/users/:id', ['reason' => 'Self-delete attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Huwezi kujifuta mwenyewe'], 400);
            }

            $hasSales = Sale::where('seller_id', $userId)->limit(1)->exists();
            $hasProducts = Product::where('seller_id', $userId)->limit(1)->exists();

            User::where('id', $userId)->delete();

            $this->log($adminId, 'ADMIN_USER_DELETE', '/api/admin/users/:id', [
                'target_user_id' => $userId,
                'had_sales' => $hasSales,
                'had_products' => $hasProducts,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Mtumiaji amefutwa kabisa kutoka kwenye mfumo!',
                'userId' => $userId,
                'warnings' => [
                    'hadSales' => $hasSales,
                    'hadProducts' => $hasProducts,
                    'note' => ($hasSales || $hasProducts)
                        ? 'Mauzo na bidhaa za mtumiaji huyu zimesalia kwenye mfumo.'
                        : 'Hakuna data iliyobaki ya mtumiaji huyu.',
                ],
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_USER_DELETE_ERROR', '/api/admin/users/:id', [
                'error' => $e->getMessage(),
                'target_user_id' => $id,
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Hitilafu katika kufuta mtumiaji',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateUserStatus(Request $request, string $id)
    {
        try {
            $adminId = $this->userId($request);
            $userId = $id;
            $status = $request->input('status');

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'ADMIN_UNAUTHORIZED', '/api/admin/users/' . $userId . '/status', [
                    'reason' => 'Non-admin status update attempt',
                    'target_user_id' => $userId,
                ], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
                $this->log($adminId, 'ADMIN_STATUS_UPDATE_FAILED', '/api/admin/users/' . $userId . '/status', [
                    'reason' => 'Invalid status',
                    'target_user_id' => $userId,
                    'status' => $status,
                ], $this->ip($request), 'failed');
                return $this->json(['error' => 'Status si sahihi'], 400);
            }

            $targetUser = User::where('id', $userId)->select('email', 'role', 'status', 'previous_status')->first();

            if (!$targetUser) {
                throw new \Exception('Target user not found');
            }

            $currentStatus = $targetUser->status ?: 'pending';

            User::where('id', $userId)->update([
                'status' => $status,
                'previous_status' => $currentStatus,
                'updated_at' => $this->isoNow(),
            ]);

            $this->log($adminId, 'ADMIN_STATUS_UPDATE', '/api/admin/users/' . $userId . '/status', [
                'target_user_id' => $userId,
                'target_email' => $targetUser->email,
                'target_role' => $targetUser->role,
                'old_status' => $currentStatus,
                'new_status' => $status,
                'previous_status_saved' => true,
            ], $this->ip($request), 'success');

            return $this->json([
                'message' => 'Status imesasishwa kikamilifu!',
                'userId' => $userId,
                'status' => $status,
                'previous_status' => $currentStatus,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADMIN_STATUS_UPDATE_ERROR', '/api/admin/users/' . $id . '/status', [
                'error' => $e->getMessage(),
                'target_user_id' => $id,
            ], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function onlineUsers(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'ONLINE_USERS_UNAUTHORIZED', '/api/admin/online-users', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $recentUsers = User::query()
                ->select('id', 'email', 'full_name', 'role', 'business_name', 'last_seen', 'is_online', 'phone', 'created_at')
                ->where('last_seen', '>=', $this->fiveMinutesAgo())
                ->where('is_online', true)
                ->orderByDesc('last_seen')
                ->get()
                ->toArray();

            $usersWithConnections = array_map(function ($user) {
                $user['has_live_connection'] = false;
                $user['connection_age'] = null;
                $user['session_id'] = null;
                $user['ip_address'] = null;
                $user['user_agent'] = 'Unknown';
                return $user;
            }, $recentUsers ?: []);

            $this->log($adminId, 'ONLINE_USERS_FETCH', '/api/admin/online-users', [
                'count' => count($usersWithConnections),
                'with_live_connection' => 0,
            ], $this->ip($request), 'success');

            return $this->json([
                'online_users' => array_values($usersWithConnections),
                'total_connected' => 0,
                'admin_connected' => 0,
                'timestamp' => $this->jsIso(),
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ONLINE_USERS_ERROR', '/api/admin/online-users', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json(['error' => 'Failed to fetch online users'], 500);
        }
    }

    public function realTimeStats(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'REAL_TIME_STATS_UNAUTHORIZED', '/api/admin/real-time-stats', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $today = $this->todayUtc();

            $totalUsers = User::query()->count();

            $onlineUsers = User::where('last_seen', '>=', $this->fiveMinutesAgo())->where('is_online', true)->count();

            $todayActivities = UserLog::where('created_at', '>=', $today)->count();

            $todayRevenue = Sale::where('sale_date', $today)->sum('total_amount');

            $allMatangazo = Advertisement::query()->select('id', 'report_count')->get();
            $reportedPosts = $allMatangazo->filter(fn($m) => (int)($m->report_count ?? 0) > 0)->count();

            $usersByRole = User::query()->select('role', 'status')->get()->toArray();

            $totalAdmins = count(array_filter($usersByRole, fn($u) => ($u['role'] ?? null) === 'admin'));
            $totalSellers = count(array_filter($usersByRole, fn($u) => ($u['role'] ?? null) === 'seller'));
            $totalClients = count(array_filter($usersByRole, fn($u) => ($u['role'] ?? null) === 'client'));
            $activeUsers = count(array_filter($usersByRole, fn($u) => ($u['status'] ?? null) === 'approved'));
            $pendingUsers = count(array_filter($usersByRole, fn($u) => ($u['status'] ?? null) === 'pending'));

            $stats = [
                'totalUsers' => $totalUsers ?: 0,
                'totalAdmins' => $totalAdmins,
                'totalSellers' => $totalSellers,
                'totalClients' => $totalClients,
                'activeUsers' => $activeUsers,
                'pendingUsers' => $pendingUsers,
                'reportedPosts' => $reportedPosts,
                'todayActivities' => $todayActivities ?: 0,
                'todayRevenue' => round($todayRevenue, 2),
                'onlineUsers' => $onlineUsers ?: 0,
                'connectedNow' => 0,
                'adminConnected' => 0,
                'webSocketConnected' => 0,
                'timestamp' => $this->jsIso(),
            ];

            $this->log($adminId, 'REAL_TIME_STATS_FETCH', '/api/admin/real-time-stats', $stats, $this->ip($request), 'success');

            return $this->json($stats);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'REAL_TIME_STATS_ERROR', '/api/admin/real-time-stats', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json(['error' => 'Failed to fetch real-time stats'], 500);
        }
    }

    public function presenceHistory(Request $request, string $id)
    {
        try {
            $adminId = $this->userId($request);
            $userId = $id;

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'PRESENCE_HISTORY_UNAUTHORIZED', '/api/admin/user/' . $userId . '/presence-history', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $user = User::where('id', $userId)->first();

            if (!$user) {
                $this->log($adminId, 'PRESENCE_HISTORY_FAILED', '/api/admin/user/' . $userId . '/presence-history', ['reason' => 'User not found'], $this->ip($request), 'failed');
                return $this->json(['error' => 'User not found'], 404);
            }

            $activities = UserLog::where('user_id', $userId)->orderByDesc('created_at')->limit(50)->get()->toArray();

            $connectionInfo = [
                'is_live' => false,
                'last_seen' => $user->last_seen,
            ];

            $this->log($adminId, 'PRESENCE_HISTORY_FETCH', '/api/admin/user/' . $userId . '/presence-history', [
                'user_id' => $userId,
                'has_live_connection' => false,
            ], $this->ip($request), 'success');

            return $this->json([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'role' => $user->role,
                    'status' => $user->status,
                    'business_name' => $user->business_name,
                    'is_online' => $user->is_online,
                    'last_seen' => $user->last_seen,
                    'created_at' => $user->created_at,
                ],
                'connection_info' => $connectionInfo,
                'recent_activities' => array_values($activities),
                'total_activities' => count($activities),
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'PRESENCE_HISTORY_ERROR', '/api/admin/user/' . $id . '/presence-history', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ], $this->ip($request), 'failed');
            return $this->json(['error' => 'Failed to fetch presence history'], 500);
        }
    }

    public function updatePresence(Request $request, string $id)
    {
        try {
            $adminId = $this->userId($request);
            $userId = $id;
            $isOnline = $request->input('is_online');

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'UPDATE_PRESENCE_UNAUTHORIZED', '/api/admin/user/' . $userId . '/presence', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            if (!is_bool($isOnline)) {
                $this->log($adminId, 'UPDATE_PRESENCE_FAILED', '/api/admin/user/' . $userId . '/presence', ['reason' => 'Invalid is_online value'], $this->ip($request), 'failed');
                return $this->json(['error' => 'is_online must be boolean'], 400);
            }

            $now = $this->isoNow();

            User::where('id', $userId)->update([
                'is_online' => $isOnline,
                'last_seen' => $now,
                'updated_at' => $now,
            ]);

            $this->log($adminId, 'UPDATE_PRESENCE', '/api/admin/user/' . $userId . '/presence', [
                'user_id' => $userId,
                'is_online' => $isOnline,
                'was_connected' => false,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'User presence updated to ' . ($isOnline ? 'online' : 'offline'),
                'user_id' => $userId,
                'is_online' => $isOnline,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'UPDATE_PRESENCE_ERROR', '/api/admin/user/' . $id . '/presence', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ], $this->ip($request), 'failed');
            return $this->json(['error' => 'Failed to update user presence'], 500);
        }
    }

    public function wsStatus(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'WS_STATUS_UNAUTHORIZED', '/api/admin/ws-status', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $wsStatus = [
                'total_connections' => 0,
                'admin_connections' => 0,
                'user_sessions' => 0,
                'connected_users' => [],
                'server_time' => $this->jsIso(),
                'uptime' => round(microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)), 2),
            ];

            $this->log($adminId, 'WS_STATUS_FETCH', '/api/admin/ws-status', ['total_connections' => 0], $this->ip($request), 'success');

            return $this->json($wsStatus);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'WS_STATUS_ERROR', '/api/admin/ws-status', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json(['error' => 'Failed to fetch WebSocket status'], 500);
        }
    }

    public function migrateRealtime(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $results = [];

            $columnProbes = [
                'users.is_online' => ['users', 'is_online'],
                'users.last_seen' => ['users', 'last_seen'],
                'users.session_id' => ['users', 'session_id'],
                'users.connection_count' => ['users', 'connection_count'],
                'users.total_online_time' => ['users', 'total_online_time'],
            ];

            foreach ($columnProbes as $label => [$table, $column]) {
                try {
                    Supabase::table($table)->select($column)->limit(1)->get();
                    $results[] = ['migration' => $label, 'status' => 'success'];
                } catch (\Throwable $e) {
                    $results[] = ['migration' => $label, 'status' => 'failed', 'error' => $e->getMessage()];
                }
            }

            foreach (['user_presence_history', 'active_sessions'] as $table) {
                try {
                    Supabase::table($table)->select('id')->limit(1)->get();
                    $results[] = ['migration' => $table, 'status' => 'success'];
                } catch (\Throwable $e) {
                    $results[] = ['migration' => $table, 'status' => 'failed', 'error' => $e->getMessage()];
                }
            }

            $this->log($adminId, 'DATABASE_MIGRATION', '/api/admin/migrate/realtime', [
                'migrations' => count($results),
                'successful' => count(array_filter($results, fn($r) => $r['status'] === 'success')),
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Database migration completed successfully',
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'DATABASE_MIGRATION_ERROR', '/api/admin/migrate/realtime', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json([
                'error' => 'Database migration failed',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function migratePasswordReset(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $results = [];

            try {
                Supabase::table('password_reset_codes')->select('id')->limit(1)->get();
                $results[] = ['migration' => 'password_reset_codes', 'status' => 'success'];
            } catch (\Throwable $e) {
                $results[] = ['migration' => 'password_reset_codes', 'status' => 'failed', 'error' => $e->getMessage()];
            }

            $this->log($adminId, 'DATABASE_MIGRATION', '/api/admin/migrate/password-reset', [
                'migrations' => count($results),
                'successful' => count(array_filter($results, fn($r) => $r['status'] === 'success')),
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Password reset database migration completed',
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'DATABASE_MIGRATION_ERROR', '/api/admin/migrate/password-reset', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json([
                'error' => 'Database migration failed',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function databaseInfo(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $columns = [];
            $columnNames = [];
            foreach (['is_online', 'last_seen', 'session_id', 'connection_count', 'total_online_time'] as $col) {
                $ok = false;
                try {
                    Supabase::table('users')->select($col)->limit(1)->get();
                    $ok = true;
                } catch (\Throwable $e) {
                    $ok = false;
                }
                $columns[] = ['column_name' => $col, 'data_type' => 'unknown', 'is_nullable' => 'unknown', 'available' => $ok];
                if ($ok) {
                    $columnNames[] = $col;
                }
            }

            $tableNames = [];
            foreach (['user_presence_history', 'active_sessions'] as $table) {
                try {
                    Supabase::table($table)->select('id')->limit(1)->get();
                    $tableNames[] = $table;
                } catch (\Throwable $e) {
                }
            }

            $schemaInfo = [
                'users_table_columns' => $columns,
                'real_time_tables' => array_values($tableNames),
                'has_is_online' => in_array('is_online', $columnNames, true),
                'has_last_seen' => in_array('last_seen', $columnNames, true),
                'has_presence_history' => in_array('user_presence_history', $tableNames, true),
                'has_active_sessions' => in_array('active_sessions', $tableNames, true),
                'web_socket_connected' => 0,
                'server_time' => $this->jsIso(),
            ];

            $this->log($adminId, 'DATABASE_INFO_FETCH', '/api/admin/database-info', [
                'has_real_time_features' => $schemaInfo['has_is_online'],
            ], $this->ip($request), 'success');

            return $this->json($schemaInfo);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'DATABASE_INFO_ERROR', '/api/admin/database-info', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json(['error' => 'Failed to get database info'], 500);
        }
    }
}