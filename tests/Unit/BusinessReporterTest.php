<?php

namespace Tests\Unit;

use App\Services\Ai\BusinessReporter;
use PHPUnit\Framework\TestCase;

/**
 * Capability #4: reporting figures must be computed correctly in trusted code
 * from real rows. Pure-function tests, no database.
 */
class BusinessReporterTest extends TestCase
{
    private function products(): array
    {
        return [
            'p1' => ['id' => 'p1', 'name' => 'Sukari', 'price' => 400, 'stock' => 10, 'expected_selling_price' => 700],
            'p2' => ['id' => 'p2', 'name' => 'Chai', 'price' => 100, 'stock' => 0, 'expected_selling_price' => 500],
        ];
    }

    public function test_revenue_summary_computes_profit_and_margin(): void
    {
        $sales = [['total_amount' => 1500], ['total_amount' => 500]];
        $items = [
            ['product_id' => 'p1', 'quantity' => 2, 'total_price' => 1500],
            ['product_id' => 'p2', 'quantity' => 1, 'total_price' => 500],
        ];

        $out = BusinessReporter::revenueSummary($sales, $items, $this->products());

        $this->assertSame(2000.0, $out['revenue']);
        $this->assertSame(2, $out['sales_count']);
        $this->assertSame(3.0, $out['units']);
        $this->assertSame(900.0, $out['cogs']);      // 2*400 + 1*100
        $this->assertSame(1100.0, $out['gross_profit']);
        $this->assertSame(55.0, $out['margin_pct']);
        $this->assertSame(0, $out['unknown_cogs_items']);
    }

    public function test_missing_buying_price_is_reported_not_invented(): void
    {
        $sales = [['total_amount' => 500]];
        $items = [['product_id' => 'unknown', 'quantity' => 1, 'total_price' => 500]];

        $out = BusinessReporter::revenueSummary($sales, $items, $this->products());

        $this->assertSame(1, $out['unknown_cogs_items']);
        $this->assertSame(0.0, $out['cogs']);
        $this->assertSame(500.0, $out['gross_profit']);
    }

    public function test_product_breakdown_orders_by_the_chosen_metric(): void
    {
        $items = [
            ['product_id' => 'p1', 'quantity' => 2, 'total_price' => 1000], // profit 200
            ['product_id' => 'p2', 'quantity' => 1, 'total_price' => 500],  // profit 400
        ];

        $byProfit = BusinessReporter::productBreakdown($items, $this->products(), 'profit');
        $this->assertSame('Chai', $byProfit['items'][0]['name']);
        $this->assertSame(400.0, $byProfit['items'][0]['profit']);

        $byRevenue = BusinessReporter::productBreakdown($items, $this->products(), 'revenue');
        $this->assertSame('Sukari', $byRevenue['items'][0]['name']);
    }

    public function test_product_breakdown_profit_is_null_when_cost_is_missing(): void
    {
        $items = [['product_id' => 'nope', 'quantity' => 1, 'total_price' => 500]];
        $out = BusinessReporter::productBreakdown($items, $this->products(), 'profit');

        $this->assertNull($out['items'][0]['profit']);
    }

    public function test_expense_summary_totals_and_filters_by_category(): void
    {
        $expenses = [
            ['amount' => 1000, 'category' => 'Usafiri'],
            ['amount' => 500, 'category' => 'Usafiri'],
            ['amount' => 200, 'category' => 'Umeme'],
        ];

        $all = BusinessReporter::expenseSummary($expenses);
        $this->assertSame(1700.0, $all['total']);
        $this->assertSame(3, $all['count']);
        $this->assertSame('Usafiri', $all['by_category'][0]['category']);
        $this->assertSame(1500.0, $all['by_category'][0]['amount']);

        $filtered = BusinessReporter::expenseSummary($expenses, 'usafiri');
        $this->assertSame(1500.0, $filtered['category_total']);
        $this->assertTrue($filtered['category_matched']);
    }

    public function test_customer_summary_counts_new_customers_in_range(): void
    {
        $customers = [
            ['name' => 'A', 'total_purchases' => 5000, 'purchases_count' => 4, 'created_at' => '2026-10-05'],
            ['name' => 'B', 'total_purchases' => 9000, 'purchases_count' => 2, 'created_at' => '2026-09-01'],
            ['name' => 'C', 'total_purchases' => 1000, 'purchases_count' => 1, 'created_at' => '2026-10-12'],
        ];

        $out = BusinessReporter::customerSummary($customers, ['from' => '2026-10-01', 'to' => '2026-10-15']);

        $this->assertSame(3, $out['customer_count']);
        $this->assertSame(2, $out['new_customers']);
        $this->assertSame('B', $out['top_customers'][0]['name']);
    }

    public function test_stock_value_sums_at_cost_and_counts_out_of_stock(): void
    {
        $out = BusinessReporter::stockValue($this->products());

        $this->assertSame(4000.0, $out['inventory_value_cost']); // 10*400 + 0*100
        $this->assertSame(7000.0, $out['inventory_value_retail']); // 10*700 + 0*500
        $this->assertSame(2, $out['product_count']);
        $this->assertSame(1, $out['out_of_stock_count']);
        $this->assertSame(10.0, $out['units_in_stock']);
    }

    public function test_compare_reports_absolute_and_percentage_change(): void
    {
        $current = ['revenue' => 2000, 'sales_count' => 4, 'gross_profit' => 800, 'units' => 10];
        $previous = ['revenue' => 1000, 'sales_count' => 2, 'gross_profit' => 400, 'units' => 5];

        $out = BusinessReporter::compare($current, $previous);

        $this->assertSame(1000.0, $out['revenue']['change']);
        $this->assertSame(100.0, $out['revenue']['change_pct']);
        $this->assertSame(2.0, $out['sales_count']['change']);
    }

    public function test_compare_handles_a_zero_previous_period(): void
    {
        $out = BusinessReporter::compare(['revenue' => 500], ['revenue' => 0]);

        $this->assertNull($out['revenue']['change_pct']);
        $this->assertSame(500.0, $out['revenue']['change']);
    }
}
