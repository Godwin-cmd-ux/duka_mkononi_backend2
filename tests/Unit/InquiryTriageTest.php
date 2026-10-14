<?php

namespace Tests\Unit;

use App\Services\Ai\InquiryTriage;
use PHPUnit\Framework\TestCase;

/**
 * Capability #18 contract. The triage is the first stage of inquiry
 * management, so these tests pin down:
 *  - classification into the allow-listed families, with the matched keywords
 *    as evidence (the same message always lands in the same bucket);
 *  - urgency from the DOCUMENTED criteria only, with the matched wording
 *    returned as the reason;
 *  - the category -> team routing map plus the critical escalation mark;
 *  - the draft is COMPOSED from approved help content (never generated): the
 *    approved answer appears verbatim, no internal record and no customer
 *    contact detail can reach it, and when nothing matches the draft says a
 *    human will follow up;
 *  - the model step is gated: topic ids must be allow-listed and the
 *    acknowledgement must be claim/figure/link/address-free;
 *  - human-support flags cover urgency, disputes, unmatched inquiries and an
 *    account question with no matching account;
 *  - the narrow auto-send policy is opt-in AND limited to general, low-urgency,
 *    human-free inquiries;
 *  - eight-locale message and help-content completeness with identical
 *    placeholders.
 *
 * Pure-function tests: no database, no HTTP, no Laravel helpers.
 */
class InquiryTriageTest extends TestCase
{
    private const LANGS = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /* ------------------------------------------------------------------ */
    /* Message + help content coverage                                     */
    /* ------------------------------------------------------------------ */

    public function test_every_locale_carries_every_message(): void
    {
        $this->assertTrue(InquiryTriage::messagesComplete(), 'All eight locales must carry the same message keys.');
        $this->assertSame(self::LANGS, array_keys(InquiryTriage::MESSAGES));
    }

    public function test_every_locale_fills_the_same_placeholders(): void
    {
        foreach (array_keys(InquiryTriage::MESSAGES['en']) as $key) {
            $expected = InquiryTriage::placeholders($key, 'en');
            foreach (self::LANGS as $locale) {
                $this->assertSame(
                    $expected,
                    InquiryTriage::placeholders($key, $locale),
                    "Message '{$key}' must use the same placeholders in {$locale}."
                );
            }
        }
    }

    public function test_every_locale_has_approved_help_for_every_topic(): void
    {
        $this->assertTrue(InquiryTriage::helpComplete(), 'Every locale needs an approved answer for every topic.');
        $this->assertSame(self::LANGS, array_keys(InquiryTriage::HELP));
        $this->assertCount(count(InquiryTriage::TOPICS), InquiryTriage::topicIds());
        $this->assertSame(InquiryTriage::topicIds(), array_values(array_unique(InquiryTriage::topicIds())));
    }

    public function test_every_help_topic_category_is_allow_listed(): void
    {
        foreach (InquiryTriage::TOPICS as $topic) {
            $this->assertContains($topic['category'], InquiryTriage::CATEGORIES);
            $this->assertNotSame([], $topic['keywords']);
        }
    }

    public function test_messages_fall_back_to_english_then_the_key(): void
    {
        $this->assertSame('Payments', InquiryTriage::msg('cat_payments', 'en'));
        $this->assertNotSame('cat_payments', InquiryTriage::msg('cat_payments', 'sw'));
        $this->assertSame('missing_key', InquiryTriage::msg('missing_key', 'sw'));
    }

    /* ------------------------------------------------------------------ */
    /* Classification                                                      */
    /* ------------------------------------------------------------------ */

    public function test_normalize_folds_accents_case_and_punctuation(): void
    {
        $this->assertSame('contrasena olvidada', InquiryTriage::normalize('  ¡Contraseña OLVidada! '));
        $this->assertSame('mot de passe', InquiryTriage::normalize('Mot de passe'));
        $this->assertSame('', InquiryTriage::normalize('  ???  '));
    }

    public function test_inquiries_are_classified_into_the_allow_listed_families(): void
    {
        $this->assertSame('account', InquiryTriage::classify('Forgot my password', 'I cannot log in to my account')['category']);
        $this->assertSame('payments', InquiryTriage::classify('Payment pending', 'I have paid with Pesapal but my payment status is still pending')['category']);
        $this->assertSame('technical', InquiryTriage::classify('App problem', 'The app is not working on my phone and shows an error')['category']);
        $this->assertSame('certification', InquiryTriage::classify('Certification', 'How do I get my business certified?')['category']);
        $this->assertSame('general', InquiryTriage::classify('Hello', 'What is Duka Mkononi and how do I start?')['category']);
    }

    public function test_swahili_inquiries_are_classified_too(): void
    {
        $this->assertSame('account', InquiryTriage::classify('Neno la siri', 'Nimesahau neno la siri la kuingia kwenye akaunti yangu')['category']);
        $this->assertSame('payments', InquiryTriage::classify('Malipo', 'Nimelipa kwa pesapal lakini malipo hayajaonekana')['category']);
    }

    public function test_classification_returns_evidence_and_always_a_listed_category(): void
    {
        $result = InquiryTriage::classify('Payment rejected', 'My payment was rejected by the system');
        $this->assertContains($result['category'], InquiryTriage::CATEGORIES);
        $this->assertNotSame([], $result['matched_topics']);
        $this->assertNotSame([], $result['evidence']['payments']);

        $unmatched = InquiryTriage::classify('Random text', 'zxqv wlkj');
        $this->assertSame('general', $unmatched['category']);
        $this->assertSame([], $unmatched['matched_topics'], 'Nothing may be matched by guesswork.');
    }

    public function test_a_specific_family_beats_general(): void
    {
        $result = InquiryTriage::classify('How do I start?', 'I want to register and also reset my password');
        $this->assertSame('account', $result['category'], 'A specific family wins over the general welcome topic.');
    }

    public function test_at_most_two_topics_are_used_per_draft(): void
    {
        $this->assertLessThanOrEqual(InquiryTriage::MAX_TOPICS, count(InquiryTriage::matchTopics('Payment', 'Payment problem and I forgot my password and the app is broken')));
    }

    /* ------------------------------------------------------------------ */
    /* Urgency + routing                                                   */
    /* ------------------------------------------------------------------ */

    public function test_urgency_follows_the_documented_criteria(): void
    {
        $critical = InquiryTriage::urgency('Help', 'I think my account has been hacked', 'en');
        $this->assertSame('critical', $critical['urgency']);
        $this->assertNotSame([], $critical['reasons'], 'The matched wording must be returned as the reason.');
        $this->assertStringContainsString('hacked', implode(' ', $critical['evidence']));

        $high = InquiryTriage::urgency('URGENT', 'I cannot log in and I am blocked', 'en');
        $this->assertSame('high', $high['urgency']);

        $low = InquiryTriage::urgency('Just a suggestion', 'No rush, this is feedback about the app', 'en');
        $this->assertSame('low', $low['urgency']);

        $normal = InquiryTriage::urgency('Question', 'How do I add a product to my shop?', 'en');
        $this->assertSame('normal', $normal['urgency']);
        $this->assertSame([], $normal['reasons']);
    }

    public function test_critical_outranks_high(): void
    {
        $result = InquiryTriage::urgency('URGENT', 'I was charged twice and I think it is fraud', 'en');
        $this->assertSame('critical', $result['urgency']);
    }

    public function test_an_explicitly_non_urgent_message_stays_low(): void
    {
        /* "not urgent" contains "urgent" and "si haraka" contains "haraka": the
         * LOW phrase must not be escalated by the HIGH term inside itself. */
        foreach ([
            'No rush, when you have time, this is only feedback',
            'This is not urgent, just a suggestion about the app',
            'Si haraka, hii ni pendekezo tu kuhusu programu',
        ] as $message) {
            $result = InquiryTriage::urgency('Feedback', $message, 'en');
            $this->assertSame('low', $result['urgency'], 'Not-urgent wording must stay low: '.$message);
            $this->assertNotSame([], $result['evidence']);
        }
    }

    public function test_real_blocking_wording_still_wins_over_a_not_urgent_phrase(): void
    {
        $result = InquiryTriage::urgency('Problem', 'No rush, but I cannot log in to my account', 'en');
        $this->assertSame('high', $result['urgency'], 'Only the negation phrase is removed; the blocking wording still decides.');

        $swahili = InquiryTriage::urgency('Tatizo', 'Si haraka, lakini siwezi kuingia kwenye akaunti yangu', 'en');
        $this->assertSame('high', $swahili['urgency']);
    }

    public function test_routing_map_is_documented_and_complete(): void
    {
        $this->assertSame('support', InquiryTriage::route('account', 'normal', 'en')['team']);
        $this->assertSame('support', InquiryTriage::route('technical', 'normal', 'en')['team']);
        $this->assertSame('billing', InquiryTriage::route('payments', 'normal', 'en')['team']);
        $this->assertSame('certification', InquiryTriage::route('certification', 'normal', 'en')['team']);
        $this->assertSame('general', InquiryTriage::route('general', 'normal', 'en')['team']);
        $this->assertSame('general', InquiryTriage::route('bogus', 'normal', 'en')['team'], 'An unknown category routes to general.');

        $critical = InquiryTriage::route('payments', 'critical', 'en');
        $this->assertTrue($critical['escalate']);
        $this->assertNotSame([], $critical['reasons']);
        $this->assertFalse(InquiryTriage::route('payments', 'high', 'en')['escalate']);
    }

    public function test_every_route_has_a_localized_label(): void
    {
        foreach (InquiryTriage::ROUTES as $team) {
            foreach (self::LANGS as $locale) {
                $this->assertNotSame('route_'.$team, InquiryTriage::msg('route_'.$team, $locale));
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Needs-human criteria                                                */
    /* ------------------------------------------------------------------ */

    private function triage(string $subject, string $message, array $overrides = []): array
    {
        return InquiryTriage::triage(array_merge([
            'locale' => 'en',
            'subject' => $subject,
            'message' => $message,
        ], $overrides));
    }

    public function test_urgent_and_critical_inquiries_always_need_a_human(): void
    {
        foreach ([['Urgent', 'I cannot log in at all'], ['Help', 'My account was hacked']] as [$subject, $message]) {
            $result = $this->triage($subject, $message);
            $this->assertTrue($result['needs_human']);
            $this->assertStringContainsString('Urgency', implode(' ', $result['needs_human_reasons']));
        }
    }

    public function test_a_payment_dispute_needs_a_human(): void
    {
        $result = $this->triage('Refund', 'I want a refund for my payment');
        $this->assertTrue($result['needs_human']);
        $this->assertStringContainsString('dispute', implode(' ', $result['needs_human_reasons']));
    }

    public function test_an_inquiry_with_no_approved_help_needs_a_human(): void
    {
        $result = $this->triage('Question', 'zxqv wlkj plmokn');
        $this->assertTrue($result['needs_human']);
        $this->assertStringContainsString('No approved help content', implode(' ', $result['needs_human_reasons']));
    }

    public function test_an_account_question_with_no_matching_account_needs_a_human(): void
    {
        $result = $this->triage('Account', 'I cannot log in to my account', ['account_found' => false]);
        $this->assertTrue($result['needs_human']);
        $this->assertStringContainsString('No account matches', implode(' ', $result['needs_human_reasons']));

        $found = $this->triage('Account', 'I forgot my password', ['account_found' => true]);
        $this->assertStringNotContainsString('No account matches', implode(' ', $found['needs_human_reasons']));
    }

    public function test_a_plain_general_low_urgency_inquiry_needs_no_human(): void
    {
        $result = $this->triage('Question', 'No rush, I just have a suggestion about how to start selling');
        $this->assertSame('general', $result['category']);
        $this->assertSame('low', $result['urgency']);
        $this->assertFalse($result['needs_human']);
        $this->assertSame([], $result['needs_human_reasons']);
    }

    /* ------------------------------------------------------------------ */
    /* The draft                                                           */
    /* ------------------------------------------------------------------ */

    public function test_the_draft_uses_the_approved_help_answer_verbatim(): void
    {
        $triage = $this->triage('Password', 'I forgot my password and cannot log in');
        $draft = InquiryTriage::draft($triage, ['subject' => 'Password'], 'en');

        $this->assertStringContainsString(InquiryTriage::HELP['en']['help_reset'], $draft['body']);
        $this->assertContains('help_reset', $draft['topic_ids']);
        $this->assertTrue($draft['uses_approved_help']);
        $this->assertSame('Re: Password', $draft['subject']);
    }

    public function test_the_draft_is_localized_in_every_language(): void
    {
        foreach (self::LANGS as $locale) {
            $triage = InquiryTriage::triage([
                'locale' => $locale,
                'subject' => 'Password',
                'message' => 'I forgot my password',
            ]);
            $draft = InquiryTriage::draft($triage, ['subject' => 'Password'], $locale);

            $this->assertStringContainsString(InquiryTriage::HELP[$locale]['help_reset'], $draft['body'], "The {$locale} draft must carry the approved {$locale} answer.");
        }
    }

    public function test_the_draft_cannot_carry_internal_or_customer_data(): void
    {
        $triage = $this->triage('Account', 'Please help me with my account, my email is customer@example.com and my phone is +255712345678');
        $draft = InquiryTriage::draft($triage, ['subject' => 'Account'], 'en');

        $this->assertStringNotContainsString('customer@example.com', $draft['body']);
        $this->assertStringNotContainsString('255712345678', $draft['body']);
    }

    public function test_an_unmatched_inquiry_draft_promises_a_human_not_an_answer(): void
    {
        $triage = $this->triage('Question', 'zxqv wlkj');
        $draft = InquiryTriage::draft($triage, ['subject' => 'Question'], 'en');

        $this->assertFalse($draft['uses_approved_help']);
        $this->assertSame([], $draft['topic_ids']);
        $this->assertStringContainsString(InquiryTriage::msg('why_no_help', 'en'), $draft['body']);
    }

    public function test_the_draft_asks_only_for_details_it_does_not_already_have(): void
    {
        $cases = [
            // [subject, message, expected asked detail key, should be asked]
            ['Payment', 'I have paid but my payment status is still pending', 'detail_payment_ref', true],
            ['Payment', 'I have paid, reference 884422, but the payment status is still pending', 'detail_payment_ref', false],
            ['App', 'The app is not working', 'detail_device', true],
            ['App', 'The app is not working on my Android phone', 'detail_device', false],
            ['Certification', 'How do I get certified?', 'detail_business_name', true],
            ['Certification', 'How do I certify? My business name is Asha Stores', 'detail_business_name', false],
        ];

        foreach ($cases as [$subject, $message, $key, $shouldAsk]) {
            $triage = $this->triage($subject, $message);
            $asked = InquiryTriage::draft($triage, ['subject' => $subject, 'message' => $message], 'en')['asked_details'];

            if ($shouldAsk) {
                $this->assertContains($key, $asked, "Expected '{$key}' to be requested for: {$message}");
                $this->assertStringContainsString(InquiryTriage::msg($key, 'en'), InquiryTriage::draft($triage, ['subject' => $subject, 'message' => $message], 'en')['body']);
            } else {
                $this->assertNotContains($key, $asked, "Expected '{$key}' to be skipped for: {$message}");
            }
        }
    }

    public function test_the_draft_reports_the_approved_sources(): void
    {
        $draft = InquiryTriage::draft($this->triage('Password', 'I forgot my password'), [], 'en');
        $this->assertContains(InquiryTriage::msg('src_help_content', 'en'), $draft['sources']);
        $this->assertContains(InquiryTriage::msg('src_your_message', 'en'), $draft['sources']);
    }

    public function test_the_draft_falls_back_to_the_category_when_the_subject_is_empty(): void
    {
        $draft = InquiryTriage::draft($this->triage('', 'I forgot my password'), [], 'en');
        $this->assertStringContainsString(InquiryTriage::msg('cat_account', 'en'), $draft['subject']);
    }

    /* ------------------------------------------------------------------ */
    /* The gated model step                                                */
    /* ------------------------------------------------------------------ */

    public function test_the_acknowledgement_gate_blocks_claims_figures_links_and_addresses(): void
    {
        $this->assertNull(InquiryTriage::sanitizeAckString('We will give you a full refund today'));
        $this->assertNull(InquiryTriage::sanitizeAckString('Your order 1234 is on the way'));
        $this->assertNull(InquiryTriage::sanitizeAckString('See https://example.com/help for details'));
        $this->assertNull(InquiryTriage::sanitizeAckString('Write to us at help@example.com'));
        $this->assertNull(InquiryTriage::sanitizeAckString('Call +255712345678'));
        $this->assertNull(InquiryTriage::sanitizeAckString('short'));
        $this->assertNull(InquiryTriage::sanitizeAckString(str_repeat('a', 240)));

        $ok = 'Thanks for writing in about your password, we have noted the details.';
        $this->assertSame($ok, InquiryTriage::sanitizeAckString($ok));
    }

    public function test_the_narration_gate_keeps_only_allow_listed_topic_ids(): void
    {
        $narration = InquiryTriage::sanitizeNarration([
            'ack' => 'Thanks for writing in, we are looking into it now.',
            'topics' => ['help_reset', 'not_a_topic', 42, 'help_payment', 'help_welcome', 'help_reset'],
        ]);

        $this->assertNotNull($narration);
        $this->assertSame(['help_reset', 'help_payment'], $narration['topics'], 'Allow-listed ids only, bounded, deduplicated.');
        $this->assertNotNull($narration['ack']);

        $this->assertNull(InquiryTriage::sanitizeNarration('not an array'));
        $this->assertSame(['ack' => null, 'topics' => []], InquiryTriage::sanitizeNarration(['topics' => ['bogus']]));
    }

    public function test_an_approved_acknowledgement_is_included_but_a_banned_one_is_not(): void
    {
        $triage = $this->triage('Password', 'I forgot my password');

        $good = InquiryTriage::draft($triage, [], 'en', 'Thanks for writing in, we have your request.');
        $this->assertStringContainsString('Thanks for writing in, we have your request.', $good['body']);

        $bad = InquiryTriage::draft($triage, [], 'en', 'We will refund you 5000 today');
        $this->assertStringNotContainsString('refund', $bad['body']);
    }

    /* ------------------------------------------------------------------ */
    /* The narrow automation policy                                        */
    /* ------------------------------------------------------------------ */

    public function test_auto_send_is_opt_in_and_narrow(): void
    {
        $generalLow = $this->triage('Suggestion', 'No rush, just a suggestion about how to start selling');

        $this->assertTrue(InquiryTriage::mayAutoSend($generalLow, true));
        $this->assertFalse(InquiryTriage::mayAutoSend($generalLow, false), 'Without the policy nothing is ever auto-sent.');

        $urgent = $this->triage('Urgent', 'I cannot log in, please help immediately');
        $this->assertFalse(InquiryTriage::mayAutoSend($urgent, true), 'A high urgency inquiry is never auto-sent.');

        $dispute = $this->triage('Refund', 'I want a refund');
        $this->assertFalse(InquiryTriage::mayAutoSend($dispute, true));

        $accountLow = $this->triage('Question', 'No rush, a small suggestion about my account login');
        $this->assertFalse(InquiryTriage::mayAutoSend($accountLow, true), 'Only general inquiries may be auto-sent.');
    }

    public function test_the_limitations_tell_the_truth(): void
    {
        foreach (self::LANGS as $locale) {
            $noResolution = InquiryTriage::msg('lim_no_resolution', $locale);
            $this->assertNotSame('lim_no_resolution', $noResolution);
            $this->assertNotSame('', trim($noResolution), 'Every locale must say that sending is not resolution.');
        }
    }
}
