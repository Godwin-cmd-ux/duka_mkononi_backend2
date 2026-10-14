<?php

namespace App\Http\Controllers\Api;

use App\Models\OfficeExpense;
use App\Services\Ai\AiException;
use App\Services\Ai\ExpenseParser;
use App\Services\Ai\GeminiClient;
use App\Services\BusinessResolver;
use App\Services\ExpenseCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #7 - Smart Expense Categorisation.
 *
 * POST /api/ai/expenses/parse
 *
 * Read-only: it proposes an editable preview (description, amount, date,
 * category) for the seller to review; the expense itself is only ever saved
 * through the existing POST /api/office-expenses workflow, which keeps its
 * own validation, ownership checks and audit trail - so a parse can never
 * write a record on its own and a double-submit is bounded by that flow.
 *
 * Grounding: amounts and dates are extracted from the seller's own words by
 * trusted code. The model is consulted only when the keyword table has no
 * confident category, and it may only pick from the fixed allow-list or tidy
 * the description - it can never introduce a number the text does not hold.
 */
class AiExpenseParseController extends BaseController
{
    public function parse(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.expense_parse', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $maxText = (int) config('ai.expense_parse.max_text', 600);
        $text = trim((string) $request->input('text', ''));
        if ($text === '') {
            $this->log($userId, 'AI_EXPENSE_PARSE_ERROR', '/api/ai/expenses/parse', [
                'error' => 'TEXT_REQUIRED',
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Tafadhali andika maelezo ya matumizi.',
                'code' => 'TEXT_REQUIRED',
            ], 422);
        }
        if (mb_strlen($text) > $maxText) {
            $text = mb_substr($text, 0, $maxText);
        }

        $locale = $this->resolveLocale($request);
        // Same clock the existing store() writes with, so "today" means the
        // exact date the confirmed expense would be recorded under.
        $today = gmdate('Y-m-d');

        try {
            $base = ExpenseParser::parse($text, $locale, $today);

            $model = null;
            $ai = [
                'available' => false,
                'degraded' => false,
                'code' => null,
                'reason' => null,
                'meta' => null,
            ];

            if ($base['preview']['category'] === null) {
                $ai['degraded'] = true;
                $model = $this->generateCategory($text, $locale, $today, $userId, $ip, $ai);
            } else {
                // Confident keyword match: no model call (cost guard).
                $ai['code'] = 'KEYWORD_MATCH';
            }

            $final = ExpenseParser::parse($text, $locale, $today, $model);

            $flags = [];
            foreach ($final['flags'] as $code) {
                $flags[] = [
                    'code' => $code,
                    'message' => ExpenseParser::flagMessage($code, $locale, ['date' => $today]),
                ];
            }

            $duplicate = $this->findDuplicate(
                $final['preview']['amount'],
                $final['preview']['category'],
                $today,
                $userId
            );

            $this->log($userId, 'AI_EXPENSE_PARSE', '/api/ai/expenses/parse', [
                'amount_found' => $final['preview']['amount'] !== null,
                'category' => $final['preview']['category'],
                'date_found' => $final['preview']['expense_date'] !== null,
                'flags' => $final['flags'],
                'missing_required' => $final['missing_required'],
                'duplicate_found' => $duplicate !== null,
                'ai_status' => ($ai['code'] === 'KEYWORD_MATCH') ? 'keyword'
                    : ($ai['degraded'] ? 'degraded' : 'ok'),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'capability' => ExpenseParser::CAPABILITY,
                'locale' => $locale,
                'generated_at' => now()->toISOString(true),
                'preview' => $final['preview'],
                'flags' => $flags,
                'missing_required' => $final['missing_required'],
                'categories' => ExpenseCategories::all(),
                'possible_duplicate' => $duplicate,
                'ai' => $ai,
                'limitations' => ExpenseParser::limitations($locale),
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_EXPENSE_PARSE_ERROR', '/api/ai/expenses/parse', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Imeshindwa kuchambua maelezo kwa sasa. Tafadhali jaribu tena.',
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'EXPENSE_PARSE_ERROR',
            ], 500);
        }
    }

    /**
     * Ask the model for a category (and tidied description) only when the
     * keyword table had no confident match, degrading honestly when the
     * provider is off, unconfigured or unable to answer.
     *
     * @param  array<string, mixed>  $ai  Updated in place: available/degraded/code/reason/meta.
     */
    private function generateCategory(string $text, string $locale, string $today, ?string $userId, ?string $ip, array &$ai): ?array
    {
        $client = new GeminiClient;

        if (! $client->isEnabled()) {
            $ai['code'] = 'AI_DISABLED';
            $ai['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return null;
        }
        if (! $client->isConfigured()) {
            $ai['code'] = 'AI_NOT_CONFIGURED';
            $ai['reason'] = AiException::reasonForCode('AI_NOT_CONFIGURED', $locale);

            return null;
        }

        try {
            $result = $client->generateJson(
                [['text' => ExpenseParser::userPrompt($text, $today, $locale)]],
                ExpenseParser::systemInstruction(),
                [
                    'capability' => ExpenseParser::CAPABILITY,
                    'user_id' => $userId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.1,
                    'maxOutputTokens' => 1024,
                ],
            );

            $model = ExpenseParser::sanitizeModel($result->data);
            $ai['meta'] = $result->meta();

            if ($model === null) {
                // Nothing usable came back: the deterministic preview stands
                // and the category stays flagged for the seller.
                $ai['code'] = 'AI_INVALID_OUTPUT';
                $ai['reason'] = AiException::reasonForCode('AI_INVALID_JSON', $locale);

                return null;
            }

            $ai['available'] = true;
            $ai['degraded'] = false;
            $ai['code'] = null;
            $ai['reason'] = null;

            return $model;
        } catch (AiException $error) {
            $ai['code'] = $error->errorCode;
            $ai['reason'] = $error->userReason($locale);

            return null;
        } catch (Throwable $error) {
            $ai['code'] = 'AI_ERROR';
            $ai['reason'] = AiException::reasonForCode('AI_ERROR', $locale);

            return null;
        }
    }

    /**
     * Warn (never block) when a nearly identical expense was already
     * recorded for this business in the look-back window.
     */
    private function findDuplicate(?float $amount, ?string $category, string $today, ?string $userId): ?array
    {
        if ($amount === null || ! $userId) {
            return null;
        }

        try {
            $businessId = BusinessResolver::idForUser($userId);
            if (! $businessId) {
                return null;
            }

            $memberIds = BusinessResolver::memberIds($businessId);
            if (! is_array($memberIds) || $memberIds === []) {
                $memberIds = [$userId];
            }

            $days = max(0, (int) config('ai.expense_parse.duplicate_window_days', 2));
            $window = gmdate('Y-m-d', time() - ($days * 86400));

            $query = OfficeExpense::whereIn('user_id', $memberIds)
                ->where('expense_date', '>=', $window)
                ->where('expense_date', '<=', $today)
                ->where('amount', '>=', $amount - 0.005)
                ->where('amount', '<=', $amount + 0.005);

            if ($category !== null && $category !== '') {
                $query->where('category', $category);
            }

            $row = $query->orderByDesc('created_at')->first();
            if (! $row) {
                return null;
            }

            return [
                'id' => $row['id'] ?? null,
                'amount' => (float) ($row['amount'] ?? 0),
                'description' => (string) ($row['description'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'expense_date' => (string) ($row['expense_date'] ?? ''),
            ];
        } catch (Throwable $error) {
            // A duplicate warning must never break the parse itself.
            return null;
        }
    }

    private function resolveLocale(Request $request): string
    {
        $supported = (array) config('ai.locales', ['sw']);
        $candidate = strtolower(trim((string) $request->input('locale', '')));

        if ($candidate === '' || ! in_array($candidate, $supported, true)) {
            $candidate = (string) config('ai.default_locale', 'sw');
        }

        return in_array($candidate, $supported, true) ? $candidate : 'sw';
    }
}
