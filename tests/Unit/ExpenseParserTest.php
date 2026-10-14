<?php

namespace Tests\Unit;

use App\Services\Ai\ExpenseParser;
use App\Services\ExpenseCategories;
use PHPUnit\Framework\TestCase;

/**
 * Capability #7: amounts, dates and categories must be extracted from the
 * seller's own words by trusted code, ambiguity must be reported instead of
 * guessed away, and the model may never introduce a number the text does not
 * contain. Pure-function tests: no database, no HTTP, no Laravel helpers.
 */
class ExpenseParserTest extends TestCase
{
    private const TODAY = '2026-10-12';

    /* ------------------------------------------------------------------ */
    /* Amounts                                                            */
    /* ------------------------------------------------------------------ */

    public function test_extracts_plain_and_grouped_amounts(): void
    {
        $plain = ExpenseParser::extractAmounts('Nauli ya usafiri 5000');
        $this->assertCount(1, $plain);
        $this->assertSame(5000.0, $plain[0]['value']);
        $this->assertFalse($plain[0]['tagged']);

        $tagged = ExpenseParser::extractAmounts('Nililipa 20,000 TSh kwa umeme');
        $this->assertCount(1, $tagged);
        $this->assertSame(20000.0, $tagged[0]['value']);
        $this->assertTrue($tagged[0]['tagged'], 'A currency token marks the candidate as explicit money.');

        $spaced = ExpenseParser::extractAmounts('Malipo 5 000 /=');
        $this->assertCount(1, $spaced);
        $this->assertSame(5000.0, $spaced[0]['value']);
    }

    public function test_normalize_amount_enforces_bounds(): void
    {
        $this->assertSame(20000.0, ExpenseParser::normalizeAmount('20,000 TSh'));
        $this->assertNull(ExpenseParser::normalizeAmount('0'), 'Zero is not a valid expense.');
        $this->assertNull(ExpenseParser::normalizeAmount('-500'), 'Negative amounts are rejected.');
        $this->assertNull(ExpenseParser::normalizeAmount('abc'));
        $this->assertNull(ExpenseParser::normalizeAmount('99999999999999999'), 'Above the maximum.');
        $this->assertNull(ExpenseParser::normalizeAmount('12.345'), 'At most two decimals.');
    }

    public function test_dates_and_phone_numbers_are_never_amounts(): void
    {
        $amounts = ExpenseParser::extractAmounts('Ada ya 2026-01-05 ni 5000');
        $this->assertCount(1, $amounts);
        $this->assertSame(5000.0, $amounts[0]['value']);

        $phone = ExpenseParser::extractAmounts('Nimepigwa 0712345678 kulipia 3000');
        $this->assertCount(1, $phone);
        $this->assertSame(3000.0, $phone[0]['value'], 'A 10-digit phone number must not become money.');
    }

    public function test_year_like_numbers_are_dropped_unless_currency_tagged(): void
    {
        $yearOnly = ExpenseParser::extractAmounts('Mwaka 2026');
        $chosen = ExpenseParser::chooseAmount($yearOnly);
        $this->assertNull($chosen['amount'], 'A bare year must never be proposed as an amount.');

        $tagged = ExpenseParser::extractAmounts('Mwaka 2026, lipia 2050 TSh');
        $chosenTagged = ExpenseParser::chooseAmount($tagged);
        $this->assertSame(2050.0, $chosenTagged['amount'], 'The currency-tagged candidate wins over the year.');
        $this->assertFalse($chosenTagged['ambiguous']);
    }

    public function test_multiple_amounts_are_reported_as_ambiguous(): void
    {
        $result = ExpenseParser::parse('Nunua kopo 5000 na boxi 20000', 'sw', self::TODAY);

        $this->assertSame(5000.0, $result['preview']['amount'], 'The first candidate is proposed for editing.');
        $this->assertContains(ExpenseParser::FLAG_AMOUNT_AMBIGUOUS, $result['flags']);
        $this->assertNotContains(ExpenseParser::FLAG_AMOUNT_MISSING, $result['flags']);
    }

    public function test_missing_amount_is_flagged_and_required(): void
    {
        $result = ExpenseParser::parse('Nunua sukuma wiki kwa duka', 'sw', self::TODAY);

        $this->assertNull($result['preview']['amount']);
        $this->assertContains(ExpenseParser::FLAG_AMOUNT_MISSING, $result['flags']);
        $this->assertContains('amount', $result['missing_required']);
    }

    /* ------------------------------------------------------------------ */
    /* Dates                                                              */
    /* ------------------------------------------------------------------ */

    public function test_relative_days_resolve_in_the_active_locale_and_english(): void
    {
        $this->assertSame('2026-10-12', ExpenseParser::extractDate('Nililipa leo', self::TODAY, 'sw')['date']);
        $this->assertSame('2026-10-11', ExpenseParser::extractDate('Nililipa jana', self::TODAY, 'sw')['date']);
        $this->assertSame('2026-10-10', ExpenseParser::extractDate('Nililipa juzi', self::TODAY, 'sw')['date']);
        $this->assertSame('2026-10-11', ExpenseParser::extractDate('Paid yesterday', self::TODAY, 'sw')['date'], 'English words work even under another locale.');
        $this->assertSame('2026-10-12', ExpenseParser::extractDate("Payé aujourd'hui", self::TODAY, 'fr')['date']);
        $this->assertSame('2026-10-12', ExpenseParser::extractDate('今天付了', self::TODAY, 'zh')['date']);
    }

    public function test_iso_dates_resolve_and_invalid_slash_dates_stay_ambiguous(): void
    {
        $iso = ExpenseParser::extractDate('Ada ya 2026-10-05', self::TODAY, 'sw');
        $this->assertSame('2026-10-05', $iso['date']);
        $this->assertFalse($iso['ambiguous']);
        $this->assertFalse($iso['future']);

        $slash = ExpenseParser::extractDate('Ada ya 12/03/2026', self::TODAY, 'sw');
        $this->assertNull($slash['date'], 'dd/mm vs mm/dd is never guessed.');
        $this->assertTrue($slash['ambiguous']);

        $mixed = ExpenseParser::extractDate('jana ya 2026-10-10', self::TODAY, 'sw');
        $this->assertSame('2026-10-10', $mixed['date']);
        $this->assertTrue($mixed['ambiguous'], 'Two different date signals must be reported.');
    }

    public function test_not_today_and_future_dates_are_flagged(): void
    {
        $past = ExpenseParser::parse('Nililipa jana 5000 kwa maji', 'sw', self::TODAY);
        $this->assertContains(ExpenseParser::FLAG_DATE_NOT_TODAY, $past['flags']);
        $this->assertNotContains(ExpenseParser::FLAG_FUTURE_DATE, $past['flags']);

        $future = ExpenseParser::parse('Nitakulipa kesho 5000 kwa maji', 'sw', self::TODAY);
        $this->assertContains(ExpenseParser::FLAG_FUTURE_DATE, $future['flags']);
        $this->assertNotContains(ExpenseParser::FLAG_DATE_NOT_TODAY, $future['flags']);

        $today = ExpenseParser::parse('Leo 5000 kwa maji', 'sw', self::TODAY);
        $this->assertNotContains(ExpenseParser::FLAG_DATE_NOT_TODAY, $today['flags']);
        $this->assertNotContains(ExpenseParser::FLAG_FUTURE_DATE, $today['flags']);
    }

    /* ------------------------------------------------------------------ */
    /* Categories                                                         */
    /* ------------------------------------------------------------------ */

    public function test_keyword_matching_covers_representative_categories(): void
    {
        $this->assertSame('Usafiri', ExpenseParser::matchCategory('Nauli ya usafiri 5000'));
        $this->assertSame('Kodisho za Nguvu', ExpenseParser::matchCategory('Nililipa umeme wa duka'));
        $this->assertSame('Maji', ExpenseParser::matchCategory('Nunua maji ya kunywa'));
        $this->assertSame('Vifaa vya Ofisi', ExpenseParser::matchCategory('Nunua karatasi za ofisi'));
        $this->assertSame('Ada za Benki', ExpenseParser::matchCategory('M-Pesa transfer fee'));
        $this->assertSame('Matengenezo', ExpenseParser::matchCategory('Garage repair ya pikipiki'));
        $this->assertSame('Mengineyo', ExpenseParser::matchCategory('Mengineyo tu'), 'The explicit fallback still matches.');
    }

    public function test_no_match_or_a_tie_returns_null_instead_of_guessing(): void
    {
        $this->assertNull(ExpenseParser::matchCategory('Hakuna kitu hapa'), 'No keyword means no confident category.');

        $tied = ExpenseParser::matchCategory('maji na simu');
        $this->assertNull($tied, 'An exact tie between two categories must not be silently broken.');
    }

    public function test_uncertain_category_is_flagged_and_required(): void
    {
        $result = ExpenseParser::parse('Hakuna kitu hapa', 'sw', self::TODAY);

        $this->assertNull($result['preview']['category']);
        $this->assertContains(ExpenseParser::FLAG_CATEGORY_UNCERTAIN, $result['flags']);
        $this->assertContains('category', $result['missing_required']);
    }

    public function test_model_output_is_strictly_allowlisted_and_never_invents_amounts(): void
    {
        $valid = ExpenseParser::sanitizeModel(['description' => 'Nime lipia 5000', 'category' => 'Usafiri']);
        $this->assertSame('Usafiri', $valid['category']);

        $rejected = ExpenseParser::sanitizeModel(['description' => 'Note text', 'category' => 'Not A Category']);
        $this->assertNull($rejected['category'], 'A category outside the allow-list is dropped.');
        $this->assertSame('Note text', $rejected['description']);

        $this->assertNull(ExpenseParser::sanitizeModel([]), 'Nothing usable means null.');
        $this->assertNull(ExpenseParser::sanitizeModel('not-an-array'));
        $this->assertNull(ExpenseParser::sanitizeModel(['description' => 'ok', 'category' => 'ok']), 'Short descriptions are rejected.');

        // Even a valid model block cannot put money into a note that has none.
        $result = ExpenseParser::parse('Nunua nafaka', 'sw', self::TODAY, [
            'description' => 'Manunuzi ya nafaka',
            'category' => 'Malighafi',
        ]);
        $this->assertNull($result['preview']['amount'], 'Amounts come only from the seller\u2019s own text.');
        $this->assertContains(ExpenseParser::FLAG_AMOUNT_MISSING, $result['flags']);
        $this->assertSame('Malighafi', $result['preview']['category'], 'The allowed model category fills the keyword gap.');
        $this->assertNotContains(ExpenseParser::FLAG_CATEGORY_UNCERTAIN, $result['flags']);
    }

    public function test_description_is_cleaned_and_bounded(): void
    {
        $this->assertSame('nafaka bora', ExpenseParser::cleanDescription("  nafaka   bora  "));

        $long = ExpenseParser::cleanDescription(str_repeat('a ', 300));
        $this->assertSame(ExpenseParser::MAX_DESCRIPTION, mb_strlen($long));

        $result = ExpenseParser::parse('ok', 'sw', self::TODAY);
        $this->assertContains('description', $result['missing_required'], 'A description under 3 characters is not enough to save.');
    }

    /* ------------------------------------------------------------------ */
    /* Localized copy + allow-list contract                               */
    /* ------------------------------------------------------------------ */

    public function test_every_flag_message_exists_in_all_eight_locales(): void
    {
        $codes = [
            ExpenseParser::FLAG_AMOUNT_MISSING,
            ExpenseParser::FLAG_AMOUNT_AMBIGUOUS,
            ExpenseParser::FLAG_CATEGORY_UNCERTAIN,
            ExpenseParser::FLAG_DATE_AMBIGUOUS,
            ExpenseParser::FLAG_DATE_NOT_TODAY,
            ExpenseParser::FLAG_FUTURE_DATE,
        ];

        foreach ($codes as $code) {
            foreach (['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'] as $locale) {
                $message = ExpenseParser::flagMessage($code, $locale);
                $this->assertNotSame('', $message, "{$code}/{$locale} must be translated.");
                $this->assertNotSame($code, $message, "{$code}/{$locale} must not fall back to the raw code.");
            }
        }

        $dated = ExpenseParser::flagMessage(ExpenseParser::FLAG_DATE_NOT_TODAY, 'en', ['date' => self::TODAY]);
        $this->assertStringContainsString(self::TODAY, $dated, 'The placeholder must be replaced.');
        $this->assertStringNotContainsString('{date}', $dated);
    }

    public function test_limitations_exist_for_all_eight_locales(): void
    {
        foreach (['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'] as $locale) {
            $lines = ExpenseParser::limitations($locale);
            $this->assertGreaterThanOrEqual(2, count($lines), "Locale {$locale} needs limitation lines.");
            foreach ($lines as $line) {
                $this->assertNotSame('', $line);
            }
        }
    }

    public function test_categories_allow_list_is_the_exact_endpoint_contract(): void
    {
        $this->assertSame([
            'Kodisho za Nguvu',
            'Maji',
            'Mawasiliano',
            'Usafiri',
            'Chakula cha Mchana',
            'Vifaa vya Ofisi',
            'Matengenezo',
            'Ushuru',
            'Ada za Benki',
            'Malighafi',
            'Ufungashaji',
            'Mengineyo',
        ], ExpenseCategories::all(), 'GET office-expenses/categories serves exactly this list.');

        $this->assertTrue(ExpenseCategories::isValid('Usafiri'));
        $this->assertFalse(ExpenseCategories::isValid('Not A Category'));
        $this->assertFalse(ExpenseCategories::isValid(''));
    }

    public function test_parse_respects_the_text_length_cap(): void
    {
        $result = ExpenseParser::parse('5000 '.str_repeat('a', 1000), 'sw', self::TODAY);

        $this->assertLessThanOrEqual(ExpenseParser::MAX_TEXT, mb_strlen($result['preview']['description']));
        $this->assertSame(5000.0, $result['preview']['amount'], 'The amount appears before the cap, so it survives truncation.');
    }
}
