<?php

namespace App\Http\Controllers\Api;

use App\Models\UserLog;
use Illuminate\Http\Request;

class UserActivityController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $userId = $this->userId($request);

            $activities = UserLog::where('user_id', $userId)
                ->select('action', 'endpoint', 'status', 'created_at', 'details')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            $this->log($userId, 'ACTIVITY_VIEW', '/api/user/activity', ['count' => $activities->count()], $this->ip($request), 'success');

            return $this->json($activities);
        } catch (\Throwable $e) {
            $this->log($request->attributes->get('jwt_user_id'), 'ACTIVITY_VIEW_ERROR', '/api/user/activity', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }
}