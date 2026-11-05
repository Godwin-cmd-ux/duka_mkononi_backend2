<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\ServiceSale;
use Illuminate\Http\Request;

class RevenueController extends BaseController
{
    private function jsIso(): string
    {
        return gmdate('Y-m-d\TH:i:s.v\Z');
    }

    public function my(Request $request)
    {
        try {
            $userId = $this->userId($request);

            $this->touchLastSeen((string)$userId);

            $today = gmdate('Y-m-d');

            $startOfWeek = \Illuminate\Support\Carbon::now()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
            $endOfWeek = (clone $startOfWeek)->addDays(6);

            $startOfMonth = gmdate('Y-m-01');
            $endOfMonth = (new \DateTime($startOfMonth))->modify('last day of this month')->format('Y-m-d');

            $todayRevenue = Sale::where('seller_id', $userId)->where('sale_date', $today)->get()->sum(function ($sale) {
                return (float)($sale->total_amount ?? 0);
            });

            $weeklyRevenue = Sale::where('seller_id', $userId)
                ->where('sale_date', '>=', $startOfWeek->toDateString())
                ->where('sale_date', '<=', $endOfWeek->toDateString())
                ->get()
                ->sum(function ($sale) {
                    return (float)($sale->total_amount ?? 0);
                });

            $monthlyRevenue = Sale::where('seller_id', $userId)
                ->where('sale_date', '>=', $startOfMonth)
                ->where('sale_date', '<=', $endOfMonth)
                ->get()
                ->sum(function ($sale) {
                    return (float)($sale->total_amount ?? 0);
                });

            $totalRevenue = Sale::where('seller_id', $userId)->get()->sum(function ($sale) {
                return (float)($sale->total_amount ?? 0);
            });

            // Huduma (services) are real revenue too. Revenue is the amount
            // charged for the service; its consumable cost is not deducted here
            // because this endpoint reports turnover, not profit.
            $todayServiceRevenue = (float) ServiceSale::where('seller_id', $userId)->where('sale_date', $today)->sum('selling_price');
            $weeklyServiceRevenue = (float) ServiceSale::where('seller_id', $userId)
                ->where('sale_date', '>=', $startOfWeek->toDateString())
                ->where('sale_date', '<=', $endOfWeek->toDateString())
                ->sum('selling_price');
            $monthlyServiceRevenue = (float) ServiceSale::where('seller_id', $userId)
                ->where('sale_date', '>=', $startOfMonth)
                ->where('sale_date', '<=', $endOfMonth)
                ->sum('selling_price');
            $totalServiceRevenue = (float) ServiceSale::where('seller_id', $userId)->sum('selling_price');

            $todayRevenue += $todayServiceRevenue;
            $weeklyRevenue += $weeklyServiceRevenue;
            $monthlyRevenue += $monthlyServiceRevenue;
            $totalRevenue += $totalServiceRevenue;

            $this->log($userId, 'REVENUE_VIEW', '/api/revenue/my', [
                'today' => $todayRevenue,
                'weekly' => $weeklyRevenue,
                'monthly' => $monthlyRevenue,
                'total' => $totalRevenue,
            ], $this->ip($request), 'success');

            return $this->json([
                'today' => round($todayRevenue, 2),
                'weekly' => round($weeklyRevenue, 2),
                'monthly' => round($monthlyRevenue, 2),
                'total' => round($totalRevenue, 2),
                'service_revenue' => [
                    'today' => round($todayServiceRevenue, 2),
                    'weekly' => round($weeklyServiceRevenue, 2),
                    'monthly' => round($monthlyServiceRevenue, 2),
                    'total' => round($totalServiceRevenue, 2),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'REVENUE_VIEW_ERROR', '/api/revenue/my', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }
}