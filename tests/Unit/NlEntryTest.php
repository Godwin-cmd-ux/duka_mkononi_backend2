<?php

namespace Tests\Unit;

use App\Services\Ai\NlEntry;
use Tests\TestCase;

/**
 * Capability #25 - Schema-Free Natural-Language Data Entry: the pure layer.
 *
 * No database, no HTTP. What these tests pin:
 *  - the record type is classified against the allow-list, and a sentence that
 *    is not one of the four types stays null with an explanation instead of a
 *    guess;
 *  - numbers keep their meaning: a count is never read as money and money is
 *    never read as a count (the first real defect a live probe found);
 *  - a product/customer reference is only ever copied from the user's own
 *    words - never a number, never a fragment, never a guess - and non-Latin
 *    scripts survive intact;
 *  - a near-tie between two catalogue products stays `ambiguous` with NO
 *    product id, because silently choosing one is exactly what this capability
 *    must not do;
 *  - the model is confined to the allow-list and to phrases that already exist
 *    in the sentence;
 *  - all eight languages really carry the strings the clients render.
 */
class NlEntryTest extends TestCase
{
    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = gmdate('Y-m-d');
    }

    /* ------------------------------------------------------------------ */
    /* Classification                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * The strongest possible check of the classifier AND of the examples the UI
     * shows: every worked example we publish must classify as its own type.
     */
    public function test_every_published_example_classifies_as_its_own_type(): void
    {
        foreach (self::LOCALES as $locale) {
            $examples = NlEntry::examples($locale);

            foreach (NlEntry::TYPES as $type) {
                $this->assertNotEmpty($examples[$type] ?? [], "{$locale}/{$type} needs a worked example.");

                foreach ($examples[$type] as $sentence) {
                    $result = NlEntry::classify($sentence);
                    $this->assertSame(
                        $type,
                        $result['type'],
                        "{$locale}: \"{$sentence}\" must classify as {$type}, got ".var_export($result['type'], true)." (decision: {$result['decision']})."
                    );
                    $this->assertGreaterThanOrEqual(0.5, $result['confidence']);
                }
            }
        }
    }

    public function test_an_unrelated_sentence_is_not_forced_into_a_type(): void
    {
        $result = NlEntry::classify('The weather is nice today');

        $this->assertNull($result['type']);
        $this->assertSame('no_confident_type', $result['decision']);
        $this->assertArrayHasKey('product', $result['scores']);
        $this->assertSame(0.0, (float) $result['scores']['product']);
    }

    public function test_a_sentence_that_fits_two_types_is_reported_as_ambiguous(): void
    {
        // Mentions a sale AND an expense without a clear winner.
        $result = NlEntry::classify('I sold nothing but recorded an expense');

        $this->assertNull($result['type'], 'A tie must never be resolved by guessing.');
        $this->assertSame('ambiguous_type', $result['decision']);
    }

    public function test_classification_is_case_and_punctuation_insensitive(): void
    {
        foreach (['ADD 20 BOTTLES OF BLUE PERFUME TO STOCK', 'add 20 bottles of blue perfume to stock.', '  add, 20 bottles of blue perfume to stock!! '] as $sentence) {
            $this->assertSame(NlEntry::TYPE_PRODUCT, NlEntry::classify($sentence)['type'], $sentence);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Quantities, units and money                                        */
    /* ------------------------------------------------------------------ */

    public function test_a_number_before_a_unit_is_a_quantity_not_money(): void
    {
        $quantity = NlEntry::extractQuantity('Add 20 bottles of blue perfume to stock', 'en');

        $this->assertSame(20, $quantity['quantity']);
        $this->assertSame('bottle', $quantity['unit']);
        $this->assertSame([], NlEntry::moneyMatches('Add 20 bottles of blue perfume to stock'), '20 bottles is not a price.');
    }

    /**
     * Swahili (and several of the other languages) put the count AFTER the noun.
     * Missing that made "chupa 20" read as a price of 20.
     */
    public function test_a_number_after_a_unit_is_still_a_quantity(): void
    {
        foreach ([
            'Ongeza chupa 20 za manukato kwenye stock' => 20,
            'Nimeuza vipande 3 vya sabuni' => 3,
            'Ajouter 20 bouteilles de parfum' => 20,
        ] as $sentence => $expected) {
            $quantity = NlEntry::extractQuantity($sentence, $sentence === 'Nimeuza vipande 3 vya sabuni' ? 'sw' : 'en');
            $this->assertSame($expected, $quantity['quantity'], $sentence);
            $this->assertSame([], NlEntry::moneyMatches($sentence), "{$sentence}: the count must not be money.");
        }
    }

    public function test_counting_words_are_understood_and_money_stays_money(): void
    {
        $quantity = NlEntry::extractQuantity('I sold three units of soap for 2,000 each', 'en');

        $this->assertSame(3, $quantity['quantity']);
        $this->assertSame('piece', $quantity['unit']);
        $this->assertSame([2000.0], array_map(static fn (array $m): float => $m['value'], NlEntry::moneyMatches('I sold three units of soap for 2,000 each')));
    }

    public function test_an_unknown_unit_is_reported_rather_than_invented(): void
    {
        $quantity = NlEntry::extractQuantity('Add 7 of the blue perfume to stock', 'en');

        $this->assertSame(7, $quantity['quantity']);
        $this->assertNull($quantity['unit']);
        $this->assertTrue($quantity['unit_uncertain']);
    }

    public function test_a_date_is_never_read_as_a_quantity_or_a_price(): void
    {
        $sentence = 'Record the expense on 2026-01-05 of 5,000';

        $this->assertSame(5000.0, NlEntry::moneyMatches($sentence)[0]['value']);
        $this->assertNotSame(2026, NlEntry::extractQuantity($sentence, 'en')['quantity']);
    }

    public function test_thousand_separators_are_one_amount(): void
    {
        $matches = NlEntry::moneyMatches('Record transport of 15,000 TSh');

        $this->assertCount(1, $matches);
        $this->assertSame(15000.0, $matches[0]['value']);
    }

    /* ------------------------------------------------------------------ */
    /* References                                                         */
    /* ------------------------------------------------------------------ */

    public function test_a_quoted_name_wins_over_every_other_marker(): void
    {
        $this->assertSame('Blue Perfume 50ml', NlEntry::extractProductReference('Add 20 bottles of "Blue Perfume 50ml" to stock'));
    }

    public function test_the_earliest_reference_marker_wins_so_the_whole_phrase_survives(): void
    {
        $this->assertSame('manukato ya bluu', NlEntry::extractProductReference('Ongeza chupa 20 za manukato ya bluu kwenye stock'));
        $this->assertSame('blue perfume', NlEntry::extractProductReference('Add 20 bottles of blue perfume to stock'));
    }

    public function test_price_words_are_not_part_of_the_product_name(): void
    {
        $this->assertSame(
            'cement',
            NlEntry::extractProductReference('Add a new product cement, 50 bags, buying 18000, selling 22000')
        );
    }

    /** Devanagari vowel signs are combining MARKS: they must survive intact. */
    public function test_non_latin_references_survive_intact(): void
    {
        $reference = NlEntry::extractProductReference('स्टॉक में 20 बोतल नीली परफ्यूम जोड़ें');

        $this->assertSame('नीली परफ्यूम', $reference);
        $this->assertSame(
            '蓝色香水',
            NlEntry::extractProductReference('把 20 瓶蓝色香水加入库存'),
            'CJK has no word spaces, so the scaffolding must be stripped by character.'
        );
    }

    public function test_a_reference_made_only_of_digits_is_refused(): void
    {
        // "kilo 5 kwa 3500" leaves "sukari": the number is not the reference.
        $this->assertSame('sukari', NlEntry::extractProductReference('Niliuza sukari kilo 5 kwa 3500'));
        $this->assertNull(NlEntry::extractProductReference('Ongeza 20 30 40 kwenye stock'));
    }

    public function test_a_customer_name_never_contains_a_number(): void
    {
        $this->assertSame('amina', NlEntry::extractCustomerReference('Add customer Amina, phone 0712345678'));
        $this->assertSame('juma', NlEntry::extractCustomerReference('mteja Juma, simu 0712345678'));
    }

    public function test_a_price_is_not_mistaken_for_a_customer(): void
    {
        $this->assertNull(
            NlEntry::extractCustomerReference('Nimeuza vipande 3 vya sabuni kwa 2000 kila kimoja'),
            '"kwa 2000" is a price, not a customer.'
        );
        $this->assertSame('amina', NlEntry::extractCustomerReference('I sold 5 kilos of sugar at 3,500 to customer Amina'));
    }

    /**
     * Live defect: the real catalogue contains products literally named "6203".
     * A new product may be named with digits alone, and those digits are its
     * NAME - not a buying price.
     */
    public function test_a_new_product_can_be_named_with_digits_alone(): void
    {
        $draft = NlEntry::draft(NlEntry::TYPE_PRODUCT, 'Add 20 bottles of 6203 to stock', 'en', $this->today);

        $this->assertSame('6203', $draft['name']);
        $this->assertNull($draft['price'], 'The product name must not be read as a price.');
        $this->assertSame(20, $draft['stock']);
    }

    /**
     * Live defect: "for 2,000 each" was reported as a unit price of 6204 (the
     * product's numeric name) because the first amount near ANY per-unit word
     * won. The CLOSEST amount must win.
     */
    public function test_the_amount_closest_to_a_per_unit_word_is_the_unit_price(): void
    {
        $draft = NlEntry::draft(NlEntry::TYPE_SALE, 'I sold three units of 6204 for 2,000 each', 'en', $this->today);
        $item = $draft['items'][0];

        $this->assertSame('6204', $item['reference'], 'The product name is the reference, not the price.');
        $this->assertSame(3, $item['quantity']);
        $this->assertSame(2000.0, $item['unit_price']);
        $this->assertSame(6000.0, $item['line_total']);
    }

    /** A price marker never introduces the product: "kwa 3500" is money. */
    public function test_a_price_marker_tail_is_never_the_product_reference(): void
    {
        $draft = NlEntry::draft(NlEntry::TYPE_SALE, 'Niliuza sukari kilo 5 kwa 3500', 'sw', $this->today);

        $this->assertSame('sukari', $draft['items'][0]['reference']);
        $this->assertSame(3500.0, $draft['items'][0]['unit_price']);
    }

    public function test_phone_and_email_are_copied_only_when_present_and_plausible(): void
    {
        $this->assertSame('0712345678', NlEntry::extractPhone('Add customer Amina, phone 0712345678'));
        $this->assertNull(NlEntry::extractPhone('Add customer Amina, phone 12345'));
        $this->assertSame('juma@example.com', NlEntry::extractEmail('Add customer Juma with email juma@example.com'));
        $this->assertNull(NlEntry::extractEmail('Add customer Juma'));
    }

    /* ------------------------------------------------------------------ */
    /* Prices                                                             */
    /* ------------------------------------------------------------------ */

    public function test_a_per_unit_marker_makes_the_amount_a_unit_price(): void
    {
        $prices = NlEntry::extractPrices('I sold three units of soap for 2,000 each');

        $this->assertSame(2000.0, $prices['unit_price']);
        $this->assertFalse($prices['assumed']);
        $this->assertFalse($prices['ambiguous']);
    }

    public function test_a_single_amount_with_no_marker_is_flagged_as_an_assumption(): void
    {
        $prices = NlEntry::extractPrices('I sold 5 kilos of sugar at 3,500 to customer Amina');

        $this->assertSame(3500.0, $prices['unit_price']);
        $this->assertTrue($prices['assumed'], 'The seller must confirm that one amount was the price of ONE unit.');

        $draft = NlEntry::draft(NlEntry::TYPE_SALE, 'I sold 5 kilos of sugar at 3,500 to customer Amina', 'en', $this->today);
        $inspection = NlEntry::inspect(NlEntry::TYPE_SALE, $draft, $this->today);

        $this->assertContains(NlEntry::FLAG_PRICE_ASSUMED_UNIT, $inspection['flags']);
    }

    public function test_two_amounts_without_a_per_unit_marker_are_ambiguous(): void
    {
        $prices = NlEntry::extractPrices('I bought 4 items for 12000 and sold for 15000');

        $this->assertTrue($prices['ambiguous']);
    }

    public function test_a_selling_price_is_only_read_when_the_words_say_so(): void
    {
        $this->assertSame(22000.0, NlEntry::extractSellingPrice('Add a new product cement, 50 bags, buying 18000, selling 22000'));
        $this->assertNull(NlEntry::extractSellingPrice('Add 20 bottles of blue perfume to stock'));
    }

    /* ------------------------------------------------------------------ */
    /* Drafts + required fields                                           */
    /* ------------------------------------------------------------------ */

    public function test_a_product_draft_reports_every_missing_required_field(): void
    {
        $draft = NlEntry::draft(NlEntry::TYPE_PRODUCT, 'Add 20 bottles of blue perfume to stock', 'en', $this->today);
        $inspection = NlEntry::inspect(NlEntry::TYPE_PRODUCT, $draft, $this->today);

        $this->assertSame('blue perfume', $draft['name']);
        $this->assertSame(20, $draft['stock']);
        $this->assertSame('bottle', $draft['unit']);
        $this->assertNull($draft['price'], 'No price was stated, so none is invented.');

        foreach (['category', 'price', 'expected_selling_price'] as $field) {
            $this->assertContains($field, $inspection['missing_required'], "{$field} must be listed as missing.");
        }
    }

    public function test_an_expense_draft_is_tidied_and_categorised_from_the_words(): void
    {
        $draft = NlEntry::draft(NlEntry::TYPE_EXPENSE, "Record today's transport expense of 5,000 TSh.", 'en', $this->today);
        $inspection = NlEntry::inspect(NlEntry::TYPE_EXPENSE, $draft, $this->today);

        $this->assertSame(5000.0, $draft['amount']);
        $this->assertStringContainsString('transport', (string) $draft['description']);
        $this->assertStringNotContainsString('5', (string) $draft['description'], 'The amount must not be left inside the description.');
        $this->assertSame($this->today, $draft['expense_date']);
        $this->assertNotEmpty($draft['category']);
        $this->assertSame([], $inspection['missing_required']);
    }

    public function test_a_sale_draft_without_a_resolved_product_blocks_until_one_is_chosen(): void
    {
        $draft = NlEntry::draft(NlEntry::TYPE_SALE, 'I sold three units of soap for 2,000 each', 'en', $this->today);
        $draft['items'][0]['match'] = ['status' => 'ambiguous', 'product_id' => null, 'candidates' => []];
        $inspection = NlEntry::inspect(NlEntry::TYPE_SALE, $draft, $this->today);

        $this->assertContains(NlEntry::FLAG_PRODUCT_AMBIGUOUS, $inspection['flags']);
        $this->assertContains('items[0].product_id', $inspection['missing_required']);
    }

    public function test_a_sale_that_names_no_catalogue_product_is_flagged_not_invented(): void
    {
        $draft = NlEntry::draft(NlEntry::TYPE_SALE, 'I sold three units of soap for 2,000 each', 'en', $this->today);
        $draft['items'][0]['match'] = ['status' => 'not_found', 'product_id' => null, 'candidates' => []];
        $inspection = NlEntry::inspect(NlEntry::TYPE_SALE, $draft, $this->today);

        $this->assertContains(NlEntry::FLAG_PRODUCT_NOT_FOUND, $inspection['flags']);
        $this->assertNull($draft['items'][0]['product_id']);
    }

    public function test_a_customer_draft_needs_a_name_and_reports_a_missing_phone(): void
    {
        $draft = NlEntry::draft(NlEntry::TYPE_CUSTOMER, 'Add customer Amina', 'en', $this->today);
        $inspection = NlEntry::inspect(NlEntry::TYPE_CUSTOMER, $draft, $this->today);

        $this->assertSame('amina', $draft['name']);
        $this->assertContains(NlEntry::FLAG_PHONE_MISSING, $inspection['flags']);
        $this->assertContains('phone', $inspection['missing_required']);
    }

    public function test_a_future_date_is_flagged(): void
    {
        $tomorrow = gmdate('Y-m-d', time() + 86400);
        $draft = NlEntry::draft(NlEntry::TYPE_EXPENSE, "Record tomorrow's transport expense of 5,000 ({$tomorrow})", 'en', $this->today);
        $inspection = NlEntry::inspect(NlEntry::TYPE_EXPENSE, $draft, $this->today);

        $this->assertContains(NlEntry::FLAG_FUTURE_DATE, $inspection['flags']);
    }

    /* ------------------------------------------------------------------ */
    /* Match decisions - the "never guess" rule                           */
    /* ------------------------------------------------------------------ */

    public function test_a_clear_winner_is_matched(): void
    {
        $match = NlEntry::chooseMatch([
            ['id' => 'p1', 'name' => 'Blue perfume 50ml', 'relevance' => 0.9],
            ['id' => 'p2', 'name' => 'Blue perfume 100ml', 'relevance' => 0.4],
        ]);

        $this->assertSame('matched', $match['status']);
        $this->assertSame('p1', $match['product_id']);
    }

    public function test_a_near_tie_is_ambiguous_and_carries_no_id(): void
    {
        $match = NlEntry::chooseMatch([
            ['id' => 'p1', 'name' => 'Blue perfume 50ml', 'relevance' => 0.90],
            ['id' => 'p2', 'name' => 'Blue perfume 100ml', 'relevance' => 0.88],
        ]);

        $this->assertSame('ambiguous', $match['status']);
        $this->assertNull($match['product_id'], 'An ambiguous reference must NEVER carry an id.');
        $this->assertCount(2, $match['candidates']);
    }

    public function test_nothing_ranked_means_not_found(): void
    {
        $match = NlEntry::chooseMatch([]);

        $this->assertSame('not_found', $match['status']);
        $this->assertNull($match['product_id']);
        $this->assertSame([], $match['candidates']);
    }

    /* ------------------------------------------------------------------ */
    /* The model is confined                                             */
    /* ------------------------------------------------------------------ */

    public function test_the_model_may_only_return_an_allow_listed_type(): void
    {
        $this->assertSame(NlEntry::TYPE_SALE, NlEntry::sanitizeModel(['type' => 'SALE'])['type']);
        $this->assertNull(
            NlEntry::sanitizeModel(['type' => 'invoice'])['type'] ?? null,
            'An invented type must be dropped.'
        );
        $this->assertNull(NlEntry::sanitizeModel(['type' => 'DROP TABLE users', 'reason' => 'x'])['type']);
        $this->assertNull(NlEntry::sanitizeModel('not json'));
        $this->assertNull(NlEntry::sanitizeModel(null));
    }

    public function test_the_model_can_never_supply_a_number(): void
    {
        $clean = NlEntry::sanitizeModel(['type' => 'product', 'name' => '999', 'reason' => 'ok']);

        $this->assertNull($clean['name'], 'A numeric "name" is a number in disguise.');
    }

    public function test_a_model_name_is_only_kept_when_the_sentence_contains_it(): void
    {
        $this->assertTrue(NlEntry::modelNameIsGrounded('blue perfume', 'Add 20 bottles of Blue Perfume to stock'));
        $this->assertFalse(NlEntry::modelNameIsGrounded('red shoes', 'Add 20 bottles of blue perfume to stock'));
        $this->assertFalse(NlEntry::modelNameIsGrounded(null, 'Add 20 bottles of blue perfume to stock'));
        $this->assertFalse(NlEntry::modelNameIsGrounded('   ', 'Add 20 bottles of blue perfume to stock'));
    }

    public function test_the_prompt_tells_the_model_it_may_not_write_sql_or_invent_numbers(): void
    {
        $instruction = NlEntry::systemInstruction();

        $this->assertStringContainsString('product, sale, expense, customer', $instruction);
        $this->assertStringContainsString('NEVER invent', $instruction);
        $this->assertStringContainsString('SQL', $instruction);
    }

    /* ------------------------------------------------------------------ */
    /* Localized copy - all eight languages                               */
    /* ------------------------------------------------------------------ */

    public function test_every_locale_carries_every_ui_string(): void
    {
        $keys = array_keys(NlEntry::MESSAGES['en']);

        foreach (self::LOCALES as $locale) {
            $this->assertArrayHasKey($locale, NlEntry::MESSAGES, "{$locale} is missing from MESSAGES.");

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, NlEntry::MESSAGES[$locale], "MESSAGES/{$locale} is missing {$key}.");
                $this->assertNotSame('', trim(NlEntry::MESSAGES[$locale][$key]), "MESSAGES/{$locale}.{$key} is empty.");
            }
        }
    }

    public function test_every_flag_has_a_message_in_every_locale(): void
    {
        $flags = [
            NlEntry::FLAG_TYPE_UNCERTAIN, NlEntry::FLAG_TYPE_UNSUPPORTED, NlEntry::FLAG_NAME_MISSING,
            NlEntry::FLAG_CATEGORY_MISSING, NlEntry::FLAG_CATEGORY_UNCERTAIN, NlEntry::FLAG_AMOUNT_MISSING,
            NlEntry::FLAG_AMOUNT_AMBIGUOUS, NlEntry::FLAG_PRICE_MISSING, NlEntry::FLAG_PRICE_ASSUMED_UNIT,
            NlEntry::FLAG_PRICE_ASSUMED_TOTAL, NlEntry::FLAG_SELLING_PRICE_MISSING, NlEntry::FLAG_STOCK_MISSING,
            NlEntry::FLAG_QUANTITY_MISSING, NlEntry::FLAG_QUANTITY_AMBIGUOUS, NlEntry::FLAG_UNIT_UNCERTAIN,
            NlEntry::FLAG_DATE_AMBIGUOUS, NlEntry::FLAG_DATE_NOT_TODAY, NlEntry::FLAG_FUTURE_DATE,
            NlEntry::FLAG_PRODUCT_AMBIGUOUS, NlEntry::FLAG_PRODUCT_NOT_FOUND, NlEntry::FLAG_PRODUCT_NEW,
            NlEntry::FLAG_PRODUCT_EXISTS, NlEntry::FLAG_CUSTOMER_NEW, NlEntry::FLAG_CUSTOMER_AMBIGUOUS,
            NlEntry::FLAG_CUSTOMER_MISSING, NlEntry::FLAG_PHONE_MISSING,
        ];

        foreach ($flags as $flag) {
            foreach (self::LOCALES as $locale) {
                $message = NlEntry::flagMessage($flag, $locale);
                $this->assertNotSame($flag, $message, "{$flag}/{$locale} has no localized message.");
                $this->assertNotSame('', trim($message));
            }
        }
    }

    public function test_messages_and_flags_substitute_their_placeholders(): void
    {
        $this->assertStringContainsString('12', NlEntry::message('characters_left', 'en', ['count' => 12]));
        $this->assertStringNotContainsString('{count}', NlEntry::message('characters_left', 'de', ['count' => 3]));
        $this->assertStringContainsString($this->today, NlEntry::flagMessage(NlEntry::FLAG_DATE_NOT_TODAY, 'fr', ['date' => $this->today]));
        $this->assertStringNotContainsString('{date}', NlEntry::flagMessage(NlEntry::FLAG_FUTURE_DATE, 'zh', ['date' => $this->today]));
    }

    public function test_every_locale_has_limitations_and_type_labels(): void
    {
        foreach (self::LOCALES as $locale) {
            $this->assertNotEmpty(NlEntry::limitations($locale), "{$locale} needs limitation lines.");
            foreach (NlEntry::TYPES as $type) {
                $this->assertNotSame('', NlEntry::typeLabel($type, $locale), "{$locale} needs a label for {$type}.");
            }
        }

        $this->assertSame('', NlEntry::typeLabel('invoice', 'en'), 'A non-allow-listed type has no label.');
        $this->assertSame('', NlEntry::typeLabel(null, 'en'));
    }

    public function test_the_expense_categories_come_from_the_approved_list(): void
    {
        $this->assertNotEmpty(NlEntry::categories());
        $this->assertContains('Usafiri', NlEntry::categories());
    }

    /**
     * Drift guard: the service, the website catalogue and the eight app locale
     * files must agree on the whole `nl_entry` namespace.
     */
    public function test_the_service_and_both_locale_catalogues_agree_on_every_string(): void
    {
        $root = json_decode((string) file_get_contents(base_path('locales.json')), true);
        $keys = array_keys(NlEntry::MESSAGES['en']);
        sort($keys);

        foreach (self::LOCALES as $locale) {
            $appFile = base_path('Duka_mkononi/locales/'.$locale.'.json');
            $this->assertFileExists($appFile);

            $app = json_decode((string) file_get_contents($appFile), true);
            $this->assertIsArray($app, "{$locale}.json must stay valid JSON.");
            $this->assertArrayHasKey('nl_entry', $app, "{$locale}.json needs the nl_entry section.");

            $appKeys = array_keys($app['nl_entry']);
            sort($appKeys);
            $this->assertSame($keys, $appKeys, "Duka_mkononi/locales/{$locale}.json disagrees with NlEntry::MESSAGES.");

            $rootSection = $root['translations'][$locale]['nl_entry'] ?? null;
            $this->assertIsArray($rootSection, "locales.json translations.{$locale}.nl_entry is missing.");
            $rootKeys = array_keys($rootSection);
            sort($rootKeys);
            $this->assertSame($keys, $rootKeys, "locales.json translations.{$locale}.nl_entry disagrees with NlEntry::MESSAGES.");

            foreach ($keys as $key) {
                $this->assertSame(
                    NlEntry::MESSAGES[$locale][$key],
                    $app['nl_entry'][$key],
                    "{$locale}.{$key} differs between the service and the app catalogue."
                );
                $this->assertSame(
                    NlEntry::MESSAGES[$locale][$key],
                    $rootSection[$key],
                    "{$locale}.{$key} differs between the service and locales.json."
                );
            }
        }
    }
}
