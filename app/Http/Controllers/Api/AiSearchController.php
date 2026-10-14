<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\ProductSearch;
use App\Services\PublicCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #24 - Semantic & Typo-Tolerant Search.
 *
 * PUBLIC + throttled, exactly like the other public catalogue surfaces: this
 * searches the SAME rows the shop page already serves (PublicCatalog), so
 * public visibility, business scope and stock rules are still enforced before
 * anything is ranked. The controller adds no data of its own - it returns the
 * catalogue rows plus the transparent relevance signals that ranked them.
 *
 * Why public: the shop search box and the public concierge are the surfaces a
 * visitor uses before signing in, and a search that demanded a token would be
 * useless there. Nothing private is reachable: PublicCatalog never selects a
 * private field.
 */
class AiSearchController extends BaseController
{
    /**
     * GET /api/ai/search/products?q=&business_id=&available=1&limit=
     *
     * Returns `{ results, meta }`, where every result carries `relevance`,
     * `matched_on` and `search_signals`, and `meta` reports how many candidates
     * were read, whether that list was truncated, which query variants were
     * tried and how long it took.
     */
    public function products(Request $request): JsonResponse
    {
        if (! config('ai.features.search', true) || ! config('ai.search.enabled', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $query = trim((string) $request->query('q', ''));
        // One character matches almost everything, so it is refused the same way
        // the concierge refuses a one-character question.
        if (mb_strlen($query) < 2) {
            return $this->json([
                'success' => false,
                'capability' => ProductSearch::CAPABILITY,
                'code' => 'QUERY_REQUIRED',
                'error' => 'Tafadhali andika herufi mbili au zaidi.',
                'examples' => self::examples($this->resolveLocale($request)),
            ], 422);
        }

        $locale = $this->resolveLocale($request);

        $businessId = $request->query('business_id');
        $businessId = is_string($businessId) && $businessId !== '' ? $businessId : null;

        $limit = (int) $request->query('limit', (int) config('ai.search.max_results', 24));
        $limit = max(1, min(60, $limit > 0 ? $limit : (int) config('ai.search.max_results', 24)));

        // Read the public catalogue once; the ranker applies business scope and
        // availability itself and reports what it did in `meta`.
        //
        // A read failure must never be reported as "no products match": the
        // contract still comes back, but `meta.degraded` says plainly that the
        // catalogue could not be read, so the UI cannot mistake an outage for an
        // empty result.
        $rows = [];
        $degraded = false;
        try {
            $rows = PublicCatalog::products();
        } catch (Throwable $e) {
            $degraded = true;
        }

        $result = ProductSearch::rank($rows, $query, [
            'business_id' => $businessId,
            'available_only' => $request->boolean('available'),
            'limit' => $limit,
        ]);

        $meta = $result['meta'];
        $meta['business_id'] = $businessId;
        $meta['available_only'] = $request->boolean('available');
        $meta['degraded'] = $degraded;
        if ($degraded) {
            $meta['degraded_reason'] = self::degradedReason($locale);
        }

        return $this->json([
            'success' => true,
            'capability' => ProductSearch::CAPABILITY,
            'query' => $query,
            'results' => $result['results'],
            'meta' => $meta,
        ]);
    }

    /**
     * One honest sentence for a catalogue read failure, in the caller's
     * language. Kept here (like the other capabilities' messages) rather than
     * in the UI catalogues, because only the server knows the search degraded.
     */
    private const DEGRADED = [
        'sw' => 'Orodha ya bidhaa haikuweza kusomwa kwa sasa. Tafadhali jaribu tena.',
        'en' => 'The product catalogue could not be read right now. Please try again.',
        'fr' => 'Le catalogue des produits n\'a pas pu être lu pour le moment. Veuillez réessayer.',
        'hi' => 'उत्पाद सूची अभी पढ़ी नहीं जा सकी। कृपया पुनः प्रयास करें।',
        'es' => 'No se pudo leer el catálogo de productos ahora mismo. Inténtalo de nuevo.',
        'ur' => 'پروڈکٹ کی فہرست ابھی پڑھی نہیں جا سکی۔ براہ کرم دوبارہ کوشش کریں۔',
        'de' => 'Der Produktkatalog konnte gerade nicht gelesen werden. Bitte erneut versuchen.',
        'zh' => '暂时无法读取商品目录。请重试。',
    ];

    private static function degradedReason(string $locale): string
    {
        return self::DEGRADED[$locale] ?? self::DEGRADED['en'];
    }

    /**
     * Short, localized example queries that demonstrate the matcher: a plain
     * product word, a synonym-crossing pair and the catalogue's own
     * mixed-language example.
     */
    private const EXAMPLES = [
        'sw' => ['mchele', 'viatu', 'breki za premio', 'sukari'],
        'en' => ['rice', 'shoes', 'brake pads for premio', 'sugar'],
        'fr' => ['riz', 'chaussures', 'plaquettes de frein pour premio', 'sucre'],
        'hi' => ['चावल', 'जूते', 'प्रेमियो के लिए ब्रेक पैड', 'चीनी'],
        'es' => ['arroz', 'zapatos', 'pastillas de freno para premio', 'azúcar'],
        'ur' => ['چاول', 'جوتے', 'پریمیو کے لیے بریک پیڈ', 'چینی'],
        'de' => ['Reis', 'Schuhe', 'Bremsbeläge für Premio', 'Zucker'],
        'zh' => ['大米', '鞋子', '普瑞米欧刹车片', '糖'],
    ];

    /** @return array<int, string> */
    private static function examples(string $locale): array
    {
        return self::EXAMPLES[$locale] ?? self::EXAMPLES['en'];
    }
}
