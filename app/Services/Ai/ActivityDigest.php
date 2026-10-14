<?php

namespace App\Services\Ai;

/**
 * Capability #17 - Team Activity Digest (deterministic core).
 *
 * A manager-facing digest of what the business's own staff actually recorded in
 * the existing activity-log system (`user_logs`). Grounding rules that shape
 * this class:
 *  - every count, actor and timestamp comes from real log rows passed in by the
 *    caller (this class touches no database and reads no config, so the whole
 *    contract is unit-testable);
 *  - the log family of an action is classified by deterministic prefix rules
 *    (never by the model), so a "sale" is counted the same way every time;
 *  - it never infers intent, misconduct or employee performance: counts are
 *    presented as logged events with an explicit limitation saying so;
 *  - missing logging coverage is made VISIBLE (members with no recorded events)
 *    instead of being silently averaged away;
 *  - windows are pure date arithmetic over a caller-supplied "today", so an
 *    in-progress period can be flagged as partial rather than compared
 *    unfairly against a complete one.
 *
 * The model (see AiTeamActivityController) may at most REWORD the deterministic
 * narrative, with every figure gated against the payload it was given.
 */
class ActivityDigest
{
    public const CAPABILITY = 'team_activity';

    /** The reporting windows a manager may ask for. */
    public const PERIODS = ['today', 'yesterday', 'this_week', 'last_week', 'last_7_days'];

    /**
     * Activity families. These are the allow-listed buckets the digest groups
     * real `user_logs.action` values into - the model never picks one.
     */
    public const CATEGORIES = [
        'product', 'sale', 'expense', 'customer', 'order', 'advert', 'account', 'ai', 'other',
    ];

    /** Prefixes per family, tested against the action name with any `AI_` stripped. */
    private const FAMILY_PREFIXES = [
        'product' => ['PRODUCT', 'PRODUCTS', 'INVENTORY', 'STOCK', 'RESTOCK', 'PRICE', 'BIDHAA'],
        'sale' => ['SALE', 'SALES', 'REVENUE', 'MAUZO'],
        'expense' => ['EXPENSE', 'EXPENSES', 'MATUMIZI'],
        'customer' => ['CUSTOMER', 'CUSTOMERS', 'CLIENT', 'MTEJA'],
        'order' => ['ORDER', 'ORDERS', 'DELIVERY', 'SHIPMENT', 'PAYMENT', 'PAYMENTS'],
        'advert' => ['MATANGAZO', 'ADVERT', 'ADVERTS', 'ADS'],
        // 'AUTH' is what the auth flow actually writes today (AUTH_SUCCESS),
        // alongside the older LOGIN_* actions - both are sign-in activity.
        'account' => ['LOGIN', 'LOGOUT', 'REGISTER', 'PASSWORD', 'PROFILE', 'ONBOARDING', 'SESSION', 'LANGUAGE', 'AUTH'],
    ];

    /**
     * Detail keys that are never surfaced: they identify people rather than a
     * record a manager can open, and the digest already shows the actor.
     */
    private const REF_DENY = ['user_id', 'seller_id', 'business_id', 'actor_id', 'reviewer_id'];

    /** Actions that create/mutate a business record - the noteworthy candidates. */
    private const CREATION_SUFFIXES = ['_CREATE', '_COMMIT', '_RECORDED', '_INITIATED'];

    /**
     * Every message must exist for all eight locales (unit-tested), and every
     * locale must carry exactly the same placeholders for a key.
     */
    public const MESSAGES = [
        'sw' => [
            'period_today' => 'Leo',
            'period_yesterday' => 'Jana',
            'period_this_week' => 'Wiki hii',
            'period_last_week' => 'Wiki iliyopita',
            'period_last_7_days' => 'Siku 7 zilizopita',
            'cat_product' => 'Bidhaa',
            'cat_sale' => 'Mauzo',
            'cat_expense' => 'Matumizi',
            'cat_customer' => 'Wateja',
            'cat_order' => 'Oda na malipo',
            'cat_advert' => 'Matangazo',
            'cat_account' => 'Akaunti na kuingia',
            'cat_ai' => 'Matumizi ya AI',
            'cat_other' => 'Mengineyo',
            'actor_unknown' => 'Mwanachama',
            'n_period' => 'Kipindi: {period} ({from} hadi {to}).',
            'n_events' => 'Matukio {events} yalirekodiwa na wanachama {actors}.',
            'n_top_category' => 'Zaidi ni {category}: matukio {count}.',
            'n_failed' => 'Matukio {failed} yalishindikana.',
            'n_compare_up' => 'Kipindi kilichopita kilikuwa na matukio {previous}; sasa kuna {change} zaidi.',
            'n_compare_down' => 'Kipindi kilichopita kilikuwa na matukio {previous}; sasa kuna {change} pungufu.',
            'n_compare_flat' => 'Kiwango kinafanana na kipindi kilichopita (matukio {previous}).',
            'n_compare_from_zero' => 'Kipindi kilichopita hakikuwa na matukio yaliyorekodiwa.',
            'n_no_events' => 'Hakuna matukio yaliyorekodiwa katika kipindi hiki.',
            'n_silent' => 'Wanachama {silent} hawana matukio yaliyorekodiwa katika kipindi hiki.',
            'lim_counts_not_performance' => 'Hesabu hizi ni za matukio yaliyorekodiwa; si tathmini ya utendaji wa mfanyakazi.',
            'lim_partial_window' => 'Kipindi cha sasa bado kinaendelea, kwa hiyo hesabu zinaweza kuongezeka.',
            'lim_log_coverage' => 'Ukamilifu hutegemea kile mfumo ulichorekodi; matukio yasiyorekodiwa hayaonekani.',
            'lim_no_inference' => 'Rekodi hizi zinaonyesha matukio pekee - hazithibitishi nia, makosa, wala ubora wa kazi.',
            'lim_no_events' => 'Hakuna kumbukumbu za matukio kwa kipindi hiki, hivyo hakuna muhtasari wa kutolewa.',
            'lim_truncated' => 'Haijakamilika: matukio zaidi ya {max} hayakuchanganuliwa.',
            'src_activity_log' => 'Kumbukumbu za mfumo (user_logs) za wanachama wa biashara hii.',
            'err_period' => 'Kipindi si sahihi. Tumia: today, yesterday, this_week, last_week au last_7_days.',
        ],
        'en' => [
            'period_today' => 'Today',
            'period_yesterday' => 'Yesterday',
            'period_this_week' => 'This week',
            'period_last_week' => 'Last week',
            'period_last_7_days' => 'Last 7 days',
            'cat_product' => 'Products',
            'cat_sale' => 'Sales',
            'cat_expense' => 'Expenses',
            'cat_customer' => 'Customers',
            'cat_order' => 'Orders and payments',
            'cat_advert' => 'Advertisements',
            'cat_account' => 'Account and sign-in',
            'cat_ai' => 'AI usage',
            'cat_other' => 'Other',
            'actor_unknown' => 'Team member',
            'n_period' => 'Period: {period} ({from} to {to}).',
            'n_events' => '{events} events were recorded by {actors} members.',
            'n_top_category' => 'Most of them were {category}: {count} events.',
            'n_failed' => '{failed} events failed.',
            'n_compare_up' => 'The previous period had {previous} events; there are now {change} more.',
            'n_compare_down' => 'The previous period had {previous} events; there are now {change} fewer.',
            'n_compare_flat' => 'Activity is level with the previous period ({previous} events).',
            'n_compare_from_zero' => 'The previous period had no recorded events.',
            'n_no_events' => 'No events were recorded in this period.',
            'n_silent' => '{silent} members have no recorded events in this period.',
            'lim_counts_not_performance' => 'These counts are recorded events, not an assessment of staff performance.',
            'lim_partial_window' => 'The current period is still in progress, so the counts may still grow.',
            'lim_log_coverage' => 'Completeness depends on what the system recorded; unlogged work is invisible here.',
            'lim_no_inference' => 'These records show events only - they do not establish intent, wrongdoing or quality of work.',
            'lim_no_events' => 'There are no activity records for this period, so no digest can be produced.',
            'lim_truncated' => 'Incomplete: more than {max} events were not analysed.',
            'src_activity_log' => 'The system activity log (user_logs) of this business\'s members.',
            'err_period' => 'Invalid period. Use: today, yesterday, this_week, last_week or last_7_days.',
        ],
        'fr' => [
            'period_today' => "Aujourd'hui",
            'period_yesterday' => 'Hier',
            'period_this_week' => 'Cette semaine',
            'period_last_week' => 'La semaine dernière',
            'period_last_7_days' => 'Les 7 derniers jours',
            'cat_product' => 'Produits',
            'cat_sale' => 'Ventes',
            'cat_expense' => 'Dépenses',
            'cat_customer' => 'Clients',
            'cat_order' => 'Commandes et paiements',
            'cat_advert' => 'Publicités',
            'cat_account' => 'Compte et connexion',
            'cat_ai' => "Utilisation de l'IA",
            'cat_other' => 'Autre',
            'actor_unknown' => "Membre de l'équipe",
            'n_period' => 'Période : {period} ({from} à {to}).',
            'n_events' => '{events} événements ont été enregistrés par {actors} membres.',
            'n_top_category' => 'La plupart concernent {category} : {count} événements.',
            'n_failed' => '{failed} événements ont échoué.',
            'n_compare_up' => 'La période précédente comptait {previous} événements ; il y en a maintenant {change} de plus.',
            'n_compare_down' => 'La période précédente comptait {previous} événements ; il y en a maintenant {change} de moins.',
            'n_compare_flat' => "L'activité est stable par rapport à la période précédente ({previous} événements).",
            'n_compare_from_zero' => "La période précédente n'avait aucun événement enregistré.",
            'n_no_events' => 'Aucun événement enregistré durant cette période.',
            'n_silent' => '{silent} membres n\'ont aucun événement enregistré durant cette période.',
            'lim_counts_not_performance' => "Ces chiffres sont des événements enregistrés, pas une évaluation de la performance du personnel.",
            'lim_partial_window' => 'La période en cours n\'est pas terminée, les chiffres peuvent encore augmenter.',
            'lim_log_coverage' => "L'exhaustivité dépend de ce que le système a enregistré ; le travail non journalisé est invisible ici.",
            'lim_no_inference' => "Ces enregistrements montrent des événements uniquement - ils n'établissent ni intention, ni faute, ni qualité du travail.",
            'lim_no_events' => "Aucun enregistrement d'activité pour cette période : aucun résumé ne peut être produit.",
            'lim_truncated' => 'Incomplet : plus de {max} événements n\'ont pas été analysés.',
            'src_activity_log' => "Le journal d'activité système (user_logs) des membres de cette entreprise.",
            'err_period' => 'Période invalide. Utilisez : today, yesterday, this_week, last_week ou last_7_days.',
        ],
        'hi' => [
            'period_today' => 'आज',
            'period_yesterday' => 'कल',
            'period_this_week' => 'इस सप्ताह',
            'period_last_week' => 'पिछले सप्ताह',
            'period_last_7_days' => 'पिछले 7 दिन',
            'cat_product' => 'उत्पाद',
            'cat_sale' => 'बिक्री',
            'cat_expense' => 'खर्च',
            'cat_customer' => 'ग्राहक',
            'cat_order' => 'ऑर्डर और भुगतान',
            'cat_advert' => 'विज्ञापन',
            'cat_account' => 'खाता और साइन-इन',
            'cat_ai' => 'AI उपयोग',
            'cat_other' => 'अन्य',
            'actor_unknown' => 'टीम सदस्य',
            'n_period' => 'अवधि: {period} ({from} से {to})।',
            'n_events' => '{actors} सदस्यों द्वारा {events} घटनाएँ दर्ज की गईं।',
            'n_top_category' => 'इनमें सबसे अधिक {category} थीं: {count} घटनाएँ।',
            'n_failed' => '{failed} घटनाएँ विफल रहीं।',
            'n_compare_up' => 'पिछली अवधि में {previous} घटनाएँ थीं; अब {change} अधिक हैं।',
            'n_compare_down' => 'पिछली अवधि में {previous} घटनाएँ थीं; अब {change} कम हैं।',
            'n_compare_flat' => 'गतिविधि पिछली अवधि के बराबर है ({previous} घटनाएँ)।',
            'n_compare_from_zero' => 'पिछली अवधि में कोई घटना दर्ज नहीं हुई।',
            'n_no_events' => 'इस अवधि में कोई घटना दर्ज नहीं हुई।',
            'n_silent' => '{silent} सदस्यों की इस अवधि में कोई घटना दर्ज नहीं है।',
            'lim_counts_not_performance' => 'ये संख्याएँ दर्ज घटनाएँ हैं, कर्मचारी प्रदर्शन का आकलन नहीं।',
            'lim_partial_window' => 'वर्तमान अवधि अभी चल रही है, इसलिए संख्याएँ और बढ़ सकती हैं।',
            'lim_log_coverage' => 'पूर्णता इस पर निर्भर है कि सिस्टम ने क्या दर्ज किया; बिना दर्ज किया गया काम यहाँ दिखता नहीं।',
            'lim_no_inference' => 'ये रिकॉर्ड केवल घटनाएँ दिखाते हैं - ये नीयत, गलती या काम की गुणवत्ता सिद्ध नहीं करते।',
            'lim_no_events' => 'इस अवधि के लिए कोई गतिविधि रिकॉर्ड नहीं है, इसलिए सारांश नहीं बनाया जा सकता।',
            'lim_truncated' => 'अपूर्ण: {max} से अधिक घटनाओं का विश्लेषण नहीं हुआ।',
            'src_activity_log' => 'इस व्यवसाय के सदस्यों का सिस्टम गतिविधि लॉग (user_logs)।',
            'err_period' => 'अमान्य अवधि। उपयोग करें: today, yesterday, this_week, last_week या last_7_days।',
        ],
        'es' => [
            'period_today' => 'Hoy',
            'period_yesterday' => 'Ayer',
            'period_this_week' => 'Esta semana',
            'period_last_week' => 'La semana pasada',
            'period_last_7_days' => 'Últimos 7 días',
            'cat_product' => 'Productos',
            'cat_sale' => 'Ventas',
            'cat_expense' => 'Gastos',
            'cat_customer' => 'Clientes',
            'cat_order' => 'Pedidos y pagos',
            'cat_advert' => 'Anuncios',
            'cat_account' => 'Cuenta e inicio de sesión',
            'cat_ai' => 'Uso de IA',
            'cat_other' => 'Otros',
            'actor_unknown' => 'Miembro del equipo',
            'n_period' => 'Período: {period} ({from} a {to}).',
            'n_events' => '{actors} miembros registraron {events} eventos.',
            'n_top_category' => 'La mayoría fue {category}: {count} eventos.',
            'n_failed' => '{failed} eventos fallaron.',
            'n_compare_up' => 'El período anterior tuvo {previous} eventos; ahora hay {change} más.',
            'n_compare_down' => 'El período anterior tuvo {previous} eventos; ahora hay {change} menos.',
            'n_compare_flat' => 'La actividad está al mismo nivel que el período anterior ({previous} eventos).',
            'n_compare_from_zero' => 'El período anterior no tuvo eventos registrados.',
            'n_no_events' => 'No se registraron eventos en este período.',
            'n_silent' => '{silent} miembros no tienen eventos registrados en este período.',
            'lim_counts_not_performance' => 'Estos recuentos son eventos registrados, no una evaluación del desempeño del personal.',
            'lim_partial_window' => 'El período actual aún está en curso, así que los recuentos pueden aumentar.',
            'lim_log_coverage' => 'La exhaustividad depende de lo que el sistema registró; el trabajo no registrado es invisible aquí.',
            'lim_no_inference' => 'Estos registros muestran solo eventos - no establecen intención, falta ni calidad del trabajo.',
            'lim_no_events' => 'No hay registros de actividad para este período, por lo que no se puede producir un resumen.',
            'lim_truncated' => 'Incompleto: no se analizaron más de {max} eventos.',
            'src_activity_log' => 'El registro de actividad del sistema (user_logs) de los miembros de esta empresa.',
            'err_period' => 'Período inválido. Use: today, yesterday, this_week, last_week o last_7_days.',
        ],
        'ur' => [
            'period_today' => 'آج',
            'period_yesterday' => 'کل',
            'period_this_week' => 'اس ہفتے',
            'period_last_week' => 'گزشتہ ہفتے',
            'period_last_7_days' => 'گزشتہ 7 دن',
            'cat_product' => 'مصنوعات',
            'cat_sale' => 'فروخت',
            'cat_expense' => 'اخراجات',
            'cat_customer' => 'گاہک',
            'cat_order' => 'آرڈر اور ادائیگی',
            'cat_advert' => 'اشتہارات',
            'cat_account' => 'اکاؤنٹ اور سائن اِن',
            'cat_ai' => 'AI استعمال',
            'cat_other' => 'دیگر',
            'actor_unknown' => 'ٹیم ممبر',
            'n_period' => 'مدت: {period} ({from} سے {to})۔',
            'n_events' => '{actors} ممبران نے {events} واقعات درج کیے۔',
            'n_top_category' => 'زیادہ تر {category} تھے: {count} واقعات۔',
            'n_failed' => '{failed} واقعات ناکام ہوئے۔',
            'n_compare_up' => 'گزشتہ مدت میں {previous} واقعات تھے؛ اب {change} زیادہ ہیں۔',
            'n_compare_down' => 'گزشتہ مدت میں {previous} واقعات تھے؛ اب {change} کم ہیں۔',
            'n_compare_flat' => 'سرگرمی گزشتہ مدت کے برابر ہے ({previous} واقعات)۔',
            'n_compare_from_zero' => 'گزشتہ مدت میں کوئی واقعہ درج نہیں ہوا۔',
            'n_no_events' => 'اس مدت میں کوئی واقعہ درج نہیں ہوا۔',
            'n_silent' => '{silent} ممبران کے اس مدت میں کوئی درج واقعات نہیں ہیں۔',
            'lim_counts_not_performance' => 'یہ اعداد درج واقعات ہیں، عملے کی کارکردگی کا جائزہ نہیں۔',
            'lim_partial_window' => 'موجودہ مدت ابھی جاری ہے، اس لیے اعداد مزید بڑھ سکتے ہیں۔',
            'lim_log_coverage' => 'مکمل ہونا اس پر منحصر ہے کہ سسٹم نے کیا درج کیا؛ بغیر درج کام یہاں نظر نہیں آتا۔',
            'lim_no_inference' => 'یہ ریکارڈ صرف واقعات دکھاتے ہیں - یہ نیت، غلطی یا کام کے معیار کو ثابت نہیں کرتے۔',
            'lim_no_events' => 'اس مدت کے لیے کوئی سرگرمی ریکارڈ نہیں، اس لیے خلاصہ نہیں بنایا جا سکتا۔',
            'lim_truncated' => 'نامکمل: {max} سے زیادہ واقعات کا تجزیہ نہیں ہوا۔',
            'src_activity_log' => 'اس کاروبار کے ممبران کا سسٹم سرگرمی لاگ (user_logs)۔',
            'err_period' => 'مدت غلط ہے۔ استعمال کریں: today, yesterday, this_week, last_week یا last_7_days۔',
        ],
        'de' => [
            'period_today' => 'Heute',
            'period_yesterday' => 'Gestern',
            'period_this_week' => 'Diese Woche',
            'period_last_week' => 'Letzte Woche',
            'period_last_7_days' => 'Letzte 7 Tage',
            'cat_product' => 'Produkte',
            'cat_sale' => 'Verkäufe',
            'cat_expense' => 'Ausgaben',
            'cat_customer' => 'Kunden',
            'cat_order' => 'Bestellungen und Zahlungen',
            'cat_advert' => 'Anzeigen',
            'cat_account' => 'Konto und Anmeldung',
            'cat_ai' => 'KI-Nutzung',
            'cat_other' => 'Sonstiges',
            'actor_unknown' => 'Teammitglied',
            'n_period' => 'Zeitraum: {period} ({from} bis {to}).',
            'n_events' => '{events} Ereignisse wurden von {actors} Mitgliedern erfasst.',
            'n_top_category' => 'Die meisten betrafen {category}: {count} Ereignisse.',
            'n_failed' => '{failed} Ereignisse sind fehlgeschlagen.',
            'n_compare_up' => 'Im vorherigen Zeitraum gab es {previous} Ereignisse; jetzt sind es {change} mehr.',
            'n_compare_down' => 'Im vorherigen Zeitraum gab es {previous} Ereignisse; jetzt sind es {change} weniger.',
            'n_compare_flat' => 'Die Aktivität entspricht dem vorherigen Zeitraum ({previous} Ereignisse).',
            'n_compare_from_zero' => 'Im vorherigen Zeitraum wurden keine Ereignisse erfasst.',
            'n_no_events' => 'In diesem Zeitraum wurden keine Ereignisse erfasst.',
            'n_silent' => '{silent} Mitglieder haben in diesem Zeitraum keine erfassten Ereignisse.',
            'lim_counts_not_performance' => 'Diese Zahlen sind erfasste Ereignisse, keine Bewertung der Mitarbeiterleistung.',
            'lim_partial_window' => 'Der laufende Zeitraum ist noch nicht beendet, die Zahlen können noch steigen.',
            'lim_log_coverage' => 'Die Vollständigkeit hängt davon ab, was das System erfasst hat; nicht protokollierte Arbeit ist hier unsichtbar.',
            'lim_no_inference' => 'Diese Aufzeichnungen zeigen nur Ereignisse - sie belegen weder Absicht noch Fehlverhalten noch Arbeitsqualität.',
            'lim_no_events' => 'Für diesen Zeitraum gibt es keine Aktivitätsaufzeichnungen, daher ist keine Zusammenfassung möglich.',
            'lim_truncated' => 'Unvollständig: mehr als {max} Ereignisse wurden nicht ausgewertet.',
            'src_activity_log' => 'Das System-Aktivitätsprotokoll (user_logs) der Mitglieder dieses Betriebs.',
            'err_period' => 'Ungültiger Zeitraum. Verwenden Sie: today, yesterday, this_week, last_week oder last_7_days.',
        ],
        'zh' => [
            'period_today' => '今天',
            'period_yesterday' => '昨天',
            'period_this_week' => '本周',
            'period_last_week' => '上周',
            'period_last_7_days' => '最近 7 天',
            'cat_product' => '商品',
            'cat_sale' => '销售',
            'cat_expense' => '支出',
            'cat_customer' => '客户',
            'cat_order' => '订单与付款',
            'cat_advert' => '广告',
            'cat_account' => '账户与登录',
            'cat_ai' => 'AI 使用',
            'cat_other' => '其他',
            'actor_unknown' => '团队成员',
            'n_period' => '期间：{period}（{from} 至 {to}）。',
            'n_events' => '{actors} 名成员共记录了 {events} 个事件。',
            'n_top_category' => '其中最多的是{category}：{count} 个事件。',
            'n_failed' => '{failed} 个事件失败。',
            'n_compare_up' => '上一期间有 {previous} 个事件；现在多了 {change} 个。',
            'n_compare_down' => '上一期间有 {previous} 个事件；现在少了 {change} 个。',
            'n_compare_flat' => '活动量与上一期间持平（{previous} 个事件）。',
            'n_compare_from_zero' => '上一期间没有记录任何事件。',
            'n_no_events' => '本期间没有记录任何事件。',
            'n_silent' => '{silent} 名成员在本期间没有记录任何事件。',
            'lim_counts_not_performance' => '这些数字是已记录的事件，不是对员工业绩的评估。',
            'lim_partial_window' => '当前期间仍在进行中，因此计数可能还会增加。',
            'lim_log_coverage' => '完整性取决于系统的记录情况；未记录的工作在此不可见。',
            'lim_no_inference' => '这些记录只显示事件 - 不能证明意图、违规或工作质量。',
            'lim_no_events' => '本期间没有活动记录，因此无法生成摘要。',
            'lim_truncated' => '不完整：超过 {max} 个事件未分析。',
            'src_activity_log' => '本商户成员的系统活动日志（user_logs）。',
            'err_period' => '期间无效。请使用：today、yesterday、this_week、last_week 或 last_7_days。',
        ],
    ];

    /** Localized message. Falls back to English and then to the key itself. */
    public static function msg(string $key, string $locale, array $params = []): string
    {
        $table = self::MESSAGES[$locale] ?? self::MESSAGES['en'];
        $text = $table[$key] ?? self::MESSAGES['en'][$key] ?? $key;

        foreach ($params as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /** True when every locale carries every message key (unit-tested). */
    public static function messagesComplete(): bool
    {
        $keys = array_keys(self::MESSAGES['en']);
        sort($keys);
        foreach (self::MESSAGES as $table) {
            $other = array_keys($table);
            sort($other);
            if ($keys !== $other) {
                return false;
            }
        }

        return true;
    }

    /**
     * The placeholders used by a message, sorted - so a test can prove every
     * locale fills the same ones (a translated string that drops {count} would
     * silently print a sentence with no figure).
     *
     * @return array<int, string>
     */
    public static function placeholders(string $key, string $locale): array
    {
        $text = self::MESSAGES[$locale][$key] ?? '';
        preg_match_all('/\{([a-z_]+)\}/', $text, $matches);
        $found = array_values(array_unique($matches[1] ?? []));
        sort($found);

        return $found;
    }

    /* ------------------------------------------------------------------ */
    /* Windows                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Resolve a reporting window and the equal-length window right before it.
     *
     * Windows that include TODAY are flagged `partial`: the period is still
     * running, so a lower count is expected and the UI must say so instead of
     * presenting it as a decline. The previous window is always the same number
     * of days immediately before the current one, which is what makes the two
     * counts comparable.
     *
     * @return array{period: string, current: array{from: string, to: string}, previous: ?array{from: string, to: string}, days: int, partial: bool}
     */
    public static function windows(string $period, string $today): array
    {
        $empty = [
            'period' => '',
            'current' => ['from' => '', 'to' => ''],
            'previous' => null,
            'days' => 0,
            'partial' => false,
        ];

        $period = strtolower(trim($period));
        $today = substr(trim($today), 0, 10);
        $todayTs = strtotime($today);

        if ($todayTs === false || ! in_array($period, self::PERIODS, true)) {
            return $empty;
        }

        $todayDate = date('Y-m-d', $todayTs);

        switch ($period) {
            case 'today':
                $from = $todayDate;
                $to = $todayDate;
                $partial = true;
                break;

            case 'yesterday':
                $from = date('Y-m-d', strtotime('-1 day', $todayTs));
                $to = $from;
                $partial = false;
                break;

            case 'this_week':
                // Monday of the current week through today.
                $from = date('Y-m-d', strtotime('monday this week', $todayTs));
                $to = $todayDate;
                $partial = true;
                break;

            case 'last_week':
                $from = date('Y-m-d', strtotime('monday last week', $todayTs));
                $to = date('Y-m-d', strtotime('sunday last week', $todayTs));
                $partial = false;
                break;

            case 'last_7_days':
            default:
                $from = date('Y-m-d', strtotime('-6 days', $todayTs));
                $to = $todayDate;
                $partial = true;
                break;
        }

        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($fromTs === false || $toTs === false || $fromTs > $toTs) {
            return $empty;
        }

        $days = (int) floor(($toTs - $fromTs) / 86400) + 1;

        return [
            'period' => $period,
            'current' => ['from' => $from, 'to' => $to],
            'previous' => [
                'from' => date('Y-m-d', strtotime('-'.$days.' days', $fromTs)),
                'to' => date('Y-m-d', strtotime('-1 day', $fromTs)),
            ],
            'days' => $days,
            'partial' => $partial,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Classification                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * The activity family of a raw `user_logs.action` value. Deterministic
     * prefix rules only: the same action always lands in the same bucket.
     *
     * A leading `AI_` is stripped BEFORE matching so that an AI-assisted sale
     * commit still counts as a sale entry (the business event), not as generic
     * AI usage; only actions with no business family at all become `ai`.
     */
    public static function categoryFor(?string $action): string
    {
        $action = strtoupper(trim((string) $action));
        if ($action === '') {
            return 'other';
        }

        $isAi = str_starts_with($action, 'AI_');
        $probe = $isAi ? substr($action, 3) : $action;

        foreach (self::FAMILY_PREFIXES as $category => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($probe, $prefix)) {
                    return $category;
                }
            }
        }

        return $isAi ? 'ai' : 'other';
    }

    /** Localized display label for a category key. */
    public static function categoryLabel(string $category, string $locale): string
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            $category = 'other';
        }

        return self::msg('cat_'.$category, $locale);
    }

    /**
     * Record references found in a log row's `details` payload, so a manager can
     * jump to the affected record. Only `*_id`-style keys with a short scalar
     * value are surfaced, person identifiers are skipped, and the list is
     * bounded - the digest must never mirror arbitrary log content.
     *
     * @param  mixed  $details  Raw `details` value (JSON string or array).
     * @return array<int, array{field: string, value: string}>
     */
    public static function extractRefs($details, int $limit = 3): array
    {
        if (is_string($details)) {
            $decoded = json_decode($details, true);
            $details = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($details)) {
            return [];
        }

        $refs = [];
        foreach ($details as $field => $value) {
            if (count($refs) >= max(1, $limit)) {
                break;
            }

            $field = (string) $field;
            if (! preg_match('/_id$/', $field) || in_array($field, self::REF_DENY, true)) {
                continue;
            }
            if (! is_scalar($value) || is_bool($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value === '' || mb_strlen($value) > 64) {
                continue;
            }

            $refs[] = ['field' => $field, 'value' => $value];
        }

        return $refs;
    }

    /* ------------------------------------------------------------------ */
    /* The deterministic core                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Build the grounded digest from real log rows.
     *
     * @param  array{
     *     locale: string,
     *     period: string,
     *     windows: array,
     *     members: array<int, array{id: string, name: string, role: string}>,
     *     logs: array<int, array<string, mixed>>,
     *     previous_logs: array<int, array<string, mixed>>,
     *     noteworthy_limit?: int
     * }  $ctx
     */
    public static function summarize(array $ctx): array
    {
        $locale = (string) ($ctx['locale'] ?? 'sw');
        $period = (string) ($ctx['period'] ?? '');
        $windows = (array) ($ctx['windows'] ?? []);
        $logs = array_values((array) ($ctx['logs'] ?? []));
        $previousLogs = array_values((array) ($ctx['previous_logs'] ?? []));
        $noteworthyLimit = max(3, min(30, (int) ($ctx['noteworthy_limit'] ?? 12)));

        /* Actor directory: id -> display label. Never an email or phone. */
        $actorLabels = [];
        $actorRoles = [];
        foreach ((array) ($ctx['members'] ?? []) as $member) {
            $id = (string) ($member['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $name = trim((string) ($member['name'] ?? ''));
            $actorLabels[$id] = $name !== '' ? $name : self::msg('actor_unknown', $locale);
            $actorRoles[$id] = (string) ($member['role'] ?? '');
        }

        $current = (array) ($windows['current'] ?? ['from' => '', 'to' => '']);
        $currentFrom = (string) ($current['from'] ?? '');
        $currentTo = (string) ($current['to'] ?? '');

        /* ---- counts ---------------------------------------------------- */
        $categoryCounts = [];
        $categoryFailed = [];
        $memberCounts = [];
        $memberLast = [];
        $dayCounts = [];
        $success = 0;
        $failed = 0;
        $candidates = [];

        foreach ($logs as $row) {
            $action = (string) ($row['action'] ?? '');
            $category = self::categoryFor($action);
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            $isSuccess = $status === 'success';
            $at = (string) ($row['created_at'] ?? '');
            $actorId = (string) ($row['user_id'] ?? '');

            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            if ($isSuccess) {
                $success++;
            } else {
                $failed++;
                $categoryFailed[$category] = ($categoryFailed[$category] ?? 0) + 1;
            }

            if ($actorId !== '') {
                $memberCounts[$actorId] = ($memberCounts[$actorId] ?? 0) + 1;
                if ($at !== '' && (! isset($memberLast[$actorId]) || $at > $memberLast[$actorId])) {
                    $memberLast[$actorId] = $at;
                }
            }

            $date = substr($at, 0, 10);
            if ($date !== '') {
                $dayCounts[$date] = ($dayCounts[$date] ?? 0) + 1;
            }

            /* Noteworthy candidates: failed attempts and record creation. */
            if (! $isSuccess || self::isCreation($action)) {
                $candidates[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'action' => $action,
                    'category' => $category,
                    'status' => $status !== '' ? $status : 'unknown',
                    'actor_id' => $actorId,
                    'actor_name' => $actorLabels[$actorId] ?? self::msg('actor_unknown', $locale),
                    'at' => $at,
                    'endpoint' => (string) ($row['endpoint'] ?? ''),
                    'refs' => self::extractRefs($row['details'] ?? null),
                ];
            }
        }

        $events = count($logs);
        $previousEvents = count($previousLogs);
        $activeMembers = count($memberCounts);

        /* Categories, biggest first, zero-count families omitted. */
        $categories = [];
        foreach ($categoryCounts as $key => $count) {
            $categories[] = [
                'key' => $key,
                'label' => self::categoryLabel($key, $locale),
                'count' => $count,
                'failed' => $categoryFailed[$key] ?? 0,
            ];
        }
        usort($categories, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcmp($a['key'], $b['key']);
        });

        /* Per-member logged activity - a factual count, deliberately not ranked
           with any superlative language, and limited to real members. */
        $members = [];
        foreach ($actorLabels as $id => $label) {
            $members[] = [
                'id' => $id,
                'name' => $label,
                'role' => $actorRoles[$id] ?? '',
                'count' => $memberCounts[$id] ?? 0,
                'last_at' => $memberLast[$id] ?? null,
            ];
        }
        usort($members, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']);
        });

        /* Missing logging coverage, made visible instead of averaged away. */
        $silent = [];
        foreach ($members as $member) {
            if (($member['count'] ?? 0) === 0) {
                $silent[] = $member['name'];
            }
        }

        /* One bucket per day of the current window, so gaps show as zeros. */
        $daySeries = [];
        if ($currentFrom !== '' && $currentTo !== '') {
            $cursor = strtotime($currentFrom);
            $end = strtotime($currentTo);
            $guard = 0;
            while ($cursor !== false && $end !== false && $cursor <= $end && $guard < 400) {
                $date = date('Y-m-d', $cursor);
                $daySeries[] = ['date' => $date, 'count' => $dayCounts[$date] ?? 0];
                $cursor = strtotime('+1 day', $cursor);
                $guard++;
            }
        }

        /* Noteworthy: newest first, deduplicated by row id, bounded. */
        $seen = [];
        $noteworthy = [];
        usort($candidates, static function (array $a, array $b): int {
            return strcmp((string) $b['at'], (string) $a['at']);
        });
        foreach ($candidates as $candidate) {
            $key = $candidate['id'] !== '' ? $candidate['id'] : md5($candidate['at'].'|'.$candidate['action'].'|'.$candidate['actor_id']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $noteworthy[] = [
                'id' => $candidate['id'],
                'action' => $candidate['action'],
                'category' => $candidate['category'],
                'category_label' => self::categoryLabel($candidate['category'], $locale),
                'status' => $candidate['status'],
                'actor_id' => $candidate['actor_id'],
                'actor_name' => $candidate['actor_name'],
                'at' => $candidate['at'],
                'endpoint' => $candidate['endpoint'],
                'refs' => $candidate['refs'],
            ];
            if (count($noteworthy) >= $noteworthyLimit) {
                break;
            }
        }

        /* Comparison against the equal-length previous window. */
        if ($previousEvents === 0 && $events > 0) {
            $direction = 'up_from_zero';
            $change = $events;
        } elseif ($events > $previousEvents) {
            $direction = 'up';
            $change = $events - $previousEvents;
        } elseif ($events < $previousEvents) {
            $direction = 'down';
            $change = $previousEvents - $events;
        } else {
            $direction = 'flat';
            $change = 0;
        }

        $periodLabel = $period !== '' ? self::msg('period_'.$period, $locale) : '';

        /* ---- narrative: deterministic, figure-complete ------------------ */
        $narrative = [];
        $narrative[] = self::msg('n_period', $locale, [
            'period' => $periodLabel,
            'from' => $currentFrom,
            'to' => $currentTo,
        ]);

        if ($events === 0) {
            $narrative[] = self::msg('n_no_events', $locale);
        } else {
            $narrative[] = self::msg('n_events', $locale, ['events' => $events, 'actors' => $activeMembers]);
            /* Only name a leading family when it genuinely leads: on a tie the
               sentence would imply a dominance the rows do not show, so the
               tied list is left to the categories array instead. */
            if ($categories !== [] && (count($categories) === 1 || $categories[0]['count'] > $categories[1]['count'])) {
                $narrative[] = self::msg('n_top_category', $locale, [
                    'category' => $categories[0]['label'],
                    'count' => $categories[0]['count'],
                ]);
            }
            if ($failed > 0) {
                $narrative[] = self::msg('n_failed', $locale, ['failed' => $failed]);
            }
            $narrative[] = match ($direction) {
                'up_from_zero' => self::msg('n_compare_from_zero', $locale),
                'up' => self::msg('n_compare_up', $locale, ['previous' => $previousEvents, 'change' => $change]),
                'down' => self::msg('n_compare_down', $locale, ['previous' => $previousEvents, 'change' => $change]),
                default => self::msg('n_compare_flat', $locale, ['previous' => $previousEvents]),
            };
            if ($silent !== []) {
                $narrative[] = self::msg('n_silent', $locale, ['silent' => count($silent)]);
            }
        }

        /* ---- limitations: always honest about what this can and cannot say */
        $limitations = [
            self::msg('lim_counts_not_performance', $locale),
            self::msg('lim_no_inference', $locale),
            self::msg('lim_log_coverage', $locale),
        ];
        if ($events === 0) {
            $limitations[] = self::msg('lim_no_events', $locale);
        }
        if ((bool) ($windows['partial'] ?? false)) {
            $limitations[] = self::msg('lim_partial_window', $locale);
        }

        return [
            'period' => $period,
            'period_label' => $periodLabel,
            'windows' => [
                'current' => $current,
                'previous' => $windows['previous'] ?? null,
                'days' => (int) ($windows['days'] ?? 0),
                'partial' => (bool) ($windows['partial'] ?? false),
            ],
            'totals' => [
                'events' => $events,
                'success' => $success,
                'failed' => $failed,
                'actors' => $activeMembers,
                'members' => count($actorLabels),
            ],
            'comparison' => [
                'direction' => $direction,
                'previous_events' => $previousEvents,
                'change' => $change,
            ],
            'categories' => $categories,
            'members' => $members,
            'silent_members' => $silent,
            'day_series' => $daySeries,
            'noteworthy' => $noteworthy,
            'narrative' => $narrative,
            'limitations' => $limitations,
            'sources' => [self::msg('src_activity_log', $locale)],
        ];
    }

    /** True when an action creates or commits a business record. */
    public static function isCreation(?string $action): bool
    {
        $action = strtoupper(trim((string) $action));

        foreach (self::CREATION_SUFFIXES as $suffix) {
            if (str_ends_with($action, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
