<?php

namespace Tests\Unit;

use App\Services\Ai\RecommendationEngine;
use Tests\TestCase;

/**
 * Capability #14 contract. The recommendation ranking is deterministic by
 * design, so these tests pin down:
 *  - hard gates (in stock, available, caller's dismissals) applied BEFORE
 *    ranking, so a dismissed item can never reappear;
 *  - every explanation built only from a real signal of the caller's own
 *    rows (purchases, likes, location) - or an honest cold-start fallback
 *    (popularity/certification) when there is no history at all;
 *  - popularity explanations carrying the real aggregate count;
 *  - deterministic ordering (score desc, name asc, id asc);
 *  - signal bundles fabricated from NOTHING when the input rows are empty;
 *  - complete eight-locale coverage of every server-side string.
 *
 * Nothing here touches a model, HTTP or the database.
 */
class RecommendationEngineTest extends TestCase
{
    private const LANGS = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /** A PublicCatalog::products()-shaped row. */
    private function product(array $overrides = []): array
    {
        return array_merge([
            'id' => 'p1',
            'name' => 'Brake Pad Front',
            'description' => 'Genuine spare part',
            'category' => 'Spare Parts',
            'selling_price' => 25000.0,
            'unit' => 'pcs',
            'image' => null,
            'stock_quantity' => 5,
            'available' => true,
            'business_id' => 'b1',
            'business_name' => 'AutoSpares TZ',
            'business_location' => 'Dar es Salaam',
            'business_avatar' => null,
            'is_certified' => false,
            'owner_id' => 'o1',
        ], $overrides);
    }

    /** A PublicCatalog::shopBusinesses()-shaped row. */
    private function business(array $overrides = []): array
    {
        return array_merge([
            'id' => 'b1',
            'business_name' => 'AutoSpares TZ',
            'business_location' => 'Dar es Salaam',
            'business_type' => 'spares',
            'business_description' => null,
            'business_logo_url' => null,
            'is_certified' => false,
            'product_count' => 8,
        ], $overrides);
    }

    private function rank(array $catalog, array $signals, array $dismissed = [], array $sales = [], string $locale = 'en', int $limit = 12): array
    {
        return RecommendationEngine::rankProducts($catalog, $signals, $dismissed, $sales, $locale, $limit);
    }

    /* ------------------------------------------------------------------ */
    /* Hard gates                                                          */
    /* ------------------------------------------------------------------ */

    public function test_out_of_stock_and_unavailable_rows_are_never_recommended(): void
    {
        $rows = [
            $this->product(['id' => 'in-stock']),
            $this->product(['id' => 'no-stock', 'stock_quantity' => 0]),
            $this->product(['id' => 'hidden', 'available' => false]),
        ];

        $out = $this->rank($rows, RecommendationEngine::emptySignals());

        $this->assertSame(['in-stock'], array_column($out, 'id'));
    }

    public function test_dismissed_items_are_filtered_before_ranking(): void
    {
        $rows = [
            $this->product(['id' => 'a']),
            $this->product(['id' => 'b']),
        ];
        $dismissed = ['b' => time()];

        $out = $this->rank($rows, RecommendationEngine::emptySignals(), $dismissed);

        $this->assertSame(['a'], array_column($out, 'id'));
    }

    public function test_real_public_catalog_rows_survive_the_gates_after_normalization(): void
    {
        // Exactly what PublicCatalog::products() emits today: `stock`,
        // `business_is_certified` and `image_url` - NOT the engine's
        // stock_quantity / is_certified / image keys. Without the mapping
        // this row would fail the in-stock gate and the feed would be
        // empty in production while this suite still passed on fixtures.
        $raw = [
            'id' => 'p9',
            'name' => 'Brake Pad Front',
            'description' => 'Genuine spare part',
            'category' => 'Spare Parts',
            'image_url' => 'https://cdn.example/pad.jpg',
            'unit' => 'pcs',
            'selling_price' => 25000.0,
            'currency' => 'TZS',
            'stock' => 5,
            'available' => true,
            'business_id' => 'b1',
            'business_name' => 'AutoSpares TZ',
            'business_location' => 'Dar es Salaam',
            'business_logo_url' => null,
            'business_is_certified' => true,
        ];

        $row = RecommendationEngine::normalizeCatalogRow($raw);
        $out = $this->rank([$row], RecommendationEngine::emptySignals());

        $this->assertCount(1, $out, 'A real in-stock catalogue row must pass the hard gates.');
        $this->assertSame('https://cdn.example/pad.jpg', $out[0]['image']);
        $this->assertTrue($out[0]['is_certified']);

        // Zero stock in the real key is still a hard gate.
        $outOfStock = RecommendationEngine::normalizeCatalogRow(
            array_merge($raw, ['id' => 'p10', 'stock' => 0, 'available' => false])
        );
        $this->assertSame([], $this->rank([$outOfStock], RecommendationEngine::emptySignals()));
    }

    public function test_normalization_never_overwrites_engine_shaped_keys(): void
    {
        $fixture = $this->product(['is_certified' => false]);

        $this->assertSame($fixture, RecommendationEngine::normalizeCatalogRow($fixture));
    }

    public function test_limit_is_respected(): void
    {
        $rows = [];
        for ($i = 0; $i < 20; $i++) {
            $rows[] = $this->product(['id' => 'p'.$i, 'name' => 'Item '.$i]);
        }

        $out = $this->rank($rows, RecommendationEngine::emptySignals(), [], [], 'en', 5);

        $this->assertCount(5, $out);
    }

    /* ------------------------------------------------------------------ */
    /* Real-signal explanations                                            */
    /* ------------------------------------------------------------------ */

    public function test_previously_bought_product_ranks_first_with_bought_before_reason(): void
    {
        $rows = [
            $this->product(['id' => 'other', 'name' => 'Air Filter']),
            $this->product(['id' => 'bought', 'name' => 'Brake Pad Front']),
        ];
        $signals = RecommendationEngine::buildSignals(
            [['product_id' => 'bought', 'product_name' => 'Brake Pad Front', 'business_id' => 'b1', 'quantity' => 1, 'category' => 'Spare Parts']],
            [],
            null
        );

        $out = $this->rank($rows, $signals);

        $this->assertSame('bought', $out[0]['id']);
        $this->assertSame('bought_before', $out[0]['reason']['code']);
        $this->assertNotSame('', trim($out[0]['reason']['text']));
    }

    public function test_same_category_products_explain_with_the_callers_own_purchase(): void
    {
        $rows = [
            $this->product(['id' => 'unrelated', 'name' => 'Cooking Oil', 'category' => 'Groceries', 'business_id' => 'b9']),
            $this->product(['id' => 'related', 'name' => 'Brake Disc Front', 'category' => 'Spare Parts', 'business_id' => 'b9']),
        ];
        $signals = RecommendationEngine::buildSignals(
            [['product_id' => 'x', 'product_name' => 'Brake Pad Front', 'business_id' => 'b1', 'quantity' => 2, 'category' => 'Spare Parts']],
            [],
            null
        );

        $out = $this->rank($rows, $signals);

        $this->assertSame('related', $out[0]['id']);
        $this->assertSame('similar', $out[0]['reason']['code']);
        // Detail must be the caller's OWN purchased product name.
        $this->assertStringContainsString('Brake Pad Front', $out[0]['reason']['text']);
    }

    public function test_same_shop_reason_carries_the_business_name(): void
    {
        $rows = [
            $this->product(['id' => 'elsewhere', 'name' => 'Spark Plug', 'business_id' => 'b9', 'business_name' => 'Other Shop']),
            $this->product(['id' => 'loyal', 'name' => 'Oil Filter', 'business_id' => 'b1', 'business_name' => 'AutoSpares TZ']),
        ];
        $signals = RecommendationEngine::buildSignals(
            [['product_id' => 'x', 'product_name' => 'Brake Pad', 'business_id' => 'b1', 'quantity' => 3, 'category' => 'Spare Parts']],
            [],
            null
        );

        $out = $this->rank($rows, $signals);

        $this->assertSame('loyal', $out[0]['id']);
        $this->assertSame('same_shop', $out[0]['reason']['code']);
        $this->assertStringContainsString('AutoSpares TZ', $out[0]['reason']['text']);
    }

    public function test_liked_ad_business_is_boosted_and_explained(): void
    {
        $rows = [
            $this->product(['id' => 'plain', 'name' => 'Wiper Blade', 'business_id' => 'b9', 'business_name' => 'Other']),
            $this->product(['id' => 'liked', 'name' => 'Tyre Pump', 'business_id' => 'b2', 'business_name' => 'Liked Shop']),
        ];
        $signals = RecommendationEngine::buildSignals(
            [],
            [['category' => 'Tools', 'business_id' => 'b2']],
            null
        );

        $out = $this->rank($rows, $signals);

        $this->assertSame('liked', $out[0]['id']);
        $this->assertSame('liked_ad', $out[0]['reason']['code']);
        $this->assertStringContainsString('Liked Shop', $out[0]['reason']['text']);
    }

    public function test_location_match_gives_near_you_reason_case_insensitively(): void
    {
        $rows = [
            $this->product(['id' => 'far', 'name' => 'Belt', 'business_location' => 'Mwanza']),
            $this->product(['id' => 'near', 'name' => 'Fan Belt', 'business_location' => 'dar es salaam']),
        ];
        $signals = RecommendationEngine::buildSignals([], [], 'Dar es Salaam');

        $out = $this->rank($rows, $signals);

        $this->assertSame('near', $out[0]['id']);
        $this->assertSame('near_you', $out[0]['reason']['code']);
        $this->assertStringContainsString('dar es salaam', $out[0]['reason']['text']);
    }

    /* ------------------------------------------------------------------ */
    /* Cold start                                                          */
    /* ------------------------------------------------------------------ */

    public function test_guest_with_no_signals_gets_popularity_with_the_real_count(): void
    {
        $rows = [
            $this->product(['id' => 'rare', 'name' => 'Rare Item']),
            $this->product(['id' => 'hot', 'name' => 'Hot Item']),
        ];
        $sales = ['hot' => 12, 'rare' => 1];

        $out = $this->rank($rows, RecommendationEngine::emptySignals(), [], $sales, 'en');

        $this->assertSame('hot', $out[0]['id']);
        $this->assertSame('popular', $out[0]['reason']['code']);
        $this->assertStringContainsString('12', $out[0]['reason']['text']);
    }

    public function test_cold_start_can_explain_by_certification(): void
    {
        $rows = [
            $this->product(['id' => 'plain', 'name' => 'Plain Item', 'is_certified' => false]),
            $this->product(['id' => 'cert', 'name' => 'Certified Item', 'is_certified' => true]),
        ];

        $out = $this->rank($rows, RecommendationEngine::emptySignals(), [], [], 'en');

        $this->assertSame('cert', $out[0]['id']);
        $this->assertSame('certified', $out[0]['reason']['code']);
    }

    public function test_zero_signal_rows_get_an_honest_empty_state_reason(): void
    {
        $rows = [$this->product(['id' => 'x', 'name' => 'Nobody Wants This', 'is_certified' => false])];

        $out = $this->rank($rows, RecommendationEngine::emptySignals(), [], [], 'en');

        $this->assertCount(1, $out);
        $this->assertSame(0.0, (float) $out[0]['score']);
        $this->assertSame('no_signal', $out[0]['reason']['code']);
        $this->assertStringContainsString('No products to recommend', $out[0]['reason']['text']);
    }

    public function test_personal_signal_detection_for_cold_start_fallback(): void
    {
        $this->assertFalse(RecommendationEngine::hasPersonalSignals(RecommendationEngine::emptySignals()));

        $withLikes = RecommendationEngine::buildSignals([], [['category' => 'Tools', 'business_id' => 'b1']], null);
        $this->assertTrue(RecommendationEngine::hasPersonalSignals($withLikes));

        $withLocation = RecommendationEngine::buildSignals([], [], 'Dar es Salaam');
        $this->assertTrue(RecommendationEngine::hasPersonalSignals($withLocation));

        $withHistory = RecommendationEngine::buildSignals(
            [['product_id' => 'p', 'product_name' => 'Thing', 'business_id' => 'b', 'quantity' => 1, 'category' => '']],
            [],
            null
        );
        $this->assertTrue(RecommendationEngine::hasPersonalSignals($withHistory));
    }

    /* ------------------------------------------------------------------ */
    /* Signals                                                             */
    /* ------------------------------------------------------------------ */

    public function test_empty_rows_fabricate_no_signals_at_all(): void
    {
        $signals = RecommendationEngine::buildSignals([], [], null);

        $this->assertSame(RecommendationEngine::emptySignals(), $signals);
        $this->assertNull($signals['location']);
        $this->assertSame(0, $signals['history_lines']);
        $this->assertSame(0, $signals['likes_count']);
    }

    public function test_build_signals_counts_quantities_categories_and_tokens(): void
    {
        $signals = RecommendationEngine::buildSignals(
            [
                ['product_id' => 'p1', 'product_name' => 'Brake Pad Front', 'business_id' => 'b1', 'quantity' => 2, 'category' => 'Spare Parts'],
                ['product_id' => 'p2', 'product_name' => 'Brake Disc Front', 'business_id' => 'b1', 'quantity' => 1, 'category' => 'Spare Parts'],
            ],
            [
                ['category' => 'Tools', 'business_id' => 'b2'],
                ['category' => 'Tools', 'business_id' => 'b2'],
            ],
            '  Dar es Salaam  '
        );

        $this->assertSame(2, $signals['history_lines']);
        $this->assertSame(3, $signals['purchased_products']['p1'] + $signals['purchased_products']['p2']);
        $this->assertSame(3, $signals['purchased_categories']['spare parts']);
        $this->assertSame(3, $signals['purchased_businesses']['b1']);
        $this->assertSame('Brake Pad Front', $signals['sample_names']['spare parts']);
        $this->assertSame(2, $signals['liked_categories']['tools']);
        $this->assertSame(2, $signals['liked_businesses']['b2']);
        $this->assertSame(2, $signals['likes_count']);
        $this->assertSame('Dar es Salaam', $signals['location']);

        // Filler words never become similarity tokens.
        $this->assertArrayNotHasKey('the', $signals['purchased_tokens']);
        $this->assertArrayHasKey('brake', $signals['purchased_tokens']);

        // Blank location collapses to null - nothing to personalise on.
        $blank = RecommendationEngine::buildSignals([], [], '   ');
        $this->assertNull($blank['location']);
    }

    /* ------------------------------------------------------------------ */
    /* Businesses                                                          */
    /* ------------------------------------------------------------------ */

    public function test_businesses_rank_by_own_purchase_and_exclude_dismissed(): void
    {
        $businesses = [
            $this->business(['id' => 'b1', 'business_name' => 'AutoSpares TZ']),
            $this->business(['id' => 'b2', 'business_name' => 'Disliked Shop', 'product_count' => 50, 'is_certified' => true]),
        ];
        $signals = RecommendationEngine::buildSignals(
            [['product_id' => 'p', 'product_name' => 'Pad', 'business_id' => 'b1', 'quantity' => 3, 'category' => 'Spare Parts']],
            [],
            null
        );

        $out = RecommendationEngine::rankBusinesses($businesses, $signals, ['b2' => time()], [], 'en', 6);

        $this->assertSame(['b1'], array_column($out, 'id'));
        $this->assertSame('bought_from', $out[0]['reason']['code']);
    }

    public function test_cold_start_businesses_explain_by_popularity_and_stock(): void
    {
        $businesses = [
            $this->business(['id' => 'b1', 'business_name' => 'Quiet Shop', 'product_count' => 2]),
            $this->business(['id' => 'b2', 'business_name' => 'Busy Shop', 'product_count' => 18]),
        ];

        $out = RecommendationEngine::rankBusinesses($businesses, RecommendationEngine::emptySignals(), [], ['b2' => 40, 'b1' => 3], 'en', 6);

        $this->assertSame('b2', $out[0]['id']);
        $this->assertSame('popular', $out[0]['reason']['code']);
        $this->assertStringContainsString('40', $out[0]['reason']['text']);
    }

    /* ------------------------------------------------------------------ */
    /* Determinism                                                         */
    /* ------------------------------------------------------------------ */

    public function test_ranking_is_deterministic_and_breaks_ties_by_name_then_id(): void
    {
        $rows = [
            $this->product(['id' => 'z', 'name' => 'Same Item']),
            $this->product(['id' => 'a', 'name' => 'Same Item']),
            $this->product(['id' => 'm', 'name' => 'Alpha Item']),
        ];

        $first = $this->rank($rows, RecommendationEngine::emptySignals());
        $second = $this->rank(array_reverse($rows), RecommendationEngine::emptySignals());

        $this->assertSame(array_column($first, 'id'), array_column($second, 'id'));
        // Equal scores: name asc first, then id asc.
        $this->assertSame(['m', 'a', 'z'], array_column($first, 'id'));
    }

    /* ------------------------------------------------------------------ */
    /* Localization                                                        */
    /* ------------------------------------------------------------------ */

    public function test_every_message_exists_in_all_eight_locales(): void
    {
        $this->assertTrue(RecommendationEngine::messagesComplete());
    }

    public function test_msg_falls_back_to_english_and_replaces_placeholders(): void
    {
        $en = RecommendationEngine::msg('reason_popular', 'en', ['count' => 7]);
        $this->assertStringContainsString('7', $en);

        // Unknown locale falls back to English rather than leaking the key.
        $fallback = RecommendationEngine::msg('reason_popular', 'xx', ['count' => 7]);
        $this->assertSame($en, $fallback);

        // Privacy + cold-start notes are present in every locale.
        foreach (self::LANGS as $lang) {
            $this->assertNotSame('', trim(RecommendationEngine::msg('privacy_note', $lang)));
            $this->assertNotSame('', trim(RecommendationEngine::msg('new_user_note', $lang)));
            if ($lang !== 'en') {
                $this->assertNotSame(
                    RecommendationEngine::msg('privacy_note', $lang),
                    RecommendationEngine::msg('privacy_note', 'en')
                );
            }
        }
    }

    public function test_reason_texts_render_for_every_locale(): void
    {
        $codes = [
            'bought_before' => null,
            'bought_from' => null,
            'similar' => 'Brake Pad',
            'same_shop' => 'AutoSpares',
            'liked_ad' => 'AutoSpares',
            'near_you' => 'Dar es Salaam',
            'popular' => 9,
            'certified' => 'AutoSpares',
            'stocked' => 4,
        ];

        foreach (self::LANGS as $lang) {
            foreach ($codes as $code => $detail) {
                $text = RecommendationEngine::reasonText($code, $detail, $lang);
                $this->assertNotSame('', trim($text));
                // Every placeholder must have been substituted.
                $this->assertStringNotContainsString('{detail}', $text);
                $this->assertStringNotContainsString('{count}', $text);
            }
        }
    }
}
