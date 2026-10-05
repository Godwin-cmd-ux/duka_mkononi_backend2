<?php

namespace App\Services\Ai;

/**
 * Capability #4 - composes the concise answer text for a report.
 *
 * The sentence is built from the already-computed figures and a small per-locale
 * template table, so the answer is always truthful and always in the seller's
 * language. The model is not needed to state the numbers (grounding rule 2).
 */
class ReportNarrator
{
    private const TEMPLATES = [
        'sw' => [
            'revenue_summary' => 'Kati ya {from} na {to} uliuza {currency} {revenue} kwa mauzo {count}. Faida ghafi: {currency} {profit} ({margin}%).',
            'top_products' => 'Bidhaa zinazoongoza kati ya {from} na {to}:',
            'sales_comparison' => 'Kati ya {from} na {to} mauzo yalikuwa {currency} {revenue}. Kipindi kilichopita ({prev_from} - {prev_to}) yalikuwa {currency} {prev_revenue} ({change}%).',
            'expenses' => 'Kati ya {from} na {to} ulitumia {currency} {total} kwenye matumizi {count}.',
            'expenses_category' => 'Kati ya {from} na {to} ulitumia {currency} {category_total} kwenye {category}.',
            'customers' => 'Una wateja {count} kwa jumla; {new} walijiunga kati ya {from} na {to}.',
            'stock_value' => 'Thamani ya bidhaa zilizopo (kwa gharama): {currency} {cost} kwa bidhaa {products}; {out} zimeisha.',
            'unknown' => 'Sikuweza kuelewa swali hili. Naweza kujibu kuhusu mauzo, faida, bidhaa zinazoongoza, matumizi, wateja na thamani ya stock.',
        ],
        'en' => [
            'revenue_summary' => 'Between {from} and {to} you sold {currency} {revenue} across {count} sales. Gross profit: {currency} {profit} ({margin}%).',
            'top_products' => 'Top products between {from} and {to}:',
            'sales_comparison' => 'Between {from} and {to} sales were {currency} {revenue}. The previous period ({prev_from} - {prev_to}) was {currency} {prev_revenue} ({change}%).',
            'expenses' => 'Between {from} and {to} you spent {currency} {total} across {count} expenses.',
            'expenses_category' => 'Between {from} and {to} you spent {currency} {category_total} on {category}.',
            'customers' => 'You have {count} customers in total; {new} joined between {from} and {to}.',
            'stock_value' => 'Inventory value at cost: {currency} {cost} across {products} products; {out} are out of stock.',
            'unknown' => 'I could not understand this question. I can answer about sales, profit, top products, expenses, customers and stock value.',
        ],
        'fr' => [
            'revenue_summary' => 'Entre le {from} et le {to}, vous avez vendu {currency} {revenue} sur {count} ventes. Marge brute : {currency} {profit} ({margin}%).',
            'top_products' => 'Meilleurs produits entre le {from} et le {to} :',
            'sales_comparison' => 'Entre le {from} et le {to}, les ventes étaient de {currency} {revenue}. La période précédente ({prev_from} - {prev_to}) : {currency} {prev_revenue} ({change}%).',
            'expenses' => 'Entre le {from} et le {to}, vous avez dépensé {currency} {total} sur {count} dépenses.',
            'expenses_category' => 'Entre le {from} et le {to}, vous avez dépensé {currency} {category_total} pour {category}.',
            'customers' => 'Vous avez {count} clients au total ; {new} ont rejoint entre le {from} et le {to}.',
            'stock_value' => 'Valeur du stock au coût : {currency} {cost} pour {products} produits ; {out} en rupture.',
            'unknown' => 'Je n\'ai pas compris cette question. Je peux répondre sur les ventes, le bénéfice, les meilleurs produits, les dépenses, les clients et la valeur du stock.',
        ],
        'hi' => [
            'revenue_summary' => '{from} से {to} तक आपने {count} बिक्री में {currency} {revenue} बेचा। सकल लाभ: {currency} {profit} ({margin}%)।',
            'top_products' => '{from} से {to} के शीर्ष उत्पाद:',
            'sales_comparison' => '{from} से {to} तक बिक्री {currency} {revenue} थी। पिछली अवधि ({prev_from} - {prev_to}) {currency} {prev_revenue} ({change}%) थी।',
            'expenses' => '{from} से {to} तक आपने {count} खर्चों में {currency} {total} खर्च किया।',
            'expenses_category' => '{from} से {to} तक आपने {category} पर {currency} {category_total} खर्च किया।',
            'customers' => 'आपके कुल {count} ग्राहक हैं; {from} से {to} के बीच {new} जुड़े।',
            'stock_value' => 'लागत पर स्टॉक मूल्य: {products} उत्पादों के लिए {currency} {cost}; {out} स्टॉक ख़त्म।',
            'unknown' => 'मैं यह प्रश्न समझ नहीं पाया। मैं बिक्री, लाभ, शीर्ष उत्पाद, खर्च, ग्राहक और स्टॉक मूल्य के बारे में बता सकता हूँ।',
        ],
        'es' => [
            'revenue_summary' => 'Entre el {from} y el {to} vendiste {currency} {revenue} en {count} ventas. Beneficio bruto: {currency} {profit} ({margin}%).',
            'top_products' => 'Productos destacados entre el {from} y el {to}:',
            'sales_comparison' => 'Entre el {from} y el {to} las ventas fueron {currency} {revenue}. El periodo anterior ({prev_from} - {prev_to}) fue {currency} {prev_revenue} ({change}%).',
            'expenses' => 'Entre el {from} y el {to} gastaste {currency} {total} en {count} gastos.',
            'expenses_category' => 'Entre el {from} y el {to} gastaste {currency} {category_total} en {category}.',
            'customers' => 'Tienes {count} clientes en total; {new} se unieron entre el {from} y el {to}.',
            'stock_value' => 'Valor del inventario al coste: {currency} {cost} en {products} productos; {out} agotados.',
            'unknown' => 'No pude entender esta pregunta. Puedo responder sobre ventas, beneficio, productos destacados, gastos, clientes y valor del inventario.',
        ],
        'ur' => [
            'revenue_summary' => '{from} سے {to} تک آپ نے {count} فروخت میں {currency} {revenue} بیچا۔ مجموعی منافع: {currency} {profit} ({margin}%)۔',
            'top_products' => '{from} سے {to} تک سرفہرست مصنوعات:',
            'sales_comparison' => '{from} سے {to} تک فروخت {currency} {revenue} تھی۔ پچھلی مدت ({prev_from} - {prev_to}) {currency} {prev_revenue} ({change}%) تھی۔',
            'expenses' => '{from} سے {to} تک آپ نے {count} اخراجات پر {currency} {total} خرچ کیا۔',
            'expenses_category' => '{from} سے {to} تک آپ نے {category} پر {currency} {category_total} خرچ کیا۔',
            'customers' => 'آپ کے کل {count} گاہک ہیں؛ {from} سے {to} کے درمیان {new} شامل ہوئے۔',
            'stock_value' => 'لاگت پر اسٹاک کی قیمت: {products} مصنوعات کے لیے {currency} {cost}؛ {out} اسٹاک ختم۔',
            'unknown' => 'میں یہ سوال سمجھ نہیں سکا۔ میں فروخت، منافع، سرفہرست مصنوعات، اخراجات، گاہکوں اور اسٹاک قیمت کے بارے میں بتا سکتا ہوں۔',
        ],
        'de' => [
            'revenue_summary' => 'Zwischen {from} und {to} hast du {currency} {revenue} in {count} Verkäufen verkauft. Bruttogewinn: {currency} {profit} ({margin}%).',
            'top_products' => 'Top-Produkte zwischen {from} und {to}:',
            'sales_comparison' => 'Zwischen {from} und {to} betrug der Umsatz {currency} {revenue}. Der vorherige Zeitraum ({prev_from} - {prev_to}) war {currency} {prev_revenue} ({change}%).',
            'expenses' => 'Zwischen {from} und {to} hast du {currency} {total} für {count} Ausgaben ausgegeben.',
            'expenses_category' => 'Zwischen {from} und {to} hast du {currency} {category_total} für {category} ausgegeben.',
            'customers' => 'Du hast insgesamt {count} Kunden; {new} kamen zwischen {from} und {to} dazu.',
            'stock_value' => 'Lagerwert zu Kosten: {currency} {cost} für {products} Produkte; {out} nicht vorrätig.',
            'unknown' => 'Ich konnte diese Frage nicht verstehen. Ich kann zu Umsatz, Gewinn, Top-Produkten, Ausgaben, Kunden und Lagerwert antworten.',
        ],
        'zh' => [
            'revenue_summary' => '{from} 至 {to}，您通过 {count} 笔销售售出 {currency} {revenue}。毛利：{currency} {profit}（{margin}%）。',
            'top_products' => '{from} 至 {to} 的热门商品：',
            'sales_comparison' => '{from} 至 {to} 的销售额为 {currency} {revenue}。上一期间（{prev_from} - {prev_to}）为 {currency} {prev_revenue}（{change}%）。',
            'expenses' => '{from} 至 {to}，您在 {count} 笔支出上花费了 {currency} {total}。',
            'expenses_category' => '{from} 至 {to}，您在 {category} 上花费了 {currency} {category_total}。',
            'customers' => '您共有 {count} 位客户；{from} 至 {to} 新增 {new} 位。',
            'stock_value' => '按成本计的库存价值：{products} 件商品共 {currency} {cost}；{out} 件缺货。',
            'unknown' => '我无法理解这个问题。我可以回答关于销售额、利润、热门商品、支出、客户和库存价值的问题。',
        ],
    ];

    public static function supportedLocales(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{from: string, to: string}|null  $range
     * @param  array{from: string, to: string}|null  $previousRange
     */
    public static function summaryText(string $tool, array $data, string $locale, string $currency, ?array $range = null, ?array $previousRange = null): string
    {
        $locale = isset(self::TEMPLATES[$locale]) ? $locale : 'en';
        $table = self::TEMPLATES[$locale];

        $from = $range['from'] ?? '';
        $to = $range['to'] ?? '';
        $prevFrom = $previousRange['from'] ?? '';
        $prevTo = $previousRange['to'] ?? '';

        switch ($tool) {
            case 'revenue_summary':
                $tpl = $table['revenue_summary'];

                return strtr($tpl, [
                    '{from}' => $from,
                    '{to}' => $to,
                    '{currency}' => $currency,
                    '{revenue}' => number_format((float) ($data['revenue'] ?? 0), 0),
                    '{count}' => (string) ($data['sales_count'] ?? 0),
                    '{profit}' => number_format((float) ($data['gross_profit'] ?? 0), 0),
                    '{margin}' => $data['margin_pct'] === null ? '-' : number_format((float) $data['margin_pct'], 1),
                ]);

            case 'top_products':
                return strtr($table['top_products'], ['{from}' => $from, '{to}' => $to]);

            case 'sales_comparison':
                return strtr($table['sales_comparison'], [
                    '{from}' => $from,
                    '{to}' => $to,
                    '{prev_from}' => $prevFrom,
                    '{prev_to}' => $prevTo,
                    '{currency}' => $currency,
                    '{revenue}' => number_format((float) ($data['revenue'] ?? 0), 0),
                    '{prev_revenue}' => number_format((float) ($data['previous_revenue'] ?? 0), 0),
                    '{change}' => $data['revenue_change_pct'] === null ? '-' : number_format((float) $data['revenue_change_pct'], 1),
                ]);

            case 'expenses':
                if (($data['category_filter'] ?? null) !== null) {
                    return strtr($table['expenses_category'], [
                        '{from}' => $from,
                        '{to}' => $to,
                        '{currency}' => $currency,
                        '{category_total}' => number_format((float) ($data['category_total'] ?? 0), 0),
                        '{category}' => (string) $data['category_filter'],
                    ]);
                }

                return strtr($table['expenses'], [
                    '{from}' => $from,
                    '{to}' => $to,
                    '{currency}' => $currency,
                    '{total}' => number_format((float) ($data['total'] ?? 0), 0),
                    '{count}' => (string) ($data['count'] ?? 0),
                ]);

            case 'customers':
                return strtr($table['customers'], [
                    '{from}' => $from,
                    '{to}' => $to,
                    '{count}' => (string) ($data['customer_count'] ?? 0),
                    '{new}' => (string) ($data['new_customers'] ?? 0),
                ]);

            case 'stock_value':
                return strtr($table['stock_value'], [
                    '{currency}' => $currency,
                    '{cost}' => number_format((float) ($data['inventory_value_cost'] ?? 0), 0),
                    '{products}' => (string) ($data['product_count'] ?? 0),
                    '{out}' => (string) ($data['out_of_stock_count'] ?? 0),
                ]);

            default:
                return $table['unknown'];
        }
    }
}
