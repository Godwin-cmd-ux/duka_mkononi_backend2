<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\User;
use App\Services\BusinessResolver;
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
        $full_name = $request->input('full_name');
        $phone = $request->input('phone');
        $business_name = $request->input('business_name');
        $business_location = $request->input('business_location');
        $business_logo_url = $request->input('business_logo_url');
        $business_latitude = $request->input('business_latitude');
        $business_longitude = $request->input('business_longitude');
        $business_type = $request->input('business_type');
        $business_description = $request->input('business_description');

        try {
            $businessKeys = array_values(array_intersect(self::BUSINESS_FIELDS, array_keys($all)));
            $hasBusinessEdit = count($businessKeys) > 0 || $business_name !== null;

            if (!$full_name && !$business_logo_url && !$business_type && !$business_description && !array_key_exists('business_latitude', $all) && !array_key_exists('business_longitude', $all)) {
                $this->log($userId, 'PROFILE_UPDATE_FAILED', '/api/user/profile', ['reason' => 'Full name required'], $ip, 'failed');
                return $this->json(['error' => 'Jina kamili linahitajika'], 400);
            }

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

            if ($updatedBusinessId && $hasBusinessEdit) {
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

                \App\Models\Business::where('id', $updatedBusinessId)->update($businessUpdate);
            } elseif ($hasBusinessEdit && !$updatedBusinessId) {
                // A customer (or an unmigrated account) has no business to edit.
                $this->log($userId, 'PROFILE_UPDATE_NO_BUSINESS', '/api/user/profile', [
                    'reason' => 'Account has no business_id',
                    'ignored_fields' => $businessKeys,
                ], $ip, 'failed');
            }

            if (count($updateData) > 2) {
                \App\Models\User::where('id', $userId)->update($updateData);
            }

            $updatedUser = $this->readProfile($userId);

            $fieldsUpdated = array_values(array_diff(array_keys($updateData), ['updated_at', 'last_seen']));

            $this->log($userId, 'PROFILE_UPDATE', '/api/user/profile', [
                'fields_updated' => $fieldsUpdated,
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
            'last_seen', 'created_at', 'updated_at', 'business_id',
        ])->where('id', $userId)->first();

        if (!$user) {
            return $user;
        }

        $businessId = $user->business_id ?: null;
        $business = $businessId ? \App\Models\Business::where('id', $businessId)->first() : null;

        if ($business) {
            foreach (self::BUSINESS_FIELDS as $field) {
                $user->{$field} = $business->{$field} ?? null;
            }
        } else {
            $user->business_type = null;
            $user->business_description = null;
        }

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