<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Services\BranchContext as BranchContextService;
use App\Services\JwtToken;
use App\Services\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Branch selection and branch listing.
 *
 * The selected branch is never an accepted request parameter for operational
 * work: this controller verifies ownership once, then mints a signed JWT that
 * carries `branch_id` + `branch_context`. AuthenticateJwt re-verifies that
 * claim on every later request, so a browser or app cannot switch branch by
 * editing a URL, a header, or localStorage.
 */
class BranchController extends BaseController
{
    /**
     * GET /api/branches
     *
     * The authenticated business's active branches, plus the context the
     * caller is currently in. This is what the branch selection screen reads.
     */
    public function index(Request $request)
    {
        $businessId = $this->businessId($request);

        if (! $businessId) {
            return $this->json(['error' => 'Biashara haijapatikana'], 403);
        }

        try {
            $business = Business::where('id', $businessId)->first();
        } catch (\Throwable $e) {
            $business = null;
        }

        $structure = $this->businessStructure($request);
        $branches = BranchContextService::options($businessId);

        // A seller may only see the business and their own branch assignment;
        // they never get to choose another branch.
        if (Roles::isSeller($this->role($request))) {
            $own = $this->branchId($request);
            $branches = array_values(array_filter($branches, fn ($b) => $own && $b['id'] === $own));
        }

        return $this->json([
            'success' => true,
            'business' => [
                'id' => $businessId,
                'business_name' => (string) ($business->business_name ?? $this->businessName($request)),
                'business_location' => $business->business_location ?? null,
                'business_logo_url' => $business->business_logo_url ?? null,
                'business_structure' => $structure,
            ],
            'branches' => $branches,
            'context' => [
                'branch_context' => $this->branchContext($request),
                'branch_id' => $this->branchId($request),
            ],
            'can_select_branch' => $this->isAdmin($request) && $structure === 'branch',
        ]);
    }

    /**
     * POST /api/branch-context  { branch_id: uuid|null }
     *
     * branch_id = a branch of THIS business -> individual_branch
     * branch_id = null                      -> cross_branch
     *
     * Returns a fresh token. The old token keeps working but remains scoped to
     * whatever it carried; clients replace it with this one.
     */
    public function context(Request $request)
    {
        $businessId = $this->businessId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana'], 403);
        }

        if (! $this->isAdmin($request)) {
            return $this->json([
                'success' => false,
                'error' => 'Muuzaji hawezi kubadili tawi. Tawi lako linawekwa na msimamizi.',
                'code' => 'BRANCH_CONTEXT_FORBIDDEN',
            ], 403);
        }

        $structure = $this->businessStructure($request);

        if ($structure !== 'branch') {
            return $this->json([
                'success' => true,
                'context' => BranchContextService::NON_BRANCH,
                'branch' => null,
                'token' => $this->issueToken($request, $businessId, 'non_branch', null, BranchContextService::NON_BRANCH),
            ]);
        }

        $rawBranchId = $request->input('branch_id');
        $branchId = is_string($rawBranchId) ? trim($rawBranchId) : '';

        if ($branchId === '') {
            return $this->json([
                'success' => true,
                'context' => BranchContextService::CROSS,
                'branch' => null,
                'token' => $this->issueToken($request, $businessId, 'branch', null, BranchContextService::CROSS),
            ]);
        }

        $branch = BranchContextService::activeBranch($branchId, $businessId);

        if (! $branch) {
            $this->log($this->userId($request), 'BRANCH_CONTEXT_DENIED', '/api/branch-context', [
                'requested_branch_id' => $branchId,
                'business_id' => $businessId,
            ], $this->ip($request), 'failed');

            return $this->json([
                'success' => false,
                'error' => 'Tawi hili haipatikani kwenye biashara yako au limezimwa.',
                'code' => 'BRANCH_NOT_FOUND',
            ], 404);
        }

        $this->log($this->userId($request), 'BRANCH_CONTEXT_SELECTED', '/api/branch-context', [
            'branch_id' => $branch->id,
            'branch_name' => $branch->branch_name,
        ], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'context' => BranchContextService::INDIVIDUAL,
            'branch' => [
                'id' => (string) $branch->id,
                'branch_name' => (string) $branch->branch_name,
                'location' => $branch->location,
                'latitude' => $branch->latitude !== null ? (float) $branch->latitude : null,
                'longitude' => $branch->longitude !== null ? (float) $branch->longitude : null,
            ],
            'token' => $this->issueToken($request, $businessId, 'branch', (string) $branch->id, BranchContextService::INDIVIDUAL),
        ]);
    }

    /**
     * PUT /api/admin/users/{id}/branch  { branch_id: uuid|null }
     *
     * Assigns a seller to one branch of the admin's own business. Passing null
     * un-assigns them. Cross-business assignments are impossible.
     */
    public function assignSeller(Request $request, string $id)
    {
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $businessId = $this->businessId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana'], 403);
        }

        $target = User::where('id', $id)->select('id', 'role', 'business_id', 'branch_id')->first();

        if (! $target || (string) $target->business_id !== (string) $businessId) {
            return $this->json(['success' => false, 'error' => 'Mtumiaji haipatikani kwenye biashara yako'], 404);
        }

        if ((string) $target->role !== 'seller') {
            return $this->json(['success' => false, 'error' => 'Tawi hupewa muuzaji tu'], 422);
        }

        $rawBranchId = $request->input('branch_id');
        $branchId = is_string($rawBranchId) ? trim($rawBranchId) : '';

        if ($branchId === '') {
            User::where('id', $id)->where('business_id', $businessId)->update(['branch_id' => null]);

            return $this->json(['success' => true, 'message' => 'Muuzaji ameondolewa kwenye tawi', 'branch_id' => null]);
        }

        $branch = BranchContextService::activeBranch($branchId, $businessId);

        if (! $branch) {
            return $this->json(['success' => false, 'error' => 'Tawi hili haipatikani kwenye biashara yako'], 404);
        }

        User::where('id', $id)->where('business_id', $businessId)->update(['branch_id' => (string) $branch->id]);

        $this->log($this->userId($request), 'SELLER_BRANCH_ASSIGNED', '/api/admin/users/' . $id . '/branch', [
            'target_user_id' => $id,
            'branch_id' => $branch->id,
        ], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'message' => 'Muuzaji amewekwa kwenye tawi',
            'branch_id' => (string) $branch->id,
            'branch_name' => (string) $branch->branch_name,
        ]);
    }

    /**
     * POST /api/branches  { branch_name, location?, latitude?, longitude? }
     *
     * Adds a branch to the admin's OWN branch-structured business. A business
     * that registered as single-location must not silently grow a branch
     * structure through this endpoint; that conversion stays an explicit,
     * separate decision.
     */
    public function store(Request $request)
    {
        $guard = $this->requireBranchAdmin($request);
        if ($guard) {
            return $guard;
        }

        $businessId = (string) $this->businessId($request);

        $structure = $this->storedStructure($businessId);
        if ($structure !== 'branch') {
            return $this->json([
                'success' => false,
                'error' => 'Biashara hii haijasajiliwa kama biashara yenye matawi.',
                'code' => 'STRUCTURE_NOT_BRANCH',
            ], 422);
        }

        $data = $request->validate([
            'branch_name' => 'required|string|min:2|max:120',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $name = trim($data['branch_name']);

        if ($this->branchNameTaken($businessId, $name)) {
            return $this->json([
                'success' => false,
                'error' => 'Tawi lenye jina hili lipo tayari.',
                'code' => 'BRANCH_NAME_TAKEN',
            ], 409);
        }

        try {
            $branch = Branch::query()->create([
                'id' => Str::uuid()->toString(),
                'business_id' => $businessId,
                'branch_name' => $name,
                'location' => isset($data['location']) ? trim((string) $data['location']) : null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'status' => 'active',
                'created_at' => $this->isoNow(),
                'updated_at' => $this->isoNow(),
            ]);

            $this->log($this->userId($request), 'BRANCH_CREATED', '/api/branches', [
                'branch_id' => (string) $branch->id,
                'branch_name' => $name,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Tawi limeongezwa',
                'branch' => $this->branchPayload($branch),
            ], 201);
        } catch (\Throwable $error) {
            return $this->dbError($error, 'Imeshindikana kuongeza tawi');
        }
    }

    /**
     * PUT /api/branches/{id}  { branch_name?, location?, latitude?, longitude?, status? }
     *
     * Edits one branch of the caller's own business. The id never changes, and
     * sellers, products, sales and stock keep pointing at the same branch row.
     */
    public function update(Request $request, string $id)
    {
        $guard = $this->requireBranchAdmin($request);
        if ($guard) {
            return $guard;
        }

        $businessId = (string) $this->businessId($request);
        $branch = Branch::where('id', $id)->where('business_id', $businessId)->first();

        if (! $branch) {
            return $this->json([
                'success' => false,
                'error' => 'Tawi hili haipatikani kwenye biashara yako',
                'code' => 'BRANCH_NOT_FOUND',
            ], 404);
        }

        $data = $request->validate([
            'branch_name' => 'sometimes|required|string|min:2|max:120',
            'location' => 'sometimes|nullable|string|max:255',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        if ($data === []) {
            return $this->json(['success' => false, 'error' => 'Hakuna taarifa za kubadili'], 422);
        }

        $update = ['updated_at' => $this->isoNow()];
        $before = $this->branchPayload($branch);

        if (array_key_exists('branch_name', $data)) {
            $name = trim((string) $data['branch_name']);

            if ($this->branchNameTaken($businessId, $name, (string) $branch->id)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Tawi lenye jina hili lipo tayari.',
                    'code' => 'BRANCH_NAME_TAKEN',
                ], 409);
            }

            $update['branch_name'] = $name;
        }

        foreach (['location', 'latitude', 'longitude'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        if (isset($data['status']) && $data['status'] !== ($branch->status ?: 'active')) {
            // Never strand the business with zero usable branches: login and
            // branch selection both require at least one active branch.
            if ($data['status'] === 'inactive' && $this->activeBranchCount($businessId) <= 1) {
                return $this->json([
                    'success' => false,
                    'error' => 'Huwezi kuzima tawi pekee linalotumika.',
                    'code' => 'LAST_ACTIVE_BRANCH',
                ], 409);
            }

            $update['status'] = $data['status'];
        }

        try {
            Branch::where('id', $id)->where('business_id', $businessId)->update($update);

            $after = array_merge($before, array_map(
                fn ($value) => is_string($value) ? trim($value) : $value,
                array_intersect_key($update, $before)
            ));

            $changed = [];
            foreach ($after as $key => $value) {
                if (($before[$key] ?? null) !== $value) {
                    $changed[$key] = ['from' => $before[$key] ?? null, 'to' => $value];
                }
            }

            $this->log($this->userId($request), 'BRANCH_UPDATED', '/api/branches/' . $id, [
                'branch_id' => $id,
                'changes' => $changed,
            ], $this->ip($request), 'success');

            $fresh = Branch::where('id', $id)->where('business_id', $businessId)->first();

            return $this->json([
                'success' => true,
                'message' => 'Taarifa za tawi zimehifadhiwa',
                'branch' => $this->branchPayload($fresh ?: $branch),
            ]);
        } catch (\Throwable $error) {
            return $this->dbError($error, 'Imeshindikana kuhifadhi tawi');
        }
    }

    /**
     * DELETE /api/branches/{id}
     *
     * Deactivates rather than deletes. Sellers assigned to the branch keep
     * their assignment; existing stock, sales and reports remain attached to
     * the branch id, and reactivating restores access to all of it.
     */
    public function destroy(Request $request, string $id)
    {
        $guard = $this->requireBranchAdmin($request);
        if ($guard) {
            return $guard;
        }

        $businessId = (string) $this->businessId($request);
        $branch = Branch::where('id', $id)->where('business_id', $businessId)->first();

        if (! $branch) {
            return $this->json([
                'success' => false,
                'error' => 'Tawi hili haipatikani kwenye biashara yako',
                'code' => 'BRANCH_NOT_FOUND',
            ], 404);
        }

        if (($branch->status ?: 'active') === 'inactive') {
            return $this->json(['success' => true, 'message' => 'Tawi limezimwa', 'branch' => $this->branchPayload($branch)]);
        }

        if ($this->activeBranchCount($businessId) <= 1) {
            return $this->json([
                'success' => false,
                'error' => 'Huwezi kuzima tawi pekee linalotumika.',
                'code' => 'LAST_ACTIVE_BRANCH',
            ], 409);
        }

        try {
            Branch::where('id', $id)->where('business_id', $businessId)->update([
                'status' => 'inactive',
                'updated_at' => $this->isoNow(),
            ]);

            $this->log($this->userId($request), 'BRANCH_DEACTIVATED', '/api/branches/' . $id, [
                'branch_id' => $id,
                'branch_name' => $branch->branch_name,
            ], $this->ip($request), 'success');

            $payload = $this->branchPayload($branch);
            $payload['status'] = 'inactive';

            return $this->json([
                'success' => true,
                'message' => 'Tawi limezimwa',
                'branch' => $payload,
            ]);
        } catch (\Throwable $error) {
            return $this->dbError($error, 'Imeshindikana kuzima tawi');
        }
    }

    private function requireBranchAdmin(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if (! $this->businessId($request)) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana'], 403);
        }

        return null;
    }

    /** The structure from the business row, not from a possibly stale token. */
    private function storedStructure(string $businessId): string
    {
        try {
            $structure = Business::where('id', $businessId)->value('business_structure');
        } catch (\Throwable $e) {
            $structure = null;
        }

        return $structure === 'branch' ? 'branch' : 'non_branch';
    }

    private function branchNameTaken(string $businessId, string $name, ?string $ignoreId = null): bool
    {
        $needle = mb_strtolower($name);

        foreach (Branch::where('business_id', $businessId)->get() as $branch) {
            if ($ignoreId !== null && (string) $branch->id === $ignoreId) {
                continue;
            }

            if (mb_strtolower(trim((string) $branch->branch_name)) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function activeBranchCount(string $businessId): int
    {
        return Branch::where('business_id', $businessId)->where('status', 'active')->count();
    }

    private function branchPayload($branch): array
    {
        return [
            'id' => (string) $branch->id,
            'business_id' => (string) $branch->business_id,
            'branch_name' => (string) $branch->branch_name,
            'location' => $branch->location,
            'latitude' => $branch->latitude !== null ? (float) $branch->latitude : null,
            'longitude' => $branch->longitude !== null ? (float) $branch->longitude : null,
            'status' => $branch->status ?: 'active',
        ];
    }

    private function issueToken(Request $request, string $businessId, string $structure, ?string $branchId, string $context): string
    {
        $user = $this->user($request);

        return JwtToken::encode([
            'userId' => $this->userId($request),
            'email' => $user->email ?? null,
            'role' => $this->role($request),
            'business_id' => $businessId,
            'business_structure' => $structure,
            'branch_id' => $branchId,
            'branch_context' => $context,
        ]);
    }
}
