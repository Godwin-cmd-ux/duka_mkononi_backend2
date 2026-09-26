<?php

namespace App\Http\Controllers\Api;

use App\Models\Reaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReactionController extends BaseController
{
    public function userLikes(Request $request)
    {
        try {
            $userId = $this->userId($request);

            $this->touchLastSeen($userId);

            $likes = Reaction::where('user_id', $userId)
                ->where('reaction_type', 'like')
                ->select(['matangazo_id'])
                ->get()
                ->toArray();

            $likedPostIds = array_map(function ($like) {
                return $like['matangazo_id'];
            }, $likes);

            $this->log($userId, 'LIKES_VIEW', '/api/reactions/user/likes', [
                'count' => count($likedPostIds),
            ], $this->ip($request), 'success');

            return $this->json([
                'likedPosts' => $likedPostIds,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'LIKES_VIEW_ERROR', '/api/reactions/user/likes', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function like(Request $request)
    {
        try {
            $userId = $this->userId($request);
            $matangazo_id = $request->input('matangazo_id');

            $this->touchLastSeen($userId);

            if (!$matangazo_id) {
                $this->log($userId, 'LIKE_FAILED', '/api/reactions/like', [
                    'reason' => 'Missing matangazo_id',
                ], $this->ip($request), 'failed');

                return $this->json(['error' => 'matangazo_id inahitajika'], 400);
            }

            $existing = Reaction::where('user_id', $userId)
                ->where('matangazo_id', $matangazo_id)
                ->where('reaction_type', 'like')
                ->select(['id'])
                ->first();

            $liked = false;

            if ($existing) {
                Reaction::where('user_id', $userId)
                    ->where('matangazo_id', $matangazo_id)
                    ->where('reaction_type', 'like')
                    ->delete();

                $liked = false;
            } else {
                Reaction::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $userId,
                    'matangazo_id' => $matangazo_id,
                    'reaction_type' => 'like',
                    'created_at' => $this->isoNow(),
                ]);

                $liked = true;
            }

            $likeCount = Reaction::where('matangazo_id', $matangazo_id)
                ->where('reaction_type', 'like')
                ->count();

            $reportCount = Reaction::where('matangazo_id', $matangazo_id)
                ->where('reaction_type', 'report')
                ->count();

            $this->log($userId, 'LIKE_ACTION', '/api/reactions/like', [
                'matangazo_id' => $matangazo_id,
                'action' => $liked ? 'liked' : 'unliked',
                'new_like_count' => $likeCount,
            ], $this->ip($request), 'success');

            return $this->json([
                'message' => $liked ? 'Like imeongezwa kikamilifu!' : 'Like imeondolewa',
                'liked' => $liked,
                'like_count' => $likeCount,
                'report_count' => $reportCount,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'LIKE_ERROR', '/api/reactions/like', [
                'error' => $e->getMessage(),
                'matangazo_id' => $request->input('matangazo_id'),
            ], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function report(Request $request)
    {
        try {
            $userId = $this->userId($request);
            $matangazo_id = $request->input('matangazo_id');

            $this->touchLastSeen($userId);

            if (!$matangazo_id) {
                $this->log($userId, 'REPORT_FAILED', '/api/reactions/report', [
                    'reason' => 'Missing matangazo_id',
                ], $this->ip($request), 'failed');

                return $this->json(['error' => 'matangazo_id inahitajika'], 400);
            }

            $existing = Reaction::where('user_id', $userId)
                ->where('matangazo_id', $matangazo_id)
                ->where('reaction_type', 'report')
                ->select(['id'])
                ->first();

            if ($existing) {
                $this->log($userId, 'REPORT_FAILED', '/api/reactions/report', [
                    'reason' => 'Already reported',
                    'matangazo_id' => $matangazo_id,
                ], $this->ip($request), 'failed');

                return $this->json(['error' => 'Umewahi kuripoti tangazo hili'], 400);
            }

            Reaction::create([
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'matangazo_id' => $matangazo_id,
                'reaction_type' => 'report',
                'created_at' => $this->isoNow(),
            ]);

            $reportCount = Reaction::where('matangazo_id', $matangazo_id)
                ->where('reaction_type', 'report')
                ->count();

            $likeCount = Reaction::where('matangazo_id', $matangazo_id)
                ->where('reaction_type', 'like')
                ->count();

            $this->log($userId, 'REPORT_ACTION', '/api/reactions/report', [
                'matangazo_id' => $matangazo_id,
                'report_count' => $reportCount,
            ], $this->ip($request), 'success');

            return $this->json([
                'message' => 'Ripoti imeongezwa kikamilifu!',
                'report_count' => $reportCount,
                'like_count' => $likeCount,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'REPORT_ERROR', '/api/reactions/report', [
                'error' => $e->getMessage(),
                'matangazo_id' => $request->input('matangazo_id'),
            ], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function counts(Request $request, $matangazo_id)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        try {
            $likeCount = Reaction::where('matangazo_id', $matangazo_id)
                ->where('reaction_type', 'like')
                ->count();

            $reportCount = Reaction::where('matangazo_id', $matangazo_id)
                ->where('reaction_type', 'report')
                ->count();

            $this->log(null, 'REACTION_COUNTS', '/api/reactions/matangazo/' . $matangazo_id . '/counts', [
                'matangazo_id' => $matangazo_id,
                'like_count' => $likeCount,
                'report_count' => $reportCount,
            ], $ip, 'success');

            return $this->json([
                'matangazo_id' => $matangazo_id,
                'like_count' => $likeCount,
                'report_count' => $reportCount,
            ]);
        } catch (\Throwable $e) {
            $this->log(null, 'REACTION_COUNTS_ERROR', '/api/reactions/matangazo/' . $matangazo_id . '/counts', [
                'error' => $e->getMessage(),
            ], $ip, 'failed');
            return $this->dbError($e);
        }
    }
}