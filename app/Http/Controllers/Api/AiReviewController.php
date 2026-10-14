<?php

namespace App\Http\Controllers\Api;

use App\Models\Advertisement;
use App\Models\Product;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\ReviewAssistant;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Capability #16 - Advertisement & Content Review Assistant.
 *
 * A moderation AID for authorized staff (admin or seller of a business),
 * never an enforcement authority:
 *  - scope: the caller's own business only. An admin reviews the rows of the
 *    business members; a seller reviews their own rows. Ids always come from
 *    the authenticated business, never from client input;
 *  - findings: produced by the deterministic checks in ReviewAssistant (a
 *    pure, unit-tested core), each with a category, an explanation, a
 *    severity (definite violation vs uncertain concern) and the relevant
 *    text. Claim keyword hits are ALWAYS concerns;
 *  - AI: may at most CLASSIFY flagged claim excerpts as advisory context
 *    (supported / unsupported / uncertain), bounded and figure-free. Its
 *    verdicts are attached to the findings but can never create, upgrade or
 *    remove one;
 *  - decisions: the human presses approve / reject / request edits. The
 *    server RE-DERIVES the findings itself (never trusts the client), makes
 *    the reviewer confirm they read them, requires a written reason when
 *    rejecting something with definite violations, applies the publish state
 *    (approve -> active, reject -> inactive, request edits -> unchanged) and
 *    records the decision in the audit trail (AI_REVIEW_DECISION).
 */
class AiReviewController extends BaseController
{
    private const CACHE_PREFIX = 'ai.reviews.';

    public function check(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $ip = $this->ip($request);
        $userId = $this->userId($request);

        if (! config('ai.features.reviews', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $role = $this->role($request);
        if (! in_array($role, ['admin', 'seller'], true)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $target = (string) $request->input('target', 'all');
        if (! in_array($target, ['all', 'ads', 'products'], true)) {
            $target = 'all';
        }

        $cfg = (array) config('ai.reviews', []);
        $maxItems = max(10, min(500, (int) ($cfg['max_items'] ?? 200)));
        $policy = array_merge(ReviewAssistant::defaultPolicy(), array_filter(
            $cfg,
            static fn ($value) => $value !== null
        ));

        /* Scope: this caller's business, resolved server-side. */
        $memberIds = $this->memberIds($request, $userId);
        if ($memberIds === []) {
            return $this->json([
                'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                'code' => 'NO_BUSINESS',
            ], 400);
        }

        $cacheKey = self::CACHE_PREFIX.md5(json_encode([
            $memberIds, $target, $locale, $policy,
        ]));

        try {
            $cached = null;
            try {
                $cached = cache()->get($cacheKey);
            } catch (\Throwable $e) {
                $cached = null;
            }
            if (is_array($cached)) {
                $cached['cached'] = true;
                $this->log($userId, 'AI_REVIEW_CHECK', '/api/ai/reviews/check', [
                    'target' => $target,
                    'locale' => $locale,
                    'cached' => true,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ], $ip, 'success');

                return $this->json($cached);
            }

            /* The same rows the staff screens already list. */
            $ads = [];
            if ($target === 'all' || $target === 'ads') {
                $ads = array_slice(
                    Advertisement::whereIn('user_id', $memberIds)
                        ->orderByDesc('created_at')
                        ->get()->toArray(),
                    0,
                    $maxItems
                );
            }

            $products = [];
            if ($target === 'all' || $target === 'products') {
                $products = array_slice(
                    Product::whereIn('seller_id', $memberIds)
                        ->orderByDesc('created_at')
                        ->get()->toArray(),
                    0,
                    $maxItems
                );
            }

            /* Deterministic findings (the ground truth of this endpoint). */
            $items = [];
            foreach ($ads as $row) {
                $items[] = $this->itemPayload('ad', $row, $locale, $policy);
            }
            foreach ($products as $row) {
                $items[] = $this->itemPayload('product', $row, $locale, $policy);
            }

            /* Optional ADVISORY classification of the flagged claims. */
            $ai = $this->classifyClaims($items, $locale, $ip, (bool) ($cfg['ai_classify'] ?? true));

            /* Stats over the deterministic findings only. */
            $stats = [
                'items_checked' => count($items),
                'ads' => count($ads),
                'products' => count($products),
                'violations' => 0,
                'concerns' => 0,
                'clean' => 0,
            ];
            foreach ($items as $item) {
                $stats['violations'] += $item['violations'];
                $stats['concerns'] += $item['concerns'];
                if ($item['violations'] === 0 && $item['concerns'] === 0) {
                    $stats['clean']++;
                }
            }

            $payload = [
                'success' => true,
                'capability' => ReviewAssistant::CAPABILITY,
                'locale' => $locale,
                'generated_at' => now()->toISOString(true),
                'policy' => [
                    'min_description_length' => $policy['min_description_length'],
                    'max_title_length' => $policy['max_title_length'],
                    'max_price_multiplier' => $policy['max_price_multiplier'],
                    'block_contact_details' => $policy['block_contact_details'],
                    'prohibited_terms_count' => count($policy['prohibited_terms']),
                    'ai_classify' => (bool) ($cfg['ai_classify'] ?? true),
                ],
                'stats' => $stats,
                'items' => $items,
                'ai' => $ai,
                'limitations' => [ReviewAssistant::msg('lim_advisory', $locale)],
            ];

            $ttl = max(1, (int) ($cfg['cache_minutes'] ?? 5));
            try {
                cache()->put($cacheKey, $payload, $ttl * 60);
            } catch (\Throwable $e) {
                // A cache failure must never break the review.
            }

            $this->log($userId, 'AI_REVIEW_CHECK', '/api/ai/reviews/check', [
                'target' => $target,
                'locale' => $locale,
                'items_checked' => $stats['items_checked'],
                'violations' => $stats['violations'],
                'concerns' => $stats['concerns'],
                'ai_status' => $ai['degraded'] ? 'degraded' : 'ok',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json($payload);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_REVIEW_CHECK_ERROR', '/api/ai/reviews/check', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /**
     * The human decision. The server re-derives the findings itself, so the
     * client can never hide a violation to sneak a decision through, and the
     * decision is always recorded as belonging to this reviewer.
     */
    public function decision(Request $request): JsonResponse
    {
        $ip = $this->ip($request);
        $userId = $this->userId($request);

        if (! config('ai.features.reviews', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $role = $this->role($request);
        if (! in_array($role, ['admin', 'seller'], true)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $targetType = (string) $request->input('target_type', '');
        $targetId = trim((string) $request->input('target_id', ''));
        $decision = (string) $request->input('decision', '');
        $note = mb_substr(trim((string) $request->input('note', '')), 0, 300);
        $reviewed = filter_var($request->input('reviewed', false), FILTER_VALIDATE_BOOL);

        if (! in_array($targetType, ['ad', 'product'], true)
            || ! in_array($decision, ['approve', 'reject', 'request_edit'], true)
            || $targetId === '') {
            return $this->json(['error' => 'Data si sahihi.', 'code' => 'INVALID_INPUT'], 422);
        }

        try {
            $memberIds = $this->memberIds($request, $userId);
            if ($memberIds === []) {
                return $this->json([
                    'error' => 'Biashara haijapatikana. Sajili biashara yako kwanza.',
                    'code' => 'NO_BUSINESS',
                ], 400);
            }

            $row = $targetType === 'ad'
                ? Advertisement::where('id', $targetId)->whereIn('user_id', $memberIds)->first()
                : Product::where('id', $targetId)->whereIn('seller_id', $memberIds)->first();

            if ($row === null) {
                $this->log($userId, 'AI_REVIEW_DECISION_ERROR', '/api/ai/reviews/decision', [
                    'reason' => 'UNKNOWN_TARGET',
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                ], $ip, 'failed');

                return $this->json([
                    'error' => ReviewAssistant::msg('err_unknown_target', $locale),
                    'code' => 'UNKNOWN_TARGET',
                ], 404);
            }
            $data = $row->toArray();

            /* The reviewer must confirm they READ the findings. */
            if (! $reviewed) {
                return $this->json([
                    'error' => ReviewAssistant::msg('err_review_required', $locale),
                    'code' => 'REVIEW_CONFIRM_REQUIRED',
                ], 422);
            }

            $policy = array_merge(ReviewAssistant::defaultPolicy(), array_filter(
                (array) config('ai.reviews', []),
                static fn ($value) => $value !== null
            ));
            $findings = $targetType === 'ad'
                ? ReviewAssistant::reviewAd($data, $locale, $policy)
                : ReviewAssistant::reviewProduct($data, $locale, $policy);
            $counts = ReviewAssistant::countBySeverity($findings);

            /* Rejecting content with definite violations needs a human reason. */
            if ($decision === 'reject' && $counts[ReviewAssistant::SEVERITY_VIOLATION] > 0 && $note === '') {
                return $this->json([
                    'error' => ReviewAssistant::msg('err_note_required', $locale),
                    'code' => 'REVIEW_NOTE_REQUIRED',
                    'violations' => $counts[ReviewAssistant::SEVERITY_VIOLATION],
                ], 422);
            }

            /* approve -> published, reject -> unpublished, edits -> unchanged. */
            $wasActive = (bool) ($data['is_active'] ?? false);
            $isActive = $decision === 'approve'
                ? true
                : ($decision === 'reject' ? false : $wasActive);

            if ($isActive !== $wasActive) {
                if ($targetType === 'ad') {
                    Advertisement::where('id', $targetId)->update([
                        'is_active' => $isActive,
                        'updated_at' => $this->isoNow(),
                    ]);
                } else {
                    Product::where('id', $targetId)->update([
                        'is_active' => $isActive,
                        'updated_at' => $this->isoNow(),
                    ]);
                }
            }

            $this->log($userId, 'AI_REVIEW_DECISION', '/api/ai/reviews/decision', [
                'target_type' => $targetType,
                'target_id' => $targetId,
                'decision' => $decision,
                'violations' => $counts[ReviewAssistant::SEVERITY_VIOLATION],
                'concerns' => $counts[ReviewAssistant::SEVERITY_CONCERN],
                'has_note' => $note !== '',
                'is_active_after' => $isActive,
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'capability' => ReviewAssistant::CAPABILITY,
                'decision' => $decision,
                'target' => [
                    'type' => $targetType,
                    'id' => $targetId,
                    'is_active' => $isActive,
                ],
                'violations' => $counts[ReviewAssistant::SEVERITY_VIOLATION],
                'concerns' => $counts[ReviewAssistant::SEVERITY_CONCERN],
                'note_saved' => $note !== '',
                'recorded_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_REVIEW_DECISION_ERROR', '/api/ai/reviews/decision', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /** Server-side scope: admin -> business members; seller -> own rows. */
    private function memberIds(Request $request, ?string $userId): array
    {
        $businessId = $this->businessId($request) ?: BusinessResolver::forUser($userId)?->id;
        if (! $businessId) {
            return $userId ? [$userId] : [];
        }

        $memberIds = BusinessResolver::memberIds($businessId, ['admin', 'seller']);
        if ($userId && ! in_array($userId, $memberIds, true)) {
            $memberIds[] = $userId;
        }

        return array_values(array_unique(array_filter($memberIds)));
    }

    /** One reviewable row with its deterministic findings and counts. */
    private function itemPayload(string $type, array $row, string $locale, array $policy): array
    {
        $findings = $type === 'ad'
            ? ReviewAssistant::reviewAd($row, $locale, $policy)
            : ReviewAssistant::reviewProduct($row, $locale, $policy);
        $counts = ReviewAssistant::countBySeverity($findings);

        $title = $type === 'ad' ? (string) ($row['title'] ?? '') : (string) ($row['name'] ?? '');
        $subtitle = $type === 'ad'
            ? mb_substr(trim((string) ($row['description'] ?? '')), 0, 120)
            : mb_substr(trim((string) ($row['category'] ?? '')), 0, 60);

        return [
            'type' => $type,
            'id' => (string) ($row['id'] ?? ''),
            'owner_id' => (string) ($type === 'ad' ? ($row['user_id'] ?? '') : ($row['seller_id'] ?? '')),
            'title' => $title,
            'subtitle' => $subtitle,
            'is_active' => (bool) ($row['is_active'] ?? false),
            'media_url' => (string) ($row[$type === 'ad' ? 'media_url' : 'image_url'] ?? ''),
            'findings' => $findings,
            'violations' => $counts[ReviewAssistant::SEVERITY_VIOLATION],
            'concerns' => $counts[ReviewAssistant::SEVERITY_CONCERN],
        ];
    }

    /**
     * The advisory classification. Bounded: at most a handful of excerpts,
     * figure-free reasons, and the verdicts are attached NEXT TO the
     * deterministic findings - they never change a severity or a decision.
     */
    private function classifyClaims(array &$items, string $locale, ?string $ip, bool $enabled): array
    {
        $ai = [
            'available' => false,
            'degraded' => false,
            'code' => null,
            'reason' => null,
            'meta' => null,
            'classified' => 0,
        ];

        $claims = ReviewAssistant::claimExcerpts($items, 6);
        if ($claims === [] || ! $enabled) {
            return $ai;
        }

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            $ai['degraded'] = true;
            $ai['code'] = 'AI_DISABLED';
            $ai['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $ai;
        }

        try {
            $result = $client->generateJson(
                [[
                    'text' => json_encode([
                        'locale' => $locale,
                        'excerpts' => array_map(static function (array $claim): array {
                            return [
                                'id' => $claim['id'],
                                'type' => $claim['type'],
                                'title' => $claim['title'],
                                'text' => $claim['text'],
                            ];
                        }, $claims),
                    ], JSON_UNESCAPED_UNICODE),
                ]],
                ReviewAssistant::classificationInstruction($locale),
                [
                    'capability' => ReviewAssistant::CAPABILITY,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 600,
                ],
            );

            $ai['meta'] = $result->meta();
            $verdicts = ReviewAssistant::sanitizeVerdicts(
                $result->data,
                array_column($claims, 'id')
            );

            if ($verdicts === null) {
                $ai['degraded'] = true;
                $ai['code'] = 'AI_UNSAFE_OUTPUT';
                $ai['reason'] = AiException::reasonForCode('AI_UNSAFE_OUTPUT', $locale);

                return $ai;
            }

            /* Attach verdicts next to the exact findings they concern. */
            foreach ($claims as $claim) {
                $verdict = $verdicts['#'.$claim['id']] ?? null;
                if ($verdict === null) {
                    continue;
                }
                $itemIndex = $claim['ref']['item'];
                $findingIndex = $claim['ref']['finding'];
                if (! isset($items[$itemIndex]['findings'][$findingIndex])) {
                    continue;
                }
                $items[$itemIndex]['findings'][$findingIndex]['ai'] = [
                    'verdict' => $verdict['verdict'],
                    'reason' => $verdict['reason'],
                ];
                $ai['classified']++;
            }

            $ai['available'] = true;

            return $ai;
        } catch (AiException $error) {
            $ai['degraded'] = true;
            $ai['code'] = $error->errorCode;
            $ai['reason'] = AiException::reasonForCode($error->errorCode, $locale);

            return $ai;
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
