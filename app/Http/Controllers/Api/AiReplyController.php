<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\ReplyDrafter;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #9 - Customer-Reply Drafting.
 *
 * POST /api/ai/replies/draft
 *
 * Read-only drafting endpoint: it drafts the seller's reply to one customer
 * order question, grounded in that order's OWN records (reference, status,
 * items, real prices/totals, the customer's question and the seller's already
 * customer-visible notes). It never writes to `orders` or
 * `order_status_history` and never sends anything to the customer - the draft
 * only reaches the customer when the seller edits it and presses the normal
 * save button (PUT seller/orders/{id}/status or POST seller/orders/{id}/notes),
 * which keeps its own validation, ownership checks and audit trail.
 *
 * Grounding: money-scale numbers must exist on the order, delivery-time
 * promises and discount/refund sentences are stripped unless the seller
 * already recorded them, and length is validated against the configured cap
 * (ReplyDrafter::sanitize). Internal notes are never read here.
 */
class AiReplyController extends BaseController
{
    public function draft(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.reply_draft', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $orderId = trim((string) $request->input('order_id', ''));
        if ($orderId === '') {
            return $this->json(['error' => 'Chagua oda kwanza.', 'code' => 'ORDER_REQUIRED'], 422);
        }
        if (! OrderService::isUuid($orderId)) {
            return $this->json(['error' => 'Kitambulisho cha oda si sahihi.', 'code' => 'INVALID_ORDER_ID'], 422);
        }

        $tone = (string) $request->input('tone', 'casual');
        if (! in_array($tone, ReplyDrafter::TONES, true)) {
            return $this->json(['error' => 'Mtindo (tone) si sahihi.', 'code' => 'INVALID_TONE'], 422);
        }

        $locale = $this->resolveLocale($request);

        $order = $this->scopedOrder($request, $orderId);
        if ($order === null) {
            $this->log($userId, 'AI_REPLY_DRAFT_ERROR', '/api/ai/replies/draft', [
                'error' => 'ORDER_NOT_FOUND',
                'order_id' => $orderId,
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Oda haipatikani kwenye biashara yako.',
                'code' => 'ORDER_NOT_FOUND',
            ], 404);
        }

        $controls = ['tone' => $tone];

        $ai = [
            'available' => false,
            'degraded' => false,
            'code' => null,
            'reason' => null,
            'meta' => null,
        ];
        $san = [
            'ok' => false,
            'text' => '',
            'flags' => [],
            'code' => null,
            'reason' => null,
        ];

        $client = new GeminiClient;

        if (! $client->isEnabled()) {
            $ai['degraded'] = true;
            $ai['code'] = 'AI_DISABLED';
            $ai['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);
        } elseif (! $client->isConfigured()) {
            $ai['degraded'] = true;
            $ai['code'] = 'AI_NOT_CONFIGURED';
            $ai['reason'] = AiException::reasonForCode('AI_NOT_CONFIGURED', $locale);
        } else {
            try {
                $result = $client->generateJson(
                    [['text' => ReplyDrafter::userPrompt($locale, $order, $controls)]],
                    ReplyDrafter::systemInstruction(),
                    [
                        'capability' => ReplyDrafter::CAPABILITY,
                        'user_id' => $userId,
                        'ip' => $ip,
                        'locale' => $locale,
                        'temperature' => 0.5,
                        'maxOutputTokens' => 1024,
                    ],
                );

                $ai['meta'] = $result->meta();
                $data = is_array($result->data) ? $result->data : null;
                $san = ReplyDrafter::sanitize($data, $order, $controls, $locale);

                if ($san['ok']) {
                    $ai['available'] = true;
                } else {
                    // A rejected or empty draft degrades honestly with the
                    // localized reason - the seller can retry or write it
                    // themselves.
                    $ai['degraded'] = true;
                    $ai['code'] = $san['code'];
                    $ai['reason'] = $san['reason'];
                }
            } catch (AiException $error) {
                $ai['degraded'] = true;
                $ai['code'] = $error->errorCode;
                $ai['reason'] = $error->userReason($locale);
            } catch (Throwable $error) {
                $ai['degraded'] = true;
                $ai['code'] = 'AI_ERROR';
                $ai['reason'] = AiException::reasonForCode('AI_ERROR', $locale);
            }
        }

        $duplicate = $san['ok'] ? ReplyDrafter::duplicateOf($san['text'], $order) : null;

        $this->log($userId, 'AI_REPLY_DRAFT', '/api/ai/replies/draft', [
            'order_id' => $orderId,
            'tone' => $tone,
            'flag_codes' => array_column($san['flags'], 'code'),
            'draft_ok' => (bool) $san['ok'],
            'ai_code' => $ai['code'],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ], $ip, $san['ok'] || $ai['degraded'] ? 'success' : 'failed');

        return $this->json([
            'success' => true,
            'capability' => ReplyDrafter::CAPABILITY,
            'locale' => $locale,
            'tone' => $tone,
            'generated_at' => now()->toISOString(true),
            'order' => [
                'id' => (string) ($order['id'] ?? ''),
                'order_reference' => (string) ($order['order_reference'] ?? ''),
                'status' => (string) ($order['status'] ?? ''),
                'payment_status' => (string) ($order['payment_status'] ?? ''),
            ],
            'customer_question' => (string) ($order['customer_note'] ?? ''),
            'draft' => $san['text'],
            'flags' => $san['flags'],
            'missing' => ReplyDrafter::missingInfo($order, $locale),
            'recent_reply' => $duplicate,
            'limits' => ['max_chars' => ReplyDrafter::maxChars()],
            'ai' => $ai,
            'limitations' => ReplyDrafter::limitations($locale),
        ]);
    }

    /**
     * Ownership-scoped order lookup: platform admins may access any order;
     * everyone else only an order of their own business. Internal notes are
     * deliberately excluded from the selected columns - they are private to
     * the seller and never needed for a customer reply.
     *
     * @return array<string, mixed>|null
     */
    private function scopedOrder(Request $request, string $orderId): ?array
    {
        try {
            $role = $this->role($request);

            $query = Order::where('id', $orderId)->select([
                'id', 'business_id', 'order_reference', 'status', 'payment_status',
                'customer_name', 'customer_note', 'customer_visible_note',
                'subtotal', 'shipping_cost', 'total_amount', 'currency',
                'placed_at', 'created_at', 'updated_at',
            ]);

            $order = $query->first();
            if (! $order) {
                return null;
            }

            if ($role !== 'system_admin') {
                $businessId = $this->businessId($request);
                if (! $businessId || $order->business_id !== $businessId) {
                    return null;
                }
            }

            $data = $order->toArray();

            $data['items'] = OrderService::itemsFor([$orderId])[$orderId] ?? [];

            // Customer-visible notes, oldest first; the draft may restate
            // these but never the seller's internal notes.
            $rows = OrderStatusHistory::where('order_id', $orderId)
                ->orderBy('created_at')
                ->get(['note', 'created_at'])
                ->toArray();

            $visible = [];
            $lastAt = null;
            foreach ($rows as $row) {
                $note = trim((string) ($row['note'] ?? ''));
                if ($note !== '') {
                    $visible[] = $note;
                    $lastAt = $row['created_at'] ?? $lastAt;
                }
            }

            $current = trim((string) ($data['customer_visible_note'] ?? ''));
            if ($current !== '' && ($visible === [] || end($visible) !== $current)) {
                $visible[] = $current;
                $lastAt = $data['updated_at'] ?? $lastAt;
            }

            $data['visible_notes'] = $visible;
            $data['last_visible_note_at'] = $lastAt;

            return $data;
        } catch (Throwable) {
            return null;
        }
    }
}
