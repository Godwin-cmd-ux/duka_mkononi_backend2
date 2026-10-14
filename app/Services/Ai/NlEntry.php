<?php

namespace App\Services\Ai;

use App\Services\ExpenseCategories;

/**
 * Capability #25 - Schema-Free Natural-Language Data Entry.
 *
 * Turns ONE free sentence (typed or dictated) into a strict, editable preview
 * of a business record, so a seller never has to hunt for the right screen:
 *
 *   "Add 20 bottles of blue perfume to stock."      -> product
 *   "Record today's transport expense of 5,000 TSh." -> expense
 *   "I sold three units of product X for 15,000 each." -> sale
 *   "Add customer Amina, phone 0712345678."          -> customer
 *
 * Grounding rules implemented here (the whole contract is pure, unit-testable,
 * and NEVER touches the database):
 *
 *  - The intended record type is CLASSIFIED against a fixed allow-list. There is
 *    no such thing as a model-invented type: an unrecognised sentence produces
 *    `type: null` plus a localized explanation instead of a guess.
 *  - Numbers, dates, quantities and units come from the user's OWN words by
 *    trusted code (ExpenseParser's extractors are reused, not duplicated). The
 *    model may only pick one type from the allow-list and copy a phrase that the
 *    text already contains - sanitizeModel() drops anything else, and drops
 *    every number the model proposes because numbers must come from the text.
 *  - Product references are resolved by the caller against the caller's OWN
 *    business catalogue (through ProductSearch, capability #24); this class only
 *    decides what a RESULT means - matched, ambiguous or not found. An ambiguous
 *    reference is never silently narrowed to one product.
 *  - Ambiguity everywhere else (two quantities, several prices, an unresolvable
 *    date, an unknown customer) is REPORTED as a flag for a human to settle.
 *  - Nothing here writes. Committing is the controller's job, and it routes the
 *    confirmed preview through the EXISTING validated endpoints.
 */
class NlEntry
{
    public const CAPABILITY = 'entry';

    /* Record types. Exactly the allow-list - nothing else may ever be built. */
    public const TYPE_PRODUCT = 'product';
    public const TYPE_SALE = 'sale';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_CUSTOMER = 'customer';

    public const TYPES = [
        self::TYPE_PRODUCT,
        self::TYPE_SALE,
        self::TYPE_EXPENSE,
        self::TYPE_CUSTOMER,
    ];

    /* Flag codes - localized through flagMessage(). */
    public const FLAG_TYPE_UNCERTAIN = 'type_uncertain';
    public const FLAG_TYPE_UNSUPPORTED = 'type_unsupported';
    public const FLAG_NAME_MISSING = 'name_missing';
    public const FLAG_CATEGORY_MISSING = 'category_missing';
    public const FLAG_CATEGORY_UNCERTAIN = 'category_uncertain';
    public const FLAG_AMOUNT_MISSING = 'amount_missing';
    public const FLAG_AMOUNT_AMBIGUOUS = 'amount_ambiguous';
    public const FLAG_PRICE_MISSING = 'price_missing';
    public const FLAG_PRICE_ASSUMED_UNIT = 'price_assumed_unit';
    public const FLAG_SELLING_PRICE_MISSING = 'selling_price_missing';
    public const FLAG_STOCK_MISSING = 'stock_missing';
    public const FLAG_QUANTITY_MISSING = 'quantity_missing';
    public const FLAG_QUANTITY_AMBIGUOUS = 'quantity_ambiguous';
    public const FLAG_UNIT_UNCERTAIN = 'unit_uncertain';
    public const FLAG_DATE_AMBIGUOUS = 'date_ambiguous';
    public const FLAG_DATE_NOT_TODAY = 'date_not_today';
    public const FLAG_FUTURE_DATE = 'future_date';
    public const FLAG_PRODUCT_AMBIGUOUS = 'product_ambiguous';
    public const FLAG_PRODUCT_NOT_FOUND = 'product_not_found';
    public const FLAG_PRODUCT_NEW = 'product_new';
    public const FLAG_PRODUCT_EXISTS = 'product_exists';
    public const FLAG_CUSTOMER_NEW = 'customer_new';
    public const FLAG_CUSTOMER_AMBIGUOUS = 'customer_ambiguous';
    public const FLAG_CUSTOMER_MISSING = 'customer_missing';
    public const FLAG_PHONE_MISSING = 'phone_missing';
    public const FLAG_PRICE_ASSUMED_TOTAL = 'price_assumed_total';

    public const MAX_TEXT = 500;
    public const MAX_REFERENCE = 120;

    /** Confidence the classifier must reach before it commits to a type. */
    private const TYPE_MIN_SCORE = 2.0;
    /** ... and how far ahead of the runner-up it must be. */
    private const TYPE_MARGIN = 1.35;

    private const CURRENCY = 'TZS';
    private const MIN_DESCRIPTION = 3;
    private const MAX_DESCRIPTION = 200;

    /* ------------------------------------------------------------------ */
    /* Type classification                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Weighted intent keywords per record type, across all eight app locales
     * plus the loan words sellers actually mix in. ASCII entries match on word
     * boundaries; non-ASCII (Devanagari, Arabic, CJK) match as substrings,
     * because those scripts have no word spaces in the same sense.
     */
    private const TYPE_KEYWORDS = [
        // NOTE: no entry may be a strict superstring of another one in the same
        // list - "i sold" next to "sold" scored 6 for one verb and made a
        // sentence that mentioned BOTH a sale and an expense look decisive.
        self::TYPE_SALE => [
            'nimeuza' => 4, 'niluza' => 4, 'niliuza' => 4, 'sold' => 4,
            'sell' => 2, 'sale' => 2, 'sales' => 2, 'mauzo' => 2, 'uza' => 1.5,
            'vendí' => 4, 'vendido' => 4, 'vendu' => 4, 'vente' => 1.5,
            'verkauft' => 4, 'verkauf' => 2, 'बेचा' => 4, 'बेची' => 4, 'बिक्री' => 2, 'बेच' => 2,
            'فروخت' => 4, 'بیچا' => 4, 'بیچی' => 4, 'بیچے' => 4,
            '卖了' => 4, '卖出' => 4, '售出' => 4, '卖' => 3, '销售' => 2,
        ],
        self::TYPE_EXPENSE => [
            'expense' => 3, 'matumizi' => 3, 'nimetumia' => 3, 'nilitumia' => 3,
            'nililipa' => 3, 'nimelipa' => 3, 'spent' => 3, 'spend' => 2, 'paid' => 2,
            'dépense' => 3, 'dépensé' => 3, 'gasto' => 3, 'gasté' => 3,
            'ausgabe' => 3, 'ausgegeben' => 3, 'bezahlt' => 2,
            'खर्च' => 3, 'خرچ' => 3, '费用' => 3, '支出' => 3, '花了' => 2,
        ],
        // A customer NOUN is a weaker signal than a verb that says what was
        // done ("kwa mteja Amina" accompanies a sale; "add customer Amina" is
        // the record itself), so the noun weighs 2 and the command weighs 3.5.
        self::TYPE_CUSTOMER => [
            'add customer' => 3.5, 'new customer' => 3.5, 'ongeza mteja' => 3.5,
            'nouveau client' => 3.5, 'ajouter le client' => 3.5, 'nuevo cliente' => 3.5,
            'añadir cliente' => 3.5, 'añadir un cliente' => 3.5, 'neuer kunde' => 3.5,
            'kunde hinzufügen' => 3.5, 'नया ग्राहक' => 3.5, 'ग्राहक जोड़ें' => 3.5,
            'نیا گاہک' => 3.5, 'گاہک شامل' => 3.5, '新客户' => 3.5, '添加客户' => 3.5,
            'customer' => 2, 'mteja' => 2, 'wateja' => 2, 'client' => 2, 'clients' => 2, 'cliente' => 2,
            'kunde' => 2, 'ग्राहक' => 2, 'گاہک' => 2, '客户' => 2,
        ],
        self::TYPE_PRODUCT => [
            'add product' => 3, 'new product' => 3, 'product' => 1.5, 'catalogue' => 2,
            'nouveau produit' => 3.5, 'nuevo producto' => 3.5, 'neues produkt' => 3.5,
            'नया उत्पाद' => 3.5, 'نیا پروڈکٹ' => 3.5, '新产品' => 3.5, 'bidhaa mpya' => 3.5,
            'catalog' => 2, 'stock' => 2, 'stoo' => 2, 'bidhaa' => 3, 'ongeza bidhaa' => 3,
            'inventory' => 2, 'unit' => 0.5,
            'produit' => 2, 'produkt' => 2, 'producto' => 2, 'catalogo' => 2, 'स्टॉक' => 2, '库存' => 2,
            'bestand' => 2, 'bestände' => 2,
            'उत्पाद' => 2, 'माल' => 2, 'اسٹاک' => 2, 'پروڈکٹ' => 2, '产品' => 2, '库存' => 2,
        ],
    ];

    /**
     * Classify one sentence into the allow-list.
     *
     * @return array{type: ?string, confidence: float, scores: array<string, float>, keywords: array<string, array<int, string>>, decision: string}
     */
    public static function classify(string $text): array
    {
        $haystack = SearchText::normalize($text);
        $scores = [];
        $hits = [];

        foreach (self::TYPE_KEYWORDS as $type => $table) {
            $score = 0.0;
            $hits[$type] = [];
            foreach ($table as $phrase => $weight) {
                if (self::phraseIn($haystack, $phrase)) {
                    $score += $weight;
                    $hits[$type][] = $phrase;
                }
            }
            $scores[$type] = round($score, 3);
        }

        arsort($scores);
        $types = array_keys($scores);
        $top = $types[0] ?? null;
        $topScore = (float) ($scores[$top] ?? 0.0);
        $runnerUp = (float) ($scores[$types[1] ?? ''] ?? 0.0);

        if ($top === null || $topScore < self::TYPE_MIN_SCORE) {
            return [
                'type' => null,
                'confidence' => 0.0,
                'scores' => $scores,
                'keywords' => $hits,
                'decision' => 'no_confident_type',
            ];
        }

        if ($runnerUp > 0 && ($topScore / max($runnerUp, 0.001)) < self::TYPE_MARGIN) {
            return [
                'type' => null,
                'confidence' => round($runnerUp / max($topScore, 0.001), 3),
                'scores' => $scores,
                'keywords' => $hits,
                'decision' => 'ambiguous_type',
            ];
        }

        $confidence = $topScore >= 4.0 ? 0.9 : ($topScore >= 3.0 ? 0.8 : 0.65);

        return [
            'type' => $top,
            'confidence' => $confidence,
            'scores' => $scores,
            'keywords' => $hits,
            'decision' => 'matched_type',
        ];
    }

    /** ASCII phrases match on word boundaries; other scripts match as substrings. */
    private static function phraseIn(string $haystack, string $phrase): bool
    {
        $needle = SearchText::normalize($phrase);
        if ($needle === '') {
            return false;
        }

        // Word boundaries for ASCII, plus a compound/plural allowance: a LONG
        // keyword (>= 7 characters) may also match inside a word, because
        // German builds compounds ("Transportausgabe" contains "ausgabe") and
        // plurals are ordinary ("products", "expenses", "customers"). Shorter
        // keywords never get that latitude - "sale" must not fire inside
        // "wholesale", "paid" inside "unpaid", or "client" inside "cliente"
        // (which double-counted the customer signal and turned a sale into a
        // tie during a live example check).
        if (preg_match('/^[\x20-\x7e]+$/', $needle) === 1) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', $haystack) === 1) {
                return true;
            }

            return mb_strlen($needle) >= 7 && str_contains($haystack, $needle);
        }

        return str_contains($haystack, $needle);
    }

    /* ------------------------------------------------------------------ */
    /* Field extraction (numbers, units, references)                      */
    /* ------------------------------------------------------------------ */

    /** Counting words per locale (longest phrase first when matched). */
    private const NUMBER_WORDS = [
        'en' => ['twenty' => 20, 'nineteen' => 19, 'eighteen' => 18, 'seventeen' => 17, 'sixteen' => 16, 'fifteen' => 15, 'fourteen' => 14, 'thirteen' => 13, 'twelve' => 12, 'eleven' => 11, 'ten' => 10, 'nine' => 9, 'eight' => 8, 'seven' => 7, 'six' => 6, 'five' => 5, 'four' => 4, 'three' => 3, 'two' => 2, 'one' => 1, 'a dozen' => 12, 'dozen' => 12],
        'sw' => ['kumi na tisa' => 19, 'kumi na nane' => 18, 'kumi na saba' => 17, 'kumi na sita' => 16, 'kumi na tano' => 15, 'kumi na nne' => 14, 'kumi na tatu' => 13, 'kumi na mbili' => 12, 'kumi na moja' => 11, 'thelathini' => 30, 'ishirini' => 20, 'kumi' => 10, 'tisa' => 9, 'nane' => 8, 'saba' => 7, 'sita' => 6, 'tano' => 5, 'nne' => 4, 'tatu' => 3, 'mbili' => 2, 'moja' => 1, 'dazani' => 12],
        'fr' => ['vingt' => 20, 'dix-neuf' => 19, 'dix-huit' => 18, 'dix-sept' => 17, 'seize' => 16, 'quinze' => 15, 'quatorze' => 14, 'treize' => 13, 'douze' => 12, 'onze' => 11, 'dix' => 10, 'neuf' => 9, 'huit' => 8, 'sept' => 7, 'six' => 6, 'cinq' => 5, 'quatre' => 4, 'trois' => 3, 'deux' => 2, 'un' => 1, 'une' => 1, 'douzaine' => 12],
        'es' => ['veinte' => 20, 'diecinueve' => 19, 'dieciocho' => 18, 'diecisiete' => 17, 'dieciséis' => 16, 'quince' => 15, 'catorce' => 14, 'trece' => 13, 'doce' => 12, 'once' => 11, 'diez' => 10, 'nueve' => 9, 'ocho' => 8, 'siete' => 7, 'seis' => 6, 'cinco' => 5, 'cuatro' => 4, 'tres' => 3, 'dos' => 2, 'uno' => 1, 'una' => 1, 'docena' => 12],
        'de' => ['zwanzig' => 20, 'neunzehn' => 19, 'achtzehn' => 18, 'siebzehn' => 17, 'sechzehn' => 16, 'fünfzehn' => 15, 'vierzehn' => 14, 'dreizehn' => 13, 'zwölf' => 12, 'elf' => 11, 'zehn' => 10, 'neun' => 9, 'acht' => 8, 'sieben' => 7, 'sechs' => 6, 'fünf' => 5, 'vier' => 4, 'drei' => 3, 'zwei' => 2, 'eins' => 1, 'ein' => 1, 'eine' => 1, 'dutzend' => 12],
        'hi' => ['बीस' => 20, 'पंद्रह' => 15, 'दस' => 10, 'नौ' => 9, 'आठ' => 8, 'सात' => 7, 'छह' => 6, 'पाँच' => 5, 'पांच' => 5, 'चार' => 4, 'तीन' => 3, 'दो' => 2, 'एक' => 1, 'दर्जन' => 12],
        'ur' => ['بیس' => 20, 'پندرہ' => 15, 'دس' => 10, 'نو' => 9, 'آٹھ' => 8, 'سات' => 7, 'چھ' => 6, 'پانچ' => 5, 'چار' => 4, 'تین' => 3, 'دو' => 2, 'ایک' => 1, 'درجن' => 12],
        'zh' => ['二十' => 20, '十五' => 15, '十二' => 12, '十个' => 10, '十' => 10, '九' => 9, '八' => 8, '七' => 7, '六' => 6, '五' => 5, '四' => 4, '三' => 3, '两' => 2, '二' => 2, '一' => 1, '打' => 12],
    ];

    /**
     * Canonical unit <- aliases across the eight locales. A unit is only ever
     * COPIED from the user's words; an unknown one stays null and is flagged.
     */
    private const UNITS = [
        'bottle' => ['bottles', 'bottle', 'chupa', 'bouteille', 'bouteilles', 'botella', 'botellas', 'flasche', 'flaschen', 'बोतल', 'بوتل', '瓶'],
        'piece' => ['pieces', 'piece', 'units', 'unit', 'vipande', 'kipande', 'vitu', 'kitu', 'pièce', 'pièces', 'unité', 'unité', 'pieza', 'piezas', 'stück', 'टुकड़ा', 'عدد', '个', '件'],
        'kg' => ['kg', 'kgs', 'kilo', 'kilos', 'kilo', 'किलो', 'کلو', '公斤'],
        'litre' => ['litre', 'litres', 'liter', 'liters', 'lita', 'litro', 'litros', 'लीटर', 'لیٹر', '升'],
        'box' => ['box', 'boxes', 'sanduku', 'masanduku', 'caja', 'cajas', 'karton', 'kartons', 'डिब्बा', 'ڈبہ', '箱'],
        'packet' => ['packet', 'packets', 'pack', 'packs', 'pakiti', 'paquet', 'paquets', 'paquete', 'paquetes', 'paket', 'पैकेट', 'پیکٹ', '包'],
        'pair' => ['pair', 'pairs', 'jozi', 'paire', 'paires', 'par', 'पेयर', 'جوڑا', '双'],
        'dozen' => ['dozen', 'dazani', 'douzaine', 'docena', 'dutzend', 'दर्जन', 'درجن', '打'],
        'bag' => ['bag', 'bags', 'gunia', 'mfuko', 'mifuko', 'sac', 'sacs', 'bolsa', 'bolsas', 'sack', 'बोरी', 'بوری', '袋'],
        'carton' => ['carton', 'cartons', 'katoni', 'cartón', 'kiste', 'कार्टन', 'کارٹن', '纸箱'],
        'roll' => ['roll', 'rolls', 'roli', 'rouleau', 'rouleaux', 'rollo', 'रोल', 'رول', '卷'],
        'sheet' => ['sheet', 'sheets', 'karatasi', 'feuille', 'feuilles', 'hoja', 'hojas', 'blatt', 'शीट', 'شیٹ', '张'],
        'metre' => ['metre', 'metres', 'meter', 'meters', 'mita', 'mètre', 'mètres', 'metro', 'metros', 'मीटर', 'میٹر', '米'],
    ];

    /** Selling price is written with these words around it, e.g. "for 15,000 each". */
    private const PER_UNIT_MARKERS = ['each', 'per', 'kila', 'cha moja', 'unité', 'chacune', 'cada', 'je', 'प्रति', 'فی', '每'];

    /** How close a per-unit word has to be to an amount to claim it. */
    private const PER_UNIT_MAX_DISTANCE = 12;

    /** Sale/stock phrases that mean "the count is stock", not a sale. */
    private const STOCK_MARKERS = ['to stock', 'into stock', 'kwenye stock', 'kwenye stoo', 'stock', 'stoo', 'inventory', '库存', 'اسٹاک', 'स्टॉक'];

    /**
     * Markers that introduce a PRICE rather than a thing ("for 2,000"). A
     * digits-only reference after one of these is a price, never a product.
     */
    private const PRICE_MARKERS = ['for', 'kwa'];

    /** Markers that introduce a product reference. */
    private const REFERENCE_MARKERS = ['of', 'ya', 'za', 'wa', 'vya', 'cha', 'la', 'for', 'kwa', 'named', 'called', 'name', 'de', 'del', 'von', ' का ', ' کی ', '的'];

    /** Markers that introduce a customer reference. */
    private const CUSTOMER_MARKERS = ['customer', 'mteja', 'client', 'cliente', 'kunde', 'ग्राहक', 'گاہک', '客户'];

    /**
     * Quantities the sentence states: digits that are NOT money, plus counting
     * words in the caller's language.
     *
     * @return array{quantity: ?int, unit: ?string, candidates: array<int, int>, ambiguous: bool, unit_uncertain: bool}
     */
    /**
     * A count is read in this order of trust:
     *   1. a number DIRECTLY followed by a unit ("20 bottles", "3 vipande") -
     *      the strongest signal there is, in any of the eight languages;
     *   2. a counting word ("three", "tatu");
     *   3. a bare number that is not money.
     * Money is never allowed to become a quantity, and a quantity is never
     * allowed to become money - that distinction was the first real defect a
     * live probe found ("Add 20 bottles" reported a price of 20).
     *
     * @return array{quantity: ?int, unit: ?string, candidates: array<int, int>, ambiguous: bool, unit_uncertain: bool}
     */
    public static function extractQuantity(string $text, string $locale): array
    {
        $masked = self::maskDates($text);

        [$adjacent, $adjacentUnit] = self::quantityNextToUnit($masked);
        $unit = $adjacentUnit ?? self::extractUnit($text, $locale);

        if ($adjacent !== []) {
            return [
                'quantity' => $adjacent[0],
                'unit' => $unit,
                'candidates' => array_values(array_unique($adjacent)),
                'ambiguous' => count(array_unique($adjacent)) > 1,
                'unit_uncertain' => $unit === null,
            ];
        }

        $word = self::firstNumberWord($text, $locale);
        if ($word !== null) {
            return [
                'quantity' => $word,
                'unit' => $unit,
                'candidates' => [$word],
                'ambiguous' => false,
                'unit_uncertain' => $unit === null,
            ];
        }

        $claim = [];
        foreach (self::moneyMatches($masked) as $match) {
            $claim[$match['offset']] = (int) round((float) $match['value']);
        }

        $candidates = [];
        $claimedValues = [];
        if (preg_match_all('/\b(\d{1,6})\b/u', $masked, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[1] as [$raw, $byteOffset]) {
                $value = (int) $raw;
                if ($value <= 0) {
                    continue;
                }
                $charOffset = mb_strlen(substr($masked, 0, $byteOffset), 'UTF-8');
                if (isset($claim[$charOffset])) {
                    $claimedValues[] = $value;
                    continue; // this number reads as money
                }
                $candidates[] = $value;
            }
        }

        // A bare number with no unit could be either. When the sentence is
        // explicitly about STOCK ("add 7 of the blue perfume to stock") the
        // count reading is the right one; otherwise money wins, because
        // "expense of 5,000" must never become five thousand units.
        if ($candidates === [] && $claimedValues !== [] && self::mentionsStock($text)) {
            $candidates = $claimedValues;
        }

        return [
            'quantity' => $candidates[0] ?? null,
            'unit' => $unit,
            'candidates' => array_values(array_unique($candidates)),
            'ambiguous' => count(array_unique($candidates)) > 1,
            'unit_uncertain' => $unit === null,
        ];
    }

    /** Mask dates so their digits are never read as counts or money. */
    private static function maskDates(string $text): string
    {
        $masked = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', ' ', $text) ?? $text;

        return preg_replace('/\b\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}\b/', ' ', $masked) ?? $masked;
    }

    /**
     * Numbers written immediately before a unit word, plus that unit.
     *
     * @return array{0: array<int, int>, 1: ?string}
     */
    private static function quantityNextToUnit(string $text): array
    {
        foreach (self::UNITS as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $needle = SearchText::normalize($alias);
                if ($needle === '') {
                    continue;
                }
                foreach (self::unitNumberPatterns($needle) as $pattern) {
                    if (preg_match_all($pattern, $text, $m) > 0) {
                        $values = [];
                        foreach ($m[1] as $raw) {
                            $value = (int) $raw;
                            if ($value > 0) {
                                $values[] = $value;
                            }
                        }
                        if ($values !== []) {
                            return [$values, $canonical];
                        }
                    }
                }
            }
        }

        return [[], null];
    }

    /**
     * "20 bottles" AND "chupa 20": Swahili and several other languages put the
     * count AFTER the noun, and missing that made "chupa 20" read as a price of
     * 20 instead of 20 bottles (found by the live probe).
     *
     * @return array<int, string>
     */
    private static function unitNumberPatterns(string $needle): array
    {
        $quoted = preg_quote($needle, '/');
        $ascii = preg_match('/^[\x20-\x7e]+$/', $needle) === 1;
        $unit = $ascii
            ? '(?<![\p{L}\p{N}])'.$quoted.'(?![\p{L}\p{N}])'
            : $quoted;

        return [
            '/(\d{1,6})\s*'.$unit.'/u',
            '/'.$unit.'\s*(\d{1,6})(?![\p{L}\p{N}])/u',
        ];
    }

    /**
     * Every money-looking number with its character offset, EXCLUDING numbers
     * that are a quantity (a number glued to a unit word). Currency words and
     * thousand separators are recognized; "2,000" is 2000, not 2 and 000.
     *
     * @return array<int, array{raw: string, value: float, offset: int}>
     */
    public static function moneyMatches(string $text): array
    {
        $masked = self::maskDates($text);
        $pattern = '/(?<![\p{L}\p{N}])(?:TZS|TSh|Tsh|tsh|TSH|shs|shillings?|\/-)?\s*(\d{1,3}(?:[ ,]\d{3})+(?:[.,]\d{1,2})?|\d+(?:[.,]\d{1,2})?)(?:\s*(?:TZS|TSh|Tsh|tsh|TSH|shs|shillings?|\/-))?(?![\p{L}\p{N}])/u';

        $out = [];
        if (preg_match_all($pattern, $masked, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return $out;
        }

        $unitsAdjacent = self::numbersNextToUnits($masked);

        foreach ($matches[1] as [$raw, $byteOffset]) {
            $charOffset = mb_strlen(substr($masked, 0, $byteOffset), 'UTF-8');
            if (in_array($charOffset, $unitsAdjacent, true)) {
                continue; // a count, not money
            }
            $value = ExpenseParser::normalizeAmount($raw);
            if ($value === null || $value <= 0) {
                continue;
            }
            $out[] = ['raw' => $raw, 'value' => $value, 'offset' => $charOffset];
        }

        return $out;
    }

    /** Character offsets of numbers that sit directly in front of a unit word. */
    private static function numbersNextToUnits(string $text): array
    {
        $offsets = [];
        foreach (self::UNITS as $aliases) {
            foreach ($aliases as $alias) {
                $needle = SearchText::normalize($alias);
                if ($needle === '') {
                    continue;
                }
                foreach (self::unitNumberPatterns($needle) as $pattern) {
                    if (preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE) > 0) {
                        foreach ($m[1] as [, $byteOffset]) {
                            $offsets[] = mb_strlen(substr($text, 0, $byteOffset), 'UTF-8');
                        }
                    }
                }
            }
        }

        return array_values(array_unique($offsets));
    }

    /** First counting word present in the text (locale first, then English). */
    private static function firstNumberWord(string $text, string $locale): ?int
    {
        foreach ([$locale, 'en'] as $lang) {
            $words = self::NUMBER_WORDS[$lang] ?? [];
            foreach ($words as $word => $value) {
                if (self::phraseIn(SearchText::normalize($text), (string) $word)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /** The measurement word the sentence uses, mapped to a canonical unit. */
    public static function extractUnit(string $text, string $locale): ?string
    {
        $haystack = ' '.SearchText::normalize($text).' ';

        foreach (self::UNITS as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $needle = SearchText::normalize($alias);
                if ($needle === '') {
                    continue;
                }
                $isAscii = preg_match('/^[\x20-\x7e]+$/', $needle) === 1;
                $found = $isAscii
                    ? preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', $haystack) === 1
                    : str_contains($haystack, $needle);
                if ($found) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    /**
     * The product reference inside a sentence, e.g. "20 bottles of blue
     * perfume to stock" -> "blue perfume". Markers ("of", "ya") win; otherwise
     * the noun phrase left after removing counts, units and intent words is used.
     * Never returns a guess: an empty result means "the user did not name one".
     */
    public static function extractProductReference(string $text, bool $allowNumeric = false): ?string
    {
        $raw = trim($text);
        if ($raw === '') {
            return null;
        }

        // 1. An explicit quoted name always wins, digits and all - the seller
        //    wrote it out, so nothing in it is a guess.
        if (preg_match('/["\'“”«»]([^"\'“”«»]{2,80})["\'“”«»]/u', $raw, $m) === 1) {
            return self::cleanReference($m[1], true);
        }

        $normalized = SearchText::normalize($raw);

        // 2. Marker-introduced phrase. The EARLIEST marker wins (not the first
        //    one listed), so "chupa 20 za manukato ya bluu" yields the whole
        //    "manukato ya bluu" rather than the fragment after the last "ya".
        $best = null;
        foreach (self::REFERENCE_MARKERS as $marker) {
            $pos = self::markerPosition($normalized, $marker);
            if ($pos === null) {
                continue;
            }
            if ($best === null || $pos < $best['pos']) {
                $best = ['pos' => $pos, 'marker' => $marker];
            }
        }
        if ($best !== null) {
            $withDigits = $allowNumeric && ! in_array($best['marker'], self::PRICE_MARKERS, true);
            $tail = mb_substr($normalized, $best['pos'] + mb_strlen(SearchText::normalize($best['marker'])));
            $candidate = self::trimTail($tail, $withDigits);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        // 3. Fall back to the sentence with the scaffolding removed.
        $stripped = $normalized;
        foreach (self::STOCK_MARKERS as $marker) {
            $stripped = str_replace(SearchText::normalize($marker), ' ', $stripped);
        }
        $stripped = preg_replace('/\b\d{1,6}\b/u', ' ', $stripped) ?? $stripped;
        $stripped = preg_replace('/\b(nimeuza|niliuza|niluza|sold|sell|add|ongeza|record|rekodi|nimeongeza|expense|matumizi|sale|mauzo|product|bidhaa|new|mpya|create|weka|of|ya|za|wa|vya|cha|la|for|kwa|to|kwenye|the|a|an|my|yangu|units?|pieces?|bottles?|each|kila|per|buying|buy|price|selling|purchase|kununua|kuuza|bei|kwa|tsh|tzs)\b/u', ' ', $stripped) ?? $stripped;

        // Counting words are scaffolding too, and a one-letter leftover never
        // is the product name.
        // \p{M} belongs in every word-character class: a Devanagari matra is a
        // combining MARK, so treating it as a boundary split "नीली" into single
        // letters and deleted them.
        foreach (array_merge(self::NUMBER_WORDS['en'], self::NUMBER_WORDS['sw'], self::NUMBER_WORDS['fr'], self::NUMBER_WORDS['es'], self::NUMBER_WORDS['de']) as $word => $value) {
            $stripped = preg_replace('/(?<![\p{L}\p{M}\p{N}])'.preg_quote((string) $word, '/').'(?![\p{L}\p{M}\p{N}])/u', ' ', $stripped) ?? $stripped;
        }
        $stripped = preg_replace('/(?<![\p{L}\p{M}\p{N}])\p{L}(?![\p{L}\p{M}\p{N}])/u', ' ', $stripped) ?? $stripped;

        // Units are scaffolding too: "sukari kilo 5" is "sukari".
        foreach (self::UNITS as $aliases) {
            foreach ($aliases as $alias) {
                $needle = SearchText::normalize($alias);
                if ($needle !== '' && preg_match('/^[\x20-\x7e]+$/', $needle) === 1) {
                    $stripped = preg_replace('/\b'.preg_quote($needle, '/').'\b/u', ' ', $stripped) ?? $stripped;
                }
            }
        }

        return self::cleanReference($stripped, $allowNumeric);
    }

    /** Cut a tail phrase at the first "sentence furniture" word. */
    private static function trimTail(string $tail, bool $allowNumeric = false): ?string
    {
        $cut = preg_replace(
            '/\b(to stock|into stock|kwenye stock|kwenye stoo|in stock|au stock|al inventario|zum bestand|for|kwa|at|each|kila|per|today|leo|yesterday|jana|with|na|to|the|a|an|my|yangu)\b.*$/u',
            ' ',
            ' '.$tail
        ) ?? $tail;

        return self::cleanReference($cut, $allowNumeric);
    }

    /**
     * Tidy a candidate reference: drop CJK scaffolding, punctuation and (for
     * names) digits; collapse spaces and cap the length. A reference must
     * contain at least one LETTER - a pure number is never a product or a
     * customer name, and returning one would be a guess.
     */
    private static function cleanReference(string $value, bool $allowDigits = true): ?string
    {
        if (preg_match('/[\x{4E00}-\x{9FFF}\x{0900}-\x{097F}\x{0600}-\x{06FF}]/u', $value) === 1) {
            $value = self::stripScriptScaffolding($value);
        }

        // \p{M} must survive: a Devanagari vowel sign is a MARK, not a letter,
        // and stripping it shredded the Hindi reference into single letters.
        $value = preg_replace('/[^\p{L}\p{M}\p{N}\s\'\-]+/u', ' ', $value) ?? $value;
        if (! $allowDigits) {
            $value = preg_replace('/\p{N}+/u', ' ', $value) ?? $value;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $value = mb_substr($value, 0, self::MAX_REFERENCE);
        if (mb_strlen($value) < 2) {
            return null;
        }
        // A reference normally has to contain a letter - but a NEW product may
        // genuinely be named with digits alone (this catalogue really contains
        // products called "6203"), so the product type passes $allowDigits.
        if (! $allowDigits && preg_match('/\p{L}/u', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * CJK, Devanagari and Arabic-script scaffolding. These are the command and
     * container words the examples use; removing them leaves the thing itself
     * ("把20瓶蓝色香水加入库存" -> "蓝色香水", "स्टॉक में 20 बोतल नीली परफ्यूम जोड़ें"
     * -> "नीली परफ्यूम"). Word-boundary cleanup cannot do this in scripts that
     * do not separate words with spaces.
     */
    private static function stripScriptScaffolding(string $value): string
    {
        $words = [
            // Chinese
            '请', '帮我', '把', '加入', '加到', '添加', '新增', '记录', '库存', '新的', '一个', '客户',
            // Hindi
            'में', 'जोड़ें', 'जोड़ो', 'जोड़', 'स्टॉक', 'नया', 'नई', 'एक', 'का', 'की', 'के', 'और',
            // Urdu
            'میں', 'شامل', 'کریں', 'اسٹاک', 'نیا', 'ایک', 'اور',
        ];
        $units = ['瓶', '箱', '包', '个', '件', '双', '袋', '卷', '张', '公斤', '升', '米', '打', 'बोतल', 'टुकड़ा', 'किलो', 'बोरी', 'بوتل', 'عدد', 'کلو', 'بوری'];

        foreach (array_merge($words, $units) as $word) {
            $value = str_replace($word, ' ', $value);
        }

        return $value;
    }

    /**
     * The customer the sentence refers to ("for Amina", "mteja Juma", "customer
     * Amina"). Returns the name only - resolving it to an existing row is the
     * caller's job, and an unknown name stays a proposal, never a silent write.
     */
    public static function extractCustomerReference(string $text): ?string
    {
        $normalized = SearchText::normalize($text);

        foreach (self::CUSTOMER_MARKERS as $marker) {
            $pos = self::markerPosition($normalized, $marker);
            if ($pos === null) {
                continue;
            }
            $tail = mb_substr($normalized, $pos + mb_strlen(SearchText::normalize($marker)));
            $tail = preg_replace('/\b(named|called|name|jina|with|na|phone|simu|number|namba|wa|ya|of|for|kwa|add|ongeza)\b/u', ' ', ' '.$tail) ?? $tail;
            $candidate = self::nameCandidate($tail);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        // "for <Name>" / "kwa <Name>" - but only when a NAME follows: "kwa
        // 2000" is a price, and reporting "2000 kila kimoja" as a customer name
        // was a real defect.
        foreach (['for', 'kwa'] as $marker) {
            $pos = self::markerPosition($normalized, $marker);
            if ($pos === null) {
                continue;
            }
            $tail = mb_substr($normalized, $pos + mb_strlen($marker));
            $tail = preg_replace('/\b(mteja|customer|client|the|a|an)\b/u', ' ', ' '.$tail) ?? $tail;
            $candidate = self::nameCandidate($tail);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * A customer name: no digits, must start with a letter, at most three words
     * (a name is short - "amina 0712345678" is a name plus a phone number).
     */
    private static function nameCandidate(string $tail): ?string
    {
        $trimmed = ltrim($tail);
        if ($trimmed === '' || preg_match('/^\p{N}/u', $trimmed) === 1) {
            return null;
        }

        $candidate = self::cleanReference($tail, false);
        if ($candidate === null) {
            return null;
        }

        $words = explode(' ', $candidate);
        if (count($words) > 3) {
            $candidate = implode(' ', array_slice($words, 0, 3));
        }

        return $candidate;
    }

    /**
     * Position of a marker phrase inside a normalized string. ASCII markers are
     * matched on WORD boundaries (otherwise "wa" would fire inside "mauzo" and
     * "au" inside half the Swahili vocabulary); other scripts match directly.
     */
    private static function markerPosition(string $haystack, string $marker): ?int
    {
        $needle = SearchText::normalize($marker);
        if ($needle === '') {
            return null;
        }

        if (preg_match('/^[\x20-\x7e]+$/', $needle) !== 1) {
            $pos = mb_strpos($haystack, $needle);

            return $pos === false ? null : $pos;
        }

        if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', $haystack, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        // preg offsets are bytes; mb_* works in characters.
        return mb_strlen(substr($haystack, 0, $m[0][1]), 'UTF-8');
    }

    /**
     * Normalized text with thousand separators collapsed, so a price written
     * "15,000" (normalizes to "15 000") is still findable as "15000" and the
     * marker/amount proximity test works in ONE coordinate space.
     */
    private static function digitsCollapsed(string $text): string
    {
        $normalized = SearchText::normalize($text);

        return (string) preg_replace('/(?<=\d)[\s.](?=\d{3}(?!\d))/u', '', $normalized);
    }

    /**
     * A selling price is only ever read from words that SAY so ("selling",
     * "kuuza", "bei ya kuuzia", "售价", "verkauf"). It is never inferred from a
     * second amount, because guessing which amount is the selling price is
     * exactly the kind of guess this capability must not make.
     */
    public static function extractSellingPrice(string $text): ?float
    {
        $normalized = SearchText::normalize($text);
        $markers = ['selling', 'sell price', 'kuuza', 'kuuzia', 'bei ya kuuza', 'precio de venta', 'prix de vente', 'verkaufspreis', 'verkauf', '售价', 'بيع', 'बिक्री मूल्य'];

        foreach (self::moneyMatches($text) as $match) {
            $offset = $match['offset'];
            // Look 30 characters BEFORE the amount and only a few after it: a
            // forward window swallowed the NEXT amount's marker and reported
            // the buying price as the selling price.
            $window = mb_substr($normalized, max(0, $offset - 30), min(30, max(0, $offset)));
            foreach ($markers as $marker) {
                if (self::phraseIn($window, $marker)) {
                    return (float) $match['value'];
                }
            }
        }

        return null;
    }

    /**
     * A tidied expense description: the sentence without its command words, the
     * money and the trailing date words. Everything left is the user's own
     * wording - nothing is invented.
     */    public static function tidyDescription(string $text): string
    {
        // Work on the digits-collapsed text so "5,000" (normalized to "5 000")
        // can be found and removed as one number.
        $value = ' '.self::digitsCollapsed($text).' ';

        $value = preg_replace(
            '/\b(record|rekodi|add|ongeza|register|enregistrer|registrar|erfassen|nimetumia|nilitumia|i spent|spent|paid|nililipa|nimelipa|today|leo|yesterday|jana|tomorrow|kesho|hoy|ayer|heute|gestern|aujourd|आज|آج|今天|昨天|明天)\b/u',
            ' ',
            $value
        ) ?? $value;

        foreach (self::moneyMatches($text) as $match) {
            $plain = rtrim(rtrim(number_format((float) $match['value'], 2, '.', ''), '0'), '.');
            $value = str_replace($plain, ' ', $value);
        }

        $value = preg_replace('/\b(tzs|tsh|shs|shillings?|currency|\/-)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/\b(of|ya|za|wa|for|kwa|-|:)\b/u', ' ', $value) ?? $value;
        // Drop one-letter leftovers such as the "s" of "today's".
        $value = preg_replace('/(?<![\p{L}\p{M}\p{N}])\p{L}(?![\p{L}\p{M}\p{N}])/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if (mb_strlen($value) < self::MIN_DESCRIPTION) {
            // The tidying left too little: the cleaned original is more honest
            // than a fragment.
            $fallback = ExpenseParser::cleanDescription($text);

            return mb_substr($fallback, 0, self::MAX_DESCRIPTION);
        }

        return mb_substr($value, 0, self::MAX_DESCRIPTION);
    }

    /** A phone number stated in the text, if any (digits only, 9-15 long). */
    public static function extractPhone(string $text): ?string
    {
        if (preg_match('/(\+?\d[\d\s\-]{7,17}\d)/u', $text, $m) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $m[1]) ?? '';
        if (strlen($digits) < 9 || strlen($digits) > 15) {
            return null;
        }
        // A bare 5-digit run is a price, not a phone number.
        if (strlen($digits) === 9 && preg_match('/^\d{5}$/', ltrim($digits, '0')) === 1) {
            return null;
        }

        return $digits;
    }

    /** An email address stated in the text, if any. */
    public static function extractEmail(string $text): ?string
    {
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]{2,}/u', $text, $m) !== 1) {
            return null;
        }

        return mb_substr($m[0], 0, 120);
    }

    /* ------------------------------------------------------------------ */
    /* Match decisions (pure over rows the caller already scored)          */
    /* ------------------------------------------------------------------ */

    /**
     * Decide what a RANKED candidate list means. This is where "never guess a
     * product identifier and never silently choose an ambiguous match" lives:
     * a near-tie is reported as ambiguous and carries NO product id.
     *
     * @param  array<int, array<string, mixed>>  $ranked  rows from ProductSearch::rank()
     * @return array{status: string, product_id: ?string, product_name: ?string, confidence: float, candidates: array<int, array<string, mixed>>}
     */
    public static function chooseMatch(array $ranked, float $margin = 0.85): array
    {
        $rows = [];
        foreach ($ranked as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $rows[] = [
                'product_id' => $id,
                'name' => (string) ($row['name'] ?? ''),
                'category' => $row['category'] ?? null,
                'selling_price' => isset($row['selling_price']) ? (float) $row['selling_price'] : null,
                'stock' => isset($row['stock']) ? (int) $row['stock'] : null,
                'available' => (bool) ($row['available'] ?? false),
                'relevance' => round((float) ($row['relevance'] ?? 0.0), 4),
                'matched_on' => $row['matched_on'] ?? null,
            ];
        }

        if ($rows === []) {
            return [
                'status' => 'not_found',
                'product_id' => null,
                'product_name' => null,
                'confidence' => 0.0,
                'candidates' => [],
            ];
        }

        $best = $rows[0];
        $runnerUp = $rows[1] ?? null;

        if ($runnerUp !== null && $best['relevance'] > 0 && $runnerUp['relevance'] >= ($best['relevance'] * $margin)) {
            return [
                'status' => 'ambiguous',
                'product_id' => null,
                'product_name' => null,
                'confidence' => round($runnerUp['relevance'] / max($best['relevance'], 0.001), 3),
                'candidates' => array_slice($rows, 0, 5),
            ];
        }

        return [
            'status' => 'matched',
            'product_id' => $best['product_id'],
            'product_name' => $best['name'],
            'confidence' => $best['relevance'],
            'candidates' => array_slice($rows, 0, 3),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Prices, dates, required fields                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Sale prices stated in the text. A per-unit marker ("each", "kila") marks
     * the amount as the price of ONE unit; a single amount with no marker is
     * assumed per-unit but flagged, so the seller confirms it.
     *
     * @return array{unit_price: ?float, total: ?float, amounts: array<int, float>, assumed: bool, ambiguous: bool}
     */
    public static function extractPrices(string $text): array
    {
        $amounts = [];
        foreach (self::moneyMatches($text) as $match) {
            $amounts[] = (float) $match['value'];
        }

        $flat = self::digitsCollapsed($text);

        // The CLOSEST amount to a per-unit word wins. Taking the first one that
        // was merely "near" a marker made a product name that happens to be a
        // number ("6204") beat the real price: "I sold three units of 6204 for
        // 2,000 each" reported 6204 as the unit price.
        $unit = null;
        $assumed = false;
        $closest = null;
        foreach ($amounts as $amount) {
            $amountPos = self::digitPosition($flat, $amount);
            if ($amountPos === null) {
                continue;
            }
            foreach (self::PER_UNIT_MARKERS as $marker) {
                $markerPos = self::markerPosition($flat, $marker);
                if ($markerPos === null) {
                    continue;
                }
                $distance = abs($markerPos - $amountPos);
                if ($distance <= self::PER_UNIT_MAX_DISTANCE && ($closest === null || $distance < $closest['distance'])) {
                    $closest = ['amount' => $amount, 'distance' => $distance];
                }
            }
        }
        if ($closest !== null) {
            $unit = $closest['amount'];
        }

        if ($unit === null && count($amounts) === 1) {
            $unit = $amounts[0];
            $assumed = true;
        }

        $total = null;
        if (count($amounts) > 1) {
            foreach (array_reverse($amounts) as $amount) {
                if ($unit === null || $amount !== $unit) {
                    $total = $amount;
                    break;
                }
            }
        }

        return [
            'unit_price' => $unit,
            'total' => $total,
            'amounts' => $amounts,
            'assumed' => $assumed,
            'ambiguous' => count($amounts) > 1 && ! self::hasPerUnitMarker($flat),
        ];
    }

    /** Character position of an amount's digits in the collapsed text. */
    private static function digitPosition(string $flat, float $amount): ?int
    {
        $plain = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
        $pos = mb_strpos($flat, $plain);

        return $pos === false ? null : $pos;
    }

    private static function hasPerUnitMarker(string $flat): bool
    {
        foreach (self::PER_UNIT_MARKERS as $marker) {
            if (self::markerPosition($flat, $marker) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run the trusted extractors for one record type.
     *
     * @return array<string, mixed> a draft preview (no database lookups)
     */
    public static function draft(string $type, string $text, string $locale, string $today): array
    {
        $quantity = self::extractQuantity($text, $locale);
        $money = [];
        foreach (self::moneyMatches($text) as $match) {
            $money[] = (float) $match['value'];
        }
        $date = ExpenseParser::extractDate($text, $today, $locale);
        // A NEW product may legitimately be named with digits alone (the live
        // catalogue really contains products called "6203"); a product being
        // SOLD is matched against existing rows, where a bare number is far
        // more likely to be a price, so digits-only references are refused
        // there and for customer names.
        $reference = self::extractProductReference(
            $text,
            $type === self::TYPE_PRODUCT || $type === self::TYPE_SALE
        );

        // Whatever the reference turned out to be, its own digits are the
        // product's name - not a price. Without this, "Add 20 bottles of 6203"
        // reported a buying price of 6203.
        if ($reference !== null) {
            $referenceDigits = SearchText::normalize($reference);
            $money = array_values(array_filter($money, static function (float $amount) use ($referenceDigits): bool {
                $plain = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

                return ! str_contains($referenceDigits, $plain);
            }));
        }

        $ambiguousMoney = count($money) > 1;

        switch ($type) {
            case self::TYPE_PRODUCT:
                $stock = self::mentionsStock($text) ? $quantity['quantity'] : null;
                $selling = self::extractSellingPrice($text);

                return [
                    'name' => $reference,
                    'category' => null,
                    'unit' => $quantity['unit'],
                    'stock' => $stock,
                    'price' => $money[0] ?? null,
                    'expected_selling_price' => $selling,
                    'description' => null,
                    'quantity' => $quantity,
                    'date' => $date,
                    'amount_ambiguous' => $ambiguousMoney,
                ];

            case self::TYPE_SALE:
                $prices = self::extractPrices($text);
                $lineTotal = ($quantity['quantity'] !== null && $prices['unit_price'] !== null)
                    ? round($quantity['quantity'] * $prices['unit_price'], 2)
                    : null;
                if ($prices['assumed'] && $prices['unit_price'] !== null) {
                    $lineTotal = ($quantity['quantity'] !== null)
                        ? round($quantity['quantity'] * $prices['unit_price'], 2)
                        : null;
                }

                return [
                    'items' => [[
                        'reference' => $reference,
                        'product_id' => null,
                        'product_name' => null,
                        'quantity' => $quantity['quantity'],
                        'unit' => $quantity['unit'],
                        'unit_price' => $prices['unit_price'],
                        'line_total' => $lineTotal,
                        'match' => null,
                    ]],
                    'customer_name' => self::extractCustomerReference($text),
                    'customer_id' => null,
                    'customer_status' => null,
                    'sale_date' => $date['date'],
                    'payment_method' => 'cash',
                    'total_amount' => $lineTotal,
                    'quantity' => $quantity,
                    'prices' => $prices,
                    'date' => $date,
                ];

            case self::TYPE_EXPENSE:
                $category = ExpenseParser::matchCategory($text);

                return [
                    'description' => self::tidyDescription($text),
                    'amount' => $money[0] ?? null,
                    'currency' => self::CURRENCY,
                    'expense_date' => $date['date'],
                    'category' => $category,
                    'quantity' => $quantity,
                    'date' => $date,
                    'amount_ambiguous' => $ambiguousMoney,
                ];

            case self::TYPE_CUSTOMER:
                return [
                    'name' => self::extractCustomerReference($text) ?? $reference,
                    'phone' => self::extractPhone($text),
                    'email' => self::extractEmail($text),
                    'quantity' => $quantity,
                    'date' => $date,
                ];
        }

        return [];
    }

    /** Does the sentence say the count lands in stock (a product, not a sale)? */
    public static function mentionsStock(string $text): bool
    {
        $normalized = ' '.SearchText::normalize($text).' ';
        foreach (self::STOCK_MARKERS as $marker) {
            $needle = SearchText::normalize($marker);
            if ($needle !== '' && str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flags + missing required fields for a draft. Pure: it only reads the
     * draft and the date extraction, so every rule is unit-testable.
     *
     * @param  array<string, mixed>  $draft
     * @return array{flags: array<int, string>, missing_required: array<int, string>}
     */
    public static function inspect(string $type, array $draft, string $today): array
    {
        $flags = [];
        $missing = [];

        if ($type === self::TYPE_PRODUCT) {
            if (empty($draft['name'])) {
                $flags[] = self::FLAG_NAME_MISSING;
                $missing[] = 'name';
            }
            if (empty($draft['category'])) {
                $flags[] = self::FLAG_CATEGORY_MISSING;
                $missing[] = 'category';
            }
            if (empty($draft['price'])) {
                $flags[] = self::FLAG_PRICE_MISSING;
                $missing[] = 'price';
            }
            if (empty($draft['expected_selling_price'])) {
                $flags[] = self::FLAG_SELLING_PRICE_MISSING;
                $missing[] = 'expected_selling_price';
            }
            if ($draft['stock'] === null) {
                $flags[] = self::FLAG_STOCK_MISSING;
                $missing[] = 'stock';
            }
            if (($draft['quantity']['unit_uncertain'] ?? true) === true) {
                $flags[] = self::FLAG_UNIT_UNCERTAIN;
            }
            if (($draft['quantity']['ambiguous'] ?? false) === true) {
                $flags[] = self::FLAG_QUANTITY_AMBIGUOUS;
            }
            if (($draft['amount_ambiguous'] ?? false) === true) {
                $flags[] = self::FLAG_AMOUNT_AMBIGUOUS;
            }
        }

        if ($type === self::TYPE_SALE) {
            $item = $draft['items'][0] ?? [];
            $match = $item['match'] ?? null;
            $status = is_array($match) ? ($match['status'] ?? null) : null;

            if ($status === 'ambiguous') {
                $flags[] = self::FLAG_PRODUCT_AMBIGUOUS;
                $missing[] = 'items[0].product_id';
            } elseif ($status === 'not_found') {
                $flags[] = self::FLAG_PRODUCT_NOT_FOUND;
                $missing[] = 'items[0].product_id';
            }

            if (empty($item['quantity'])) {
                $flags[] = self::FLAG_QUANTITY_MISSING;
                $missing[] = 'items[0].quantity';
            }
            if (empty($item['unit_price'])) {
                $flags[] = self::FLAG_PRICE_MISSING;
                $missing[] = 'items[0].unit_price';
            }
            if (($draft['prices']['assumed'] ?? false) === true) {
                $flags[] = self::FLAG_PRICE_ASSUMED_UNIT;
            }
            if (($draft['prices']['ambiguous'] ?? false) === true) {
                $flags[] = self::FLAG_AMOUNT_AMBIGUOUS;
            }
            if (($draft['quantity']['ambiguous'] ?? false) === true) {
                $flags[] = self::FLAG_QUANTITY_AMBIGUOUS;
            }
            if (($draft['quantity']['unit_uncertain'] ?? true) === true) {
                $flags[] = self::FLAG_UNIT_UNCERTAIN;
            }
            if (($draft['customer_status'] ?? null) === 'new') {
                $flags[] = self::FLAG_CUSTOMER_NEW;
            }
            if (($draft['customer_status'] ?? null) === 'ambiguous') {
                $flags[] = self::FLAG_CUSTOMER_AMBIGUOUS;
                $missing[] = 'customer_id';
            }
            self::dateFlags($draft['date'] ?? [], $today, $flags);
        }

        if ($type === self::TYPE_EXPENSE) {
            if (empty($draft['amount'])) {
                $flags[] = self::FLAG_AMOUNT_MISSING;
                $missing[] = 'amount';
            }
            if (mb_strlen((string) ($draft['description'] ?? '')) < 3) {
                $flags[] = self::FLAG_NAME_MISSING;
                $missing[] = 'description';
            }
            if (empty($draft['category'])) {
                $flags[] = self::FLAG_CATEGORY_UNCERTAIN;
                $missing[] = 'category';
            }
            if (($draft['amount_ambiguous'] ?? false) === true) {
                $flags[] = self::FLAG_AMOUNT_AMBIGUOUS;
            }
            self::dateFlags($draft['date'] ?? [], $today, $flags);
        }

        if ($type === self::TYPE_CUSTOMER) {
            if (empty($draft['name'])) {
                $flags[] = self::FLAG_NAME_MISSING;
                $missing[] = 'name';
            }
            if (empty($draft['phone'])) {
                $flags[] = self::FLAG_PHONE_MISSING;
                $missing[] = 'phone';
            }
        }

        return [
            'flags' => array_values(array_unique($flags)),
            'missing_required' => array_values(array_unique($missing)),
        ];
    }

    /** @param array<string, mixed> $date  @param array<int, string> $flags */
    private static function dateFlags(array $date, string $today, array &$flags): void
    {
        if (($date['ambiguous'] ?? false) === true) {
            $flags[] = self::FLAG_DATE_AMBIGUOUS;
        }
        if (($date['future'] ?? false) === true) {
            $flags[] = self::FLAG_FUTURE_DATE;
        } elseif (($date['date'] ?? null) !== null && $date['date'] !== $today) {
            $flags[] = self::FLAG_DATE_NOT_TODAY;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Optional model assist (allow-listed type only; never a number)      */
    /* ------------------------------------------------------------------ */

    public static function systemInstruction(): string
    {
        return implode("\n", [
            'You help a shop owner in East Africa turn one sentence into ONE record type.',
            'You may ONLY choose a "type" from exactly this list: '.implode(', ', self::TYPES).'.',
            'You may ONLY return a field value if that exact text appears in the sentence you were given.',
            'You must NEVER invent or compute a number, price, quantity or date.',
            'You must never write SQL, table names, column names or any code.',
            'If no listed type fits the sentence, return "type": null.',
            'Reply with JSON only: {"type": "product|sale|expense|customer|null", "name": "text copied from the sentence or null", "reason": "one short sentence in the same language as the sentence"}.',
        ]);
    }

    public static function userPrompt(string $text, string $locale): string
    {
        return "Sentence (language: {$locale}):\n".mb_substr($text, 0, self::MAX_TEXT)."\n\nWhich single record type is this?";
    }

    /**
     * Keep only what is allowed: a type from the allow-list and a name phrase
     * that literally occurs in the sentence. Every number is dropped - numbers
     * come from the trusted extractors, never from the model.
     *
     * @param  mixed  $data
     * @return array<string, mixed>|null
     */
    public static function sanitizeModel($data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $type = isset($data['type']) && is_string($data['type']) ? mb_strtolower(trim($data['type'])) : null;
        if ($type !== null && ! in_array($type, self::TYPES, true)) {
            $type = null;
        }

        $name = null;
        if (isset($data['name']) && is_string($data['name'])) {
            $name = trim($data['name']);
            // A numeric-looking "name" is a number in disguise: refuse it.
            if (preg_match('/^\d+([.,]\d+)?$/', $name) === 1) {
                $name = null;
            }
            if ($name !== null && mb_strlen($name) > self::MAX_REFERENCE) {
                $name = mb_substr($name, 0, self::MAX_REFERENCE);
            }
        }

        $reason = null;
        if (isset($data['reason']) && is_string($data['reason'])) {
            $reason = mb_substr(trim(strip_tags($data['reason'])), 0, 200);
        }

        if ($type === null && $name === null && $reason === null) {
            return null;
        }

        return ['type' => $type, 'name' => $name, 'reason' => $reason];
    }

    /**
     * Drop a model-proposed name unless the sentence really contains it.
     * Case/whitespace/punctuation are ignored; nothing else is forgiven.
     */
    public static function modelNameIsGrounded(?string $name, string $text): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        $haystack = SearchText::normalize($text);
        $needle = SearchText::normalize($name);

        return $needle !== '' && str_contains($haystack, $needle);
    }

    /* ------------------------------------------------------------------ */
    /* Localized copy                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Client-facing chrome for this capability, served by the endpoint so both
     * the website partial and the mobile panel render the same words. Mirrored
     * in the two locale catalogues; NlEntryTest asserts the copies agree.
     */
    public const MESSAGES = [
        'en' => [
            'title' => 'Write it, we record it',
            'subtitle' => 'Describe one thing that happened and check the preview before saving.',
            'placeholder' => 'e.g. Add 20 bottles of blue perfume to stock',
            'analyze' => 'Prepare preview',
            'preview_title' => 'Check before saving',
            'confirm' => 'Confirm and save',
            'cancel' => 'Cancel',
            'retry' => 'Try again',
            'saved' => 'Saved through the normal workflow.',
            'unsupported' => 'I could not tell which record this is. Write it as a product, a sale, an expense or a customer.',
            'type_product' => 'Product',
            'type_sale' => 'Sale',
            'type_expense' => 'Expense',
            'type_customer' => 'Customer',
            'matched' => 'Found in your catalogue',
            'ambiguous' => 'More than one product fits - choose one',
            'not_found' => 'Not in your catalogue',
            'new_record' => 'Will be created as a new record',
            'characters_left' => '{count} characters left',
            'name' => 'Name',
            'category' => 'Category',
            'unit' => 'Unit',
            'stock' => 'Stock',
            'price' => 'Price',
            'selling_price' => 'Selling price',
            'description' => 'Description',
            'amount' => 'Amount',
            'date' => 'Date',
            'phone' => 'Phone',
            'email' => 'Email',
            'quantity' => 'Quantity',
            'unit_price' => 'Unit price',
            'customer' => 'Customer',
            'payment' => 'Payment method',
            'sign_in' => 'Please sign in again.',
            'check_missing' => 'Fill in the fields marked as missing.',
        ],
        'sw' => [
            'title' => 'Andika, sisi tunarekodi',
            'subtitle' => 'Eleza jambo moja lililotokea na uangalie muhtasari kabla ya kuhifadhi.',
            'placeholder' => 'mfano: Ongeza chupa 20 za manukato ya bluu kwenye stock',
            'analyze' => 'Tengeneza muhtasari',
            'preview_title' => 'Angalia kabla ya kuhifadhi',
            'confirm' => 'Thibitisha na hifadhi',
            'cancel' => 'Ghairi',
            'retry' => 'Jaribu tena',
            'saved' => 'Imehifadhiwa kwa mtiririko wa kawaida.',
            'unsupported' => 'Sikuweza kutambua rekodi hii. Andika kama bidhaa, mauzo, matumizi au mteja.',
            'type_product' => 'Bidhaa',
            'type_sale' => 'Mauzo',
            'type_expense' => 'Matumizi',
            'type_customer' => 'Mteja',
            'matched' => 'Imepatikana kwenye katalogi yako',
            'ambiguous' => 'Bidhaa zaidi ya moja zinafanana - chagua moja',
            'not_found' => 'Haipo kwenye katalogi yako',
            'new_record' => 'Itaundwa kama rekodi mpya',
            'characters_left' => 'Herufi {count} zimebaki',
            'name' => 'Jina',
            'category' => 'Aina',
            'unit' => 'Kipimo',
            'stock' => 'Stock',
            'price' => 'Bei',
            'selling_price' => 'Bei ya kuuza',
            'description' => 'Maelezo',
            'amount' => 'Kiasi',
            'date' => 'Tarehe',
            'phone' => 'Simu',
            'email' => 'Barua pepe',
            'quantity' => 'Idadi',
            'unit_price' => 'Bei ya kimoja',
            'customer' => 'Mteja',
            'payment' => 'Njia ya malipo',
            'sign_in' => 'Tafadhali ingia tena.',
            'check_missing' => 'Jaza sehemu zilizoonyeshwa kama zinazokosekana.',
        ],
        'fr' => [
            'title' => 'Écrivez, nous enregistrons',
            'subtitle' => "Décrivez une seule chose qui s'est passée et vérifiez l'aperçu avant d'enregistrer.",
            'placeholder' => 'ex. Ajouter 20 bouteilles de parfum bleu au stock',
            'analyze' => "Préparer l'aperçu",
            'preview_title' => "Vérifier avant d'enregistrer",
            'confirm' => 'Confirmer et enregistrer',
            'cancel' => 'Annuler',
            'retry' => 'Réessayer',
            'saved' => 'Enregistré via le flux habituel.',
            'unsupported' => "Je n'ai pas pu déterminer de quel enregistrement il s'agit. Écrivez-le comme un produit, une vente, une dépense ou un client.",
            'type_product' => 'Produit',
            'type_sale' => 'Vente',
            'type_expense' => 'Dépense',
            'type_customer' => 'Client',
            'matched' => 'Trouvé dans votre catalogue',
            'ambiguous' => 'Plusieurs produits correspondent - choisissez-en un',
            'not_found' => 'Absent de votre catalogue',
            'new_record' => 'Sera créé comme nouvel enregistrement',
            'characters_left' => '{count} caractères restants',
            'name' => 'Nom',
            'category' => 'Catégorie',
            'unit' => 'Unité',
            'stock' => 'Stock',
            'price' => 'Prix',
            'selling_price' => 'Prix de vente',
            'description' => 'Description',
            'amount' => 'Montant',
            'date' => 'Date',
            'phone' => 'Téléphone',
            'email' => 'E-mail',
            'quantity' => 'Quantité',
            'unit_price' => 'Prix unitaire',
            'customer' => 'Client',
            'payment' => 'Mode de paiement',
            'sign_in' => 'Veuillez vous reconnecter.',
            'check_missing' => 'Remplissez les champs signalés comme manquants.',
        ],
        'hi' => [
            'title' => 'लिखें, हम दर्ज करेंगे',
            'subtitle' => 'जो हुआ उसे एक वाक्य में लिखें और सहेजने से पहले पूर्वावलोकन देखें।',
            'placeholder' => 'जैसे: स्टॉक में 20 बोतल नीली परफ्यूम जोड़ें',
            'analyze' => 'पूर्वावलोकन बनाएँ',
            'preview_title' => 'सहेजने से पहले जाँचें',
            'confirm' => 'पुष्टि करें और सहेजें',
            'cancel' => 'रद्द करें',
            'retry' => 'फिर कोशिश करें',
            'saved' => 'सामान्य प्रक्रिया से सहेजा गया।',
            'unsupported' => 'मैं यह नहीं पहचान सका कि यह कौन-सी प्रविष्टि है। इसे उत्पाद, बिक्री, खर्च या ग्राहक के रूप में लिखें।',
            'type_product' => 'उत्पाद',
            'type_sale' => 'बिक्री',
            'type_expense' => 'खर्च',
            'type_customer' => 'ग्राहक',
            'matched' => 'आपकी सूची में मिला',
            'ambiguous' => 'एक से अधिक उत्पाद मेल खाते हैं - एक चुनें',
            'not_found' => 'आपकी सूची में नहीं है',
            'new_record' => 'नई प्रविष्टि के रूप में बनेगा',
            'characters_left' => '{count} अक्षर शेष',
            'name' => 'नाम',
            'category' => 'श्रेणी',
            'unit' => 'इकाई',
            'stock' => 'स्टॉक',
            'price' => 'मूल्य',
            'selling_price' => 'बिक्री मूल्य',
            'description' => 'विवरण',
            'amount' => 'राशि',
            'date' => 'तारीख',
            'phone' => 'फ़ोन',
            'email' => 'ईमेल',
            'quantity' => 'मात्रा',
            'unit_price' => 'इकाई मूल्य',
            'customer' => 'ग्राहक',
            'payment' => 'भुगतान का तरीका',
            'sign_in' => 'कृपया दोबारा साइन इन करें।',
            'check_missing' => 'छूटे हुए बताए गए फ़ील्ड भरें।',
        ],
        'es' => [
            'title' => 'Escríbelo, lo registramos',
            'subtitle' => 'Describe una sola cosa que pasó y revisa la vista previa antes de guardar.',
            'placeholder' => 'p. ej. Añadir 20 botellas de perfume azul al stock',
            'analyze' => 'Preparar vista previa',
            'preview_title' => 'Revisa antes de guardar',
            'confirm' => 'Confirmar y guardar',
            'cancel' => 'Cancelar',
            'retry' => 'Intentar de nuevo',
            'saved' => 'Guardado mediante el flujo habitual.',
            'unsupported' => 'No pude identificar qué registro es. Escríbelo como producto, venta, gasto o cliente.',
            'type_product' => 'Producto',
            'type_sale' => 'Venta',
            'type_expense' => 'Gasto',
            'type_customer' => 'Cliente',
            'matched' => 'Encontrado en tu catálogo',
            'ambiguous' => 'Más de un producto coincide: elige uno',
            'not_found' => 'No está en tu catálogo',
            'new_record' => 'Se creará como registro nuevo',
            'characters_left' => 'Quedan {count} caracteres',
            'name' => 'Nombre',
            'category' => 'Categoría',
            'unit' => 'Unidad',
            'stock' => 'Stock',
            'price' => 'Precio',
            'selling_price' => 'Precio de venta',
            'description' => 'Descripción',
            'amount' => 'Importe',
            'date' => 'Fecha',
            'phone' => 'Teléfono',
            'email' => 'Correo',
            'quantity' => 'Cantidad',
            'unit_price' => 'Precio unitario',
            'customer' => 'Cliente',
            'payment' => 'Método de pago',
            'sign_in' => 'Vuelve a iniciar sesión.',
            'check_missing' => 'Completa los campos marcados como faltantes.',
        ],
        'ur' => [
            'title' => 'لکھیں، ہم درج کریں گے',
            'subtitle' => 'جو ہوا اسے ایک جملے میں لکھیں اور محفوظ کرنے سے پہلے جھلک دیکھیں۔',
            'placeholder' => 'مثلاً: اسٹاک میں 20 بوتلیں نیلی خوشبو شامل کریں',
            'analyze' => 'جھلک تیار کریں',
            'preview_title' => 'محفوظ کرنے سے پہلے دیکھیں',
            'confirm' => 'تصدیق کریں اور محفوظ کریں',
            'cancel' => 'منسوخ کریں',
            'retry' => 'دوبارہ کوشش کریں',
            'saved' => 'عام طریقہ کار سے محفوظ ہو گیا۔',
            'unsupported' => 'میں یہ نہیں پہچان سکا کہ یہ کون سا ریکارڈ ہے۔ اسے پروڈکٹ، فروخت، خرچ یا گاہک کے طور پر لکھیں۔',
            'type_product' => 'پروڈکٹ',
            'type_sale' => 'فروخت',
            'type_expense' => 'خرچ',
            'type_customer' => 'گاہک',
            'matched' => 'آپ کی فہرست میں ملا',
            'ambiguous' => 'ایک سے زیادہ پروڈکٹ مماثل ہیں - ایک منتخب کریں',
            'not_found' => 'آپ کی فہرست میں نہیں',
            'new_record' => 'نئے ریکارڈ کے طور پر بنے گا',
            'characters_left' => '{count} حروف باقی',
            'name' => 'نام',
            'category' => 'قسم',
            'unit' => 'یونٹ',
            'stock' => 'اسٹاک',
            'price' => 'قیمت',
            'selling_price' => 'فروخت قیمت',
            'description' => 'تفصیل',
            'amount' => 'رقم',
            'date' => 'تاریخ',
            'phone' => 'فون',
            'email' => 'ای میل',
            'quantity' => 'مقدار',
            'unit_price' => 'فی یونٹ قیمت',
            'customer' => 'گاہک',
            'payment' => 'ادائیگی کا طریقہ',
            'sign_in' => 'براہ کرم دوبارہ سائن ان کریں۔',
            'check_missing' => 'جو فیلڈز غائب بتائی گئی ہیں انہیں بھریں۔',
        ],
        'de' => [
            'title' => 'Schreib es, wir erfassen es',
            'subtitle' => 'Beschreibe eine Sache, die passiert ist, und prüfe die Vorschau vor dem Speichern.',
            'placeholder' => 'z. B. 20 Flaschen blaues Parfüm zum Bestand hinzufügen',
            'analyze' => 'Vorschau erstellen',
            'preview_title' => 'Vor dem Speichern prüfen',
            'confirm' => 'Bestätigen und speichern',
            'cancel' => 'Abbrechen',
            'retry' => 'Erneut versuchen',
            'saved' => 'Über den normalen Ablauf gespeichert.',
            'unsupported' => 'Ich konnte nicht erkennen, um welchen Datensatz es geht. Schreibe es als Produkt, Verkauf, Ausgabe oder Kunde.',
            'type_product' => 'Produkt',
            'type_sale' => 'Verkauf',
            'type_expense' => 'Ausgabe',
            'type_customer' => 'Kunde',
            'matched' => 'In deinem Katalog gefunden',
            'ambiguous' => 'Mehrere Produkte passen - wähle eines',
            'not_found' => 'Nicht in deinem Katalog',
            'new_record' => 'Wird als neuer Datensatz angelegt',
            'characters_left' => '{count} Zeichen übrig',
            'name' => 'Name',
            'category' => 'Kategorie',
            'unit' => 'Einheit',
            'stock' => 'Bestand',
            'price' => 'Preis',
            'selling_price' => 'Verkaufspreis',
            'description' => 'Beschreibung',
            'amount' => 'Betrag',
            'date' => 'Datum',
            'phone' => 'Telefon',
            'email' => 'E-Mail',
            'quantity' => 'Menge',
            'unit_price' => 'Stückpreis',
            'customer' => 'Kunde',
            'payment' => 'Zahlungsart',
            'sign_in' => 'Bitte melde dich erneut an.',
            'check_missing' => 'Fülle die als fehlend markierten Felder aus.',
        ],
        'zh' => [
            'title' => '写下来，我们来记录',
            'subtitle' => '用一句话描述发生的事，保存前先检查预览。',
            'placeholder' => '例如：把 20 瓶蓝色香水加入库存',
            'analyze' => '生成预览',
            'preview_title' => '保存前请核对',
            'confirm' => '确认并保存',
            'cancel' => '取消',
            'retry' => '重试',
            'saved' => '已通过常规流程保存。',
            'unsupported' => '我无法判断这是哪类记录。请按产品、销售、支出或客户来写。',
            'type_product' => '产品',
            'type_sale' => '销售',
            'type_expense' => '支出',
            'type_customer' => '客户',
            'matched' => '在你的目录中找到',
            'ambiguous' => '有多个产品匹配——请选择其一',
            'not_found' => '不在你的目录中',
            'new_record' => '将创建为新记录',
            'characters_left' => '剩余 {count} 个字符',
            'name' => '名称',
            'category' => '类别',
            'unit' => '单位',
            'stock' => '库存',
            'price' => '价格',
            'selling_price' => '销售价',
            'description' => '说明',
            'amount' => '金额',
            'date' => '日期',
            'phone' => '电话',
            'email' => '邮箱',
            'quantity' => '数量',
            'unit_price' => '单价',
            'customer' => '客户',
            'payment' => '支付方式',
            'sign_in' => '请重新登录。',
            'check_missing' => '请填写标记为缺失的字段。',
        ],
    ];

    private const FLAG_MESSAGES = [
        'type_uncertain' => [
            'sw' => 'Sijaelewa ni rekodi ya aina gani. Andika kama bidhaa, mauzo, matumizi au mteja.',
            'en' => 'I could not tell which type of record this is. Write it as a product, a sale, an expense or a customer.',
            'fr' => "Je n'ai pas pu déterminer le type d'enregistrement. Écrivez-le comme produit, vente, dépense ou client.",
            'hi' => 'मैं यह नहीं समझ सका कि यह किस प्रकार की प्रविष्टि है। इसे उत्पाद, बिक्री, खर्च या ग्राहक के रूप में लिखें।',
            'es' => 'No pude determinar qué tipo de registro es. Escríbelo como producto, venta, gasto o cliente.',
            'ur' => 'میں یہ نہیں سمجھ سکا کہ یہ کس قسم کا ریکارڈ ہے۔ اسے پروڈکٹ، فروخت، خرچ یا گاہک کے طور پر لکھیں۔',
            'de' => 'Ich konnte die Art des Datensatzes nicht bestimmen. Schreibe es als Produkt, Verkauf, Ausgabe oder Kunde.',
            'zh' => '我无法判断这是哪类记录。请按产品、销售、支出或客户来写。',
        ],
        'type_unsupported' => [
            'sw' => 'Aina ya rekodi hii haitumiki. Tunatumia bidhaa, mauzo, matumizi na wateja pekee.',
            'en' => 'That kind of record is not supported. Only products, sales, expenses and customers are recorded here.',
            'fr' => "Ce type d'enregistrement n'est pas pris en charge. Seuls produits, ventes, dépenses et clients le sont.",
            'hi' => 'इस प्रकार की प्रविष्टि समर्थित नहीं है। यहाँ केवल उत्पाद, बिक्री, खर्च और ग्राहक दर्ज होते हैं।',
            'es' => 'Ese tipo de registro no es compatible. Aquí solo se registran productos, ventas, gastos y clientes.',
            'ur' => 'اس قسم کا ریکارڈ تعاون یافتہ نہیں۔ یہاں صرف پروڈکٹ، فروخت، خرچ اور گاہک درج ہوتے ہیں۔',
            'de' => 'Diese Art von Datensatz wird nicht unterstützt. Erfasst werden nur Produkte, Verkäufe, Ausgaben und Kunden.',
            'zh' => '不支持这种记录类型。这里只记录产品、销售、支出和客户。',
        ],
        'name_missing' => [
            'sw' => 'Jina halikuonekana kwenye maandishi - liweke kwenye fomu.',
            'en' => 'No name was recognized in the text - enter it in the form.',
            'fr' => "Aucun nom n'a été reconnu - saisissez-le dans le formulaire.",
            'hi' => 'पाठ में कोई नाम नहीं मिला - फ़ॉर्म में भरें।',
            'es' => 'No se reconoció ningún nombre: introdúcelo en el formulario.',
            'ur' => 'متن میں کوئی نام نہیں ملا - فارم میں درج کریں۔',
            'de' => 'Im Text wurde kein Name erkannt - gib ihn im Formular ein.',
            'zh' => '文本中未识别到名称——请在表单中填写。',
        ],
        'category_missing' => [
            'sw' => 'Aina ya bidhaa haipo - chagua moja kwenye fomu.',
            'en' => 'The product category is missing - pick one in the form.',
            'fr' => 'La catégorie du produit manque - choisissez-en une.',
            'hi' => 'उत्पाद श्रेणी नहीं है - फ़ॉर्म में एक चुनें।',
            'es' => 'Falta la categoría del producto: elige una en el formulario.',
            'ur' => 'پروڈکٹ کی قسم موجود نہیں - فارم میں ایک منتخب کریں۔',
            'de' => 'Die Produktkategorie fehlt - wähle eine im Formular.',
            'zh' => '缺少产品类别——请在表单中选择。',
        ],
        'category_uncertain' => [
            'sw' => 'Aina ya matumizi haijulikani kwa uhakika - chagua kwenye orodha.',
            'en' => 'The expense category is not certain - pick one from the list.',
            'fr' => "La catégorie de dépense n'est pas certaine - choisissez-en une.",
            'hi' => 'खर्च की श्रेणी निश्चित नहीं - सूची से चुनें।',
            'es' => 'La categoría del gasto no es segura: elige una de la lista.',
            'ur' => 'خرچ کی قسم یقینی نہیں - فہرست سے منتخب کریں۔',
            'de' => 'Die Ausgabenkategorie ist nicht sicher - wähle eine aus der Liste.',
            'zh' => '支出类别不确定——请从列表中选择。',
        ],
        'amount_missing' => [
            'sw' => 'Kiasi hakikuonekana kwenye maandishi - weka kiasi kwenye fomu.',
            'en' => 'No amount was recognized in the text - enter it in the form.',
            'fr' => "Aucun montant n'a été reconnu - saisissez-le.",
            'hi' => 'पाठ में कोई राशि नहीं मिली - फ़ॉर्म में भरें।',
            'es' => 'No se reconoció ningún importe: introdúcelo.',
            'ur' => 'متن میں کوئی رقم نہیں ملی - فارم میں درج کریں۔',
            'de' => 'Im Text wurde kein Betrag erkannt - gib ihn ein.',
            'zh' => '文本中未识别到金额——请填写。',
        ],
        'amount_ambiguous' => [
            'sw' => 'Kuna namba zaidi ya moja - angalia kiasi na bei kwa makini.',
            'en' => 'More than one amount was found - check the quantity and the price carefully.',
            'fr' => 'Plusieurs montants trouvés - vérifiez la quantité et le prix.',
            'hi' => 'एक से अधिक राशि मिलीं - मात्रा और मूल्य ध्यान से देखें।',
            'es' => 'Se encontraron varios importes: revisa la cantidad y el precio.',
            'ur' => 'ایک سے زیادہ رقم ملی - مقدار اور قیمت غور سے دیکھیں۔',
            'de' => 'Mehrere Beträge gefunden - prüfe Menge und Preis.',
            'zh' => '找到多个金额——请仔细核对数量与价格。',
        ],
        'price_missing' => [
            'sw' => 'Bei haikuonekana - weka bei kwenye fomu.',
            'en' => 'No price was recognized - enter it in the form.',
            'fr' => "Aucun prix n'a été reconnu - saisissez-le.",
            'hi' => 'कोई मूल्य नहीं मिला - फ़ॉर्म में भरें।',
            'es' => 'No se reconoció ningún precio: introdúcelo.',
            'ur' => 'کوئی قیمت نہیں ملی - فارم میں درج کریں۔',
            'de' => 'Es wurde kein Preis erkannt - gib ihn ein.',
            'zh' => '未识别到价格——请填写。',
        ],
        'selling_price_missing' => [
            'sw' => 'Bei ya kuuzia haipo - weka bei ya kuuzia kwenye fomu.',
            'en' => 'The selling price is missing - enter the selling price in the form.',
            'fr' => 'Le prix de vente manque - saisissez-le.',
            'hi' => 'बिक्री मूल्य नहीं है - फ़ॉर्म में भरें।',
            'es' => 'Falta el precio de venta: introdúcelo.',
            'ur' => 'فروخت قیمت موجود نہیں - فارم میں درج کریں۔',
            'de' => 'Der Verkaufspreis fehlt - gib ihn ein.',
            'zh' => '缺少销售价——请填写。',
        ],
        'price_assumed_unit' => [
            'sw' => 'Kiasi kimoja kimechukuliwa kama bei ya kitu kimoja - hakikisha.',
            'en' => 'The single amount was read as the price of ONE unit - please confirm.',
            'fr' => "Le montant unique a été lu comme le prix d'UNE unité - confirmez.",
            'hi' => 'एकमात्र राशि को एक इकाई का मूल्य माना गया - पुष्टि करें।',
            'es' => 'El único importe se leyó como el precio de UNA unidad: confírmalo.',
            'ur' => 'واحد رقم کو ایک یونٹ کی قیمت سمجھا گیا - تصدیق کریں۔',
            'de' => 'Der einzelne Betrag wurde als Preis für EINE Einheit gelesen - bitte bestätigen.',
            'zh' => '已将唯一金额视为单价——请确认。',
        ],
        'price_assumed_total' => [
            'sw' => 'Kiasi kimechukuliwa kama jumla - hakikisha.',
            'en' => 'The amount was read as the total - please confirm.',
            'fr' => 'Le montant a été lu comme le total - confirmez.',
            'hi' => 'राशि को कुल माना गया - पुष्टि करें।',
            'es' => 'El importe se leyó como el total: confírmalo.',
            'ur' => 'رقم کو کل سمجھا گیا - تصدیق کریں۔',
            'de' => 'Der Betrag wurde als Gesamtsumme gelesen - bitte bestätigen.',
            'zh' => '已将该金额视为总额——请确认。',
        ],
        'stock_missing' => [
            'sw' => 'Idadi ya stock haijaonekana - weka idadi kwenye fomu.',
            'en' => 'No stock quantity was recognized - enter the quantity in the form.',
            'fr' => 'Aucune quantité en stock reconnue - saisissez-la.',
            'hi' => 'स्टॉक मात्रा नहीं मिली - फ़ॉर्म में भरें।',
            'es' => 'No se reconoció ninguna cantidad de stock: introdúcela.',
            'ur' => 'اسٹاک مقدار نہیں ملی - فارم میں درج کریں۔',
            'de' => 'Keine Bestandsmenge erkannt - gib die Menge ein.',
            'zh' => '未识别到库存数量——请填写。',
        ],
        'quantity_missing' => [
            'sw' => 'Idadi haijaonekana - weka idadi kwenye fomu.',
            'en' => 'No quantity was recognized - enter it in the form.',
            'fr' => 'Aucune quantité reconnue - saisissez-la.',
            'hi' => 'कोई मात्रा नहीं मिली - फ़ॉर्म में भरें।',
            'es' => 'No se reconoció ninguna cantidad: introdúcela.',
            'ur' => 'کوئی مقدار نہیں ملی - فارم میں درج کریں۔',
            'de' => 'Keine Menge erkannt - gib sie ein.',
            'zh' => '未识别到数量——请填写。',
        ],
        'quantity_ambiguous' => [
            'sw' => 'Namba zaidi ya moja zinaweza kuwa idadi - hakikisha idadi sahihi.',
            'en' => 'More than one number could be the quantity - confirm the right one.',
            'fr' => 'Plusieurs nombres pourraient être la quantité - confirmez.',
            'hi' => 'एक से अधिक संख्याएँ मात्रा हो सकती हैं - सही की पुष्टि करें।',
            'es' => 'Más de un número podría ser la cantidad: confirma el correcto.',
            'ur' => 'ایک سے زیادہ اعداد مقدار ہو سکتے ہیں - درست کی تصدیق کریں۔',
            'de' => 'Mehrere Zahlen könnten die Menge sein - bestätige die richtige.',
            'zh' => '可能有多个数字表示数量——请确认正确的一个。',
        ],
        'unit_uncertain' => [
            'sw' => 'Kipimo (chupa, vipande, kilo) hakijulikani - chagua kwenye fomu.',
            'en' => 'The unit (bottles, pieces, kilos) is not clear - pick one in the form.',
            'fr' => "L'unité (bouteilles, pièces, kilos) n'est pas claire - choisissez-la.",
            'hi' => 'इकाई (बोतल, टुकड़े, किलो) स्पष्ट नहीं - फ़ॉर्म में चुनें।',
            'es' => 'La unidad (botellas, piezas, kilos) no está clara: elige una.',
            'ur' => 'یونٹ (بوتل، عدد، کلو) واضح نہیں - فارم میں منتخب کریں۔',
            'de' => 'Die Einheit (Flaschen, Stück, Kilo) ist unklar - wähle eine.',
            'zh' => '单位（瓶、个、公斤）不明确——请在表单中选择。',
        ],
        'date_ambiguous' => [
            'sw' => 'Tarehe haijaainishwa wazi - hakikisha tarehe sahihi.',
            'en' => 'The date is ambiguous - confirm the right date.',
            'fr' => 'La date est ambiguë - confirmez la bonne date.',
            'hi' => 'तारीख अस्पष्ट है - सही तारीख की पुष्टि करें।',
            'es' => 'La fecha es ambigua: confirma la correcta.',
            'ur' => 'تاریخ واضح نہیں - درست تاریخ کی تصدیق کریں۔',
            'de' => 'Das Datum ist mehrdeutig - bestätige das richtige.',
            'zh' => '日期不明确——请确认正确日期。',
        ],
        'date_not_today' => [
            'sw' => 'Tarehe iliyotajwa si ya leo ({date}) - hakikisha.',
            'en' => 'The stated date is not today ({date}) - please confirm.',
            'fr' => "La date indiquée n'est pas aujourd'hui ({date}) - confirmez.",
            'hi' => 'बताई गई तारीख आज नहीं है ({date}) - पुष्टि करें।',
            'es' => 'La fecha indicada no es hoy ({date}): confírmala.',
            'ur' => 'بتائی گئی تاریخ آج نہیں ({date}) - تصدیق کریں۔',
            'de' => 'Das angegebene Datum ist nicht heute ({date}) - bitte bestätigen.',
            'zh' => '所述日期不是今天（{date}）——请确认。',
        ],
        'future_date' => [
            'sw' => 'Tarehe ni baadaye ya leo ({date}) - hakikisha ni sahihi.',
            'en' => 'The date is in the future ({date}) - please confirm it is correct.',
            'fr' => 'La date est dans le futur ({date}) - veuillez confirmer.',
            'hi' => 'तारीख भविष्य में है ({date}) - कृपया पुष्टि करें।',
            'es' => 'La fecha es futura ({date}): confirma que es correcta.',
            'ur' => 'تاریخ مستقبل میں ہے ({date}) - براہ کرم تصدیق کریں۔',
            'de' => 'Das Datum liegt in der Zukunft ({date}) - bitte bestätigen.',
            'zh' => '日期在未来（{date}）——请确认。',
        ],
        'product_ambiguous' => [
            'sw' => 'Bidhaa zaidi ya moja zinafanana - chagua bidhaa sahihi; sitachagua mwenyewe.',
            'en' => 'More than one product matches - choose the right one; I will not pick for you.',
            'fr' => 'Plusieurs produits correspondent - choisissez le bon ; je ne choisirai pas à votre place.',
            'hi' => 'एक से अधिक उत्पाद मेल खाते हैं - सही चुनें; मैं आपके लिए नहीं चुनूँगा।',
            'es' => 'Más de un producto coincide: elige el correcto; no elegiré por ti.',
            'ur' => 'ایک سے زیادہ پروڈکٹ مماثل ہیں - درست منتخب کریں؛ میں آپ کے لیے نہیں چنوں گا۔',
            'de' => 'Mehrere Produkte passen - wähle das richtige; ich wähle nicht für dich.',
            'zh' => '有多个产品匹配——请选择正确的一个；我不会替你选择。',
        ],
        'product_not_found' => [
            'sw' => 'Bidhaa hii haipo kwenye katalogi yako - ongeza bidhaa kwanza au chagua bidhaa iliyopo.',
            'en' => 'That product is not in your catalogue - add the product first, or pick an existing one.',
            'fr' => "Ce produit n'est pas dans votre catalogue - ajoutez-le d'abord ou choisissez-en un existant.",
            'hi' => 'यह उत्पाद आपकी सूची में नहीं है - पहले उत्पाद जोड़ें या मौजूदा चुनें।',
            'es' => 'Ese producto no está en tu catálogo: añádelo primero o elige uno existente.',
            'ur' => 'یہ پروڈکٹ آپ کی فہرست میں نہیں - پہلے پروڈکٹ شامل کریں یا موجودہ منتخب کریں۔',
            'de' => 'Dieses Produkt ist nicht in deinem Katalog - lege es zuerst an oder wähle ein vorhandenes.',
            'zh' => '该产品不在你的目录中——请先添加产品，或选择现有产品。',
        ],
        'product_exists' => [
            'sw' => 'Bidhaa yenye jina hili ipo tayari kwenye katalogi yako - ongeza stock kwenye bidhaa iliyopo badala ya kuunda mpya.',
            'en' => 'A product with this name already exists in your catalogue - add stock to the existing product instead of creating a new one.',
            'fr' => 'Un produit portant ce nom existe déjà dans votre catalogue - ajoutez du stock au produit existant plutôt que d\'en créer un nouveau.',
            'hi' => 'इस नाम का उत्पाद आपकी सूची में पहले से है - नया बनाने के बजाय मौजूदा उत्पाद में स्टॉक जोड़ें।',
            'es' => 'Ya existe un producto con este nombre en tu catálogo: añade stock al producto existente en lugar de crear uno nuevo.',
            'ur' => 'اس نام کا پروڈکٹ آپ کی فہرست میں پہلے سے موجود ہے - نیا بنانے کے بجائے موجودہ پروڈکٹ میں اسٹاک شامل کریں۔',
            'de' => 'Ein Produkt mit diesem Namen existiert bereits in deinem Katalog - füge dem vorhandenen Produkt Bestand hinzu, statt ein neues anzulegen.',
            'zh' => '你的目录中已有同名产品——请为现有产品增加库存，而不是新建。',
        ],
        'product_new' => [
            'sw' => 'Bidhaa hii haipo bado - itaundwa kama bidhaa mpya.',
            'en' => 'This product does not exist yet - it will be created as a new product.',
            'fr' => "Ce produit n'existe pas encore - il sera créé comme nouveau produit.",
            'hi' => 'यह उत्पाद अभी नहीं है - इसे नए उत्पाद के रूप में बनाया जाएगा।',
            'es' => 'Este producto aún no existe: se creará como producto nuevo.',
            'ur' => 'یہ پروڈکٹ ابھی موجود نہیں - اسے نئے پروڈکٹ کے طور پر بنایا جائے گا۔',
            'de' => 'Dieses Produkt existiert noch nicht - es wird als neues Produkt angelegt.',
            'zh' => '该产品尚不存在——将作为新产品创建。',
        ],
        'customer_new' => [
            'sw' => 'Mteja huyu hayupo kwenye orodha yako - ataundwa kama mteja mpya.',
            'en' => 'This customer is not in your list - they will be created as a new customer.',
            'fr' => "Ce client n'est pas dans votre liste - il sera créé comme nouveau client.",
            'hi' => 'यह ग्राहक आपकी सूची में नहीं है - इसे नए ग्राहक के रूप में बनाया जाएगा।',
            'es' => 'Este cliente no está en tu lista: se creará como cliente nuevo.',
            'ur' => 'یہ گاہک آپ کی فہرست میں نہیں - اسے نئے گاہک کے طور پر بنایا جائے گا۔',
            'de' => 'Dieser Kunde ist nicht in deiner Liste - er wird als neuer Kunde angelegt.',
            'zh' => '该客户不在你的名单中——将作为新客户创建。',
        ],
        'customer_ambiguous' => [
            'sw' => 'Wateja zaidi ya mmoja wanafanana na jina hili - chagua mmoja.',
            'en' => 'More than one customer matches this name - choose one.',
            'fr' => 'Plusieurs clients correspondent à ce nom - choisissez-en un.',
            'hi' => 'इस नाम से एक से अधिक ग्राहक मेल खाते हैं - एक चुनें।',
            'es' => 'Más de un cliente coincide con este nombre: elige uno.',
            'ur' => 'اس نام سے ایک سے زیادہ گاہک مماثل ہیں - ایک منتخب کریں۔',
            'de' => 'Mehrere Kunden passen zu diesem Namen - wähle einen.',
            'zh' => '有多个客户与此名称匹配——请选择其一。',
        ],
        'customer_missing' => [
            'sw' => 'Jina la mteja halikuonekana - liweke kwenye fomu.',
            'en' => 'No customer name was recognized - enter it in the form.',
            'fr' => "Aucun nom de client reconnu - saisissez-le.",
            'hi' => 'ग्राहक का नाम नहीं मिला - फ़ॉर्म में भरें।',
            'es' => 'No se reconoció ningún nombre de cliente: introdúcelo.',
            'ur' => 'گاہک کا نام نہیں ملا - فارم میں درج کریں۔',
            'de' => 'Es wurde kein Kundenname erkannt - gib ihn ein.',
            'zh' => '未识别到客户姓名——请填写。',
        ],
        'phone_missing' => [
            'sw' => 'Namba ya simu haikuonekana - weka namba kwenye fomu.',
            'en' => 'No phone number was recognized - enter it in the form.',
            'fr' => "Aucun numéro de téléphone reconnu - saisissez-le.",
            'hi' => 'फ़ोन नंबर नहीं मिला - फ़ॉर्म में भरें।',
            'es' => 'No se reconoció ningún número de teléfono: introdúcelo.',
            'ur' => 'فون نمبر نہیں ملا - فارم میں درج کریں۔',
            'de' => 'Es wurde keine Telefonnummer erkannt - gib sie ein.',
            'zh' => '未识别到电话号码——请填写。',
        ],
    ];

    /** Localized, parameterized message for one flag code. */
    public static function flagMessage(string $code, string $locale, array $params = []): string
    {
        $table = self::FLAG_MESSAGES[$code] ?? null;
        $message = $table[$locale] ?? ($table['en'] ?? $code);

        foreach ($params as $name => $value) {
            $message = str_replace('{'.$name.'}', (string) $value, $message);
        }

        return $message;
    }

    /** One UI string, localized, with {placeholders} replaced. */
    public static function message(string $key, string $locale, array $params = []): string
    {
        $table = self::MESSAGES[$locale] ?? self::MESSAGES['en'];
        $value = $table[$key] ?? (self::MESSAGES['en'][$key] ?? $key);

        foreach ($params as $name => $param) {
            $value = str_replace('{'.$name.'}', (string) $param, $value);
        }

        return $value;
    }

    /** Human label for a record type in the caller's language. */
    public static function typeLabel(?string $type, string $locale): string
    {
        if ($type === null || ! in_array($type, self::TYPES, true)) {
            return '';
        }

        return self::message('type_'.$type, $locale);
    }

    /**
     * Worked examples per type and language - the same ones the UI shows, so the
     * capability never looks like magic: every example is a sentence the
     * extractor really handles.
     */
    public static function examples(string $locale): array
    {
        $table = [
            'sw' => [
                self::TYPE_PRODUCT => ['Ongeza chupa 20 za manukato ya bluu kwenye stock', 'Ongeza bidhaa mpya saruji, mfuko 50, bei 18000, kuuza 22000'],
                self::TYPE_SALE => ["Nimeuza vipande 3 vya sabuni kwa 2000 kila kimoja", 'Niliuza sukari kilo 5 kwa 3500 kwa mteja Amina'],
                self::TYPE_EXPENSE => ["Rekodi matumizi ya usafiri ya leo ya 5000", 'Nimetumia 12000 kwa umeme leo'],
                self::TYPE_CUSTOMER => ['Ongeza mteja Amina, simu 0712345678', 'Ongeza mteja Juma mwenye barua pepe juma@example.com'],
            ],
            'en' => [
                self::TYPE_PRODUCT => ['Add 20 bottles of blue perfume to stock', 'Add a new product cement, 50 bags, buying 18000, selling 22000'],
                self::TYPE_SALE => ['I sold three units of soap for 2,000 each', 'I sold 5 kilos of sugar at 3,500 to customer Amina'],
                self::TYPE_EXPENSE => ["Record today's transport expense of 5,000", 'I spent 12,000 on electricity today'],
                self::TYPE_CUSTOMER => ['Add customer Amina, phone 0712345678', 'Add customer Juma with email juma@example.com'],
            ],
            'fr' => [
                self::TYPE_PRODUCT => ['Ajouter 20 bouteilles de parfum bleu au stock', 'Ajouter un nouveau produit ciment, 50 sacs, achat 18000, vente 22000'],
                self::TYPE_SALE => ["J'ai vendu trois pièces de savon à 2 000 chacune", "J'ai vendu 5 kilos de sucre à 3 500 au client Amina"],
                self::TYPE_EXPENSE => ["Enregistrer la dépense de transport d'aujourd'hui de 5 000", "J'ai dépensé 12 000 en électricité aujourd'hui"],
                self::TYPE_CUSTOMER => ['Ajouter le client Amina, téléphone 0712345678', 'Ajouter le client Juma avec l\'e-mail juma@example.com'],
            ],
            'hi' => [
                self::TYPE_PRODUCT => ['स्टॉक में 20 बोतल नीली परफ्यूम जोड़ें', 'नया उत्पाद सीमेंट जोड़ें, 50 बोरी, खरीद 18000, बिक्री 22000'],
                self::TYPE_SALE => ['मैंने तीन टुकड़े साबुन 2000 प्रति टुकड़ा बेचे', 'मैंने ग्राहक अमीना को 5 किलो चीनी 3500 में बेची'],
                self::TYPE_EXPENSE => ['आज का 5000 परिवहन खर्च दर्ज करें', 'आज मैंने बिजली पर 12000 खर्च किया'],
                self::TYPE_CUSTOMER => ['ग्राहक अमीना जोड़ें, फ़ोन 0712345678', 'ग्राहक जुमा जोड़ें, ईमेल juma@example.com'],
            ],
            'es' => [
                self::TYPE_PRODUCT => ['Añadir 20 botellas de perfume azul al stock', 'Añadir un producto nuevo cemento, 50 sacos, compra 18000, venta 22000'],
                self::TYPE_SALE => ['Vendí tres piezas de jabón a 2.000 cada una', 'Vendí 5 kilos de azúcar a 3.500 al cliente Amina'],
                self::TYPE_EXPENSE => ['Registrar el gasto de transporte de hoy de 5.000', 'Gasté 12.000 en electricidad hoy'],
                self::TYPE_CUSTOMER => ['Añadir cliente Amina, teléfono 0712345678', 'Añadir cliente Juma con correo juma@example.com'],
            ],
            'ur' => [
                self::TYPE_PRODUCT => ['اسٹاک میں 20 بوتلیں نیلی خوشبو شامل کریں', 'نیا پروڈکٹ سیمنٹ شامل کریں، 50 بوری، خرید 18000، فروخت 22000'],
                self::TYPE_SALE => ['میں نے تین عدد صابن 2000 فی عدد بیچے', 'میں نے گاہک امینہ کو 5 کلو چینی 3500 میں بیچی'],
                self::TYPE_EXPENSE => ['آج کا 5000 سفر خرچ درج کریں', 'آج میں نے بجلی پر 12000 خرچ کیا'],
                self::TYPE_CUSTOMER => ['گاہک امینہ شامل کریں، فون 0712345678', 'گاہک جوما شامل کریں، ای میل juma@example.com'],
            ],
            'de' => [
                self::TYPE_PRODUCT => ['20 Flaschen blaues Parfüm zum Bestand hinzufügen', 'Neues Produkt Zement hinzufügen, 50 Säcke, Einkauf 18000, Verkauf 22000'],
                self::TYPE_SALE => ['Ich habe drei Stück Seife für je 2.000 verkauft', 'Ich habe 5 Kilo Zucker für 3.500 an Kunde Amina verkauft'],
                self::TYPE_EXPENSE => ['Die heutige Transportausgabe von 5.000 erfassen', 'Ich habe heute 12.000 für Strom ausgegeben'],
                self::TYPE_CUSTOMER => ['Kunde Amina hinzufügen, Telefon 0712345678', 'Kunde Juma mit E-Mail juma@example.com hinzufügen'],
            ],
            'zh' => [
                self::TYPE_PRODUCT => ['把 20 瓶蓝色香水加入库存', '新增产品水泥，50 袋，进价 18000，售价 22000'],
                self::TYPE_SALE => ['我以每块 2000 卖了三块肥皂', '我把 5 公斤糖以 3500 卖给了客户 Amina'],
                self::TYPE_EXPENSE => ['记录今天 5000 的交通支出', '我今天在电费上花了 12000'],
                self::TYPE_CUSTOMER => ['添加客户 Amina，电话 0712345678', '添加客户 Juma，邮箱 juma@example.com'],
            ],
        ];

        return $table[$locale] ?? $table['en'];
    }

    /** Honest limitations for the response (8 locales, English fallback). */
    public static function limitations(string $locale): array
    {
        $table = [
            'sw' => [
                'Kiasi, idadi, bei na tarehe vinatoka kwenye maneno yako; kile kisichoeleweka kinaonyeshwa kwa ukaguzi.',
                'Bidhaa zinatambuliwa kwenye katalogi YAKO pekee; jina lisilojulikana haliundwi moja kwa moja.',
                'Hakuna kinachohifadhiwa mpaka uthibitishe; kila kitu kinapita kwenye midundo ya kawaida ya biashara.',
            ],
            'en' => [
                'Amounts, quantities, prices and dates come from your own words; anything unclear is flagged for review.',
                'Products are resolved against YOUR catalogue only; an unknown name is never created silently.',
                'Nothing is saved until you confirm, and the confirmed record goes through the existing business workflow.',
            ],
            'fr' => [
                'Montants, quantités, prix et dates viennent de votre texte ; tout élément flou est signalé.',
                'Les produits sont résolus dans VOTRE catalogue uniquement ; un nom inconnu n\'est jamais créé silencieusement.',
                'Rien n\'est enregistré avant votre confirmation, via le flux habituel.',
            ],
            'hi' => [
                'राशि, मात्रा, मूल्य और तारीखें आपके ही शब्दों से आती हैं; जो अस्पष्ट है वह समीक्षा के लिए चिह्नित होता है।',
                'उत्पाद केवल आपकी सूची में खोजे जाते हैं; अज्ञात नाम चुपचाप नहीं बनाया जाता।',
                'पुष्टि से पहले कुछ भी सहेजा नहीं जाता, और पुष्टि की गई प्रविष्टि सामान्य प्रक्रिया से जाती है।',
            ],
            'es' => [
                'Importes, cantidades, precios y fechas vienen de tus propias palabras; lo dudoso se marca para revisión.',
                'Los productos se buscan SOLO en tu catálogo; un nombre desconocido nunca se crea en silencio.',
                'No se guarda nada hasta que confirmes, y el registro confirmado pasa por el flujo habitual.',
            ],
            'ur' => [
                'رقوم، مقدار، قیمت اور تاریخیں آپ کے اپنے الفاظ سے آتی ہیں؛ جو غیر واضح ہے وہ جائزے کے لیے نشان زد ہوتا ہے۔',
                'پروڈکٹ صرف آپ کی فہرست میں تلاش کیے جاتے ہیں؛ نامعلوم نام خاموشی سے نہیں بنایا جاتا۔',
                'تصدیق سے پہلے کچھ محفوظ نہیں ہوتا، اور تصدیق شدہ ریکارڈ عام طریقہ کار سے جاتا ہے۔',
            ],
            'de' => [
                'Beträge, Mengen, Preise und Daten stammen aus deinen eigenen Worten; Unklares wird zur Prüfung markiert.',
                'Produkte werden nur in DEINEM Katalog gesucht; ein unbekannter Name wird nie still angelegt.',
                'Nichts wird gespeichert, bis du bestätigst, und der bestätigte Datensatz läuft über den normalen Ablauf.',
            ],
            'zh' => [
                '金额、数量、价格与日期来自你自己的话；不清楚之处会被标记以便复核。',
                '产品只会在你自己的目录中查找；未知名称绝不会被静默创建。',
                '在你确认之前不会保存任何内容，确认后的记录会走现有的业务流程。',
            ],
        ];

        return $table[$locale] ?? $table['en'];
    }

    /** The approved category list the expense preview may offer. */
    public static function categories(): array
    {
        return ExpenseCategories::all();
    }
}
