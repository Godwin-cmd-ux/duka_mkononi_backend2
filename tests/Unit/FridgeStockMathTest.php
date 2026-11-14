<?php

namespace Tests\Unit;

use App\Services\FridgeStockMath;
use PHPUnit\Framework\TestCase;

class FridgeStockMathTest extends TestCase
{
    public function test_outside_stock_is_business_stock_minus_inside(): void
    {
        $this->assertSame(7.0, FridgeStockMath::outsideStock(10, [3]));
        $this->assertSame(4.0, FridgeStockMath::outsideStock(10, [2.5, 3.5]));
        $this->assertSame(10.0, FridgeStockMath::outsideStock(10, []));
        $this->assertSame(0.0, FridgeStockMath::outsideStock(10, ['not-a-number', 10]));
    }

    public function test_plain_sale_defaults_to_outside_only(): void
    {
        $result = FridgeStockMath::normalizeAllocations(5, []);

        $this->assertTrue($result['valid']);
        $this->assertSame(0.0, $result['inside']);
        $this->assertSame(5.0, $result['outside']);
        $this->assertSame([['fridge_id' => null, 'quantity' => 5.0]], $result['items']);
    }

    public function test_mixed_allocation_is_valid(): void
    {
        $result = FridgeStockMath::normalizeAllocations(6, [
            ['fridge_id' => null, 'quantity' => 4],
            ['fridge_id' => 'fridge-1', 'quantity' => 2],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(4.0, $result['outside']);
        $this->assertSame(2.0, $result['inside']);
    }

    public function test_allocation_must_equal_sold_quantity(): void
    {
        $result = FridgeStockMath::normalizeAllocations(6, [
            ['fridge_id' => null, 'quantity' => 4],
            ['fridge_id' => 'fridge-1', 'quantity' => 1],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('Allocated quantity must equal the sold quantity.', $result['errors']);
    }

    public function test_duplicate_source_is_rejected(): void
    {
        $result = FridgeStockMath::normalizeAllocations(4, [
            ['fridge_id' => 'fridge-1', 'quantity' => 2],
            ['fridge_id' => 'fridge-1', 'quantity' => 2],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('The same source cannot be listed twice.', $result['errors']);
    }

    public function test_non_positive_quantities_are_rejected(): void
    {
        $this->assertFalse(FridgeStockMath::normalizeAllocations(0, [])['valid']);

        $result = FridgeStockMath::normalizeAllocations(4, [
            ['fridge_id' => 'fridge-1', 'quantity' => 0],
        ]);
        $this->assertFalse($result['valid']);
        $this->assertContains('Every allocation must have a positive quantity.', $result['errors']);
    }

    public function test_business_and_outside_alerts_skip_unconfigured_thresholds(): void
    {
        $products = [
            ['id' => 'p1', 'name' => 'Soda', 'stock' => 5, 'min_stock_level' => 0, 'min_outside_stock' => 0, 'branch_id' => 'b1'],
            ['id' => 'p2', 'name' => 'Juice', 'stock' => 5, 'min_stock_level' => 6, 'min_outside_stock' => 8, 'branch_id' => 'b1'],
        ];

        $alerts = FridgeStockMath::candidateAlerts($products, [], []);

        $this->assertCount(2, $alerts);
        $this->assertSame(['business_low', 'outside_low'], array_column($alerts, 'alert_type'));
        $this->assertSame('p2', $alerts[0]['product_id']);
        $this->assertSame('p2', $alerts[1]['product_id']);
    }

    public function test_fridge_alert_uses_per_fridge_threshold_and_active_fridges(): void
    {
        $products = [['id' => 'p1', 'name' => 'Milk', 'stock' => 10, 'branch_id' => 'b1']];
        $fridges = [
            ['id' => 'f1', 'name' => 'Friji 1', 'status' => 'active', 'branch_id' => 'b1'],
            ['id' => 'f2', 'name' => 'Friji 2', 'status' => 'archived', 'branch_id' => 'b1'],
        ];
        $stock = [
            ['fridge_id' => 'f1', 'product_id' => 'p1', 'quantity' => 1, 'min_stock' => 2],
            ['fridge_id' => 'f2', 'product_id' => 'p1', 'quantity' => 0, 'min_stock' => 2],
        ];

        $alerts = FridgeStockMath::candidateAlerts($products, $fridges, $stock);

        $this->assertCount(1, $alerts);
        $this->assertSame('fridge_low', $alerts[0]['alert_type']);
        $this->assertSame('f1', $alerts[0]['fridge_id']);
        $this->assertSame(1.0, $alerts[0]['quantity']);
        $this->assertSame(2.0, $alerts[0]['threshold']);
    }
}
