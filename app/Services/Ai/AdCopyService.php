<?php

namespace App\Services\Ai;

/**
 * Capability #8 - Advertisement & Product-Copy Generator (grounding layer).
 *
 * The model drafts marketing copy; this class decides what of that draft may
 * reach the seller. Grounding rules enforced here:
 *  - every number in the copy must exist in the seller's own facts (product
 *    price/stock rows or the seller's objective/product description), so a
 *    fabricated discount or invented price can never reach the preview;
 *  - guarantee/refund language is only allowed when the seller's own product
 *    description or objective already contains it;
 *  - the product name must survive verbatim (brand preservation);
 *  - output length is validated against configured hard caps and clipped at a
 *    sentence/word boundary with an honest flag.
 *
 * Publishing is NOT part of this class: nothing here writes to the
 * `advertisements` table. The copy reaches the platform only when the seller
 * approves it through the existing POST /api/matangazo workflow.
 */
class AdCopyService
{
    public const CAPABILITY = 'ad_copy';

    public const CONTENT_TYPES = ['headline', 'description', 'product_description', 'promo'];
    public const TONES = ['friendly', 'professional', 'urgent', 'playful'];
    public const LENGTHS = ['short', 'medium', 'long'];

    // Non-fatal flags shown in the preview.
    public const FLAG_TRUNCATED = 'truncated';
    public const FLAG_BRAND_MISSING = 'brand_missing';
    public const FLAG_OUT_OF_STOCK = 'out_of_stock';

    // Fatal outcomes: the draft is rejected, copy stays empty.
    public const FAIL_EMPTY = 'AI_EMPTY';
    public const FAIL_UNFACTUAL = 'UNFACTUAL';
    public const FAIL_CLAIM = 'UNSUPPORTED_CLAIM';

    /**
     * Guarantee/refund language that is an unsupported claim unless the
     * seller's own objective or product description already contains it.
     * Word boundaries are only used where the script has them.
     */
    private const CLAIM_PATTERNS = [
        '/\bguarantee(?:d|s|ing)?\b/iu',
        '/\bwarrant(?:y|ies)\b/iu',
        '/\brefund(?:s|ed|ing)?\b/iu',
        '/\bmoney[\s-]?back\b/iu',
        '/\brisk[\s-]?free\b/iu',
        '/\bdhamana\b/iu',
        '/\bgarantie\b/iu',
        '/\bgarant[ií]a\b/iu',
        '/\berstattung\b/iu',
        '/\bremboursement\b/iu',
        '/\bsatisfait ou\b/iu',
        '/वारंटी|गारंटी|रिफ़ंड/u',
        '/وارنٹی|گارنٹی|ریفنڈ/u',
        '/保修|无条件保证|退款/u',
    ];

    /** Per-flag localized copy (8 locales, English fallback). */
    private const FLAG_MESSAGES = [
        self::FLAG_TRUNCATED => [
            'sw' => 'Matumizi ya herufi yalipita kikomo - yamekatishwa kwenye sentensi kamili. Fupisha zaidi kama unataka.',
            'en' => 'The text exceeded the length limit - it was cut at a full sentence. Shorten it further if needed.',
            'fr' => "Le texte a dépassé la limite - il a été coupé à une phrase complète. Raccourcissez-le si nécessaire.",
            'hi' => 'पाठ सीमा से अधिक था - इसे पूरे वाक्य पर काटा गया। चाहें तो और छोटा करें।',
            'es' => 'El texto superó el límite - se cortó en una frase completa. Acórtalo si es necesario.',
            'ur' => 'متن حد سے زیادہ تھا - مکمل جملے پر کاٹ دیا گیا۔ ضرورت ہو تو مزید چھوٹا کریں۔',
            'de' => 'Der Text überschreitet das Limit - er wurde an einem ganzen Satz abgeschnitten. Kürzen Sie ihn bei Bedarf.',
            'zh' => '文本超出长度限制——已在完整句子处截断。如有需要请再缩短。',
        ],
        self::FLAG_BRAND_MISSING => [
            'sw' => 'Jina la bidhaa halipo kwenye nakala - liliongeza ili chapa ibaki salama.',
            'en' => 'The product name is missing from the copy - add it so the brand stays intact.',
            'fr' => "Le nom du produit manque dans le texte - ajoutez-le pour préserver la marque.",
            'hi' => 'प्रति में उत्पाद का नाम नहीं है - इसे जोड़ें ताकि ब्रांड बना रहे।',
            'es' => 'El nombre del producto falta en el texto - añádelo para conservar la marca.',
            'ur' => 'کاپی میں مصنوعات کا نام نہیں ہے - برانڈ برقرار رکھنے کے لیے شامل کریں۔',
            'de' => 'Der Produktname fehlt im Text - fügen Sie ihn hinzu, damit die Marke erhalten bleibt.',
            'zh' => '文案中缺少产品名称——请添加以保留品牌名。',
        ],
        self::FLAG_OUT_OF_STOCK => [
            'sw' => 'Bidhaa hii iimeisha stock - usidai upatikanaji kwenye matangazo.',
            'en' => 'This product is out of stock - do not claim availability in the ad.',
            'fr' => "Ce produit est en rupture de stock - n'affirmez pas sa disponibilité dans l'annonce.",
            'hi' => 'यह उत्पाद स्टॉक में नहीं है - विज्ञापन में उपलब्धता का दावा न करें।',
            'es' => 'Este producto no tiene existencias - no afirme disponibilidad en el anuncio.',
            'ur' => 'یہ مصنوعات اسٹاک میں نہیں ہے - اشتہار میں دستیابی کا دعویٰ نہ کریں۔',
            'de' => 'Dieses Produkt ist nicht auf Lager - behaupten Sie keine Verfügbarkeit in der Anzeige.',
            'zh' => '该产品已无库存——请勿在广告中宣称有货。',
        ],
    ];

    /** Why a draft was rejected (8 locales, English fallback). */
    private const FAIL_REASONS = [
        self::FAIL_EMPTY => [
            'sw' => 'Modeli haikutoa nakala yenye maudhui. Jaribu tena au badilisha vidhibiti.',
            'en' => 'The model returned no usable copy. Try again or adjust the controls.',
            'fr' => "Le modèle n'a renvoyé aucun texte exploitable. Réessayez ou ajustez les contrôles.",
            'hi' => 'मॉडल ने कोई उपयोगी कॉपी नहीं दी। पुनः प्रयास करें या नियंत्रण बदलें।',
            'es' => 'El modelo no devolvió texto utilizable. Inténtalo de nuevo o ajusta los controles.',
            'ur' => 'ماڈل نے کوئی قابل استعمال کاپی نہیں دی۔ دوبارہ کوشش کریں یا کنٹرول تبدیل کریں۔',
            'de' => 'Das Modell hat keinen verwertbaren Text geliefert. Versuchen Sie es erneut oder passen Sie die Einstellungen an.',
            'zh' => '模型未返回可用文案。请重试或调整控制项。',
        ],
        self::FAIL_UNFACTUAL => [
            'sw' => 'Nakala ilikuwa na namba (bei au asilimia) isiyo kwenye data yako ya bidhaa - imekataliwa.',
            'en' => 'The copy contained a number (price or percentage) that is not in your product data - rejected.',
            'fr' => "Le texte contenait un nombre (prix ou pourcentage) absent de vos données produit - rejeté.",
            'hi' => 'कॉपी में ऐसी संख्या (कीमत या प्रतिशत) थी जो आपके उत्पाद डेटा में नहीं है - अस्वीकृत।',
            'es' => 'El texto contenía un número (precio o porcentaje) que no está en tus datos de producto - rechazado.',
            'ur' => 'کاپی میں ایسی رقم یا فیصد تھا جو آپ کے مصنوعات کے ڈیٹا میں نہیں - مسترد۔',
            'de' => 'Der Text enthielt eine Zahl (Preis oder Prozent), die nicht in Ihren Produktdaten steht - abgelehnt.',
            'zh' => '文案中出现了产品数据里没有的数字（价格或百分比）——已拒绝。',
        ],
        self::FAIL_CLAIM => [
            'sw' => 'Nakala ilitumia ahadi (dhamana au marejesho) ambayo haiko kwenye maelezo yako ya bidhaa - imekataliwa.',
            'en' => 'The copy made a promise (guarantee or refund) that is not in your product description - rejected.',
            'fr' => "Le texte promet quelque chose (garantie ou remboursement) absent de votre description produit - rejeté.",
            'hi' => 'कॉपी ने ऐसा वादा (वारंटी या रिफ़ंड) किया जो आपके उत्पाद विवरण में नहीं है - अस्वीकृत।',
            'es' => 'El texto hizo una promesa (garantía o reembolso) que no está en tu descripción de producto - rechazado.',
            'ur' => 'کاپی نے ایسا وعدہ (وارنٹی یا ریفنڈ) کیا جو آپ کی مصنوعات کی تفصیل میں نہیں - مسترد۔',
            'de' => 'Der Text enthielt ein Versprechen (Garantie oder Erstattung), das nicht in Ihrer Produktbeschreibung steht - abgelehnt.',
            'zh' => '文案做出了产品描述中没有的承诺（保修或退款）——已拒绝。',
        ],
    ];

    /**
     * Honest limitation lines for the response (8 locales, English fallback).
     */
    private const LIMITATIONS = [
        'sw' => [
            'Nakala imeandikwa na AI kutoka kwenye rekodi zako za bidhaa pekee.',
            'Bei na idadi ya stock zinatoka kwenye data yako ya sasa - hakikisha unasasisha kwenye Uza.',
            'Tofauti, dhamana na upatikanaji haviongezwi bila rekodi yako.',
            'Hakuna tangazo linalochapishwa bila uidhini wako - kila nakala huhaririwa na kuthibitishwa nawe.',
        ],
        'en' => [
            'The copy is AI-drafted from your own product records only.',
            'Prices and stock come from your current data - keep them updated in Sell.',
            'Discounts, guarantees and availability are never added beyond your records.',
            'Nothing is published without your approval - every draft is edited and confirmed by you.',
        ],
        'fr' => [
            "Le texte est rédigé par IA à partir de vos seules fiches produit.",
            'Prix et stock proviennent de vos données actuelles - mettez-les à jour dans Vendre.',
            'Remises, garanties et disponibilités ne sont jamais ajoutées au-delà de vos données.',
            "Rien n'est publié sans votre approbation - chaque brouillon est édité et confirmé par vous.",
        ],
        'hi' => [
            'कॉपी केवल आपके उत्पाद रिकॉर्ड से AI द्वारा बनाई गई है।',
            'कीमत और स्टॉक आपके वर्तमान डेटा से आते हैं - बेचें में अपडेट रखें।',
            'छूट, वारंटी और उपलब्धता आपके रिकॉर्ड से आगे कभी नहीं जोड़ी जातीं।',
            'आपकी अनुमति के बिना कुछ भी प्रकाशित नहीं होता - हर ड्राफ्ट आप ही संपादित व पुष्टि करते हैं।',
        ],
        'es' => [
            'El texto lo redacta la IA solo con tus registros de producto.',
            'Precios y stock provienen de tus datos actuales - mantenlos en Vender.',
            'Descuentos, garantías y disponibilidad nunca se añaden fuera de tus registros.',
            'No se publica nada sin tu aprobación - cada borrador lo editas y confirmas tú.',
        ],
        'ur' => [
            'کاپی صرف آپ کے مصنوعات کے ریکارڈ سے AI بناتی ہے۔',
            'قیمت اور اسٹاک آپ کے موجودہ ڈیٹا سے آتے ہیں - بیچنے میں اپ ڈیٹ رکھیں۔',
            'رعایتیں، وارنٹی اور دستیابی آپ کے ریکارڈ سے آگے کبھی نہیں ڈالی جاتیں۔',
            'آپ کی اجازت کے بغیر کچھ بھی شائع نہیں ہوتا - ہر ڈرافٹ آپ ہی میں کرتے اور تصدیق کرتے ہیں۔',
        ],
        'de' => [
            'Der Text wird per KI ausschließlich aus Ihren Produktdaten verfasst.',
            'Preise und Bestand stammen aus Ihren aktuellen Daten - halten Sie sie im Verkauf aktuell.',
            'Rabatte, Garantien und Verfügbarkeit werden nie über Ihre Daten hinaus ergänzt.',
            'Nichts wird ohne Ihre Freigabe veröffentlicht - jeden Entwurf bearbeiten und bestätigen Sie selbst.',
        ],
        'zh' => [
            '文案仅由 AI 根据您自己的产品记录生成。',
            '价格与库存来自您当前的数据——请在“出售”中保持更新。',
            '折扣、保修与有货情况绝不超出您的记录添加。',
            '未经您批准不会发布任何内容——每份草稿都由您编辑确认。',
        ],
    ];

    /**
     * Hard character caps for one content type (validated post-generation).
     *
     * @return array{headline: int, body: int}
     */
    public static function limits(string $contentType): array
    {
        $headline = max(20, (int) config('ai.ad_copy.max_headline', 90));

        if ($contentType === 'headline') {
            return ['headline' => $headline, 'body' => 0];
        }

        $body = match ($contentType) {
            'product_description' => max(120, (int) config('ai.ad_copy.max_product_description', 700)),
            'promo' => max(60, (int) config('ai.ad_copy.max_promo', 240)),
            default => max(80, (int) config('ai.ad_copy.max_description', 480)),
        };

        return ['headline' => $headline, 'body' => $body];
    }

    /** Character target band for the seller's length control. */
    public static function targetBand(string $length, int $cap): array
    {
        $cap = max(1, $cap);

        return match ($length) {
            'short' => [(int) ceil($cap * 0.30), (int) ceil($cap * 0.60)],
            'long' => [(int) ceil($cap * 0.70), $cap],
            default => [(int) ceil($cap * 0.50), (int) ceil($cap * 0.80)],
        };
    }

    public static function systemInstruction(): string
    {
        return implode("\n", [
            'You write marketplace advertisement copy inside "Duka Mkononi" for a small seller.',
            'Return STRICT JSON with exactly these keys: {"headline": string, "body": string}.',
            'Rules:',
            '- Write in the language identified by the "locale" field. Match the requested tone, audience and content type.',
            '- Use ONLY the facts provided: product name, category, description, price, stock and the seller objective.',
            '- The product name is a brand identifier: reproduce it verbatim, never translate, shorten or alter it.',
            '- NEVER invent prices, discounts, percentages, warranties, guarantees, refunds, delivery promises, or availability.',
            '- Mention a discount or percentage ONLY if the seller objective or product description contains it.',
            '- If stock is 0, do not claim the product is available.',
            '- Respect the character limits: "headline" and "body" must each fit their limit.',
            '- "headline" is one short line (no trailing newline). "body" is the main text.',
            '- Output JSON only, no markdown, no code fences, no emojis, no hashtags.',
        ]);
    }

    /**
     * Facts payload for the model: seller controls + authorized product data.
     * Prices and stock are the live rows, never model-readable numbers we did
     * not send.
     */
    public static function userPrompt(string $locale, array $product, array $controls): string
    {
        $type = in_array($controls['content_type'] ?? '', self::CONTENT_TYPES, true)
            ? $controls['content_type']
            : 'description';
        $limits = self::limits($type);

        $facts = [
            'locale' => $locale,
            'content_type' => $type,
            'tone' => $controls['tone'] ?? 'friendly',
            'audience' => $controls['audience'] ?? '',
            'length' => $controls['length'] ?? 'medium',
            'objective' => $controls['objective'] ?? '',
            'target_chars' => [
                'headline' => self::targetBand($controls['length'] ?? 'medium', $limits['headline']),
                'body' => self::targetBand($controls['length'] ?? 'medium', $limits['body'] ?: $limits['headline']),
            ],
            'product' => [
                'name' => (string) ($product['name'] ?? ''),
                'category' => (string) ($product['category'] ?? ''),
                'description' => (string) ($product['description'] ?? ''),
                'price' => (float) ($product['price'] ?? 0),
                'expected_selling_price' => (float) ($product['expected_selling_price'] ?? 0),
                'stock' => (float) ($product['stock'] ?? 0),
            ],
            'limits' => $limits,
        ];

        return "Write the advertisement copy for these facts:\n"
            .json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Validate and shape one model draft. Pure function (no I/O) so the full
     * grounding contract is unit-testable.
     *
     * @param  array<string, mixed>|null  $model
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $controls
     * @return array{ok: bool, copy: array<string, string>, flags: array<int, array{code: string, message: string}>, code: ?string, reason: ?string}
     */
    public static function sanitize(?array $model, array $product, array $controls, string $locale): array
    {
        $type = in_array($controls['content_type'] ?? '', self::CONTENT_TYPES, true)
            ? $controls['content_type']
            : 'description';

        $headline = self::clean(is_array($model) ? (string) ($model['headline'] ?? '') : '');
        $body = self::clean(is_array($model) ? (string) ($model['body'] ?? '') : '');

        if ($type === 'headline') {
            $body = '';
        }
        if ($type === 'product_description') {
            $headline = '';
        }

        if ($type === 'headline' && trim($headline) === '') {
            return self::fail(self::FAIL_EMPTY, $locale);
        }
        if ($type !== 'headline' && trim($body) === '') {
            return self::fail(self::FAIL_EMPTY, $locale);
        }

        $combined = trim($headline.' '.$body);

        // 1) Guarantee/refund promises must already exist in the seller's own
        //    product description or objective (claim guard).
        $sources = strtolower(self::clean(
            (string) ($product['description'] ?? '').' '.(string) ($controls['objective'] ?? '')
        ));
        foreach (self::CLAIM_PATTERNS as $pattern) {
            $hitCopy = preg_match($pattern, $combined, $matches) === 1;
            $hitSource = preg_match($pattern, $sources) === 1;
            if ($hitCopy && ! $hitSource) {
                return self::fail(self::FAIL_CLAIM, $locale);
            }
        }

        // 2) Every number in the copy must exist in the authorized facts.
        $allowedNumbers = self::allowedNumbers($product, $controls);
        $allowedPercents = self::allowedPercents($controls, $product);
        if (self::hasUnfactedNumber($combined, $allowedNumbers, $allowedPercents)) {
            return self::fail(self::FAIL_UNFACTUAL, $locale);
        }

        // 3) Length caps: clip at a sentence/word boundary, honestly flagged.
        $caps = self::limits($type);
        $clipped = false;
        if ($caps['headline'] > 0) {
            $before = $headline;
            $headline = self::clip($headline, $caps['headline']);
            $clipped = $clipped || $headline !== $before;
        }
        if ($caps['body'] > 0) {
            $before = $body;
            $body = self::clip($body, $caps['body']);
            $clipped = $clipped || $body !== $before;
        }

        $flags = [];
        if ($clipped) {
            $flags[] = self::flag(self::FLAG_TRUNCATED, $locale);
        }

        // 4) Brand preservation: the product name must survive verbatim in
        //    the copy as the seller will actually receive it (post-clip).
        $finalText = trim($headline.' '.$body);
        $name = mb_strtolower(trim(self::clean((string) ($product['name'] ?? ''))));
        if ($name !== '' && mb_strpos(mb_strtolower($finalText), $name) === false) {
            $flags[] = self::flag(self::FLAG_BRAND_MISSING, $locale);
        }

        // 5) Availability honesty: never let a draft imply stock we do not have.
        if ((float) ($product['stock'] ?? 0) <= 0) {
            $flags[] = self::flag(self::FLAG_OUT_OF_STOCK, $locale);
        }

        $copy = [];
        if (trim($headline) !== '') {
            $copy['headline'] = trim($headline);
        }
        if (trim($body) !== '') {
            $copy['body'] = trim($body);
        }

        if ($copy === []) {
            return self::fail(self::FAIL_EMPTY, $locale);
        }

        return [
            'ok' => true,
            'copy' => $copy,
            'flags' => $flags,
            'code' => null,
            'reason' => null,
        ];
    }

    /**
     * All numbers the copy may use: product money fields plus every number
     * that already appears in the seller's objective or product description.
     *
     * @return array<int, float>
     */
    public static function allowedNumbers(array $product, array $controls): array
    {
        $numbers = [
            (float) ($product['price'] ?? 0),
            (float) ($product['expected_selling_price'] ?? 0),
        ];

        $sources = (string) ($controls['objective'] ?? '').' '.(string) ($product['description'] ?? '');
        foreach (self::numbersIn($sources) as $value) {
            $numbers[] = $value;
        }

        return $numbers;
    }

    /**
     * Percentages the copy may use - only ones the seller wrote themselves.
     *
     * @return array<int, float>
     */
    public static function allowedPercents(array $controls, array $product): array
    {
        $sources = (string) ($controls['objective'] ?? '').' '.(string) ($product['description'] ?? '');
        $percents = [];

        if (preg_match_all('/(\d+(?:\.\d+)?)\s*%/u', $sources, $matches) > 0) {
            foreach ($matches[1] as $value) {
                $percents[] = (float) $value;
            }
        }

        return $percents;
    }

    /**
     * True when the text holds a percentage not in the allowed set, or a
     * number >= 100 not in the allowed facts (prices/desc numbers).
     *
     * @param  array<int, float>  $allowedNumbers
     * @param  array<int, float>  $allowedPercents
     */
    public static function hasUnfactedNumber(string $text, array $allowedNumbers, array $allowedPercents): bool
    {
        // Strip thousands separators first so "5,000" compares as 5000.
        $normalized = preg_replace('/(?<=\d)(?:,\s*)?(?=\d{3}(?!\d))/u', '', $text) ?? $text;

        // Percentages are validated against the percent set below; removing
        // them here keeps them from being re-checked as plain numbers.
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*%/u', $normalized, $percentMatches) > 0) {
            foreach ($percentMatches[1] as $value) {
                $candidate = (float) $value;
                foreach ($allowedPercents as $allowed) {
                    if (abs($allowed - $candidate) < 0.01) {
                        continue 2;
                    }
                }

                return true;
            }
        }

        // Percentages were validated above; drop them so a large allowed %
        // (e.g. a seller-declared 150%) is not re-checked as a plain number.
        $withoutPercents = preg_replace('/\d+(?:\.\d+)?\s*%/u', ' ', $normalized) ?? $normalized;

        if (preg_match_all('/\d+(?:\.\d+)?/u', $withoutPercents, $matches) > 0) {
            foreach ($matches[0] as $token) {
                $value = (float) $token;
                if ($value < 100) {
                    continue; // counts, days, sizes - not money-scale claims
                }
                foreach ($allowedNumbers as $allowed) {
                    if (abs($allowed - $value) < 0.01) {
                        continue 2;
                    }
                }

                return true;
            }
        }

        return false;
    }

    /** @return array<int, float> */
    private static function numbersIn(string $text): array
    {
        $normalized = preg_replace('/(?<=\d)(?:,\s*)?(?=\d{3}(?!\d))/u', '', $text) ?? $text;
        $values = [];

        if (preg_match_all('/\d+(?:\.\d+)?/u', $normalized, $matches) > 0) {
            foreach ($matches[0] as $token) {
                $values[] = (float) $token;
            }
        }

        return $values;
    }

    /** Strip HTML, control characters and superfluous whitespace. */
    public static function clean(string $text): string
    {
        $text = preg_replace('/<[^>]*>/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** Clip to a cap at a sentence boundary when one fits, else at a word. */
    public static function clip(string $text, int $cap): string
    {
        if ($cap < 1 || mb_strlen($text) <= $cap) {
            return $text;
        }

        $window = mb_substr($text, 0, $cap);
        $best = 0;
        foreach (['.', '!', '?', '।', '。'] as $end) {
            $pos = mb_strrpos($window, $end);
            if ($pos !== false && $pos + 1 > $best) {
                $best = $pos + 1;
            }
        }
        if ($best >= (int) floor($cap * 0.5)) {
            return rtrim(mb_substr($window, 0, $best));
        }

        $space = mb_strrpos($window, ' ');

        return $space !== false && $space > 0
            ? rtrim(mb_substr($window, 0, $space))
            : $window;
    }

    private static function flag(string $code, string $locale): array
    {
        $table = self::FLAG_MESSAGES[$code] ?? null;
        $message = $table[$locale] ?? ($table['en'] ?? $code);

        return ['code' => $code, 'message' => $message];
    }

    private static function fail(string $code, string $locale): array
    {
        $table = self::FAIL_REASONS[$code] ?? null;
        $reason = $table[$locale] ?? ($table['en'] ?? $code);

        return [
            'ok' => false,
            'copy' => [],
            'flags' => [],
            'code' => $code,
            'reason' => $reason,
        ];
    }

    /** Localized limitation lines for the response. */
    public static function limitations(string $locale): array
    {
        return self::LIMITATIONS[$locale] ?? self::LIMITATIONS['en'];
    }
}
