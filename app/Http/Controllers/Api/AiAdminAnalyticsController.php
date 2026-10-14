<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\UserLog;
use App\Services\Ai\AdminAnalytics;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Capability #20 - Natural-Language Admin Analytics.
 *
 * POST /api/ai/platform-analytics/ask      - answer a platform question with a
 *                                         table, a chart and an explanation.
 * GET  /api/ai/platform-analytics/examples - the supported example questions and
 *                                         the allow-listed metrics (no DB read).
 *
 * Guarantees:
 *  - STRICT system-admin trust boundary: `system_admin`/`admin` only, the same
 *    check the platform's own system-admin endpoints use. Every answer is
 *    platform-wide, so nobody else may call it;
 *  - the question only SELECTS one of the allow-listed tools in
 *    AdminAnalytics::TOOLS. There is no SQL anywhere in this path: the numbers
 *    come from bounded, time-filtered reads over the platform's own tables
 *    (`users`, `user_logs`, `businesses`, `products`, `customers`, `sales`) -
 *    the same sources `/api/admin/stats` and `/api/admin/real-time-stats` use;
 *  - the model may only (a) choose a tool id when the deterministic keywords
 *    found nothing, from that same allow-list, and (b) write ONE acknowledgement
 *    sentence that must pass the sanitiser. It never supplies a number, a range
 *    or a filter, and a rejected model choice becomes the honest "could not
 *    understand" answer;
 *  - read cost is bounded: every row read is capped by
 *    `ai.admin_analytics.max_rows` and the cap is DETECTED and reported
 *    (`truncated`), a range longer than `max_range_days` is clamped, and any
 *    named list is capped at 5 businesses;
 *  - live presence is never mixed with history: `presence_now` answers from the
 *    same `is_online` + `last_seen` window the admin dashboard uses and says it
 *    is a live snapshot, while `activity_history` answers from recorded
 *    `user_logs` and says it is not presence;
 *  - nothing personal is returned: only aggregates (plus capped business names
 *    for certification), enforced by AdminAnalytics::FORBIDDEN_KEYS;
 *  - every call is audited (AI_ADMIN_ANALYTICS, AI_ADMIN_ANALYTICS_EXAMPLES and
 *    their _ERROR variants).
 */
class AiAdminAnalyticsController extends BaseController
{
    private const NARRATION_CACHE_PREFIX = 'ai.admin_analytics.narration.';

    private const ANSWER_CACHE_PREFIX = 'ai.admin_analytics.answer.';

    /* ------------------------------------------------------------------ */
    /* Ask                                                                 */
    /* ------------------------------------------------------------------ */

    public function ask(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.admin_analytics', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }
        if (! $this->isSuperAdmin($request)) {
            $this->log($userId, 'AI_ADMIN_ANALYTICS_UNAUTHORIZED', '/api/ai/platform-analytics/ask', [
                'reason' => 'Non-system-admin access attempt',
                'role' => $this->role($request),
            ], $ip, 'failed');

            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $maxChars = max(20, (int) config('ai.admin_analytics.max_question_chars', 300));
        $question = mb_substr(trim((string) $request->input('question', '')), 0, $maxChars);

        if (mb_strlen($question) < 3) {
            return $this->json([
                'error' => AdminAnalytics::msg('notice_no_match', $locale),
                'code' => 'QUESTION_REQUIRED',
            ], 422);
        }

        $cacheKey = self::ANSWER_CACHE_PREFIX.md5($userId.'|'.$locale.'|'.mb_strtolower($question));
        $cached = $this->cacheGet($cacheKey);
        if (is_array($cached)) {
            $cached['cached'] = true;
            $this->log($userId, 'AI_ADMIN_ANALYTICS', '/api/ai/platform-analytics/ask', [
                'question_chars' => mb_strlen($question),
                'tool' => $cached['parsed']['tool'] ?? null,
                'cached' => true,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json($cached);
        }

        try {
            $parsed = AdminAnalytics::parse($question, [
                'now' => date('Y-m-d'),
                'max_range_days' => (int) config('ai.admin_analytics.max_range_days', AdminAnalytics::MAX_RANGE_DAYS),
            ]);
            $toolSource = $parsed['tool'] !== null ? 'keyword' : 'none';

            /* The model may only CHOOSE from the allow-list, and only when the
             * deterministic parse found nothing. */
            $narration = $this->narrate($userId, $question, $parsed['tool'], $locale, $ip, (bool) config('ai.admin_analytics.ai_narrate', true));
            if ($parsed['tool'] === null && $narration['tool'] !== null) {
                $parsed['tool'] = $narration['tool'];
                $parsed['live'] = in_array($narration['tool'], AdminAnalytics::LIVE_TOOLS, true);
                if (! $parsed['live'] && $parsed['range'] === null) {
                    $parsed['range'] = AdminAnalytics::resolveRange('this_month', date('Y-m-d'));
                    $parsed['range_key'] = 'this_month';
                    $parsed['range_source'] = 'default';
                }
                $toolSource = 'model';
                $parsed['confidence'] = 'medium';
                $narration['ack'] = null;
            }

            $computed = ['truncated' => false, 'rows_read' => 0];
            $result = null;

            if ($parsed['tool'] !== null) {
                $computed = $this->computeTool($parsed, $locale);
                $result = AdminAnalytics::present($parsed['tool'], $parsed, $computed, $locale);
            }

            $payload = [
                'success' => true,
                'capability' => AdminAnalytics::CAPABILITY,
                'locale' => $locale,
                'scope' => 'platform',
                'question' => $question,
                'parsed' => [
                    'tool' => $parsed['tool'],
                    'tool_label' => $parsed['tool'] === null ? null : AdminAnalytics::msg('tool_'.$parsed['tool'], $locale),
                    'tool_source' => $toolSource,
                    'range' => $parsed['range'],
                    'range_key' => $parsed['range_key'],
                    'range_source' => $parsed['range_source'],
                    'role_filter' => $parsed['role_filter'],
                    'confidence' => $parsed['confidence'],
                    'matched' => $parsed['matched'],
                    'live' => $parsed['live'],
                ],
                'result' => $result,
                'notice' => $result === null
                    ? AdminAnalytics::msg('notice_no_match', $locale)
                    : (string) ($result['explanation'] ?? ''),
                'notice_no_sql' => AdminAnalytics::msg('notice_no_sql', $locale),
                'scope_note' => AdminAnalytics::msg('scope_note', $locale),
                'examples' => AdminAnalytics::supportedExamples($locale),
                'ai' => [
                    'available' => $narration['available'],
                    'degraded' => $narration['degraded'],
                    'code' => $narration['code'],
                    'reason' => $narration['reason'],
                ],
                'acknowledgement' => $narration['ack'],
                'truncated' => (bool) ($computed['truncated'] ?? false),
                'rows_read' => (int) ($computed['rows_read'] ?? 0),
                'generated_at' => now()->toISOString(true),
            ];

            /* Defence in depth: this must never contain a personal field. */
            $personal = AdminAnalytics::findPersonalFields($payload);
            if ($personal !== []) {
                $this->log($userId, 'AI_ADMIN_ANALYTICS_LEAK_BLOCKED', '/api/ai/platform-analytics/ask', [
                    'fields' => $personal,
                ], $ip, 'failed');
                $payload = AdminAnalytics::assertNoPersonalFields($payload);
            }

            $this->cachePut($cacheKey, $payload);

            $this->log($userId, 'AI_ADMIN_ANALYTICS', '/api/ai/platform-analytics/ask', [
                'question_chars' => mb_strlen($question),
                'tool' => $parsed['tool'],
                'tool_source' => $toolSource,
                'range_key' => $parsed['range_key'],
                'role_filter' => $parsed['role_filter'],
                'rows_read' => (int) ($computed['rows_read'] ?? 0),
                'truncated' => (bool) ($computed['truncated'] ?? false),
                'matched' => $parsed['matched'],
                'ai_status' => $narration['degraded'] ? 'degraded' : ($narration['available'] ? 'ok' : 'off'),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json($payload);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_ADMIN_ANALYTICS_ERROR', '/api/ai/platform-analytics/ask', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Examples                                                            */
    /* ------------------------------------------------------------------ */

    public function examples(Request $request): JsonResponse
    {
        if (! config('ai.features.admin_analytics', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);

        $this->log($this->userId($request), 'AI_ADMIN_ANALYTICS_EXAMPLES', '/api/ai/platform-analytics/examples', [
            'locale' => $locale,
        ], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'capability' => AdminAnalytics::CAPABILITY,
            'locale' => $locale,
            'scope' => 'platform',
            'title' => AdminAnalytics::msg('title', $locale),
            'subtitle' => AdminAnalytics::msg('subtitle', $locale),
            'scope_note' => AdminAnalytics::msg('scope_note', $locale),
            'notice_no_sql' => AdminAnalytics::msg('notice_no_sql', $locale),
            'tools' => array_map(static fn (string $tool): array => [
                'id' => $tool,
                'label' => AdminAnalytics::msg('tool_'.$tool, $locale),
                'live' => in_array($tool, AdminAnalytics::LIVE_TOOLS, true),
            ], AdminAnalytics::TOOLS),
            'ranges' => array_map(static fn (string $range): array => [
                'id' => $range,
                'label' => AdminAnalytics::msg('range_'.$range, $locale),
            ], AdminAnalytics::RANGES),
            'examples' => AdminAnalytics::supportedExamples($locale),
            'presence_window_minutes' => (int) config('ai.admin_analytics.presence_window_minutes', AdminAnalytics::PRESENCE_WINDOW_MINUTES),
            'max_range_days' => (int) config('ai.admin_analytics.max_range_days', AdminAnalytics::MAX_RANGE_DAYS),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Trusted reads (bounded, time-filtered, platform scope)              */
    /* ------------------------------------------------------------------ */

    /**
     * Read the rows one allow-listed tool needs and compute its numbers.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function computeTool(array $parsed, string $locale): array
    {
        $tool = (string) $parsed['tool'];
        $maxRows = max(100, (int) config('ai.admin_analytics.max_rows', 20000));
        $windowMinutes = max(1, (int) config('ai.admin_analytics.presence_window_minutes', AdminAnalytics::PRESENCE_WINDOW_MINUTES));
        $namedLimit = max(1, min(AdminAnalytics::MAX_NAMED_BUSINESSES, (int) config('ai.admin_analytics.max_named_businesses', AdminAnalytics::MAX_NAMED_BUSINESSES)));
        $range = AdminAnalytics::clampRange(
            is_array($parsed['range'] ?? null) ? $parsed['range'] : AdminAnalytics::resolveRange('this_month', date('Y-m-d')),
            max(7, (int) config('ai.admin_analytics.max_range_days', AdminAnalytics::MAX_RANGE_DAYS))
        );
        $parsed['range'] = $range;

        switch ($tool) {
            case 'users_joined':
                /* The period is applied SERVER-side and the rows are ordered
                 * newest-first, so the cap can only ever cut rows that are
                 * inside the requested window. An unordered capped read could
                 * miss the newest rows entirely and answer "0 joined" while
                 * people did join. */
                $rows = User::select('id', 'role', 'status', 'created_at')
                    ->where('created_at', '>=', $range['from'].'T00:00:00')
                    ->where('created_at', '<=', $range['to'].'T23:59:59.999')
                    ->orderByDesc('created_at')
                    ->limit($maxRows)
                    ->get()
                    ->toArray();
                $out = AdminAnalytics::countUsersJoined($rows, $range);
                $out['rows_read'] = count($rows);
                $out['truncated'] = count($rows) >= $maxRows;

                return $out;

            case 'presence_now':
                $since = now()->subMinutes($windowMinutes)->toISOString(true);
                $rows = User::select('id', 'role')
                    ->where('last_seen', '>=', $since)
                    ->where('is_online', true)
                    ->limit($maxRows)
                    ->get()
                    ->toArray();

                $byRole = [];
                foreach ($rows as $row) {
                    $role = strtolower(trim((string) ($row['role'] ?? '')));
                    $byRole[$role === '' ? 'unknown' : $role] = ($byRole[$role === '' ? 'unknown' : $role] ?? 0) + 1;
                }
                arsort($byRole);

                return [
                    'online' => count($rows),
                    'by_role' => $byRole,
                    'window_minutes' => $windowMinutes,
                    'rows_read' => count($rows),
                    'truncated' => count($rows) >= $maxRows,
                ];

            case 'activity_history':
                /* Same rule as users_joined: the period is a server-side filter
                 * over recorded activity, newest-first, so "today" is really
                 * today (an unordered capped read of the whole log answered
                 * "9 actions today" when the log held 43). */
                $rows = UserLog::select('id', 'action', 'created_at')
                    ->where('created_at', '>=', $range['from'].'T00:00:00')
                    ->where('created_at', '<=', $range['to'].'T23:59:59.999')
                    ->orderByDesc('created_at')
                    ->limit($maxRows)
                    ->get()
                    ->toArray();
                $out = AdminAnalytics::countActivity($rows, $range);
                $out['rows_read'] = count($rows);
                $out['truncated'] = count($rows) >= $maxRows;

                return $out;

            case 'certification_queue':
                /* Both counts are EXACT server-side counts (no cap), so
                 * "N are certified" can never be a lie; only the example names
                 * are a capped slice, and it is the oldest still-pending
                 * businesses, so "waiting longest" is a real row. */
                $totalBusinesses = Business::query()->count();
                $certified = Business::where('is_certified', true)->count();
                $rows = Business::select('id', 'business_name', 'created_at', 'is_certified', 'certified_at')
                    ->where('is_certified', false)
                    ->orderBy('created_at')
                    ->limit($namedLimit)
                    ->get()
                    ->toArray();
                $out = AdminAnalytics::summarizeCertification($rows, date('Y-m-d'), $namedLimit);
                /* is_certified is NOT NULL DEFAULT false (migration
                 * 20261002120500), so total - certified is the exact pending
                 * count and matches what summarizeCertification means. */
                $out['pending'] = max(0, $totalBusinesses - $certified);
                $out['certified'] = $certified;
                $out['rows_read'] = count($rows);
                $out['truncated'] = false;

                return $out;

            case 'platform_totals':
                /* Every number is an exact server-side count (Prefer:
                 * count=exact) - no cap and, except for the roles, no rows read
                 * at all, so nothing here can be an under-count. */
                $users = User::query()->count();
                $products = Product::query()->count();
                $customers = Customer::query()->count();
                $sales = Sale::query()->count();
                $sellers = User::where('role', 'seller')->count();
                $admins = User::whereIn('role', ['admin', 'system_admin'])->count();

                return [
                    'users' => $users,
                    'sellers' => $sellers,
                    'admins' => $admins,
                    'products' => $products,
                    'customers' => $customers,
                    'sales' => $sales,
                    'rows_read' => 0,
                    'truncated' => false,
                ];

            case 'revenue_trend':
                $rows = Sale::select('id', 'sale_date', 'total_amount')
                    ->where('sale_date', '>=', $range['from'])
                    ->where('sale_date', '<=', $range['to'])
                    ->limit($maxRows)
                    ->get()
                    ->toArray();
                $out = AdminAnalytics::summarizeRevenue($rows, $range);
                $out['rows_read'] = count($rows);
                $out['truncated'] = count($rows) >= $maxRows;

                return $out;

            default:
                return ['rows_read' => 0, 'truncated' => false];
        }
    }

    /* ------------------------------------------------------------------ */
    /* Optional, gated model step                                          */
    /* ------------------------------------------------------------------ */

    /**
     * ONE acknowledgement sentence (sanitised) and, only when the deterministic
     * parse found no tool, a choice from the allow-list.
     *
     * @return array{available: bool, degraded: bool, code: ?string, reason: ?string, ack: ?string, tool: ?string}
     */
    private function narrate(string $userId, string $question, ?string $deterministicTool, string $locale, ?string $ip, bool $enabled): array
    {
        $fallback = [
            'available' => false,
            'degraded' => true,
            'code' => null,
            'reason' => null,
            'ack' => null,
            'tool' => null,
        ];

        if (! $enabled) {
            return $fallback;
        }

        $cacheKey = self::NARRATION_CACHE_PREFIX.md5($userId.'|'.$locale.'|'.mb_strtolower($question).'|'.($deterministicTool ?? 'none'));

        try {
            $cached = cache()->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // A cache failure only costs one extra model call.
        }

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            $fallback['code'] = 'AI_DISABLED';
            $fallback['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }

        $instruction = implode("\n", [
            'You are the admin analytics helper of the Duka Mkononi marketplace platform.',
            'Return STRICT JSON only: {"ack": string, "tool": string}.',
            '"tool" must be one of these ids or an empty string: '.implode(', ', AdminAnalytics::TOOLS).'.',
            $deterministicTool === null
                ? 'Choose the single tool id whose metric best answers the question. If none fits, use an empty string.'
                : 'The tool is already decided as "'.$deterministicTool.'": return it unchanged.',
            'The "ack" is ONE short sentence (no digits, no links, no email addresses, no phone numbers, no question marks) that says the recorded platform data below answers the question.',
            'You must NOT state any number, count, date or range yourself, must NOT promise anything, and must NOT mention any person, account, email or business.',
            'Write the sentence in the language with locale code "'.$locale.'" (sw=Kiswahili, en=English, fr=French, es=Spanish, zh=Chinese, hi=Hindi, ur=Urdu, de=German).',
            'DATA: '.json_encode(['locale' => $locale, 'decided_tool' => $deterministicTool], JSON_UNESCAPED_UNICODE),
        ]);

        try {
            $result = $client->generateJson(
                [['text' => 'Return the JSON for this platform analytics question.']],
                $instruction,
                [
                    'capability' => AdminAnalytics::CAPABILITY.'_ack',
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 200,
                ],
            );

            $data = is_array($result->data) ? $result->data : [];
            $ack = isset($data['ack']) && is_string($data['ack']) ? AdminAnalytics::sanitizeAckString($data['ack']) : null;
            $tool = isset($data['tool']) && is_string($data['tool']) && in_array($data['tool'], AdminAnalytics::TOOLS, true)
                ? $data['tool']
                : null;

            /* The model may not silently override a deterministic decision. */
            if ($deterministicTool !== null) {
                $tool = null;
            }

            if ($ack === null && $tool === null) {
                return [
                    'available' => true,
                    'degraded' => true,
                    'code' => 'AI_UNSAFE_OUTPUT',
                    'reason' => AiException::reasonForCode('AI_UNSAFE_OUTPUT', $locale),
                    'ack' => null,
                    'tool' => null,
                ];
            }

            $out = [
                'available' => true,
                'degraded' => false,
                'code' => null,
                'reason' => null,
                'ack' => $ack,
                'tool' => $tool,
            ];

            $this->cachePut($cacheKey, $out, (int) config('ai.admin_analytics.cache_minutes', 10));

            return $out;
        } catch (AiException $error) {
            $fallback['code'] = $error->errorCode;
            $fallback['reason'] = AiException::reasonForCode($error->errorCode, $locale);

            return $fallback;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed>|null */
    private function cacheGet(string $key): ?array
    {
        try {
            $value = cache()->get($key);

            return is_array($value) ? $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param array<string, mixed> $value */
    private function cachePut(string $key, array $value, ?int $minutes = null): void
    {
        try {
            $ttl = max(1, $minutes ?? (int) config('ai.admin_analytics.cache_minutes', 10));
            cache()->put($key, $value, $ttl * 60);
        } catch (\Throwable $e) {
            // Cache is only a cost optimisation.
        }
    }

    /** Mirrors the platform's system-admin check: system_admin or admin. */
    private function isSuperAdmin(Request $request): bool
    {
        return in_array($this->role($request), ['system_admin', 'admin'], true);
    }
}
