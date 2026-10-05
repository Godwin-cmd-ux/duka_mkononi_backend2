<?php

namespace Tests\Unit;

use App\Services\Ai\BusinessHealthScore;
use App\Services\Ai\HealthDigestService;
use PHPUnit\Framework\TestCase;

/**
 * Capability #5 - the health score must be explainable, weighted, honest about
 * missing data and never label a business on insufficient evidence.
 */
class BusinessHealthScoreTest extends TestCase
{
    private function config(): array
    {
        return [
            'weights' => [
                'margin' => 0.30,
                'stock_cover' => 0.25,
                'sales_trend' => 0.25,
                'expense_ratio' => 0.20,
            ],
            'thresholds' => [
                'margin' => [[0, 0], [3, 30], [8, 55], [15, 75], [25, 90], [40, 100]],
                'expense_ratio' => [[0, 100], [10, 90], [20, 75], [35, 55], [50, 30], [80, 0]],
                'sales_trend' => [[-40, 0], [-15, 30], [-5, 50], [0, 60], [10, 80], [25, 95], [50, 100]],
            ],
            'bands' => ['strong' => 80, 'steady' => 60, 'watch' => 40],
            'target_cover_days' => 14,
            'min_coverage' => 0.6,
            'min_components' => 2,
            'max_actions' => 3,
            'meaningful_change_pct' => 5.0,
        ];
    }

    private function strongCurrent(array $overrides = []): array
    {
        return array_merge([
            'revenue' => 1000000.0,
            'cogs' => 600000.0,
            'gross_profit' => 400000.0,
            'margin_pct' => 40.0,
            'sales_count' => 50,
            'units' => 100,
            'unknown_cogs_items' => 0,
            'expenses_total' => 50000.0,
            'products_with_demand' => 10,
            'out_of_stock_count' => 0,
            'overstock_count' => 0,
            'average_days_cover' => 14.0,
        ], $overrides);
    }

    private function strongPrevious(array $overrides = []): array
    {
        return array_merge([
            'revenue' => 900000.0,
            'gross_profit' => 350000.0,
            'margin_pct' => 38.9,
            'expenses_total' => 45000.0,
            'out_of_stock_count' => 0,
        ], $overrides);
    }

    public function test_all_components_present_produces_a_weighted_score_and_band(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(),
            'previous' => $this->strongPrevious(),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertTrue($result['sufficient']);
        $this->assertNotNull($result['score']);
        $this->assertGreaterThanOrEqual(80, $result['score']);
        $this->assertSame('strong', $result['band']);
        $this->assertCount(4, $result['components']);
        $this->assertSame([], $result['missing_components']);
        $this->assertSame(1.0, $result['coverage']);
    }

    public function test_no_evidence_produces_no_score(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => [
                'revenue' => 0,
                'margin_pct' => null,
                'expenses_total' => 0,
                'products_with_demand' => 0,
            ],
            'previous' => ['revenue' => 0],
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertFalse($result['sufficient']);
        $this->assertNull($result['score']);
        $this->assertSame('insufficient_evidence', $result['band']);
        $this->assertSame([], $result['actions']);
        $this->assertCount(4, $result['missing_components']);
    }

    public function test_coverage_below_minimum_refuses_a_score(): void
    {
        // Only margin is present (revenue > 0, no products, no previous period).
        $result = BusinessHealthScore::compute([
            'current' => [
                'revenue' => 500000.0,
                'gross_profit' => 200000.0,
                'margin_pct' => 40.0,
                'expenses_total' => 0,
                'products_with_demand' => 0,
            ],
            'previous' => ['revenue' => 0],
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertFalse($result['sufficient']);
        $this->assertNull($result['score']);
        $this->assertLessThan(0.6, $result['coverage']);
    }

    public function test_margin_missing_when_no_revenue(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(['revenue' => 0, 'margin_pct' => null]),
            'previous' => $this->strongPrevious(),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertContains('margin', $result['missing_components']);
    }

    public function test_sales_trend_missing_without_a_previous_period(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(),
            'previous' => ['revenue' => 0],
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertContains('sales_trend', $result['missing_components']);
    }

    public function test_weights_are_renormalised_over_present_components(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(['average_days_cover' => null, 'products_with_demand' => 0]),
            'previous' => $this->strongPrevious(),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertTrue($result['sufficient']);
        // stock_cover (0.25) is missing, so coverage is 0.75.
        $this->assertSame(0.75, $result['coverage']);
        $this->assertNotNull($result['score']);
    }

    public function test_out_of_stock_products_reduce_stock_cover_score(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(['out_of_stock_count' => 6]),
            'previous' => $this->strongPrevious(),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $cover = $this->component($result, 'stock_cover');
        $this->assertLessThan(100, $cover['score']);
    }

    public function test_overstock_reduces_stock_cover_score(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(['overstock_count' => 4]),
            'previous' => $this->strongPrevious(),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertLessThan(100, $this->component($result, 'stock_cover')['score']);
    }

    public function test_declining_sales_score_lower_than_growing_sales(): void
    {
        $declining = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(),
            'previous' => $this->strongPrevious(['revenue' => 1400000.0]),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $growing = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(),
            'previous' => $this->strongPrevious(['revenue' => 700000.0]),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertLessThan(
            $this->component($growing, 'sales_trend')['score'],
            $this->component($declining, 'sales_trend')['score'],
        );
    }

    public function test_meaningful_changes_are_reported_with_figures(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(),
            'previous' => $this->strongPrevious(),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $revenue = $this->change($result, 'revenue');
        $this->assertNotNull($revenue);
        $this->assertSame('up', $revenue['direction']);
        $this->assertStringContainsString('Sales', $revenue['text']);
    }

    public function test_small_changes_are_not_reported(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(['revenue' => 1005000.0]),
            'previous' => $this->strongPrevious(['revenue' => 1000000.0]),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertNull($this->change($result, 'revenue'));
    }

    public function test_out_of_stock_produces_a_restock_action(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(['out_of_stock_count' => 3]),
            'previous' => $this->strongPrevious(),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $keys = array_column($result['actions'], 'key');
        $this->assertContains('restock', $keys);
        $this->assertLessThanOrEqual(3, count($result['actions']));
    }

    public function test_actions_are_empty_when_evidence_is_insufficient(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => ['revenue' => 0],
            'previous' => ['revenue' => 0],
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertSame([], $result['actions']);
    }

    public function test_every_action_has_a_title_and_a_body(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent([
                'margin_pct' => 2.0,
                'expenses_total' => 400000.0,
                'out_of_stock_count' => 5,
                'overstock_count' => 3,
            ]),
            'previous' => $this->strongPrevious(['revenue' => 1200000.0]),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertNotEmpty($result['actions']);
        foreach ($result['actions'] as $action) {
            $this->assertNotSame('', $action['title']);
            $this->assertNotSame('', $action['body']);
        }
    }

    public function test_summary_text_is_in_the_requested_language(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(),
            'previous' => $this->strongPrevious(),
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertStringContainsString('Strong', $result['summary_text']);

        $sw = BusinessHealthScore::compute([
            'current' => $this->strongCurrent(),
            'previous' => $this->strongPrevious(),
        ], ['locale' => 'sw', 'currency' => 'TZS', 'config' => $this->config()]);

        $this->assertStringContainsString('Nzuri', $sw['summary_text']);
    }

    public function test_limitations_name_missing_components(): void
    {
        $result = BusinessHealthScore::compute([
            'current' => ['revenue' => 0],
            'previous' => ['revenue' => 0],
        ], ['locale' => 'en', 'currency' => 'TZS', 'config' => $this->config()]);

        $limitations = BusinessHealthScore::limitations($result, 'en');
        $this->assertNotEmpty($limitations);
    }

    public function test_sanitizer_bounds_model_text_and_ignores_missing_data(): void
    {
        $clean = BusinessHealthScore::sanitizeAiResponse([
            'headline' => str_repeat('a', 500),
            'interpretation' => str_repeat('b', 900),
        ]);

        $this->assertLessThanOrEqual(160, mb_strlen($clean['headline']));
        $this->assertLessThanOrEqual(400, mb_strlen($clean['interpretation']));

        $empty = BusinessHealthScore::sanitizeAiResponse(null);
        $this->assertFalse($empty['available']);
        $this->assertTrue($empty['degraded']);
    }

    public function test_week_period_is_the_previous_completed_monday_to_sunday(): void
    {
        // Thursday 2026-10-01 -> the completed week is Mon 2026-09-21 .. Sun 2026-09-27.
        $period = HealthDigestService::periodForWeek('2026-10-01');

        $this->assertSame('2026-09-21', $period['start']);
        $this->assertSame('2026-09-27', $period['end']);
        $this->assertSame(7, $period['days']);
    }

    public function test_previous_period_is_the_equal_length_window_before(): void
    {
        $prev = HealthDigestService::previousPeriod('2026-09-21', '2026-09-27');

        $this->assertSame('2026-09-14', $prev['start']);
        $this->assertSame('2026-09-20', $prev['end']);
        $this->assertSame(7, $prev['days']);
    }

    /* ------------------------------------------------------------------ */

    private function component(array $result, string $key): array
    {
        foreach ($result['components'] as $component) {
            if ($component['key'] === $key) {
                return $component;
            }
        }

        $this->fail("Component {$key} not found");
    }

    private function change(array $result, string $key): ?array
    {
        foreach ($result['changes'] as $change) {
            if ($change['key'] === $key) {
                return $change;
            }
        }

        return null;
    }
}
