<?php

namespace App\Services\Ai;

/**
 * Capability #21 - Public AI Concierge.
 *
 * A visitor who is not signed in asks the public pages what Duka Mkononi is,
 * how the marketplace works, how to get started or where to download the app,
 * and gets an answer built ONLY from approved public information plus the same
 * public catalogue the shop pages already show.
 *
 * WHAT THIS SERVICE IS
 * --------------------
 * The knowledge base, the scope guard, the search over public rows and the
 * localized link set. It is pure: arrays in, arrays out - no model, no HTTP, no
 * database, no Laravel helpers - so the whole contract is unit-testable and no
 * visitor ever sees a number this service did not receive as a row.
 *
 * WHAT IT CANNOT DO (by construction)
 * -----------------------------------
 *  - It cannot reach private data. Search functions only ever receive rows the
 *    caller read through PublicCatalog (public businesses, sellable products),
 *    and `PUBLIC_PRODUCT_FIELDS` / `PUBLIC_BUSINESS_FIELDS` STRIP every field
 *    that is not part of the public contract, so a catalogue change cannot leak
 *    a phone number through this path.
 *  - It cannot invent a link. Every URL comes from `SECTION_LINKS` (internal
 *    paths this website serves) or `DEFAULT_EXTERNAL_LINKS` (the APK, email,
 *    phone and WhatsApp links the public footer/navbar already publish).
 *    Neither a question nor a model answer can add or change one.
 *  - It cannot answer about the platform's internals. `OUT_OF_SCOPE` covers
 *    administration, credentials, technical internals and other people's private
 *    data; those questions get a polite refusal plus the fallback links instead
 *    of an answer.
 *  - It cannot go off-topic: a question matching no approved topic gets the
 *    honest "I did not find that in the public information" answer and the
 *    section links - never a guess.
 */

class PublicConcierge
{
    public const CAPABILITY = 'public_concierge';

    /**
     * The approved public topics. Nothing else is answerable.
     */
    public const TOPICS = [
        'what_is',
        'how_to_buy',
        'how_to_sell',
        'get_started',
        'download_app',
        'payments',
        'delivery_tracking',
        'trust_safety',
        'contact_support',
    ];

    /** Internal sections of THIS website. A question can never add one. */
    public const SECTION_LINKS = [
        'home' => '/',
        'shop' => '/shop',
        'businesses' => '/businesses',
        'advertisements' => '/advertisements',
        'track_orders' => '/track-orders',
        'login' => '/login',
        'client_signup' => '/client-signup',
        'business_signup' => '/admin-signup',
        'forgot' => '/forgot',
    ];

    /**
     * The external links the public website itself publishes (navbar/footer).
     * Mirrors resources/views/partials/site-footer.blade.php and
     * site-navbar.blade.php - no invented phone, WhatsApp or download URL.
     */
    public const DEFAULT_EXTERNAL_LINKS = [
        'download_apk' => 'https://expo.dev/artifacts/eas/ZRIDMBDq4PlR47LI2j_iuOItHZs6sz6DdPyFFJyqO8w.apk',
        'contact_email' => 'mailto:info@dukamkononi.com',
        'contact_phone' => 'tel:0757071967',
        'contact_whatsapp' => 'https://wa.me/255757071967',
    ];

    /** Which link ids each topic offers (order = display order). */
    public const TOPIC_LINKS = [
        'what_is' => ['shop', 'businesses'],
        'how_to_buy' => ['shop', 'track_orders'],
        'how_to_sell' => ['business_signup', 'businesses'],
        'get_started' => ['client_signup', 'login', 'forgot'],
        'download_app' => ['download_apk', 'home'],
        'payments' => ['track_orders', 'contact_whatsapp'],
        'delivery_tracking' => ['track_orders', 'contact_whatsapp'],
        'trust_safety' => ['businesses', 'contact_email'],
        'contact_support' => ['contact_whatsapp', 'contact_email', 'contact_phone'],
    ];

    /** The public fields a product result may carry - everything else is dropped. */
    public const PUBLIC_PRODUCT_FIELDS = [
        'id', 'name', 'description', 'category', 'image_url', 'unit',
        'selling_price', 'currency', 'stock', 'available',
        'business_id', 'business_name', 'business_location',
        'business_logo_url', 'business_is_certified',
    ];

    /** The public fields a business result may carry - everything else is dropped. */
    public const PUBLIC_BUSINESS_FIELDS = [
        'id', 'business_name', 'business_location', 'business_type',
        'business_description', 'business_logo_url', 'is_certified', 'product_count',
    ];

    /** A single answer may name at most this many products / businesses. */
    public const MAX_PRODUCT_RESULTS = 6;

    public const MAX_BUSINESS_RESULTS = 4;

    /** At most this many links are ever returned with one answer. */
    public const MAX_LINKS = 4;

    /** The fallback sent with a refusal or an unanswered question. */
    public const FALLBACK_LINKS = ['shop', 'businesses', 'track_orders', 'contact_whatsapp'];

    /**
     * Keys that must never appear in a payload (private person/business data,
     * credentials, technical internals). Checked by `findPrivateFields()`.
     * NOTE: business_name / business_location / business_id are NOT here - they
     * are public catalogue facts the shop pages already publish.
     */
    public const PRIVATE_KEYS = [
        'email', 'phone', 'full_name', 'password', 'token', 'jwt', 'api_key',
        'secret', 'user_id', 'seller_id', 'customer_id', 'national_id', 'otp',
        'ip_address', 'user_agent', 'seller_phone', 'business_phone',
        'business_email', 'staff', 'cashier_id', 'session_id',
    ];

    /**
     * Questions about the platform's internals or other people's private data.
     * Deliberately multilingual: a visitor may ask in any of the 8 locales.
     */
    public const OUT_OF_SCOPE_PATTERNS = [
        // Administration / internals / technical
        '/\b(super\s*admin|system\s*admin|admin panel|back\s*office|internal|database|sql|query|server|api key|credentials?|config(uration)?|deploy|log\s?files?)\b/i',
        '/(msimamizi\s*mkuu|mfumo\s*wa\s*ndani|hifadhidata|seva|siri|kibali|kumbukumbu\s*za\s*ndani)/i',
        '/(super\s*admin|administrateur\s*syst[eè]me|base\s*de\s*donn[eé]es|serveur|interne|identifiants?|cl[eé]\s*api)/i',
        '/(सुपर\s*एडमिन|सिस्टम\s*एडमिन|डेटाबेस|सर्वर|आंतरिक|एपीआई\s*कुंजी|क्रेडेंशियल)/u',
        '/(super\s*admin|administrador\s*del\s*sistema|base\s*de\s*datos|servidor|interno|credenciales?|clave\s*de\s*api)/i',
        '/(سپر\s*ایڈمن|سسٹم\s*ایڈمن|ڈیٹا\s*بیس|سرور|اندرونی|api\s*کلید|اسناد)/u',
        '/(super\s*admin|systemadministrator|datenbank|server|intern|anmeldedaten|api[-\s]?schl[uü]ssel)/i',
        '/(超级管理员|系统管理员|数据库|服务器|内部|api\s*密钥|凭证)/u',
        // Other people's private data / another business's books
        '/\b(someone\'?s|another\s+(user|business|customer)|other\s+(user|business)|their)\s+(email|phone|password|orders?|sales|revenue|profit|data)\b/i',
        '/(barua\s*pepe|namba\s*ya\s*simu|nywila|password)\s+ya\s+(mtumiaji|mteja|muuzaji|biashara)\s+(mw(m)?ingine|wengine)/i',
        '/\b(list|give me|show me)\s+(all\s+)?(users|customers|sellers|passwords|emails|phones)\b/i',
        '/\b(contrase[ñn]a|correo|tel[eé]fono|datos|pedidos)\s+de\s+(otro|otra|otros|otras)\s+(usuario|persona|empresa|cliente)/iu',
        '/\b(mot\s+de\s+passe|e-?mail|t[ée]l[ée]phone|donn[ée]es|commandes)\s+d\'?(un|une|autres?)\s+(utilisateur|personne|entreprise|client)/iu',
        '/\b(passwort|e-?mail|telefon|daten|bestellungen)\s+(eines?\s+)?(anderen|anderer|fremden|anderes)\s+(nutzers?|person|betriebs?|kunden?)/iu',
        '/(पासवर्ड|ईमेल|फोन|फ़ोन|डेटा|जानकारी)[\s\S]{0,20}(किसी\s+अन्य|दूसरे|अन्य)\s*(उपयोगकर्ता|व्यक्ति|ग्राहक|व्यवसाय)?/u',
        '/(پاس\s*ورڈ|ای\s*میل|فون|ڈیٹا)[\s\S]{0,20}(کسی\s+اور|دوسرے)\s*(صارف|شخص|گاہک|کاروبار)?/u',
        '/(密码|邮箱|电子邮件|电话|数据)[\s\S]{0,12}(其他|别人|另一個|另一个)\s*(用户|顾客|商家|人)?/u',
        // Non-commerce
        '/\b(write|draft)\s+(me\s+)?(an?\s+)?(essay|poem|song|homework|code|program)\b/i',
    ];

    /**
     * The vocabulary that puts a question inside this assistant's scope. A
     * question about the MARKETPLACE contains at least one of these; "what is
     * the weather tomorrow" does not, so the generic "what is" reading cannot
     * drag an off-topic question into a public answer. Multilingual on purpose.
     */
    public const COMMERCE_TERMS = [
        'duka mkononi', 'mkononi', 'marketplace', 'market', 'shop', 'store', 'product', 'products',
        'buy', 'sell', 'seller', 'order', 'delivery', 'payment', 'pay', 'price', 'account', 'app',
        'application', 'website', 'business', 'customer', 'cart', 'advertisement', 'review', 'catalogue', 'catalog',
        'duka', 'bidhaa', 'nunua', 'uza', 'muuzaji', 'agizo', 'usafirishaji', 'malipo', 'akaunti', 'programu', 'tovuti', 'biashara', 'bei', 'mteja',
        'boutique', 'acheter', 'vendre', 'commande', 'livraison', 'paiement', 'compte', 'site web', 'entreprise', 'produit', 'prix',
        'दुकान', 'उत्पाद', 'खरीद', 'बिक्री', 'व्यवसाय', 'ऑर्डर', 'डिलीवरी', 'भुगतान', 'खाता', 'ऐप', 'वेबसाइट', 'ग्राहक', 'कीमत',
        'tienda', 'comprar', 'vender', 'pedido', 'entrega', 'pago', 'cuenta', 'sitio web', 'empresa', 'producto', 'precio', 'mercado', 'cliente',
        'دکان', 'پروڈکٹ', 'خرید', 'فروخت', 'کاروبار', 'آرڈر', 'ڈیلیوری', 'ادائیگی', 'اکاؤنٹ', 'ایپ', 'ویب سائٹ', 'گاہک', 'قیمت',
        'laden', 'kaufen', 'verkaufen', 'bestellung', 'lieferung', 'zahlung', 'konto', 'website', 'unternehmen', 'produkt', 'preis', 'markt', 'kunde',
        '商家', '商品', '购买', '出售', '订单', '配送', '支付', '账号', '应用', '网站', '顾客', '价格', '市场',
    ];

    /**
     * Unambiguous intents that outrank the keyword count. A question with a
     * tracking verb is a DELIVERY question even if it also says "order":
     * otherwise "how do I track my order?" was answered as "how to buy",
     * because "order" and "track" scored the same and the buying topic came
     * first. Each word here is a verb or phrase that only means this topic.
     */
    public const STRONG_HINTS = [
        'delivery_tracking' => [
            'track my order', 'track order', 'tracking', 'track', 'where is my order', 'order status',
            'kufuatilia', 'nifuatilie', 'fuatilia', 'agizo langu', 'usafirishaji',
            'suivre', 'suivi', 'où est ma commande',
            'ट्रैक', 'ऑर्डर कहाँ',
            'seguir', 'sigo', 'seguimiento', 'dónde está mi pedido',
            'ٹریک', 'نگرانی', 'آرڈر کہاں',
            'verfolg', 'wo ist meine bestellung',
            '追踪', '订单状态', '配送到哪里',
        ],
        'download_app' => [
            'download', 'install', 'apk', 'pakua', 'kuweka programu',
            'télécharger', 'installer',
            'डाउनलोड', 'इंस्टॉल',
            'descargar', 'instalar',
            'ڈاؤن لوڈ', 'انسٹال',
            'herunterladen', 'installieren',
            '下载', '安装',
        ],
    ];

    /** Does this question mention anything this assistant is allowed to cover? */
    public static function mentionsCommerce(string $question): bool
    {
        $text = self::normalise($question);

        foreach (self::COMMERCE_TERMS as $term) {
            $needle = self::normalise($term);
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Topic keywords per locale. Deterministic: the first topic with the most
     * hits wins (ties broken by `TOPICS` order - the earlier topic is the more
     * specific reading). A question with no hit is honestly unanswered.
     *
     * @return array<string, array<int, string>>
     */
    private static function topicKeywords(): array
    {
        return [
            'what_is' => [
                'what is', 'what does', 'about', 'who are you', 'marketplace', 'duka mkononi is',
                'ni nini', 'kuhusu', 'soko', 'duka mkononi ni',
                "qu'est", 'quoi', 'à propos', 'marché',
                'क्या है', 'कौन', 'बाज़ार', 'बाजार',
                'qué es', 'que es', 'acerca', 'quiénes', 'mercado',
                'کیا ہے', 'کون', 'بازار',
                'was ist', 'über', 'wer', 'marktplatz',
                '是什么', '是什么？', '关于', '谁', '市场',
            ],
            'how_to_buy' => [
                'how to buy', 'how do i buy', 'buy', 'purchase', 'order', 'place an order', 'shopping', 'shop for',
                'ninunue', 'kununua', 'nunua', 'kuagiza', 'agiza',
                'acheter', 'commander', 'achat', 'commande',
                'कैसे खरीद', 'खरीदना', 'ऑर्डर', 'खरीद',
                'cómo comprar', 'comprar', 'compro', 'pedido', 'pedir',
                'کیسے خرید', 'خریدنا', 'آرڈر',
                'kaufen', 'bestellen', 'wie kaufe ich',
                '怎么买', '如何购买', '购买', '下单', '订货',
            ],
            'how_to_sell' => [
                'how to sell', 'sell', 'seller', 'start selling', 'open a shop', 'register my business', 'vendor', 'business account',
                'kuuza', 'muuzaji', 'fungua duka', 'biashara yangu', 'sajili biashara',
                'vendre', 'vendeur', 'ouvrir une boutique',
                'verkauf', 'verkaufe', 'gewerbe',
                'कैसे बेच', 'बेचना', 'विक्रेता', 'दुकान',
                'vender', 'vendedor', 'abrir una tienda',
                'کیسے بیچ', 'بیچنا', 'بیچنے والا', 'دکان',
                'verkaufen', 'wie verkaufe ich', 'laden eröffnen',
                '怎么卖', '出售', '卖家', '开店',
            ],
            'get_started' => [
                'get started', 'sign up', 'signup', 'create an account', 'register', 'log in', 'login', 'password', 'forgot', 'verify', 'verification code',
                'jisajili', 'fungua akaunti', 'ingia', 'nywila', 'umesahau', 'msimbo',
                "s'inscrire", 'cr\u00e9er un compte', 'connexion', 'mot de passe', 'oubli\u00e9',
                'साइन अप', 'खाता', 'लॉगिन', 'पासवर्ड', 'भूल',
                'registrarse', 'crear una cuenta', 'iniciar sesión', 'contraseña', 'olvidé',
                'سائن اپ', 'اکاؤنٹ', 'لاگ ان', 'پاس ورڈ', 'بھول',
                'registrier', 'konto erstellen', 'anmeld', 'passwort', 'vergessen',
                '注册', '登录', '账号', '密码', '忘记',
            ],
            'download_app' => [
                'download', 'install', 'app', 'apk', 'application', 'play store', 'android',
                'pakua', 'programu', 'simu', 'kuweka',
                'télécharger', 'installer', 'application',
                'डाउनलोड', 'ऐप', 'इंस्टॉल',
                'descargar', 'instalar', 'aplicación', 'app',
                'ڈاؤن لوڈ', 'ایپ', 'انسٹال',
                'herunterladen', 'installieren', 'app',
                '下载', '安装', '应用',
            ],
            'payments' => [
                'pay', 'payment', 'how do i pay', 'mobile money', 'mpesa', 'm-pesa', 'tigo', 'airtel', 'cash', 'price of paying', 'refund',
                'kulipa', 'malipo', 'malipo ya simu', 'fedha', 'pesa',
                'paiement', 'payer', 'mobile money', 'remboursement',
                'भुगतान', 'कैसे भुगतान', 'पैसे',
                'pagar', 'pago', 'dinero móvil', 'reembolso',
                'ادائیگی', 'کیسے ادائیگی', 'رقم',
                'bezahlen', 'zahlung', 'handy geld', 'rückerstattung',
                '支付', '付款', '退款',
            ],
            'delivery_tracking' => [
                'delivery', 'deliver', 'shipping', 'ship', 'track', 'tracking', 'where is my order', 'order status', 'courier', 'pickup',
                'usafirishaji', 'kupeleka', 'kufuatilia', 'agizo langu', 'wapi', 'kuchukua',
                'livraison', 'suivi', 'où est', 'statut',
                'डिलीवरी', 'ट्रैक', 'ऑर्डर कहाँ', 'स्थिति',
                'entrega', 'seguimiento', 'dónde está', 'estado',
                'ڈیلیوری', 'ٹریک', 'آرڈر کہاں', 'حالت',
                'lieferung', 'verfolg', 'wo ist', 'status',
                '配送', '送货', '追踪', '订单状态',
            ],
            'trust_safety' => [
                'safe', 'safety', 'trust', 'trusted', 'verified', 'certified', 'certification', 'scam', 'fraud', 'fake', 'reviews', 'rating', 'legit',
                'salama', 'kuaminika', 'imethibitishwa', 'uthibitisho', 'utapeli', 'uongo', 'maoni',
                'sûr', 'confiance', 'vérifié', 'certifié', 'arnaque', 'avis',
                'सुरक्षित', 'भरोसा', 'प्रमाणित', 'धोखा', 'समीक्षा',
                'seguro', 'confianza', 'verificado', 'certificado', 'estafa', 'reseñas',
                'محفوظ', 'بھروسہ', 'تصدیق شدہ', 'فراڈ', 'تبصرے',
                'sicher', 'vertrauen', 'verifiziert', 'zertifiziert', 'betrug', 'bewertungen',
                '安全', '可信', '认证', '诈骗', '评价',
            ],
            'contact_support' => [
                'contact', 'support', 'help me', 'reach you', 'phone number', 'email address', 'whatsapp', 'talk to a person', 'complaint', 'problem with',
                'wasiliana', 'msaada', 'nisaidie', 'namba ya simu', 'barua pepe', 'lalamiko', 'tatizo',
                'contacter', 'assistance', 'aide', 'numéro', 'e-mail', 'réclamation', 'problème',
                'संपर्क', 'सहायता', 'मदद', 'फ़ोन', 'ईमेल', 'शिकायत',
                'contacto', 'ayuda', 'soporte', 'teléfono', 'correo', 'queja', 'problema',
                'رابطہ', 'مدد', 'فون نمبر', 'ای میل', 'شکایت',
                'kontakt', 'hilfe', 'support', 'telefonnummer', 'e-mail', 'beschwerde',
                '联系', '帮助', '支持', '电话', '邮箱', '投诉',
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Intent + scope                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Read a public question into an approved topic, a product/business search
     * or an honest "I cannot answer that".
     *
     * @return array{topic: ?string, intent: string, query: ?string, out_of_scope: bool, matched: array<int, string>, confidence: string}
     */
    public static function parse(string $question, array $options = []): array
    {
        $text = self::normalise($question);
        $matched = [];

        if (self::isOutOfScope($question)) {
            return [
                'topic' => null,
                'intent' => 'refused',
                'query' => null,
                'out_of_scope' => true,
                'matched' => [],
                'confidence' => 'high',
            ];
        }

        /* Unambiguous intents first (see STRONG_HINTS): a tracking question must
         * not be answered with the buying topic just because both words scored
         * the same. */
        $topic = null;
        $bestHits = 0;
        foreach (self::STRONG_HINTS as $candidate => $words) {
            foreach ($words as $word) {
                $needle = self::normalise($word);
                if ($needle !== '' && str_contains($text, $needle)) {
                    $topic = $candidate;
                    $matched = [$word];
                    $bestHits = PHP_INT_MAX;
                    break 2;
                }
            }
        }

        foreach (self::topicKeywords() as $candidate => $keywords) {
            if ($bestHits === PHP_INT_MAX) {
                break;
            }
            $hits = [];
            foreach ($keywords as $keyword) {
                $needle = self::normalise($keyword);
                if ($needle !== '' && str_contains($text, $needle)) {
                    $hits[] = $keyword;
                }
            }
            /* Strictly more hits wins, so TOPICS order decides a tie (the
             * earlier topic is the more specific reading). */
            if (count($hits) > $bestHits) {
                $bestHits = count($hits);
                $topic = $candidate;
                $matched = $hits;
            }
        }

        /* The generic "what is ..." reading is only allowed when the question
         * is actually about the marketplace - otherwise "what is the weather
         * tomorrow" would be answered with the marketplace introduction. */
        if ($topic === 'what_is' && ! self::mentionsCommerce($text) && self::lookupQuery($question) === null) {
            $topic = null;
            $matched = [];
        }

        /* "Find me shops that sell X" / "do you have paint?" is a catalogue
         * question: the visitor wants actual rows. But a question that ALSO
         * matched an approved topic is answered from the knowledge base first
         * ("how do I buy?" is a how-to question, not a product hunt), so the
         * search intent only applies when no topic matched. */
        if ($topic !== null) {
            return [
                'topic' => $topic,
                'intent' => 'kb',
                'query' => null,
                'out_of_scope' => false,
                'matched' => $matched,
                'confidence' => 'high',
            ];
        }

        $lookup = self::lookupQuery($question);
        if ($lookup !== null) {
            return [
                'topic' => null,
                'intent' => 'search',
                'query' => $lookup,
                'out_of_scope' => false,
                'matched' => [],
                'confidence' => 'medium',
            ];
        }

        return [
            'topic' => null,
            'intent' => 'unknown',
            'query' => null,
            'out_of_scope' => false,
            'matched' => [],
            'confidence' => 'low',
        ];
    }

    /** Is this question about our internals or someone else's private data? */
    public static function isOutOfScope(string $question): bool
    {
        foreach (self::OUT_OF_SCOPE_PATTERNS as $pattern) {
            if (preg_match($pattern, $question) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a catalogue search term from "find me X" style questions, or null
     * when the question is not a lookup. The term is only ever used against
     * PUBLIC rows and is capped in length.
     */
    public static function lookupQuery(string $question, int $maxChars = 60): ?string
    {
        $text = trim($question);
        $patterns = [
            '/\b(?:find|search|looking for|show me|do you have|i need|i want|where can i (?:buy|find|get))\b(.*)$/iu',
            '/\b(?:nipe|nitafutie|naomba|ninataka|ninaitaji|nionyeshe|wapi naweza (?:kununua|kupata))\b(.*)$/iu',
            '/\b(?:je cherche|montrez-moi|montre-moi|montrez|montre|trouver|chercher|avez-vous|j\'ai besoin)\b(.*)$/iu',
            '/(?:खोजें|दिखाएँ|दिखाओ|कहाँ मिल|चाहिए)(.*)$/u',
            '/\b(?:buscar|encontrar|busco|necesito|mu[eé]strame|tienen)\b(.*)$/iu',
            '/(?:تلاش|دکھائیں|کہاں مل|چاہیے)(.*)$/u',
            '/\b(?:ich suche|zeig mir|zeig|suchen|finden|brauche|haben sie)\b(.*)$/iu',
            '/(?:给我看看|给我看|搜索|找|看看|需要|有没有)(.*)$/u',
        ];

        $looksLikeLookup = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                $looksLikeLookup = true;
                $term = self::cleanTerm((string) ($m[1] ?? ''));
                if ($term !== null) {
                    return mb_substr($term, 0, $maxChars);
                }
            }
        }

        if (! $looksLikeLookup) {
            return null;
        }

        /* Verb-final languages: "मुझे कपड़े दिखाएँ" ends with the verb, so the
         * term is what sits BEFORE it. Strip the known request words and use
         * whatever is left ("कपड़े"). */
        $term = self::cleanTerm(self::stripRequestWords($text));

        return $term === null ? null : mb_substr($term, 0, $maxChars);
    }

    /** Trim a captured term to something usable, or null when it is too short. */
    private static function cleanTerm(string $term): ?string
    {
        $term = trim($term, " \t\n\r\0\x0B?!.,;:\"'");
        $term = trim(str_replace(['?', '。', '？', '！', '¿', '¡', '।', '،'], ' ', $term));
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? $term);

        return mb_strlen($term) >= 2 ? $term : null;
    }

    /** Remove the "show me / find / please" request words from a question. */
    private static function stripRequestWords(string $text): string
    {
        $words = [
            'show me', 'find me', 'find', 'search for', 'search', 'looking for', 'look for',
            'do you have', 'i need', 'i want', 'please',
            'nipe', 'nitafutie', 'naomba', 'ninataka', 'ninaitaji', 'nionyeshe', 'tafadhali',
            'montrez-moi', 'montre-moi', 'montrez', 'cherchez', 'chercher', 'trouvez', 'trouver', 'montre', 's\'il vous plaît',
            'मुझे', 'मुझको', 'दिखाएँ', 'दिखाओ', 'चाहिए', 'खोजें', 'कृपया',
            'muéstrame', 'muestrame', 'buscar', 'encontrar', 'necesito', 'por favor',
            'مجھے', 'دکھائیں', 'چاہیے', 'تلاش', 'براہ کرم',
            'zeig mir', 'zeige mir', 'zeig', 'suche', 'suchen', 'finde', 'finden', 'brauche', 'bitte',
            '给我看看', '给我看', '我需要', '我要', '看看', '找', '搜索', '请',
        ];

        $out = str_ireplace($words, ' ', $text);

        return (string) $out;
    }

    /* ------------------------------------------------------------------ */
    /* Public search (pure over rows the caller read from PublicCatalog)    */
    /* ------------------------------------------------------------------ */

    /**
     * Products whose name/category/description/shop matches the term. Only the
     * PUBLIC field whitelist survives, and at most `$limit` rows are returned.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function searchProducts(array $rows, string $term, int $limit = self::MAX_PRODUCT_RESULTS): array
    {
        $needle = self::normalise($term);
        if ($needle === '') {
            return [];
        }

        $hits = [];
        foreach ($rows as $row) {
            $haystack = self::normalise(implode(' ', [
                (string) ($row['name'] ?? ''),
                (string) ($row['category'] ?? ''),
                (string) ($row['description'] ?? ''),
                (string) ($row['business_name'] ?? ''),
                (string) ($row['business_location'] ?? ''),
            ]));
            if (str_contains($haystack, $needle)) {
                $hits[] = $row;
            }
        }

        /* In-stock and available first, then cheapest: a visitor looking for a
         * product should see something they can actually order. */
        usort($hits, static function (array $a, array $b): int {
            $aAvail = ! empty($a['available']) ? 1 : 0;
            $bAvail = ! empty($b['available']) ? 1 : 0;
            if ($aAvail !== $bAvail) {
                return $bAvail <=> $aAvail;
            }

            return ((float) ($a['selling_price'] ?? 0)) <=> ((float) ($b['selling_price'] ?? 0));
        });

        return array_map(
            static fn (array $row): array => self::publicFields($row, self::PUBLIC_PRODUCT_FIELDS),
            array_slice($hits, 0, max(0, min(self::MAX_PRODUCT_RESULTS, $limit)))
        );
    }

    /**
     * Public businesses whose name/type/location/description matches the term.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function searchBusinesses(array $rows, string $term, int $limit = self::MAX_BUSINESS_RESULTS): array
    {
        $needle = self::normalise($term);
        if ($needle === '') {
            return [];
        }

        $hits = [];
        foreach ($rows as $row) {
            $haystack = self::normalise(implode(' ', [
                (string) ($row['business_name'] ?? ''),
                (string) ($row['business_type'] ?? ''),
                (string) ($row['business_description'] ?? ''),
                (string) ($row['business_location'] ?? ''),
            ]));
            if (str_contains($haystack, $needle)) {
                $hits[] = $row;
            }
        }

        /* Certified shops first, then the ones with more products. */
        usort($hits, static function (array $a, array $b): int {
            $aCert = ! empty($a['is_certified']) ? 1 : 0;
            $bCert = ! empty($b['is_certified']) ? 1 : 0;
            if ($aCert !== $bCert) {
                return $bCert <=> $aCert;
            }

            return ((int) ($b['product_count'] ?? 0)) <=> ((int) ($a['product_count'] ?? 0));
        });

        return array_map(
            static fn (array $row): array => self::publicFields($row, self::PUBLIC_BUSINESS_FIELDS),
            array_slice($hits, 0, max(0, min(self::MAX_BUSINESS_RESULTS, $limit)))
        );
    }

    /**
     * Keep ONLY whitelisted keys - the protection that survives a future
     * catalogue change (a new private field cannot ride along).
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    public static function publicFields(array $row, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Answers + links                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * The approved answer for a topic: a title, a short body, its bullets and
     * the links that belong to it (localized labels, allow-listed URLs).
     *
     * @param  array<string, string>  $externalLinks
     * @return array{topic: string, title: string, body: string, bullets: array<int, string>, links: array<int, array{id: string, label: string, url: string}>}
     */
    public static function answer(string $topic, string $locale, array $externalLinks = []): array
    {
        if (! in_array($topic, self::TOPICS, true)) {
            $topic = 'what_is';
        }

        return [
            'topic' => $topic,
            'title' => self::msg('topic_'.$topic.'_title', $locale),
            'body' => self::msg('topic_'.$topic.'_body', $locale),
            'bullets' => array_values(array_filter([
                self::msg('topic_'.$topic.'_b1', $locale),
                self::msg('topic_'.$topic.'_b2', $locale),
            ], static fn (string $line): bool => $line !== '' && $line !== 'topic_'.$topic.'_b1' && $line !== 'topic_'.$topic.'_b2')),
            'links' => self::linksFor($topic, $locale, $externalLinks),
        ];
    }

    /**
     * The allow-listed links for a topic, with localized labels. A link id that
     * is not in the allow-list is skipped, never guessed.
     *
     * @param  array<string, string>  $externalLinks
     * @return array<int, array{id: string, label: string, url: string}>
     */
    public static function linksFor(string $topic, string $locale, array $externalLinks = []): array
    {
        $ids = self::TOPIC_LINKS[$topic] ?? [];
        $urls = array_merge(self::SECTION_LINKS, array_merge(self::DEFAULT_EXTERNAL_LINKS, $externalLinks));

        $out = [];
        foreach (array_slice($ids, 0, self::MAX_LINKS) as $id) {
            $url = $urls[$id] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'label' => self::msg('link_'.$id, $locale),
                'url' => $url,
            ];
        }

        return $out;
    }

    /**
     * Where to look when the concierge refuses or does not know.
     *
     * @param  array<string, string>  $externalLinks
     * @return array<int, array{id: string, label: string, url: string}>
     */
    public static function fallbackLinks(string $locale, array $externalLinks = []): array
    {
        $urls = array_merge(self::SECTION_LINKS, array_merge(self::DEFAULT_EXTERNAL_LINKS, $externalLinks));

        $out = [];
        foreach (self::FALLBACK_LINKS as $id) {
            $url = $urls[$id] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'label' => self::msg('link_'.$id, $locale),
                'url' => $url,
            ];
        }

        return $out;
    }

    /**
     * The topic list for the /help endpoint: every approved topic in the
     * visitor's language, so the widget can offer them as chips.
     *
     * @return array<int, array{id: string, label: string, title: string}>
     */
    public static function topics(string $locale): array
    {
        return array_map(static fn (string $topic): array => [
            'id' => $topic,
            'label' => self::msg('topic_label_'.$topic, $locale),
            'title' => self::msg('topic_'.$topic.'_title', $locale),
        ], self::TOPICS);
    }

    /** The advertised example questions (they must all be answerable). */
    public static function supportedExamples(string $locale): array
    {
        return [
            self::msg('example_what_is', $locale),
            self::msg('example_download', $locale),
            self::msg('example_buy', $locale),
            self::msg('example_sell', $locale),
            self::msg('example_track', $locale),
            self::msg('example_find_product', $locale),
        ];
    }

    /**
     * The published external links, overridable by config. The config lookup is
     * guarded so this service stays usable (and testable) without a Laravel
     * container: with no container the published defaults are used.
     */
    public static function externalLinks(): array
    {
        try {
            $configured = (array) config('ai.concierge.links', []);
        } catch (\Throwable $e) {
            $configured = [];
        }

        $out = [];
        foreach (array_keys(self::DEFAULT_EXTERNAL_LINKS) as $id) {
            $value = $configured[$id] ?? null;
            $out[$id] = is_string($value) && trim($value) !== '' ? trim($value) : self::DEFAULT_EXTERNAL_LINKS[$id];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Privacy + text guards                                               */
    /* ------------------------------------------------------------------ */

    /** @return array<int, string> */
    public static function findPrivateFields(array $payload, string $prefix = ''): array
    {
        $found = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $leaf = is_string($key) ? strtolower($key) : '';
            if ($leaf !== '' && in_array($leaf, self::PRIVATE_KEYS, true)) {
                $found[] = $path;
            }
            if (is_array($value)) {
                $found = array_merge($found, self::findPrivateFields($value, $path));
            }
        }

        return $found;
    }

    /** @param array<string, mixed> $payload */
    public static function assertNoPrivateFields(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::PRIVATE_KEYS, true)) {
                unset($payload[$key]);
                continue;
            }
            if (is_array($value)) {
                $payload[$key] = self::assertNoPrivateFields($value);
            }
        }

        return $payload;
    }

    /**
     * The optional model sentence: one short line about the PUBLIC information
     * answering the question. No digits, links, @, /, email, phone or question
     * marks - anything else is dropped and the deterministic answer stands.
     */
    public static function sanitizeAck(?string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? (string) $text);
        if ($text === '' || mb_strlen($text) > 140) {
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

    /** Lower-case, accent-stripped, punctuation-light comparison text. */
    public static function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(
            ['á', 'à', 'â', 'ä', 'ã', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'ö', 'õ', 'ú', 'ù', 'û', 'ü', 'ñ', 'ç', 'å', 'ø'],
            ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'n', 'c', 'a', 'o'],
            $text
        );

        return $text;
    }

    /* ------------------------------------------------------------------ */
    /* Messages (8 locales, identical key sets - pinned by the unit test)  */
    /* ------------------------------------------------------------------ */

    public const MESSAGES = [
        'sw' => [
            'title' => 'Msaidizi wa Duka Mkononi',
            'subtitle' => 'Uliza kuhusu jukwaa letu, jinsi la kufanya kazi, au mahali pa kupakua programu',
            'ask_placeholder' => 'Mfano: jinsi ya kununua bidhaa?',
            'ask_button' => 'Uliza',
            'clear' => 'Futa',
            'loading' => 'Inatafuta jibu...',
            'error' => 'Imeshindikana kupata jibu. Tafadhali jaribu tena.',
            'retry' => 'Jaribu tena',
            'topics_title' => 'Mada zinazopatikana',
            'examples_title' => 'Mifano ya maswali',
            'links_title' => 'Viungo vinavyohusiana',
            'results_title' => 'Bidhaa zilizopatikana',
            'businesses_title' => 'Biashara zilizopatikana',
            'empty_state' => 'Uliza swali ili kupata jibu kutoka kwa taarifa za umma.',
            'notice_public' => 'Najibu kwa kutumia taarifa za umma za Duka Mkononi tu.',
            'notice_scope' => 'Siwezi kutoa taarifa za ndani za mfumo, nywila wala taarifa za faragha za mtu mwingine.',
            'out_of_scope' => 'Hilo ni swali la ndani ya mfumo, hivyo siwezi kulijibu. Nipo hapa kusaidia maswali ya ununuzi na uuzaji.',
            'unknown' => 'Sikuipata hiyo kwenye taarifa zetu za umma. Angalia sehemu hizi au wasiliana nasi.',
            'no_results' => 'Sikupata bidhaa au biashara inayolingana na hilo kwenye orodha ya umma.',
            'fallback_title' => 'Sehemu zinazoweza kukusaidia',
            'contact_hint' => 'Kwa msaada wa haraka, wasiliana nasi kwa namba zilizo hapa chini.',
            'topic_label_what_is' => 'Duka Mkononi ni nini',
            'topic_label_how_to_buy' => 'Jinsi ya kununua',
            'topic_label_how_to_sell' => 'Jinsi ya kuuza',
            'topic_label_get_started' => 'Kuanza: akaunti na kuingia',
            'topic_label_download_app' => 'Kupakua programu',
            'topic_label_payments' => 'Malipo',
            'topic_label_delivery_tracking' => 'Usafirishaji na kufuatilia agizo',
            'topic_label_trust_safety' => 'Kuaminika na usalama',
            'topic_label_contact_support' => 'Wasiliana nasi',
            'topic_what_is_title' => 'Duka Mkononi ni nini',
            'topic_what_is_body' => 'Duka Mkononi ni jukwaa la biashara mtandaoni linalounganisha wateja na biashara halisi Tanzania: unaona bidhaa na bei, unaagiza, na unafuatilia agizo lako.',
            'topic_what_is_b1' => 'Bidhaa zote zinatoka kwa biashara zilizojisajili, si kwa jukwaa lenyewe.',
            'topic_what_is_b2' => 'Unaweza kutumia tovuti au programu ya simu.',
            'topic_how_to_buy_title' => 'Jinsi ya kununua',
            'topic_how_to_buy_body' => 'Chagua bidhaa kwenye sehemu ya Bidhaa au kwenye duka la biashara, kisha tuma agizo lako.',
            'topic_how_to_buy_b1' => 'Biashara inathibitisha agizo, na malipo yanaonyeshwa wakati wa kuagiza.',
            'topic_how_to_buy_b2' => 'Unaweza kufuatilia agizo lako kwa kumbukumbu ya agizo.',
            'topic_how_to_sell_title' => 'Jinsi ya kuuza',
            'topic_how_to_sell_body' => 'Jisajili kama biashara, ongeza bidhaa zako na bei, kisha wateja wataanza kuagiza.',
            'topic_how_to_sell_b1' => 'Weka bidhaa zako kwa hisa na bei sahihi ili wateja waone.',
            'topic_how_to_sell_b2' => 'Uthibitisho wa biashara hufanywa na timu ya jukwaa, na huonyeshwa kwenye ukurasa wa biashara.',
            'topic_get_started_title' => 'Kuanza: akaunti na kuingia',
            'topic_get_started_body' => 'Fungua akaunti ya mteja au ya biashara, kisha ingia kwenye akaunti yako.',
            'topic_get_started_b1' => 'Kama umesahau nywila, unaweza kuiweka upya kwa barua pepe yako.',
            'topic_get_started_b2' => 'Msimbo wa kuthibitisha huwa halali kwa dakika kumi tu.',
            'topic_download_app_title' => 'Kupakua programu',
            'topic_download_app_body' => 'Programu ya Duka Mkononi inapatikana kwa kiungo kinachotolewa kwenye tovuti yetu.',
            'topic_download_app_b1' => 'Fungua kiungo kwa simu yako, kisha ruhusu usanikishaji na ufungue faili.',
            'topic_download_app_b2' => 'Kama usanikishaji haufanikiwi, tumia tovuti yetu au wasiliana nasi.',
            'topic_payments_title' => 'Malipo',
            'topic_payments_body' => 'Njia za malipo zinaonyeshwa kwako wakati wa kukamilisha agizo.',
            'topic_payments_b1' => 'Usitume fedha kwa namba ya mtu binafsi; fuata maelekezo ya malipo yanayoonyeshwa.',
            'topic_payments_b2' => 'Hifadhi kumbukumbu ya agizo na malipo kwa ajili ya msaada.',
            'topic_delivery_tracking_title' => 'Usafirishaji na kufuatilia agizo',
            'topic_delivery_tracking_body' => 'Usafirishaji au kuchukua mzigo hupangwa na biashara, na unaweza kufuatilia hali ya agizo lako.',
            'topic_delivery_tracking_b1' => 'Kwa swali la agizo mahususi, wasiliana na biashara kwanza, kisha nasi.',
            'topic_delivery_tracking_b2' => 'Kumbukumbu ya agizo ni muhimu ili tupate agizo lako haraka.',
            'topic_trust_safety_title' => 'Kuaminika na usalama',
            'topic_trust_safety_body' => 'Biashara zilizothibitishwa zinaonyeshwa kwenye sehemu ya biashara, na maoni ya wateja yanaonekana kwenye jukwaa.',
            'topic_trust_safety_b1' => 'Angalia jina, eneo na bidhaa za biashara kabla ya kulipa.',
            'topic_trust_safety_b2' => 'Kama kuna jambo linalokutia shaka, wasiliana nasi kabla ya kulipa.',
            'topic_contact_support_title' => 'Wasiliana nasi',
            'topic_contact_support_body' => 'Tuko tayari kukusaidia kwa barua pepe, simu au WhatsApp.',
            'topic_contact_support_b1' => 'Taja kumbukumbu ya agizo lako ili tupate msaada wa haraka.',
            'topic_contact_support_b2' => 'Njia hizi ni zile zilizoandikwa kwenye tovuti yetu.',
            'link_home' => 'Ukurasa wa mwanzo',
            'link_shop' => 'Bidhaa',
            'link_businesses' => 'Biashara',
            'link_advertisements' => 'Matangazo',
            'link_track_orders' => 'Fuatilia agizo',
            'link_login' => 'Ingia',
            'link_client_signup' => 'Fungua akaunti ya mteja',
            'link_business_signup' => 'Sajili biashara',
            'link_forgot' => 'Umesahau nywila',
            'link_download_apk' => 'Pakua programu',
            'link_contact_email' => 'Barua pepe',
            'link_contact_phone' => 'Simu',
            'link_contact_whatsapp' => 'WhatsApp',
            'example_what_is' => 'Duka Mkononi ni nini?',
            'example_download' => 'Nipakue programu vipi?',
            'example_buy' => 'Ninunue bidhaa vipi?',
            'example_sell' => 'Ninajisajili vipi kama muuzaji?',
            'example_track' => 'Nifuatilie agizo langu vipi?',
            'example_find_product' => 'Nipe bidhaa za nguo',
        ],
        'en' => [
            'title' => 'Duka Mkononi assistant',
            'subtitle' => 'Ask about our marketplace, how it works, or where to download the app',
            'ask_placeholder' => 'e.g. How do I buy a product?',
            'ask_button' => 'Ask',
            'clear' => 'Clear',
            'loading' => 'Looking for an answer...',
            'error' => 'Could not get an answer. Please try again.',
            'retry' => 'Try again',
            'topics_title' => 'Topics I can explain',
            'examples_title' => 'Example questions',
            'links_title' => 'Related pages',
            'results_title' => 'Products found',
            'businesses_title' => 'Businesses found',
            'empty_state' => 'Ask a question to get an answer from our public information.',
            'notice_public' => 'I answer from Duka Mkononi public information only.',
            'notice_scope' => 'I cannot share internal system details, passwords or another person\'s private data.',
            'out_of_scope' => 'That is about our internals, so I cannot answer it. I am here to help with buying and selling questions.',
            'unknown' => 'I did not find that in our public information. Try these sections or contact us.',
            'no_results' => 'I found no matching product or business in the public catalogue.',
            'fallback_title' => 'Where to look next',
            'contact_hint' => 'For quick help, reach us on the contacts below.',
            'topic_label_what_is' => 'What Duka Mkononi is',
            'topic_label_how_to_buy' => 'How to buy',
            'topic_label_how_to_sell' => 'How to sell',
            'topic_label_get_started' => 'Getting started: accounts and login',
            'topic_label_download_app' => 'Downloading the app',
            'topic_label_payments' => 'Payments',
            'topic_label_delivery_tracking' => 'Delivery and order tracking',
            'topic_label_trust_safety' => 'Trust and safety',
            'topic_label_contact_support' => 'Contacting us',
            'topic_what_is_title' => 'What Duka Mkononi is',
            'topic_what_is_body' => 'Duka Mkononi is an online marketplace that connects customers with real businesses in Tanzania: you see products and prices, you order, and you track your order.',
            'topic_what_is_b1' => 'Every product comes from a registered business, not from the platform itself.',
            'topic_what_is_b2' => 'You can use the website or the mobile app.',
            'topic_how_to_buy_title' => 'How to buy',
            'topic_how_to_buy_body' => 'Pick a product in the Products section or on a shop page, then place your order.',
            'topic_how_to_buy_b1' => 'The business confirms the order, and the payment options are shown when you order.',
            'topic_how_to_buy_b2' => 'You can follow your order with your order reference.',
            'topic_how_to_sell_title' => 'How to sell',
            'topic_how_to_sell_body' => 'Register as a business, add your products and prices, and customers can start ordering.',
            'topic_how_to_sell_b1' => 'Keep your products, stock and prices up to date so customers see them.',
            'topic_how_to_sell_b2' => 'Certification is decided by the platform team and shown on the business page.',
            'topic_get_started_title' => 'Getting started: accounts and login',
            'topic_get_started_body' => 'Create a customer account or a business account, then sign in.',
            'topic_get_started_b1' => 'If you forgot your password you can reset it with your email address.',
            'topic_get_started_b2' => 'A verification code stays valid for ten minutes only.',
            'topic_download_app_title' => 'Downloading the app',
            'topic_download_app_body' => 'The Duka Mkononi app is available from the link published on our website.',
            'topic_download_app_b1' => 'Open the link on your phone, allow the installation, then open the file.',
            'topic_download_app_b2' => 'If the installation does not work, use our website or contact us.',
            'topic_payments_title' => 'Payments',
            'topic_payments_body' => 'The payment options are shown to you when you complete your order.',
            'topic_payments_b1' => 'Never send money to a personal number; follow the payment instructions shown to you.',
            'topic_payments_b2' => 'Keep your order and payment reference for support.',
            'topic_delivery_tracking_title' => 'Delivery and order tracking',
            'topic_delivery_tracking_body' => 'Delivery or pickup is arranged by the business, and you can follow your order status.',
            'topic_delivery_tracking_b1' => 'For a specific order question, contact the business first, then us.',
            'topic_delivery_tracking_b2' => 'Your order reference helps us find your order quickly.',
            'topic_trust_safety_title' => 'Trust and safety',
            'topic_trust_safety_body' => 'Certified businesses are listed in the businesses section, and customer reviews are visible on the platform.',
            'topic_trust_safety_b1' => 'Check the business name, location and products before you pay.',
            'topic_trust_safety_b2' => 'If anything looks wrong, contact us before paying.',
            'topic_contact_support_title' => 'Contacting us',
            'topic_contact_support_body' => 'We are ready to help by email, phone or WhatsApp.',
            'topic_contact_support_b1' => 'Mention your order reference so we can help faster.',
            'topic_contact_support_b2' => 'These are the same contacts published on our website.',
            'link_home' => 'Home page',
            'link_shop' => 'Products',
            'link_businesses' => 'Businesses',
            'link_advertisements' => 'Advertisements',
            'link_track_orders' => 'Track an order',
            'link_login' => 'Sign in',
            'link_client_signup' => 'Create a customer account',
            'link_business_signup' => 'Register a business',
            'link_forgot' => 'Forgot password',
            'link_download_apk' => 'Download the app',
            'link_contact_email' => 'Email',
            'link_contact_phone' => 'Phone',
            'link_contact_whatsapp' => 'WhatsApp',
            'example_what_is' => 'What is Duka Mkononi?',
            'example_download' => 'How do I download the app?',
            'example_buy' => 'How do I buy a product?',
            'example_sell' => 'How do I register as a seller?',
            'example_track' => 'How do I track my order?',
            'example_find_product' => 'Show me clothes',
        ],
        'fr' => [
            'title' => 'Assistant Duka Mkononi',
            'subtitle' => 'Posez vos questions sur notre marché, son fonctionnement ou le téléchargement de l\'application',
            'ask_placeholder' => 'ex. Comment acheter un produit ?',
            'ask_button' => 'Demander',
            'clear' => 'Effacer',
            'loading' => 'Recherche d\'une réponse...',
            'error' => 'Impossible d\'obtenir une réponse. Veuillez réessayer.',
            'retry' => 'Réessayer',
            'topics_title' => 'Sujets que je peux expliquer',
            'examples_title' => 'Exemples de questions',
            'links_title' => 'Pages utiles',
            'results_title' => 'Produits trouvés',
            'businesses_title' => 'Entreprises trouvées',
            'empty_state' => 'Posez une question pour obtenir une réponse à partir de nos informations publiques.',
            'notice_public' => 'Je réponds uniquement à partir des informations publiques de Duka Mkononi.',
            'notice_scope' => 'Je ne peux pas partager de détails internes du système, des mots de passe ni les données privées d\'autrui.',
            'out_of_scope' => 'Cela concerne nos systèmes internes, je ne peux donc pas y répondre. Je suis là pour les questions d\'achat et de vente.',
            'unknown' => 'Je n\'ai pas trouvé cela dans nos informations publiques. Essayez ces sections ou contactez-nous.',
            'no_results' => 'Je n\'ai trouvé aucun produit ni entreprise correspondant dans le catalogue public.',
            'fallback_title' => 'Où chercher ensuite',
            'contact_hint' => 'Pour une aide rapide, contactez-nous via les coordonnées ci-dessous.',
            'topic_label_what_is' => 'Qu\'est-ce que Duka Mkononi',
            'topic_label_how_to_buy' => 'Comment acheter',
            'topic_label_how_to_sell' => 'Comment vendre',
            'topic_label_get_started' => 'Démarrage : comptes et connexion',
            'topic_label_download_app' => 'Télécharger l\'application',
            'topic_label_payments' => 'Paiements',
            'topic_label_delivery_tracking' => 'Livraison et suivi de commande',
            'topic_label_trust_safety' => 'Confiance et sécurité',
            'topic_label_contact_support' => 'Nous contacter',
            'topic_what_is_title' => 'Qu\'est-ce que Duka Mkononi',
            'topic_what_is_body' => 'Duka Mkononi est une place de marché en ligne qui relie les clients à de vraies entreprises en Tanzanie : vous voyez les produits et les prix, vous commandez, puis vous suivez votre commande.',
            'topic_what_is_b1' => 'Chaque produit vient d\'une entreprise enregistrée, pas de la plateforme elle-même.',
            'topic_what_is_b2' => 'Vous pouvez utiliser le site web ou l\'application mobile.',
            'topic_how_to_buy_title' => 'Comment acheter',
            'topic_how_to_buy_body' => 'Choisissez un produit dans la section Produits ou sur la page d\'une boutique, puis passez votre commande.',
            'topic_how_to_buy_b1' => 'L\'entreprise confirme la commande et les options de paiement s\'affichent au moment de commander.',
            'topic_how_to_buy_b2' => 'Vous pouvez suivre votre commande avec sa référence.',
            'topic_how_to_sell_title' => 'Comment vendre',
            'topic_how_to_sell_body' => 'Inscrivez votre entreprise, ajoutez vos produits et vos prix, et les clients peuvent commencer à commander.',
            'topic_how_to_sell_b1' => 'Tenez à jour vos produits, votre stock et vos prix pour que les clients les voient.',
            'topic_how_to_sell_b2' => 'La certification est décidée par l\'équipe de la plateforme et affichée sur la page de l\'entreprise.',
            'topic_get_started_title' => 'Démarrage : comptes et connexion',
            'topic_get_started_body' => 'Créez un compte client ou un compte entreprise, puis connectez-vous.',
            'topic_get_started_b1' => 'Si vous avez oublié votre mot de passe, vous pouvez le réinitialiser avec votre adresse e-mail.',
            'topic_get_started_b2' => 'Un code de vérification reste valable dix minutes seulement.',
            'topic_download_app_title' => 'Télécharger l\'application',
            'topic_download_app_body' => 'L\'application Duka Mkononi est disponible via le lien publié sur notre site web.',
            'topic_download_app_b1' => 'Ouvrez le lien sur votre téléphone, autorisez l\'installation, puis ouvrez le fichier.',
            'topic_download_app_b2' => 'Si l\'installation ne fonctionne pas, utilisez notre site web ou contactez-nous.',
            'topic_payments_title' => 'Paiements',
            'topic_payments_body' => 'Les options de paiement vous sont affichées lorsque vous finalisez votre commande.',
            'topic_payments_b1' => 'N\'envoyez jamais d\'argent à un numéro personnel ; suivez les instructions de paiement affichées.',
            'topic_payments_b2' => 'Conservez votre référence de commande et de paiement pour le support.',
            'topic_delivery_tracking_title' => 'Livraison et suivi de commande',
            'topic_delivery_tracking_body' => 'La livraison ou le retrait est organisé par l\'entreprise, et vous pouvez suivre l\'état de votre commande.',
            'topic_delivery_tracking_b1' => 'Pour une question sur une commande précise, contactez d\'abord l\'entreprise, puis nous.',
            'topic_delivery_tracking_b2' => 'Votre référence de commande nous aide à retrouver votre commande rapidement.',
            'topic_trust_safety_title' => 'Confiance et sécurité',
            'topic_trust_safety_body' => 'Les entreprises certifiées sont listées dans la section entreprises, et les avis clients sont visibles sur la plateforme.',
            'topic_trust_safety_b1' => 'Vérifiez le nom, la localisation et les produits de l\'entreprise avant de payer.',
            'topic_trust_safety_b2' => 'Si quelque chose semble anormal, contactez-nous avant de payer.',
            'topic_contact_support_title' => 'Nous contacter',
            'topic_contact_support_body' => 'Nous sommes prêts à vous aider par e-mail, téléphone ou WhatsApp.',
            'topic_contact_support_b1' => 'Mentionnez la référence de votre commande pour une aide plus rapide.',
            'topic_contact_support_b2' => 'Ce sont les mêmes coordonnées que celles publiées sur notre site web.',
            'link_home' => 'Page d\'accueil',
            'link_shop' => 'Produits',
            'link_businesses' => 'Entreprises',
            'link_advertisements' => 'Annonces',
            'link_track_orders' => 'Suivre une commande',
            'link_login' => 'Se connecter',
            'link_client_signup' => 'Créer un compte client',
            'link_business_signup' => 'Inscrire une entreprise',
            'link_forgot' => 'Mot de passe oublié',
            'link_download_apk' => 'Télécharger l\'application',
            'link_contact_email' => 'E-mail',
            'link_contact_phone' => 'Téléphone',
            'link_contact_whatsapp' => 'WhatsApp',
            'example_what_is' => 'Qu\'est-ce que Duka Mkononi ?',
            'example_download' => 'Comment télécharger l\'application ?',
            'example_buy' => 'Comment acheter un produit ?',
            'example_sell' => 'Comment m\'inscrire comme vendeur ?',
            'example_track' => 'Comment suivre ma commande ?',
            'example_find_product' => 'Montrez-moi des vêtements',
        ],
        'es' => [
            'title' => 'Asistente de Duka Mkononi',
            'subtitle' => 'Pregunta sobre nuestro mercado, cómo funciona o dónde descargar la app',
            'ask_placeholder' => 'p. ej. ¿Cómo compro un producto?',
            'ask_button' => 'Preguntar',
            'clear' => 'Limpiar',
            'loading' => 'Buscando una respuesta...',
            'error' => 'No se pudo obtener una respuesta. Inténtalo de nuevo.',
            'retry' => 'Reintentar',
            'topics_title' => 'Temas que puedo explicar',
            'examples_title' => 'Preguntas de ejemplo',
            'links_title' => 'Páginas útiles',
            'results_title' => 'Productos encontrados',
            'businesses_title' => 'Empresas encontradas',
            'empty_state' => 'Haz una pregunta para obtener una respuesta de nuestra información pública.',
            'notice_public' => 'Respondo solo con la información pública de Duka Mkononi.',
            'notice_scope' => 'No puedo compartir detalles internos del sistema, contraseñas ni datos privados de otra persona.',
            'out_of_scope' => 'Eso es sobre nuestros sistemas internos, así que no puedo responderlo. Estoy aquí para preguntas de compra y venta.',
            'unknown' => 'No encontré eso en nuestra información pública. Prueba estas secciones o contáctanos.',
            'no_results' => 'No encontré ningún producto ni empresa que coincida en el catálogo público.',
            'fallback_title' => 'Dónde mirar ahora',
            'contact_hint' => 'Para ayuda rápida, contáctanos por los datos de abajo.',
            'topic_label_what_is' => 'Qué es Duka Mkononi',
            'topic_label_how_to_buy' => 'Cómo comprar',
            'topic_label_how_to_sell' => 'Cómo vender',
            'topic_label_get_started' => 'Empezar: cuentas e inicio de sesión',
            'topic_label_download_app' => 'Descargar la app',
            'topic_label_payments' => 'Pagos',
            'topic_label_delivery_tracking' => 'Entrega y seguimiento del pedido',
            'topic_label_trust_safety' => 'Confianza y seguridad',
            'topic_label_contact_support' => 'Contactarnos',
            'topic_what_is_title' => 'Qué es Duka Mkononi',
            'topic_what_is_body' => 'Duka Mkononi es un mercado en línea que conecta a los clientes con empresas reales de Tanzania: ves productos y precios, haces tu pedido y sigues su estado.',
            'topic_what_is_b1' => 'Cada producto proviene de una empresa registrada, no de la plataforma.',
            'topic_what_is_b2' => 'Puedes usar el sitio web o la aplicación móvil.',
            'topic_how_to_buy_title' => 'Cómo comprar',
            'topic_how_to_buy_body' => 'Elige un producto en la sección Productos o en la página de una tienda y luego haz tu pedido.',
            'topic_how_to_buy_b1' => 'La empresa confirma el pedido y las opciones de pago se muestran al ordenar.',
            'topic_how_to_buy_b2' => 'Puedes seguir tu pedido con su referencia.',
            'topic_how_to_sell_title' => 'Cómo vender',
            'topic_how_to_sell_body' => 'Registra tu empresa, añade tus productos y precios, y los clientes podrán empezar a pedir.',
            'topic_how_to_sell_b1' => 'Mantén actualizados tus productos, stock y precios para que los clientes los vean.',
            'topic_how_to_sell_b2' => 'La certificación la decide el equipo de la plataforma y se muestra en la página de la empresa.',
            'topic_get_started_title' => 'Empezar: cuentas e inicio de sesión',
            'topic_get_started_body' => 'Crea una cuenta de cliente o de empresa y luego inicia sesión.',
            'topic_get_started_b1' => 'Si olvidaste tu contraseña, puedes restablecerla con tu correo electrónico.',
            'topic_get_started_b2' => 'Un código de verificación solo es válido durante diez minutos.',
            'topic_download_app_title' => 'Descargar la app',
            'topic_download_app_body' => 'La app de Duka Mkononi está disponible desde el enlace publicado en nuestro sitio web.',
            'topic_download_app_b1' => 'Abre el enlace en tu teléfono, permite la instalación y abre el archivo.',
            'topic_download_app_b2' => 'Si la instalación no funciona, usa nuestro sitio web o contáctanos.',
            'topic_payments_title' => 'Pagos',
            'topic_payments_body' => 'Las opciones de pago se te muestran al completar tu pedido.',
            'topic_payments_b1' => 'Nunca envíes dinero a un número personal; sigue las instrucciones de pago mostradas.',
            'topic_payments_b2' => 'Guarda tu referencia de pedido y pago para el soporte.',
            'topic_delivery_tracking_title' => 'Entrega y seguimiento del pedido',
            'topic_delivery_tracking_body' => 'La entrega o la recogida la organiza la empresa, y puedes seguir el estado de tu pedido.',
            'topic_delivery_tracking_b1' => 'Para una pregunta sobre un pedido concreto, contacta primero con la empresa y luego con nosotros.',
            'topic_delivery_tracking_b2' => 'La referencia de tu pedido nos ayuda a encontrarlo rápido.',
            'topic_trust_safety_title' => 'Confianza y seguridad',
            'topic_trust_safety_body' => 'Las empresas certificadas aparecen en la sección de empresas, y las reseñas de clientes son visibles en la plataforma.',
            'topic_trust_safety_b1' => 'Comprueba el nombre, la ubicación y los productos de la empresa antes de pagar.',
            'topic_trust_safety_b2' => 'Si algo parece mal, contáctanos antes de pagar.',
            'topic_contact_support_title' => 'Contactarnos',
            'topic_contact_support_body' => 'Estamos listos para ayudarte por correo, teléfono o WhatsApp.',
            'topic_contact_support_b1' => 'Menciona la referencia de tu pedido para una ayuda más rápida.',
            'topic_contact_support_b2' => 'Son los mismos contactos publicados en nuestro sitio web.',
            'link_home' => 'Inicio',
            'link_shop' => 'Productos',
            'link_businesses' => 'Empresas',
            'link_advertisements' => 'Anuncios',
            'link_track_orders' => 'Seguir un pedido',
            'link_login' => 'Iniciar sesión',
            'link_client_signup' => 'Crear cuenta de cliente',
            'link_business_signup' => 'Registrar una empresa',
            'link_forgot' => 'Olvidé mi contraseña',
            'link_download_apk' => 'Descargar la app',
            'link_contact_email' => 'Correo',
            'link_contact_phone' => 'Teléfono',
            'link_contact_whatsapp' => 'WhatsApp',
            'example_what_is' => '¿Qué es Duka Mkononi?',
            'example_download' => '¿Cómo descargo la app?',
            'example_buy' => '¿Cómo compro un producto?',
            'example_sell' => '¿Cómo me registro como vendedor?',
            'example_track' => '¿Cómo sigo mi pedido?',
            'example_find_product' => 'Muéstrame ropa',
        ],
        'de' => [
            'title' => 'Duka-Mkononi-Assistent',
            'subtitle' => 'Fragen Sie zu unserem Marktplatz, seiner Funktionsweise oder zum App-Download',
            'ask_placeholder' => 'z. B. Wie kaufe ich ein Produkt?',
            'ask_button' => 'Fragen',
            'clear' => 'Leeren',
            'loading' => 'Antwort wird gesucht...',
            'error' => 'Es konnte keine Antwort geladen werden. Bitte erneut versuchen.',
            'retry' => 'Erneut versuchen',
            'topics_title' => 'Themen, die ich erklären kann',
            'examples_title' => 'Beispielfragen',
            'links_title' => 'Nützliche Seiten',
            'results_title' => 'Gefundene Produkte',
            'businesses_title' => 'Gefundene Betriebe',
            'empty_state' => 'Stellen Sie eine Frage, um eine Antwort aus unseren öffentlichen Informationen zu erhalten.',
            'notice_public' => 'Ich antworte nur mit öffentlichen Informationen von Duka Mkononi.',
            'notice_scope' => 'Ich darf keine internen Systemdetails, Passwörter oder private Daten anderer Personen teilen.',
            'out_of_scope' => 'Das betrifft unsere internen Systeme, daher kann ich es nicht beantworten. Ich helfe bei Kauf- und Verkaufsfragen.',
            'unknown' => 'Das habe ich in unseren öffentlichen Informationen nicht gefunden. Versuchen Sie diese Bereiche oder kontaktieren Sie uns.',
            'no_results' => 'Ich habe kein passendes Produkt und keinen passenden Betrieb im öffentlichen Katalog gefunden.',
            'fallback_title' => 'Wo Sie weiter schauen können',
            'contact_hint' => 'Für schnelle Hilfe erreichen Sie uns über die untenstehenden Kontakte.',
            'topic_label_what_is' => 'Was Duka Mkononi ist',
            'topic_label_how_to_buy' => 'Wie man kauft',
            'topic_label_how_to_sell' => 'Wie man verkauft',
            'topic_label_get_started' => 'Erste Schritte: Konten und Anmeldung',
            'topic_label_download_app' => 'Die App herunterladen',
            'topic_label_payments' => 'Zahlungen',
            'topic_label_delivery_tracking' => 'Lieferung und Bestellverfolgung',
            'topic_label_trust_safety' => 'Vertrauen und Sicherheit',
            'topic_label_contact_support' => 'Kontakt aufnehmen',
            'topic_what_is_title' => 'Was Duka Mkononi ist',
            'topic_what_is_body' => 'Duka Mkononi ist ein Online-Marktplatz, der Kunden mit echten Betrieben in Tansania verbindet: Sie sehen Produkte und Preise, bestellen und verfolgen Ihre Bestellung.',
            'topic_what_is_b1' => 'Jedes Produkt kommt von einem registrierten Betrieb, nicht von der Plattform selbst.',
            'topic_what_is_b2' => 'Sie können die Website oder die mobile App nutzen.',
            'topic_how_to_buy_title' => 'Wie man kauft',
            'topic_how_to_buy_body' => 'Wählen Sie ein Produkt im Bereich Produkte oder auf der Seite eines Ladens und geben Sie dann Ihre Bestellung auf.',
            'topic_how_to_buy_b1' => 'Der Betrieb bestätigt die Bestellung, und die Zahlungsoptionen werden beim Bestellen angezeigt.',
            'topic_how_to_buy_b2' => 'Sie können Ihre Bestellung mit der Bestellreferenz verfolgen.',
            'topic_how_to_sell_title' => 'Wie man verkauft',
            'topic_how_to_sell_body' => 'Registrieren Sie Ihren Betrieb, fügen Sie Produkte und Preise hinzu, dann können Kunden bestellen.',
            'topic_how_to_sell_b1' => 'Halten Sie Produkte, Bestand und Preise aktuell, damit Kunden sie sehen.',
            'topic_how_to_sell_b2' => 'Über die Zertifizierung entscheidet das Plattformteam; sie wird auf der Betriebsseite angezeigt.',
            'topic_get_started_title' => 'Erste Schritte: Konten und Anmeldung',
            'topic_get_started_body' => 'Erstellen Sie ein Kunden- oder Betriebskonto und melden Sie sich dann an.',
            'topic_get_started_b1' => 'Wenn Sie Ihr Passwort vergessen haben, können Sie es mit Ihrer E-Mail-Adresse zurücksetzen.',
            'topic_get_started_b2' => 'Ein Bestätigungscode ist nur zehn Minuten gültig.',
            'topic_download_app_title' => 'Die App herunterladen',
            'topic_download_app_body' => 'Die Duka-Mkononi-App ist über den auf unserer Website veröffentlichten Link verfügbar.',
            'topic_download_app_b1' => 'Öffnen Sie den Link auf Ihrem Telefon, erlauben Sie die Installation und öffnen Sie die Datei.',
            'topic_download_app_b2' => 'Wenn die Installation nicht klappt, nutzen Sie unsere Website oder kontaktieren Sie uns.',
            'topic_payments_title' => 'Zahlungen',
            'topic_payments_body' => 'Die Zahlungsoptionen werden Ihnen beim Abschluss Ihrer Bestellung angezeigt.',
            'topic_payments_b1' => 'Senden Sie niemals Geld an eine private Nummer; folgen Sie den angezeigten Zahlungshinweisen.',
            'topic_payments_b2' => 'Bewahren Sie Ihre Bestell- und Zahlungsreferenz für den Support auf.',
            'topic_delivery_tracking_title' => 'Lieferung und Bestellverfolgung',
            'topic_delivery_tracking_body' => 'Lieferung oder Abholung organisiert der Betrieb, und Sie können den Status Ihrer Bestellung verfolgen.',
            'topic_delivery_tracking_b1' => 'Bei Fragen zu einer bestimmten Bestellung wenden Sie sich zuerst an den Betrieb, dann an uns.',
            'topic_delivery_tracking_b2' => 'Ihre Bestellreferenz hilft uns, Ihre Bestellung schnell zu finden.',
            'topic_trust_safety_title' => 'Vertrauen und Sicherheit',
            'topic_trust_safety_body' => 'Zertifizierte Betriebe sind im Bereich Betriebe gelistet, und Kundenbewertungen sind auf der Plattform sichtbar.',
            'topic_trust_safety_b1' => 'Prüfen Sie Name, Standort und Produkte des Betriebs, bevor Sie zahlen.',
            'topic_trust_safety_b2' => 'Wenn etwas nicht stimmt, kontaktieren Sie uns vor der Zahlung.',
            'topic_contact_support_title' => 'Kontakt aufnehmen',
            'topic_contact_support_body' => 'Wir helfen Ihnen gerne per E-Mail, Telefon oder WhatsApp.',
            'topic_contact_support_b1' => 'Nennen Sie Ihre Bestellreferenz, damit wir schneller helfen können.',
            'topic_contact_support_b2' => 'Das sind dieselben Kontakte, die auf unserer Website veröffentlicht sind.',
            'link_home' => 'Startseite',
            'link_shop' => 'Produkte',
            'link_businesses' => 'Betriebe',
            'link_advertisements' => 'Anzeigen',
            'link_track_orders' => 'Bestellung verfolgen',
            'link_login' => 'Anmelden',
            'link_client_signup' => 'Kundenkonto erstellen',
            'link_business_signup' => 'Betrieb registrieren',
            'link_forgot' => 'Passwort vergessen',
            'link_download_apk' => 'App herunterladen',
            'link_contact_email' => 'E-Mail',
            'link_contact_phone' => 'Telefon',
            'link_contact_whatsapp' => 'WhatsApp',
            'example_what_is' => 'Was ist Duka Mkononi?',
            'example_download' => 'Wie lade ich die App herunter?',
            'example_buy' => 'Wie kaufe ich ein Produkt?',
            'example_sell' => 'Wie registriere ich mich als Verkäufer?',
            'example_track' => 'Wie verfolge ich meine Bestellung?',
            'example_find_product' => 'Zeig mir Kleidung',
        ],
        'hi' => [
            'title' => 'Duka Mkononi सहायक',
            'subtitle' => 'हमारे बाज़ार, इसके काम करने के तरीके या ऐप डाउनलोड के बारे में पूछें',
            'ask_placeholder' => 'उदा. मैं उत्पाद कैसे खरीदूँ?',
            'ask_button' => 'पूछें',
            'clear' => 'साफ़ करें',
            'loading' => 'उत्तर खोजा जा रहा है...',
            'error' => 'उत्तर नहीं मिल सका। कृपया पुनः प्रयास करें।',
            'retry' => 'पुनः प्रयास करें',
            'topics_title' => 'जिन विषयों को मैं समझा सकता हूँ',
            'examples_title' => 'उदाहरण प्रश्न',
            'links_title' => 'उपयोगी पृष्ठ',
            'results_title' => 'मिले उत्पाद',
            'businesses_title' => 'मिले व्यवसाय',
            'empty_state' => 'हमारी सार्वजनिक जानकारी से उत्तर पाने के लिए प्रश्न पूछें।',
            'notice_public' => 'मैं केवल Duka Mkononi की सार्वजनिक जानकारी से उत्तर देता हूँ।',
            'notice_scope' => 'मैं सिस्टम की आंतरिक जानकारी, पासवर्ड या किसी अन्य व्यक्ति का निजी डेटा साझा नहीं कर सकता।',
            'out_of_scope' => 'यह हमारे आंतरिक सिस्टम से जुड़ा है, इसलिए मैं इसका उत्तर नहीं दे सकता। मैं खरीद-बिक्री से जुड़े सवालों में मदद करता हूँ।',
            'unknown' => 'यह मुझे हमारी सार्वजनिक जानकारी में नहीं मिला। इन अनुभागों को देखें या हमसे संपर्क करें।',
            'no_results' => 'सार्वजनिक सूची में मुझे कोई मेल खाता उत्पाद या व्यवसाय नहीं मिला।',
            'fallback_title' => 'अब कहाँ देखें',
            'contact_hint' => 'तुरंत सहायता के लिए नीचे दिए संपर्कों पर हमसे बात करें।',
            'topic_label_what_is' => 'Duka Mkononi क्या है',
            'topic_label_how_to_buy' => 'कैसे खरीदें',
            'topic_label_how_to_sell' => 'कैसे बेचें',
            'topic_label_get_started' => 'शुरुआत: खाते और लॉगिन',
            'topic_label_download_app' => 'ऐप डाउनलोड करना',
            'topic_label_payments' => 'भुगतान',
            'topic_label_delivery_tracking' => 'डिलीवरी और ऑर्डर ट्रैकिंग',
            'topic_label_trust_safety' => 'भरोसा और सुरक्षा',
            'topic_label_contact_support' => 'हमसे संपर्क करें',
            'topic_what_is_title' => 'Duka Mkononi क्या है',
            'topic_what_is_body' => 'Duka Mkononi एक ऑनलाइन बाज़ार है जो ग्राहकों को तंज़ानिया के असली व्यवसायों से जोड़ता है: आप उत्पाद और कीमतें देखते हैं, ऑर्डर करते हैं और अपने ऑर्डर को ट्रैक करते हैं।',
            'topic_what_is_b1' => 'हर उत्पाद किसी पंजीकृत व्यवसाय से आता है, प्लेटफ़ॉर्म से नहीं।',
            'topic_what_is_b2' => 'आप वेबसाइट या मोबाइल ऐप का उपयोग कर सकते हैं।',
            'topic_how_to_buy_title' => 'कैसे खरीदें',
            'topic_how_to_buy_body' => 'उत्पाद अनुभाग या किसी दुकान के पृष्ठ पर उत्पाद चुनें, फिर अपना ऑर्डर दें।',
            'topic_how_to_buy_b1' => 'व्यवसाय ऑर्डर की पुष्टि करता है, और भुगतान विकल्प ऑर्डर करते समय दिखाए जाते हैं।',
            'topic_how_to_buy_b2' => 'आप ऑर्डर संदर्भ से अपने ऑर्डर को ट्रैक कर सकते हैं।',
            'topic_how_to_sell_title' => 'कैसे बेचें',
            'topic_how_to_sell_body' => 'व्यवसाय के रूप में पंजीकरण करें, अपने उत्पाद और कीमतें जोड़ें, फिर ग्राहक ऑर्डर कर सकते हैं।',
            'topic_how_to_sell_b1' => 'अपने उत्पाद, स्टॉक और कीमतें अद्यतन रखें ताकि ग्राहक उन्हें देख सकें।',
            'topic_how_to_sell_b2' => 'प्रमाणन प्लेटफ़ॉर्म टीम तय करती है और यह व्यवसाय पृष्ठ पर दिखाया जाता है।',
            'topic_get_started_title' => 'शुरुआत: खाते और लॉगिन',
            'topic_get_started_body' => 'ग्राहक खाता या व्यवसाय खाता बनाएँ, फिर साइन इन करें।',
            'topic_get_started_b1' => 'अगर आप पासवर्ड भूल गए हैं तो अपने ईमेल से उसे रीसेट कर सकते हैं।',
            'topic_get_started_b2' => 'सत्यापन कोड केवल दस मिनट के लिए मान्य रहता है।',
            'topic_download_app_title' => 'ऐप डाउनलोड करना',
            'topic_download_app_body' => 'Duka Mkononi ऐप हमारी वेबसाइट पर प्रकाशित लिंक से उपलब्ध है।',
            'topic_download_app_b1' => 'अपने फ़ोन पर लिंक खोलें, इंस्टॉलेशन की अनुमति दें, फिर फ़ाइल खोलें।',
            'topic_download_app_b2' => 'अगर इंस्टॉलेशन काम न करे तो हमारी वेबसाइट उपयोग करें या हमसे संपर्क करें।',
            'topic_payments_title' => 'भुगतान',
            'topic_payments_body' => 'ऑर्डर पूरा करते समय आपको भुगतान विकल्प दिखाए जाते हैं।',
            'topic_payments_b1' => 'किसी व्यक्तिगत नंबर पर पैसे न भेजें; दिखाए गए भुगतान निर्देशों का पालन करें।',
            'topic_payments_b2' => 'सहायता के लिए अपना ऑर्डर और भुगतान संदर्भ सुरक्षित रखें।',
            'topic_delivery_tracking_title' => 'डिलीवरी और ऑर्डर ट्रैकिंग',
            'topic_delivery_tracking_body' => 'डिलीवरी या पिकअप व्यवसाय तय करता है, और आप अपने ऑर्डर की स्थिति ट्रैक कर सकते हैं।',
            'topic_delivery_tracking_b1' => 'किसी विशेष ऑर्डर के सवाल के लिए पहले व्यवसाय से, फिर हमसे संपर्क करें।',
            'topic_delivery_tracking_b2' => 'आपका ऑर्डर संदर्भ हमें ऑर्डर जल्दी खोजने में मदद करता है।',
            'topic_trust_safety_title' => 'भरोसा और सुरक्षा',
            'topic_trust_safety_body' => 'प्रमाणित व्यवसाय व्यवसाय अनुभाग में सूचीबद्ध हैं, और ग्राहक समीक्षाएँ प्लेटफ़ॉर्म पर दिखती हैं।',
            'topic_trust_safety_b1' => 'भुगतान से पहले व्यवसाय का नाम, स्थान और उत्पाद जाँचें।',
            'topic_trust_safety_b2' => 'कुछ गलत लगे तो भुगतान से पहले हमसे संपर्क करें।',
            'topic_contact_support_title' => 'हमसे संपर्क करें',
            'topic_contact_support_body' => 'हम ईमेल, फ़ोन या WhatsApp से मदद के लिए तैयार हैं।',
            'topic_contact_support_b1' => 'तेज़ सहायता के लिए अपना ऑर्डर संदर्भ बताएं।',
            'topic_contact_support_b2' => 'ये वही संपर्क हैं जो हमारी वेबसाइट पर प्रकाशित हैं।',
            'link_home' => 'मुखपृष्ठ',
            'link_shop' => 'उत्पाद',
            'link_businesses' => 'व्यवसाय',
            'link_advertisements' => 'विज्ञापन',
            'link_track_orders' => 'ऑर्डर ट्रैक करें',
            'link_login' => 'साइन इन',
            'link_client_signup' => 'ग्राहक खाता बनाएँ',
            'link_business_signup' => 'व्यवसाय पंजीकृत करें',
            'link_forgot' => 'पासवर्ड भूल गए',
            'link_download_apk' => 'ऐप डाउनलोड करें',
            'link_contact_email' => 'ईमेल',
            'link_contact_phone' => 'फ़ोन',
            'link_contact_whatsapp' => 'WhatsApp',
            'example_what_is' => 'Duka Mkononi क्या है?',
            'example_download' => 'ऐप कैसे डाउनलोड करें?',
            'example_buy' => 'मैं उत्पाद कैसे खरीदूँ?',
            'example_sell' => 'मैं विक्रेता के रूप में कैसे पंजीकरण करूँ?',
            'example_track' => 'अपना ऑर्डर कैसे ट्रैक करूँ?',
            'example_find_product' => 'मुझे कपड़े दिखाएँ',
        ],
        'ur' => [
            'title' => 'Duka Mkononi معاون',
            'subtitle' => 'ہمارے بازار، اس کے کام کرنے کے طریقے یا ایپ ڈاؤن لوڈ کے بارے میں پوچھیں',
            'ask_placeholder' => 'مثلاً میں پروڈکٹ کیسے خریدوں؟',
            'ask_button' => 'پوچھیں',
            'clear' => 'صاف کریں',
            'loading' => 'جواب تلاش کیا جا رہا ہے...',
            'error' => 'جواب نہیں مل سکا۔ براہ کرم دوبارہ کوشش کریں۔',
            'retry' => 'دوبارہ کوشش کریں',
            'topics_title' => 'وہ موضوعات جو میں سمجھا سکتا ہوں',
            'examples_title' => 'مثال کے سوالات',
            'links_title' => 'مفید صفحات',
            'results_title' => 'ملنے والی مصنوعات',
            'businesses_title' => 'ملنے والے کاروبار',
            'empty_state' => 'ہماری عوامی معلومات سے جواب حاصل کرنے کے لیے سوال پوچھیں۔',
            'notice_public' => 'میں صرف Duka Mkononi کی عوامی معلومات سے جواب دیتا ہوں۔',
            'notice_scope' => 'میں سسٹم کی اندرونی تفصیلات، پاس ورڈ یا کسی دوسرے شخص کا نجی ڈیٹا شیئر نہیں کر سکتا۔',
            'out_of_scope' => 'یہ ہمارے اندرونی نظام سے متعلق ہے، اس لیے میں اس کا جواب نہیں دے سکتا۔ میں خرید و فروخت کے سوالات میں مدد کرتا ہوں۔',
            'unknown' => 'یہ مجھے ہماری عوامی معلومات میں نہیں ملا۔ یہ حصے دیکھیں یا ہم سے رابطہ کریں۔',
            'no_results' => 'عوامی فہرست میں مجھے کوئی مماثل پروڈکٹ یا کاروبار نہیں ملا۔',
            'fallback_title' => 'اب کہاں دیکھیں',
            'contact_hint' => 'فوری مدد کے لیے نیچے دیے گئے ذرائع سے ہم سے رابطہ کریں۔',
            'topic_label_what_is' => 'Duka Mkononi کیا ہے',
            'topic_label_how_to_buy' => 'کیسے خریدیں',
            'topic_label_how_to_sell' => 'کیسے بیچیں',
            'topic_label_get_started' => 'شروعات: اکاؤنٹس اور لاگ ان',
            'topic_label_download_app' => 'ایپ ڈاؤن لوڈ کرنا',
            'topic_label_payments' => 'ادائیگیاں',
            'topic_label_delivery_tracking' => 'ڈیلیوری اور آرڈر ٹریکنگ',
            'topic_label_trust_safety' => 'بھروسہ اور سلامتی',
            'topic_label_contact_support' => 'ہم سے رابطہ',
            'topic_what_is_title' => 'Duka Mkononi کیا ہے',
            'topic_what_is_body' => 'Duka Mkononi ایک آن لائن بازار ہے جو گاہکوں کو تنزانیہ کے حقیقی کاروباروں سے جوڑتا ہے: آپ مصنوعات اور قیمتیں دیکھتے ہیں، آرڈر کرتے ہیں اور اپنے آرڈر کی نگرانی کرتے ہیں۔',
            'topic_what_is_b1' => 'ہر پروڈکٹ کسی رجسٹرڈ کاروبار سے آتی ہے، پلیٹ فارم سے نہیں۔',
            'topic_what_is_b2' => 'آپ ویب سائٹ یا موبائل ایپ استعمال کر سکتے ہیں۔',
            'topic_how_to_buy_title' => 'کیسے خریدیں',
            'topic_how_to_buy_body' => 'پروڈکٹس کے حصے یا کسی دکان کے صفحے پر پروڈکٹ منتخب کریں، پھر اپنا آرڈر دیں۔',
            'topic_how_to_buy_b1' => 'کاروبار آرڈر کی تصدیق کرتا ہے، اور ادائیگی کے اختیارات آرڈر کرتے وقت دکھائے جاتے ہیں۔',
            'topic_how_to_buy_b2' => 'آپ آرڈر ریفرنس سے اپنے آرڈر کی نگرانی کر سکتے ہیں۔',
            'topic_how_to_sell_title' => 'کیسے بیچیں',
            'topic_how_to_sell_body' => 'کاروبار کے طور پر رجسٹر کریں، اپنی مصنوعات اور قیمتیں شامل کریں، پھر گاہک آرڈر کر سکتے ہیں۔',
            'topic_how_to_sell_b1' => 'اپنی مصنوعات، اسٹاک اور قیمتیں تازہ رکھیں تاکہ گاہک انہیں دیکھ سکیں۔',
            'topic_how_to_sell_b2' => 'تصدیق پلیٹ فارم ٹیم کرتی ہے اور یہ کاروبار کے صفحے پر دکھائی جاتی ہے۔',
            'topic_get_started_title' => 'شروعات: اکاؤنٹس اور لاگ ان',
            'topic_get_started_body' => 'گاہک اکاؤنٹ یا کاروبار اکاؤنٹ بنائیں، پھر سائن ان کریں۔',
            'topic_get_started_b1' => 'اگر پاس ورڈ بھول گئے ہیں تو اپنے ای میل سے اسے دوبارہ مقرر کر سکتے ہیں۔',
            'topic_get_started_b2' => 'تصدیقی کوڈ صرف دس منٹ کے لیے کارآمد ہوتا ہے۔',
            'topic_download_app_title' => 'ایپ ڈاؤن لوڈ کرنا',
            'topic_download_app_body' => 'Duka Mkononi ایپ ہماری ویب سائٹ پر شائع لنک سے دستیاب ہے۔',
            'topic_download_app_b1' => 'اپنے فون پر لنک کھولیں، انسٹالیشن کی اجازت دیں، پھر فائل کھولیں۔',
            'topic_download_app_b2' => 'اگر انسٹالیشن کام نہ کرے تو ہماری ویب سائٹ استعمال کریں یا ہم سے رابطہ کریں۔',
            'topic_payments_title' => 'ادائیگیاں',
            'topic_payments_body' => 'آرڈر مکمل کرتے وقت آپ کو ادائیگی کے اختیارات دکھائے جاتے ہیں۔',
            'topic_payments_b1' => 'کسی ذاتی نمبر پر رقم نہ بھیجیں؛ دکھائی گئی ادائیگی ہدایات پر عمل کریں۔',
            'topic_payments_b2' => 'مدد کے لیے اپنا آرڈر اور ادائیگی ریفرنس محفوظ رکھیں۔',
            'topic_delivery_tracking_title' => 'ڈیلیوری اور آرڈر ٹریکنگ',
            'topic_delivery_tracking_body' => 'ڈیلیوری یا پک اپ کاروبار طے کرتا ہے، اور آپ اپنے آرڈر کی حالت دیکھ سکتے ہیں۔',
            'topic_delivery_tracking_b1' => 'کسی مخصوص آرڈر کے سوال کے لیے پہلے کاروبار سے، پھر ہم سے رابطہ کریں۔',
            'topic_delivery_tracking_b2' => 'آپ کا آرڈر ریفرنس ہمیں آرڈر جلد ڈھونڈنے میں مدد دیتا ہے۔',
            'topic_trust_safety_title' => 'بھروسہ اور سلامتی',
            'topic_trust_safety_body' => 'تصدیق شدہ کاروبار کاروبار کے حصے میں درج ہیں، اور گاہکوں کے تبصرے پلیٹ فارم پر نظر آتے ہیں۔',
            'topic_trust_safety_b1' => 'ادائیگی سے پہلے کاروبار کا نام، مقام اور مصنوعات دیکھیں۔',
            'topic_trust_safety_b2' => 'کچھ غلط لگے تو ادائیگی سے پہلے ہم سے رابطہ کریں۔',
            'topic_contact_support_title' => 'ہم سے رابطہ',
            'topic_contact_support_body' => 'ہم ای میل، فون یا WhatsApp سے مدد کے لیے تیار ہیں۔',
            'topic_contact_support_b1' => 'تیز مدد کے لیے اپنا آرڈر ریفرنس بتائیں۔',
            'topic_contact_support_b2' => 'یہ وہی روابط ہیں جو ہماری ویب سائٹ پر شائع ہیں۔',
            'link_home' => 'ہوم پیج',
            'link_shop' => 'مصنوعات',
            'link_businesses' => 'کاروبار',
            'link_advertisements' => 'اشتہارات',
            'link_track_orders' => 'آرڈر ٹریک کریں',
            'link_login' => 'سائن ان',
            'link_client_signup' => 'گاہک اکاؤنٹ بنائیں',
            'link_business_signup' => 'کاروبار رجسٹر کریں',
            'link_forgot' => 'پاس ورڈ بھول گئے',
            'link_download_apk' => 'ایپ ڈاؤن لوڈ کریں',
            'link_contact_email' => 'ای میل',
            'link_contact_phone' => 'فون',
            'link_contact_whatsapp' => 'WhatsApp',
            'example_what_is' => 'Duka Mkononi کیا ہے؟',
            'example_download' => 'ایپ کیسے ڈاؤن لوڈ کروں؟',
            'example_buy' => 'میں پروڈکٹ کیسے خریدوں؟',
            'example_sell' => 'میں بطور بیچنے والا کیسے رجسٹر کروں؟',
            'example_track' => 'اپنے آرڈر کی نگرانی کیسے کروں؟',
            'example_find_product' => 'مجھے کپڑے دکھائیں',
        ],
        'zh' => [
            'title' => 'Duka Mkononi 助手',
            'subtitle' => '询问我们的市场、运作方式或如何下载应用',
            'ask_placeholder' => '例如：我如何购买商品？',
            'ask_button' => '询问',
            'clear' => '清除',
            'loading' => '正在寻找答案...',
            'error' => '无法获取答案，请重试。',
            'retry' => '重试',
            'topics_title' => '我可以解释的主题',
            'examples_title' => '示例问题',
            'links_title' => '相关页面',
            'results_title' => '找到的商品',
            'businesses_title' => '找到的商家',
            'empty_state' => '提出一个问题，即可从我们的公开信息中获得答案。',
            'notice_public' => '我只依据 Duka Mkononi 的公开信息作答。',
            'notice_scope' => '我不能提供系统内部细节、密码或他人的私人数据。',
            'out_of_scope' => '这涉及我们的内部系统，因此我无法回答。我在这里帮助解答购买和销售问题。',
            'unknown' => '我在公开信息中没有找到这个。请查看以下页面或联系我们。',
            'no_results' => '我在公开目录中没有找到匹配的商品或商家。',
            'fallback_title' => '接下来可以看哪里',
            'contact_hint' => '如需快速帮助，请通过下方联系方式联系我们。',
            'topic_label_what_is' => 'Duka Mkononi 是什么',
            'topic_label_how_to_buy' => '如何购买',
            'topic_label_how_to_sell' => '如何销售',
            'topic_label_get_started' => '开始使用：账号与登录',
            'topic_label_download_app' => '下载应用',
            'topic_label_payments' => '支付',
            'topic_label_delivery_tracking' => '配送与订单追踪',
            'topic_label_trust_safety' => '信任与安全',
            'topic_label_contact_support' => '联系我们',
            'topic_what_is_title' => 'Duka Mkononi 是什么',
            'topic_what_is_body' => 'Duka Mkononi 是一个在线市场，把顾客与坦桑尼亚的真实商家连接起来：您可以看到商品和价格、下单，并追踪订单。',
            'topic_what_is_b1' => '每个商品都来自已注册的商家，而不是平台本身。',
            'topic_what_is_b2' => '您可以使用网站或手机应用。',
            'topic_how_to_buy_title' => '如何购买',
            'topic_how_to_buy_body' => '在“商品”页面或某个店铺页面选择商品，然后下单。',
            'topic_how_to_buy_b1' => '商家确认订单，下单时会显示支付选项。',
            'topic_how_to_buy_b2' => '您可以用订单编号追踪订单。',
            'topic_how_to_sell_title' => '如何销售',
            'topic_how_to_sell_body' => '注册商家，添加您的商品和价格，顾客就可以开始下单。',
            'topic_how_to_sell_b1' => '保持商品、库存和价格更新，让顾客能看到。',
            'topic_how_to_sell_b2' => '认证由平台团队决定，并显示在商家页面上。',
            'topic_get_started_title' => '开始使用：账号与登录',
            'topic_get_started_body' => '创建顾客账号或商家账号，然后登录。',
            'topic_get_started_b1' => '如果忘记密码，可以用您的电子邮箱重设。',
            'topic_get_started_b2' => '验证码仅在十分钟内有效。',
            'topic_download_app_title' => '下载应用',
            'topic_download_app_body' => 'Duka Mkononi 应用可通过我们网站公布的链接获取。',
            'topic_download_app_b1' => '在手机上打开该链接，允许安装，然后打开文件。',
            'topic_download_app_b2' => '如果安装不成功，请使用我们的网站或联系我们。',
            'topic_payments_title' => '支付',
            'topic_payments_body' => '完成订单时会向您显示支付选项。',
            'topic_payments_b1' => '切勿向个人号码汇款；请遵循显示的支付说明。',
            'topic_payments_b2' => '请保留订单和支付编号，以便获得支持。',
            'topic_delivery_tracking_title' => '配送与订单追踪',
            'topic_delivery_tracking_body' => '配送或自取由商家安排，您可以查看订单状态。',
            'topic_delivery_tracking_b1' => '如需查询具体订单，请先联系商家，然后再联系我们。',
            'topic_delivery_tracking_b2' => '您的订单编号能帮助我们快速找到订单。',
            'topic_trust_safety_title' => '信任与安全',
            'topic_trust_safety_body' => '已认证商家列在“商家”页面，顾客评价在平台上可见。',
            'topic_trust_safety_b1' => '付款前请查看商家名称、位置和商品。',
            'topic_trust_safety_b2' => '如有可疑之处，请在付款前联系我们。',
            'topic_contact_support_title' => '联系我们',
            'topic_contact_support_body' => '我们随时通过电子邮件、电话或 WhatsApp 提供帮助。',
            'topic_contact_support_b1' => '请提供您的订单编号，以便更快获得帮助。',
            'topic_contact_support_b2' => '这些联系方式与我们网站上公布的一致。',
            'link_home' => '首页',
            'link_shop' => '商品',
            'link_businesses' => '商家',
            'link_advertisements' => '广告',
            'link_track_orders' => '追踪订单',
            'link_login' => '登录',
            'link_client_signup' => '创建顾客账号',
            'link_business_signup' => '注册商家',
            'link_forgot' => '忘记密码',
            'link_download_apk' => '下载应用',
            'link_contact_email' => '电子邮件',
            'link_contact_phone' => '电话',
            'link_contact_whatsapp' => 'WhatsApp',
            'example_what_is' => 'Duka Mkononi 是什么？',
            'example_download' => '我如何下载应用？',
            'example_buy' => '我如何购买商品？',
            'example_sell' => '我如何注册成为卖家？',
            'example_track' => '我如何追踪订单？',
            'example_find_product' => '给我看看衣服',
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

        foreach (self::MESSAGES as $table) {
            $keys = array_keys($table);
            sort($keys);
            if ($keys !== $english) {
                return false;
            }
        }

        return true;
    }

    /** Every topic and link has a label in every locale. */
    public static function labelsComplete(): bool
    {
        foreach (array_keys(self::MESSAGES) as $locale) {
            foreach (self::TOPICS as $topic) {
                if (self::msg('topic_'.$topic.'_title', $locale) === 'topic_'.$topic.'_title') {
                    return false;
                }
                if (self::msg('topic_label_'.$topic, $locale) === 'topic_label_'.$topic) {
                    return false;
                }
                if (self::msg('topic_'.$topic.'_body', $locale) === 'topic_'.$topic.'_body') {
                    return false;
                }
            }
            foreach (array_keys(self::SECTION_LINKS + self::DEFAULT_EXTERNAL_LINKS) as $id) {
                if (self::msg('link_'.$id, $locale) === 'link_'.$id) {
                    return false;
                }
            }
        }

        return true;
    }
}
