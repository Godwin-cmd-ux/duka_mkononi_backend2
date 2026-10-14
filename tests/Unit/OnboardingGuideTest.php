<?php

namespace Tests\Unit;

use App\Services\Ai\OnboardingGuide;
use PHPUnit\Framework\TestCase;

/**
 * Capability #19 (onboarding half): a grounded setup checklist + approved
 * answers, in the user's own language.
 *
 * What these tests pin:
 *  - a step is done ONLY because the platform actually records the value - never
 *    because the user (or a client) claims it, and never for an unread row;
 *  - the seller approval step reflects the real rule (pending -> waiting);
 *  - the checklist is role-aware (a customer gets no business steps) and the
 *    "next step" is the first required step that is not done;
 *  - answers come from the approved knowledge base verbatim in the requested
 *    language, and an unrelated question matches nothing instead of guessing;
 *  - the optional acknowledgement can never smuggle digits, links, addresses,
 *    phone numbers or an unapproved topic in;
 *  - no internal or sensitive field (a password, a code) ever leaves the guide;
 *  - all eight locales carry identical message keys and the same topic ids.
 */
class OnboardingGuideTest extends TestCase
{
    private function user(array $overrides = []): array
    {
        return array_merge([
            'id' => 'u1',
            'email' => 'gift@example.com',
            'role' => 'seller',
            'status' => 'approved',
            'full_name' => 'Gift Mego',
            'phone' => '0614125882',
            'language' => 'sw',
            'business_name' => 'GN Motorcycle spare parts',
            'business_location' => 'Mbeya',
            'business_logo_url' => 'https://cdn.example.com/shop.jpg',
            'business_latitude' => '-8.9094',
            'business_longitude' => '33.4607',
        ], $overrides);
    }

    private function business(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'GN Motorcycle spare parts',
            'business_type' => 'spare_parts',
            'business_location' => 'Mbeya',
            'business_description' => 'We sell Toyota and Nissan motorcycle spare parts in Mbeya town.',
            'business_logo_url' => 'https://cdn.example.com/logo.jpg',
            'business_latitude' => '-8.9094',
            'business_longitude' => '33.4607',
            'is_public' => true,
            'is_certified' => false,
        ], $overrides);
    }

    private function guide(array $overrides = []): array
    {
        return OnboardingGuide::steps(array_merge([
            'locale' => 'en',
            'role' => 'seller',
            'user' => $this->user(),
            'business' => $this->business(),
            'product_count' => 2,
        ], $overrides));
    }

    private function step(array $guide, string $id): array
    {
        foreach ($guide['steps'] as $step) {
            if ($step['id'] === $id) {
                return $step;
            }
        }

        $this->fail('Step '.$id.' is missing from the guide.');
    }

    /* ------------------------------------------------------------------ */
    /* Localization completeness                                           */
    /* ------------------------------------------------------------------ */

    public function test_all_eight_locales_carry_the_same_messages_and_topics(): void
    {
        $this->assertTrue(OnboardingGuide::messagesComplete());
        $this->assertCount(8, OnboardingGuide::MESSAGES);
        $this->assertCount(8, OnboardingGuide::KB);
    }

    public function test_every_approved_topic_has_text_in_every_locale(): void
    {
        foreach (OnboardingGuide::topicIds() as $topicId) {
            foreach (array_keys(OnboardingGuide::MESSAGES) as $locale) {
                $text = OnboardingGuide::kbText($topicId, $locale);
                $this->assertNotSame('', $text, 'Missing KB text for '.$topicId.' in '.$locale);
                // Script-aware floor: every approved answer is a real sentence
                // (CJK packs the same content into fewer characters).
                $this->assertGreaterThan(20, mb_strlen($text), 'Approved text is too thin for '.$topicId.' ('.$locale.')');
            }
        }
    }

    public function test_role_names_are_mapped_to_the_documented_roles(): void
    {
        $this->assertSame('customer', OnboardingGuide::role('mteja'));
        $this->assertSame('customer', OnboardingGuide::role('CUSTOMER'));
        $this->assertSame('seller', OnboardingGuide::role('muuzaji'));
        $this->assertSame('seller', OnboardingGuide::role('cashier'));
        $this->assertSame('admin', OnboardingGuide::role('msimamizi'));
        $this->assertSame('admin', OnboardingGuide::role('admin'));
        $this->assertSame('customer', OnboardingGuide::role('something-else'), 'An unknown role is never treated as staff.');
    }

    /* ------------------------------------------------------------------ */
    /* Role-aware checklist                                                */
    /* ------------------------------------------------------------------ */

    public function test_a_customer_gets_no_business_steps(): void
    {
        $guide = $this->guide(['role' => 'customer', 'business' => null, 'product_count' => null]);
        $ids = array_column($guide['steps'], 'id');

        $this->assertSame('customer', $guide['role']);
        $this->assertSame('Customer', $guide['role_label']);
        $this->assertContains('profile_name', $ids);
        $this->assertContains('phone', $ids);
        foreach (['business_type', 'logo', 'approval', 'first_product', 'public_listing', 'certification'] as $businessStep) {
            $this->assertNotContains($businessStep, $ids, 'A customer must not be asked for '.$businessStep.'.');
        }
    }

    public function test_an_admin_and_a_seller_get_the_business_setup_steps(): void
    {
        foreach (['admin', 'seller'] as $role) {
            $ids = array_column($this->guide(['role' => $role])['steps'], 'id');
            foreach (['business_name', 'business_type', 'business_location', 'logo', 'location_pinned', 'approval', 'first_product'] as $expected) {
                $this->assertContains($expected, $ids, $role.' must be walked through '.$expected.'.');
            }
        }
    }

    public function test_every_step_carries_a_localized_label_and_instruction(): void
    {
        foreach (array_keys(OnboardingGuide::MESSAGES) as $locale) {
            $guide = $this->guide(['locale' => $locale]);
            foreach ($guide['steps'] as $step) {
                $this->assertNotSame('label_'.$step['id'], $step['label'], 'Missing label for '.$step['id'].' ('.$locale.')');
                $this->assertNotSame('how_'.$step['id'], $step['how'], 'Missing instruction for '.$step['id'].' ('.$locale.')');
                $this->assertStringNotContainsString('{', $step['label']);
                $this->assertStringNotContainsString('{', $step['how']);
                $this->assertNotSame('', $step['status_label']);
                $this->assertNotSame('', $step['source']);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Statuses come from records only                                     */
    /* ------------------------------------------------------------------ */

    public function test_a_step_is_done_only_when_the_value_is_recorded(): void
    {
        $this->assertTrue($this->step($this->guide(), 'profile_name')['done']);

        $empty = $this->guide(['user' => $this->user(['full_name' => '  '])]);
        $this->assertFalse($this->step($empty, 'profile_name')['done']);
        $this->assertSame('todo', $this->step($empty, 'profile_name')['status']);
        $this->assertSame('To do', $this->step($empty, 'profile_name')['status_label']);
    }

    public function test_the_description_needs_the_documented_length(): void
    {
        $short = $this->guide(['business' => $this->business(['business_description' => 'Short'])]);
        $this->assertFalse($this->step($short, 'business_description')['done']);

        $long = $this->step($this->guide(), 'business_description');
        $this->assertTrue($long['done']);
        $this->assertStringContainsString((string) OnboardingGuide::MIN_DESCRIPTION_CHARS, $long['how']);
    }

    public function test_a_value_on_either_the_user_or_the_business_row_counts(): void
    {
        $onBusinessOnly = $this->guide(['user' => $this->user(['business_logo_url' => null])]);
        $this->assertTrue($this->step($onBusinessOnly, 'logo')['done']);

        $onUserOnly = $this->guide(['business' => $this->business(['business_logo_url' => null])]);
        $this->assertTrue($this->step($onUserOnly, 'logo')['done']);

        $neither = $this->guide([
            'user' => $this->user(['business_logo_url' => null]),
            'business' => $this->business(['business_logo_url' => null]),
        ]);
        $this->assertFalse($this->step($neither, 'logo')['done']);
    }

    public function test_the_map_step_needs_both_coordinates(): void
    {
        $half = $this->guide([
            'user' => $this->user(['business_latitude' => null, 'business_longitude' => null]),
            'business' => $this->business(['business_latitude' => '-8.9', 'business_longitude' => null]),
        ]);
        $this->assertFalse($this->step($half, 'location_pinned')['done']);

        $this->assertTrue($this->step($this->guide(), 'location_pinned')['done']);
    }

    public function test_the_seller_approval_step_reflects_the_real_rule(): void
    {
        $pending = $this->guide(['user' => $this->user(['status' => 'pending'])]);
        $step = $this->step($pending, 'approval');
        $this->assertFalse($step['done']);
        $this->assertSame('waiting', $step['status']);
        $this->assertSame('Waiting for approval', $step['status_label']);

        $approved = $this->step($this->guide(), 'approval');
        $this->assertTrue($approved['done']);

        $rejected = $this->guide(['user' => $this->user(['status' => 'rejected'])]);
        $this->assertSame('rejected', $this->step($rejected, 'approval')['status']);
        $this->assertSame('Rejected', $this->step($rejected, 'approval')['status_label']);
    }

    public function test_products_and_public_listing_read_real_records(): void
    {
        $noProducts = $this->guide(['product_count' => 0]);
        $this->assertFalse($this->step($noProducts, 'first_product')['done']);

        $unread = $this->guide(['product_count' => null]);
        $this->assertSame('unknown', $this->step($unread, 'first_product')['status']);
        $this->assertFalse($this->step($unread, 'first_product')['done'], 'An unread record is never done.');

        $notPublic = $this->guide(['business' => $this->business(['is_public' => false])]);
        $this->assertFalse($this->step($notPublic, 'public_listing')['done']);
    }

    public function test_certification_is_reported_but_never_required(): void
    {
        $guide = $this->guide();
        $step = $this->step($guide, 'certification');

        $this->assertFalse($step['required'], 'Certification is never a required setup step.');
        $this->assertFalse($step['done']);
        $this->assertStringContainsString('administrator', $step['how']);

        $certified = $this->guide(['business' => $this->business(['is_certified' => true])]);
        $this->assertTrue($this->step($certified, 'certification')['done']);
    }

    /* ------------------------------------------------------------------ */
    /* Progress + next step                                                */
    /* ------------------------------------------------------------------ */

    public function test_the_next_step_is_the_first_required_step_that_is_not_done(): void
    {
        $guide = $this->guide([
            'user' => $this->user(['full_name' => '', 'status' => 'pending']),
            'business' => $this->business(['business_logo_url' => null]),
            'product_count' => 0,
        ]);

        $this->assertSame('profile_name', $guide['next_step']['id']);
        $this->assertNotSame('', $guide['next_step']['how']);

        $this->assertSame($guide['counts']['required'] - $guide['counts']['done'], $guide['counts']['remaining']);
        $this->assertLessThan(100, $guide['progress']);
        $this->assertContains(OnboardingGuide::msg('sum_remaining', 'en', ['count' => $guide['counts']['remaining']]), $guide['summary']);
    }

    public function test_a_recommended_step_is_never_returned_as_the_next_step(): void
    {
        // Only the optional steps are open (public listing + certification).
        $guide = $this->guide(['business' => $this->business(['is_public' => false, 'is_certified' => false])]);

        $this->assertNull($guide['next_step'], 'Recommended steps must not appear as the next step.');
        $this->assertSame(100, $guide['progress']);
        $this->assertSame(0, $guide['counts']['remaining']);
        $this->assertContains(OnboardingGuide::msg('sum_all_done', 'en'), $guide['summary']);
    }

    public function test_an_unread_account_reports_unknown_steps_instead_of_done(): void
    {
        $guide = OnboardingGuide::steps(['locale' => 'en', 'role' => 'admin', 'user' => null, 'business' => null, 'product_count' => null]);

        foreach ($guide['steps'] as $step) {
            $this->assertFalse($step['done'], $step['id'].' must not be done without records.');
        }
        $this->assertSame(count($guide['steps']), $guide['counts']['total']);
    }

    /* ------------------------------------------------------------------ */
    /* Signup facts                                                        */
    /* ------------------------------------------------------------------ */

    public function test_the_signup_facts_match_the_real_application_rules(): void
    {
        $seller = $this->guide(['role' => 'seller'])['signup'];
        $this->assertSame('/cashier-signup', $seller['web']);
        $this->assertTrue($seller['needs_approval']);
        $this->assertSame(OnboardingGuide::msg('signup_needs_approval', 'en'), $seller['note']);

        $admin = $this->guide(['role' => 'admin'])['signup'];
        $this->assertSame('/admin-signup', $admin['web']);
        $this->assertFalse($admin['needs_approval']);

        $customer = $this->guide(['role' => 'customer'])['signup'];
        $this->assertSame('/client-signup', $customer['web']);
        $this->assertFalse($customer['needs_approval']);
    }

    /* ------------------------------------------------------------------ */
    /* Answers                                                             */
    /* ------------------------------------------------------------------ */

    public function test_an_answer_uses_the_approved_text_verbatim_in_the_users_language(): void
    {
        $answer = OnboardingGuide::answer([
            'locale' => 'sw',
            'role' => 'seller',
            'question' => 'Nimesahau neno la siri, nifanye nini?',
            'user' => $this->user(),
            'business' => $this->business(),
            'product_count' => 1,
        ]);

        $this->assertTrue($answer['matched']);
        $this->assertContains('kb_password', array_column($answer['topics'], 'id'));
        $this->assertContains(OnboardingGuide::kbText('kb_password', 'sw'), $answer['answers']);
        $this->assertSame(OnboardingGuide::kbText('kb_password', 'sw'), $answer['answers'][0]);
        $this->assertNotSame([], $answer['limitations']);
    }

    public function test_an_unrelated_question_matches_nothing_instead_of_guessing(): void
    {
        $answer = OnboardingGuide::answer([
            'locale' => 'en',
            'role' => 'customer',
            'question' => 'zzz qqq',
            'user' => $this->user(),
            'business' => null,
            'product_count' => null,
        ]);

        $this->assertFalse($answer['matched']);
        $this->assertSame([], $answer['answers']);
        $this->assertSame([], $answer['topics']);
        $this->assertNotSame([], $answer['guide']['steps'], 'The checklist is still returned.');
    }

    public function test_topic_matching_is_bounded_and_ordered_by_evidence(): void
    {
        $matches = OnboardingGuide::matchTopics('How do I sign up and which fields are required?');
        $this->assertLessThanOrEqual(OnboardingGuide::MAX_TOPICS, count($matches));
        $this->assertContains('kb_signup_roles', array_column($matches, 'id'));
        foreach ($matches as $match) {
            $this->assertNotSame([], $match['evidence'], 'A match always shows the wording that produced it.');
        }

        $this->assertSame([], OnboardingGuide::matchTopics('   '));
    }

    public function test_topic_matching_works_in_the_users_language(): void
    {
        $matches = array_column(OnboardingGuide::matchTopics('Nataka kuthibitishwa, uthibitisho wa biashara ni nini?'), 'id');
        $this->assertContains('kb_certification', $matches);

        $swahili = array_column(OnboardingGuide::matchTopics('Siwezi kuingia bado kwenye akaunti yangu'), 'id');
        $this->assertContains('kb_pending_login', $swahili);
    }

    public function test_every_supported_locale_can_reach_an_approved_answer(): void
    {
        /* A real question in each of the 8 languages must select an approved
         * topic, otherwise a user who asks in their own language is told there
         * is no approved answer at all. This pins the per-language wording of
         * TOPIC_KEYWORDS (the sole purpose of a keyword is picking an APPROVED
         * topic - it can never add text). */
        $questions = [
            'sw' => 'Nimesajili akaunti mpya, kwa nini nasubiri idhini?',
            'en' => 'How do I add my first product to my shop?',
            'fr' => 'Comment ajouter un produit?',
            'hi' => 'उत्पाद कैसे जोड़ें',
            'es' => 'Como registro mi cuenta?',
            'ur' => 'پروڈکٹ کیسے شامل کریں',
            'de' => 'Wie lade ich ein produkt hoch?',
            'zh' => '如何添加商品？',
        ];

        foreach ($questions as $locale => $question) {
            $matches = OnboardingGuide::matchTopics($question);
            $this->assertNotSame([], $matches, 'The '.$locale.' question must select an approved topic.');

            $answer = OnboardingGuide::answer([
                'locale' => $locale,
                'role' => 'seller',
                'question' => $question,
                'user' => null,
                'business' => null,
                'product_count' => null,
            ]);

            $this->assertTrue($answer['matched'], 'The '.$locale.' answer must be grounded, not empty.');
            $this->assertNotSame([], $answer['answers']);
            foreach ($answer['answers'] as $text) {
                $this->assertNotSame('', trim($text));
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* The gated acknowledgement                                           */
    /* ------------------------------------------------------------------ */

    public function test_an_acknowledgement_with_numbers_links_or_contacts_is_rejected(): void
    {
        $this->assertNull(OnboardingGuide::sanitizeAckString('Call us on 0755 000 000'));
        $this->assertNull(OnboardingGuide::sanitizeAckString('See https://example.com/help'));
        $this->assertNull(OnboardingGuide::sanitizeAckString('Write to help@example.com'));
        $this->assertNull(OnboardingGuide::sanitizeAckString('Is this the right place?'));
        $this->assertNull(OnboardingGuide::sanitizeAckString(str_repeat('a', 201)));
        $this->assertNull(OnboardingGuide::sanitizeAckString('   '));

        $this->assertSame('Welcome, let us get you started', OnboardingGuide::sanitizeAckString('  Welcome,   let us get you started '));
    }

    public function test_narration_keeps_only_allow_listed_topics(): void
    {
        $clean = OnboardingGuide::sanitizeNarration([
            'ack' => 'Happy to help you get set up',
            'topics' => ['kb_password', 'kb_password', 'kb_not_real', 42, 'kb_certification'],
        ]);

        $this->assertSame('Happy to help you get set up', $clean['ack']);
        $this->assertSame(['kb_password', 'kb_certification'], $clean['topics']);

        $this->assertSame(['ack' => null, 'topics' => []], OnboardingGuide::sanitizeNarration('not json'));
        $this->assertSame(['ack' => null, 'topics' => []], OnboardingGuide::sanitizeNarration(['ack' => ['nested'], 'topics' => 'kb_password']));
    }

    /* ------------------------------------------------------------------ */
    /* Privacy + configuration                                             */
    /* ------------------------------------------------------------------ */

    public function test_no_sensitive_field_ever_leaves_the_guide(): void
    {
        $guide = $this->guide(['user' => $this->user([
            'password' => 'super-secret-hash',
            'email' => 'gift@example.com',
            'session_id' => 'abc-123',
        ])]);

        $json = json_encode($guide, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('super-secret-hash', $json);
        $this->assertStringNotContainsString('abc-123', $json);
        $this->assertStringNotContainsString('gift@example.com', $json);
        // No sensitive column is ever named as a step source either.
        foreach (['users.password', 'users.email', 'users.session_id', 'users.previous_status'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $json);
        }
    }

    public function test_an_operator_can_correct_one_approved_answer_without_losing_the_rest(): void
    {
        $override = ['en' => ['kb_password' => 'Use the reset link we email you.', 'kb_not_real' => 'nope']];

        $answer = OnboardingGuide::answer([
            'locale' => 'en',
            'role' => 'customer',
            'question' => 'I forgot password',
            'user' => $this->user(),
            'business' => null,
            'product_count' => null,
            'kb' => $override,
        ]);

        $this->assertSame('Use the reset link we email you.', $answer['answers'][0]);
        $this->assertSame(
            OnboardingGuide::KB['en']['kb_signup_roles'],
            OnboardingGuide::kbText('kb_signup_roles', 'en', $override),
            'An override only replaces the topics it names.'
        );
        $this->assertSame(
            OnboardingGuide::KB['sw']['kb_password'],
            OnboardingGuide::kbText('kb_password', 'sw', $override),
            'An override only touches the locale it names.'
        );
    }

    public function test_an_operator_step_list_drops_unknown_rules(): void
    {
        $guide = OnboardingGuide::steps([
            'locale' => 'en',
            'role' => 'admin',
            'user' => $this->user(),
            'business' => $this->business(),
            'product_count' => 1,
            'steps' => [
                ['id' => 'profile_name', 'rule' => 'user_text', 'field' => 'full_name', 'roles' => ['admin'], 'required' => true, 'source' => 'users.full_name'],
                ['id' => 'bogus', 'rule' => 'mind_read', 'roles' => ['admin'], 'required' => true],
            ],
        ]);

        $this->assertSame(['profile_name'], array_column($guide['steps'], 'id'));
        $this->assertSame(['users.full_name'], $guide['sources']);
    }

    public function test_an_empty_operator_step_list_falls_back_to_the_built_in_set(): void
    {
        $guide = $this->guide(['steps' => []]);

        $this->assertSame(array_column(OnboardingGuide::STEPS, 'id'), array_column(
            $this->guide(['role' => 'admin'])['steps'],
            'id'
        ));
        $this->assertNotSame([], $guide['steps']);
    }
}
