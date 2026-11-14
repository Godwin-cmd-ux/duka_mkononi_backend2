<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;

/**
 * Single source of truth for multi-branch identity.
 *
 *   business_id = the whole business
 *   branch_id   = one branch inside it
 *
 * The context is ALWAYS issued by the server (signed JWT claims set by
 * BranchController::context) and re-verified here. A branch_id coming from a
 * request body or query string is never trusted: it must belong to the
 * authenticated business and be active.
 */
class BranchContext
{
    public const NON_BRANCH = 'non_branch';

    public const INDIVIDUAL = 'individual_branch';

    public const CROSS = 'cross_branch';

    /** A branch business whose administrator has not chosen a branch yet. */
    public const UNSELECTED = 'unselected';

    public const STRUCTURES = ['non_branch', 'branch'];

    /**
     * The business structure, defaulting to non_branch when the column is not
     * present yet (a deployment that has not run the migration) or the row
     * cannot be read. Never throws.
     */
    public static function structure(?string $businessId): string
    {
        if (! $businessId) {
            return self::NON_BRANCH;
        }

        try {
            $business = Business::select('id', 'business_structure')->where('id', $businessId)->first();
            $structure = (string) ($business->business_structure ?? '');
        } catch (\Throwable $e) {
            return self::NON_BRANCH;
        }

        return in_array($structure, self::STRUCTURES, true) ? $structure : self::NON_BRANCH;
    }

    /**
     * An active branch of the given business, or null. This is the ownership
     * check every branch-scoped request relies on.
     */
    public static function activeBranch(string $branchId, string $businessId): ?SupabaseRow
    {
        if ($branchId === '' || $businessId === '') {
            return null;
        }

        try {
            return Branch::where('id', $branchId)
                ->where('business_id', $businessId)
                ->where('status', 'active')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Active branches of a business, in registration order. Each row carries
     * the exact fields the selection screen and the dashboard need.
     *
     * @return list<array<string,mixed>>
     */
    public static function options(string $businessId): array
    {
        if ($businessId === '') {
            return [];
        }

        try {
            $rows = Branch::where('business_id', $businessId)
                ->where('status', 'active')
                ->orderBy('created_at')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_map(static function ($row) {
            return [
                'id' => (string) ($row['id'] ?? ''),
                'branch_id' => (string) ($row['id'] ?? ''),
                'business_id' => (string) ($row['business_id'] ?? ''),
                'branch_name' => (string) ($row['branch_name'] ?? ''),
                'location' => $row['location'] ?? null,
                'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
                'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
                'status' => (string) ($row['status'] ?? 'active'),
            ];
        }, $rows));
    }

    /**
     * Resolve the context of a token/request from its verified claims.
     *
     * Rules:
     *   - non_branch business            -> non_branch, no branch
     *   - branch + seller                -> the seller's assigned branch, or
     *                                       unselected when unassigned
     *   - branch + admin, no selection   -> unselected (must choose first)
     *   - branch + individual selection  -> verified against business/status
     *   - branch + cross selection       -> cross_branch, no branch
     *
     * `unselected` is deliberately NOT the same as cross_branch: queries in
     * that state must return nothing rather than everything.
     *
     * @return array{structure:string,context:string,branch_id:?string}
     */
    public static function resolve(?string $businessId, ?string $role, ?string $userId, ?string $claimedBranchId, ?string $claimedContext, ?string $claimedStructure = null): array
    {
        // The stored structure is authoritative. A token issued before an
        // administrator converted the business still carries the old value,
        // and honouring it would leave a newly branchful business in
        // non_branch mode (or the reverse) until that user logged in again.
        // Claims are only the fallback when the business row cannot be read.
        $structure = null;

        if ($businessId) {
            try {
                $business = Business::select('id', 'business_structure')->where('id', $businessId)->first();
                $stored = (string) ($business->business_structure ?? '');

                if (in_array($stored, self::STRUCTURES, true)) {
                    $structure = $stored;
                }
            } catch (\Throwable $e) {
                // Fall through to the signed claim.
            }
        }

        if ($structure === null) {
            $structure = in_array((string) $claimedStructure, self::STRUCTURES, true)
                ? (string) $claimedStructure
                : self::structure($businessId);
        }

        if ($structure === self::NON_BRANCH) {
            return ['structure' => self::NON_BRANCH, 'context' => self::NON_BRANCH, 'branch_id' => null];
        }

        if (! Roles::isBusinessMember($role)) {
            return ['structure' => self::STRUCTURES[1], 'context' => self::UNSELECTED, 'branch_id' => null];
        }

        // A seller is always bound to their assignment, never to a choice made
        // in the UI and never to a context carried by an old token. This check
        // runs BEFORE the claims: an account demoted from admin to seller may
        // still hold a signed cross_branch token, and honouring it would hand
        // them every branch. The database assignment always wins.
        if (Roles::isSeller($role) && $userId) {
            try {
                $user = User::select('id', 'branch_id')->where('id', $userId)->first();
                $assigned = (string) ($user->branch_id ?? '');
            } catch (\Throwable $e) {
                $assigned = '';
            }

            if ($assigned !== '' && self::activeBranch($assigned, (string) $businessId)) {
                return ['structure' => 'branch', 'context' => self::INDIVIDUAL, 'branch_id' => $assigned];
            }

            return ['structure' => 'branch', 'context' => self::UNSELECTED, 'branch_id' => null];
        }

        $claimedBranchId = $claimedBranchId !== null && $claimedBranchId !== '' ? $claimedBranchId : null;
        $claimedContext = (string) ($claimedContext ?? '');

        if ($claimedContext === self::CROSS) {
            return ['structure' => 'branch', 'context' => self::CROSS, 'branch_id' => null];
        }

        if ($claimedBranchId !== null) {
            $branch = self::activeBranch($claimedBranchId, (string) $businessId);
            if ($branch) {
                return ['structure' => 'branch', 'context' => self::INDIVIDUAL, 'branch_id' => (string) $branch->id];
            }
        }

        return ['structure' => 'branch', 'context' => self::UNSELECTED, 'branch_id' => null];
    }
}
