<?php

namespace App\Http\Controllers\Api;

use App\Models\Advertisement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reaction;
use App\Models\User;
use App\Services\Ai\RecommendationEngine;
use App\Services\JwtToken;
use App\Services\OrderService;
use App\Services\PhoneNumber;
use App\Services\PublicCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Capability #14 - Personalised Recommendations.
 *
 * GET  /api/ai/recommendations       PUBLIC + throttled; identity optional.
 * POST /api/ai/recommendations/dismiss   auth.jwt (caller's own dismissals).
 * POST /api/ai/recommendations/feedback  auth.jwt (relevance measurement).
 *
 * Privacy contract enforced here, server-side:
 *  - personalisation reads ONLY the caller's own rows: their own order
 *    lines (matched by their account phone, the same key capability #13
 *    uses), their own ad likes, and their own profile location;
 *  - a missing/invalid token degrades to anonymous cold-start (popularity
 *    and certification) instead of erroring - the read route is public by
 *    design, exactly like capability #11;
 *  - popularity inputs are aggregate counts over a bounded recent window,
 *    never individual customer rows, so no customer's behaviour can leak
 *    into another customer's feed;
 *  - dismissals and feedback are stored per account with a TTL (data
 *    retention) and never shared across accounts.
 */
class AiRecommendationController extends BaseController
{
    /** Cache key prefixes (per account - never shared). */
    private const DISMISS_PREFIX = 'ai.recommendations.dismissed.';

    private const METRICS_KEY = 'ai.recommendations.metrics';

    public function index(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $ip = $this->ip($request);

        if (! config('ai.features.recommendations', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);
        $cfg = config('ai.recommendations', []);
        $historyOrders = max(1, (int) ($cfg['history_orders'] ?? 50));
        $popularityRows = max(100, (int) ($cfg['popularity_rows'] ?? 2000));
        $maxProducts = max(1, (int) ($cfg['max_products'] ?? 12));
        $maxBusinesses = max(1, (int) ($cfg['max_businesses'] ?? 6));

        $userId = $this->optionalCustomer($request);

        try {
            /* 1. Public catalogue (visibility already enforced upstream).
             *    Normalise the catalogue row keys before ranking: PublicCatalog
             *    emits stock/business_is_certified/image_url while the engine
             *    gates on stock_quantity/is_certified and the cards show
             *    image. */
            $catalog = array_map(function (array $row): array {
                return RecommendationEngine::normalizeCatalogRow($row);
            }, PublicCatalog::products());
            $businesses = PublicCatalog::shopBusinesses();

            /* 2. Aggregate popularity: bounded recent windows, counts only. */
            $productSales = [];
            $itemRows = OrderItem::select('product_id', 'quantity')
                ->orderByDesc('created_at')
                ->limit($popularityRows)
                ->get()
                ->toArray();
            foreach ($itemRows as $row) {
                $pid = (string) ($row['product_id'] ?? '');
                if ($pid === '') {
                    continue;
                }
                $productSales[$pid] = ($productSales[$pid] ?? 0) + max(1, (int) ($row['quantity'] ?? 1));
            }

            $businessSales = [];
            $orderRows = Order::select('business_id')
                ->orderByDesc('created_at')
                ->limit($popularityRows)
                ->get()
                ->toArray();
            foreach ($orderRows as $row) {
                $bid = (string) ($row['business_id'] ?? '');
                if ($bid === '') {
                    continue;
                }
                $businessSales[$bid] = ($businessSales[$bid] ?? 0) + 1;
            }

            /* 3. Personal signals - the caller's own rows only. */
            $signals = RecommendationEngine::emptySignals();
            if ($userId !== null) {
                $profile = User::where('id', $userId)
                    ->select('id', 'phone', 'business_location', 'status')
                    ->first();
                $phone = $profile ? ($profile->phone ?? null) : null;

                $orderItems = [];
                if (PhoneNumber::isValid($phone)) {
                    $normalized = PhoneNumber::normalize($phone);
                    $ownOrders = Order::where('customer_phone_normalized', $normalized)
                        ->select('id')
                        ->orderByDesc('created_at')
                        ->limit($historyOrders)
                        ->get()
                        ->toArray();
                    $ownIds = array_map(function ($o) {
                        return (string) $o['id'];
                    }, $ownOrders);

                    if ($ownIds !== []) {
                        $lines = OrderItem::whereIn('order_id', $ownIds)
                            ->select('product_id', 'business_id', 'product_name', 'quantity')
                            ->limit(1000)
                            ->get()
                            ->toArray();

                        // Order lines carry no category; enrich from the
                        // public catalogue so category affinity is honest.
                        $catByProduct = [];
                        foreach ($catalog as $p) {
                            $catByProduct[(string) $p['id']] = (string) ($p['category'] ?? '');
                        }
                        foreach ($lines as $line) {
                            $line['category'] = $catByProduct[(string) ($line['product_id'] ?? '')] ?? '';
                            $orderItems[] = $line;
                        }
                    }
                }

                $likes = Reaction::where('user_id', $userId)
                    ->where('reaction_type', 'like')
                    ->select('matangazo_id')
                    ->limit(200)
                    ->get()
                    ->toArray();
                $adIds = array_values(array_unique(array_map(function ($l) {
                    return (string) $l['matangazo_id'];
                }, $likes)));

                $likedAds = [];
                if ($adIds !== []) {
                    $ads = Advertisement::whereIn('id', $adIds)
                        ->select('id', 'user_id', 'category')
                        ->limit(200)
                        ->get()
                        ->toArray();

                    $sellerIds = array_values(array_unique(array_map(function ($a) {
                        return (string) $a['user_id'];
                    }, $ads)));

                    $businessBySeller = [];
                    if ($sellerIds !== []) {
                        $sellers = User::whereIn('id', $sellerIds)
                            ->select('id', 'business_id')
                            ->limit(500)
                            ->get()
                            ->toArray();
                        foreach ($sellers as $s) {
                            $businessBySeller[(string) $s['id']] = (string) ($s['business_id'] ?? '');
                        }
                    }

                    foreach ($ads as $ad) {
                        $likedAds[] = [
                            'category' => (string) ($ad['category'] ?? ''),
                            'business_id' => $businessBySeller[(string) ($ad['user_id'] ?? '')] ?? '',
                        ];
                    }
                }

                $signals = RecommendationEngine::buildSignals(
                    $orderItems,
                    $likedAds,
                    $profile ? ($profile->business_location ?? null) : null
                );
            }

            $dismissed = $userId !== null ? $this->dismissalsFor($userId) : [];

            /* 4. Deterministic ranking with hard gates inside the engine. */
            $products = RecommendationEngine::rankProducts(
                $catalog, $signals, $dismissed, $productSales, $locale, $maxProducts
            );
            $recommendedBusinesses = RecommendationEngine::rankBusinesses(
                $businesses, $signals, $dismissed, $businessSales, $locale, $maxBusinesses
            );

            $personalized = RecommendationEngine::hasPersonalSignals($signals);
            $metrics = $this->metrics();

            $this->log($userId, 'AI_RECO_VIEW', '/api/ai/recommendations', [
                'locale' => $locale,
                'personalized' => $personalized,
                'products' => count($products),
                'businesses' => count($recommendedBusinesses),
                'history_lines' => $signals['history_lines'],
                'likes' => $signals['likes_count'],
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'products' => $products,
                'businesses' => $recommendedBusinesses,
                'meta' => [
                    'personalized' => $personalized,
                    'locale' => $locale,
                    'signals' => [
                        'history_lines' => $signals['history_lines'],
                        'likes' => $signals['likes_count'],
                        'location' => $signals['location'] !== null,
                    ],
                    'privacy' => RecommendationEngine::msg('privacy_note', $locale),
                    'note' => $personalized ? null : RecommendationEngine::msg('new_user_note', $locale),
                    'metrics' => $metrics,
                ],
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_RECO_VIEW_ERROR', '/api/ai/recommendations', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    public function dismiss(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.recommendations', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);

        $type = trim((string) $request->input('type', ''));
        if (! in_array($type, ['product', 'business'], true)) {
            return $this->json([
                'error' => RecommendationEngine::msg('err_type', $locale),
                'code' => 'TYPE_INVALID',
            ], 422);
        }

        $id = trim((string) $request->input('id', ''));
        if ($id === '' || ! OrderService::isUuid($id)) {
            return $this->json([
                'error' => RecommendationEngine::msg('err_id', $locale),
                'code' => 'ID_INVALID',
            ], 422);
        }

        try {
            $dismissed = $this->dismissalsFor($userId);
            $dismissed[$id] = time();

            // Bounded retention: keep only the newest N dismissals.
            $cfg = config('ai.recommendations', []);
            $max = max(10, (int) ($cfg['dismiss_max'] ?? 100));
            if (count($dismissed) > $max) {
                asort($dismissed);
                $dismissed = array_slice($dismissed, -$max, null, true);
            }

            $this->putDismissals($userId, $dismissed);

            $this->log($userId, 'AI_RECO_DISMISSED', '/api/ai/recommendations/dismiss', [
                'type' => $type,
                'subject' => $id,
                'locale' => $locale,
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'dismissed' => true,
                'total' => count($dismissed),
                'message' => RecommendationEngine::msg('dismiss_ok', $locale),
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_RECO_DISMISS_ERROR', '/api/ai/recommendations/dismiss', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    public function feedback(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.recommendations', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);

        $rating = trim((string) $request->input('rating', ''));
        if (! in_array($rating, ['helpful', 'not_helpful'], true)) {
            return $this->json([
                'error' => RecommendationEngine::msg('err_rating', $locale),
                'code' => 'RATING_INVALID',
            ], 422);
        }

        $itemId = trim((string) $request->input('item_id', ''));
        if ($itemId !== '' && ! OrderService::isUuid($itemId)) {
            return $this->json([
                'error' => RecommendationEngine::msg('err_id', $locale),
                'code' => 'ID_INVALID',
            ], 422);
        }

        try {
            $metrics = $this->metrics();
            if ($rating === 'helpful') {
                $metrics['helpful'] = (int) $metrics['helpful'] + 1;
            } else {
                $metrics['not_helpful'] = (int) $metrics['not_helpful'] + 1;
            }
            $metrics['updated_at'] = now()->toISOString(true);
            Cache::forever(self::METRICS_KEY, $metrics);

            $this->log($userId, 'AI_RECO_FEEDBACK', '/api/ai/recommendations/feedback', [
                'rating' => $rating,
                'subject' => $itemId !== '' ? $itemId : null,
                'locale' => $locale,
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => RecommendationEngine::msg('feedback_ok', $locale),
                'metrics' => [
                    'helpful' => (int) $metrics['helpful'],
                    'not_helpful' => (int) $metrics['not_helpful'],
                ],
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_RECO_FEEDBACK_ERROR', '/api/ai/recommendations/feedback', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Optional identity on the public route: a valid bearer token
     * personalises; anything else degrades to anonymous (never errors).
     * Mirrors capability #11's optionalCustomer.
     */
    private function optionalCustomer(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (! preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return null;
        }

        try {
            $decoded = JwtToken::decode(trim($m[1]));
            if (! isset($decoded->userId)) {
                return null;
            }
            $user = User::where('id', $decoded->userId)->select('id', 'role', 'status')->first();
            if (! $user) {
                return null;
            }

            return (string) $user->id;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** This account's dismissals only: id => unix timestamp. */
    private function dismissalsFor(string $userId): array
    {
        try {
            $raw = Cache::get(self::DISMISS_PREFIX.$userId);
        } catch (Throwable $e) {
            return [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $id => $at) {
            if (is_string($id) && is_numeric($at)) {
                $out[$id] = (int) $at;
            }
        }

        return $out;
    }

    private function putDismissals(string $userId, array $dismissed): void
    {
        $days = max(1, (int) (config('ai.recommendations.dismiss_ttl_days', 90)));
        Cache::put(self::DISMISS_PREFIX.$userId, $dismissed, 60 * 24 * $days);
    }

    /** Aggregate relevance counters (counts only - no per-user rows). */
    private function metrics(): array
    {
        try {
            $raw = Cache::get(self::METRICS_KEY);
        } catch (Throwable $e) {
            $raw = null;
        }

        if (! is_array($raw)) {
            $raw = ['helpful' => 0, 'not_helpful' => 0, 'updated_at' => null];
        }

        return [
            'helpful' => (int) ($raw['helpful'] ?? 0),
            'not_helpful' => (int) ($raw['not_helpful'] ?? 0),
            'updated_at' => $raw['updated_at'] ?? null,
        ];
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
