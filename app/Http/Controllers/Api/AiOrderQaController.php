<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\OrderQaService;
use App\Services\OrderService;
use App\Services\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #13 - Order & Delivery Q&A.
 *
 * POST /api/ai/orders/ask
 *
 * Ownership is resolved server-side from the authenticated account's own
 * phone number (the same key the public track flow uses); the caller can
 * never point this endpoint at another customer's orders, and no client-
 * supplied phone is accepted here. Answers only ever contain recorded
 * statuses, timestamps and customer-visible notes - a missing tracking
 * record produces an honest "not found", never a guessed status.
 */
class AiOrderQaController extends BaseController
{
    private const MAX_QUESTION = 400;

    private const MAX_ORDERS_RETURNED = 5;

    private const REFERENCE_PATTERN = '/^DM-\d{8}-[A-Za-z0-9]{6}$/';

    public function ask(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.order_qa', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $question = trim((string) $request->input('question', ''));
        if ($question === '') {
            return $this->json(['error' => 'Tafadhali weka swali.', 'code' => 'QUESTION_REQUIRED'], 422);
        }
        if (mb_strlen($question) > self::MAX_QUESTION) {
            $question = mb_substr($question, 0, self::MAX_QUESTION);
        }

        $locale = $this->resolveLocale($request);

        $reference = strtoupper(trim((string) $request->input('reference', '')));
        if ($reference !== '' && preg_match(self::REFERENCE_PATTERN, $reference) !== 1) {
            return $this->json(['error' => 'Kumbukumbu ya oda si sahihi.', 'code' => 'REFERENCE_INVALID'], 422);
        }
        if ($reference === '') {
            $reference = OrderQaService::extractReference($question) ?? '';
        }

        try {
            /* --- Ownership: the authenticated account's own phone only. --- */
            $accountPhone = null;
            try {
                $user = User::where('id', $userId)->first();
                $accountPhone = $user ? ($user->phone ?? null) : null;
            } catch (Throwable $error) {
                $accountPhone = null;
            }

            if (! PhoneNumber::isValid($accountPhone)) {
                $this->log($userId, 'AI_ORDER_QA_NO_PHONE', '/api/ai/orders/ask', [], $ip, 'warning');

                return $this->json([
                    'success' => true,
                    'capability' => OrderQaService::CAPABILITY,
                    'locale' => $locale,
                    'question' => $question,
                    'orders' => [],
                    'focused_reference' => null,
                    'message' => OrderQaService::noPhoneText($locale),
                    'answer' => $this->unavailableBlock('NO_ACCOUNT_PHONE', $locale),
                    'status_guide' => OrderQaService::statusGuide($locale),
                    'limitations' => OrderQaService::limitations($locale),
                    'generated_at' => now()->toISOString(true),
                ], 422);
            }

            $normalized = PhoneNumber::normalize($accountPhone);

            $orders = Order::where('customer_phone_normalized', $normalized)
                ->orderByDesc('created_at')
                ->get()
                ->toArray();

            if ($orders === []) {
                $this->log($userId, 'AI_ORDER_QA_NO_ORDERS', '/api/ai/orders/ask', [], $ip, 'success');

                return $this->json([
                    'success' => true,
                    'capability' => OrderQaService::CAPABILITY,
                    'locale' => $locale,
                    'question' => $question,
                    'orders' => [],
                    'focused_reference' => null,
                    'message' => OrderQaService::noOrdersText($locale),
                    'answer' => $this->unavailableBlock('NO_ORDERS', $locale),
                    'status_guide' => OrderQaService::statusGuide($locale),
                    'limitations' => OrderQaService::limitations($locale),
                    'generated_at' => now()->toISOString(true),
                ]);
            }

            /* --- Optional narrowing by reference (still the caller's orders). --- */
            if ($reference !== '') {
                $orders = array_values(array_filter(
                    $orders,
                    fn ($order) => mb_strtoupper((string) ($order['order_reference'] ?? '')) === $reference
                ));
                if ($orders === []) {
                    $this->log($userId, 'AI_ORDER_QA_REFERENCE_MISS', '/api/ai/orders/ask', ['reference' => $reference], $ip, 'success');

                    return $this->json([
                        'success' => true,
                        'capability' => OrderQaService::CAPABILITY,
                        'locale' => $locale,
                        'question' => $question,
                        'orders' => [],
                        'focused_reference' => $reference,
                        'message' => OrderQaService::referenceNotFoundText($reference, $locale),
                        'answer' => $this->unavailableBlock('REFERENCE_NOT_FOUND', $locale),
                        'status_guide' => OrderQaService::statusGuide($locale),
                        'limitations' => OrderQaService::limitations($locale),
                        'generated_at' => now()->toISOString(true),
                    ]);
                }
            }

            /* --- Shape the orders exactly like the trusted track flow. --- */
            $allIds = array_column($orders, 'id');
            $itemsByOrder = OrderService::itemsFor($allIds);
            $historyByOrder = $this->customerHistory($allIds);
            $businessNames = $this->businessNames(array_filter(array_column($orders, 'business_id')));

            $shaped = [];
            foreach ($orders as $order) {
                $shaped[] = $this->trackingShape(
                    $order,
                    $itemsByOrder[$order['id']] ?? [],
                    $historyByOrder[$order['id']] ?? [],
                    $businessNames[$order['business_id'] ?? ''] ?? null
                );
            }

            $focused = OrderQaService::focusOrder($shaped, $reference !== '' ? $reference : null);
            if ($focused === null) {
                $focused = $shaped[0] ?? null;
            }

            $answerBlock = $focused !== null
                ? $this->generateAnswer($focused, $question, $locale, $userId, $ip)
                : $this->unavailableBlock('NO_ORDERS', $locale);

            $this->log($userId, 'AI_ORDER_QA', '/api/ai/orders/ask', [
                'orders_found' => count($shaped),
                'focused_reference' => $focused['order_reference'] ?? null,
                'ai_status' => $answerBlock['degraded'] ? 'degraded' : 'ok',
                'ai_code' => $answerBlock['code'],
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, $answerBlock['degraded'] ? 'warning' : 'success');

            return $this->json([
                'success' => true,
                'capability' => OrderQaService::CAPABILITY,
                'locale' => $locale,
                'question' => $question,
                'orders' => array_slice($shaped, 0, self::MAX_ORDERS_RETURNED),
                'focused_reference' => $focused['order_reference'] ?? null,
                'answer' => $answerBlock,
                'status_guide' => OrderQaService::statusGuide($locale),
                'limitations' => OrderQaService::limitations($locale),
                'generated_at' => now()->toISOString(true),
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_ORDER_QA_ERROR', '/api/ai/orders/ask', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Imeshindwa kupata majibu kwa sasa. Tafadhali jaribu tena.',
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'ORDER_QA_ERROR',
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Ask the shared provider to answer from the provided facts only,
     * degrading honestly (and still usefully) when it cannot.
     *
     * @param  array<string, mixed>  $focused
     * @return array{text: string, available: bool, degraded: bool, code: ?string, reason: ?string, meta: ?array}
     */
    private function generateAnswer(array $focused, string $question, string $locale, ?string $userId, ?string $ip): array
    {
        $fallbackText = OrderQaService::fallbackAnswer($focused, $locale);

        $fallback = [
            'text' => $fallbackText,
            'available' => false,
            'degraded' => true,
            'code' => null,
            'reason' => null,
            'meta' => null,
        ];

        $client = new GeminiClient;
        if (! $client->isEnabled()) {
            $fallback['code'] = 'AI_DISABLED';
            $fallback['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }
        if (! $client->isConfigured()) {
            $fallback['code'] = 'AI_NOT_CONFIGURED';
            $fallback['reason'] = AiException::reasonForCode('AI_NOT_CONFIGURED', $locale);

            return $fallback;
        }

        $payload = [
            'locale' => $locale,
            'question' => $question,
            'order' => OrderQaService::contextFor($focused, $locale),
        ];

        $instruction = implode("\n", [
            'You are an order support assistant inside "Duka Mkononi".',
            'You receive ONLY the recorded facts of ONE order the customer owns. Use exactly these facts.',
            'Never invent a status, date, dispatch, delivery, payment or cancellation that is not recorded.',
            'If something is not recorded, say it is not recorded yet.',
            'Answer the question in 2-4 short sentences in this language code: '.$locale.'.',
            'Return STRICT JSON only: {"answer": string}.',
        ]);

        try {
            $result = $client->generateJson(
                [['text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
                $instruction,
                [
                    'capability' => OrderQaService::CAPABILITY,
                    'user_id' => $userId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.2,
                    'maxOutputTokens' => 512,
                ],
            );

            $answer = $result->data['answer'] ?? '';
            $answer = is_string($answer) ? trim(preg_replace('/\s+/u', ' ', $answer) ?? '') : '';
            $answer = mb_substr($answer, 0, 900);
            if ($answer === '') {
                throw new AiException('The AI returned an empty answer.', 'AI_EMPTY_RESPONSE');
            }

            return [
                'text' => $answer,
                'available' => true,
                'degraded' => false,
                'code' => null,
                'reason' => null,
                'meta' => $result->meta(),
            ];
        } catch (AiException $error) {
            $fallback['code'] = $error->errorCode;
            $fallback['reason'] = $error->userReason($locale);

            return $fallback;
        } catch (Throwable $error) {
            $fallback['code'] = 'AI_ERROR';
            $fallback['reason'] = AiException::reasonForCode('AI_ERROR', $locale);

            return $fallback;
        }
    }

    /**
     * Honest empty-answer block for the no-data paths (never calls the model).
     *
     * @return array{text: string, available: bool, degraded: bool, code: ?string, reason: ?string, meta: ?array}
     */
    private function unavailableBlock(string $code, string $locale): array
    {
        $messages = [
            'NO_ACCOUNT_PHONE' => OrderQaService::noPhoneText($locale),
            'NO_ORDERS' => OrderQaService::noOrdersText($locale),
            'REFERENCE_NOT_FOUND' => null, // caller already returned the message
        ];

        return [
            'text' => (string) ($messages[$code] ?? ''),
            'available' => false,
            'degraded' => true,
            'code' => $code,
            'reason' => null,
            'meta' => null,
        ];
    }

    /**
     * Latest customer-visible notes per order (internal notes are never read).
     *
     * @param  array<int, string>  $orderIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function customerHistory(array $orderIds): array
    {
        $orderIds = array_values(array_filter($orderIds));
        if ($orderIds === []) {
            return [];
        }

        $rows = OrderStatusHistory::whereIn('order_id', $orderIds)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        $byOrder = [];
        foreach ($rows as $row) {
            $byOrder[(string) $row['order_id']][] = [
                'status' => $row['status'],
                'note' => $row['note'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $byOrder;
    }

    /**
     * @param  array<int, string>  $businessIds
     * @return array<string, string|null>
     */
    private function businessNames(array $businessIds): array
    {
        $businessIds = array_values(array_unique(array_filter($businessIds)));
        if ($businessIds === []) {
            return [];
        }

        try {
            $rows = Business::whereIn('id', $businessIds)->get()->toArray();
        } catch (Throwable $error) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['id']] = $row['business_name'] ?? null;
        }

        return $map;
    }

    /**
     * Same shape (and the same customer-visible fields) as the public track
     * endpoint, so both flows can never disagree about an order.
     *
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    private function trackingShape(array $order, array $items, array $history, ?string $businessName): array
    {
        $latestNote = null;
        foreach ($history as $entry) {
            if (! empty($entry['note'])) {
                $latestNote = $entry['note'];
                break;
            }
        }

        return [
            'order_reference' => $order['order_reference'] ?? null,
            'order_date' => $order['placed_at'] ?? ($order['created_at'] ?? null),
            'status' => $order['status'] ?? null,
            'subtotal' => (float) ($order['subtotal'] ?? 0),
            'shipping_cost' => (float) ($order['shipping_cost'] ?? 0),
            'total_amount' => (float) ($order['total_amount'] ?? 0),
            'currency' => $order['currency'] ?? 'TZS',
            'business_name' => $businessName,
            'items' => $items,
            'latest_note' => $latestNote,
            'timeline' => array_map(fn ($entry) => [
                'status' => $entry['status'],
                'note' => $entry['note'] ?? null,
                'at' => $entry['created_at'] ?? null,
            ], array_slice($history, 0, 8)),
        ];
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
