<?php

namespace App\Services\Ai;

/**
 * Capability #24 - Semantic & Typo-Tolerant Search: the pure text layer.
 *
 * Everything here is deterministic, dependency-free and unit-testable. It does
 * NO database access and NO network access; it only turns text into comparable
 * forms. That separation is deliberate - the ranking in ProductSearch is then
 * just "score rows with these functions", and the whole matcher can be tested
 * without touching Supabase.
 *
 * What it handles:
 *   * case, accents and punctuation          -> normalize()
 *   * multi-word queries and stopwords       -> tokens()
 *   * spelling mistakes                      -> similarity() / mbLevenshtein()
 *   * Latin transliteration variants         -> fold()
 *   * alternative names / another language   -> variants() (the configured
 *                                               synonym map, both directions)
 *
 * The fuzzy distance is MB-safe (PHP's built-in levenshtein() counts BYTES, so
 * it would distort Devanagari, Urdu and Han queries).
 */
class SearchText
{
    /** Words that carry no product signal, in the languages used here. */
    private const STOPWORDS = [
        'na', 'ya', 'wa', 'kwa', 'katika', 'kwenye', 'za', 'la',
        'the', 'a', 'an', 'of', 'for', 'and', 'to', 'with', 'in', 'on', 'my',
    ];

    /** Latin accents folded to ASCII so "café" and "cafe" match. */
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
    ];

    /** Never run the (quadratic) fuzzy pass over a token this long. */
    private const MAX_FUZZY_LENGTH = 48;

    /** Never expand one query into more variants than this. */
    private const MAX_VARIANTS = 12;

    /** A typo must be at least this similar to count as the same word. */
    private const MIN_FUZZY_RATIO = 0.7;

    /**
     * Memoised transliteration folds. fold() runs a regex per call and the
     * matcher asks for the SAME catalogue tokens over and over (every query
     * compares against every candidate), so caching it is the difference
     * between a fast search and a multi-second one. Bounded so a large
     * catalogue cannot grow it without limit.
     *
     * @var array<string, string>
     */
    private static array $foldCache = [];

    private const FOLD_CACHE_LIMIT = 20000;

    /**
     * Lower-case, accent-folded, punctuation-free, single-spaced text. This is
     * the form everything else compares in.
     */
    public static function normalize(?string $text): string
    {
        $value = mb_strtolower(trim((string) $text), 'UTF-8');
        if ($value === '') {
            return '';
        }

        $value = strtr($value, self::ACCENTS);
        // Anything that is not a letter, a digit, a combining MARK or a space
        // becomes a space, so "brake-pads/for:premio" reads as words.
        // \p{M} must be kept: a Devanagari vowel sign or an Arabic diacritic is
        // a mark, not a letter, and stripping it would silently corrupt the
        // token ("चावल" would become "च वल").
        $value = (string) preg_replace('/[^\p{L}\p{N}\p{M}]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * Signal-carrying tokens (stopwords removed). Falls back to the unstripped
     * list when a query is nothing but stopwords, so "the" still searches.
     *
     * @return array<int, string>
     */
    public static function tokens(?string $text): array
    {
        $normalized = self::normalize($text);
        if ($normalized === '') {
            return [];
        }

        $all = explode(' ', $normalized);
        $significant = array_values(array_filter(
            $all,
            static fn (string $token): bool => $token !== '' && ! in_array($token, self::STOPWORDS, true)
        ));

        return $significant !== [] ? $significant : $all;
    }

    /**
     * Transliteration fold: collapses the common Latin variants that mean the
     * same word, so "phone"/"fone", "breaki"/"breki" and "quality"/"kwaliti"
     * compare equal. Used as an ADDITIONAL comparison key, never as a
     * replacement for the real token.
     */
    public static function foldCached(string $token): string
    {
        if (isset(self::$foldCache[$token])) {
            return self::$foldCache[$token];
        }

        if (count(self::$foldCache) >= self::FOLD_CACHE_LIMIT) {
            self::$foldCache = [];
        }

        return self::$foldCache[$token] = self::fold($token);
    }

    public static function fold(?string $text): string
    {
        $value = self::normalize($text);
        if ($value === '') {
            return '';
        }

        $value = strtr($value, [
            'ph' => 'f',
            'ck' => 'k',
            'q' => 'k',
            'x' => 'ks',
            'z' => 's',
            'w' => 'v',
        ]);
        // Collapse doubled letters ("sukkaari" -> "sukari").
        $value = (string) preg_replace('/(.)\1+/u', '$1', $value);

        return $value;
    }

    /**
     * The query plus one variant per configured synonym/transliteration hit.
     *
     * The synonym map is the only place search vocabulary lives; expansion is
     * bounded because the map itself is bounded and a variant is only added when
     * its key genuinely appears (as a phrase) in the query.
     *
     * @return array<int, string> Normalised query variants (original first).
     */
    public static function variants(?string $query, array $synonyms = []): array
    {
        $normalized = self::normalize($query);
        if ($normalized === '') {
            return [];
        }

        // Normalise the configured keys once, and drop the empties.
        $keys = [];
        foreach ($synonyms as $key => $replacements) {
            $phrase = self::normalize((string) $key);
            if ($phrase === '' || ! is_array($replacements) || $replacements === []) {
                continue;
            }
            $keys[$phrase] = $replacements;
        }

        $variants = [$normalized];

        foreach ($keys as $phrase => $replacements) {
            if (! self::containsPhrase($normalized, $phrase)) {
                continue;
            }

            // Never let a SHORTER key fire inside a longer key that already
            // matched: with both "brake pads" and "brake" configured, expanding
            // "brake" as well would produce nonsense like "brake pad pads for
            // premio" and waste a variant. A key is only skipped when the longer
            // phrase really contains it ("brake pads" contains "brake"), so
            // unrelated keys still expand ("premio" -> "toyota premio").
            foreach ($keys as $other => $ignored) {
                if ($other === $phrase || mb_strlen($other) <= mb_strlen($phrase)) {
                    continue;
                }
                if (self::containsPhrase($other, $phrase) && self::containsPhrase($normalized, $other)) {
                    continue 2;
                }
            }

            foreach ($replacements as $replacement) {
                $value = self::normalize((string) $replacement);
                if ($value === '') {
                    continue;
                }
                $variant = self::collapseRepeatedWords(trim((string) preg_replace(
                    '/\b'.preg_quote($phrase, '/').'\b/u',
                    $value,
                    $normalized
                )));
                if ($variant !== '' && $variant !== $normalized && ! in_array($variant, $variants, true)) {
                    $variants[] = $variant;
                }
                if (count($variants) >= self::MAX_VARIANTS) {
                    return $variants;
                }
            }
        }

        return $variants;
    }

    /**
     * "brake pads pads for premio" -> "brake pads for premio".
     *
     * A shorter synonym key can match INSIDE a longer phrase that another key
     * already replaced ("brake" inside "brake pads"), which would otherwise
     * duplicate the word and waste a variant - and every variant costs work.
     */
    public static function collapseRepeatedWords(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $out = [];
        foreach (explode(' ', $text) as $word) {
            if ($out !== [] && end($out) === $word) {
                continue;
            }
            $out[] = $word;
        }

        return implode(' ', $out);
    }

    /** Whole-word phrase presence ("premio" inside "toyota premio"). */
    public static function containsPhrase(string $haystack, string $phrase): bool
    {
        if ($haystack === '' || $phrase === '') {
            return false;
        }

        return (bool) preg_match('/\b'.preg_quote($phrase, '/').'\b/u', $haystack);
    }

    /** MB-safe Levenshtein distance (PHP's own function is byte-based). */
    public static function mbLevenshtein(string $a, string $b, int $max = 0): int
    {
        $left = $a === '' ? [] : mb_str_split($a);
        $right = $b === '' ? [] : mb_str_split($b);
        $n = count($left);
        $m = count($right);

        if ($n === 0) {
            return $m;
        }
        if ($m === 0) {
            return $n;
        }
        // Guard the quadratic cost on absurd tokens.
        if ($n > self::MAX_FUZZY_LENGTH || $m > self::MAX_FUZZY_LENGTH) {
            return max($n, $m);
        }

        $previous = range(0, $m);
        for ($i = 1; $i <= $n; $i++) {
            $current = [$i];
            for ($j = 1; $j <= $m; $j++) {
                $cost = $left[$i - 1] === $right[$j - 1] ? 0 : 1;
                $current[$j] = min(
                    $previous[$j] + 1,        // deletion
                    $current[$j - 1] + 1,     // insertion
                    $previous[$j - 1] + $cost // substitution
                );
            }
            $previous = $current;

            // Early exit: once a whole row exceeds the budget, the answer is
            // certainly above it, so stop paying for the rest of the matrix.
            if ($max > 0 && min($current) > $max) {
                return $max + 1;
            }
        }

        return $previous[$m];
    }

    /** 1.0 for identical strings, 0.0 for completely different ones. */
    public static function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $longest = max(mb_strlen($a), mb_strlen($b));
        if ($longest === 0) {
            return 0.0;
        }

        return max(0.0, 1.0 - (self::mbLevenshtein($a, $b) / $longest));
    }

    /**
     * How well one query token matches one candidate token (0.0 - 1.0).
     * Exact > prefix > transliteration fold > fuzzy, in that order.
     */
    public static function tokenScore(string $queryToken, string $candidateToken, ?array $options = null): float
    {
        if ($queryToken === '' || $candidateToken === '') {
            return 0.0;
        }
        if ($queryToken === $candidateToken) {
            return 1.0;
        }

        // Prefix match (both directions) - "prem" finds "premio".
        $minPrefix = 4;
        if (mb_strlen($queryToken) >= $minPrefix && str_starts_with($candidateToken, $queryToken)) {
            return 0.92;
        }
        if (mb_strlen($candidateToken) >= $minPrefix && str_starts_with($queryToken, $candidateToken)) {
            return 0.85;
        }

        // Same word written differently ("phone"/"fone"). Memoised: this is
        // the hottest path in the matcher.
        if (self::foldCached($queryToken) === self::foldCached($candidateToken)) {
            return 0.88;
        }

        if (empty($options['fuzzy_enabled'])) {
            return 0.0;
        }

        $minLength = max(5, (int) ($options['fuzzy_min_token_length'] ?? 5));
        $queryLength = mb_strlen($queryToken);
        $candidateLength = mb_strlen($candidateToken);
        if ($queryLength < $minLength || $candidateLength < $minLength) {
            return 0.0;
        }

        // One edit for words up to nine characters, two beyond that. A four- or
        // five-letter word must be almost exactly right: without this, "rice"
        // matched "race" and a rice search returned ball bearings.
        $budget = min(
            (int) ($options['fuzzy_max_distance'] ?? 2),
            max(1, intdiv($queryLength, 5))
        );

        // Cheapest possible prune FIRST: a word inside the edit budget can only
        // be as different in LENGTH as the budget allows. This skips the
        // quadratic DP for the overwhelming majority of pairs.
        if (abs($queryLength - $candidateLength) > $budget) {
            return 0.0;
        }

        $distance = self::mbLevenshtein($queryToken, $candidateToken, $budget);
        if ($distance > $budget) {
            return 0.0;
        }

        $ratio = self::similarity($queryToken, $candidateToken);

        // Distance alone is not enough for a product name; require real overlap.
        return $ratio >= self::MIN_FUZZY_RATIO ? round($ratio * 0.9, 4) : 0.0;
    }

    /**
     * Best score for a query token across a candidate's tokens.
     *
     * @param  array<int, string>  $candidateTokens
     */
    public static function bestTokenScore(string $queryToken, array $candidateTokens, ?array $options = null): float
    {
        $best = 0.0;
        foreach ($candidateTokens as $candidateToken) {
            $score = self::tokenScore($queryToken, $candidateToken, $options);
            if ($score > $best) {
                $best = $score;
                if ($best >= 1.0) {
                    break;
                }
            }
        }

        return $best;
    }

    /**
     * Fuzzy options derived from config, so callers cannot forget the bounds.
     *
     * @return array<string, mixed>
     */
    public static function fuzzyOptions(?array $override = null): array
    {
        $options = [
            'fuzzy_enabled' => (bool) config('ai.search.fuzzy_enabled', true),
            'fuzzy_max_distance' => (int) config('ai.search.fuzzy_max_distance', 2),
            'fuzzy_min_token_length' => (int) config('ai.search.fuzzy_min_token_length', 4),
        ];

        return $override === null ? $options : array_merge($options, $override);
    }
}
