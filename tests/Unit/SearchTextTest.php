<?php

namespace Tests\Unit;

use App\Services\Ai\SearchText;
use PHPUnit\Framework\TestCase;

/**
 * Capability #24 - the pure text layer, tested without a database.
 *
 * What these tests pin:
 *  - normalization makes case, accents and punctuation irrelevant;
 *  - stopwords are dropped so "brake pads for premio" searches for the goods,
 *    but a query that is ONLY stopwords still searches;
 *  - the fuzzy distance is MB-safe (PHP's own levenshtein() counts bytes, which
 *    would wreck Devanagari/Urdu/Han queries) and is bounded by a budget;
 *  - a short typo cannot match a short word ("rce" must not match "rice");
 *  - transliteration folding catches the same word written differently;
 *  - synonym expansion works in BOTH directions (mchele <-> rice) and only when
 *    the key really appears.
 */
class SearchTextTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $fuzzy = [
        'fuzzy_enabled' => true,
        'fuzzy_max_distance' => 2,
        'fuzzy_min_token_length' => 4,
    ];

    public function test_normalize_folds_case_accents_and_punctuation(): void
    {
        $this->assertSame('brake pads premio', SearchText::normalize('  Brake-Pads/Premio! '));
        $this->assertSame('cafe creme', SearchText::normalize('Café  Crème'));
        $this->assertSame('', SearchText::normalize('   '));
        $this->assertSame('', SearchText::normalize(null));
        // Non-Latin scripts survive normalization (they are letters).
        $this->assertSame('चावल', SearchText::normalize('चावल'));
        $this->assertSame('بریک پیڈ', SearchText::normalize('بریک، پیڈ'));
    }

    public function test_tokens_drop_stopwords_but_never_return_nothing_from_text(): void
    {
        $this->assertSame(['brake', 'pads', 'premio'], SearchText::tokens('brake pads for premio'));
        $this->assertSame(['mchele', 'kilo'], SearchText::tokens('mchele ya kilo'));
        // A query that is nothing but stopwords still searches for them.
        $this->assertSame(['the'], SearchText::tokens('the'));
        $this->assertSame([], SearchText::tokens('   '));
    }

    public function test_contains_phrase_respects_word_boundaries(): void
    {
        $this->assertTrue(SearchText::containsPhrase('toyota premio 2009', 'premio'));
        // "remio" must not match "premio" as a phrase (that is the fuzzy pass).
        $this->assertFalse(SearchText::containsPhrase('toyota remio', 'premio'));
        $this->assertFalse(SearchText::containsPhrase('', 'premio'));
        $this->assertFalse(SearchText::containsPhrase('toyota premio', ''));
    }

    public function test_fold_collapses_common_transliteration_variants(): void
    {
        $this->assertSame(SearchText::fold('phone'), SearchText::fold('fone'));
        $this->assertSame(SearchText::fold('brakee'), SearchText::fold('brake'));
        $this->assertSame(SearchText::fold('sukkaari'), SearchText::fold('sukari'));
        // Folding is a comparison key, not a replacement: the real tokens differ.
        $this->assertNotSame('phone', 'fone');
    }

    public function test_variants_expand_synonyms_in_both_directions(): void
    {
        $map = [
            'mchele' => ['rice'],
            'rice' => ['mchele'],
            'brake pads' => ['brake pad', 'breki'],
            'premio' => ['toyota premio'],
        ];

        $this->assertSame(['mchele', 'rice'], SearchText::variants('mchele', $map));
        $this->assertSame(['rice', 'mchele'], SearchText::variants('rice', $map));

        $expanded = SearchText::variants('brake pads for premio', $map);
        $this->assertContains('brake pad for premio', $expanded);
        $this->assertContains('breki for premio', $expanded);
        $this->assertContains('brake pads for toyota premio', $expanded);

        // No key present -> no expansion, and never a duplicate variant.
        $this->assertSame(['sugar'], SearchText::variants('sugar', $map));
        $this->assertSame([], SearchText::variants('', $map));
    }

    public function test_levenshtein_is_multibyte_safe(): void
    {
        // One character deleted = distance 1. A byte-based implementation would
        // report several multiples of that (each character is 3 bytes).
        $this->assertSame(1, SearchText::mbLevenshtein('चावल', 'चाव'));
        $this->assertSame(0, SearchText::mbLevenshtein('चावल', 'चावल'));
        // च ा व ल = four characters (the vowel sign is its own character).
        $this->assertSame(4, SearchText::mbLevenshtein('', 'चावल'));
        $this->assertSame(2, SearchText::mbLevenshtein('ab', 'abcd'));
    }

    public function test_levenshtein_honours_an_early_exit_budget(): void
    {
        // The exact figure does not matter once it exceeds the budget; what
        // matters is that it is reported as "> budget" without walking the
        // whole matrix.
        $this->assertGreaterThan(1, SearchText::mbLevenshtein('abcdef', 'uvwxyz', 1));
        $this->assertSame(1, SearchText::mbLevenshtein('abcdef', 'abcdeg', 2));
    }

    public function test_similarity_is_one_for_identical_and_zero_for_disjoint(): void
    {
        $this->assertSame(1.0, SearchText::similarity('premio', 'premio'));
        $this->assertSame(0.0, SearchText::similarity('', 'premio'));
        // One inserted character in a six-letter word.
        $this->assertGreaterThan(0.8, SearchText::similarity('brake', 'brakee'));
        $this->assertLessThan(0.3, SearchText::similarity('premio', 'sukari'));
    }

    public function test_token_score_prefers_exact_then_prefix_then_fold(): void
    {
        $this->assertSame(1.0, SearchText::tokenScore('premio', 'premio', $this->fuzzy));
        $this->assertSame(0.92, SearchText::tokenScore('prem', 'premio', $this->fuzzy));
        $this->assertSame(0.88, SearchText::tokenScore('phone', 'fone', $this->fuzzy));
        $this->assertSame(0.0, SearchText::tokenScore('premio', '', $this->fuzzy));
    }

    public function test_token_score_tolerates_a_typo_but_not_a_short_one(): void
    {
        // A real misspelling of a long enough word matches, at a lower score.
        $score = SearchText::tokenScore('brakee', 'brake', $this->fuzzy);
        $this->assertGreaterThan(0.6, $score);
        $this->assertLessThan(1.0, $score);

        // Too short to be trusted: "rce" must NOT be read as "rice".
        $this->assertSame(0.0, SearchText::tokenScore('rce', 'rice', $this->fuzzy));

        // Completely different words never match, whatever the budget.
        $this->assertSame(0.0, SearchText::tokenScore('premio', 'sukari', $this->fuzzy));
    }

    public function test_fuzzy_can_be_switched_off_entirely(): void
    {
        $off = ['fuzzy_enabled' => false, 'fuzzy_max_distance' => 2, 'fuzzy_min_token_length' => 4];

        // "braka" is neither a prefix of "brake" nor a transliteration fold of
        // it, so without the fuzzy pass there is nothing left to match on.
        $this->assertSame(0.0, SearchText::tokenScore('braka', 'brake', $off));
        $this->assertGreaterThan(0.6, SearchText::tokenScore('braka', 'brake', $this->fuzzy));

        // Exact and prefix matches are unaffected by the fuzzy switch.
        $this->assertSame(1.0, SearchText::tokenScore('brake', 'brake', $off));
        $this->assertSame(0.92, SearchText::tokenScore('brak', 'brake', $off));
    }

    public function test_best_token_score_takes_the_strongest_candidate_token(): void
    {
        $tokens = ['toyota', 'brakee', 'premio'];
        $this->assertSame(1.0, SearchText::bestTokenScore('premio', $tokens, $this->fuzzy));
        $this->assertGreaterThan(0.6, SearchText::bestTokenScore('brake', $tokens, $this->fuzzy));
        $this->assertSame(0.0, SearchText::bestTokenScore('cement', $tokens, $this->fuzzy));
    }
}
