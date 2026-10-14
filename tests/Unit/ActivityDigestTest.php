<?php

namespace Tests\Unit;

use App\Services\Ai\ActivityDigest;
use PHPUnit\Framework\TestCase;

/**
 * Capability #17 contract. The digest is grounded in the real activity log, so
 * these tests pin down:
 *  - the reporting windows (daily + weekly, equal-length previous window,
 *    partial-window flag) are pure date arithmetic;
 *  - action names are classified into allow-listed families deterministically,
 *    and an AI-assisted business event still counts as the business event;
 *  - record references come only from `*_id` keys, never person identifiers;
 *  - the digest reports real counts, flags a partial window, shows missing
 *    logging coverage, and always carries the "not a performance assessment"
 *    limitation;
 *  - nothing in it is inferred: no ranking language, no intent, no guessing.
 *
 * Pure-function tests: no database, no HTTP, no Laravel helpers.
 */
class ActivityDigestTest extends TestCase
{
    private const LANGS = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /** A Wednesday, so "this week" spans Monday..today (3 days). */
    private const TODAY = '2026-10-14';

    /* ------------------------------------------------------------------ */
    /* Message coverage                                                    */
    /* ------------------------------------------------------------------ */

    public function test_every_locale_carries_every_message(): void
    {
        $this->assertTrue(ActivityDigest::messagesComplete(), 'All eight locales must carry the same message keys.');
        $this->assertSame(self::LANGS, array_keys(ActivityDigest::MESSAGES));
    }

    public function test_every_locale_fills_the_same_placeholders(): void
    {
        foreach (array_keys(ActivityDigest::MESSAGES['en']) as $key) {
            $expected = ActivityDigest::placeholders($key, 'en');
            foreach (self::LANGS as $locale) {
                $this->assertSame(
                    $expected,
                    ActivityDigest::placeholders($key, $locale),
                    "Message '{$key}' must use the same placeholders in {$locale}."
                );
            }
        }
    }

    public function test_messages_fall_back_to_english_then_the_key(): void
    {
        $this->assertSame('Today', ActivityDigest::msg('period_today', 'en'));
        $this->assertNotSame('period_today', ActivityDigest::msg('period_today', 'sw'));
        $this->assertSame('does_not_exist', ActivityDigest::msg('does_not_exist', 'sw'));
    }

    public function test_placeholders_are_substituted(): void
    {
        $text = ActivityDigest::msg('n_events', 'en', ['events' => 12, 'actors' => 3]);
        $this->assertStringContainsString('12', $text);
        $this->assertStringContainsString('3', $text);
        $this->assertStringNotContainsString('{', $text);
    }

    /* ------------------------------------------------------------------ */
    /* Windows                                                             */
    /* ------------------------------------------------------------------ */

    public function test_windows_rejects_an_unknown_period(): void
    {
        $windows = ActivityDigest::windows('next_month', self::TODAY);
        $this->assertSame('', $windows['period']);
        $this->assertSame(0, $windows['days']);
        $this->assertNull($windows['previous']);
    }

    public function test_today_window_is_one_partial_day(): void
    {
        $windows = ActivityDigest::windows('today', self::TODAY);
        $this->assertSame(['from' => '2026-10-14', 'to' => '2026-10-14'], $windows['current']);
        $this->assertSame(['from' => '2026-10-13', 'to' => '2026-10-13'], $windows['previous']);
        $this->assertSame(1, $windows['days']);
        $this->assertTrue($windows['partial'], 'A window containing today is still in progress.');
    }

    public function test_yesterday_window_is_complete(): void
    {
        $windows = ActivityDigest::windows('yesterday', self::TODAY);
        $this->assertSame(['from' => '2026-10-13', 'to' => '2026-10-13'], $windows['current']);
        $this->assertSame(['from' => '2026-10-12', 'to' => '2026-10-12'], $windows['previous']);
        $this->assertFalse($windows['partial']);
    }

    public function test_this_week_window_starts_on_monday_and_is_partial(): void
    {
        $windows = ActivityDigest::windows('this_week', self::TODAY);
        $this->assertSame(['from' => '2026-10-12', 'to' => '2026-10-14'], $windows['current']);
        $this->assertSame(3, $windows['days']);
        $this->assertTrue($windows['partial']);
        // The previous window must be the same number of days, immediately before.
        $this->assertSame(['from' => '2026-10-09', 'to' => '2026-10-11'], $windows['previous']);
    }

    public function test_last_week_window_is_a_complete_monday_to_sunday(): void
    {
        $windows = ActivityDigest::windows('last_week', self::TODAY);
        $this->assertSame(['from' => '2026-10-05', 'to' => '2026-10-11'], $windows['current']);
        $this->assertSame(7, $windows['days']);
        $this->assertFalse($windows['partial']);
        $this->assertSame(['from' => '2026-09-28', 'to' => '2026-10-04'], $windows['previous']);
    }

    public function test_last_7_days_window_is_seven_days_and_partial(): void
    {
        $windows = ActivityDigest::windows('last_7_days', self::TODAY);
        $this->assertSame(['from' => '2026-10-08', 'to' => '2026-10-14'], $windows['current']);
        $this->assertSame(7, $windows['days']);
        $this->assertTrue($windows['partial']);
        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-07'], $windows['previous']);
    }

    public function test_every_window_has_a_previous_window_of_the_same_length(): void
    {
        foreach (ActivityDigest::PERIODS as $period) {
            $windows = ActivityDigest::windows($period, self::TODAY);
            $this->assertNotNull($windows['previous'], "{$period} must be comparable to a previous window.");

            $currentFrom = strtotime($windows['current']['from']);
            $currentTo = strtotime($windows['current']['to']);
            $previousFrom = strtotime($windows['previous']['from']);
            $previousTo = strtotime($windows['previous']['to']);

            $this->assertSame(
                (int) round(($currentTo - $currentFrom) / 86400),
                (int) round(($previousTo - $previousFrom) / 86400),
                "{$period}: the previous window must match the current window length."
            );
            $this->assertSame(
                date('Y-m-d', strtotime('-1 day', $currentFrom)),
                $windows['previous']['to'],
                "{$period}: the previous window must end immediately before the current one."
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /* Classification                                                      */
    /* ------------------------------------------------------------------ */

    public function test_actions_are_classified_into_allow_listed_families(): void
    {
        $this->assertSame('product', ActivityDigest::categoryFor('PRODUCT_CREATE'));
        $this->assertSame('product', ActivityDigest::categoryFor('PRODUCT_UPDATE'));
        $this->assertSame('sale', ActivityDigest::categoryFor('SALE_CREATE'));
        $this->assertSame('sale', ActivityDigest::categoryFor('SALES_VIEW'));
        $this->assertSame('expense', ActivityDigest::categoryFor('EXPENSE_CREATE'));
        $this->assertSame('customer', ActivityDigest::categoryFor('CUSTOMER_UPDATE'));
        $this->assertSame('order', ActivityDigest::categoryFor('PAYMENT_RECORDED'));
        $this->assertSame('advert', ActivityDigest::categoryFor('MATANGAZO_CREATE'));
        $this->assertSame('account', ActivityDigest::categoryFor('LOGIN'));
        $this->assertSame('other', ActivityDigest::categoryFor('SOMETHING_ELSE'));
    }

    /**
     * The prefixes are grounded in the action names the live log actually
     * contains, so real sign-ins are not dumped into "Other".
     */
    public function test_the_live_logs_own_action_names_are_classified(): void
    {
        $this->assertSame('account', ActivityDigest::categoryFor('AUTH_SUCCESS'));
        $this->assertSame('account', ActivityDigest::categoryFor('LOGIN_SUCCESS'));
        $this->assertSame('account', ActivityDigest::categoryFor('PROFILE_VIEW'));
        $this->assertSame('product', ActivityDigest::categoryFor('PRODUCTS_VIEW'));
        $this->assertSame('product', ActivityDigest::categoryFor('PRODUCT_UPDATE'));
        $this->assertSame('sale', ActivityDigest::categoryFor('SALES_VIEW'));
        $this->assertSame('sale', ActivityDigest::categoryFor('SALE_CREATE'));
        $this->assertSame('customer', ActivityDigest::categoryFor('CUSTOMERS_VIEW'));
    }

    public function test_an_ai_assisted_business_event_still_counts_as_that_event(): void
    {
        // An AI-committed sale is a SALE entry, not generic AI usage.
        $this->assertSame('sale', ActivityDigest::categoryFor('AI_SALE_COMMIT'));
        $this->assertSame('expense', ActivityDigest::categoryFor('AI_EXPENSE_PARSE'));
        // A purely AI action with no business family is AI usage.
        $this->assertSame('ai', ActivityDigest::categoryFor('AI_REQUEST'));
        $this->assertSame('ai', ActivityDigest::categoryFor('AI_REVIEW_CHECK'));
    }

    public function test_classification_never_returns_an_unlisted_category(): void
    {
        foreach (['', '   ', 'PRODUCT_CREATE', 'AI_X', 'ZZZ', 'sale_create'] as $action) {
            $this->assertContains(ActivityDigest::categoryFor($action), ActivityDigest::CATEGORIES);
        }
    }

    public function test_category_labels_exist_in_every_locale(): void
    {
        foreach (ActivityDigest::CATEGORIES as $category) {
            foreach (self::LANGS as $locale) {
                $this->assertNotSame('cat_'.$category, ActivityDigest::categoryLabel($category, $locale));
            }
        }
        // An unknown key degrades to "other" instead of leaking the raw value.
        $this->assertSame(ActivityDigest::msg('cat_other', 'en'), ActivityDigest::categoryLabel('bogus', 'en'));
    }

    public function test_creation_actions_are_recognised(): void
    {
        $this->assertTrue(ActivityDigest::isCreation('PRODUCT_CREATE'));
        $this->assertTrue(ActivityDigest::isCreation('PAYMENT_INITIATED'));
        $this->assertFalse(ActivityDigest::isCreation('PRODUCTS_VIEW'));
        $this->assertFalse(ActivityDigest::isCreation(''));
    }

    /* ------------------------------------------------------------------ */
    /* Record references                                                   */
    /* ------------------------------------------------------------------ */

    public function test_refs_surface_record_ids_only(): void
    {
        $refs = ActivityDigest::extractRefs(json_encode([
            'product_id' => 'p-1',
            'sale_id' => 's-9',
            'user_id' => 'u-should-be-skipped',
            'business_id' => 'b-should-be-skipped',
            'seller_id' => 'u-again-skipped',
            'reason' => 'not an id',
        ]));

        $fields = array_column($refs, 'field');
        $this->assertSame(['product_id', 'sale_id'], $fields);
    }

    public function test_refs_are_bounded_and_ignore_unsafe_values(): void
    {
        $refs = ActivityDigest::extractRefs([
            'product_id' => str_repeat('x', 500),
            'sale_id' => ['nested' => 'array'],
            'order_id' => 'o-1',
            'customer_id' => 'c-1',
            'expense_id' => 'e-1',
            'advert_id' => 'a-1',
        ], 3);

        $this->assertCount(3, $refs, 'Refs must be bounded.');
        $this->assertSame(['order_id', 'customer_id', 'expense_id'], array_column($refs, 'field'));
    }

    public function test_refs_accept_the_raw_details_column_being_a_string(): void
    {
        $this->assertSame([], ActivityDigest::extractRefs('not json'));
        $this->assertSame([], ActivityDigest::extractRefs(null));
    }

    /* ------------------------------------------------------------------ */
    /* The digest itself                                                   */
    /* ------------------------------------------------------------------ */

    private function members(): array
    {
        return [
            ['id' => 'u-admin', 'name' => 'Asha', 'role' => 'admin'],
            ['id' => 'u-seller', 'name' => 'Juma', 'role' => 'seller'],
            ['id' => 'u-quiet', 'name' => 'Neema', 'role' => 'seller'],
        ];
    }

    private function log(string $id, string $actor, string $action, string $at, string $status = 'success', array $details = []): array
    {
        return [
            'id' => $id,
            'user_id' => $actor,
            'action' => $action,
            'endpoint' => '/api/'.strtolower(str_replace('_', '/', $action)),
            'details' => $details === [] ? null : json_encode($details),
            'status' => $status,
            'created_at' => $at,
        ];
    }

    private function summarize(array $logs, array $previousLogs = [], string $period = 'this_week', ?array $windows = null): array
    {
        return ActivityDigest::summarize([
            'locale' => 'en',
            'period' => $period,
            'windows' => $windows ?? ActivityDigest::windows($period, self::TODAY),
            'members' => $this->members(),
            'logs' => $logs,
            'previous_logs' => $previousLogs,
        ]);
    }

    public function test_an_empty_window_reports_zero_and_says_so(): void
    {
        $digest = $this->summarize([]);

        $this->assertSame(0, $digest['totals']['events']);
        $this->assertSame(3, $digest['totals']['members']);
        $this->assertSame(0, $digest['totals']['actors']);
        $this->assertSame([], $digest['categories']);
        $this->assertSame([], $digest['noteworthy']);
        $this->assertContains(ActivityDigest::msg('n_no_events', 'en'), $digest['narrative']);
        $this->assertContains(ActivityDigest::msg('lim_no_events', 'en'), $digest['limitations']);
    }

    public function test_counts_come_from_the_rows_and_are_grouped_by_family(): void
    {
        $digest = $this->summarize([
            $this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-12T09:00:00.000000+03:00'),
            $this->log('2', 'u-admin', 'SALE_CREATE', '2026-10-13T10:00:00.000000+03:00'),
            $this->log('3', 'u-seller', 'PRODUCT_CREATE', '2026-10-13T11:00:00.000000+03:00'),
            $this->log('4', 'u-seller', 'EXPENSE_CREATE', '2026-10-14T08:00:00.000000+03:00'),
            $this->log('5', 'u-seller', 'PRODUCT_DELETE_FAILED', '2026-10-14T08:30:00.000000+03:00', 'failed'),
        ]);

        $this->assertSame(5, $digest['totals']['events']);
        $this->assertSame(4, $digest['totals']['success']);
        $this->assertSame(1, $digest['totals']['failed']);
        $this->assertSame(2, $digest['totals']['actors']);

        $byKey = [];
        foreach ($digest['categories'] as $row) {
            $byKey[$row['key']] = $row;
        }
        $this->assertSame(2, $byKey['sale']['count']);
        $this->assertSame(2, $byKey['product']['count']);
        $this->assertSame(1, $byKey['product']['failed'], 'The failed action is counted against its family too.');
        $this->assertSame(1, $byKey['expense']['count']);

        // Biggest family first (ties broken alphabetically, deterministically).
        $this->assertSame('product', $digest['categories'][0]['key']);
        $this->assertSame('sale', $digest['categories'][1]['key']);

        // A tie must NOT be reported as one family dominating the window.
        $narrative = implode(' ', $digest['narrative']);
        $this->assertStringNotContainsString('Most of them were', $narrative);

        $dominant = $this->summarize([
            $this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-13T09:00:00.000000+03:00'),
            $this->log('2', 'u-admin', 'SALE_CREATE', '2026-10-13T10:00:00.000000+03:00'),
            $this->log('3', 'u-seller', 'PRODUCT_CREATE', '2026-10-13T11:00:00.000000+03:00'),
        ]);
        $this->assertStringContainsString('Most of them were Sales: 2 events', implode(' ', $dominant['narrative']));
    }

    public function test_the_narrative_carries_the_real_figures(): void
    {
        $digest = $this->summarize([
            $this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-13T09:00:00.000000+03:00'),
            $this->log('2', 'u-seller', 'SALE_CREATE', '2026-10-13T10:00:00.000000+03:00'),
            $this->log('3', 'u-seller', 'PRODUCT_DELETE_FAILED', '2026-10-13T11:00:00.000000+03:00', 'failed'),
        ]);

        $narrative = implode(' ', $digest['narrative']);
        $this->assertStringContainsString('3 events', $narrative);
        $this->assertStringContainsString('2 members', $narrative);
        $this->assertStringContainsString('1 events failed', $narrative);
    }

    public function test_missing_logging_coverage_is_visible(): void
    {
        $digest = $this->summarize([
            $this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-13T09:00:00.000000+03:00'),
        ]);

        $this->assertSame(['Juma', 'Neema'], $digest['silent_members'], 'Members with no logged events must be named.');
        $this->assertStringContainsString('2 members have no recorded events', implode(' ', $digest['narrative']));
    }

    public function test_per_member_counts_are_factual_and_never_ranked_by_superlative(): void
    {
        $digest = $this->summarize([
            $this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-13T09:00:00.000000+03:00'),
            $this->log('2', 'u-seller', 'SALE_CREATE', '2026-10-13T10:00:00.000000+03:00'),
            $this->log('3', 'u-seller', 'PRODUCT_CREATE', '2026-10-13T11:00:00.000000+03:00'),
        ]);

        $rows = [];
        foreach ($digest['members'] as $member) {
            $rows[$member['name']] = $member['count'];
        }
        $this->assertSame(['Juma' => 2, 'Asha' => 1, 'Neema' => 0], $rows);

        $text = strtolower(implode(' ', $digest['narrative']).' '.implode(' ', $digest['limitations']));
        foreach (['best', 'worst', 'top performer', 'lazy', 'steal', 'theft'] as $banned) {
            $this->assertStringNotContainsString($banned, $text, 'The digest must not judge staff.');
        }
        $this->assertContains(ActivityDigest::msg('lim_counts_not_performance', 'en'), $digest['limitations']);
        $this->assertContains(ActivityDigest::msg('lim_no_inference', 'en'), $digest['limitations']);
    }

    public function test_day_series_covers_every_day_of_the_window_including_gaps(): void
    {
        $digest = $this->summarize([
            $this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-12T09:00:00.000000+03:00'),
            $this->log('2', 'u-admin', 'SALE_CREATE', '2026-10-12T10:00:00.000000+03:00'),
            $this->log('3', 'u-seller', 'SALE_CREATE', '2026-10-14T10:00:00.000000+03:00'),
        ]);

        $this->assertSame([
            ['date' => '2026-10-12', 'count' => 2],
            ['date' => '2026-10-13', 'count' => 0],
            ['date' => '2026-10-14', 'count' => 1],
        ], $digest['day_series']);
    }

    public function test_comparison_directions_are_explicit(): void
    {
        $one = [$this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-13T09:00:00.000000+03:00')];
        $two = [
            $this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-06T09:00:00.000000+03:00'),
            $this->log('2', 'u-admin', 'SALE_CREATE', '2026-10-07T09:00:00.000000+03:00'),
        ];

        $up = $this->summarize($two, $one);
        $this->assertSame('up', $up['comparison']['direction']);
        $this->assertSame(1, $up['comparison']['change']);

        $down = $this->summarize($one, $two);
        $this->assertSame('down', $down['comparison']['direction']);
        $this->assertSame(1, $down['comparison']['change']);

        $flat = $this->summarize($one, $one);
        $this->assertSame('flat', $flat['comparison']['direction']);
        $this->assertSame(0, $flat['comparison']['change']);

        $fromZero = $this->summarize($one, []);
        $this->assertSame('up_from_zero', $fromZero['comparison']['direction']);
        $this->assertContains(ActivityDigest::msg('n_compare_from_zero', 'en'), $fromZero['narrative']);
    }

    public function test_noteworthy_events_are_failures_and_record_creations_newest_first(): void
    {
        $digest = $this->summarize([
            $this->log('1', 'u-admin', 'PRODUCTS_VIEW', '2026-10-12T09:00:00.000000+03:00'),
            $this->log('2', 'u-admin', 'SALE_CREATE', '2026-10-12T10:00:00.000000+03:00', 'success', ['sale_id' => 's-1']),
            $this->log('3', 'u-seller', 'PASSWORD_RESET_FAILED', '2026-10-13T10:00:00.000000+03:00', 'failed'),
            $this->log('4', 'u-seller', 'PRODUCT_CREATE', '2026-10-14T10:00:00.000000+03:00', 'success', ['product_id' => 'p-7']),
        ], [], 'this_week');

        $ids = array_column($digest['noteworthy'], 'id');
        $this->assertSame(['4', '3', '2'], $ids, 'Views are not noteworthy; creations and failures are, newest first.');

        $this->assertSame('Juma', $digest['noteworthy'][1]['actor_name'], 'The failed sign-in is attributed to the member who attempted it.');

        $product = $digest['noteworthy'][0];
        $this->assertSame('product', $product['category']);
        $this->assertSame('Products', $product['category_label']);
        $this->assertSame([['field' => 'product_id', 'value' => 'p-7']], $product['refs']);
        $this->assertSame('Juma', $product['actor_name']);
    }

    public function test_noteworthy_events_are_bounded(): void
    {
        $logs = [];
        for ($i = 0; $i < 40; $i++) {
            $logs[] = $this->log('row-'.$i, 'u-admin', 'SALE_CREATE', '2026-10-13T09:00:00.000000+03:00');
        }

        $digest = ActivityDigest::summarize([
            'locale' => 'en',
            'period' => 'this_week',
            'windows' => ActivityDigest::windows('this_week', self::TODAY),
            'members' => $this->members(),
            'logs' => $logs,
            'previous_logs' => [],
            'noteworthy_limit' => 12,
        ]);

        $this->assertCount(12, $digest['noteworthy']);
        $this->assertSame(40, $digest['totals']['events']);
    }

    public function test_a_partial_window_is_disclosed(): void
    {
        $partial = $this->summarize([$this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-13T09:00:00.000000+03:00')], [], 'this_week');
        $this->assertTrue($partial['windows']['partial']);
        $this->assertContains(ActivityDigest::msg('lim_partial_window', 'en'), $partial['limitations']);

        $complete = $this->summarize([$this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-07T09:00:00.000000+03:00')], [], 'last_week');
        $this->assertFalse($complete['windows']['partial']);
        $this->assertNotContains(ActivityDigest::msg('lim_partial_window', 'en'), $complete['limitations']);
    }

    public function test_rows_from_an_unknown_actor_do_not_break_the_digest(): void
    {
        $digest = $this->summarize([
            $this->log('1', 'u-gone', 'SALE_CREATE', '2026-10-13T09:00:00.000000+03:00'),
        ]);

        $this->assertSame(1, $digest['totals']['events']);
        $this->assertSame(ActivityDigest::msg('actor_unknown', 'en'), $digest['noteworthy'][0]['actor_name']);
    }

    public function test_sources_and_limitations_are_always_present(): void
    {
        $digest = $this->summarize([$this->log('1', 'u-admin', 'SALE_CREATE', '2026-10-13T09:00:00.000000+03:00')]);

        $this->assertNotSame([], $digest['sources']);
        $this->assertNotSame([], $digest['limitations']);
        $this->assertNotSame([], $digest['narrative']);
        $this->assertSame('this_week', $digest['period']);
        $this->assertSame('This week', $digest['period_label']);
    }
}
