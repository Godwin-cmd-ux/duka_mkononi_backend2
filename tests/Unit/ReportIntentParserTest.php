<?php

namespace Tests\Unit;

use App\Services\Ai\ReportIntentParser;
use PHPUnit\Framework\TestCase;

/**
 * Capability #4: a question must map to an allow-listed tool and a trustworthy
 * date range, deterministically. Pure-function tests, no HTTP/database.
 */
class ReportIntentParserTest extends TestCase
{
    private const NOW = '2026-10-15';

    private function parse(string $question): array
    {
        return ReportIntentParser::parse($question, ['now' => self::NOW]);
    }

    public function test_swahili_sales_question_maps_to_revenue_summary_with_units(): void
    {
        $out = $this->parse('Niliuza mangapi wiki hii?');

        $this->assertSame('revenue_summary', $out['tool']);
        $this->assertSame('units', $out['metric']);
        $this->assertSame('this_week', $out['range_key']);
        $this->assertSame('high', $out['confidence']);
        $this->assertSame(['from' => '2026-10-12', 'to' => '2026-10-15'], $out['range']);
    }

    public function test_english_top_products_question_maps_to_top_products_by_profit(): void
    {
        $out = $this->parse('Which products generated the highest profit this month?');

        $this->assertSame('top_products', $out['tool']);
        $this->assertSame('profit', $out['metric']);
        $this->assertSame('this_month', $out['range_key']);
        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-15'], $out['range']);
    }

    public function test_comparison_question_is_flagged_to_compare(): void
    {
        $out = $this->parse('Compare sales this week with last week.');

        $this->assertSame('sales_comparison', $out['tool']);
        $this->assertTrue($out['compare']);
        $this->assertSame('this_week', $out['range_key']);
    }

    public function test_expense_question_detects_the_category_and_defaults_the_period(): void
    {
        $out = $this->parse('How much did I spend on transport?');

        $this->assertSame('expenses', $out['tool']);
        $this->assertSame('usafiri', $out['category_filter']);
        $this->assertSame('this_month', $out['range_key']);
        $this->assertSame('default', $out['range_source']);
        $this->assertSame('medium', $out['confidence']);
    }

    public function test_customer_question_maps_to_customers(): void
    {
        $out = $this->parse('How many customers do I have?');

        $this->assertSame('customers', $out['tool']);
        $this->assertSame('units', $out['metric']);
    }

    public function test_stock_question_maps_to_stock_value(): void
    {
        $out = $this->parse('Thamani ya bidhaa zangu zilizopo ni kiasi gani?');

        $this->assertSame('stock_value', $out['tool']);
        $this->assertNull($out['range']);
    }

    public function test_an_unrecognised_question_returns_no_tool(): void
    {
        $out = $this->parse('Habari yako leo?');

        $this->assertNull($out['tool']);
        $this->assertSame('low', $out['confidence']);
    }

    public function test_top_n_limit_is_parsed(): void
    {
        $out = $this->parse('Top 3 products by profit this month');

        $this->assertSame('top_products', $out['tool']);
        $this->assertSame('profit', $out['metric']);
        $this->assertSame(3, $out['limit']);
    }

    public function test_dynamic_last_n_days_is_supported(): void
    {
        $out = $this->parse('Niliuza mangapi siku 14 zilizopita?');

        $this->assertSame('revenue_summary', $out['tool']);
        $this->assertSame('last_n_days', $out['range_key']);
        $this->assertSame(['from' => '2026-10-02', 'to' => '2026-10-15'], $out['range']);
    }

    public function test_resolve_range_for_each_supported_key(): void
    {
        $this->assertSame(['from' => '2026-10-15', 'to' => '2026-10-15'], ReportIntentParser::resolveRange('today', self::NOW));
        $this->assertSame(['from' => '2026-10-14', 'to' => '2026-10-14'], ReportIntentParser::resolveRange('yesterday', self::NOW));
        $this->assertSame(['from' => '2026-10-05', 'to' => '2026-10-11'], ReportIntentParser::resolveRange('last_week', self::NOW));
        $this->assertSame(['from' => '2026-09-01', 'to' => '2026-09-30'], ReportIntentParser::resolveRange('last_month', self::NOW));
        $this->assertSame(['from' => '2026-01-01', 'to' => '2026-10-15'], ReportIntentParser::resolveRange('this_year', self::NOW));
        $this->assertSame(['from' => '2026-10-09', 'to' => '2026-10-15'], ReportIntentParser::resolveRange('last_7_days', self::NOW));
    }

    public function test_previous_range_is_the_equal_length_period_before(): void
    {
        $range = ['from' => '2026-10-01', 'to' => '2026-10-15'];
        $prev = ReportIntentParser::previousRange($range);

        $this->assertSame(['from' => '2026-09-16', 'to' => '2026-09-30'], $prev);
    }
}
