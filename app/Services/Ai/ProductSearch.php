<?php

namespace App\Services\Ai;

use App\Services\Supabase;
use Throwable;

/**
 * Capability #24 - Semantic & Typo-Tolerant Search.
 *
 * WHAT THIS REPLACES
 * ------------------
 * The catalogue search used to be a single case-insensitive `str_contains`:
 * "brake pads for premio" found nothing unless a product literally contained
 * that string, a single typo killed the result, and nothing was ranked. This
 * service keeps the SAME rows (still read from PublicCatalog, so public
 * visibility and business scope are unchanged) and replaces only the matching
 * and ordering:
 *
 *   exact phrase -> exact token -> prefix -> transliteration fold -> bounded
 *   fuzzy -> configured synonyms (both directions, mixed languages)
 *
 * TRANSPARENCY
 * ------------
 * Every result carries `relevance`, the `signals` that produced it and the
 * `matched_on` fields, so the ranking can be explained rather than trusted.
 * The score is text-based only; availability and price are ORDERING tiebreaks,
 * not hidden relevance, and they are reported in `meta`.
 *
 * BOUNDS
 * ------
 * At most `ai.search.max_candidates` rows are scored, `max_results` are
 * returned, and anything weaker than `min_score` is dropped. `meta` reports
 * candidates, whether the candidate list was truncated and how long it took, so
 * retrieval stays observable.
 *
 * EMBEDDINGS
 * ----------
 * Optional and off by default (see Embeddings). When enabled AND the provider
 * answers AND stored vectors exist for the current version, the semantic score
 * is blended in and `mode` becomes `hybrid`. If any of that is unavailable the
 * search stays `lexical` and MUST still return useful results - a unit test
 * pins exactly that.
 */
class ProductSearch
{
    public const CAPABILITY = 'search';

    /**
     * Per-field contribution to the relevance score. They sum to 1.0, so a
     * perfect match on every field is exactly 1.0 and the score is comparable
     * across queries.
     */
    private const FIELD_WEIGHTS = [
        'name' => 0.55,
        'category' => 0.15,
        'description' => 0.12,
        'business_name' => 0.12,
        'unit' => 0.06,
    ];

    /** Only the first N tokens of a field are scored (a long blurb is noise). */
    private const MAX_FIELD_TOKENS = 12;

    /**
     * Rank product rows (the exact array PublicCatalog::products() returns)
     * against a query.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $options  limit, business_id, available_only,
     *                                         min_score, type
     * @return array{results: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function rank(array $rows, string $query, array $options = []): array
    {
        $started = microtime(true);

        $maxCandidates = max(1, (int) config('ai.search.max_candidates', 2000));
        $maxResults = max(1, (int) ($options['limit'] ?? config('ai.search.max_results', 24)));
        $minScore = isset($options['min_score'])
            ? (float) $options['min_score']
            : (float) config('ai.search.min_score', 0.28);

        $businessId = isset($options['business_id']) && $options['business_id'] !== ''
            ? (string) $options['business_id']
            : null;
        $availableOnly = ! empty($options['available_only']);

        // 1. Filters first: an excluded row must never cost a score.
        $candidates = [];
        foreach ($rows as $row) {
            if ($businessId !== null && (string) ($row['business_id'] ?? '') !== $businessId) {
                continue;
            }
            if ($availableOnly && empty($row['available'])) {
                continue;
            }
            $candidates[] = $row;
        }

        $totalCandidates = count($candidates);
        $truncated = $totalCandidates > $maxCandidates;
        if ($truncated) {
            $candidates = array_slice($candidates, 0, $maxCandidates);
        }

        $variants = SearchText::variants($query, (array) config('ai.search.synonyms', []));
        // Tokenise every variant ONCE per query. Doing it per row (1872 rows x 5
        // fields x N variants) dominated the whole search and is pure waste.
        $prepared = self::prepareVariants($variants);
        $fuzzy = SearchText::fuzzyOptions();

        $meta = [
            'query' => trim($query),
            'variants' => $variants,
            'mode' => 'lexical',
            'candidates' => $totalCandidates,
            'scored' => count($candidates),
            'matched' => 0,
            'returned' => 0,
            'truncated' => $truncated,
            'min_score' => $minScore,
            'fuzzy_enabled' => (bool) ($fuzzy['fuzzy_enabled'] ?? true),
            'fuzzy_used' => false,
            'max_candidates' => $maxCandidates,
            'max_results' => $maxResults,
            'embeddings' => [
                'enabled' => Embeddings::isEnabled(),
                'version' => Embeddings::isEnabled() ? Embeddings::version() : null,
                'used' => false,
            ],
        ];

        if ($variants === []) {
            $meta['took_ms'] = round((microtime(true) - $started) * 1000, 2);

            return ['results' => [], 'meta' => $meta];
        }

        // 2. Text score for every candidate. The CHEAP pass runs first, with the
        //    fuzzy comparison switched off, so an ordinary query never pays for
        //    edit-distance work. Only when that pass finds almost nothing -
        //    which is precisely the typo case - do we pay for a full fuzzy pass,
        //    and `meta.fuzzy_used` says which happened.
        $scored = self::scoreCandidates($candidates, $prepared, array_merge($fuzzy, ['fuzzy_enabled' => false]));
        $topUpBelow = max(0, (int) config('ai.search.fuzzy_topup_below', 5));
        if (! empty($fuzzy['fuzzy_enabled']) && count($scored) < $topUpBelow) {
            $scored = self::scoreCandidates($candidates, $prepared, $fuzzy);
            $meta['fuzzy_used'] = true;
        }

        // 3. Optional semantic blend (never required for a result to exist).
        $semantic = self::semanticScores($query, $scored);
        if ($semantic !== []) {
            $meta['mode'] = 'hybrid';
            $meta['embeddings']['used'] = true;
            $weight = min(0.9, max(0.0, (float) (Embeddings::config()['weight'] ?? 0.35)));
            $scored = array_map(static function (array $entry) use ($semantic, $weight): array {
                $id = (string) ($entry['row']['id'] ?? '');
                $semanticScore = $id !== '' && isset($semantic[$id]) ? max(0.0, min(1.0, $semantic[$id])) : 0.0;
                $entry['score']['semantic'] = round($semanticScore, 4);
                if ($semanticScore > 0.0) {
                    $entry['score']['relevance'] = round(
                        min(1.0, ((1 - $weight) * $entry['score']['relevance']) + ($weight * $semanticScore)),
                        4
                    );
                }

                return $entry;
            }, $scored);
        }

        // 4. Drop the noise, then order: relevance, then things a buyer cares
        //    about (in stock, cheaper), then a stable name tiebreak.
        $hits = array_values(array_filter(
            $scored,
            static fn (array $entry): bool => $entry['score']['relevance'] >= $minScore || ! empty($entry['score']['semantic'])
        ));

        usort($hits, static function (array $a, array $b): int {
            if ($a['score']['relevance'] !== $b['score']['relevance']) {
                return $a['score']['relevance'] < $b['score']['relevance'] ? 1 : -1;
            }
            $aAvail = ! empty($a['row']['available']) ? 1 : 0;
            $bAvail = ! empty($b['row']['available']) ? 1 : 0;
            if ($aAvail !== $bAvail) {
                return $bAvail <=> $aAvail;
            }
            $price = ((float) ($a['row']['selling_price'] ?? 0)) <=> ((float) ($b['row']['selling_price'] ?? 0));
            if ($price !== 0) {
                return $price;
            }

            return strcmp((string) ($a['row']['name'] ?? ''), (string) ($b['row']['name'] ?? ''));
        });

        $meta['matched'] = count($hits);

        $results = [];
        foreach (array_slice($hits, 0, $maxResults) as $entry) {
            $row = $entry['row'];
            $row['relevance'] = $entry['score']['relevance'];
            $row['matched_on'] = $entry['score']['matched_on'];
            $row['search_signals'] = $entry['score']['signals'];
            if (isset($entry['score']['semantic'])) {
                $row['search_signals']['semantic'] = $entry['score']['semantic'];
            }
            $results[] = $row;
        }

        $meta['returned'] = count($results);
        $meta['took_ms'] = round((microtime(true) - $started) * 1000, 2);

        return ['results' => $results, 'meta' => $meta];
    }

    /** Convenience wrapper used by the controllers. */
    public static function searchProducts(array $rows, string $query, array $options = []): array
    {
        return self::rank($rows, $query, $options);
    }

    /**
     * Turn query variants into the shape the scorer wants (tokens + phrase).
     *
     * @param  array<int, string>  $variants
     * @return array<int, array{phrase: string, tokens: array<int, string>}>
     */
    private static function prepareVariants(array $variants): array
    {
        $prepared = [];
        foreach ($variants as $variant) {
            $tokens = SearchText::tokens($variant);
            if ($tokens === []) {
                continue;
            }
            $prepared[] = ['phrase' => implode(' ', $tokens), 'tokens' => $tokens];
        }

        return $prepared;
    }

    /**
     * Score every candidate with the given fuzzy options and keep the matches.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<int, array{phrase: string, tokens: array<int, string>}>  $prepared
     * @param  array<string, mixed>  $options
     * @return array<int, array{row: array<string, mixed>, score: array<string, mixed>}>
     */
    private static function scoreCandidates(array $candidates, array $prepared, array $options): array
    {
        $scored = [];
        foreach ($candidates as $row) {
            $score = self::scoreRow($row, $prepared, $options);
            if ($score['relevance'] > 0.0) {
                $scored[] = ['row' => $row, 'score' => $score];
            }
        }

        return $scored;
    }

    /**
     * Score one row against every query variant.
     *
     * The per-field score is COVERAGE, not "best single token": the fraction of
     * the query's words that field actually accounts for. Scoring only the best
     * token made "brake pads for premio" rank every item containing the word
     * "brake" equally, so a real "Brake Pads Toyota Premio" could not win.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, array{phrase: string, tokens: array<int, string>}>  $prepared
     * @param  array<string, mixed>  $options
     * @return array{relevance: float, matched_on: array<int, string>, signals: array<string, float>}
     */
    private static function scoreRow(array $row, array $prepared, array $options): array
    {
        $signals = [];
        $matchedOn = [];

        // Every token any variant asks for, scored once per field instead of
        // once per (variant, field) pair.
        $queryTokens = [];
        foreach ($prepared as $variant) {
            foreach ($variant['tokens'] as $token) {
                $queryTokens[$token] = true;
            }
        }
        $queryTokens = array_keys($queryTokens);

        foreach (array_keys(self::FIELD_WEIGHTS) as $field) {
            $text = SearchText::normalize((string) ($row[$field] ?? ''));
            if ($text === '') {
                continue;
            }

            $fieldTokens = array_slice(SearchText::tokens($text), 0, self::MAX_FIELD_TOKENS);
            if ($fieldTokens === []) {
                continue;
            }

            $tokenScores = [];
            $best = 0.0;
            foreach ($prepared as $variant) {
                // Whole-phrase hit inside the field is the strongest signal.
                if (SearchText::containsPhrase($text, $variant['phrase'])) {
                    $best = 1.0;
                    break;
                }

                $sum = 0.0;
                foreach ($variant['tokens'] as $token) {
                    if (! array_key_exists($token, $tokenScores)) {
                        $tokenScores[$token] = SearchText::bestTokenScore($token, $fieldTokens, $options);
                    }
                    $sum += $tokenScores[$token];
                }
                $best = max($best, $sum / count($variant['tokens']));
            }

            if ($best <= 0.0) {
                continue;
            }

            $contribution = round($best * self::FIELD_WEIGHTS[$field], 4);
            $signals[$field] = $contribution;
            $matchedOn[] = $field;
        }

        return [
            'relevance' => round(min(1.0, array_sum($signals)), 4),
            'matched_on' => $matchedOn,
            'signals' => $signals,
        ];
    }

    /**
     * Best-effort semantic scores. Returns [] whenever embeddings are off,
     * unconfigured, unreachable, or no stored vector matches the current
     * version - in every one of those cases the caller keeps its lexical
     * results.
     *
     * @param  array<int, array{row: array<string, mixed>, score: array<string, mixed>}>  $scored
     * @return array<string, float> product id => cosine similarity
     */
    private static function semanticScores(string $query, array $scored): array
    {
        if (! Embeddings::isConfigured() || $scored === []) {
            return [];
        }

        $ids = [];
        foreach ($scored as $entry) {
            $id = (string) ($entry['row']['id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $queryVector = Embeddings::embed($query);
        if ($queryVector === null) {
            return [];
        }

        try {
            $rows = Supabase::table(Embeddings::table())
                ->select('product_id', 'embedding', 'version')
                ->whereIn('product_id', array_slice($ids, 0, 500))
                ->get()
                ->toArray();
        } catch (Throwable $e) {
            // Missing table / provider down / malformed data: stay lexical.
            return [];
        }

        $version = Embeddings::version();
        $scores = [];
        foreach ($rows as $stored) {
            $id = (string) ($stored['product_id'] ?? '');
            if ($id === '' || (string) ($stored['version'] ?? '') !== $version) {
                continue;
            }

            $vector = $stored['embedding'] ?? null;
            if (is_string($vector)) {
                $decoded = json_decode($vector, true);
                $vector = is_array($decoded) ? $decoded : null;
            }
            if (! is_array($vector) || $vector === []) {
                continue;
            }

            $scores[$id] = Embeddings::cosine($queryVector, $vector);
        }

        return $scores;
    }
}
