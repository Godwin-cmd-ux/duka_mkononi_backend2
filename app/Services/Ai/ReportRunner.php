<?php

namespace App\Services\Ai;

/**
 * Executes an allow-listed report over the business's own rows.
 *
 * This is the body that used to live in AiReportController::runReport,
 * extracted so the Seller Copilot (capability #10) runs exactly the same
 * trusted computation instead of duplicating it. It is pure orchestration
 * over BusinessDataProvider / BusinessReporter: no model, no HTTP, no writes,
 * and every row is scoped by the caller-supplied member ids (which come from
 * the authenticated user's business, never from client input).
 */
class ReportRunner
{
    /**
     * @param  array{tool: ?string, metric: string, range: ?array{from: string, to: string}|null, range_source?: string, compare: bool, category_filter: ?string, limit: int}  $parsed
     * @param  array<int, string>  $memberIds
     * @return array{data: array<string, mixed>, table: ?array<string, mixed>, chart: ?array<string, mixed>, comparison: ?array<string, mixed>, sources: array<int, string>, limitations: array<int, string>, range: ?array<string, string>, previous_range: ?array<string, string>}
     */
    public static function run(array $parsed, array $memberIds, string $locale, string $currency): array
    {
        $tool = $parsed['tool'];
        $range = $parsed['range'];

        $products = [];
        $productIndex = [];
        foreach (BusinessDataProvider::productsForBusiness($memberIds) as $product) {
            $products[] = $product;
            if (! empty($product['id'])) {
                $productIndex[(string) $product['id']] = $product;
            }
        }

        $base = [
            'data' => [],
            'table' => null,
            'chart' => null,
            'comparison' => null,
            'sources' => [],
            'limitations' => [],
            'range' => $range,
            'previous_range' => null,
        ];

        if ($tool === null) {
            $base['limitations'][] = 'Sikuweza kuelewa swali. Tafadhali uliza kuhusu mauzo, faida, bidhaa zinazoongoza, matumizi, wateja au thamani ya stock.';
            $base['data'] = ['supported_examples' => ReportIntentParser::supportedExamples($locale)];

            return $base;
        }

        if ($tool === 'stock_value') {
            $base['sources'] = ['products'];
            $base['data'] = BusinessReporter::stockValue($productIndex, $currency);
            if (($base['data']['missing_cost_count'] ?? 0) > 0) {
                $base['limitations'][] = 'Bidhaa '.$base['data']['missing_cost_count'].' hazina bei ya kununua, hivyo thamani haijakamilika.';
            }

            return $base;
        }

        if ($range === null) {
            $range = ReportIntentParser::resolveRange('this_month', date('Y-m-d'));
            $base['range'] = $range;
        }
        if (($parsed['range_source'] ?? 'none') === 'default') {
            $base['limitations'][] = 'Hakuna kipindi kilichotajwa, hivyo nimetumia mwezi huu.';
        }

        switch ($tool) {
            case 'revenue_summary':
                $sales = BusinessDataProvider::salesInRange($memberIds, $range['from'], $range['to']);
                $items = BusinessDataProvider::saleItemsInRange($memberIds, $range['from'], $range['to']);
                $data = BusinessReporter::revenueSummary($sales, $items, $productIndex, $currency);
                $base['sources'] = ['sales', 'sale_items', 'products'];
                $base['data'] = $data;
                if ($data['unknown_cogs_items'] > 0) {
                    $base['limitations'][] = 'Faida haijakamilika: bidhaa '.$data['unknown_cogs_items'].' zilizouzwa hazina bei ya kununua.';
                }

                if ($parsed['compare']) {
                    $prevRange = ReportIntentParser::previousRange($range);
                    $prevSales = BusinessDataProvider::salesInRange($memberIds, $prevRange['from'], $prevRange['to']);
                    $prevItems = BusinessDataProvider::saleItemsInRange($memberIds, $prevRange['from'], $prevRange['to']);
                    $prev = BusinessReporter::revenueSummary($prevSales, $prevItems, $productIndex, $currency);
                    $base['previous_range'] = $prevRange;
                    $base['comparison'] = BusinessReporter::compare($data, $prev);
                }
                break;

            case 'top_products':
                $items = BusinessDataProvider::saleItemsInRange($memberIds, $range['from'], $range['to']);
                $data = BusinessReporter::productBreakdown($items, $productIndex, $parsed['metric'], $parsed['limit'], $currency);
                $base['sources'] = ['sales', 'sale_items', 'products'];
                $base['data'] = $data;
                $base['table'] = [
                    'columns' => ['name', 'units', 'revenue', 'profit'],
                    'rows' => array_map(fn ($row) => [$row['name'], $row['units'], $row['revenue'], $row['profit']], $data['items']),
                ];
                $base['chart'] = [
                    'type' => 'bar',
                    'labels' => array_map(fn ($row) => $row['name'], $data['items']),
                    'values' => array_map(fn ($row) => $row[$data['metric']] ?? 0, $data['items']),
                    'metric' => $data['metric'],
                ];
                if ($data['metric'] === 'profit' && in_array(null, array_column($data['items'], 'profit'), true)) {
                    $base['limitations'][] = 'Faida haijakamilika kwa baadhi ya bidhaa (bei ya kununua haipo).';
                }
                break;

            case 'sales_comparison':
                $prevRange = ReportIntentParser::previousRange($range);
                $sales = BusinessDataProvider::salesInRange($memberIds, $range['from'], $range['to']);
                $items = BusinessDataProvider::saleItemsInRange($memberIds, $range['from'], $range['to']);
                $prevSales = BusinessDataProvider::salesInRange($memberIds, $prevRange['from'], $prevRange['to']);
                $prevItems = BusinessDataProvider::saleItemsInRange($memberIds, $prevRange['from'], $prevRange['to']);
                $current = BusinessReporter::revenueSummary($sales, $items, $productIndex, $currency);
                $previous = BusinessReporter::revenueSummary($prevSales, $prevItems, $productIndex, $currency);
                $comparison = BusinessReporter::compare($current, $previous);
                $base['sources'] = ['sales', 'sale_items', 'products'];
                $base['previous_range'] = $prevRange;
                $base['comparison'] = $comparison;
                $base['data'] = [
                    'currency' => $currency,
                    'revenue' => $current['revenue'],
                    'previous_revenue' => $previous['revenue'],
                    'revenue_change_pct' => $comparison['revenue']['change_pct'],
                    'sales_count' => $current['sales_count'],
                    'previous_sales_count' => $previous['sales_count'],
                    'gross_profit' => $current['gross_profit'],
                    'previous_gross_profit' => $previous['gross_profit'],
                ];
                $base['chart'] = [
                    'type' => 'bar',
                    'labels' => ['current', 'previous'],
                    'values' => [$current['revenue'], $previous['revenue']],
                    'metric' => 'revenue',
                ];
                break;

            case 'expenses':
                $expenses = BusinessDataProvider::expensesInRange($memberIds, $range['from'], $range['to']);
                $data = BusinessReporter::expenseSummary($expenses, $parsed['category_filter'], $currency);
                $base['sources'] = ['office_expenses'];
                $base['data'] = $data;
                $base['table'] = [
                    'columns' => ['category', 'amount'],
                    'rows' => array_map(fn ($row) => [$row['category'], $row['amount']], $data['by_category']),
                ];
                $base['chart'] = [
                    'type' => 'bar',
                    'labels' => array_map(fn ($row) => $row['category'], array_slice($data['by_category'], 0, 6)),
                    'values' => array_map(fn ($row) => $row['amount'], array_slice($data['by_category'], 0, 6)),
                    'metric' => 'expenses',
                ];
                if ($data['category_filter'] !== null && $data['category_matched'] === false) {
                    $base['limitations'][] = 'Hakuna matumizi ya aina "'.$data['category_filter'].'" kwenye kipindi hiki.';
                }
                break;

            case 'customers':
                $customers = BusinessDataProvider::customersForBusiness($memberIds);
                $data = BusinessReporter::customerSummary($customers, $range);
                $base['sources'] = ['customers'];
                $base['data'] = $data;
                $base['table'] = [
                    'columns' => ['name', 'total_purchases', 'purchases_count'],
                    'rows' => array_map(fn ($row) => [$row['name'], $row['total_purchases'], $row['purchases_count']], $data['top_customers']),
                ];
                break;

            default:
                $base['limitations'][] = 'Kipengele hiki hakijatekelezwa.';
                break;
        }

        return $base;
    }
}
