<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\OfficeExpense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;

/**
 * The trusted retrieval layer (instruction section 3.2).
 *
 * Only this code touches the database; the model never sees a query language
 * or credentials. Every method is scoped by the caller-supplied business
 * member ids, which come from the authenticated user's business (never from
 * client input), so one business can never read another's rows.
 */
class BusinessDataProvider
{
    private const MAX_PRODUCTS = 5000;

    private const MAX_SALES = 50000;

    private const MAX_SALE_ITEMS = 200000;

    /**
     * Active products belonging to the business.
     *
     * @param  array<int, string>  $memberIds
     * @return array<int, array<string, mixed>>
     */
    public static function productsForBusiness(array $memberIds, int $limit = self::MAX_PRODUCTS): array
    {
        $memberIds = array_values(array_filter(array_map('strval', $memberIds)));
        if ($memberIds === []) {
            return [];
        }

        return Product::whereIn('seller_id', $memberIds)
            ->where('is_active', true)
            ->select(['id', 'name', 'category', 'stock', 'price', 'expected_selling_price', 'is_active', 'seller_id'])
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Units sold and revenue per product over the trailing window.
     *
     * @param  array<int, string>  $memberIds
     * @return array<string, array{units: float, revenue: float}>
     */
    public static function salesVelocity(array $memberIds, int $days = 30): array
    {
        $memberIds = array_values(array_filter(array_map('strval', $memberIds)));
        if ($memberIds === []) {
            return [];
        }

        $days = max(1, $days);
        $from = gmdate('Y-m-d', strtotime('-'.$days.' days'));

        $sales = Sale::whereIn('seller_id', $memberIds)
            ->where('sale_date', '>=', $from)
            ->select(['id'])
            ->limit(self::MAX_SALES)
            ->get();

        $saleIds = [];
        foreach ($sales as $sale) {
            if (! empty($sale->id)) {
                $saleIds[] = (string) $sale->id;
            }
        }
        if ($saleIds === []) {
            return [];
        }

        $items = SaleItem::whereIn('sale_id', $saleIds)
            ->select(['product_id', 'quantity', 'total_price'])
            ->limit(self::MAX_SALE_ITEMS)
            ->get();

        $velocity = [];
        foreach ($items as $item) {
            $productId = (string) ($item->product_id ?? '');
            if ($productId === '') {
                continue;
            }

            $quantity = is_numeric($item->quantity ?? null) ? (float) $item->quantity : 0.0;
            $total = is_numeric($item->total_price ?? null) ? (float) $item->total_price : 0.0;

            if (! isset($velocity[$productId])) {
                $velocity[$productId] = ['units' => 0.0, 'revenue' => 0.0];
            }

            $velocity[$productId]['units'] += $quantity;
            $velocity[$productId]['revenue'] += $total;
        }

        return $velocity;
    }

    /**
     * Demand detail per product over a trailing window, plus a shorter recent
     * window used to detect demand change (instruction section 4, capability
     * #3). A sale item shares its parent sale's date, so the sale rows are
     * mapped by id first.
     *
     * Rows in `sales` are completed sales; returns, cancelled and unfulfilled
     * orders live in the orders tables and are deliberately excluded here.
     *
     * @param  array<int, string>  $memberIds
     * @return array<string, array{units: float, revenue: float, recent_units: float, active_days: int, last_sale_date: ?string}>
     */
    public static function salesVelocityDetailed(array $memberIds, int $windowDays = 30, int $recentDays = 7): array
    {
        $memberIds = array_values(array_filter(array_map('strval', $memberIds)));
        if ($memberIds === []) {
            return [];
        }

        $windowDays = max(1, $windowDays);
        $recentDays = max(1, $recentDays);
        $from = gmdate('Y-m-d', strtotime('-'.$windowDays.' days'));
        $recentFrom = gmdate('Y-m-d', strtotime('-'.$recentDays.' days'));

        $sales = Sale::whereIn('seller_id', $memberIds)
            ->where('sale_date', '>=', $from)
            ->select(['id', 'sale_date'])
            ->limit(self::MAX_SALES)
            ->get();

        $saleDates = [];
        foreach ($sales as $sale) {
            if (empty($sale->id)) {
                continue;
            }
            $date = is_string($sale->sale_date) ? $sale->sale_date : (string) $sale->sale_date;
            $saleDates[(string) $sale->id] = substr($date, 0, 10);
        }
        if ($saleDates === []) {
            return [];
        }

        $items = SaleItem::whereIn('sale_id', array_keys($saleDates))
            ->select(['sale_id', 'product_id', 'quantity', 'total_price'])
            ->limit(self::MAX_SALE_ITEMS)
            ->get();

        $velocity = [];
        foreach ($items as $item) {
            $productId = (string) ($item->product_id ?? '');
            if ($productId === '') {
                continue;
            }

            $quantity = is_numeric($item->quantity ?? null) ? (float) $item->quantity : 0.0;
            $total = is_numeric($item->total_price ?? null) ? (float) $item->total_price : 0.0;
            $saleDate = $saleDates[(string) $item->sale_id] ?? null;

            if (! isset($velocity[$productId])) {
                $velocity[$productId] = [
                    'units' => 0.0,
                    'revenue' => 0.0,
                    'recent_units' => 0.0,
                    'active_days_set' => [],
                    'last_sale_date' => null,
                ];
            }

            $velocity[$productId]['units'] += $quantity;
            $velocity[$productId]['revenue'] += $total;
            if ($saleDate !== null && $saleDate >= $recentFrom) {
                $velocity[$productId]['recent_units'] += $quantity;
            }
            if ($saleDate !== null) {
                $velocity[$productId]['active_days_set'][$saleDate] = true;
                if ($velocity[$productId]['last_sale_date'] === null || $saleDate > $velocity[$productId]['last_sale_date']) {
                    $velocity[$productId]['last_sale_date'] = $saleDate;
                }
            }
        }

        $out = [];
        foreach ($velocity as $productId => $row) {
            $out[$productId] = [
                'units' => $row['units'],
                'revenue' => $row['revenue'],
                'recent_units' => $row['recent_units'],
                'active_days' => count($row['active_days_set']),
                'last_sale_date' => $row['last_sale_date'],
            ];
        }

        return $out;
    }

    /**
     * Completed sales for the business within an inclusive date range.
     *
     * @param  array<int, string>  $memberIds
     * @return array<int, array<string, mixed>>
     */
    public static function salesInRange(array $memberIds, string $from, string $to): array
    {
        $memberIds = array_values(array_filter(array_map('strval', $memberIds)));
        if ($memberIds === []) {
            return [];
        }

        return Sale::whereIn('seller_id', $memberIds)
            ->where('sale_date', '>=', $from)
            ->where('sale_date', '<=', $to)
            ->select(['id', 'sale_date', 'total_amount'])
            ->limit(self::MAX_SALES)
            ->get()
            ->toArray();
    }

    /**
     * Sale line items within an inclusive date range, each carrying its parent
     * sale's date so reporting can aggregate by product and by period.
     *
     * @param  array<int, string>  $memberIds
     * @return array<int, array<string, mixed>>
     */
    public static function saleItemsInRange(array $memberIds, string $from, string $to): array
    {
        $memberIds = array_values(array_filter(array_map('strval', $memberIds)));
        if ($memberIds === []) {
            return [];
        }

        $sales = Sale::whereIn('seller_id', $memberIds)
            ->where('sale_date', '>=', $from)
            ->where('sale_date', '<=', $to)
            ->select(['id', 'sale_date'])
            ->limit(self::MAX_SALES)
            ->get();

        $saleDates = [];
        foreach ($sales as $sale) {
            if (! empty($sale->id)) {
                $saleDates[(string) $sale->id] = substr((string) $sale->sale_date, 0, 10);
            }
        }
        if ($saleDates === []) {
            return [];
        }

        $items = SaleItem::whereIn('sale_id', array_keys($saleDates))
            ->select(['sale_id', 'product_id', 'quantity', 'unit_price', 'total_price'])
            ->limit(self::MAX_SALE_ITEMS)
            ->get();

        $out = [];
        foreach ($items as $item) {
            $row = $item->toArray();
            $row['sale_date'] = $saleDates[(string) ($item->sale_id ?? '')] ?? null;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Office expenses for the business within an inclusive date range. Expenses
     * are owned by the recording user (`user_id`), matching ExpenseController.
     *
     * @param  array<int, string>  $memberIds
     * @return array<int, array<string, mixed>>
     */
    public static function expensesInRange(array $memberIds, string $from, string $to): array
    {
        $memberIds = array_values(array_filter(array_map('strval', $memberIds)));
        if ($memberIds === []) {
            return [];
        }

        return OfficeExpense::whereIn('user_id', $memberIds)
            ->where('expense_date', '>=', $from)
            ->where('expense_date', '<=', $to)
            ->select(['amount', 'category', 'expense_date', 'user_id'])
            ->limit(self::MAX_SALES)
            ->get()
            ->toArray();
    }

    /**
     * Customers belonging to the business's sellers.
     *
     * @param  array<int, string>  $memberIds
     * @return array<int, array<string, mixed>>
     */
    public static function customersForBusiness(array $memberIds): array
    {
        $memberIds = array_values(array_filter(array_map('strval', $memberIds)));
        if ($memberIds === []) {
            return [];
        }

        return Customer::whereIn('seller_id', $memberIds)
            ->select(['id', 'name', 'total_purchases', 'purchases_count', 'created_at', 'seller_id'])
            ->limit(self::MAX_PRODUCTS)
            ->get()
            ->toArray();
    }
}
