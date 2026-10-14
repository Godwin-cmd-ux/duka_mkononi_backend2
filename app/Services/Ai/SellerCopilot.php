<?php

namespace App\Services\Ai;

use App\Models\AiCopilotConversation;
use App\Models\UserLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Capability #10 - Seller Copilot Q&A.
 *
 * A persistent, business-scoped assistant. Every answer is produced by running
 * one of a fixed set of READ-ONLY tools over the authenticated business's own
 * rows:
 *
 *  - report ......... ReportRunner (capability #4's exact computation)
 *  - restock ........ RestockPlanner over BusinessDataProvider (capability #3)
 *  - health ......... HealthDigestService::build without storing (capability #5)
 *  - anomalies ...... AnomalyDetector plus the recorded dismissals (capability #6)
 *  - expense_parse . ExpenseParser preview - NEVER saved (capability #7)
 *  - help ........... capability overview and quick links
 *
 * The model is used (a) as a fallback router into this same allow-list and
 * (b) to narrate the already-computed result. A grounding check rejects any
 * narration that mentions a money-scale number the tool output does not
 * contain, so an ungrounded answer degrades to the trusted deterministic
 * summary instead of reaching the seller.
 *
 * Conversation history is bounded (store_messages / history_turns) and scoped
 * by business_id + user_id on every read and write. When the conversation
 * table is unavailable the context falls back to cache and the response says
 * so; nothing here ever writes a business record - the expense tool only
 * previews and links the seller to the existing confirmation workflow.
 */
class SellerCopilot
{
    public const CAPABILITY = 'seller_copilot';

    /** The fixed tool allow-list. The model can never widen it. */
    public const TOOLS = ['report', 'restock', 'health', 'anomalies', 'expense_parse', 'help'];

    public const DEFAULT_STORE_MESSAGES = 40;

    public const DEFAULT_HISTORY_TURNS = 3;

    /* ------------------------------------------------------------------ */
    /* Deterministic routing keywords (normalised: lowercase, no punctuation) */
    /* ------------------------------------------------------------------ */

    /** Openers and "what can you do" questions -> help. */
    private const HELP_KEYWORDS = [
        'msaada', 'habari', 'habari yako', 'niaje', 'salamu', 'nini unajua', 'unafanya nini',
        'help', 'hello', 'good morning', 'good afternoon', 'what can you do', 'who are you',
        'bonjour', 'salut', 'aide', 'que peux tu faire',
        'namaste', 'नमस्ते', 'मदद', 'हैलो', 'आप क्या कर सकते हैं',
        'hola', 'buenos días', 'ayuda', 'qué puedes hacer',
        'السلام علیکم', 'مدد', 'ہیلو', 'آپ کیا کر سکتے ہیں',
        'hallo', 'guten tag', 'hilfe', 'was kannst du',
        '你好', '帮助', '早上好', '你能做什么',
    ];

    /** Capability #5 - business health questions. */
    private const HEALTH_KEYWORDS = [
        'afya ya biashara', 'alama ya biashara', 'biashara yangu ikoje', 'ikoje biashara',
        'health score', 'business health', 'how is my business', 'how is my shop',
        'score de santé', 'santé de l entreprise', 'comment va ma boutique',
        'व्यापार स्वास्थ्य', 'बिजनेस हेल्थ', 'मेरा व्यापार कैसा चल रहा है',
        'salud del negocio', 'puntuación de salud', 'cómo va mi negocio',
        'کاروبار کی صحت', 'صحت اسکور', 'میرا کاروبار کیسے چل رہا ہے',
        'geschäftsgesundheit', 'gesundheitswert', 'wie läuft mein geschäft',
        '健康分数', '生意健康', '我的生意怎么样',
    ];

    /** Capability #6 - anomaly / leak questions. */
    private const ANOMALY_KEYWORDS = [
        'dalili', 'uchunguzi', 'shaka', 'kutokwa', 'wizi', 'kwa nini faida', 'kwa nini gharama',
        'anomaly', 'anomalies', 'suspicious', 'unusual', 'fraud', 'theft',
        'why did my profit drop', 'why is profit down', 'signs of loss',
        'anomalie', 'suspect', 'vol', 'fraude', 'pourquoi mon profit a baissé',
        'अनियमितता', 'संदिग्ध', 'चोरी', 'धोखाधड़ी',
        'anomalía', 'sospechoso', 'robo', 'fraude',
        'غیر معمول', 'مشکوک', 'چوری', 'دھوکہ دہی',
        'anomalie de', 'auffällig', 'verdächtig', 'diebstahl', 'betrug',
        '异常', '可疑', '盗窃', '欺诈',
    ];

    /** Capability #3 - restock questions. */
    private const RESTOCK_KEYWORDS = [
        'kuagiza', 'kuagizwa', 'kuisha stock', 'stock itaisha', 'zilizoisha', 'imeisha stock',
        'restock', 'reorder', 're order', 'run out', 'runs out', 'running out', 'stock out',
        'order stock', 'when to order', 'need to order',
        'réappro', 'reappro', 'rupture de stock', 'manquer de stock',
        'पुनः ऑर्डर', 'स्टॉक खत्म', 'कब ऑर्डर', 'स्टॉक भरना',
        'reponer', 'reabastecer', 'quedarse sin', 'cuándo pedir',
        'دوبارہ آرڈر', 'اسٹاک ختم', 'کب آرڈر',
        'nachbestellen', 'nachbestellung', 'bestand ausgehen', 'wann bestellen',
        '补货', '进货', '什么时候订', '库存用完',
    ];

    /**
     * Capability #7 - expense *statements* only. route() additionally refuses
     * any question containing "?", so "Nilitumia kiasi gani kwenye usafiri?"
     * stays a report while "Nilitumia 5000 kwenye usafiri." becomes a draft.
     */
    private const EXPENSE_KEYWORDS = [
        'hifadhi matumizi', 'sajili matumizi', 'rekodi matumizi', 'nililipa', 'nimekulipa',
        'nilitumia', 'nilitoa', 'nauli ya',
        'record expense', 'record expenses', 'add expense', 'add expenses', 'save expense', 'save expenses', 'log expense', 'log expenses', 'i paid', 'we paid', 'my expense is',
        'spent', 'spend',
        'enregistrer dépense', 'noter dépense', "j ai payé",
        'खर्च दर्ज', 'मैंने भुगतान', 'खर्च किया', 'भुगतान किया',
        'registrar gasto', 'añadir gasto', 'pagué', 'gasté',
        'خرچ درج', 'میں نے ادا کیا', 'خرچ کیا',
        'ausgabe eintragen', 'ausgabe speichern', 'ich habe bezahlt',
        '记录支出', '添加支出', '我支付了', '花了',
    ];

    /** "Show it again / refresh" -> repeat the previous tool. */
    private const REFRESH_KEYWORDS = [
        'tena', 'onyesha tena', 'sasisha',
        'again', 'refresh', 'update', 'show me more',
        'encore', 'actualiser',
        'फिर से', 'रीफ्रेश',
        'otra vez', 'actualizar',
        'دوبارہ', 'ریفریش',
        'nochmal', 'aktualisieren',
        '再来', '刷新',
    ];

    /* ------------------------------------------------------------------ */
    /* Server-side messages (all eight locales; params use {name} placeholders) */
    /* ------------------------------------------------------------------ */

    private const MESSAGES = [
        'sw' => [
            'help_intro' => 'Ni msaidizi wako wa biashara. Ninajibu maswali kuhusu mauzo, faida, stock, matumizi na afya ya biashara yako kwa kutumia data halisi ya duka lako.',
            'help_report' => 'Mauzo na faida: "Niliuza wiki hii?", "Bidhaa zipi zinafaida zaidi mwezi huu?"',
            'help_restock' => 'Kuagiza stock: "Ni lini stock itaisha?", "Nihitaji kuagiza nini?"',
            'help_health' => 'Afya ya biashara: "Afya ya biashara yangu ikoje?"',
            'help_anomalies' => 'Uchunguzi: "Kuna dalili za kutokwa kwenye faida?", "Gharama zimepanda gani?"',
            'help_expense' => 'Kuhifadhi matumizi: "Nilitumia 5000 kwa nauli ya usafiri." (wasiwishi, si swali)',
            'fallback_restock' => 'Bidhaa {products} zinahitaji kuagiza (jumla ya unit {units}); {out} zimeisha stock. Angalia mpango kamili kwenye ukurasa wa Mpango wa Kuagiza.',
            'restock_none' => 'Hakuna bidhaa inayohitaji kuagizwa kwa sasa.',
            'expense_note' => 'Nimeandaa risiti: {description} — {amount} {currency}, tarehe {date}. Hakuna kilichohifadhiwa bado; thibitisha kwenye ukurasa wa Matumizi ili kuhifadhi.',
            'not_understood' => 'Sikuweza kuelewa swali. Jaribu: {examples}',
            'ungrounded' => 'Jibu la AI lilikuwa na namba ambazo hazipo kwenye data yako, kwa hivyo nimetumia muhtasari wa kuaminika badala yake.',
            'history_not_persisted' => 'Historia ya mazungumzo imehifadhiwa muda mfupi kwenye cache kwa sababu sanduku la kihistoria halipatikani kwa sasa.',
            'tool_report' => 'Ripoti',
            'tool_restock' => 'Kuagiza stock',
            'tool_health' => 'Afya ya biashara',
            'tool_anomalies' => 'Dalili za uchunguzi',
            'tool_expense' => 'Risiti ya matumizi',
            'tool_help' => 'Msaada',
            'hl_restock' => 'Zinahitaji kuagiza',
            'hl_out' => 'Zimeisha stock',
            'hl_units' => 'Unit zinazopendekezwa',
            'hl_detected' => 'Zilizopimwa',
            'hl_open' => 'Wazi kwa kuchunguza',
            'hl_score' => 'Alama ya afya',
            'hl_coverage' => 'Uwiano wa data',
            'qa_sales' => 'Mauzo',
            'qa_expenses' => 'Matumizi',
            'qa_restock' => 'Mpango wa Kuagiza',
            'qa_health' => 'Afya ya Biashara',
            'qa_ask' => 'Uliza Biashara',
            'qa_hub' => 'Huduma Nyingine',
        ],
        'en' => [
            'help_intro' => "I am your business assistant. I answer questions about sales, profit, stock, expenses and business health using your shop's real data.",
            'help_report' => 'Sales and profit: "How did I sell this week?", "Which products are most profitable this month?"',
            'help_restock' => 'Restocking: "When will I run out of stock?", "What should I reorder?"',
            'help_health' => 'Business health: "How is my business doing?"',
            'help_anomalies' => 'Review signals: "Any signs of unusual losses?", "What expenses jumped?"',
            'help_expense' => 'Recording expenses: "I paid 5,000 for transport." (a statement, not a question)',
            'fallback_restock' => '{products} products need re-ordering ({units} units in total); {out} are out of stock. See the full plan on the Restock page.',
            'restock_none' => 'No product needs re-ordering right now.',
            'expense_note' => 'I prepared an expense: {description} — {amount} {currency}, dated {date}. Nothing is saved yet; confirm it on the Expenses page to record it.',
            'not_understood' => 'I could not understand the question. Try: {examples}',
            'ungrounded' => 'The AI answer contained numbers that are not in your data, so I used the trusted summary instead.',
            'history_not_persisted' => 'Conversation history is being kept temporarily in cache because the conversation store is unavailable right now.',
            'tool_report' => 'Report',
            'tool_restock' => 'Restocking',
            'tool_health' => 'Business health',
            'tool_anomalies' => 'Review signals',
            'tool_expense' => 'Expense draft',
            'tool_help' => 'Help',
            'hl_restock' => 'Need re-ordering',
            'hl_out' => 'Out of stock',
            'hl_units' => 'Proposed units',
            'hl_detected' => 'Detected',
            'hl_open' => 'Open for review',
            'hl_score' => 'Health score',
            'hl_coverage' => 'Data coverage',
            'qa_sales' => 'Sales',
            'qa_expenses' => 'Expenses',
            'qa_restock' => 'Restock plan',
            'qa_health' => 'Business health',
            'qa_ask' => 'Ask about sales',
            'qa_hub' => 'Other services',
        ],
        'fr' => [
            'help_intro' => 'Je suis votre assistant commercial. Je réponds aux questions sur les ventes, le profit, le stock, les dépenses et la santé de votre boutique avec vos données réelles.',
            'help_report' => 'Ventes et profit : « Combien ai-je vendu cette semaine ? », « Quels produits sont les plus rentables ? »',
            'help_restock' => 'Réapprovisionnement : « Quand vais-je manquer de stock ? », ' . '"Que dois-je commander ?"',
            'help_health' => 'Santé de l’entreprise : « Comment va ma boutique ? »',
            'help_anomalies' => 'Signaux à vérifier : « Y a-t-il des pertes inhabituelles ? », « Quelles dépenses ont augmenté ? »',
            'help_expense' => 'Dépenses : « J’ai payé 5 000 de transport. » (une affirmation, pas une question)',
            'fallback_restock' => '{products} produits à recommander ({units} unités au total) ; {out} sont en rupture. Voir le plan complet sur la page Réappro.',
            'restock_none' => 'Aucun produit à recommander pour le moment.',
            'expense_note' => 'J’ai préparé une dépense : {description} — {amount} {currency}, le {date}. Rien n’est encore enregistré ; confirmez sur la page Dépenses.',
            'not_understood' => 'Je n’ai pas compris la question. Essayez : {examples}',
            'ungrounded' => 'La réponse de l’IA contenait des chiffres absents de vos données, donc j’ai utilisé le résumé fiable à la place.',
            'history_not_persisted' => 'L’historique des conversations est conservé temporairement car le stockage n’est pas disponible pour le moment.',
            'tool_report' => 'Rapport',
            'tool_restock' => 'Réapprovisionnement',
            'tool_health' => 'Santé de l’entreprise',
            'tool_anomalies' => 'Signaux à vérifier',
            'tool_expense' => 'Brouillon de dépense',
            'tool_help' => 'Aide',
            'hl_restock' => 'À recommander',
            'hl_out' => 'Rupture de stock',
            'hl_units' => 'Unités proposées',
            'hl_detected' => 'Détectées',
            'hl_open' => 'À examiner',
            'hl_score' => 'Score de santé',
            'hl_coverage' => 'Couverture des données',
            'qa_sales' => 'Ventes',
            'qa_expenses' => 'Dépenses',
            'qa_restock' => 'Plan de réappro',
            'qa_health' => 'Santé de l’entreprise',
            'qa_ask' => 'Poser une question',
            'qa_hub' => 'Autres services',
        ],
        'hi' => [
            'help_intro' => 'मैं आपका व्यापार सहायक हूँ। आपकी दुकान के असली डेटा से बिक्री, लाभ, स्टॉक, खर्च और व्यापार स्वास्थ्य के सवालों का जवाब देता हूँ।',
            'help_report' => 'बिक्री और लाभ: "इस हफ्ते कितना बेचा?", "कौन से उत्पाद सबसे लाभदायक हैं?"',
            'help_restock' => 'स्टॉक भरना: "स्टॉक कब खत्म होगा?", "क्या फिर से मँगाना है?"',
            'help_health' => 'व्यापार स्वास्थ्य: "मेरा व्यापार कैसा चल रहा है?"',
            'help_anomalies' => 'जाँच संकेत: "असामान्य नुकसान के संकेत हैं?", "कौन से खर्च बढ़े?"',
            'help_expense' => 'खर्च दर्ज करना: "मैंने यात्रा पर 5000 चुकाए।" (वाक्य, सवाल नहीं)',
            'fallback_restock' => '{products} उत्पादों को फिर से मँगाना है (कुल {units} यूनिट); {out} का स्टॉक खत्म है। पूरी योजना रीस्टॉक पेज पर देखें।',
            'restock_none' => 'अभी किसी उत्पाद को फिर से मँगाने की ज़रूरत नहीं है।',
            'expense_note' => 'मैंने खर्च तैयार किया: {description} — {amount} {currency}, तारीख {date}। अभी कुछ सेव नहीं हुआ; दर्ज करने के लिए खर्च पेज पर पुष्टि करें।',
            'not_understood' => 'मैं सवाल नहीं समझा। आज़माएँ: {examples}',
            'ungrounded' => 'AI के जवाब में ऐसी संख्याएँ थीं जो आपके डेटा में नहीं हैं, इसलिए मैंने भरोसेमंद सारांश इस्तेमाल किया।',
            'history_not_persisted' => 'बातचीत का इतिहास अस्थायी रूप से कैश में रखा जा रहा है क्योंकि संवाद स्टोर अभी उपलब्ध नहीं है।',
            'tool_report' => 'रिपोर्ट',
            'tool_restock' => 'रीस्टॉक',
            'tool_health' => 'व्यापार स्वास्थ्य',
            'tool_anomalies' => 'जाँच संकेत',
            'tool_expense' => 'खर्च मसौदा',
            'tool_help' => 'मदद',
            'hl_restock' => 'फिर से मँगाने हैं',
            'hl_out' => 'स्टॉक खत्म',
            'hl_units' => 'प्रस्तावित यूनिट',
            'hl_detected' => 'पाई गईं',
            'hl_open' => 'समीक्षा बाकी',
            'hl_score' => 'स्वास्थ्य अंक',
            'hl_coverage' => 'डेटा कवरेज',
            'qa_sales' => 'बिक्री',
            'qa_expenses' => 'खर्च',
            'qa_restock' => 'रीस्टॉक योजना',
            'qa_health' => 'व्यापार स्वास्थ्य',
            'qa_ask' => 'बिक्री पूछें',
            'qa_hub' => 'अन्य सेवाएँ',
        ],
        'es' => [
            'help_intro' => 'Soy tu asistente de negocios. Respondo preguntas sobre ventas, beneficios, stock, gastos y la salud de tu tienda con tus datos reales.',
            'help_report' => 'Ventas y beneficio: "¿Cuánto vendí esta semana?", "¿Qué productos son más rentables?"',
            'help_restock' => 'Reposición: "¿Cuándo me quedaré sin stock?", "¿Qué debo pedir?"',
            'help_health' => 'Salud del negocio: "¿Cómo va mi negocio?"',
            'help_anomalies' => 'Señales a revisar: "¿Hay pérdidas inusuales?", "¿Qué gastos subieron?"',
            'help_expense' => 'Registrar gastos: "Pagué 5.000 de transporte." (afirmación, no pregunta)',
            'fallback_restock' => '{products} productos necesitan reposición ({units} unidades en total); {out} están sin stock. Ver el plan completo en la página de Reposición.',
            'restock_none' => 'Ningún producto necesita reposición ahora mismo.',
            'expense_note' => 'Preparé un gasto: {description} — {amount} {currency}, fecha {date}. Nada se ha guardado aún; confírmalo en la página de Gastos para registrarlo.',
            'not_understood' => 'No entendí la pregunta. Prueba: {examples}',
            'ungrounded' => 'La respuesta de la IA contenía cifras que no están en tus datos, así que usé el resumen fiable.',
            'history_not_persisted' => 'El historial de conversaciones se guarda temporalmente en caché porque el almacén no está disponible ahora.',
            'tool_report' => 'Informe',
            'tool_restock' => 'Reposición',
            'tool_health' => 'Salud del negocio',
            'tool_anomalies' => 'Señales a revisar',
            'tool_expense' => 'Borrador de gasto',
            'tool_help' => 'Ayuda',
            'hl_restock' => 'Necesitan reposición',
            'hl_out' => 'Sin stock',
            'hl_units' => 'Unidades propuestas',
            'hl_detected' => 'Detectadas',
            'hl_open' => 'Pendientes de revisión',
            'hl_score' => 'Puntuación de salud',
            'hl_coverage' => 'Cobertura de datos',
            'qa_sales' => 'Ventas',
            'qa_expenses' => 'Gastos',
            'qa_restock' => 'Plan de reposición',
            'qa_health' => 'Salud del negocio',
            'qa_ask' => 'Preguntar por ventas',
            'qa_hub' => 'Otros servicios',
        ],
        'ur' => [
            'help_intro' => 'میں آپ کا کاروباری معاون ہوں۔ آپ کی دکان کے اصل ڈیٹا سے فروخت، منافع، اسٹاک، اخراجات اور کاروبار کی صحت کے سوالوں کا جواب دیتا ہوں۔',
            'help_report' => 'فرختہ اور منافع: "اس ہفتے کتنا بیچا؟", "کون سے پروڈکٹ سب سے منافع بخش ہیں؟"',
            'help_restock' => 'اسٹاک بھرنا: "اسٹاک کب ختم ہوگا؟", "کیا دوبارہ منگوانا ہے؟"',
            'help_health' => 'کاروباری صحت: "میرا کاروبار کیسے چل رہا ہے؟"',
            'help_anomalies' => 'جائزہ نشانیات: "غیر معمولی نقصان کی نشانیاں ہیں؟", "کون سے اخراجات بڑھے؟"',
            'help_expense' => 'اخراجات درج کرنا: "میں نے سفر پر 5000 ادا کیے۔" (جملہ، سوال نہیں)',
            'fallback_restock' => '{products} پروڈکٹس کو دوبارہ منگوانا ہے (کل {units} یونٹس); {out} کا اسٹاک ختم ہے۔ مکمل منصوبہ ریسٹاک صفحے پر دیکھیں۔',
            'restock_none' => 'ابھی کسی پروڈکٹ کو دوبارہ منگوانے کی ضرورت نہیں ہے۔',
            'expense_note' => 'میں نے خرچ تیار کیا: {description} — {amount} {currency}, تاریخ {date}۔ ابھی کچھ محفوظ نہیں ہوا؛ درج کرنے کے لیے اخراجات صفحے پر تصدیق کریں۔',
            'not_understood' => 'میں سوال نہیں سمجھا۔ آزمائیں: {examples}',
            'ungrounded' => 'AI کے جواب میں ایسے اعداد تھے جو آپ کے ڈیٹا میں نہیں، اس لیے میں نے قابل اعتماد خلاصہ استعمال کیا۔',
            'history_not_persisted' => 'گفتگو کی تاریخ عارضی طور پر کیش میں رکھی جا رہی ہے کیونکہ گفتگو اسٹور دستیاب نہیں ہے۔',
            'tool_report' => 'رپورٹ',
            'tool_restock' => 'اسٹاک بھرنا',
            'tool_health' => 'کاروباری صحت',
            'tool_anomalies' => 'جائزہ نشانیات',
            'tool_expense' => 'خرچ کا خاکہ',
            'tool_help' => 'مدد',
            'hl_restock' => 'دوبارہ منگوانے ہیں',
            'hl_out' => 'اسٹاک ختم',
            'hl_units' => 'پیش کردہ یونٹس',
            'hl_detected' => 'ملیں',
            'hl_open' => 'جائزہ باقی',
            'hl_score' => 'صحت اسکور',
            'hl_coverage' => 'ڈیٹا کی پوشش',
            'qa_sales' => 'فرختہ',
            'qa_expenses' => 'اخراجات',
            'qa_restock' => 'اسٹاک منصوبہ',
            'qa_health' => 'کاروباری صحت',
            'qa_ask' => 'سوال پوچھیں',
            'qa_hub' => 'دیگر خدمات',
        ],
        'de' => [
            'help_intro' => 'Ich bin Ihr Geschäftsassistent. Ich beantworte Fragen zu Umsatz, Gewinn, Bestand, Ausgaben und der Gesundheit Ihres Geschäfts mit Ihren echten Daten.',
            'help_report' => 'Umsatz und Gewinn: „Wie gut habe ich diese Woche verkauft?", „Welche Produkte sind am rentabelsten?"',
            'help_restock' => 'Nachbestellung: „Wann geht mir der Bestand aus?", „Was muss ich nachbestellen?"',
            'help_health' => 'Geschäftsgesundheit: „Wie läuft mein Geschäft?"',
            'help_anomalies' => 'Prüfsignale: „Gibt es ungewöhnliche Verluste?", „Welche Ausgaben sind gestiegen?"',
            'help_expense' => 'Ausgaben erfassen: „Ich habe 5.000 für Transport bezahlt." (Aussage, keine Frage)',
            'fallback_restock' => '{products} Produkte müssen nachbestellt werden ({units} Stück insgesamt); {out} sind ausverkauft. Ganzen Plan auf der Nachbestellungsseite ansehen.',
            'restock_none' => 'Kein Produkt muss derzeit nachbestellt werden.',
            'expense_note' => 'Ich habe eine Ausgabe vorbereitet: {description} — {amount} {currency}, Datum {date}. Noch nichts gespeichert; auf der Ausgabenseite bestätigen.',
            'not_understood' => 'Ich habe die Frage nicht verstanden. Versuchen Sie: {examples}',
            'ungrounded' => 'Die AI-Antwort enthielt Zahlen, die nicht in Ihren Daten stehen, daher verwende ich die vertrauenswürdige Zusammenfassung.',
            'history_not_persisted' => 'Der Gesprächsverlauf wird vorübergehend im Cache aufbewahrt, weil der Gesprächsspeicher gerade nicht verfügbar ist.',
            'tool_report' => 'Bericht',
            'tool_restock' => 'Nachbestellung',
            'tool_health' => 'Geschäftsgesundheit',
            'tool_anomalies' => 'Prüfsignale',
            'tool_expense' => 'Ausgaben-Entwurf',
            'tool_help' => 'Hilfe',
            'hl_restock' => 'Nachzubestellen',
            'hl_out' => 'Ausverkauft',
            'hl_units' => 'Vorgeschlagene Stück',
            'hl_detected' => 'Erkannt',
            'hl_open' => 'Offen zur Prüfung',
            'hl_score' => 'Gesundheitswert',
            'hl_coverage' => 'Datenabdeckung',
            'qa_sales' => 'Umsatz',
            'qa_expenses' => 'Ausgaben',
            'qa_restock' => 'Nachbestellplan',
            'qa_health' => 'Geschäftsgesundheit',
            'qa_ask' => 'Umsatz fragen',
            'qa_hub' => 'Weitere Dienste',
        ],
        'zh' => [
            'help_intro' => '我是您的生意助手，用您店铺的真实数据回答销售、利润、库存、支出和生意健康的问题。',
            'help_report' => '销售与利润：“这周卖了多少？”“哪些商品最赚钱？”',
            'help_restock' => '补货：“库存什么时候用完？”“我该补什么货？”',
            'help_health' => '生意健康：“我的生意怎么样？”',
            'help_anomalies' => '审查信号：“有异常亏损的迹象吗？”“哪些支出涨了？”',
            'help_expense' => '记录支出：“我付了5000交通费。”（陈述句，不是问题）',
            'fallback_restock' => '{products}种商品需要补货（共{units}件）；{out}种已缺货。完整计划见补货页面。',
            'restock_none' => '目前没有商品需要补货。',
            'expense_note' => '我准备了一笔支出：{description} — {amount} {currency}，日期{date}。尚未保存；请在支出页面确认后记录。',
            'not_understood' => '我没有理解这个问题。请试试：{examples}',
            'ungrounded' => 'AI的回答包含了数据中不存在的数字，因此我改用了可靠的摘要。',
            'history_not_persisted' => '对话历史暂时缓存在本地，因为对话存储当前不可用。',
            'tool_report' => '报告',
            'tool_restock' => '补货',
            'tool_health' => '生意健康',
            'tool_anomalies' => '审查信号',
            'tool_expense' => '支出草稿',
            'tool_help' => '帮助',
            'hl_restock' => '需要补货',
            'hl_out' => '缺货',
            'hl_units' => '建议订货数',
            'hl_detected' => '已检测',
            'hl_open' => '待审查',
            'hl_score' => '健康分数',
            'hl_coverage' => '数据覆盖率',
            'qa_sales' => '销售',
            'qa_expenses' => '支出',
            'qa_restock' => '补货计划',
            'qa_health' => '生意健康',
            'qa_ask' => '询问销售',
            'qa_hub' => '其他服务',
        ],
    ];

    /* ------------------------------------------------------------------ */
    /* Localization                                                        */
    /* ------------------------------------------------------------------ */

    /** Localized message with {param} placeholders. Falls back en -> sw. */
    public static function msg(string $key, string $locale, array $params = []): string
    {
        $table = self::MESSAGES[$locale] ?? self::MESSAGES['en'];
        $text = $table[$key] ?? self::MESSAGES['en'][$key] ?? $key;
        if ($text === $key && $locale !== 'sw') {
            $text = self::MESSAGES['sw'][$key] ?? $key;
        }

        foreach ($params as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /** True when every locale carries every message (unit-tested). */
    public static function messagesComplete(): bool
    {
        foreach (array_keys(self::MESSAGES['en']) as $key) {
            foreach (array_keys(self::MESSAGES) as $locale) {
                if (trim((string) (self::MESSAGES[$locale][$key] ?? '')) === '') {
                    return false;
                }
            }
        }

        return true;
    }

    /** Localized tool label for badges in the clients. */
    public static function toolLabel(string $tool, string $locale): string
    {
        $key = 'tool_'.preg_replace('/[^a-z_]/', '', strtolower($tool));

        return self::msg($key, $locale);
    }

    /* ------------------------------------------------------------------ */
    /* Deterministic routing (pure: no model, no HTTP, no database)        */
    /* ------------------------------------------------------------------ */

    /** Lowercase, strip punctuation (keeping letters incl. CJK), collapse spaces. */
    public static function normalise(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        // Punctuation and symbols become spaces; letters/digits (any script) survive.
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * Word/phrase match on the normalised text. Non-CJK keywords must match
     * whole words (so "hi" never matches "this"); CJK keywords match as
     * substrings because Chinese has no word spaces.
     */
    public static function matchAny(string $normalisedText, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $keyword = self::normalise($keyword);
            if ($keyword === '') {
                continue;
            }

            $hasCjk = preg_match('/[\x{4e00}-\x{9fff}]/u', $keyword) === 1;
            if ($hasCjk) {
                if (mb_strpos($normalisedText, $keyword) !== false) {
                    return true;
                }

                continue;
            }

            $pattern = '/(?:^|\s)'.preg_quote($keyword, '/').'(?:\s|$)/u';
            if (preg_match($pattern, $normalisedText) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map a seller's message to a tool from the allow-list.
     *
     * Order matters: copilot-specific intents are checked before the report
     * parser so "ni lini stock itaisha" never becomes a stock-value report,
     * and expense parsing only accepts statements (never a question).
     *
     * @param  array<int, array<string, mixed>>  $history  stored messages (oldest first)
     * @return array{tool: string, args: array<string, mixed>, source: string, confidence: string}
     */
    public static function route(string $question, array $history): array
    {
        $text = self::normalise($question);
        $isQuestion = mb_strstr($question, '?') !== false;

        if (self::matchAny($text, self::HEALTH_KEYWORDS)) {
            return self::routed('health', [], 'deterministic', 'high');
        }
        if (self::matchAny($text, self::ANOMALY_KEYWORDS)) {
            return self::routed('anomalies', [], 'deterministic', 'high');
        }
        if (self::matchAny($text, self::RESTOCK_KEYWORDS)) {
            return self::routed('restock', [], 'deterministic', 'high');
        }

        // Capability #7 accepts STATEMENTS only: "Nilitumia 5000 kwenye
        // usafiri." is a draft, while "Nilitumia kiasi gani kwenye usafiri?"
        // (a question) must stay a report.
        if (! $isQuestion && self::matchAny($text, self::EXPENSE_KEYWORDS)) {
            return self::routed('expense_parse', [
                'phrase' => mb_substr(trim($question), 0, 600),
            ], 'deterministic', 'high');
        }

        // Greetings and capability questions come after the domain tools, so
        // "help me restock" still routes to the restock planner.
        if (self::matchAny($text, self::HELP_KEYWORDS)) {
            return self::routed('help', [], 'deterministic', 'high');
        }

        $parsed = ReportIntentParser::parse($question);
        if ($parsed['tool'] !== null) {
            return self::routed('report', ['report' => $parsed], 'deterministic', $parsed['confidence']);
        }

        // Follow-up: a new period with no new topic keeps the previous report
        // tool; an explicit refresh repeats the previous tool.
        $previous = self::lastTool($history);
        if ($previous !== null) {
            if ($previous['tool'] === 'report' && $parsed['range'] !== null) {
                if ($parsed['tool'] === null) {
                    $parsed['tool'] = $previous['sub'];
                }
                if ($parsed['tool'] !== null) {
                    return self::routed('report', ['report' => $parsed], 'history', 'medium');
                }
            }

            if (self::matchAny($text, self::REFRESH_KEYWORDS)) {
                $args = [];
                if ($previous['tool'] === 'report' && $previous['sub'] !== null) {
                    $parsed['tool'] = $previous['sub'];
                    $args = ['report' => $parsed];
                } elseif ($previous['tool'] === 'expense_parse') {
                    $args = ['phrase' => mb_substr(trim($question), 0, 600)];
                }

                return self::routed($previous['tool'], $args, 'history', 'low');
            }
        }

        return self::routed('', [], 'none', 'low');
    }

    /** The last tool used in a conversation (from stored assistant messages). */
    public static function lastTool(array $history): ?array
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $entry = $history[$i] ?? null;
            if (! is_array($entry) || ($entry['role'] ?? '') !== 'assistant') {
                continue;
            }
            $tool = (string) ($entry['tool'] ?? '');
            if ($tool !== '' && in_array($tool, self::TOOLS, true)) {
                return ['tool' => $tool, 'sub' => $entry['sub'] ?? null];
            }
        }

        return null;
    }

    /**
     * Optional model fallback router: it may only choose from the same
     * allow-list; every returned value is validated against it.
     */
    public static function classifyWithAi(string $question, ?string $lastTool, string $locale, ?string $userId, ?string $businessId, ?string $ip): array
    {
        $fallback = self::routed('', [], 'none', 'low');

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            return $fallback;
        }

        $instruction = implode("\n", [
            'You route a small-shop owner\'s message to ONE tool.',
            'Return STRICT JSON only: {"tool": string|null, "report_tool": string|null, "metric": string|null, "range_key": string|null}.',
            'Allowed tools: '.implode(', ', self::TOOLS).'.',
            'Allowed report_tool values: '.implode(', ', ReportIntentParser::TOOLS).'.',
            'Allowed metric values: '.implode(', ', ReportIntentParser::METRICS).'.',
            'Allowed range_key values: '.implode(', ', ReportIntentParser::RANGES).'.',
            'Use tool "report" with report_tool for sales/profit/customer/stock-value questions,',
            '"expense_parse" only for a statement that records a new expense,',
            '"help" for greetings or capability questions, and null when unclear.',
            'If the message is a follow-up about a new period, keep report_tool from context: '.$lastTool.'.',
            'Never invent a tool.',
        ]);

        try {
            $result = $client->generateJson(
                [['text' => $question]],
                $instruction,
                [
                    'capability' => self::CAPABILITY.'_router',
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 160,
                ],
            );

            $data = is_array($result->data) ? $result->data : [];

            $tool = is_string($data['tool'] ?? null) ? $data['tool'] : null;
            if ($tool !== null && ! in_array($tool, self::TOOLS, true)) {
                $tool = null;
            }
            if ($tool === 'expense_parse' && mb_strstr($question, '?') !== false) {
                $tool = null;
            }

            if ($tool === null) {
                return $fallback;
            }

            if ($tool === 'report') {
                $reportTool = is_string($data['report_tool'] ?? null) ? $data['report_tool'] : null;
                if ($reportTool === null || ! in_array($reportTool, ReportIntentParser::TOOLS, true)) {
                    return $fallback;
                }

                $parsed = ReportIntentParser::parse($question);
                $parsed['tool'] = $reportTool;

                $metric = is_string($data['metric'] ?? null) ? $data['metric'] : null;
                if ($metric !== null && in_array($metric, ReportIntentParser::METRICS, true)) {
                    $parsed['metric'] = $metric;
                }

                $rangeKey = is_string($data['range_key'] ?? null) ? $data['range_key'] : null;
                if ($rangeKey !== null && in_array($rangeKey, ReportIntentParser::RANGES, true)) {
                    $parsed['range'] = ReportIntentParser::resolveRange($rangeKey, date('Y-m-d'));
                    $parsed['range_key'] = $rangeKey;
                    $parsed['range_source'] = 'ai';
                }

                return self::routed('report', ['report' => $parsed], 'ai', 'medium');
            }

            if ($tool === 'expense_parse') {
                return self::routed('expense_parse', [
                    'phrase' => mb_substr(trim($question), 0, 600),
                ], 'ai', 'medium');
            }

            return self::routed($tool, [], 'ai', 'medium');
        } catch (Throwable $error) {
            return $fallback;
        }
    }

    /**
     * Run one allow-listed tool. Every figure comes from trusted code over the
     * caller's own member ids; no tool here writes a business record.
     *
     * @param  array<string, mixed>  $ctx  member_ids, business_id, user_id, ip, locale, currency, today
     * @return array<string, mixed>
     */
    public static function runTool(string $tool, array $args, array $ctx): array
    {
        $locale = (string) ($ctx['locale'] ?? 'sw');
        $currency = (string) ($ctx['currency'] ?? 'TZS');
        $memberIds = array_values(array_filter((array) ($ctx['member_ids'] ?? [])));

        $base = [
            'ok' => true,
            'tool' => $tool,
            'period' => null,
            'data' => [],
            'table' => null,
            'chart' => null,
            'highlights' => [],
            'sources' => [],
            'limitations' => [],
            'summary_text' => '',
        ];

        switch ($tool) {
            case 'report':
                $parsed = is_array($args['report'] ?? null) ? $args['report'] : ['tool' => null];
                $parsed += [
                    'tool' => null,
                    'metric' => 'revenue',
                    'range' => null,
                    'range_source' => 'none',
                    'compare' => false,
                    'category_filter' => null,
                    'limit' => 10,
                ];

                $run = ReportRunner::run($parsed, $memberIds, $locale, $currency);
                $base['period'] = $run['range'];
                $base['data'] = $run['data'];
                $base['table'] = $run['table'];
                $base['chart'] = $run['chart'];
                $base['sources'] = $run['sources'];
                $base['limitations'] = $run['limitations'];

                if (isset($run['data']['supported_examples'])) {
                    $base['summary_text'] = self::notUnderstood($locale);
                } else {
                    $base['summary_text'] = ReportNarrator::summaryText(
                        $parsed['tool'] ?? 'unknown',
                        $run['data'],
                        $locale,
                        $currency,
                        $run['range'],
                        $run['previous_range'],
                    );
                }

                if ($run['comparison'] !== null) {
                    $base['data']['comparison'] = $run['comparison'];
                }

                return $base;

            case 'restock':
                $defaults = (array) config('ai.restock', []);
                $windowDays = 30;
                $recentDays = max(1, (int) ($defaults['recent_days'] ?? 7));

                $products = BusinessDataProvider::productsForBusiness($memberIds);
                $velocity = BusinessDataProvider::salesVelocityDetailed($memberIds, $windowDays, $recentDays);

                $plan = RestockPlanner::planProducts($products, $velocity, [
                    'currency' => $currency,
                    'window_days' => $windowDays,
                    'recent_days' => $recentDays,
                    'lead_time_days' => (int) ($defaults['lead_time_days'] ?? 7),
                    'safety_days' => (int) ($defaults['safety_days'] ?? 7),
                    'review_days' => (int) ($defaults['review_days'] ?? 7),
                    'overstock_days' => (int) ($defaults['overstock_days'] ?? 60),
                    'min_stock' => 0.0,
                    'fast_moving_daily' => (float) ($defaults['fast_moving_daily'] ?? 1.0),
                    'max_products' => 25,
                ]);

                $summary = $plan['summary'];
                $actionable = array_values(array_filter($plan['products'], function ($product) {
                    return ($product['proposed_quantity'] ?? 0) > 0
                        || in_array($product['status'] ?? '', ['OUT_OF_STOCK', 'LOW_STOCK', 'RESTOCK'], true);
                }));
                $top = array_slice($actionable, 0, 10);

                $base['period'] = ['window_days' => $windowDays];
                $base['data'] = [
                    'summary' => $summary,
                    'products' => array_map(fn ($product) => [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'status' => $product['status'],
                        'stock' => $product['stock'],
                        'days_until_stockout' => $product['days_until_stockout'],
                        'proposed_quantity' => $product['proposed_quantity'],
                        'confidence' => $product['confidence'],
                    ], $top),
                ];
                $base['table'] = $top === [] ? null : [
                    'columns' => ['name', 'stock', 'days', 'proposed'],
                    'rows' => array_map(fn ($product) => [
                        $product['name'],
                        $product['stock'],
                        $product['days_until_stockout'],
                        $product['proposed_quantity'],
                    ], $top),
                ];
                $base['highlights'] = [
                    ['label' => self::msg('hl_restock', $locale), 'value' => (int) ($summary['restock_count'] ?? 0), 'kind' => 'number'],
                    ['label' => self::msg('hl_out', $locale), 'value' => (int) ($summary['out_of_stock_count'] ?? 0), 'kind' => 'number'],
                    ['label' => self::msg('hl_units', $locale), 'value' => (float) ($summary['total_proposed_units'] ?? 0), 'kind' => 'number'],
                ];
                $base['sources'] = ['products', 'sales', 'sale_items'];
                $base['limitations'] = RestockPlanner::limitations($summary);
                $base['summary_text'] = (($summary['restock_count'] ?? 0) === 0 && ($summary['out_of_stock_count'] ?? 0) === 0)
                    ? self::msg('restock_none', $locale)
                    : self::msg('fallback_restock', $locale, [
                        'products' => (int) ($summary['restock_count'] ?? 0),
                        'units' => (int) ($summary['total_proposed_units'] ?? 0),
                        'out' => (int) ($summary['out_of_stock_count'] ?? 0),
                    ]);

                return $base;

            case 'health':
                $businessId = (string) ($ctx['business_id'] ?? '');
                $period = HealthDigestService::periodForWeek();

                $digest = HealthDigestService::build(
                    $memberIds,
                    $businessId,
                    $period['start'],
                    $period['end'],
                    $locale,
                    [
                        'currency' => $currency,
                        'with_ai' => false,
                        'user_id' => $ctx['user_id'] ?? null,
                        'ip' => $ctx['ip'] ?? null,
                    ],
                );

                $componentRows = [];
                foreach ((array) ($digest['components'] ?? []) as $component) {
                    $componentRows[] = [
                        (string) ($component['label'] ?? ($component['key'] ?? '')),
                        $component['score'] ?? null,
                    ];
                }

                $base['period'] = ['from' => $period['start'], 'to' => $period['end']];
                $base['data'] = [
                    'score' => $digest['score'] ?? null,
                    'band' => $digest['band'] ?? null,
                    'band_label' => $digest['band_label'] ?? null,
                    'sufficient' => (bool) ($digest['sufficient'] ?? false),
                    'coverage' => $digest['coverage'] ?? null,
                    'missing_components' => $digest['missing_components'] ?? [],
                    'changes' => $digest['changes'] ?? [],
                    'actions' => array_map(fn ($action) => [
                        'key' => $action['key'] ?? '',
                        'title' => $action['title'] ?? ($action['text'] ?? ''),
                    ], (array) ($digest['actions'] ?? [])),
                    'summary_text' => $digest['summary_text'] ?? '',
                ];
                $base['table'] = $componentRows === [] ? null : [
                    'columns' => ['component', 'score'],
                    'rows' => $componentRows,
                ];
                $base['highlights'] = array_values(array_filter([
                    ($digest['sufficient'] ?? false) && ($digest['score'] ?? null) !== null
                        ? ['label' => self::msg('hl_score', $locale), 'value' => $digest['score'], 'kind' => 'number']
                        : null,
                    ($digest['coverage'] ?? null) !== null
                        ? ['label' => self::msg('hl_coverage', $locale), 'value' => round(((float) $digest['coverage']) * 100), 'kind' => 'percent']
                        : null,
                ]));
                $base['sources'] = ['sales', 'sale_items', 'products', 'office_expenses'];
                $base['limitations'] = (array) ($digest['limitations'] ?? []);
                if (! ($digest['sufficient'] ?? false) && trim((string) ($digest['summary_text'] ?? '')) !== '') {
                    $base['limitations'][] = (string) $digest['summary_text'];
                }
                $base['summary_text'] = trim((string) ($digest['summary_text'] ?? '')) !== ''
                    ? (string) $digest['summary_text']
                    : (string) ($digest['headline'] ?? '');

                return $base;

            case 'anomalies':
                $businessId = (string) ($ctx['business_id'] ?? '');
                $days = max(1, (int) config('ai.anomalies.compare_days', 14));

                $recentTo = date('Y-m-d');
                $recentFrom = date('Y-m-d', strtotime('-'.($days - 1).' days'));
                $priorTo = date('Y-m-d', strtotime('-'.$days.' days'));
                $priorFrom = date('Y-m-d', strtotime('-'.((2 * $days) - 1).' days'));

                $saleItems = BusinessDataProvider::saleItemsInRange($memberIds, $priorFrom, $recentTo);
                $expenses = BusinessDataProvider::expensesInRange($memberIds, $priorFrom, $recentTo);
                $products = BusinessDataProvider::productsForBusiness($memberIds);

                $alerts = AnomalyDetector::detect($products, $saleItems, $expenses, [
                    'recent' => ['from' => $recentFrom, 'to' => $recentTo],
                    'prior' => ['from' => $priorFrom, 'to' => $priorTo],
                ], [
                    'locale' => $locale,
                    'currency' => $currency,
                    'max_alerts' => (int) config('ai.anomalies.max_alerts', 20),
                    'min_margin_lines' => (int) config('ai.anomalies.min_margin_lines', 3),
                    'margin_drop_pp' => (float) config('ai.anomalies.margin_drop_pp', 10.0),
                    'expense_spike_ratio' => (float) config('ai.anomalies.expense_spike_ratio', 2.0),
                    'expense_spike_min_abs' => (float) config('ai.anomalies.expense_spike_min_abs', 10000),
                ]);

                $dismissed = self::dismissalsFor($businessId);
                $open = [];
                foreach ($alerts as $alert) {
                    if (! isset($dismissed[(string) ($alert['key'] ?? '')])) {
                        $open[] = $alert;
                    }
                }

                $base['period'] = ['from' => $recentFrom, 'to' => $recentTo];
                $base['data'] = [
                    'detected_count' => count($alerts),
                    'open_count' => count($open),
                    'dismissed_count' => count($alerts) - count($open),
                    'windows' => [
                        'recent' => ['from' => $recentFrom, 'to' => $recentTo],
                        'prior' => ['from' => $priorFrom, 'to' => $priorTo],
                    ],
                    'alerts' => array_map(fn ($alert) => [
                        'key' => $alert['key'],
                        'rule' => $alert['rule'],
                        'severity' => $alert['severity'],
                        'subject' => $alert['subject'],
                        'detail' => $alert['detail'],
                    ], array_slice($open, 0, 8)),
                ];
                $base['highlights'] = [
                    ['label' => self::msg('hl_detected', $locale), 'value' => count($alerts), 'kind' => 'number'],
                    ['label' => self::msg('hl_open', $locale), 'value' => count($open), 'kind' => 'number'],
                ];
                $base['sources'] = ['products', 'sales', 'sale_items', 'office_expenses'];
                $base['limitations'] = AnomalyDetector::limitations($locale);
                $base['summary_text'] = AnomalyDetector::summaryText(count($open), $locale, $recentFrom, $recentTo);

                return $base;

            case 'expense_parse':
                $phrase = mb_substr(trim((string) ($args['phrase'] ?? '')), 0, 600);
                $today = (string) ($ctx['today'] ?? date('Y-m-d'));

                if ($phrase === '') {
                    $base['ok'] = false;
                    $base['summary_text'] = self::notUnderstood($locale);

                    return $base;
                }

                $preview = ExpenseParser::parse($phrase, $locale, $today);
                $flagMessages = [];
                foreach ((array) ($preview['flags'] ?? []) as $flag) {
                    $flagMessages[] = ExpenseParser::flagMessage((string) $flag, $locale);
                }

                $amount = $preview['preview']['amount'] ?? null;
                $base['data'] = [
                    'preview' => $preview['preview'],
                    'missing_required' => $preview['missing_required'] ?? [],
                    'flags' => $flagMessages,
                    'saved' => false,
                ];
                $base['limitations'] = $flagMessages;
                $base['summary_text'] = self::msg('expense_note', $locale, [
                    'description' => (string) ($preview['preview']['description'] ?? ''),
                    'amount' => $amount === null ? '-' : number_format((float) $amount),
                    'currency' => (string) ($preview['preview']['currency'] ?? $currency),
                    'date' => (string) ($preview['preview']['expense_date'] ?? $today),
                ]);

                return $base;

            case 'help':
            default:
                $base['data'] = ['capabilities' => self::TOOLS];
                $base['summary_text'] = implode("\n", [
                    self::msg('help_intro', $locale),
                    self::msg('help_report', $locale),
                    self::msg('help_restock', $locale),
                    self::msg('help_health', $locale),
                    self::msg('help_anomalies', $locale),
                    self::msg('help_expense', $locale),
                ]);

                return $base;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Narration with grounding                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Narrate an already-computed result. Returns the model's answer only if
     * every money-scale number in it exists in the payload (bounded to two
     * attempts); otherwise the trusted summary_text is used, honestly flagged.
     *
     * @return array{answer: string, next_steps: array<int, string>, ai: array<string, mixed>}
     */
    public static function narrate(array $result, string $question, array $history, array $ctx): array
    {
        $locale = (string) ($ctx['locale'] ?? 'sw');
        $currency = (string) ($ctx['currency'] ?? 'TZS');
        $fallback = [
            'answer' => trim((string) ($result['summary_text'] ?? '')) !== ''
                ? (string) $result['summary_text']
                : (string) AiException::reasonForCode('NO_TOOL', $locale),
            'next_steps' => [],
            'ai' => [
                'available' => false,
                'degraded' => true,
                'code' => null,
                'reason' => null,
                'meta' => null,
            ],
        ];

        $client = new GeminiClient;
        if (! $client->isEnabled()) {
            $fallback['ai']['code'] = 'AI_DISABLED';
            $fallback['ai']['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }
        if (! $client->isConfigured()) {
            $fallback['ai']['code'] = 'AI_NOT_CONFIGURED';
            $fallback['ai']['reason'] = AiException::reasonForCode('AI_NOT_CONFIGURED', $locale);

            return $fallback;
        }

        [$allowedNumbers, $allowedPercents] = self::factsFrom($result);

        $payload = [
            'locale' => $locale,
            'currency' => $currency,
            'question' => mb_substr($question, 0, 400),
            'tool' => $result['tool'] ?? '',
            'period' => $result['period'] ?? null,
            'trusted_summary' => $result['summary_text'] ?? '',
            'highlights' => $result['highlights'] ?? [],
            'table' => self::compactTable($result['table'] ?? null),
            'data' => self::compactData($result['data'] ?? []),
            'recent_turns' => self::historySnippet($history, (int) config('ai.copilot.history_turns', self::DEFAULT_HISTORY_TURNS)),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || mb_strlen($json) > (int) config('ai.copilot.max_payload_chars', 6000)) {
            unset($payload['data']);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        $instruction = implode("\n", [
            'You are the seller copilot inside "Duka Mkononi".',
            'You receive FINAL, AUTHORITATIVE figures computed by trusted code. Never compute, never invent a number, record or period.',
            'Write 1-3 short sentences answering the seller, then up to 2 short actionable next steps.',
            'Copy every amount and percentage EXACTLY from the data; if a number is not in the data, do not mention it.',
            'If the trusted summary says information is missing or incomplete, say so plainly.',
            'Reply in this language code: '.$locale.'. Keep money in '.$currency.'.',
            'Return STRICT JSON only: {"answer": string, "next_steps": string[]}.',
        ]);

        $hint = '';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $input = $hint === '' ? $json : $json."\n".$hint;
                $aiResult = $client->generateJson(
                    [['text' => $input]],
                    $instruction,
                    [
                        'capability' => self::CAPABILITY,
                        'user_id' => $ctx['user_id'] ?? null,
                        'business_id' => $ctx['business_id'] ?? null,
                        'ip' => $ctx['ip'] ?? null,
                        'locale' => $locale,
                        'temperature' => 0.3,
                        'maxOutputTokens' => 700,
                    ],
                );

                $answer = self::cleanText(is_string($aiResult->data['answer'] ?? null) ? $aiResult->data['answer'] : '');
                $answer = mb_substr($answer, 0, 700);

                $steps = [];
                foreach ((array) ($aiResult->data['next_steps'] ?? []) as $step) {
                    if (! is_string($step)) {
                        continue;
                    }
                    $step = mb_substr(self::cleanText($step), 0, 140);
                    if ($step === '') {
                        continue;
                    }
                    if (AdCopyService::hasUnfactedNumber($step, $allowedNumbers, $allowedPercents)) {
                        continue;
                    }
                    $steps[] = $step;
                    if (count($steps) >= 2) {
                        break;
                    }
                }

                if ($answer !== '' && ! AdCopyService::hasUnfactedNumber($answer, $allowedNumbers, $allowedPercents)) {
                    return [
                        'answer' => $answer,
                        'next_steps' => $steps,
                        'ai' => [
                            'available' => true,
                            'degraded' => false,
                            'code' => null,
                            'reason' => null,
                            'meta' => $aiResult->meta(),
                        ],
                    ];
                }

                // Bounded repair: one retry that names the offending numbers.
                $hint = 'Your previous answer used numbers that are NOT in the data. Allowed numbers: '
                    .implode(', ', array_slice($allowedNumbers, 0, 40))
                    .'. Allowed percentages: '.implode(', ', array_slice($allowedPercents, 0, 20))
                    .'. Rewrite using only those.';
            } catch (AiException $error) {
                $fallback['ai']['code'] = $error->errorCode;
                $fallback['ai']['reason'] = $error->userReason($locale);

                return $fallback;
            } catch (Throwable $error) {
                $fallback['ai']['code'] = 'AI_ERROR';
                $fallback['ai']['reason'] = AiException::reasonForCode('AI_ERROR', $locale);

                return $fallback;
            }
        }

        // Two ungrounded attempts: fall back to the trusted summary.
        $fallback['ai']['code'] = 'AI_UNGROUNDED';
        $fallback['ai']['reason'] = self::msg('ungrounded', $locale);

        return $fallback;
    }

    /* ------------------------------------------------------------------ */
    /* Grounding helpers                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Every numeric token and percentage appearing in the trusted payload.
     *
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    public static function factsFrom(array $result): array
    {
        $json = json_encode([
            'data' => $result['data'] ?? [],
            'highlights' => $result['highlights'] ?? [],
            'table' => $result['table'] ?? [],
            'summary' => $result['summary_text'] ?? '',
            'period' => $result['period'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $text = $json === false ? '' : $json;

        // Thousands separators first so "5,000" compares as 5000.
        $normalized = preg_replace('/(?<=\d)(?:,\s*)?(?=\d{3}(?!\\d))/u', '', $text) ?? $text;

        $percents = [];
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*%/u', $normalized, $percentMatches) > 0) {
            foreach ($percentMatches[1] as $value) {
                $percents[] = (float) $value;
            }
        }

        $withoutPercents = preg_replace('/\d+(?:\.\d+)?\s*%/u', ' ', $normalized) ?? $normalized;
        $numbers = [];
        if (preg_match_all('/\d+(?:\.\d+)?/u', $withoutPercents, $matches) > 0) {
            foreach ($matches[0] as $token) {
                $numbers[] = (float) $token;
            }
        }

        return [array_values(array_unique($numbers)), array_values(array_unique($percents))];
    }

    /** Strip HTML/control characters and collapse whitespace. */
    public static function cleanText(string $text): string
    {
        $text = preg_replace('/<[^>]*>/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /* ------------------------------------------------------------------ */
    /* Quick actions (deterministic links; paths are valid on web AND mobile) */
    /* ------------------------------------------------------------------ */

    /**
     * Deterministic links to the relevant screens. Paths are identical on the
     * website (/muuzaji/...) and the Expo app, and the labels are resolved
     * server-side so both surfaces show them in the active locale.
     *
     * @return array<int, array{key: string, href: string, label: string}>
     */
    public static function quickActions(string $tool, string $locale = 'sw'): array
    {
        $map = [
            'report' => [
                ['key' => 'sales', 'href' => '/muuzaji/mauzo', 'label' => self::msg('qa_sales', $locale)],
                ['key' => 'expenses', 'href' => '/muuzaji/matumizi', 'label' => self::msg('qa_expenses', $locale)],
            ],
            'restock' => [
                ['key' => 'restock', 'href' => '/muuzaji/mpango-stock', 'label' => self::msg('qa_restock', $locale)],
            ],
            'health' => [
                ['key' => 'health', 'href' => '/muuzaji/afya-biashara', 'label' => self::msg('qa_health', $locale)],
            ],
            'anomalies' => [],
            'expense_parse' => [
                ['key' => 'expenses', 'href' => '/muuzaji/matumizi', 'label' => self::msg('qa_expenses', $locale)],
            ],
            'help' => [
                ['key' => 'ask', 'href' => '/muuzaji/uliza-biashara', 'label' => self::msg('qa_ask', $locale)],
                ['key' => 'hub', 'href' => '/muuzaji/huduma-nyingine', 'label' => self::msg('qa_hub', $locale)],
            ],
        ];

        return $map[$tool] ?? [];
    }

    /* ------------------------------------------------------------------ */
    /* Conversation persistence (bounded, tenant-scoped, honest fallback)  */
    /* ------------------------------------------------------------------ */

    /**
     * Load (or start) a conversation for this business + user. Every query is
     * scoped by business_id AND user_id, so a conversation can never cross a
     * tenant or a staff member boundary.
     *
     * @return array{id: string, title: string, messages: array<int, array<string, mixed>>, stored: bool}
     */
    public static function loadConversation(string $businessId, string $userId, ?string $conversationId): array
    {
        $id = self::isUuid($conversationId) ? (string) $conversationId : (string) Str::uuid();
        $title = '';
        $messages = [];
        $stored = false;

        try {
            self::pruneExpired($businessId, $userId);

            $row = AiCopilotConversation::where('business_id', $businessId)
                ->where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if ($row !== null) {
                $stored = true;
                $title = (string) ($row->title ?? '');
                $decoded = $row->messages ?? [];
                $messages = is_array($decoded) ? $decoded : [];
            }
        } catch (Throwable $error) {
            // Table missing / Supabase unavailable: fall back to cache below.
        }

        if (! $stored) {
            $cached = Cache::get(self::cacheKey($businessId, $userId, $id));
            if (is_array($cached)) {
                $title = (string) ($cached['title'] ?? '');
                $messages = is_array($cached['messages'] ?? null) ? $cached['messages'] : [];
            }
        }

        return [
            'id' => $id,
            'title' => $title,
            'messages' => $messages,
            'stored' => $stored,
        ];
    }

    /**
     * Persist both sides of the exchange, trimming to the configured bound.
     * Returns false when the durable store is unavailable (the caller then
     * tells the seller the history is only cached).
     */
    public static function saveConversation(string $businessId, string $userId, string $conversationId, string $title, array $messages): bool
    {
        $max = max(2, (int) config('ai.copilot.store_messages', self::DEFAULT_STORE_MESSAGES));
        $messages = self::trimMessages($messages, $max);
        $now = now()->toISOString(true);
        $title = mb_substr($title, 0, 80);

        try {
            $row = AiCopilotConversation::where('business_id', $businessId)
                ->where('user_id', $userId)
                ->where('id', $conversationId)
                ->first();

            if ($row !== null) {
                AiCopilotConversation::where('business_id', $businessId)
                    ->where('user_id', $userId)
                    ->where('id', $conversationId)
                    ->update([
                        'title' => $title !== '' ? $title : (string) ($row->title ?? ''),
                        'messages' => $messages,
                        'updated_at' => $now,
                    ]);
            } else {
                AiCopilotConversation::create([
                    'id' => $conversationId,
                    'business_id' => $businessId,
                    'user_id' => $userId,
                    'title' => $title,
                    'messages' => $messages,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            try {
                Cache::forget(self::cacheKey($businessId, $userId, $conversationId));
            } catch (Throwable $error) {
                // Cache cleanup is best-effort.
            }

            return true;
        } catch (Throwable $error) {
            try {
                Cache::put(
                    self::cacheKey($businessId, $userId, $conversationId),
                    ['title' => $title, 'messages' => $messages],
                    now()->addHours(max(1, (int) config('ai.copilot.cache_hours', 24))),
                );
            } catch (Throwable $cacheError) {
                // Neither store is available; the answer still returns.
            }

            return false;
        }
    }

    /** Recent conversations for this business + user, newest first. */
    public static function listConversations(string $businessId, string $userId, int $limit = 20): array
    {
        try {
            self::pruneExpired($businessId, $userId);

            $rows = AiCopilotConversation::where('business_id', $businessId)
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->limit(max(1, min(50, $limit)))
                ->get()
                ->toArray();

            $out = [];
            foreach ($rows as $row) {
                $messages = is_array($row['messages'] ?? null) ? $row['messages'] : [];
                $out[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'message_count' => count($messages),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                ];
            }

            return $out;
        } catch (Throwable $error) {
            return [];
        }
    }

    /** One conversation's messages, or null when absent / not owned. */
    public static function conversationMessages(string $businessId, string $userId, string $conversationId): ?array
    {
        if (! self::isUuid($conversationId)) {
            return null;
        }

        try {
            $row = AiCopilotConversation::where('business_id', $businessId)
                ->where('user_id', $userId)
                ->where('id', $conversationId)
                ->first();

            if ($row === null) {
                return null;
            }

            $messages = $row->messages ?? [];

            return is_array($messages) ? $messages : [];
        } catch (Throwable $error) {
            return null;
        }
    }

    /** Delete one conversation (ownership-scoped). */
    public static function deleteConversation(string $businessId, string $userId, string $conversationId): bool
    {
        if (! self::isUuid($conversationId)) {
            return false;
        }

        try {
            AiCopilotConversation::where('business_id', $businessId)
                ->where('user_id', $userId)
                ->where('id', $conversationId)
                ->delete();
        } catch (Throwable $error) {
            // Cache fallback below still clears local context.
        }

        try {
            Cache::forget(self::cacheKey($businessId, $userId, $conversationId));
        } catch (Throwable $error) {
            // Best-effort.
        }

        return true;
    }

    /** Delete every conversation this user has in this business. */
    public static function deleteAllConversations(string $businessId, string $userId): int
    {
        $deleted = 0;

        try {
            $ids = AiCopilotConversation::where('business_id', $businessId)
                ->where('user_id', $userId)
                ->limit(200)
                ->pluck('id');
            foreach ($ids as $id) {
                if (self::deleteConversation($businessId, $userId, (string) $id)) {
                    $deleted++;
                }
            }
        } catch (Throwable $error) {
            // Table unavailable - nothing durable to delete.
        }

        return $deleted;
    }

    /** Retention: drop this user's conversations older than the configured window. */
    private static function pruneExpired(string $businessId, string $userId): void
    {
        $days = max(1, (int) config('ai.copilot.retention_days', 90));
        $cutoff = now()->subDays($days)->toISOString(true);

        AiCopilotConversation::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('updated_at', '<', $cutoff)
            ->delete();
    }

    /** Append both messages and trim from the front (oldest first). */
    public static function appendMessages(array $messages, array $userMessage, array $assistantMessage, int $max = self::DEFAULT_STORE_MESSAGES): array
    {
        $messages[] = $userMessage;
        $messages[] = $assistantMessage;

        return self::trimMessages($messages, $max);
    }

    /** Keep the newest $max entries (oldest drop off first). */
    public static function trimMessages(array $messages, int $max): array
    {
        $max = max(2, $max);

        if (count($messages) <= $max) {
            return array_values($messages);
        }

        return array_values(array_slice($messages, -$max));
    }

    /** Bounded context for the model: last N exchanges, content clipped. */
    public static function historySnippet(array $history, int $turns = self::DEFAULT_HISTORY_TURNS): array
    {
        $turns = max(0, $turns);
        if ($turns === 0 || $history === []) {
            return [];
        }

        $snippet = [];
        foreach (array_slice($history, -$turns * 2) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $role = (string) ($entry['role'] ?? '');
            if ($role !== 'user' && $role !== 'assistant') {
                continue;
            }
            $snippet[] = [
                'role' => $role,
                'content' => mb_substr((string) ($entry['content'] ?? ''), 0, 240),
            ];
        }

        return $snippet;
    }

    public static function isUuid(?string $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private static function cacheKey(string $businessId, string $userId, string $conversationId): string
    {
        return 'ai:copilot:conv:'.$businessId.':'.$userId.':'.$conversationId;
    }

    /**
     * Open dismissals for a business (mirror of AiAnomalyController::
     * dismissalsFor): cache first, then a bounded read of the durable
     * `user_logs` audit rows, so dismissed alerts are not re-raised in chat.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function dismissalsFor(string $businessId): array
    {
        if ($businessId === '') {
            return [];
        }

        $cacheKey = 'ai:anomalies:dismissed:'.$businessId;
        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable $error) {
            // Cache is optional; the audit rows below are authoritative.
        }

        $map = [];
        try {
            $rows = UserLog::where('action', 'AI_ANOMALY_DISMISSED')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get()
                ->toArray();
        } catch (Throwable $error) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $details = $row['details'] ?? null;
            if (is_string($details)) {
                $details = json_decode($details, true);
            }
            if (! is_array($details) || ($details['business_id'] ?? null) !== $businessId) {
                continue;
            }
            $key = (string) ($details['key'] ?? '');
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            $map[$key] = ['key' => $key];
        }

        try {
            Cache::put($cacheKey, $map, now()->addDays(365));
        } catch (Throwable $error) {
            // Best-effort mirror.
        }

        return $map;
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /** @return array{tool: string, args: array<string, mixed>, source: string, confidence: string} */
    private static function routed(string $tool, array $args, string $source, string $confidence): array
    {
        return [
            'tool' => $tool,
            'args' => $args,
            'source' => $source,
            'confidence' => $confidence,
        ];
    }

    public static function notUnderstood(string $locale): string
    {
        return self::msg('not_understood', $locale, [
            'examples' => implode(' | ', array_slice(ReportIntentParser::supportedExamples($locale), 0, 3)),
        ]);
    }

    /** Table rows for the model, capped so the prompt stays bounded. */
    private static function compactTable(?array $table): ?array
    {
        if (! is_array($table) || empty($table['rows'])) {
            return null;
        }

        return [
            'columns' => $table['columns'] ?? [],
            'rows' => array_slice($table['rows'], 0, 8),
        ];
    }

    /** Cap the biggest list fields before they reach the model. */
    private static function compactData(array $data): array
    {
        foreach (['items' => 8, 'by_category' => 6, 'top_customers' => 5, 'products' => 8, 'alerts' => 6] as $key => $limit) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data[$key] = array_slice($data[$key], 0, $limit);
            }
        }

        if (isset($data['actions']) && is_array($data['actions'])) {
            $data['actions'] = array_slice($data['actions'], 0, 3);
        }

        return $data;
    }
}
