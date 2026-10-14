<?php

namespace Tests\Unit;

use App\Services\Ai\ReviewAssistant;
use PHPUnit\Framework\TestCase;

/**
 * Capability #16 contract. The assistant is a moderation AID, so these tests
 * pin down:
 *  - objective checks (missing mandatory fields, stock 0 while listed,
 *    configurable policy terms, phone numbers, price anomalies) produce
 *    structured findings with category / severity / field / excerpt /
 *    localized explanation;
 *  - severity separates a DEFINITE violation from an UNCERTAIN concern, and
 *    claim keyword hits are ALWAYS concerns - never verdicts;
 *  - the policy is configurable and the service reads no config (pure);
 *  - the AI may only attach advisory verdicts: bounded ids, allowed enum,
 *    figure-free reasons - anything else is dropped, never guessed;
 *  - complete eight-locale message coverage with identical placeholders.
 *
 * Pure-function tests: no database, no HTTP, no Laravel helpers.
 */
class ReviewAssistantTest extends TestCase
{
    private const LANGS = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /* ------------------------------------------------------------------ */
    /* Fixtures                                                            */
    /* ------------------------------------------------------------------ */

    private function ad(array $overrides = []): array
    {
        return array_merge([
            'id' => 'ad-1',
            'user_id' => 'u-1',
            'title' => 'Duka la Sokoni',
            'description' => 'Mauzo makubwa ya bidhaa mbalimbali kwa bei nafuu',
            'media_url' => 'https://cdn.example/ad.jpg',
            'media_type' => 'image',
            'is_active' => true,
            'is_free' => true,
            'payment_status' => 'completed',
            'expires_at' => null,
        ], $overrides);
    }

    private function product(array $overrides = []): array
    {
        return array_merge([
            'id' => 'p-1',
            'seller_id' => 'u-1',
            'name' => 'Maji Safi 1L',
            'category' => 'Vinywaji',
            'description' => 'Maji safi ya kunywa kwa familia nzima',
            'image_url' => 'https://cdn.example/maji.png',
            'price' => 1000,
            'expected_selling_price' => 1500,
            'stock' => 12,
            'is_active' => true,
        ], $overrides);
    }

    /** The first finding with the given category, or fail loudly. */
    private function findByCategory(array $findings, string $category): array
    {
        foreach ($findings as $finding) {
            if (($finding['category'] ?? '') === $category) {
                return $finding;
            }
        }

        $this->fail('No '.$category.' finding was produced: '.json_encode($findings));
    }

    /* ------------------------------------------------------------------ */
    /* Messages                                                            */
    /* ------------------------------------------------------------------ */

    public function test_every_locale_carries_every_message_with_the_same_placeholders(): void
    {
        $this->assertTrue(ReviewAssistant::messagesComplete(), 'All eight locales must define every key.');

        $enKeys = array_keys(ReviewAssistant::MESSAGES['en']);
        $this->assertCount(22, $enKeys, 'The message table is the localized contract.');

        foreach (self::LANGS as $locale) {
            $this->assertArrayHasKey($locale, ReviewAssistant::MESSAGES, 'Locale missing: '.$locale);

            foreach ($enKeys as $key) {
                $this->assertArrayHasKey($key, ReviewAssistant::MESSAGES[$locale], $locale.' is missing '.$key);

                preg_match_all('/\{(\w+)\}/', ReviewAssistant::MESSAGES[$locale][$key], $own);
                preg_match_all('/\{(\w+)\}/', ReviewAssistant::MESSAGES['en'][$key], $en);
                sort($own[1]);
                sort($en[1]);
                $this->assertSame($en[1], $own[1], $locale.' '.$key.' has different placeholders.');
            }
        }
    }

    public function test_messages_fill_every_placeholder_for_every_locale(): void
    {
        $params = ['min' => 20, 'max' => 80, 'selling' => 'TZS 900', 'buying' => 'TZS 1,000', 'multiplier' => 20, 'term' => 'silaha'];

        foreach (array_keys(ReviewAssistant::MESSAGES['en']) as $key) {
            foreach (self::LANGS as $locale) {
                $text = ReviewAssistant::msg($key, $locale, $params);
                $this->assertNotSame('', $text, $locale.' '.$key.' rendered empty.');
                $this->assertStringNotContainsString('{', $text, $locale.' '.$key.' kept an unfilled placeholder.');
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Advertisements                                                       */
    /* ------------------------------------------------------------------ */

    public function test_a_complete_ad_produces_no_findings(): void
    {
        $this->assertSame([], ReviewAssistant::reviewAd($this->ad(), 'en'));
    }

    public function test_incomplete_ads_produce_the_right_findings_and_severities(): void
    {
        $findings = ReviewAssistant::reviewAd($this->ad([
            'title' => str_repeat('K', 100),
            'description' => '',
            'media_url' => '',
        ]), 'en');

        $byKey = [];
        foreach ($findings as $finding) {
            $byKey[$finding['category'].':'.$finding['field']] = $finding;
        }

        $this->assertSame(ReviewAssistant::SEVERITY_VIOLATION, $byKey['incomplete:description']['severity'], 'No description is objective.');
        $this->assertSame(ReviewAssistant::SEVERITY_VIOLATION, $byKey['incomplete:media_url']['severity'], 'No media is objective.');
        $this->assertSame(ReviewAssistant::SEVERITY_CONCERN, $byKey['incomplete:title']['severity'], 'A long title is a concern, not a violation.');
        $this->assertStringContainsString('80', $byKey['incomplete:title']['message']);
    }

    public function test_short_descriptions_excerpt_the_text_as_a_concern(): void
    {
        $findings = ReviewAssistant::reviewAd($this->ad(['description' => 'Sokoni Bora']), 'en');

        $this->assertCount(1, $findings);
        $this->assertSame('incomplete', $findings[0]['category'], 'A short description is incomplete; nothing else applies.');
        $this->assertSame(ReviewAssistant::SEVERITY_CONCERN, $findings[0]['severity']);
        $this->assertSame('Sokoni Bora', $findings[0]['excerpt']);
    }

    public function test_policy_terms_and_phone_numbers_are_violations_with_context(): void
    {
        $findings = ReviewAssistant::reviewAd($this->ad([
            'description' => 'Tunauza SILAHA mbalimbali, piga simu 0712345678 leo uchukue.',
        ]), 'en');

        $prohibited = $this->findByCategory($findings, 'prohibited');
        $this->assertSame(ReviewAssistant::SEVERITY_VIOLATION, $prohibited['severity']);
        $this->assertStringContainsString('SILAHA', $prohibited['excerpt'], 'The excerpt carries the relevant text.');
        $this->assertStringContainsString('silaha', $prohibited['message']);

        $contact = $this->findByCategory($findings, 'policy');
        $this->assertSame(ReviewAssistant::SEVERITY_VIOLATION, $contact['severity']);
        $this->assertStringContainsString('0712345678', $contact['excerpt']);
    }

    public function test_claim_keywords_are_always_concerns_never_violations(): void
    {
        $findings = ReviewAssistant::reviewAd($this->ad([
            'description' => 'Bei daima bora zaidi, guaranteed 100% satisfaction kwa wateja wote.',
        ]), 'en');

        $claims = array_values(array_filter($findings, fn ($f) => $f['category'] === 'claim'));
        $this->assertNotEmpty($claims, 'The claim keywords are flagged.');
        foreach ($claims as $claim) {
            $this->assertSame(ReviewAssistant::SEVERITY_CONCERN, $claim['severity'], 'A claim is never a definite violation.');
            $this->assertNotEmpty($claim['excerpt']);
        }
        $this->assertSame([], array_values(array_filter($findings, fn ($f) => $f['severity'] === ReviewAssistant::SEVERITY_VIOLATION)));
    }

    public function test_a_keyword_inside_a_word_is_not_a_claim(): void
    {
        $findings = ReviewAssistant::reviewAd($this->ad([
            'description' => 'Bestway brand ya bidhaa za ubora wa hali ya juu sana kabisa.',
        ]), 'en');

        $this->assertSame([], array_values(array_filter($findings, fn ($f) => $f['category'] === 'claim')), 'No word-boundary hit inside "Bestway".');
    }

    public function test_an_expired_active_paid_ad_is_flagged_but_free_or_hidden_ones_are_not(): void
    {
        $past = date('Y-m-d H:i:s', time() - 3600);

        $expired = ReviewAssistant::reviewAd($this->ad([
            'is_free' => false,
            'payment_status' => 'completed',
            'expires_at' => $past,
        ]), 'en');
        $this->assertSame(ReviewAssistant::SEVERITY_CONCERN, $this->findByCategory($expired, 'policy')['severity']);

        $free = ReviewAssistant::reviewAd($this->ad(['is_free' => true, 'expires_at' => $past]), 'en');
        $this->assertSame([], array_values(array_filter($free, fn ($f) => $f['field'] === 'expires_at')));

        $hidden = ReviewAssistant::reviewAd($this->ad([
            'is_active' => false, 'is_free' => false, 'expires_at' => $past,
        ]), 'en');
        $this->assertSame([], array_values(array_filter($hidden, fn ($f) => $f['field'] === 'expires_at')));
    }

    public function test_the_policy_is_configurable_and_never_reads_config(): void
    {
        $custom = [
            'min_description_length' => 60,
            'prohibited_terms' => ['leva'],
            'block_contact_details' => false,
        ];

        $findings = ReviewAssistant::reviewAd($this->ad([
            'description' => 'Mauzo ya bidhaa mbalimbali kwa bei nafuu kabisa leo',
        ]), 'en', $custom);
        $short = $this->findByCategory($findings, 'incomplete');
        $this->assertStringContainsString('60', $short['message'], 'The configured minimum applies.');

        $contact = ReviewAssistant::reviewAd($this->ad(['description' => 'Piga 0712345678']), 'en', $custom);
        $this->assertSame([], array_values(array_filter($contact, fn ($f) => $f['category'] === 'policy')), 'Contact checks can be disabled.');

        $terms = ReviewAssistant::reviewAd($this->ad(['description' => 'Bidhaa halali kabisa']), 'en', $custom);
        $this->assertSame([], array_values(array_filter($terms, fn ($f) => $f['category'] === 'prohibited')), 'Custom terms replace the defaults.');
    }

    /* ------------------------------------------------------------------ */
    /* Product listings                                                    */
    /* ------------------------------------------------------------------ */

    public function test_a_complete_product_produces_no_findings(): void
    {
        $this->assertSame([], ReviewAssistant::reviewProduct($this->product(), 'en'));
    }

    public function test_missing_mandatory_product_fields_are_violations(): void
    {
        $findings = ReviewAssistant::reviewProduct($this->product([
            'name' => '', 'category' => '', 'price' => null,
        ]), 'en');

        $fields = array_map(fn ($f) => $f['field'], array_filter(
            $findings,
            fn ($f) => $f['severity'] === ReviewAssistant::SEVERITY_VIOLATION
        ));
        sort($fields);
        $this->assertSame(['category', 'name', 'price'], $fields);
    }

    public function test_price_anomalies_are_concerns_with_both_numbers_in_the_excerpt(): void
    {
        $loss = ReviewAssistant::reviewProduct($this->product([
            'price' => 2000, 'expected_selling_price' => 1500,
        ]), 'en');
        $lossFinding = $this->findByCategory($loss, 'price');
        $this->assertSame(ReviewAssistant::SEVERITY_CONCERN, $lossFinding['severity'], 'A loss price may be a clearance sale.');
        $this->assertStringContainsString('1500', $lossFinding['excerpt']);
        $this->assertStringContainsString('2000', $lossFinding['excerpt']);

        $markup = ReviewAssistant::reviewProduct($this->product([
            'price' => 1000, 'expected_selling_price' => 50000,
        ]), 'en');
        $markupFinding = $this->findByCategory($markup, 'price');
        $this->assertSame(ReviewAssistant::SEVERITY_CONCERN, $markupFinding['severity']);
        $this->assertStringContainsString('20', $markupFinding['message']);
    }

    public function test_an_active_product_without_stock_is_a_violation_but_a_hidden_one_is_not(): void
    {
        $active = ReviewAssistant::reviewProduct($this->product(['stock' => 0]), 'en');
        $this->assertSame(ReviewAssistant::SEVERITY_VIOLATION, $this->findByCategory($active, 'policy')['severity']);

        $hidden = ReviewAssistant::reviewProduct($this->product(['stock' => 0, 'is_active' => false]), 'en');
        $this->assertSame([], array_values(array_filter($hidden, fn ($f) => $f['field'] === 'stock')));
    }

    public function test_incomplete_products_produce_concerns_not_violations(): void
    {
        $findings = ReviewAssistant::reviewProduct($this->product([
            'description' => '', 'image_url' => '', 'expected_selling_price' => null,
        ]), 'en');

        $this->assertNotEmpty($findings);
        foreach ($findings as $finding) {
            $this->assertSame(ReviewAssistant::SEVERITY_CONCERN, $finding['severity']);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Counts, excerpts and the advisory verdict gate                      */
    /* ------------------------------------------------------------------ */

    public function test_count_by_severity_splits_the_findings(): void
    {
        $findings = ReviewAssistant::reviewProduct($this->product([
            'name' => '', 'stock' => 0, 'image_url' => '', 'description' => '',
        ]), 'en');

        $counts = ReviewAssistant::countBySeverity($findings);
        $this->assertSame(2, $counts[ReviewAssistant::SEVERITY_VIOLATION], 'Name + stock.');
        $this->assertSame(2, $counts[ReviewAssistant::SEVERITY_CONCERN], 'Description + photo.');
    }

    public function test_claim_excerpts_are_bounded_and_point_at_their_findings(): void
    {
        $items = [
            ['type' => 'ad', 'title' => 'Ad A', 'findings' => [
                ['category' => 'claim', 'excerpt' => 'guaranteed 100% for all'],
                ['category' => 'incomplete', 'excerpt' => 'ignored'],
            ]],
            ['type' => 'product', 'title' => 'P B', 'findings' => [
                ['category' => 'claim', 'excerpt' => 'daima bora zaidi'],
                ['category' => 'claim', 'excerpt' => ''], // no context - skipped
            ]],
        ];

        $claims = ReviewAssistant::claimExcerpts($items, 1);
        $this->assertCount(1, $claims, 'The limit is honoured.');
        $this->assertSame('1', $claims[0]['id']);
        $this->assertSame(['item' => 0, 'finding' => 0], $claims[0]['ref']);
        $this->assertSame('ad', $claims[0]['type']);

        $all = ReviewAssistant::claimExcerpts($items, 6);
        $this->assertCount(2, $all, 'The empty-excerpt claim is skipped.');
        $this->assertSame(['item' => 1, 'finding' => 0], $all[1]['ref']);
    }

    public function test_verdicts_are_accepted_only_for_asked_ids_in_the_allowed_enum(): void
    {
        $raw = [
            'verdicts' => [
                ['id' => '1', 'verdict' => 'supported', 'reason' => 'The text itself states the quality'],
                ['id' => '2', 'verdict' => 'definitely-true', 'reason' => 'nope'],
                ['id' => '99', 'verdict' => 'uncertain', 'reason' => 'an id we never asked about'],
                ['id' => '3', 'verdict' => 'uncertain', 'reason' => 'sales of 9000 units'],
                ['id' => '4', 'verdict' => 'unsupported', 'reason' => str_repeat('x', 200)],
                'not an array',
            ],
        ];

        $verdicts = ReviewAssistant::sanitizeVerdicts($raw, ['1', '2', '3', '4']);
        $this->assertNotNull($verdicts);
        $this->assertSame(['#1'], array_keys($verdicts), 'Only the valid, asked-for verdict survives (string ids never become int keys).');
        $this->assertSame('supported', $verdicts['#1']['verdict']);
        $this->assertSame('1', $verdicts['#1']['id'], 'The id inside the entry stays the exact string.');
        $this->assertArrayNotHasKey('#2', $verdicts, 'An unknown verdict enum is dropped.');
        $this->assertArrayNotHasKey('#3', $verdicts, 'A figure we never supplied is dropped.');
        $this->assertArrayNotHasKey('#4', $verdicts, 'An over-long reason is dropped.');
    }

    public function test_malformed_verdict_payloads_are_rejected(): void
    {
        $this->assertNull(ReviewAssistant::sanitizeVerdicts('a string', ['1']));
        $this->assertNull(ReviewAssistant::sanitizeVerdicts(null, ['1']));
        $this->assertNull(ReviewAssistant::sanitizeVerdicts(['text' => 'no verdicts'], ['1']));
        $this->assertSame([], ReviewAssistant::sanitizeVerdicts(['verdicts' => []], ['1']), 'An empty reply is fine - verdicts are optional.');
    }

    public function test_the_classification_instruction_is_json_only_and_localized(): void
    {
        $instruction = ReviewAssistant::classificationInstruction('sw');

        $this->assertStringContainsString('STRICT JSON', $instruction);
        $this->assertStringContainsString('"sw"', $instruction);
        $this->assertStringContainsString('advisory', $instruction);
    }
}
