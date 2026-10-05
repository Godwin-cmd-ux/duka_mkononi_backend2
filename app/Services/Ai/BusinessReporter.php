<?php

namespace App\Services\Ai;

/**
 * Capability #4 - Natural-Language Business Reporting (trusted engine).
 *
 * Aggregates already-scoped rows (sales, sale items, expenses, customers,
 * products) into report figures. All arithmetic lives here in PHP - never in
 * the model (grounding rules 1-2). Pure functions: arrays in, arrays out.
 */
class BusinessReporter
{
    public const CAPABILITY = 'business_report';

    private const TOP_LIMIT = 5;

    /**
     * @param  array<int, array<string, mixed>>  $sales
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $products  keyed by product id
     * @return array<string, mixed>
     */
    public static function revenueSummary(array $sales, array $items, array $products, string $currency = 'TZS'): array
    {
        $revenue = 0.0;
        foreach ($sales as $sale) {
            $revenue += self::amount($sale['total_amount'] ?? null);
        }

        $units = 0.0;
        $cogs = 0.0;
        $unknownCogs = 0;

        foreach ($items as $item) {
            $quantity = self::num($item['quantity'] ?? null, 0.0);
            $units += $quantity;

            $buying = self::amount($products[(string) ($item['product_id'] ?? '')]['price'] ?? null);
            if ($buying === null) {
                $unknownCogs++;
            } else {
                $cogs += $buying * $quantity;
            }
        }

        $grossProfit = $revenue - $cogs;

        return [
            'currency' => $currency,
            'revenue' => round($revenue, 2),
            'sales_count' => count($sales),
            'units' => round($units, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'margin_pct' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : null,
            'unknown_cogs_items' => $unknownCogs,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public static function productBreakdown(array $items, array $products, string $metric = 'profit', int $limit = self::TOP_LIMIT, string $currency = 'TZS'): array
    {
        $metric = in_array($metric, ['revenue', 'profit', 'units'], true) ? $metric : 'profit';
        $limit = max(1, min(20, $limit));

        $byProduct = [];
        foreach ($items as $item) {
            $id = trim((string) ($item['product_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $quantity = self::num($item['quantity'] ?? null, 0.0);
            $lineRevenue = self::amount($item['total_price'] ?? null) ?? (self::num($item['unit_price'] ?? null, 0.0) * $quantity);
            $buying = self::amount($products[$id]['price'] ?? null);
            $lineCogs = $buying !== null ? $buying * $quantity : null;

            if (! isset($byProduct[$id])) {
                $byProduct[$id] = [
                    'product_id' => $id,
                    'name' => (string) ($products[$id]['name'] ?? 'Bidhaa'),
                    'units' => 0.0,
                    'revenue' => 0.0,
                    'cogs' => 0.0,
                    'unknown_cogs' => false,
                ];
            }

            $byProduct[$id]['units'] += $quantity;
            $byProduct[$id]['revenue'] += $lineRevenue;
            if ($lineCogs === null) {
                $byProduct[$id]['unknown_cogs'] = true;
            } else {
                $byProduct[$id]['cogs'] += $lineCogs;
            }
        }

        $rows = [];
        foreach ($byProduct as $row) {
            $rows[] = [
                'product_id' => $row['product_id'],
                'name' => $row['name'],
                'units' => round($row['units'], 2),
                'revenue' => round($row['revenue'], 2),
                'profit' => $row['unknown_cogs'] ? null : round($row['revenue'] - $row['cogs'], 2),
            ];
        }

        usort($rows, function ($a, $b) use ($metric) {
            $av = $metric === 'profit' ? ($a['profit'] ?? PHP_FLOAT_MIN) : $a[$metric];
            $bv = $metric === 'profit' ? ($b['profit'] ?? PHP_FLOAT_MIN) : $b[$metric];

            return [$bv, $a['name']] <=> [$av, $b['name']];
        });

        return [
            'currency' => $currency,
            'metric' => $metric,
            'limit' => $limit,
            'items' => array_slice($rows, 0, $limit),
            'product_count_total' => count($rows),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $expenses
     * @return array<string, mixed>
     */
    public static function expenseSummary(array $expenses, ?string $categoryFilter = null, string $currency = 'TZS'): array
    {
        $total = 0.0;
        $count = 0;
        $byCategory = [];
        $filteredTotal = 0.0;
        $filterLower = $categoryFilter !== null ? mb_strtolower($categoryFilter) : null;

        foreach ($expenses as $expense) {
            $amount = self::amount($expense['amount'] ?? null) ?? 0.0;
            $category = trim((string) ($expense['category'] ?? '')) !== '' ? (string) $expense['category'] : 'Mengineyo';

            $total += $amount;
            $count++;

            $byCategory[$category] = ($byCategory[$category] ?? 0.0) + $amount;

            if ($filterLower !== null && mb_strpos(mb_strtolower($category), $filterLower) !== false) {
                $filteredTotal += $amount;
            }
        }

        $categories = [];
        foreach ($byCategory as $category => $amount) {
            $categories[] = ['category' => $category, 'amount' => round($amount, 2)];
        }
        usort($categories, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'currency' => $currency,
            'total' => round($total, 2),
            'count' => $count,
            'by_category' => $categories,
            'category_filter' => $categoryFilter,
            'category_total' => $filterLower !== null ? round($filteredTotal, 2) : null,
            'category_matched' => $filterLower === null ? null : $filteredTotal > 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $customers
     * @param  array{from: string, to: string}|null  $range
     * @return array<string, mixed>
     */
    public static function customerSummary(array $customers, ?array $range = null): array
    {
        $newCustomers = 0;
        if ($range !== null) {
            foreach ($customers as $customer) {
                $created = substr((string) ($customer['created_at'] ?? ''), 0, 10);
                if ($created !== '' && $created >= $range['from'] && $created <= $range['to']) {
                    $newCustomers++;
                }
            }
        }

        $rows = [];
        foreach ($customers as $customer) {
            $rows[] = [
                'name' => (string) ($customer['name'] ?? 'Mteja'),
                'total_purchases' => round(self::num($customer['total_purchases'] ?? null, 0.0), 2),
                'purchases_count' => (int) self::num($customer['purchases_count'] ?? null, 0.0),
            ];
        }
        usort($rows, fn ($a, $b) => $b['total_purchases'] <=> $a['total_purchases']);

        return [
            'customer_count' => count($customers),
            'new_customers' => $newCustomers,
            'top_customers' => array_slice($rows, 0, self::TOP_LIMIT),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public static function stockValue(array $products, string $currency = 'TZS'): array
    {
        $cost = 0.0;
        $retail = 0.0;
        $units = 0.0;
        $outOfStock = 0;
        $missingCost = 0;

        foreach ($products as $product) {
            $stock = max(0.0, self::num($product['stock'] ?? null, 0.0));
            $buying = self::amount($product['price'] ?? null);
            $selling = self::amount($product['expected_selling_price'] ?? null);

            $units += $stock;
            if ($stock <= 0) {
                $outOfStock++;
            }
            if ($buying === null) {
                $missingCost++;
            } else {
                $cost += $buying * $stock;
            }
            if ($selling !== null) {
                $retail += $selling * $stock;
            }
        }

        return [
            'currency' => $currency,
            'inventory_value_cost' => round($cost, 2),
            'inventory_value_retail' => round($retail, 2),
            'product_count' => count($products),
            'units_in_stock' => round($units, 2),
            'out_of_stock_count' => $outOfStock,
            'missing_cost_count' => $missingCost,
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return array<string, mixed>
     */
    public static function compare(array $current, array $previous): array
    {
        $delta = [];
        foreach (['revenue', 'sales_count', 'gross_profit', 'units'] as $key) {
            $cur = self::num($current[$key] ?? null, 0.0);
            $prev = self::num($previous[$key] ?? null, 0.0);
            $delta[$key] = [
                'current' => round($cur, 2),
                'previous' => round($prev, 2),
                'change' => round($cur - $prev, 2),
                'change_pct' => $prev != 0.0 ? round((($cur - $prev) / abs($prev)) * 100, 2) : null,
            ];
        }

        return $delta;
    }

    /* ------------------------------------------------------------------ */

    private static function num($value, float $default): float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $default;
        }
        $float = (float) $value;

        return is_finite($float) ? $float : $default;
    }

    private static function amount($value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        if (! is_finite($float) || $float <= 0) {
            return null;
        }

        return $float;
    }
}
