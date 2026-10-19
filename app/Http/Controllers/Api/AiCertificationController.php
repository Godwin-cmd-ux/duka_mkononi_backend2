<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\CertificationReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Capability #19 (certification half) - business certification review for the
 * Super Admin trust boundary.
 *
 * GET  /api/ai/certification/queue       - businesses awaiting certification,
 *                                          each with its checklist summary.
 * POST /api/ai/certification/{id}/review - the full evidence checklist for one
 *                                          business.
 *
 * Guarantees:
 *  - Super Admin only (`system_admin`/`admin`), the same trust boundary the
 *    existing inquiry/certification endpoints use. The evidence includes the
 *    business's own member records, so it must never be reachable by anyone
 *    else;
 *  - every line is computed in PHP from real rows (`businesses.*`, the
 *    business's own `users` rows and its products through `products.seller_id`).
 *    The model is not involved at all - there is nothing here for it to invent;
 *  - a submitted document is NEVER reported as verified (this platform has no
 *    document store) and backfilled name/location ambiguity is always handed to
 *    a human;
 *  - this endpoint CANNOT certify anything. The only thing it writes is the
 *    audit trail (AI_CERTIFICATION_QUEUE / AI_CERTIFICATION_REVIEW + their
 *    _ERROR variants). The decision stays with the existing
 *    `PUT /api/system-admin/businesses/{id}/certify` human workflow, and the
 *    response names that endpoint instead of implying an approval.
 */
class AiCertificationController extends BaseController
{
    /* ------------------------------------------------------------------ */
    /* Queue                                                               */
    /* ------------------------------------------------------------------ */

    public function queue(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.certification', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $limit = max(1, min(500, (int) config('ai.certification.queue_limit', 100)));

        try {
            $pending = Business::where('is_certified', false)->orderBy('business_name')->get()->toArray();
            $certified = Business::where('is_certified', true)->get()->toArray();

            $truncated = count($pending) > $limit;
            $pending = array_slice($pending, 0, $limit);

            /* The member and product reads are bounded and their cap is DETECTED:
             * a capped read is reported as "not read" for the affected items
             * instead of silently under-counting them. */
            $members = $this->membersByBusiness();
            $membersCapped = $members['__capped'] ?? false;
            unset($members['__capped']);
            $productsCapped = false;
            $productCounts = $membersCapped
                ? []
                : $this->productCountsByBusiness($members, $productsCapped);

            $items = [];
            $counts = [
                'pending' => 0,
                'certified' => count($certified),
                'ready' => 0,
                'with_missing' => 0,
                'with_human_checks' => 0,
            ];

            foreach ($pending as $business) {
                $businessId = (string) ($business['id'] ?? '');
                $review = $this->reviewOne(
                    $business,
                    $members[$businessId] ?? [],
                    $productCounts[$businessId] ?? null,
                    $locale,
                    ! $membersCapped,
                    $membersCapped || $productsCapped
                );

                $counts['pending']++;
                if ($review['missing'] === [] && $review['counts']['required_total'] === $review['counts']['required_met']) {
                    $counts['ready']++;
                }
                if ($review['missing'] !== []) {
                    $counts['with_missing']++;
                }
                if ($review['human_checks'] !== []) {
                    $counts['with_human_checks']++;
                }

                $items[] = $this->summaryRow($business, $review);
            }

            /* Least complete first: the businesses that need the most work are
             * the ones an administrator should look at first. */
            usort($items, static function (array $a, array $b): int {
                return $a['completeness'] <=> $b['completeness'] ?: strcmp((string) $a['business_name'], (string) $b['business_name']);
            });

            $this->log($userId, 'AI_CERTIFICATION_QUEUE', '/api/ai/certification/queue', [
                'pending' => $counts['pending'],
                'certified' => $counts['certified'],
                'ready' => $counts['ready'],
                'truncated' => $truncated,
                'reads_capped' => $membersCapped || $productsCapped,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'capability' => CertificationReview::CAPABILITY,
                'locale' => $locale,
                'counts' => $counts,
                'items' => $items,
                'truncated' => $truncated,
                'reads_capped' => $membersCapped || $productsCapped,
                'queue_limit' => $limit,
                'decision' => 'human_only',
                'decision_endpoint' => 'PUT /api/system-admin/businesses/{id}/certify',
                'reviewed_note' => CertificationReview::msg('lim_no_decision', $locale),
                'generated_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_CERTIFICATION_QUEUE_ERROR', '/api/ai/certification/queue', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* One business                                                        */
    /* ------------------------------------------------------------------ */

    public function review(Request $request, string $id): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.certification', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $business = $this->findBusiness($id);
        if (! $business) {
            return $this->json([
                'error' => 'Biashara haipatikani.',
                'code' => 'BUSINESS_NOT_FOUND',
            ], 404);
        }

        try {
            $membersResult = $this->membersFor($id);
            $members = $membersResult['rows'];
            $productsCapped = false;
            $productCount = $membersResult['capped'] ? null : $this->productCountFor($members, $productsCapped);
            $review = $this->reviewOne($business, $members, $productCount, $locale, ! $membersResult['capped'], $membersResult['capped'] || $productsCapped);

            $this->log($userId, 'AI_CERTIFICATION_REVIEW', '/api/ai/certification/'.$id.'/review', [
                'business_id' => $id,
                'completeness' => $review['completeness'],
                'missing' => $review['missing'],
                'human_checks' => array_column($review['human_checks'], 'id'),
                'already_certified' => (bool) ($business['is_certified'] ?? false),
                'checklist_hash' => $this->checklistHash($review),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'capability' => CertificationReview::CAPABILITY,
                'locale' => $locale,
                'business' => [
                    'id' => (string) ($business['id'] ?? ''),
                    'business_name' => (string) ($business['business_name'] ?? ''),
                    'business_location' => (string) ($business['business_location'] ?? ''),
                    'business_type' => (string) ($business['business_type'] ?? ''),
                    'is_certified' => (bool) ($business['is_certified'] ?? false),
                    'certified_at' => $business['certified_at'] ?? null,
                    'is_public' => $business['is_public'] ?? null,
                    'created_at' => $business['created_at'] ?? null,
                ],
                'review' => $review,
                'checklist_hash' => $this->checklistHash($review),
                'members_examined' => count($members),
                'product_count' => $productCount,
                'reads_capped' => $membersResult['capped'] || $productsCapped,
                'decision' => 'human_only',
                'decision_endpoint' => 'PUT /api/system-admin/businesses/'.$id.'/certify',
                'notice' => CertificationReview::msg('lim_no_decision', $locale),
                'generated_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_CERTIFICATION_REVIEW_ERROR', '/api/ai/certification/'.$id.'/review', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Load one business by id, as an array. A malformed id (this backend rejects
     * a non-UUID) is a "not found", never an unhandled 500 with a raw database
     * message.
     *
     * NOTE: the row MUST be converted to an array here. This data layer returns
     * `App\Services\SupabaseRow`, NOT the model class, so a `: ?Business` return
     * type would raise a TypeError that the catch block below would swallow into
     * a "not found" - i.e. every review of a REAL business would 404. The
     * onboarding controller's `businessRow()` uses the same `?array` shape.
     *
     * @return array<string, mixed>|null
     */
    private function findBusiness(string $id): ?array
    {
        try {
            $business = Business::where('id', $id)->first();

            return $business ? $business->toArray() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The business's own member rows. Roles of interest are admin/seller (the
     * certification requirement).
     *
     * @return array{rows: array<int, array<string, mixed>>, capped: bool}
     */
    private function membersFor(string $businessId): array
    {
        $cap = $this->readCap();

        try {
            $rows = User::select('id', 'business_id', 'role', 'status', 'full_name', 'phone')
                ->where('business_id', $businessId)
                ->limit($cap)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return ['rows' => [], 'capped' => false];
        }

        return ['rows' => $rows, 'capped' => count($rows) >= $cap];
    }

    /**
     * Every business's members in one bounded read, keyed by business id - so the
     * queue does not query the users table once per business. The key `__capped`
     * reports whether the read hit its bound (the caller must then report the
     * affected items as "not read").
     *
     * @return array<string, mixed>
     */
    private function membersByBusiness(): array
    {
        $grouped = [];
        $cap = $this->readCap();

        try {
            $users = User::select('id', 'business_id', 'role', 'status', 'full_name', 'phone')->limit($cap)->get()->toArray();
        } catch (\Throwable $e) {
            return ['__capped' => true];
        }

        $grouped['__capped'] = count($users) >= $cap;

        foreach ($users as $user) {
            $businessId = trim((string) ($user['business_id'] ?? ''));
            if ($businessId === '') {
                continue;
            }
            $grouped[$businessId][] = $user;
        }

        return $grouped;
    }

    /**
     * How many products each business has. Products are linked to a business
     * through their seller's own row (`products.seller_id` -> `users.business_id`),
     * which is how the public catalogue resolves ownership too. The read is
     * bounded: when the bound is hit, `$capped` is set and every count becomes
     * null ("not read") instead of a wrong number.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $membersByBusiness
     * @param  bool  $capped  set to true when the read hit its bound
     * @return array<string, int|null> null when the count could not be read
     */
    private function productCountsByBusiness(array $membersByBusiness, bool &$capped = false): array
    {
        $sellerToBusiness = [];
        foreach ($membersByBusiness as $businessId => $members) {
            if ($businessId === '__capped' || ! is_array($members)) {
                continue;
            }
            foreach ($members as $member) {
                $memberId = trim((string) ($member['id'] ?? ''));
                if ($memberId !== '') {
                    $sellerToBusiness[$memberId] = $businessId;
                }
            }
        }

        $cap = $this->readCap();

        try {
            $products = Product::select('id', 'seller_id')->limit($cap)->get()->toArray();
        } catch (\Throwable $e) {
            return $this->unknownCounts($membersByBusiness);
        }

        if (count($products) >= $cap) {
            // The bound was hit: a count would be wrong, so report "not read".
            $capped = true;

            return $this->unknownCounts($membersByBusiness);
        }

        $counts = [];
        foreach ($products as $product) {
            $sellerId = trim((string) ($product['seller_id'] ?? ''));
            $businessId = $sellerToBusiness[$sellerId] ?? null;
            if ($businessId === null) {
                continue;
            }
            $counts[$businessId] = ($counts[$businessId] ?? 0) + 1;
        }

        foreach (array_keys($membersByBusiness) as $businessId) {
            if ($businessId === '__capped') {
                continue;
            }
            $counts[$businessId] = $counts[$businessId] ?? 0;
        }

        return $counts;
    }

    /**
     * The product count for one business (null when it cannot be read, or when
     * the read hit its bound - an under-count is never reported as a number).
     */
    private function productCountFor(array $members, bool &$capped = false): ?int
    {
        $sellerIds = [];
        foreach ($members as $member) {
            $memberId = trim((string) ($member['id'] ?? ''));
            if ($memberId !== '') {
                $sellerIds[] = $memberId;
            }
        }

        if ($sellerIds === []) {
            return 0;
        }

        $cap = $this->readCap();

        try {
            $products = Product::whereIn('seller_id', $sellerIds)->limit($cap)->get()->toArray();
        } catch (\Throwable $e) {
            return null;
        }

        if (count($products) >= $cap) {
            $capped = true;

            return null;
        }

        return count($products);
    }

    /** The documented bound for the bulk member/product reads. */
    private function readCap(): int
    {
        return max(100, (int) config('ai.certification.max_read_rows', 20000));
    }

    /**
     * Every business's product count as unknown.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $membersByBusiness
     * @return array<string, int|null>
     */
    private function unknownCounts(array $membersByBusiness): array
    {
        $counts = [];
        foreach (array_keys($membersByBusiness) as $businessId) {
            if ($businessId === '__capped') {
                continue;
            }
            $counts[$businessId] = null;
        }

        return $counts;
    }

    /** The deterministic review for one row. */
    private function reviewOne(array $business, array $members, ?int $productCount, string $locale, bool $membersRead = true, bool $readsCapped = false): array
    {
        $requirements = config('ai.certification.requirements');

        return CertificationReview::review([
            'locale' => $locale,
            'business' => $business,
            'members' => $members,
            'product_count' => $productCount,
            'members_read' => $membersRead,
            'reads_capped' => $readsCapped,
            'requirements' => is_array($requirements) && $requirements !== [] ? $requirements : null,
        ]);
    }

    /** A queue row: identity + the checklist outcome, never the whole review. */
    private function summaryRow(array $business, array $review): array
    {
        return [
            'id' => (string) ($business['id'] ?? ''),
            'business_name' => (string) ($business['business_name'] ?? ''),
            'business_location' => (string) ($business['business_location'] ?? ''),
            'business_type' => (string) ($business['business_type'] ?? ''),
            'is_public' => $business['is_public'] ?? null,
            'created_at' => $business['created_at'] ?? null,
            'completeness' => $review['completeness'],
            'missing' => $review['missing'],
            'missing_labels' => $this->labelsFor($review, $review['missing']),
            'human_check_ids' => array_column($review['human_checks'], 'id'),
            'human_check_labels' => array_column($review['human_checks'], 'label'),
            'summary' => $review['summary'],
        ];
    }

    /**
     * The localized labels of the given requirement ids.
     *
     * @param  array<int, string>  $ids
     * @return array<int, array{id: string, label: string, severity: string}>
     */
    private function labelsFor(array $review, array $ids): array
    {
        $rows = [];
        foreach ($review['requirements'] as $line) {
            if (in_array($line['id'], $ids, true)) {
                $rows[] = ['id' => $line['id'], 'label' => $line['label'], 'severity' => $line['severity']];
            }
        }

        return $rows;
    }

    /**
     * A stable digest of the checklist outcome, so the audit row records exactly
     * which finding set was shown to the administrator.
     */
    private function checklistHash(array $review): string
    {
        $shape = [];
        foreach ($review['requirements'] as $line) {
            $shape[$line['id']] = $line['status'];
        }

        return substr(hash('sha256', json_encode($shape)), 0, 32);
    }

    /** Mirrors the existing check: platform Super Admin = system_admin or admin. */
    private function isSuperAdmin(Request $request): bool
    {
        return in_array($this->role($request), ['super_admin', 'system_admin', 'admin'], true);
    }
}
