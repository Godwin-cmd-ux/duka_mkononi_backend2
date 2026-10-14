<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\AiException;
use App\Services\Ai\BusinessDataProvider;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\ReportIntentParser;
use App\Services\Ai\ReportSummary;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Capability #15 - AI Report Summaries.
 *
 * Grounded explanations over the EXISTING manager reporting screens ("Mapitio"
 * on the website and the phone app). The endpoint reuses the same trusted
 * report queries (BusinessDataProvider) and the same verified aggregates the
 * report runner already computes (BusinessReporter), so a summary can never
 * disagree with the numbers on screen.
 *
 * Guarantees:
 *  - scope: the caller's own business only (member ids come from the
 *    authenticated business, never from client input); the caller must be a
 *    manager of that business (admin or seller - the two roles the manager
 *    portal serves);
 *  - periods: the current window is truncated to complete days and compared
 *    against an equal-length previous window (ReportSummary::windows);
 *  - comparability: gross profit / margin are only compared when both windows
 *    carry a buying price for every sold line - otherwise the limitation is
 *    disclosed instead of guessed;
 *  - contributors: recorded revenue movement per product, described as
 *    movement, never as cause;
 *  - the deterministic narrative is always present; Gemini may only REWORD
 *    the same figures (every number must already exist in the payload) and
 *    the reply is discarded otherwise.
 */
class AiReportSummaryController extends BaseController
{
    private const CACHE_PREFIX = 'ai.report_summary.';

    public function summary(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $ip = $this->ip($request);
        $userId = $this->userId($request);

        if (! config('ai.features.report_summary', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $role = $this->role($request);
        if (! in_array($role, ['admin', 'seller'], true)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $currency = (string) config('ai.currency', 'TZS');
        $cfg = config('ai.report_summary', []);
        $maxDays = max(7, (int) ($cfg['max_days'] ?? 366));

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

        /* Period: explicit from/to, or a recognised key, or the default. */
        $from = trim((string) $request->input('from', ''));
        $to = trim((string) $request->input('to', ''));
        $periodKey = trim((string) $request->input('period', ''));

        if ($from === '' && $to === '' && $periodKey !== '') {
            if (! in_array($periodKey, ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'last_7_days', 'last_30_days', 'last_90_days', 'this_year'], true)) {
                return $this->json([
                    'error' => ReportSummary::msg('err_period', $locale),
                    'code' => 'INVALID_PERIOD',
                ], 422);
            }
            $range = ReportIntentParser::resolveRange($periodKey, date('Y-m-d'));
            $from = $range['from'];
            $to = $range['to'];
        } elseif ($from === '' && $to === '') {
            $range = ReportIntentParser::resolveRange('last_30_days', date('Y-m-d'));
            $from = $range['from'];
            $to = $range['to'];
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return $this->json([
                'error' => ReportSummary::msg('err_period', $locale),
                'code' => 'INVALID_PERIOD',
            ], 422);
        }

        $windows = ReportSummary::windows($from, $to, date('Y-m-d'));
        if ($windows['days'] < 1) {
            return $this->json([
                'error' => ReportSummary::msg('err_period', $locale),
                'code' => 'INVALID_PERIOD',
            ], 422);
        }
        if ($windows['days'] > $maxDays) {
            return $this->json([
                'error' => ReportSummary::msg('err_range_too_long', $locale, ['max' => $maxDays]),
                'code' => 'RANGE_TOO_LONG',
            ], 422);
        }

        $cacheKey = self::CACHE_PREFIX.md5(json_encode([$businessId, $memberIds, $windows['current'], $locale, $currency]));

        try {
            /* 1. Cache hit? */
            $cached = null;
            try {
                $cached = cache()->get($cacheKey);
            } catch (\Throwable $e) {
                $cached = null;
            }
            if (is_array($cached)) {
                $cached['cached'] = true;
                $this->log($userId, 'AI_REPORT_SUMMARY', '/api/ai/reports/summary', [
                    'business_id' => $businessId,
                    'locale' => $locale,
                    'cached' => true,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ], $ip, 'success');

                return $this->json($cached);
            }

            /* 2. The same rows the existing report screens already use. */
            $productIndex = [];
            foreach (BusinessDataProvider::productsForBusiness($memberIds) as $product) {
                if (! empty($product['id'])) {
                    $productIndex[(string) $product['id']] = $product;
                }
            }

            $cur = $windows['current'];
            $current = [
                'products' => $productIndex,
                'sales' => BusinessDataProvider::salesInRange($memberIds, $cur['from'], $cur['to']),
                'items' => BusinessDataProvider::saleItemsInRange($memberIds, $cur['from'], $cur['to']),
                'expenses' => BusinessDataProvider::expensesInRange($memberIds, $cur['from'], $cur['to']),
            ];

            $prev = $windows['previous'];
            $previous = [
                'sales' => $prev !== null ? BusinessDataProvider::salesInRange($memberIds, $prev['from'], $prev['to']) : [],
                'items' => $prev !== null ? BusinessDataProvider::saleItemsInRange($memberIds, $prev['from'], $prev['to']) : [],
                'expenses' => $prev !== null ? BusinessDataProvider::expensesInRange($memberIds, $prev['from'], $prev['to']) : [],
            ];

            /* 3. Deterministic, verified summary. */
            $summary = ReportSummary::summarize([
                'locale' => $locale,
                'currency' => $currency,
                'windows' => $windows,
                'current' => $current,
                'previous' => $previous,
            ]);

            /* 4. Optional model reword - same figures, numbers gated. */
            $aiBlock = $this->reword($summary, $locale, $ip);

            $payload = [
                'success' => true,
                'capability' => ReportSummary::CAPABILITY,
                'locale' => $locale,
                'currency' => $currency,
                'business_id' => $businessId,
                'generated_at' => now()->toISOString(true),
                'periods' => $summary['periods'],
                'narrative' => $summary['narrative'],
                'summary_text' => $aiBlock['text'],
                'metrics' => $summary['metrics'],
                'previous_metrics' => $summary['previous_metrics'],
                'comparison' => $summary['comparison'],
                'contributors' => $summary['contributors'],
                'table' => $summary['table'],
                'chart' => $summary['chart'],
                'sources' => $summary['sources'],
                'limitations' => $summary['limitations'],
                'ai' => [
                    'available' => $aiBlock['available'],
                    'degraded' => $aiBlock['degraded'],
                    'code' => $aiBlock['code'],
                    'reason' => $aiBlock['reason'],
                    'meta' => $aiBlock['meta'],
                ],
            ];

            $ttl = max(1, (int) ($cfg['cache_minutes'] ?? 15));
            try {
                cache()->put($cacheKey, $payload, $ttl * 60);
            } catch (\Throwable $e) {
                // A cache failure must never break the report.
            }

            $this->log($userId, 'AI_REPORT_SUMMARY', '/api/ai/reports/summary', [
                'business_id' => $businessId,
                'locale' => $locale,
                'days' => $windows['days'],
                'truncated' => $windows['truncated'],
                'revenue' => $summary['metrics']['revenue'],
                'sales_count' => $summary['metrics']['sales_count'],
                'direction' => $summary['comparison']['direction'],
                'ai_status' => $aiBlock['degraded'] ? 'degraded' : 'ok',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json($payload);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_REPORT_SUMMARY_ERROR', '/api/ai/reports/summary', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /**
     * The deterministic narrative is the ground truth. Gemini may reword the
     * same figures into one short paragraph; the reply is discarded unless
     * every number in it already exists in the payload we supplied.
     */
    private function reword(array $summary, string $locale, ?string $ip): array
    {
        $fallback = [
            'available' => false,
            'degraded' => true,
            'code' => null,
            'reason' => null,
            'meta' => null,
            'text' => implode(' ', $summary['narrative']),
        ];

        // Nothing to reword when there is no data at all.
        if ($summary['metrics']['sales_count'] === 0 && $summary['metrics']['expenses_count'] === 0) {
            $fallback['code'] = 'NO_DATA';

            return $fallback;
        }

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            $fallback['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }

        $data = [
            'locale' => $locale,
            'periods' => $summary['periods'],
            'metrics' => $summary['metrics'],
            'previous_metrics' => $summary['previous_metrics'],
            'comparison' => $summary['comparison'],
            'contributors' => $summary['contributors'],
            'top_products' => $summary['table']['items'],
            'limitations' => $summary['limitations'],
            'deterministic_narrative' => $summary['narrative'],
        ];

        $instruction = implode("\n", [
            'You restate a verified business report summary as ONE short paragraph (2-4 sentences) for the shop manager.',
            'Return STRICT JSON only: {"summary": string}.',
            'Rules: use ONLY the figures, periods and limitations in the data below; never invent numbers, products, dates or causes;',
            'describe changes as movements, never as causes; keep every limitation that affects the reading;',
            'write the summary in the language with locale code "'.$locale.'" (sw=Kiswahili, en=English, fr=French, es=Spanish, zh=Chinese, hi=Hindi, ur=Urdu, de=German);',
            'No markdown, no line breaks.',
            'DATA: '.json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);

        try {
            $result = $client->generateJson(
                [['text' => 'Restate the verified report summary.']],
                $instruction,
                [
                    'capability' => ReportSummary::CAPABILITY.'_reword',
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 420,
                ],
            );

            $allowed = ReportSummary::allowedNumbers($data, $summary['narrative']);
            $text = ReportSummary::sanitizeReword($result->data, $allowed);
            if ($text === null) {
                return [
                    'available' => true,
                    'degraded' => true,
                    'code' => 'AI_UNSAFE_OUTPUT',
                    'reason' => AiException::reasonForCode('AI_UNSAFE_OUTPUT', $locale),
                    'meta' => $result->meta(),
                    'text' => implode(' ', $summary['narrative']),
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

    private function resolveLocale(Request $request): string
    {
        $supported = (array) config('ai.locales', ['sw']);
        $candidate = strtolower(trim((string) $request->input('locale', '')));

        if ($candidate === '' || ! in_array($candidate, $supported, true)) {
            $candidate = (string) config('ai.default_locale', 'sw');
        }

        return in_array($candidate, $supported, true) ? $candidate : 'sw';
    }
}
