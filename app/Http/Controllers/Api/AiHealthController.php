<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\BusinessHealthScore;
use App\Services\Ai\HealthDigestService;
use App\Services\BusinessResolver;
use App\Services\Supabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #5 - Business Health Score and Weekly Coaching.
 *
 *   POST /api/ai/reports/weekly          generate (or refresh) a digest
 *   GET  /api/ai/reports/weekly          latest stored digest
 *   GET  /api/ai/reports/weekly/history  stored digest summaries
 *   POST /api/ai/health/setup            admin: create the storage table
 *
 * The score is an explainable, deterministic computation over the seller's own
 * business rows. A digest is generated once per business and reporting period
 * (idempotent) and stored so authorized users can review history.
 */
class AiHealthController extends BaseController
{
    private const MAX_HISTORY = 52;

    private const MAX_PERIOD_DAYS = 31;

    public function weekly(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.health', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $scope = $this->resolveScope($request);
        if (isset($scope['error'])) {
            return $scope['error'];
        }

        $locale = $this->resolveLocale($request, $scope['businessId']);
        $period = $this->resolvePeriod($request);

        try {
            $digest = HealthDigestService::generateForBusiness(
                $scope['memberIds'],
                $scope['businessId'],
                $period['start'],
                $period['end'],
                $locale,
                [
                    'currency' => (string) config('ai.currency', 'TZS'),
                    'refresh' => (bool) $request->boolean('refresh'),
                    'with_ai' => ! $request->filled('with_ai') || $request->boolean('with_ai'),
                    'user_id' => $userId,
                    'ip' => $ip,
                    'generated_by' => HealthDigestService::GENERATED_MANUAL,
                ],
            );

            $this->log($userId, 'AI_BUSINESS_HEALTH', '/api/ai/reports/weekly', [
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'band' => $digest['band'] ?? null,
                'score' => $digest['score'] ?? null,
                'sufficient' => $digest['sufficient'] ?? false,
                'reused' => $digest['reused'] ?? false,
                'stored' => $digest['stored'] ?? false,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, ($digest['stored'] ?? true) ? 'success' : 'warning');

            return $this->json($this->present($digest));
        } catch (Throwable $error) {
            $this->log($userId, 'AI_BUSINESS_HEALTH_ERROR', '/api/ai/reports/weekly', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Imeshindwa kutengeneza ripoti ya afya ya biashara. Tafadhali jaribu tena.',
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'HEALTH_ERROR',
            ], 500);
        }
    }

    public function latest(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.health', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $scope = $this->resolveScope($request);
        if (isset($scope['error'])) {
            return $scope['error'];
        }

        $latest = HealthDigestService::latestFull($scope['businessId']);

        if ($latest === null) {
            // No digest stored yet: generate one for the most recent completed
            // week so the seller still sees something useful.
            $locale = $this->resolveLocale($request, $scope['businessId']);
            $period = HealthDigestService::periodForWeek();
            $digest = HealthDigestService::generateForBusiness(
                $scope['memberIds'],
                $scope['businessId'],
                $period['start'],
                $period['end'],
                $locale,
                [
                    'currency' => (string) config('ai.currency', 'TZS'),
                    'user_id' => $userId,
                    'ip' => $ip,
                    'generated_by' => HealthDigestService::GENERATED_MANUAL,
                ],
            );

            $this->log($userId, 'AI_BUSINESS_HEALTH', '/api/ai/reports/weekly', [
                'generated' => true,
                'band' => $digest['band'] ?? null,
            ], $ip, 'success');

            return $this->json($this->present($digest));
        }

        $this->log($userId, 'AI_BUSINESS_HEALTH_VIEW', '/api/ai/reports/weekly', [
            'period_start' => $latest['period_start'] ?? null,
        ], $ip, 'success');

        return $this->json($this->present($latest));
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        if (! config('ai.features.health', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $scope = $this->resolveScope($request);
        if (isset($scope['error'])) {
            return $scope['error'];
        }

        $limit = $this->clampInt($request->input('limit'), 12, 1, self::MAX_HISTORY);
        $digests = HealthDigestService::history($scope['businessId'], $limit);

        return $this->json([
            'success' => true,
            'capability' => HealthDigestService::CAPABILITY,
            'count' => count($digests),
            'digests' => $digests,
        ]);
    }

    /**
     * Admin-only: create the storage table if it is missing. No-op when the
     * table already exists; returns the SQL to run by hand when it does not.
     */
    public function setup(Request $request): JsonResponse
    {
        if ($this->role($request) !== 'admin') {
            return $this->error('Unauthorized', 403);
        }

        $exists = false;
        try {
            Supabase::table('ai_weekly_digests')->select('id')->limit(1)->get();
            $exists = true;
        } catch (Throwable) {
            $exists = false;
        }

        return $this->json([
            'success' => $exists,
            'migration' => 'CREATE TABLE IF NOT EXISTS ai_weekly_digests',
            'status' => $exists ? 'success' : 'failed',
            'error' => $exists ? null : 'Table ai_weekly_digests is missing in Supabase; create it in the Supabase SQL Editor.',
            'manual_sql' => $exists ? null : self::manualSql(),
        ]);
    }

    public static function manualSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS ai_weekly_digests (\n"
            ."    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),\n"
            ."    business_id UUID NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,\n"
            ."    period_start DATE NOT NULL,\n"
            ."    period_end DATE NOT NULL,\n"
            ."    locale TEXT NOT NULL DEFAULT 'sw',\n"
            ."    score NUMERIC,\n"
            ."    band TEXT,\n"
            ."    sufficient BOOLEAN NOT NULL DEFAULT FALSE,\n"
            ."    coverage NUMERIC NOT NULL DEFAULT 0,\n"
            ."    headline TEXT,\n"
            ."    digest JSONB NOT NULL DEFAULT '{}'::jsonb,\n"
            ."    created_at TIMESTAMPTZ DEFAULT NOW(),\n"
            ."    updated_at TIMESTAMPTZ DEFAULT NOW(),\n"
            ."    UNIQUE (business_id, period_start, period_end)\n"
            .");\n"
            .'CREATE INDEX IF NOT EXISTS idx_ai_weekly_digests_business ON ai_weekly_digests (business_id, period_start DESC);';
    }

    /* ------------------------------------------------------------------ */

    /**
     * @return array{businessId?: string, memberIds?: array<int, string>, error?: JsonResponse}
     */
    private function resolveScope(Request $request): array
    {
        $userId = $this->userId($request);
        $businessId = $this->businessId($request) ?: BusinessResolver::forUser($userId)?->id;

        if (! $businessId) {
            return ['error' => $this->json([
                'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                'code' => 'NO_BUSINESS',
            ], 400)];
        }

        $memberIds = BusinessResolver::memberIds($businessId, ['admin', 'seller']);
        if ($userId && ! in_array($userId, $memberIds, true)) {
            $memberIds[] = $userId;
        }
        $memberIds = array_values(array_unique(array_filter($memberIds)));

        return ['businessId' => $businessId, 'memberIds' => $memberIds];
    }

    /**
     * @return array{start: string, end: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $default = HealthDigestService::periodForWeek();

        $start = $this->clampDate($request->input('period_start'), $default['start']);
        $end = $this->clampDate($request->input('period_end'), $default['end']);

        if (strtotime($start) > strtotime($end)) {
            [$start, $end] = [$end, $start];
        }

        $maxDays = self::MAX_PERIOD_DAYS;
        if ((int) round((strtotime($end) - strtotime($start)) / 86400) + 1 > $maxDays) {
            $end = date('Y-m-d', strtotime($start.' +'.($maxDays - 1).' days'));
        }

        return ['start' => $start, 'end' => $end];
    }

    private function clampDate($value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $default;
        }

        return strtotime($value) !== false ? $value : $default;
    }

    private function resolveLocale(Request $request, string $businessId): string
    {
        $supported = (array) config('ai.locales', ['sw']);
        $candidate = strtolower(trim((string) $request->input('locale', '')));

        if ($candidate === '' || ! in_array($candidate, $supported, true)) {
            $candidate = HealthDigestService::defaultLocaleForBusiness($businessId);
        }

        return in_array($candidate, $supported, true) ? $candidate : 'sw';
    }

    /**
     * Shape the response so the frontend never has to defend against missing
     * keys.
     */
    private function present(array $digest): array
    {
        return [
            'success' => true,
            'capability' => BusinessHealthScore::CAPABILITY,
            'locale' => $digest['locale'] ?? 'sw',
            'currency' => $digest['currency'] ?? 'TZS',
            'generated_at' => $digest['generated_at'] ?? now()->toISOString(true),
            'id' => $digest['id'] ?? null,
            'stored' => $digest['stored'] ?? false,
            'reused' => $digest['reused'] ?? false,
            'storage_error' => $digest['storage_error'] ?? null,
            'period' => [
                'start' => $digest['period_start'] ?? null,
                'end' => $digest['period_end'] ?? null,
                'days' => $digest['period_days'] ?? null,
                'previous' => $digest['previous_period'] ?? null,
            ],
            'score' => $digest['score'] ?? null,
            'band' => $digest['band'] ?? 'insufficient_evidence',
            'band_label' => $digest['band_label'] ?? '',
            'band_interpretation' => $digest['band_interpretation'] ?? '',
            'sufficient' => $digest['sufficient'] ?? false,
            'coverage' => $digest['coverage'] ?? 0,
            'headline' => $digest['headline'] ?? '',
            'summary_text' => $digest['summary_text'] ?? '',
            'components' => $digest['components'] ?? [],
            'missing_components' => $digest['missing_components'] ?? [],
            'changes' => $digest['changes'] ?? [],
            'actions' => $digest['actions'] ?? [],
            'limitations' => $digest['limitations'] ?? [],
            'ai' => $digest['ai'] ?? ['available' => false, 'degraded' => true, 'code' => null, 'interpretation' => ''],
        ];
    }

    private function clampInt($value, int $default, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }
}
