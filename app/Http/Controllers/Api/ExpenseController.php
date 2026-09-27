<?php

namespace App\Http\Controllers\Api;

use App\Models\OfficeExpense;
use App\Models\User;
use App\Services\BusinessResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseController extends BaseController
{
    private function formatExpense($exp, ?string $fallbackDate = null): array
    {
        return [
            'id' => $exp['id'],
            'amount' => (float)($exp['amount'] ?? 0),
            'description' => $exp['description'] ?? '',
            'category' => !empty($exp['category']) ? $exp['category'] : 'Mengineyo',
            'expense_date' => $fallbackDate ? (!empty($exp['expense_date']) ? $exp['expense_date'] : $fallbackDate) : ($exp['expense_date'] ?? null),
            'notes' => $exp['notes'] ?? '',
            'user_id' => $exp['user_id'] ?? null,
            'business_name' => $exp['business_name'] ?? null,
            'created_at' => $exp['created_at'] ?? null,
        ];
    }

    public function today(Request $request)
    {
        try {
            $userId = $this->userId($request);
            $today = gmdate('Y-m-d');

            $user = User::where('id', $userId)->select('business_name')->first();

            if (!$user || empty($user->business_name)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Biashara haijapatikana',
                ], 400);
            }

            $businessName = $user->business_name;

            $expenses = OfficeExpense::where('business_name', $businessName)
                ->where('expense_date', $today)
                ->orderByDesc('created_at')
                ->get()
                ->toArray();

            $total = array_reduce($expenses, function ($sum, $exp) {
                return $sum + (float)($exp['amount'] ?? 0);
            }, 0);

            $formattedExpenses = array_map(fn($exp) => $this->formatExpense($exp), $expenses);

            return $this->json([
                'success' => true,
                'expenses' => array_values($formattedExpenses),
                'total' => $total,
                'date' => $today,
                'count' => count($formattedExpenses),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Hitilafu ya kupata matumizi',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $userId = $this->userId($request);
            $amount = $request->input('amount');
            $description = $request->input('description');
            $category = $request->input('category');
            $notes = $request->input('notes');

            if (!$amount || !$description || !$category) {
                return $this->json([
                    'success' => false,
                    'error' => 'Kiasi, maelezo na aina ya matumizi vinahitajika',
                ], 400);
            }

            $amountValue = (float)$amount;
            if (is_nan($amountValue) || $amountValue <= 0) {
                return $this->json([
                    'success' => false,
                    'error' => 'Kiasi lazima kiwe namba kubwa kuliko 0',
                ], 400);
            }

            if (strlen(trim($description)) < 3) {
                return $this->json([
                    'success' => false,
                    'error' => 'Maelezo lazima yawe na herufi 3 au zaidi',
                ], 400);
            }

            $user = User::where('id', $userId)->select('business_name')->first();

            if (!$user || empty($user->business_name)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Biashara haijapatikana',
                ], 400);
            }

            $businessName = $user->business_name;
            $today = gmdate('Y-m-d');

            $expenseData = [
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'business_name' => $businessName,
                'expense_date' => $today,
                'amount' => $amountValue,
                'description' => trim($description),
                'category' => $category,
                'notes' => $notes ? trim($notes) : null,
                'created_at' => $this->isoNow(),
                'updated_at' => $this->isoNow(),
            ];

            $newExpense = OfficeExpense::create($expenseData);

            $this->log($userId, 'ADD_EXPENSE', '/api/office-expenses', [
                'expense_id' => $newExpense->id,
                'amount' => $amountValue,
                'category' => $category,
                'business_name' => $businessName,
                'date' => $today,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Matumizi yameongezwa kikamilifu!',
                'expense' => [
                    'id' => $newExpense->id,
                    'amount' => (float)$newExpense->amount,
                    'description' => $newExpense->description,
                    'category' => $newExpense->category,
                    'expense_date' => $newExpense->expense_date,
                    'notes' => $newExpense->notes,
                    'user_id' => $newExpense->user_id,
                    'business_name' => $newExpense->business_name,
                ],
            ], 201);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'ADD_EXPENSE_ERROR', '/api/office-expenses', [
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'success' => false,
                'error' => 'Hitilafu ya kuongeza matumizi',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function byDate(Request $request, string $date)
    {
        try {
            $userId = $this->userId($request);
            $targetDate = $date;

            if (!$targetDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Tarehe si sahihi. Tumia format: YYYY-MM-DD',
                ], 400);
            }

            $user = User::where('id', $userId)->select('business_name', 'id', 'email', 'role')->first();

            if (!$user) {
                return $this->json([
                    'success' => false,
                    'error' => 'Mtumiaji hajapatikana',
                ], 400);
            }

            $businessName = $user->business_name;

            if (!$businessName) {
                return $this->json([
                    'success' => false,
                    'error' => 'Biashara haijapatikana kwenye akaunti yako',
                ], 400);
            }

            $expenses = OfficeExpense::where('business_name', $businessName)
                ->where('expense_date', $targetDate)
                ->orderByDesc('created_at')
                ->get()
                ->toArray();

            $total = array_reduce($expenses, function ($sum, $exp) {
                return $sum + (float)($exp['amount'] ?? 0);
            }, 0);

            $formattedExpenses = array_map(fn($exp) => $this->formatExpense($exp, $targetDate), $expenses);

            return $this->json([
                'success' => true,
                'expenses' => array_values($formattedExpenses),
                'total' => $total,
                'date' => $targetDate,
                'count' => count($formattedExpenses),
                'business_name' => $businessName,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Hitilafu ya kupata matumizi',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function range(Request $request)
    {
        try {
            $userId = $this->userId($request);
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            if (!$startDate || !$endDate) {
                return $this->json([
                    'success' => false,
                    'error' => 'Tarehe za kuanza na mwisho zinahitajika',
                ], 400);
            }

            $businessId = $this->businessId($request) ?: BusinessResolver::idForUser($userId);

            if (!$businessId) {
                return $this->json([
                    'success' => false,
                    'error' => 'Biashara haijapatikana',
                ], 400);
            }

            // office_expenses has no business_id, so the only reliable link is
            // the user_id that recorded the row. The previous
            // `where('business_name', $user->business_name)` match meant an
            // expense was invisible the moment the business name was respelled:
            // three Jerald expenses are stored as "Jerald Stationary" while
            // both members' users.business_name reads "Jerald Stationari", so
            // the report returned nothing at all for that business.
            // Membership is also the same scope the sales/products totals this
            // report nets against are already using.
            $memberIds = BusinessResolver::memberIds($businessId);

            $query = OfficeExpense::whereIn('user_id', $memberIds ?: ['00000000-0000-0000-0000-000000000000'])
                ->orderByDesc('expense_date');

            if ($startDate) {
                $query->where('expense_date', '>=', $startDate);
            }

            if ($endDate) {
                $query->where('expense_date', '<=', $endDate);
            }

            $expenses = $query->get()->toArray();

            $total = array_reduce($expenses, function ($sum, $exp) {
                return $sum + (float)($exp['amount'] ?? 0);
            }, 0);

            $byCategory = [];
            foreach ($expenses as $exp) {
                $cat = !empty($exp['category']) ? $exp['category'] : 'Mengineyo';
                $byCategory[$cat] = ($byCategory[$cat] ?? 0) + (float)($exp['amount'] ?? 0);
            }

            $byDate = [];
            foreach ($expenses as $exp) {
                $date = $exp['expense_date'];
                if (!isset($byDate[$date])) {
                    $byDate[$date] = [];
                }
                $byDate[$date][] = $exp;
            }

            $formattedExpenses = array_map(fn($exp) => $this->formatExpense($exp), $expenses);

            return $this->json([
                'success' => true,
                'expenses' => array_values($formattedExpenses),
                'total' => $total,
                'by_category' => $byCategory,
                'by_date' => $byDate,
                'count' => count($formattedExpenses),
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Hitilafu ya kupata matumizi',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function categories(Request $request)
    {
        $categories = [
            'Kodisho za Nguvu',
            'Maji',
            'Mawasiliano',
            'Usafiri',
            'Chakula cha Mchana',
            'Vifaa vya Ofisi',
            'Matengenezo',
            'Ushuru',
            'Ada za Benki',
            'Malighafi',
            'Ufungashaji',
            'Mengineyo',
        ];

        return $this->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $userId = $this->userId($request);
            $expenseId = $id;

            $user = User::where('id', $userId)->select('business_name', 'role')->first();

            if (!$user) {
                return $this->json([
                    'success' => false,
                    'error' => 'Mtumiaji hajapatikana',
                ], 400);
            }

            $businessName = $user->business_name;
            $userRole = $user->role;

            $expense = OfficeExpense::where('id', $expenseId)->select('id', 'amount', 'description', 'business_name', 'user_id')->first();

            if (!$expense) {
                return $this->json([
                    'success' => false,
                    'error' => 'Matumizi hayajapatikana',
                ], 404);
            }

            if ($expense->business_name !== $businessName && $userRole !== 'admin') {
                return $this->json([
                    'success' => false,
                    'error' => 'Huna ruhusa ya kufuta matumizi haya',
                ], 403);
            }

            OfficeExpense::where('id', $expenseId)->delete();

            $this->log($userId, 'DELETE_EXPENSE', '/api/office-expenses/' . $expenseId, [
                'expense_id' => $expenseId,
                'amount' => $expense->amount,
                'description' => $expense->description,
                'business_name' => $businessName,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Matumizi yamefutwa kikamilifu!',
                'expenseId' => $expenseId,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Hitilafu ya kufuta matumizi',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}