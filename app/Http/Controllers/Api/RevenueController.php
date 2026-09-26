<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
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
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'REVENUE_VIEW_ERROR', '/api/revenue/my', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }
}