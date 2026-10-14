<?php

namespace App\Http\Controllers\Api;

use App\Models\UserLog;
use App\Services\Ai\AiException;
use App\Services\Ai\AnomalyDetector;
use App\Services\Ai\BusinessDataProvider;
use App\Services\Ai\GeminiClient;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Capability #6 - Anomaly & Leak Detection.
 *
 * GET  /api/ai/anomalies          - run the rule engine over the business's own
 *                                    rows, explain the open alerts, return the
 *                                    audit history of past reviews.
 * POST /api/ai/anomalies/dismiss  - record a reviewed-and-dismissed decision.
 *
 * Detection happens in AnomalyDetector (trusted PHP); the model only narrates
 * the resulting alert list and can never change a record or invent a figure.
 * Dismissals are durable audit rows in `user_logs` (mirrored in cache for
 * speed), so the same unchanged evidence is not re-raised over and over.
 */
class AiAnomalyController extends BaseController
{
    private const DISMISS_KEY_PATTERN = '/^[A-Z_]+:[a-z0-9-]{1,40}:[0-9a-f]{12}$/';

    private const DISMISS_CACHE_TTL_DAYS = 365;

    /* ------------------------------------------------------------------ */
    /* Scan                                                                */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.anomalies', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);

        try {
            $business = $this->resolveBusiness($userId, $request);
            if ($business instanceof JsonResponse) {
                return $business;
            }

            $detected = $this->detectFor($business['id'], $userId, $locale);
            $dismissed = $this->dismissalsFor($business['id']);

            $open = [];
            foreach ($detected['alerts'] as $alert) {
                if (! isset($dismissed[$alert['key']])) {
                    $open[] = $alert;
                }
            }

            $history = array_values(array_filter($dismissed, fn ($row) => true));
            usort($history, fn ($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
            $history = array_slice($history, 0, 20);

            $summary = $this->summarize($open, $detected['windows'], $locale, $business['id'], $userId, $ip);

            $this->log($userId, 'AI_ANOMALY_SCAN', '/api/ai/anomalies', [
                'business_id' => $business['id'],
                'detected' => count($detected['alerts']),
                'open' => count($open),
                'dismissed' => count($detected['alerts']) - count($open),
                'ai_status' => $summary['degraded'] ? 'degraded' : 'ok',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, $summary['degraded'] ? 'warning' : 'success');

            return $this->json([
                'success' => true,
                'capability' => AnomalyDetector::CAPABILITY,
                'locale' => $locale,
                'currency' => (string) config('ai.currency', 'TZS'),
                'windows' => $detected['windows'],
                'detected_count' => count($detected['alerts']),
                'open_count' => count($open),
                'dismissed_count' => count($detected['alerts']) - count($open),
                'alerts' => $open,
                'history' => $history,
                'summary' => $summary['text'],
                'ai' => [
                    'available' => $summary['available'],
                    'degraded' => $summary['degraded'],
                    'code' => $summary['code'],
                    'reason' => $summary['reason'],
                    'meta' => $summary['meta'],
                ],
                'limitations' => AnomalyDetector::limitations($locale),
                'generated_at' => now()->toISOString(true),
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_ANOMALY_SCAN_ERROR', '/api/ai/anomalies', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Imeshindwa kupitia dalili za uchunguzi kwa sasa. Tafadhali jaribu tena.',
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'ANOMALY_SCAN_ERROR',
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Dismiss (review workflow)                                           */
    /* ------------------------------------------------------------------ */

    public function dismiss(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.anomalies', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $key = trim((string) $request->input('key', ''));
        if ($key === '') {
            return $this->json(['error' => 'Alama ya dalili inahitajika.', 'code' => 'KEY_REQUIRED'], 422);
        }
        if (strlen($key) > 120 || preg_match(self::DISMISS_KEY_PATTERN, $key) !== 1) {
            return $this->json(['error' => 'Alama ya dalili si sahihi.', 'code' => 'KEY_INVALID'], 422);
        }

        $reason = trim((string) $request->input('reason', ''));
        if (mb_strlen($reason) > 200) {
            $reason = mb_substr($reason, 0, 200);
        }

        $locale = $this->resolveLocale($request);

        try {
            $business = $this->resolveBusiness($userId, $request);
            if ($business instanceof JsonResponse) {
                return $business;
            }

            // Re-run detection so a dismissal can only ever reference an alert
            // that is real right now - no junk keys in the audit trail.
            $detected = $this->detectFor($business['id'], $userId, $locale);

            $current = null;
            foreach ($detected['alerts'] as $alert) {
                if ($alert['key'] === $key) {
                    $current = $alert;
                    break;
                }
            }
            if ($current === null) {
                return $this->json([
                    'error' => 'Dalili hii haipo kwa sasa.',
                    'code' => 'UNKNOWN_ALERT',
                ], 422);
            }

            $this->log($userId, 'AI_ANOMALY_DISMISSED', '/api/ai/anomalies/dismiss', [
                'business_id' => $business['id'],
                'key' => $key,
                'rule' => $current['rule'],
                'severity' => $current['severity'],
                'subject' => $current['subject'],
                'reason' => $reason !== '' ? $reason : null,
                'locale' => $locale,
            ], $ip, 'success');

            // Refresh the cache mirror so this scan (and the next one) is quiet.
            $dismissed = $this->dismissalsFor($business['id'], true);
            $dismissed[$key] = [
                'key' => $key,
                'rule' => $current['rule'],
                'subject' => $current['subject'],
                'reason' => $reason !== '' ? $reason : null,
                'at' => now()->toISOString(true),
                'by' => $userId,
            ];
            $this->putDismissals($business['id'], $dismissed);

            return $this->json([
                'success' => true,
                'key' => $key,
                'dismissed_at' => $dismissed[$key]['at'],
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_ANOMALY_DISMISS_ERROR', '/api/ai/anomalies/dismiss', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Imeshindwa kuhifadhi uamuzi. Tafadhali jaribu tena.',
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'ANOMALY_DISMISS_ERROR',
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Resolve the authorized business server-side (never from raw client
     * input beyond the existing jwt-scoped helper the other AI routes use).
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function resolveBusiness(?string $userId, Request $request): array|JsonResponse
    {
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

        return [
            'id' => (string) $businessId,
            'member_ids' => array_values(array_unique(array_filter($memberIds))),
        ];
    }

    /**
     * Pull the business's own rows and run the deterministic rule engine.
     *
     * @return array{alerts: array<int, array<string, mixed>>, windows: array<string, mixed>}
     */
    private function detectFor(string $businessId, ?string $userId, string $locale): array
    {
        $days = max(1, (int) config('ai.anomalies.compare_days', 14));

        $recentTo = date('Y-m-d');
        $recentFrom = date('Y-m-d', strtotime('-'.($days - 1).' days'));
        $priorTo = date('Y-m-d', strtotime('-'.$days.' days'));
        $priorFrom = date('Y-m-d', strtotime('-'.((2 * $days) - 1).' days'));

        $memberIds = BusinessResolver::memberIds($businessId, ['admin', 'seller']);
        if ($userId && ! in_array($userId, $memberIds, true)) {
            $memberIds[] = $userId;
        }
        $memberIds = array_values(array_unique(array_filter($memberIds)));

        $products = BusinessDataProvider::productsForBusiness($memberIds);
        $saleItems = BusinessDataProvider::saleItemsInRange($memberIds, $priorFrom, $recentTo);
        $expenses = BusinessDataProvider::expensesInRange($memberIds, $priorFrom, $recentTo);

        $alerts = AnomalyDetector::detect($products, $saleItems, $expenses, [
            'recent' => ['from' => $recentFrom, 'to' => $recentTo],
            'prior' => ['from' => $priorFrom, 'to' => $priorTo],
        ], [
            'locale' => $locale,
            'currency' => (string) config('ai.currency', 'TZS'),
            'max_alerts' => (int) config('ai.anomalies.max_alerts', 20),
            'min_margin_lines' => (int) config('ai.anomalies.min_margin_lines', 3),
            'margin_drop_pp' => (float) config('ai.anomalies.margin_drop_pp', 10.0),
            'expense_spike_ratio' => (float) config('ai.anomalies.expense_spike_ratio', 2.0),
            'expense_spike_min_abs' => (float) config('ai.anomalies.expense_spike_min_abs', 10000),
        ]);

        return [
            'alerts' => $alerts,
            'windows' => [
                'recent' => ['from' => $recentFrom, 'to' => $recentTo],
                'prior' => ['from' => $priorFrom, 'to' => $priorTo],
                'compare_days' => $days,
            ],
        ];
    }

    /**
     * Dismissed keys for a business: cache mirror first, then a bounded
     * hydration from the durable `user_logs` audit rows.
     *
     * @return array<string, array<string, mixed>>
     */
    private function dismissalsFor(string $businessId, bool $forceFresh = false): array
    {
        $cacheKey = 'ai:anomalies:dismissed:'.$businessId;

        if (! $forceFresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $map = [];
        try {
            $rows = UserLog::where('action', 'AI_ANOMALY_DISMISSED')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get()
                ->toArray();
        } catch (Throwable $error) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $details = $row['details'] ?? null;
            if (is_string($details)) {
                $details = json_decode($details, true);
            }
            if (! is_array($details) || ($details['business_id'] ?? null) !== $businessId) {
                continue;
            }
            $key = (string) ($details['key'] ?? '');
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'key' => $key,
                'rule' => (string) ($details['rule'] ?? ''),
                'subject' => (string) ($details['subject'] ?? ''),
                'reason' => $details['reason'] ?? null,
                'at' => (string) ($row['created_at'] ?? ''),
                'by' => $row['user_id'] ?? null,
            ];
        }

        $this->putDismissals($businessId, $map);

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $map
     */
    private function putDismissals(string $businessId, array $map): void
    {
        try {
            Cache::put('ai:anomalies:dismissed:'.$businessId, $map, now()->addDays(self::DISMISS_CACHE_TTL_DAYS));
        } catch (Throwable $error) {
            // Cache is only a mirror; the audit rows remain authoritative.
        }
    }

    /**
     * One grounded narrative over the open alerts, cached per alert set so a
     * refresh does not pay for the same explanation twice. Degrades honestly
     * to a deterministic localized summary when the provider is unavailable.
     *
     * @param  array<int, array<string, mixed>>  $alerts
     * @return array{text: string, available: bool, degraded: bool, code: ?string, reason: ?string, meta: ?array}
     */
    private function summarize(array $alerts, array $windows, string $locale, string $businessId, ?string $userId, ?string $ip): array
    {
        $from = (string) ($windows['recent']['from'] ?? '');
        $to = (string) ($windows['recent']['to'] ?? '');
        $fallbackText = AnomalyDetector::summaryText(count($alerts), $locale, $from, $to);

        $fallback = [
            'text' => $fallbackText,
            'available' => false,
            'degraded' => true,
            'code' => null,
            'reason' => null,
            'meta' => null,
        ];

        if ($alerts === []) {
            $fallback['code'] = 'NO_ALERTS';
            $fallback['text'] = AnomalyDetector::summaryText(0, $locale, $from, $to);

            return $fallback;
        }

        // Deterministic cache: same alert keys => same explanation.
        $fingerprint = substr(sha1(implode('|', array_column($alerts, 'key'))), 0, 16);
        $cacheKey = 'ai:anomalies:explain:'.$businessId.':'.$fingerprint;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $client = new GeminiClient;
        if (! $client->isEnabled()) {
            $fallback['code'] = 'AI_DISABLED';
            $fallback['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }
        if (! $client->isConfigured()) {
            $fallback['code'] = 'AI_NOT_CONFIGURED';
            $fallback['reason'] = AiException::reasonForCode('AI_NOT_CONFIGURED', $locale);

            return $fallback;
        }

        $payload = [
            'locale' => $locale,
            'currency' => (string) config('ai.currency', 'TZS'),
            'window' => ['from' => $from, 'to' => $to],
            'alerts' => array_map(fn ($alert) => [
                'rule' => $alert['rule'],
                'severity' => $alert['severity'],
                'subject' => $alert['subject'],
                'detail' => $alert['detail'],
            ], array_slice($alerts, 0, 20)),
        ];

        $instruction = implode("\n", [
            'You are an operations reviewer inside "Duka Mkononi".',
            'You receive DETECTED signals computed by trusted code. Never invent figures, records or causes.',
            'Explain in 2-3 short sentences what the signals suggest to verify first, in neutral, non-accusatory language.',
            'Never state or imply that a person stole or committed fraud; these are review signals only.',
            'Reply in this language code: '.$locale.'.',
            'Return STRICT JSON only: {"explanation": string}.',
        ]);

        try {
            $result = $client->generateJson(
                [['text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
                $instruction,
                [
                    'capability' => AnomalyDetector::CAPABILITY,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.2,
                    'maxOutputTokens' => 512,
                ],
            );

            $explanation = $result->data['explanation'] ?? '';
            $explanation = is_string($explanation) ? trim(preg_replace('/\s+/u', ' ', $explanation) ?? '') : '';
            $explanation = mb_substr($explanation, 0, 700);
            if ($explanation === '') {
                throw new AiException('The AI returned an empty explanation.', 'AI_EMPTY_RESPONSE');
            }

            $block = [
                'text' => $explanation,
                'available' => true,
                'degraded' => false,
                'code' => null,
                'reason' => null,
                'meta' => $result->meta(),
            ];
        } catch (AiException $error) {
            $fallback['code'] = $error->errorCode;
            $fallback['reason'] = $error->userReason($locale);

            $block = $fallback;
        } catch (Throwable $error) {
            $fallback['code'] = 'AI_ERROR';
            $fallback['reason'] = AiException::reasonForCode('AI_ERROR', $locale);

            $block = $fallback;
        }

        try {
            $ttl = max(1, (int) config('ai.anomalies.explain_ttl_minutes', 10));
            Cache::put($cacheKey, $block, now()->addMinutes($ttl));
        } catch (Throwable $error) {
            // A cache failure must not fail the scan.
        }

        return $block;
    }
}
