<?php

namespace App\Http\Controllers\Api;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ProfileController extends BaseController
{
    public function show(Request $request)
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        try {
            $user = $this->selectProfile(['id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location', 'business_logo_url', 'business_latitude', 'business_longitude', 'status', 'is_online', 'last_seen', 'created_at', 'updated_at', 'business_type', 'business_description'])
                ->where('id', $userId)
                ->first();

            $this->log($userId, 'PROFILE_VIEW', '/api/user/profile', [], $ip, 'success');

            return $this->json($user);
        } catch (\Throwable $error) {
            if (!preg_match('/does not exist|column.*not found|no such column|could not find|PGRST/i', $error->getMessage())) {
                $this->log($userId, 'PROFILE_VIEW_ERROR', '/api/user/profile', ['error' => $error->getMessage()], $ip, 'failed');
                return $this->dbError($error, 'Hitilafu ya ndani ya server');
            }

            $user = $this->selectProfile(['id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location', 'business_logo_url', 'business_latitude', 'business_longitude', 'status', 'is_online', 'last_seen', 'created_at', 'updated_at'])
                ->where('id', $userId)
                ->first();
            $user->business_type = null;
            $user->business_description = null;

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
            if ($business_name) {
                $updateData['business_name'] = $business_name;
            }
            if (array_key_exists('business_location', $all)) {
                $updateData['business_location'] = $business_location;
            }
            if (array_key_exists('business_logo_url', $all)) {
                $updateData['business_logo_url'] = $business_logo_url;
            }
            if (array_key_exists('business_latitude', $all)) {
                $updateData['business_latitude'] = $business_latitude;
            }
            if (array_key_exists('business_longitude', $all)) {
                $updateData['business_longitude'] = $business_longitude;
            }
            if (array_key_exists('business_type', $all)) {
                $updateData['business_type'] = $business_type ?: null;
            }
            if (array_key_exists('business_description', $all)) {
                $updateData['business_description'] = $business_description ?: null;
            }

            try {
                \App\Models\User::where('id', $userId)->update($updateData);
            } catch (\Throwable $error) {
                if (!preg_match('/does not exist|column.*not found|no such column|could not find|PGRST/i', $error->getMessage())) {
                    throw $error;
                }
                $safeUpdate = $updateData;
                unset($safeUpdate['business_type']);
                unset($safeUpdate['business_description']);
                \App\Models\User::where('id', $userId)->update($safeUpdate);
            }

            try {
                $updatedUser = $this->selectProfile(['id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location', 'business_logo_url', 'business_latitude', 'business_longitude', 'status', 'is_online', 'last_seen', 'created_at', 'business_type', 'business_description'])
                    ->where('id', $userId)
                    ->first();
            } catch (\Throwable $error) {
                if (!preg_match('/does not exist|column.*not found|no such column|could not find|PGRST/i', $error->getMessage())) {
                    throw $error;
                }
                $updatedUser = $this->selectProfile(['id', 'email', 'role', 'full_name', 'phone', 'business_name', 'business_location', 'business_logo_url', 'business_latitude', 'business_longitude', 'status', 'is_online', 'last_seen', 'created_at'])
                    ->where('id', $userId)
                    ->first();
                $updatedUser->business_type = null;
                $updatedUser->business_description = null;
            }

            $fieldsUpdated = array_values(array_diff(array_keys($updateData), ['updated_at', 'last_seen']));

            $this->log($userId, 'PROFILE_UPDATE', '/api/user/profile', [
                'fields_updated' => $fieldsUpdated
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