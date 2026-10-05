<?php

namespace Tests\Unit;

use App\Services\Ai\RestockPlanner;
use PHPUnit\Framework\TestCase;

/**
 * Capability #3: velocity, days of cover, days until stock-out and proposed
 * quantities must be computed correctly in trusted code, and the model must
 * never be able to smuggle in an unknown product id. Pure-function tests:
 * no database, no HTTP, no Supabase.
 */
class RestockPlannerTest extends TestCase
{
    private function product(array $overrides = []): array
    {
        return array_merge([
            'id' => 'p1',
            'name' => 'Sukari 1kg',
            'category' => 'Vyakula',
            'stock' => 40,
            'price' => 1000,
            'expected_selling_price' => 1500,
        ], $overrides);
    }

    private function velocity(float $units, float $recent, int $activeDays = 5, ?string $last = null): array
    {
        return [
            'units' => $units,
            'revenue' => $units * 1500,
            'recent_units' => $recent,
            'active_days' => $activeDays,
            'last_sale_date' => $last,
        ];
    }

    public function test_out_of_stock_with_demand_is_flagged_and_gets_a_proposed_quantity(): void
    {
        // 30 units / 30 days = 1 unit/day; stock 0.
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 0])],
            ['p1' => $this->velocity(30, 7)],
            ['window_days' => 30, 'recent_days' => 7, 'lead_time_days' => 7, 'safety_days' => 7, 'review_days' => 7]
        );

        $product = $out['products'][0];
        $this->assertSame('OUT_OF_STOCK', $product['status']);
        $this->assertSame(0.0, $product['days_until_stockout']);
        $this->assertSame(21.0, $product['proposed_quantity']); // 1/day * (7+7+7) - 0
        $this->assertSame(14.0, $product['min_stock_level']);    // 1/day * (7+7)
        $this->assertSame(1, $out['summary']['out_of_stock_count']);
        $this->assertSame(21.0, $out['summary']['total_proposed_units']);
    }

    public function test_stock_that_runs_out_before_lead_time_is_low_stock(): void
    {
        // stock 5, velocity 1/day -> 5 days cover, lead time 7.
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 5])],
            ['p1' => $this->velocity(30, 7)],
            ['lead_time_days' => 7, 'safety_days' => 7, 'review_days' => 7]
        );

        $this->assertSame('LOW_STOCK', $out['products'][0]['status']);
        $this->assertSame(5.0, $out['products'][0]['days_until_stockout']);
        $this->assertSame(16.0, $out['products'][0]['proposed_quantity']);
    }

    public function test_cover_between_lead_time_and_restock_cover_is_restock(): void
    {
        // stock 10, velocity 1/day -> 10 days cover (> 7 lead, <= 14 cover).
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 10])],
            ['p1' => $this->velocity(30, 7)],
            ['lead_time_days' => 7, 'safety_days' => 7, 'review_days' => 7]
        );

        $product = $out['products'][0];
        $this->assertSame('RESTOCK', $product['status']);
        $this->assertSame(11.0, $product['proposed_quantity']);
    }

    public function test_ample_cover_is_healthy_and_needs_no_restock(): void
    {
        // stock 30, velocity 1/day -> 30 days cover.
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 30])],
            ['p1' => $this->velocity(30, 7)],
            ['lead_time_days' => 7, 'safety_days' => 7, 'review_days' => 7, 'overstock_days' => 60]
        );

        $product = $out['products'][0];
        $this->assertSame('HEALTHY', $product['status']);
        $this->assertSame(0.0, $product['proposed_quantity']);
        $this->assertSame(0, $out['summary']['restock_count']);
    }

    public function test_excess_cover_is_overstock(): void
    {
        // stock 120, velocity 1/day -> 120 days cover.
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 120])],
            ['p1' => $this->velocity(30, 7)],
            ['overstock_days' => 60]
        );

        $this->assertSame('OVERSTOCK', $out['products'][0]['status']);
        $this->assertTrue($out['products'][0]['overstocked']);
        $this->assertSame(1, $out['summary']['overstock_count']);
    }

    public function test_no_sales_history_is_reported_honestly_not_predicted(): void
    {
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 0])],
            [],
            []
        );

        $product = $out['products'][0];
        $this->assertSame('NO_DEMAND', $product['status']);
        $this->assertNull($product['days_until_stockout']);
        $this->assertSame(0.0, $product['proposed_quantity']);
        $this->assertSame('none', $product['confidence']);
        $this->assertTrue($product['sparse_data']);
        $this->assertSame(1, $out['summary']['no_demand_count']);
    }

    public function test_incoming_stock_reduces_the_proposed_quantity(): void
    {
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 0, 'incoming_stock' => 10])],
            ['p1' => $this->velocity(30, 7)],
            ['lead_time_days' => 7, 'safety_days' => 7, 'review_days' => 7]
        );

        $product = $out['products'][0];
        $this->assertSame(10.0, $product['effective_stock']);
        // demand 1/day * 21 - 10 = 11.
        $this->assertSame(11.0, $product['proposed_quantity']);
    }

    public function test_recent_demand_rise_is_detected_and_raises_the_order(): void
    {
        // 30 units over 30 days (1/day) but 14 over the last 7 days (2/day).
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 10])],
            ['p1' => $this->velocity(30, 14)],
            ['window_days' => 30, 'recent_days' => 7, 'lead_time_days' => 7, 'safety_days' => 7, 'review_days' => 7]
        );

        $product = $out['products'][0];
        $this->assertSame('accelerating', $product['demand_trend']);
        $this->assertSame(2.0, $product['recent_daily_velocity']);
        $this->assertSame('LOW_STOCK', $product['status']); // 10 stock / 2 per day = 5 days
        $this->assertSame(32.0, $product['proposed_quantity']); // 2 * 21 - 10
    }

    public function test_confidence_reflects_the_depth_of_history(): void
    {
        $high = RestockPlanner::planProducts([$this->product()], ['p1' => $this->velocity(40, 10, 6)], []);
        $medium = RestockPlanner::planProducts([$this->product()], ['p1' => $this->velocity(5, 1, 2)], []);
        $low = RestockPlanner::planProducts([$this->product()], ['p1' => $this->velocity(2, 1, 1)], []);

        $this->assertSame('high', $high['products'][0]['confidence']);
        $this->assertSame('medium', $medium['products'][0]['confidence']);
        $this->assertSame('low', $low['products'][0]['confidence']);
        $this->assertTrue($low['products'][0]['sparse_data']);
        $this->assertFalse($high['products'][0]['sparse_data']);
    }

    public function test_proposed_quantity_rounds_up_to_the_configured_step(): void
    {
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 0])],
            ['p1' => $this->velocity(30, 7)],
            ['lead_time_days' => 0, 'safety_days' => 0, 'review_days' => 0, 'round_to' => 5]
        );

        // demand 1/day * 0 cover target = 0 -> but OUT_OF_STOCK forces at least
        // one step, so the result is 5, never 1.
        $this->assertSame(5.0, $out['products'][0]['proposed_quantity']);
    }

    public function test_product_needing_restock_is_ranked_before_healthy_ones(): void
    {
        $out = RestockPlanner::planProducts([
            $this->product(['id' => 'healthy', 'name' => 'A', 'stock' => 30]),
            $this->product(['id' => 'out', 'name' => 'B', 'stock' => 0]),
        ], [
            'healthy' => $this->velocity(30, 7),
            'out' => $this->velocity(30, 7),
        ], []);

        $this->assertSame('out', $out['products'][0]['id']);
    }

    public function test_fast_moving_and_slow_moving_flags(): void
    {
        $fast = RestockPlanner::planProducts([$this->product()], ['p1' => $this->velocity(60, 14)], ['fast_moving_daily' => 1.0]);
        $slow = RestockPlanner::planProducts([$this->product()], ['p1' => $this->velocity(1, 0)], ['fast_moving_daily' => 1.0]);

        $this->assertTrue($fast['products'][0]['fast_moving']);
        $this->assertTrue($slow['products'][0]['slow_moving']);
    }

    public function test_rows_without_an_id_or_name_are_ignored(): void
    {
        $out = RestockPlanner::planProducts([
            ['id' => '', 'name' => 'x', 'stock' => 1],
            ['id' => 'p2', 'name' => '', 'stock' => 1],
        ], [], []);

        $this->assertSame(0, $out['summary']['product_count']);
        $this->assertSame([], $out['products']);
    }

    public function test_restock_cost_uses_the_buying_price_when_known(): void
    {
        $out = RestockPlanner::planProducts(
            [$this->product(['stock' => 0, 'price' => 1000])],
            ['p1' => $this->velocity(30, 7)],
            ['lead_time_days' => 7, 'safety_days' => 7, 'review_days' => 7]
        );

        // 21 units * 1000.
        $this->assertSame(21000.0, $out['summary']['estimated_restock_cost']);
        $this->assertSame(0, $out['summary']['restock_cost_unknown_items']);
    }

    public function test_sanitizer_drops_a_hallucinated_product_id(): void
    {
        $out = RestockPlanner::sanitizeAiResponse([
            'insights' => [
                ['product_id' => 'not-mine', 'severity' => 'high', 'title' => 'x', 'action' => 'y'],
                ['product_id' => 'p1', 'severity' => 'high', 'title' => 'Agiza', 'action' => 'Agiza 20'],
            ],
        ], ['p1']);

        $this->assertCount(1, $out['insights']);
        $this->assertSame('p1', $out['insights'][0]['product_id']);
        $this->assertSame('high', $out['insights'][0]['severity']);
    }

    public function test_deterministic_limitations_flag_sparse_and_missing_data(): void
    {
        $summary = [
            'sparse_count' => 3,
            'no_demand_count' => 2,
            'restock_cost_unknown_items' => 1,
            'inventory_value_missing_items' => 1,
            'window_days' => 30,
        ];

        $limitations = RestockPlanner::limitations($summary);

        $this->assertNotEmpty($limitations);
        $this->assertStringContainsString('3', $limitations[0]);
        $this->assertStringContainsString('30', implode(' ', $limitations));
    }
}
