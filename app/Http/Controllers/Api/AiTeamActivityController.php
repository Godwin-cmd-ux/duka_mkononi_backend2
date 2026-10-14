<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\UserLog;
use App\Services\Ai\ActivityDigest;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\ReportSummary;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Capability #17 - Team Activity Digest.
 *
 * GET /api/ai/activity/digest   - a manager-facing digest of what this
 *                                 business's own staff recorded, over a daily
 *                                 or weekly window.
 * GET /api/ai/activity/history  - past digest runs (the audit trail).
 *
 * Grounding:
 *  - the ONLY source is the existing activity log (`user_logs`), read through
 *    the business's own member ids taken from the authenticated session - never
 *    from client input, so one business can never read another's activity;
 *  - every count, actor and timestamp is computed in PHP (ActivityDigest), so
 *    the digest can never disagree with the rows it was built from;
 *  - the model may at most REWORD the deterministic narrative and every figure
 *    in its reply is gated against the payload it was given;
 *  - the digest never infers intent, misconduct or employee performance, and
 *    it states that as a limitation on every response;
 *  - members with no recorded events in the window are reported explicitly, so
 *    missing logging coverage is visible rather than silently averaged away;
 *  - reading the digest does not change the digest: the digest's own audit
 *    actions are excluded from the scan.
 */
class AiTeamActivityController extends BaseController
{
    private const CACHE_PREFIX = 'ai.activity_digest.';

    /** The digest's own audit action - excluded from the activity it reports. */
    public const AUDIT_ACTION = 'AI_TEAM_ACTIVITY';

    public function digest(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $ip = $this->ip($request);
        $userId = $this->userId($request);

        if (! config('ai.features.team_activity', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        /* Manager-facing: the same two roles the manager portal serves. */
        $role = $this->role($request);
        if (! in_array($role, ['admin', 'seller'], true)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $cfg = (array) config('ai.team_activity', []);

        $period = strtolower(trim((string) $request->input('period', 'this_week')));
        if (! in_array($period, ActivityDigest::PERIODS, true)) {
            return $this->json([
                'error' => ActivityDigest::msg('err_period', $locale),
                'code' => 'INVALID_PERIOD',
            ], 422);
        }

        $windows = ActivityDigest::windows($period, date('Y-m-d'));
        if ((int) ($windows['days'] ?? 0) < 1) {
            return $this->json([
                'error' => ActivityDigest::msg('err_period', $locale),
                'code' => 'INVALID_PERIOD',
            ], 422);
        }

        try {
            /* Scope: this caller's business, resolved server-side. */
            $businessId = $this->businessId($request) ?: BusinessResolver::forUser($userId)?->id;
            if (! $businessId) {
                return $this->json([
                    'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                    'code' => 'NO_BUSINESS',
                ], 400);
            }

            $memberIds = BusinessResolver::memberIds($businessId, ['admin', 'seller']);
            if ($userId && ! in_array($userId, $memberIds, true)) {
                $memberIds[] = $userId;
            }
            $memberIds = array_values(array_unique(array_filter($memberIds)));
            if ($memberIds === []) {
                return $this->json(['error' => 'Biashara haijaonyesha wanachama.', 'code' => 'NO_MEMBERS'], 400);
            }

            $cacheKey = self::CACHE_PREFIX.md5(json_encode([
                $businessId, $memberIds, $period, $windows['current'], $locale,
            ]));

            $cached = null;
            try {
                $cached = cache()->get($cacheKey);
            } catch (\Throwable $e) {
                $cached = null;
            }
            if (is_array($cached)) {
                $cached['cached'] = true;
                $this->log($userId, self::AUDIT_ACTION, '/api/ai/activity/digest', [
                    'business_id' => $businessId,
                    'period' => $period,
                    'locale' => $locale,
                    'cached' => true,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ], $ip, 'success');

                return $this->json($cached);
            }

            /* The actor directory: names only, never emails or phone numbers. */
            $members = [];
            foreach (User::whereIn('id', $memberIds)->select(['id', 'full_name', 'role'])->get() as $member) {
                $members[] = [
                    'id' => (string) $member->id,
                    'name' => trim((string) ($member->full_name ?? '')),
                    'role' => (string) ($member->role ?? ''),
                ];
            }

            $maxRows = max(100, min(20000, (int) ($cfg['max_rows'] ?? 4000)));

            $currentRows = $this->rowsFor($memberIds, $windows['current'], $maxRows);
            $previousRows = $windows['previous'] !== null
                ? $this->rowsFor($memberIds, (array) $windows['previous'], $maxRows)
                : [];

            $digest = ActivityDigest::summarize([
                'locale' => $locale,
                'period' => $period,
                'windows' => $windows,
                'members' => $members,
                'logs' => $currentRows,
                'previous_logs' => $previousRows,
                'noteworthy_limit' => (int) ($cfg['noteworthy_limit'] ?? 12),
            ]);

            /* Scanning is bounded; when the cap is hit we say so instead of
               presenting a partial window as if it were complete. */
            $truncated = count($currentRows) >= $maxRows;
            if ($truncated) {
                $digest['limitations'][] = ActivityDigest::msg('lim_truncated', $locale, ['max' => $maxRows]);
            }

            /* Optional model narration - same verified figures, numbers gated. */
            $narration = $this->narrate($digest, $locale, $ip, (bool) ($cfg['ai_narrate'] ?? true));

            $payload = [
                'success' => true,
                'capability' => ActivityDigest::CAPABILITY,
                'locale' => $locale,
                'business_id' => $businessId,
                'generated_at' => now()->toISOString(true),
                'period' => $digest['period'],
                'period_label' => $digest['period_label'],
                'windows' => $digest['windows'],
                'totals' => $digest['totals'],
                'comparison' => $digest['comparison'],
                'categories' => $digest['categories'],
                'members' => $digest['members'],
                'silent_members' => $digest['silent_members'],
                'day_series' => $digest['day_series'],
                'noteworthy' => $digest['noteworthy'],
                'narrative' => $digest['narrative'],
                'digest_text' => $narration['text'],
                'sources' => $digest['sources'],
                'limitations' => $digest['limitations'],
                'truncated' => $truncated,
                'ai' => [
                    'available' => $narration['available'],
                    'degraded' => $narration['degraded'],
                    'code' => $narration['code'],
                    'reason' => $narration['reason'],
                    'meta' => $narration['meta'],
                ],
            ];

            $ttl = max(1, (int) ($cfg['cache_minutes'] ?? 5));
            try {
                cache()->put($cacheKey, $payload, $ttl * 60);
            } catch (\Throwable $e) {
                // A cache failure must never break the digest.
            }

            $this->log($userId, self::AUDIT_ACTION, '/api/ai/activity/digest', [
                'business_id' => $businessId,
                'period' => $period,
                'locale' => $locale,
                'events' => $digest['totals']['events'],
                'actors' => $digest['totals']['actors'],
                'silent' => count($digest['silent_members']),
                'direction' => $digest['comparison']['direction'],
                'truncated' => $truncated,
                'ai_status' => $narration['degraded'] ? 'degraded' : 'ok',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json($payload);
        } catch (\Throwable $error) {
            $this->log($userId, self::AUDIT_ACTION.'_ERROR', '/api/ai/activity/digest', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /**
     * Past digest runs for this business, from the durable audit rows - so a
     * manager (or an auditor) can see when a digest was produced and what it
     * reported, even after the cache expired.
     */
    public function history(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.team_activity', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $role = $this->role($request);
        if (! in_array($role, ['admin', 'seller'], true)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);

        try {
            $businessId = $this->businessId($request) ?: BusinessResolver::forUser($userId)?->id;
            if (! $businessId) {
                return $this->json([
                    'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                    'code' => 'NO_BUSINESS',
                ], 400);
            }

            $memberIds = array_values(array_unique(array_filter(
                BusinessResolver::memberIds($businessId, ['admin', 'seller'])
            )));
            if ($memberIds === []) {
                return $this->json(['error' => 'Biashara haijaonyesha wanachama.', 'code' => 'NO_MEMBERS'], 400);
            }

            $limit = max(1, min(100, (int) config('ai.team_activity.history_limit', 20)));

            $rows = UserLog::where('action', self::AUDIT_ACTION)
                ->orderByDesc('created_at')
                ->limit(300)
                ->get()
                ->toArray();

            $names = [];
            foreach (User::whereIn('id', $memberIds)->select(['id', 'full_name'])->get() as $member) {
                $names[(string) $member->id] = trim((string) ($member->full_name ?? ''));
            }

            $history = [];
            foreach ($rows as $row) {
                $details = $row['details'] ?? null;
                if (is_string($details)) {
                    $details = json_decode($details, true);
                }
                if (! is_array($details) || ($details['business_id'] ?? null) !== $businessId) {
                    continue;
                }

                $by = (string) ($row['user_id'] ?? '');
                $history[] = [
                    'period' => (string) ($details['period'] ?? ''),
                    'locale' => (string) ($details['locale'] ?? ''),
                    'events' => (int) ($details['events'] ?? 0),
                    'actors' => (int) ($details['actors'] ?? 0),
                    'direction' => (string) ($details['direction'] ?? ''),
                    'cached' => (bool) ($details['cached'] ?? false),
                    'by' => $names[$by] ?? '',
                    'at' => (string) ($row['created_at'] ?? ''),
                ];

                if (count($history) >= $limit) {
                    break;
                }
            }

            $this->log($userId, 'AI_TEAM_ACTIVITY_HISTORY', '/api/ai/activity/history', [
                'business_id' => $businessId,
                'locale' => $locale,
                'rows' => count($history),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'capability' => ActivityDigest::CAPABILITY,
                'locale' => $locale,
                'business_id' => $businessId,
                'history' => $history,
                'generated_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_TEAM_ACTIVITY_HISTORY_ERROR', '/api/ai/activity/history', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Activity rows for the business's members inside one window.
     *
     * The window is half-open [from, day-after-to) on purpose: `created_at` is
     * a full timestamp, so an inclusive `<= "to 23:59:59"` bound would drop
     * events in the final second of the day. The digest's own audit actions are
     * excluded so reading the digest never changes the digest.
     *
     * @param  array<int, string>  $memberIds
     * @param  array{from: string, to: string}  $window
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(array $memberIds, array $window, int $maxRows): array
    {
        $from = (string) ($window['from'] ?? '');
        $to = (string) ($window['to'] ?? '');
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($fromTs === false || $toTs === false) {
            return [];
        }

        return UserLog::whereIn('user_id', $memberIds)
            ->where('created_at', '>=', date('Y-m-d', $fromTs))
            ->where('created_at', '<', date('Y-m-d', strtotime('+1 day', $toTs)))
            ->whereNotIn('action', [self::AUDIT_ACTION, 'AI_TEAM_ACTIVITY_HISTORY'])
            ->orderByDesc('created_at')
            ->limit($maxRows)
            ->get()
            ->toArray();
    }

    /**
     * The deterministic narrative is the ground truth. Gemini may reword the
     * SAME figures into one short paragraph; the reply is discarded unless every
     * number in it already exists in the payload we supplied. Reuses the
     * report-summary number gate so both capabilities share one implementation.
     */
    private function narrate(array $digest, string $locale, ?string $ip, bool $enabled): array
    {
        $fallback = [
            'available' => false,
            'degraded' => true,
            'code' => null,
            'reason' => null,
            'meta' => null,
            'text' => implode(' ', $digest['narrative']),
        ];

        if (! $enabled) {
            $fallback['code'] = 'AI_DISABLED';

            return $fallback;
        }

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            $fallback['code'] = 'AI_DISABLED';
            $fallback['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }

        $data = [
            'locale' => $locale,
            'period' => $digest['period'],
            'windows' => $digest['windows'],
            'totals' => $digest['totals'],
            'comparison' => $digest['comparison'],
            'categories' => array_slice($digest['categories'], 0, 5),
            'silent_member_count' => count($digest['silent_members']),
            'day_series' => $digest['day_series'],
            'limitations' => $digest['limitations'],
            'deterministic_narrative' => $digest['narrative'],
        ];

        $instruction = implode("\n", [
            'You restate a verified staff-activity digest as ONE short paragraph (2-4 sentences) for the shop manager.',
            'Return STRICT JSON only: {"summary": string}.',
            'Rules: use ONLY the figures and periods in the data below; never invent people, records, dates or causes;',
            'describe logged events as recorded activity and never as employee performance, intent or wrongdoing;',
            'keep the partial-window and coverage caveats that affect the reading;',
            'write the summary in the language with locale code "'.$locale.'" (sw=Kiswahili, en=English, fr=French, es=Spanish, zh=Chinese, hi=Hindi, ur=Urdu, de=German);',
            'No markdown, no line breaks.',
            'DATA: '.json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);

        try {
            $result = $client->generateJson(
                [['text' => 'Restate the verified activity digest.']],
                $instruction,
                [
                    'capability' => ActivityDigest::CAPABILITY.'_narrate',
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 420,
                ],
            );

            $allowed = ReportSummary::allowedNumbers($data, $digest['narrative']);
            $text = ReportSummary::sanitizeReword($result->data, $allowed);
            if ($text === null) {
                return [
                    'available' => true,
                    'degraded' => true,
                    'code' => 'AI_UNSAFE_OUTPUT',
                    'reason' => AiException::reasonForCode('AI_UNSAFE_OUTPUT', $locale),
                    'meta' => $result->meta(),
                    'text' => implode(' ', $digest['narrative']),
                ];
            }

            return [
                'available' => true,
                'degraded' => false,
                'code' => null,
                'reason' => null,
                'meta' => $result->meta(),
                'text' => $text,
            ];
        } catch (AiException $error) {
            $fallback['code'] = $error->errorCode;
            $fallback['reason'] = AiException::reasonForCode($error->errorCode, $locale);

            return $fallback;
        }
    }
}
