<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\User;
use App\Services\Ai\Locale;
use App\Services\BusinessResolver;
use App\Services\ThemePreference;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ProfileController extends BaseController
{
    /**
     * Business columns that live on `businesses` after the migration. The
     * response is still flattened into the same top-level keys the clients
     * already read, so no client change is needed.
     */
    private const BUSINESS_FIELDS = [
        'business_name',
        'business_location',
        'business_logo_url',
        'business_latitude',
        'business_longitude',
        'business_type',
        'business_description',
    ];

    public function show(Request $request)
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        try {
            $user = $this->readProfile($userId);

            $this->log($userId, 'PROFILE_VIEW', '/api/user/profile', [], $ip, 'success');

            return $this->json($user);
        } catch (\Throwable $error) {
            $this->log($userId, 'PROFILE_VIEW_ERROR', '/api/user/profile', ['error' => $error->getMessage()], $ip, 'failed');
            return $this->dbError($error, 'Hitilafu ya ndani ya server');
        }
    }

    public function update(Request $request)
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        $all = $request->all();

        // Validate only the fields that were sent: this endpoint serves both
        // the dashboard edit modal and the Settings page, which saves one
        // section at a time. Protected columns (role, status, business_id,
        // branch_id, email) are never read from this payload at all, so no
        // amount of extra JSON can change them.
        $validator = \Illuminate\Support\Facades\Validator::make($all, [
            'full_name' => 'sometimes|required|string|min:2|max:120',
            'phone' => 'sometimes|nullable|string|max:30|regex:/^[0-9+\-\s()]*$/',
            'business_name' => 'sometimes|required|string|min:2|max:150',
            'business_location' => 'sometimes|nullable|string|max:255',
            'business_logo_url' => 'sometimes|nullable|string|max:2048',
            'business_latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'business_longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'business_type' => 'sometimes|nullable|string|max:100',
            'business_description' => 'sometimes|nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            $this->log($userId, 'PROFILE_UPDATE_FAILED', '/api/user/profile', ['reason' => 'Validation failed'], $ip, 'failed');

            return $this->json([
                'error' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach (['full_name', 'phone', 'business_name', 'business_location', 'business_logo_url', 'business_type', 'business_description'] as $field) {
            if (array_key_exists($field, $all) && is_string($all[$field])) {
                $all[$field] = trim($all[$field]);
            }
        }

        $full_name = $all['full_name'] ?? null;
        $phone = $all['phone'] ?? null;
        $business_name = $all['business_name'] ?? null;
        $business_location = $all['business_location'] ?? null;
        $business_logo_url = $all['business_logo_url'] ?? null;
        $business_latitude = $all['business_latitude'] ?? null;
        $business_longitude = $all['business_longitude'] ?? null;
        $business_type = $all['business_type'] ?? null;
        $business_description = $all['business_description'] ?? null;

        try {
            $businessKeys = array_values(array_intersect(self::BUSINESS_FIELDS, array_keys($all)));
            $hasBusinessEdit = count($businessKeys) > 0 || $business_name !== null;

            $recognised = array_merge(self::BUSINESS_FIELDS, [
                'full_name', 'phone', 'business_name', 'business_location',
                'business_logo_url', 'business_latitude', 'business_longitude',
            ]);

            if (array_intersect($recognised, array_keys($all)) === []) {
                $this->log($userId, 'PROFILE_UPDATE_FAILED', '/api/user/profile', ['reason' => 'No editable field supplied'], $ip, 'failed');
                return $this->json(['error' => 'Hakuna taarifa za kuhifadhi'], 400);
            }

            $beforeUser = User::where('id', $userId)->select('full_name', 'phone')->first();

            $updateData = [
                'updated_at' => $this->isoNow(),
                'last_seen' => $this->isoNow()
            ];

            if ($full_name) {
                $updateData['full_name'] = $full_name;
            }
            if (array_key_exists('phone', $all)) {
                $updateData['phone'] = $phone ?: null;
            }

            $updatedBusinessId = $this->businessId($request) ?: BusinessResolver::idForUser($userId);
            $beforeBusiness = null;
            $businessUpdate = null;

            if ($updatedBusinessId && $hasBusinessEdit) {
                $beforeBusiness = Business::where('id', $updatedBusinessId)->first();
                $businessUpdate = ['updated_at' => $this->isoNow()];

                if (array_key_exists('business_name', $all) && $business_name) {
                    $incomingKey = BusinessResolver::key($business_name);
                    $currentBusiness = Business::where('id', $updatedBusinessId)->first();

                    // Only a *real* rename re-keys the business.
                    //
                    // The clients keep `business_name` in localStorage from
                    // login (the legacy users column) and echo it back on every
                    // save, so a save that never touched the name still arrives
                    // as the old spelling. Treating that as a rename re-keyed
                    // the row and blew up on businesses_legacy_name_key_uniq
                    // whenever the legacy spelling was already taken by a
                    // sibling row. Both of this business's own names - the
                    // canonical one and any legacy spelling its members still
                    // carry - therefore count as unchanged.
                    $ownNames = [BusinessResolver::key($currentBusiness->business_name ?? '')];
                    $ownNames = array_merge($ownNames, User::where('business_id', $updatedBusinessId)
                        ->whereNotNull('business_name')
                        ->pluck('business_name')
                        ->map(fn ($n) => BusinessResolver::key($n))
                        ->all());

                    if ($incomingKey !== '' && ! in_array($incomingKey, $ownNames, true)) {
                        $owner = Business::where('legacy_name_key', $incomingKey)->first();

                        if ($owner && $owner->id !== $currentBusiness?->id) {
                            $this->log($userId, 'PROFILE_UPDATE_NAME_TAKEN', '/api/user/profile', [
                                'requested_name' => $business_name,
                            ], $ip, 'failed');

                            return $this->json([
                                'error' => 'Jina la biashara limeshughulikiwa na biashara nyingine. Chagua jina lingine.',
                            ], 409);
                        }

                        $businessUpdate['business_name'] = $business_name;
                        $businessUpdate['legacy_name_key'] = $incomingKey;
                    }
                }
                if (array_key_exists('business_location', $all)) {
                    $businessUpdate['business_location'] = $business_location;
                }
                if (array_key_exists('business_logo_url', $all)) {
                    $businessUpdate['business_logo_url'] = $business_logo_url;
                }
                if (array_key_exists('business_latitude', $all)) {
                    $businessUpdate['business_latitude'] = $business_latitude;
                }
                if (array_key_exists('business_longitude', $all)) {
                    $businessUpdate['business_longitude'] = $business_longitude;
                }
                if (array_key_exists('business_type', $all)) {
                    $businessUpdate['business_type'] = $business_type ?: null;
                }
                if (array_key_exists('business_description', $all)) {
                    $businessUpdate['business_description'] = $business_description ?: null;
                }

                $businessUpdated = Business::where('id', $updatedBusinessId)->update($businessUpdate);

                if ($businessUpdated > 0) {
                    $savedBusiness = Business::where('id', $updatedBusinessId)->first();

                    if ($savedBusiness) {
                        // Keep the legacy user columns in sync with the
                        // authoritative business row, but only for fields
                        // this request actually changed.
                        foreach (['business_name', 'business_location', 'business_logo_url', 'business_latitude', 'business_longitude', 'business_type', 'business_description'] as $field) {
                            if (array_key_exists($field, $businessUpdate)) {
                                $updateData[$field] = $savedBusiness->{$field};
                            }
                        }
                    }
                }
            } elseif ($hasBusinessEdit && !$updatedBusinessId) {
                // A customer (or an unmigrated account) has no business to edit.
                $this->log($userId, 'PROFILE_UPDATE_NO_BUSINESS', '/api/user/profile', [
                    'reason' => 'Account has no business_id',
                    'ignored_fields' => $businessKeys,
                ], $ip, 'failed');
            }

            if (count($updateData) > 2) {
                $userUpdated = User::where('id', $userId)->update($updateData);

                if ($userUpdated === 0 && $hasBusinessEdit && $updatedBusinessId) {
                    return $this->json([
                        'error' => 'Eneo la biashara halikuhifadhiwa. Jaribu tena.',
                    ], 500);
                }
            }

            $updatedUser = $this->readProfile($userId);

            $fieldsUpdated = array_values(array_diff(array_keys($updateData), ['updated_at', 'last_seen']));

            // Audit old/new values for every field that actually changed. Keys
            // that are internal (timestamps, the derived rename key) are left
            // out; no secret is ever part of this payload.
            $changes = [];
            foreach (['full_name', 'phone'] as $field) {
                if (! array_key_exists($field, $updateData)) {
                    continue;
                }

                $old = $beforeUser->{$field} ?? null;
                $new = $updateData[$field];

                if ((string) $old !== (string) $new) {
                    $changes[$field] = ['from' => $old, 'to' => $new];
                }
            }

            if ($beforeBusiness && is_array($businessUpdate)) {
                foreach ($businessUpdate as $field => $new) {
                    if (in_array($field, ['updated_at', 'legacy_name_key'], true)) {
                        continue;
                    }

                    $old = $beforeBusiness->{$field} ?? null;
                    if ((string) $old !== (string) $new) {
                        $changes[$field] = ['from' => $old, 'to' => $new ?: null];
                    }
                }
            }

            $this->log($userId, 'PROFILE_UPDATE', '/api/user/profile', [
                'fields_updated' => $fieldsUpdated,
                'changes' => $changes,
                'business_id' => $updatedBusinessId,
            ], $ip, 'success');

            return $this->json([
                'message' => 'Profaili imesasishwa kikamilifu!',
                'user' => $updatedUser
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'PROFILE_UPDATE_ERROR', '/api/user/profile', ['error' => $error->getMessage()], $ip, 'failed');
            return $this->dbError($error, 'Hitilafu ya ndani ya server');
        }
    }

    /**
     * Persist the caller's explicit theme choice from the website's public-page
     * selector (guarded by auth.jwt via PUT /api/user/theme).
     *
     * Mirrors how the language preference is stored: only allow-listed
     * identifiers are accepted (422 otherwise) and the stored value is echoed
     * back so the client can adopt it. If the write fails — e.g. the
     * preferred_theme migration has not run yet — the error is reported
     * honestly instead of pretending success; the client keeps its local
     * preference either way.
     */
    public function theme(Request $request)
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        $theme = ThemePreference::normalize($request->input('theme'));

        if (!$theme) {
            $this->log($userId, 'PREFERRED_THEME_UPDATE_FAILED', '/api/user/theme', ['reason' => 'Invalid theme'], $ip, 'failed');
            return $this->json(['error' => 'Mandhari si sahihi'], 422);
        }

        try {
            User::where('id', $userId)->update(['preferred_theme' => $theme]);

            $this->log($userId, 'PREFERRED_THEME_UPDATE', '/api/user/theme', ['preferred_theme' => $theme], $ip, 'success');

            return $this->json(['preferred_theme' => $theme]);
        } catch (\Throwable $error) {
            $this->log($userId, 'PREFERRED_THEME_UPDATE_ERROR', '/api/user/theme', ['error' => $error->getMessage()], $ip, 'failed');
            return $this->dbError($error, 'Hitilafu ya ndani ya server');
        }
    }

    /**
     * Persist the caller's preferred language on their account.
     *
     * This is the SAME preference the website language widget and the mobile
     * LanguageContext already use (users.language, surfaced in the JWT and
     * re-synchronized at login). The Settings page writes it here instead of
     * introducing a second language store; the allow-list is the one
     * App\Services\Ai\Locale::TAGS catalogue.
     */
    public function language(Request $request)
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        $raw = $request->input('language');
        $language = is_string($raw) ? strtolower(trim($raw)) : '';

        if (! array_key_exists($language, Locale::TAGS)) {
            $this->log($userId, 'PREFERRED_LANGUAGE_UPDATE_FAILED', '/api/user/language', ['reason' => 'Invalid language'], $ip, 'failed');

            return $this->json(['error' => 'Lugha si sahihi'], 422);
        }

        try {
            User::where('id', $userId)->update([
                'language' => $language,
                'updated_at' => $this->isoNow(),
            ]);

            $this->log($userId, 'PREFERRED_LANGUAGE_UPDATE', '/api/user/language', ['language' => $language], $ip, 'success');

            return $this->json(['language' => $language]);
        } catch (\Throwable $error) {
            return $this->dbError($error, 'Imeshindikana kuhifadhi lugha');
        }
    }

    /**
     * Change the authenticated user's own password.
     *
     * The current password is verified before the new one is written, so a
     * hijacked session cannot silently lock the owner out. Passwords are stored
     * with the same bcrypt scheme as registration and reset (AuthController /
     * ResetPasswordController), so password_verify can read them back.
     */
    public function changePassword(Request $request)
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        // Accept both the camelCase used by the password-reset screens and the
        // snake_case used elsewhere in this API.
        $currentPassword = $request->input('current_password', $request->input('currentPassword'));
        $newPassword = $request->input('new_password', $request->input('newPassword'));
        $confirmPassword = $request->input('confirm_password', $request->input('confirmPassword'));

        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            $this->log($userId, 'PASSWORD_CHANGE_FAILED', '/api/user/password', ['reason' => 'Missing fields'], $ip, 'failed');
            return $this->json(['error' => 'Nenosiri la sasa, nenosiri jipya na uthibitisho zinahitajika'], 400);
        }

        if ($newPassword !== $confirmPassword) {
            $this->log($userId, 'PASSWORD_CHANGE_FAILED', '/api/user/password', ['reason' => 'Passwords do not match'], $ip, 'failed');
            return $this->json(['error' => 'Nenosiri jipya na uthibitisho havifanani'], 400);
        }

        if (strlen($newPassword) < 6) {
            $this->log($userId, 'PASSWORD_CHANGE_FAILED', '/api/user/password', ['reason' => 'Password too short'], $ip, 'failed');
            return $this->json(['error' => 'Nenosiri jipya lazima liwe na herufi 6 au zaidi'], 400);
        }

        try {
            $user = \App\Models\User::select('id', 'password')->where('id', $userId)->first();

            if (!$user) {
                $this->log($userId, 'PASSWORD_CHANGE_FAILED', '/api/user/password', ['reason' => 'User not found'], $ip, 'failed');
                return $this->json(['error' => 'Akaunti haipatikani'], 404);
            }

            if (!password_verify($currentPassword, (string) $user->password)) {
                $this->log($userId, 'PASSWORD_CHANGE_FAILED', '/api/user/password', ['reason' => 'Current password incorrect'], $ip, 'failed');
                return $this->json(['error' => 'Nenosiri la sasa si sahihi'], 400);
            }

            if (password_verify($newPassword, (string) $user->password)) {
                $this->log($userId, 'PASSWORD_CHANGE_FAILED', '/api/user/password', ['reason' => 'New password same as current'], $ip, 'failed');
                return $this->json(['error' => 'Nenosiri jipya linapaswa kuwa tofauti na la sasa'], 400);
            }

            \App\Models\User::where('id', $userId)->update([
                'password' => password_hash($newPassword, PASSWORD_BCRYPT),
                'updated_at' => $this->isoNow(),
            ]);

            $this->log($userId, 'PASSWORD_CHANGED', '/api/user/password', ['success' => true], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'Nenosiri limebadilishwa kikamilifu!',
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'PASSWORD_CHANGE_ERROR', '/api/user/password', ['error' => $error->getMessage()], $ip, 'failed');
            return $this->dbError($error, 'Hitilafu ya ndani ya server');
        }
    }

    /**
     * Read the user row and merge the business columns from `businesses` into
     * the same flat shape the clients already consume.
     *
     * When no `businesses` row exists (customers, or an account that predates
     * the migration) the user's own legacy columns are left untouched so the
     * screen still shows a name; only the two columns that never existed on
     * `users` are reported as null.
     */
    private function readProfile(string $userId)
    {
        $user = $this->selectProfile([
            'id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location',
            'business_logo_url', 'business_latitude', 'business_longitude', 'status', 'is_online',
            'last_seen', 'created_at', 'updated_at', 'business_id', 'branch_id',
        ])->where('id', $userId)->first();

        if (!$user) {
            return $user;
        }

        // Legacy users may not have business_id yet; resolve their business
        // from the existing name instead of returning stale user columns.
        $businessId = $user->business_id ?: BusinessResolver::idForUser($userId);
        $business = $businessId ? \App\Models\Business::where('id', $businessId)->first() : null;

        if ($business) {
            foreach (self::BUSINESS_FIELDS as $field) {
                $user->{$field} = $business->{$field} ?? null;
            }
        } else {
            $user->business_type = null;
            $user->business_description = null;
        }

        // A seller's branch, read from the same business so a stale/dangling
        // assignment can never label the account with another tenant's branch.
        $branchId = $user->branch_id ?? null;
        $branch = null;

        if ($branchId && $businessId) {
            $branch = \App\Models\Branch::where('id', $branchId)
                ->where('business_id', $businessId)
                ->first();
        }

        $user->branch_name = $branch->branch_name ?? null;
        $user->branch_location = $branch->location ?? null;

        return $user;
    }

    public function onboarding(Request $request)
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        $all = $request->all();
        $business_logo_url = $request->input('business_logo_url');
        $business_location = $request->input('business_location');
        $business_latitude = $request->input('business_latitude');
        $business_longitude = $request->input('business_longitude');

        try {
            if (!array_key_exists('business_logo_url', $all) && !array_key_exists('business_location', $all) && !array_key_exists('business_latitude', $all) && !array_key_exists('business_longitude', $all)) {
                return $this->json(['error' => 'Hakuna data ya kusasisha.'], 400);
            }

            $updateData = [
                'updated_at' => $this->isoNow()
            ];
            if (array_key_exists('business_logo_url', $all)) {
                $updateData['business_logo_url'] = $business_logo_url;
            }
            if (array_key_exists('business_location', $all)) {
                $updateData['business_location'] = $business_location;
            }
            if (array_key_exists('business_latitude', $all)) {
                $updateData['business_latitude'] = $business_latitude;
            }
            if (array_key_exists('business_longitude', $all)) {
                $updateData['business_longitude'] = $business_longitude;
            }

            \App\Models\User::where('id', $userId)->update($updateData);

            $updatedUser = \App\Models\User::where('id', $userId)
                ->select('id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location', 'business_logo_url', 'business_latitude', 'business_longitude', 'status')
                ->first();

            $fieldsUpdated = array_values(array_diff(array_keys($updateData), ['updated_at']));

            $this->log($userId, 'ONBOARDING_PROFILE_UPDATE', '/api/onboarding/profile', [
                'fields_updated' => $fieldsUpdated
            ], $ip, 'success');

            return $this->json([
                'message' => 'Profaili ya onboarding imesasishwa kikamilifu!',
                'user' => $updatedUser
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'ONBOARDING_PROFILE_UPDATE_ERROR', '/api/onboarding/profile', ['error' => $error->getMessage()], $ip, 'failed');
            return $this->dbError($error, 'Hitilafu ya ndani ya server');
        }
    }

    private function selectProfile(array $columns)
    {
        return \App\Models\User::select($columns);
    }
}