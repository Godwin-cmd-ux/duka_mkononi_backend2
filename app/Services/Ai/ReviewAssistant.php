<?php

namespace App\Services\Ai;

/**
 * Capability #16 - Advertisement & Content Review Assistant (deterministic
 * core + the gate for the OPTIONAL advisory classification).
 *
 * This is a MODERATION AID, never an enforcement authority:
 *  - deterministic checks produce the findings (objective requirements only:
 *    missing mandatory fields, out-of-stock listings, configurable policy
 *    terms, price anomalies);
 *  - every finding carries a category, an explanation, a severity and the
 *    relevant text so a human can verify it in one glance;
 *  - severity separates a DEFINITE violation (objective, checkable fact)
 *    from an UNCERTAIN concern (e.g. an unverified claim keyword);
 *  - claim keyword hits are ALWAYS concerns - only a human decides;
 *  - the model may at most CLASSIFY flagged excerpts as advisory context
 *    (supported / unsupported / uncertain); its verdicts can never create,
 *    upgrade or remove a finding, and no approve/reject decision is ever
 *    taken from it. The decision endpoint re-derives the findings on the
 *    server and records the human decision + audit trail.
 *
 * All public methods are pure (arrays in, arrays out) so the whole contract
 * is unit-testable without HTTP, a database or Laravel helpers.
 */
class ReviewAssistant
{
    public const CAPABILITY = 'review_assistant';

    /** A violation is objective and checkable; a concern is uncertain. */
    public const SEVERITY_VIOLATION = 'violation';
    public const SEVERITY_CONCERN = 'concern';

    public const CATEGORIES = ['incomplete', 'price', 'claim', 'prohibited', 'policy'];

    /** Allowed verdicts of the ADVISORY classification (never a decision). */
    public const VERDICTS = ['supported', 'unsupported', 'uncertain'];

    /**
     * Unverified-claim keywords. A hit is ALWAYS a concern (uncertain): the
     * phrase may be true, harmless puffery or fine print - only a human can
     * tell, so the assistant flags it and stops here.
     */
    public const CLAIM_TERMS = [
        '100%', 'bora zaidi', 'best', 'guaranteed', 'daima', 'always',
        'rahisi zaidi', 'cheapest', 'nzuri zaidi',
    ];

    /** Marketplace policy terms - configurable per marketplace in config/ai.php. */
    public const PROHIBITED_TERMS = [
        'silaha', 'dawa za kulevya', 'bangi', 'stolen', 'counterfeit',
        'fake', 'replica', 'miracle cure',
    ];

    /**
     * Every message must exist for all eight locales (unit-tested), and every
     * locale must carry exactly the same placeholders.
     */
    public const MESSAGES = [
        'sw' => [
            'ad_desc_missing' => 'Matangazo hayana maelezo.',
            'ad_desc_short' => 'Maelezo ni mafupi kuliko herufi {min}.',
            'ad_media_missing' => 'Matangazo hayana picha wala video.',
            'ad_title_long' => 'Kichwa kirefu kuliko herufi {max}.',
            'ad_expired' => 'Matangazo haya yameisha muda lakini bado yanaweza kuonekana.',
            'p_name_missing' => 'Bidhaa haijajazwa jina.',
            'p_category_missing' => 'Bidhaa haijachaguliwa kategoria.',
            'p_desc_missing' => 'Bidhaa haina maelezo.',
            'p_desc_short' => 'Maelezo ya bidhaa ni mafupi kuliko herufi {min}.',
            'p_image_missing' => 'Bidhaa haina picha.',
            'p_price_missing' => 'Bei ya kununua haijajazwa au si sahihi.',
            'p_selling_missing' => 'Bei ya kuuzia haijajazwa.',
            'p_loss_price' => 'Bei ya kuuzia ({selling}) iko chini ya bei ya kununua ({buying}).',
            'p_high_markup' => 'Bei ya kuuzia ni zaidi ya mara {multiplier} ya bei ya kununua.',
            'p_out_of_stock' => 'Hisa ni 0 lakini bidhaa bado inaonekana inauzwa.',
            'prohibited_hit' => 'Neno lilizuiwa "{term}" linaonekana kwenye maandishi.',
            'claim_hit' => 'Dai lisilo na uthibitisho: "{term}". Hakiki kwa mkono.',
            'contact_number' => 'Namba ya simu inaonekana kwenye maandishi; mawasiliano hubaki kwenye jukwaa.',
            'lim_advisory' => 'AI ni msaada tu: kila idhini au kuzuia ni uamuzi wa mwanadamu uliorekodiwa.',
            'err_review_required' => 'Thibitisha kuumeona mabao kabla ya kuamua.',
            'err_note_required' => 'Andika sababu: kuna mabao ya uhakika (violations) kwenye hii.',
            'err_unknown_target' => 'Bidhaa au matangazo hayapatikani kwenye biashara yako.',
        ],
        'en' => [
            'ad_desc_missing' => 'The advertisement has no description.',
            'ad_desc_short' => 'The description is shorter than {min} characters.',
            'ad_media_missing' => 'The advertisement has no photo or video.',
            'ad_title_long' => 'The title is longer than {max} characters.',
            'ad_expired' => 'This advertisement has expired but may still be visible.',
            'p_name_missing' => 'The product has no name.',
            'p_category_missing' => 'The product has no category.',
            'p_desc_missing' => 'The product has no description.',
            'p_desc_short' => 'The product description is shorter than {min} characters.',
            'p_image_missing' => 'The product has no photo.',
            'p_price_missing' => 'The buying price is missing or not valid.',
            'p_selling_missing' => 'The selling price is missing.',
            'p_loss_price' => 'The selling price ({selling}) is below the buying price ({buying}).',
            'p_high_markup' => 'The selling price is more than {multiplier} times the buying price.',
            'p_out_of_stock' => 'Stock is 0 but the product is still listed as available.',
            'prohibited_hit' => 'The prohibited term "{term}" appears in the text.',
            'claim_hit' => 'Unverified claim: "{term}". Verify it by hand.',
            'contact_number' => 'A phone number appears in the text; contact stays on the platform.',
            'lim_advisory' => 'AI is advisory only: every approve or reject is a recorded human decision.',
            'err_review_required' => 'Confirm you have read the findings before deciding.',
            'err_note_required' => 'Write a reason: there are definite violations on this item.',
            'err_unknown_target' => 'That product or advertisement was not found in your business.',
        ],
        'fr' => [
            'ad_desc_missing' => "La publicité n'a pas de description.",
            'ad_desc_short' => 'La description est plus courte que {min} caractères.',
            'ad_media_missing' => "La publicité n'a ni photo ni vidéo.",
            'ad_title_long' => 'Le titre dépasse {max} caractères.',
            'ad_expired' => 'Cette publicité a expiré mais peut encore être visible.',
            'p_name_missing' => "Le produit n'a pas de nom.",
            'p_category_missing' => "Le produit n'a pas de catégorie.",
            'p_desc_missing' => "Le produit n'a pas de description.",
            'p_desc_short' => 'La description du produit est plus courte que {min} caractères.',
            'p_image_missing' => "Le produit n'a pas de photo.",
            'p_price_missing' => "Le prix d'achat est manquant ou invalide.",
            'p_selling_missing' => 'Le prix de vente est manquant.',
            'p_loss_price' => 'Le prix de vente ({selling}) est inférieur au prix d\'achat ({buying}).',
            'p_high_markup' => "Le prix de vente dépasse {multiplier} fois le prix d'achat.",
            'p_out_of_stock' => 'Le stock est à 0 mais le produit est toujours présenté comme disponible.',
            'prohibited_hit' => 'Le terme interdit « {term} » apparaît dans le texte.',
            'claim_hit' => 'Affirmation non vérifiée : « {term} ». Vérifiez-la manuellement.',
            'contact_number' => 'Un numéro de téléphone apparaît dans le texte ; le contact reste sur la plateforme.',
            'lim_advisory' => "L'IA est un simple aide : toute approbation ou rejection est une décision humaine enregistrée.",
            'err_review_required' => 'Confirmez avoir lu les constatations avant de décider.',
            'err_note_required' => 'Indiquez une raison : il y a des violations certaines sur cet élément.',
            'err_unknown_target' => 'Ce produit ou cette publicité est introuvable dans votre entreprise.',
        ],
        'es' => [
            'ad_desc_missing' => 'El anuncio no tiene descripción.',
            'ad_desc_short' => 'La descripción tiene menos de {min} caracteres.',
            'ad_media_missing' => 'El anuncio no tiene foto ni video.',
            'ad_title_long' => 'El título supera los {max} caracteres.',
            'ad_expired' => 'Este anuncio ha expirado pero puede seguir visible.',
            'p_name_missing' => 'El producto no tiene nombre.',
            'p_category_missing' => 'El producto no tiene categoría.',
            'p_desc_missing' => 'El producto no tiene descripción.',
            'p_desc_short' => 'La descripción del producto tiene menos de {min} caracteres.',
            'p_image_missing' => 'El producto no tiene foto.',
            'p_price_missing' => 'Falta el precio de compra o no es válido.',
            'p_selling_missing' => 'Falta el precio de venta.',
            'p_loss_price' => 'El precio de venta ({selling}) está por debajo del precio de compra ({buying}).',
            'p_high_markup' => 'El precio de venta supera {multiplier} veces el precio de compra.',
            'p_out_of_stock' => 'El stock es 0 pero el producto sigue listado como disponible.',
            'prohibited_hit' => 'El término prohibido "{term}" aparece en el texto.',
            'claim_hit' => 'Afirmación sin verificar: "{term}". Verifícala manualmente.',
            'contact_number' => 'Aparece un número de teléfono en el texto; el contacto se mantiene en la plataforma.',
            'lim_advisory' => 'La IA es solo orientación: cada aprobación o rechazo es una decisión humana registrada.',
            'err_review_required' => 'Confirma que leíste los hallazgos antes de decidir.',
            'err_note_required' => 'Escribe un motivo: hay violaciones definidas en este elemento.',
            'err_unknown_target' => 'Ese producto o anuncio no se encontró en tu negocio.',
        ],
        'zh' => [
            'ad_desc_missing' => '该广告没有描述。',
            'ad_desc_short' => '描述少于 {min} 个字符。',
            'ad_media_missing' => '该广告没有图片或视频。',
            'ad_title_long' => '标题超过 {max} 个字符。',
            'ad_expired' => '该广告已过期，但仍可能显示。',
            'p_name_missing' => '该产品没有名称。',
            'p_category_missing' => '该产品没有分类。',
            'p_desc_missing' => '该产品没有描述。',
            'p_desc_short' => '产品描述少于 {min} 个字符。',
            'p_image_missing' => '该产品没有图片。',
            'p_price_missing' => '买入价缺失或无效。',
            'p_selling_missing' => '卖出价缺失。',
            'p_loss_price' => '卖出价（{selling}）低于买入价（{buying}）。',
            'p_high_markup' => '卖出价超过买入价的 {multiplier} 倍。',
            'p_out_of_stock' => '库存为 0，但产品仍列为可售。',
            'prohibited_hit' => '文本中出现禁用词「{term}」。',
            'claim_hit' => '未经证实的说法：「{term}」。请人工核实。',
            'contact_number' => '文本中出现电话号码；联系应留在平台内。',
            'lim_advisory' => 'AI 仅作辅助：每次通过或拒绝都是已记录的人工决定。',
            'err_review_required' => '请先确认你已阅读检查结果再做决定。',
            'err_note_required' => '请填写理由：此内容存在明确违规。',
            'err_unknown_target' => '在你的商家中未找到该产品或广告。',
        ],
        'hi' => [
            'ad_desc_missing' => 'विज्ञापन में विवरण नहीं है।',
            'ad_desc_short' => 'विवरण {min} अक्षरों से कम है।',
            'ad_media_missing' => 'विज्ञापन में फोटो या वीडियो नहीं है।',
            'ad_title_long' => 'शीर्षक {max} अक्षरों से अधिक है।',
            'ad_expired' => 'यह विज्ञापन समाप्त हो चुका है लेकिन अभी भी दिखाई दे सकता है।',
            'p_name_missing' => 'उत्पाद का नाम नहीं है।',
            'p_category_missing' => 'उत्पाद की श्रेणी नहीं है।',
            'p_desc_missing' => 'उत्पाद का विवरण नहीं है।',
            'p_desc_short' => 'उत्पाद विवरण {min} अक्षरों से कम है।',
            'p_image_missing' => 'उत्पाद की फोटो नहीं है।',
            'p_price_missing' => 'खरीद मूल्य अनुपलब्ध या अमान्य है।',
            'p_selling_missing' => 'बिक्री मूल्य अनुपलब्ध है।',
            'p_loss_price' => 'बिक्री मूल्य ({selling}) खरीद मूल्य ({buying}) से कम है।',
            'p_high_markup' => 'बिक्री मूल्य खरीद मूल्य के {multiplier} गुने से अधिक है।',
            'p_out_of_stock' => 'स्टॉक 0 है लेकिन उत्पाद अभी भी उपलब्ध सूचीबद्ध है।',
            'prohibited_hit' => 'पाठ में निषिद्ध शब्द "{term}" मिला।',
            'claim_hit' => 'असत्यापित दावा: "{term}"। इसे स्वयं जाँचें।',
            'contact_number' => 'पाठ में फ़ोन नंबर है; संपर्क प्लेटफ़ॉर्म पर ही रहता है।',
            'lim_advisory' => 'AI केवल सुझाव देता है: हर स्वीकृति या अस्वीकृति दर्ज मानवीय निर्णय है।',
            'err_review_required' => 'निर्णय लेने से पहले पुष्टि करें कि आपने निष्कर्ष पढ़ लिए हैं।',
            'err_note_required' => 'कारण लिखें: इस पर निश्चित उल्लंघन मौजूद हैं।',
            'err_unknown_target' => 'वह उत्पाद या विज्ञापन आपके व्यवसाय में नहीं मिला।',
        ],
        'ur' => [
            'ad_desc_missing' => 'اشتہار میں تفصیل نہیں ہے۔',
            'ad_desc_short' => 'تفصیل {min} حروف سے کم ہے۔',
            'ad_media_missing' => 'اشتہار میں تصویر یا ویڈیو نہیں ہے۔',
            'ad_title_long' => 'عنوان {max} حروف سے زیادہ ہے۔',
            'ad_expired' => 'یہ اشتہار ختم ہو چکا ہے لیکن اب بھی نظر آ سکتا ہے۔',
            'p_name_missing' => 'مصنوعات کا نام نہیں ہے۔',
            'p_category_missing' => 'مصنوعات کی زمرہ جات نہیں ہے۔',
            'p_desc_missing' => 'مصنوعات کی تفصیل نہیں ہے۔',
            'p_desc_short' => 'مصنوعات کی تفصیل {min} حروف سے کم ہے۔',
            'p_image_missing' => 'مصنوعات کی تصویر نہیں ہے۔',
            'p_price_missing' => 'خریداری قیمت غائب یا غیر درست ہے۔',
            'p_selling_missing' => 'فروخت قیمت غائب ہے۔',
            'p_loss_price' => 'فروخت قیمت ({selling}) خریداری قیمت ({buying}) سے کم ہے۔',
            'p_high_markup' => 'فروخت قیمت خریداری قیمت کے {multiplier} گنا سے زیادہ ہے۔',
            'p_out_of_stock' => 'اسٹاک 0 ہے لیکن مصنوعات اب بھی دستیاب list ہے۔',
            'prohibited_hit' => 'متن میں ممنوع لفظ "{term}" ملا ہے۔',
            'claim_hit' => 'غیر تصدیق شدہ دعویٰ: "{term}"۔ خود جانچیں۔',
            'contact_number' => 'متن میں فون نمبر ہے؛ رابطہ پلیٹ فارم پر ہی رہتا ہے۔',
            'lim_advisory' => 'AI صرف رہنمائی ہے: ہر منظوری یا ردی درج شدہ انسانی فیصلہ ہے۔',
            'err_review_required' => 'فیصلے سے پہلے تصدیق کریں کہ آپ نے نتائج پڑھے ہیں۔',
            'err_note_required' => 'وجہ لکھیں: اس پر یقینی خلاف ورزیاں موجود ہیں۔',
            'err_unknown_target' => 'وہ مصنوعات یا اشتہار آپ کے کاروبار میں نہیں ملا۔',
        ],
        'de' => [
            'ad_desc_missing' => 'Die Anzeige hat keine Beschreibung.',
            'ad_desc_short' => 'Die Beschreibung ist kürzer als {min} Zeichen.',
            'ad_media_missing' => 'Die Anzeige hat kein Foto und kein Video.',
            'ad_title_long' => 'Der Titel ist länger als {max} Zeichen.',
            'ad_expired' => 'Diese Anzeige ist abgelaufen, kann aber noch sichtbar sein.',
            'p_name_missing' => 'Das Produkt hat keinen Namen.',
            'p_category_missing' => 'Das Produkt hat keine Kategorie.',
            'p_desc_missing' => 'Das Produkt hat keine Beschreibung.',
            'p_desc_short' => 'Die Produktbeschreibung ist kürzer als {min} Zeichen.',
            'p_image_missing' => 'Das Produkt hat kein Foto.',
            'p_price_missing' => 'Der Einkaufspreis fehlt oder ist ungültig.',
            'p_selling_missing' => 'Der Verkaufspreis fehlt.',
            'p_loss_price' => 'Der Verkaufspreis ({selling}) liegt unter dem Einkaufspreis ({buying}).',
            'p_high_markup' => 'Der Verkaufspreis übersteigt das {multiplier}-fache des Einkaufspreises.',
            'p_out_of_stock' => 'Der Bestand ist 0, das Produkt wird aber noch als verfügbar geführt.',
            'prohibited_hit' => 'Das verbotene Wort „{term}“ erscheint im Text.',
            'claim_hit' => 'Unbelegte Behauptung: „{term}“. Bitte selbst prüfen.',
            'contact_number' => 'Eine Telefonnummer erscheint im Text; der Kontakt bleibt auf der Plattform.',
            'lim_advisory' => 'Die KI ist nur eine Beratung: Jede Freigabe oder Ablehnung ist eine dokumentierte menschliche Entscheidung.',
            'err_review_required' => 'Bestätigen Sie, dass Sie die Befunde gelesen haben, bevor Sie entscheiden.',
            'err_note_required' => 'Nennen Sie einen Grund: Es gibt eindeutige Verstöße.',
            'err_unknown_target' => 'Dieses Produkt oder diese Anzeige wurde in Ihrem Geschäft nicht gefunden.',
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
    /* Policy                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * The marketplace policy the deterministic checks run against. The
     * controller merges config('ai.reviews') over these defaults; the
     * service itself never reads config so it stays pure and testable.
     */
    public static function defaultPolicy(): array
    {
        return [
            'min_description_length' => 20,
            'max_title_length' => 80,
            'max_price_multiplier' => 20.0,
            'block_contact_details' => true,
            'prohibited_terms' => self::PROHIBITED_TERMS,
        ];
    }

    /** Defaults merged with a caller-supplied policy. */
    public static function policy(array $overrides = []): array
    {
        $policy = array_merge(self::defaultPolicy(), array_filter(
            $overrides,
            static fn ($value) => $value !== null
        ));

        $policy['min_description_length'] = max(1, (int) $policy['min_description_length']);
        $policy['max_title_length'] = max(10, (int) $policy['max_title_length']);
        $policy['max_price_multiplier'] = max(1.0, (float) $policy['max_price_multiplier']);
        $policy['block_contact_details'] = (bool) $policy['block_contact_details'];
        $policy['prohibited_terms'] = array_values(array_filter(array_map(
            static fn ($term) => mb_strtolower(trim((string) $term)),
            (array) $policy['prohibited_terms']
        )));

        return $policy;
    }

    /* ------------------------------------------------------------------ */
    /* Deterministic checks                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Structured findings for one advertisement row. Objective requirements
     * only - a hit is a fact a human can verify against the excerpt.
     *
     * @return array<int, array{category: string, severity: string, field: string, excerpt: string, message: string}>
     */
    public static function reviewAd(array $ad, string $locale = 'en', array $policyOverrides = []): array
    {
        $policy = self::policy($policyOverrides);
        $findings = [];

        $title = trim((string) ($ad['title'] ?? ''));
        $description = trim((string) ($ad['description'] ?? ''));
        $mediaUrl = trim((string) ($ad['media_url'] ?? ''));
        $isActive = (bool) ($ad['is_active'] ?? false);
        $isFree = (bool) ($ad['is_free'] ?? false);
        $paymentStatus = (string) ($ad['payment_status'] ?? '');

        /* incomplete - objective, checkable facts */
        if ($description === '') {
            $findings[] = self::finding('incomplete', self::SEVERITY_VIOLATION, 'description', '', 'ad_desc_missing', $locale);
        } elseif (mb_strlen($description) < $policy['min_description_length']) {
            $findings[] = self::finding('incomplete', self::SEVERITY_CONCERN, 'description', mb_substr($description, 0, 120), 'ad_desc_short', $locale, [
                'min' => $policy['min_description_length'],
            ]);
        }

        if ($mediaUrl === '') {
            $findings[] = self::finding('incomplete', self::SEVERITY_VIOLATION, 'media_url', '', 'ad_media_missing', $locale);
        }

        if ($title !== '' && mb_strlen($title) > $policy['max_title_length']) {
            $findings[] = self::finding('incomplete', self::SEVERITY_CONCERN, 'title', mb_substr($title, 0, 120), 'ad_title_long', $locale, [
                'max' => $policy['max_title_length'],
            ]);
        }

        /* prohibited - the configured marketplace policy terms */
        $text = $title.' '.$description;
        foreach ($policy['prohibited_terms'] as $term) {
            $hit = self::phraseHit($text, $term);
            if ($hit !== null) {
                $findings[] = self::finding('prohibited', self::SEVERITY_VIOLATION, 'description', $hit, 'prohibited_hit', $locale, [
                    'term' => $term,
                ]);
            }
        }

        /* off-platform contact attempts */
        if ($policy['block_contact_details']) {
            $hit = self::phoneHit($description);
            if ($hit !== null) {
                $findings[] = self::finding('policy', self::SEVERITY_VIOLATION, 'description', $hit, 'contact_number', $locale);
            }
        }

        /* unsupported claims - ALWAYS uncertain concerns */
        foreach (self::CLAIM_TERMS as $term) {
            $hit = self::phraseHit($text, $term);
            if ($hit !== null) {
                $findings[] = self::finding('claim', self::SEVERITY_CONCERN, 'description', $hit, 'claim_hit', $locale, [
                    'term' => $term,
                ]);
            }
        }

        /* stale: a paid ad past its expiry that is still active */
        if ($isActive && ! $isFree && $paymentStatus !== 'free') {
            $expiresAt = trim((string) ($ad['expires_at'] ?? ''));
            if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
                $findings[] = self::finding('policy', self::SEVERITY_CONCERN, 'expires_at', $expiresAt, 'ad_expired', $locale);
            }
        }

        return $findings;
    }

    /**
     * Structured findings for one product listing row.
     *
     * @return array<int, array{category: string, severity: string, field: string, excerpt: string, message: string}>
     */
    public static function reviewProduct(array $product, string $locale = 'en', array $policyOverrides = []): array
    {
        $policy = self::policy($policyOverrides);
        $findings = [];

        $name = trim((string) ($product['name'] ?? ''));
        $category = trim((string) ($product['category'] ?? ''));
        $description = trim((string) ($product['description'] ?? ''));
        $imageUrl = trim((string) ($product['image_url'] ?? ''));
        $isActive = array_key_exists('is_active', $product) ? (bool) $product['is_active'] : true;

        $buying = self::positiveNumber($product['price'] ?? null);
        $selling = self::positiveNumber($product['expected_selling_price'] ?? null);
        $stock = is_numeric($product['stock'] ?? null) ? (int) $product['stock'] : null;

        /* incomplete - objective, checkable facts */
        if ($name === '') {
            $findings[] = self::finding('incomplete', self::SEVERITY_VIOLATION, 'name', '', 'p_name_missing', $locale);
        }
        if ($category === '') {
            $findings[] = self::finding('incomplete', self::SEVERITY_VIOLATION, 'category', '', 'p_category_missing', $locale);
        }
        if ($description === '') {
            $findings[] = self::finding('incomplete', self::SEVERITY_CONCERN, 'description', '', 'p_desc_missing', $locale);
        } elseif (mb_strlen($description) < $policy['min_description_length']) {
            $findings[] = self::finding('incomplete', self::SEVERITY_CONCERN, 'description', mb_substr($description, 0, 120), 'p_desc_short', $locale, [
                'min' => $policy['min_description_length'],
            ]);
        }
        if ($imageUrl === '') {
            $findings[] = self::finding('incomplete', self::SEVERITY_CONCERN, 'image_url', '', 'p_image_missing', $locale);
        }

        /* price anomalies - misleading prices are concerns, never verdicts */
        if ($buying === null) {
            $findings[] = self::finding('price', self::SEVERITY_VIOLATION, 'price', (string) ($product['price'] ?? ''), 'p_price_missing', $locale);
        }
        if ($selling === null) {
            $findings[] = self::finding('incomplete', self::SEVERITY_CONCERN, 'expected_selling_price', '', 'p_selling_missing', $locale);
        }
        if ($buying !== null && $selling !== null) {
            if ($selling < $buying) {
                $findings[] = self::finding('price', self::SEVERITY_CONCERN, 'expected_selling_price', $selling.' < '.$buying, 'p_loss_price', $locale, [
                    'selling' => $selling,
                    'buying' => $buying,
                ]);
            } elseif ($buying > 0 && $selling > $buying * $policy['max_price_multiplier']) {
                $findings[] = self::finding('price', self::SEVERITY_CONCERN, 'expected_selling_price', $selling.' > '.$buying, 'p_high_markup', $locale, [
                    // '20.00' -> '20', '15.50' -> '15.5' - never eat a
                    // significant digit of a whole number (rtrim would).
                    'multiplier' => rtrim(rtrim(number_format((float) $policy['max_price_multiplier'], 2, '.', ''), '0'), '.'),
                    'selling' => $selling,
                    'buying' => $buying,
                ]);
            }
        }

        /* availability: an active listing with no stock misleads buyers */
        if ($isActive && $stock !== null && $stock <= 0) {
            $findings[] = self::finding('policy', self::SEVERITY_VIOLATION, 'stock', (string) $stock, 'p_out_of_stock', $locale);
        }

        /* policy terms and claims apply to product text too */
        if ($policy['block_contact_details']) {
            $hit = self::phoneHit($description);
            if ($hit !== null) {
                $findings[] = self::finding('policy', self::SEVERITY_VIOLATION, 'description', $hit, 'contact_number', $locale);
            }
        }

        foreach ($policy['prohibited_terms'] as $term) {
            $hit = self::phraseHit($description, $term);
            if ($hit !== null) {
                $findings[] = self::finding('prohibited', self::SEVERITY_VIOLATION, 'description', $hit, 'prohibited_hit', $locale, [
                    'term' => $term,
                ]);
            }
        }

        foreach (self::CLAIM_TERMS as $term) {
            $hit = self::phraseHit($description, $term);
            if ($hit !== null) {
                $findings[] = self::finding('claim', self::SEVERITY_CONCERN, 'description', $hit, 'claim_hit', $locale, [
                    'term' => $term,
                ]);
            }
        }

        return $findings;
    }

    /** Counts a mixed set of findings (used for stats and gates). */
    public static function countBySeverity(array $findings): array
    {
        $counts = [self::SEVERITY_VIOLATION => 0, self::SEVERITY_CONCERN => 0];
        foreach ($findings as $finding) {
            $severity = (string) ($finding['severity'] ?? '');
            if (isset($counts[$severity])) {
                $counts[$severity]++;
            }
        }

        return $counts;
    }

    /* ------------------------------------------------------------------ */
    /* The advisory classification (optional, never a decision)            */
    /* ------------------------------------------------------------------ */

    /**
     * The claim excerpts the model may look at: bounded text, each with a
     * stable id so its verdict can be mapped back to the exact finding.
     *
     * @param  array<int, array{type: string, title: string, findings: array}>  $items
     * @return array<int, array{id: string, ref: array{item: int, finding: int}, type: string, title: string, text: string}>
     */
    public static function claimExcerpts(array $items, int $limit = 6): array
    {
        $limit = max(0, $limit);
        $out = [];

        foreach ($items as $itemIndex => $item) {
            foreach ((array) ($item['findings'] ?? []) as $findingIndex => $finding) {
                if (($finding['category'] ?? '') !== 'claim') {
                    continue;
                }
                $text = trim((string) ($finding['excerpt'] ?? ''));
                if (mb_strlen($text) < 4) {
                    continue; // no usable context around the keyword
                }
                $out[] = [
                    'id' => (string) (count($out) + 1),
                    'ref' => ['item' => (int) $itemIndex, 'finding' => (int) $findingIndex],
                    'type' => (string) ($item['type'] ?? ''),
                    'title' => mb_substr((string) ($item['title'] ?? ''), 0, 80),
                    'text' => mb_substr($text, 0, 300),
                ];
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * Accept at most the verdicts we asked about, in the allowed enum, with a
     * bounded figure-free reason. A partial reply is fine: excerpts without a
     * verdict simply get none - never a guess.
     *
     * @return array<string, array{id: string, verdict: string, reason: string}>|null
     */
    public static function sanitizeVerdicts($raw, array $allowedIds): ?array
    {
        if (! is_array($raw) || ! is_array($raw['verdicts'] ?? null)) {
            return null;
        }

        $allowed = array_fill_keys(array_map('strval', $allowedIds), true);
        $out = [];
        // NOTE: '#' prefixes the key so a numeric-string id is never coerced
        // to an int key - the id inside the entry stays the exact string.


        foreach ($raw['verdicts'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $id = (string) ($entry['id'] ?? '');
            if ($id === '' || ! isset($allowed[$id])) {
                continue;
            }
            $verdict = (string) ($entry['verdict'] ?? '');
            if (! in_array($verdict, self::VERDICTS, true)) {
                continue;
            }
            $reason = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($entry['reason'] ?? '')) ?? '';
            $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');
            if ($reason === '' || mb_strlen($reason) > 140) {
                continue;
            }
            if (preg_match('/\d/', $reason) === 1) {
                continue; // a figure we never supplied - drop the verdict
            }
            $out['#'.$id] = ['id' => $id, 'verdict' => $verdict, 'reason' => $reason];
        }

        return $out;
    }

    /** The strict instruction for the advisory classification. */
    public static function classificationInstruction(string $locale): string
    {
        return implode("\n", [
            'You assist a human reviewer on a marketplace. For each excerpt, judge ONLY whether the unverified claim in it is supported by the text itself.',
            'Return STRICT JSON only: {"verdicts":[{"id":"1","verdict":"supported|unsupported|uncertain","reason":"max 120 chars"}]}.',
            'Rules: one entry per excerpt id you were given, no new ids; verdicts are advisory for a human and never a decision;',
            'use no numbers in reasons; do not invent facts, prices, dates or products; when in doubt use "uncertain";',
            'write the reason in the language with locale code "'.$locale.'" (sw=Kiswahili, en=English, fr=French, es=Spanish, zh=Chinese, hi=Hindi, ur=Urdu, de=German).',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private static function finding(string $category, string $severity, string $field, string $excerpt, string $messageKey, string $locale, array $params = []): array
    {
        return [
            'category' => $category,
            'severity' => $severity,
            'field' => $field,
            'excerpt' => $excerpt,
            'message' => self::msg($messageKey, $locale, $params),
        ];
    }

    /** Word-boundary phrase hit with a bounded window of context. */
    private static function phraseHit(string $text, string $term): ?string
    {
        if ($term === '') {
            return null;
        }

        if (! preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/ui', $text, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $bytePos = $match[0][1];
        $before = substr($text, 0, $bytePos);
        $start = mb_strlen($before) - 40;
        $start = max(0, $start);

        $window = mb_substr($text, $start, 140);

        return ($start > 0 ? '…' : '').trim($window).(mb_strlen($text) > $start + 140 ? '…' : '');
    }

    /** A phone-like number of at least nine digits (off-platform contact). */
    private static function phoneHit(string $text): ?string
    {
        if (! preg_match_all('/(?:\+?\d[\d\s\-]{7,}\d)/u', $text, $matches)) {
            return null;
        }

        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate) ?? '';
            if (strlen($digits) < 9) {
                continue;
            }
            $pos = mb_strpos($text, $candidate);
            $start = max(0, ($pos === false ? 0 : $pos) - 40);

            return ($start > 0 ? '…' : '').trim(mb_substr($text, $start, 140));
        }

        return null;
    }

    private static function positiveNumber($value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $float = (float) $value;

        return is_finite($float) && $float > 0 ? round($float, 2) : null;
    }
}
