<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\CustomerReview;
use App\Models\Order;
use App\Services\Ai\TrustSummary;
use App\Services\OrderService;
use App\Services\PublicCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #12 - Business & Review Summarisation.
 *
 * GET /api/ai/trust/summary?business_id=<uuid>&locale=sw
 *
 * PUBLIC + throttled: any customer can read a business' trust summary
 * without logging in. Everything in the payload comes from records the
 * platform already exposes or aggregates:
 *
 *  - reviews: only `is_published` rows (the same rows GET /api/shop/reviews
 *    serves); the parent inquiry's e-mail/phone are never loaded and the
 *    certification_note (moderation note) is never read;
 *  - business: only is_public businesses (visibility enforced by
 *    PublicCatalog before this controller sees the row);
 *  - activity: order aggregates (counts + one date) - never customer rows.
 *
 * Reviews on this platform carry no business_id, so the review half is
 * labelled `scope: platform` and no client may attribute it to one seller.
 *
 * The payload is cached per locale with version keys: publishing or
 * unpublishing a review bumps the reviews version (used by every summary),
 * certifying or uncertifying a business bumps only that business' version.
 */
class AiTrustSummaryController extends BaseController
{
    public function summary(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.business_summary', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);
        $cacheMinutes = max(1, (int) config('ai.trust.cache_minutes', 15));
        $ip = $this->ip($request);

        // Optional business scope; absent -> platform-level summary only.
        $businessId = null;
        $rawBusinessId = trim((string) $request->query('business_id', ''));
        if ($rawBusinessId !== '') {
            if (! OrderService::isUuid($rawBusinessId)) {
                return $this->json([
                    'error' => TrustSummary::msg('not_found', $locale),
                    'code' => 'INVALID_BUSINESS',
                ], 422);
            }
            $businessId = $rawBusinessId;
        }

        try {
            /* 1. Cache hit? */
            $hit = TrustSummary::cached($locale, $businessId);
            if ($hit !== null) {
                $hit['cached'] = true;

                $this->log(null, 'AI_TRUST_SUMMARY', '/api/ai/trust/summary', [
                    'business_id' => $businessId,
                    'locale' => $locale,
                    'cached' => true,
                    'summary_source' => $hit['summary']['source'] ?? null,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ], $ip, 'success');

                return $this->json($hit);
            }

            /* 2. The public business row (visibility enforced upstream). */
            $businessRow = null;
            $activity = null;
            if ($businessId !== null) {
                foreach (PublicCatalog::shopBusinesses() as $row) {
                    if ((string) ($row['id'] ?? '') === $businessId) {
                        $businessRow = $row;
                        break;
                    }
                }

                if ($businessRow === null) {
                    return $this->json([
                        'error' => TrustSummary::msg('not_found', $locale),
                        'code' => 'BUSINESS_NOT_FOUND',
                    ], 404);
                }

                // certified_at is not part of the cached shop listing.
                try {
                    $full = Business::where('id', $businessId)->first();
                    $businessRow['certified_at'] = $full ? ($full->certified_at ?? null) : null;
                } catch (Throwable $e) {
                    $businessRow['certified_at'] = null;
                }

                $activity = $this->orderActivity($businessId);
            }

            /* 3. Published reviews only (bounded). */
            $maxReviews = max(10, (int) config('ai.trust.max_reviews', 100));
            try {
                $reviews = CustomerReview::select(
                    'id', 'title', 'body', 'display_name', 'rating', 'published_at'
                )->where('is_published', true)
                    ->orderByDesc('published_at')
                    ->limit($maxReviews)
                    ->get()
                    ->toArray();
            } catch (Throwable $e) {
                $reviews = [];
            }

            /* 4. Deterministic statistics, themes and wording. */
            $stats = TrustSummary::reviewStats($reviews);
            $themes = TrustSummary::themes(
                $reviews,
                max(1, (int) config('ai.trust.theme_min_reviews', 2)),
                max(1, (int) config('ai.trust.theme_limit', 6))
            );
            $facts = TrustSummary::facts($businessRow, $activity, $locale);
            $opinions = TrustSummary::opinions($stats, $themes, $locale);
            $summaryText = TrustSummary::deterministicSummary($stats, $themes, $businessRow, $locale);
            $summarySource = 'deterministic';

            /* 5. Optional model rewording: may only repeat our numbers. */
            $modelled = TrustSummary::summarizeWithAi($stats, $facts, $opinions, $reviews, $locale, $ip);
            $ai = $modelled['ai'];
            if ($modelled['summary'] !== null) {
                $summaryText = $modelled['summary'];
                $summarySource = 'ai';
            }

            $payload = [
                'success' => true,
                'capability' => TrustSummary::CAPABILITY,
                'locale' => $locale,
                'generated_at' => now()->toISOString(true),
                'cached' => false,
                'cache_minutes' => $cacheMinutes,
                'business' => $businessRow === null ? null : [
                    'id' => $businessRow['id'] ?? null,
                    'name' => (string) ($businessRow['business_name'] ?? ''),
                    'location' => $businessRow['business_location'] ?? null,
                    'type' => $businessRow['business_type'] ?? null,
                    'logo' => $businessRow['business_logo_url'] ?? null,
                    'certified' => (bool) ($businessRow['is_certified'] ?? false),
                    'certified_at' => $businessRow['certified_at'] ?? null,
                    'product_count' => (int) ($businessRow['product_count'] ?? 0),
                    'activity' => $activity,
                ],
                'reviews' => [
                    // Platform-level testimonials: they carry no business_id,
                    // so no UI may show them as one seller's reviews.
                    'scope' => 'platform',
                    'originals_endpoint' => '/api/shop/reviews',
                    'sample_size' => (int) $stats['sample_size'],
                    'rated_count' => (int) $stats['rated_count'],
                    'rating_average' => $stats['rating_average'],
                    'rating_distribution' => $stats['rating_distribution'],
                    'period' => $stats['period'],
                    'divided' => (bool) $stats['divided'],
                    'themes' => $themes,
                    'items' => TrustSummary::itemRows($reviews),
                ],
                'summary' => [
                    'text' => $summaryText,
                    'source' => $summarySource,
                ],
                'facts' => $facts,
                'opinions' => $opinions,
                'sources' => TrustSummary::sources($locale),
                'limitations' => TrustSummary::limitations($locale, $cacheMinutes),
                'ai' => $ai,
            ];

            TrustSummary::storePayload($locale, $businessId, $payload, $cacheMinutes);

            $this->log(null, 'AI_TRUST_SUMMARY', '/api/ai/trust/summary', [
                'business_id' => $businessId,
                'locale' => $locale,
                'cached' => false,
                'reviews' => count($reviews),
                'themes' => count($themes),
                'divided' => (bool) $stats['divided'],
                'summary_source' => $summarySource,
                'ai_status' => ($ai['available'] ?? false) ? 'ok' : 'degraded',
                'ai_code' => $ai['code'] ?? null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json($payload);
        } catch (Throwable $error) {
            $this->log(null, 'AI_TRUST_SUMMARY_ERROR', '/api/ai/trust/summary', [
                'business_id' => $businessId,
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => TrustSummary::msg('error_generic', $locale),
                'details' => $debug ? $error->getMessage() : null,
                'code' => 'TRUST_SUMMARY_ERROR',
            ], 500);
        }
    }

    /**
     * Order aggregates for one business - counts and one date only, never
     * customer rows or notes. When the scan fails the activity block is
     * simply absent: an optional signal must never break the summary.
     *
     * @return array<string, int|string|null>|null
     */
    private function orderActivity(string $businessId): ?array
    {
        try {
            $limit = max(50, (int) config('ai.trust.max_orders_scanned', 500));
            $rows = Order::select('status', 'created_at')
                ->where('business_id', $businessId)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (Throwable $e) {
            return null;
        }

        $completedStatuses = ['delivered', 'completed'];
        $completed = 0;
        $open = 0;
        $cancelled = 0;
        $last = null;

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'cancelled') {
                $cancelled++;
            } elseif (in_array($status, $completedStatuses, true)) {
                $completed++;
            } else {
                $open++;
            }

            $date = (string) ($row['created_at'] ?? '');
            if ($last === null || $date > $last) {
                $last = $date;
            }
        }

        return [
            'total' => count($rows),
            'completed' => $completed,
            'open' => $open,
            'cancelled' => $cancelled,
            'last_order_at' => $last !== null ? substr($last, 0, 10) : null,
        ];
    }
}
