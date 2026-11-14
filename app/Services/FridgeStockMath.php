<?php

namespace App\Services;

/**
 * Pure stock arithmetic for the Fridge / Freezer feature.
 *
 * Keeping this free of HTTP and database calls means the invariant can be
 * unit-tested with a plain PHPUnit test, in the same spirit as
 * SaleController::planStockDeltas().
 */
class FridgeStockMath
{
    /**
     * Outside stock = Business Stock - stock physically inside active fridges.
     * It is derived, never stored, so two different numbers can never drift.
     */
    public static function outsideStock(float $businessStock, array $insideQuantities): float
    {
        $inside = 0.0;
        foreach ($insideQuantities as $qty) {
            $inside += is_numeric($qty) ? (float) $qty : 0.0;
        }

        return round($businessStock - $inside, 3);
    }

    /**
     * Validate and normalise a sale line's source allocations.
     *
     * @param  float $quantity  requested line quantity
     * @param  array $allocations  [['fridge_id' => string|null, 'quantity' => n], ...]
     * @return array{valid: bool, errors: array<int,string>, inside: float, outside: float, items: array<int,array{fridge_id: ?string, quantity: float}>}
     */
    public static function normalizeAllocations(float $quantity, array $allocations): array
    {
        $errors = [];
        $normalised = [];
        $inside = 0.0;
        $outside = 0.0;

        if ($quantity <= 0) {
            return [
                'valid' => false,
                'errors' => ['Quantity must be greater than zero.'],
                'inside' => 0.0,
                'outside' => 0.0,
                'items' => [],
            ];
        }

        if ($allocations === []) {
            return [
                'valid' => true,
                'errors' => [],
                'inside' => 0.0,
                'outside' => round($quantity, 3),
                'items' => [['fridge_id' => null, 'quantity' => round($quantity, 3)]],
            ];
        }

        $seen = [];
        foreach ($allocations as $allocation) {
            $fridgeId = $allocation['fridge_id'] ?? null;
            $fridgeId = $fridgeId === '' ? null : $fridgeId;
            $qty = $allocation['quantity'] ?? null;

            if (! is_numeric($qty) || (float) $qty <= 0) {
                $errors[] = 'Every allocation must have a positive quantity.';
                continue;
            }

            $key = $fridgeId === null ? '__outside__' : (string) $fridgeId;
            if (isset($seen[$key])) {
                $errors[] = 'The same source cannot be listed twice.';
                continue;
            }
            $seen[$key] = true;

            $qty = round((float) $qty, 3);
            $normalised[] = ['fridge_id' => $fridgeId, 'quantity' => $qty];

            if ($fridgeId === null) {
                $outside += $qty;
            } else {
                $inside += $qty;
            }
        }

        $total = round($inside + $outside, 3);
        if (abs($total - round($quantity, 3)) > 0.0005) {
            $errors[] = 'Allocated quantity must equal the sold quantity.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'inside' => round($inside, 3),
            'outside' => round($outside, 3),
            'items' => $normalised,
        ];
    }

    /**
     * Build the three independent alert families.
     *
     * A threshold of 0 means "not configured" - we never invent an alert for
     * every out-of-stock product just because the column defaults to zero.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function candidateAlerts(array $products, array $fridges, array $fridgeStock): array
    {
        $fridgeById = [];
        foreach ($fridges as $fridge) {
            $fridgeById[(string) $fridge['id']] = $fridge;
        }

        $insideByProduct = [];
        foreach ($fridgeStock as $row) {
            $fridge = $fridgeById[(string) ($row['fridge_id'] ?? '')] ?? null;
            if (! $fridge || ($fridge['status'] ?? 'active') !== 'active') {
                continue;
            }
            $pid = (string) ($row['product_id'] ?? '');
            if ($pid === '') {
                continue;
            }
            $insideByProduct[$pid] = ($insideByProduct[$pid] ?? 0.0) + (float) ($row['quantity'] ?? 0);
        }

        $alerts = [];
        foreach ($products as $product) {
            $pid = (string) ($product['id'] ?? '');
            if ($pid === '') {
                continue;
            }

            $stock = (float) ($product['stock'] ?? 0);
            $inside = (float) ($insideByProduct[$pid] ?? 0);
            $outside = self::outsideStock($stock, [$inside]);
            $branchId = $product['branch_id'] ?? null;

            $businessThreshold = (float) ($product['min_stock_level'] ?? 0);
            if ($businessThreshold > 0 && $stock <= $businessThreshold) {
                $alerts[] = self::alert('business_low', $branchId, null, $pid, $businessThreshold, $stock, $product);
            }

            $outsideThreshold = (float) ($product['min_outside_stock'] ?? 0);
            if ($outsideThreshold > 0 && $outside <= $outsideThreshold) {
                $alerts[] = self::alert('outside_low', $branchId, null, $pid, $outsideThreshold, $outside, $product);
            }
        }

        foreach ($fridgeStock as $row) {
            $fridge = $fridgeById[(string) ($row['fridge_id'] ?? '')] ?? null;
            if (! $fridge || ($fridge['status'] ?? 'active') !== 'active') {
                continue;
            }
            $threshold = (float) ($row['min_stock'] ?? 0);
            $qty = (float) ($row['quantity'] ?? 0);
            if ($threshold > 0 && $qty <= $threshold) {
                $product = null;
                foreach ($products as $candidate) {
                    if ((string) ($candidate['id'] ?? '') === (string) ($row['product_id'] ?? '')) {
                        $product = $candidate;
                        break;
                    }
                }
                $alerts[] = self::alert(
                    'fridge_low',
                    $fridge['branch_id'] ?? ($row['branch_id'] ?? null),
                    $fridge['id'],
                    (string) ($row['product_id'] ?? ''),
                    $threshold,
                    $qty,
                    $product
                );
            }
        }

        return $alerts;
    }

    private static function alert(
        string $type,
        ?string $branchId,
        ?string $fridgeId,
        string $productId,
        float $threshold,
        float $quantity,
        ?array $product
    ): array {
        return [
            'alert_type' => $type,
            'branch_id' => $branchId,
            'fridge_id' => $fridgeId,
            'product_id' => $productId,
            'product_name' => $product['name'] ?? null,
            'threshold' => round($threshold, 3),
            'quantity' => round($quantity, 3),
        ];
    }
}
