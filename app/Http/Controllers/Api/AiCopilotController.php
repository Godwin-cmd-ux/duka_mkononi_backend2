<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\SellerCopilot;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #10 - Seller Copilot Q&A.
 *
 * POST   /api/ai/copilot/chat                - one conversational turn
 * GET    /api/ai/copilot/conversations       - the caller's recent conversations
 * GET    /api/ai/copilot/conversations/{id}  - one conversation's messages
 * DELETE /api/ai/copilot/conversations/{id}  - delete one conversation
 * DELETE /api/ai/copilot/conversations       - delete every conversation
 *
 * All routes live inside the auth.jwt group. Business and member ids are
 * resolved server-side on every call and every conversation query filters on
 * business_id AND user_id, so nothing can cross a tenant or staff boundary.
 *
 * The copilot is READ-ONLY: it runs allow-listed tools over the business's
 * own rows and narrates them. It never writes a product, sale, price,
 * expense, order or customer record - the expense tool only drafts a preview
 * and links the seller to the existing confirmation workflow.
 */
class AiCopilotController extends BaseController
{
    /* ------------------------------------------------------------------ */
    /* Chat                                                                */
    /* ------------------------------------------------------------------ */

    public function chat(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.copilot', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $question = trim((string) $request->input('question', ''));
        if ($question === '') {
            return $this->json(['error' => 'Tafadhali weka swali.', 'code' => 'QUESTION_REQUIRED'], 422);
        }
        $maxQuestion = max(50, (int) config('ai.copilot.max_question', 400));
        if (mb_strlen($question) > $maxQuestion) {
            $question = mb_substr($question, 0, $maxQuestion);
        }

        $locale = $this->resolveLocale($request);
        $currency = (string) config('ai.currency', 'TZS');

        try {
            $businessId = $this->businessId($request) ?: BusinessResolver::forUser($userId)?->id;
            if (! $businessId) {
                return $this->json([
                    'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                    'code' => 'NO_BUSINESS',
                ], 400);
            }
            $businessId = (string) $businessId;

            $memberIds = BusinessResolver::memberIds($businessId, ['admin', 'seller']);
            if ($userId && ! in_array($userId, $memberIds, true)) {
                $memberIds[] = $userId;
            }
            $memberIds = array_values(array_unique(array_filter($memberIds)));

            $conversation = SellerCopilot::loadConversation(
                $businessId,
                (string) $userId,
                $request->input('conversation_id'),
            );

            // 1. Deterministic router; 2. model router into the same
            // allow-list; 3. honest "did not understand" answer.
            $routed = SellerCopilot::route($question, $conversation['messages']);
            $unmatched = false;
            if ($routed['tool'] === '') {
                $previous = SellerCopilot::lastTool($conversation['messages']);
                $routed = SellerCopilot::classifyWithAi(
                    $question,
                    $previous['tool'] ?? null,
                    $locale,
                    $userId,
                    $businessId,
                    $ip,
                );
                if ($routed['tool'] === '') {
                    $routed = ['tool' => 'help', 'args' => [], 'source' => 'none', 'confidence' => 'low'];
                    $unmatched = true;
                }
            }

            $ctx = [
                'member_ids' => $memberIds,
                'business_id' => $businessId,
                'user_id' => $userId,
                'ip' => $ip,
                'locale' => $locale,
                'currency' => $currency,
                'today' => date('Y-m-d'),
            ];

            $result = SellerCopilot::runTool($routed['tool'], $routed['args'], $ctx);
            if ($unmatched) {
                $result['summary_text'] = SellerCopilot::notUnderstood($locale);
            }

            $narration = SellerCopilot::narrate($result, $question, $conversation['messages'], $ctx);

            // Persist both sides of the exchange, bounded and tenant-scoped.
            $now = now()->toISOString(true);
            $userMessage = ['role' => 'user', 'content' => $question, 'at' => $now];
            $assistantMessage = [
                'role' => 'assistant',
                'content' => $narration['answer'],
                'tool' => $routed['tool'],
                'tool_label' => SellerCopilot::toolLabel($routed['tool'], $locale),
                'sub' => $routed['tool'] === 'report' ? ($routed['args']['report']['tool'] ?? null) : null,
                'at' => $now,
            ];
            $title = $conversation['title'] !== ''
                ? $conversation['title']
                : mb_substr(preg_replace('/\s+/u', ' ', $question) ?? $question, 0, 60);
            $allMessages = SellerCopilot::appendMessages(
                $conversation['messages'],
                $userMessage,
                $assistantMessage,
                max(2, (int) config('ai.copilot.store_messages', SellerCopilot::DEFAULT_STORE_MESSAGES)),
            );
            $stored = SellerCopilot::saveConversation($businessId, (string) $userId, $conversation['id'], $title, $allMessages);

            $limitations = (array) ($result['limitations'] ?? []);
            if (! $stored) {
                $limitations[] = SellerCopilot::msg('history_not_persisted', $locale);
            }

            $this->log($userId, 'AI_COPILOT_CHAT', '/api/ai/copilot/chat', [
                'conversation_id' => $conversation['id'],
                'tool' => $routed['tool'],
                'router_source' => $routed['source'],
                'router_confidence' => $routed['confidence'],
                'ai_status' => ($narration['ai']['available'] ?? false) ? 'ok' : 'degraded',
                'ai_code' => $narration['ai']['code'] ?? null,
                'stored' => $stored,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, $unmatched ? 'warning' : 'success');

            return $this->json([
                'success' => true,
                'capability' => SellerCopilot::CAPABILITY,
                'locale' => $locale,
                'currency' => $currency,
                'generated_at' => now()->toISOString(true),
                'conversation' => [
                    'id' => $conversation['id'],
                    'title' => $title,
                    'stored' => $stored,
                    'message_count' => count($allMessages),
                ],
                'question' => $question,
                'message' => [
                    'role' => 'assistant',
                    'content' => $narration['answer'],
                    'tool' => $routed['tool'],
                    'tool_label' => SellerCopilot::toolLabel($routed['tool'], $locale),
                    'at' => $now,
                ],
                'next_steps' => $narration['next_steps'],
                'tool_result' => [
                    'tool' => $result['tool'],
                    'period' => $result['period'],
                    'data' => $result['data'],
                    'table' => $result['table'],
                    'chart' => $result['chart'],
                    'highlights' => $result['highlights'],
                    'sources' => $result['sources'],
                    'limitations' => array_values(array_unique($limitations)),
                    'summary_text' => $result['summary_text'],
                ],
                'quick_actions' => SellerCopilot::quickActions($routed['tool'], $locale),
                'router' => [
                    'tool' => $routed['tool'],
                    'source' => $routed['source'],
                    'confidence' => $routed['confidence'],
                    'unmatched' => $unmatched,
                ],
                'ai' => $narration['ai'],
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_COPILOT_CHAT_ERROR', '/api/ai/copilot/chat', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Imeshindwa kujibu kwa sasa. Tafadhali jaribu tena.',
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'COPILOT_ERROR',
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Conversation history (persistence + deletion controls)              */
    /* ------------------------------------------------------------------ */

    public function conversations(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        try {
            $businessId = $this->resolveBusinessId($request, $userId);
            if ($businessId === null) {
                return $this->json([
                    'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                    'code' => 'NO_BUSINESS',
                ], 400);
            }

            $limit = (int) $request->input('limit', (int) config('ai.copilot.list_limit', 20));

            return $this->json([
                'success' => true,
                'capability' => SellerCopilot::CAPABILITY,
                'conversations' => SellerCopilot::listConversations($businessId, (string) $userId, $limit),
                'retention_days' => (int) config('ai.copilot.retention_days', 90),
            ]);
        } catch (Throwable $error) {
            return $this->dbError($error);
        }
    }

    public function show(Request $request, string $conversationId): JsonResponse
    {
        $userId = $this->userId($request);

        try {
            $businessId = $this->resolveBusinessId($request, $userId);
            if ($businessId === null) {
                return $this->json([
                    'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                    'code' => 'NO_BUSINESS',
                ], 400);
            }

            if (! SellerCopilot::isUuid($conversationId)) {
                return $this->json(['error' => 'Mazungumzo hayapatikani.', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            $messages = SellerCopilot::conversationMessages($businessId, (string) $userId, $conversationId);
            if ($messages === null) {
                return $this->json(['error' => 'Mazungumzo hayapatikani.', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            return $this->json([
                'success' => true,
                'capability' => SellerCopilot::CAPABILITY,
                'conversation' => ['id' => $conversationId],
                'messages' => $messages,
            ]);
        } catch (Throwable $error) {
            return $this->dbError($error);
        }
    }

    public function destroy(Request $request, string $conversationId): JsonResponse
    {
        $userId = $this->userId($request);

        try {
            $businessId = $this->resolveBusinessId($request, $userId);
            if ($businessId === null) {
                return $this->json([
                    'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                    'code' => 'NO_BUSINESS',
                ], 400);
            }

            if (! SellerCopilot::isUuid($conversationId)) {
                return $this->json(['error' => 'Mazungumzo hayapatikani.', 'code' => 'CONVERSATION_NOT_FOUND'], 404);
            }

            SellerCopilot::deleteConversation($businessId, (string) $userId, $conversationId);

            $this->log($userId, 'AI_COPILOT_DELETED', '/api/ai/copilot/conversations/{id}', [
                'conversation_id' => $conversationId,
            ], $this->ip($request), 'success');

            return $this->json(['success' => true, 'deleted' => 1]);
        } catch (Throwable $error) {
            return $this->dbError($error);
        }
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $userId = $this->userId($request);

        try {
            $businessId = $this->resolveBusinessId($request, $userId);
            if ($businessId === null) {
                return $this->json([
                    'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                    'code' => 'NO_BUSINESS',
                ], 400);
            }

            $deleted = SellerCopilot::deleteAllConversations($businessId, (string) $userId);

            $this->log($userId, 'AI_COPILOT_DELETED_ALL', '/api/ai/copilot/conversations', [
                'deleted' => $deleted,
            ], $this->ip($request), 'success');

            return $this->json(['success' => true, 'deleted' => $deleted]);
        } catch (Throwable $error) {
            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    private function resolveBusinessId(Request $request, ?string $userId): ?string
    {
        $businessId = $this->businessId($request) ?: BusinessResolver::forUser($userId)?->id;

        return $businessId ? (string) $businessId : null;
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
