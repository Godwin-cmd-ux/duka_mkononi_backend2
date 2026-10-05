<?php

namespace Tests\Unit;

use App\Services\Ai\PricingAdvisor;
use PHPUnit\Framework\TestCase;

/**
 * Capability #2: the money math must be correct, live in trusted code, and the
 * AI narrative must never be able to smuggle in an unknown product id, a made
 * up severity, or an unbounded string. These are pure-function tests: no
 * database, no HTTP, no Supabase.
 */
class PricingAdvisorTest extends TestCase
{
    private function product(array $overrides = []): array
    {
        return array_merge([
            'id' => 'p1',
            'name' => 'Sukari 1kg',
            'category' => 'Vyakula',
            'stock' => 10,
            'price' => 1000,
            'expected_selling_price' => 1500,
        ], $overrides);
    }

    public function test_a_healthy_product_is_flagged_healthy_with_no_suggested_price(): void
    {
        $out = PricingAdvisor::analyzeProducts([$this->product()], [], ['round_to' => 1]);

        $product = $out['products'][0];
        $this->assertSame('HEALTHY', $product['status']);
        $this->assertSame(500.0, $product['margin']);
        $this->assertSame(50.0, $product['margin_pct']);
        $this->assertNull($product['suggested_price']);
        $this->assertSame(0, $out['summary']['at_risk_count']);
    }

    public function test_selling_below_cost_is_a_loss_and_gets_a_suggested_price(): void
    {
        $out = PricingAdvisor::analyzeProducts(
            [$this->product(['price' => 1000, 'expected_selling_price' => 900])],
            [],
            ['round_to' => 1, 'target_margin_pct' => 20, 'thin_margin_pct' => 10]
        );

        $product = $out['products'][0];
        $this->assertSame('LOSS', $product['status']);
        $this->assertSame(-100.0, $product['margin']);
        $this->assertSame(1200.0, $product['suggested_price']);
        $this->assertSame(1100.0, $product['floor_price']);
        $this->assertSame(200.0, $product['expected_margin_at_suggested']);
        $this->assertSame(1, $out['summary']['counts']['LOSS']);
    }

    public function test_equal_buying_and_selling_prices_are_zero_margin(): void
    {
        $out = PricingAdvisor::analyzeProducts(
            [$this->product(['price' => 1000, 'expected_selling_price' => 1000])],
            [],
            ['round_to' => 1]
        );

        $this->assertSame('ZERO_MARGIN', $out['products'][0]['status']);
        $this->assertSame(0.0, $out['products'][0]['margin']);
    }

    public function test_margin_below_the_thin_threshold_is_thin(): void
    {
        $out = PricingAdvisor::analyzeProducts(
            [$this->product(['price' => 1000, 'expected_selling_price' => 1050])],
            [],
            ['round_to' => 1, 'thin_margin_pct' => 10]
        );

        $this->assertSame('THIN', $out['products'][0]['status']);
        $this->assertSame(5.0, $out['products'][0]['margin_pct']);
    }

    public function test_missing_cost_cannot_produce_a_margin_or_suggested_price(): void
    {
        $out = PricingAdvisor::analyzeProducts(
            [$this->product(['price' => null])],
            [],
            ['round_to' => 1]
        );

        $product = $out['products'][0];
        $this->assertSame('MISSING_COST', $product['status']);
        $this->assertNull($product['margin']);
        $this->assertNull($product['suggested_price']);
        $this->assertSame(1, $out['summary']['missing_cost_count']);
    }

    public function test_missing_selling_price_is_reported_and_suggested_from_cost(): void
    {
        $out = PricingAdvisor::analyzeProducts(
            [$this->product(['expected_selling_price' => null])],
            [],
            ['round_to' => 1, 'target_margin_pct' => 20]
        );

        $product = $out['products'][0];
        $this->assertSame('MISSING_SELLING_PRICE', $product['status']);
        $this->assertSame(1200.0, $product['suggested_price']);
        $this->assertSame(1, $out['summary']['missing_selling_count']);
    }

    public function test_suggested_price_rounds_up_to_the_nearest_step(): void
    {
        $out = PricingAdvisor::analyzeProducts(
            [$this->product(['price' => 1300, 'expected_selling_price' => 1300])],
            [],
            ['round_to' => 500, 'target_margin_pct' => 20]
        );

        // 1300 * 1.20 = 1560 -> next 500 step = 2000
        $this->assertSame(2000.0, $out['products'][0]['suggested_price']);
    }

    public function test_velocity_is_aggregated_into_the_summary(): void
    {
        $out = PricingAdvisor::analyzeProducts(
            [$this->product()],
            ['p1' => ['units' => 2, 'revenue' => 3000]],
            ['round_to' => 1]
        );

        $summary = $out['summary'];
        $this->assertSame(2.0, $summary['units_sold_window']);
        $this->assertSame(3000.0, $summary['revenue_window']);
        $this->assertSame(2000.0, $summary['cogs_window']);
        $this->assertSame(1000.0, $summary['gross_margin_window']);
        $this->assertSame(33.33, $summary['margin_pct_window']);
        $this->assertSame(50.0, $summary['weighted_margin_pct']);
    }

    public function test_at_risk_products_are_ranked_before_healthy_ones(): void
    {
        $out = PricingAdvisor::analyzeProducts([
            $this->product(['id' => 'healthy', 'name' => 'A', 'price' => 1000, 'expected_selling_price' => 2000]),
            $this->product(['id' => 'loss', 'name' => 'B', 'price' => 1000, 'expected_selling_price' => 500]),
        ], [], ['round_to' => 1]);

        $this->assertSame('loss', $out['products'][0]['id']);
    }

    public function test_rows_without_an_id_or_name_are_ignored(): void
    {
        $out = PricingAdvisor::analyzeProducts([
            ['id' => '', 'name' => 'x', 'price' => 1, 'expected_selling_price' => 2],
            ['id' => 'p2', 'name' => '', 'price' => 1, 'expected_selling_price' => 2],
        ], [], ['round_to' => 1]);

        $this->assertSame(0, $out['summary']['product_count']);
        $this->assertSame([], $out['products']);
    }

    public function test_sanitizer_drops_a_hallucinated_product_id(): void
    {
        $out = PricingAdvisor::sanitizeAiResponse([
            'insights' => [
                ['product_id' => 'not-mine', 'severity' => 'high', 'title' => 'x', 'action' => 'y'],
            ],
        ], ['p1']);

        $this->assertSame([], $out['insights']);
    }

    public function test_sanitizer_keeps_known_products_and_clamps_severity(): void
    {
        $out = PricingAdvisor::sanitizeAiResponse([
            'headline' => 'Punguza hasara',
            'insights' => [
                ['product_id' => 'p1', 'severity' => 'critical', 'title' => 'Hasara', 'action' => 'Pandisha bei'],
            ],
            'watchouts' => ['Angalia gharama'],
            'limitations' => ['Baadhi ya bei hazipo'],
        ], ['p1']);

        $this->assertTrue($out['available']);
        $this->assertFalse($out['degraded']);
        $this->assertSame('medium', $out['insights'][0]['severity']);
        $this->assertSame('p1', $out['insights'][0]['product_id']);
        $this->assertSame(['Angalia gharama'], $out['watchouts']);
    }

    public function test_sanitizer_deduplicates_and_caps_insights(): void
    {
        $raw = ['insights' => []];
        for ($i = 0; $i < 30; $i++) {
            $raw['insights'][] = ['product_id' => 'p1', 'severity' => 'low', 'title' => 't'.$i, 'action' => 'a'.$i];
        }

        $out = PricingAdvisor::sanitizeAiResponse($raw, ['p1'], ['max_insights' => 5]);

        $this->assertCount(1, $out['insights']);
    }

    public function test_sanitizer_bounds_long_strings(): void
    {
        $out = PricingAdvisor::sanitizeAiResponse([
            'headline' => str_repeat('a', 5000),
            'insights' => [[
                'product_id' => 'p1',
                'severity' => 'low',
                'title' => str_repeat('t', 1000),
                'action' => str_repeat('x', 5000),
            ]],
        ], ['p1']);

        $this->assertSame(240, mb_strlen($out['headline']));
        $this->assertSame(160, mb_strlen($out['insights'][0]['title']));
        $this->assertSame(400, mb_strlen($out['insights'][0]['action']));
    }

    public function test_sanitizer_handles_a_null_payload_honestly(): void
    {
        $out = PricingAdvisor::sanitizeAiResponse(null, ['p1']);

        $this->assertFalse($out['available']);
        $this->assertTrue($out['degraded']);
        $this->assertSame([], $out['insights']);
    }

    public function test_deterministic_limitations_flag_missing_data(): void
    {
        $summary = [
            'missing_cost_count' => 2,
            'missing_selling_count' => 1,
            'cogs_unknown_items' => 0,
            'window_days' => 30,
        ];

        $limitations = PricingAdvisor::limitations($summary);

        $this->assertNotEmpty($limitations);
        $this->assertStringContainsString('2', $limitations[0]);
    }
}
