<?php

namespace App\Services\Ai;

/**
 * Capability #20 - Natural-Language Admin Analytics.
 *
 * A system administrator asks about PLATFORM OPERATIONS ("How many sellers
 * joined last month?", "How many users are online now?", "How many businesses
 * are awaiting certification?") and gets back a table, a simple chart and a
 * concise explanation grounded in the platform's own rows.
 *
 * WHAT THIS SERVICE IS
 * --------------------
 * The intent parser and the arithmetic. It is pure: arrays in, arrays out, no
 * model, no HTTP, no database, no Laravel helpers - so the whole contract is
 * unit-testable and the numbers can never come from a language model.
 *
 * WHAT IT CANNOT DO (by construction)
 * -----------------------------------
 *  - It cannot run SQL. There is no query here and no query text can be built
 *    from a question: the caller may only ask for one of `TOOLS`, and every tool
 *    is computed in PHP from rows the controller read with a bounded,
 *    time-filtered query over the platform's own tables.
 *  - It cannot widen itself. The optional model step (see the controller) may
 *    only NARROW a tool choice to an id that is already in `TOOLS` and add one
 *    acknowledgement sentence that passes the sanitiser; it can never add a
 *    tool, a range, a role or a number.
 *  - It cannot report on a person. Only aggregates leave this service, plus (for
 *    the certification tool) capped BUSINESS names. `FORBIDDEN_KEYS` is enforced
 *    on every payload before it is returned, so an email, phone, full name,
 *    address, credential or token can never be surfaced - even by mistake.
 *  - It cannot mix presence with history: `presence_now` is a live snapshot of
 *    the presence window and `activity_history` is recorded activity in a past
 *    period. Each answer says which one it is, in the user's language.
 *
 * SCOPE: every tool is platform-wide, so this service only ever runs behind the
 * strict system-admin authorisation of its controller. It takes no business id
 * from the client and no tenant filter, so cross-tenant access is not possible
 * (and not offered as a feature).
 */
class AdminAnalytics
{
    public const CAPABILITY = 'admin_analytics';

    /** The allow-listed, read-only analytics tools. Nothing else is runnable. */
    public const TOOLS = [
        'users_joined',
        'presence_now',
        'activity_history',
        'certification_queue',
        'platform_totals',
        'revenue_trend',
    ];

    /** The named periods a question may resolve to. */
    public const RANGES = [
        'today',
        'yesterday',
        'this_week',
        'last_week',
        'this_month',
        'last_month',
        'this_year',
        'last_7_days',
        'last_30_days',
        'last_90_days',
    ];

    /** The role filter a question may apply (never a person). */
    public const ROLE_FILTERS = ['admin', 'seller', 'customer', 'client', 'system_admin'];

    /** A single answer may cover at most this many days. */
    public const MAX_RANGE_DAYS = 366;

    /** Named business rows in a certification answer. */
    public const MAX_NAMED_BUSINESSES = 5;

    /**
     * Keys that must never appear in a payload. Checked on every response
     * payload by `assertNoPersonalFields()` (pinned by the unit test).
     */
    public const FORBIDDEN_KEYS = [
        'email', 'phone', 'full_name', 'name', 'address', 'location', 'password',
        'token', 'jwt', 'api_key', 'secret', 'ip_address', 'user_agent', 'user_id',
        'seller_id', 'customer_id', 'national_id', 'otp', 'code',
    ];

    /** Presence means "seen inside this many minutes". Mirrors /api/admin/stats. */
    public const PRESENCE_WINDOW_MINUTES = 5;

    /** Tools whose answer is a live snapshot rather than a period. */
    public const LIVE_TOOLS = ['presence_now', 'certification_queue', 'platform_totals'];

    /* ------------------------------------------------------------------ */
    /* Intent                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Map a natural-language question onto one allow-listed tool + a period.
     *
     * Deterministic keyword matching over the normalised text. A question that
     * matches no tool returns `tool: null` and the caller answers with the
     * supported examples instead of guessing.
     *
     * @return array{tool: ?string, range_key: ?string, range: ?array{from: string, to: string}, range_source: string, role_filter: ?string, limit: int, confidence: string, matched: array<int, string>, live: bool}
     */
    public static function parse(string $question, array $options = []): array
    {
        $now = self::normaliseDate($options['now'] ?? date('Y-m-d'));
        $maxDays = max(7, (int) ($options['max_range_days'] ?? self::MAX_RANGE_DAYS));
        $text = self::normalise($question);
        $matched = [];

        $rangeKey = null;
        $range = null;
        $rangeSource = 'none';

        foreach (self::rangeKeywords() as $key => $words) {
            foreach ($words as $word) {
                if ($word !== '' && str_contains($text, self::normalise($word))) {
                    $rangeKey = $key;
                    $range = self::resolveRange($key, $now);
                    $rangeSource = 'keyword';
                    $matched[] = $word;
                    break 2;
                }
            }
        }

        if ($rangeKey === null && preg_match('/(?:siku|last|past|days?)\s+(\d{1,3})/', $text, $m)) {
            $days = max(1, min($maxDays, (int) $m[1]));
            $range = [
                'from' => date('Y-m-d', strtotime('-'.($days - 1).' days', strtotime($now))),
                'to' => $now,
            ];
            $rangeKey = 'last_n_days';
            $rangeSource = 'keyword';
            $matched[] = $m[0];
        }

        $tool = self::detectTool($text, $matched);

        /* A live tool has no period: saying "last month" about "who is online
         * now" would be wrong, so the period is dropped and reported as such. */
        $live = $tool !== null && in_array($tool, self::LIVE_TOOLS, true);
        if ($live) {
            $range = null;
            $rangeKey = null;
            $rangeSource = 'none';
        }

        if ($tool !== null && ! $live && $range === null) {
            $range = self::resolveRange('this_month', $now);
            $rangeKey = 'this_month';
            $rangeSource = 'default';
        }

        $role = self::detectRole($text);
        if ($role !== null) {
            $matched[] = $role;
        }

        return [
            'tool' => $tool,
            'range_key' => $rangeKey,
            'range' => $range,
            'range_source' => $rangeSource,
            'role_filter' => $role,
            'limit' => self::detectLimit($text),
            'confidence' => $tool === null ? 'low' : ($rangeSource === 'keyword' ? 'high' : 'medium'),
            'matched' => array_values(array_unique($matched)),
            'live' => $live,
        ];
    }

    /**
     * The exact calendar window a range key means.
     *
     * @return array{from: string, to: string}
     */
    public static function resolveRange(string $key, string $now): array
    {
        $now = self::normaliseDate($now);
        $ts = strtotime($now);
        $today = date('Y-m-d', $ts);
        $monday = date('Y-m-d', strtotime('monday this week', $ts));
        $sunday = date('Y-m-d', strtotime('sunday this week', $ts));

        return match ($key) {
            'today' => ['from' => $today, 'to' => $today],
            'yesterday' => [
                'from' => date('Y-m-d', strtotime('-1 day', $ts)),
                'to' => date('Y-m-d', strtotime('-1 day', $ts)),
            ],
            'this_week' => ['from' => $monday, 'to' => $sunday],
            'last_week' => [
                'from' => date('Y-m-d', strtotime('-7 days', strtotime($monday))),
                'to' => date('Y-m-d', strtotime('-1 day', strtotime($monday))),
            ],
            'this_month' => ['from' => date('Y-m-01', $ts), 'to' => $today],
            'last_month' => [
                'from' => date('Y-m-01', strtotime('first day of last month', $ts)),
                'to' => date('Y-m-t', strtotime('last day of last month', $ts)),
            ],
            'this_year' => ['from' => date('Y-01-01', $ts), 'to' => $today],
            'last_7_days' => ['from' => date('Y-m-d', strtotime('-6 days', $ts)), 'to' => $today],
            'last_30_days' => ['from' => date('Y-m-d', strtotime('-29 days', $ts)), 'to' => $today],
            'last_90_days' => ['from' => date('Y-m-d', strtotime('-89 days', $ts)), 'to' => $today],
            default => ['from' => date('Y-m-01', $ts), 'to' => $today],
        };
    }

    /** The period immediately before the given one, for a comparison. */
    public static function previousRange(array $range): array
    {
        $from = strtotime($range['from']);
        $to = strtotime($range['to']);
        $days = max(1, (int) round(($to - $from) / 86400) + 1);

        return [
            'from' => date('Y-m-d', strtotime('-'.$days.' days', $from)),
            'to' => date('Y-m-d', strtotime('-1 day', $from)),
        ];
    }

    /**
     * Days in a range, used to refuse an absurd window before anything is read.
     */
    public static function rangeDays(array $range): int
    {
        $from = strtotime($range['from']);
        $to = strtotime($range['to']);

        return max(1, (int) round(($to - $from) / 86400) + 1);
    }

    /** Clamp a requested window to the documented bound. */
    public static function clampRange(array $range, int $maxDays): array
    {
        $maxDays = max(1, $maxDays);
        if (self::rangeDays($range) <= $maxDays) {
            return $range;
        }

        return [
            'from' => date('Y-m-d', strtotime('-'.($maxDays - 1).' days', strtotime($range['to']))),
            'to' => $range['to'],
        ];
    }

    /** @return array<int, string> */
    public static function supportedExamples(string $locale = 'sw'): array
    {
        return [
            self::msg('example_sellers', $locale),
            self::msg('example_online', $locale),
            self::msg('example_certification', $locale),
            self::msg('example_revenue', $locale),
            self::msg('example_activity', $locale),
            self::msg('example_totals', $locale),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Aggregation (pure arithmetic over already-read rows)                */
    /* ------------------------------------------------------------------ */

    /**
     * Users created inside the range, counted by role and status.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, by_role: array<string, int>, by_status: array<string, int>}
     */
    public static function countUsersJoined(array $rows, array $range): array
    {
        $byRole = [];
        $byStatus = [];

        foreach ($rows as $row) {
            $created = substr((string) ($row['created_at'] ?? ''), 0, 10);
            if ($created === '' || $created < $range['from'] || $created > $range['to']) {
                continue;
            }
            $role = strtolower(trim((string) ($row['role'] ?? '')));
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            $byRole[$role === '' ? 'unknown' : $role] = ($byRole[$role === '' ? 'unknown' : $role] ?? 0) + 1;
            $byStatus[$status === '' ? 'unknown' : $status] = ($byStatus[$status === '' ? 'unknown' : $status] ?? 0) + 1;
        }

        arsort($byRole);
        arsort($byStatus);

        return ['total' => array_sum($byRole), 'by_role' => $byRole, 'by_status' => $byStatus];
    }

    /**
     * Recorded activity in the range, counted by action per day.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, by_action: array<string, int>, by_day: array<string, int>, busiest_day: ?array{day: string, count: int}}
     */
    public static function countActivity(array $rows, array $range): array
    {
        $byAction = [];
        $byDay = [];

        foreach ($rows as $row) {
            $day = substr((string) ($row['created_at'] ?? ''), 0, 10);
            if ($day === '' || $day < $range['from'] || $day > $range['to']) {
                continue;
            }
            $action = strtoupper(trim((string) ($row['action'] ?? '')));
            $action = $action === '' ? 'UNKNOWN' : $action;
            $byAction[$action] = ($byAction[$action] ?? 0) + 1;
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;
        }

        arsort($byAction);
        ksort($byDay);

        $busiest = null;
        foreach ($byDay as $day => $count) {
            if ($busiest === null || $count > $busiest['count']) {
                $busiest = ['day' => $day, 'count' => $count];
            }
        }

        return ['total' => array_sum($byAction), 'by_action' => $byAction, 'by_day' => $byDay, 'busiest_day' => $busiest];
    }

    /**
     * Which businesses await certification: counted, never listed in full.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{pending: int, certified: int, oldest: ?array{business_name: string, created_at: string, days_waiting: int}, named: array<int, array{business_name: string, created_at: string, days_waiting: int}>}
     */
    public static function summarizeCertification(array $rows, string $now, int $namedLimit): array
    {
        $ts = strtotime(self::normaliseDate($now));
        $pending = [];
        $certified = 0;

        foreach ($rows as $row) {
            if (filter_var($row['is_certified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $certified++;
                continue;
            }
            $created = substr((string) ($row['created_at'] ?? ''), 0, 10);
            $pending[] = [
                'business_name' => mb_substr(trim((string) ($row['business_name'] ?? '')), 0, 80),
                'created_at' => $created,
                'days_waiting' => $created === '' ? 0 : max(0, (int) floor(($ts - strtotime($created)) / 86400)),
            ];
        }

        usort($pending, static fn (array $a, array $b): int => $b['days_waiting'] <=> $a['days_waiting']);

        $limit = max(0, min(self::MAX_NAMED_BUSINESSES, $namedLimit));

        return [
            'pending' => count($pending),
            'certified' => $certified,
            'oldest' => $pending[0] ?? null,
            'named' => $limit === 0 ? [] : array_slice($pending, 0, $limit),
        ];
    }

    /**
     * Sales per day in the range.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: float, by_day: array<string, float>, best_day: ?array{day: string, amount: float}, days: int}
     */
    public static function summarizeRevenue(array $rows, array $range): array
    {
        $byDay = [];

        foreach ($rows as $row) {
            $day = substr((string) ($row['sale_date'] ?? $row['created_at'] ?? ''), 0, 10);
            if ($day === '' || $day < $range['from'] || $day > $range['to']) {
                continue;
            }
            $byDay[$day] = ($byDay[$day] ?? 0.0) + (float) ($row['total_amount'] ?? 0);
        }

        ksort($byDay);

        $best = null;
        foreach ($byDay as $day => $amount) {
            if ($best === null || $amount > $best['amount']) {
                $best = ['day' => $day, 'amount' => round($amount, 2)];
            }
        }

        return [
            'total' => round(array_sum($byDay), 2),
            'by_day' => array_map(static fn ($v): float => round((float) $v, 2), $byDay),
            'best_day' => $best,
            'days' => count($byDay),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Presentation                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Turn a tool + its computed numbers into the answer payload: a value, a
     * concise explanation in the user's language, an optional table and chart,
     * the sources, and the honest limitations.
     *
     * @param  array<string, mixed>  $computed
     * @return array<string, mixed>
     */
    public static function present(string $tool, array $parsed, array $computed, string $locale): array
    {
        $range = $parsed['range'] ?? null;
        $rangeLabel = $range === null
            ? self::msg('range_live', $locale)
            : self::msg('range_from_to', $locale, ['from' => $range['from'], 'to' => $range['to']]);

        $payload = [
            'tool' => $tool,
            'tool_label' => self::msg('tool_'.$tool, $locale),
            'live' => in_array($tool, self::LIVE_TOOLS, true),
            'range' => $range,
            'range_key' => $parsed['range_key'] ?? null,
            'range_label' => $rangeLabel,
            'range_source' => $parsed['range_source'] ?? 'none',
            'role_filter' => $parsed['role_filter'] ?? null,
            'value' => null,
            'value_label' => '',
            'explanation' => '',
            'table' => null,
            'chart' => null,
            'sources' => [],
            'limitations' => [],
        ];

        switch ($tool) {
            case 'users_joined':
                $payload['value'] = $parsed['role_filter'] !== null
                    ? ($computed['by_role'][$parsed['role_filter']] ?? 0)
                    : $computed['total'];
                $payload['value_label'] = $parsed['role_filter'] !== null
                    ? self::msg('metric_role_'.$parsed['role_filter'], $locale)
                    : self::msg('metric_users', $locale);
                $payload['explanation'] = self::msg('sum_users_joined', $locale, [
                    'count' => number_format((int) $payload['value']),
                    'range' => $rangeLabel,
                ]);
                $payload['table'] = [
                    'columns' => [self::msg('col_role', $locale), self::msg('col_count', $locale)],
                    'rows' => self::roleRows($computed['by_role'], $locale),
                ];
                $payload['chart'] = self::barChart(
                    array_map(fn (array $row): string => (string) $row[0], $payload['table']['rows']),
                    array_map(fn (array $row): float => (float) $row[1], $payload['table']['rows']),
                    $payload['value_label']
                );
                $payload['sources'] = [self::msg('source_users', $locale)];
                break;

            case 'presence_now':
                $payload['value'] = (int) $computed['online'];
                $payload['value_label'] = self::msg('metric_online', $locale);
                $payload['explanation'] = self::msg('sum_presence_now', $locale, [
                    'count' => number_format((int) $computed['online']),
                    'minutes' => (string) $computed['window_minutes'],
                ]);
                $payload['table'] = [
                    'columns' => [self::msg('col_role', $locale), self::msg('col_online', $locale)],
                    'rows' => self::roleRows($computed['by_role'], $locale),
                ];
                $payload['chart'] = self::barChart(
                    array_map(fn (array $row): string => (string) $row[0], $payload['table']['rows']),
                    array_map(fn (array $row): float => (float) $row[1], $payload['table']['rows']),
                    self::msg('metric_online', $locale)
                );
                $payload['sources'] = [self::msg('source_presence', $locale)];
                $payload['limitations'][] = self::msg('lim_presence_is_live', $locale, ['minutes' => (string) $computed['window_minutes']]);
                break;

            case 'activity_history':
                $payload['value'] = (int) $computed['total'];
                $payload['value_label'] = self::msg('metric_actions', $locale);
                $payload['explanation'] = self::msg('sum_activity', $locale, [
                    'count' => number_format((int) $computed['total']),
                    'range' => $rangeLabel,
                ]) . ($computed['busiest_day'] === null ? '' : ' ' . self::msg('sum_activity_busiest', $locale, [
                    'day' => (string) $computed['busiest_day']['day'],
                    'count' => number_format((int) $computed['busiest_day']['count']),
                ]));
                $payload['table'] = [
                    'columns' => [self::msg('col_action', $locale), self::msg('col_count', $locale)],
                    'rows' => array_map(static fn ($action, $count): array => [$action, $count], array_keys($computed['by_action']), array_values($computed['by_action'])),
                ];
                $payload['chart'] = self::dayChart($computed['by_day'], self::msg('metric_actions', $locale));
                $payload['sources'] = [self::msg('source_logs', $locale)];
                $payload['limitations'][] = self::msg('lim_history_is_not_presence', $locale);
                break;

            case 'certification_queue':
                $payload['value'] = (int) $computed['pending'];
                $payload['value_label'] = self::msg('metric_pending', $locale);
                $payload['explanation'] = self::msg('sum_certification', $locale, [
                    'pending' => number_format((int) $computed['pending']),
                    'certified' => number_format((int) $computed['certified']),
                ]) . ($computed['oldest'] === null ? '' : ' ' . self::msg('sum_certification_oldest', $locale, [
                    'name' => (string) $computed['oldest']['business_name'],
                    'days' => (string) $computed['oldest']['days_waiting'],
                ]));
                $payload['table'] = empty($computed['named']) ? null : [
                    'columns' => [self::msg('col_business', $locale), self::msg('col_waiting_days', $locale)],
                    'rows' => array_map(static fn (array $row): array => [$row['business_name'], $row['days_waiting']], $computed['named']),
                ];
                $payload['chart'] = [
                    'type' => 'bar',
                    'labels' => [self::msg('metric_pending', $locale), self::msg('metric_certified', $locale)],
                    'values' => [(float) $computed['pending'], (float) $computed['certified']],
                    'metric' => self::msg('metric_businesses', $locale),
                ];
                $payload['sources'] = [self::msg('source_businesses', $locale)];
                $payload['limitations'][] = self::msg('lim_business_names_only', $locale);
                $payload['limitations'][] = self::msg('lim_certification_human', $locale);
                break;

            case 'platform_totals':
                $payload['value'] = (int) $computed['users'];
                $payload['value_label'] = self::msg('metric_users', $locale);
                $payload['explanation'] = self::msg('sum_totals', $locale, [
                    'users' => number_format((int) $computed['users']),
                    'sellers' => number_format((int) $computed['sellers']),
                    'products' => number_format((int) $computed['products']),
                    'customers' => number_format((int) $computed['customers']),
                ]);
                $payload['table'] = [
                    'columns' => [self::msg('col_metric', $locale), self::msg('col_value', $locale)],
                    'rows' => [
                        [self::msg('metric_users', $locale), $computed['users']],
                        [self::msg('metric_sellers', $locale), $computed['sellers']],
                        [self::msg('metric_admins', $locale), $computed['admins']],
                        [self::msg('metric_products', $locale), $computed['products']],
                        [self::msg('metric_customers', $locale), $computed['customers']],
                        [self::msg('metric_sales', $locale), $computed['sales']],
                    ],
                ];
                $payload['chart'] = self::barChart(
                    [self::msg('metric_users', $locale), self::msg('metric_products', $locale), self::msg('metric_customers', $locale)],
                    [(float) $computed['users'], (float) $computed['products'], (float) $computed['customers']],
                    self::msg('chart_totals', $locale)
                );
                $payload['sources'] = [
                    self::msg('source_users', $locale),
                    self::msg('source_products', $locale),
                    self::msg('source_customers', $locale),
                    self::msg('source_sales', $locale),
                ];
                $payload['limitations'][] = self::msg('lim_snapshot', $locale);
                break;

            case 'revenue_trend':
                $payload['value'] = $computed['total'];
                $payload['value_label'] = self::msg('metric_revenue', $locale);
                $payload['explanation'] = self::msg('sum_revenue', $locale, [
                    'amount' => number_format((float) $computed['total']),
                    'range' => $rangeLabel,
                ]) . ($computed['best_day'] === null ? '' : ' ' . self::msg('sum_revenue_best', $locale, [
                    'day' => (string) $computed['best_day']['day'],
                    'amount' => number_format((float) $computed['best_day']['amount']),
                ]));
                $payload['table'] = [
                    'columns' => [self::msg('col_day', $locale), self::msg('col_amount', $locale)],
                    'rows' => array_map(static fn ($day, $amount): array => [$day, $amount], array_keys($computed['by_day']), array_values($computed['by_day'])),
                ];
                $payload['chart'] = self::dayChart($computed['by_day'], self::msg('metric_revenue', $locale));
                $payload['sources'] = [self::msg('source_sales', $locale)];
                break;

            default:
                $payload['explanation'] = self::msg('notice_no_match', $locale);
                $payload['limitations'][] = self::msg('notice_no_match', $locale);
                break;
        }

        if (($parsed['range_source'] ?? 'none') === 'default') {
            $payload['limitations'][] = self::msg('lim_no_range', $locale);
        }
        if (! empty($computed['truncated'])) {
            $payload['limitations'][] = self::msg('lim_capped', $locale, ['rows' => number_format((int) ($computed['rows_read'] ?? 0))]);
        }

        $payload['limitations'][] = self::msg('notice_privacy', $locale);

        return self::assertNoPersonalFields($payload);
    }

    /* ------------------------------------------------------------------ */
    /* Privacy + safety guards                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Walk a payload and refuse any forbidden (personal) key. Returns a list of
     * the offending paths instead of throwing, so a caller can report honestly.
     *
     * @return array<int, string>
     */
    public static function findPersonalFields(array $payload, string $prefix = ''): array
    {
        $found = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $leaf = is_string($key) ? strtolower($key) : '';
            if ($leaf !== '' && in_array($leaf, self::FORBIDDEN_KEYS, true)) {
                $found[] = $path;
            }
            if (is_array($value)) {
                $found = array_merge($found, self::findPersonalFields($value, $path));
            }
        }

        return $found;
    }

    /**
     * Strip any forbidden key from a payload (defence in depth: `present()`
     * never builds one, but a future edit must not be able to leak through).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function assertNoPersonalFields(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                unset($payload[$key]);
                continue;
            }
            if (is_array($value)) {
                $payload[$key] = self::assertNoPersonalFields($value);
            }
        }

        return $payload;
    }

    /**
     * The optional acknowledgement sentence: one short line, no digits, no
     * links, no emails, no phone numbers, no question marks. Anything else is
     * dropped and the deterministic explanation is used as-is.
     */
    public static function sanitizeAckString(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '' || mb_strlen($text) > 160) {
            return null;
        }
        if (preg_match('/\d/u', $text) === 1
            || preg_match('/[\/\\\\@#*_]/u', $text) === 1
            || preg_match('/https?:|www\./iu', $text) === 1
            || str_contains($text, '?')) {
            return null;
        }

        return $text;
    }

    /* ------------------------------------------------------------------ */
    /* Messages (8 locales, identical key sets - pinned by the unit test)  */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, array<string, string>>
     */
    public const MESSAGES = [
        'sw' => [
            'title' => 'Uchanganuzi wa mfumo kwa lugha ya kawaida',
            'subtitle' => 'Uliza kuhusu shughuli za jukwaa na upate majibu kutoka kwa kumbukumbu zilizopo',
            'scope_note' => 'Hii ni kwa msimamizi mkuu peke yake na inaonyesha jumla za jukwaa zima.',
            'ask_placeholder' => 'Mfano: wauzaji wangapi walijiunga mwezi uliopita?',
            'btn_ask' => 'Uliza',
            'asking' => 'Inahesabu...',
            'examples_title' => 'Mifano ya maswali',
            'tool_users_joined' => 'Watumiaji waliojiunga',
            'tool_presence_now' => 'Waliopo mtandaoni sasa',
            'tool_activity_history' => 'Shughuli zilizorekodiwa',
            'tool_certification_queue' => 'Biashara zinazosubiri uthibitisho',
            'tool_platform_totals' => 'Jumla za jukwaa',
            'tool_revenue_trend' => 'Mwenendo wa mauzo',
            'metric_users' => 'Watumiaji',
            'metric_sellers' => 'Wauzaji',
            'metric_admins' => 'Wasimamizi',
            'metric_customers' => 'Wateja',
            'metric_products' => 'Bidhaa',
            'metric_sales' => 'Mauzo',
            'metric_online' => 'Waliopo mtandaoni',
            'metric_actions' => 'Vitendo',
            'metric_businesses' => 'Biashara',
            'metric_certified' => 'Zilizothibitishwa',
            'metric_pending' => 'Zinasubiri',
            'metric_revenue' => 'Mapato',
            'metric_role_admin' => 'Wasimamizi',
            'metric_role_system_admin' => 'Wasimamizi wakuu',
            'metric_role_seller' => 'Wauzaji',
            'metric_role_customer' => 'Wateja',
            'metric_role_client' => 'Wateja',
            'range_live' => 'Sasa hivi',
            'range_from_to' => 'kuanzia {from} hadi {to}',
            'range_today' => 'leo',
            'range_yesterday' => 'jana',
            'range_this_week' => 'wiki hii',
            'range_last_week' => 'wiki iliyopita',
            'range_this_month' => 'mwezi huu',
            'range_last_month' => 'mwezi uliopita',
            'range_this_year' => 'mwaka huu',
            'range_last_7_days' => 'siku 7 zilizopita',
            'range_last_30_days' => 'siku 30 zilizopita',
            'range_last_90_days' => 'siku 90 zilizopita',
            'sum_users_joined' => 'Watumiaji {count} walijiunga {range}.',
            'sum_presence_now' => 'Watumiaji {count} wanaonekana mtandaoni sasa (walionekana ndani ya dakika {minutes}).',
            'sum_activity' => 'Vitendo {count} vilirekodiwa {range}.',
            'sum_activity_busiest' => 'Siku yenye shughuli nyingi ni {day} ({count}).',
            'sum_certification' => 'Biashara {pending} zinasubiri uthibitisho na {certified} zimethibitishwa.',
            'sum_certification_oldest' => 'Iliyosubiri kwa muda mrefu zaidi ni {name} (siku {days}).',
            'sum_totals' => 'Jukwaa lina watumiaji {users}, wauzaji {sellers}, bidhaa {products} na wateja {customers}.',
            'sum_revenue' => 'Mauzo ya {amount} yameingizwa {range}.',
            'sum_revenue_best' => 'Siku yenye mauzo mengi zaidi ni {day} ({amount}).',
            'col_role' => 'Wajibu',
            'col_count' => 'Idadi',
            'col_action' => 'Kitendo',
            'col_business' => 'Biashara',
            'col_waiting_days' => 'Siku zinasubiri',
            'col_metric' => 'Kipimo',
            'col_value' => 'Thamani',
            'col_day' => 'Siku',
            'col_amount' => 'Kiasi',
            'col_online' => 'Waliopo mtandaoni',
            'chart_totals' => 'Jumla',
            'source_users' => 'users (waliojiunga)',
            'source_presence' => 'users.is_online + users.last_seen',
            'source_logs' => 'user_logs (shughuli zilizorekodiwa)',
            'source_businesses' => 'businesses (uthibitisho)',
            'source_products' => 'products',
            'source_customers' => 'customers',
            'source_sales' => 'sales',
            'lim_presence_is_live' => 'Hii ni picha ya sasa (dakika {minutes}), si historia ya shughuli.',
            'lim_history_is_not_presence' => 'Hii ni shughuli iliyorekodiwa, si idadi ya waliopo mtandaoni sasa.',
            'lim_no_range' => 'Hakuna kipindi kilichotajwa, hivyo nimetumia mwezi huu.',
            'lim_capped' => 'Tulikagua safu {rows} tu; idadi kamili inaweza kuwa kubwa zaidi.',
            'lim_business_names_only' => 'Majina ya biashara tu; hakuna taarifa za watu.',
            'lim_certification_human' => 'Uthibitisho hufanywa na msimamizi; hii ni hali ya sasa tu.',
            'lim_snapshot' => 'Hizi ni jumla za sasa, si za kipindi.',
            'notice_privacy' => 'Majibu haya yana jumla na majina ya biashara tu - hakuna barua pepe, namba za simu wala taarifa za mtu mmoja mmoja.',
            'notice_no_match' => 'Sikuweza kuelewa swali hilo. Jaribu mojawapo ya mifano hapa chini.',
            'notice_no_sql' => 'Mfumo haujaandiki SQL; swali lako linachagua kipimo kilichoorodheshwa tu.',
            'error' => 'Imeshindikana kupata jibu. Jaribu tena.',
            'retry' => 'Jaribu tena',
            'loading' => 'Inapakia...',
            'examples' => 'Mifano',
            'example_sellers' => 'Wauzaji wangapi walijiunga mwezi uliopita?',
            'example_online' => 'Watumiaji wangapi wapo mtandaoni sasa?',
            'example_certification' => 'Biashara ngapi zinasubiri uthibitisho?',
            'example_revenue' => 'Mauzo ya wiki hii ni kiasi gani?',
            'example_activity' => 'Shughuli ngapi zilirekodiwa leo?',
            'example_totals' => 'Jukwaa lina watumiaji wangapi kwa jumla?',
        ],
        'en' => [
            'title' => 'Platform analytics in plain language',
            'subtitle' => 'Ask about platform operations and get answers from the recorded rows',
            'scope_note' => 'Super-admin only, and always platform-wide totals.',
            'ask_placeholder' => 'For example: how many sellers joined last month?',
            'btn_ask' => 'Ask',
            'asking' => 'Counting...',
            'examples_title' => 'Example questions',
            'tool_users_joined' => 'Users who joined',
            'tool_presence_now' => 'Online right now',
            'tool_activity_history' => 'Recorded activity',
            'tool_certification_queue' => 'Businesses awaiting certification',
            'tool_platform_totals' => 'Platform totals',
            'tool_revenue_trend' => 'Sales trend',
            'metric_users' => 'Users',
            'metric_sellers' => 'Sellers',
            'metric_admins' => 'Admins',
            'metric_customers' => 'Customers',
            'metric_products' => 'Products',
            'metric_sales' => 'Sales',
            'metric_online' => 'Online now',
            'metric_actions' => 'Actions',
            'metric_businesses' => 'Businesses',
            'metric_certified' => 'Certified',
            'metric_pending' => 'Waiting',
            'metric_revenue' => 'Revenue',
            'metric_role_admin' => 'Admins',
            'metric_role_system_admin' => 'Super admins',
            'metric_role_seller' => 'Sellers',
            'metric_role_customer' => 'Customers',
            'metric_role_client' => 'Customers',
            'range_live' => 'Right now',
            'range_from_to' => 'from {from} to {to}',
            'range_today' => 'today',
            'range_yesterday' => 'yesterday',
            'range_this_week' => 'this week',
            'range_last_week' => 'last week',
            'range_this_month' => 'this month',
            'range_last_month' => 'last month',
            'range_this_year' => 'this year',
            'range_last_7_days' => 'the last 7 days',
            'range_last_30_days' => 'the last 30 days',
            'range_last_90_days' => 'the last 90 days',
            'sum_users_joined' => '{count} users joined {range}.',
            'sum_presence_now' => '{count} users are shown online right now (seen in the last {minutes} minutes).',
            'sum_activity' => '{count} actions were recorded {range}.',
            'sum_activity_busiest' => 'The busiest day was {day} ({count}).',
            'sum_certification' => '{pending} businesses are waiting for certification and {certified} are certified.',
            'sum_certification_oldest' => 'Waiting longest: {name} ({days} days).',
            'sum_totals' => 'The platform holds {users} users, {sellers} sellers, {products} products and {customers} customers.',
            'sum_revenue' => '{amount} of sales were recorded {range}.',
            'sum_revenue_best' => 'The best day was {day} ({amount}).',
            'col_role' => 'Role',
            'col_count' => 'Count',
            'col_action' => 'Action',
            'col_business' => 'Business',
            'col_waiting_days' => 'Days waiting',
            'col_metric' => 'Metric',
            'col_value' => 'Value',
            'col_day' => 'Day',
            'col_amount' => 'Amount',
            'col_online' => 'Online',
            'chart_totals' => 'Totals',
            'source_users' => 'users (join dates)',
            'source_presence' => 'users.is_online + users.last_seen',
            'source_logs' => 'user_logs (recorded activity)',
            'source_businesses' => 'businesses (certification)',
            'source_products' => 'products',
            'source_customers' => 'customers',
            'source_sales' => 'sales',
            'lim_presence_is_live' => 'This is a live snapshot ({minutes} minutes), not a history of activity.',
            'lim_history_is_not_presence' => 'This is recorded activity, not how many users are online now.',
            'lim_no_range' => 'No period was stated, so I used this month.',
            'lim_capped' => 'Only {rows} rows were read; the true count may be higher.',
            'lim_business_names_only' => 'Business names only - no staff or customer details.',
            'lim_certification_human' => 'Certification is decided by an administrator; this is only the current state.',
            'lim_snapshot' => 'These are current totals, not a period.',
            'notice_privacy' => 'This answer contains aggregates and business names only - no emails, phone numbers or per-person details.',
            'notice_no_match' => 'I could not understand that question. Try one of the examples below.',
            'notice_no_sql' => 'No SQL is generated; your question only selects from the listed metrics.',
            'error' => 'Could not get an answer. Please try again.',
            'retry' => 'Try again',
            'loading' => 'Loading...',
            'examples' => 'Examples',
            'example_sellers' => 'How many sellers joined last month?',
            'example_online' => 'How many users are online now?',
            'example_certification' => 'How many businesses are awaiting certification?',
            'example_revenue' => 'What were this week\'s sales?',
            'example_activity' => 'How many actions were recorded today?',
            'example_totals' => 'How many users does the platform have in total?',
        ],
        'fr' => [
            'title' => 'Analyse de la plateforme en langage naturel',
            'subtitle' => 'Posez une question sur l\'activité de la plateforme et obtenez une réponse depuis les données enregistrées',
            'scope_note' => 'Réservé au super administrateur et toujours au niveau de toute la plateforme.',
            'ask_placeholder' => 'Par exemple : combien de vendeurs se sont inscrits le mois dernier ?',
            'btn_ask' => 'Demander',
            'asking' => 'Calcul en cours...',
            'examples_title' => 'Exemples de questions',
            'tool_users_joined' => 'Utilisateurs inscrits',
            'tool_presence_now' => 'En ligne maintenant',
            'tool_activity_history' => 'Activité enregistrée',
            'tool_certification_queue' => 'Entreprises en attente de certification',
            'tool_platform_totals' => 'Totaux de la plateforme',
            'tool_revenue_trend' => 'Tendance des ventes',
            'metric_users' => 'Utilisateurs',
            'metric_sellers' => 'Vendeurs',
            'metric_admins' => 'Administrateurs',
            'metric_customers' => 'Clients',
            'metric_products' => 'Produits',
            'metric_sales' => 'Ventes',
            'metric_online' => 'En ligne',
            'metric_actions' => 'Actions',
            'metric_businesses' => 'Entreprises',
            'metric_certified' => 'Certifiées',
            'metric_pending' => 'En attente',
            'metric_revenue' => 'Chiffre d\'affaires',
            'metric_role_admin' => 'Administrateurs',
            'metric_role_system_admin' => 'Super administrateurs',
            'metric_role_seller' => 'Vendeurs',
            'metric_role_customer' => 'Clients',
            'metric_role_client' => 'Clients',
            'range_live' => 'Maintenant',
            'range_from_to' => 'du {from} au {to}',
            'range_today' => 'aujourd\'hui',
            'range_yesterday' => 'hier',
            'range_this_week' => 'cette semaine',
            'range_last_week' => 'la semaine dernière',
            'range_this_month' => 'ce mois-ci',
            'range_last_month' => 'le mois dernier',
            'range_this_year' => 'cette année',
            'range_last_7_days' => 'les 7 derniers jours',
            'range_last_30_days' => 'les 30 derniers jours',
            'range_last_90_days' => 'les 90 derniers jours',
            'sum_users_joined' => '{count} utilisateurs se sont inscrits {range}.',
            'sum_presence_now' => '{count} utilisateurs sont en ligne maintenant (vus dans les {minutes} dernières minutes).',
            'sum_activity' => '{count} actions ont été enregistrées {range}.',
            'sum_activity_busiest' => 'Le jour le plus actif était {day} ({count}).',
            'sum_certification' => '{pending} entreprises attendent la certification et {certified} sont certifiées.',
            'sum_certification_oldest' => 'En attente depuis le plus longtemps : {name} ({days} jours).',
            'sum_totals' => 'La plateforme compte {users} utilisateurs, {sellers} vendeurs, {products} produits et {customers} clients.',
            'sum_revenue' => '{amount} de ventes ont été enregistrées {range}.',
            'sum_revenue_best' => 'Le meilleur jour était {day} ({amount}).',
            'col_role' => 'Rôle',
            'col_count' => 'Nombre',
            'col_action' => 'Action',
            'col_business' => 'Entreprise',
            'col_waiting_days' => 'Jours d\'attente',
            'col_metric' => 'Indicateur',
            'col_value' => 'Valeur',
            'col_day' => 'Jour',
            'col_amount' => 'Montant',
            'col_online' => 'En ligne',
            'chart_totals' => 'Totaux',
            'source_users' => 'users (dates d\'inscription)',
            'source_presence' => 'users.is_online + users.last_seen',
            'source_logs' => 'user_logs (activité enregistrée)',
            'source_businesses' => 'businesses (certification)',
            'source_products' => 'products',
            'source_customers' => 'customers',
            'source_sales' => 'sales',
            'lim_presence_is_live' => 'Ceci est un instantané actuel ({minutes} minutes), pas un historique.',
            'lim_history_is_not_presence' => 'Ceci est l\'activité enregistrée, pas le nombre d\'utilisateurs en ligne maintenant.',
            'lim_no_range' => 'Aucune période indiquée, j\'ai donc utilisé ce mois-ci.',
            'lim_capped' => 'Seules {rows} lignes ont été lues ; le total réel peut être plus élevé.',
            'lim_business_names_only' => 'Noms des entreprises uniquement - aucune donnée personnelle.',
            'lim_certification_human' => 'La certification est décidée par un administrateur ; ceci n\'est que l\'état actuel.',
            'lim_snapshot' => 'Ce sont des totaux actuels, pas ceux d\'une période.',
            'notice_privacy' => 'Cette réponse ne contient que des totaux et des noms d\'entreprises - aucun e-mail, numéro de téléphone ni donnée personnelle.',
            'notice_no_match' => 'Je n\'ai pas compris la question. Essayez un des exemples ci-dessous.',
            'notice_no_sql' => 'Aucun SQL n\'est généré ; votre question ne choisit que parmi les indicateurs listés.',
            'error' => 'Impossible d\'obtenir une réponse. Réessayez.',
            'retry' => 'Réessayer',
            'loading' => 'Chargement...',
            'examples' => 'Exemples',
            'example_sellers' => 'Combien de vendeurs se sont inscrits le mois dernier ?',
            'example_online' => 'Combien d\'utilisateurs sont en ligne maintenant ?',
            'example_certification' => 'Combien d\'entreprises attendent la certification ?',
            'example_revenue' => 'Quel a été le chiffre d\'affaires cette semaine ?',
            'example_activity' => 'Combien d\'actions ont été enregistrées aujourd\'hui ?',
            'example_totals' => 'Combien d\'utilisateurs la plateforme compte-t-elle au total ?',
        ],
        'hi' => [
            'title' => 'सरल भाषा में प्लेटफ़ॉर्म विश्लेषण',
            'subtitle' => 'प्लेटफ़ॉर्म की गतिविधि के बारे में पूछें और दर्ज डेटा से उत्तर पाएं',
            'scope_note' => 'केवल सुपर एडमिन के लिए, और हमेशा पूरे प्लेटफ़ॉर्म के कुल।',
            'ask_placeholder' => 'उदाहरण: पिछले महीने कितने विक्रेता जुड़े?',
            'btn_ask' => 'पूछें',
            'asking' => 'गिनती हो रही है...',
            'examples_title' => 'उदाहरण प्रश्न',
            'tool_users_joined' => 'जुड़े उपयोगकर्ता',
            'tool_presence_now' => 'अभी ऑनलाइन',
            'tool_activity_history' => 'दर्ज गतिविधि',
            'tool_certification_queue' => 'प्रमाणन की प्रतीक्षा में व्यवसाय',
            'tool_platform_totals' => 'प्लेटफ़ॉर्म कुल',
            'tool_revenue_trend' => 'बिक्री रुझान',
            'metric_users' => 'उपयोगकर्ता',
            'metric_sellers' => 'विक्रेता',
            'metric_admins' => 'एडमिन',
            'metric_customers' => 'ग्राहक',
            'metric_products' => 'उत्पाद',
            'metric_sales' => 'बिक्री',
            'metric_online' => 'ऑनलाइन',
            'metric_actions' => 'कार्रवाइयाँ',
            'metric_businesses' => 'व्यवसाय',
            'metric_certified' => 'प्रमाणित',
            'metric_pending' => 'प्रतीक्षारत',
            'metric_revenue' => 'आय',
            'metric_role_admin' => 'एडमिन',
            'metric_role_system_admin' => 'सुपर एडमिन',
            'metric_role_seller' => 'विक्रेता',
            'metric_role_customer' => 'ग्राहक',
            'metric_role_client' => 'ग्राहक',
            'range_live' => 'अभी',
            'range_from_to' => '{from} से {to} तक',
            'range_today' => 'आज',
            'range_yesterday' => 'कल',
            'range_this_week' => 'इस सप्ताह',
            'range_last_week' => 'पिछले सप्ताह',
            'range_this_month' => 'इस महीने',
            'range_last_month' => 'पिछले महीने',
            'range_this_year' => 'इस वर्ष',
            'range_last_7_days' => 'पिछले 7 दिन',
            'range_last_30_days' => 'पिछले 30 दिन',
            'range_last_90_days' => 'पिछले 90 दिन',
            'sum_users_joined' => '{range} में {count} उपयोगकर्ता जुड़े।',
            'sum_presence_now' => 'अभी {count} उपयोगकर्ता ऑनलाइन दिख रहे हैं (पिछले {minutes} मिनट में देखे गए)।',
            'sum_activity' => '{range} में {count} कार्रवाइयाँ दर्ज हुईं।',
            'sum_activity_busiest' => 'सबसे व्यस्त दिन {day} था ({count})।',
            'sum_certification' => '{pending} व्यवसाय प्रमाणन की प्रतीक्षा में हैं और {certified} प्रमाणित हैं।',
            'sum_certification_oldest' => 'सबसे लंबे समय से प्रतीक्षारत: {name} ({days} दिन)।',
            'sum_totals' => 'प्लेटफ़ॉर्म पर {users} उपयोगकर्ता, {sellers} विक्रेता, {products} उत्पाद और {customers} ग्राहक हैं।',
            'sum_revenue' => '{range} में {amount} की बिक्री दर्ज हुई।',
            'sum_revenue_best' => 'सर्वश्रेष्ठ दिन {day} था ({amount})।',
            'col_role' => 'भूमिका',
            'col_count' => 'संख्या',
            'col_action' => 'कार्रवाई',
            'col_business' => 'व्यवसाय',
            'col_waiting_days' => 'प्रतीक्षा दिन',
            'col_metric' => 'मापदंड',
            'col_value' => 'मान',
            'col_day' => 'दिन',
            'col_amount' => 'राशि',
            'col_online' => 'ऑनलाइन',
            'chart_totals' => 'कुल',
            'source_users' => 'users (जुड़ने की तारीखें)',
            'source_presence' => 'users.is_online + users.last_seen',
            'source_logs' => 'user_logs (दर्ज गतिविधि)',
            'source_businesses' => 'businesses (प्रमाणन)',
            'source_products' => 'products',
            'source_customers' => 'customers',
            'source_sales' => 'sales',
            'lim_presence_is_live' => 'यह वर्तमान स्नैपशॉट है ({minutes} मिनट), गतिविधि का इतिहास नहीं।',
            'lim_history_is_not_presence' => 'यह दर्ज गतिविधि है, अभी ऑनलाइन उपयोगकर्ताओं की संख्या नहीं।',
            'lim_no_range' => 'कोई अवधि नहीं बताई गई, इसलिए मैंने इस महीने का उपयोग किया।',
            'lim_capped' => 'केवल {rows} पंक्तियाँ पढ़ी गईं; वास्तविक संख्या अधिक हो सकती है।',
            'lim_business_names_only' => 'केवल व्यवसाय के नाम - किसी व्यक्ति का विवरण नहीं।',
            'lim_certification_human' => 'प्रमाणन एडमिन तय करता है; यह केवल वर्तमान स्थिति है।',
            'lim_snapshot' => 'ये वर्तमान कुल हैं, किसी अवधि के नहीं।',
            'notice_privacy' => 'इस उत्तर में केवल कुल और व्यवसाय के नाम हैं - कोई ईमेल, फ़ोन नंबर या व्यक्तिगत विवरण नहीं।',
            'notice_no_match' => 'मैं यह प्रश्न समझ नहीं पाया। नीचे दिए उदाहरणों में से एक आज़माएँ।',
            'notice_no_sql' => 'कोई SQL नहीं बनाया जाता; आपका प्रश्न केवल सूचीबद्ध मापदंडों में से चुनता है।',
            'error' => 'उत्तर नहीं मिला। कृपया पुनः प्रयास करें।',
            'retry' => 'पुनः प्रयास',
            'loading' => 'लोड हो रहा है...',
            'examples' => 'उदाहरण',
            'example_sellers' => 'पिछले महीने कितने विक्रेता जुड़े?',
            'example_online' => 'अभी कितने उपयोगकर्ता ऑनलाइन हैं?',
            'example_certification' => 'कितने व्यवसाय प्रमाणन की प्रतीक्षा में हैं?',
            'example_revenue' => 'इस सप्ताह की बिक्री कितनी रही?',
            'example_activity' => 'आज कितनी कार्रवाइयाँ दर्ज हुईं?',
            'example_totals' => 'प्लेटफ़ॉर्म पर कुल कितने उपयोगकर्ता हैं?',
        ],
        'es' => [
            'title' => 'Analítica de la plataforma en lenguaje natural',
            'subtitle' => 'Pregunta por la actividad de la plataforma y recibe respuestas de los datos registrados',
            'scope_note' => 'Solo para el superadministrador y siempre con totales de toda la plataforma.',
            'ask_placeholder' => 'Por ejemplo: ¿cuántos vendedores se registraron el mes pasado?',
            'btn_ask' => 'Preguntar',
            'asking' => 'Contando...',
            'examples_title' => 'Preguntas de ejemplo',
            'tool_users_joined' => 'Usuarios que se registraron',
            'tool_presence_now' => 'En línea ahora',
            'tool_activity_history' => 'Actividad registrada',
            'tool_certification_queue' => 'Empresas pendientes de certificación',
            'tool_platform_totals' => 'Totales de la plataforma',
            'tool_revenue_trend' => 'Tendencia de ventas',
            'metric_users' => 'Usuarios',
            'metric_sellers' => 'Vendedores',
            'metric_admins' => 'Administradores',
            'metric_customers' => 'Clientes',
            'metric_products' => 'Productos',
            'metric_sales' => 'Ventas',
            'metric_online' => 'En línea',
            'metric_actions' => 'Acciones',
            'metric_businesses' => 'Empresas',
            'metric_certified' => 'Certificadas',
            'metric_pending' => 'Pendientes',
            'metric_revenue' => 'Ingresos',
            'metric_role_admin' => 'Administradores',
            'metric_role_system_admin' => 'Superadministradores',
            'metric_role_seller' => 'Vendedores',
            'metric_role_customer' => 'Clientes',
            'metric_role_client' => 'Clientes',
            'range_live' => 'Ahora mismo',
            'range_from_to' => 'del {from} al {to}',
            'range_today' => 'hoy',
            'range_yesterday' => 'ayer',
            'range_this_week' => 'esta semana',
            'range_last_week' => 'la semana pasada',
            'range_this_month' => 'este mes',
            'range_last_month' => 'el mes pasado',
            'range_this_year' => 'este año',
            'range_last_7_days' => 'los últimos 7 días',
            'range_last_30_days' => 'los últimos 30 días',
            'range_last_90_days' => 'los últimos 90 días',
            'sum_users_joined' => '{count} usuarios se registraron {range}.',
            'sum_presence_now' => '{count} usuarios aparecen en línea ahora (vistos en los últimos {minutes} minutos).',
            'sum_activity' => 'Se registraron {count} acciones {range}.',
            'sum_activity_busiest' => 'El día más activo fue {day} ({count}).',
            'sum_certification' => '{pending} empresas esperan certificación y {certified} están certificadas.',
            'sum_certification_oldest' => 'Esperando más tiempo: {name} ({days} días).',
            'sum_totals' => 'La plataforma tiene {users} usuarios, {sellers} vendedores, {products} productos y {customers} clientes.',
            'sum_revenue' => 'Se registraron {amount} en ventas {range}.',
            'sum_revenue_best' => 'El mejor día fue {day} ({amount}).',
            'col_role' => 'Rol',
            'col_count' => 'Cantidad',
            'col_action' => 'Acción',
            'col_business' => 'Empresa',
            'col_waiting_days' => 'Días esperando',
            'col_metric' => 'Indicador',
            'col_value' => 'Valor',
            'col_day' => 'Día',
            'col_amount' => 'Importe',
            'col_online' => 'En línea',
            'chart_totals' => 'Totales',
            'source_users' => 'users (fechas de alta)',
            'source_presence' => 'users.is_online + users.last_seen',
            'source_logs' => 'user_logs (actividad registrada)',
            'source_businesses' => 'businesses (certificación)',
            'source_products' => 'products',
            'source_customers' => 'customers',
            'source_sales' => 'sales',
            'lim_presence_is_live' => 'Esta es una instantánea actual ({minutes} minutos), no un historial.',
            'lim_history_is_not_presence' => 'Esto es actividad registrada, no cuántos usuarios están en línea ahora.',
            'lim_no_range' => 'No se indicó un período, así que usé este mes.',
            'lim_capped' => 'Solo se leyeron {rows} filas; el total real puede ser mayor.',
            'lim_business_names_only' => 'Solo nombres de empresas, sin datos de personas.',
            'lim_certification_human' => 'La certificación la decide un administrador; esto es solo el estado actual.',
            'lim_snapshot' => 'Son totales actuales, no de un período.',
            'notice_privacy' => 'Esta respuesta contiene solo totales y nombres de empresas: sin correos, teléfonos ni datos personales.',
            'notice_no_match' => 'No entendí esa pregunta. Prueba uno de los ejemplos de abajo.',
            'notice_no_sql' => 'No se genera SQL; tu pregunta solo elige entre los indicadores listados.',
            'error' => 'No se pudo obtener respuesta. Inténtalo de nuevo.',
            'retry' => 'Reintentar',
            'loading' => 'Cargando...',
            'examples' => 'Ejemplos',
            'example_sellers' => '¿Cuántos vendedores se registraron el mes pasado?',
            'example_online' => '¿Cuántos usuarios están en línea ahora?',
            'example_certification' => '¿Cuántas empresas esperan certificación?',
            'example_revenue' => '¿Cuánto se vendió esta semana?',
            'example_activity' => '¿Cuántas acciones se registraron hoy?',
            'example_totals' => '¿Cuántos usuarios tiene la plataforma en total?',
        ],
        'ur' => [
            'title' => 'سادہ زبان میں پلیٹ فارم تجزیہ',
            'subtitle' => 'پلیٹ فارم کی کارکردگی کے بارے میں پوچھیں اور درج ریکارڈ سے جواب پائیں',
            'scope_note' => 'صرف سپر ایڈمن کے لیے، اور ہمیشہ پورے پلیٹ فارم کے مجموعے۔',
            'ask_placeholder' => 'مثال: پچھلے مہینے کتنے بیچنے والے شامل ہوئے؟',
            'btn_ask' => 'پوچھیں',
            'asking' => 'گنتی جاری ہے...',
            'examples_title' => 'مثالی سوالات',
            'tool_users_joined' => 'شامل ہونے والے صارفین',
            'tool_presence_now' => 'ابھی آن لائن',
            'tool_activity_history' => 'درج شدہ کارکردگی',
            'tool_certification_queue' => 'تصدیق کے منتظر کاروبار',
            'tool_platform_totals' => 'پلیٹ فارم کے مجموعے',
            'tool_revenue_trend' => 'فروخت کا رجحان',
            'metric_users' => 'صارفین',
            'metric_sellers' => 'بیچنے والے',
            'metric_admins' => 'ایڈمن',
            'metric_customers' => 'گاہک',
            'metric_products' => 'مصنوعات',
            'metric_sales' => 'فروخت',
            'metric_online' => 'آن لائن',
            'metric_actions' => 'کارروائیاں',
            'metric_businesses' => 'کاروبار',
            'metric_certified' => 'تصدیق شدہ',
            'metric_pending' => 'زیر التواء',
            'metric_revenue' => 'آمدنی',
            'metric_role_admin' => 'ایڈمن',
            'metric_role_system_admin' => 'سپر ایڈمن',
            'metric_role_seller' => 'بیچنے والے',
            'metric_role_customer' => 'گاہک',
            'metric_role_client' => 'گاہک',
            'range_live' => 'ابھی',
            'range_from_to' => '{from} سے {to} تک',
            'range_today' => 'آج',
            'range_yesterday' => 'کل',
            'range_this_week' => 'اس ہفتے',
            'range_last_week' => 'پچھلے ہفتے',
            'range_this_month' => 'اس مہینے',
            'range_last_month' => 'پچھلے مہینے',
            'range_this_year' => 'اس سال',
            'range_last_7_days' => 'پچھلے 7 دن',
            'range_last_30_days' => 'پچھلے 30 دن',
            'range_last_90_days' => 'پچھلے 90 دن',
            'sum_users_joined' => '{range} میں {count} صارفین شامل ہوئے۔',
            'sum_presence_now' => 'ابھی {count} صارفین آن لائن ہیں (پچھلے {minutes} منٹ میں دیکھے گئے)۔',
            'sum_activity' => '{range} میں {count} کارروائیاں درج ہوئیں۔',
            'sum_activity_busiest' => 'سب سے مصروف دن {day} تھا ({count})۔',
            'sum_certification' => '{pending} کاروبار تصدیق کے منتظر ہیں اور {certified} تصدیق شدہ ہیں۔',
            'sum_certification_oldest' => 'سب سے زیادہ انتظار: {name} ({days} دن)۔',
            'sum_totals' => 'پلیٹ فارم میں {users} صارفین، {sellers} بیچنے والے، {products} مصنوعات اور {customers} گاہک ہیں۔',
            'sum_revenue' => '{range} میں {amount} کی فروخت درج ہوئی۔',
            'sum_revenue_best' => 'بہترین دن {day} تھا ({amount})۔',
            'col_role' => 'کردار',
            'col_count' => 'تعداد',
            'col_action' => 'کارروائی',
            'col_business' => 'کاروبار',
            'col_waiting_days' => 'انتظار کے دن',
            'col_metric' => 'پیمانہ',
            'col_value' => 'قدر',
            'col_day' => 'دن',
            'col_amount' => 'رقم',
            'col_online' => 'آن لائن',
            'chart_totals' => 'مجموعے',
            'source_users' => 'users (شمولیت کی تاریخیں)',
            'source_presence' => 'users.is_online + users.last_seen',
            'source_logs' => 'user_logs (درج شدہ کارکردگی)',
            'source_businesses' => 'businesses (تصدیق)',
            'source_products' => 'products',
            'source_customers' => 'customers',
            'source_sales' => 'sales',
            'lim_presence_is_live' => 'یہ موجودہ اسنیپ شاٹ ہے ({minutes} منٹ)، کارکردگی کی تاریخ نہیں۔',
            'lim_history_is_not_presence' => 'یہ درج شدہ کارکردگی ہے، ابھی آن لائن صارفین کی تعداد نہیں۔',
            'lim_no_range' => 'کوئی مدت نہیں بتائی گئی، اس لیے میں نے اس مہینے کا استعمال کیا۔',
            'lim_capped' => 'صرف {rows} قطاریں پڑھی گئیں؛ حقیقی تعداد زیادہ ہو سکتی ہے۔',
            'lim_business_names_only' => 'صرف کاروبار کے نام - کسی شخص کی تفصیل نہیں۔',
            'lim_certification_human' => 'تصدیق ایڈمن کرتا ہے؛ یہ صرف موجودہ حالت ہے۔',
            'lim_snapshot' => 'یہ موجودہ مجموعے ہیں، کسی مدت کے نہیں۔',
            'notice_privacy' => 'اس جواب میں صرف مجموعے اور کاروبار کے نام ہیں - کوئی ای میل، فون نمبر یا ذاتی تفصیل نہیں۔',
            'notice_no_match' => 'میں یہ سوال سمجھ نہیں سکا۔ نیچے دی گئی مثالوں میں سے کوئی آزمائیں۔',
            'notice_no_sql' => 'کوئی SQL نہیں بنایا جاتا؛ آپ کا سوال صرف درج پیمانوں میں سے چنتا ہے۔',
            'error' => 'جواب نہیں مل سکا۔ دوبارہ کوشش کریں۔',
            'retry' => 'دوبارہ کوشش',
            'loading' => 'لوڈ ہو رہا ہے...',
            'examples' => 'مثالیں',
            'example_sellers' => 'پچھلے مہینے کتنے بیچنے والے شامل ہوئے؟',
            'example_online' => 'ابھی کتنے صارفین آن لائن ہیں؟',
            'example_certification' => 'کتنے کاروبار تصدیق کے منتظر ہیں؟',
            'example_revenue' => 'اس ہفتے کی فروخت کتنی رہی؟',
            'example_activity' => 'آج کتنی کارروائیاں درج ہوئیں؟',
            'example_totals' => 'پلیٹ فارم میں کل کتنے صارفین ہیں؟',
        ],
        'de' => [
            'title' => 'Plattform-Analyse in einfacher Sprache',
            'subtitle' => 'Fragen Sie nach dem Plattformbetrieb und erhalten Sie Antworten aus den erfassten Daten',
            'scope_note' => 'Nur für Super-Admins und immer plattformweite Summen.',
            'ask_placeholder' => 'Zum Beispiel: Wie viele Verkäufer sind letzten Monat beigetreten?',
            'btn_ask' => 'Fragen',
            'asking' => 'Wird gezählt...',
            'examples_title' => 'Beispielfragen',
            'tool_users_joined' => 'Beigetretene Nutzer',
            'tool_presence_now' => 'Jetzt online',
            'tool_activity_history' => 'Erfasste Aktivität',
            'tool_certification_queue' => 'Betriebe warten auf Zertifizierung',
            'tool_platform_totals' => 'Plattform-Summen',
            'tool_revenue_trend' => 'Umsatztrend',
            'metric_users' => 'Nutzer',
            'metric_sellers' => 'Verkäufer',
            'metric_admins' => 'Admins',
            'metric_customers' => 'Kunden',
            'metric_products' => 'Produkte',
            'metric_sales' => 'Verkäufe',
            'metric_online' => 'Online',
            'metric_actions' => 'Aktionen',
            'metric_businesses' => 'Betriebe',
            'metric_certified' => 'Zertifiziert',
            'metric_pending' => 'Wartend',
            'metric_revenue' => 'Umsatz',
            'metric_role_admin' => 'Admins',
            'metric_role_system_admin' => 'Super-Admins',
            'metric_role_seller' => 'Verkäufer',
            'metric_role_customer' => 'Kunden',
            'metric_role_client' => 'Kunden',
            'range_live' => 'Gerade jetzt',
            'range_from_to' => 'vom {from} bis {to}',
            'range_today' => 'heute',
            'range_yesterday' => 'gestern',
            'range_this_week' => 'diese Woche',
            'range_last_week' => 'letzte Woche',
            'range_this_month' => 'diesen Monat',
            'range_last_month' => 'letzten Monat',
            'range_this_year' => 'dieses Jahr',
            'range_last_7_days' => 'die letzten 7 Tage',
            'range_last_30_days' => 'die letzten 30 Tage',
            'range_last_90_days' => 'die letzten 90 Tage',
            'sum_users_joined' => '{count} Nutzer sind {range} beigetreten.',
            'sum_presence_now' => '{count} Nutzer sind gerade online (in den letzten {minutes} Minuten gesehen).',
            'sum_activity' => '{count} Aktionen wurden {range} erfasst.',
            'sum_activity_busiest' => 'Der aktivste Tag war {day} ({count}).',
            'sum_certification' => '{pending} Betriebe warten auf Zertifizierung und {certified} sind zertifiziert.',
            'sum_certification_oldest' => 'Am längsten wartend: {name} ({days} Tage).',
            'sum_totals' => 'Die Plattform hat {users} Nutzer, {sellers} Verkäufer, {products} Produkte und {customers} Kunden.',
            'sum_revenue' => '{amount} Umsatz wurden {range} erfasst.',
            'sum_revenue_best' => 'Der beste Tag war {day} ({amount}).',
            'col_role' => 'Rolle',
            'col_count' => 'Anzahl',
            'col_action' => 'Aktion',
            'col_business' => 'Betrieb',
            'col_waiting_days' => 'Wartetage',
            'col_metric' => 'Kennzahl',
            'col_value' => 'Wert',
            'col_day' => 'Tag',
            'col_amount' => 'Betrag',
            'col_online' => 'Online',
            'chart_totals' => 'Summen',
            'source_users' => 'users (Beitrittsdaten)',
            'source_presence' => 'users.is_online + users.last_seen',
            'source_logs' => 'user_logs (erfasste Aktivität)',
            'source_businesses' => 'businesses (Zertifizierung)',
            'source_products' => 'products',
            'source_customers' => 'customers',
            'source_sales' => 'sales',
            'lim_presence_is_live' => 'Dies ist eine aktuelle Momentaufnahme ({minutes} Minuten), keine Historie.',
            'lim_history_is_not_presence' => 'Dies ist erfasste Aktivität, nicht wie viele Nutzer gerade online sind.',
            'lim_no_range' => 'Kein Zeitraum genannt, daher habe ich diesen Monat verwendet.',
            'lim_capped' => 'Es wurden nur {rows} Zeilen gelesen; die echte Zahl kann höher sein.',
            'lim_business_names_only' => 'Nur Betriebsnamen - keine Personendaten.',
            'lim_certification_human' => 'Die Zertifizierung entscheidet ein Admin; dies ist nur der aktuelle Stand.',
            'lim_snapshot' => 'Dies sind aktuelle Summen, kein Zeitraum.',
            'notice_privacy' => 'Diese Antwort enthält nur Summen und Betriebsnamen - keine E-Mails, Telefonnummern oder Personendaten.',
            'notice_no_match' => 'Ich habe die Frage nicht verstanden. Probieren Sie ein Beispiel unten.',
            'notice_no_sql' => 'Es wird kein SQL erzeugt; Ihre Frage wählt nur aus den gelisteten Kennzahlen.',
            'error' => 'Keine Antwort erhalten. Bitte versuchen Sie es erneut.',
            'retry' => 'Erneut versuchen',
            'loading' => 'Wird geladen...',
            'examples' => 'Beispiele',
            'example_sellers' => 'Wie viele Verkäufer sind letzten Monat beigetreten?',
            'example_online' => 'Wie viele Nutzer sind jetzt online?',
            'example_certification' => 'Wie viele Betriebe warten auf Zertifizierung?',
            'example_revenue' => 'Wie hoch war der Umsatz diese Woche?',
            'example_activity' => 'Wie viele Aktionen wurden heute erfasst?',
            'example_totals' => 'Wie viele Nutzer hat die Plattform insgesamt?',
        ],
        'zh' => [
            'title' => '用自然语言分析平台',
            'subtitle' => '询问平台运营情况，答案来自已记录的数据',
            'scope_note' => '仅限超级管理员，且始终是全平台汇总。',
            'ask_placeholder' => '例如：上个月新增了多少卖家？',
            'btn_ask' => '提问',
            'asking' => '正在统计...',
            'examples_title' => '示例问题',
            'tool_users_joined' => '新增用户',
            'tool_presence_now' => '当前在线',
            'tool_activity_history' => '已记录活动',
            'tool_certification_queue' => '等待认证的商家',
            'tool_platform_totals' => '平台总量',
            'tool_revenue_trend' => '销售趋势',
            'metric_users' => '用户',
            'metric_sellers' => '卖家',
            'metric_admins' => '管理员',
            'metric_customers' => '顾客',
            'metric_products' => '商品',
            'metric_sales' => '销售',
            'metric_online' => '在线',
            'metric_actions' => '操作',
            'metric_businesses' => '商家',
            'metric_certified' => '已认证',
            'metric_pending' => '待处理',
            'metric_revenue' => '收入',
            'metric_role_admin' => '管理员',
            'metric_role_system_admin' => '超级管理员',
            'metric_role_seller' => '卖家',
            'metric_role_customer' => '顾客',
            'metric_role_client' => '顾客',
            'range_live' => '此刻',
            'range_from_to' => '从 {from} 到 {to}',
            'range_today' => '今天',
            'range_yesterday' => '昨天',
            'range_this_week' => '本周',
            'range_last_week' => '上周',
            'range_this_month' => '本月',
            'range_last_month' => '上个月',
            'range_this_year' => '今年',
            'range_last_7_days' => '过去 7 天',
            'range_last_30_days' => '过去 30 天',
            'range_last_90_days' => '过去 90 天',
            'sum_users_joined' => '{range}有 {count} 位用户注册。',
            'sum_presence_now' => '目前有 {count} 位用户在线（在过去 {minutes} 分钟内活跃）。',
            'sum_activity' => '{range}记录了 {count} 次操作。',
            'sum_activity_busiest' => '最活跃的一天是 {day}（{count}）。',
            'sum_certification' => '{pending} 个商家等待认证，{certified} 个已认证。',
            'sum_certification_oldest' => '等待最久：{name}（{days} 天）。',
            'sum_totals' => '平台共有 {users} 位用户、{sellers} 位卖家、{products} 件商品和 {customers} 位顾客。',
            'sum_revenue' => '{range}记录了 {amount} 的销售额。',
            'sum_revenue_best' => '最好的一天是 {day}（{amount}）。',
            'col_role' => '角色',
            'col_count' => '数量',
            'col_action' => '操作',
            'col_business' => '商家',
            'col_waiting_days' => '等待天数',
            'col_metric' => '指标',
            'col_value' => '数值',
            'col_day' => '日期',
            'col_amount' => '金额',
            'col_online' => '在线',
            'chart_totals' => '总计',
            'source_users' => 'users（注册日期）',
            'source_presence' => 'users.is_online + users.last_seen',
            'source_logs' => 'user_logs（已记录活动）',
            'source_businesses' => 'businesses（认证）',
            'source_products' => 'products',
            'source_customers' => 'customers',
            'source_sales' => 'sales',
            'lim_presence_is_live' => '这是当前快照（{minutes} 分钟），不是活动历史。',
            'lim_history_is_not_presence' => '这是已记录的活动，不是当前在线人数。',
            'lim_no_range' => '未说明时间范围，因此我使用了本月。',
            'lim_capped' => '只读取了 {rows} 行；真实数量可能更多。',
            'lim_business_names_only' => '仅商家名称——不含人员信息。',
            'lim_certification_human' => '认证由管理员决定；这里只是当前状态。',
            'lim_snapshot' => '这些是当前总量，不是某个时间段的。',
            'notice_privacy' => '此答案只包含汇总和商家名称——不含邮箱、电话或个人细节。',
            'notice_no_match' => '我没有理解这个问题。请尝试下面的例子。',
            'notice_no_sql' => '不会生成 SQL；你的问题只会在列出的指标中选择。',
            'error' => '无法获取答案。请重试。',
            'retry' => '重试',
            'loading' => '加载中...',
            'examples' => '示例',
            'example_sellers' => '上个月新增了多少卖家？',
            'example_online' => '现在有多少用户在线？',
            'example_certification' => '有多少商家等待认证？',
            'example_revenue' => '本周的销售额是多少？',
            'example_activity' => '今天记录了多少次操作？',
            'example_totals' => '平台总共有多少用户？',
        ],
    ];

    /** One message in the given locale, with {placeholders} filled. */
    public static function msg(string $key, string $locale, array $params = []): string
    {
        $table = self::MESSAGES[$locale] ?? self::MESSAGES['en'];
        $text = $table[$key] ?? (self::MESSAGES['en'][$key] ?? $key);

        foreach ($params as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /** Every locale carries the same message keys. */
    public static function messagesComplete(): bool
    {
        $english = array_keys(self::MESSAGES['en']);
        sort($english);

        foreach (self::MESSAGES as $locale => $table) {
            $keys = array_keys($table);
            sort($keys);
            if ($keys !== $english) {
                return false;
            }
        }

        return true;
    }

    /** Every tool and range has a label in every locale. */
    public static function labelsComplete(): bool
    {
        foreach (array_keys(self::MESSAGES) as $locale) {
            foreach (self::TOOLS as $tool) {
                if (self::msg('tool_'.$tool, $locale) === 'tool_'.$tool) {
                    return false;
                }
            }
            foreach (self::RANGES as $range) {
                if (self::msg('range_'.$range, $locale) === 'range_'.$range) {
                    return false;
                }
            }
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, int>  $byRole
     * @return array<int, array<int, mixed>>
     */
    private static function roleRows(array $byRole, string $locale): array
    {
        $rows = [];
        foreach ($byRole as $role => $count) {
            $label = self::msg('metric_role_'.$role, $locale);
            $rows[] = [$label === 'metric_role_'.$role ? strtoupper($role) : $label, $count];
        }

        return $rows;
    }

    /** @return array{type: string, labels: array<int, string>, values: array<int, float>, metric: string} */
    private static function barChart(array $labels, array $values, string $metric): array
    {
        return ['type' => 'bar', 'labels' => array_values($labels), 'values' => array_values($values), 'metric' => $metric];
    }

    /** @param array<string, mixed> $byDay */
    private static function dayChart(array $byDay, string $metric): array
    {
        return [
            'type' => 'line',
            'labels' => array_map('strval', array_keys($byDay)),
            'values' => array_map(static fn ($v): float => (float) $v, array_values($byDay)),
            'metric' => $metric,
        ];
    }

    /** @return array<string, array<int, string>> */
    private static function rangeKeywords(): array
    {
        return [
            'today' => ['today', 'leo', 'aujourd', 'heute', 'hoy', '今天', 'आज', 'آج'],
            'yesterday' => ['yesterday', 'jana', 'hier', 'gestern', 'ayer', '昨天', 'कल', 'گزشتہ کل'],
            'this_week' => ['this week', 'wiki hii', 'cette semaine', 'diese woche', 'esta semana', '本周', 'इस सप्ताह', 'اس ہفتے'],
            'last_week' => ['last week', 'wiki iliyopita', 'semaine derniere', 'letzte woche', 'semana pasada', '上周', 'पिछले सप्ताह', 'پچھلے ہفتے'],
            'this_month' => ['this month', 'mwezi huu', 'ce mois', 'diesen monat', 'este mes', '本月', 'इस महीने', 'اس مہینے'],
            'last_month' => ['last month', 'mwezi uliopita', 'mois dernier', 'letzten monat', 'mes pasado', '上个月', 'पिछले महीने', 'پچھلے مہینے'],
            'this_year' => ['this year', 'mwaka huu', 'cette annee', 'dieses jahr', 'este ano', '今年', 'इस वर्ष', 'اس سال'],
            'last_7_days' => ['last 7 days', 'siku 7', '7 derniers jours', 'letzten 7 tage', 'ultimos 7 dias', '过去 7 天'],
            'last_30_days' => ['last 30 days', 'siku 30', '30 derniers jours', 'letzten 30 tage', 'ultimos 30 dias', '过去 30 天'],
            'last_90_days' => ['last 90 days', 'siku 90', '90 derniers jours', 'letzten 90 tage', 'ultimos 90 dias', '过去 90 天'],
        ];
    }

    /**
     * How specific each tool's wording is, used as the tie-break when two tools
     * match the same number of keywords (earlier = more specific). Without this,
     * "how many users are online now?" could fall to the totals tool through
     * "how many users", so the order is explicit and pinned by the unit test.
     */
    private const TOOL_PRIORITY = [
        'presence_now',
        'users_joined',
        'certification_queue',
        'activity_history',
        'revenue_trend',
        'platform_totals',
    ];

    /**
     * The tool whose wording best fits the question: most keyword hits first,
     * then the longest matched keyword (the more specific phrase), then
     * TOOL_PRIORITY. Always an id from `TOOLS`, or null.
     */
    private static function detectTool(string $text, array &$matched): ?string
    {
        $scores = [];

        foreach (self::toolKeywords() as $tool => $keywords) {
            $hits = [];
            $longest = 0;
            foreach ($keywords as $keyword) {
                $needle = self::normalise($keyword);
                if ($needle !== '' && str_contains($text, $needle)) {
                    $hits[] = $keyword;
                    $longest = max($longest, mb_strlen($needle));
                }
            }
            if ($hits !== []) {
                $scores[$tool] = [
                    'score' => count($hits),
                    'longest' => $longest,
                    'priority' => array_search($tool, self::TOOL_PRIORITY, true) ?: 99,
                    'hits' => $hits,
                ];
            }
        }

        if ($scores === []) {
            return null;
        }

        /* Most keyword hits first, then the SPECIFIC tool (TOOL_PRIORITY) and only
         * then the longest phrase. Length alone is not specificity: "how many
         * users are online now?" hits "online now" (presence) and "how many
         * users" (totals), and the totals phrase is longer. */
        uasort($scores, static function (array $a, array $b): int {
            return ($b['score'] <=> $a['score'])
                ?: ($a['priority'] <=> $b['priority'])
                ?: ($b['longest'] <=> $a['longest']);
        });

        $tool = (string) array_key_first($scores);
        foreach ($scores[$tool]['hits'] as $hit) {
            $matched[] = $hit;
        }

        return in_array($tool, self::TOOLS, true) ? $tool : null;
    }

    /** @return array<string, array<int, string>> */
    private static function toolKeywords(): array
    {
        return [
            'presence_now' => [
                'online now', 'who is online', 'users online', 'currently online', 'online',
                'waliopo mtandaoni', 'mtandaoni sasa', 'wangapi wapo mtandaoni', 'mtandaoni',
                'en ligne maintenant', 'en ligne', 'en linea ahora', 'en linea', 'ahora mismo',
                'jetzt online', 'online jetzt', '现在在线', '当前在线', '在线',
                'ऑनलाइन', 'آن لائن',
            ],
            'users_joined' => [
                'joined', 'new users', 'new sellers', 'signed up', 'registrations', 'registered',
                'walijiunga', 'watumiaji wapya', 'wauzaji wapya', 'waliojiunga', 'wapya',
                'inscrits', 'inscription', 'registraron', 'registrarse', 'beigetreten', 'angemeldet',
                '新注册', '新增用户', '新增', '加入', '注册',
                'जुड़े', 'शामिल', 'شامل ہوئے', 'شامل',
            ],
            'certification_queue' => [
                'certification', 'certified', 'awaiting certification', 'waiting for certification',
                'uthibitisho', 'zinasubiri uthibitisho', 'certification des entreprises',
                'certificacion', 'certificadas', 'zertifizierung', 'zertifiziert',
                '认证', '待认证', 'प्रमाणन', 'تصدیق',
            ],
            'activity_history' => [
                'activity', 'actions', 'how many actions', 'recorded activity', 'logs', 'log entries',
                'shughuli', 'vitendo', 'matendo', 'activite', 'activites', 'actividad', 'acciones', 'se registraron',
                'aktivitat', 'aktionen', '操作', '行动', 'गतिविधि', 'कार्रवाइयाँ', 'کارکردگی', 'کارروائیاں',
            ],
            'revenue_trend' => [
                'sales', 'revenue', 'turnover', 'trend', 'mauzo', 'mapato', 'ventes',
                'chiffre', 'ventas', 'ingresos', 'vendio', 'vendieron', 'vendu', 'umsatz', 'verkaufe',
                '销售', '销售额', '收入', 'बिक्री', 'فروخت',
            ],
            'platform_totals' => [
                'total users', 'totals', 'in total', 'how many users', 'how many products',
                'jumla', 'watumiaji wangapi', 'bidhaa ngapi',
                'utilisateurs au total', 'au total', 'en tout', 'totales', 'en total', 'gesamt', 'insgesamt',
                '总共', '总数', '共', 'कुल', 'کل',
            ],
        ];
    }

    private static function detectRole(string $text): ?string
    {
        $map = [
            'seller' => ['seller', 'sellers', 'wauzaji', 'muuzaji', 'vendeur', 'vendedor', 'verkaufer', '卖家', 'विक्रेता', 'بیچنے والے'],
            'system_admin' => ['super admin', 'system admin', 'super-admin', 'msimamizi mkuu', 'super administrateur', 'superadministrador', '超级管理员', 'सुपर एडमिन', 'سپر ایڈمن'],
            'admin' => ['admins', 'administrators', 'wasimamizi', 'administrateurs', 'administradores', '管理员', 'एडमिन', 'ایڈمن'],
            'customer' => ['customers', 'clients', 'wateja', 'clients ', 'clientes', 'kunden', '顾客', '客户', 'ग्राहक', 'گاہک'],
        ];

        foreach ($map as $role => $words) {
            foreach ($words as $word) {
                if (str_contains($text, self::normalise($word))) {
                    return in_array($role, self::ROLE_FILTERS, true) ? $role : null;
                }
            }
        }

        return null;
    }

    private static function detectLimit(string $text): int
    {
        if (preg_match('/\b(\d{1,2})\b/', $text, $m)) {
            return max(1, min(self::MAX_NAMED_BUSINESSES, (int) $m[1]));
        }

        return 3;
    }

    /**
     * Case- and accent-insensitive form used for every keyword comparison, so a
     * user may type "en línea ahora", "en linea ahora", "Verkäufer" or
     * "verkaufer" and match the same keyword. Accents are folded to their base
     * letter; punctuation becomes a space; runs of whitespace collapse.
     */
    private static function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['_', '\'', '’', '`'], [' ', '', '', ''], $text);
        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
        ]);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function normaliseDate(string $date): string
    {
        $ts = strtotime($date);

        return $ts === false ? date('Y-m-d') : date('Y-m-d', $ts);
    }
}
