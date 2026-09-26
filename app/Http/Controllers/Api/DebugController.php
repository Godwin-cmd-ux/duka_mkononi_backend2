<?php

namespace App\Http\Controllers\Api;

use App\Models\OfficeExpense;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DebugController extends BaseController
{
    private function requestIp(Request $request): ?string
    {
        return $request->header('x-forwarded-for', '') ?: $request->ip();
    }

    public function checkUser(Request $request, string $email): JsonResponse
    {
        try {
            $users = User::where('email', $email)
                ->select('id', 'email', 'role', 'status')
                ->get()
                ->toArray();

            return $this->json([
                'email' => $email,
                'found' => count($users) > 0,
                'users' => $users,
                'message' => count($users) ? 'User found' : 'User not found',
            ]);
        } catch (Throwable $error) {
            return $this->json(['error' => $error->getMessage()], 500);
        }
    }

    public function checkBusiness(Request $request): JsonResponse
    {
        try {
            $userId = $this->userId($request);

            $user = User::where('id', $userId)
                ->select('id', 'email', 'role', 'business_name', 'status')
                ->first();

            if (!$user) {
                throw new \Exception('User not found');
            }

            $allExpenses = OfficeExpense::where('business_name', $user->business_name)
                ->orderBy('expense_date', 'desc')
                ->get();

            $today = now()->format('Y-m-d');

            $todayExpenses = OfficeExpense::where('business_name', $user->business_name)
                ->where('expense_date', $today)
                ->get();

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'business_name' => $user->business_name,
                    'status' => $user->status,
                ],
                'all_expenses_count' => count($allExpenses),
                'today_expenses_count' => count($todayExpenses),
                'today_expenses_total' => $todayExpenses->sum(fn($e) => $e->amount ?? 0) ?: 0,
                'sample_expenses' => $allExpenses->slice(0, 5)->values()->toArray(),
            ]);
        } catch (Throwable $error) {
            return $this->json(['error' => $error->getMessage()], 500);
        }
    }

    public function expenses(Request $request, string $date): JsonResponse
    {
        try {
            $userId = $this->userId($request);
            $targetDate = $date;

            $user = User::where('id', $userId)
                ->select('business_name', 'id', 'email', 'role')
                ->first();

            if (!$user) {
                return $this->json(['error' => 'User not found'], 400);
            }

            $businessName = $user->business_name;

            $dateExpenses = OfficeExpense::where('business_name', $businessName)
                ->where('expense_date', $targetDate)
                ->get();

            $anyExpenses = OfficeExpense::where('business_name', $businessName)
                ->limit(5)
                ->get();

            $totalCount = OfficeExpense::where('business_name', $businessName)
                ->count();

            $expenseMapper = fn($e) => [
                'id' => $e->id,
                'amount' => $e->amount,
                'description' => $e->description,
                'category' => $e->category,
                'expense_date' => $e->expense_date,
            ];

            $debugInfo = [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'business_name' => $businessName,
                ],
                'requested_date' => $targetDate,
                'expenses_for_date' => array_map($expenseMapper, $dateExpenses->toArray()),
                'count_for_date' => count($dateExpenses),
                'total_for_date' => $dateExpenses->sum(fn($e) => ($e->amount ?? 0) ?: 0) ?: 0,
                'sample_expenses_any_date' => array_map($expenseMapper, $anyExpenses->toArray()),
                'any_expenses_exist' => count($anyExpenses) > 0,
                'total_expenses_in_business' => $totalCount ?: 0,
                'database_query' => [
                    'table' => 'office_expenses',
                    'business_name' => $businessName,
                    'date' => $targetDate,
                ],
                'timestamp' => now()->toISOString(true),
            ];

            return $this->json([
                'success' => true,
                'debug' => $debugInfo,
            ]);
        } catch (Throwable $error) {
            return $this->json([
                'success' => false,
                'error' => $error->getMessage(),
            ], 500);
        }
    }
}