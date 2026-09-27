<?php

namespace App\Http\Controllers\Api;

use App\Models\Advertisement;
use App\Models\Reaction;
use App\Models\User;
use App\Services\BusinessResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AdvertisementController extends BaseController
{
    public function publicIndex(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        try {
            $matangazo = Advertisement::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();

            $now = time();
            $visibleMatangazo = array_values(array_filter($matangazo, function ($m) use ($now) {
                $isFree = (bool) ($m['is_free'] ?? false);
                $paidNotExpired = ($m['payment_status'] ?? null) === 'completed' && !empty($m['expires_at']) && strtotime($m['expires_at']) > $now;
                return $isFree || $paidNotExpired;
            }));

            $matangazoIds = array_column($visibleMatangazo, 'id');
            $reactionCounts = $this->reactionCounts($matangazoIds);

            $userIds = array_filter(array_map(function ($m) {
                return $m['user_id'] ?? null;
            }, $visibleMatangazo));
            $userMap = $userIds ? User::whereIn('id', array_unique($userIds))->get()->keyBy('id')->toArray() : [];

            $matangazoWithCounts = array_map(function ($m) use ($reactionCounts, $userMap) {
                $m['like_count'] = $reactionCounts[$m['id']]['like_count'] ?? 0;
                $m['report_count'] = $reactionCounts[$m['id']]['report_count'] ?? 0;
                $m['users'] = $userMap[$m['user_id'] ?? null] ?? null;
                return $m;
            }, $visibleMatangazo);

            $this->log(null, 'MATANGAZO_VIEW_PUBLIC', '/api/matangazo', [
                'count' => count($matangazoWithCounts),
            ], $ip, 'success');

            return $this->json($matangazoWithCounts);
        } catch (\Throwable $e) {
            $this->log(null, 'MATANGAZO_VIEW_ERROR', '/api/matangazo', ['error' => $e->getMessage()], $ip, 'failed');
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function my(Request $request)
    {
        try {
            $userId = $this->userId($request);

            $this->touchLastSeen($userId);

            $matangazo = Advertisement::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();

            $matangazoIds = array_column($matangazo, 'id');
            $reactionCounts = $this->reactionCounts($matangazoIds);

            $userIds = array_filter(array_map(function ($m) {
                return $m['user_id'] ?? null;
            }, $matangazo));
            $userMap = $userIds ? User::whereIn('id', array_unique($userIds))->get()->keyBy('id')->toArray() : [];

            $matangazoWithCounts = array_map(function ($m) use ($reactionCounts, $userMap) {
                $m['like_count'] = $reactionCounts[$m['id']]['like_count'] ?? 0;
                $m['report_count'] = $reactionCounts[$m['id']]['report_count'] ?? 0;
                $m['users'] = $userMap[$m['user_id'] ?? null] ?? null;
                return $m;
            }, $matangazo);

            $this->log($userId, 'MATANGAZO_VIEW_MY', '/api/matangazo/my', [
                'count' => count($matangazoWithCounts),
            ], $this->ip($request), 'success');

            return $this->json($matangazoWithCounts);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'MATANGAZO_VIEW_ERROR', '/api/matangazo/my', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json([
                'error' => 'Hitilafu ya kupakia matangazo',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $userId = $this->userId($request);
            $description = $request->input('description');
            $media_url = $request->input('media_url');
            $media_type = $request->input('media_type');
            $thumbnail_url = $request->input('thumbnail_url');
            $expires_at = $request->input('expires_at');

            $this->touchLastSeen($userId);

            if (!$description || !$media_url) {
                $this->log($userId, 'MATANGAZO_CREATE_FAILED', '/api/matangazo', [
                    'reason' => 'Missing required fields',
                ], $this->ip($request), 'failed');

                return $this->json([
                    'error' => 'Maelezo na URL ya media zinahitajika',
                ], 400);
            }

            $user = User::where('id', $userId)->select(['business_name', 'full_name'])->first();

            if (!$user) {
                throw new \RuntimeException('User not found');
            }

            // Prefer the canonical business name from `businesses`. After the
            // business_id migration that is the authoritative display name;
            // users.business_name is a legacy snapshot that can still carry an
            // older spelling. Falls back to the user columns unchanged.
            $businessName = BusinessResolver::forUser($userId)?->business_name;
            $title = ($businessName ?: $user->business_name) ?: ($user->full_name ?: 'Matangazo');

            $isPendingPayment = $request->input('payment_status') === 'pending';

            $newMatangazoId = Str::uuid()->toString();
            $now = $this->isoNow();

            $matangazoData = [
                'id' => $newMatangazoId,
                'user_id' => $userId,
                'title' => $title,
                'description' => $description,
                'media_url' => $media_url,
                'media_type' => $media_type ?: 'image',
                'thumbnail_url' => $thumbnail_url ?: $media_url,
                'payment_status' => $isPendingPayment ? 'pending' : 'completed',
                'is_free' => !$isPendingPayment,
                'order_tracking_id' => null,
                'expires_at' => $isPendingPayment ? null : ($expires_at ?: null),
                'is_active' => !$isPendingPayment,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            Advertisement::create($matangazoData);

            $newMatangazo = Advertisement::where('id', $newMatangazoId)->first()->toArray();

            $fullUser = User::where('id', $userId)->first();
            $newMatangazo['users'] = $fullUser ? $fullUser->toArray() : null;
            $newMatangazo['like_count'] = 0;
            $newMatangazo['report_count'] = 0;

            $this->log($userId, 'MATANGAZO_CREATE', '/api/matangazo', [
                'matangazo_id' => $newMatangazo['id'],
                'title' => $newMatangazo['title'],
                'media_type' => $newMatangazo['media_type'],
            ], $this->ip($request), 'success');

            return $this->json([
                'message' => 'Tangazo limeongezwa kikamilifu!',
                'data' => $newMatangazo,
            ], 201);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'MATANGAZO_CREATE_ERROR', '/api/matangazo', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->json([
                'error' => 'Hitilafu katika kuongeza matangazo',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $matangazoId = $id;
            $userId = $this->userId($request);

            $this->touchLastSeen($userId);

            $matangazo = Advertisement::where('id', $matangazoId)->select(['user_id', 'title', 'media_url', 'media_type'])->first();

            if (!$matangazo) {
                $this->log($userId, 'MATANGAZO_DELETE_FAILED', '/api/matangazo/:id', [
                    'reason' => 'Matangazo not found',
                    'matangazo_id' => $matangazoId,
                ], $this->ip($request), 'failed');

                return $this->json(['error' => 'Tangazo halipo'], 404);
            }

            if ($matangazo->user_id !== $userId) {
                $this->log($userId, 'MATANGAZO_DELETE_FAILED', '/api/matangazo/:id', [
                    'reason' => 'Unauthorized access',
                    'matangazo_id' => $matangazoId,
                    'owner_id' => $matangazo->user_id,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'error' => 'Huna ruhusa ya kufuta tangazo hili',
                ], 403);
            }

            // 1) Delete the media from Cloudinary first (per the deletion
            // flow: CDN first, then the database row). Media cleanup is
            // silent from the user's perspective — if the CDN delete fails
            // (e.g. credentials not configured) the database deletion still
            // succeeds and the failure is only visible in the server logs.
            $cloudDeleted = null;
            $cloudTarget = $this->cloudinaryTarget($matangazo->media_url ?? null);
            if ($cloudTarget) {
                $cloudDeleted = $this->deleteCloudinaryAsset($cloudTarget);
            }

            // 2) Delete the database rows (reactions first, then the
            // matangazo itself — a HARD delete so it disappears from the
            // user's list too; the old soft-delete kept it visible and made
            // deletion look broken).
            try {
                Reaction::where('matangazo_id', $matangazoId)->delete();
            } catch (\Throwable $e) {
                // Reactions cleanup is best-effort.
            }

            Advertisement::where('id', $matangazoId)->delete();

            $this->log($userId, 'MATANGAZO_DELETE', '/api/matangazo/:id', [
                'matangazo_id' => $matangazoId,
                'title' => $matangazo->title,
                'cloudinary_deleted' => $cloudDeleted,
            ], $this->ip($request), 'success');

            // No CDN details are exposed to the client — deletion either
            // succeeded end-to-end or the admin checks the server logs.
            return $this->json([
                'message' => 'Tangazo limefutwa kikamilifu!',
                'id' => $matangazoId,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'MATANGAZO_DELETE_ERROR', '/api/matangazo/:id', [
                'error' => $e->getMessage(),
                'matangazo_id' => $id,
            ], $this->ip($request), 'failed');
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Extract the Cloudinary public_id + resource type from an asset URL.
     * Uploads from this app look like:
     *   https://res.cloudinary.com/<cloud>/image/upload/v<ver>/<public_id>.<ext>
     * Returns null for non-Cloudinary URLs.
     */
    private function cloudinaryTarget(?string $url): ?array
    {
        if (!$url || !str_contains($url, 'res.cloudinary.com')) {
            return null;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $parts = explode('/', trim($path, '/'));
        $uploadIdx = array_search('upload', $parts, true);
        if ($uploadIdx === false || $uploadIdx < 1) {
            return null;
        }

        $resourceType = $parts[$uploadIdx - 1] ?? 'image';
        if (!in_array($resourceType, ['image', 'video', 'raw'], true)) {
            $resourceType = 'image';
        }

        $segments = array_slice($parts, $uploadIdx + 1);
        // Drop the delivery version segment (v1234567890) if present.
        if (isset($segments[0]) && preg_match('/^v\\d+$/', $segments[0])) {
            array_shift($segments);
        }

        $publicId = implode('/', $segments);
        $publicId = preg_replace('/\\.[A-Za-z0-9]+$/', '', $publicId);
        if ($publicId === '') {
            return null;
        }

        return ['public_id' => $publicId, 'resource_type' => $resourceType];
    }

    /**
     * Signed Cloudinary destroy call. Returns:
     *   true  — asset deleted (result "ok")
     *   false — credentials missing or Cloudinary refused
     *   null  — nothing to delete / not a Cloudinary URL
     */
    private function deleteCloudinaryAsset(array $target): ?bool
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME', 'dooidwbgt');
        $apiKey = env('CLOUDINARY_API_KEY', '');
        $apiSecret = env('CLOUDINARY_API_SECRET', '');

        if ($apiKey === '' || $apiSecret === '') {
            $this->log(null, 'CLOUDINARY_DESTROY_SKIPPED', 'cloudinary/destroy', [
                'reason' => 'CLOUDINARY_API_KEY / CLOUDINARY_API_SECRET not configured',
            ], null, 'warning');
            return false;
        }

        try {
            $timestamp = time();
            // Cloudinary's signature = sha1(params sorted alphabetically + api_secret).
            $signature = sha1('public_id=' . $target['public_id'] . '&timestamp=' . $timestamp . $apiSecret);

            $response = Http::asForm()
                ->timeout(15)
                ->post("https://api.cloudinary.com/v1_1/{$cloudName}/{$target['resource_type']}/destroy", [
                    'public_id' => $target['public_id'],
                    'timestamp' => $timestamp,
                    'api_key' => $apiKey,
                    'signature' => $signature,
                ]);

            $result = is_array($response->json()) ? ($response->json()['result'] ?? null) : null;

            if ($response->ok() && in_array($result, ['ok', 'not found'], true)) {
                return true;
            }

            $this->log(null, 'CLOUDINARY_DESTROY_FAILED', 'cloudinary/destroy', [
                'status' => $response->status(),
                'result' => $result,
                'public_id' => $target['public_id'],
            ], null, 'failed');
            return false;
        } catch (\Throwable $e) {
            $this->log(null, 'CLOUDINARY_DESTROY_ERROR', 'cloudinary/destroy', [
                'error' => $e->getMessage(),
                'public_id' => $target['public_id'],
            ], null, 'failed');
            return false;
        }
    }

    private function reactionCounts(array $matangazoIds): array
    {
        if (count($matangazoIds) === 0) {
            return [];
        }

        $reactions = Reaction::whereIn('matangazo_id', $matangazoIds)
            ->select(['matangazo_id', 'reaction_type'])
            ->get()
            ->toArray();

        $counts = [];
        foreach ($matangazoIds as $id) {
            $counts[$id] = ['like_count' => 0, 'report_count' => 0];
        }

        foreach ($reactions as $reaction) {
            if ($reaction['reaction_type'] === 'like') {
                $counts[$reaction['matangazo_id']]['like_count']++;
            } elseif ($reaction['reaction_type'] === 'report') {
                $counts[$reaction['matangazo_id']]['report_count']++;
            }
        }

        return $counts;
    }
}