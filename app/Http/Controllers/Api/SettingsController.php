<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Services\Ai\Locale;
use App\Services\BranchContext;
use App\Services\BusinessSettings;
use App\Services\JwtToken;
use App\Services\Supabase;
use App\Services\ThemePreference;
use Illuminate\Http\Request;

/**
 * The administrative Settings module's API.
 *
 * Reads are consolidated here so web and mobile load one authoritative
 * snapshot. Writes to the user/business profile deliberately reuse
 * PUT /api/user/profile (the existing, already-audited path) instead of
 * building a second writer for the same columns. Feature switches and branch
 * management live here because they are business-scoped configuration.
 */
class SettingsController extends BaseController
{
    /**
     * GET /api/settings
     *
     * Everything the settings screen renders, for an administrator of the
     * caller's OWN business. The business is taken from the verified JWT and
     * is never accepted from request input.
     */
    public function show(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kufikia mipangilio'], 403);
        }

        $businessId = $this->businessId($request);
        $userId = $this->userId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana'], 403);
        }

        try {
            $user = User::where('id', $userId)
                ->select('id', 'email', 'role', 'full_name', 'phone', 'business_id', 'branch_id', 'language', 'preferred_theme', 'status')
                ->first();

            if (! $user) {
                return $this->json(['success' => false, 'error' => 'Mtumiaji hajapatikana'], 404);
            }

            $business = Business::where('id', $businessId)->first();
            $branches = Branch::where('business_id', $businessId)->orderBy('branch_name')->get();
            $settings = BusinessSettings::forBusiness($businessId);

            $branchName = null;
            if ($user->branch_id) {
                foreach ($branches as $branch) {
                    if ((string) $branch->id === (string) $user->branch_id) {
                        $branchName = (string) $branch->branch_name;
                        break;
                    }
                }
            }

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => (string) $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'full_name' => $user->full_name,
                    'phone' => $user->phone,
                    'business_id' => $businessId,
                    'branch_id' => $user->branch_id,
                    'branch_name' => $branchName,
                    'language' => $user->language ?: 'sw',
                    'preferred_theme' => $user->preferred_theme ?: ThemePreference::DEFAULT,
                    'status' => $user->status,
                    // Email is intentionally read-only: changing it would
                    // change the login identity and there is no email-change
                    // verification flow to make that safe.
                    'email_editable' => false,
                ],
                'business' => [
                    'id' => $businessId,
                    'business_name' => $business->business_name ?? null,
                    'business_structure' => $business->business_structure ?? BranchContext::NON_BRANCH,
                    'business_location' => $business->business_location ?? null,
                    'business_logo_url' => $business->business_logo_url ?? null,
                    'business_latitude' => $business->business_latitude ?? null,
                    'business_longitude' => $business->business_longitude ?? null,
                    'business_type' => $business->business_type ?? null,
                    'business_description' => $business->business_description ?? null,
                ],
                'branches' => array_map(fn ($branch) => $this->branchPayload($branch), $branches->all()),
                'settings' => $settings,
                'features' => BusinessSettings::features($businessId),
                'options' => [
                    'languages' => array_keys(Locale::TAGS),
                    'themes' => ThemePreference::ALLOWED,
                ],
            ]);
        } catch (\Throwable $error) {
            return $this->dbError($error, 'Imeshindikana kupakia mipangilio');
        }
    }

    /**
     * GET /api/settings/features
     *
     * Readable by every business member (sellers need it to decide which
     * navigation to show). It exposes only the three switches, never business
     * details or another member's information.
     */
    public function features(Request $request)
    {
        $businessId = $this->businessId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana'], 403);
        }

        return $this->json([
            'success' => true,
            'features' => BusinessSettings::features($businessId),
        ]);
    }

    /**
     * PUT /api/settings/features
     *
     * A partial update: only the keys present are changed, so two admins
     * editing different sections cannot overwrite each other's unrelated
     * choices. Every changed key is audited with its old and new value.
     */
    public function updateFeatures(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kubadili mipangilio'], 403);
        }

        $businessId = $this->businessId($request);
        $userId = $this->userId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana'], 403);
        }

        $allowed = array_keys(BusinessSettings::defaults());
        $changes = [];

        foreach ($allowed as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $value = $request->input($key);
            $normalized = $this->boolean($value);

            if ($normalized === null) {
                return $this->json([
                    'success' => false,
                    'error' => 'Thamani ya ' . $key . ' si sahihi',
                    'code' => 'INVALID_SETTING_VALUE',
                ], 422);
            }

            $changes[$key] = $normalized;
        }

        if ($changes === []) {
            return $this->json([
                'success' => false,
                'error' => 'Hakuna mipangilio ya kubadili',
                'code' => 'NO_SETTINGS_PROVIDED',
            ], 422);
        }

        try {
            $result = BusinessSettings::update($businessId, $changes, $userId);

            if ($result['changed'] !== []) {
                $this->log($userId, 'SETTINGS_FEATURES_UPDATED', '/api/settings/features', [
                    'business_id' => $businessId,
                    'changes' => $result['changed'],
                ], $this->ip($request), 'success');
            }

            return $this->json([
                'success' => true,
                'message' => $result['changed'] === []
                    ? 'Mipangilio haijabadilika'
                    : 'Mipangilio imehifadhiwa',
                'settings' => $result['settings'],
                'features' => BusinessSettings::features($businessId),
                'changed' => $result['changed'],
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'SETTINGS_FEATURES_UPDATE_FAILED', '/api/settings/features', [
                'business_id' => $businessId,
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->dbError($error, 'Imeshindikana kuhifadhi mipangilio');
        }
    }

    /**
     * POST /api/settings/business-structure/branchful
     *
     * Converts the admin's own branchless business into a branchful one and
     * registers its first branch in the same database transaction. Existing
     * products, sales, customers, services, expenses and fridge records are
     * moved to the new branch; their quantities and history are untouched.
     */
    public function convertToBranchful(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kubadili muundo wa biashara'], 403);
        }

        $businessId = $this->businessId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana'], 403);
        }

        $current = BranchContext::structure($businessId);
        if ($current === BranchContext::NON_BRANCH) {
            // proceed
        } else {
            return $this->json([
                'success' => false,
                'error' => 'Biashara yako tayari inafanya kazi kwa matawi.',
                'code' => 'ALREADY_BRANCHFUL',
            ], 409);
        }

        $data = $request->validate([
            'branch_name' => 'required|string|min:2|max:120',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $branchName = trim((string) $data['branch_name']);

        try {
            // Reject an obvious duplicate before the RPC, so the admin gets a
            // field-level message. The RPC repeats the check under the lock.
            $duplicateName = false;
            foreach (Branch::where('business_id', $businessId)->get() as $existingBranch) {
                if (mb_strtolower(trim((string) $existingBranch->branch_name)) === mb_strtolower($branchName)) {
                    $duplicateName = true;
                    break;
                }
            }

            if ($duplicateName) {
                return $this->json([
                    'success' => false,
                    'error' => 'Tawi lenye jina hili lipo tayari.',
                    'code' => 'BRANCH_NAME_TAKEN',
                ], 409);
            }

            $result = Supabase::callFunction('convert_business_to_branch', [
                'p_business_id' => $businessId,
                'p_branch_name' => $branchName,
                'p_location' => isset($data['location']) ? trim((string) $data['location']) : null,
                'p_latitude' => $data['latitude'] ?? null,
                'p_longitude' => $data['longitude'] ?? null,
                'p_actor' => $this->userId($request),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Biashara sasa inafanya kazi kwa matawi.',
                'business_structure' => BranchContext::STRUCTURES[1],
                'branch' => $result['branch'] ?? null,
                // The old token still carries non_branch. BranchContext now
                // reads the stored structure first, but a fresh context makes
                // the next request unambiguous and lets the client continue
                // into the branch dashboard immediately.
                'token' => $this->issueStructureToken($request, BranchContext::STRUCTURES[1], null, BranchContext::CROSS),
            ]);
        } catch (\Throwable $error) {
            return $this->conversionFailure($request, $error, 'branchful', 'Imeshindikana kubadili biashara kuwa ya matawi');
        }
    }

    /**
     * POST /api/settings/business-structure/branchless
     *
     * Validates the unified business identity and consolidates the branchful
     * operation inside one transaction. It refuses (never forces) consolidation
     * when the same product exists in multiple branches: merging would either
     * double-count stock or discard part of it.
     */
    public function convertToBranchless(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kubadili muundo wa biashara'], 403);
        }

        $businessId = $this->businessId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana'], 403);
        }

        if (BranchContext::structure($businessId) !== BranchContext::STRUCTURES[1]) {
            return $this->json([
                'success' => false,
                'error' => 'Biashara yako tayari inafanya kazi kwenye eneo moja.',
                'code' => 'ALREADY_BRANCHLESS',
            ], 409);
        }

        $data = $request->validate([
            'business_name' => 'required|string|min:2|max:150',
            'business_location' => 'nullable|string|max:255',
            'business_description' => 'nullable|string|max:1000',
            'business_type' => 'nullable|string|max:100',
            'business_logo_url' => 'nullable|string|max:2048',
            'business_latitude' => 'nullable|numeric|between:-90,90',
            'business_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $result = Supabase::callFunction('convert_business_to_branchless', [
                'p_business_id' => $businessId,
                'p_business_name' => trim((string) $data['business_name']),
                'p_location' => isset($data['business_location']) ? trim((string) $data['business_location']) : null,
                'p_description' => isset($data['business_description']) ? trim((string) $data['business_description']) : null,
                'p_business_type' => isset($data['business_type']) ? trim((string) $data['business_type']) : null,
                'p_logo_url' => isset($data['business_logo_url']) ? trim((string) $data['business_logo_url']) : null,
                'p_latitude' => $data['business_latitude'] ?? null,
                'p_longitude' => $data['business_longitude'] ?? null,
                'p_actor' => $this->userId($request),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Biashara sasa inafanya kazi kwenye eneo moja.',
                'business_structure' => BranchContext::NON_BRANCH,
                'business_name' => $result['business_name'] ?? trim((string) $data['business_name']),
                'token' => $this->issueStructureToken($request, BranchContext::NON_BRANCH, null, BranchContext::NON_BRANCH),
            ]);
        } catch (\Throwable $error) {
            return $this->conversionFailure($request, $error, 'branchless', 'Imeshindikana kubadili biashara kuwa ya eneo moja');
        }
    }

    private function issueStructureToken(Request $request, string $structure, ?string $branchId, string $context): string
    {
        $user = $this->user($request);

        return JwtToken::encode([
            'userId' => $this->userId($request),
            'email' => $user->email ?? null,
            'role' => $this->role($request),
            'business_id' => $this->businessId($request),
            'business_structure' => $structure,
            'branch_id' => $branchId,
            'branch_context' => $context,
        ]);
    }

    /**
     * Translate the database function's stable exception codes into honest API
     * responses. A missing function means the SQL migration has not been run on
     * that deployment; it is reported as unavailable, never as success.
     */
    private function conversionFailure(Request $request, \Throwable $error, string $endpoint, string $fallback)
    {
        $message = (string) $error->getMessage();

        $this->log($this->userId($request), 'BUSINESS_STRUCTURE_CONVERSION_FAILED', '/api/settings/business-structure/' . $endpoint, [
            'business_id' => $this->businessId($request),
            'error' => $message,
        ], $this->ip($request), 'failed');

        if (stripos($message, 'Could not find the function') !== false
            || stripos($message, 'schema cache') !== false) {
            return $this->json([
                'success' => false,
                'error' => 'Mfumo wa kubadili muundo haujawekwa kwenye database. Wasiliana na msimamizi wa mfumo.',
                'code' => 'CONVERSION_NOT_INSTALLED',
            ], 503);
        }

        if (str_contains($message, 'ALREADY_BRANCHFUL')) {
            return $this->json(['success' => false, 'error' => 'Biashara yako tayari inafanya kazi kwa matawi.', 'code' => 'ALREADY_BRANCHFUL'], 409);
        }
        if (str_contains($message, 'ALREADY_BRANCHLESS')) {
            return $this->json(['success' => false, 'error' => 'Biashara yako tayari inafanya kazi kwenye eneo moja.', 'code' => 'ALREADY_BRANCHLESS'], 409);
        }
        if (str_contains($message, 'BRANCH_NAME_TAKEN') || str_contains($message, 'BUSINESS_NAME_TAKEN')) {
            return $this->json(['success' => false, 'error' => 'Jina ulilochagua lipo tayari. Chagua jina lingine.', 'code' => 'NAME_TAKEN'], 409);
        }
        if (str_contains($message, 'BRANCH_NAME_REQUIRED') || str_contains($message, 'BUSINESS_NAME_REQUIRED')) {
            return $this->json(['success' => false, 'error' => 'Jina linahitajika.', 'code' => 'NAME_REQUIRED'], 422);
        }
        if (str_contains($message, 'BUSINESS_NOT_FOUND')) {
            return $this->json(['success' => false, 'error' => 'Biashara haipatikani.', 'code' => 'BUSINESS_NOT_FOUND'], 404);
        }
        if (str_contains($message, 'UNSAFE_DUPLICATE_PRODUCTS')) {
            return $this->json([
                'success' => false,
                'error' => 'Haiwezi kuunganisha kwa usalama: bidhaa yenye jina, SKU au barcode sawa ipo kwenye matawi zaidi ya moja. Badilisha majina/SKU kwanza ili stock isichanganywe au kupotea.',
                'code' => 'UNSAFE_DUPLICATE_PRODUCTS',
            ], 409);
        }

        return $this->json([
            'success' => false,
            'error' => $fallback,
            'code' => 'CONVERSION_FAILED',
            'details' => $message,
        ], 500);
    }

    /**
     * Accept the shapes a toggle can take from JSON, form posts, or a stale
     * client: real booleans, 1/0, and the strings "true"/"false".
     * Returns null for anything ambiguous so the write is rejected rather
     * than silently coerced.
     */
    private function boolean($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ((int) $value === 1) {
                return true;
            }
            if ((int) $value === 0) {
                return false;
            }

            return null;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
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
}
