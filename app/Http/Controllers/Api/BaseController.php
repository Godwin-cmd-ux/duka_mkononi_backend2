<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\Locale;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\BusinessSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseController
{
    protected function user(Request $request)
    {
        return $request->attributes->get('jwt_user');
    }

    protected function userId(Request $request): ?string
    {
        return $request->attributes->get('jwt_user_id');
    }

    protected function role(Request $request): ?string
    {
        return $request->attributes->get('jwt_role');
    }

    /** Platform super admin (canonical super_admin + legacy system_admin). */
    protected function isSuperAdmin(Request $request): bool
    {
        return \App\Services\Roles::isSuperAdmin($this->role($request));
    }

    /** Business admin or above (admin, super_admin, legacy system_admin). */
    protected function isAdmin(Request $request): bool
    {
        return \App\Services\Roles::isAdmin($this->role($request));
    }

    protected function businessName(Request $request): ?string
    {
        return $request->attributes->get('jwt_business_name');
    }

    /**
     * Stable business identity for the authenticated user. Never accept this
     * from client input - scope every business query with this instead.
     */
    protected function businessId(Request $request): ?string
    {
        return $request->attributes->get('jwt_business_id');
    }

    protected function ip(Request $request): ?string
    {
        return $request->attributes->get('jwt_ip');
    }

    /** 'non_branch' | 'branch' for the authenticated business. */
    protected function businessStructure(Request $request): string
    {
        return (string) ($request->attributes->get('jwt_business_structure') ?: BranchContext::NON_BRANCH);
    }

    /** 'non_branch' | 'individual_branch' | 'cross_branch' | 'unselected'. */
    protected function branchContext(Request $request): string
    {
        return (string) ($request->attributes->get('jwt_branch_context') ?: BranchContext::NON_BRANCH);
    }

    /** The verified selected branch (individual context only). */
    protected function branchId(Request $request): ?string
    {
        $id = $request->attributes->get('jwt_branch_id');

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    /**
     * Gate an optional module at the API boundary.
     *
     * The web and mobile navigation hide a disabled module, but this is the
     * actual security boundary: a hand-written request must be refused even
     * when the caller never saw the button. The check reads the same cached
     * service every surface uses, so web and mobile can never disagree.
     */
    protected function requireFeature(Request $request, string $feature): ?JsonResponse
    {
        if (BusinessSettings::enabled($feature, $this->businessId($request))) {
            return null;
        }

        return $this->json([
            'success' => false,
            'error' => 'Kipengele hiki kimezimwa na msimamizi wa biashara.',
            'code' => 'FEATURE_DISABLED',
            'feature' => $feature,
        ], 403);
    }

    protected function isNonBranchContext(Request $request): bool
    {
        return $this->branchContext($request) === BranchContext::NON_BRANCH;
    }

    protected function isIndividualBranchContext(Request $request): bool
    {
        return $this->branchContext($request) === BranchContext::INDIVIDUAL;
    }

    protected function isCrossBranchContext(Request $request): bool
    {
        return $this->branchContext($request) === BranchContext::CROSS;
    }

    /**
     * Scope a PostgREST query to the selected branch.
     *
     *   non_branch  -> no filter (legacy behaviour, every row is business-wide)
     *   cross_branch-> no filter (all branches of the business)
     *   individual  -> branch_id = selected branch
     *   unselected  -> impossible filter, never an unscoped read
     *
     * $shared adds branch rows that are intentionally business-wide
     * (services with branch_id NULL are available to every branch).
     */
    protected function scopeBranch($query, Request $request, bool $shared = false)
    {
        $context = $this->branchContext($request);

        if ($context === BranchContext::INDIVIDUAL) {
            $branchId = $this->branchId($request);

            if (! $branchId) {
                return $query->whereRaw('id = ?', ['00000000-0000-0000-0000-000000000000']);
            }

            if ($shared) {
                return $query->where(function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
            }

            return $query->where('branch_id', $branchId);
        }

        if ($context === BranchContext::UNSELECTED) {
            return $query->whereRaw('id = ?', ['00000000-0000-0000-0000-000000000000']);
        }

        return $query;
    }

    /**
     * The branch a write must be stamped with.
     *
     * Returns [branch_id|null, errorResponse|null]:
     *   non_branch -> [null, null] (existing behaviour)
     *   individual -> [selected id, null]
     *   cross      -> [null, 422]  choose a branch before writing
     *   unselected -> [null, 409]  select a branch first
     */
    protected function requireWriteBranch(Request $request): array
    {
        $context = $this->branchContext($request);

        if ($context === BranchContext::NON_BRANCH) {
            return [null, null];
        }

        if ($context === BranchContext::INDIVIDUAL && $this->branchId($request)) {
            return [$this->branchId($request), null];
        }

        if ($context === BranchContext::CROSS) {
            return [null, $this->json([
                'success' => false,
                'error' => 'Chagua tawi kabla ya kufanya mabadiliko. Cross-branch ni muktadha wa muhtasari.',
                'code' => 'BRANCH_REQUIRED',
            ], 422)];
        }

        return [null, $this->json([
            'success' => false,
            'error' => 'Chagua tawi lako kwanza.',
            'code' => 'BRANCH_SELECTION_REQUIRED',
        ], 409)];
    }

    /**
     * Capability #22 - the single locale resolution for every AI endpoint.
     *
     * Order (see App\Services\Ai\Locale::resolve): the request's own `locale`
     * field, then the caller's stored account language surfaced by the JWT
     * middleware, then the configured application default. Capabilities must
     * call this instead of carrying their own copy, so the rule can only exist
     * in one place.
     */
    protected function resolveLocale(Request $request): string
    {
        return Locale::resolve($request, (string) $request->attributes->get('jwt_language', ''));
    }

    protected function isoNow(string $format = 'millis'): string
    {
        return substr(now()->toISOString(true), 0, 23) . '';
    }

    protected function touchLastSeen(string $userId): void
    {
        try {
            \App\Models\User::where('id', $userId)->update([
                'last_seen' => now()->toISOString(true),
                'updated_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $e) {
        }
    }

    protected function log(?string $userId, string $action, string $endpoint, array $details = [], ?string $ip = null, string $status = 'success'): void
    {
        AuditLogger::log($userId, $action, $endpoint, $details, $ip, $status);
    }

    protected function json($data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function error(string $message, int $status = 500, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['error' => $message], $extra), $status);
    }

    protected function dbError(\Throwable $e, string $fallback = 'Hitilafu ya ndani ya server'): JsonResponse
    {
        return response()->json(['error' => $fallback, 'details' => $e->getMessage()], 500);
    }

    protected function take(\Illuminate\Http\Request $request, array $keys, array $defaults = []): array
    {
        $out = [];
        foreach ($keys as $k) {
            $v = $request->input($k);
            $out[$k] = $v ?? ($defaults[$k] ?? null);
        }
        return $out;
    }
}