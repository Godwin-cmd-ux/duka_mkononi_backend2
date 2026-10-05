<?php

namespace App\Services\Ai;

use App\Models\AiWeeklyDigest;
use App\Models\User;
use App\Services\SupabaseRow;
use Throwable;

/**
 * Capability #5 - assembles, stores and retrieves weekly health digests.
 *
 * This is the single place that turns real rows into a digest: it retrieves
 * the business's data (current period + the preceding equal-length period),
 * computes every figure with BusinessReporter/BusinessHealthScore, optionally
 * asks the model for a short narrative, and persists the result. Both the API
 * controller and the scheduled command call it, so a seller-triggered digest
 * and a scheduled digest are always identical.
 *
 * Persistence is idempotent per (business_id, period_start, period_end): a
 * second call for the same business and period returns the stored digest
 * instead of creating a duplicate.
 */
class HealthDigestService
{
    public const CAPABILITY = BusinessHealthScore::CAPABILITY;

    public const GENERATED_MANUAL = 'manual';

    public const GENERATED_SCHEDULED = 'scheduled';

    /**
     * The most recent completed week (Monday..Sunday) relative to $now.
     *
     * @return array{start: string, end: string, days: int}
     */
    public static function periodForWeek(?string $now = null): array
    {
        $timestamp = $now !== null ? strtotime($now) : time();
        if ($timestamp === false) {
            $timestamp = time();
        }

        // This week's Monday, then step back one whole week.
        $thisMonday = strtotime('monday this week', $timestamp);
        if ($thisMonday === false) {
            $thisMonday = $timestamp;
        }

        $start = date('Y-m-d', strtotime('-7 days', $thisMonday));
        $end = date('Y-m-d', strtotime('-1 day', $thisMonday));

        return ['start' => $start, 'end' => $end, 'days' => max(1, (int) round((strtotime($end) - strtotime($start)) / 86400) + 1)];
    }

    /** The equal-length window ending the day before $start. */
    public static function previousPeriod(string $start, string $end): array
    {
        $days = max(1, (int) round((strtotime($end) - strtotime($start)) / 86400) + 1);
        $prevEnd = date('Y-m-d', strtotime($start.' -1 day'));
        $prevStart = date('Y-m-d', strtotime($prevEnd.' -'.($days - 1).' days'));

        return ['start' => $prevStart, 'end' => $prevEnd, 'days' => $days];
    }

    public static function defaultLocaleForBusiness(string $businessId): string
    {
        try {
            $row = User::where('business_id', $businessId)->select('language')->first();
            $language = $row ? strtolower(trim((string) ($row->language ?? ''))) : '';
        } catch (Throwable) {
            $language = '';
        }

        $supported = (array) config('ai.locales', ['sw']);
        if ($language !== '' && in_array($language, $supported, true)) {
            return $language;
        }

        return (string) config('ai.default_locale', 'sw');
    }

    /** An existing digest for this business and period, or null. */
    public static function existing(string $businessId, string $periodStart, string $periodEnd): ?SupabaseRow
    {
        try {
            return AiWeeklyDigest::where('business_id', $businessId)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Build (but do not store) a digest for one business and period.
     *
     * @param  array<int, string>  $memberIds
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function build(array $memberIds, string $businessId, string $periodStart, string $periodEnd, string $locale, array $options = []): array
    {
        $currency = (string) ($options['currency'] ?? config('ai.currency', 'TZS'));
        $withAi = (bool) ($options['with_ai'] ?? true);
        $userId = $options['user_id'] ?? null;
        $ip = $options['ip'] ?? null;

        $previous = self::previousPeriod($periodStart, $periodEnd);

        $inputs = self::computeInputs($memberIds, $periodStart, $periodEnd, $previous['start'], $previous['end'], $currency);

        $result = BusinessHealthScore::compute($inputs, [
            'locale' => $locale,
            'currency' => $currency,
        ]);

        $ai = $withAi
            ? self::generateNarrative($result, $inputs, $locale, $currency, $businessId, $userId, $ip)
            : self::emptyAi('AI_SKIPPED');

        $limitations = BusinessHealthScore::limitations($result, $locale);
        if ($ai['degraded'] && $ai['code'] !== null) {
            $limitations[] = HealthNarrator::title('lim', 'lim_ai', $locale);
        }

        return [
            'business_id' => $businessId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_days' => max(1, (int) round((strtotime($periodEnd) - strtotime($periodStart)) / 86400) + 1),
            'previous_period' => ['start' => $previous['start'], 'end' => $previous['end']],
            'locale' => $locale,
            'currency' => $currency,
            'success' => true,
            'capability' => self::CAPABILITY,
            'generated_at' => now()->toISOString(true),
            'score' => $result['score'],
            'band' => $result['band'],
            'band_label' => $result['band_label'],
            'band_interpretation' => $result['band_interpretation'],
            'sufficient' => $result['sufficient'],
            'coverage' => $result['coverage'],
            'components' => $result['components'],
            'missing_components' => $result['missing_components'],
            'changes' => $result['changes'],
            'actions' => $result['actions'],
            'summary_text' => $result['summary_text'],
            'headline' => $ai['headline'] !== '' ? $ai['headline'] : $result['headline'],
            'findings' => $inputs,
            'limitations' => array_values(array_unique($limitations)),
            'ai' => [
                'available' => $ai['available'],
                'degraded' => $ai['degraded'],
                'code' => $ai['code'],
                'interpretation' => $ai['interpretation'],
                'meta' => $ai['meta'],
            ],
        ];
    }

    /**
     * Idempotent generate: returns the stored digest for the period, creating
     * it only when it does not exist (or when $refresh is true).
     *
     * @param  array<int, string>  $memberIds
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function generateForBusiness(array $memberIds, string $businessId, string $periodStart, string $periodEnd, string $locale, array $options = []): array
    {
        $refresh = (bool) ($options['refresh'] ?? false);
        $generatedBy = (string) ($options['generated_by'] ?? self::GENERATED_MANUAL);

        if (! $refresh) {
            $existing = self::existing($businessId, $periodStart, $periodEnd);
            if ($existing) {
                $digest = is_array($existing->digest) ? $existing->digest : [];
                if ($digest !== []) {
                    $digest['id'] = (string) ($existing->id ?? '');
                    $digest['stored'] = true;
                    $digest['reused'] = true;

                    return $digest;
                }
            }
        }

        $digest = self::build($memberIds, $businessId, $periodStart, $periodEnd, $locale, $options);
        $digest['generated_by'] = $generatedBy;
        $digest['delivery'] = [
            'channels' => (array) (config('ai.health.delivery.channels') ?? ['in_app']),
        ];

        $stored = self::store($digest);
        $digest['stored'] = $stored['ok'];
        $digest['reused'] = false;
        if (! $stored['ok']) {
            $digest['storage_error'] = $stored['error'];
        } elseif ($stored['id'] !== '') {
            $digest['id'] = $stored['id'];
        }

        return $digest;
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return array{ok: bool, id: string, error: ?string}
     */
    public static function store(array $digest): array
    {
        $payload = [
            'business_id' => $digest['business_id'] ?? null,
            'period_start' => $digest['period_start'] ?? null,
            'period_end' => $digest['period_end'] ?? null,
            'locale' => $digest['locale'] ?? 'sw',
            'score' => $digest['score'] ?? null,
            'band' => $digest['band'] ?? null,
            'sufficient' => (bool) ($digest['sufficient'] ?? false),
            'coverage' => $digest['coverage'] ?? 0,
            'headline' => mb_substr((string) ($digest['headline'] ?? ''), 0, 300),
            'digest' => $digest,
            'created_at' => now()->toISOString(true),
            'updated_at' => now()->toISOString(true),
        ];

        // Remove any existing row first so a refresh overwrites instead of
        // duplicating (belt-and-braces alongside the pre-check).
        try {
            AiWeeklyDigest::where('business_id', $digest['business_id'] ?? '')
                ->where('period_start', $digest['period_start'] ?? '')
                ->where('period_end', $digest['period_end'] ?? '')
                ->delete();
        } catch (Throwable) {
            // Table may not exist yet; the insert below reports the real error.
        }

        try {
            $row = AiWeeklyDigest::create($payload);

            return ['ok' => true, 'id' => (string) ($row->id ?? ''), 'error' => null];
        } catch (Throwable $error) {
            return ['ok' => false, 'id' => '', 'error' => $error->getMessage()];
        }
    }

    /**
     * Stored digest summaries for a business, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function history(string $businessId, int $limit = 12): array
    {
        try {
            $rows = AiWeeklyDigest::where('business_id', $businessId)
                ->orderByDesc('period_start')
                ->limit($limit)
                ->get();
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $digest = is_array($row->digest) ? $row->digest : [];

            $out[] = [
                'id' => (string) ($row->id ?? ''),
                'period_start' => (string) ($row->period_start ?? ''),
                'period_end' => (string) ($row->period_end ?? ''),
                'locale' => (string) ($row->locale ?? ''),
                'score' => $row->score !== null ? (float) $row->score : ($digest['score'] ?? null),
                'band' => (string) ($row->band ?? ''),
                'band_label' => $digest['band_label'] ?? null,
                'sufficient' => (bool) ($row->sufficient ?? false),
                'headline' => (string) ($row->headline ?? ''),
                'summary_text' => $digest['summary_text'] ?? '',
                'created_at' => $row->created_at ?? null,
            ];
        }

        return $out;
    }

    public static function latest(string $businessId): ?array
    {
        $history = self::history($businessId, 1);

        return $history[0] ?? null;
    }

    /** The full stored digest of the newest period, or null. */
    public static function latestFull(string $businessId): ?array
    {
        try {
            $row = AiWeeklyDigest::where('business_id', $businessId)
                ->orderByDesc('period_start')
                ->first();
        } catch (Throwable) {
            return null;
        }

        if (! $row) {
            return null;
        }

        $digest = is_array($row->digest) ? $row->digest : [];
        if ($digest === []) {
            return null;
        }

        $digest['id'] = (string) ($row->id ?? '');
        $digest['stored'] = true;
        $digest['reused'] = true;

        return $digest;
    }

    /* ------------------------------------------------------------------ */
    /* Internals */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<int, string>  $memberIds
     * @return array{current: array<string, mixed>, previous: array<string, mixed>}
     */
    private static function computeInputs(array $memberIds, string $start, string $end, string $prevStart, string $prevEnd, string $currency): array
    {
        $restock = (array) config('ai.restock', []);
        $overstockDays = max(1.0, (float) ($restock['overstock_days'] ?? 60));

        $products = [];
        $productIndex = [];
        foreach (BusinessDataProvider::productsForBusiness($memberIds) as $product) {
            $products[] = $product;
            if (! empty($product['id'])) {
                $productIndex[(string) $product['id']] = $product;
            }
        }

        $current = self::periodInputs($memberIds, $start, $end, $productIndex, $products, $currency, $overstockDays);
        $previous = self::periodInputs($memberIds, $prevStart, $prevEnd, $productIndex, $products, $currency, $overstockDays);

        // Only comparison-relevant fields are carried forward.
        return [
            'current' => $current,
            'previous' => [
                'revenue' => $previous['revenue'],
                'gross_profit' => $previous['gross_profit'],
                'margin_pct' => $previous['margin_pct'],
                'expenses_total' => $previous['expenses_total'],
                'out_of_stock_count' => $previous['out_of_stock_count'],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $memberIds
     * @param  array<string, array<string, mixed>>  $productIndex
     * @param  array<int, array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    private static function periodInputs(array $memberIds, string $start, string $end, array $productIndex, array $products, string $currency, float $overstockDays): array
    {
        $days = max(1, (int) round((strtotime($end) - strtotime($start)) / 86400) + 1);

        $sales = BusinessDataProvider::salesInRange($memberIds, $start, $end);
        $items = BusinessDataProvider::saleItemsInRange($memberIds, $start, $end);
        $expenses = BusinessDataProvider::expensesInRange($memberIds, $start, $end);

        $revenue = BusinessReporter::revenueSummary($sales, $items, $productIndex, $currency);
        $expense = BusinessReporter::expenseSummary($expenses, null, $currency);

        // Per-product units over this exact period, for stock cover.
        $unitsByProduct = [];
        foreach ($items as $item) {
            $id = trim((string) ($item['product_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $unitsByProduct[$id] = ($unitsByProduct[$id] ?? 0.0) + max(0.0, (float) ($item['quantity'] ?? 0));
        }

        $coverValues = [];
        $withDemand = 0;
        $outOfStock = 0;
        $overstock = 0;
        foreach ($products as $product) {
            $id = (string) ($product['id'] ?? '');
            $stock = max(0.0, (float) ($product['stock'] ?? 0));
            $units = $unitsByProduct[$id] ?? 0.0;

            if ($stock <= 0) {
                $outOfStock++;
                continue;
            }
            if ($units <= 0) {
                continue;
            }

            $daily = $units / $days;
            $cover = $daily > 0 ? $stock / $daily : null;
            if ($cover === null) {
                continue;
            }

            $withDemand++;
            $coverValues[] = $cover;
            if ($cover >= $overstockDays) {
                $overstock++;
            }
        }

        $averageCover = $coverValues !== [] ? array_sum($coverValues) / count($coverValues) : null;

        $topCategory = $expense['by_category'][0] ?? null;
        $expenseRatio = $revenue['revenue'] > 0 ? ($expense['total'] / $revenue['revenue']) * 100 : null;

        return [
            'period_start' => $start,
            'period_end' => $end,
            'days' => $days,
            'revenue' => $revenue['revenue'],
            'cogs' => $revenue['cogs'],
            'gross_profit' => $revenue['gross_profit'],
            'margin_pct' => $revenue['margin_pct'],
            'sales_count' => $revenue['sales_count'],
            'units' => $revenue['units'],
            'unknown_cogs_items' => $revenue['unknown_cogs_items'],
            'expenses_total' => $expense['total'],
            'expense_ratio' => $expenseRatio !== null ? round($expenseRatio, 2) : null,
            'top_expense_category' => $topCategory['category'] ?? null,
            'top_expense_amount' => $topCategory['amount'] ?? null,
            'products_count' => count($products),
            'products_with_demand' => $withDemand,
            'out_of_stock_count' => $outOfStock,
            'overstock_count' => $overstock,
            'average_days_cover' => $averageCover !== null ? round($averageCover, 2) : null,
            'currency' => $currency,
        ];
    }

    /**
     * Optional model narrative. It receives the already-computed score,
     * components, changes and actions and may only add a bounded headline and
     * one interpretation sentence.
     */
    private static function generateNarrative(array $result, array $inputs, string $locale, string $currency, string $businessId, ?string $userId, ?string $ip): array
    {
        $empty = self::emptyAi(null);

        if (($result['sufficient'] ?? false) === false) {
            $empty['code'] = 'INSUFFICIENT_EVIDENCE';
            $empty['reason'] = 'Data haitoshi kutoa ushauri wa AI.';

            return $empty;
        }

        $client = new GeminiClient;
        if (! $client->isEnabled()) {
            $empty['code'] = 'AI_DISABLED';

            return $empty;
        }
        if (! $client->isConfigured()) {
            $empty['code'] = 'AI_NOT_CONFIGURED';

            return $empty;
        }

        $payload = [
            'locale' => $locale,
            'currency' => $currency,
            'score' => $result['score'],
            'band' => $result['band'],
            'coverage' => $result['coverage'],
            'components' => array_map(function ($component) {
                return [
                    'key' => $component['key'],
                    'value' => $component['value'],
                    'score' => $component['score'],
                    'status' => $component['status'],
                ];
            }, $result['components']),
            'changes' => array_map(fn ($change) => ['key' => $change['key'], 'change_pct' => $change['change_pct']], $result['changes']),
            'actions' => array_map(fn ($action) => ['key' => $action['key'], 'title' => $action['title']], $result['actions']),
        ];

        try {
            $aiResult = $client->generateJson(
                [['text' => BusinessHealthScore::userPrompt($payload)]],
                BusinessHealthScore::systemInstruction($locale, $currency),
                [
                    'capability' => self::CAPABILITY,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.2,
                    'maxOutputTokens' => 512,
                ],
            );

            $sanitized = BusinessHealthScore::sanitizeAiResponse($aiResult->data);
            $sanitized['meta'] = $aiResult->meta();

            return $sanitized;
        } catch (AiException $error) {
            $empty['code'] = $error->errorCode;
            $empty['reason'] = $error->getMessage();

            return $empty;
        } catch (Throwable) {
            $empty['code'] = 'AI_ERROR';
            $empty['reason'] = 'AI haipatikani kwa sasa.';

            return $empty;
        }
    }

    private static function emptyAi(?string $code): array
    {
        return [
            'available' => false,
            'degraded' => true,
            'reason' => null,
            'code' => $code,
            'headline' => '',
            'interpretation' => '',
            'meta' => null,
        ];
    }
}
