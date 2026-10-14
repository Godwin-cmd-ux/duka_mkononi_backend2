<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Ai\ShoppingAssistant;
use App\Services\JwtToken;
use App\Services\PublicCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Capability #11 - Conversational Shopping Assistant.
 *
 * POST /api/ai/shopping/ask          - one conversational search turn
 * GET  /api/ai/shopping/suggestions  - type-ahead over public product names
 *
 * Both routes are PUBLIC (throttled) because any shopper must be able to
 * ask, signed in or not. When the caller presents a valid customer JWT we
 * remember only the LAST turn (a single bounded parse, 30-minute TTL in
 * cache) so follow-ups like "chini ya 20000" keep the previous topic. No
 * shopper query is ever written to a durable table by this capability.
 *
 * Visibility is enforced upstream: every row here comes from
 * PublicCatalog, which applies the same public-visibility and
 * availability rules as the shop endpoints. This controller adds ranking,
 * clarification and localized honesty notes - never extra data.
 */
class AiShopAssistantController extends BaseController
{
    /** Opaque cache-key tenant for shopper memory (never a business id). */
    private const MEMORY_PREFIX = 'shopping_assistant:memory:';

    /* ------------------------------------------------------------------ */
    /* Ask                                                                 */
    /* ------------------------------------------------------------------ */

    public function ask(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.shopping_assistant', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);
        $currency = (string) config('ai.currency', 'TZS');
        $userId = $this->optionalCustomer($request);
        $ip = $this->ip($request) ?: ($request->header('x-forwarded-for') ?: $request->ip());

        $maxQuery = max(60, (int) config('ai.assistant.max_query', 240));
        $query = trim((string) $request->input('query', ''));
        if ($query === '') {
            return $this->json([
                'error' => ShoppingAssistant::msg('query_required', $locale),
                'code' => 'QUERY_REQUIRED',
                'samples' => ShoppingAssistant::samples($locale),
            ], 422);
        }
        if (mb_strlen($query) > $maxQuery) {
            $query = mb_substr($query, 0, $maxQuery);
        }

        $resultLimit = max(3, (int) config('ai.assistant.result_limit', 12));
        $businessLimit = max(1, (int) config('ai.assistant.business_limit', 5));

        try {
            // 1. Remembered context (signed-in customers only, short TTL).
            $memory = $this->loadMemory($userId);

            // 2. Parse: deterministic first; the model may only PROPOSE the
            //    same allow-listed shape, and only when the plain parser
            //    found nothing usable in the sentence.
            $fresh = ShoppingAssistant::deterministicParse($query);
            $parseSource = 'deterministic';
            $ai = [
                'available' => false,
                'degraded' => true,
                'code' => null,
                'reason' => null,
                'meta' => null,
            ];

            $hasContent = $fresh['terms'] !== []
                || $fresh['phrase'] !== null
                || $fresh['price_min'] !== null
                || $fresh['price_max'] !== null
                || $fresh['in_stock'] !== null;

            if (! $hasContent) {
                $modelled = ShoppingAssistant::parseWithAi($query, $memory['parse'] ?? [], $locale, $ip);
                $ai = $modelled['ai'];
                if ($modelled['parse'] !== null && $modelled['parse'] !== ShoppingAssistant::emptyParse()) {
                    $fresh = $modelled['parse'];
                    $parseSource = 'ai';
                } else {
                    $parseSource = 'none';
                }
            }

            // 3. Layer over the remembered turn so follow-ups keep the topic.
            $merged = $memory['parse'] !== null
                ? ShoppingAssistant::mergeParse($memory['parse'], $fresh)
                : $fresh;
            $mergedWithPrevious = $merged !== $fresh;

            // 4. Explicit refinements (from tapped clarification options).
            $merged = $this->applyRefinements($merged, $request);

            // Pure follow-up with no terms: browse the shop we were already in.
            if ($merged['terms'] === [] && $merged['category'] === null && $merged['business_id'] === null && ! empty($memory['business_id'])) {
                $merged['business_id'] = (string) $memory['business_id'];
            }

            // 5. Search the PUBLIC catalogue, then rank. PublicCatalog is
            //    the single source of visibility/availability truth.
            $rows = PublicCatalog::products();

            $strict = ShoppingAssistant::rank($rows, $merged, $resultLimit);
            $relaxed = [];
            if ($strict === []) {
                $relaxed = ShoppingAssistant::relax($rows, $merged, $resultLimit);
            }

            // 5b. Total miss: give the model ONE chance to re-read the
            //     sentence into the SAME allow-listed parse, then re-rank.
            //     Deterministic-first stays true; a miss with any nearby
            //     result never triggers this (no hidden guessing).
            if ($strict === [] && $relaxed === [] && $parseSource === 'deterministic') {
                $modelled = ShoppingAssistant::parseWithAi($query, $memory['parse'] ?? [], $locale, $ip);
                $ai = $modelled['ai'];
                if ($modelled['parse'] !== null && $modelled['parse'] !== ShoppingAssistant::emptyParse()) {
                    $fresh = $modelled['parse'];
                    $merged = ShoppingAssistant::mergeParse($memory['parse'] ?? ShoppingAssistant::emptyParse(), $fresh);
                    $merged = $this->applyRefinements($merged, $request);
                    if ($merged['terms'] === [] && $merged['category'] === null && $merged['business_id'] === null && ! empty($memory['business_id'])) {
                        $merged['business_id'] = (string) $memory['business_id'];
                    }
                    $parseSource = 'ai';
                    $mergedWithPrevious = $merged !== $fresh;
                    $strict = ShoppingAssistant::rank($rows, $merged, $resultLimit);
                    $relaxed = $strict === [] ? ShoppingAssistant::relax($rows, $merged, $resultLimit) : [];
                }
            }

            $matches = $strict !== [] ? $strict : $relaxed;
            $kind = 'results';
            if ($matches === []) {
                $kind = 'none';
            } elseif ($strict === []) {
                $kind = 'relaxed';
            }

            // 6. Clarify with REAL alternatives only (never invented ones).
            $clarification = null;
            if (ShoppingAssistant::needsClarification($strict, $relaxed)) {
                $kind = 'clarify';
                $options = ShoppingAssistant::refinementOptions($relaxed, 4);
                $labels = array_map(function ($option) {
                    return $option['label'];
                }, $options);
                $clarification = [
                    'question' => ShoppingAssistant::msg('clarify_q', $locale, ['options' => implode(' / ', $labels)]),
                    'options' => $options,
                ];
            }

            // 7. Matching public businesses help when no product matched.
            $businesses = [];
            if ($matches === []) {
                $businessRows = PublicCatalog::shopBusinesses();
                $businesses = ShoppingAssistant::matchBusinesses($businessRows, $merged, $businessLimit);
                if ($businesses !== []) {
                    $kind = $kind === 'none' ? 'businesses' : $kind;
                }
            }

            // 8. Honest, localized answer text built from real numbers.
            $answerText = $this->answerText($kind, $query, count($matches), count($businesses), $locale, $clarification);

            $firstBusinessId = null;
            foreach ($matches as $match) {
                if (! empty($match['business_id'])) {
                    $firstBusinessId = (string) $match['business_id'];
                    break;
                }
            }

            // 9. Remember the turn (customers only; bounded cache; never durable).
            $remembered = $this->remember($userId, $merged, $firstBusinessId, $query);

            $limitations = array_values(array_unique([
                ShoppingAssistant::msg('limit_prices', $locale),
                ShoppingAssistant::msg('limit_stock', $locale),
                ShoppingAssistant::msg('limit_public', $locale),
            ]));
            $notes = [];
            if ($parseSource !== 'ai') {
                $notes[] = ShoppingAssistant::msg('ai_fallback', $locale);
            }

            $products = array_map(function ($match) {
                return $this->productRow($match);
            }, $matches);
            $businessOut = array_map(function ($business) {
                return $this->businessRow($business);
            }, $businesses);

            $payload = [
                'success' => true,
                'capability' => ShoppingAssistant::CAPABILITY,
                'locale' => $locale,
                'currency' => $currency,
                'generated_at' => now()->toISOString(true),
                'query' => $query,
                'interpreted' => [
                    'terms' => $merged['terms'],
                    'phrase' => $merged['phrase'],
                    'category' => $merged['category'],
                    'location' => $merged['location'],
                    'price_min' => $merged['price_min'],
                    'price_max' => $merged['price_max'],
                    'in_stock' => $merged['in_stock'],
                    'sort' => $merged['sort'],
                    'source' => $parseSource,
                    'merged_with_previous' => $mergedWithPrevious,
                    'business_id' => $merged['business_id'],
                ],
                'answer' => [
                    'role' => 'assistant',
                    'kind' => $kind,
                    'text' => $answerText,
                    'at' => now()->toISOString(true),
                ],
                'products' => $products,
                'businesses' => $businessOut,
                'clarification' => $clarification,
                'categories' => ShoppingAssistant::categories($matches, 6),
                'limitations' => $limitations,
                'notes' => $notes,
                'ai' => $ai,
                'session' => [
                    'remembered' => $remembered,
                    'memory_minutes' => (int) config('ai.assistant.memory_minutes', 30),
                ],
            ];

            $payload = $this->guardPayload($payload);

            $this->log($userId, 'AI_SHOP_ASK', '/api/ai/shopping/ask', [
                'query' => $query,
                'kind' => $kind,
                'parse_source' => $parseSource,
                'merged_with_previous' => $mergedWithPrevious,
                'products' => count($products),
                'businesses' => count($businessOut),
                'ai_status' => ($ai['available'] ?? false) ? 'ok' : 'degraded',
                'ai_code' => $ai['code'] ?? null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json($payload);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_SHOP_ASK_ERROR', '/api/ai/shopping/ask', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => ShoppingAssistant::msg('error_generic', $locale),
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'SHOPPING_ASSISTANT_ERROR',
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Type-ahead suggestions                                              */
    /* ------------------------------------------------------------------ */

    public function suggestions(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        if (! config('ai.features.shopping_assistant', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $prefix = ShoppingAssistant::normalise((string) $request->input('q', ''));
        if (mb_strlen($prefix) < 2) {
            return $this->json([
                'success' => true,
                'capability' => ShoppingAssistant::CAPABILITY,
                'prefix' => $prefix,
                'suggestions' => [],
                'samples' => ShoppingAssistant::samples($locale),
            ]);
        }

        try {
            $limit = max(3, min(12, (int) config('ai.assistant.suggest_limit', 8)));
            $rows = PublicCatalog::products(null, $prefix);

            $seen = [];
            $suggestions = [];
            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $key = mb_strtolower($name);
                if ($name === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $suggestions[] = [
                    'name' => $name,
                    'category' => $row['category'] ?? null,
                    'selling_price' => (float) ($row['selling_price'] ?? 0),
                    'available' => (bool) ($row['available'] ?? false),
                    'business_name' => $row['business_name'] ?? null,
                ];
                if (count($suggestions) >= $limit) {
                    break;
                }
            }

            return $this->json([
                'success' => true,
                'capability' => ShoppingAssistant::CAPABILITY,
                'prefix' => $prefix,
                'suggestions' => $suggestions,
            ]);
        } catch (Throwable $error) {
            $this->log(null, 'AI_SHOP_SUGGEST_ERROR', '/api/ai/shopping/suggestions', [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');

            // Type-ahead must stay silent, never broken.
            return $this->json([
                'success' => true,
                'capability' => ShoppingAssistant::CAPABILITY,
                'prefix' => $prefix,
                'suggestions' => [],
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * A valid customer token when present, null otherwise. Anonymous
     * shoppers get the full experience minus memory; an invalid or expired
     * token degrades to anonymous instead of erroring (this route is
     * public by design).
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

    /** @return array{parse:?array,business_id:?string} */
    private function loadMemory(?string $userId): array
    {
        $empty = ['parse' => null, 'business_id' => null];
        if ($userId === null) {
            return $empty;
        }

        try {
            $memory = Cache::get(self::MEMORY_PREFIX.$userId);
            if (! is_array($memory)) {
                return $empty;
            }
            $storedAt = strtotime((string) ($memory['at'] ?? ''));
            $ttlMinutes = max(1, (int) config('ai.assistant.memory_minutes', 30));
            if ($storedAt === false || (time() - $storedAt) > $ttlMinutes * 60) {
                return $empty;
            }

            return [
                'parse' => is_array($memory['parse'] ?? null) ? $memory['parse'] : null,
                'business_id' => is_string($memory['business_id'] ?? null) ? $memory['business_id'] : null,
            ];
        } catch (Throwable $e) {
            return $empty;
        }
    }

    private function remember(?string $userId, array $parse, ?string $businessId, string $query): bool
    {
        if ($userId === null) {
            return false;
        }

        try {
            $ttlMinutes = max(1, (int) config('ai.assistant.memory_minutes', 30));
            Cache::put(self::MEMORY_PREFIX.$userId, [
                'parse' => $parse,
                'business_id' => $businessId,
                'query' => mb_substr($query, 0, 120),
                'at' => now()->toISOString(true),
            ], $ttlMinutes * 60);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function answerText(string $kind, string $query, int $productCount, int $businessCount, string $locale, ?array $clarification): string
    {
        if ($clarification !== null) {
            $summary = $productCount > 0
                ? ShoppingAssistant::msg('summary_relaxed', $locale, ['count' => $productCount, 'query' => $query])
                : '';

            return trim($summary.' '.$clarification['question']);
        }

        return match ($kind) {
            'results' => ShoppingAssistant::msg('summary_results', $locale, ['count' => $productCount, 'query' => $query]),
            'relaxed' => ShoppingAssistant::msg('summary_relaxed', $locale, ['count' => $productCount, 'query' => $query]),
            'businesses' => trim(
                ShoppingAssistant::msg('summary_none', $locale, ['query' => $query]).' '
                .ShoppingAssistant::msg('summary_businesses', $locale, ['count' => $businessCount])
            ),
            default => ShoppingAssistant::msg('summary_none', $locale, ['query' => $query]),
        };
    }

    private function productRow(array $product): array
    {
        return [
            'id' => $product['id'] ?? null,
            'name' => (string) ($product['name'] ?? ''),
            'category' => $product['category'] ?? null,
            'description' => $product['description'] ?? null,
            'selling_price' => (float) ($product['selling_price'] ?? 0),
            'currency' => (string) ($product['currency'] ?? config('ai.currency', 'TZS')),
            'unit' => $product['unit'] ?? null,
            'image' => $product['image_url'] ?? null,
            'available' => (bool) ($product['available'] ?? false),
            'business' => [
                'id' => $product['business_id'] ?? null,
                'name' => $product['business_name'] ?? null,
                'location' => $product['business_location'] ?? null,
                'avatar' => $product['business_logo_url'] ?? null,
                'certified' => (bool) ($product['business_is_certified'] ?? false),
            ],
            'match' => [
                'score' => (int) ($product['match_score'] ?? 0),
                'reason' => $product['match_reason'] ?? null,
                'type' => $product['match'] ?? 'strict',
            ],
        ];
    }

    private function businessRow(array $business): array
    {
        return [
            'id' => $business['id'] ?? null,
            'name' => (string) ($business['business_name'] ?? ''),
            'location' => $business['business_location'] ?? null,
            'type' => $business['business_type'] ?? null,
            'description' => $business['business_description'] ?? null,
            'avatar' => $business['business_logo_url'] ?? null,
            'certified' => (bool) ($business['is_certified'] ?? false),
            'product_count' => (int) ($business['product_count'] ?? 0),
            'match' => [
                'score' => (int) ($business['match_score'] ?? 0),
                'reason' => $business['match_reason'] ?? null,
            ],
        ];
    }

    /** Sanitized optional scalar from the request (never trusted as-is). */
    private function cleanScalar($value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            return null;
        }
        if (! preg_match('/^[A-Za-z0-9\p{L}\p{N} _.,-]+$/u', $value)) {
            return null;
        }

        return $value;
    }

    /** Apply optional client refinements (category / business / sort) safely. */
    private function applyRefinements(array $parse, Request $request): array
    {
        $category = $this->cleanScalar($request->input('category'), 60);
        if ($category !== null) {
            $parse['category'] = ShoppingAssistant::normalise($category);
        }
        $businessId = $this->cleanScalar($request->input('business_id'), 64);
        if ($businessId !== null) {
            $parse['business_id'] = $businessId;
        }
        $sort = (string) $request->input('sort', '');
        if (in_array($sort, ShoppingAssistant::SORTS, true)) {
            $parse['sort'] = $sort;
        }

        return $parse;
    }

    /**
     * Bounded response: on slow connections the full descriptions can
     * overshoot the payload cap, so shed the least critical fields first
     * rather than shipping a payload the shop cannot use.
     */
    private function guardPayload(array $payload): array
    {
        $cap = max(2000, (int) config('ai.assistant.max_payload_chars', 6000));
        if (strlen(json_encode($payload)) <= $cap) {
            return $payload;
        }

        foreach ($payload['products'] as $index => $product) {
            $payload['products'][$index]['description'] = null;
        }
        if (strlen(json_encode($payload)) <= $cap) {
            return $payload;
        }

        $payload['products'] = array_slice($payload['products'], 0, 6);

        return $payload;
    }
}
