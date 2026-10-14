<?php

namespace Tests\Unit;

use App\Services\Ai\TrustSummary;
use Tests\TestCase;

/**
 * Capability #12 contract. The trust summariser is deterministic by design,
 * so these tests pin down:
 *  - review statistics (sample size, rating coverage, distribution, period),
 *  - the fair "divided feedback" signal (both ends genuinely represented),
 *  - theme extraction by document frequency with rating-derived polarity,
 *    including the mixed case where the same word is praised and complained
 *    about,
 *  - verified-fact statements that only restate platform records,
 *  - opinion lines that always carry a sample size or count,
 *  - bounded original-review rows (never private fields),
 *  - version-key cache invalidation (review publish, business certify),
 *  - complete eight-locale coverage of every server-side string.
 *
 * Nothing here touches a model, HTTP or the database.
 */
class TrustSummaryTest extends TestCase
{
    private const LANGS = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /** A CustomerReview row as publishedReviews()-style reads return it. */
    private function review(array $overrides = []): array
    {
        return array_merge([
            'id' => 'r1',
            'title' => 'Huduma nzuri',
            'body' => 'Huduma ya haraka na bei nzuri sana. Delivery ilifika kwa muda.',
            'display_name' => 'Asha',
            'rating' => 5,
            'published_at' => '2026-01-10T09:00:00+03:00',
        ], $overrides);
    }

    /* ------------------------------------------------------------------ */
    /* Statistics                                                          */
    /* ------------------------------------------------------------------ */

    public function test_stats_cover_ratings_distribution_and_period(): void
    {
        $reviews = [
            $this->review(['rating' => 5, 'published_at' => '2026-01-05T10:00:00+03:00']),
            $this->review(['rating' => 4, 'published_at' => '2026-01-15T10:00:00+03:00']),
            $this->review(['rating' => 1, 'published_at' => '2026-02-01T10:00:00+03:00']),
            $this->review(['rating' => null, 'published_at' => 'not-a-date']),
        ];

        $stats = TrustSummary::reviewStats($reviews);

        $this->assertSame(4, $stats['sample_size'], 'Sample size must count every published review.');
        $this->assertSame(3, $stats['rated_count'], 'Only real ratings count as rated.');
        $this->assertEqualsWithDelta(3.3, $stats['rating_average'], 0.01);
        $this->assertSame(['1' => 1, '2' => 0, '3' => 0, '4' => 1, '5' => 1], $stats['rating_distribution']);
        $this->assertSame('2026-01-05', $stats['period']['from']);
        $this->assertSame('2026-02-01', $stats['period']['to']);
    }

    public function test_stats_without_any_rating_stay_honest(): void
    {
        $stats = TrustSummary::reviewStats([$this->review(['rating' => null]), $this->review(['rating' => null])]);

        $this->assertSame(2, $stats['sample_size']);
        $this->assertSame(0, $stats['rated_count']);
        $this->assertNull($stats['rating_average'], 'No ratings must mean no average, never a guessed one.');
        $this->assertFalse($stats['divided']);
    }

    public function test_divided_needs_both_ends_represented_with_enough_rated_reviews(): void
    {
        $divided = TrustSummary::reviewStats([
            $this->review(['rating' => 5]),
            $this->review(['rating' => 5]),
            $this->review(['rating' => 1]),
        ]);
        $this->assertTrue($divided['divided'], 'Praise plus complaints among three ratings is genuinely divided.');

        $oneSided = TrustSummary::reviewStats([
            $this->review(['rating' => 5]),
            $this->review(['rating' => 4]),
            $this->review(['rating' => 5]),
        ]);
        $this->assertFalse($oneSided['divided']);

        $tooFew = TrustSummary::reviewStats([
            $this->review(['rating' => 5]),
            $this->review(['rating' => 1]),
        ]);
        $this->assertFalse($tooFew['divided'], 'Two ratings alone are not enough evidence to call feedback divided.');
    }

    /* ------------------------------------------------------------------ */
    /* Themes                                                              */
    /* ------------------------------------------------------------------ */

    public function test_theme_needs_repeated_document_frequency(): void
    {
        $themes = TrustSummary::themes([
            $this->review(['body' => 'Huduma nzuri sana. Huduma ya haraka.']),
            $this->review(['body' => 'Walipokagua huduma yao vizuri.']),
            $this->review(['body' => 'Whisperloni pekee hapa.']),
        ], 2, 6);

        $labels = array_column($themes, 'label');
        $this->assertContains('huduma', $labels, 'A word appearing across reviews is a recurring theme.');
        $this->assertNotContains('whisperloni', $labels, 'A once-only word is not a recurring theme.');
    }

    public function test_stopwords_and_digit_tokens_never_become_themes(): void
    {
        $themes = TrustSummary::themes([
            $this->review(['title' => '', 'body' => 'The the na na na kwa kwa sana.']),
            $this->review(['title' => '', 'body' => 'The the na na na kwa kwa pia.']),
        ], 2, 6);

        $this->assertSame([], $themes, 'Filler words must never be reported as themes.');

        // A digit-bearing token (a year/code) is never a theme either, while a
        // plain content word in the same sentences still may be.
        $digitThemes = TrustSummary::themes([
            $this->review(['title' => '', 'body' => 'Poda poda 2024.']),
            $this->review(['title' => '', 'body' => 'Poda 2024 mpya.']),
        ], 2, 6);

        $this->assertNotContains('2024', array_column($digitThemes, 'label'), 'Digit tokens must never be themes.');
    }

    public function test_theme_polarity_follows_the_ratings_of_reviews_mentioning_it(): void
    {
        $reviews = [
            $this->review(['body' => 'Huduma ya haraka sana', 'rating' => 5]),
            $this->review(['body' => 'Huduma mbaya sana', 'rating' => 5]),
            $this->review(['body' => 'Huduma si nzuri', 'rating' => 4]),
        ];
        $themes = TrustSummary::themes($reviews, 2, 6);
        $huduma = array_values(array_filter($themes, fn ($t) => $t['label'] === 'huduma'))[0];
        $this->assertSame('positive', $huduma['polarity']);

        $negative = TrustSummary::themes([
            $this->review(['body' => 'Vitu vya juu mno', 'rating' => 1]),
            $this->review(['body' => 'Vitu vya juu sana', 'rating' => 2]),
        ], 2, 6);
        $juu = array_values(array_filter($negative, fn ($t) => $t['label'] === 'juu'))[0];
        $this->assertSame('negative', $juu['polarity']);

        // Same word, both ends of the scale -> reported as mixed, never flattened.
        $mixed = TrustSummary::themes([
            $this->review(['body' => 'Kujibu haraka sana', 'rating' => 5]),
            $this->review(['body' => 'Kujibu polepole sana', 'rating' => 1]),
        ], 2, 6);
        $kujibu = array_values(array_filter($mixed, fn ($t) => $t['label'] === 'kujibu'))[0];
        $this->assertSame('mixed', $kujibu['polarity']);

        // Unrated mentions stay neutral instead of being guessed.
        $unrated = TrustSummary::themes([
            $this->review(['body' => 'Ufungaji mzuri', 'rating' => null]),
            $this->review(['body' => 'Ufungaji gumu', 'rating' => null]),
        ], 2, 6);
        $ufungaji = array_values(array_filter($unrated, fn ($t) => $t['label'] === 'ufungaji'))[0];
        $this->assertSame('neutral', $ufungaji['polarity']);
    }

    public function test_theme_order_is_by_document_frequency_then_label(): void
    {
        $reviews = [
            $this->review(['body' => 'Delivery na huduma na bei']),
            $this->review(['body' => 'Delivery na huduma']),
            $this->review(['body' => 'Delivery']),
        ];

        $themes = TrustSummary::themes($reviews, 1, 6);

        $this->assertSame('delivery', $themes[0]['label'], 'The most frequent theme must come first.');
        $counts = array_column($themes, 'count');
        $sorted = $counts;
        rsort($sorted);
        $this->assertSame($sorted, $counts);
    }

    /* ------------------------------------------------------------------ */
    /* Facts and opinions                                                  */
    /* ------------------------------------------------------------------ */

    public function test_facts_restate_only_platform_records(): void
    {
        $business = [
            'is_certified' => true,
            'certified_at' => '2025-06-01T10:00:00+03:00',
            'product_count' => 12,
        ];
        $activity = ['total' => 30, 'completed' => 20, 'open' => 8, 'last_order_at' => '2026-02-03'];

        $facts = TrustSummary::facts($business, $activity, 'sw');

        $this->assertSame('Imethibitishwa na jukwaa tangu 2025-06-01.', $facts[0]);
        $this->assertContains('Bidhaa 12 zinazouzwa sasa.', $facts);
        $this->assertContains('Oda 30 zimepokelewa; 20 zimekamilika na 8 bado zinaendelea.', $facts);
        $this->assertContains('Oda ya hivi karibuni ilipokelewa 2026-02-03.', $facts);
    }

    public function test_facts_are_empty_without_a_business(): void
    {
        $this->assertSame([], TrustSummary::facts(null, null, 'sw'));
    }

    public function test_uncertified_business_says_so_plainly(): void
    {
        $facts = TrustSummary::facts(['is_certified' => false, 'certified_at' => null, 'product_count' => 0], null, 'en');

        $this->assertSame('Not yet certified by the platform.', $facts[0]);
        $this->assertContains('0 products on sale right now.', $facts);
    }

    public function test_opinions_always_carry_their_sample_size(): void
    {
        $stats = TrustSummary::reviewStats([
            $this->review(['rating' => 5]),
            $this->review(['rating' => 3]),
        ]);
        $themes = [['label' => 'huduma', 'count' => 2, 'polarity' => 'positive']];

        $opinions = TrustSummary::opinions($stats, $themes, 'en');

        $this->assertSame('2 published reviews; 2 carry a rating, averaging 4.0/5.', $opinions[0]);
        $this->assertContains('2 reviews mention huduma (positive).', $opinions);
    }

    public function test_opinions_state_honestly_when_there_are_none(): void
    {
        $stats = TrustSummary::reviewStats([]);

        $this->assertSame(['No published reviews yet.'], TrustSummary::opinions($stats, [], 'en'));
    }

    /* ------------------------------------------------------------------ */
    /* Summary wording and original rows                                   */
    /* ------------------------------------------------------------------ */

    public function test_deterministic_summary_uses_only_real_numbers(): void
    {
        $reviews = [
            $this->review(['rating' => 5, 'body' => 'Huduma nzuri']),
            $this->review(['rating' => 4, 'body' => 'Huduma nzuri pia']),
            $this->review(['rating' => 1, 'body' => 'Huduma kwa bei ya juu']),
        ];
        $stats = TrustSummary::reviewStats($reviews);
        $themes = TrustSummary::themes($reviews, 2, 6);

        $summary = TrustSummary::deterministicSummary($stats, $themes, null, 'en');

        $this->assertStringContainsString('3 published reviews', $summary);
        $this->assertStringContainsString('3.3/5', $summary);
        $this->assertStringContainsString('Feedback is divided', $summary, 'Divided feedback must be said out loud.');
    }

    public function test_deterministic_summary_without_reviews_adds_the_certification_state(): void
    {
        $stats = TrustSummary::reviewStats([]);
        $summary = TrustSummary::deterministicSummary($stats, [], ['is_certified' => true], 'en');

        $this->assertStringContainsString('No published reviews yet.', $summary);
        $this->assertStringContainsString('Certified by the platform.', $summary);
    }

    public function test_item_rows_are_bounded_and_never_invent_fields(): void
    {
        $longBody = str_repeat('Huduma nzuri. ', 60);
        $items = TrustSummary::itemRows([
            $this->review(['body' => $longBody]),
            $this->review(['id' => 'r2']),
            $this->review(['id' => 'r3']),
            $this->review(['id' => 'r4']),
        ]);

        $this->assertCount(TrustSummary::ITEM_LIMIT, $items, 'Only a bounded number of originals ship with the summary.');
        $this->assertTrue($items[0]['truncated']);
        $this->assertLessThanOrEqual(290, mb_strlen($items[0]['body']), 'Long bodies must be clipped.');
        $this->assertArrayNotHasKey('email', $items[0], 'Private inquiry fields must never appear.');
        $this->assertArrayNotHasKey('phone', $items[0]);
    }

    /* ------------------------------------------------------------------ */
    /* Cache invalidation                                                  */
    /* ------------------------------------------------------------------ */

    public function test_cached_payload_is_served_until_a_review_changes(): void
    {
        $locale = 'sw';
        TrustSummary::storePayload($locale, null, ['capability' => 'business_summary'], 15);

        $this->assertNotNull(TrustSummary::cached($locale, null), 'A fresh payload must be served from cache.');

        TrustSummary::invalidateReviews();

        $this->assertNull(TrustSummary::cached($locale, null), 'Publishing/unpublishing a review must expire every summary.');
    }

    public function test_certification_bump_expires_only_that_business(): void
    {
        $localeA = 'sw';
        TrustSummary::storePayload($localeA, 'biz-1', ['capability' => 'business_summary'], 15);
        TrustSummary::storePayload($localeA, 'biz-2', ['capability' => 'business_summary'], 15);

        TrustSummary::invalidateBusiness('biz-1');

        $this->assertNull(TrustSummary::cached($localeA, 'biz-1'), 'The recertified business must refresh.');
        $this->assertNotNull(TrustSummary::cached($localeA, 'biz-2'), 'Other businesses keep their cached summary.');
    }

    /* ------------------------------------------------------------------ */
    /* Localized strings                                                   */
    /* ------------------------------------------------------------------ */

    public function test_every_locale_carries_every_message(): void
    {
        $this->assertTrue(TrustSummary::messagesComplete(), 'Every locale must carry every capability #12 string.');
    }

    public function test_msg_substitutes_placeholders_and_falls_back_to_english(): void
    {
        $this->assertSame(
            'Bidhaa 7 zinazouzwa sasa.',
            TrustSummary::msg('fact_products', 'sw', ['count' => 7])
        );
        $this->assertSame(
            TrustSummary::msg('error_generic', 'en'),
            TrustSummary::msg('error_generic', 'xx'),
            'An unknown locale must fall back to English, never print the raw key.'
        );
    }

    public function test_limitations_and_sources_are_localized_for_all_languages(): void
    {
        foreach (self::LANGS as $lang) {
            $this->assertCount(5, TrustSummary::limitations($lang, 15));
            $this->assertCount(3, TrustSummary::sources($lang));
            $this->assertStringContainsString('15', TrustSummary::limitations($lang, 15)[4], 'The cache note must name the real TTL.');
        }
    }
}
