<?php

namespace App\Services\Ai;

use App\Services\ExpenseCategories;

/**
 * Capability #7 - Smart Expense Categorisation.
 *
 * Turns one short free-text (or dictated) expense note into a structured,
 * editable preview: description, amount, currency, date and category.
 *
 * Grounding rules implemented here:
 *  - Amounts and dates are extracted from the seller's OWN words by trusted
 *    code; the model may never introduce a number the text does not contain.
 *  - The category is either a confident keyword match or a model choice from
 *    the fixed allow-list (ExpenseCategories); anything else stays null and
 *    is flagged for the seller to decide.
 *  - Ambiguity is reported, never guessed away: multiple candidate amounts,
 *    unresolvable date formats and uncertain categories all surface as flags.
 *
 * Pure functions only - no database, no HTTP, no Laravel helpers - so the
 * whole extraction contract is unit-testable.
 */
class ExpenseParser
{
    public const CAPABILITY = 'expense_parse';

    /* Flag codes - localized through flagMessage(). */
    public const FLAG_AMOUNT_MISSING = 'amount_missing';
    public const FLAG_AMOUNT_AMBIGUOUS = 'amount_ambiguous';
    public const FLAG_CATEGORY_UNCERTAIN = 'category_uncertain';
    public const FLAG_DATE_AMBIGUOUS = 'date_ambiguous';
    public const FLAG_DATE_NOT_TODAY = 'date_not_today';
    public const FLAG_FUTURE_DATE = 'future_date';

    public const MIN_AMOUNT = 0.01;
    public const MAX_AMOUNT = 999999999999.99;
    public const MAX_TEXT = 600;
    public const MIN_DESCRIPTION = 3;
    public const MAX_DESCRIPTION = 200;

    private const CURRENCY = 'TZS';

    /** Relative day words: [phrase, offset from today in days; -1 = yesterday, +1 = tomorrow]. */
    private const RELATIVE_DAYS = [
        'sw' => [['leo', 0], ['jana', -1], ['juzi', -2], ['kesho', 1]],
        'en' => [['today', 0], ['yesterday', -1], ['tomorrow', 1]],
        'fr' => [["aujourd'hui", 0], ['hier', -1], ['demain', 1]],
        'es' => [['hoy', 0], ['ayer', -1]],
        'de' => [['heute', 0], ['gestern', -1]],
        'hi' => [['आज', 0]],
        'ur' => [['آج', 0]],
        'zh' => [['今天', 0], ['昨天', -1], ['明天', 1]],
    ];

    /**
     * Keyword aliases per canonical category (all lowercase). ASCII aliases
     * match on word boundaries; non-ASCII (Devanagari, Arabic, CJK) match as
     * substrings. The table spans all eight locales plus the loan words
     * sellers commonly mix into Swahili/English notes.
     */
    private const CATEGORY_ALIASES = [
        'Kodisho za Nguvu' => [
            'umeme', 'kodisho', 'electricity', 'power bill', 'stroom', 'luz',
            'electricite', 'bijli', 'بجلی', '电费', 'electric bill',
        ],
        'Maji' => [
            'maji', 'water', 'agua', 'eau', 'wasser', 'पानी', 'پانी', '水费', '水',
        ],
        'Mawasiliano' => [
            'mawasiliano', 'simu', 'intaneti', 'internet', 'airtime', 'data',
            'vodacom', 'airtel', 'tigo', 'halotel', 'afritel', 'phone',
            'telephone', 'telefon', 'telefono', 'फोन', 'फ़ोन', 'فون', 'انٹرنیٹ',
            '电话', '网费', '互联网',
        ],
        'Usafiri' => [
            'usafiri', 'nauli', 'taxi', 'uber', 'bolt', 'bodaboda', 'pikipiki',
            'daladala', 'transport', 'fuel', 'mafuta', 'petroli', 'diesel',
            'petrol', 'gasoline', 'essence', 'benzin', 'gasolina', 'transporte',
            'पेट्रोल', 'پٹرول', '油费', '车费', '交通費',
        ],
        'Chakula cha Mchana' => [
            'chakula', 'lunch', 'food', 'dinner', 'breakfast', 'supper', 'meal',
            'mgahawa', 'restaurant', 'chai', 'kahawa', 'dejeuner', 'almuerzo',
            'comida', 'mittagessen', 'essen', 'भोजन', 'खाना', 'کھانا', '午饭', '餐费',
        ],
        'Vifaa vya Ofisi' => [
            'ofisi', 'office', 'stationery', 'penseli', 'karatasi', 'paper',
            'daftari', 'notebook', 'ink', 'toner', 'printer', 'stapler',
            'fournitures', 'papier', 'papeleria', 'oficina', 'papel', 'buró',
            'kagaz', 'کاغذ', 'دفتری', 'قلم', '办公', '文具',
        ],
        'Matengenezo' => [
            'matengenezo', 'repair', 'repairs', 'maintenance', 'servis',
            'garage', 'reparation', 'reparacion', 'reparatur', 'रिपेयर',
            'मरम्मत', 'مرمت', '维修', '修理',
        ],
        'Ushuru' => [
            'ushuru', 'tax', 'taxes', 'vat', 'levy', 'impot', 'impots',
            'impuesto', 'impuestos', 'steuer', 'steuern', 'टैक्स', 'ٹیکس', '税',
        ],
        'Ada za Benki' => [
            'benki', 'bank', 'atm', 'transfer', 'mpesa', 'm-pesa', 'commission',
            'banque', 'comision', 'gebuhren', 'gebühr', 'बैंक', 'بینک', '银行',
            '手续费',
        ],
        'Malighafi' => [
            'malighafi', 'raw material', 'materials', 'matiere premiere',
            'materia prima', 'rohmaterial', 'rohstoffe', 'कच्चा माल', 'خام مال',
            '原材料',
        ],
        'Ufungashaji' => [
            'ufungashaji', 'packaging', 'crates', 'mifuko', 'sacks',
            'emballage', 'embalaje', 'verpackung', 'पैकेजिंग', 'پیکنگ', '包装',
        ],
        'Mengineyo' => ['mengineyo'],
    ];

    /* ------------------------------------------------------------------ */
    /* Parsing                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Build the editable preview from raw text, optionally folding in a
     * validated model contribution (description tidy-up + category choice).
     *
     * @param  array<string, mixed>|null  $model  Already-sanitized model output.
     * @return array{preview: array<string, mixed>, flags: array<int, string>, missing_required: array<int, string>, amount_candidates: int}
     */
    public static function parse(string $text, string $locale, string $today, ?array $model = null): array
    {
        $text = mb_substr(trim($text), 0, self::MAX_TEXT);

        $amounts = self::extractAmounts($text);
        $chosen = self::chooseAmount($amounts);

        $date = self::extractDate($text, $today, $locale);
        $category = self::matchCategory($text);
        $description = self::cleanDescription($text);

        if ($model !== null) {
            if (($model['description'] ?? null) !== null) {
                $description = $model['description'];
            }
            if ($category === null) {
                $category = $model['category'] ?? null;
            }
        }

        $flags = [];
        if ($chosen['amount'] === null) {
            $flags[] = self::FLAG_AMOUNT_MISSING;
        } elseif ($chosen['ambiguous']) {
            $flags[] = self::FLAG_AMOUNT_AMBIGUOUS;
        }
        if ($category === null) {
            $flags[] = self::FLAG_CATEGORY_UNCERTAIN;
        }
        if ($date['date'] !== null) {
            if ($date['future']) {
                $flags[] = self::FLAG_FUTURE_DATE;
            } elseif ($date['date'] !== $today) {
                $flags[] = self::FLAG_DATE_NOT_TODAY;
            }
        }
        if ($date['ambiguous']) {
            $flags[] = self::FLAG_DATE_AMBIGUOUS;
        }

        $missing = [];
        if ($chosen['amount'] === null) {
            $missing[] = 'amount';
        }
        if (mb_strlen($description) < self::MIN_DESCRIPTION) {
            $missing[] = 'description';
        }
        if ($category === null) {
            $missing[] = 'category';
        }

        return [
            'preview' => [
                'description' => $description,
                'amount' => $chosen['amount'],
                'currency' => self::CURRENCY,
                'expense_date' => $date['date'],
                'category' => $category,
            ],
            'flags' => array_values(array_unique($flags)),
            'missing_required' => $missing,
            'amount_candidates' => count($amounts),
        ];
    }

    /**
     * All plausible amounts in reading order. Date-like and phone-like digit
     * runs are masked first so `2026-01-05` and `0712345678` are never money.
     *
     * @return array<int, array{value: float, raw: string, tagged: bool}>
     */
    public static function extractAmounts(string $text): array
    {
        $masked = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', ' ', $text) ?? $text;
        $masked = preg_replace('/\b\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}\b/', ' ', $masked) ?? $masked;
        $masked = preg_replace('/(?<![\d,.])\d{9,}(?![\d,.])/', ' ', $masked) ?? $masked;

        $matched = preg_match_all(
            '/(?:\b(?:TZS|TSh|Tsh)\s*)?(?:\d{1,3}(?:[ ,]\d{3})+(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)(?:\s*(?:\bTZS\b|\bTSh\b|\bTsh\b|\/=))?/u',
            $masked,
            $m
        );

        $out = [];
        if ($matched === 0 || ! isset($m[0])) {
            return $out;
        }

        foreach ($m[0] as $raw) {
            if (trim($raw) === '') {
                continue;
            }
            $value = self::normalizeAmount($raw);
            if ($value === null) {
                continue;
            }
            $tagged = preg_match('/TZS|TSh|Tsh|\/=/u', $raw) === 1;
            $out[] = ['value' => $value, 'raw' => trim($raw), 'tagged' => $tagged];
        }

        return $out;
    }

    /**
     * Normalize one raw candidate ("20,000 TSh" -> 20000.0) and enforce the
     * amount bounds. Anything non-finite or out of range is rejected.
     */
    public static function normalizeAmount(string $raw): ?float
    {
        // Currency tokens, whitespace AND thousands separators are removed so
        // "20,000 TSh" normalises to 20000 (the match regex only ever emits
        // comma-grouped thousands, so a comma here is always a separator).
        $clean = preg_replace('/(?:TZS|TSh|Tsh|\/\=|[\s,])/u', '', $raw) ?? '';
        if ($clean === '' || preg_match('/^\d{1,15}(\.\d{1,2})?$/', $clean) !== 1) {
            return null;
        }

        $value = (float) $clean;
        if (! is_finite($value) || $value < self::MIN_AMOUNT || $value > self::MAX_AMOUNT) {
            return null;
        }

        return $value;
    }

    /**
     * Pick the amount to preview: currency-tagged candidates win over bare
     * numbers, and bare year-like numbers (1900-2100) are dropped so a year
     * in the note can never become an amount. Genuinely multiple candidates
     * are reported as ambiguous with the first value proposed for editing.
     *
     * @param  array<int, array{value: float, raw: string, tagged: bool}>  $candidates
     * @return array{amount: float|null, ambiguous: bool}
     */
    public static function chooseAmount(array $candidates): array
    {
        if ($candidates === []) {
            return ['amount' => null, 'ambiguous' => false];
        }

        $tagged = array_values(array_filter($candidates, fn ($c) => $c['tagged']));
        $pool = $tagged !== []
            ? $tagged
            : array_values(array_filter($candidates, function ($c) {
                $isYear = trim($c['raw']) !== ''
                    && preg_match('/^\d{4}$/', trim($c['raw'])) === 1
                    && $c['value'] >= 1900
                    && $c['value'] <= 2100;

                return ! $isYear;
            }));

        if ($pool === []) {
            return ['amount' => null, 'ambiguous' => false];
        }

        $values = array_values(array_unique(array_map(fn ($c) => $c['value'], $pool)));
        if (count($values) === 1) {
            return ['amount' => $values[0], 'ambiguous' => false];
        }

        return ['amount' => $values[0], 'ambiguous' => true];
    }

    /**
     * Resolve a concrete expense date from ISO dates and relative day words
     * of the active locale (plus English, since notes mix languages).
     * Slash dates are recognised but deliberately unresolvable (dd/mm vs
     * mm/dd), which is reported as ambiguous instead of guessed.
     *
     * @return array{date: string|null, ambiguous: bool, future: bool}
     */
    public static function extractDate(string $text, string $today, string $locale): array
    {
        $result = ['date' => null, 'ambiguous' => false, 'future' => false];

        $iso = [];
        if (preg_match_all('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m) > 0) {
            foreach ($m[0] as $i => $full) {
                $year = (int) $m[1][$i];
                $month = (int) $m[2][$i];
                $day = (int) $m[3][$i];
                if (checkdate($month, $day, $year)) {
                    $iso[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }
        $iso = array_values(array_unique($iso));

        $working = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', ' ', $text) ?? $text;
        $lower = mb_strtolower($working);

        $relative = null;
        $words = array_merge(self::RELATIVE_DAYS[$locale] ?? [], self::RELATIVE_DAYS['en']);
        foreach ($words as [$phrase, $offset]) {
            if (self::containsPhrase($lower, $phrase)) {
                $base = \DateTimeImmutable::createFromFormat('!Y-m-d', $today, new \DateTimeZone('UTC'));
                if ($base !== false) {
                    $relative = $base->modify(sprintf('%+d days', $offset))->format('Y-m-d');
                }
                break;
            }
        }

        $slash = preg_match('/\b\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}\b/', $working) === 1;

        if ($iso !== []) {
            $result['date'] = $iso[0];
            $result['ambiguous'] = count($iso) > 1 || $relative !== null || $slash;
        } elseif ($relative !== null) {
            $result['date'] = $relative;
            $result['ambiguous'] = $slash;
        } elseif ($slash) {
            $result['ambiguous'] = true;
        }

        if ($result['date'] !== null && $result['date'] > $today) {
            $result['future'] = true;
        }

        return $result;
    }

    /**
     * Confident keyword match against the canonical category list, or null
     * when nothing clearly wins (including exact ties between categories).
     */
    public static function matchCategory(string $text): ?string
    {
        $hay = mb_strtolower($text);

        $best = null;
        $bestScore = 0;
        $bestLen = 0;
        $tied = false;

        foreach (self::CATEGORY_ALIASES as $category => $aliases) {
            if ($aliases === []) {
                continue;
            }

            $score = 0;
            $len = 0;
            foreach ($aliases as $alias) {
                if (self::containsPhrase($hay, $alias)) {
                    $score++;
                    $len = max($len, mb_strlen($alias));
                }
            }

            if ($score === 0) {
                continue;
            }

            if ($score > $bestScore || ($score === $bestScore && $len > $bestLen)) {
                $best = $category;
                $bestScore = $score;
                $bestLen = $len;
                $tied = false;
            } elseif ($score === $bestScore && $len === $bestLen && $category !== $best) {
                $tied = true;
            }
        }

        return ($best === null || $tied) ? null : $best;
    }

    /** Whitespace-normalized, bounded description straight from the note. */
    public static function cleanDescription(string $text): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($clean) > self::MAX_DESCRIPTION) {
            $clean = mb_substr($clean, 0, self::MAX_DESCRIPTION);
        }

        return $clean;
    }

    /**
     * Strictly validate whatever the model returned. Unknown categories,
     * malformed values and empty descriptions all collapse to null instead
     * of being trusted.
     *
     * @param  mixed  $data
     * @return array{description: string|null, category: string|null}|null
     */
    public static function sanitizeModel($data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $description = null;
        if (isset($data['description']) && is_string($data['description'])) {
            $clean = self::cleanDescription($data['description']);
            if (mb_strlen($clean) >= self::MIN_DESCRIPTION) {
                $description = $clean;
            }
        }

        $category = null;
        if (isset($data['category']) && is_string($data['category'])) {
            $candidate = trim($data['category']);
            if (ExpenseCategories::isValid($candidate)) {
                $category = $candidate;
            }
        }

        if ($description === null && $category === null) {
            return null;
        }

        return ['description' => $description, 'category' => $category];
    }

    /* ------------------------------------------------------------------ */
    /* Model contract                                                     */
    /* ------------------------------------------------------------------ */

    public static function systemInstruction(): string
    {
        return 'You tidy one short office-expense note written by a shop owner in any language '
            . '(Swahili, English, French, Hindi, Spanish, Urdu, German or Chinese). '
            . 'Return ONLY a JSON object: {"description": string, "category": string|null}. '
            . 'Rules: '
            . '(1) description is a clean restatement of the note, 3-200 characters, in the SAME language as the note; '
            . 'keep brand names, numbers and identifiers exactly as written and never add facts, amounts, discounts or dates that are not in the note. '
            . '(2) category is EXACTLY one string from the allowed categories list, copied character-for-character, or null when none clearly fits. '
            . '(3) Never invent an amount, product, vendor or promise. '
            . '(4) Output raw JSON only - no markdown, no commentary.';
    }

    public static function userPrompt(string $text, string $today, string $locale): string
    {
        return json_encode([
            'locale' => $locale,
            'today' => $today,
            'allowed_categories' => ExpenseCategories::all(),
            'note' => $text,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /* ------------------------------------------------------------------ */
    /* Localized copy                                                     */
    /* ------------------------------------------------------------------ */

    private const FLAG_MESSAGES = [
        'amount_missing' => [
            'sw' => 'Hakuna kiasi lililotambulika kwenye maandishi. Weka kiasi kwenye fomu.',
            'en' => 'No amount was recognized in the text. Enter the amount in the form.',
            'fr' => "Aucun montant n'a été reconnu dans le texte. Saisissez le montant dans le formulaire.",
            'hi' => 'पाठ में कोई राशि पहचानी नहीं गई। फ़ॉर्म में राशि भरें।',
            'es' => 'No se reconoció ningún importe en el texto. Introduzca el importe en el formulario.',
            'ur' => 'متن میں کویی رقم نہیں پہچانی گئی۔ فارم میں رقم درج کریں۔',
            'de' => 'Im Text wurde kein Betrag erkannt. Geben Sie den Betrag im Formular ein.',
            'zh' => '文本中未识别到金额。请在表单中输入金额。',
        ],
        'amount_ambiguous' => [
            'sw' => 'Kuna namba zaidi ya moja zinazofanana na kiasi - kiasi cha kwanza kimependekezwa, kihakikishe.',
            'en' => 'More than one amount was found - the first is proposed, please check it.',
            'fr' => 'Plusieurs montants ont été trouvés - le premier est proposé, veuillez vérifier.',
            'hi' => 'एक से अधिक राशि मिली - पहली प्रस्तावित है, कृपया जाँच करें।',
            'es' => 'Se encontraron varios importes - se propone el primero, compruébelo.',
            'ur' => 'ایک سے زیادہ رقم ملی - پہلی پیش کی گئی ہے، براہ کرم چیک کریں۔',
            'de' => 'Mehrere Beträge gefunden - der erste wird vorgeschlagen, bitte prüfen.',
            'zh' => '找到多个金额——已选用第一个，请核对。',
        ],
        'category_uncertain' => [
            'sw' => 'Aina ya matumizi haijakaulika kwa uhakika - chagua kwenye orodha.',
            'en' => 'The category is not certain - pick one from the list.',
            'fr' => "La catégorie n'est pas certaine - choisissez-en une dans la liste.",
            'hi' => 'श्रेणी निश्चित नहीं है - सूची से एक चुनें।',
            'es' => 'La categoría no es segura - elija una de la lista.',
            'ur' => 'زمرہ یقینی نہیں ہے - فہرست سے ایک منتخب کریں۔',
            'de' => 'Die Kategorie ist nicht sicher - wählen Sie eine aus der Liste.',
            'zh' => '类别不确定——请从列表中选择一个。',
        ],
        'date_ambiguous' => [
            'sw' => 'Tarehe haijaambiliwa wazi (mfano dd/mm) - hakikisha tarehe sahihi.',
            'en' => 'The date is ambiguous (e.g. dd/mm) - confirm the right date.',
            'fr' => 'La date est ambiguë (ex. jj/mm) - confirmez la date correcte.',
            'hi' => 'तारीख स्पष्ट नहीं है (जैसे dd/mm) - सही तारीख की पुष्टि करें।',
            'es' => 'La fecha es ambigua (p. ej. dd/mm) - confirme la fecha correcta.',
            'ur' => 'تاریخ واضح نہیں (مثلاً dd/mm) - درست تاریخ کی تصدیق کریں۔',
            'de' => 'Das Datum ist mehrdeutig (z. B. TT/MM) - bestätigen Sie das richtige Datum.',
            'zh' => '日期不明确（如 dd/mm）——请确认正确日期。',
        ],
        'date_not_today' => [
            'sw' => 'Tarehe iliyotajwa si ya leo; kwenye hii mfumo itarekodiwa tarehe ya leo ({date}).',
            'en' => 'The stated date is not today; this system records the expense under today ({date}).',
            'fr' => "La date indiquée n'est pas aujourd'hui ; le système enregistre sous aujourd'hui ({date}).",
            'hi' => 'बताई गई तारीख आज नहीं है; सिस्टम आज ({date}) के अंतर्गत दर्ज करेगा।',
            'es' => 'La fecha indicada no es hoy; el sistema la registra bajo hoy ({date}).',
            'ur' => 'بتائی گئی تاریخ آج نہیں ہے؛ سسٹم آج ({date}) کے تحت ریکارڈ کرے گا۔',
            'de' => 'Das angegebene Datum ist nicht heute; das System speichert es unter heute ({date}).',
            'zh' => '所述日期不是今天；系统将按今天（{date}）记录。',
        ],
        'future_date' => [
            'sw' => 'Tarehe ni baadaye ya leo ({date}) - hakikisha ni sahihi.',
            'en' => 'The date is in the future ({date}) - please confirm it is correct.',
            'fr' => 'La date est dans le futur ({date}) - veuillez confirmer.',
            'hi' => 'तारीख भविष्य में है ({date}) - कृपया पुष्टि करें।',
            'es' => 'La fecha es futura ({date}) - confirme que es correcta.',
            'ur' => 'تاریخ مستقبل میں ہے ({date}) - براہ کرم تصدیق کریں۔',
            'de' => 'Das Datum liegt in der Zukunft ({date}) - bitte bestätigen.',
            'zh' => '日期在未来（{date}）——请确认是否正确。',
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

    /** Honest limitation lines for the response (8 locales, English fallback). */
    public static function limitations(string $locale): array
    {
        $table = [
            'sw' => [
                'Kiasi na tarehe vinatokana na maandishi yako; kile hakichoambiliwa kinaonyeshwa kwa ukaguzi.',
                'Aina ya matumizi ni pendekezo kutoka kwenye orodha iliyoidhinishwa; wewe huamua mwishoni.',
                'Mfumo hurekodi matumizi kwenye tarehe ya leo kama ilivyokuwa midundo ya sasa.',
            ],
            'en' => [
                'Amounts and dates come from your own text; anything unclear is flagged for review.',
                'The category is a suggestion drawn from the approved list; you make the final choice.',
                "The system records expenses under today's date, matching the existing workflow.",
            ],
            'fr' => [
                'Les montants et dates viennent de votre texte ; tout élément flou est signalé.',
                'La catégorie est une suggestion tirée de la liste approuvée ; vous décidez.',
                'Le système enregistre sous la date du jour, comme le prévoit le flux existant.',
            ],
            'hi' => [
                'राशि और तारीख आपके अपने पाठ से आती हैं; अस्पष्ट बातें समीक्षा के लिए चिह्नित होती हैं।',
                'श्रेणी सूची में से एक सुझाव है; अंतिम निर्णय आपका होगा।',
                'सिस्टम आज की तारीख के अंतर्गत दर्ज करता है, जैसा कि मौजूदा प्रवाह है।',
            ],
            'es' => [
                'Los importes y fechas salen de su propio texto; lo ambiguo se marca para revisión.',
                'La categoría es una sugerencia de la lista aprobada; la decisión final es suya.',
                'El sistema registra bajo la fecha de hoy, según el flujo existente.',
            ],
            'ur' => [
                'رقم اور تاریخ آپ کے اپنے متن سے آتی ہیں؛ غیر واضح باتیں جانچ کے لیے نشان زد ہوتی ہیں۔',
                'زمرہ منظور شدہ فہرست کا ایک تجویز ہے؛ حتمی فیصلہ آپ کا ہوگا۔',
                'سسٹم موجودہ کاروبار کے مطابق آج کی تاریخ کے تحت ریکارڈ کرتا ہے۔',
            ],
            'de' => [
                'Beträge und Daten stammen aus Ihrem Text; Unklares wird zur Prüfung markiert.',
                'Die Kategorie ist ein Vorschlag aus der freigegebenen Liste; Sie entscheiden.',
                'Das System speichert unter dem heutigen Datum, wie im bestehenden Ablauf.',
            ],
            'zh' => [
                '金额和日期来自您自己的文本；不清楚的内容会标记待核对。',
                '类别取自已批准列表的建议；最终由您决定。',
                '系统按今天日期记录，与现有流程一致。',
            ],
        ];

        return $table[$locale] ?? $table['en'];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Word-boundary match for ASCII phrases (case-insensitive) and plain
     * substring match for scripts without word boundaries.
     */
    private static function containsPhrase(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        if (preg_match('/^[\x20-\x7e]+$/', $needle) === 1) {
            return preg_match('/\b'.preg_quote($needle, '/').'\b/iu', $haystack) === 1;
        }

        return mb_strpos(mb_strtolower($haystack), mb_strtolower($needle)) !== false;
    }
}
