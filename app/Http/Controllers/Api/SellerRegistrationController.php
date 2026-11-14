<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\BusinessResolver;
use App\Services\Roles;
use Illuminate\Http\Request;

/**
 * Public lookup used ONLY by the seller registration screen.
 *
 * It deliberately exposes less than the public business pages: a seller needs
 * the eligible business's id, name and structure, and the active branches of
 * the business they picked. It never returns owner emails, products, sales or
 * any other private business data.
 *
 * A business is eligible when it has an approved administrator (the same gate
 * the existing seller signup enforced by business name, now applied by id).
 */
class SellerRegistrationController extends BaseController
{
    /**
     * GET /api/seller-registration/businesses?q=...
     *
     * Only businesses that can accept a seller today.
     */
    public function businesses(Request $request)
    {
        $search = mb_strtolower(trim((string) $request->query('q', '')));

        try {
            $businesses = Business::select('id', 'business_name', 'business_structure')->get()->toArray();
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => 'Biashara hazikuweza kupatikana', 'code' => 'BUSINESS_LOOKUP_FAILED'], 500);
        }

        $eligibleIds = $this->approvedAdminBusinessIds();
        $eligibleNames = $this->approvedAdminBusinessKeys();

        $result = [];
        foreach ($businesses as $business) {
            $id = (string) ($business['id'] ?? '');
            $name = trim((string) ($business['business_name'] ?? ''));

            if ($id === '' || $name === '') {
                continue;
            }

            $eligible = in_array($id, $eligibleIds, true)
                || in_array(BusinessResolver::key($name), $eligibleNames, true);

            if (! $eligible) {
                continue;
            }

            if ($search !== '' && ! str_contains(mb_strtolower($name), $search)) {
                continue;
            }

            $result[] = [
                'id' => $id,
                'business_name' => $name,
                'business_structure' => in_array((string) ($business['business_structure'] ?? ''), BranchContext::STRUCTURES, true)
                    ? (string) $business['business_structure']
                    : BranchContext::NON_BRANCH,
            ];
        }

        usort($result, fn ($a, $b) => strcasecmp($a['business_name'], $b['business_name']));

        return $this->json(['success' => true, 'businesses' => $result]);
    }

    /**
     * GET /api/seller-registration/businesses/{businessId}/branches
     *
     * Active branches of one eligible business. A non-branch business returns
     * an empty list; the seller then registers against the business only.
     */
    public function branches(string $businessId)
    {
        try {
            $business = Business::select('id', 'business_name', 'business_structure')->where('id', $businessId)->first();
        } catch (\Throwable $e) {
            $business = null;
        }

        if (! $business) {
            return $this->json(['success' => false, 'error' => 'Biashara haipatikani', 'code' => 'BUSINESS_NOT_FOUND'], 404);
        }

        $name = trim((string) ($business->business_name ?? ''));

        $eligible = in_array((string) $business->id, $this->approvedAdminBusinessIds(), true)
            || in_array(BusinessResolver::key($name), $this->approvedAdminBusinessKeys(), true);

        if (! $eligible) {
            // Do not reveal whether an ineligible business exists.
            return $this->json(['success' => false, 'error' => 'Biashara haipatikani', 'code' => 'BUSINESS_NOT_FOUND'], 404);
        }

        $structure = in_array((string) ($business->business_structure ?? ''), BranchContext::STRUCTURES, true)
            ? (string) $business->business_structure
            : BranchContext::NON_BRANCH;

        $branches = $structure === 'branch'
            ? BranchContext::options((string) $business->id)
            : [];

        return $this->json([
            'success' => true,
            'business' => [
                'id' => (string) $business->id,
                'business_name' => $name,
                'business_structure' => $structure,
            ],
            'branches' => array_map(fn ($branch) => [
                'id' => $branch['id'],
                'branch_name' => $branch['branch_name'],
                'location' => $branch['location'],
            ], $branches),
        ]);
    }

    /** @return list<string> */
    private function approvedAdminBusinessIds(): array
    {
        try {
            return User::whereIn('role', Roles::BUSINESS_OWNERS)
                ->where('status', 'approved')
                ->whereNotNull('business_id')
                ->pluck('business_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<string> */
    private function approvedAdminBusinessKeys(): array
    {
        try {
            return User::whereIn('role', Roles::BUSINESS_OWNERS)
                ->where('status', 'approved')
                ->whereNotNull('business_name')
                ->pluck('business_name')
                ->map(fn ($name) => BusinessResolver::key((string) $name))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
