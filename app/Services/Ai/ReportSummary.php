<?php

namespace App\Services\Ai;

/**
 * Capability #15 - AI Report Summaries (deterministic core).
 *
 * Builds a grounded explanation over the EXISTING report queries: the
 * controller feeds this class the same rows the manager screens already use
 * (BusinessDataProvider) and the same verified aggregates the report runner
 * computes (BusinessReporter::revenueSummary / expenseSummary / compare), so
 * the summary can never disagree with the numbers on screen.
 *
 * Design guarantees (unit-tested):
 *  - periods: the current window is truncated to COMPLETE days only (an
 *    in-progress day would make any comparison dishonest), and the previous
 *    window is always the same number of days immediately before it;
 *  - comparability: revenue / sales_count / units always compare; gross
 *    profit and margin compare ONLY when both windows have a buying price
 *    for every sold line - otherwise the difference is disclosed instead of
 *    guessed;
 *  - contributors: the products with the largest recorded revenue movement
 *    between the two windows, described as MOVEMENT, never as a cause;
 *  - narrative: every line is built only from the figures in the payload,
 *    and the payload carries the figures, the periods and the sources;
 *  - limitations: missing sales, missing expenses, incomplete profit and the
 *    partial-period truncation are always disclosed, never silently filled;
 *  - a model may only REWORD the same figures (numbers must already exist in
 *    the data we supplied), otherwise the reply is discarded.
 *
 * All public methods are pure (arrays in, arrays out) so the whole contract
 * is unit-testable without HTTP or a database.
 */
class ReportSummary
{
    public const CAPABILITY = 'report_summary';

    /** Below this revenue change the narrative says "barely changed". */
    private const MEANINGFUL_PCT = 1.0;

    /** Every message must exist for all eight locales (unit-tested). */
    public const MESSAGES = [
        'sw' => [
            'period_label' => 'Kipindi: {from} - {to} ({days} siku).',
            'revenue_line' => 'Mapato {revenue} kutoka mauzo {count} (bidhaa {units} zilizouzwa).',
            'profit_line' => 'Faida ghafi {profit} ({pct}% ya mapato).',
            'expenses_line' => 'Matumizi {total} ({count} manunuzi yaliyorekodiwa).',
            'expense_ratio_line' => 'Matumizi ni {pct}% ya mapato.',
            'compare_up' => 'Mapato yameongezeka kwa {pct}% ({change}) kuliko kipindi kilichopita ({prev_from} - {prev_to}).',
            'compare_down' => 'Mapato yamepungua kwa {pct}% ({change}) kuliko kipindi kilichopita ({prev_from} - {prev_to}).',
            'compare_flat' => 'Mapato hayajabadilika sana ({change}) kuliko kipindi kilichopita ({prev_from} - {prev_to}).',
            'compare_from_zero' => 'Kipindi kilichopita hakikuwa na mauzo; ongezeko la mapato ni {change} ({prev_from} - {prev_to}).',
            'contributors_line' => 'Washiriki wakuu wa mabadiliko ya mapato: {list}.',
            'contributor_item' => '{name} ({change})',
            'contributor_note' => 'Haya ni mabadiliko ya mapato ya bidhaa hizi katika kipindi - si uthibitisho wa sababu.',
            'top_products_line' => 'Bidhaa zinazoongoza: {list}.',
            'top_product_item' => '{name} ({revenue})',
            'lim_no_sales' => 'Hakuna mauzo yaliyorekodiwa katika kipindi hiki.',
            'lim_no_expenses' => 'Hakuna matumizi yaliyorekodiwa katika kipindi hiki.',
            'lim_unknown_cogs' => 'Faida haijakamilika: bidhaa {count} zilizouzwa hazina bei ya kununua.',
            'lim_margin_not_compared' => 'Faida haijalinganishwa kati ya vipindi viwili kwa sababu bei ya kununua haijaandikwa kwa bidhaa fulani.',
            'lim_partial_period' => 'Kipindi cha sasa kina leo ambayo bado haijakamilika; kimekatwa hadi {to} ili viwe sawa.',
            'lim_prev_no_sales' => 'Kipindi kilichopita ({prev_from} - {prev_to}) hakikuwa na mauzo yaliyorekodiwa.',
            'err_period' => 'Tarehe si sahihi.',
            'err_range_too_long' => 'Kipindi ni kirefu zaidi ya siku {max}.',
        ],
        'en' => [
            'period_label' => 'Period: {from} to {to} ({days} days).',
            'revenue_line' => 'Revenue {revenue} from {count} sales ({units} units sold).',
            'profit_line' => 'Gross profit {profit} ({pct}% of revenue).',
            'expenses_line' => 'Expenses {total} ({count} recorded entries).',
            'expense_ratio_line' => 'Expenses are {pct}% of revenue.',
            'compare_up' => 'Revenue increased by {pct}% ({change}) versus the previous period ({prev_from} - {prev_to}).',
            'compare_down' => 'Revenue decreased by {pct}% ({change}) versus the previous period ({prev_from} - {prev_to}).',
            'compare_flat' => 'Revenue barely changed ({change}) versus the previous period ({prev_from} - {prev_to}).',
            'compare_from_zero' => 'The previous period had no sales; the revenue increase is {change} ({prev_from} - {prev_to}).',
            'contributors_line' => 'Main contributors to the revenue change: {list}.',
            'contributor_item' => '{name} ({change})',
            'contributor_note' => 'These are revenue movements of these products within the period - not proof of a cause.',
            'top_products_line' => 'Top products: {list}.',
            'top_product_item' => '{name} ({revenue})',
            'lim_no_sales' => 'No sales were recorded in this period.',
            'lim_no_expenses' => 'No expenses were recorded in this period.',
            'lim_unknown_cogs' => 'Profit is incomplete: {count} sold products have no buying price on record.',
            'lim_margin_not_compared' => 'Profit was not compared between the two periods because a buying price is missing for some products.',
            'lim_partial_period' => 'The current period includes today, which is not complete yet; it was cut to {to} so both periods match.',
            'lim_prev_no_sales' => 'The previous period ({prev_from} - {prev_to}) had no recorded sales.',
            'err_period' => 'The dates are not valid.',
            'err_range_too_long' => 'The period is longer than the maximum of {max} days.',
        ],
        'fr' => [
            'period_label' => 'Période : {from} à {to} ({days} jours).',
            'revenue_line' => 'Chiffre d\'affaires {revenue} issu de {count} ventes ({units} unités vendues).',
            'profit_line' => 'Bénéfice brut {profit} ({pct}% du chiffre d\'affaires).',
            'expenses_line' => 'Dépenses {total} ({count} entrées enregistrées).',
            'expense_ratio_line' => 'Les dépenses représentent {pct}% du chiffre d\'affaires.',
            'compare_up' => 'Le chiffre d\'affaires a augmenté de {pct}% ({change}) par rapport à la période précédente ({prev_from} - {prev_to}).',
            'compare_down' => 'Le chiffre d\'affaires a diminué de {pct}% ({change}) par rapport à la période précédente ({prev_from} - {prev_to}).',
            'compare_flat' => 'Le chiffre d\'affaires a à peine changé ({change}) par rapport à la période précédente ({prev_from} - {prev_to}).',
            'compare_from_zero' => 'La période précédente n\'avait aucune vente ; la hausse du chiffre d\'affaires est de {change} ({prev_from} - {prev_to}).',
            'contributors_line' => 'Principaux contributeurs à la variation du chiffre d\'affaires : {list}.',
            'contributor_item' => '{name} ({change})',
            'contributor_note' => 'Ce sont des variations de chiffre d\'affaires de ces produits sur la période - pas une preuve de cause.',
            'top_products_line' => 'Meilleurs produits : {list}.',
            'top_product_item' => '{name} ({revenue})',
            'lim_no_sales' => 'Aucune vente n\'a été enregistrée durant cette période.',
            'lim_no_expenses' => 'Aucune dépense n\'a été enregistrée durant cette période.',
            'lim_unknown_cogs' => 'Le bénéfice est incomplet : {count} produits vendus n\'ont pas de prix d\'achat enregistré.',
            'lim_margin_not_compared' => 'Le bénéfice n\'a pas été comparé entre les deux périodes car un prix d\'achat manque pour certains produits.',
            'lim_partial_period' => 'La période actuelle inclut aujourd\'hui, qui n\'est pas terminée ; elle a été coupée à {to} pour que les deux périodes correspondent.',
            'lim_prev_no_sales' => 'La période précédente ({prev_from} - {prev_to}) n\'avait aucune vente enregistrée.',
            'err_period' => 'Les dates ne sont pas valides.',
            'err_range_too_long' => 'La période dépasse la durée maximale de {max} jours.',
        ],
        'hi' => [
            'period_label' => 'अवधि: {from} से {to} ({days} दिन)।',
            'revenue_line' => 'राजस्व {revenue}, {count} बिक्री से ({units} इकाइयाँ बिकीं)।',
            'profit_line' => 'सकल लाभ {profit} (राजस्व का {pct}%)।',
            'expenses_line' => 'व्यय {total} ({count} दर्ज प्रविष्टियाँ)।',
            'expense_ratio_line' => 'व्यय राजस्व का {pct}% है।',
            'compare_up' => 'पिछली अवधि ({prev_from} - {prev_to}) की तुलना में राजस्व {pct}% ({change}) बढ़ा।',
            'compare_down' => 'पिछली अवधि ({prev_from} - {prev_to}) की तुलना में राजस्व {pct}% ({change}) घटा।',
            'compare_flat' => 'पिछली अवधि ({prev_from} - {prev_to}) की तुलना में राजस्व लगभग नहीं बदला ({change})।',
            'compare_from_zero' => 'पिछली अवधि में कोई बिक्री नहीं थी; राजस्व वृद्धि {change} है ({prev_from} - {prev_to})।',
            'contributors_line' => 'राजस्व परिवर्तन के मुख्य योगदानकर्ता: {list}।',
            'contributor_item' => '{name} ({change})',
            'contributor_note' => 'ये इस अवधि में इन उत्पादों के राजस्व परिवर्तन हैं - कारण का प्रमाण नहीं।',
            'top_products_line' => 'शीर्ष उत्पाद: {list}।',
            'top_product_item' => '{name} ({revenue})',
            'lim_no_sales' => 'इस अवधि में कोई बिक्री दर्ज नहीं हुई।',
            'lim_no_expenses' => 'इस अवधि में कोई व्यय दर्ज नहीं हुआ।',
            'lim_unknown_cogs' => 'लाभ अधूरा है: बिके हुए {count} उत्पादों की खरीद मूल्य दर्ज नहीं है।',
            'lim_margin_not_compared' => 'कुछ उत्पादों की खरीद मूल्य अनुपलब्ध होने के कारण दोनों अवधियों का लाभ तुलित नहीं किया गया।',
            'lim_partial_period' => 'वर्तमान अवधि में आज शामिल है, जो अभी पूरी नहीं हुई; दोनों अवधियाँ मेल खाएँ इसलिए इसे {to} तक काटा गया।',
            'lim_prev_no_sales' => 'पिछली अवधि ({prev_from} - {prev_to}) में कोई बिक्री दर्ज नहीं थी।',
            'err_period' => 'तिथियाँ मान्य नहीं हैं।',
            'err_range_too_long' => 'अवधि अधिकतम {max} दिनों से अधिक है।',
        ],
        'es' => [
            'period_label' => 'Período: del {from} al {to} ({days} días).',
            'revenue_line' => 'Ingresos {revenue} de {count} ventas ({units} unidades vendidas).',
            'profit_line' => 'Beneficio bruto {profit} ({pct}% de los ingresos).',
            'expenses_line' => 'Gastos {total} ({count} entradas registradas).',
            'expense_ratio_line' => 'Los gastos son el {pct}% de los ingresos.',
            'compare_up' => 'Los ingresos aumentaron un {pct}% ({change}) frente al período anterior ({prev_from} - {prev_to}).',
            'compare_down' => 'Los ingresos disminuyeron un {pct}% ({change}) frente al período anterior ({prev_from} - {prev_to}).',
            'compare_flat' => 'Los ingresos apenas cambiaron ({change}) frente al período anterior ({prev_from} - {prev_to}).',
            'compare_from_zero' => 'El período anterior no tuvo ventas; el aumento de ingresos es de {change} ({prev_from} - {prev_to}).',
            'contributors_line' => 'Principales contribuyentes al cambio de ingresos: {list}.',
            'contributor_item' => '{name} ({change})',
            'contributor_note' => 'Son movimientos de ingresos de estos productos dentro del período - no una prueba de causa.',
            'top_products_line' => 'Productos principales: {list}.',
            'top_product_item' => '{name} ({revenue})',
            'lim_no_sales' => 'No se registraron ventas en este período.',
            'lim_no_expenses' => 'No se registraron gastos en este período.',
            'lim_unknown_cogs' => 'El beneficio está incompleto: a {count} productos vendidos les falta el precio de compra registrado.',
            'lim_margin_not_compared' => 'El beneficio no se comparó entre los dos períodos porque falta el precio de compra de algunos productos.',
            'lim_partial_period' => 'El período actual incluye hoy, que aún no terminó; se cortó al {to} para que ambos períodos coincidan.',
            'lim_prev_no_sales' => 'El período anterior ({prev_from} - {prev_to}) no tuvo ventas registradas.',
            'err_period' => 'Las fechas no son válidas.',
            'err_range_too_long' => 'El período supera el máximo de {max} días.',
        ],
        'ur' => [
            'period_label' => 'مدت: {from} سے {to} ({days} دن)۔',
            'revenue_line' => 'آمدنی {revenue}، {count} فروخت سے ({units} اکائیاں فروخت ہوئیں)۔',
            'profit_line' => 'مجموعی منافع {profit} (آمدنی کا {pct}%)۔',
            'expenses_line' => 'اخراجات {total} ({count} درج شدہ انٹریز)۔',
            'expense_ratio_line' => 'اخراجات آمدنی کا {pct} ہیں۔',
            'compare_up' => 'پچھلی مدت ({prev_from} - {prev_to}) کے مقابلے میں آمدنی {pct}% ({change}) بڑھی۔',
            'compare_down' => 'پچھلی مدت ({prev_from} - {prev_to}) کے مقابلے میں آمدنی {pct}% ({change}) گھٹی۔',
            'compare_flat' => 'پچھلی مدت ({prev_from} - {prev_to}) کے مقابلے میں آمدنی تقریباً نہیں بدلی ({change})۔',
            'compare_from_zero' => 'پچھلی مدت میں کوئی فروخت نہیں تھی؛ آمدنی میں اضافہ {change} ہے ({prev_from} - {prev_to})۔',
            'contributors_line' => 'آمدنی کی تبدیلی کے اہم شراکت دار: {list}۔',
            'contributor_item' => '{name} ({change})',
            'contributor_note' => 'یہ اس مدت میں ان مصنوعات کی آمدنی کی تبدیلیاں ہیں - وجہ کا ثبوت نہیں۔',
            'top_products_line' => 'اعلیٰ مصنوعات: {list}۔',
            'top_product_item' => '{name} ({revenue})',
            'lim_no_sales' => 'اس مدت میں کوئی فروخت درج نہیں ہوئی۔',
            'lim_no_expenses' => 'اس مدت میں کوئی خرچ درج نہیں ہوا۔',
            'lim_unknown_cogs' => 'منافع نامکمل ہے: {count} فروخت شدہ مصنوعات کی خریداری قیمت درج نہیں ہے۔',
            'lim_margin_not_compared' => 'کچھ مصنوعات کی خریداری قیمت غائب ہونے کی وجہ سے دونوں ادتوں کا منافع موازنہ نہیں کیا گیا۔',
            'lim_partial_period' => 'موجودہ مدت میں آج شامل ہے جو ابھی مکمل نہیں؛ دونوں ادتوں کا میل رکھنے کے لیے {to} تک کاٹ دیا گیا۔',
            'lim_prev_no_sales' => 'پچھلی مدت ({prev_from} - {prev_to}) میں کوئی درج شدہ فروخت نہیں تھی۔',
            'err_period' => 'تاریخیں درست نہیں ہیں۔',
            'err_range_too_long' => 'مدت زیادہ سے زیادہ {max} دن سے زیادہ ہے۔',
        ],
        'de' => [
            'period_label' => 'Zeitraum: {from} bis {to} ({days} Tage).',
            'revenue_line' => 'Umsatz {revenue} aus {count} Verkäufen ({units} verkaufte Einheiten).',
            'profit_line' => 'Bruttogewinn {profit} ({pct}% des Umsatzes).',
            'expenses_line' => 'Ausgaben {total} ({count} erfasste Buchungen).',
            'expense_ratio_line' => 'Die Ausgaben betragen {pct}% des Umsatzes.',
            'compare_up' => 'Der Umsatz stieg gegenüber dem vorherigen Zeitraum ({prev_from} - {prev_to}) um {pct}% ({change}).',
            'compare_down' => 'Der Umsatz sank gegenüber dem vorherigen Zeitraum ({prev_from} - {prev_to}) um {pct}% ({change}).',
            'compare_flat' => 'Der Umsatz veränderte sich kaum ({change}) gegenüber dem vorherigen Zeitraum ({prev_from} - {prev_to}).',
            'compare_from_zero' => 'Der vorherige Zeitraum hatte keine Verkäufe; der Umsatzanstieg beträgt {change} ({prev_from} - {prev_to}).',
            'contributors_line' => 'Wichtigste Beiträge zur Umsatzveränderung: {list}.',
            'contributor_item' => '{name} ({change})',
            'contributor_note' => 'Dies sind Umsatzbewegungen dieser Produkte im Zeitraum - kein Beweis einer Ursache.',
            'top_products_line' => 'Top-Produkte: {list}.',
            'top_product_item' => '{name} ({revenue})',
            'lim_no_sales' => 'In diesem Zeitraum wurden keine Verkäufe erfasst.',
            'lim_no_expenses' => 'In diesem Zeitraum wurden keine Ausgaben erfasst.',
            'lim_unknown_cogs' => 'Der Gewinn ist unvollständig: {count} verkaufte Produkte haben keinen Einkaufspreis erfasst.',
            'lim_margin_not_compared' => 'Der Gewinn wurde zwischen den beiden Zeiträumen nicht verglichen, weil für einige Produkte ein Einkaufspreis fehlt.',
            'lim_partial_period' => 'Der aktuelle Zeitraum enthält heute, das noch nicht abgeschlossen ist; er wurde auf {to} gekürzt, damit beide Zeiträume stimmen.',
            'lim_prev_no_sales' => 'Der vorherige Zeitraum ({prev_from} - {prev_to}) hatte keine erfassten Verkäufe.',
            'err_period' => 'Die Daten sind nicht gültig.',
            'err_range_too_long' => 'Der Zeitraum ist länger als das Maximum von {max} Tagen.',
        ],
        'zh' => [
            'period_label' => '周期：{from} 至 {to}（{days} 天）。',
            'revenue_line' => '营收 {revenue}，来自 {count} 笔销售（售出 {units} 件）。',
            'profit_line' => '毛利 {profit}（占营收 {pct}%）。',
            'expenses_line' => '支出 {total}（{count} 条记录）。',
            'expense_ratio_line' => '支出占营收的 {pct}%。',
            'compare_up' => '与上一周期（{prev_from} - {prev_to}）相比，营收增长 {pct}%（{change}）。',
            'compare_down' => '与上一周期（{prev_from} - {prev_to}）相比，营收下降 {pct}%（{change}）。',
            'compare_flat' => '与上一周期（{prev_from} - {prev_to}）相比，营收几乎没有变化（{change}）。',
            'compare_from_zero' => '上一周期没有销售；营收增加额为 {change}（{prev_from} - {prev_to}）。',
            'contributors_line' => '营收变化的主要来源：{list}。',
            'contributor_item' => '{name} ({change})',
            'contributor_note' => '这些是本周期内这些产品的营收变动——并非因果证明。',
            'top_products_line' => '畅销产品：{list}。',
            'top_product_item' => '{name} ({revenue})',
            'lim_no_sales' => '本周期没有记录到销售。',
            'lim_no_expenses' => '本周期没有记录到支出。',
            'lim_unknown_cogs' => '利润不完整：有 {count} 个已售产品没有记录买入价。',
            'lim_margin_not_compared' => '由于部分产品缺少买入价，两个周期之间的利润未作比较。',
            'lim_partial_period' => '当前周期包含尚未结束的今天；已截断至 {to} 以便两个周期一致。',
            'lim_prev_no_sales' => '上一周期（{prev_from} - {prev_to}）没有记录到销售。',
            'err_period' => '日期无效。',
            'err_range_too_long' => '周期超过最长 {max} 天。',
        ],
    ];

    /* ------------------------------------------------------------------ */
    /* Messages                                                            */
    /* ------------------------------------------------------------------ */

    public static function msg(string $key, string $locale, array $params = []): string
    {
        $table = self::MESSAGES[$locale] ?? self::MESSAGES['en'];
        $text = $table[$key] ?? self::MESSAGES['en'][$key] ?? $key;

        foreach ($params as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /** True when every locale carries every message (unit-tested). */
    public static function messagesComplete(): bool
    {
        $keys = array_keys(self::MESSAGES['en']);
        foreach (self::MESSAGES as $table) {
            sort($keys);
            $other = array_keys($table);
            sort($other);
            if ($keys !== $other) {
                return false;
            }
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Periods                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Resolve the two windows. An in-progress current period (one that
     * includes today) is truncated to COMPLETE days only, so the comparison
     * never weighs a partial day against full ones. The previous window is
     * always the same number of days immediately before the current one.
     *
     * @return array{current: array{from: string, to: string}, previous: ?array{from: string, to: string}, days: int, truncated: bool}
     */
    public static function windows(string $from, string $to, string $today): array
    {
        $from = substr(trim($from), 0, 10);
        $to = substr(trim($to), 0, 10);

        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($fromTs === false || $toTs === false || $from > $to) {
            return ['current' => ['from' => '', 'to' => ''], 'previous' => null, 'days' => 0, 'truncated' => false];
        }

        $truncated = false;
        if ($to >= $today) {
            // Truncate relative to the PASSED $today, never the wall clock - the
            // caller supplies "today" so this function stays pure and testable.
            $todayTs = strtotime($today) ?: time();
            $completeTo = date('Y-m-d', strtotime('-1 day', $todayTs));
            if ($completeTo < $from) {
                // The whole requested window is still in progress.
                return ['current' => ['from' => '', 'to' => ''], 'previous' => null, 'days' => 0, 'truncated' => true];
            }
            $to = $completeTo;
            $toTs = strtotime($to);
            $truncated = true;
        }

        $days = (int) floor(($toTs - $fromTs) / 86400) + 1;
        if ($days < 1) {
            return ['current' => ['from' => '', 'to' => ''], 'previous' => null, 'days' => 0, 'truncated' => $truncated];
        }

        $prevTo = date('Y-m-d', strtotime('-1 day', $fromTs));
        $prevFrom = date('Y-m-d', strtotime('-'.($days - 1).' days', strtotime($prevTo)));

        return [
            'current' => ['from' => $from, 'to' => $to],
            'previous' => ['from' => $prevFrom, 'to' => $prevTo],
            'days' => $days,
            'truncated' => $truncated,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* The deterministic core                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Build the grounded summary from already-verified aggregates.
     *
     * @param array{locale: string, currency: string, windows: array, current: array{sales: array, items: array, expenses: array, products: array}, previous: array{sales: array, items: array, expenses: array}} $ctx
     *        `products` is the product index (id => row) used for names and
     *        buying prices; `sales`/`items`/`expenses` are the raw rows the
     *        existing report queries return for each window.
     * @return array<string, mixed> narrative, metrics, comparison, contributors, table, chart, sources, limitations, periods
     */
    public static function summarize(array $ctx): array
    {
        $locale = (string) ($ctx['locale'] ?? 'sw');
        $currency = (string) ($ctx['currency'] ?? 'TZS');
        $windows = is_array($ctx['windows'] ?? null) ? $ctx['windows'] : [];
        $current = is_array($ctx['current'] ?? null) ? $ctx['current'] : [];
        $previous = is_array($ctx['previous'] ?? null) ? $ctx['previous'] : [];
        $products = is_array($current['products'] ?? null) ? $current['products'] : [];

        $curMetrics = BusinessReporter::revenueSummary(
            is_array($current['sales'] ?? null) ? $current['sales'] : [],
            is_array($current['items'] ?? null) ? $current['items'] : [],
            $products,
            $currency
        );
        $curExpenses = BusinessReporter::expenseSummary(
            is_array($current['expenses'] ?? null) ? $current['expenses'] : []
        );

        $prevMetrics = BusinessReporter::revenueSummary(
            is_array($previous['sales'] ?? null) ? $previous['sales'] : [],
            is_array($previous['items'] ?? null) ? $previous['items'] : [],
            $products,
            $currency
        );
        $prevExpenses = BusinessReporter::expenseSummary(
            is_array($previous['expenses'] ?? null) ? $previous['expenses'] : []
        );

        $periods = [
            'current' => $windows['current'] ?? ['from' => '', 'to' => ''],
            'previous' => $windows['previous'] ?? null,
            'days' => (int) ($windows['days'] ?? 0),
        ];

        $limitations = [];
        if (! empty($windows['truncated'])) {
            $limitations[] = self::msg('lim_partial_period', $locale, [
                'to' => $periods['current']['to'],
            ]);
        }
        if ($curMetrics['sales_count'] === 0) {
            $limitations[] = self::msg('lim_no_sales', $locale);
        }
        if ($curExpenses['count'] === 0) {
            $limitations[] = self::msg('lim_no_expenses', $locale);
        }
        if ($curMetrics['unknown_cogs_items'] > 0) {
            $limitations[] = self::msg('lim_unknown_cogs', $locale, [
                'count' => $curMetrics['unknown_cogs_items'],
            ]);
        }

        /* ---------------- comparison (only when comparable) ------------- */

        $deltas = BusinessReporter::compare($curMetrics, $prevMetrics);
        $cogsCompleteBoth = $curMetrics['unknown_cogs_items'] === 0
            && $prevMetrics['unknown_cogs_items'] === 0;

        $comparisonMetrics = [
            'revenue' => $deltas['revenue'],
            'sales_count' => $deltas['sales_count'],
            'units' => $deltas['units'],
        ];
        if ($cogsCompleteBoth) {
            $comparisonMetrics['gross_profit'] = $deltas['gross_profit'];
        } else {
            $limitations[] = self::msg('lim_margin_not_compared', $locale);
        }

        $marginComparison = null;
        if ($cogsCompleteBoth && $curMetrics['margin_pct'] !== null && $prevMetrics['margin_pct'] !== null) {
            $marginComparison = [
                'current' => $curMetrics['margin_pct'],
                'previous' => $prevMetrics['margin_pct'],
                'change_pp' => round($curMetrics['margin_pct'] - $prevMetrics['margin_pct'], 2),
            ];
        }

        $prevEmpty = $prevMetrics['sales_count'] === 0;
        if ($prevEmpty) {
            $limitations[] = self::msg('lim_prev_no_sales', $locale, [
                'prev_from' => $periods['previous']['from'] ?? '',
                'prev_to' => $periods['previous']['to'] ?? '',
            ]);
        }

        $revenueChange = (float) $deltas['revenue']['change'];
        $revenuePct = $deltas['revenue']['change_pct'];
        if ($prevEmpty && $curMetrics['sales_count'] > 0) {
            $direction = 'up_from_zero';
        } elseif ($revenuePct === null) {
            $direction = 'flat';
        } elseif ($revenuePct >= self::MEANINGFUL_PCT) {
            $direction = 'up';
        } elseif ($revenuePct <= -self::MEANINGFUL_PCT) {
            $direction = 'down';
        } else {
            $direction = 'flat';
        }

        $comparison = [
            'previous_period' => $periods['previous'],
            'comparable' => true,
            'metrics' => $comparisonMetrics,
            'margin_pct' => $marginComparison,
            'direction' => $direction,
            'revenue_change' => round($revenueChange, 2),
            'revenue_change_pct' => $revenuePct,
        ];

        /* ---------------- contributors (recorded movement) --------------- */

        $contributors = self::contributors(
            is_array($current['items'] ?? null) ? $current['items'] : [],
            is_array($previous['items'] ?? null) ? $previous['items'] : [],
            $products,
            3
        );

        /* ---------------- table + chart ---------------------------------- */

        $table = BusinessReporter::productBreakdown(
            is_array($current['items'] ?? null) ? $current['items'] : [],
            $products,
            'revenue',
            5,
            $currency
        );

        $chart = [
            'labels' => array_values(array_map(function ($row) {
                return (string) $row;
            }, self::windowDates($periods['current']))),
            'revenue' => self::dailyRevenue(
                is_array($current['sales'] ?? null) ? $current['sales'] : [],
                $periods['current']
            ),
        ];

        /* ---------------- narrative (figures only) ------------------------ */

        $narrative = [self::msg('period_label', $locale, [
            'from' => $periods['current']['from'],
            'to' => $periods['current']['to'],
            'days' => $periods['days'],
        ])];

        if ($curMetrics['sales_count'] > 0) {
            $narrative[] = self::msg('revenue_line', $locale, [
                'revenue' => self::money($curMetrics['revenue'], $currency),
                'count' => $curMetrics['sales_count'],
                'units' => self::num($curMetrics['units']),
            ]);

            if ($curMetrics['margin_pct'] !== null) {
                $narrative[] = self::msg('profit_line', $locale, [
                    'profit' => self::money($curMetrics['gross_profit'], $currency),
                    'pct' => self::pct($curMetrics['margin_pct']),
                ]);
            }

            if ($direction === 'up_from_zero') {
                $narrative[] = self::msg('compare_from_zero', $locale, [
                    'change' => self::money(abs($revenueChange), $currency),
                    'prev_from' => $periods['previous']['from'] ?? '',
                    'prev_to' => $periods['previous']['to'] ?? '',
                ]);
            } elseif ($direction === 'up') {
                $narrative[] = self::msg('compare_up', $locale, [
                    'pct' => self::pct(abs((float) $revenuePct)),
                    'change' => self::money(abs($revenueChange), $currency),
                    'prev_from' => $periods['previous']['from'] ?? '',
                    'prev_to' => $periods['previous']['to'] ?? '',
                ]);
            } elseif ($direction === 'down') {
                $narrative[] = self::msg('compare_down', $locale, [
                    'pct' => self::pct(abs((float) $revenuePct)),
                    'change' => self::money(abs($revenueChange), $currency),
                    'prev_from' => $periods['previous']['from'] ?? '',
                    'prev_to' => $periods['previous']['to'] ?? '',
                ]);
            } else {
                $narrative[] = self::msg('compare_flat', $locale, [
                    'change' => self::money(abs($revenueChange), $currency),
                    'prev_from' => $periods['previous']['from'] ?? '',
                    'prev_to' => $periods['previous']['to'] ?? '',
                ]);
            }

            if ($contributors !== []) {
                $items = [];
                foreach ($contributors as $contributor) {
                    $items[] = self::msg('contributor_item', $locale, [
                        'name' => $contributor['name'],
                        'change' => self::signedMoney($contributor['change'], $currency),
                    ]);
                }
                $narrative[] = self::msg('contributors_line', $locale, [
                    'list' => implode('; ', $items),
                ]);
                $narrative[] = self::msg('contributor_note', $locale);
            }

            if ($table['items'] !== []) {
                $topItems = [];
                foreach ($table['items'] as $row) {
                    $topItems[] = self::msg('top_product_item', $locale, [
                        'name' => $row['name'],
                        'revenue' => self::money($row['revenue'], $currency),
                    ]);
                }
                $narrative[] = self::msg('top_products_line', $locale, [
                    'list' => implode('; ', $topItems),
                ]);
            }
        }

        if ($curExpenses['count'] > 0) {
            $narrative[] = self::msg('expenses_line', $locale, [
                'total' => self::money($curExpenses['total'], $currency),
                'count' => $curExpenses['count'],
            ]);
            if ($curMetrics['revenue'] > 0) {
                $ratio = round(($curExpenses['total'] / $curMetrics['revenue']) * 100, 1);
                $narrative[] = self::msg('expense_ratio_line', $locale, ['pct' => self::pct($ratio)]);
            }
        }

        return [
            'periods' => $periods,
            'narrative' => $narrative,
            'metrics' => [
                'currency' => $currency,
                'revenue' => $curMetrics['revenue'],
                'sales_count' => $curMetrics['sales_count'],
                'units' => $curMetrics['units'],
                'gross_profit' => $curMetrics['gross_profit'],
                'margin_pct' => $curMetrics['margin_pct'],
                'unknown_cogs_items' => $curMetrics['unknown_cogs_items'],
                'expenses_total' => $curExpenses['total'],
                'expenses_count' => $curExpenses['count'],
                'expense_ratio_pct' => $curMetrics['revenue'] > 0
                    ? round(($curExpenses['total'] / $curMetrics['revenue']) * 100, 2)
                    : null,
            ],
            'previous_metrics' => [
                'revenue' => $prevMetrics['revenue'],
                'sales_count' => $prevMetrics['sales_count'],
                'units' => $prevMetrics['units'],
                'gross_profit' => $prevMetrics['gross_profit'],
                'margin_pct' => $prevMetrics['margin_pct'],
                'expenses_total' => $prevExpenses['total'],
            ],
            'comparison' => $comparison,
            'contributors' => $contributors,
            'table' => $table,
            'chart' => $chart,
            'sources' => ['sales', 'sale_items', 'products', 'office_expenses'],
            'limitations' => $limitations,
        ];
    }

    /**
     * The products with the largest RECORDED revenue movement between the two
     * windows, biggest absolute movement first, ties by name then id. This is
     * arithmetic over the same rows the table uses - never a causal claim.
     *
     * @return array<int, array{product_id: string, name: string, current: float, previous: float, change: float}>
     */
    public static function contributors(array $currentItems, array $previousItems, array $products, int $limit = 3): array
    {
        $limit = max(1, $limit);

        $revenueByProduct = function (array $items): array {
            $out = [];
            foreach ($items as $item) {
                $id = (string) ($item['product_id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $total = is_numeric($item['total_price'] ?? null) ? (float) $item['total_price'] : 0.0;
                $out[$id] = ($out[$id] ?? 0.0) + $total;
            }

            return $out;
        };

        $currentRevenue = $revenueByProduct($currentItems);
        $previousRevenue = $revenueByProduct($previousItems);

        $rows = [];
        foreach (array_unique(array_merge(array_keys($currentRevenue), array_keys($previousRevenue))) as $id) {
            $cur = round($currentRevenue[$id] ?? 0.0, 2);
            $prev = round($previousRevenue[$id] ?? 0.0, 2);
            $change = round($cur - $prev, 2);
            if ($change == 0.0) {
                continue;
            }
            $rows[] = [
                'product_id' => (string) $id,
                'name' => (string) ($products[$id]['name'] ?? 'Bidhaa'),
                'current' => $cur,
                'previous' => $prev,
                'change' => $change,
            ];
        }

        usort($rows, function (array $a, array $b) {
            return [abs($b['change']), $b['name'], $a['product_id']]
                <=> [abs($a['change']), $a['name'], $a['product_id']];
        });

        return array_slice($rows, 0, $limit);
    }

    /* ------------------------------------------------------------------ */
    /* Model reword (optional; numbers must already exist in the data)     */
    /* ------------------------------------------------------------------ */

    /**
     * Accept only a bounded single-paragraph string whose numbers are a
     * subset of the numbers present in the data we supplied.
     */
    public static function sanitizeReword($raw, array $allowedNumbers): ?string
    {
        if (! is_array($raw) || ! is_string($raw['summary'] ?? null)) {
            return null;
        }

        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $raw['summary']) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text) < 20 || mb_strlen($text) > 600) {
            return null;
        }

        if (preg_match_all('/\d+(?:[.,]\d+)?/u', $text, $matches) > 0) {
            // Compare normalised forms on BOTH sides, so a caller that hands us
            // whole numbers as ints still gates correctly.
            $allowed = array_map(
                static fn ($value): string => self::normalizeNumber((string) $value),
                $allowedNumbers
            );
            foreach ($matches[0] as $number) {
                $normalized = self::normalizeNumber($number);
                if (! in_array($normalized, $allowed, true)) {
                    return null; // a number we never supplied - reject
                }
            }
        }

        return $text;
    }

    /**
     * Every number that may legally appear in a reworded summary: the digits
     * of the payload plus the formatted forms used in the narrative.
     *
     * @return array<int, string>
     */
    public static function allowedNumbers(array $summary, array $extraStrings = []): array
    {
        $chunks = [json_encode($summary, JSON_UNESCAPED_UNICODE)];
        foreach ($extraStrings as $string) {
            if (is_string($string)) {
                $chunks[] = $string;
            }
        }

        $allowed = [];
        foreach ($chunks as $chunk) {
            if (! is_string($chunk)) {
                continue;
            }
            if (preg_match_all('/\d+(?:[.,]\d+)?/', $chunk, $matches) > 0) {
                foreach ($matches[0] as $number) {
                    $allowed[] = self::normalizeNumber($number);
                }
            }
        }

        // A plain list, never a map: a numeric-string KEY would be coerced to an
        // int, and the strict gate in sanitizeReword() would then reject every
        // legitimate figure (string '9000' !== int 9000).
        return array_values(array_unique($allowed));
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /** Every date of a window, ascending (bounded by the caller's range). */
    private static function windowDates(array $window): array
    {
        $from = (string) ($window['from'] ?? '');
        $to = (string) ($window['to'] ?? '');
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($from === '' || $to === '' || $fromTs === false || $toTs === false || $from > $to) {
            return [];
        }

        $dates = [];
        for ($ts = $fromTs; $ts <= $toTs; $ts = strtotime('+1 day', $ts)) {
            $dates[] = date('Y-m-d', $ts);
            if (count($dates) > 366) {
                break;
            }
        }

        return $dates;
    }

    /** Daily revenue aligned to the window labels (missing days are 0). */
    private static function dailyRevenue(array $sales, array $window): array
    {
        $byDate = [];
        foreach ($sales as $sale) {
            $date = substr((string) ($sale['sale_date'] ?? ''), 0, 10);
            if ($date === '') {
                continue;
            }
            $amount = is_numeric($sale['total_amount'] ?? null) ? (float) $sale['total_amount'] : 0.0;
            $byDate[$date] = ($byDate[$date] ?? 0.0) + $amount;
        }

        $series = [];
        foreach (self::windowDates($window) as $date) {
            $series[] = round($byDate[$date] ?? 0.0, 2);
        }

        return $series;
    }

    private static function money(float $value, string $currency): string
    {
        return $currency.' '.number_format(round($value), 0, '.', ',');
    }

    private static function signedMoney(float $value, string $currency): string
    {
        $sign = $value > 0 ? '+' : ($value < 0 ? '-' : '');

        return $sign.$currency.' '.number_format(abs(round($value)), 0, '.', ',');
    }

    private static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }

    private static function pct(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ','), '0'), '.');
    }

    private static function normalizeNumber(string $number): string
    {
        $normalized = str_replace(',', '.', $number);
        if (str_contains($normalized, '.')) {
            $normalized = rtrim(rtrim($normalized, '0'), '.');
        }

        return $normalized;
    }
}
