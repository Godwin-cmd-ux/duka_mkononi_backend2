<?php

namespace Tests\Unit;

use App\Services\Ai\ReportSummary;
use PHPUnit\Framework\TestCase;

/**
 * Capability #15: the summary of a business's books must be deterministic and
 * grounded in the rows the existing report screens already show. Pure-function
 * tests: no database, no HTTP, no Laravel helpers.
 *
 * These tests pin down:
 *  - periods: an in-progress current window is truncated to complete days and
 *    the previous window is always the same length immediately before it;
 *  - narrative: every line is built from the supplied figures only, quoted
 *    exactly as the eight-locale message table defines it;
 *  - limitations: missing sales, missing expenses, incomplete profit and the
 *    incomplete-period truncation are always disclosed, never silently filled;
 *  - comparability: gross profit / margin compare only when both windows carry
 *    a buying price for every sold line;
 *  - contributors: recorded revenue movement per product (never a cause),
 *    biggest absolute movement first, zero-movement rows dropped;
 *  - the model reword may only reuse numbers we supplied, within bounds.
 */
class ReportSummaryTest extends TestCase
{
    private const LANGS = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /* ------------------------------------------------------------------ */
    /* Fixtures                                                            */
    /* ------------------------------------------------------------------ */

    /** A complete 7-day window with known figures (shallow per-section merge). */
    private function ctx(array $overrides = []): array
    {
        $base = [
            'locale' => 'en',
            'currency' => 'TZS',
            'windows' => [
                'current' => ['from' => '2026-10-01', 'to' => '2026-10-07'],
                'previous' => ['from' => '2026-09-24', 'to' => '2026-09-30'],
                'days' => 7,
                'truncated' => false,
            ],
            'current' => [
                'products' => [
                    'p1' => ['id' => 'p1', 'name' => 'Mchuzi', 'price' => 1000],
                    'p2' => ['id' => 'p2', 'name' => 'Sabuni', 'price' => 500],
                ],
                'sales' => [
                    ['sale_date' => '2026-10-03', 'total_amount' => 6000],
                    ['sale_date' => '2026-10-05', 'total_amount' => 4000],
                ],
                'items' => [
                    ['product_id' => 'p1', 'quantity' => 5, 'total_price' => 5000],
                    ['product_id' => 'p2', 'quantity' => 8, 'total_price' => 4000],
                ],
                'expenses' => [
                    ['amount' => 1000, 'category' => 'Nishati'],
                    ['amount' => 500, 'category' => 'Usafiri'],
                ],
            ],
            'previous' => [
                'sales' => [
                    ['sale_date' => '2026-09-26', 'total_amount' => 6000],
                ],
                'items' => [
                    ['product_id' => 'p1', 'quantity' => 3, 'total_price' => 3000],
                    ['product_id' => 'p2', 'quantity' => 6, 'total_price' => 3000],
                ],
                'expenses' => [],
            ],
        ];

        foreach ($overrides as $section => $value) {
            $base[$section] = $value;
        }

        return $base;
    }

    /* ------------------------------------------------------------------ */
    /* Periods                                                             */
    /* ------------------------------------------------------------------ */

    public function test_a_complete_past_window_is_untouched_with_an_equal_length_previous_window(): void
    {
        $w = ReportSummary::windows('2026-09-01', '2026-09-10', '2026-09-15');

        $this->assertFalse($w['truncated']);
        $this->assertSame(['from' => '2026-09-01', 'to' => '2026-09-10'], $w['current']);
        $this->assertSame(10, $w['days'], 'Ten complete days.');
        $this->assertSame(['from' => '2026-08-22', 'to' => '2026-08-31'], $w['previous'], 'Exactly ten days immediately before.');
    }

    public function test_an_in_progress_window_is_truncated_to_complete_days(): void
    {
        $w = ReportSummary::windows('2026-10-01', '2026-10-12', '2026-10-12');

        $this->assertTrue($w['truncated']);
        $this->assertSame('2026-10-11', $w['current']['to'], 'The in-progress day is cut off.');
        $this->assertSame(11, $w['days']);
        $this->assertSame(['from' => '2026-09-20', 'to' => '2026-09-30'], $w['previous']);
    }

    public function test_a_window_that_is_entirely_in_progress_reports_no_days(): void
    {
        $w = ReportSummary::windows('2026-10-12', '2026-10-12', '2026-10-12');

        $this->assertTrue($w['truncated']);
        $this->assertSame(0, $w['days']);
        $this->assertSame('', $w['current']['from']);
        $this->assertNull($w['previous']);
    }

    public function test_invalid_and_reversed_ranges_report_no_days(): void
    {
        $this->assertSame(0, ReportSummary::windows('2026-09-10', '2026-09-01', '2026-09-15')['days'], 'Reversed range.');
        $this->assertSame(0, ReportSummary::windows('not-a-date', '2026-09-01', '2026-09-15')['days'], 'Garbage date.');
    }

    /* ------------------------------------------------------------------ */
    /* The deterministic summary                                           */
    /* ------------------------------------------------------------------ */

    public function test_summarize_builds_the_narrative_exactly_from_the_recorded_figures(): void
    {
        $result = ReportSummary::summarize($this->ctx());

        /* Structure the screens consume. */
        $this->assertSame(
            ['periods', 'narrative', 'metrics', 'previous_metrics', 'comparison', 'contributors', 'table', 'chart', 'sources', 'limitations'],
            array_keys($result)
        );
        $this->assertSame(['sales', 'sale_items', 'products', 'office_expenses'], $result['sources']);
        $this->assertSame(7, $result['periods']['days']);
        $this->assertSame([], $result['limitations'], 'Nothing is missing or undisclosed in this fixture.');

        /* Figures: revenue TZS 10,000 (2 sales, 13 units), cost TZS 9,000. */
        $this->assertSame(10000.0, $result['metrics']['revenue']);
        $this->assertSame(2, $result['metrics']['sales_count']);
        $this->assertSame(13.0, $result['metrics']['units']);
        $this->assertSame(1000.0, $result['metrics']['gross_profit']);
        $this->assertSame(10.0, $result['metrics']['margin_pct']);
        $this->assertSame(0, $result['metrics']['unknown_cogs_items']);
        $this->assertSame(1500.0, $result['metrics']['expenses_total']);
        $this->assertSame(2, $result['metrics']['expenses_count']);
        $this->assertSame(15.0, $result['metrics']['expense_ratio_pct']);
        $this->assertSame(6000.0, $result['previous_metrics']['revenue']);

        /* Comparison: 6,000 -> 10,000 is +4,000 (+66.67%), profit comparable. */
        $this->assertSame('up', $result['comparison']['direction']);
        $this->assertSame(4000.0, $result['comparison']['revenue_change']);
        $this->assertSame(66.67, $result['comparison']['revenue_change_pct']);
        $this->assertArrayHasKey('gross_profit', $result['comparison']['metrics'], 'Both windows carry buying prices, so profit compares.');
        $this->assertSame(10.0, $result['comparison']['margin_pct']['change_pp']);

        /* Contributors: recorded movement, biggest first - never a cause. */
        $this->assertSame('p1', $result['contributors'][0]['product_id']);
        $this->assertSame(2000.0, $result['contributors'][0]['change']);
        $this->assertSame('p2', $result['contributors'][1]['product_id']);
        $this->assertSame(1000.0, $result['contributors'][1]['change']);

        /* Table and chart come from the same rows. */
        $this->assertSame('Mchuzi', $result['table']['items'][0]['name']);
        $this->assertSame(5000.0, $result['table']['items'][0]['revenue']);
        $this->assertSame(
            ['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07'],
            $result['chart']['labels']
        );
        $this->assertSame([0.0, 0.0, 6000.0, 0.0, 4000.0, 0.0, 0.0], $result['chart']['revenue']);

        /* The narrative: every figure above, no invention, no causality. */
        $this->assertSame([
            'Period: 2026-10-01 to 2026-10-07 (7 days).',
            'Revenue TZS 10,000 from 2 sales (13 units sold).',
            'Gross profit TZS 1,000 (10% of revenue).',
            'Revenue increased by 66.7% (TZS 4,000) versus the previous period (2026-09-24 - 2026-09-30).',
            'Main contributors to the revenue change: Mchuzi (+TZS 2,000); Sabuni (+TZS 1,000).',
            'These are revenue movements of these products within the period - not proof of a cause.',
            'Top products: Mchuzi (TZS 5,000); Sabuni (TZS 4,000).',
            'Expenses TZS 1,500 (2 recorded entries).',
            'Expenses are 15% of revenue.',
        ], $result['narrative']);
    }

    public function test_missing_buying_prices_disclose_incomplete_profit_instead_of_comparing_it(): void
    {
        /* p2 has no buying price on record in either window. */
        $result = ReportSummary::summarize($this->ctx([
            'current' => [
                'products' => ['p1' => ['id' => 'p1', 'name' => 'Mchuzi', 'price' => 1000]],
                'sales' => $this->ctx()['current']['sales'],
                'items' => $this->ctx()['current']['items'],
                'expenses' => $this->ctx()['current']['expenses'],
            ],
        ]));

        $this->assertSame(1, $result['metrics']['unknown_cogs_items']);
        $this->assertArrayNotHasKey('gross_profit', $result['comparison']['metrics'], 'Incomplete cost, no profit comparison.');
        $this->assertNull($result['comparison']['margin_pct']);
        $this->assertSame([
            'Profit is incomplete: 1 sold products have no buying price on record.',
            'Profit was not compared between the two periods because a buying price is missing for some products.',
        ], $result['limitations']);
    }

    public function test_an_empty_period_is_fully_disclosed_and_says_so_once(): void
    {
        $empty = ['sales' => [], 'items' => [], 'expenses' => []];
        $result = ReportSummary::summarize($this->ctx([
            'current' => ['products' => [], 'sales' => [], 'items' => [], 'expenses' => []],
            'previous' => $empty,
        ]));

        $this->assertSame(0, $result['metrics']['sales_count']);
        $this->assertNull($result['metrics']['expense_ratio_pct'], 'No revenue means no ratio - never a made-up one.');
        $this->assertSame('flat', $result['comparison']['direction']);
        $this->assertSame([], $result['contributors']);
        $this->assertSame([
            'No sales were recorded in this period.',
            'No expenses were recorded in this period.',
            'The previous period (2026-09-24 - 2026-09-30) had no recorded sales.',
        ], $result['limitations']);

        /* Only the period line: nothing happened, so nothing is claimed. */
        $this->assertSame(['Period: 2026-10-01 to 2026-10-07 (7 days).'], $result['narrative']);
        $this->assertSame(array_fill(0, 7, 0.0), $result['chart']['revenue']);
    }

    public function test_growth_from_a_zero_previous_period_is_reported_as_such(): void
    {
        $result = ReportSummary::summarize($this->ctx([
            'previous' => ['sales' => [], 'items' => [], 'expenses' => []],
        ]));

        $this->assertSame('up_from_zero', $result['comparison']['direction']);
        $this->assertNull($result['comparison']['revenue_change_pct'], 'A percentage against zero would be dishonest.');
        $this->assertNull($result['comparison']['margin_pct'], 'Margin without a previous period cannot compare.');
        $this->assertArrayHasKey('gross_profit', $result['comparison']['metrics']);
        $this->assertSame([
            'The previous period (2026-09-24 - 2026-09-30) had no recorded sales.',
        ], $result['limitations']);

        $this->assertContains(
            'The previous period had no sales; the revenue increase is TZS 10,000 (2026-09-24 - 2026-09-30).',
            $result['narrative']
        );
        $this->assertSame('p1', $result['contributors'][0]['product_id'], 'Recorded movement still ranks by size.');
    }

    public function test_an_unchanged_period_is_reported_flat_and_ranks_no_contributors(): void
    {
        $ctx = $this->ctx();
        $ctx['previous'] = [
            'sales' => $ctx['current']['sales'],
            'items' => $ctx['current']['items'],
            'expenses' => $ctx['current']['expenses'],
        ];

        $result = ReportSummary::summarize($ctx);

        $this->assertSame('flat', $result['comparison']['direction']);
        $this->assertSame(0.0, $result['comparison']['revenue_change']);
        $this->assertSame(0.0, $result['comparison']['revenue_change_pct']);
        $this->assertSame([], $result['contributors'], 'Zero movement is not a "contributor".');

        $narrative = $result['narrative'];
        $this->assertContains('Revenue barely changed (TZS 0) versus the previous period (2026-09-24 - 2026-09-30).', $narrative);
        foreach ($narrative as $line) {
            $this->assertStringNotContainsString('contributors', $line, 'No movement means no contributor claim.');
        }
    }

    public function test_a_decline_says_decline_and_orders_contributors_by_absolute_movement(): void
    {
        /* The period earns TZS 3,000 against the previous period's TZS 6,000. */
        $result = ReportSummary::summarize($this->ctx([
            'current' => [
                'products' => [
                    'p1' => ['id' => 'p1', 'name' => 'Mchuzi', 'price' => 1000],
                    'p2' => ['id' => 'p2', 'name' => 'Sabuni', 'price' => 500],
                ],
                'sales' => [['sale_date' => '2026-10-04', 'total_amount' => 3000]],
                'items' => [
                    ['product_id' => 'p1', 'quantity' => 2, 'total_price' => 2000],
                    ['product_id' => 'p2', 'quantity' => 2, 'total_price' => 1000],
                ],
                'expenses' => $this->ctx()['current']['expenses'],
            ],
        ]));

        $this->assertSame('down', $result['comparison']['direction']);
        $this->assertSame(-3000.0, $result['comparison']['revenue_change']);
        $this->assertSame(-50.0, $result['comparison']['revenue_change_pct']);

        $this->assertContains(
            'Revenue decreased by 50% (TZS 3,000) versus the previous period (2026-09-24 - 2026-09-30).',
            $result['narrative']
        );

        /* -2,000 (p2) before -1,000 (p1): biggest absolute movement first. */
        $this->assertSame('p2', $result['contributors'][0]['product_id']);
        $this->assertSame(-2000.0, $result['contributors'][0]['change']);
        $this->assertSame('p1', $result['contributors'][1]['product_id']);
        $this->assertSame(-1000.0, $result['contributors'][1]['change']);
    }

    public function test_a_truncated_window_always_discloses_the_cut(): void
    {
        $ctx = $this->ctx();
        $ctx['windows']['truncated'] = true;

        $result = ReportSummary::summarize($ctx);

        $this->assertSame(
            ['The current period includes today, which is not complete yet; it was cut to 2026-10-07 so both periods match.'],
            $result['limitations']
        );
    }

    /* ------------------------------------------------------------------ */
    /* Contributors                                                        */
    /* ------------------------------------------------------------------ */

    public function test_contributors_rank_by_absolute_movement_and_drop_unchanged_rows(): void
    {
        $current = [
            ['product_id' => 'a', 'total_price' => 300],
            ['product_id' => 'b', 'total_price' => 5000],
            ['product_id' => 'c', 'total_price' => 100],
            ['product_id' => '', 'total_price' => 9999], // no id - ignored
        ];
        $previous = [
            ['product_id' => 'a', 'total_price' => 300], // unchanged - dropped
            ['product_id' => 'c', 'total_price' => 900],
        ];

        $rows = ReportSummary::contributors($current, $previous, ['b' => ['name' => 'Chai']], 2);

        $this->assertCount(2, $rows, 'The limit is honoured and zero-movement rows never appear.');
        $this->assertSame('b', $rows[0]['product_id']);
        $this->assertSame('Chai', $rows[0]['name']);
        $this->assertSame(5000.0, $rows[0]['change']);
        $this->assertSame('c', $rows[1]['product_id']);
        $this->assertSame('Bidhaa', $rows[1]['name'], 'An unknown product still gets an honest label.');
        $this->assertSame(-800.0, $rows[1]['change'], 'Negative movement keeps its sign.');
    }

    /* ------------------------------------------------------------------ */
    /* Messages                                                            */
    /* ------------------------------------------------------------------ */

    public function test_every_locale_carries_every_message_with_the_same_placeholders(): void
    {
        $this->assertTrue(ReportSummary::messagesComplete(), 'All eight locales must define every key.');

        $enKeys = array_keys(ReportSummary::MESSAGES['en']);
        foreach (self::LANGS as $locale) {
            $this->assertArrayHasKey($locale, ReportSummary::MESSAGES, 'Locale missing from the table: '.$locale);

            foreach ($enKeys as $key) {
                $this->assertArrayHasKey($key, ReportSummary::MESSAGES[$locale], $locale.' is missing '.$key);

                preg_match_all('/\{(\w+)\}/', ReportSummary::MESSAGES[$locale][$key], $own);
                preg_match_all('/\{(\w+)\}/', ReportSummary::MESSAGES['en'][$key], $en);
                sort($own[1]);
                sort($en[1]);
                $this->assertSame($en[1], $own[1], $locale.' '.$key.' has different placeholders.');
            }
        }
    }

    public function test_messages_fill_every_placeholder_for_every_locale(): void
    {
        $params = [
            'from' => '2026-01-01', 'to' => '2026-01-07', 'days' => 7,
            'prev_from' => '2025-12-25', 'prev_to' => '2025-12-31',
            'count' => 2, 'pct' => 15, 'change' => 500, 'revenue' => 1000,
            'units' => 4, 'profit' => 200, 'total' => 300,
            'max' => 366, 'name' => 'N', 'list' => 'L',
        ];

        foreach (array_keys(ReportSummary::MESSAGES['en']) as $key) {
            foreach (self::LANGS as $locale) {
                $text = ReportSummary::msg($key, $locale, $params);
                $this->assertNotSame('', $text, $locale.' '.$key.' rendered empty.');
                $this->assertStringNotContainsString('{', $text, $locale.' '.$key.' kept an unfilled placeholder.');
            }
        }
    }

    public function test_a_msg_falls_back_to_english_for_unknown_locales_and_keys(): void
    {
        $this->assertSame(
            'Period: 2026-01-01 to 2026-01-07 (7 days).',
            ReportSummary::msg('period_label', 'en', ['from' => '2026-01-01', 'to' => '2026-01-07', 'days' => 7])
        );
        $this->assertSame(
            ReportSummary::msg('period_label', 'en', []),
            ReportSummary::msg('period_label', 'xx', []),
            'Unknown locale renders English, not the key.'
        );
        $this->assertSame('no_such_key', ReportSummary::msg('no_such_key', 'en', []));
    }

    /* ------------------------------------------------------------------ */
    /* The model reword                                                    */
    /* ------------------------------------------------------------------ */

    public function test_reword_accepts_only_a_bounded_paragraph_of_supplied_numbers(): void
    {
        $allowed = ReportSummary::allowedNumbers(['revenue' => 9000.0, 'sales_count' => 2]);
        $this->assertContains('9000', $allowed);
        $this->assertContains('2', $allowed);

        $good = 'Revenue reached 9000 across 2 recorded sales in the window.';
        $this->assertSame($good, ReportSummary::sanitizeReword(['summary' => $good], $allowed));

        $this->assertNull(
            ReportSummary::sanitizeReword(
                ['summary' => 'Revenue reached 9500 across 2 recorded sales in the window.'],
                $allowed
            ),
            'A figure we never supplied must be rejected.'
        );
        $this->assertNull(
            ReportSummary::sanitizeReword(['summary' => 'Revenue reached 9000 and costs were 4321 today.'], $allowed),
            'Every number must be supplied, not just some.'
        );
    }

    public function test_reword_rejects_malformed_or_unbounded_payloads(): void
    {
        $allowed = ['9000'];

        $this->assertNull(ReportSummary::sanitizeReword('a plain string', $allowed), 'Not an array.');
        $this->assertNull(ReportSummary::sanitizeReword(null, $allowed));
        $this->assertNull(ReportSummary::sanitizeReword(['text' => 'wrong key'], $allowed));
        $this->assertNull(ReportSummary::sanitizeReword(['summary' => 'Too short.'], $allowed), 'Under 20 characters.');
        $this->assertNull(
            ReportSummary::sanitizeReword(['summary' => str_repeat('a ', 400)], $allowed),
            'Over 600 characters.'
        );

        $clean = ReportSummary::sanitizeReword(
            ['summary' => "Line one here\tand\x00\x01 two places now"],
            $allowed
        );
        $this->assertSame('Line one here and two places now', $clean, 'Control characters are stripped and spaces collapsed.');
    }

    public function test_allowed_numbers_come_from_the_payload_and_its_rendered_forms(): void
    {
        $allowed = ReportSummary::allowedNumbers(
            ['revenue' => 9000.0, 'margin_pct' => 10.0],
            ['TZS 9,000', 'TZS 1,000', '10%']
        );

        $this->assertContains('9000', $allowed, 'Raw payload digits.');
        $this->assertContains('10', $allowed, 'Payload and rendered forms both count.');
        $this->assertContains('9', $allowed, 'A thousands-separator form from the narrative is covered.');
    }
}
