<?php

namespace App\Services\Ai;

/**
 * Capability #11 - Conversational Shopping Assistant.
 *
 * Understands a shopper's natural-language request (Swahili, English or a
 * mix: "Natafuta breki za Toyota Harrier.") and turns it into a structured
 * parse that is ranked over the PUBLIC catalogue only.
 *
 * Design rules (unit-tested, all pure - no model, no HTTP, no database):
 *  - the deterministic parser always works; the model may only PROPOSE a
 *    parse that survives sanitizeModelParse() (same allow-listed shape);
 *  - PublicCatalog already enforces public-visibility and availability
 *    rules - this class never sees an ineligible row and never invents one;
 *  - strict matching is AND over the query's significant terms; when strict
 *    finds nothing we relax to OR and, if the relaxed matches span several
 *    distinct categories, ask an honest clarifying question built from
 *    those real categories instead of guessing the intent;
 *  - every returned product carries its real price, stock flag and the
 *    business identity from the catalogue, plus WHY it matched.
 */
class ShoppingAssistant
{
    public const CAPABILITY = 'shopping_assistant';

    public const SORTS = ['relevance', 'price_asc', 'price_desc'];

    /** Cap on significant terms taken from one query. */
    public const MAX_TERMS = 6;

    /**
     * Words that carry no product meaning (Swahili + English shop speak).
     * Everything here is dropped before matching, so a shopper's filler
     * never becomes an AND condition that can never match.
     */
    private const FILLER = [
        // English
        'the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'want',
        'needs', 'need', 'looking', 'please', 'some', 'any', 'are', 'was',
        'were', 'how', 'much', 'many', 'what', 'where', 'which', 'when',
        'does', 'sell', 'sells', 'buy', 'get', 'show', 'find', 'product',
        'products', 'item', 'items', 'thing', 'things', 'cheap', 'cheapest',
        'shop', 'store', 'online', 'today', 'now', 'really', 'very', 'just',
        'also', 'one', 'two', 'three', 'my', 'our', 'your', 'there', 'here',
        // Swahili
        'natafuta',        'tafuta', 'nataka', 'nahitaji', 'hitaji',
        'niko', 'nina', 'nitafute', 'utafute', 'mimi', 'sisi', 'wewe',
        'kwenye', 'katika', 'ndani', 'hii', 'hizi', 'hizo', 'hiyo', 'hayo',
        'yangu', 'wangu', 'yako', 'wako', 'yake', 'wake', 'lete', 'leta',
        'beba', 'mbuya',        'bidhaa', 'mambo', 'kitu', 'vitu',
        'hilo', 'hili', 'lile', 'yule', 'wale', 'pale', 'hapa', 'sasa',
        'basi', 'ndio', 'sana', 'tu', 'pia', 'kabisa', 'zaidi', 'chini',
        'leo', 'jana', 'kesho', 'duka', 'maduka', 'nisaidie', 'tafadhali',
        'shukrani', 'habari', 'ndiyo', 'sindio', 'karibu', 'hakuna',
        // Swahili function words / interrogatives (never product content).
        'za', 'ya', 'wa', 'la', 'kwa', 'na', 'au', 'ili', 'je', 'kama',
        'ila', 'hadi', 'zilizo', 'bei', 'gani', 'ipi', 'zipi',
        'lipi', 'lingine', 'sokoni', 'mtandaoni', 'nzuri',
        'bora', 'mpya', 'ghafi', 'halali',
        // English interrogatives / possessives left after tokenisation.
        'whose', 'into', 'onto', 'than', 'them', 'they', 'their', 'these',
        'those', 'been', 'will', 'would', 'could', 'should', 'about',
    ];

    /**
     * Sample shopper questions per locale (used for empty-input hints and
     * suggestion chips). Kept server-side so web and mobile show the same
     * examples without duplicating copy in 16 client files.
     */
    public const SAMPLES = [
        'sw' => [
            'Natafuta breki za Toyota Harrier',
            'Sneakers nafuu chini ya 30000',
            'Vifaa vya jikoni',
            'Duka la dawa karibu na Dar',
            'Biskuti zilizo stock',
        ],
        'en' => [
            'Brake pads for Toyota Harrier',
            'Affordable sneakers under 30000',
            'Kitchen appliances',
            'Pharmacy near Dar',
            'Biscuits in stock',
        ],
        'es' => [
            'Frenos para Toyota Harrier',
            'Zapatillas baratas bajo 30000',
            'Artículos de cocina',
            'Farmacia cerca de Dar',
            'Galletas en stock',
        ],
        'fr' => [
            'Freins pour Toyota Harrier',
            'Sneakers abordables moins de 30000',
            'Appareils de cuisine',
            'Pharmacie près de Dar',
            'Biscuits en stock',
        ],
        'zh' => [
            '丰田Harrier刹车片',
            '30000以内的平价运动鞋',
            '厨房用具',
            '达累斯萨拉姆附近的药店',
            '有货的饼干',
        ],
        'hi' => [
            'Toyota Harrier के ब्रेक पैड',
            '30000 से सस्ते स्नीकर्स',
            'रसोई के उपकरण',
            'दार के पास फ़ार्मेसी',
            'स्टॉक में बिस्किट',
        ],
        'ur' => [
            'ٹویوٹا ہریئر کے بریک پیڈ',
            '30000 سے سستے سنیکرز',
            'باورچی خانے کے آلات',
            'ڈیرے کے قریب فارمیسی',
            'اسٹاک میں بسکٹ',
        ],
        'de' => [
            'Bremsen für Toyota Harrier',
            'Günstige Sneaker unter 30000',
            'Küchengeräte',
            'Apotheke bei Dar',
            'Kekse auf Lager',
        ],
    ];

    /**
     * Server-side strings for the API response. Keys mirror the client
     * locale files; messagesComplete() proves every locale carries them.
     */
    public const MESSAGES = [
        'sw' => [
            'query_required' => 'Tafadhali andika unachotafuta.',
            'error_generic' => 'Imeshindwa kutafuta kwa sasa. Tafadhali jaribu tena.',
            'summary_results' => 'Bidhaa {count} zinazolingana na "{query}".',
            'summary_relaxed' => 'Hakuna mchanganyiko kamili, hizi ni karibu zaidi na "{query}".',
            'summary_none' => 'Hakuna bidhaa wazi zinazolingana na "{query}".',
            'summary_businesses' => 'Biashara {count} zinazolingana.',
            'clarify_q' => 'Ninaweza kukusaidia vizuri zaidi. Je, unamaanisha: {options}?',
            'ai_fallback' => 'Utambuzi wa lugha ya kawaida umetumika; andika maneno zaidi kwa matokeo bora.',
            'limit_prices' => 'Bei zimewekwa na muuzaji na zinaweza kubadilika.',
            'limit_stock' => 'Upatikanaji unategemea stock iliyorekodiwa sasa hivi.',
            'limit_public' => 'Ni bidhaa za biashara zilizo wazi pekee zinazoonyeshwa.',
        ],
        'en' => [
            'query_required' => 'Please type what you are looking for.',
            'error_generic' => 'Search failed right now. Please try again.',
            'summary_results' => '{count} products match "{query}".',
            'summary_relaxed' => 'No exact combination; these are the closest to "{query}".',
            'summary_none' => 'No open products match "{query}".',
            'summary_businesses' => '{count} businesses match.',
            'clarify_q' => 'I can help better. Do you mean: {options}?',
            'ai_fallback' => 'Plain keyword matching was used; add more words for better results.',
            'limit_prices' => 'Prices are set by the seller and may change.',
            'limit_stock' => 'Availability depends on the stock recorded right now.',
            'limit_public' => 'Only products from publicly listed businesses are shown.',
        ],
        'es' => [
            'query_required' => 'Escribe lo que buscas.',
            'error_generic' => 'La búsqueda falló. Inténtalo de nuevo.',
            'summary_results' => '{count} productos coinciden con "{query}".',
            'summary_relaxed' => 'No hay combinación exacta; estos se acercan más a "{query}".',
            'summary_none' => 'Ningún producto disponible coincide con "{query}".',
            'summary_businesses' => '{count} negocios coinciden.',
            'clarify_q' => 'Puedo ayudarte mejor. ¿Te refieres a: {options}?',
            'ai_fallback' => 'Se usó búsqueda por palabras clave; añade más palabras para mejores resultados.',
            'limit_prices' => 'Los precios los fija el vendedor y pueden cambiar.',
            'limit_stock' => 'La disponibilidad depende del stock registrado ahora mismo.',
            'limit_public' => 'Solo se muestran productos de negocios públicos.',
        ],
        'fr' => [
            'query_required' => 'Veuillez écrire ce que vous cherchez.',
            'error_generic' => 'La recherche a échoué. Veuillez réessayer.',
            'summary_results' => '{count} produits correspondent à « {query} ».',
            'summary_relaxed' => 'Aucune combinaison exacte ; voici les plus proches de « {query} ».',
            'summary_none' => 'Aucun produit disponible ne correspond à « {query} ».',
            'summary_businesses' => '{count} entreprises correspondent.',
            'clarify_q' => 'Je peux mieux aider. Voulez-vous dire : {options} ?',
            'ai_fallback' => 'Recherche par mots-clés utilisée ; ajoutez des mots pour de meilleurs résultats.',
            'limit_prices' => 'Les prix sont fixés par le vendeur et peuvent changer.',
            'limit_stock' => 'La disponibilité dépend du stock enregistré actuellement.',
            'limit_public' => 'Seuls les produits des entreprises publiques sont affichés.',
        ],
        'zh' => [
            'query_required' => '请输入您要找的商品。',
            'error_generic' => '搜索暂时失败，请重试。',
            'summary_results' => '有 {count} 件商品与“{query}”匹配。',
            'summary_relaxed' => '没有完全匹配的组合；以下是最接近“{query}”的结果。',
            'summary_none' => '没有在售商品与“{query}”匹配。',
            'summary_businesses' => '有 {count} 家商家匹配。',
            'clarify_q' => '我可以更好地帮助您。您是指：{options} 吗？',
            'ai_fallback' => '已使用关键词匹配；添加更多词语可获得更好结果。',
            'limit_prices' => '价格由卖家设定，可能会变动。',
            'limit_stock' => '库存情况取决于当前记录的库存。',
            'limit_public' => '仅显示公开上市商家的商品。',
        ],
        'hi' => [
            'query_required' => 'कृपया वह लिखें जो आप ढूंढ रहे हैं।',
            'error_generic' => 'खोज अभी विफल रही। कृपया पुनः प्रयास करें।',
            'summary_results' => '{count} उत्पाद "{query}" से मेल खाते हैं।',
            'summary_relaxed' => 'कोई सटीक संयोजन नहीं; ये "{query}" के सबसे करीब हैं।',
            'summary_none' => 'कोई उपलब्ध उत्पाद "{query}" से मेल नहीं खाता।',
            'summary_businesses' => '{count} व्यवसाय मेल खाते हैं।',
            'clarify_q' => 'मैं बेहतर मदद कर सकता हूँ। क्या आपका मतलब है: {options}?',
            'ai_fallback' => 'कीवर्ड खोज का उपयोग किया गया; बेहतर परिणामों के लिए और शब्द जोड़ें।',
            'limit_prices' => 'कीमतें विक्रेता तय करता है और बदल सकती हैं।',
            'limit_stock' => 'उपलब्धता अभी दर्ज स्टॉक पर निर्भर है।',
            'limit_public' => 'केवल सार्वजनिक व्यवसायों के उत्पाद दिखाए जाते हैं।',
        ],
        'ur' => [
            'query_required' => 'براہ کرم لکھیں آپ کیا تلاش کر رہے ہیں۔',
            'error_generic' => 'تلاش اس وقت ناکام ہوئی۔ براہ کرم دوبارہ کوشش کریں۔',
            'summary_results' => '{count} مصنوعات "{query}" سے ملتی ہیں۔',
            'summary_relaxed' => 'کوئی بالکل مماثل مجموعہ نہیں؛ یہ "{query}" کے قریب تر ہیں۔',
            'summary_none' => 'کوئی موجودہ مصنوعات "{query}" سے میل نہیں کھاتیں۔',
            'summary_businesses' => '{count} کاروبار میل کھاتے ہیں۔',
            'clarify_q' => 'میں بہتر مدد کر سکتا ہوں۔ کیا آپ کا مراد ہے: {options}؟',
            'ai_fallback' => 'کی ورڈز تلاش استعمال ہوئی؛ بہتر نتائج کے لیے مزید الفاظ شامل کریں۔',
            'limit_prices' => 'قیمتیں فروخت کنندہ طے کرتا ہیں اور بدل سکتی ہیں۔',
            'limit_stock' => 'دستیابی اس وقت درج اسٹاک پر منحصر ہے۔',
            'limit_public' => 'صرف عوامی کاروبار کی مصنوعات دکھائی جاتی ہیں۔',
        ],
        'de' => [
            'query_required' => 'Bitte schreiben Sie, was Sie suchen.',
            'error_generic' => 'Die Suche ist fehlgeschlagen. Bitte erneut versuchen.',
            'summary_results' => '{count} Produkte passen zu "{query}".',
            'summary_relaxed' => 'Keine exakte Kombination; dies sind die nächsten zu "{query}".',
            'summary_none' => 'Keine verfügbaren Produkte passen zu "{query}".',
            'summary_businesses' => '{count} Geschäfte passen.',
            'clarify_q' => 'Ich kann besser helfen. Meinen Sie: {options}?',
            'ai_fallback' => 'Schlüsselwortsuche verwendet; fügen Sie mehr Wörter für bessere Ergebnisse hinzu.',
            'limit_prices' => 'Preise legt der Verkäufer fest und können sich ändern.',
            'limit_stock' => 'Die Verfügbarkeit hängt vom aktuell erfassten Bestand ab.',
            'limit_public' => 'Nur Produkte öffentlich gelisteter Geschäfte werden angezeigt.',
        ],
    ];

    /* ------------------------------------------------------------------ */
    /* Parsing (pure)                                                       */
    /* ------------------------------------------------------------------ */

    /** Lowercase, keep letters/digits, collapse spaces (unicode-safe). */
    public static function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N},]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * Turn a shopper's sentence into an allow-listed structured parse.
     * Never throws; a query with no usable content yields empty fields.
     *
     * @return array{terms:array<int,string>,phrase:?string,category:?string,
     *   location:?string,price_min:?int,price_max:?int,in_stock:?bool,sort:string}
     */
    public static function deterministicParse(string $query): array
    {
        $parse = self::emptyParse();
        $text = mb_strtolower(trim($query));

        // 1. Quoted phrase (exact-ish intent), removed from the token stream.
        if (preg_match('/["\x{201C}\x{2018}]([^"\x{201D}\x{2019}]{2,60})["\x{201D}\x{2019}]/u', $text, $m)) {
            $phrase = self::normalise($m[1]);
            if ($phrase !== '') {
                $parse['phrase'] = $phrase;
            }
            $text = str_replace($m[0], ' ', $text);
        }

        // 2. Price bounds: keyword + number pairs (both languages).
        $maxPatterns = [
            'chini ya', 'bei ya chini ya', 'under', 'below', 'less than',
            'not more than', 'hadi', 'maximum', 'kiwango cha chini ya',
        ];
        $minPatterns = [
            'zaidi ya', 'angalau', 'above', 'more than', 'at least',
            'starting from', 'from', 'juu ya', 'minimum',
        ];
        foreach ($maxPatterns as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\s+([0-9][0-9,.]*)/u', $text, $m)) {
                $parse['price_max'] = self::toInt($m[1]);
                $text = str_replace($m[0], ' ', $text);
            }
        }
        foreach ($minPatterns as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\s+([0-9][0-9,.]*)/u', $text, $m)) {
                $parse['price_min'] = self::toInt($m[1]);
                $text = str_replace($m[0], ' ', $text);
            }
        }
        if ($parse['price_min'] !== null && $parse['price_max'] !== null && $parse['price_min'] > $parse['price_max']) {
            [$parse['price_min'], $parse['price_max']] = [$parse['price_max'], $parse['price_min']];
        }

        // 3. Availability words (longest alternatives first).
        if (preg_match('/\b(in stock|stock ipo|yenye stock|available|availability|ipo|zipo|stock)\b/u', $text, $m)) {
            $parse['in_stock'] = true;
            $text = str_replace($m[0], ' ', $text);
        }

        // 4. Cheapest-first intent (only when no explicit price bound says otherwise).
        if (preg_match('/\b(cheap|cheapest|nafuu|bei ya chini|rahisi zaidi|afordi|affordable)\b/u', $text, $m)) {
            if ($parse['price_max'] === null && $parse['price_min'] === null) {
                $parse['sort'] = 'price_asc';
            }
            $text = str_replace($m[0], ' ', $text);
        }

        // 5. Significant tokens become AND terms.
        $tokens = explode(' ', self::normalise($text));
        foreach ($tokens as $token) {
            $token = trim($token, " \t\n\r\0\x0B,.");
            if ($token === '' || mb_strlen($token) < 2) {
                continue;
            }
            if (in_array($token, self::FILLER, true)) {
                continue;
            }
            if (in_array($token, $parse['terms'], true)) {
                continue;
            }
            $parse['terms'][] = $token;
            if (count($parse['terms']) >= self::MAX_TERMS) {
                break;
            }
        }

        return $parse;
    }

    /** The allow-listed shape every parse must fit. */
    public static function emptyParse(): array
    {
        return [
            'terms' => [],
            'phrase' => null,
            'category' => null,
            'location' => null,
            'price_min' => null,
            'price_max' => null,
            'in_stock' => null,
            'business_id' => null,
            'sort' => 'relevance',
        ];
    }

    /**
     * Validate a model-proposed parse. Anything outside the allow-listed
     * shape is dropped - the model can never introduce a new field, and a
     * malformed value degrades to null instead of poisoning the search.
     */
    public static function sanitizeModelParse($raw): array
    {
        $parse = self::emptyParse();
        if (! is_array($raw)) {
            return $parse;
        }

        if (isset($raw['terms']) && is_array($raw['terms'])) {
            foreach (array_slice($raw['terms'], 0, self::MAX_TERMS) as $term) {
                if (! is_string($term)) {
                    continue;
                }
                $term = self::normalise($term);
                if (mb_strlen($term) < 2 || mb_strlen($term) > 40) {
                    continue;
                }
                if (! in_array($term, $parse['terms'], true)) {
                    $parse['terms'][] = $term;
                }
            }
        }

        if (is_string($raw['phrase'] ?? null)) {
            $phrase = self::normalise($raw['phrase']);
            if (mb_strlen($phrase) >= 2 && mb_strlen($phrase) <= 80) {
                $parse['phrase'] = $phrase;
            }
        }
        foreach (['category', 'location'] as $field) {
            if (is_string($raw[$field] ?? null)) {
                $value = self::normalise($raw[$field]);
                if (mb_strlen($value) >= 2 && mb_strlen($value) <= 60) {
                    $parse[$field] = $value;
                }
            }
        }
        foreach (['price_min', 'price_max'] as $field) {
            if (is_numeric($raw[$field] ?? null)) {
                $value = (int) $raw[$field];
                if ($value >= 0) {
                    $parse[$field] = $value;
                }
            }
        }
        if ($parse['price_min'] !== null && $parse['price_max'] !== null && $parse['price_min'] > $parse['price_max']) {
            [$parse['price_min'], $parse['price_max']] = [$parse['price_max'], $parse['price_min']];
        }
        if (is_bool($raw['in_stock'] ?? null)) {
            $parse['in_stock'] = $raw['in_stock'];
        }
        if (is_string($raw['sort'] ?? null) && in_array($raw['sort'], self::SORTS, true)) {
            $parse['sort'] = $raw['sort'];
        }

        return $parse;
    }

    /**
     * Layer one parse over another: explicit values in $override win.
     * Terms only override when the override actually has terms, so a
     * follow-up like "chini ya 20000" keeps the previous topic.
     */
    public static function mergeParse(array $base, array $override): array
    {
        $merged = array_merge(self::emptyParse(), $base);
        $override = array_merge(self::emptyParse(), $override);

        if ($override['terms'] !== []) {
            $merged['terms'] = $override['terms'];
        }
        if ($override['phrase'] !== null) {
            $merged['phrase'] = $override['phrase'];
        }
        foreach (['category', 'location'] as $field) {
            if ($override[$field] !== null) {
                $merged[$field] = $override[$field];
            }
        }
        foreach (['price_min', 'price_max', 'in_stock'] as $field) {
            if ($override[$field] !== null) {
                $merged[$field] = $override[$field];
            }
        }
        if ($override['sort'] !== 'relevance') {
            $merged['sort'] = $override['sort'];
        }

        return $merged;
    }

    /* ------------------------------------------------------------------ */
    /* Ranking (pure)                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Strict ranking: a product must match the phrase (when given) AND every
     * term, then pass the hard filters. Score explains the rank; the reason
     * names the field that matched best.
     *
     * @param  array<int,array<string,mixed>>  $products  rows from PublicCatalog::products()
     * @return array<int,array<string,mixed>> capped, annotated rows
     */
    public static function rank(array $products, array $parse, int $limit = 12): array
    {
        $parse = array_merge(self::emptyParse(), $parse);
        $limit = max(1, $limit);
        $out = [];

        foreach ($products as $product) {
            if (! self::passesFilters($product, $parse)) {
                continue;
            }

            if (! self::matchesAll($product, $parse)) {
                continue;
            }

            [$score, $reason] = self::scoreProduct($product, $parse);

            $row = $product;
            $row['match_score'] = $score;
            $row['match_reason'] = $reason !== null ? $reason : 'filters';
            $row['match'] = 'strict';
            $out[] = $row;
        }

        return self::sortRows($out, $parse['sort'], $limit);
    }

    /**
     * Relaxed ranking (OR over terms) used when strict finds nothing: real
     * rows only, clearly tagged as approximate so no UI can imply an exact
     * match that did not happen.
     */
    public static function relax(array $products, array $parse, int $limit = 12): array
    {
        $parse = array_merge(self::emptyParse(), $parse);
        $limit = max(1, $limit);
        $out = [];

        foreach ($products as $product) {
            if (! self::passesFilters($product, $parse)) {
                continue;
            }

            [$score, $reason] = self::scoreProduct($product, $parse);
            if ($score <= 0) {
                continue; // OR semantics: at least one signal must hit
            }

            $row = $product;
            $row['match_score'] = $score;
            $row['match_reason'] = $reason !== null ? $reason : 'filters';
            $row['match'] = 'relaxed';
            $out[] = $row;
        }

        return self::sortRows($out, 'relevance', $limit);
    }

    /**
     * Clarification is warranted only when strict found nothing, relaxed
     * found real rows, and those rows span at least two genuinely different
     * interpretations (categories). One category is not ambiguity - that is
     * simply a close match, so it returns as normal results.
     *
     * @param  array<int,array<string,mixed>>  $strict
     * @param  array<int,array<string,mixed>>  $relaxed
     */
    public static function needsClarification(array $strict, array $relaxed): bool
    {
        if ($strict !== [] || $relaxed === []) {
            return false;
        }

        $categories = [];
        foreach ($relaxed as $row) {
            $category = mb_strtolower(trim((string) ($row['category'] ?? '')));
            if ($category !== '') {
                $categories[$category] = true;
            }
        }

        return count($categories) >= 2;
    }

    /**
     * Refinement options built ONLY from rows that actually exist:
     * distinct categories of the current matches (plus matched businesses
     * when categories alone are too few to choose from).
     */
    public static function refinementOptions(array $matches, int $limit = 4): array
    {
        $limit = max(1, $limit);
        $options = [];
        $counts = [];

        foreach ($matches as $row) {
            $category = trim((string) ($row['category'] ?? ''));
            if ($category === '') {
                continue;
            }
            $key = mb_strtolower($category);
            if (! isset($counts[$key])) {
                $counts[$key] = ['label' => $category, 'count' => 0];
            }
            $counts[$key]['count']++;
        }
        uasort($counts, function ($a, $b) {
            return [$b['count'], $a['label']] <=> [$a['count'], $b['label']];
        });
        foreach ($counts as $count) {
            $options[] = ['type' => 'category', 'label' => $count['label'], 'category' => mb_strtolower($count['label']), 'count' => $count['count']];
            if (count($options) >= $limit) {
                return $options;
            }
        }

        // Fewer than two categories: offer the real matched businesses.
        if (count($options) < 2) {
            $businesses = [];
            foreach ($matches as $row) {
                $name = trim((string) ($row['business_name'] ?? ''));
                $id = (string) ($row['business_id'] ?? '');
                if ($name === '' || $id === '' || isset($businesses[$id])) {
                    continue;
                }
                $businesses[$id] = ['type' => 'business', 'label' => $name, 'business_id' => $id];
            }
            foreach ($businesses as $business) {
                $options[] = $business;
                if (count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * Public businesses that match the terms (any-term scoring - a business
     * is a coarse target, so requiring every term would hide good matches).
     *
     * @param  array<int,array<string,mixed>>  $businesses  rows from PublicCatalog::shopBusinesses()
     * @return array<int,array<string,mixed>>
     */
    public static function matchBusinesses(array $businesses, array $parse, int $limit = 5): array
    {
        $parse = array_merge(self::emptyParse(), $parse);
        $limit = max(1, $limit);

        if ($parse['terms'] === [] && $parse['phrase'] === null) {
            return [];
        }

        $out = [];
        foreach ($businesses as $business) {
            $name = mb_strtolower((string) ($business['business_name'] ?? ''));
            $location = mb_strtolower((string) ($business['business_location'] ?? ''));
            $type = mb_strtolower((string) ($business['business_type'] ?? ''));
            $description = mb_strtolower((string) ($business['business_description'] ?? ''));
            $haystack = $name . ' ' . $location . ' ' . $type . ' ' . $description;

            if ($parse['location'] !== null && ! str_contains($location, $parse['location'])) {
                continue;
            }

            $score = 0;
            $reason = null;
            if ($parse['phrase'] !== null && str_contains($haystack, $parse['phrase'])) {
                $score += 6;
                $reason = 'phrase';
            }
            foreach ($parse['terms'] as $term) {
                if (self::termMatches($name, $term)) {
                    $score += 4;
                    $reason = $reason ?? 'name';
                } elseif (self::termMatches($type, $term) || self::termMatches($location, $term)) {
                    $score += 3;
                    $reason = $reason ?? 'location';
                } elseif (self::termMatches($description, $term)) {
                    $score += 1;
                    $reason = $reason ?? 'description';
                }
            }
            if ($score <= 0) {
                continue;
            }

            $row = $business;
            $row['match_score'] = $score;
            $row['match_reason'] = $reason ?? 'name';
            $out[] = $row;
        }

        usort($out, function ($a, $b) {
            return [$b['match_score'], $a['business_name'] ?? ''] <=> [$a['match_score'], $b['business_name'] ?? ''];
        });

        return array_slice($out, 0, $limit);
    }

    /** Distinct categories of the current matches with counts (real data only). */
    public static function categories(array $matches, int $limit = 6): array
    {
        $counts = [];
        foreach ($matches as $row) {
            $label = trim((string) ($row['category'] ?? ''));
            if ($label === '') {
                continue;
            }
            $key = mb_strtolower($label);
            if (! isset($counts[$key])) {
                $counts[$key] = ['category' => $label, 'count' => 0];
            }
            $counts[$key]['count']++;
        }
        uasort($counts, function ($a, $b) {
            return [$b['count'], $a['category']] <=> [$a['count'], $b['category']];
        });

        return array_slice(array_values($counts), 0, max(1, $limit));
    }

    /* ------------------------------------------------------------------ */
    /* Model-assisted parse (optional; same allow-list as the pure parser)  */
    /* ------------------------------------------------------------------ */

    /**
     * Ask the model to structure a mixed-language request into the SAME
     * allow-listed parse. Purely optional: when the provider is off or the
     * call fails we return parse=null and the caller stays deterministic.
     *
     * @param  array<string,mixed>  $previous  the previous interpreted parse (bounded context)
     * @return array{parse:?array,ai:array<string,mixed>}
     */
    public static function parseWithAi(string $query, array $previous, string $locale, ?string $ip): array
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

            return ['parse' => null, 'ai' => $ai];
        }
        if (! $client->isConfigured()) {
            $ai['code'] = 'AI_NOT_CONFIGURED';
            $ai['reason'] = AiException::reasonForCode('AI_NOT_CONFIGURED', $locale);

            return ['parse' => null, 'ai' => $ai];
        }

        $previousSummary = self::summariseParse($previous);

        $instruction = implode("\n", [
            'You structure ONE shopper\'s request into a search parse for a small-shop catalogue.',
            'Return STRICT JSON only: {"terms": string[], "phrase": string|null, "category": string|null, "location": string|null, "price_min": int|null, "price_max": int|null, "in_stock": bool|null, "sort": "relevance"|"price_asc"|"price_desc"}.',
            'terms: 1-6 significant lowercase product words (drop filler like "natafuta", "the", "bidhaa").',
            'Mixed Swahili/English is normal: "Natafuta breki za Toyota Harrier" -> terms ["breki","toyota","harrier"].',
            'category/location: only when the request clearly names one; lowercase, null otherwise.',
            'price_min/price_max: integer Tanzanian shillings only when the request states a bound.',
            'in_stock: true only when the shopper explicitly asks for availability.',
            'Never invent products, prices or brands. Unknown fields are null.',
            $previousSummary !== '' ? 'Previous turn context: '.$previousSummary : '',
        ]);

        try {
            $result = $client->generateJson(
                [['text' => $query]],
                $instruction,
                [
                    'capability' => self::CAPABILITY.'_parse',
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 220,
                ],
            );

            $ai['available'] = true;
            $ai['degraded'] = false;
            $ai['meta'] = $result->meta();

            return ['parse' => self::sanitizeModelParse($result->data), 'ai' => $ai];
        } catch (AiException $error) {
            $ai['code'] = $error->errorCode;
            $ai['reason'] = AiException::reasonForCode($error->errorCode, $locale);

            return ['parse' => null, 'ai' => $ai];
        }
    }

    /** Bounded, safe one-line summary of the previous parse for the prompt. */
    private static function summariseParse(array $parse): string
    {
        $parse = array_merge(self::emptyParse(), $parse);
        $parts = [];
        if ($parse['terms'] !== []) {
            $parts[] = 'terms='.implode(',', array_slice($parse['terms'], 0, self::MAX_TERMS));
        }
        if ($parse['category'] !== null) {
            $parts[] = 'category='.$parse['category'];
        }
        if ($parse['price_max'] !== null) {
            $parts[] = 'price_max='.$parse['price_max'];
        }
        if ($parse['price_min'] !== null) {
            $parts[] = 'price_min='.$parse['price_min'];
        }

        return mb_substr(implode(' ', $parts), 0, 160);
    }

    /* ------------------------------------------------------------------ */
    /* Localized server-side strings                                        */
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
        foreach (array_keys(self::MESSAGES) as $locale) {
            if (count(self::SAMPLES[$locale] ?? []) < 3) {
                return false;
            }
        }

        return true;
    }

    /** Localized example questions (fallback: English). */
    public static function samples(string $locale): array
    {
        return self::SAMPLES[$locale] ?? self::SAMPLES['en'];
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                            */
    /* ------------------------------------------------------------------ */

    /** Hard visibility/availability/price gates - never scored, only pass/fail. */
    private static function passesFilters(array $product, array $parse): bool
    {
        $price = (float) ($product['selling_price'] ?? 0);

        if ($parse['price_min'] !== null && $price < (float) $parse['price_min']) {
            return false;
        }
        if ($parse['price_max'] !== null && $price > (float) $parse['price_max']) {
            return false;
        }
        if ($parse['in_stock'] === true && empty($product['available'])) {
            return false;
        }
        if (! empty($parse['business_id']) && (string) ($product['business_id'] ?? '') !== (string) $parse['business_id']) {
            return false;
        }
        if ($parse['category'] !== null) {
            $productCategory = mb_strtolower((string) ($product['category'] ?? ''));
            if ($productCategory === '' || (! str_contains($productCategory, $parse['category']) && ! str_contains($parse['category'], $productCategory))) {
                return false;
            }
        }
        if ($parse['location'] !== null) {
            $location = mb_strtolower((string) ($product['business_location'] ?? ''));
            if ($location === '' || ! str_contains($location, $parse['location'])) {
                return false;
            }
        }

        return true;
    }

    /** [score, best-reason] for one product under the parse. */
    private static function scoreProduct(array $product, array $parse): array
    {
        $name = mb_strtolower((string) ($product['name'] ?? ''));
        $category = mb_strtolower((string) ($product['category'] ?? ''));
        $description = mb_strtolower((string) ($product['description'] ?? ''));
        $business = mb_strtolower((string) ($product['business_name'] ?? ''));

        $score = 0;
        $reason = null;
        $best = 0;
        $termsInName = 0;            foreach ($parse['terms'] as $term) {
                if (self::termMatches($name, $term)) {
                    $score += 4;
                    $termsInName++;
                    if ($best < 4) {
                        $best = 4;
                        $reason = 'name';
                    }
                } elseif (self::termMatches($category, $term)) {
                    $score += 3;
                    if ($best < 3) {
                        $best = 3;
                        $reason = 'category';
                    }
                } elseif (self::termMatches($description, $term)) {
                    $score += 1;
                    if ($best < 1) {
                        $best = 1;
                        $reason = 'description';
                    }
                } elseif (self::termMatches($business, $term)) {
                    $score += 1;
                    if ($best < 1) {
                        $best = 1;
                        $reason = 'business';
                    }
                }
            }

        if ($parse['terms'] !== [] && $termsInName === count($parse['terms'])) {
            $score += 2; // every term in the name: strongest signal
        }
        if ($parse['phrase'] !== null && self::matchesPhrase($product, $parse['phrase'])) {
            $score += 6;
            if ($best < 6) {
                $best = 6;
                $reason = 'phrase';
            }
        }

        return [$score, $reason];
    }

    private static function matchesPhrase(array $product, string $phrase): bool
    {
        $haystack = mb_strtolower(trim(implode(' ', [
            (string) ($product['name'] ?? ''),
            (string) ($product['category'] ?? ''),
            (string) ($product['description'] ?? ''),
            (string) ($product['business_name'] ?? ''),
        ])));

        return self::phraseMatches($haystack, $phrase);
    }

    /**
     * Strict AND semantics: the phrase (when given) and EVERY term must
     * appear in the product's name/category/description/business text.
     */
    private static function matchesAll(array $product, array $parse): bool
    {
        $haystack = mb_strtolower(trim(implode(' ', [
            (string) ($product['name'] ?? ''),
            (string) ($product['category'] ?? ''),
            (string) ($product['description'] ?? ''),
            (string) ($product['business_name'] ?? ''),
        ])));

        if ($parse['phrase'] !== null && ! self::phraseMatches($haystack, $parse['phrase'])) {
            return false;
        }
        foreach ($parse['terms'] as $term) {
            if (! self::termMatches($haystack, $term)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Substring match with a tiny plural allowance: "pads" matches "pad",
     * "phones" matches "phone". Nothing more - inventing a stemmer would
     * make matches we could not honestly explain.
     */
    private static function termMatches(string $haystack, string $term): bool
    {
        if (str_contains($haystack, $term)) {
            return true;
        }

        $singular = preg_replace('/s$/', '', $term);
        if ($singular !== null && $singular !== $term && mb_strlen($singular) >= 3 && str_contains($haystack, $singular)) {
            return true;
        }

        return false;
    }

    /** Phrase match with the same plural allowance as termMatches(). */
    private static function phraseMatches(string $haystack, string $phrase): bool
    {
        if (str_contains($haystack, $phrase)) {
            return true;
        }

        foreach (explode(' ', $phrase) as $word) {
            if ($word !== '' && ! self::termMatches($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Stable sort: score/price first as requested, then name for a total
     * order so the same query never shuffles between calls.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private static function sortRows(array $rows, string $sort, int $limit): array
    {
        usort($rows, function ($a, $b) use ($sort) {
            if ($sort === 'price_asc') {
                $cmp = [(float) $a['selling_price'], (string) ($a['name'] ?? '')] <=> [(float) $b['selling_price'], (string) ($b['name'] ?? '')];
                if ($cmp !== 0) {
                    return $cmp;
                }
            } elseif ($sort === 'price_desc') {
                $cmp = [(float) $b['selling_price'], (string) ($a['name'] ?? '')] <=> [(float) $a['selling_price'], (string) ($b['name'] ?? '')];
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            $cmp = [(int) ($b['match_score'] ?? 0), (string) ($a['name'] ?? '')] <=> [(int) ($a['match_score'] ?? 0), (string) ($b['name'] ?? '')];

            return $cmp;
        });

        return array_slice($rows, 0, $limit);
    }

    /** "5,000" / "5000.5" / "5 000" -> int (0 when unparseable). */
    private static function toInt(string $value): ?int
    {
        $clean = str_replace([',', ' '], '', $value);
        if (! preg_match('/^\d+(\.\d+)?$/', $clean)) {
            return null;
        }

        return (int) (float) $clean;
    }
}
