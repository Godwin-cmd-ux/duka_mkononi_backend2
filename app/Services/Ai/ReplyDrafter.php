<?php

namespace App\Services\Ai;

/**
 * Capability #9 - Customer-Reply Drafting (grounding layer).
 *
 * The model drafts a seller's reply to a customer's order question; this
 * class decides what of that draft may reach the seller's note box.
 * Grounding rules enforced here:
 *  - the draft may only use the facts we sent (order reference, status,
 *    recorded items, real prices/totals, the customer's own question and the
 *    seller's already customer-visible notes);
 *  - every money-scale number must exist in this order's own amounts, so a
 *    fabricated price can never reach the reply;
 *  - delivery-time promises and discount/refund/guarantee sentences are
 *    stripped (and honestly flagged) unless the seller already put that
 *    commitment into a customer-visible note - the assistant may restate the
 *    seller's own promises, never invent new ones;
 *  - output is clipped to the configured cap at a sentence boundary.
 *
 * Sending is NOT part of this class: nothing here writes to `orders` or
 * `order_status_history`. The draft only reaches the customer when the seller
 * edits it and presses the normal save button (PUT seller/orders/{id}/status
 * or POST seller/orders/{id}/notes), which keeps its own ownership checks and
 * audit trail. Internal notes are never read by - and never sent to - the
 * model.
 */
class ReplyDrafter
{
    public const CAPABILITY = 'reply_draft';

    public const TONES = ['formal', 'casual'];

    // Non-fatal flags shown above the draft.
    public const FLAG_PROMISE_REMOVED = 'promise_removed';
    public const FLAG_DISCOUNT_REMOVED = 'discount_removed';
    public const FLAG_TRUNCATED = 'truncated';
    public const FLAG_DUPLICATE = 'duplicate_reply';

    // Fatal outcomes: the draft is rejected, the note box stays empty.
    public const FAIL_EMPTY = 'AI_EMPTY';
    public const FAIL_AMOUNT = 'UNFACTUAL_AMOUNT';

    // Deterministic "the seller must supply this" notices.
    public const MISSING_NO_TIMING = 'no_delivery_timing';
    public const MISSING_UNPAID = 'payment_not_recorded';

    /**
     * Delivery-time promise words (8 locales). A sentence containing one of
     * these is stripped unless the seller already promised timing in a
     * customer-visible note.
     */
    private const PROMISE_PATTERNS = [
        '/\bkesho\b/iu',
        '/\btomorrow\b/iu',
        '/\btoday\b/iu',
        '/\bdemain\b/iu',
        "/aujourd['’]?hui/iu",
        '/\bma[ñn]ana\b/iu',
        '/\bhoy\b/iu',
        '/\bmorgen\b/iu',
        '/\bheute\b/iu',
        '/\bkal\b/iu',
        '/आज|कल/u',
        '/اليوم|غدا/u',
        '/آج|کل/u',
        '/明天|今天/u',
        // Concrete durations: "3 siku", "48 hours", "2 días", "5 Tage"...
        '/\b\d{1,3}\s*(?:siku|days|jours|tage|d[ií]as|din|ساعت|hours|heures|std|天|دن)\b/iu',
    ];

    /**
     * Discount / refund / guarantee language. A sentence containing one of
     * these is stripped unless the seller already used such a term in a
     * customer-visible note.
     */
    private const CLAIM_PATTERNS = [
        '/\bfree\b/iu',
        '/\bdiscount(?:s|ed)?\b/iu',
        '/\brefund(?:s|ed|ing)?\b/iu',
        '/\bguarantee(?:d|s)?\b/iu',
        '/\bwarrant(?:y|ies)\b/iu',
        '/\bdhamana\b/iu',
        '/\bpunguzo\b/iu',
        '/\bbure\b/iu',
        '/\bremise\b/iu',
        '/\bdescuento\b/iu',
        '/\brabatt\b/iu',
        '/remboursement|garantie/u',
        '/रिफ़ंड|छूट|वारंटी/u',
        '/رعایت|ریفنڈ|وارنٹی/u',
        '/退款|折扣|保修/u',
    ];

    /** Per-flag localized copy (8 locales, English fallback). */
    private const FLAG_MESSAGES = [
        self::FLAG_PROMISE_REMOVED => [
            'sw' => 'Sentensi yenye ahadi ya muda iliondolewa - hakuna muda wa kuletwa uliorekodiwa kwenye oda hii.',
            'en' => 'A sentence promising a delivery time was removed - no delivery timing is recorded for this order.',
            'fr' => "Une phrase promettant un délai a été supprimée - aucun délai de livraison n'est enregistré pour cette commande.",
            'hi' => 'डिलीवरी समय का वादा करने वाला वाक्य हटाया गया - इस ऑर्डर के लिए कोई समय दर्ज नहीं है।',
            'es' => 'Se eliminó una frase que prometía un plazo de entrega - no hay plazo registrado para este pedido.',
            'ur' => 'ڈیلیوری کا وقت وعدہ کرنے والہ جملہ ہٹا دیا گیا - اس آرڈر کے لیے کوئی وقت درج نہیں ہے۔',
            'de' => 'Ein Satz mit einer Lieferzeit-Zusage wurde entfernt - für diese Bestellung ist keine Lieferzeit erfasst.',
            'zh' => '已删除承诺送达时间的句子——该订单没有记录任何送达时间。',
        ],
        self::FLAG_DISCOUNT_REMOVED => [
            'sw' => 'Sentensi yenye punguzo au marejesho iliondolewa - hayajarekodiwa kwenye oda hii.',
            'en' => 'A sentence about a discount or refund was removed - neither is recorded on this order.',
            'fr' => "Une phrase sur une remise ou un remboursement a été supprimée - rien n'est enregistré sur cette commande.",
            'hi' => 'छूट या रिफ़ंड के बारे में वाक्य हटाया गया - इस ऑर्डर में कोई दर्ज नहीं है।',
            'es' => 'Se eliminó una frase sobre descuento o reembolso - ninguno está registrado en este pedido.',
            'ur' => 'رعایت یا ریفنڈ کے بارے میں جملہ ہٹایا گیا - اس آرڈر پر کوئی درج نہیں ہے۔',
            'de' => 'Ein Satz zu Rabatt oder Erstattung wurde entfernt - keines davon ist für diese Bestellung erfasst.',
            'zh' => '已删除关于折扣或退款的句子——该订单没有相关记录。',
        ],
        self::FLAG_TRUNCATED => [
            'sw' => 'Jibu lilipita kikomo cha herufi - limekatishwa kwenye sentensi kamili. Fupisha zaidi kama unataka.',
            'en' => 'The reply exceeded the length limit - it was cut at a full sentence. Shorten it further if needed.',
            'fr' => "La réponse a dépassé la limite - elle a été coupée à une phrase complète. Raccourcissez-la si nécessaire.",
            'hi' => 'जवाब लंबाई सीमा से अधिक था - इसे पूरे वाक्य पर काटा गया। चाहें तो और छोटा करें।',
            'es' => 'La respuesta superó el límite - se cortó en una frase completa. Acórtala si es necesario.',
            'ur' => 'جواب حد سے زیادہ تھا - مکمل جملے پر کاٹ دیا گیا۔ ضرورت ہو تو مزید چھوٹا کریں۔',
            'de' => 'Die Antwort überschreitet das Limit - sie wurde an einem ganzen Satz abgeschnitten. Kürzen Sie sie bei Bedarf.',
            'zh' => '回复超出长度限制——已在完整句子处截断。如有需要请再缩短。',
        ],
        self::FLAG_DUPLICATE => [
            'sw' => 'Jibu hili linafanana sana na ujumbe tayari uliotumwa kwa oda hii - hakikisha hutaki kutuma mara mbili.',
            'en' => 'This reply is nearly identical to a note already sent on this order - make sure you are not sending it twice.',
            'fr' => "Cette réponse est presque identique à un message déjà envoyé sur cette commande - vérifiez que vous ne l'envoyez pas deux fois.",
            'hi' => 'यह जवाब इस ऑर्डर पर पहले भेजे गए नोट जैसा ही है - सुनिश्चित करें कि आप इसे दोबारा नहीं भेज रहे।',
            'es' => 'Esta respuesta es casi idéntica a una nota ya enviada en este pedido - asegúrate de no enviarla dos veces.',
            'ur' => 'یہ جواب اس آرڈر پر پہلے بھیجے گئے نوٹ کا تقریباً ایک جیسا ہے - یقینی بنائیں کہ آپ اسے دوبارہ نہیں بھیج رہے۔',
            'de' => 'Diese Antwort ist fast identisch mit einer bereits gesendeten Notiz - vermeiden Sie das doppelte Senden.',
            'zh' => '此回复与该订单已发送的备注几乎相同——请确认不要重复发送。',
        ],
    ];

    /** Why a draft was rejected (8 locales, English fallback). */
    private const FAIL_REASONS = [
        self::FAIL_EMPTY => [
            'sw' => 'Modeli hakikutoa jibu lenye maudhui. Jaribu tena au badilisha mtindo.',
            'en' => 'The model returned no usable reply. Try again or change the tone.',
            'fr' => "Le modèle n'a renvoyé aucune réponse exploitable. Réessayez ou changez le ton.",
            'hi' => 'मॉडल ने कोई उपयोगी जवाब नहीं दिया। पुनः प्रयास करें या शैली बदलें।',
            'es' => 'El modelo no devolvió una respuesta utilizable. Inténtalo de nuevo o cambia el tono.',
            'ur' => 'ماڈل نے کوئی قابل استعمال جواب نہیں دیا۔ دوبارہ کوشش کریں یا لہجہ بدلیں۔',
            'de' => 'Das Modell hat keine verwertbare Antwort geliefert. Versuchen Sie es erneut oder ändern Sie den Ton.',
            'zh' => '模型未返回可用回复。请重试或更换语气。',
        ],
        self::FAIL_AMOUNT => [
            'sw' => 'Jibu lilikuwa na kiasi (bei) lisilopo kwenye oda hii - limekataliwa. Jaribu tena.',
            'en' => 'The reply contained an amount (price) that is not on this order - rejected. Try again.',
            'fr' => "La réponse contenait un montant (prix) absent de cette commande - rejetée. Réessayez.",
            'hi' => 'जवाब में ऐसी राशि (कीमत) थी जो इस ऑर्डर में नहीं है - अस्वीकृत। पुनः प्रयास करें।',
            'es' => 'La respuesta contenía un importe (precio) que no está en este pedido - rechazada. Inténtalo de nuevo.',
            'ur' => 'جواب میں ایسی رقم (قیمت) تھی جو اس آرڈر میں نہیں - مسترد۔ دوبارہ کوشش کریں۔',
            'de' => 'Die Antwort enthielt einen Betrag (Preis), der nicht zu dieser Bestellung gehört - abgelehnt. Versuchen Sie es erneut.',
            'zh' => '回复中出现了该订单没有的金额（价格）——已拒绝。请重试。',
        ],
    ];

    /** "The seller must supply this" notices (8 locales, English fallback). */
    private const MISSING_MESSAGES = [
        self::MISSING_NO_TIMING => [
            'sw' => 'Hakuna muda wa kuletwa uliorekodiwa kwenye oda hii. Jibu halitoi ahadi ya muda - weka mwenyewe kama unaijua.',
            'en' => 'No delivery timing is recorded on this order. The draft never promises one - add it yourself if you know it.',
            'fr' => "Aucun délai de livraison n'est enregistré sur cette commande. Le brouillon n'en promet jamais - ajoutez-le vous-même si vous le savez.",
            'hi' => 'इस ऑर्डर पर कोई डिलीवरी समय दर्ज नहीं है। ड्राफ्ट कभी ऐसा वादा नहीं करता - यदि आप जानते हैं तो स्वयं जोड़ें।',
            'es' => 'No hay plazo de entrega registrado en este pedido. El borrador nunca promete uno - añádelo tú si lo sabes.',
            'ur' => 'اس آرڈر پر کوئی ڈیلیوری وقت درج نہیں ہے۔ ڈرافٹ کبھی وعدہ نہیں کرتا - اگر آپ جانتے ہیں تو خود شامل کریں۔',
            'de' => 'Für diese Bestellung ist keine Lieferzeit erfasst. Der Entwurf verspricht nie eine - tragen Sie sie selbst ein, wenn Sie sie kennen.',
            'zh' => '该订单没有记录送达时间。草稿绝不承诺时间——如您知道，请自行填写。',
        ],
        self::MISSING_UNPAID => [
            'sw' => 'Malipo ya oda hii hayajathibitishwa kwenye rekodi. Usithibitishe malipo kwenye jibu.',
            'en' => 'Payment for this order is not recorded as complete. Do not confirm payment in the reply.',
            'fr' => "Le paiement de cette commande n'est pas enregistré comme terminé. Ne confirmez pas le paiement dans la réponse.",
            'hi' => 'इस ऑर्डर का भुगतान दर्ज नहीं हुआ है। जवाब में भुगतान की पुष्टि न करें।',
            'es' => 'El pago de este pedido no está registrado como completo. No confirme el pago en la respuesta.',
            'ur' => 'اس آرڈر کی ادائیگی مکمل درج نہیں ہے۔ جواب میں ادائیگی کی تصدیق نہ کریں۔',
            'de' => 'Die Zahlung für diese Bestellung ist nicht als abgeschlossen erfasst. Bestätigen Sie die Zahlung nicht in der Antwort.',
            'zh' => '该订单的付款未记录为已完成。请勿在回复中确认收款。',
        ],
    ];

    /**
     * Honest limitation lines for the response (8 locales, English fallback).
     */
    private const LIMITATIONS = [
        'sw' => [
            'Jibu limeandikwa na AI kutoka kwenye rekodi za oda hii pekee - swali la mteja, bidhaa na bei halisi.',
            'Hakuna ahadi ya muda, punguzo au marejesho yanayoongezwa bila rekodi yako.',
            'Hakuna jibu lolote limetumwa bila wewe kuhariri na kubonyeza Hifadhi.',
            'Taarifa za ndani (notes za siri) hazitumwi kwa AI.',
        ],
        'en' => [
            'The reply is AI-drafted from this order\'s records only - the customer question, items and real prices.',
            'No delivery timing, discounts or refunds are added beyond your records.',
            'Nothing is sent until you edit it and press Save yourself.',
            'Internal (private) notes are never sent to the AI.',
        ],
        'fr' => [
            "La réponse est rédigée par IA à partir des seuls enregistrements de cette commande - la question du client, les articles et les prix réels.",
            "Aucun délai, remise ou remboursement n'est ajouté au-delà de vos données.",
            "Rien n'est envoyé tant que vous ne l'éditez pas et n'appuyez pas sur Enregistrer.",
            'Les notes internes (privées) ne sont jamais envoyées à IA.',
        ],
        'hi' => [
            'जवाब केवल इस ऑर्डर के रिकॉर्ड से AI द्वारा बनाया गया है - ग्राहक का प्रश्न, आइटम और वास्तविक कीमतें।',
            'आपके रिकॉर्ड से आगे कोई समय, छूट या रिफ़ंड नहीं जोड़ा जाता।',
            'जब तक आप संपादित कर सेव नहीं करते, कुछ भी नहीं भेजा जाता।',
            'आंतरिक (निजी) नोट कभी AI को नहीं भेजे जाते।',
        ],
        'es' => [
            'La respuesta la redacta la IA solo con los registros de este pedido - la pregunta del cliente, los artículos y los precios reales.',
            'No se añade plazo, descuento ni reembolso fuera de tus registros.',
            'No se envía nada hasta que la editas y pulsas Guardar tú mismo.',
            'Las notas internas (privadas) nunca se envían a la IA.',
        ],
        'ur' => [
            'جواب صرف اس آرڈر کے ریکارڈ سے AI بناتی ہے - گاہک کا سوال، آئٹمز اور حقیقی قیمتیں۔',
            'آپ کے ریکارڈ سے آگے کوئی وقت، رعایت یا ریفنڈ نہیں ڈالا جاتا۔',
            'جب تک آپ ترمیم کر کے محفوظ نہیں کرتے، کچھ بھی نہیں بھیجا جاتا۔',
            'اندرونی (ذاتی) نوٹ کبھی AI کو نہیں بھیجے جاتے۔',
        ],
        'de' => [
            'Die Antwort wird per KI ausschließlich aus den Daten dieser Bestellung verfasst - Kundenfrage, Artikel und echte Preise.',
            'Es werden keine Lieferzeiten, Rabatte oder Erstattungen über Ihre Daten hinaus ergänzt.',
            'Nichts wird gesendet, bis Sie es selbst bearbeiten und auf Speichern drücken.',
            'Interne (private) Notizen werden nie an die KI gesendet.',
        ],
        'zh' => [
            '回复仅由 AI 根据此订单的记录生成——客户问题、商品与真实价格。',
            '绝不添加记录之外的送达时间、折扣或退款。',
            '在您编辑并点击保存之前，不会发送任何内容。',
            '内部（私密）备注绝不会发送给 AI。',
        ],
    ];

    /**
     * Money-scale facts allowed in a reply for one order.
     *
     * @param  array<string, mixed>  $order
     * @return array<int, float>
     */
    public static function allowedNumbers(array $order): array
    {
        $allowed = [];
        foreach (['subtotal', 'shipping_cost', 'total_amount'] as $key) {
            $allowed[] = (float) ($order[$key] ?? 0);
        }
        foreach ((array) ($order['items'] ?? []) as $item) {
            $allowed[] = (float) ($item['unit_price'] ?? 0);
            $allowed[] = (float) ($item['line_total'] ?? 0);
        }

        return array_values(array_unique($allowed));
    }

    public static function systemInstruction(): string
    {
        return implode("\n", [
            'You draft a short, polite seller reply to one customer question inside the "Duka Mkononi" marketplace.',
            'Return STRICT JSON with exactly this key: {"text": string}.',
            'Rules:',
            '- Write in the language of the customer question, in the requested tone ("formal" or "casual").',
            '- Use ONLY the facts provided: order reference, status, items, prices/totals, the customer question and the seller\'s own already-visible notes.',
            '- NEVER promise delivery dates or times, discounts, refunds, warranties or availability that the facts do not contain.',
            '- You may repeat a commitment only if the seller\'s own visible notes already contain it.',
            '- Quote money amounts exactly as given; never compute new amounts.',
            '- If information is missing, say the seller will confirm - never guess.',
            '- Greet briefly, answer the question directly, and close politely. One short paragraph, no emojis, no markdown.',
            '- Output JSON only, no code fences.',
        ]);
    }

    /**
     * Facts payload for the model. Customer-visible history only; internal
     * notes are never included.
     *
     * @param  array<string, mixed>  $order
     */
    public static function userPrompt(string $locale, array $order, array $controls): string
    {
        $facts = [
            'locale' => $locale,
            'tone' => $controls['tone'] ?? 'casual',
            'order' => [
                'reference' => (string) ($order['order_reference'] ?? ''),
                'status' => (string) ($order['status'] ?? ''),
                'payment_status' => (string) ($order['payment_status'] ?? ''),
                'currency' => (string) ($order['currency'] ?? 'TZS'),
                'subtotal' => (float) ($order['subtotal'] ?? 0),
                'shipping_cost' => (float) ($order['shipping_cost'] ?? 0),
                'total_amount' => (float) ($order['total_amount'] ?? 0),
                'items' => array_map(fn ($item) => [
                    'name' => (string) ($item['product_name'] ?? ''),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                ], (array) ($order['items'] ?? [])),
            ],
            'customer_question' => (string) ($order['customer_note'] ?? ''),
            'seller_already_visible_notes' => array_values(array_filter((array) ($order['visible_notes'] ?? []))),
            'missing_the_seller_must_supply' => self::missingInfo($order, $locale),
            'max_chars' => self::maxChars(),
        ];

        return "Draft the seller reply for these facts:\n"
            .json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Hard character cap for one reply draft. */
    public static function maxChars(): int
    {
        return max(120, (int) config('ai.reply_draft.max_chars', 600));
    }

    /**
     * Percentages the seller themselves put into a customer-visible note on
     * this order (e.g. "punguzo la 10%"). Anything else is fabricated.
     *
     * @param  array<string, mixed>  $order
     * @return array<int, float>
     */
    public static function allowedPercents(array $order): array
    {
        $percents = [];
        foreach ((array) ($order['visible_notes'] ?? []) as $note) {
            if (preg_match_all('/(\d+(?:\.\d+)?)\s*%/u', (string) $note, $matches) > 0) {
                foreach ($matches[1] as $value) {
                    $percents[] = (float) $value;
                }
            }
        }

        return array_values(array_unique($percents));
    }

    /**
     * Remove this order's reference and bare date strings before the number
     * scan: quoting "DM-20261012-ABC123" or "12/10/2026" is expected and is
     * not a money claim.
     *
     * @param  array<string, mixed>  $order
     */
    public static function withoutReferences(string $text, array $order): string
    {
        $ref = trim((string) ($order['order_reference'] ?? ''));
        if ($ref !== '') {
            $text = str_replace($ref, ' ', $text);
        }

        // Any DM-YYYYMMDD-XXXXXX style reference, even if re-spaced.
        $text = preg_replace('/\bDM\s*-\s*\d{8}\s*-\s*[A-Z0-9]{4,8}\b/iu', ' ', $text) ?? $text;
        // Bare dates (2026-10-12, 12/10/2026, 12.10.26).
        $text = preg_replace('/\b\d{4}-\d{1,2}-\d{1,2}\b/', ' ', $text) ?? $text;
        $text = preg_replace('/\b\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}\b/', ' ', $text) ?? $text;

        return $text;
    }

    /**
     * True when the text contains a money-scale number (>= 100) or a
     * percentage that is not in the allowed sets. Thousands separators are
     * stripped first so "5,000" compares as 5000.
     *
     * @param  array<int, float>  $allowedNumbers
     * @param  array<int, float>  $allowedPercents
     */
    public static function hasUnfactedNumber(string $text, array $allowedNumbers, array $allowedPercents): bool
    {
        $normalized = preg_replace('/(?<=\d)(?:,\s*)?(?=\d{3}(?!\d))/u', '', $text) ?? $text;

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

        $withoutPercents = preg_replace('/\d+(?:\.\d+)?\s*%/u', ' ', $normalized) ?? $normalized;

        if (preg_match_all('/\d+(?:\.\d+)?/u', $withoutPercents, $matches) > 0) {
            foreach ($matches[0] as $token) {
                $value = (float) $token;
                if ($value < 100) {
                    continue; // counts, quantities, day numbers - not money-scale claims
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

    /**
     * Validate and shape one model draft. Pure function (no I/O) so the full
     * grounding contract is unit-testable.
     *
     * @param  array<string, mixed>|null  $model
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $controls
     * @return array{ok: bool, text: string, flags: array<int, array{code: string, message: string}>, code: ?string, reason: ?string}
     */
    public static function sanitize(?array $model, array $order, array $controls, string $locale): array
    {
        $raw = is_array($model) ? trim((string) ($model['text'] ?? '')) : '';
        if ($raw === '') {
            return self::fail(self::FAIL_EMPTY, $locale);
        }

        $text = self::clean($raw);
        if ($text === '') {
            return self::fail(self::FAIL_EMPTY, $locale);
        }

        // A money-scale number that is not part of this order is fatal: a
        // wrong price in a personal reply is worse than no reply. Order
        // references and bare dates are not money claims, so they are removed
        // from the scan first (quoting the reference is expected behaviour).
        if (self::hasUnfactedNumber(self::withoutReferences($text, $order), self::allowedNumbers($order), self::allowedPercents($order))) {
            return self::fail(self::FAIL_AMOUNT, $locale);
        }

        $flags = [];

        // Promises and claims may be restated only from the seller's own
        // customer-visible notes; anything else is stripped sentence by
        // sentence and honestly flagged.
        [$text, $promiseStripped] = self::stripSentences(
            $text,
            self::PROMISE_PATTERNS,
            self::sellerHasTimingNote($order)
        );
        if ($promiseStripped > 0) {
            $flags[] = self::flag(self::FLAG_PROMISE_REMOVED, $locale);
        }

        [$text, $claimStripped] = self::stripSentences(
            $text,
            self::CLAIM_PATTERNS,
            self::sellerHasClaimNote($order)
        );
        if ($claimStripped > 0) {
            $flags[] = self::flag(self::FLAG_DISCOUNT_REMOVED, $locale);
        }

        $text = self::clean($text);
        if ($text === '') {
            return self::fail(self::FAIL_EMPTY, $locale);
        }

        $cap = self::maxChars();
        if (mb_strlen($text) > $cap) {
            $text = self::clip($text, $cap);
            $flags[] = self::flag(self::FLAG_TRUNCATED, $locale);
        }

        $duplicate = self::duplicateOf($text, $order);
        if ($duplicate !== null) {
            $flags[] = self::flag(self::FLAG_DUPLICATE, $locale);
        }

        return [
            'ok' => true,
            'text' => $text,
            'flags' => $flags,
            'code' => null,
            'reason' => null,
        ];
    }

    /**
     * Does any customer-visible note on this order already contain a delivery
     * timing? Only the seller's own recorded words unlock timing language.
     *
     * @param  array<string, mixed>  $order
     */
    public static function sellerHasTimingNote(array $order): bool
    {
        foreach ((array) ($order['visible_notes'] ?? []) as $note) {
            if (self::matchesAny((string) $note, self::PROMISE_PATTERNS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public static function sellerHasClaimNote(array $order): bool
    {
        foreach ((array) ($order['visible_notes'] ?? []) as $note) {
            if (self::matchesAny((string) $note, self::CLAIM_PATTERNS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deterministic missing-information notices: things only the seller can
     * supply and the assistant must never invent.
     *
     * @param  array<string, mixed>  $order
     * @return array<int, array{code: string, message: string}>
     */
    public static function missingInfo(array $order, string $locale): array
    {
        $missing = [];

        $status = (string) ($order['status'] ?? '');
        $historyHasTiming = self::sellerHasTimingNote($order);
        if (! in_array($status, ['delivered', 'completed'], true) && ! $historyHasTiming) {
            $missing[] = self::missing(self::MISSING_NO_TIMING, $locale);
        }

        $payment = (string) ($order['payment_status'] ?? '');
        if (! in_array($payment, ['paid', 'completed'], true)) {
            $missing[] = self::missing(self::MISSING_UNPAID, $locale);
        }

        return $missing;
    }

    /**
     * Duplicate-send guard: compare the draft against the latest note the
     * seller already sent to this customer. Returns the previous note when the
     * draft is (nearly) identical to it.
     *
     * @param  array<string, mixed>  $order
     * @return array{note: string, at: ?string}|null
     */
    public static function duplicateOf(string $text, array $order): ?array
    {
        $notes = array_values(array_filter((array) ($order['visible_notes'] ?? [])));
        if ($notes === []) {
            return null;
        }

        $previous = (string) end($notes);
        if (mb_strlen($previous) < 12) {
            return null; // too short to meaningfully duplicate
        }

        $a = self::normalize($text);
        $b = self::normalize($previous);
        if ($a === '' || $b === '') {
            return null;
        }

        $identical = $a === $b;
        $contained = mb_strlen($b) >= 20 && (str_contains($a, $b) || str_contains($b, $a));

        if (! $identical && ! $contained) {
            return null;
        }

        return [
            'note' => $previous,
            'at' => ($order['last_visible_note_at'] ?? null) ?: null,
        ];
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

    /** Localized limitation lines for the response. */
    public static function limitations(string $locale): array
    {
        return self::LIMITATIONS[$locale] ?? self::LIMITATIONS['en'];
    }

    /**
     * Remove sentences matching any pattern unless $allowed unlocks them.
     *
     * @param  array<int, string>  $patterns
     * @return array{0: string, 1: int} [text, stripped count]
     */
    private static function stripSentences(string $text, array $patterns, bool $allowed): array
    {
        if ($allowed) {
            return [$text, 0];
        }

        $sentences = preg_split('/(?<=[.!?।।。])\s+/u', $text) ?: [$text];
        $kept = [];
        $stripped = 0;
        foreach ($sentences as $sentence) {
            if (self::matchesAny($sentence, $patterns)) {
                $stripped++;

                continue;
            }
            $kept[] = $sentence;
        }

        return [trim(implode(' ', $kept)), $stripped];
    }

    /** @param array<int, string> $patterns */
    private static function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function flag(string $code, string $locale): array
    {
        $table = self::FLAG_MESSAGES[$code] ?? null;
        $message = $table[$locale] ?? ($table['en'] ?? $code);

        return ['code' => $code, 'message' => $message];
    }

    private static function missing(string $code, string $locale): array
    {
        $table = self::MISSING_MESSAGES[$code] ?? null;
        $message = $table[$locale] ?? ($table['en'] ?? $code);

        return ['code' => $code, 'message' => $message];
    }

    private static function fail(string $code, string $locale): array
    {
        $table = self::FAIL_REASONS[$code] ?? null;
        $reason = $table[$locale] ?? ($table['en'] ?? $code);

        return [
            'ok' => false,
            'text' => '',
            'flags' => [],
            'code' => $code,
            'reason' => $reason,
        ];
    }
}
