<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\AiException;
use App\Services\Ai\BusinessDataProvider;
use App\Services\Ai\BusinessReporter;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\ReportIntentParser;
use App\Services\Ai\ReportNarrator;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Capability #4 - Natural-Language Business Reporting.
 *
 * POST /api/ai/report/ask
 *
 * A seller asks a question in ordinary language; it is mapped (deterministically,
 * with an optional model fallback classifier) to an allow-listed read-only
 * report, computed in trusted PHP over the authenticated business's own rows.
 * The model never runs queries and never supplies a number.
 */
class AiReportController extends BaseController
{
    private const MAX_QUESTION = 400;

    private const CONTEXT_TTL_MINUTES = 30;

    public function ask(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.reports', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $question = trim((string) $request->input('question', ''));
        if ($question === '') {
            return $this->json(['error' => 'Tafadhali weka swali.', 'code' => 'QUESTION_REQUIRED'], 422);
        }
        if (mb_strlen($question) > self::MAX_QUESTION) {
            $question = mb_substr($question, 0, self::MAX_QUESTION);
        }

        $locale = $this->resolveLocale($request);
        $currency = (string) config('ai.currency', 'TZS');

        try {
            $parsed = ReportIntentParser::parse($question);

            // Follow-up: inherit the previous tool when only a new period is stated.
            $conversationId = (string) $request->input('conversation_id', 'default');
            $contextKey = 'ai:report:ctx:'.$userId.':'.$conversationId;
            $context = Cache::get($contextKey);

            if ($parsed['tool'] === null && is_array($context) && isset($context['tool']) && $parsed['range'] !== null) {
                $parsed['tool'] = $context['tool'];
                $parsed['metric'] = $context['metric'] ?? $parsed['metric'];
                $parsed['confidence'] = 'medium';
            }

            // Model fallback classifier: only fills an unknown tool, and only
            // with values from the server-side allow-list.
            $aiClassified = false;
            if ($parsed['tool'] === null) {
                $classified = $this->classifyWithAi($question, $locale, $userId, $ip);
                if ($classified['tool'] !== null) {
                    $parsed['tool'] = $classified['tool'];
                    $parsed['metric'] = $classified['metric'] ?? $parsed['metric'];
                    if ($parsed['range'] === null && $classified['range_key'] !== null) {
                        $parsed['range'] = ReportIntentParser::resolveRange($classified['range_key'], date('Y-m-d'));
                        $parsed['range_key'] = $classified['range_key'];
                        $parsed['range_source'] = 'ai';
                    }
                    $parsed['confidence'] = 'medium';
                    $aiClassified = true;
                }
            }

            if ($parsed['tool'] !== null) {
                Cache::put($contextKey, ['tool' => $parsed['tool'], 'metric' => $parsed['metric']], now()->addMinutes(self::CONTEXT_TTL_MINUTES));
            }

            $businessId = $this->businessId($request) ?: BusinessResolver::forUser($userId)?->id;
            if (! $businessId) {
                return $this->json(['error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.', 'code' => 'NO_BUSINESS'], 400);
            }

            $memberIds = BusinessResolver::memberIds($businessId, ['admin', 'seller']);
            if ($userId && ! in_array($userId, $memberIds, true)) {
                $memberIds[] = $userId;
            }
            $memberIds = array_values(array_unique(array_filter($memberIds)));

            $result = $this->runReport($parsed, $memberIds, $locale, $currency);

            $summaryText = ReportNarrator::summaryText(
                $parsed['tool'] ?? 'unknown',
                $result['data'],
                $locale,
                $currency,
                $result['range'],
                $result['previous_range'],
            );

            $aiBlock = $this->generateExplanation($result, $locale, $currency, $businessId, $userId, $ip, $aiClassified);

            $this->log($userId, 'AI_BUSINESS_REPORT', '/api/ai/report/ask', [
                'tool' => $parsed['tool'],
                'range_key' => $parsed['range_key'],
                'confidence' => $parsed['confidence'],
                'ai_status' => $aiBlock['degraded'] ? 'degraded' : 'ok',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, ($parsed['tool'] ?? null) === null ? 'warning' : 'success');

            return $this->json([
                'success' => true,
                'capability' => BusinessReporter::CAPABILITY,
                'locale' => $locale,
                'generated_at' => now()->toISOString(true),
                'question' => $question,
                'currency' => $currency,
                'understanding' => [
                    'tool' => $parsed['tool'],
                    'metric' => $parsed['metric'],
                    'range_key' => $parsed['range_key'],
                    'range' => $result['range'],
                    'range_source' => $parsed['range_source'],
                    'compare' => $parsed['compare'],
                    'category_filter' => $parsed['category_filter'],
                    'limit' => $parsed['limit'],
                    'confidence' => $parsed['confidence'],
                    'matched' => $parsed['matched'],
                    'ai_classified' => $aiClassified,
                ],
                'answer' => ['summary_text' => $summaryText],
                'data' => $result['data'],
                'table' => $result['table'],
                'chart' => $result['chart'],
                'comparison' => $result['comparison'],
                'sources' => $result['sources'],
                'limitations' => $result['limitations'],
                'ai' => [
                    'available' => $aiBlock['available'],
                    'degraded' => $aiBlock['degraded'],
                    'code' => $aiBlock['code'],
                    'reason' => $aiBlock['reason'],
                    'explanation' => $aiBlock['explanation'],
                    'meta' => $aiBlock['meta'],
                ],
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_BUSINESS_REPORT_ERROR', '/api/ai/report/ask', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Imeshindwa kujibu swali kwa sasa. Tafadhali jaribu tena.',
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'REPORT_ERROR',
            ], 500);
        }
    }

    /**
     * Execute the allow-listed report. Returns the structured data plus the
     * presentation extras (table/chart) the frontend renders.
     */
    private function runReport(array $parsed, array $memberIds, string $locale, string $currency): array
    {
        $tool = $parsed['tool'];
        $range = $parsed['range'];

        $products = [];
        $productIndex = [];
        foreach (BusinessDataProvider::productsForBusiness($memberIds) as $product) {
            $products[] = $product;
            if (! empty($product['id'])) {
                $productIndex[(string) $product['id']] = $product;
            }
        }

        $base = [
            'data' => [],
            'table' => null,
            'chart' => null,
            'comparison' => null,
            'sources' => [],
            'limitations' => [],
            'range' => $range,
            'previous_range' => null,
        ];

        if ($tool === null) {
            $base['limitations'][] = 'Sikuweza kuelewa swali. Tafadhali uliza kuhusu mauzo, faida, bidhaa zinazoongoza, matumizi, wateja au thamani ya stock.';
            $base['data'] = ['supported_examples' => ReportIntentParser::supportedExamples($locale)];

            return $base;
        }

        if ($tool === 'stock_value') {
            $base['sources'] = ['products'];
            $base['data'] = BusinessReporter::stockValue($productIndex, $currency);
            if (($base['data']['missing_cost_count'] ?? 0) > 0) {
                $base['limitations'][] = 'Bidhaa '.$base['data']['missing_cost_count'].' hazina bei ya kununua, hivyo thamani haijakamilika.';
            }

            return $base;
        }

        if ($range === null) {
            $range = ReportIntentParser::resolveRange('this_month', date('Y-m-d'));
            $base['range'] = $range;
        }
        if (($parsed['range_source'] ?? 'none') === 'default') {
            $base['limitations'][] = 'Hakuna kipindi kilichotajwa, hivyo nimetumia mwezi huu.';
        }

        switch ($tool) {
            case 'revenue_summary':
                $sales = BusinessDataProvider::salesInRange($memberIds, $range['from'], $range['to']);
                $items = BusinessDataProvider::saleItemsInRange($memberIds, $range['from'], $range['to']);
                $data = BusinessReporter::revenueSummary($sales, $items, $productIndex, $currency);
                $base['sources'] = ['sales', 'sale_items', 'products'];
                $base['data'] = $data;
                if ($data['unknown_cogs_items'] > 0) {
                    $base['limitations'][] = 'Faida haijakamilika: bidhaa '.$data['unknown_cogs_items'].' zilizouzwa hazina bei ya kununua.';
                }

                if ($parsed['compare']) {
                    $prevRange = ReportIntentParser::previousRange($range);
                    $prevSales = BusinessDataProvider::salesInRange($memberIds, $prevRange['from'], $prevRange['to']);
                    $prevItems = BusinessDataProvider::saleItemsInRange($memberIds, $prevRange['from'], $prevRange['to']);
                    $prev = BusinessReporter::revenueSummary($prevSales, $prevItems, $productIndex, $currency);
                    $base['previous_range'] = $prevRange;
                    $base['comparison'] = BusinessReporter::compare($data, $prev);
                }
                break;

            case 'top_products':
                $items = BusinessDataProvider::saleItemsInRange($memberIds, $range['from'], $range['to']);
                $data = BusinessReporter::productBreakdown($items, $productIndex, $parsed['metric'], $parsed['limit'], $currency);
                $base['sources'] = ['sales', 'sale_items', 'products'];
                $base['data'] = $data;
                $base['table'] = [
                    'columns' => ['name', 'units', 'revenue', 'profit'],
                    'rows' => array_map(fn ($row) => [$row['name'], $row['units'], $row['revenue'], $row['profit']], $data['items']),
                ];
                $base['chart'] = [
                    'type' => 'bar',
                    'labels' => array_map(fn ($row) => $row['name'], $data['items']),
                    'values' => array_map(fn ($row) => $row[$data['metric']] ?? 0, $data['items']),
                    'metric' => $data['metric'],
                ];
                if ($data['metric'] === 'profit' && in_array(null, array_column($data['items'], 'profit'), true)) {
                    $base['limitations'][] = 'Faida haijakamilika kwa baadhi ya bidhaa (bei ya kununua haipo).';
                }
                break;

            case 'sales_comparison':
                $prevRange = ReportIntentParser::previousRange($range);
                $sales = BusinessDataProvider::salesInRange($memberIds, $range['from'], $range['to']);
                $items = BusinessDataProvider::saleItemsInRange($memberIds, $range['from'], $range['to']);
                $prevSales = BusinessDataProvider::salesInRange($memberIds, $prevRange['from'], $prevRange['to']);
                $prevItems = BusinessDataProvider::saleItemsInRange($memberIds, $prevRange['from'], $prevRange['to']);
                $current = BusinessReporter::revenueSummary($sales, $items, $productIndex, $currency);
                $previous = BusinessReporter::revenueSummary($prevSales, $prevItems, $productIndex, $currency);
                $comparison = BusinessReporter::compare($current, $previous);
                $base['sources'] = ['sales', 'sale_items', 'products'];
                $base['previous_range'] = $prevRange;
                $base['comparison'] = $comparison;
                $base['data'] = [
                    'currency' => $currency,
                    'revenue' => $current['revenue'],
                    'previous_revenue' => $previous['revenue'],
                    'revenue_change_pct' => $comparison['revenue']['change_pct'],
                    'sales_count' => $current['sales_count'],
                    'previous_sales_count' => $previous['sales_count'],
                    'gross_profit' => $current['gross_profit'],
                    'previous_gross_profit' => $previous['gross_profit'],
                ];
                $base['chart'] = [
                    'type' => 'bar',
                    'labels' => ['current', 'previous'],
                    'values' => [$current['revenue'], $previous['revenue']],
                    'metric' => 'revenue',
                ];
                break;

            case 'expenses':
                $expenses = BusinessDataProvider::expensesInRange($memberIds, $range['from'], $range['to']);
                $data = BusinessReporter::expenseSummary($expenses, $parsed['category_filter'], $currency);
                $base['sources'] = ['office_expenses'];
                $base['data'] = $data;
                $base['table'] = [
                    'columns' => ['category', 'amount'],
                    'rows' => array_map(fn ($row) => [$row['category'], $row['amount']], $data['by_category']),
                ];
                $base['chart'] = [
                    'type' => 'bar',
                    'labels' => array_map(fn ($row) => $row['category'], array_slice($data['by_category'], 0, 6)),
                    'values' => array_map(fn ($row) => $row['amount'], array_slice($data['by_category'], 0, 6)),
                    'metric' => 'expenses',
                ];
                if ($data['category_filter'] !== null && $data['category_matched'] === false) {
                    $base['limitations'][] = 'Hakuna matumizi ya aina "'.$data['category_filter'].'" kwenye kipindi hiki.';
                }
                break;

            case 'customers':
                $customers = BusinessDataProvider::customersForBusiness($memberIds);
                $data = BusinessReporter::customerSummary($customers, $range);
                $base['sources'] = ['customers'];
                $base['data'] = $data;
                $base['table'] = [
                    'columns' => ['name', 'total_purchases', 'purchases_count'],
                    'rows' => array_map(fn ($row) => [$row['name'], $row['total_purchases'], $row['purchases_count']], $data['top_customers']),
                ];
                break;

            default:
                $base['limitations'][] = 'Kipengele hiki hakijatekelezwa.';
                break;
        }

        return $base;
    }

    /**
     * Optional model fallback: classify an unrecognised question into the
     * server-side allow-list only. The model can never add a tool or a free
     * date range.
     */
    private function classifyWithAi(string $question, string $locale, ?string $userId, ?string $ip): array
    {
        $fallback = ['tool' => null, 'metric' => null, 'range_key' => null];

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            return $fallback;
        }

        $instruction = implode("\n", [
            'You classify a small-shop owner\'s question into a fixed report tool.',
            'Return STRICT JSON only: {"tool": string|null, "metric": "revenue"|"profit"|"units", "range_key": string|null}.',
            'Allowed tools: '.implode(', ', ReportIntentParser::TOOLS).'.',
            'Allowed range_key values: '.implode(', ', ReportIntentParser::RANGES).'.',
            'If the question does not match, return tool null. Never invent a tool.',
        ]);

        try {
            $result = $client->generateJson(
                [['text' => $question]],
                $instruction,
                [
                    'capability' => 'report_classifier',
                    'user_id' => $userId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 128,
                ],
            );

            $tool = $result->data['tool'] ?? null;
            $tool = is_string($tool) && in_array($tool, ReportIntentParser::TOOLS, true) ? $tool : null;

            $rangeKey = $result->data['range_key'] ?? null;
            $rangeKey = is_string($rangeKey) && in_array($rangeKey, ReportIntentParser::RANGES, true) ? $rangeKey : null;

            $metric = $result->data['metric'] ?? null;
            $metric = is_string($metric) && in_array($metric, ReportIntentParser::METRICS, true) ? $metric : null;

            return ['tool' => $tool, 'metric' => $metric, 'range_key' => $rangeKey];
        } catch (Throwable $error) {
            return $fallback;
        }
    }

    /**
     * Optional model narrative. It receives the already-computed figures and is
     * asked only to interpret them, never to state a different number.
     */
    private function generateExplanation(array $result, string $locale, string $currency, string $businessId, ?string $userId, ?string $ip, bool $skip): array
    {
        $fallback = [
            'available' => false,
            'degraded' => true,
            'reason' => null,
            'code' => null,
            'explanation' => '',
            'meta' => null,
        ];

        if ($skip) {
            // The classifier already spent a call; skip a second one silently.
            $fallback['code'] = 'AI_CLASSIFIER_ONLY';
            $fallback['reason'] = null;

            return $fallback;
        }

        if (($result['data'] ?? []) === [] || isset($result['data']['supported_examples'])) {
            $fallback['code'] = 'NO_TOOL';
            $fallback['reason'] = 'Hakuna data ya kueleza.';

            return $fallback;
        }

        $client = new GeminiClient;
        if (! $client->isEnabled()) {
            $fallback['code'] = 'AI_DISABLED';
            $fallback['reason'] = 'AI imezimwa kwenye server.';

            return $fallback;
        }
        if (! $client->isConfigured()) {
            $fallback['code'] = 'AI_NOT_CONFIGURED';
            $fallback['reason'] = 'AI haijawekwa kwenye server (GEMINI_API_KEY).';

            return $fallback;
        }

        $payload = [
            'locale' => $locale,
            'currency' => $currency,
            'range' => $result['range'],
            'previous_range' => $result['previous_range'],
            'data' => $result['data'],
        ];

        $instruction = implode("\n", [
            'You are a business reporting assistant inside "Duka Mkononi".',
            'You are given FINAL, AUTHORITATIVE figures. Never compute, never invent a number, never reference data not shown.',
            'Write 1-2 short sentences of practical interpretation a shop owner can act on.',
            'If the figures are incomplete, say so; do not guess.',
            'Reply in this language code: '.$locale.'. Keep money in '.$currency.'.',
            'Return STRICT JSON only: {"explanation": string}.',
        ]);

        try {
            $aiResult = $client->generateJson(
                [['text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
                $instruction,
                [
                    'capability' => 'business_report',
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.3,
                    'maxOutputTokens' => 512,
                ],
            );

            $explanation = $aiResult->data['explanation'] ?? '';
            $explanation = is_string($explanation) ? trim(preg_replace('/\s+/u', ' ', $explanation) ?? '') : '';
            $explanation = mb_substr($explanation, 0, 600);

            return [
                'available' => true,
                'degraded' => false,
                'reason' => null,
                'code' => null,
                'explanation' => $explanation,
                'meta' => $aiResult->meta(),
            ];
        } catch (AiException $error) {
            $fallback['code'] = $error->errorCode;
            $fallback['reason'] = $error->getMessage();

            return $fallback;
        } catch (Throwable $error) {
            $fallback['code'] = 'AI_ERROR';
            $fallback['reason'] = 'AI haipatikani kwa sasa.';

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
