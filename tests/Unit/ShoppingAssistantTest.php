<?php

namespace Tests\Unit;

use App\Services\Ai\ShoppingAssistant;
use Tests\TestCase;

/**
 * Capability #11 contract. The shopping assistant is deterministic by
 * design, so these tests pin down:
 *  - natural-language parsing (Swahili, English, mixed, price bounds,
 *    availability words, quoted phrases),
 *  - the allow-listed shape a model-proposed parse must survive,
 *  - follow-up merging (a refinement keeps the previous topic),
 *  - strict AND ranking with hard price/stock/business gates over the
 *    PUBLIC-catalogue row shape,
 *  - honest clarification built only from rows that actually exist,
 *  - complete eight-locale coverage of every server-side string.
 *
 * Nothing here touches a model, HTTP or the database.
 */
class ShoppingAssistantTest extends TestCase
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

    private function parse(array $overrides = []): array
    {
        return array_merge(ShoppingAssistant::emptyParse(), $overrides);
    }

    /* ------------------------------------------------------------------ */
    /* Parsing                                                             */
    /* ------------------------------------------------------------------ */

    public function test_swahili_english_mix_keeps_only_significant_terms(): void
    {
        $parse = ShoppingAssistant::deterministicParse('Natafuta breki za Toyota Harrier.');

        $this->assertSame(['breki', 'toyota', 'harrier'], $parse['terms']);
        $this->assertNull($parse['phrase']);
        $this->assertNull($parse['price_min']);
        $this->assertNull($parse['price_max']);
        $this->assertNull($parse['in_stock']);
        $this->assertSame('relevance', $parse['sort']);
    }

    public function test_price_bounds_and_cheap_intent(): void
    {
        $max = ShoppingAssistant::deterministicParse('Sneakers nafuu chini ya 30000');
        $this->assertSame(30000, $max['price_max']);
        $this->assertSame(['sneakers'], $max['terms']);
        // An explicit bound wins over the cheap-word sort hint.
        $this->assertSame('relevance', $max['sort']);

        $english = ShoppingAssistant::deterministicParse('Brake pads under 50000');
        $this->assertSame(50000, $english['price_max']);
        $this->assertSame(['brake', 'pads'], $english['terms']);

        $min = ShoppingAssistant::deterministicParse('Natafuta soda angalau 10000');
        $this->assertSame(10000, $min['price_min']);
        $this->assertSame(['soda'], $min['terms']);

        $cheap = ShoppingAssistant::deterministicParse('sneakers nafuu');
        $this->assertSame('price_asc', $cheap['sort']);
        $this->assertSame(['sneakers'], $cheap['terms']);
    }

    public function test_inverted_price_bounds_are_swapped(): void
    {
        $parse = ShoppingAssistant::deterministicParse('zaidi ya 50000 chini ya 10000');
        $this->assertSame(10000, $parse['price_min']);
        $this->assertSame(50000, $parse['price_max']);
        $this->assertSame([], $parse['terms']);
    }

    public function test_availability_words_become_in_stock_filters(): void
    {
        $sw = ShoppingAssistant::deterministicParse('biskuti zilizo stock');
        $this->assertTrue($sw['in_stock']);
        $this->assertSame(['biskuti'], $sw['terms']);

        $en = ShoppingAssistant::deterministicParse('phones available');
        $this->assertTrue($en['in_stock']);
        $this->assertSame(['phones'], $en['terms']);

        $none = ShoppingAssistant::deterministicParse('natafuta soda');
        $this->assertNull($none['in_stock']);
    }

    public function test_quoted_phrase_is_extracted_and_removed_from_terms(): void
    {
        $parse = ShoppingAssistant::deterministicParse('natafuta "breki za toyota"');
        $this->assertSame('breki za toyota', $parse['phrase']);
        $this->assertSame([], $parse['terms']);
    }

    public function test_parser_never_returns_more_than_six_terms(): void
    {
        $parse = ShoppingAssistant::deterministicParse('alpha beta gamma delta epsilon zeta eta theta');
        $this->assertCount(6, $parse['terms']);
    }

    /* ------------------------------------------------------------------ */
    /* Model-proposed parses (allow-listed shape)                          */
    /* ------------------------------------------------------------------ */

    public function test_model_parse_is_forced_into_the_allow_listed_shape(): void
    {
        $sanitised = ShoppingAssistant::sanitizeModelParse([
            'terms' => ['breki', 'toyota', 123, 'x'],
            'evil' => 'should-not-survive',
            'sort' => 'hack',
            'price_min' => -5,
            'price_max' => '20000',
            'in_stock' => 'yes',
            'phrase' => 'ok phrase here',
        ]);

        $this->assertSame(['breki', 'toyota'], $sanitised['terms']);
        $this->assertArrayNotHasKey('evil', $sanitised);
        $this->assertSame('relevance', $sanitised['sort']);
        $this->assertNull($sanitised['price_min']);
        $this->assertSame(20000, $sanitised['price_max']);
        $this->assertNull($sanitised['in_stock']);
        $this->assertSame('ok phrase here', $sanitised['phrase']);

        $this->assertSame(ShoppingAssistant::emptyParse(), ShoppingAssistant::sanitizeModelParse('not-an-array'));
        $this->assertSame(ShoppingAssistant::emptyParse(), ShoppingAssistant::sanitizeModelParse(null));
    }

    public function test_model_parse_swaps_inverted_bounds(): void
    {
        $sanitised = ShoppingAssistant::sanitizeModelParse(['price_min' => 9000, 'price_max' => 1000]);
        $this->assertSame(1000, $sanitised['price_min']);
        $this->assertSame(9000, $sanitised['price_max']);
    }

    /* ------------------------------------------------------------------ */
    /* Follow-up merging                                                   */
    /* ------------------------------------------------------------------ */

    public function test_follow_up_without_terms_keeps_the_previous_topic(): void
    {
        $base = ShoppingAssistant::deterministicParse('Natafuta breki za Toyota');
        $follow = ShoppingAssistant::deterministicParse('chini ya 20000');

        $merged = ShoppingAssistant::mergeParse($base, $follow);

        $this->assertSame(['breki', 'toyota'], $merged['terms']);
        $this->assertSame(20000, $merged['price_max']);
    }

    public function test_follow_up_with_terms_replaces_them(): void
    {
        $base = ShoppingAssistant::deterministicParse('Natafuta breki za Toyota');
        $merged = ShoppingAssistant::mergeParse($base, ShoppingAssistant::deterministicParse('soda'));

        $this->assertSame(['soda'], $merged['terms']);
        $this->assertNull($merged['price_max']);
    }

    /* ------------------------------------------------------------------ */
    /* Ranking                                                             */
    /* ------------------------------------------------------------------ */

    public function test_strict_ranking_is_and_over_all_terms(): void
    {
        $products = [
            $this->product(['id' => 'a', 'name' => 'Brake Pad Front']),
            $this->product(['id' => 'b', 'name' => 'Brake Disc Rear']),
            $this->product(['id' => 'c', 'name' => 'Toyota Floor Mat', 'category' => 'Accessories', 'business_id' => 'b2', 'business_name' => 'Mama Ntilie']),
            $this->product(['id' => 'd', 'name' => 'Brake Pad Toyota', 'selling_price' => 30000.0]),
        ];

        $rows = ShoppingAssistant::rank($products, $this->parse(['terms' => ['brake', 'toyota']]));

        $this->assertCount(1, $rows);
        $this->assertSame('d', $rows[0]['id']);
        $this->assertSame('strict', $rows[0]['match']);
        $this->assertSame('name', $rows[0]['match_reason']);
    }

    public function test_ties_rank_by_score_then_name_deterministically(): void
    {
        $products = [
            $this->product(['id' => 'a', 'name' => 'Brake Pad Front']),
            $this->product(['id' => 'b', 'name' => 'Brake Disc Rear']),
            $this->product(['id' => 'd', 'name' => 'Brake Pad Toyota']),
        ];

        $rows = ShoppingAssistant::rank($products, $this->parse(['terms' => ['brake']]));

        $this->assertSame(['b', 'a', 'd'], array_column($rows, 'id'));
    }

    public function test_price_and_stock_gates_are_inclusive_and_hard(): void
    {
        $products = [
            $this->product(['id' => 'a', 'name' => 'Brake Pad Front', 'selling_price' => 25000.0]),
            $this->product(['id' => 'b', 'name' => 'Brake Disc Rear', 'selling_price' => 40000.0]),
            $this->product(['id' => 'e', 'name' => 'Brake Caliper', 'selling_price' => 20000.0, 'available' => false, 'stock_quantity' => 0]),
        ];

        $rows = ShoppingAssistant::rank($products, $this->parse([
            'terms' => ['brake'],
            'price_max' => 25000,
            'in_stock' => true,
        ]));

        $this->assertCount(1, $rows);
        $this->assertSame('a', $rows[0]['id']);
    }

    public function test_business_scoping_narrows_to_one_shop(): void
    {
        $products = [
            $this->product(['id' => 'a', 'business_id' => 'b1']),
            $this->product(['id' => 'c', 'business_id' => 'b2', 'name' => 'Kitchen Bowl']),
        ];

        $rows = ShoppingAssistant::rank($products, $this->parse(['business_id' => 'b2']));

        $this->assertCount(1, $rows);
        $this->assertSame('c', $rows[0]['id']);
    }

    public function test_plural_terms_match_singular_product_text(): void
    {
        $products = [
            $this->product(['id' => 'p', 'name' => 'Phone Charger 5V', 'category' => 'Electronics']),
        ];

        $rows = ShoppingAssistant::rank($products, $this->parse(['terms' => ['phones']]));

        $this->assertCount(1, $rows);
    }

    public function test_rank_returns_empty_for_empty_catalogue_or_no_terms_needed(): void
    {
        $this->assertSame([], ShoppingAssistant::rank([], $this->parse(['terms' => ['breki']])));

        // Browse mode: no terms, no phrase - every public row is eligible.
        $products = [
            $this->product(['id' => 'x', 'name' => 'Item X']),
            $this->product(['id' => 'y', 'name' => 'Item Y']),
        ];
        $rows = ShoppingAssistant::rank($products, $this->parse());
        $this->assertCount(2, $rows);
        $this->assertSame('filters', $rows[0]['match_reason']);
    }

    public function test_limit_and_price_sorting_are_enforced(): void
    {
        $products = [];
        for ($i = 1; $i <= 15; $i++) {
            $products[] = $this->product([
                'id' => 'p'.$i,
                'name' => sprintf('Item %02d', $i),
                'selling_price' => (float) (1000 * $i),
            ]);
        }

        $asc = ShoppingAssistant::rank($products, $this->parse(['sort' => 'price_asc']), 5);
        $this->assertCount(5, $asc);
        $this->assertSame([1000.0, 2000.0, 3000.0, 4000.0, 5000.0], array_column($asc, 'selling_price'));

        $desc = ShoppingAssistant::rank($products, $this->parse(['sort' => 'price_desc']), 5);
        $this->assertSame([15000.0, 14000.0, 13000.0, 12000.0, 11000.0], array_column($desc, 'selling_price'));
    }

    public function test_ranking_never_invents_fields_or_rows(): void
    {
        $products = [
            $this->product(['id' => 'a', 'available' => false]),
            $this->product(['id' => 'b', 'available' => false]),
        ];

        $rows = ShoppingAssistant::rank($products, $this->parse(['terms' => ['brake'], 'in_stock' => true]));
        $this->assertSame([], $rows);

        $rows = ShoppingAssistant::rank($products, $this->parse(['terms' => ['brake']]));
        $this->assertCount(2, $rows);
        $expectedKeys = array_merge(array_keys($this->product()), ['match_score', 'match_reason', 'match']);
        foreach ($rows as $row) {
            $this->assertSame($expectedKeys, array_keys($row), 'Ranking must annotate rows, never add or drop catalogue fields.');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Relaxation and clarification                                        */
    /* ------------------------------------------------------------------ */

    public function test_relaxed_ranking_is_or_and_tagged(): void
    {
        $products = [
            $this->product(['id' => 'a', 'name' => 'Brake Pad Front']),
            $this->product(['id' => 'c', 'name' => 'Toyota Floor Mat', 'category' => 'Accessories']),
            $this->product(['id' => 'z', 'name' => 'Bar of Soap', 'category' => 'Toiletries']),
        ];

        $parse = $this->parse(['terms' => ['brake', 'toyota']]);
        $this->assertSame([], ShoppingAssistant::rank($products, $parse));

        $relaxed = ShoppingAssistant::relax($products, $parse);
        $this->assertCount(2, $relaxed);
        foreach ($relaxed as $row) {
            $this->assertSame('relaxed', $row['match']);
            $this->assertGreaterThan(0, $row['match_score']);
        }
    }

    public function test_clarification_requires_two_real_interpretations(): void
    {
        $mixed = [
            $this->product(['id' => 'a', 'name' => 'Brake Pad Front']),
            $this->product(['id' => 'c', 'name' => 'Toyota Floor Mat', 'category' => 'Accessories']),
        ];
        $single = [
            $this->product(['id' => 'a', 'name' => 'Brake Pad Front']),
            $this->product(['id' => 'b', 'name' => 'Brake Disc Rear']),
        ];

        $this->assertTrue(ShoppingAssistant::needsClarification([], $mixed));
        $this->assertFalse(ShoppingAssistant::needsClarification([], $single));
        $this->assertFalse(ShoppingAssistant::needsClarification($single, $mixed));
        $this->assertFalse(ShoppingAssistant::needsClarification([], []));
    }

    public function test_refinement_options_come_only_from_existing_rows(): void
    {
        $relaxed = [
            $this->product(['id' => 'a', 'name' => 'Brake Pad Front', 'business_id' => 'b1']),
            $this->product(['id' => 'c', 'name' => 'Toyota Floor Mat', 'category' => 'Accessories', 'business_id' => 'b2']),
        ];

        $options = ShoppingAssistant::refinementOptions($relaxed, 4);

        $this->assertNotEmpty($options);
        foreach ($options as $option) {
            $this->assertContains($option['type'], ['category', 'business']);
        }
        $categories = array_values(array_filter($options, function ($option) {
            return $option['type'] === 'category';
        }));
        $this->assertSame('Accessories', $categories[0]['label']);
        $this->assertSame(1, $categories[0]['count']);
    }

    public function test_refinement_falls_back_to_real_businesses(): void
    {
        $singleCategory = [
            $this->product(['id' => 'a', 'name' => 'Brake Pad Front', 'business_id' => 'b1', 'business_name' => 'AutoSpares TZ']),
            $this->product(['id' => 'b', 'name' => 'Brake Disc Rear', 'business_id' => 'b3', 'business_name' => 'City Motors']),
        ];

        $options = ShoppingAssistant::refinementOptions($singleCategory, 4);

        $this->assertGreaterThanOrEqual(2, count($options));
        $businessOptions = array_values(array_filter($options, function ($option) {
            return $option['type'] === 'business';
        }));
        $this->assertSame('b1', $businessOptions[0]['business_id']);
        $this->assertSame('AutoSpares TZ', $businessOptions[0]['label']);
    }

    /* ------------------------------------------------------------------ */
    /* Business matching                                                   */
    /* ------------------------------------------------------------------ */

    public function test_businesses_match_by_name_type_location_and_gating(): void
    {
        $businesses = [
            ['id' => 'b1', 'business_name' => 'AutoSpares TZ', 'business_location' => 'Dar es Salaam', 'business_type' => 'Garage & Spares', 'business_description' => 'Genuine car parts', 'is_certified' => true],
            ['id' => 'b2', 'business_name' => 'Mama Ntilie Kitchen', 'business_location' => 'Mbeya', 'business_type' => 'Restaurant', 'business_description' => 'Local food', 'is_certified' => false],
        ];

        $rows = ShoppingAssistant::matchBusinesses($businesses, $this->parse(['terms' => ['spares', 'dar']]));
        $this->assertCount(1, $rows);
        $this->assertSame('b1', $rows[0]['id']);
        $this->assertSame(7, $rows[0]['match_score']);
        $this->assertSame('name', $rows[0]['match_reason']);

        $gated = ShoppingAssistant::matchBusinesses($businesses, $this->parse(['terms' => ['spares'], 'location' => 'mbeya']));
        $this->assertSame([], $gated);

        $this->assertSame([], ShoppingAssistant::matchBusinesses($businesses, $this->parse()));
    }

    public function test_categories_summarise_real_matches(): void
    {
        $matches = [
            $this->product(['name' => 'Brake Pad Front']),
            $this->product(['name' => 'Brake Disc Rear']),
            $this->product(['name' => 'Floor Mat', 'category' => 'Accessories']),
        ];

        $categories = ShoppingAssistant::categories($matches, 6);

        $this->assertSame('Spare Parts', $categories[0]['category']);
        $this->assertSame(2, $categories[0]['count']);
        $this->assertSame('Accessories', $categories[1]['category']);
        $this->assertSame(1, $categories[1]['count']);
    }

    /* ------------------------------------------------------------------ */
    /* Localized server-side strings                                       */
    /* ------------------------------------------------------------------ */

    public function test_every_language_carries_every_message_and_sample(): void
    {
        $this->assertTrue(ShoppingAssistant::messagesComplete());

        foreach (self::LANGS as $lang) {
            foreach (array_keys(ShoppingAssistant::MESSAGES['en']) as $key) {
                $this->assertNotEmpty(ShoppingAssistant::MESSAGES[$lang][$key] ?? '', "Missing {$lang}.{$key}");
            }
            $this->assertGreaterThanOrEqual(3, count(ShoppingAssistant::samples($lang)));
        }
    }

    public function test_message_interpolation_and_fallbacks(): void
    {
        $this->assertSame(
            'Bidhaa 3 zinazolingana na "breki".',
            ShoppingAssistant::msg('summary_results', 'sw', ['count' => 3, 'query' => 'breki']),
        );

        // Unknown locale degrades to English, unknown key echoes itself.
        $this->assertSame(
            '3 products match "breki".',
            ShoppingAssistant::msg('summary_results', 'xx', ['count' => 3, 'query' => 'breki']),
        );
        $this->assertSame('nonexistent', ShoppingAssistant::msg('nonexistent', 'sw'));
        $this->assertSame(ShoppingAssistant::samples('en'), ShoppingAssistant::samples('xx'));
    }
}
