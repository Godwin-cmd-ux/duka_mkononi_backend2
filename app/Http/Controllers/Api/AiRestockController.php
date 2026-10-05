<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\AiException;
use App\Services\Ai\BusinessDataProvider;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\RestockPlanner;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #3 - Restock and Stock-Out Prediction.
 *
 * POST /api/ai/restock/list
 *
 * Read-only planning: it never purchases and never modifies stock, so the
 * seller simply reviews a proposed list. Real products, stock and completed
 * sales come from the authenticated user's business only; all numerical work
 * runs in trusted PHP (RestockPlanner) and the model is used solely to explain
 * and prioritise the computed results.
 */
class AiRestockController extends BaseController
{
    private const MAX_AI_PRODUCTS = 15;

    private const ACTIONABLE_STATUSES = ['OUT_OF_STOCK', 'LOW_STOCK', 'RESTOCK', 'OVERSTOCK'];

    public function list(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.restock', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $defaults = (array) config('ai.restock', []);

        $locale = $this->resolveLocale($request);
        $currency = (string) config('ai.currency', 'TZS');
        $windowDays = $this->clampInt($request->input('window_days'), 30, 7, 180);
        $recentDays = $this->clampInt($request->input('recent_days'), (int) ($defaults['recent_days'] ?? 7), 3, 60);
        $leadTime = $this->clampInt($request->input('lead_time_days'), (int) ($defaults['lead_time_days'] ?? 7), 0, 60);
        $safetyDays = $this->clampInt($request->input('safety_days'), (int) ($defaults['safety_days'] ?? 7), 0, 60);
        $reviewDays = $this->clampInt($request->input('review_days'), (int) ($defaults['review_days'] ?? 7), 0, 90);
        $overstockDays = $this->clampInt($request->input('overstock_days'), (int) ($defaults['overstock_days'] ?? 60), 7, 365);
        $minStock = $this->clampFloat($request->input('min_stock'), 0.0, 0.0, 1000000000.0);
        $limit = $this->clampInt($request->input('limit'), 25, 1, 100);

        try {
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

            $products = BusinessDataProvider::productsForBusiness($memberIds);
            $velocity = BusinessDataProvider::salesVelocityDetailed($memberIds, $windowDays, $recentDays);

            $plan = RestockPlanner::planProducts($products, $velocity, [
                'currency' => $currency,
                'window_days' => $windowDays,
                'recent_days' => $recentDays,
                'lead_time_days' => $leadTime,
                'safety_days' => $safetyDays,
                'review_days' => $reviewDays,
                'overstock_days' => $overstockDays,
                'min_stock' => $minStock,
                'fast_moving_daily' => (float) ($defaults['fast_moving_daily'] ?? 1.0),
                'max_products' => $limit,
            ]);

            $summary = $plan['summary'];

            // Only products that actually need attention go to the model (and
            // at most the configured cap), so we never pay to explain a healthy list.
            $actionable = array_values(array_filter($plan['products'], function ($product) {
                return ($product['proposed_quantity'] ?? 0) > 0
                    || in_array($product['status'] ?? '', self::ACTIONABLE_STATUSES, true);
            }));
            $top = array_slice($actionable, 0, self::MAX_AI_PRODUCTS);

            $aiBlock = $this->generateAdvice($top, $summary, $locale, $currency, $businessId, $userId, $ip);

            $insightsById = [];
            foreach ($aiBlock['insights'] as $insight) {
                if (! empty($insight['product_id'])) {
                    $insightsById[$insight['product_id']] = $insight;
                }
            }

            $productsOut = array_map(function ($product) use ($insightsById) {
                $product['ai'] = $insightsById[$product['id']] ?? null;

                return $product;
            }, $plan['products']);

            $limitations = array_values(array_unique(array_merge(
                RestockPlanner::limitations($summary),
                $aiBlock['limitations'],
            )));

            $this->log($userId, 'AI_RESTOCK_PLANNER', '/api/ai/restock/list', [
                'product_count' => $summary['product_count'],
                'restock_count' => $summary['restock_count'],
                'out_of_stock_count' => $summary['out_of_stock_count'],
                'total_proposed_units' => $summary['total_proposed_units'],
                'ai_status' => $aiBlock['degraded'] ? 'degraded' : 'ok',
                'ai_code' => $aiBlock['code'],
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, $aiBlock['degraded'] ? 'warning' : 'success');

            return $this->json([
                'success' => true,
                'capability' => RestockPlanner::CAPABILITY,
                'locale' => $locale,
                'generated_at' => now()->toISOString(true),
                'currency' => $currency,
                'summary' => $summary,
                'products' => $productsOut,
                'insights' => $aiBlock['insights'],
                'watchouts' => $aiBlock['watchouts'],
                'limitations' => $limitations,
                'ai' => [
                    'available' => $aiBlock['available'],
                    'degraded' => $aiBlock['degraded'],
                    'code' => $aiBlock['code'],
                    'reason' => $aiBlock['reason'],
                    'meta' => $aiBlock['meta'],
                    'headline' => $aiBlock['headline'],
                ],
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_RESTOCK_PLANNER_ERROR', '/api/ai/restock/list', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Imeshindwa kuchambua stock kwa sasa. Tafadhali jaribu tena.',
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'RESTOCK_PLANNER_ERROR',
            ], 500);
        }
    }

    /**
     * Ask the shared provider for a narrative, degrading honestly (never
     * faking AI output) if it is disabled, unconfigured, or unavailable.
     */
    private function generateAdvice(array $topProducts, array $summary, string $locale, string $currency, string $businessId, ?string $userId, ?string $ip): array
    {
        $fallback = [
            'available' => false,
            'degraded' => true,
            'reason' => null,
            'code' => null,
            'headline' => '',
            'insights' => [],
            'watchouts' => [],
            'limitations' => [],
            'meta' => null,
        ];

        if ($topProducts === []) {
            $fallback['code'] = 'NO_RESTOCK';
            $fallback['reason'] = AiException::reasonForCode('NO_RESTOCK', $locale);

            return $fallback;
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
            'currency' => $currency,
            'summary' => $summary,
            'products' => array_map(function ($product) {
                return [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'status' => $product['status'],
                    'stock' => $product['stock'],
                    'daily_velocity' => $product['daily_velocity'],
                    'recent_daily_velocity' => $product['recent_daily_velocity'],
                    'demand_trend' => $product['demand_trend'],
                    'days_until_stockout' => $product['days_until_stockout'],
                    'proposed_quantity' => $product['proposed_quantity'],
                    'confidence' => $product['confidence'],
                    'sparse_data' => $product['sparse_data'],
                ];
            }, $topProducts),
        ];

        try {
            $result = $client->generateJson(
                [['text' => RestockPlanner::userPrompt($payload, count($topProducts))]],
                RestockPlanner::systemInstruction($locale, $currency, min(12, count($topProducts) + 4)),
                [
                    'capability' => RestockPlanner::CAPABILITY,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.2,
                    'maxOutputTokens' => 2048,
                ],
            );

            $sanitized = RestockPlanner::sanitizeAiResponse(
                $result->data,
                array_column($topProducts, 'id'),
                ['max_insights' => min(12, count($topProducts) + 4)],
            );

            $rawInsights = is_array($result->data['insights'] ?? null) ? count($result->data['insights']) : 0;
            $meta = $result->meta();
            $meta['validation_failures'] = max(0, $rawInsights - count($sanitized['insights']));
            $sanitized['meta'] = $meta;

            return $sanitized;
        } catch (AiException $error) {
            $fallback['code'] = $error->errorCode;
            $fallback['reason'] = $error->userReason($locale);

            return $fallback;
        } catch (Throwable $error) {
            $fallback['code'] = 'AI_ERROR';
            $fallback['reason'] = AiException::reasonForCode('AI_ERROR', $locale);

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

    private function clampInt($value, int $default, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    private function clampFloat($value, float $default, float $min, float $max): float
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $float = (float) $value;

        return is_finite($float) ? max($min, min($max, $float)) : $default;
    }
}
