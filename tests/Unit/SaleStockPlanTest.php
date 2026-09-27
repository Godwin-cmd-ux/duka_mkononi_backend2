<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\SaleController;
use PHPUnit\Framework\TestCase;

/**
 * The rule a sale edit must obey: stock moves by the DIFFERENCE between the
 * recorded quantity and the edited one - never by the whole quantity again.
 *   quantity increased -> sell the extra units (stock down)
 *   quantity reduced   -> put those units back   (stock up)
 *
 * These are pure-function tests: no database, no HTTP, no Supabase.
 */
class SaleStockPlanTest extends TestCase
{
    public function test_increasing_a_quantity_sells_only_the_difference(): void
    {
        $plan = SaleController::planStockDeltas(
            [['product_id' => 'p1', 'delta' => 2.0]],
            ['p1' => 10.0]
        );

        $this->assertSame(2.0, $plan['deltas']['p1']);
        $this->assertSame([], $plan['issues']);
    }

    public function test_reducing_a_quantity_restocks_and_needs_no_stock_on_hand(): void
    {
        $plan = SaleController::planStockDeltas(
            [['product_id' => 'p1', 'delta' => -3.0]],
            ['p1' => 0.0]
        );

        $this->assertSame(-3.0, $plan['deltas']['p1']);
        $this->assertSame([], $plan['issues']);
    }

    public function test_selling_more_than_stock_on_hand_is_rejected(): void
    {
        $plan = SaleController::planStockDeltas(
            [['product_id' => 'p1', 'delta' => 5.0]],
            ['p1' => 3.0]
        );

        $this->assertCount(1, $plan['issues']);
        $this->assertSame('Insufficient stock', $plan['issues'][0]['reason']);
        $this->assertSame(3.0, $plan['issues'][0]['available']);
        $this->assertSame(5.0, $plan['issues'][0]['additional_required']);
    }

    public function test_selling_exactly_the_available_stock_is_allowed(): void
    {
        $plan = SaleController::planStockDeltas(
            [['product_id' => 'p1', 'delta' => 4.0]],
            ['p1' => 4.0]
        );

        $this->assertSame([], $plan['issues']);
    }

    public function test_deltas_are_aggregated_when_a_product_appears_on_several_lines(): void
    {
        $plan = SaleController::planStockDeltas(
            [
                ['product_id' => 'p1', 'delta' => 2.0],
                ['product_id' => 'p1', 'delta' => 3.0],
                ['product_id' => 'p1', 'delta' => -1.0],
            ],
            ['p1' => 100.0]
        );

        $this->assertSame(4.0, $plan['deltas']['p1']);
        $this->assertSame([], $plan['issues']);
    }

    public function test_opposite_edits_on_one_product_net_out_to_no_stock_need(): void
    {
        // Sell 10 on one line and return 10 on another -> net zero.
        $plan = SaleController::planStockDeltas(
            [
                ['product_id' => 'p1', 'delta' => 10.0],
                ['product_id' => 'p1', 'delta' => -10.0],
            ],
            ['p1' => 0.0]
        );

        $this->assertSame(0.0, $plan['deltas']['p1']);
        $this->assertSame([], $plan['issues']);
    }

    public function test_a_missing_product_is_reported(): void
    {
        $plan = SaleController::planStockDeltas(
            [['product_id' => 'missing', 'delta' => 1.0]],
            []
        );

        $this->assertCount(1, $plan['issues']);
        $this->assertSame('Product not found', $plan['issues'][0]['reason']);
    }

    public function test_lines_without_a_product_are_ignored(): void
    {
        $plan = SaleController::planStockDeltas(
            [['product_id' => null, 'delta' => 4.0]],
            []
        );

        $this->assertSame([], $plan['deltas']);
        $this->assertSame([], $plan['issues']);
    }
}
