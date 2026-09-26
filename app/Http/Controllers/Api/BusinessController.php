<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class BusinessController extends BaseController
{
    public function checkBusiness(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        try {
            $business_name = $request->input('business_name');

            if (!$business_name) {
                $this->log(null, 'CHECK_BUSINESS_FAILED', '/api/check-business', [
                    'reason' => 'Missing business name'
                ], $ip, 'failed');

                return $this->json(['error' => 'Jina la biashara linahitajika'], 400);
            }

            $adminUsers = User::select('id', 'email', 'role', 'status', 'created_at')
                ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($business_name))])
                ->where('role', 'admin')
                ->where('status', 'approved')
                ->get();

            $hasApprovedAdmin = $adminUsers && $adminUsers->count() > 0;

            if ($hasApprovedAdmin) {
                $this->log(null, 'CHECK_BUSINESS_SUCCESS', '/api/check-business', [
                    'business_name' => $business_name,
                    'exists' => true,
                    'hasAdmin' => true,
                    'admin_count' => $adminUsers->count()
                ], $ip, 'success');

                return $this->json([
                    'exists' => true,
                    'hasAdmin' => true,
                    'message' => 'Biashara ipo na ina msimamizi. Unaweza kuendelea na usajili wa muuzaji.',
                    'admin' => $adminUsers[0]
                ], 200);
            }

            $anyBusinessUsers = User::select('id', 'email', 'role', 'status')
                ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($business_name))])
                ->whereIn('role', ['admin', 'seller'])
                ->get();

            $businessExists = $anyBusinessUsers && $anyBusinessUsers->count() > 0;

            if ($businessExists) {
                $this->log(null, 'CHECK_BUSINESS_SUCCESS', '/api/check-business', [
                    'business_name' => $business_name,
                    'exists' => true,
                    'hasAdmin' => false,
                    'user_count' => $anyBusinessUsers->count()
                ], $ip, 'success');

                return $this->json([
                    'exists' => true,
                    'hasAdmin' => false,
                    'message' => 'Biashara hii inapatikana lakini haina msimamizi aliyethibitishwa.',
                    'users' => $anyBusinessUsers
                ], 200);
            }

            $this->log(null, 'CHECK_BUSINESS_SUCCESS', '/api/check-business', [
                'business_name' => $business_name,
                'exists' => false,
                'hasAdmin' => false
            ], $ip, 'success');

            return $this->json([
                'exists' => false,
                'hasAdmin' => false,
                'message' => 'Biashara hii haipo kwenye mfumo. Tafadhali jisajili kama msimamizi kwanza.'
            ], 200);
        } catch (\Throwable $error) {
            $this->log(null, 'CHECK_BUSINESS_ERROR', '/api/check-business', [
                'error' => $error->getMessage(),
                'business_name' => $request->input('business_name')
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Hitilafu katika ukaguzi wa biashara',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    public function checkBusinessName(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();
        $business_name = $request->query('business_name');

        try {
            if (!$business_name) {
                $this->log(null, 'CHECK_BUSINESS_NAME_FAILED', '/api/check-business-name', [
                    'reason' => 'Missing business name'
                ], $ip, 'failed');

                return $this->json(['error' => 'Jina la biashara linahitajika'], 400);
            }

            $users = User::select('business_name', 'email')
                ->whereRaw('LOWER(business_name) = ?', [mb_strtolower($business_name)])
                ->where('status', 'approved')
                ->get();

            $exists = $users && $users->count() > 0;

            $this->log(null, 'CHECK_BUSINESS_NAME', '/api/check-business-name', [
                'business_name' => $business_name,
                'exists' => $exists,
                'matches' => $users ? $users->count() : 0
            ], $ip, 'success');

            return $this->json([
                'exists' => $exists,
                'message' => $exists ? 'Jina la biashara tayari limeshasajiliwa' : 'Jina la biashara linapatikana',
                'suggestions' => $exists ? [
                    '"' . $business_name . '"',
                    '"' . $business_name . ' ' . date('Y') . '"',
                    '"' . $business_name . ' Store"'
                ] : []
            ], 200);
        } catch (\Throwable $error) {
            $this->log(null, 'CHECK_BUSINESS_NAME_ERROR', '/api/check-business-name', [
                'error' => $error->getMessage(),
                'business_name' => $business_name
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Hitilafu katika kuangalia jina la biashara',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        try {
            $users = User::select('id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location', 'business_logo_url', 'business_latitude', 'business_longitude', 'status', 'created_at')
                ->where('role', 'admin')
                ->where('status', 'approved')
                ->orderByDesc('created_at')
                ->get();

            $this->log(null, 'BUSINESSES_VIEW', '/api/businesses', [
                'count' => $users ? $users->count() : 0
            ], $ip, 'success');

            return $this->json($users->toArray());
        } catch (\Throwable $error) {
            $this->log(null, 'BUSINESSES_VIEW_ERROR', '/api/businesses', ['error' => $error->getMessage()], $ip, 'failed');

            return $this->json([
                'error' => 'Hitilafu ya kupata biashara',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    public function businessProducts(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();
        $businessId = $request->route('businessId');

        try {
            $business = User::select('id', 'business_name')
                ->where('id', $businessId)
                ->where('role', 'admin')
                ->where('status', 'approved')
                ->first();

            if (!$business) {
                $this->log(null, 'BUSINESS_PRODUCTS_FAILED', '/api/businesses/' . $businessId . '/products', [
                    'reason' => 'Business not found or not approved',
                    'business_id' => $businessId
                ], $ip, 'failed');

                return $this->json([]);
            }

            $products = Product::where('seller_id', $business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $this->log(null, 'BUSINESS_PRODUCTS_VIEW', '/api/businesses/' . $businessId . '/products', [
                'business_id' => $businessId,
                'business_name' => $business->business_name,
                'product_count' => $products ? $products->count() : 0
            ], $ip, 'success');

            return $this->json($products->toArray());
        } catch (\Throwable $error) {
            $this->log(null, 'BUSINESS_PRODUCTS_ERROR', '/api/businesses/' . $businessId . '/products', [
                'error' => $error->getMessage(),
                'business_id' => $businessId
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Hitilafu ya kupata bidhaa',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    public function testBusinesses(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        try {
            $allAdmins = User::select('id', 'business_name', 'status')
                ->where('role', 'admin')
                ->get();

            $approvedAdmins = User::select('id', 'email', 'business_name', 'status')
                ->where('role', 'admin')
                ->where('status', 'approved')
                ->get();

            $this->log(null, 'TEST_BUSINESSES_SUCCESS', '/api/test/businesses', [
                'total_admins' => $allAdmins ? $allAdmins->count() : 0,
                'approved_admins' => $approvedAdmins ? $approvedAdmins->count() : 0
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'Business endpoint test successful',
                'total_admins' => $allAdmins ? $allAdmins->count() : 0,
                'approved_admins' => $approvedAdmins ? $approvedAdmins->count() : 0,
                'all_admins' => $allAdmins->toArray(),
                'approved_admins_list' => $approvedAdmins->toArray()
            ]);
        } catch (\Throwable $error) {
            $this->log(null, 'TEST_BUSINESSES_ERROR', '/api/test/businesses', [
                'error' => $error->getMessage()
            ], $ip, 'failed');

            return $this->json([
                'success' => false,
                'error' => 'Test failed',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    public function show(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();
        $businessId = $request->route('id');

        try {
            $business = User::select('id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location', 'status')
                ->where('id', $businessId)
                ->where('role', 'admin')
                ->where('status', 'approved')
                ->first();

            if (!$business) {
                $this->log(null, 'BUSINESS_VIEW_FAILED', '/api/businesses/' . $businessId, [
                    'reason' => 'Business not found',
                    'business_id' => $businessId
                ], $ip, 'failed');

                return $this->json(['error' => 'Biashara haijapatikana'], 404);
            }

            $this->log(null, 'BUSINESS_VIEW', '/api/businesses/' . $businessId, [
                'business_id' => $businessId,
                'business_name' => $business->business_name
            ], $ip, 'success');

            return $this->json($business);
        } catch (\Throwable $error) {
            $this->log(null, 'BUSINESS_VIEW_ERROR', '/api/businesses/' . $businessId, [
                'error' => $error->getMessage(),
                'business_id' => $businessId
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Hitilafu ya kupata biashara',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();
        $query = $request->route('query');

        try {
            $businesses = User::select('id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location', 'status')
                ->where('role', 'admin')
                ->where('status', 'approved')
                ->where(function ($q) use ($query) {
                    $q->whereRaw('LOWER(business_name) LIKE ?', ['%' . mb_strtolower($query) . '%'])
                        ->orWhereRaw('LOWER(business_location) LIKE ?', ['%' . mb_strtolower($query) . '%'])
                        ->orWhereRaw('LOWER(full_name) LIKE ?', ['%' . mb_strtolower($query) . '%'])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . mb_strtolower($query) . '%']);
                })
                ->orderBy('business_name')
                ->get();

            $this->log(null, 'BUSINESS_SEARCH', '/api/businesses/search/' . $query, [
                'search_query' => $query,
                'result_count' => $businesses ? $businesses->count() : 0
            ], $ip, 'success');

            return $this->json($businesses->toArray());
        } catch (\Throwable $error) {
            $this->log(null, 'BUSINESS_SEARCH_ERROR', '/api/businesses/search/' . $query, [
                'error' => $error->getMessage(),
                'search_query' => $query
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Hitilafu ya kutafuta biashara',
                'details' => $error->getMessage()
            ], 500);
        }
    }

    public function byName(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();
        $business_name = $request->route('business_name');
        $decodedBusinessName = urldecode(trim($business_name));

        try {
            $businesses = User::select('id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location', 'status', 'created_at')
                ->where('role', 'admin')
                ->where('status', 'approved')
                ->whereRaw('LOWER(business_name) = ?', [mb_strtolower($decodedBusinessName)])
                ->orderByDesc('created_at')
                ->get();

            $businessExists = $businesses && $businesses->count() > 0;

            if (!$businessExists) {
                $this->log(null, 'BUSINESS_BY_NAME_NOT_FOUND', '/api/business/by-name/' . $business_name, [
                    'business_name' => $decodedBusinessName,
                    'exists' => false
                ], $ip, 'success');

                return $this->json([
                    'exists' => false,
                    'message' => 'Biashara haijapatikana'
                ], 404);
            }

            $admin = $businesses->first(function ($b) {
                return $b->role === 'admin' && $b->status === 'approved';
            });

            $this->log(null, 'BUSINESS_BY_NAME_FOUND', '/api/business/by-name/' . $business_name, [
                'business_name' => $decodedBusinessName,
                'exists' => true,
                'hasAdmin' => (bool) $admin,
                'match_count' => $businesses->count()
            ], $ip, 'success');

            return $this->json([
                'exists' => true,
                'hasAdmin' => (bool) $admin,
                'business' => $businesses[0],
                'admin' => $admin ?: null,
                'allMatches' => $businesses->toArray()
            ]);
        } catch (\Throwable $error) {
            $this->log(null, 'BUSINESS_BY_NAME_ERROR', '/api/business/by-name/' . $business_name, [
                'error' => $error->getMessage(),
                'business_name' => $business_name
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Hitilafu ya kupata biashara',
                'details' => $error->getMessage()
            ], 500);
        }
    }
}