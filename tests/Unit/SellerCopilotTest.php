<?php

namespace Tests\Unit;

use App\Services\Ai\AdCopyService;
use App\Services\Ai\ReportIntentParser;
use App\Services\Ai\SellerCopilot;
use Tests\TestCase;

/**
 * Capability #10 grounding and routing contract. The copilot only ever runs
 * allow-listed read-only tools, so these tests pin down:
 *  - deterministic routing (including ordering rules that keep expense
 *    statements, report questions and restock questions from stealing each
 *    other's messages),
 *  - bounded conversation context,
 *  - the money-scale grounding gate over narration,
 *  - complete eight-locale message coverage,
 *  - the two tools that need no database (help and the expense PREVIEW,
 *    which must never claim to have saved anything).
 */
class SellerCopilotTest extends TestCase
{
    private const LANGS = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /* ------------------------------------------------------------------ */
    /* Routing                                                             */
    /* ------------------------------------------------------------------ */

    public function test_help_keywords_route_to_help_in_every_language(): void
    {
        $questions = [
            'sw' => 'Habari yako',
            'en' => 'What can you do?',
            'fr' => 'Bonjour',
            'hi' => 'नमस्ते',
            'es' => 'Hola, ¿qué puedes hacer?',
            'ur' => 'السلام علیکم',
            'de' => 'Hallo, was kannst du?',
            'zh' => '你好',
        ];

        foreach ($questions as $question) {
            $routed = SellerCopilot::route($question, []);
            $this->assertSame('help', $routed['tool'], 'Help routing failed for: '.$question);
        }
    }

    public function test_domain_questions_route_to_their_tools(): void
    {
        $cases = [
            ['Ni lini stock itaisha?', 'restock'],
            ['When will I run out of stock?', 'restock'],
            ['Afya ya biashara yangu ikoje?', 'health'],
            ['Kuna dalili za kutokwa kwenye faida?', 'anomalies'],
            ['Niliuza mangapi wiki hii?', 'report'],
            ['Bidhaa zipi zilizalisha faida kubwa mwezi huu?', 'report'],
            ['Nina wateja wangapi?', 'report'],
        ];

        foreach ($cases as [$question, $expected]) {
            $routed = SellerCopilot::route($question, []);
            $this->assertSame($expected, $routed['tool'], 'Routing failed for: '.$question);
        }
    }

    public function test_restock_wins_over_help_when_both_words_appear(): void
    {
        // Ordering rule: the domain tools are checked before greetings, so
        // "help me restock" must reach the planner, not the help screen.
        $routed = SellerCopilot::route('help me restock', []);
        $this->assertSame('restock', $routed['tool']);
    }

    public function test_expense_statements_become_drafts_but_questions_stay_reports(): void
    {
        $statement = SellerCopilot::route('Nilitumia 5000 kwenye usafiri.', []);
        $this->assertSame('expense_parse', $statement['tool']);
        $this->assertStringContainsString('5000', $statement['args']['phrase']);

        // Same words, but a question: this is a report about expenses and
        // must never be swallowed by the expense drafter.
        $question = SellerCopilot::route('Nilitumia kiasi gani kwenye usafiri?', []);
        $this->assertSame('report', $question['tool']);
        $this->assertSame('expenses', $question['args']['report']['tool']);
    }

    public function test_report_question_carries_the_parsed_range(): void
    {
        $routed = SellerCopilot::route('Niliuza mangapi wiki hii?', []);
        $this->assertSame('report', $routed['tool']);
        $this->assertSame('revenue_summary', $routed['args']['report']['tool']);
        $this->assertNotNull($routed['args']['report']['range']);
        $this->assertSame('wiki hii', $routed['args']['report']['matched'][0] ?? null);
    }

    public function test_period_only_followup_inherits_the_previous_report_tool(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'Niliuza mangapi wiki hii?'],
            ['role' => 'assistant', 'content' => 'Jibu.', 'tool' => 'report', 'sub' => 'revenue_summary'],
        ];

        $routed = SellerCopilot::route('kwa jana?', $history);
        $this->assertSame('report', $routed['tool']);
        $this->assertSame('history', $routed['source']);
        $this->assertSame('revenue_summary', $routed['args']['report']['tool']);
        $this->assertSame('yesterday', $routed['args']['report']['range_key']);
    }

    public function test_refresh_request_repeats_the_previous_tool(): void
    {
        $history = [
            ['role' => 'assistant', 'content' => 'Jibu.', 'tool' => 'restock'],
        ];

        $routed = SellerCopilot::route('onyesha tena', $history);
        $this->assertSame('restock', $routed['tool']);
        $this->assertSame('history', $routed['source']);
    }

    public function test_unrecognisable_message_routes_to_nothing(): void
    {
        $routed = SellerCopilot::route('asdlkjqwe zzxxcv', []);
        $this->assertSame('', $routed['tool']);
        $this->assertSame('none', $routed['source']);
    }

    public function test_keyword_matching_is_word_safe(): void
    {
        // "hi" must never match inside "this"/"thing".
        $this->assertFalse(SellerCopilot::matchAny(SellerCopilot::normalise('this thing'), ['hi']));
        $this->assertTrue(SellerCopilot::matchAny(SellerCopilot::normalise('hi there'), ['hi']));
        // Punctuation is stripped before matching.
        $this->assertSame('niliuza 5 000', SellerCopilot::normalise('Niliuza 5,000!'));
    }

    /* ------------------------------------------------------------------ */
    /* Bounded context                                                     */
    /* ------------------------------------------------------------------ */

    public function test_trim_messages_keeps_the_newest_entries(): void
    {
        $messages = [];
        for ($i = 0; $i < 50; $i++) {
            $messages[] = ['role' => 'user', 'content' => 'm'.$i];
        }

        $trimmed = SellerCopilot::trimMessages($messages, 40);
        $this->assertCount(40, $trimmed);
        $this->assertSame('m10', $trimmed[0]['content']);
        // 40 entries kept (m10..m49) -> the newest is at index 39, not 49.
        $this->assertSame('m49', $trimmed[39]['content']);
    }

    public function test_append_messages_appends_and_bounds(): void
    {
        $existing = [];
        for ($i = 0; $i < 39; $i++) {
            $existing[] = ['role' => 'user', 'content' => 'm'.$i];
        }

        $all = SellerCopilot::appendMessages(
            $existing,
            ['role' => 'user', 'content' => 'q'],
            ['role' => 'assistant', 'content' => 'a'],
            40,
        );

        $this->assertCount(40, $all);
        $this->assertSame('q', $all[38]['content']);
        $this->assertSame('a', $all[39]['content']);
    }

    public function test_history_snippet_is_bounded_and_clipped(): void
    {
        $history = [];
        for ($i = 0; $i < 10; $i++) {
            $history[] = ['role' => $i % 2 === 0 ? 'user' : 'assistant', 'content' => str_repeat('x', 500).$i];
        }

        $snippet = SellerCopilot::historySnippet($history, 3);
        $this->assertCount(6, $snippet);
        $this->assertLessThanOrEqual(240, mb_strlen($snippet[5]['content']));
        $this->assertSame([], SellerCopilot::historySnippet($history, 0));
    }

    public function test_last_tool_reads_the_latest_assistant_entry(): void
    {
        $this->assertNull(SellerCopilot::lastTool([]));

        $history = [
            ['role' => 'user', 'content' => 'swali'],
            ['role' => 'assistant', 'content' => 'jibu', 'tool' => 'health'],
            ['role' => 'user', 'content' => 'linganisha'],
        ];
        $last = SellerCopilot::lastTool($history);
        $this->assertSame('health', $last['tool']);

        // A stored tool outside the allow-list is never trusted.
        $poisoned = [['role' => 'assistant', 'content' => 'x', 'tool' => 'drop_table']];
        $this->assertNull(SellerCopilot::lastTool($poisoned));
    }

    /* ------------------------------------------------------------------ */
    /* Grounding                                                           */
    /* ------------------------------------------------------------------ */

    private function sampleResult(): array
    {
        return [
            'tool' => 'report',
            'period' => ['from' => '2026-10-01', 'to' => '2026-10-12'],
            'data' => ['revenue' => 5000.0, 'sales_count' => 7],
            'highlights' => [],
            'table' => null,
            'summary_text' => 'Mauzo ni TZS 5000 kwa faida 12.5%.',
        ];
    }

    public function test_facts_extract_numbers_and_percentages(): void
    {
        [$numbers, $percents] = SellerCopilot::factsFrom($this->sampleResult());

        $this->assertContains(5000.0, $numbers);
        $this->assertContains(12.5, $percents);
        // Period parts are allowed too - quoting the period is never a fabrication.
        $this->assertContains(2026.0, $numbers);
    }

    public function test_grounding_rejects_fabricated_money_scale_numbers(): void
    {
        [$numbers, $percents] = SellerCopilot::factsFrom($this->sampleResult());

        // Quoting the real figure (with or without thousands separators) passes.
        $this->assertFalse(AdCopyService::hasUnfactedNumber('Bei ni TZS 5000.', $numbers, $percents));
        $this->assertFalse(AdCopyService::hasUnfactedNumber('Bei ni TZS 5,000.', $numbers, $percents));
        // A fabricated money-scale number fails the gate.
        $this->assertTrue(AdCopyService::hasUnfactedNumber('Bei ni TZS 99999.', $numbers, $percents));
        // Percentages are validated against the payload's own percentages.
        $this->assertFalse(AdCopyService::hasUnfactedNumber('Faida ni 12.5%.', $numbers, $percents));
        $this->assertTrue(AdCopyService::hasUnfactedNumber('Faida ni 77%.', $numbers, $percents));
        // Small counts below 100 are exempt (same rule as capabilities #8/#9).
        $this->assertFalse(AdCopyService::hasUnfactedNumber('Bidhaa 42 zimebaki.', $numbers, $percents));
    }

    public function test_clean_text_strips_markup_and_control_characters(): void
    {
        $cleaned = SellerCopilot::cleanText("<b>Habari</b>\x00  \n  karibu");
        $this->assertSame('Habari karibu', $cleaned);
    }

    /* ------------------------------------------------------------------ */
    /* Localization                                                        */
    /* ------------------------------------------------------------------ */

    public function test_every_message_exists_in_all_eight_locales(): void
    {
        $this->assertTrue(SellerCopilot::messagesComplete(), 'A copilot message is missing from a locale.');
    }

    public function test_messages_resolve_and_interpolate_per_locale(): void
    {
        $this->assertSame('Report', SellerCopilot::msg('tool_report', 'en'));
        $this->assertSame('Rapport', SellerCopilot::msg('tool_report', 'fr'));
        $this->assertSame('Bericht', SellerCopilot::msg('tool_report', 'de'));

        $line = SellerCopilot::msg('fallback_restock', 'sw', [
            'products' => 3,
            'units' => 12,
            'out' => 1,
        ]);
        $this->assertStringContainsString('3', $line);
        $this->assertStringNotContainsString('{', $line);

        // Unknown keys fall back instead of returning null.
        $this->assertSame('nope_key', SellerCopilot::msg('nope_key', 'zh'));
    }

    public function test_quick_actions_are_localized_and_point_at_existing_screens(): void
    {
        foreach (SellerCopilot::TOOLS as $tool) {
            $actions = SellerCopilot::quickActions($tool, 'sw');
            if ($tool === 'anomalies') {
                $this->assertSame([], $actions, 'Anomaly review happens in chat; no cross-role link.');
                continue;
            }

            $this->assertNotEmpty($actions, 'No quick action for '.$tool);
            foreach ($actions as $action) {
                $this->assertStringStartsWith('/muuzaji/', $action['href']);
                $this->assertNotSame('', trim($action['label']));
            }
        }

        // Labels are server-localized: every locale returns real text.
        foreach (self::LANGS as $locale) {
            foreach (SellerCopilot::quickActions('report', $locale) as $action) {
                $this->assertNotSame('', trim($action['label']), 'Empty label for '.$locale);
                $this->assertStringNotContainsString('qa_', $action['label']);
            }
        }
    }

    public function test_tool_labels_are_localized(): void
    {
        $this->assertSame('Kuagiza stock', SellerCopilot::toolLabel('restock', 'sw'));
        $this->assertSame('Restocking', SellerCopilot::toolLabel('restock', 'en'));
    }

    /* ------------------------------------------------------------------ */
    /* Database-free tools                                                 */
    /* ------------------------------------------------------------------ */

    public function test_help_tool_returns_the_capability_overview(): void
    {
        $result = SellerCopilot::runTool('help', [], ['locale' => 'sw']);

        $this->assertTrue($result['ok']);
        $this->assertSame(SellerCopilot::TOOLS, $result['data']['capabilities']);
        $this->assertStringContainsString('mauzo', mb_strtolower($result['summary_text']));
        $this->assertSame([], $result['sources']);
    }

    public function test_expense_tool_only_drafts_and_never_saves(): void
    {
        $result = SellerCopilot::runTool('expense_parse', [
            'phrase' => 'Nauli ya usafiri 5000.',
        ], [
            'locale' => 'sw',
            'currency' => 'TZS',
            'member_ids' => [],
            'today' => '2026-10-12',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(5000.0, $result['data']['preview']['amount']);
        // The draft explicitly reports itself as NOT saved.
        $this->assertFalse($result['data']['saved']);
        $this->assertStringContainsString('5,000', $result['summary_text']);
        $this->assertStringContainsString('hifadhiwa', $result['summary_text']);
    }

    public function test_expense_tool_without_a_phrase_degrades_honestly(): void
    {
        $result = SellerCopilot::runTool('expense_parse', ['phrase' => '   '], ['locale' => 'en']);

        $this->assertFalse($result['ok']);
        $this->assertSame(SellerCopilot::notUnderstood('en'), $result['summary_text']);
        $this->assertNotSame('', $result['summary_text']);
    }

    public function test_uuid_helper_only_accepts_real_uuids(): void
    {
        $this->assertTrue(SellerCopilot::isUuid('123e4567-e89b-12d3-a456-426614174000'));
        $this->assertFalse(SellerCopilot::isUuid('not-a-uuid'));
        $this->assertFalse(SellerCopilot::isUuid(null));
        $this->assertFalse(SellerCopilot::isUuid("123e4567-e89b-12d3-a456-426614174000' OR 1=1"));
    }

    public function test_report_tool_allow_list_matches_the_report_parser(): void
    {
        // The copilot's report tool is the same allow-list capability #4 uses.
        $this->assertSame(['report', 'restock', 'health', 'anomalies', 'expense_parse', 'help'], SellerCopilot::TOOLS);
        $this->assertContains('revenue_summary', ReportIntentParser::TOOLS);
        $this->assertContains('expenses', ReportIntentParser::TOOLS);
    }
}
