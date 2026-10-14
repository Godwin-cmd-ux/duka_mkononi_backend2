<?php

namespace Tests\Unit;

use App\Services\Ai\AdCopyService;
use Tests\TestCase;

/**
 * Capability #8 grounding contract. The sanitizer is the only gate between a
 * model draft and the seller's preview, so these tests pin down:
 *  - numbers must exist in the seller's own facts (no fabricated prices or
 *    discounts; seller-declared percentages allowed),
 *  - guarantee/refund language must already be in the seller's own text,
 *  - the product name survives verbatim (brand preservation),
 *  - configured length caps are enforced with an honest flag,
 *  - every flag, failure reason and limitation exists in all 8 locales.
 */
class AdCopyServiceTest extends TestCase
{
    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /** @return array<string, mixed> */
    private function product(array $overrides = []): array
    {
        return array_merge([
            'id' => 'p1',
            'name' => 'Mchele wa Soko',
            'category' => 'Chakula',
            'description' => 'Mchele safi wa kilo kutoka kwa wakulima wetu.',
            'price' => 5000.0,
            'expected_selling_price' => 7000.0,
            'stock' => 10.0,
            'image_url' => 'https://res.cloudinary.com/demo/sample.jpg',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function controls(array $overrides = []): array
    {
        return array_merge([
            'content_type' => 'description',
            'tone' => 'friendly',
            'length' => 'medium',
            'audience' => 'Familia',
            'objective' => '',
        ], $overrides);
    }

    public function test_grounded_copy_passes_unchanged(): void
    {
        $result = AdCopyService::sanitize([
            'headline' => 'Mchele wa Soko - 5,000 TZS kwa kilo',
            'body' => 'Ununue Mchele wa Soko leo. Bei ni 5,000 TZS tu, ubora wa hali ya juu kwa familia yako.',
        ], $this->product(), $this->controls(), 'sw');

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('headline', $result['copy']);
        $this->assertArrayHasKey('body', $result['copy']);
        $this->assertSame([], $result['flags']);
        $this->assertNull($result['code']);
    }

    public function test_price_not_in_product_data_is_rejected(): void
    {
        $result = AdCopyService::sanitize([
            'headline' => 'Mchele wa Soko bei nafuu',
            'body' => 'Mchele wa Soko leo ni 8,999 TZS tu!',
        ], $this->product(), $this->controls(), 'sw');

        $this->assertFalse($result['ok']);
        $this->assertSame(AdCopyService::FAIL_UNFACTUAL, $result['code']);
        $this->assertNotSame('', $result['reason']);
        $this->assertSame([], $result['copy']);
    }

    public function test_seller_declared_discount_is_allowed_but_invented_one_is_not(): void
    {
        $controls = $this->controls(['objective' => 'Punguzo la 10% wiki hii ya mauzo']);

        $allowed = AdCopyService::sanitize([
            'headline' => 'Mchele wa Soko - punguzo la 10%',
            'body' => 'Ununuzi wa Mchele wa Soko kwa punguzo la 10% wiki hii.',
        ], $this->product(), $controls, 'sw');
        $this->assertTrue($allowed['ok'], 'A discount declared by the seller in the objective must pass.');

        $invented = AdCopyService::sanitize([
            'headline' => 'Mchele wa Soko - punguzo la 25%',
            'body' => 'Punguzo la 25% kwa Mchele wa Soko leo.',
        ], $this->product(), $controls, 'sw');
        $this->assertFalse($invented['ok']);
        $this->assertSame(AdCopyService::FAIL_UNFACTUAL, $invented['code']);
    }

    public function test_guarantee_language_requires_seller_source_text(): void
    {
        $reject = AdCopyService::sanitize([
            'headline' => 'Mchele wa Soko - dhamana ya mwaka',
            'body' => 'Mchele wa Soko na dhamana ya mwaka mmoja bila shida.',
        ], $this->product(), $this->controls(), 'sw');

        $this->assertFalse($reject['ok']);
        $this->assertSame(AdCopyService::FAIL_CLAIM, $reject['code']);

        $sourced = $this->product([
            'description' => 'Mchele safi wa kilo. Dhamana ya mwaka mmoja kwa ubora.',
        ]);
        $allow = AdCopyService::sanitize([
            'headline' => 'Mchele wa Soko - dhamana ya mwaka',
            'body' => 'Mchele wa Soko na dhamana ya mwaka mmoja kama ilivyo kwenye maelezo.',
        ], $sourced, $this->controls(), 'sw');

        $this->assertTrue($allow['ok'], 'Guarantee wording the seller already owns must pass.');
    }

    public function test_product_name_must_survive_and_out_of_stock_is_flagged(): void
    {
        $result = AdCopyService::sanitize([
            'headline' => 'Bidhaa bora ya chakula',
            'body' => 'Ununuzi wa bidhaa bora ya chakula kwa bei ya 5,000 TZS.',
        ], $this->product(['stock' => 0.0]), $this->controls(), 'sw');

        $this->assertTrue($result['ok'], 'A missing brand is a fixable flag, not a rejection.');
        $codes = array_column($result['flags'], 'code');
        $this->assertContains(AdCopyService::FLAG_BRAND_MISSING, $codes);
        $this->assertContains(AdCopyService::FLAG_OUT_OF_STOCK, $codes);
    }

    public function test_long_body_is_clipped_at_sentence_with_flag(): void
    {
        $sentence = 'Mchele mzuri wa kaya kwa familia nzima yenye ubora wa hali ya juu sana. ';
        $body = str_repeat($sentence, 12).'Mchele wa Soko.';

        $result = AdCopyService::sanitize([
            'headline' => 'Mchele wa Soko',
            'body' => $body,
        ], $this->product(), $this->controls(), 'sw');

        $cap = AdCopyService::limits('description')['body'];
        $this->assertTrue($result['ok']);
        $this->assertLessThanOrEqual($cap, mb_strlen($result['copy']['body']));
        $this->assertContains(AdCopyService::FLAG_TRUNCATED, array_column($result['flags'], 'code'));
    }

    public function test_content_type_shapes_required_fields(): void
    {
        // headline type: body is dropped, headline is mandatory.
        $headlineOnly = AdCopyService::sanitize([
            'headline' => 'Mchele wa Soko bei nzuri',
            'body' => 'Sentensi isiyo ya kuhitajika hapa.',
        ], $this->product(), $this->controls(['content_type' => 'headline']), 'sw');
        $this->assertTrue($headlineOnly['ok']);
        $this->assertArrayNotHasKey('body', $headlineOnly['copy']);

        $missingHeadline = AdCopyService::sanitize([
            'headline' => '   ',
            'body' => 'Kitu chochote.',
        ], $this->product(), $this->controls(['content_type' => 'headline']), 'sw');
        $this->assertFalse($missingHeadline['ok']);
        $this->assertSame(AdCopyService::FAIL_EMPTY, $missingHeadline['code']);

        // product_description type: body is mandatory, headline dropped.
        $bodyOnly = AdCopyService::sanitize([
            'headline' => 'Kichwa kisichohitajika',
            'body' => 'Mchele wa Soko ni wa asili na safi kwa kila familia. Bei 5,000 TZS.',
        ], $this->product(), $this->controls(['content_type' => 'product_description']), 'sw');
        $this->assertTrue($bodyOnly['ok']);
        $this->assertArrayNotHasKey('headline', $bodyOnly['copy']);

        $noBody = AdCopyService::sanitize([
            'headline' => 'Mchele wa Soko',
            'body' => '',
        ], $this->product(), $this->controls(['content_type' => 'product_description']), 'sw');
        $this->assertFalse($noBody['ok']);
        $this->assertSame(AdCopyService::FAIL_EMPTY, $noBody['code']);
    }

    public function test_html_and_control_characters_are_stripped(): void
    {
        $result = AdCopyService::sanitize([
            'headline' => '<b>Mchele wa Soko</b>',
            'body' => "Mchele\x07 wa Soko kwa famil\x01ia. Bei 5,000 TZS.",
        ], $this->product(), $this->controls(), 'sw');

        $this->assertTrue($result['ok']);
        $this->assertStringNotContainsString('<b>', $result['copy']['headline']);
        $this->assertStringNotContainsString("\x07", $result['copy']['body']);
        $this->assertStringNotContainsString("\x01", $result['copy']['body']);
    }

    public function test_every_flag_failure_and_limitation_exists_in_all_eight_locales(): void
    {
        foreach (self::LOCALES as $locale) {
            $longBody = str_repeat('Mchele mzuri wa kaya. ', 40).'Mchele wa Soko.';

            $truncated = AdCopyService::sanitize(['headline' => 'Mchele wa Soko', 'body' => $longBody], $this->product(), $this->controls(), $locale);
            $brand = AdCopyService::sanitize(['headline' => 'Chakula bora', 'body' => 'Kwa bei ya 5,000 TZS tu.'], $this->product(), $this->controls(), $locale);
            $stock = AdCopyService::sanitize(['headline' => 'Mchele wa Soko', 'body' => 'Mchele wa Soko kwa 5,000 TZS.'], $this->product(['stock' => 0]), $this->controls(), $locale);

            $flagged = array_merge($truncated['flags'], $brand['flags'], $stock['flags']);
            $codes = array_column($flagged, 'code');
            foreach ([AdCopyService::FLAG_TRUNCATED, AdCopyService::FLAG_BRAND_MISSING, AdCopyService::FLAG_OUT_OF_STOCK] as $code) {
                $this->assertContains($code, $codes, "Flag {$code} missing while testing {$locale}.");
            }
            foreach ($flagged as $flag) {
                $this->assertNotSame('', trim($flag['message']), "Empty flag message in {$locale}.");
                $this->assertNotSame($flag['code'], $flag['message'], "Flag message fell back to the raw code in {$locale}.");
            }

            $empty = AdCopyService::sanitize(null, $this->product(), $this->controls(), $locale);
            $unfactual = AdCopyService::sanitize(['headline' => 'Mchele wa Soko', 'body' => 'Bei ni 12,345 TZS.'], $this->product(), $this->controls(), $locale);
            $claim = AdCopyService::sanitize(['headline' => 'Mchele wa Soko', 'body' => 'Mchele wa Soko na dhamana ya mwaka.'], $this->product(), $this->controls(), $locale);

            foreach ([$empty, $unfactual, $claim] as $failed) {
                $this->assertFalse($failed['ok']);
                $this->assertNotNull($failed['code'], "Expected a failure code in {$locale}.");
                $this->assertNotSame('', trim((string) $failed['reason']), "Empty failure reason in {$locale}.");
                $this->assertNotSame($failed['code'], $failed['reason'], "Failure reason fell back to the raw code in {$locale}.");
            }

            $limits = AdCopyService::limitations($locale);
            $this->assertCount(4, $limits, "Expected 4 limitation lines in {$locale}.");
            foreach ($limits as $line) {
                $this->assertNotSame('', trim($line), "Empty limitation line in {$locale}.");
            }
        }
    }

    public function test_limits_and_target_bands_follow_configured_caps(): void
    {
        $headline = AdCopyService::limits('headline');
        $this->assertSame(0, $headline['body']);
        $this->assertGreaterThan(0, $headline['headline']);

        $this->assertSame(
            (int) config('ai.ad_copy.max_promo'),
            AdCopyService::limits('promo')['body']
        );
        $this->assertSame(
            (int) config('ai.ad_copy.max_product_description'),
            AdCopyService::limits('product_description')['body']
        );

        $short = AdCopyService::targetBand('short', 400);
        $medium = AdCopyService::targetBand('medium', 400);
        $long = AdCopyService::targetBand('long', 400);
        $this->assertLessThan($medium[1], $short[1]);
        $this->assertLessThan($long[1], $medium[1]);
        $this->assertLessThanOrEqual($long[1], 400);
    }

    public function test_prompt_carries_only_authorized_facts(): void
    {
        $product = $this->product();
        $controls = $this->controls(['objective' => 'Mauzo ya mwisho ya mwezi']);

        $prompt = AdCopyService::userPrompt('sw', $product, $controls);

        $this->assertStringContainsString('Mchele wa Soko', $prompt);
        $this->assertStringContainsString('Mauzo ya mwisho ya mwezi', $prompt);
        $this->assertStringContainsString('"price"', $prompt);
        $this->assertStringContainsString('"stock"', $prompt);
        $this->assertStringContainsString('"limits"', $prompt);
        // The image URL is not part of the text contract - copy never needs it.
        $this->assertStringNotContainsString('image_url', $prompt);

        $system = AdCopyService::systemInstruction();
        $this->assertStringContainsString('STRICT JSON', $system);
        $this->assertStringContainsString('verbatim', $system);
        $this->assertStringContainsString('NEVER invent prices', $system);
    }
}
