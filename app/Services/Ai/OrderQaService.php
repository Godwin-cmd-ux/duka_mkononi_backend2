<?php

namespace App\Services\Ai;

/**
 * Capability #13 - Order & Delivery Q&A (grounding layer).
 *
 * Builds the trusted context for one customer's own orders and produces a
 * deterministic localized answer when the model is unavailable. Only fields
 * the system actually recorded are ever included: statuses come from
 * `order_status_history`, notes are customer-visible notes only (internal
 * notes are never read here), and money comes from the stored order rows.
 */
class OrderQaService
{
    public const CAPABILITY = 'order_qa';

    /**
     * Find an order reference (DM-YYYYMMDD-XXXXXX) mentioned in the question.
     */
    public static function extractReference(string $question): ?string
    {
        // Case-insensitive: sellers paste references typed in lower/mixed
        // case; the canonical uppercased form is returned.
        if (preg_match('/DM-\d{8}-[A-Za-z0-9]{6}/iu', $question, $matches) === 1) {
            return mb_strtoupper($matches[0]);
        }

        return null;
    }

    /**
     * Choose the order the answer is about: the referenced one, else the most
     * recent. Only ever called with the caller's own orders.
     *
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<string, mixed>|null
     */
    public static function focusOrder(array $orders, ?string $reference): ?array
    {
        if ($orders === []) {
            return null;
        }

        if ($reference !== null && $reference !== '') {
            foreach ($orders as $order) {
                if (mb_strtoupper((string) ($order['order_reference'] ?? '')) === $reference) {
                    return $order;
                }
            }

            return null;
        }

        return $orders[0];
    }

    /**
     * Localized label for a recorded status (falls back to the raw status so
     * an unknown value is shown honestly rather than replaced by a guess).
     */
    public static function statusLabel(string $status, string $locale): string
    {
        $pack = self::STATUS_LABELS[$status] ?? null;
        if ($pack === null) {
            return $status;
        }

        return $pack[$locale] ?? ($pack['en'] ?? $status);
    }

    /**
     * The ordered status vocabulary the platform can record, for the UI's
     * "what do the states mean" list.
     *
     * @return array<int, array{status: string, label: string}>
     */
    public static function statusGuide(string $locale): array
    {
        $guide = [];
        foreach (\App\Services\OrderService::STATUSES as $status) {
            $guide[] = [
                'status' => $status,
                'label' => self::statusLabel($status, $locale),
            ];
        }

        return $guide;
    }

    /**
     * Deterministic answer built only from recorded fields. Used verbatim when
     * the provider is disabled/unavailable, and as the honest base otherwise.
     *
     * @param  array<string, mixed>  $order  shaped like OrderController::trackingOrder
     */
    public static function fallbackAnswer(array $order, string $locale): string
    {
        $texts = self::ANSWER[$locale] ?? self::ANSWER['en'];

        $reference = (string) ($order['order_reference'] ?? '—');
        $date = mb_substr((string) ($order['order_date'] ?? ''), 0, 10);
        $status = (string) ($order['status'] ?? '');
        $statusLabel = self::statusLabel($status, $locale);
        $total = number_format((float) ($order['total_amount'] ?? 0), 0, '.', ',');
        $currency = (string) ($order['currency'] ?? 'TZS');
        $business = (string) ($order['business_name'] ?? '');
        $itemCount = is_array($order['items'] ?? null) ? count($order['items']) : 0;

        $answer = sprintf($texts['base'], $reference, $date, $statusLabel, $total, $currency, $business !== '' ? $business : '—', (string) $itemCount);

        $timeline = is_array($order['timeline'] ?? null) ? $order['timeline'] : [];
        $latest = $timeline[0] ?? null;
        if (is_array($latest) && ! empty($latest['status'])) {
            $answer .= ' '.sprintf(
                $texts['update'],
                self::statusLabel((string) $latest['status'], $locale),
                mb_substr((string) ($latest['at'] ?? ''), 0, 10)
            );
        }

        $note = (string) ($order['latest_note'] ?? '');
        if ($note !== '') {
            $answer .= ' '.sprintf($texts['note'], mb_substr($note, 0, 200));
        }

        return mb_substr($answer, 0, 800);
    }

    /** Localized message when the account's phone has no orders at all. */
    public static function noOrdersText(string $locale): string
    {
        return self::MESSAGES['no_orders'][$locale] ?? self::MESSAGES['no_orders']['en'];
    }

    /** Localized message when the account has no usable phone number. */
    public static function noPhoneText(string $locale): string
    {
        return self::MESSAGES['no_phone'][$locale] ?? self::MESSAGES['no_phone']['en'];
    }

    /** Localized message when a stated reference is not among the caller's orders. */
    public static function referenceNotFoundText(string $reference, string $locale): string
    {
        $template = self::MESSAGES['reference_not_found'][$locale] ?? self::MESSAGES['reference_not_found']['en'];

        return sprintf($template, $reference);
    }

    /** Honest, localized limitation lines for the response. */
    public static function limitations(string $locale): array
    {
        return self::LIMITATIONS[$locale] ?? self::LIMITATIONS['en'];
    }

    /**
     * Structured facts for the model: only recorded fields, customer-visible
     * notes only, bounded item/timeline sizes.
     *
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public static function contextFor(array $order, string $locale): array
    {
        $items = [];
        foreach (array_slice((array) ($order['items'] ?? []), 0, 20) as $item) {
            $items[] = [
                'name' => (string) ($item['product_name'] ?? ($item['name'] ?? '')),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'line_total' => (float) ($item['line_total'] ?? ($item['total_price'] ?? 0)),
            ];
        }

        $timeline = [];
        foreach (array_slice((array) ($order['timeline'] ?? []), 0, 8) as $entry) {
            $timeline[] = [
                'status' => (string) ($entry['status'] ?? ''),
                'status_label' => self::statusLabel((string) ($entry['status'] ?? ''), $locale),
                'note' => isset($entry['note']) ? mb_substr((string) $entry['note'], 0, 200) : null,
                'at' => (string) ($entry['at'] ?? ''),
            ];
        }

        return [
            'order_reference' => (string) ($order['order_reference'] ?? ''),
            'order_date' => (string) ($order['order_date'] ?? ''),
            'status' => (string) ($order['status'] ?? ''),
            'status_label' => self::statusLabel((string) ($order['status'] ?? ''), $locale),
            'subtotal' => (float) ($order['subtotal'] ?? 0),
            'shipping_cost' => (float) ($order['shipping_cost'] ?? 0),
            'total_amount' => (float) ($order['total_amount'] ?? 0),
            'currency' => (string) ($order['currency'] ?? 'TZS'),
            'business_name' => (string) ($order['business_name'] ?? ''),
            'items' => $items,
            'latest_note' => isset($order['latest_note']) ? mb_substr((string) $order['latest_note'], 0, 200) : null,
            'timeline' => $timeline,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Copy (8 locales, English fallback)                                  */
    /* ------------------------------------------------------------------ */

    private const STATUS_LABELS = [
        'pending' => [
            'sw' => 'Inasubiri kuthibitishwa', 'en' => 'Pending', 'fr' => 'En attente',
            'hi' => 'लंबित', 'es' => 'Pendiente', 'ur' => 'زیر التوا',
            'de' => 'Ausstehend', 'zh' => '待处理',
        ],
        'confirmed' => [
            'sw' => 'Imethibitishwa', 'en' => 'Confirmed', 'fr' => 'Confirmée',
            'hi' => 'पुष्टि', 'es' => 'Confirmada', 'ur' => 'تصدیق شدہ',
            'de' => 'Bestätigt', 'zh' => '已确认',
        ],
        'processing' => [
            'sw' => 'Inaandaliwa', 'en' => 'Processing', 'fr' => 'En cours',
            'hi' => 'प्रक्रियाधीन', 'es' => 'En proceso', 'ur' => 'عمل جاری',
            'de' => 'In Bearbeitung', 'zh' => '处理中',
        ],
        'ready' => [
            'sw' => 'Tayari', 'en' => 'Ready', 'fr' => 'Prête',
            'hi' => 'तैयार', 'es' => 'Lista', 'ur' => 'تیار',
            'de' => 'Bereit', 'zh' => '已备货',
        ],
        'out_for_delivery' => [
            'sw' => 'Njiani kufika', 'en' => 'Out for delivery', 'fr' => 'En livraison',
            'hi' => 'डिलीवरी के लिए निकला', 'es' => 'En reparto', 'ur' => 'روانہ ہونے والا',
            'de' => 'Unterwegs', 'zh' => '配送中',
        ],
        'delivered' => [
            'sw' => 'Imefika', 'en' => 'Delivered', 'fr' => 'Livrée',
            'hi' => 'पहुँच गया', 'es' => 'Entregada', 'ur' => 'پہنچ گئی',
            'de' => 'Zugestellt', 'zh' => '已送达',
        ],
        'completed' => [
            'sw' => 'Imekamilika', 'en' => 'Completed', 'fr' => 'Terminée',
            'hi' => 'पूर्ण', 'es' => 'Completada', 'ur' => 'مکمل',
            'de' => 'Abgeschlossen', 'zh' => '已完成',
        ],
        'cancelled' => [
            'sw' => 'Imeghairiwa', 'en' => 'Cancelled', 'fr' => 'Annulée',
            'hi' => 'रद्द', 'es' => 'Cancelada', 'ur' => 'منسوخ',
            'de' => 'Storniert', 'zh' => '已取消',
        ],
    ];

    /**
     * Deterministic answer parts: base (ref, date, status, total, currency,
     * business, item_count), update (status, date), note (text).
     */
    private const ANSWER = [
        'sw' => [
            'base' => 'Oda yako %s iliyowekwa %s iko hali ya %s, jumla %s %s kutoka %s, bidhaa %s.',
            'update' => 'Sasisho la hivi karibuni: %s tarehe %s.',
            'note' => 'Kuhusu: %s',
        ],
        'en' => [
            'base' => 'Your order %s placed on %s is currently %s, totalling %s %s from %s, %s item(s).',
            'update' => 'Latest update: %s on %s.',
            'note' => 'Note: %s',
        ],
        'fr' => [
            'base' => 'Votre commande %s du %s est actuellement %s, pour un total de %s %s de %s, %s article(s).',
            'update' => 'Dernière mise à jour : %s le %s.',
            'note' => 'Note : %s',
        ],
        'hi' => [
            'base' => 'आपका ऑर्डर %s जो %s को दिया गया था वह %s है, कुल %s %s से, %s वस्तु(एँ)।',
            'update' => 'नवीनतम अपडेट: %s, %s।',
            'note' => 'टिप्पणी: %s',
        ],
        'es' => [
            'base' => 'Tu pedido %s del %s está %s, por un total de %s %s de %s, %s artículo(s).',
            'update' => 'Última actualización: %s el %s.',
            'note' => 'Nota: %s',
        ],
        'ur' => [
            'base' => 'آپ کا آرڈر %s جو %s کو دیا گیا تھا وہ %s ہے، کل %s %s سے، %s اشیاء۔',
            'update' => 'حالیہ اپڈیٹ: %s، %s۔',
            'note' => 'نوٹ: %s',
        ],
        'de' => [
            'base' => 'Deine Bestellung %s vom %s ist derzeit %s, insgesamt %s %s von %s, %s Artikel.',
            'update' => 'Letzte Aktualisierung: %s am %s.',
            'note' => 'Hinweis: %s',
        ],
        'zh' => [
            'base' => '您的订单 %s（%s 下单）当前状态为 %s，总额 %s %s，来自 %s，共 %s 件商品。',
            'update' => '最新更新：%s，%s。',
            'note' => '备注：%s',
        ],
    ];

    private const MESSAGES = [
        'no_orders' => [
            'sw' => 'Hakuna oda zilizopatikana kwa namba ya simu ya akaunti yako. Tafadhali tumia ukurasa wa ufuatiliaji na namba uliyotumia kuagiza.',
            'en' => 'No orders were found for your account phone number. Please use the tracking page with the number you ordered with.',
            'fr' => 'Aucune commande trouvée pour le numéro de votre compte. Veuillez utiliser la page de suivi avec le numéro utilisé pour commander.',
            'hi' => 'आपके खाते के फ़ोन नंबर पर कोई ऑर्डर नहीं मिला। कृपया ट्रैकिंग पेज पर ऑर्डर किए गए नंबर से उपयोग करें।',
            'es' => 'No se encontraron pedidos para el número de tu cuenta. Usa la página de seguimiento con el número con el que pediste.',
            'ur' => 'آپ کے اکاؤنٹ کے فون نمبر پر کوئی آرڈر نہیں ملا۔ براہِ کرم ٹریکنگ صفحے پر آرڈر والے نمبر سے استعمال کریں۔',
            'de' => 'Für deine Kontonummer wurden keine Bestellungen gefunden. Nutze die Sendungsverfolgungsseite mit der Nummer, mit der bestellt wurde.',
            'zh' => '未找到您账户手机号对应的订单。请使用下单时的手机号在追踪页面查询。',
        ],
        'no_phone' => [
            'sw' => 'Akaunti yako haina namba ya simu sahihi. Weka namba kwenye profaili au tumia ukurasa wa ufuatiliaji na namba uliyotumia kuagiza.',
            'en' => 'Your account has no valid phone number. Add one to your profile, or use the tracking page with the number you ordered with.',
            'fr' => 'Votre compte n’a pas de numéro de téléphone valide. Ajoutez-en un à votre profil, ou utilisez la page de suivi avec le numéro de commande.',
            'hi' => 'आपके खाते में मान्य फ़ोन नंबर नहीं है। प्रोफ़ाइल में जोड़ें, या ऑर्डर वाले नंबर से ट्रैकिंग पेज का उपयोग करें।',
            'es' => 'Tu cuenta no tiene un número de teléfono válido. Añádelo a tu perfil o usa la página de seguimiento con el número con el que pediste.',
            'ur' => 'آپ کے اکاؤنٹ میں درست فون نمبر نہیں ہے۔ پروفائل میں شامل کریں یا آرڈر والے نمبر سے ٹریکنگ صفحہ استعمال کریں۔',
            'de' => 'Dein Konto hat keine gültige Telefonnummer. Ergänze sie im Profil oder nutze die Sendungsverfolgung mit der Bestellnummer.',
            'zh' => '您的账户没有有效手机号。请在资料中添加，或使用下单时的手机号在追踪页面查询。',
        ],
        'reference_not_found' => [
            'sw' => 'Hakuna oda yenye kumbukumbu %s kwenye akaunti yako.',
            'en' => 'No order with reference %s exists on your account.',
            'fr' => 'Aucune commande avec la référence %s n’existe sur votre compte.',
            'hi' => 'आपके खाते में संदर्भ %s वाला कोई ऑर्डर नहीं है।',
            'es' => 'No existe ningún pedido con la referencia %s en tu cuenta.',
            'ur' => 'آپ کے اکاؤنٹ میں حوالہ %s کا کوئی آرڈر نہیں ہے۔',
            'de' => 'Auf deinem Konto gibt keine Bestellung mit der Referenz %s.',
            'zh' => '您的账户中没有编号 %s 的订单。',
        ],
    ];

    private const LIMITATIONS = [
        'sw' => [
            'Hali za oda zinatoka kwenye rekodi zilizopo pekee; hakuna hali mpya inayokadiriwa.',
            'Maswali ya udhibiti wa nje ya akaunti yako hayajibiwi kwenye kipengele hiki.',
        ],
        'en' => [
            'Order statuses come only from recorded system states; no status is ever guessed.',
            'Questions about orders outside your account are not answered by this feature.',
        ],
        'fr' => [
            'Les statuts proviennent uniquement des états enregistrés ; aucun statut n’est deviné.',
            'Les commandes hors de votre compte ne sont pas traitées par cette fonction.',
        ],
        'hi' => [
            'ऑर्डर स्थितियाँ केवल दर्ज सिस्टम स्थितियों से आती हैं; कोई स्थिति अनुमानित नहीं की जाती।',
            'आपके खाते से बाहर के ऑर्डर के प्रश्न इस सुविधा में उत्तरित नहीं होते।',
        ],
        'es' => [
            'Los estados provienen solo de estados registrados; nunca se adivina un estado.',
            'Las preguntas sobre pedidos fuera de tu cuenta no las responde esta función.',
        ],
        'ur' => [
            'آرڈر کی حالتیں صرف درج سسٹم حالتیں ہیں؛ کوئی حالت اندازہ نہیں لگائی جاتی۔',
            'آپ کے اکاؤنٹ سے باہر کے آرڈر کے سوالات اس خصوصیت کا جواب نہیں پاتے۔',
        ],
        'de' => [
            'Statuswerte stammen ausschließlich aus erfassten Systemzuständen; nichts wird geraten.',
            'Fragen zu Bestellungen außerhalb deines Kontos beantwortet diese Funktion nicht.',
        ],
        'zh' => [
            '订单状态仅来自系统记录的状态，绝不猜测。',
            '本功能不回答您账户之外的订单问题。',
        ],
    ];
}
