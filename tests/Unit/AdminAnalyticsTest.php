<?php

namespace Tests\Unit;

use App\Services\Ai\AdminAnalytics;
use PHPUnit\Framework\TestCase;

/**
 * Capability #20 - Natural-Language Admin Analytics (parser + arithmetic).
 *
 * What these tests pin:
 *  - a question only ever maps onto an allow-listed tool, and every example the
 *    app itself advertises maps back onto the tool it advertises - in all eight
 *    locales (a question the user is shown must never answer "I did not
 *    understand");
 *  - the period a question resolves to is an exact calendar window, clamped to
 *    the documented bound, and repeated in the previous period for comparisons;
 *  - presence ("who is online now") is never mixed with history ("what was
 *    recorded"): a live tool drops the period and never gains one;
 *  - the arithmetic is pure and filters by the range: rows outside the window
 *    are ignored, so a widening query cannot inflate an answer;
 *  - certification reports an oldest pending business and caps how many
 *    businesses it will name (never the whole list);
 *  - every presentation carries a value, a plain-language explanation, a table
 *    and/or a chart, its sources and its honest limitations - and no personal
 *    field, in any locale;
 *  - the cap is disclosed: a truncated read adds the "true count may be higher"
 *    limitation rather than presenting a floor as a total;
 *  - all eight locales carry identical message keys and labels, and the model
 *    acknowledgement sanitiser refuses digits, links, addresses and emails.
 */
class AdminAnalyticsTest extends TestCase
{
    private const NOW = '2026-10-14';

    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    private function parse(string $question, array $options = []): array
    {
        return AdminAnalytics::parse($question, array_merge(['now' => self::NOW], $options));
    }

    /* ------------------------------------------------------------------ */
    /* Intent: the advertised examples must work                           */
    /* ------------------------------------------------------------------ */

    public function test_every_advertised_example_maps_to_the_tool_it_advertises_in_every_locale(): void
    {
        $expected = [
            'example_sellers' => 'users_joined',
            'example_online' => 'presence_now',
            'example_certification' => 'certification_queue',
            'example_revenue' => 'revenue_trend',
            'example_activity' => 'activity_history',
            'example_totals' => 'platform_totals',
        ];

        foreach (self::LOCALES as $locale) {
            foreach ($expected as $key => $tool) {
                $question = AdminAnalytics::msg($key, $locale);
                $this->assertNotSame($key, $question, "Missing {$key} for {$locale}.");

                $parsed = $this->parse($question);
                $this->assertSame(
                    $tool,
                    $parsed['tool'],
                    "The advertised {$key} example must resolve to {$tool} in {$locale} (got ".($parsed['tool'] ?? 'null').').'
                );
            }
        }
    }

    public function test_a_question_that_matches_nothing_returns_no_tool_instead_of_guessing(): void
    {
        $parsed = $this->parse('What is the meaning of life?');

        $this->assertNull($parsed['tool']);
        $this->assertSame('low', $parsed['confidence']);
        $this->assertSame('none', $parsed['range_source']);
    }

    public function test_a_live_question_never_gains_a_period(): void
    {
        $parsed = $this->parse('How many users were online now last month?');

        $this->assertSame('presence_now', $parsed['tool']);
        $this->assertTrue($parsed['live']);
        $this->assertNull($parsed['range']);
        $this->assertNull($parsed['range_key']);
        $this->assertSame('none', $parsed['range_source']);
    }

    public function test_history_keeps_a_period_and_defaults_to_this_month(): void
    {
        $withRange = $this->parse('How many actions were recorded today?');
        $this->assertSame('activity_history', $withRange['tool']);
        $this->assertFalse($withRange['live']);
        $this->assertSame('today', $withRange['range_key']);
        $this->assertSame(['from' => self::NOW, 'to' => self::NOW], $withRange['range']);
        $this->assertSame('high', $withRange['confidence']);

        $noRange = $this->parse('How many actions were recorded?');
        $this->assertSame('activity_history', $noRange['tool']);
        $this->assertSame('default', $noRange['range_source']);
        $this->assertSame('this_month', $noRange['range_key']);
        $this->assertSame('2026-10-01', $noRange['range']['from']);
    }

    public function test_the_specific_tool_wins_over_the_generic_one(): void
    {
        /* "how many users" also matches platform_totals; presence is the correct
         * reading of "online now". */
        $this->assertSame('presence_now', $this->parse('How many users are online now?')['tool']);
        $this->assertSame('platform_totals', $this->parse('How many users does the platform have in total?')['tool']);
        $this->assertSame('certification_queue', $this->parse('How many businesses are awaiting certification?')['tool']);

        /* A role filter is captured as a filter, never as a person. */
        $parsed = $this->parse('How many sellers joined last month?');
        $this->assertSame('users_joined', $parsed['tool']);
        $this->assertSame('seller', $parsed['role_filter']);
        $this->assertSame('last_month', $parsed['range_key']);
    }

    public function test_a_swahili_synonym_is_understood(): void
    {
        $this->assertSame('activity_history', $this->parse('Ni matendo mangapi yalirekodiwa leo?')['tool']);
    }

    /* ------------------------------------------------------------------ */
    /* Ranges                                                              */
    /* ------------------------------------------------------------------ */

    public function test_every_named_range_is_an_exact_calendar_window(): void
    {
        $expected = [
            'today' => ['2026-10-14', '2026-10-14'],
            'yesterday' => ['2026-10-13', '2026-10-13'],
            'this_week' => ['2026-10-12', '2026-10-18'],
            'last_week' => ['2026-10-05', '2026-10-11'],
            'this_month' => ['2026-10-01', '2026-10-14'],
            'last_month' => ['2026-09-01', '2026-09-30'],
            'this_year' => ['2026-01-01', '2026-10-14'],
            'last_7_days' => ['2026-10-08', '2026-10-14'],
            'last_30_days' => ['2026-09-15', '2026-10-14'],
            'last_90_days' => ['2026-07-17', '2026-10-14'],
        ];

        foreach ($expected as $key => [$from, $to]) {
            $range = AdminAnalytics::resolveRange($key, self::NOW);
            $this->assertSame($from, $range['from'], "{$key} starts on the wrong day.");
            $this->assertSame($to, $range['to'], "{$key} ends on the wrong day.");
        }
    }

    public function test_an_explicit_day_count_is_honoured_and_clamped(): void
    {
        $parsed = $this->parse('How many actions were recorded in the last 14 days?');
        $this->assertSame('2026-10-01', $parsed['range']['from']);
        $this->assertSame(self::NOW, $parsed['range']['to']);

        $clamped = $this->parse('actions in the last 900 days?', ['max_range_days' => 30]);
        $this->assertSame(30, AdminAnalytics::rangeDays($clamped['range']));
    }

    public function test_a_range_is_clamped_to_the_documented_bound(): void
    {
        /* A 366-day window ending on the requested day - the oldest 6 years are
         * dropped, never the most recent rows. */
        $clamped = AdminAnalytics::clampRange(['from' => '2020-01-01', 'to' => '2026-10-14'], 366);
        $this->assertSame(['from' => '2025-10-14', 'to' => '2026-10-14'], $clamped);
        $this->assertSame(366, AdminAnalytics::rangeDays($clamped));

        $short = ['from' => '2026-10-01', 'to' => '2026-10-14'];
        $this->assertSame($short, AdminAnalytics::clampRange($short, 366));
    }

    public function test_the_previous_period_is_the_same_length_immediately_before(): void
    {
        $previous = AdminAnalytics::previousRange(['from' => '2026-10-01', 'to' => '2026-10-14']);

        $this->assertSame('2026-09-17', $previous['from']);
        $this->assertSame('2026-09-30', $previous['to']);

        $week = AdminAnalytics::previousRange(['from' => '2026-10-12', 'to' => '2026-10-18']);
        $this->assertSame('2026-10-05', $week['from']);
        $this->assertSame('2026-10-11', $week['to']);
    }

    /* ------------------------------------------------------------------ */
    /* Aggregation                                                         */
    /* ------------------------------------------------------------------ */

    public function test_users_joined_are_counted_by_role_and_status_inside_the_range_only(): void
    {
        $range = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $rows = [
            ['role' => 'seller', 'status' => 'approved', 'created_at' => '2026-09-10T08:00:00+00:00'],
            ['role' => 'seller', 'status' => 'pending', 'created_at' => '2026-09-30T23:59:59+00:00'],
            ['role' => 'customer', 'status' => 'approved', 'created_at' => '2026-09-01T00:00:00+00:00'],
            // Outside the window: must be ignored even though it was read.
            ['role' => 'seller', 'status' => 'approved', 'created_at' => '2026-08-31T23:59:59+00:00'],
            ['role' => 'admin', 'status' => 'approved', 'created_at' => '2026-10-01T00:00:00+00:00'],
        ];

        $counted = AdminAnalytics::countUsersJoined($rows, $range);

        $this->assertSame(3, $counted['total']);
        $this->assertSame(['seller' => 2, 'customer' => 1], $counted['by_role']);
        $this->assertSame(['approved' => 2, 'pending' => 1], $counted['by_status']);
    }

    public function test_recorded_activity_is_grouped_by_action_and_day_with_a_busiest_day(): void
    {
        $range = ['from' => '2026-10-13', 'to' => '2026-10-14'];
        $rows = [
            ['action' => 'ORDER_CREATE', 'created_at' => '2026-10-13T09:00:00+00:00'],
            ['action' => 'ORDER_CREATE', 'created_at' => '2026-10-14T09:00:00+00:00'],
            ['action' => 'LOGIN', 'created_at' => '2026-10-14T10:00:00+00:00'],
            ['action' => 'LOGIN', 'created_at' => '2026-10-14T11:00:00+00:00'],
            ['action' => 'OLD', 'created_at' => '2026-10-12T23:59:59+00:00'],
        ];

        $counted = AdminAnalytics::countActivity($rows, $range);

        $this->assertSame(4, $counted['total']);
        $this->assertSame(['ORDER_CREATE' => 2, 'LOGIN' => 2], $counted['by_action']);
        $this->assertSame(['2026-10-13' => 1, '2026-10-14' => 3], $counted['by_day']);
        $this->assertSame(['day' => '2026-10-14', 'count' => 3], $counted['busiest_day']);
    }

    public function test_certification_counts_pending_and_ranks_the_longest_waiting_first(): void
    {
        $rows = [
            ['business_name' => 'Certified Co', 'created_at' => '2026-09-01', 'is_certified' => true],
            ['business_name' => 'New Shop', 'created_at' => '2026-10-10', 'is_certified' => false],
            ['business_name' => 'Old Shop', 'created_at' => '2026-08-01', 'is_certified' => false],
            ['business_name' => 'Middle Shop', 'created_at' => '2026-09-20', 'is_certified' => false],
        ];

        $summary = AdminAnalytics::summarizeCertification($rows, self::NOW, 2);

        $this->assertSame(3, $summary['pending']);
        $this->assertSame(1, $summary['certified']);
        $this->assertSame('Old Shop', $summary['oldest']['business_name']);
        $this->assertSame(74, $summary['oldest']['days_waiting']);
        $this->assertCount(2, $summary['named']);
        $this->assertSame(['Old Shop', 'Middle Shop'], array_column($summary['named'], 'business_name'));
    }

    public function test_certification_never_names_more_than_the_cap(): void
    {
        $rows = [];
        for ($i = 0; $i < 40; $i++) {
            $rows[] = ['business_name' => 'Shop '.$i, 'created_at' => '2026-09-01', 'is_certified' => false];
        }

        $this->assertCount(
            AdminAnalytics::MAX_NAMED_BUSINESSES,
            AdminAnalytics::summarizeCertification($rows, self::NOW, 99)['named']
        );
    }

    public function test_revenue_is_summed_per_day_inside_the_range_with_a_best_day(): void
    {
        $range = ['from' => '2026-10-12', 'to' => '2026-10-18'];
        $rows = [
            ['sale_date' => '2026-10-13', 'total_amount' => '1000'],
            ['sale_date' => '2026-10-13', 'total_amount' => '500'],
            ['sale_date' => '2026-10-14', 'total_amount' => '300.50'],
            ['sale_date' => '2026-10-11', 'total_amount' => '9999'],
        ];

        $revenue = AdminAnalytics::summarizeRevenue($rows, $range);

        $this->assertSame(1800.50, $revenue['total']);
        $this->assertSame(['2026-10-13' => 1500.0, '2026-10-14' => 300.50], $revenue['by_day']);
        $this->assertSame(['day' => '2026-10-13', 'amount' => 1500.0], $revenue['best_day']);
        $this->assertSame(2, $revenue['days']);
    }

    /* ------------------------------------------------------------------ */
    /* Presentation, privacy and honesty                                   */
    /* ------------------------------------------------------------------ */

    /** @param array<string, mixed> $computed */
    private function present(string $tool, array $computed, string $locale, string $rangeKey = 'this_month'): array
    {
        $live = in_array($tool, AdminAnalytics::LIVE_TOOLS, true);
        $parsed = [
            'tool' => $tool,
            'range_key' => $live ? null : $rangeKey,
            'range' => $live ? null : AdminAnalytics::resolveRange($rangeKey, self::NOW),
            'range_source' => $live ? 'none' : 'keyword',
            'role_filter' => null,
            'live' => $live,
        ];

        return AdminAnalytics::present($tool, $parsed, $computed, $locale);
    }

    /** @return array<string, array<string, mixed>> */
    private function computedForEveryTool(): array
    {
        return [
            'users_joined' => ['total' => 3, 'by_role' => ['seller' => 2, 'customer' => 1], 'by_status' => ['approved' => 3], 'rows_read' => 3, 'truncated' => false],
            'presence_now' => ['online' => 5, 'by_role' => ['seller' => 5], 'window_minutes' => 5, 'rows_read' => 5, 'truncated' => false],
            'activity_history' => ['total' => 4, 'by_action' => ['LOGIN' => 4], 'by_day' => ['2026-10-14' => 4], 'busiest_day' => ['day' => '2026-10-14', 'count' => 4], 'rows_read' => 4, 'truncated' => false],
            'certification_queue' => ['pending' => 3, 'certified' => 1, 'oldest' => ['business_name' => 'Old Shop', 'created_at' => '2026-08-01', 'days_waiting' => 74], 'named' => [['business_name' => 'Old Shop', 'created_at' => '2026-08-01', 'days_waiting' => 74]], 'rows_read' => 1, 'truncated' => false],
            'platform_totals' => ['users' => 126, 'sellers' => 52, 'admins' => 67, 'products' => 2108, 'customers' => 46, 'sales' => 663, 'rows_read' => 0, 'truncated' => false],
            'revenue_trend' => ['total' => 258500.0, 'by_day' => ['2026-10-13' => 258500.0], 'best_day' => ['day' => '2026-10-13', 'amount' => 258500.0], 'days' => 1, 'rows_read' => 1, 'truncated' => false],
        ];
    }

    public function test_every_tool_presents_a_value_an_explanation_and_a_visual_in_every_locale(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach ($this->computedForEveryTool() as $tool => $computed) {
                $payload = $this->present($tool, $computed, $locale);

                $this->assertSame($tool, $payload['tool']);
                $this->assertNotSame('', (string) $payload['explanation'], "{$tool} ({$locale}) has no explanation.");
                $this->assertNotSame('', (string) $payload['value_label'], "{$tool} ({$locale}) has no value label.");
                $this->assertNotNull($payload['value'], "{$tool} ({$locale}) has no value.");
                $this->assertTrue(
                    is_array($payload['table']) || is_array($payload['chart']),
                    "{$tool} ({$locale}) has neither a table nor a chart."
                );
                $this->assertNotEmpty($payload['sources'], "{$tool} ({$locale}) names no source.");
                $this->assertNotEmpty($payload['limitations'], "{$tool} ({$locale}) hides its limitations.");
            }
        }
    }

    public function test_no_presentation_contains_a_personal_field_in_any_locale(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach ($this->computedForEveryTool() as $tool => $computed) {
                $payload = $this->present($tool, $computed, $locale);

                $this->assertSame(
                    [],
                    AdminAnalytics::findPersonalFields($payload),
                    "{$tool} ({$locale}) leaked a personal field: ".implode(',', AdminAnalytics::findPersonalFields($payload))
                );
            }
        }
    }

    public function test_a_personal_field_is_detected_nested_and_stripped(): void
    {
        $leaky = [
            'tool' => 'platform_totals',
            'rows' => [
                ['email' => 'gift@example.com', 'user_id' => 'u-1'],
                ['ok' => true],
            ],
            'phone' => '0614125882',
        ];

        $found = AdminAnalytics::findPersonalFields($leaky);
        $this->assertContains('rows.0.email', $found);
        $this->assertContains('rows.0.user_id', $found);
        $this->assertContains('phone', $found);

        $clean = AdminAnalytics::assertNoPersonalFields($leaky);
        $this->assertSame([], AdminAnalytics::findPersonalFields($clean));
        $this->assertSame(['ok' => true], $clean['rows'][1]);
    }

    public function test_a_truncated_read_is_disclosed_as_a_floor(): void
    {
        $computed = $this->computedForEveryTool()['activity_history'];
        $computed['rows_read'] = 20000;
        $computed['truncated'] = true;

        foreach (self::LOCALES as $locale) {
            $payload = $this->present('activity_history', $computed, $locale, 'last_90_days');
            $joined = implode(' | ', $payload['limitations']);

            $this->assertStringContainsString('20,000', $joined, "{$locale} does not name the cap that was hit.");
            $this->assertStringContainsString(
                AdminAnalytics::msg('lim_capped', $locale, ['rows' => '20,000']),
                $joined,
                "{$locale} does not disclose the cap."
            );
        }
    }

    public function test_presence_and_history_each_say_which_one_they_are(): void
    {
        foreach (self::LOCALES as $locale) {
            $presence = $this->present('presence_now', $this->computedForEveryTool()['presence_now'], $locale);
            $this->assertContains(AdminAnalytics::msg('lim_presence_is_live', $locale, ['minutes' => '5']), $presence['limitations']);
            $this->assertTrue($presence['live']);
            $this->assertSame(AdminAnalytics::msg('range_live', $locale), $presence['range_label']);

            $history = $this->present('activity_history', $this->computedForEveryTool()['activity_history'], $locale);
            $this->assertContains(AdminAnalytics::msg('lim_history_is_not_presence', $locale), $history['limitations']);
            $this->assertFalse($history['live']);
        }
    }

    public function test_an_answer_without_a_stated_period_says_which_period_was_used(): void
    {
        $parsed = [
            'tool' => 'users_joined',
            'range_key' => 'this_month',
            'range' => AdminAnalytics::resolveRange('this_month', self::NOW),
            'range_source' => 'default',
            'role_filter' => null,
            'live' => false,
        ];

        $payload = AdminAnalytics::present('users_joined', $parsed, $this->computedForEveryTool()['users_joined'], 'en');

        $this->assertContains(AdminAnalytics::msg('lim_no_range', 'en'), $payload['limitations']);
    }

    public function test_an_unknown_tool_presents_an_honest_no_match(): void
    {
        $payload = $this->present('not_a_tool', [], 'en');

        $this->assertSame(AdminAnalytics::msg('notice_no_match', 'en'), $payload['explanation']);
        $this->assertContains(AdminAnalytics::msg('notice_no_match', 'en'), $payload['limitations']);
    }

    public function test_the_acknowledgement_sanitiser_refuses_digits_links_and_addresses(): void
    {
        $this->assertNull(AdminAnalytics::sanitizeAckString('Here are 12 results'));
        $this->assertNull(AdminAnalytics::sanitizeAckString('See https://example.com'));
        $this->assertNull(AdminAnalytics::sanitizeAckString('Mail gift@example.com'));
        $this->assertNull(AdminAnalytics::sanitizeAckString('Is this the answer?'));
        $this->assertNull(AdminAnalytics::sanitizeAckString('  '));
        $this->assertNull(AdminAnalytics::sanitizeAckString(str_repeat('long ', 60)));

        $this->assertSame(
            'These figures come from recorded platform activity.',
            AdminAnalytics::sanitizeAckString('  These   figures come from recorded platform activity.  ')
        );
    }

    /* ------------------------------------------------------------------ */
    /* Locale + allow-list integrity                                       */
    /* ------------------------------------------------------------------ */

    public function test_all_eight_locales_carry_identical_message_keys(): void
    {
        $locales = array_keys(AdminAnalytics::MESSAGES);
        sort($locales);
        $expected = self::LOCALES;
        sort($expected);

        $this->assertSame($expected, $locales, 'The message table must cover exactly the 8 supported locales.');
        $this->assertTrue(AdminAnalytics::messagesComplete());
    }

    public function test_every_tool_and_range_is_labelled_in_every_locale(): void
    {
        $this->assertTrue(AdminAnalytics::labelsComplete());

        foreach (self::LOCALES as $locale) {
            foreach (AdminAnalytics::supportedExamples($locale) as $example) {
                $this->assertNotSame('', $example);
                $this->assertNotSame('example_sellers', $example);
            }
        }
    }

    public function test_the_allow_list_is_small_read_only_and_has_no_write_tool(): void
    {
        $this->assertCount(6, AdminAnalytics::TOOLS);
        $this->assertSame(
            ['users_joined', 'presence_now', 'activity_history', 'certification_queue', 'platform_totals', 'revenue_trend'],
            AdminAnalytics::TOOLS
        );

        foreach (AdminAnalytics::TOOLS as $tool) {
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $tool);
        }
    }

    public function test_a_question_can_never_produce_a_sql_fragment_or_a_tenant_scope(): void
    {
        $hostile = "How many actions were recorded today?'; drop table user_logs; select * from businesses where id = 'x' --";

        $parsed = $this->parse($hostile);

        $this->assertContains($parsed['tool'], AdminAnalytics::TOOLS, 'A question must only ever select an allow-listed tool.');
        $this->assertArrayNotHasKey('sql', $parsed);
        $this->assertArrayNotHasKey('business_id', $parsed);

        $payload = $this->present($parsed['tool'], $this->computedForEveryTool()[$parsed['tool']], 'en');
        $this->assertSame([], AdminAnalytics::findPersonalFields($payload));
    }
}
