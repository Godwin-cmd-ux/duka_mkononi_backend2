<?php

namespace App\Services\Ai;

/**
 * Capability #4 - Natural-Language Business Reporting (intent parser).
 *
 * A seller's question ("Niliuza mangapi wiki hii?", "Which products generated
 * the highest profit this month?") is mapped here to an allow-listed, read-only
 * report tool plus a resolved date range. This is deterministic and pure: no
 * model, no HTTP, no database. The model may only be used as a *fallback
 * classifier* (see AiReportController) and can never widen the allow-list.
 */
class ReportIntentParser
{
    public const TOOLS = [
        'revenue_summary',
        'top_products',
        'sales_comparison',
        'expenses',
        'customers',
        'stock_value',
    ];

    public const RANGES = [
        'today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month',
        'this_year', 'last_7_days', 'last_30_days', 'last_90_days',
    ];

    public const METRICS = ['revenue', 'profit', 'units'];

    /**
     * @return array{tool: ?string, metric: string, range_key: ?string, range: ?array{from: string, to: string}, range_source: string, compare: bool, category_filter: ?string, limit: int, confidence: string, matched: array<int, string>}
     */
    public static function parse(string $question, array $options = []): array
    {
        $now = self::normaliseDate($options['now'] ?? date('Y-m-d'));
        $text = self::normalise($question);
        $matched = [];

        $rangeKey = null;
        $range = null;
        $rangeSource = 'none';

        // 1. Explicit relative ranges.
        foreach (self::rangeKeywords() as $key => $words) {
            foreach ($words as $word) {
                if (self::contains($text, $word)) {
                    $rangeKey = $key;
                    $range = self::resolveRange($key, $now);
                    $rangeSource = 'keyword';
                    $matched[] = $word;
                    break 2;
                }
            }
        }

        // 2. "siku N zilizopita" / "last N days".
        if ($rangeKey === null && preg_match('/(?:siku|last|past)\s+(\d{1,3})\s*(?:zilizopita|days?)?/', $text, $m)) {
            $days = max(1, min(365, (int) $m[1]));
            $range = [
                'from' => date('Y-m-d', strtotime('-'.($days - 1).' days', strtotime($now))),
                'to' => $now,
            ];
            $rangeKey = 'last_n_days';
            $rangeSource = 'keyword';
            $matched[] = $m[0];
        }

        // 3. Tool detection by scoring.
        $tool = self::detectTool($text, $matched);

        // stock_value has no meaningful period.
        if ($tool === 'stock_value') {
            $range = null;
            $rangeKey = null;
            $rangeSource = 'none';
        }

        // Default period for period-based tools when none was stated.
        if ($tool !== null && $tool !== 'stock_value' && $range === null) {
            $range = self::resolveRange('this_month', $now);
            $rangeKey = 'this_month';
            $rangeSource = 'default';
        }

        $confidence = 'low';
        if ($tool !== null && $rangeSource === 'keyword') {
            $confidence = 'high';
        } elseif ($tool !== null) {
            $confidence = 'medium';
        }

        return [
            'tool' => $tool,
            'metric' => self::detectMetric($text),
            'range_key' => $rangeKey,
            'range' => $range,
            'range_source' => $rangeSource,
            'compare' => self::wantsComparison($text) || $tool === 'sales_comparison',
            'category_filter' => self::detectCategory($text),
            'limit' => self::detectLimit($text),
            'confidence' => $confidence,
            'matched' => array_values(array_unique($matched)),
        ];
    }

    /**
     * @return array{from: string, to: string}
     */
    public static function resolveRange(string $key, string $now): array
    {
        $now = self::normaliseDate($now);
        $ts = strtotime($now);

        switch ($key) {
            case 'today':
                return ['from' => $now, 'to' => $now];
            case 'yesterday':
                $d = date('Y-m-d', strtotime('-1 day', $ts));

                return ['from' => $d, 'to' => $d];
            case 'this_week':
                $dow = (int) date('N', $ts);

                return ['from' => date('Y-m-d', strtotime('-'.($dow - 1).' days', $ts)), 'to' => $now];
            case 'last_week':
                $dow = (int) date('N', $ts);
                $monday = strtotime('-'.($dow - 1).' days', $ts);

                return [
                    'from' => date('Y-m-d', strtotime('-7 days', $monday)),
                    'to' => date('Y-m-d', strtotime('-1 day', $monday)),
                ];
            case 'this_month':
                return ['from' => date('Y-m-01', $ts), 'to' => $now];
            case 'last_month':
                return [
                    'from' => date('Y-m-01', strtotime('first day of last month', $ts)),
                    'to' => date('Y-m-t', strtotime('last day of last month', $ts)),
                ];
            case 'this_year':
                return ['from' => date('Y-01-01', $ts), 'to' => $now];
            case 'last_7_days':
                return ['from' => date('Y-m-d', strtotime('-6 days', $ts)), 'to' => $now];
            case 'last_30_days':
                return ['from' => date('Y-m-d', strtotime('-29 days', $ts)), 'to' => $now];
            case 'last_90_days':
                return ['from' => date('Y-m-d', strtotime('-89 days', $ts)), 'to' => $now];
            default:
                return ['from' => date('Y-m-01', $ts), 'to' => $now];
        }
    }

    /**
     * The equal-length period immediately before the given range.
     *
     * @param  array{from: string, to: string}  $range
     * @return array{from: string, to: string}
     */
    public static function previousRange(array $range): array
    {
        $from = strtotime($range['from']);
        $to = strtotime($range['to']);
        $lengthDays = (int) floor(($to - $from) / 86400) + 1;

        $prevTo = strtotime('-1 day', $from);
        $prevFrom = strtotime('-'.($lengthDays - 1).' days', $prevTo);

        return ['from' => date('Y-m-d', $prevFrom), 'to' => date('Y-m-d', $prevTo)];
    }

    /** @return array<int, string> */
    public static function supportedExamples(string $locale = 'sw'): array
    {
        if ($locale === 'en') {
            return [
                'How much did I sell this week?',
                'Which products made the most profit this month?',
                'Compare this week with last week.',
                'How much did I spend on transport?',
                'How many customers do I have?',
                'What is the value of my stock?',
            ];
        }

        return [
            'Niliuza mangapi wiki hii?',
            'Bidhaa zipi zilizalisha faida kubwa mwezi huu?',
            'Linganisha wiki hii na wiki iliyopita.',
            'Nilitumia kiasi gani kwenye usafiri?',
            'Nina wateja wangapi?',
            'Thamani ya bidhaa zangu zilizopo ni kiasi gani?',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Internals */
    /* ------------------------------------------------------------------ */

    /** @return array<int, string> */
    private static function detectTool(string $text, array &$matched): ?string
    {
        // Priority tools: an explicit domain word wins.
        $priority = [
            'sales_comparison' => ['linganisha', 'compare', 'compared', 'kulinganisha', 'ikilinganishwa', 'vs', 'tofauti', 'difference'],
            'stock_value' => ['stock', 'inventory', 'thamani', 'zilizopo', 'bidhaa zilizopo', 'stock value', 'inventory value'],
            'expenses' => ['matumizi', 'expenses', 'gharama', 'spent', 'spend', 'nilivyotumia', 'nilitumia', 'costs'],
            'customers' => ['wateja', 'customers', 'clients', 'buyers', 'mteja'],
        ];

        foreach ($priority as $tool => $words) {
            foreach ($words as $word) {
                if (self::contains($text, $word)) {
                    $matched[] = $word;

                    return $tool;
                }
            }
        }

        $ranking = ['top', 'best', 'highest', 'kubwa', 'mengi', 'most', 'top products', 'zilizouza', 'bidhaa zipi'];
        $hasRanking = false;
        foreach ($ranking as $word) {
            if (self::contains($text, $word)) {
                $hasRanking = true;
                $matched[] = $word;
                break;
            }
        }

        $productWord = self::contains($text, 'bidhaa') || self::contains($text, 'product') || self::contains($text, 'products');
        if ($productWord) {
            $matched[] = 'bidhaa';
        }

        if ($hasRanking || ($productWord && self::detectMetric($text) !== 'revenue')) {
            return 'top_products';
        }

        $revenue = ['niliuza', 'nimeuza', 'mauzo', 'mapato', 'sales', 'revenue', 'turnover', 'total sales', 'faida', 'profit', 'margin', 'mauzo ya'];
        foreach ($revenue as $word) {
            if (self::contains($text, $word)) {
                $matched[] = $word;

                return 'revenue_summary';
            }
        }

        if ($productWord) {
            return 'top_products';
        }

        return null;
    }

    private static function detectMetric(string $text): string
    {
        foreach (['faida', 'profit', 'margin', 'gain', 'faida kubwa'] as $word) {
            if (self::contains($text, $word)) {
                return 'profit';
            }
        }
        foreach (['mangapi', 'idadi', 'units', 'quantity', 'how many', 'zimeuzwa'] as $word) {
            if (self::contains($text, $word)) {
                return 'units';
            }
        }

        return 'revenue';
    }

    private static function wantsComparison(string $text): bool
    {
        foreach (['linganisha', 'compare', 'compared', 'vs', 'kuliko', 'ikilinganishwa', 'tofauti'] as $word) {
            if (self::contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    private static function detectCategory(string $text): ?string
    {
        $map = [
            'usafiri' => ['usafiri', 'transport', 'transportation'],
            'umeme' => ['umeme', 'electricity', 'power'],
            'maji' => ['maji', 'water'],
            'kodi ya pango' => ['kodi', 'rent'],
            'mishahara' => ['mshahara', 'salary', 'wages', 'payroll'],
            'chakula' => ['chakula', 'food'],
            'mawasiliano' => ['mawasiliano', 'airtime', 'communication', 'internet'],
            'usafi' => ['usafi', 'cleaning'],
        ];

        foreach ($map as $canonical => $words) {
            foreach ($words as $word) {
                if (self::contains($text, $word)) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    private static function detectLimit(string $text): int
    {
        if (preg_match('/\b(\d{1,2})\b/', $text, $m)) {
            $n = (int) $m[1];
            if ($n >= 1 && $n <= 20) {
                return $n;
            }
        }
        if (self::contains($text, 'kumi')) {
            return 10;
        }
        if (self::contains($text, 'tatu')) {
            return 3;
        }
        if (self::contains($text, 'tano')) {
            return 5;
        }

        return 5;
    }

    /** @return array<string, array<int, string>> */
    private static function rangeKeywords(): array
    {
        return [
            'today' => ['leo', 'today'],
            'yesterday' => ['jana', 'yesterday'],
            'this_week' => ['wiki hii', 'this week', 'week hii'],
            'last_week' => ['wiki iliyopita', 'last week'],
            'this_month' => ['mwezi huu', 'this month'],
            'last_month' => ['mwezi uliopita', 'last month'],
            'this_year' => ['mwaka huu', 'this year'],
            'last_7_days' => ['siku 7', 'last 7 days', 'wiki moja'],
            'last_30_days' => ['siku 30', 'last 30 days', 'mwezi mmoja'],
            'last_90_days' => ['siku 90', 'last 90 days', 'miezi mitatu'],
        ];
    }

    private static function normalise(string $text): string
    {
        $text = mb_strtolower($text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private static function normaliseDate(string $date): string
    {
        $ts = strtotime($date);

        return $ts === false ? date('Y-m-d') : date('Y-m-d', $ts);
    }

    private static function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
