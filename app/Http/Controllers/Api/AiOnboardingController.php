<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\OnboardingGuide;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Capability #19 (onboarding half) - signup and setup guide for the signed-in
 * user, in their own language.
 *
 * GET  /api/ai/onboarding/guide - the caller's own setup checklist: what is
 *                                 already recorded and what is left.
 * POST /api/ai/onboarding/ask   - an answer to an onboarding question, composed
 *                                 from the approved content, plus the checklist.
 *
 * Guarantees:
 *  - any authenticated user may call this, but ONLY for their own account: the
 *    user row is read by the JWT user id and the business through
 *    `BusinessResolver` - never from client input. A caller can therefore never
 *    see another account's checklist;
 *  - every step is decided by a real recorded value (`users.*`,
 *    `businesses.*`, the business's products). A value that cannot be read is
 *    reported as "not read", never as done;
 *  - the answer text comes from the APPROVED onboarding knowledge base. The
 *    optional model step may only narrow the allow-listed topics and add ONE
 *    acknowledgement sentence that passes the sanitiser (no digits, links,
 *    emails, phone numbers or questions), so it can never invent a rule, a
 *    link or a contact;
 *  - nothing internal is returned: no password, no verification code, no other
 *    account's data, and no internal table names in the answer text;
 *  - this endpoint cannot approve or certify anything. It only reads and audits
 *    (AI_ONBOARDING_GUIDE / AI_ONBOARDING_ASK + their _ERROR variants).
 */
class AiOnboardingController extends BaseController
{
    private const NARRATION_CACHE_PREFIX = 'ai.onboarding.narration.';

    /* ------------------------------------------------------------------ */
    /* Checklist                                                           */
    /* ------------------------------------------------------------------ */

    public function guide(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.onboarding', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);

        try {
            $user = User::where('id', $userId)->first();
            $userRow = $user ? $user->toArray() : null;

            if (! $userRow) {
                return $this->json([
                    'error' => 'Akaunti haipatikani.',
                    'code' => 'ACCOUNT_NOT_FOUND',
                ], 404);
            }

            $businessId = BusinessResolver::idForUser($userId);
            $business = $businessId ? $this->businessRow($businessId) : null;
            $productCount = $businessId ? $this->productCountForBusiness($businessId) : null;

            $guide = OnboardingGuide::steps([
                'locale' => $locale,
                'role' => (string) ($userRow['role'] ?? ''),
                'user' => $userRow,
                'business' => $business,
                'product_count' => $productCount,
                'steps' => $this->stepOverride(),
            ]);

            $this->log($userId, 'AI_ONBOARDING_GUIDE', '/api/ai/onboarding/guide', [
                'role' => $guide['role'],
                'progress' => $guide['progress'],
                'remaining' => $guide['counts']['remaining'],
                'next_step' => $guide['next_step']['id'] ?? null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'capability' => OnboardingGuide::CAPABILITY,
                'locale' => $locale,
                'business_id' => $businessId,
                'guide' => $guide,
                'generated_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_ONBOARDING_GUIDE_ERROR', '/api/ai/onboarding/guide', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Question                                                            */
    /* ------------------------------------------------------------------ */

    public function ask(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.onboarding', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);
        $maxChars = max(60, (int) config('ai.onboarding.max_question_chars', 500));
        $question = mb_substr(trim((string) $request->input('question', '')), 0, $maxChars);

        if (mb_strlen($question) < 3) {
            return $this->json([
                'error' => 'Tafadhali andika swali lako.',
                'code' => 'QUESTION_REQUIRED',
            ], 422);
        }

        try {
            $user = User::where('id', $userId)->first();
            $userRow = $user ? $user->toArray() : null;

            if (! $userRow) {
                return $this->json([
                    'error' => 'Akaunti haipatikani.',
                    'code' => 'ACCOUNT_NOT_FOUND',
                ], 404);
            }

            $businessId = BusinessResolver::idForUser($userId);
            $business = $businessId ? $this->businessRow($businessId) : null;
            $productCount = $businessId ? $this->productCountForBusiness($businessId) : null;

            $matches = OnboardingGuide::matchTopics($question, OnboardingGuide::MAX_TOPICS);
            $narration = $this->narrate($userId, $question, $matches, $locale, $ip, (bool) config('ai.onboarding.ai_narrate', true));

            /* The model may only NARROW the deterministic matches. */
            if ($narration['topics'] !== []) {
                $matches = array_values(array_filter($matches, static fn (array $m): bool => in_array($m['id'], $narration['topics'], true)));
            }

            $answer = OnboardingGuide::answer([
                'locale' => $locale,
                'role' => (string) ($userRow['role'] ?? ''),
                'question' => $question,
                'user' => $userRow,
                'business' => $business,
                'product_count' => $productCount,
                'kb' => $this->kbOverride(),
                'acknowledgement' => $narration['ack'],
            ]);

            /* The answer reports the topics that were actually used. */
            $answer['topics'] = $matches === []
                ? []
                : array_values(array_filter($answer['topics'], static fn (array $t): bool => in_array($t['id'], array_column($matches, 'id'), true)));
            $answer['matched'] = $answer['topics'] !== [];
            $answer['answers'] = array_column($answer['topics'], 'answer');

            $this->log($userId, 'AI_ONBOARDING_ASK', '/api/ai/onboarding/ask', [
                'role' => $answer['guide']['role'],
                'question_chars' => mb_strlen($question),
                'matched_topics' => array_column($answer['topics'], 'id'),
                'matched' => $answer['matched'],
                'ai_status' => $narration['degraded'] ? 'degraded' : ($narration['available'] ? 'ok' : 'off'),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'capability' => OnboardingGuide::CAPABILITY,
                'locale' => $locale,
                'business_id' => $businessId,
                'answer' => $answer,
                'ai' => [
                    'available' => $narration['available'],
                    'degraded' => $narration['degraded'],
                    'code' => $narration['code'],
                    'reason' => $narration['reason'],
                ],
                'notice' => $answer['matched']
                    ? $answer['notice']
                    : $this->noMatchNotice($locale),
                'generated_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_ONBOARDING_ASK_ERROR', '/api/ai/onboarding/ask', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * The caller's own business row, read by id from the server-side identity.
     *
     * @return array<string, mixed>|null
     */
    private function businessRow(string $businessId): ?array
    {
        try {
            $business = \App\Models\Business::where('id', $businessId)->first();

            return $business ? $business->toArray() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * How many products the caller's business has. Products belong to a business
     * through their seller's row (`products.seller_id` -> `users.business_id`),
     * which is how the catalogue resolves ownership as well. Returns null when
     * the count cannot be read, so the checklist says "not read" instead of
     * "none".
     */
    private function productCountForBusiness(string $businessId): ?int
    {
        try {
            $members = User::select('id')->where('business_id', $businessId)->get()->toArray();
        } catch (\Throwable $e) {
            return null;
        }

        $sellerIds = array_values(array_filter(array_map(
            static fn (array $m): string => trim((string) ($m['id'] ?? '')),
            $members
        ), static fn (string $id): bool => $id !== ''));

        if ($sellerIds === []) {
            return 0;
        }

        try {
            return count(Product::whereIn('seller_id', $sellerIds)->get()->toArray());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** The operator's step list override, when configured. */
    private function stepOverride(): ?array
    {
        $steps = config('ai.onboarding.steps');

        return is_array($steps) && $steps !== [] ? $steps : null;
    }

    /** The operator's approved-content override, when configured. */
    private function kbOverride(): ?array
    {
        $kb = config('ai.onboarding.kb');

        return is_array($kb) && $kb !== [] ? $kb : null;
    }

    private function noMatchNotice(string $locale): string
    {
        return OnboardingGuide::msg('notice_no_match', $locale);
    }

    /**
     * The optional, gated model step: it may return ONE acknowledgement sentence
     * (no digits, links, emails, phone numbers or questions) and a narrowing of
     * the allow-listed topics. Anything else is dropped and the deterministic
     * answer is used as-is. Cached per user + question + topics so a repeat does
     * not pay for the same sentence twice.
     */
    private function narrate(string $userId, string $question, array $matches, string $locale, ?string $ip, bool $enabled): array
    {
        $fallback = [
            'available' => false,
            'degraded' => true,
            'code' => null,
            'reason' => null,
            'ack' => null,
            'topics' => [],
        ];

        if (! $enabled || $matches === []) {
            return $fallback;
        }

        $cacheKey = self::NARRATION_CACHE_PREFIX.md5($userId.'|'.$locale.'|'.md5(OnboardingGuide::normalize($question)).'|'.implode(',', array_column($matches, 'id')));

        try {
            $cached = cache()->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // A cache failure only costs one extra model call.
        }

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            $fallback['code'] = 'AI_DISABLED';
            $fallback['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }

        $instruction = implode("\n", [
            'You are the onboarding helper of the Duka Mkononi marketplace app.',
            'Return STRICT JSON only: {"ack": string, "topics": [string]}.',
            'The "topics" list may contain ONLY ids from this allow-list: '.implode(', ', array_column($matches, 'id')).'.',
            'The "ack" is ONE short sentence (no digits, no links, no email addresses, no phone numbers, no question marks) that acknowledges the question and says the approved guide below answers it.',
            'You must NOT answer the question yourself, promise anything, mention prices, phone numbers, links or any account, and you must NOT add rules.',
            'Write the sentence in the language with locale code "'.$locale.'" (sw=Kiswahili, en=English, fr=French, es=Spanish, zh=Chinese, hi=Hindi, ur=Urdu, de=German).',
            'DATA: '.json_encode([
                'locale' => $locale,
                'matched_topics' => array_column($matches, 'id'),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        try {
            $result = $client->generateJson(
                [['text' => 'Return the JSON acknowledgement for this onboarding question.']],
                $instruction,
                [
                    'capability' => OnboardingGuide::CAPABILITY.'_ack',
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 300,
                ],
            );

            $narration = OnboardingGuide::sanitizeNarration($result->data);
            if ($narration['ack'] === null && $narration['topics'] === []) {
                return [
                    'available' => true,
                    'degraded' => true,
                    'code' => 'AI_UNSAFE_OUTPUT',
                    'reason' => AiException::reasonForCode('AI_UNSAFE_OUTPUT', $locale),
                    'ack' => null,
                    'topics' => [],
                ];
            }

            $out = [
                'available' => true,
                'degraded' => false,
                'code' => null,
                'reason' => null,
                'ack' => $narration['ack'],
                'topics' => $narration['topics'],
            ];

            try {
                cache()->put($cacheKey, $out, max(1, (int) config('ai.onboarding.cache_minutes', 10)) * 60);
            } catch (\Throwable $e) {
                // Cache is only a cost optimisation.
            }

            return $out;
        } catch (AiException $error) {
            $fallback['code'] = $error->errorCode;
            $fallback['reason'] = AiException::reasonForCode($error->errorCode, $locale);

            return $fallback;
        }
    }
}
