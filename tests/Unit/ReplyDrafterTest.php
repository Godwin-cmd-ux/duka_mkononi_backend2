<?php

namespace Tests\Unit;

use App\Services\Ai\ReplyDrafter;
use Tests\TestCase;

/**
 * Capability #9 grounding contract. The sanitizer is the only gate between a
 * model draft and the seller's note box, so these tests pin down:
 *  - money-scale numbers must exist on this order (no fabricated prices),
 *  - delivery-time promises and discount/refund sentences are stripped unless
 *    the seller already put that commitment into a customer-visible note,
 *  - a nearly identical repeat of an already-sent note is flagged (duplicate
 *    send guard),
 *  - missing information only the seller can supply is listed deterministically,
 *  - configured length caps are enforced with an honest flag,
 *  - every flag, failure reason, missing-info line and limitation exists in
 *    all 8 locales,
 *  - the prompt carries the order's own facts and never internal notes.
 */
class ReplyDrafterTest extends TestCase
{
    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /** @return array<string, mixed> */
    private function order(array $overrides = []): array
    {
        return array_merge([
            'id' => 'o1',
            'order_reference' => 'DM-20261012-ABC123',
            'status' => 'processing',
            'payment_status' => 'unpaid',
            'customer_name' => 'Asha',
            'customer_note' => 'Oda yangu iko wapi? Na lini itafika?',
            'customer_visible_note' => '',
            'subtotal' => 15000.0,
            'shipping_cost' => 2000.0,
            'total_amount' => 17000.0,
            'currency' => 'TZS',
            'items' => [
                ['product_name' => 'Mchele wa Soko', 'unit_price' => 5000.0, 'quantity' => 3, 'line_total' => 15000.0],
            ],
            'visible_notes' => [],
            'last_visible_note_at' => null,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function controls(array $overrides = []): array
    {
        return array_merge(['tone' => 'casual'], $overrides);
    }

    public function test_grounded_reply_passes_unchanged(): void
    {
        $result = ReplyDrafter::sanitize([
            'text' => 'Habari Asha, asante kwa oda DM-20261012-ABC123. Jumla ni 17,000 TZS ikiwa ni mchele 3 kwa 5,000 TZS na usafirishaji 2,000 TZS. Tutakujulisha mara oda iko tayari.',
        ], $this->order(), $this->controls(), 'sw');

        $this->assertTrue($result['ok']);
        $this->assertNotSame('', $result['text']);
        $this->assertSame([], $result['flags']);
        $this->assertNull($result['code']);
    }

    public function test_fabricated_amount_is_rejected(): void
    {
        $result = ReplyDrafter::sanitize([
            'text' => 'Bei ni 9,999 TZS tu kwa oda hii.',
        ], $this->order(), $this->controls(), 'sw');

        $this->assertFalse($result['ok']);
        $this->assertSame(ReplyDrafter::FAIL_AMOUNT, $result['code']);
        $this->assertSame('', $result['text']);
        $this->assertNotNull($result['reason']);
    }

    public function test_real_order_amounts_are_allowed(): void
    {
        foreach ([5000, 15000, 2000, 17000] as $amount) {
            $result = ReplyDrafter::sanitize([
                'text' => 'Kiasi cha oda ni '.$amount.' TZS.',
            ], $this->order(), $this->controls(), 'sw');

            $this->assertTrue($result['ok'], 'Amount '.$amount.' must be allowed.');
        }
    }

    public function test_quoting_the_order_reference_or_a_date_is_not_a_money_claim(): void
    {
        $result = ReplyDrafter::sanitize([
            'text' => 'Oda DM-20261012-ABC123 iliagizwa tarehe 12/10/2026 na jumla ni 17,000 TZS.',
        ], $this->order(), $this->controls(), 'sw');

        $this->assertTrue($result['ok'], (string) $result['reason']);
        $this->assertStringContainsString('DM-20261012-ABC123', $result['text']);
    }

    public function test_fabricated_percentage_is_rejected_unless_seller_recorded_it(): void
    {
        $fabricated = ReplyDrafter::sanitize([
            'text' => 'Tumepunguza bei kwa 10% kwa oda hii.',
        ], $this->order(), $this->controls(), 'sw');
        $this->assertFalse($fabricated['ok']);
        $this->assertSame(ReplyDrafter::FAIL_AMOUNT, $fabricated['code']);

        $recorded = ReplyDrafter::sanitize([
            'text' => 'Punguzo la 10% limeongezwa kwenye oda hii.',
        ], $this->order(['visible_notes' => ['Tumekupa punguzo la 10%.']]), $this->controls(), 'sw');
        $this->assertTrue($recorded['ok'], (string) $recorded['reason']);
    }

    public function test_delivery_promise_is_stripped_and_flagged(): void
    {
        $result = ReplyDrafter::sanitize([
            'text' => 'Oda yako itafika kesho. Tutakujulisha mara iko tayari.',
        ], $this->order(), $this->controls(), 'sw');

        $this->assertTrue($result['ok']);
        $this->assertStringNotContainsString('kesho', $result['text']);
        $this->assertStringContainsString('Tutakujulisha', $result['text']);
        $this->assertContains(
            ReplyDrafter::FLAG_PROMISE_REMOVED,
            array_column($result['flags'], 'code')
        );
    }

    public function test_promise_allowed_when_seller_already_promised_it(): void
    {
        $order = $this->order([
            'visible_notes' => ['Oda yako itafika kesho asubuhi kwa anwani yako.'],
        ]);

        $result = ReplyDrafter::sanitize([
            'text' => 'Oda yako itafika kesho kama ilivyoelezwa awali.',
        ], $order, $this->controls(), 'sw');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('kesho', $result['text']);
        $this->assertSame([], $result['flags']);
    }

    public function test_discount_sentence_is_stripped_unless_seller_recorded_it(): void
    {
        $stripped = ReplyDrafter::sanitize([
            'text' => 'Tutakupa punguzo baadaye. Asante kwa uchaguzi wako.',
        ], $this->order(), $this->controls(), 'sw');

        $this->assertTrue($stripped['ok']);
        $this->assertStringNotContainsString('punguzo', $stripped['text']);
        $this->assertContains(
            ReplyDrafter::FLAG_DISCOUNT_REMOVED,
            array_column($stripped['flags'], 'code')
        );

        $allowed = ReplyDrafter::sanitize([
            'text' => 'Kama ilivyoelezwa, kuna punguzo kwenye oda hii.',
        ], $this->order(['visible_notes' => ['Tumekupa punguzo la 10% kwenye oda hii.']]), $this->controls(), 'sw');

        $this->assertTrue($allowed['ok']);
        $this->assertStringContainsString('punguzo', $allowed['text']);
        $this->assertSame([], $allowed['flags']);
    }

    public function test_duplicate_of_already_sent_note_is_flagged(): void
    {
        $sent = 'Habari, oda yako iko tayari kuchukuliwa dukani. Asante kwa kuagiza!';
        $order = $this->order([
            'visible_notes' => [$sent],
            'last_visible_note_at' => '2026-10-12T10:00:00.000Z',
        ]);

        $result = ReplyDrafter::sanitize(['text' => $sent], $order, $this->controls(), 'sw');
        $this->assertTrue($result['ok']);
        $this->assertContains(ReplyDrafter::FLAG_DUPLICATE, array_column($result['flags'], 'code'));

        $duplicate = ReplyDrafter::duplicateOf($sent, $order);
        $this->assertNotNull($duplicate);
        $this->assertSame($sent, $duplicate['note']);
        $this->assertSame('2026-10-12T10:00:00.000Z', $duplicate['at']);

        $fresh = ReplyDrafter::sanitize([
            'text' => 'Tumepokea malipo, asante. Oda itakaguliwa kesho baada ya msaada.',
        ], $order, $this->controls(), 'sw');
        $this->assertNotContains(ReplyDrafter::FLAG_DUPLICATE, array_column($fresh['flags'], 'code'));
        $this->assertNull(ReplyDrafter::duplicateOf('Sentensi kabisa tofauti kabisa kabisa.', $order));
    }

    public function test_missing_info_is_deterministic(): void
    {
        $pending = ReplyDrafter::missingInfo($this->order(), 'en');
        $codes = array_column($pending, 'code');
        $this->assertContains(ReplyDrafter::MISSING_NO_TIMING, $codes);
        $this->assertContains(ReplyDrafter::MISSING_UNPAID, $codes);

        $done = ReplyDrafter::missingInfo($this->order([
            'status' => 'completed',
            'payment_status' => 'paid',
        ]), 'en');
        $this->assertSame([], $done);

        $timed = ReplyDrafter::missingInfo($this->order([
            'visible_notes' => ['Itafika kesho saa 4 asubuhi.'],
        ]), 'en');
        $this->assertSame(
            [ReplyDrafter::MISSING_UNPAID],
            array_column($timed, 'code')
        );
    }

    public function test_length_cap_is_enforced_with_honest_flag(): void
    {
        config(['ai.reply_draft.max_chars' => 140]);

        $long = 'Asante kwa oda yako. '.str_repeat('Tutakutumia taarifa za oda kila saa. ', 10);
        $result = ReplyDrafter::sanitize(['text' => $long], $this->order(), $this->controls(), 'sw');

        $this->assertTrue($result['ok']);
        $this->assertLessThanOrEqual(140, mb_strlen($result['text']));
        $this->assertContains(ReplyDrafter::FLAG_TRUNCATED, array_column($result['flags'], 'code'));
    }

    public function test_html_and_control_characters_are_stripped(): void
    {
        $result = ReplyDrafter::sanitize([
            'text' => "<script>alert(1)</script> Asante\x07 kwa oda yako.",
        ], $this->order(), $this->controls(), 'sw');

        $this->assertTrue($result['ok']);
        $this->assertStringNotContainsString('<', $result['text']);
        $this->assertStringNotContainsString("\x07", $result['text']);
    }

    public function test_empty_model_output_fails_honestly(): void
    {
        $null = ReplyDrafter::sanitize(null, $this->order(), $this->controls(), 'en');
        $this->assertFalse($null['ok']);
        $this->assertSame(ReplyDrafter::FAIL_EMPTY, $null['code']);

        $blank = ReplyDrafter::sanitize(['text' => '   '], $this->order(), $this->controls(), 'en');
        $this->assertFalse($blank['ok']);
        $this->assertSame(ReplyDrafter::FAIL_EMPTY, $blank['code']);
    }

    public function test_all_messages_exist_in_all_eight_locales(): void
    {
        foreach (self::LOCALES as $locale) {
            $promise = ReplyDrafter::sanitize(['text' => 'Itafika kesho. Asante.'], $this->order(), $this->controls(), $locale);
            $this->assertTrue($promise['ok'], $locale);
            $flagCodes = array_column($promise['flags'], 'code');
            $this->assertContains(ReplyDrafter::FLAG_PROMISE_REMOVED, $flagCodes, $locale);
            foreach ($promise['flags'] as $flag) {
                $this->assertNotSame('', trim($flag['message']), $locale);
                $this->assertNotSame($flag['code'], $flag['message'], $locale);
            }

            $amount = ReplyDrafter::sanitize(['text' => 'Bei ni 424242 TZS.'], $this->order(), $this->controls(), $locale);
            $this->assertFalse($amount['ok'], $locale);
            $this->assertNotSame('', trim((string) $amount['reason']), $locale);
            $this->assertNotSame($amount['code'], $amount['reason'], $locale);

            $empty = ReplyDrafter::sanitize([], $this->order(), $this->controls(), $locale);
            $this->assertNotSame('', trim((string) $empty['reason']), $locale);

            $missing = ReplyDrafter::missingInfo($this->order(), $locale);
            $this->assertCount(2, $missing, $locale);
            foreach ($missing as $line) {
                $this->assertNotSame('', trim($line['message']), $locale);
                $this->assertNotSame($line['code'], $line['message'], $locale);
            }

            $limits = ReplyDrafter::limitations($locale);
            $this->assertCount(4, $limits, $locale);
            foreach ($limits as $line) {
                $this->assertNotSame('', trim($line), $locale);
            }
        }
    }

    public function test_prompt_carries_order_facts_and_never_internal_notes(): void
    {
        $order = $this->order([
            'internal_note' => 'SAHAU_MEZA_BONYEZA_7788 - SIRI KWA MTUMIAJI',
            'visible_notes' => ['Oda iko tayari.'],
        ]);

        $prompt = ReplyDrafter::userPrompt('sw', $order, $this->controls());

        $this->assertStringContainsString('DM-20261012-ABC123', $prompt);
        $this->assertStringContainsString('Oda yangu iko wapi?', $prompt);
        $this->assertStringContainsString('Mchele wa Soko', $prompt);
        $this->assertStringContainsString('5000', $prompt);
        $this->assertStringContainsString('Oda iko tayari.', $prompt);
        $this->assertStringContainsString('casual', $prompt);

        // Private seller notes never reach the model.
        $this->assertStringNotContainsString('SAHAU_MEZA_BONYEZA_7788', $prompt);
        $this->assertStringNotContainsString('internal_note', $prompt);
    }

    public function test_allowed_numbers_cover_every_order_amount(): void
    {
        $allowed = ReplyDrafter::allowedNumbers($this->order());

        $this->assertContains(5000.0, $allowed);   // unit price
        $this->assertContains(15000.0, $allowed);  // line total + subtotal
        $this->assertContains(2000.0, $allowed);   // shipping
        $this->assertContains(17000.0, $allowed);  // grand total
        $this->assertNotContains(9999.0, $allowed);
    }
}
