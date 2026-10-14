<?php

namespace App\Services\Ai;

/**
 * Capability #14 - Personalised Recommendations (deterministic ranking).
 *
 * The ranking is pure code by design - no model is consulted - so every
 * recommendation can be explained by a signal that actually exists:
 *
 *  - candidates come from the PUBLIC catalogue only (PublicCatalog has
 *    already applied is_public / visibility filters before we see a row);
 *  - hard gates first: in-stock only (available && stock > 0) and the
 *    caller's own dismissals - a dismissed item can never reappear;
 *  - personal signals are ONLY what the caller themselves produced:
 *    their own order lines (matched server-side by their account phone),
 *    their own ad likes, and their own profile location when present.
 *    Nothing here ever reads another customer's behaviour, and popularity
 *    inputs are aggregate counts (never individual rows);
 *  - every returned item carries a reason code + localized text built from
 *    a real signal (never a guess); with no history the engine honestly
 *    falls back to popularity/certification ("cold start");
 *  - ordering is fully deterministic: score desc, then name, then id.
 *
 * Inputs are plain arrays (rows), so the whole contract is unit-testable
 * without HTTP or a database.
 */
class RecommendationEngine
{
    public const CAPABILITY = 'recommendations';

    /* Signal weights: documented, stable, and pinned by unit tests. */
    private const W_BOUGHT_PRODUCT = 6.0;

    /* Loyalty to a specific shop outranks the generic same-category signal
     * so the EXPLANATION stays the most specific real one. */
    private const W_BOUGHT_BUSINESS = 4.5;

    private const W_CATEGORY = 4.0;

    private const W_LIKED_CATEGORY = 2.0;

    private const W_TOKEN = 1.2;

    private const W_LIKED_BUSINESS = 2.5;

    private const W_LOCATION = 1.5;

    private const W_POPULARITY = 2.0;

    private const W_CERTIFIED = 0.5;

    private const W_BREADTH = 0.8;

    /* Popularity is log-damped so one runaway product cannot bury
     * everything else; 100 units sold already reaches the cap. */
    private const POPULARITY_CAP = 100.0;

    /** Filler words in any supported language that carry no product meaning. */
    private const STOPWORDS = [
        'cha', 'za', 'wa', 'na', 'ya', 'kwa', 'nia', 'bidhaa', 'item', 'the',
        'and', 'for', 'with', 'from', 'that', 'this', 'are', 'was', 'new',
    ];

    /* ------------------------------------------------------------------ */
    /* Localized server-side strings                                        */
    /* ------------------------------------------------------------------ */

    /** Every key must exist for all eight locales (unit-tested). */
    public const MESSAGES = [
        'sw' => [
            'reason_bought_before' => 'Ulishanunua hii hapo awahi.',
            'reason_similar' => 'Sawa na "{detail}" uliyonunua.',
            'reason_same_shop' => 'Kutoka duka {detail} ulilonunua hapo awahi.',
            'reason_liked_ad' => 'Unapenda matangazo kutoka duka {detail}.',
            'reason_near_you' => 'Karibu nawe - {detail}.',
            'reason_popular' => 'Imeuzwa {count} mara kwenye jukwaa.',
            'reason_certified' => 'Duka lililothibitishwa: {detail}.',
            'reason_bought_from' => 'Ulishanunua kutoka duka hili.',
            'reason_stocked' => 'Bidhaa {count} zinazopatikana sasa.',
            'empty_products' => 'Hakuna bidhaa zinazopendekezwa sasa.',
            'empty_businesses' => 'Hakuna biashara zinazopendekezwa sasa.',
            'privacy_note' => 'Mapendekezo yanatumia historia yako pekee (ununuzi na matangazo uliyopenda); hakuna mtu mwingine anayeona taarifa zako.',
            'new_user_note' => 'Hakuna historia ya kutosha bado - tunaonyesha maarufu na vilivyothibitishwa.',
            'dismiss_ok' => 'Mapendekezo hayo yamefichwa.',
            'feedback_ok' => 'Asante kwa maoni yako.',
            'err_rating' => 'Kiwango cha maoni si sahihi.',
            'err_id' => 'Kitambulicho si sahihi.',
            'err_type' => 'Aina si sahihi.',
        ],
        'en' => [
            'reason_bought_before' => 'You bought this before.',
            'reason_similar' => 'Similar to "{detail}" you bought.',
            'reason_same_shop' => 'From the shop {detail} you bought from before.',
            'reason_liked_ad' => 'You liked an ad from shop {detail}.',
            'reason_near_you' => 'Close to you - {detail}.',
            'reason_popular' => 'Sold {count} times on the platform.',
            'reason_certified' => 'Certified shop: {detail}.',
            'reason_bought_from' => 'You have bought from this shop.',
            'reason_stocked' => '{count} products available now.',
            'empty_products' => 'No products to recommend right now.',
            'empty_businesses' => 'No shops to recommend right now.',
            'privacy_note' => 'Recommendations use only your own history (your purchases and the ads you liked); no other person sees your data.',
            'new_user_note' => 'Not enough history yet - showing popular and certified items.',
            'dismiss_ok' => 'Those recommendations are hidden.',
            'feedback_ok' => 'Thank you for the feedback.',
            'err_rating' => 'Feedback value is not valid.',
            'err_id' => 'The identifier is not valid.',
            'err_type' => 'The type is not valid.',
        ],
        'fr' => [
            'reason_bought_before' => 'Vous avez déjà acheté ceci.',
            'reason_similar' => 'Similaire à "{detail}" que vous avez acheté.',
            'reason_same_shop' => 'De la boutique {detail} chez laquelle vous avez déjà acheté.',
            'reason_liked_ad' => 'Vous avez aimé une publicité de la boutique {detail}.',
            'reason_near_you' => 'Près de chez vous - {detail}.',
            'reason_popular' => 'Vendu {count} fois sur la plateforme.',
            'reason_certified' => 'Boutique certifiée : {detail}.',
            'reason_bought_from' => 'Vous avez déjà acheté dans cette boutique.',
            'reason_stocked' => '{count} produits disponibles maintenant.',
            'empty_products' => 'Aucun produit à recommander pour le moment.',
            'empty_businesses' => 'Aucune boutique à recommander pour le moment.',
            'privacy_note' => "Les recommandations n'utilisent que votre propre historique (vos achats et les publicités que vous avez aimées) ; personne d'autre ne voit vos données.",
            'new_user_note' => "Historique insuffisant - nous montrons les articles populaires et certifiés.",
            'dismiss_ok' => 'Ces recommandations sont masquées.',
            'feedback_ok' => 'Merci pour votre retour.',
            'err_rating' => "La valeur du retour n'est pas valide.",
            'err_id' => "L'identifiant n'est pas valide.",
            'err_type' => "Le type n'est pas valide.",
        ],
        'hi' => [
            'reason_bought_before' => 'आपने यह पहले खरीदा है।',
            'reason_similar' => 'आपने जो "{detail}" खरीदा था, उसके समान।',
            'reason_same_shop' => '{detail} की दुकान से, जहाँ से आपने पहले खरीदा है।',
            'reason_liked_ad' => 'आपने {detail} की दुकान का विज्ञापन पसंद किया है।',
            'reason_near_you' => 'आपके पास - {detail}।',
            'reason_popular' => 'प्लेटफ़ॉर्म पर {count} बार बिका।',
            'reason_certified' => 'प्रमाणित दुकान: {detail}।',
            'reason_bought_from' => 'आपने इस दुकान से खरीदा है।',
            'reason_stocked' => 'अभी {count} उत्पाद उपलब्ध हैं।',
            'empty_products' => 'अभी सिफ़ारिश करने के लिए कोई उत्पाद नहीं।',
            'empty_businesses' => 'अभी सिफ़ारिश करने के लिए कोई दुकान नहीं।',
            'privacy_note' => 'सिफ़ारिशाँ केवल आपके अपने इतिहास (आपकी खरीदारी और आपको पसंद आए विज्ञापन) का उपयोग करती हैं; आपका डेटा कोई और नहीं देखता।',
            'new_user_note' => 'अभी पर्याप्त इतिहास नहीं - लोकप्रिय और प्रमाणित वस्तुएँ दिखा रहे हैं।',
            'dismiss_ok' => 'वे सिफ़ारिशाँ छिपा दी गई हैं।',
            'feedback_ok' => 'आपकी प्रतिक्रिया के लिए धन्यवाद।',
            'err_rating' => 'प्रतिक्रिया मान मान्य नहीं है।',
            'err_id' => 'पहचान मान्य नहीं है।',
            'err_type' => 'प्रकार मान्य नहीं है।',
        ],
        'es' => [
            'reason_bought_before' => 'Ya compraste esto antes.',
            'reason_similar' => 'Similar a "{detail}" que compraste.',
            'reason_same_shop' => 'De la tienda {detail} de la que ya compraste.',
            'reason_liked_ad' => 'Te gustó un anuncio de la tienda {detail}.',
            'reason_near_you' => 'Cerca de ti - {detail}.',
            'reason_popular' => 'Se vendió {count} veces en la plataforma.',
            'reason_certified' => 'Tienda certificada: {detail}.',
            'reason_bought_from' => 'Ya has comprado en esta tienda.',
            'reason_stocked' => '{count} productos disponibles ahora.',
            'empty_products' => 'No hay productos que recomendar ahora mismo.',
            'empty_businesses' => 'No hay tiendas que recomendar ahora mismo.',
            'privacy_note' => 'Las recomendaciones usan solo tu propio historial (tus compras y los anuncios que te gustaron); nadie más ve tus datos.',
            'new_user_note' => 'Aún no hay historial suficiente - mostramos populares y certificados.',
            'dismiss_ok' => 'Esas recomendaciones están ocultas.',
            'feedback_ok' => 'Gracias por tu comentario.',
            'err_rating' => 'El valor del comentario no es válido.',
            'err_id' => 'El identificador no es válido.',
            'err_type' => 'El tipo no es válido.',
        ],
        'ur' => [
            'reason_bought_before' => 'آپ نے یہ پہلے خریدا ہے۔',
            'reason_similar' => 'آپ جو "{detail}" خریدتے تھے اس کی مانند۔',
            'reason_same_shop' => '{detail} کی دکان سے، جہاں سے آپ نے پہلے خریدا ہے۔',
            'reason_liked_ad' => 'آپ نے {detail} کی دکان کا اشتہار پسند کیا ہے۔',
            'reason_near_you' => 'آپ کے قریب - {detail}۔',
            'reason_popular' => 'پلیٹ فارم پر {count} بار فروخت ہوا۔',
            'reason_certified' => 'تصدیق شدہ دکان: {detail}۔',
            'reason_bought_from' => 'آپ نے اس دکان سے خریدا ہے۔',
            'reason_stocked' => 'ابھی {count} مصنوعات دستیاب ہیں۔',
            'empty_products' => 'ابھی تجویز کے لیے کوئی مصنوعات نہیں۔',
            'empty_businesses' => 'ابھی تجویز کے لیے کوئی دکانیں نہیں۔',
            'privacy_note' => 'تجویزات صرف آپ کی اپنی تاریخ (آپ کی خریداری اور آپ کو پسند آنے والے اشتہارات) استعمال کرتی ہیں؛ آپ کا ڈیٹا کوئی اور نہیں دیکھتا۔',
            'new_user_note' => 'ابھی کافی تاریخ نہیں - مقبول اور تصدیق شدہ چیزیں دکھا رہے ہیں۔',
            'dismiss_ok' => 'وہ تجویزات چھپا دی گئی ہیں۔',
            'feedback_ok' => 'آپ کی رائے کا شکریہ۔',
            'err_rating' => 'رائے کی قیمت درست نہیں ہے۔',
            'err_id' => 'شناخت درست نہیں ہے۔',
            'err_type' => 'قسم درست نہیں ہے۔',
        ],
        'de' => [
            'reason_bought_before' => 'Das haben Sie vorher gekauft.',
            'reason_similar' => 'Ähnlich wie "{detail}", das Sie gekauft haben.',
            'reason_same_shop' => 'Aus dem Shop {detail}, bei dem Sie schon gekauft haben.',
            'reason_liked_ad' => 'Sie haben die Anzeige von Shop {detail} gemocht.',
            'reason_near_you' => 'In Ihrer Nähe - {detail}.',
            'reason_popular' => '{count} Mal auf der Plattform verkauft.',
            'reason_certified' => 'Zertifizierter Shop: {detail}.',
            'reason_bought_from' => 'Sie haben hier schon gekauft.',
            'reason_stocked' => '{count} Produkte sofort verfügbar.',
            'empty_products' => 'Zurzeit keine Produkte zum Empfehlen.',
            'empty_businesses' => 'Zurzeit keine Shops zum Empfehlen.',
            'privacy_note' => 'Empfehlungen nutzen nur Ihre eigene Historie (Ihre Käufe und die Anzeigen, die Sie mögen); niemand sonst sieht Ihre Daten.',
            'new_user_note' => 'Noch nicht genug Historie - wir zeigen beliebte und zertifizierte Artikel.',
            'dismiss_ok' => 'Diese Empfehlungen sind ausgeblendet.',
            'feedback_ok' => 'Danke für Ihr Feedback.',
            'err_rating' => 'Der Feedback-Wert ist ungültig.',
            'err_id' => 'Die Kennung ist ungültig.',
            'err_type' => 'Der Typ ist ungültig.',
        ],
        'zh' => [
            'reason_bought_before' => '您以前买过这个。',
            'reason_similar' => '与您买过的"{detail}"相似。',
            'reason_same_shop' => '来自您买过的商店 {detail}。',
            'reason_liked_ad' => '您喜欢过来自商店 {detail} 的广告。',
            'reason_near_you' => '离您很近 - {detail}。',
            'reason_popular' => '在平台上已售出 {count} 次。',
            'reason_certified' => '认证商店：{detail}。',
            'reason_bought_from' => '您曾在这家店买过东西。',
            'reason_stocked' => '目前有 {count} 件商品在售。',
            'empty_products' => '目前没有可推荐的商品。',
            'empty_businesses' => '目前没有可推荐的商店。',
            'privacy_note' => '推荐只使用您自己的记录（您的购买和您喜欢的广告）；其他人看不到您的数据。',
            'new_user_note' => '记录还不够 - 先展示热门和认证商品。',
            'dismiss_ok' => '这些推荐已隐藏。',
            'feedback_ok' => '感谢您的反馈。',
            'err_rating' => '反馈值无效。',
            'err_id' => '标识符无效。',
            'err_type' => '类型无效。',
        ],
    ];

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

    /* ------------------------------------------------------------------ */
    /* Signals                                                             */
    /* ------------------------------------------------------------------ */

    /** Skeleton with no personal data at all (guest / cold start). */
    public static function emptySignals(): array
    {
        return [
            'purchased_products' => [],
            'purchased_categories' => [],
            'purchased_tokens' => [],
            'purchased_businesses' => [],
            'sample_names' => [],   // category -> one of the caller's own product names
            'liked_categories' => [],
            'liked_businesses' => [],
            'location' => null,
            'history_lines' => 0,
            'likes_count' => 0,
        ];
    }

    /**
     * Build the signal bundle from the caller's OWN rows only.
     *
     * @param array<int, array<string, mixed>> $orderItems rows with product_id,
     *        product_name, business_id, quantity and (optionally) category.
     * @param array<int, array<string, mixed>> $likedAds rows with category and
     *        business_id (already resolved from the caller's own likes).
     */
    public static function buildSignals(array $orderItems, array $likedAds, ?string $location): array
    {
        $s = self::emptySignals();

        foreach ($orderItems as $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $productId = (string) ($row['product_id'] ?? '');
            $businessId = (string) ($row['business_id'] ?? '');
            $name = trim((string) ($row['product_name'] ?? ''));
            $category = mb_strtolower(trim((string) ($row['category'] ?? '')));

            if ($productId !== '') {
                $s['purchased_products'][$productId] = ($s['purchased_products'][$productId] ?? 0) + $qty;
            }
            if ($businessId !== '') {
                $s['purchased_businesses'][$businessId] = ($s['purchased_businesses'][$businessId] ?? 0) + $qty;
            }
            if ($category !== '') {
                $s['purchased_categories'][$category] = ($s['purchased_categories'][$category] ?? 0) + $qty;
                if (! isset($s['sample_names'][$category]) && $name !== '') {
                    $s['sample_names'][$category] = $name;
                }
            }
            foreach (self::tokens($name) as $token) {
                $s['purchased_tokens'][$token] = ($s['purchased_tokens'][$token] ?? 0) + 1;
            }
            if ($name !== '') {
                $s['history_lines'] += 1;
            }
        }

        foreach ($likedAds as $row) {
            $category = mb_strtolower(trim((string) ($row['category'] ?? '')));
            $businessId = (string) ($row['business_id'] ?? '');
            if ($category !== '') {
                $s['liked_categories'][$category] = ($s['liked_categories'][$category] ?? 0) + 1;
            }
            if ($businessId !== '') {
                $s['liked_businesses'][$businessId] = ($s['liked_businesses'][$businessId] ?? 0) + 1;
            }
            $s['likes_count'] += 1;
        }

        $location = trim((string) ($location ?? ''));
        $s['location'] = $location === '' ? null : $location;

        return $s;
    }

    /** True when the caller actually produced personal signals. */
    public static function hasPersonalSignals(array $signals): bool
    {
        return ($signals['history_lines'] ?? 0) > 0
            || ($signals['likes_count'] ?? 0) > 0
            || ($signals['location'] ?? null) !== null;
    }

    /* ------------------------------------------------------------------ */
    /* Ranking                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Rank public, in-stock catalogue rows for one caller.
     *
     * @param array<int, array<string, mixed>> $catalog PublicCatalog::products() rows.
     * @param array<string, int> $dismissed id => timestamp (caller's own).
     * @param array<string, int> $sales aggregate product_id => units sold (counts only).
     * @return array<int, array<string, mixed>> ranked rows with a reason.
     */
    /**
     * Map a real PublicCatalog::products() row onto the shape the ranking
     * gates and the UIs expect. PublicCatalog emits `stock`,
     * `business_is_certified` and `image_url`; the hard gates read
     * `stock_quantity` / `is_certified` and the website/app cards read
     * `image`. Without this mapping every production row would fail the
     * in-stock gate (an always-empty feed) while unit fixtures - which
     * already use the engine shape - kept passing.
     *
     * Rows that already carry the engine keys are left untouched, so an
     * already-normalised row is never rewritten.
     */
    public static function normalizeCatalogRow(array $row): array
    {
        if (! array_key_exists('stock_quantity', $row)) {
            $row['stock_quantity'] = (int) ($row['stock'] ?? 0);
        }
        if (! array_key_exists('is_certified', $row)) {
            $row['is_certified'] = (bool) ($row['business_is_certified'] ?? false);
        }
        if (! array_key_exists('image', $row)) {
            $row['image'] = $row['image_url'] ?? null;
        }

        return $row;
    }

    public static function rankProducts(array $catalog, array $signals, array $dismissed, array $sales, string $locale, int $limit): array
    {
        $ranked = [];
        foreach ($catalog as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || isset($dismissed[$id])) {
                continue;
            }

            /* Hard gates: publicly visible (already enforced upstream),
             * actually available and in stock right now. */
            if (($row['available'] ?? true) === false) {
                continue;
            }
            if ((float) ($row['stock_quantity'] ?? 0) <= 0) {
                continue;
            }

            $score = 0.0;
            $candidates = []; // code => [weight, detail]

            $category = mb_strtolower(trim((string) ($row['category'] ?? '')));
            $businessId = (string) ($row['business_id'] ?? '');
            $businessName = (string) ($row['business_name'] ?? '');
            $tokens = self::tokens((string) ($row['name'] ?? ''));

            $boughtQty = (int) ($signals['purchased_products'][$id] ?? 0);
            if ($boughtQty > 0) {
                $score += self::W_BOUGHT_PRODUCT;
                $candidates['bought_before'] = [self::W_BOUGHT_PRODUCT, null];
            }

            $boughtBusiness = (int) ($signals['purchased_businesses'][$businessId] ?? 0);
            if ($businessId !== '' && $boughtBusiness > 0) {
                $part = self::W_BOUGHT_BUSINESS * min($boughtBusiness, 3) / 3;
                $score += $part;
                $candidates['same_shop'] = [$part, $businessName];
            }

            if ($category !== '') {
                $boughtCat = (int) ($signals['purchased_categories'][$category] ?? 0);
                if ($boughtCat > 0) {
                    $part = self::W_CATEGORY * min($boughtCat, 3) / 3;
                    $score += $part;
                    $candidates['similar'] = [$part, $signals['sample_names'][$category] ?? null];
                }
                $likedCat = (int) ($signals['liked_categories'][$category] ?? 0);
                if ($likedCat > 0) {
                    $score += self::W_LIKED_CATEGORY * min($likedCat, 3) / 3;
                }
            }

            $overlap = 0;
            foreach ($tokens as $token) {
                if (isset($signals['purchased_tokens'][$token])) {
                    $overlap += 1;
                }
            }
            if ($overlap > 0) {
                $part = self::W_TOKEN * min($overlap, 3);
                $score += $part;
                if (! isset($candidates['similar'])) {
                    $candidates['similar'] = [$part, $signals['sample_names'][$category] ?? null];
                }
            }

            $likedBusiness = (int) ($signals['liked_businesses'][$businessId] ?? 0);
            if ($businessId !== '' && $likedBusiness > 0 && $boughtBusiness === 0) {
                $part = self::W_LIKED_BUSINESS * min($likedBusiness, 3) / 3;
                $score += $part;
                $candidates['liked_ad'] = [$part, $businessName];
            }

            $location = $signals['location'] ?? null;
            $rowLocation = trim((string) ($row['business_location'] ?? ''));
            if ($location !== null && $rowLocation !== ''
                && mb_strtolower($location) === mb_strtolower($rowLocation)) {
                $score += self::W_LOCATION;
                $candidates['near_you'] = [self::W_LOCATION, $rowLocation];
            }

            $sold = (int) ($sales[$id] ?? 0);
            if ($sold > 0) {
                $part = self::W_POPULARITY * self::damp($sold);
                $score += $part;
                $candidates['popular'] = [$part, $sold];
            }

            $certified = (bool) ($row['is_certified'] ?? false);
            if ($certified) {
                $score += self::W_CERTIFIED;
                $candidates['certified'] = [self::W_CERTIFIED, $businessName];
            }

            $reason = self::bestReason($candidates, $locale);

            $out = $row;
            $out['score'] = round($score, 3);
            $out['reason'] = [
                'code' => $reason['code'],
                'text' => $reason['text'],
            ];
            $ranked[] = $out;
        }

        return self::sortAndCut($ranked, $locale, $limit, 'empty_products');
    }

    /**
     * Rank public businesses for one caller.
     *
     * @param array<int, array<string, mixed>> $businesses PublicCatalog::shopBusinesses() rows.
     * @param array<string, int> $sales aggregate business_id => order count.
     * @return array<int, array<string, mixed>> ranked rows with a reason.
     */
    public static function rankBusinesses(array $businesses, array $signals, array $dismissed, array $sales, string $locale, int $limit): array
    {
        $ranked = [];
        foreach ($businesses as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || isset($dismissed[$id])) {
                continue;
            }

            $score = 0.0;
            $candidates = [];

            $name = (string) ($row['business_name'] ?? '');
            $productCount = (int) ($row['product_count'] ?? 0);

            $bought = (int) ($signals['purchased_businesses'][$id] ?? 0);
            if ($bought > 0) {
                $part = self::W_BOUGHT_PRODUCT * min($bought, 3) / 3;
                $score += $part;
                $candidates['bought_from'] = [$part, null];
            }

            $liked = (int) ($signals['liked_businesses'][$id] ?? 0);
            if ($liked > 0 && $bought === 0) {
                $part = self::W_LIKED_BUSINESS * min($liked, 3) / 3;
                $score += $part;
                $candidates['liked_ad'] = [$part, $name];
            }

            $location = $signals['location'] ?? null;
            $rowLocation = trim((string) ($row['business_location'] ?? ''));
            if ($location !== null && $rowLocation !== ''
                && mb_strtolower($location) === mb_strtolower($rowLocation)) {
                $score += self::W_LOCATION;
                $candidates['near_you'] = [self::W_LOCATION, $rowLocation];
            }

            $sold = (int) ($sales[$id] ?? 0);
            if ($sold > 0) {
                $part = self::W_POPULARITY * self::damp($sold);
                $score += $part;
                $candidates['popular'] = [$part, $sold];
            }

            if ($productCount > 0) {
                $part = self::W_BREADTH * min($productCount, 20) / 20;
                $score += $part;
                $candidates['stocked'] = [$part, $productCount];
            }

            if ((bool) ($row['is_certified'] ?? false)) {
                $score += self::W_CERTIFIED;
                $candidates['certified'] = [self::W_CERTIFIED, $name];
            }

            $reason = self::bestReason($candidates, $locale);

            $out = $row;
            $out['score'] = round($score, 3);
            $out['reason'] = [
                'code' => $reason['code'],
                'text' => $reason['text'],
            ];
            $ranked[] = $out;
        }

        return self::sortAndCut($ranked, $locale, $limit, 'empty_businesses');
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /** Log-damped popularity in 0..1 (deterministic for a fixed input). */
    private static function damp(int $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }
        $capped = min($value, (int) self::POPULARITY_CAP);

        return min(1.0, log(1 + $capped) / log(1 + self::POPULARITY_CAP));
    }

    /** Significant lowercase name tokens (no filler, alpha only). */
    private static function tokens(string $name): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 3 || mb_strlen($part) > 20) {
                continue;
            }
            if (in_array($part, self::STOPWORDS, true)) {
                continue;
            }
            $out[$part] = true;
        }

        return array_keys($out);
    }

    /**
     * Pick the strongest EXPLANATION among achieved contributions.
     * Ties resolve by a fixed priority order so output is deterministic.
     */
    private static function bestReason(array $candidates, string $locale): array
    {
        $priority = [
            'bought_before', 'bought_from', 'similar', 'same_shop',
            'liked_ad', 'near_you', 'popular', 'certified', 'stocked',
        ];

        $best = null;
        $bestScore = -1.0;
        foreach ($priority as $code) {
            if (! isset($candidates[$code])) {
                continue;
            }
            $weight = $candidates[$code][0];
            if ($weight > $bestScore) {
                $bestScore = $weight;
                $best = $code;
            }
        }

        if ($best === null) {
            // No signal at all: an honest placeholder (the caller sees the
            // localized empty-state text; sortAndCut rewrites score-0 rows).
            return [
                'code' => 'no_signal',
                'text' => self::msg('empty_products', $locale),
            ];
        }

        return [
            'code' => $best,
            'text' => self::reasonText($best, $candidates[$best][1], $locale),
        ];
    }

    /** Localized text for one reason code with its real detail value. */
    public static function reasonText(string $code, $detail, string $locale): string
    {
        switch ($code) {
            case 'bought_before':
                return self::msg('reason_bought_before', $locale);
            case 'bought_from':
                return self::msg('reason_bought_from', $locale);
            case 'similar':
                return self::msg('reason_similar', $locale, ['detail' => (string) ($detail ?? '')]);
            case 'same_shop':
                return self::msg('reason_same_shop', $locale, ['detail' => (string) $detail]);
            case 'liked_ad':
                return self::msg('reason_liked_ad', $locale, ['detail' => (string) $detail]);
            case 'near_you':
                return self::msg('reason_near_you', $locale, ['detail' => (string) $detail]);
            case 'popular':
                return self::msg('reason_popular', $locale, ['count' => (int) $detail]);
            case 'certified':
                return self::msg('reason_certified', $locale, ['detail' => (string) ($detail ?? '')]);
            case 'stocked':
                return self::msg('reason_stocked', $locale, ['count' => (int) $detail]);
            default:
                return self::msg($code, $locale);
        }
    }

    /**
     * Deterministic ordering: score desc, name asc, id asc - then cut.
     * A zero-score row still gets its localized honest reason.
     */
    private static function sortAndCut(array $ranked, string $locale, int $limit, string $emptyKey): array
    {
        usort($ranked, function (array $a, array $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            $nameA = mb_strtolower((string) ($a['name'] ?? $a['business_name'] ?? ''));
            $nameB = mb_strtolower((string) ($b['name'] ?? $b['business_name'] ?? ''));
            if ($nameA !== $nameB) {
                return strcmp($nameA, $nameB);
            }

            return strcmp((string) $a['id'], (string) $b['id']);
        });

        $out = array_slice($ranked, 0, max(1, $limit));

        // Rows with no signal at all (score 0) still need honest text.
        foreach ($out as &$row) {
            if ((float) $row['score'] <= 0.0) {
                $row['reason'] = [
                    'code' => 'no_signal',
                    'text' => self::msg($emptyKey, $locale),
                ];
            }
        }
        unset($row);

        return $out;
    }
}
