<?php

namespace App\Services\Ai;

/**
 * Shared AI-output sanitiser (instruction section 3.4, grounding rules).
 *
 * The model may only ever return an explanation that references data we
 * already computed. This class enforces that, once, for every advisory
 * capability:
 *   - insights naming a product id we did not send are dropped;
 *   - ids are never invented;
 *   - severity is clamped to high|medium|low;
 *   - strings are trimmed and length-bounded;
 *   - the insight count is capped.
 *
 * Numeric fields returned by the model are ignored entirely, so a hallucinated
 * price or quantity can never reach the client.
 */
class InsightSanitizer
{
    /**
     * @param  array<int, string>  $allowedIds
     * @return array{available: bool, degraded: bool, reason: ?string, code: ?string, headline: string, insights: array<int, array<string, mixed>>, watchouts: array<int, string>, limitations: array<int, string>, meta: null}
     */
    public static function sanitize(?array $ai, array $allowedIds, int $maxInsights = 12): array
    {
        $maxInsights = max(1, $maxInsights);

        $empty = [
            'available' => false,
            'degraded' => true,
            'reason' => null,
            'code' => null,
            'headline' => '',
            'insights' => [],
            'watchouts' => [],
            'limitations' => [],
            'meta' => null,
        ];

        if (! is_array($ai)) {
            return $empty;
        }

        $allowed = array_flip(array_map('strval', $allowedIds));

        $insights = [];
        $seen = [];

        foreach ((array) ($ai['insights'] ?? []) as $insight) {
            if (! is_array($insight)) {
                continue;
            }

            $productId = isset($insight['product_id']) ? trim((string) $insight['product_id']) : '';
            if ($productId !== '' && ! isset($allowed[$productId])) {
                continue;
            }

            $title = self::text($insight['title'] ?? '', 160);
            $action = self::text($insight['action'] ?? '', 400);
            if ($title === '' && $action === '') {
                continue;
            }

            $key = $productId !== '' ? $productId : 'note:'.md5($title.'|'.$action);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $severity = strtolower(trim((string) ($insight['severity'] ?? 'medium')));
            if (! in_array($severity, ['high', 'medium', 'low'], true)) {
                $severity = 'medium';
            }

            $insights[] = [
                'product_id' => $productId !== '' ? $productId : null,
                'severity' => $severity,
                'title' => $title,
                'action' => $action,
            ];

            if (count($insights) >= $maxInsights) {
                break;
            }
        }

        return [
            'available' => true,
            'degraded' => false,
            'reason' => null,
            'code' => null,
            'headline' => self::text($ai['headline'] ?? '', 240),
            'insights' => $insights,
            'watchouts' => self::stringList($ai['watchouts'] ?? [], 6, 240),
            'limitations' => self::stringList($ai['limitations'] ?? [], 6, 240),
            'meta' => null,
        ];
    }

    private static function text($value, int $max): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        return mb_substr($clean, 0, $max);
    }

    /** @return array<int, string> */
    private static function stringList($values, int $maxItems, int $maxLength): array
    {
        $out = [];
        foreach ((array) $values as $value) {
            $text = self::text($value, $maxLength);
            if ($text !== '') {
                $out[] = $text;
            }
            if (count($out) >= $maxItems) {
                break;
            }
        }

        return $out;
    }
}
