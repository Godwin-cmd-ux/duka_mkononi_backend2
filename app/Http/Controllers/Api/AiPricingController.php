<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\AiException;
use App\Services\Ai\BusinessDataProvider;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\PricingAdvisor;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #2 - Pricing and Margin Advisor.
 *
 * POST /api/ai/price/suggest
 *
 * Read-only advisory: it never writes to the database, so no human-confirmation
 * step is required. Real products, costs, stock and recent sales come from the
 * authenticated user's business only; all money math runs in trusted PHP and the
 * model is used solely to prioritise and explain the computed results.
 */
class AiPricingController extends BaseController
{
    private const MAX_AI_PRODUCTS = 15;

    public function suggest(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.price_advisor', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);
        $currency = (string) config('ai.currency', 'TZS');
        $windowDays = $this->clampInt($request->input('window_days'), 30, 7, 180);
        $targetMargin = $this->clampFloat($request->input('target_margin_pct'), 20.0, 1.0, 200.0);
        $thinMargin = $this->clampFloat($request->input('thin_margin_pct'), 10.0, 0.0, 100.0);
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
            $velocity = BusinessDataProvider::salesVelocity($memberIds, $windowDays);

            $analysis = PricingAdvisor::analyzeProducts($products, $velocity, [
                'currency' => $currency,
                'window_days' => $windowDays,
                'target_margin_pct' => $targetMargin,
                'thin_margin_pct' => $thinMargin,
                'max_products' => $limit,
            ]);

            $summary = $analysis['summary'];
            $top = array_slice($analysis['products'], 0, self::MAX_AI_PRODUCTS);

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
            }, $analysis['products']);

            $limitations = array_values(array_unique(array_merge(
                PricingAdvisor::limitations($summary),
                $aiBlock['limitations'],
            )));

            $this->log($userId, 'AI_PRICE_ADVISOR', '/api/ai/price/suggest', [
                'product_count' => $summary['product_count'],
                'at_risk_count' => $summary['at_risk_count'],
                'ai_status' => $aiBlock['degraded'] ? 'degraded' : 'ok',
                'ai_code' => $aiBlock['code'],
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, $aiBlock['degraded'] ? 'warning' : 'success');

            return $this->json([
                'success' => true,
                'capability' => PricingAdvisor::CAPABILITY,
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
            $this->log($userId, 'AI_PRICE_ADVISOR_ERROR', '/api/ai/price/suggest', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Imeshindwa kuchambua bei kwa sasa. Tafadhali jaribu tena.',
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'PRICE_ADVISOR_ERROR',
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
            $fallback['code'] = 'NO_PRODUCTS';
            $fallback['reason'] = AiException::reasonForCode('NO_PRODUCTS', $locale);

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
                    'buying_price' => $product['buying_price'],
                    'selling_price' => $product['selling_price'],
                    'margin' => $product['margin'],
                    'margin_pct' => $product['margin_pct'],
                    'units_sold_window' => $product['units_sold_window'],
                    'suggested_price' => $product['suggested_price'],
                    'floor_price' => $product['floor_price'],
                ];
            }, $topProducts),
        ];

        try {
            $result = $client->generateJson(
                [['text' => PricingAdvisor::userPrompt($payload, count($topProducts))]],
                PricingAdvisor::systemInstruction($locale, $currency, min(12, count($topProducts) + 4)),
                [
                    'capability' => PricingAdvisor::CAPABILITY,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.2,
                    'maxOutputTokens' => 2048,
                ],
            );

            $sanitized = PricingAdvisor::sanitizeAiResponse(
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
