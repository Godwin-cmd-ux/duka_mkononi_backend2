<?php

namespace App\Services\Ai;

/**
 * Capability #6 - Anomaly & Leak Detection (rule engine).
 *
 * Detection is 100% deterministic and runs in trusted PHP over the business's
 * own rows. The model is never asked whether something is anomalous, never
 * computes a figure, and never sees raw tables - it only narrates the alerts
 * this class produced (see AiAnomalyController::explain()).
 *
 * Every alert carries a stable `key` built from the rule, the subject and a
 * fingerprint of the evidence, so:
 *   - the same unchanged evidence keeps the same key (a dismissal sticks),
 *   - changed evidence produces a new key (a real recurrence re-raises).
 *
 * Evidence floors (`min_*`) mean we stay silent instead of guessing when the
 * data is too thin - a potential anomaly is never presented as proven fraud.
 */
class AnomalyDetector
{
    public const CAPABILITY = 'anomaly';

    public const RULE_BELOW_COST = 'BELOW_COST_SALE';

    public const RULE_MARGIN_DROP = 'MARGIN_DROP';

    public const RULE_DUPLICATE = 'DUPLICATE_SALES';

    public const RULE_EXPENSE_SPIKE = 'EXPENSE_SPIKE';

    public const RULE_STOCK_MISMATCH = 'STOCK_MISMATCH';

    public const SEVERITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    /**
     * Run every rule over the supplied windows.
     *
     * @param  array<int, array<string, mixed>>  $products   id, name, stock, price (cost), expected_selling_price
     * @param  array<int, array<string, mixed>>  $saleItems  sale_id, product_id, quantity, unit_price, total_price, sale_date
     * @param  array<int, array<string, mixed>>  $expenses   amount, category, expense_date, user_id
     * @param  array{recent: array{from: string, to: string}, prior: array{from: string, to: string}}  $windows
     * @return array<int, array<string, mixed>>
     */
    public static function detect(array $products, array $saleItems, array $expenses, array $windows, array $options = []): array
    {
        $locale = (string) ($options['locale'] ?? 'sw');
        $currency = (string) ($options['currency'] ?? 'TZS');
        $maxAlerts = max(1, (int) ($options['max_alerts'] ?? 20));

        $recentFrom = (string) ($windows['recent']['from'] ?? '');
        $recentTo = (string) ($windows['recent']['to'] ?? '');
        $priorFrom = (string) ($windows['prior']['from'] ?? '');
        $priorTo = (string) ($windows['prior']['to'] ?? '');

        $recentItems = self::within($saleItems, $recentFrom, $recentTo);
        $priorItems = self::within($saleItems, $priorFrom, $priorTo);
        $recentExpenses = self::within($expenses, $recentFrom, $recentTo, 'expense_date');
        $priorExpenses = self::within($expenses, $priorFrom, $priorTo, 'expense_date');

        // Attach the recorded product cost to every sale line so margin and
        // below-cost rules can compare like with like (null when unknown).
        $costById = [];
        foreach ($products as $product) {
            if (! empty($product['id'])) {
                $costById[(string) $product['id']] = isset($product['price']) && (float) $product['price'] > 0
                    ? (float) $product['price']
                    : null;
            }
        }
        foreach ($recentItems as &$line) {
            $line['_cost'] = $costById[(string) ($line['product_id'] ?? '')] ?? null;
        }
        unset($line);
        foreach ($priorItems as &$line) {
            $line['_cost'] = $costById[(string) ($line['product_id'] ?? '')] ?? null;
        }
        unset($line);

        $alerts = array_merge(
            self::belowCostRules($products, $recentItems, $locale, $currency),
            self::marginDropRules($recentItems, $priorItems, $options, $locale, $currency, $recentFrom, $recentTo, $priorFrom, $priorTo),
            self::duplicateRules($recentItems, $products, $locale, $currency),
            self::expenseSpikeRules($recentExpenses, $priorExpenses, $options, $locale, $currency, $recentFrom, $recentTo),
            self::stockMismatchRules($products, $locale, $currency),
        );

        usort($alerts, function (array $a, array $b) {
            $bySeverity = self::SEVERITY_ORDER[$a['severity']] <=> self::SEVERITY_ORDER[$b['severity']];

            return $bySeverity !== 0 ? $bySeverity : strcmp((string) $a['rule'], (string) $b['rule']);
        });

        return array_slice($alerts, 0, $maxAlerts);
    }

    /* ------------------------------------------------------------------ */
    /* Rules                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Sale lines priced below the product's recorded cost (possible leak,
     * unrecorded discount or stale cost data - we say "verify", never "theft").
     *
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function belowCostRules(array $products, array $items, string $locale, string $currency): array
    {
        $byId = [];
        foreach ($products as $product) {
            if (! empty($product['id'])) {
                $byId[(string) $product['id']] = $product;
            }
        }

        $hits = [];
        foreach ($items as $item) {
            $productId = (string) ($item['product_id'] ?? '');
            if ($productId === '' || ! isset($byId[$productId])) {
                continue;
            }
            $cost = (float) ($byId[$productId]['price'] ?? 0);
            $unit = (float) ($item['unit_price'] ?? 0);
            if ($cost <= 0 || $unit <= 0) {
                continue;
            }
            // Integer-currency guard: ignore sub-unit float noise.
            if ($unit >= $cost - 0.5) {
                continue;
            }

            $hits[$productId] ??= [
                'name' => (string) ($byId[$productId]['name'] ?? $productId),
                'cost' => $cost,
                'worst_unit' => $unit,
                'units' => 0,
                'loss' => 0.0,
                'dates' => [],
                'sale_ids' => [],
            ];
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $hits[$productId]['units'] += $qty;
            $hits[$productId]['loss'] += ($cost - $unit) * $qty;
            $hits[$productId]['worst_unit'] = min($hits[$productId]['worst_unit'], $unit);
            $hits[$productId]['dates'][] = (string) ($item['sale_date'] ?? '');
            $hits[$productId]['sale_ids'][] = (string) ($item['sale_id'] ?? '');
        }

        $out = [];
        foreach ($hits as $productId => $hit) {
            $figures = [
                'cost' => round($hit['cost'], 2),
                'unit_price' => round($hit['worst_unit'], 2),
                'units' => (int) $hit['units'],
                'estimated_loss' => round($hit['loss'], 2),
            ];
            $out[] = self::alert(
                self::RULE_BELOW_COST,
                'high',
                $hit['name'],
                $locale,
                $currency,
                $figures,
                array_map(fn ($date, $saleId) => [
                    'ref' => mb_substr($saleId, 0, 8),
                    'date' => mb_substr((string) $date, 0, 10),
                    'unit_price' => $figures['unit_price'],
                    'cost' => $figures['cost'],
                ], array_slice($hit['dates'], 0, 5), array_slice($hit['sale_ids'], 0, 5)),
                [$productId, $figures['unit_price'], $figures['units'], implode('|', array_slice($hit['dates'], 0, 3))],
            );
        }

        return $out;
    }

    /**
     * Gross-margin drop between two adjacent windows, computed from sale lines
     * with a known cost only. Below `min_margin_lines` in either window there
     * is not enough evidence and no alert is raised.
     *
     * @param  array<int, array<string, mixed>>  $recent
     * @param  array<int, array<string, mixed>>  $prior
     * @return array<int, array<string, mixed>>
     */
    private static function marginDropRules(array $recent, array $prior, array $options, string $locale, string $currency, string $recentFrom, string $recentTo, string $priorFrom, string $priorTo): array
    {
        $minLines = max(1, (int) ($options['min_margin_lines'] ?? 3));
        $dropPp = max(0.0, (float) ($options['margin_drop_pp'] ?? 10.0));

        $r = self::marginOf($recent);
        $p = self::marginOf($prior);

        if ($r['lines'] < $minLines || $p['lines'] < $minLines) {
            return [];
        }
        if ($r['revenue'] <= 0 || $p['revenue'] <= 0) {
            return [];
        }

        $drop = round($p['margin'] - $r['margin'], 2);
        if ($drop < $dropPp) {
            return [];
        }

        $figures = [
            'recent_margin_pct' => round($r['margin'], 2),
            'prior_margin_pct' => round($p['margin'], 2),
            'drop_pp' => $drop,
            'recent_revenue' => round($r['revenue'], 2),
            'prior_revenue' => round($p['revenue'], 2),
            'recent_lines' => $r['lines'],
            'prior_lines' => $p['lines'],
        ];

        $evidence = [
            ['ref' => $recentFrom.' .. '.$recentTo, 'date' => $recentTo, 'value' => $figures['recent_margin_pct']],
            ['ref' => $priorFrom.' .. '.$priorTo, 'date' => $priorTo, 'value' => $figures['prior_margin_pct']],
        ];

        return [self::alert(
            self::RULE_MARGIN_DROP,
            $r['margin'] < 0 ? 'high' : 'medium',
            $recentFrom.' .. '.$recentTo,
            $locale,
            $currency,
            $figures,
            $evidence,
            [$drop, $figures['recent_margin_pct'], $figures['prior_margin_pct'], $r['lines'], $p['lines']],
        )];
    }

    /**
     * Several separate sale records on the same day with the same product,
     * quantity and unit price - a classic duplicate-entry signature. Presented
     * strictly as "possible", with the record refs to verify.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private static function duplicateRules(array $items, array $products, string $locale, string $currency): array
    {
        $names = [];
        foreach ($products as $product) {
            if (! empty($product['id'])) {
                $names[(string) $product['id']] = (string) ($product['name'] ?? $product['id']);
            }
        }

        $groups = [];
        foreach ($items as $item) {
            $productId = (string) ($item['product_id'] ?? '');
            if ($productId === '') {
                continue;
            }
            $date = mb_substr((string) ($item['sale_date'] ?? ''), 0, 10);
            $qty = (int) ($item['quantity'] ?? 0);
            $unit = round((float) ($item['unit_price'] ?? 0), 2);
            if ($date === '' || $qty <= 0 || $unit <= 0) {
                continue;
            }

            $sig = $productId.'|'.$date.'|'.$qty.'|'.$unit;
            $groups[$sig]['product_id'] = $productId;
            $groups[$sig]['date'] = $date;
            $groups[$sig]['quantity'] = $qty;
            $groups[$sig]['unit_price'] = $unit;
            $groups[$sig]['sale_ids'][] = (string) ($item['sale_id'] ?? '');
        }

        $out = [];
        foreach ($groups as $group) {
            $saleIds = array_values(array_unique(array_filter($group['sale_ids'])));
            if (count($saleIds) < 2) {
                continue;
            }
            sort($saleIds);

            $name = $names[$group['product_id']] ?? $group['product_id'];
            $figures = [
                'quantity' => (int) $group['quantity'],
                'unit_price' => (float) $group['unit_price'],
                'record_count' => count($saleIds),
            ];

            $out[] = self::alert(
                self::RULE_DUPLICATE,
                'medium',
                $name.' - '.$group['date'],
                $locale,
                $currency,
                $figures,
                array_map(fn ($id) => ['ref' => mb_substr($id, 0, 8), 'date' => $group['date'], 'value' => $figures['unit_price']], array_slice($saleIds, 0, 5)),
                [$group['product_id'], $group['date'], $group['quantity'], $group['unit_price'], implode(',', $saleIds)],
            );
        }

        // One day cannot produce an unbounded list of duplicate groups.
        return array_slice($out, 0, 5);
    }

    /**
     * Expenses in the recent window that jump against the prior window by both
     * a ratio and an absolute amount (configurable).
     *
     * @param  array<int, array<string, mixed>>  $recent
     * @param  array<int, array<string, mixed>>  $prior
     * @return array<int, array<string, mixed>>
     */
    private static function expenseSpikeRules(array $recent, array $prior, array $options, string $locale, string $currency, string $recentFrom, string $recentTo): array
    {
        $ratio = max(1.0, (float) ($options['expense_spike_ratio'] ?? 2.0));
        $minAbs = max(0.0, (float) ($options['expense_spike_min_abs'] ?? 10000));

        $recentSum = 0.0;
        $byCategory = [];
        foreach ($recent as $expense) {
            $amount = (float) ($expense['amount'] ?? 0);
            $recentSum += $amount;
            $category = trim((string) ($expense['category'] ?? ''));
            $category = $category !== '' ? $category : '—';
            $byCategory[$category] = ($byCategory[$category] ?? 0) + $amount;
        }
        $priorSum = 0.0;
        foreach ($prior as $expense) {
            $priorSum += (float) ($expense['amount'] ?? 0);
        }

        if ($recentSum <= 0) {
            return [];
        }

        $spike = $priorSum > 0
            ? ($recentSum >= $priorSum * $ratio && ($recentSum - $priorSum) >= $minAbs)
            : ($recentSum >= $minAbs * 2);

        if (! $spike) {
            return [];
        }

        arsort($byCategory);
        $top = array_slice($byCategory, 0, 3, true);

        $figures = [
            'recent_total' => round($recentSum, 2),
            'prior_total' => round($priorSum, 2),
            'delta' => round($recentSum - $priorSum, 2),
            'delta_pct' => $priorSum > 0 ? round((($recentSum - $priorSum) / $priorSum) * 100, 1) : null,
        ];

        $evidence = [];
        foreach ($top as $category => $amount) {
            $evidence[] = ['ref' => $category, 'date' => $recentTo, 'value' => round($amount, 2)];
        }

        return [self::alert(
            self::RULE_EXPENSE_SPIKE,
            $priorSum > 0 ? 'medium' : 'low',
            $recentFrom.' .. '.$recentTo,
            $locale,
            $currency,
            $figures,
            $evidence,
            [$figures['recent_total'], $figures['prior_total'], implode('|', array_keys($top))],
        )];
    }

    /**
     * Negative recorded stock is an unambiguous data discrepancy (sales were
     * recorded beyond what inventory held, or a correction was missed).
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private static function stockMismatchRules(array $products, string $locale, string $currency): array
    {
        $out = [];
        foreach ($products as $product) {
            $stock = (float) ($product['stock'] ?? 0);
            if ($stock >= 0) {
                continue;
            }
            $id = (string) ($product['id'] ?? '');
            $name = (string) ($product['name'] ?? $id);
            $figures = ['stock' => $stock];

            $out[] = self::alert(
                self::RULE_STOCK_MISMATCH,
                'high',
                $name,
                $locale,
                $currency,
                $figures,
                [['ref' => $id, 'date' => date('Y-m-d'), 'value' => $stock]],
                [$id, $stock],
            );
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private static function marginOf(array $items): array
    {
        $revenue = 0.0;
        $cost = 0.0;
        $lines = 0;

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $unit = (float) ($item['unit_price'] ?? 0);
            if ($qty <= 0 || $unit <= 0) {
                continue;
            }
            $lineRevenue = (float) ($item['total_price'] ?? 0);
            if ($lineRevenue <= 0) {
                $lineRevenue = $qty * $unit;
            }
            $productCost = isset($item['_cost']) ? (float) $item['_cost'] : null;
            if ($productCost === null || $productCost <= 0) {
                // Unknown cost: count the revenue as margin-unknown and skip the
                // line so we never assume a cost of zero.
                continue;
            }
            $revenue += $lineRevenue;
            $cost += $productCost * $qty;
            $lines++;
        }

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'lines' => $lines,
            'margin' => $revenue > 0 ? (($revenue - $cost) / $revenue) * 100 : 0.0,
        ];
    }

    /**
     * Filter rows whose date column falls inside [from, to] (inclusive).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function within(array $rows, string $from, string $to, string $dateKey = 'sale_date'): array
    {
        if ($from === '' || $to === '') {
            return [];
        }

        return array_values(array_filter($rows, function ($row) use ($from, $to, $dateKey) {
            $date = mb_substr((string) ($row[$dateKey] ?? ''), 0, 10);

            return $date !== '' && $date >= $from && $date <= $to;
        }));
    }

    /**
     * Assemble one alert with a localized title/detail/verify text and a
     * stable key derived from the evidence fingerprint.
     *
     * @param  array<int, mixed>  $fingerprintParts
     */
    private static function alert(string $rule, string $severity, string $subject, string $locale, string $currency, array $figures, array $evidence, array $fingerprintParts): array
    {
        $texts = self::texts($rule, $locale, $subject, $figures, $currency);

        return [
            'key' => self::key($rule, $subject, $fingerprintParts),
            'rule' => $rule,
            'severity' => $severity,
            'subject' => $subject,
            'title' => $texts['title'],
            'detail' => $texts['detail'],
            'verify_steps' => $texts['verify'],
            'figures' => $figures,
            'evidence' => $evidence,
            'detected_at' => now()->toISOString(true),
        ];
    }

    /** Rule + subject + evidence hash: unchanged evidence keeps the key stable. */
    public static function key(string $rule, string $subject, array $fingerprintParts): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $subject) ?? ''));
        $slug = trim(mb_substr($slug, 0, 40), '-') ?: 'general';
        $hash = substr(sha1(json_encode($fingerprintParts, JSON_UNESCAPED_UNICODE)), 0, 12);

        return $rule.':'.$slug.':'.$hash;
    }

    /**
     * Localized alert copy. Missing locales fall back to English; figures are
     * pre-formatted here and interpolated positionally - no model writes them.
     */
    private static function texts(string $rule, string $locale, string $subject, array $figures, string $currency): array
    {
        $table = self::TEXTS[$rule] ?? null;
        $pack = $table[$locale] ?? ($table['en'] ?? null);
        if ($pack === null) {
            return ['title' => $rule, 'detail' => $subject, 'verify' => ''];
        }

        $fmt = fn ($value) => self::money($value);
        $pct = fn ($value) => self::pct($value);
        $str = fn ($value) => (string) $value;

        // Ordered placeholders per rule; every locale's template uses the same
        // argument order (enforced by AnomalyDetectorTest::test_placeholder_order).
        $argsByRule = [
            self::RULE_BELOW_COST => [
                $subject,
                $str($figures['units'] ?? 0),
                $fmt($figures['unit_price'] ?? 0),
                $fmt($figures['cost'] ?? 0),
                $fmt($figures['estimated_loss'] ?? 0),
                $currency,
            ],
            self::RULE_MARGIN_DROP => [
                $pct($figures['prior_margin_pct'] ?? 0),
                $pct($figures['recent_margin_pct'] ?? 0),
                $pct($figures['drop_pp'] ?? 0),
                $fmt($figures['recent_revenue'] ?? 0),
                $currency,
                $fmt($figures['prior_revenue'] ?? 0),
            ],
            self::RULE_DUPLICATE => [
                $subject,
                $str($figures['record_count'] ?? 0),
                $str($figures['quantity'] ?? 0),
                $fmt($figures['unit_price'] ?? 0),
            ],
            self::RULE_EXPENSE_SPIKE => [
                $fmt($figures['recent_total'] ?? 0),
                $currency,
                $fmt($figures['prior_total'] ?? 0),
                $fmt($figures['delta'] ?? 0),
                $currency,
            ],
            self::RULE_STOCK_MISMATCH => [
                $subject,
                $str($figures['stock'] ?? 0),
            ],
        ];
        $args = $argsByRule[$rule] ?? [$subject];

        $render = function (string $template) use ($args) {
            try {
                return vsprintf($template, $args);
            } catch (\Throwable) {
                // A template/arg mismatch must never break the response.
                return $template;
            }
        };

        return [
            'title' => $pack['title'],
            'detail' => $render($pack['detail']),
            'verify' => $render($pack['verify']),
        ];
    }

    private static function money($value): string
    {
        return number_format((float) $value, 0, '.', ',');
    }

    private static function pct($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ','), '0'), '.').'%';
    }

    /** Honest, localized limitation lines for the response. */
    public static function limitations(string $locale): array
    {
        $lines = self::LIMITATIONS[$locale] ?? self::LIMITATIONS['en'];

        return $lines;
    }

    /** Deterministic scan summary used when the model is unavailable. */
    public static function summaryText(int $count, string $locale, string $from, string $to): string
    {
        $template = self::SUMMARY[$locale] ?? self::SUMMARY['en'];

        return sprintf($template, $count, $from, $to);
    }

    /* ------------------------------------------------------------------ */
    /* Copy tables (8 locales, English fallback)                           */
    /* ------------------------------------------------------------------ */

    private const SUMMARY = [
        'sw' => 'Dalili %d za uchunguzi zimepatikana kwa kipindi cha %s hadi %s. Hizi ni dhana za uchunguzi, si ushahidi wa wizi.',
        'en' => '%d potential anomalies were found for %s to %s. These are signals to verify, not evidence of theft.',
        'fr' => '%d anomalies potentielles ont été détectées pour la période du %s au %s. Ce sont des signaux à vérifier, pas des preuves de vol.',
        'hi' => '%d जांच के संकेत %s से %s तक की अवधि में मिले। ये पुष्टि के लिए संकेत हैं, चोरी का प्रमाण नहीं।',
        'es' => 'Se encontraron %d posibles anomalías del %s al %s. Son señales para verificar, no pruebas de robo.',
        'ur' => '%d ممکنہ عدم میزان %s سے %s کے دور کے لیے ملے۔ یہ تصدیق کے اشارے ہیں، چوری کا ثبوت نہیں۔',
        'de' => '%d mögliche Auffälligkeiten für %s bis %s gefunden. Dies sind Signale zur Prüfung, kein Diebstahlbeweis.',
        'zh' => '%d 个待核查的异常信号（%s 至 %s）。这些是需要核实的迹象，并非盗窃证据。',
    ];

    private const LIMITATIONS = [
        'sw' => [
            'Maeneo ya bei ya bidhaa yenye gharama inayojulikana pekee yametumika kwenye hesabu za faida.',
            'Dalili hizi ni za uchunguzi pekee; hukumu ya mwisho ni ya meneja baada ya kuthibitisha rekodi.',
        ],
        'en' => [
            'Margin calculations only cover sale lines whose product cost is recorded.',
            'These signals are for review only; the final judgement belongs to the manager after checking the records.',
        ],
        'fr' => [
            'Les calculs de marge ne portent que sur les lignes dont le coût du produit est enregistré.',
            'Ces signaux servent uniquement à vérification ; le jugement final appartient au gestionnaire.',
        ],
        'hi' => [
            'मार्जिन गणना केवल उन्हीं बिक्री पंक्तियों पर की गई है जिनकी उत्पाद लागत दर्ज है।',
            'ये संकेत केवल समीक्षा के लिए हैं; अंतिम निर्णय रिकॉर्ड जांचने के बाद प्रबंधक का है।',
        ],
        'es' => [
            'Los cálculos de margen solo cubren líneas de venta con costo de producto registrado.',
            'Estas señales son solo para revisión; el juicio final corresponde al gerente tras verificar los registros.',
        ],
        'ur' => [
            'مارجن کے حساب صرف ان فروخت لائنوں پر ہیں جن کی مصنوعات کی لاگت درج ہے۔',
            'یہ اشارے صرف جانچ کے لیے ہیں؛ حتمی فیصلہ ریکارڈ چیک کرنے کے بعد منیجر کا ہوگا۔',
        ],
        'de' => [
            'Die Margenrechnung umfasst nur Verkaufszeilen mit erfassten Produktkosten.',
            'Diese Signale dienen nur der Prüfung; das endgültige Urteil liegt beim Manager nach Sichtung der Belege.',
        ],
        'zh' => [
            '毛利计算仅涵盖已记录产品成本的销售行。',
            '这些信号仅供核查；最终判断由管理员在检查记录后作出。',
        ],
    ];

    /** rule => locale => ['title','detail','verify'] with %s / %d placeholders. */
    private const TEXTS = [
        self::RULE_BELOW_COST => [
            'sw' => [
                'title' => 'Bidhaa imeuzwa chini ya gharama',
                'detail' => '%s: vitu %s vimeuzwa kwa %s kila moja huku gharama iliyorekodiwa ikiwa %s (hasara ~%s %s).',
                'verify' => 'Linganisha bei ya kwenye kanzidata na kumbukumbu za mauzo; thibitisha kama punguzo halali lilirekodiwa.',
            ],
            'en' => [
                'title' => 'Product sold below its recorded cost',
                'detail' => '%s: %s unit(s) sold at %s each while the recorded cost is %s (estimated loss ~%s %s).',
                'verify' => 'Compare the catalogue cost with the sales records; confirm whether an approved discount was recorded.',
            ],
            'fr' => [
                'title' => 'Produit vendu sous son coût enregistré',
                'detail' => '%s : %s unité(s) vendue(s) à %s alors que le coût enregistré est %s (perte estimée ~%s %s).',
                'verify' => 'Comparez le coût enregistré avec les ventes ; vérifiez si une remise autorisée a été notée.',
            ],
            'hi' => [
                'title' => 'उत्पान दर्ज लागत से कम पर बेचा गया',
                'detail' => '%s: %s इकाइयाँ %s में बेची गईं जबकि दर्ज लागत %s है (अनुमानित नुकसान ~%s %s)।',
                'verify' => 'कैटलॉग लागत की बिक्रि रिकॉर्ड से तुलना करें; पुष्टि करें कि कोई स्वीकृत छूट दर्ज हुई थी।',
            ],
            'es' => [
                'title' => 'Producto vendido por debajo de su costo registrado',
                'detail' => '%s: %s unidad(es) vendidas a %s mientras el costo registrado es %s (pérdida estimada ~%s %s).',
                'verify' => 'Compare el costo del catálogo con las ventas; confirme si se registró un descuento autorizado.',
            ],
            'ur' => [
                'title' => 'مصنوعات درج شدہ لاگت سے کم قیمت پر فروخت',
                'detail' => '%s: %s یونٹس %s میں فروخت ہوئیں جبکہ درج شدہ لاگت %s ہے (تخمینی نقصان ~%s %s)۔',
                'verify' => 'کیٹلاگ لاگت کی فروخت ریکارڈ سے موازنہ کریں؛ تصدیق کریں کہ کوئی منظور شدہ رعایت درج ہوئی تھی۔',
            ],
            'de' => [
                'title' => 'Produkt unter dem erfassten Kosten verkauft',
                'detail' => '%s: %s Stück zu %s verkauft, während die erfassten Kosten %s betragen (geschätzter Verlust ~%s %s).',
                'verify' => 'Vergleichen Sie die Katalogkosten mit den Verkaufsdaten; prüfen Sie, ob ein genehmigter Rabatt erfasst wurde.',
            ],
            'zh' => [
                'title' => '产品以低于记录成本的价格售出',
                'detail' => '%s：%s 件以 %s 售出，而记录成本为 %s（估计损失约 %s %s）。',
                'verify' => '请将目录成本与销售记录核对；确认是否记录了已批准的折扣。',
            ],
        ],
        self::RULE_MARGIN_DROP => [
            'sw' => [
                'title' => 'Faida ya bidhaa imepungua sana',
                'detail' => 'Faida ya awali ilikuwa %s na sasa ni %s (anguko la %s pointsi); mapato %s %s (yalikuwa %s).',
                'verify' => 'Kagua punguzo, bei mpya za kununua, na mauzo yasiyo na gharama iliyorekodiwa katika kipindi hiki.',
            ],
            'en' => [
                'title' => 'Gross margin dropped sharply',
                'detail' => 'Margin was %s in the prior window and is %s now (a %s point drop); revenue %s %s (was %s).',
                'verify' => 'Review discounts, new purchase prices, and sales whose product cost is missing in this period.',
            ],
            'fr' => [
                'title' => 'La marge brute a fortement chuté',
                'detail' => 'La marge était de %s dans la période précédente et de %s maintenant (baisse de %s points) ; revenus %s %s (était %s).',
                'verify' => 'Vérifiez les remises, les nouveaux prix d’achat et les ventes sans coût enregistré sur la période.',
            ],
            'hi' => [
                'title' => 'सकल मार्जिन में तेज़ गिरावट',
                'detail' => 'पिछली अवधि में मार्जिन %s था और अब %s है (%s अंकों की गिरावट); राजस्व %s %s (पहले %s)।',
                'verify' => 'छूट, नई खरीद-मूल्य और इस अवधि में लागत-रहित बिक्रियों की समीक्षा करें।',
            ],
            'es' => [
                'title' => 'El margen bruto cayó notablemente',
                'detail' => 'El margen era %s en el periodo anterior y ahora es %s (caída de %s puntos); ingresos %s %s (era %s).',
                'verify' => 'Revise descuentos, nuevos precios de compra y ventas sin costo registrado en este periodo.',
            ],
            'ur' => [
                'title' => 'مجموعی مارجن میں شدید کمی',
                'detail' => 'پچھلے دور میں مارجن %s تھا اور اب %s ہے (%s نکات کی کمی); آمدنی %s %s (پہلے %s)۔',
                'verify' => 'رعایات، نئی خریداری قیمتیں اور اس دور کی لاگت کے بغیر فروخت کا جائزہ لیں۔',
            ],
            'de' => [
                'title' => 'Bruttomarge deutlich gesunken',
                'detail' => 'Die Marge lag im Vorzeitraum bei %s und jetzt bei %s (Rückgang von %s Punkten); Umsatz %s %s (zuvor %s).',
                'verify' => 'Prüfen Sie Rabatte, neue Einkaufspreise und Verkäufe ohne erfasste Kosten in diesem Zeitraum.',
            ],
            'zh' => [
                'title' => '毛利率大幅下降',
                'detail' => '上期毛利率为 %s，现为 %s（下降 %s 个百分点）；营收 %s %s（此前为 %s）。',
                'verify' => '请检查折扣、新采购价，以及本期未记录成本的销售。',
            ],
        ],
        self::RULE_DUPLICATE => [
            'sw' => [
                'title' => 'Mauzo yanayofanana (yasiyo ya kawaida)',
                'detail' => '%s: rekodi %s za mauzo zenye kipengele kile kile, idadi %s na bei %s kwa siku moja.',
                'verify' => 'Fungua kila rekodi uthibitishe kama ni miamala mipya tofauti au ingizo mara mbili.',
            ],
            'en' => [
                'title' => 'Similar sales worth verifying',
                'detail' => '%s: %s sale records with the same product, quantity %s and price %s on one day.',
                'verify' => 'Open each record and confirm whether they are separate transactions or a duplicated entry.',
            ],
            'fr' => [
                'title' => 'Ventes similaires à vérifier',
                'detail' => '%s : %s enregistrements de vente avec le même produit, la quantité %s et le prix %s le même jour.',
                'verify' => 'Ouvrez chaque enregistrement et confirmez s’il s’agit de transactions distinctes ou d’une saisie dupliquée.',
            ],
            'hi' => [
                'title' => 'जांच के योग्य समान बिक्रियाँ',
                'detail' => '%s: %s बिक्रि रिकॉर्ड जिनमें वही उत्पान, मात्रा %s और मूल्य %s एक ही दिन पर हैं।',
                'verify' => 'हर रिकॉर्ड खोलकर पुष्टि करें कि ये अलग-अलग लेनदेन हैं या डुप्लिकेट प्रविष्टि।',
            ],
            'es' => [
                'title' => 'Ventas similares para verificar',
                'detail' => '%s: %s registros de venta con el mismo producto, cantidad %s y precio %s en un mismo día.',
                'verify' => 'Abra cada registro y confirme si son transacciones separadas o una entrada duplicada.',
            ],
            'ur' => [
                'title' => 'تصدیق کے قابل مماثل فروخت',
                'detail' => '%s: %s فروخت ریکارڈز جن میں ایک ہی مصنوعات، مقدار %s اور قیمت %s ایک ہی دن پر ہیں۔',
                'verify' => 'ہر ریکارڈ کھول کر تصدیق کریں کہ یہ الگ الگ لین دین ہیں یا ڈپلیکیٹ اندراج۔',
            ],
            'de' => [
                'title' => 'Ähnliche Verkäufe zur Prüfung',
                'detail' => '%s: %s Verkaufsbuchungen mit demselben Produkt, Menge %s und Preis %s an einem Tag.',
                'verify' => 'Öffnen Sie jede Buchung und prüfen Sie, ob es sich um getrennte Geschäfte oder einen Doppelbuch handelt.',
            ],
            'zh' => [
                'title' => '相似销售记录待核查',
                'detail' => '%s：同一天内有 %s 条销售记录，产品相同、数量 %s、单价 %s。',
                'verify' => '请打开每条记录，确认是独立交易还是重复录入。',
            ],
        ],
        self::RULE_EXPENSE_SPIKE => [
            'sw' => [
                'title' => 'Matumizi yameongezeka ghafla',
                'detail' => 'Matumizi ya kipindi hiki ni %s %s dhidi ya %s katika kipindi kilichopita (ongezeko la %s %s).',
                'verify' => 'Pitia kategoria kuu na thibitisha kila bili kwa risiti husika.',
            ],
            'en' => [
                'title' => 'Expenses jumped against the previous period',
                'detail' => 'Expenses are %s %s versus %s in the prior window (an increase of %s %s).',
                'verify' => 'Review the top categories and confirm each entry against its receipt.',
            ],
            'fr' => [
                'title' => 'Les dépenses ont fortement augmenté',
                'detail' => 'Dépenses : %s %s contre %s sur la période précédente (hausse de %s %s).',
                'verify' => 'Examinez les principales catégories et confirmez chaque saisie avec son reçu.',
            ],
            'hi' => [
                'title' => 'खर्च में अचानक वृद्धि',
                'detail' => 'इस अवधि का खर्च %s %s है जबकि पिछली अवधि में %s था (वृद्धि %s %s)।',
                'verify' => 'प्रमुख श्रेणियों की समीक्षा करें और प्रत्येक प्रविष्टि की रसीद से पुष्टि करें।',
            ],
            'es' => [
                'title' => 'Los gastos subieron frente al periodo anterior',
                'detail' => 'Gastos de %s %s frente a %s en el periodo previo (aumento de %s %s).',
                'verify' => 'Revise las categorías principales y confirme cada registro con su recibo.',
            ],
            'ur' => [
                'title' => 'اخراجات پچھلے دور کے مقابلے میں بڑھے',
                'detail' => 'اس دور کے اخراجات %s %s ہیں جبکہ پچھلے دور میں %s تھے (اضافہ %s %s)۔',
                'verify' => 'اہم زمروں کا جائزہ لیں اور ہر اندراج کی رسید سے تصدیق کریں۔',
            ],
            'de' => [
                'title' => 'Ausgaben im Vergleich zum Vorzeitraum gestiegen',
                'detail' => 'Ausgaben: %s %s gegenüber %s im Vorzeitraum (Anstieg um %s %s).',
                'verify' => 'Prüfen Sie die Hauptkategorien und bestätigen Sie jeden Eintrag mit dem Beleg.',
            ],
            'zh' => [
                'title' => '支出较上期大幅上升',
                'detail' => '本期支出为 %s %s，上期为 %s（增加 %s %s）。',
                'verify' => '请检查主要类别，并凭票据核实每笔记录。',
            ],
        ],
        self::RULE_STOCK_MISMATCH => [
            'sw' => [
                'title' => 'Idadi ya stock imekuwa hasi',
                'detail' => '%s: stock iliyorekodiwa ni %s, isiyowezekana kawaida.',
                'verify' => 'Linganisha kumbukumbu za mauzo na mabadiliko ya stock; sahihi idadi na weka maelezo.',
            ],
            'en' => [
                'title' => 'Recorded stock is negative',
                'detail' => '%s: recorded stock is %s, which should not happen in normal operation.',
                'verify' => 'Reconcile the sales records with the stock adjustments; correct the count and add a note.',
            ],
            'fr' => [
                'title' => 'Le stock enregistré est négatif',
                'detail' => '%s : le stock enregistré est %s, ce qui ne devrait pas arriver normalement.',
                'verify' => 'Rapprochez les ventes des ajustements de stock ; corrigez le compte et ajoutez une note.',
            ],
            'hi' => [
                'title' => 'दर्ज स्टॉक ऋणात्मक है',
                'detail' => '%s: दर्ज स्टॉक %s है, जो सामान्य कार्य में नहीं होना चाहिए।',
                'verify' => 'बिक्रि रिकॉर्ड की स्टॉक समायोजन से तुलना करें; गिनती सुधारें और टिप्पणी जोड़ें।',
            ],
            'es' => [
                'title' => 'El stock registrado es negativo',
                'detail' => '%s: el stock registrado es %s, algo que no debería ocurrir en operación normal.',
                'verify' => 'Concilie las ventas con los ajustes de stock; corrija el conteo y añada una nota.',
            ],
            'ur' => [
                'title' => 'درج اسٹاک منفی ہے',
                'detail' => '%s: درج اسٹاک %s ہے، جو عام کارروائی میں نہیں ہونا چاہیے۔',
                'verify' => 'فروخت ریکارڈ کی اسٹاک ایڈجسٹمنٹ سے مماثلت کریں؛ گنتی درست کریں اور نوٹ شامل کریں۔',
            ],
            'de' => [
                'title' => 'Erfasster Bestand ist negativ',
                'detail' => '%s: Der erfasste Bestand beträgt %s, was im Normalbetrieb nicht vorkommen sollte.',
                'verify' => 'Abgleich der Verkaufsbuchungen mit den Bestandskorrekturen; Bestand korrigieren und einen Vermerk ergänzen.',
            ],
            'zh' => [
                'title' => '记录库存为负数',
                'detail' => '%s：记录库存为 %s，正常经营中不应出现。',
                'verify' => '请将销售记录与库存调整核对；修正数量并添加备注。',
            ],
        ],
    ];
}
