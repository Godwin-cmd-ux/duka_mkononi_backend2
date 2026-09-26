<?php

namespace App\Http\Controllers\Api;

use App\Models\OfficeExpense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Http\Request;

class ProfitController extends BaseController
{
    public function daily(Request $request, ?string $date = null)
    {
        try {
            $userId = $this->userId($request);
            $targetDate = $date ?: gmdate('Y-m-d');

            $user = User::where('id', $userId)->select('business_name')->first();

            if (!$user || empty($user->business_name)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Biashara haijapatikana',
                ], 400);
            }

            $businessName = $user->business_name;

            $businessUsers = User::where('business_name', $businessName)->where('status', 'approved')->select('id')->pluck('id')->all();
            $userIds = $businessUsers ?: [];

            $grossRevenue = 0;
            $costOfGoods = 0;

            if (count($userIds) > 0) {
                $sales = Sale::whereIn('seller_id', $userIds)->where('sale_date', $targetDate)->get();

                $saleIds = $sales->pluck('id')->all();
                $items = [];
                $productIds = [];
                if ($saleIds) {
                    foreach (SaleItem::whereIn('sale_id', $saleIds)->get() as $item) {
                        $items[] = $item;
                        if (!empty($item->product_id)) {
                            $productIds[$item->product_id] = true;
                        }
                    }
                }

                $productsById = [];
                $productKeyIds = array_keys($productIds);
                if ($productKeyIds) {
                    foreach (Product::whereIn('id', $productKeyIds)->get() as $p) {
                        $productsById[$p->id] = $p;
                    }
                }

                foreach ($sales as $sale) {
                    $grossRevenue += (float)($sale->total_amount ?? 0);
                }

                foreach ($items as $item) {
                    $costPrice = isset($productsById[$item->product_id]) ? (float)($productsById[$item->product_id]->cost_price ?? 0) : 0;
                    $costOfGoods += (float)($item->quantity ?? 0) * $costPrice;
                }
            }

            $grossProfit = $grossRevenue - $costOfGoods;

            $expenses = OfficeExpense::where('business_name', $businessName)->where('expense_date', $targetDate)->select('amount', 'category', 'description', 'user_id')->get();

            $totalExpenses = $expenses->sum(function ($exp) {
                return (float)($exp->amount ?? 0);
            });

            $netProfit = $grossProfit - $totalExpenses;

            $expensesByCategory = [];
            foreach ($expenses as $exp) {
                $cat = $exp->category ?: 'Mengineyo';
                $expensesByCategory[$cat] = ($expensesByCategory[$cat] ?? 0) + (float)($exp->amount ?? 0);
            }

            $creatorIds = $expenses->pluck('user_id')->filter()->unique()->all();
            $usersById = [];
            if ($creatorIds) {
                foreach (User::whereIn('id', $creatorIds)->select('full_name', 'email')->get() as $creator) {
                    $usersById[$creator->id] = $creator;
                }
            }

            $expensesWithCreators = [];
            foreach ($expenses as $exp) {
                $expArr = $exp->toArray();
                if (!empty($exp->user_id) && isset($usersById[$exp->user_id])) {
                    $expArr['creator_name'] = $usersById[$exp->user_id]->full_name ?: $usersById[$exp->user_id]->email ?: 'Unknown';
                }
                $expensesWithCreators[] = $expArr;
            }

            return $this->json([
                'success' => true,
                'date' => $targetDate,
                'business_name' => $businessName,
                'revenue' => [
                    'gross' => round($grossRevenue, 2),
                    'cost_of_goods' => round($costOfGoods, 2),
                ],
                'gross_profit' => round($grossProfit, 2),
                'expenses' => [
                    'total' => round($totalExpenses, 2),
                    'by_category' => $expensesByCategory ?: new \stdClass(),
                    'list' => $expensesWithCreators,
                ],
                'net_profit' => round($netProfit, 2),
                'sales_count' => 0,
                'expenses_count' => $expenses->count(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Hitilafu ya kuhesabu faida ya siku',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function monthly(Request $request, string $year, string $month)
    {
        try {
            $userId = $this->userId($request);

            $user = User::where('id', $userId)->select('business_name')->first();

            if (!$user || empty($user->business_name)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Biashara haijapatikana',
                ], 400);
            }

            $businessName = $user->business_name;

            $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
            $endDate = date('Y-m-t', mktime(0, 0, 0, (int)$month, 1, (int)$year));

            $businessUsers = User::where('business_name', $businessName)->where('status', 'approved')->select('id')->pluck('id')->all();
            $userIds = $businessUsers ?: [];

            $totalRevenue = 0;
            $totalCostOfGoods = 0;

            if (count($userIds) > 0) {
                $sales = Sale::whereIn('seller_id', $userIds)->where('sale_date', '>=', $startDate)->where('sale_date', '<=', $endDate)->get();

                $saleIds = $sales->pluck('id')->all();
                $items = [];
                $productIds = [];
                if ($saleIds) {
                    foreach (SaleItem::whereIn('sale_id', $saleIds)->get() as $item) {
                        $items[] = $item;
                        if (!empty($item->product_id)) {
                            $productIds[$item->product_id] = true;
                        }
                    }
                }

                $productsById = [];
                $productKeyIds = array_keys($productIds);
                if ($productKeyIds) {
                    foreach (Product::whereIn('id', $productKeyIds)->get() as $p) {
                        $productsById[$p->id] = $p;
                    }
                }

                foreach ($sales as $sale) {
                    $totalRevenue += (float)($sale->total_amount ?? 0);
                }

                foreach ($items as $item) {
                    $costPrice = isset($productsById[$item->product_id]) ? (float)($productsById[$item->product_id]->cost_price ?? 0) : 0;
                    $totalCostOfGoods += (float)($item->quantity ?? 0) * $costPrice;
                }
            }

            $expenses = OfficeExpense::where('business_name', $businessName)
                ->where('expense_date', '>=', $startDate)
                ->where('expense_date', '<=', $endDate)
                ->select('amount', 'expense_date', 'category')
                ->get();

            $grossProfit = $totalRevenue - $totalCostOfGoods;
            $totalExpenses = $expenses->sum(function ($exp) {
                return (float)($exp->amount ?? 0);
            });
            $netProfit = $grossProfit - $totalExpenses;

            $expenseCategories = [];
            foreach ($expenses as $exp) {
                $cat = $exp->category ?: 'Mengineyo';
                $expenseCategories[$cat] = ($expenseCategories[$cat] ?? 0) + (float)($exp->amount ?? 0);
            }

            $dailyBreakdown = new \stdClass();

            return $this->json([
                'success' => true,
                'year' => (int)$year,
                'month' => (int)$month,
                'business_name' => $businessName,
                'summary' => [
                    'total_sales' => 0,
                    'total_revenue' => round($totalRevenue, 2),
                    'total_cost_of_goods' => round($totalCostOfGoods, 2),
                    'gross_profit' => round($grossProfit, 2),
                    'total_expenses' => round($totalExpenses, 2),
                    'net_profit' => round($netProfit, 2),
                    'expense_categories' => $expenseCategories ?: new \stdClass(),
                ],
                'daily_breakdown' => $dailyBreakdown,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Hitilafu ya kuhesabu faida ya mwezi',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}