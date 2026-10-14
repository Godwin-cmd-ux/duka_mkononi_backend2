<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * Capability #12 - Business & Review Summarisation (grounding layer).
 *
 * Turns ONLY records the platform already holds into a trustworthy trust
 * summary, split so no client can confuse the two halves:
 *
 *  - facts[]     : verified platform records (certification, live product
 *                  count, order aggregates) - never opinions.
 *  - opinions[]  : what published customer reviews actually say, always
 *                  carrying sample size, rating coverage and dates.
 *  - reviews     : recurring themes extracted mechanically from the
 *                  published corpus (document frequency + rating-derived
 *                  polarity), plus the original review rows so clients can
 *                  show (or link) the source material.
 *
 * Honesty rules baked in here:
 *  - only `is_published` reviews are ever read; inquiry e-mail/phone and the
 *    certification_note (moderation notes) are never loaded at all;
 *  - reviews on this platform are PLATFORM-LEVEL testimonials (they carry no
 *    business_id), so the payload labels them `scope: platform` and the
 *    caller must not attribute them to one seller;
 *  - order figures are aggregates only (counts + one date), never customer
 *    rows;
 *  - the model, when available, may only REWORD the deterministic numbers:
 *    its sentence must contain no number that was not in the data we gave it,
 *    otherwise the deterministic summary is used instead;
 *  - cached per locale with explicit version bumps (review publish/unpublish,
 *    business certify/uncertify), so a changed record never keeps serving a
 *    stale summary for longer than its TTL.
 */
class TrustSummary
{
    public const CAPABILITY = 'business_summary';

    /** Original review rows returned verbatim with the summary. */
    public const ITEM_LIMIT = 3;

    /**
     * Words that never identify a theme on their own (English + Swahili
     * function words and review filler). Theme extraction is document
     * frequency over the remaining content words, so filler here would
     * otherwise dominate every summary.
     */
    private const STOP = [
        'the', 'and', 'for', 'with', 'that', 'this', 'these', 'those', 'from',
        'have', 'has', 'had', 'want', 'wants', 'need', 'needs', 'looking',
        'please', 'some', 'any', 'are', 'was', 'were', 'been', 'being', 'will',
        'would', 'could', 'should', 'how', 'much', 'many', 'what', 'where',
        'which', 'when', 'while', 'who', 'whom', 'why', 'does', 'did', 'doing',
        'sell', 'sells', 'sold', 'buy', 'buys', 'bought', 'get', 'gets', 'got',
        'show', 'shows', 'find', 'finds', 'found', 'product', 'products',
        'item', 'items', 'thing', 'things', 'stuff', 'shop', 'shops', 'store',
        'stores', 'online', 'today', 'yesterday', 'now', 'really', 'very',
        'just', 'also', 'only', 'even', 'still', 'always', 'never', 'maybe',
        'one', 'two', 'three', 'four', 'five', 'my', 'our', 'your', 'their',
        'there', 'here', 'into', 'over', 'under', 'about', 'again', 'more',
        'most', 'other', 'others', 'same', 'than', 'then', 'them', 'they',
        'its', 'his', 'her', 'him', 'she', 'he', 'we', 'you', 'but', 'not',
        'yes', 'very', 'all', 'each', 'every', 'both', 'few', 'own', 'too',
        'can', 'cant', 'done', 'well', 'good', 'great', 'nice', 'best',
        'better', 'worst', 'worst', 'awesome', 'amazing', 'love', 'liked',
        'like', 'likes', 'hate', 'hated', 'really', 'quite', 'lot', 'lots',
        'bit', 'bits', 'once', 'twice', 'soon', 'later', 'first', 'second',
        // Swahili
        'natafuta', 'tafuta', 'nataka', 'nahitaji', 'hitaji', 'niko', 'nina',
        'nilikuwa', 'kwenye', 'katika', 'ndani', 'hii', 'hizi', 'hizo', 'hiyo',
        'hayo', 'hilo', 'hili', 'lile', 'yule', 'wale', 'pale', 'hapa', 'sasa',
        'yangu', 'wangu', 'yako', 'wako', 'yake', 'wake', 'wetu', 'wenu',
        'lete', 'leta', 'beba', 'mbuya', 'bidhaa', 'mambo', 'kitu', 'vitu',
        'basi', 'ndio', 'sana', 'tu', 'pia', 'kabisa', 'zaidi', 'chini',
        'leo', 'jana', 'kesho', 'duka', 'maduka', 'nisaidie', 'tafadhali',
        'shukrani', 'habari', 'ndiyo', 'sindio', 'karibu', 'hakuna', 'za',
        'ya', 'wa', 'la', 'kwa', 'na', 'au', 'ili', 'je', 'kama', 'ila',
        'hadi', 'zilizo', 'bei', 'gani', 'ipi', 'zipi', 'lipi', 'lingine',
        'sokoni', 'mtandaoni', 'nzuri', 'bora', 'mpya', 'ghafi', 'halali',
        'muda', 'mara', 'moyoni', 'wote', 'zote', 'kadhaa', 'mbalimbali',
        'kweli', 'hivyo', 'vile', 'baada', 'kabla', 'wakati', 'hadi', 'pamoja',
        'moja', 'mbili', 'tatu', 'nne', 'tano', 'mbeleni', 'hapa', 'pale',
    ];

    /**
     * Server-side strings for facts, opinions, summary and limitations. Keys
     * mirror the client locale files; messagesComplete() proves every locale
     * carries them (unit-tested).
     */
    public const MESSAGES = [
        'sw' => [
            'fact_certified' => 'Imethibitishwa na jukwaa tangu {date}.',
            'fact_certified_no_date' => 'Imethibitishwa na jukwaa.',
            'fact_not_certified' => 'Bado haijaibibitishwa na jukwaa.',
            'fact_products' => 'Bidhaa {count} zinazouzwa sasa.',
            'fact_orders' => 'Oda {total} zimepokelewa; {completed} zimekamilika na {open} bado zinaendelea.',
            'fact_last_order' => 'Oda ya hivi karibuni ilipokelewa {date}.',
            'opinion_sample' => 'Maoni {count} yaliyochapishwa; {rated} yana alama, wastani {average}/5.',
            'opinion_none' => 'Hakuna maoni yaliyochapishwa bado.',
            'opinion_period' => 'Yamechapishwa kati ya {from} na {to}.',
            'opinion_theme' => 'Maoni {count} yanataja {theme} ({polarity}).',
            'polarity_positive' => 'chanya',
            'polarity_negative' => 'hasi',
            'polarity_mixed' => 'mseto',
            'polarity_neutral' => 'hayajapangwa',
            'summary_sample' => 'Maoni {count} yaliyochapishwa, wastani {average}/5.',
            'summary_themes' => 'Maneno yanayojirudia: {themes}.',
            'summary_divided' => 'Maoni yamegawanyika: kuna sifa na malalamiko kwa uwiano sawa.',
            'summary_no_reviews' => 'Hakuna maoni yaliyochapishwa bado.',
            'limit_opinions' => 'Maoni ni kauli za wateja; hayajathibitishwa na jukwaa.',
            'limit_facts' => 'Uthibitisho, bidhaa na takwimu za oda vinatoka kwenye rekodi rasmi za jukwaa.',
            'limit_scope' => 'Maoni ni ya jukwaa lote, si ya biashara husika moja.',
            'limit_cache' => 'Muhtasari huu umehifadhiwa kwa dakika {minutes}; data mpya hufuta cache.',
            'limit_private' => 'Hakuna barua pepe, simu au taarifa binafsi zinazoonyeshwa hapa.',
            'src_reviews' => 'Maoni yaliyochapishwa',
            'src_business' => 'Rekodi za biashara na bidhaa',
            'src_orders' => 'Jumla za oda (hakuna taarifa binafsi)',
            'error_generic' => 'Imeshindwa kutengeneza muhtasari sasa hivi. Jaribu tena.',
            'not_found' => 'Biashara haipatikani au si ya umma.',
        ],
        'en' => [
            'fact_certified' => 'Certified by the platform since {date}.',
            'fact_certified_no_date' => 'Certified by the platform.',
            'fact_not_certified' => 'Not yet certified by the platform.',
            'fact_products' => '{count} products on sale right now.',
            'fact_orders' => '{total} orders received; {completed} completed and {open} still in progress.',
            'fact_last_order' => 'Most recent order received on {date}.',
            'opinion_sample' => '{count} published reviews; {rated} carry a rating, averaging {average}/5.',
            'opinion_none' => 'No published reviews yet.',
            'opinion_period' => 'Published between {from} and {to}.',
            'opinion_theme' => '{count} reviews mention {theme} ({polarity}).',
            'polarity_positive' => 'positive',
            'polarity_negative' => 'negative',
            'polarity_mixed' => 'mixed',
            'polarity_neutral' => 'unrated',
            'summary_sample' => '{count} published reviews, averaging {average}/5.',
            'summary_themes' => 'Recurring themes: {themes}.',
            'summary_divided' => 'Feedback is divided: both praise and complaints appear.',
            'summary_no_reviews' => 'No published reviews yet.',
            'limit_opinions' => 'Reviews are customer statements; the platform does not verify them.',
            'limit_facts' => 'Certification, product and order figures come from the platform records.',
            'limit_scope' => 'Reviews are platform-wide, not specific to this one business.',
            'limit_cache' => 'This summary is cached for {minutes} minutes; fresh data clears the cache.',
            'limit_private' => 'No e-mail, phone number or private account data is shown here.',
            'src_reviews' => 'Published reviews',
            'src_business' => 'Business and product records',
            'src_orders' => 'Order aggregates (no customer data)',
            'error_generic' => 'The summary could not be built right now. Please try again.',
            'not_found' => 'Business not found or not public.',
        ],
        'es' => [
            'fact_certified' => 'Certificada por la plataforma desde {date}.',
            'fact_certified_no_date' => 'Certificada por la plataforma.',
            'fact_not_certified' => 'Aún no certificada por la plataforma.',
            'fact_products' => '{count} productos en venta ahora mismo.',
            'fact_orders' => '{total} pedidos recibidos; {completed} completados y {open} en curso.',
            'fact_last_order' => 'Pedido más reciente recibido el {date}.',
            'opinion_sample' => '{count} reseñas publicadas; {rated} tienen calificación, promedio {average}/5.',
            'opinion_none' => 'Aún no hay reseñas publicadas.',
            'opinion_period' => 'Publicadas entre {from} y {to}.',
            'opinion_theme' => '{count} reseñas mencionan {theme} ({polarity}).',
            'polarity_positive' => 'positivo',
            'polarity_negative' => 'negativo',
            'polarity_mixed' => 'mixto',
            'polarity_neutral' => 'sin calificar',
            'summary_sample' => '{count} reseñas publicadas, promedio {average}/5.',
            'summary_themes' => 'Temas recurrentes: {themes}.',
            'summary_divided' => 'Las opiniones están divididas: hay elogios y quejas.',
            'summary_no_reviews' => 'Aún no hay reseñas publicadas.',
            'limit_opinions' => 'Las reseñas son declaraciones de clientes; la plataforma no las verifica.',
            'limit_facts' => 'La certificación y las cifras provienen de los registros de la plataforma.',
            'limit_scope' => 'Las reseñas son de toda la plataforma, no de un solo negocio.',
            'limit_cache' => 'Este resumen se guarda en caché {minutes} minutos; los datos nuevos lo limpian.',
            'limit_private' => 'No se muestran correos, teléfonos ni datos privados.',
            'src_reviews' => 'Reseñas publicadas',
            'src_business' => 'Registros de negocios y productos',
            'src_orders' => 'Totales de pedidos (sin datos de clientes)',
            'error_generic' => 'No se pudo generar el resumen ahora. Inténtalo de nuevo.',
            'not_found' => 'Negocio no encontrado o no público.',
        ],
        'fr' => [
            'fact_certified' => 'Certifiée par la plateforme depuis {date}.',
            'fact_certified_no_date' => 'Certifiée par la plateforme.',
            'fact_not_certified' => 'Pas encore certifiée par la plateforme.',
            'fact_products' => '{count} produits en vente en ce moment.',
            'fact_orders' => '{total} commandes reçues ; {completed} terminées et {open} en cours.',
            'fact_last_order' => 'Commande la plus récente reçue le {date}.',
            'opinion_sample' => '{count} avis publiés ; {rated} notés, moyenne {average}/5.',
            'opinion_none' => 'Aucun avis publié pour le moment.',
            'opinion_period' => 'Publiés entre {from} et {to}.',
            'opinion_theme' => '{count} avis mentionnent {theme} ({polarity}).',
            'polarity_positive' => 'positif',
            'polarity_negative' => 'négatif',
            'polarity_mixed' => 'mitigé',
            'polarity_neutral' => 'non noté',
            'summary_sample' => '{count} avis publiés, moyenne {average}/5.',
            'summary_themes' => 'Thèmes récurrents : {themes}.',
            'summary_divided' => 'Les avis sont partagés : louanges et plaintes coexistent.',
            'summary_no_reviews' => 'Aucun avis publié pour le moment.',
            'limit_opinions' => 'Les avis sont des déclarations de clients ; la plateforme ne les vérifie pas.',
            'limit_facts' => 'La certification et les chiffres viennent des registres de la plateforme.',
            'limit_scope' => 'Les avis portent sur toute la plateforme, pas sur une seule entreprise.',
            'limit_cache' => 'Ce résumé est mis en cache {minutes} minutes ; des données fraîches le vident.',
            'limit_private' => 'Aucun e-mail, téléphone ou donnée privée n\'est affiché ici.',
            'src_reviews' => 'Avis publiés',
            'src_business' => 'Registres entreprises et produits',
            'src_orders' => 'Totaux de commandes (sans données clients)',
            'error_generic' => 'Le résumé ne peut pas être généré pour le moment. Réessayez.',
            'not_found' => 'Entreprise introuvable ou non publique.',
        ],
        'zh' => [
            'fact_certified' => '自 {date} 起获平台认证。',
            'fact_certified_no_date' => '已获平台认证。',
            'fact_not_certified' => '尚未获得平台认证。',
            'fact_products' => '现有 {count} 件商品在售。',
            'fact_orders' => '共收到 {total} 笔订单；{completed} 笔已完成，{open} 笔进行中。',
            'fact_last_order' => '最近一笔订单于 {date} 收到。',
            'opinion_sample' => '{count} 条已发布评价；{rated} 条带评分，平均 {average}/5。',
            'opinion_none' => '暂无已发布的评价。',
            'opinion_period' => '发布于 {from} 至 {to} 之间。',
            'opinion_theme' => '{count} 条评价提到 {theme}（{polarity}）。',
            'polarity_positive' => '正面',
            'polarity_negative' => '负面',
            'polarity_mixed' => '褒贬不一',
            'polarity_neutral' => '未评分',
            'summary_sample' => '{count} 条已发布评价，平均 {average}/5。',
            'summary_themes' => '反复出现的主题：{themes}。',
            'summary_divided' => '评价存在分歧：好评与差评并存。',
            'summary_no_reviews' => '暂无已发布的评价。',
            'limit_opinions' => '评价来自客户的陈述，平台不对其真实性背书。',
            'limit_facts' => '认证、商品与订单数字来自平台记录。',
            'limit_scope' => '评价针对整个平台，而非某一家商家。',
            'limit_cache' => '此摘要缓存 {minutes} 分钟；数据更新会清除缓存。',
            'limit_private' => '此处不显示邮箱、电话或任何私人账户信息。',
            'src_reviews' => '已发布的评价',
            'src_business' => '商家与商品记录',
            'src_orders' => '订单汇总（不含客户数据）',
            'error_generic' => '暂时无法生成摘要，请重试。',
            'not_found' => '未找到该商家或该商家未公开。',
        ],
        'hi' => [
            'fact_certified' => '{date} से प्लेटफ़ॉर्म द्वारा प्रमाणित।',
            'fact_certified_no_date' => 'प्लेटफ़ॉर्म द्वारा प्रमाणित।',
            'fact_not_certified' => 'अभी तक प्लेटफ़ॉर्म द्वारा प्रमाणित नहीं।',
            'fact_products' => 'अभी {count} उत्पाद बिक्री पर हैं।',
            'fact_orders' => '{total} ऑर्डर मिले; {completed} पूरे हुए और {open} चल रहे हैं।',
            'fact_last_order' => 'सबसे हाल का ऑर्डर {date} को मिला।',
            'opinion_sample' => '{count} प्रकाशित समीक्षाएँ; {rated} को रेटिंग है, औसत {average}/5।',
            'opinion_none' => 'अभी कोई प्रकाशित समीक्षा नहीं।',
            'opinion_period' => '{from} और {to} के बीच प्रकाशित।',
            'opinion_theme' => '{count} समीक्षाओं में {theme} का उल्लेख है ({polarity})।',
            'polarity_positive' => 'सकारात्मक',
            'polarity_negative' => 'नकारात्मक',
            'polarity_mixed' => 'मिला-जुला',
            'polarity_neutral' => 'बिना रेटिंग',
            'summary_sample' => '{count} प्रकाशित समीक्षाएँ, औसत {average}/5।',
            'summary_themes' => 'बार-बार आने वाले विषय: {themes}।',
            'summary_divided' => 'समीक्षाएँ बँटी हुई हैं: प्रशंसा और शिकायतें दोनों हैं।',
            'summary_no_reviews' => 'अभी कोई प्रकाशित समीक्षा नहीं।',
            'limit_opinions' => 'समीक्षाएँ ग्राहकों के कथन हैं; प्लेटफ़ॉर्म उनकी पुष्टि नहीं करता।',
            'limit_facts' => 'प्रमाणन, उत्पाद और ऑर्डर आँकड़े प्लेटफ़ॉर्म रिकॉर्ड से आते हैं।',
            'limit_scope' => 'समीक्षाएँ पूरे प्लेटफ़ॉर्म की हैं, किसी एक व्यवसाय की नहीं।',
            'limit_cache' => 'यह सारांश {minutes} मिनट कैश रहता है; नए डेटा से कैश हटता है।',
            'limit_private' => 'यहाँ ईमेल, फ़ोन या निजी खाता जानकारी नहीं दिखती।',
            'src_reviews' => 'प्रकाशित समीक्षाएँ',
            'src_business' => 'व्यवसाय और उत्पाद रिकॉर्ड',
            'src_orders' => 'ऑर्डर कुल (कोई ग्राहक डेटा नहीं)',
            'error_generic' => 'सारांश अभी नहीं बनाया जा सका। कृपया पुनः प्रयास करें।',
            'not_found' => 'व्यवसाय नहीं मिला या सार्वजनिक नहीं है।',
        ],
        'ur' => [
            'fact_certified' => '{date} سے پلیٹ فارم کی طرف سے تصدیق شدہ۔',
            'fact_certified_no_date' => 'پلیٹ فارم کی طرف سے تصدیق شدہ۔',
            'fact_not_certified' => 'ابھی تک پلیٹ فارم کی طرف سے تصدیق شدہ نہیں۔',
            'fact_products' => 'ابھی {count} مصنوعات فروخت پر ہیں۔',
            'fact_orders' => '{total} آرڈرز موصول ہوئے؛ {completed} مکمل اور {open} زیرِ کار ہیں۔',
            'fact_last_order' => 'تازہ ترین آرڈر {date} کو موصول ہوا۔',
            'opinion_sample' => '{count} شائع شدہ جائزے؛ {rated} کو ریٹنگ ہے، اوسط {average}/5۔',
            'opinion_none' => 'ابھی کوئی شائع شدہ جائزہ نہیں۔',
            'opinion_period' => '{from} اور {to} کے درمیان شائع کیے گئے۔',
            'opinion_theme' => '{count} جائزوں میں {theme} کا ذکر ہے ({polarity})۔',
            'polarity_positive' => 'مثبت',
            'polarity_negative' => 'منفی',
            'polarity_mixed' => 'آمیزہ',
            'polarity_neutral' => 'بغیر ریٹنگ',
            'summary_sample' => '{count} شائع شدہ جائزے، اوسط {average}/5۔',
            'summary_themes' => 'بار بار آنے والے موضوعات: {themes}۔',
            'summary_divided' => 'جائزے تقسیم شدہ ہیں: تعریف اور شکوے دونوں ہیں۔',
            'summary_no_reviews' => 'ابھی کوئی شائع شدہ جائزہ نہیں۔',
            'limit_opinions' => 'جائزے گاہکوں کے بیانات ہیں؛ پلیٹ فارم ان کی تصدیق نہیں کرتا۔',
            'limit_facts' => 'تصدیق، مصنوعات اور آرڈر کے اعداد پلیٹ فارم کے ریکارڈ سے آتے ہیں۔',
            'limit_scope' => 'جائزے پورے پلیٹ فارم کے ہیں، کسی ایک کاروبار کے نہیں۔',
            'limit_cache' => 'یہ خلاصہ {minutes} منٹ محفوظ رہتا ہے؛ نیا ڈیٹا کیش صاف کر دیتا ہے۔',
            'limit_private' => 'یہاں ای میل، فون یا ذاتی اکاؤنٹ معلومات نہیں دکھائی جاتیں۔',
            'src_reviews' => 'شائع شدہ جائزے',
            'src_business' => 'کاروبار اور مصنوعات کے ریکارڈ',
            'src_orders' => 'آرڈر کل (کوئی گاہک ڈیٹا نہیں)',
            'error_generic' => 'خلاصہ ابھی نہیں بن سکا۔ براہ کرم دوبارہ کوشش کریں۔',
            'not_found' => 'کاروبار نہیں ملا یا عوامی نہیں ہے۔',
        ],
        'de' => [
            'fact_certified' => 'Seit {date} von der Plattform zertifiziert.',
            'fact_certified_no_date' => 'Von der Plattform zertifiziert.',
            'fact_not_certified' => 'Noch nicht von der Plattform zertifiziert.',
            'fact_products' => '{count} Produkte aktuell im Angebot.',
            'fact_orders' => '{total} Bestellungen erhalten; {completed} abgeschlossen, {open} laufend.',
            'fact_last_order' => 'Neueste Bestellung am {date} erhalten.',
            'opinion_sample' => '{count} veröffentlichte Bewertungen; {rated} mit Sternebewertung, Ø {average}/5.',
            'opinion_none' => 'Noch keine veröffentlichten Bewertungen.',
            'opinion_period' => 'Veröffentlicht zwischen {from} und {to}.',
            'opinion_theme' => '{count} Bewertungen nennen {theme} ({polarity}).',
            'polarity_positive' => 'positiv',
            'polarity_negative' => 'negativ',
            'polarity_mixed' => 'gemischt',
            'polarity_neutral' => 'ohne Bewertung',
            'summary_sample' => '{count} veröffentlichte Bewertungen, Ø {average}/5.',
            'summary_themes' => 'Wiederkehrende Themen: {themes}.',
            'summary_divided' => 'Die Meinungen sind geteilt: Lob und Beschwerden gibt es gleichermaßen.',
            'summary_no_reviews' => 'Noch keine veröffentlichten Bewertungen.',
            'limit_opinions' => 'Bewertungen sind Kundenstatements; die Plattform bestätigt sie nicht.',
            'limit_facts' => 'Zertifizierung, Produkt- und Bestellungszahlen stammen aus den Plattformdaten.',
            'limit_scope' => 'Bewertungen gelten für die ganze Plattform, nicht für dieses eine Geschäft.',
            'limit_cache' => 'Diese Zusammenfassung ist {minutes} Minuten im Cache; neue Daten leeren ihn.',
            'limit_private' => 'Hier werden keine E-Mails, Telefonnummern oder privaten Daten angezeigt.',
            'src_reviews' => 'Veröffentlichte Bewertungen',
            'src_business' => 'Geschäfts- und Produktakten',
            'src_orders' => 'Bestellungssummen (keine Kundendaten)',
            'error_generic' => 'Die Zusammenfassung kann gerade nicht erstellt werden. Bitte erneut versuchen.',
            'not_found' => 'Geschäft nicht gefunden oder nicht öffentlich.',
        ],
    ];

    /* ------------------------------------------------------------------ */
    /* Review statistics (pure)                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Sample size, rating coverage, distribution, date period and the
     * "divided feedback" signal - all from the rows actually passed in.
     *
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array<string, mixed>
     */
    public static function reviewStats(array $reviews): array
    {
        $count = count($reviews);
        $rated = 0;
        $sum = 0;
        $distribution = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0];
        $from = null;
        $to = null;

        foreach ($reviews as $review) {
            $rating = is_numeric($review['rating'] ?? null) ? (int) $review['rating'] : null;
            if ($rating !== null && $rating >= 1 && $rating <= 5) {
                $rated++;
                $sum += $rating;
                $distribution[(string) $rating]++;
            }

            $date = substr((string) ($review['published_at'] ?? ''), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                if ($from === null || $date < $from) {
                    $from = $date;
                }
                if ($to === null || $date > $to) {
                    $to = $date;
                }
            }
        }

        $low = $distribution['1'] + $distribution['2'];
        $high = $distribution['4'] + $distribution['5'];

        return [
            'sample_size' => $count,
            'rated_count' => $rated,
            'rating_average' => $rated > 0 ? round($sum / $rated, 1) : null,
            'rating_distribution' => $distribution,
            'period' => ['from' => $from, 'to' => $to],
            // Fair "mixed/conflicting" signal: enough rated reviews that both
            // the low and the high end are genuinely represented.
            'divided' => $rated >= 3 && $low >= 1 && $high >= 1,
        ];
    }

    /**
     * Recurring themes, extracted mechanically: document frequency over
     * content words (stopwords and digit-bearing tokens removed). Polarity is
     * derived from the ratings of the reviews that actually mention the term
     * - a term praised in some reviews and complained about in others is
     * reported as mixed, never flattened into one side.
     *
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array<int, array{label: string, count: int, polarity: string}>
     */
    public static function themes(array $reviews, int $minReviews = 2, int $limit = 6): array
    {
        $minReviews = max(1, $minReviews);
        $limit = max(1, $limit);

        $docs = []; // term => ['df' => int, 'ratings' => int[]]
        foreach ($reviews as $review) {
            $text = mb_strtolower((string) ($review['title'] ?? '').' '.(string) ($review['body'] ?? ''));
            $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
            $tokens = preg_split('/\s+/u', $text) ?: [];

            $unique = [];
            foreach ($tokens as $token) {
                $token = trim($token);
                $length = mb_strlen($token);
                if ($length < 3 || $length > 24) {
                    continue;
                }
                if (preg_match('/\d/', $token) === 1) {
                    continue;
                }
                if (in_array($token, self::STOP, true)) {
                    continue;
                }
                $unique[$token] = true;
            }

            $rating = is_numeric($review['rating'] ?? null) ? (int) $review['rating'] : null;
            $rating = ($rating !== null && $rating >= 1 && $rating <= 5) ? $rating : null;

            foreach (array_keys($unique) as $term) {
                $docs[$term]['df'] = ($docs[$term]['df'] ?? 0) + 1;
                if ($rating !== null) {
                    $docs[$term]['ratings'][] = $rating;
                }
            }
        }

        $out = [];
        foreach ($docs as $term => $info) {
            if ((int) $info['df'] < $minReviews) {
                continue;
            }
            $out[] = [
                'label' => $term,
                'count' => (int) $info['df'],
                'polarity' => self::polarity($info['ratings'] ?? []),
            ];
        }

        usort($out, function ($a, $b) {
            return [$b['count'], $a['label']] <=> [$a['count'], $b['label']];
        });

        return array_slice($out, 0, $limit);
    }

    /**
     * Polarity of one theme from the ratings of the reviews naming it:
     * conflicting ends -> mixed; otherwise the average decides; unrated
     * mentions stay neutral instead of being guessed.
     *
     * @param  array<int, int>  $ratings
     */
    private static function polarity(array $ratings): string
    {
        if ($ratings === []) {
            return 'neutral';
        }

        $low = 0;
        $high = 0;
        $sum = 0;
        foreach ($ratings as $rating) {
            $sum += $rating;
            if ($rating <= 2) {
                $low++;
            }
            if ($rating >= 4) {
                $high++;
            }
        }

        if ($low >= 1 && $high >= 1) {
            return 'mixed';
        }

        $average = $sum / count($ratings);
        if ($average >= 4) {
            return 'positive';
        }
        if ($average <= 2) {
            return 'negative';
        }

        return 'neutral';
    }

    /* ------------------------------------------------------------------ */
    /* Localized blocks (pure)                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Verified-fact statements built only from platform records.
     *
     * @param  array<string, mixed>|null  $business  public business row (+ certified_at)
     * @param  array<string, mixed>|null  $activity  order aggregates
     * @return array<int, string>
     */
    public static function facts(?array $business, ?array $activity, string $locale): array
    {
        if ($business === null) {
            return [];
        }

        $out = [];

        if (! empty($business['is_certified'])) {
            $date = substr((string) ($business['certified_at'] ?? ''), 0, 10);
            $out[] = $date !== ''
                ? self::msg('fact_certified', $locale, ['date' => $date])
                : self::msg('fact_certified_no_date', $locale);
        } else {
            $out[] = self::msg('fact_not_certified', $locale);
        }

        if (isset($business['product_count'])) {
            $out[] = self::msg('fact_products', $locale, ['count' => (int) $business['product_count']]);
        }

        if (is_array($activity)) {
            $out[] = self::msg('fact_orders', $locale, [
                'total' => (int) ($activity['total'] ?? 0),
                'completed' => (int) ($activity['completed'] ?? 0),
                'open' => (int) ($activity['open'] ?? 0),
            ]);

            $last = substr((string) ($activity['last_order_at'] ?? ''), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $last) === 1) {
                $out[] = self::msg('fact_last_order', $locale, ['date' => $last]);
            }
        }

        return $out;
    }

    /**
     * Opinion statements: every line is anchored to a real sample size or
     * theme count, so the client can never present a feeling as data.
     *
     * @param  array<string, mixed>  $stats
     * @param  array<int, array{label: string, count: int, polarity: string}>  $themes
     * @return array<int, string>
     */
    public static function opinions(array $stats, array $themes, string $locale): array
    {
        if ((int) $stats['sample_size'] === 0) {
            return [self::msg('opinion_none', $locale)];
        }

        $out = [self::msg('opinion_sample', $locale, [
            'count' => (int) $stats['sample_size'],
            'rated' => (int) $stats['rated_count'],
            'average' => $stats['rating_average'] !== null
                ? number_format((float) $stats['rating_average'], 1, '.', '')
                : '—',
        ])];

        $from = (string) ($stats['period']['from'] ?? '');
        $to = (string) ($stats['period']['to'] ?? '');
        if ($from !== '' && $to !== '') {
            $out[] = self::msg('opinion_period', $locale, ['from' => $from, 'to' => $to]);
        }

        foreach ($themes as $theme) {
            $out[] = self::msg('opinion_theme', $locale, [
                'count' => (int) $theme['count'],
                'theme' => (string) $theme['label'],
                'polarity' => self::msg('polarity_'.$theme['polarity'], $locale),
            ]);
        }

        return $out;
    }

    /**
     * Deterministic one-paragraph summary from the same numbers the client
     * shows. Used verbatim when the provider is off/unavailable, and as the
     * honest base the model may only reword.
     *
     * @param  array<string, mixed>  $stats
     * @param  array<int, array{label: string, count: int, polarity: string}>  $themes
     * @param  array<string, mixed>|null  $business
     */
    public static function deterministicSummary(array $stats, array $themes, ?array $business, string $locale): string
    {
        if ((int) $stats['sample_size'] === 0) {
            $text = self::msg('summary_no_reviews', $locale);
            if ($business !== null) {
                $state = ! empty($business['is_certified'])
                    ? self::msg('fact_certified_no_date', $locale)
                    : self::msg('fact_not_certified', $locale);
                $text .= ' '.$state;
            }

            return $text;
        }

        $text = self::msg('summary_sample', $locale, [
            'count' => (int) $stats['sample_size'],
            'average' => $stats['rating_average'] !== null
                ? number_format((float) $stats['rating_average'], 1, '.', '')
                : '—',
        ]);

        $labels = array_slice(array_column($themes, 'label'), 0, 3);
        if ($labels !== []) {
            $text .= ' '.self::msg('summary_themes', $locale, ['themes' => implode(', ', $labels)]);
        }

        if (! empty($stats['divided'])) {
            $text .= ' '.self::msg('summary_divided', $locale);
        }

        return $text;
    }

    /**
     * Original review rows (already filtered to is_published upstream) with
     * a bounded body, so the client can offer "read the originals" without a
     * second fetch of private data.
     *
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array<int, array<string, mixed>>
     */
    public static function itemRows(array $reviews): array
    {
        $out = [];
        foreach (array_slice($reviews, 0, self::ITEM_LIMIT) as $review) {
            $body = trim((string) ($review['body'] ?? ''));
            $truncated = mb_strlen($body) > 280;
            $out[] = [
                'id' => $review['id'] ?? null,
                'title' => $review['title'] ?? null,
                'body' => $truncated ? mb_substr($body, 0, 280).'…' : $body,
                'truncated' => $truncated,
                'rating' => is_numeric($review['rating'] ?? null) ? (int) $review['rating'] : null,
                'display_name' => $review['display_name'] ?? null,
                'published_at' => $review['published_at'] ?? null,
            ];
        }

        return $out;
    }

    /** Localized limitation lines shown with every summary. */
    public static function limitations(string $locale, int $cacheMinutes): array
    {
        return [
            self::msg('limit_opinions', $locale),
            self::msg('limit_facts', $locale),
            self::msg('limit_scope', $locale),
            self::msg('limit_private', $locale),
            self::msg('limit_cache', $locale, ['minutes' => $cacheMinutes]),
        ];
    }

    /** Localized source labels (what the summary was built from). */
    public static function sources(string $locale): array
    {
        return [
            self::msg('src_reviews', $locale),
            self::msg('src_business', $locale),
            self::msg('src_orders', $locale),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Model-assisted rewording (optional; never adds data)                */
    /* ------------------------------------------------------------------ */

    /**
     * Ask the model to REWORD the deterministic numbers as one short
     * paragraph in the requested locale. The reply is dropped unless every
     * number in it already appears in the data we supplied - the model can
     * phrase the truth, never extend it.
     *
     * @param  array<string, mixed>  $stats
     * @param  array<int, string>  $facts
     * @param  array<int, string>  $opinions
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array{summary: ?string, ai: array<string, mixed>}
     */
    public static function summarizeWithAi(array $stats, array $facts, array $opinions, array $reviews, string $locale, ?string $ip): array
    {
        $ai = [
            'available' => false,
            'degraded' => true,
            'code' => null,
            'reason' => null,
            'meta' => null,
        ];

        $client = new GeminiClient;
        if (! $client->isEnabled()) {
            $ai['code'] = 'AI_DISABLED';
            $ai['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return ['summary' => null, 'ai' => $ai];
        }
        if (! $client->isConfigured()) {
            $ai['code'] = 'AI_NOT_CONFIGURED';
            $ai['reason'] = AiException::reasonForCode('AI_NOT_CONFIGURED', $locale);

            return ['summary' => null, 'ai' => $ai];
        }

        $titles = [];
        $chars = 0;
        foreach (array_slice($reviews, 0, 12) as $review) {
            $line = trim((string) ($review['title'] ?? '')).' — '.trim(mb_substr((string) ($review['body'] ?? ''), 0, 160));
            if (mb_strlen($line) + $chars > 1800) {
                break;
            }
            $chars += mb_strlen($line);
            $titles[] = $line;
        }

        $data = [
            'facts' => $facts,
            'opinions' => $opinions,
            'stats' => [
                'sample_size' => $stats['sample_size'],
                'rated_count' => $stats['rated_count'],
                'rating_average' => $stats['rating_average'],
                'rating_distribution' => $stats['rating_distribution'],
                'period' => $stats['period'],
                'divided' => $stats['divided'],
            ],
            'review_excerpts' => $titles,
        ];

        $instruction = implode("\n", [
            'You restate verified business-trust statistics as ONE short paragraph (1-3 sentences) for a customer.',
            'Return STRICT JSON only: {"summary": string}.',
            'Rules: use ONLY the figures and statements in the data below; never invent reviews, ratings, certifications, dates or numbers;',
            'write the summary in the language with locale code "'.$locale.'" (sw=Kiswahili, en=English, fr=French, es=Spanish, zh=Chinese, hi=Hindi, ur=Urdu, de=German);',
            'if a figure is absent from the data, do not mention it. No markdown, no line breaks.',
            'DATA: '.json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);

        try {
            $result = $client->generateJson(
                [['text' => 'Restate the verified trust statistics.']],
                $instruction,
                [
                    'capability' => self::CAPABILITY.'_summary',
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 320,
                ],
            );

            $ai['available'] = true;
            $ai['degraded'] = false;
            $ai['meta'] = $result->meta();

            $summary = self::sanitizeModelSummary($result->data, $facts, $opinions, $stats);
            if ($summary === null) {
                // A reply we cannot verify is worse than none: stay honest.
                $ai['code'] = 'AI_UNSAFE_OUTPUT';
                $ai['reason'] = AiException::reasonForCode('AI_UNSAFE_OUTPUT', $locale);
                $ai['degraded'] = true;

                return ['summary' => null, 'ai' => $ai];
            }

            return ['summary' => $summary, 'ai' => $ai];
        } catch (AiException $error) {
            $ai['code'] = $error->errorCode;
            $ai['reason'] = AiException::reasonForCode($error->errorCode, $locale);

            return ['summary' => null, 'ai' => $ai];
        }
    }

    /**
     * Accept only a bounded single-paragraph string whose numbers are a
     * subset of the numbers present in the data we provided.
     *
     * @param  array<int, string>  $facts
     * @param  array<int, string>  $opinions
     * @param  array<string, mixed>  $stats
     */
    private static function sanitizeModelSummary($raw, array $facts, array $opinions, array $stats): ?string
    {
        if (! is_array($raw) || ! is_string($raw['summary'] ?? null)) {
            return null;
        }

        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $raw['summary']) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text) < 12 || mb_strlen($text) > 500) {
            return null;
        }

        $allowed = self::allowedNumbers(array_merge($facts, $opinions, [
            json_encode($stats['rating_distribution'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($stats['period'] ?? [], JSON_UNESCAPED_UNICODE),
            (string) ($stats['rating_average'] ?? ''),
            (string) ($stats['sample_size'] ?? 0),
            (string) ($stats['rated_count'] ?? 0),
        ]));

        if (preg_match_all('/\d+(?:[.,]\d+)?/u', $text, $matches) > 0) {
            foreach ($matches[0] as $number) {
                $normalized = str_replace(',', '.', $number);
                if (! in_array($normalized, $allowed, true)) {
                    return null; // a number we never supplied - reject
                }
            }
        }

        return $text;
    }

    /** @return array<int, string> normalized (dot) numbers found in the inputs */
    private static function allowedNumbers(array $chunks): array
    {
        $allowed = [];
        foreach ($chunks as $chunk) {
            if (! is_string($chunk)) {
                continue;
            }
            if (preg_match_all('/\d+(?:[.,]\d+)?/', $chunk, $matches) > 0) {
                foreach ($matches[0] as $number) {
                    $allowed[str_replace(',', '.', $number)] = true;
                }
            }
        }

        return array_keys($allowed);
    }

    /* ------------------------------------------------------------------ */
    /* Cache (per locale, version-bumped on relevant public changes)       */
    /* ------------------------------------------------------------------ */

    private const VER_REVIEWS = 'dm_trust:ver:reviews';
    private const VER_BUSINESS = 'dm_trust:ver:biz:';
    private const CACHE_ITEM = 'dm_trust:item:';

    /** A published/unpublished review changes every summary's opinion half. */
    public static function invalidateReviews(): void
    {
        self::bump(self::VER_REVIEWS);
    }

    /** A certify/uncertify changes one business' fact half. */
    public static function invalidateBusiness(string $businessId): void
    {
        self::bump(self::VER_BUSINESS.$businessId);
    }

    /**
     * Cached payload for this locale+business, or null. The key embeds both
     * version numbers, so a bump makes every stale entry unreachable (it then
     * ages out via TTL) without scanning the store.
     */
    public static function cached(string $locale, ?string $businessId): ?array
    {
        try {
            $payload = self::store()->get(self::cacheKey($locale, $businessId));

            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function storePayload(string $locale, ?string $businessId, array $payload, int $ttlMinutes): void
    {
        try {
            self::store()->put(self::cacheKey($locale, $businessId), $payload, max(1, $ttlMinutes) * 60);
        } catch (\Throwable $e) {
            // A cache that cannot be written must never break the endpoint.
        }
    }

    private static function cacheKey(string $locale, ?string $businessId): string
    {
        return self::CACHE_ITEM.$locale.':'.($businessId ?? 'platform')
            .':'.self::version(self::VER_REVIEWS)
            .':'.($businessId !== null ? self::version(self::VER_BUSINESS.$businessId) : '0');
    }

    private static function version(string $key): string
    {
        try {
            $value = self::store()->get($key);

            return is_string($value) && $value !== '' ? $value : '0';
        } catch (\Throwable $e) {
            return '0';
        }
    }

    private static function bump(string $key): void
    {
        try {
            self::store()->put($key, (string) time(), 60 * 60 * 24 * 30);
        } catch (\Throwable $e) {
            // Worst case the old entry serves until its short TTL expires.
        }
    }

    private static function store()
    {
        return Cache::store('file');
    }

    /* ------------------------------------------------------------------ */
    /* Localized server-side strings                                       */
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
        foreach (array_keys(self::MESSAGES['en']) as $key) {
            foreach (array_keys(self::MESSAGES) as $locale) {
                if (trim((string) (self::MESSAGES[$locale][$key] ?? '')) === '') {
                    return false;
                }
            }
        }

        return true;
    }
}
