<?php

namespace Tests\Unit;

use App\Services\Ai\CertificationReview;
use PHPUnit\Framework\TestCase;

/**
 * Capability #19 (certification half): the review is an EVIDENCE report, never a
 * decision.
 *
 * What these tests pin:
 *  - every requirement reads a named, real source and reports met / missing /
 *    not read / human-required with the evidence that produced it;
 *  - a submitted document can NEVER be reported as met (there is no document
 *    store), and backfilled name/location ambiguity is always a human check;
 *  - an unread row is never presented as met or missing;
 *  - the review never approves anything: `decision` is always human_only;
 *  - the eight locales carry identical message keys and every requirement has a
 *    localized label with its placeholders filled in.
 */
class CertificationReviewTest extends TestCase
{
    private function business(array $overrides = []): array
    {
        return array_merge([
            'id' => 'b1',
            'business_name' => 'GN Motorcycle spare parts',
            'business_type' => 'spare_parts',
            'business_location' => 'Mbeya',
            'business_description' => 'We sell Toyota and Nissan motorcycle spare parts in Mbeya town.',
            'business_logo_url' => 'https://cdn.example.com/logo.jpg',
            'business_latitude' => '-8.9094',
            'business_longitude' => '33.4607',
            'is_public' => true,
            'is_certified' => false,
            'migration_status' => 'active',
            'merged_from' => ['raw_spellings' => ['GN Motorcycle spare parts'], 'location_variants' => ['Mbeya']],
        ], $overrides);
    }

    private function member(array $overrides = []): array
    {
        return array_merge([
            'id' => 'u1',
            'role' => 'admin',
            'status' => 'approved',
            'full_name' => 'Dismas Kimario',
            'phone' => '+255745269425',
        ], $overrides);
    }

    private function review(array $business, array $members = [], ?int $products = 3, string $locale = 'en'): array
    {
        return CertificationReview::review([
            'locale' => $locale,
            'business' => $business,
            'members' => $members === [] ? [$this->member()] : $members,
            'product_count' => $products,
        ]);
    }

    private function line(array $review, string $id): array
    {
        foreach ($review['requirements'] as $line) {
            if ($line['id'] === $id) {
                return $line;
            }
        }

        $this->fail('Requirement '.$id.' is missing from the review.');
    }

    /* ------------------------------------------------------------------ */
    /* Message + requirement completeness                                  */
    /* ------------------------------------------------------------------ */

    public function test_all_eight_locales_carry_the_same_messages(): void
    {
        $this->assertTrue(CertificationReview::messagesComplete(), 'Every locale must define the same non-empty messages.');
        $this->assertCount(8, CertificationReview::MESSAGES);
    }

    public function test_every_requirement_has_a_label_in_every_locale(): void
    {
        foreach (array_keys(CertificationReview::MESSAGES) as $locale) {
            foreach (CertificationReview::requirementIds() as $id) {
                // The raw message may carry {min}; the RENDERED line must not.
                $raw = CertificationReview::msg('req_'.$id, $locale);
                $this->assertNotSame('req_'.$id, $raw, 'Missing label for '.$id.' in '.$locale);
                foreach (CertificationReview::placeholders('req_'.$id, $locale) as $placeholder) {
                    $this->assertContains($placeholder, ['min'], 'Only the documented placeholder may appear.');
                }

                $rendered = $this->line($this->review($this->business(), [], 3, $locale), $id)['label'];
                $this->assertStringNotContainsString('{', $rendered, 'Unfilled placeholder in '.$id.' ('.$locale.')');
            }
        }
    }

    public function test_requirement_ids_are_unique_and_severities_are_documented(): void
    {
        $ids = CertificationReview::requirementIds();
        $this->assertSame($ids, array_values(array_unique($ids)));

        foreach (CertificationReview::REQUIREMENTS as $requirement) {
            $this->assertContains($requirement['severity'], CertificationReview::SEVERITIES);
            $this->assertNotSame('', (string) $requirement['source'], 'Every requirement names its source.');
        }
    }

    /* ------------------------------------------------------------------ */
    /* The happy path                                                      */
    /* ------------------------------------------------------------------ */

    public function test_a_fully_recorded_business_meets_every_required_item(): void
    {
        $review = $this->review($this->business());

        $this->assertSame(100, $review['completeness']);
        $this->assertSame([], $review['missing']);
        $this->assertSame($review['counts']['required_total'], $review['counts']['required_met']);
        $this->assertContains(CertificationReview::msg('sum_ready', 'en'), $review['summary']);
    }

    public function test_the_review_never_decides_anything(): void
    {
        $review = $this->review($this->business());

        $this->assertSame('human_only', $review['decision']);
        $this->assertSame(CertificationReview::msg('decision_human_only', 'en'), $review['decision_label']);
        $this->assertArrayNotHasKey('approved', $review);
        $this->assertArrayNotHasKey('certified', $review);
        $this->assertContains(CertificationReview::msg('lim_no_decision', 'en'), $review['limitations']);
        $this->assertContains(CertificationReview::msg('lim_documents', 'en'), $review['limitations']);
    }

    /* ------------------------------------------------------------------ */
    /* Missing / present evidence                                          */
    /* ------------------------------------------------------------------ */

    public function test_a_missing_field_is_reported_missing_with_its_source(): void
    {
        $review = $this->review($this->business(['business_type' => null]));
        $line = $this->line($review, 'type');

        $this->assertSame(CertificationReview::STATUS_MISSING, $line['status']);
        $this->assertContains('type', $review['missing']);
        $this->assertStringContainsString('businesses.business_type', $line['evidence']);
    }

    public function test_the_description_must_reach_the_documented_length(): void
    {
        $short = $this->line($this->review($this->business(['business_description' => 'Too short'])), 'description');
        $this->assertSame(CertificationReview::STATUS_MISSING, $short['status']);

        $long = $this->line($this->review($this->business()), 'description');
        $this->assertSame(CertificationReview::STATUS_MET, $long['status']);
        $this->assertStringContainsString('Recorded:', $long['evidence']);
        $this->assertStringContainsString((string) CertificationReview::MIN_DESCRIPTION_CHARS, $long['label']);
    }

    public function test_the_location_must_be_pinned_with_both_coordinates(): void
    {
        $unPinned = $this->line($this->review($this->business(['business_latitude' => null])), 'pinned');
        $this->assertSame(CertificationReview::STATUS_MISSING, $unPinned['status']);

        $pinned = $this->line($this->review($this->business()), 'pinned');
        $this->assertSame(CertificationReview::STATUS_MET, $pinned['status']);
    }

    public function test_public_visibility_is_required_and_unknown_when_unread(): void
    {
        $this->assertSame(
            CertificationReview::STATUS_MISSING,
            $this->line($this->review($this->business(['is_public' => false])), 'public')['status']
        );
        $this->assertSame(
            CertificationReview::STATUS_UNKNOWN,
            $this->line($this->review($this->business(['is_public' => null])), 'public')['status']
        );
    }

    public function test_staff_needs_an_approved_member_with_a_name_and_a_phone(): void
    {
        $this->assertSame(
            CertificationReview::STATUS_MET,
            $this->line($this->review($this->business()), 'staff')['status']
        );

        $pending = $this->review($this->business(), [$this->member(['status' => 'pending'])]);
        $this->assertSame(CertificationReview::STATUS_MISSING, $this->line($pending, 'staff')['status']);

        $noPhone = $this->review($this->business(), [$this->member(['phone' => ''])]);
        $this->assertSame(CertificationReview::STATUS_MISSING, $this->line($noPhone, 'staff')['status']);

        $customer = $this->review($this->business(), [$this->member(['role' => 'customer'])]);
        $this->assertSame(CertificationReview::STATUS_MISSING, $this->line($customer, 'staff')['status']);
    }

    public function test_products_are_recommended_not_required(): void
    {
        $none = $this->review($this->business(), [], 0);
        $line = $this->line($none, 'products');

        $this->assertSame('recommended', $line['severity']);
        $this->assertSame(CertificationReview::STATUS_MISSING, $line['status']);
        $this->assertNotContains('products', $none['missing'], 'A recommended item is never a missing requirement.');
        $this->assertSame(100, $none['completeness'], 'A recommended item never blocks completeness.');

        $unread = $this->line($this->review($this->business(), [], null), 'products');
        $this->assertSame(CertificationReview::STATUS_UNKNOWN, $unread['status']);
    }

    /* ------------------------------------------------------------------ */
    /* Documents and ambiguity: human checks only                          */
    /* ------------------------------------------------------------------ */

    public function test_a_submitted_document_is_never_reported_as_met(): void
    {
        // Even when the row claims documents exist, the platform has no document
        // store, so the reviewer must not treat them as verified.
        $review = $this->review($this->business([
            'documents' => ['business_license.pdf'],
            'document_url' => 'https://cdn.example.com/license.pdf',
        ]));

        $line = $this->line($review, 'document');
        $this->assertSame(CertificationReview::STATUS_HUMAN, $line['status']);
        $this->assertFalse($line['done'] ?? false);
        $this->assertNotSame(CertificationReview::STATUS_MET, $line['status']);
        $this->assertSame(CertificationReview::msg('why_document', 'en'), $line['why']);
        $this->assertContains('document', array_column($review['human_checks'], 'id'));
    }

    public function test_backfilled_name_spellings_require_a_human(): void
    {
        $review = $this->review($this->business(['merged_from' => [
            'raw_spellings' => ['Gn motorcycle spare parts', 'GN Motorcycle spare parts'],
            'location_variants' => ['Mbeya'],
        ]]));

        $line = $this->line($review, 'name_ambiguous');
        $this->assertSame(CertificationReview::STATUS_HUMAN, $line['status']);
        $this->assertStringContainsString('Gn motorcycle spare parts', $line['evidence'], 'The evidence lists the real spellings.');
        $this->assertStringContainsString('GN Motorcycle spare parts', $line['evidence']);
        $this->assertContains('name_ambiguous', array_column($review['human_checks'], 'id'));
    }

    public function test_a_single_spelling_and_single_location_variant_are_fine(): void
    {
        $review = $this->review($this->business());

        $this->assertSame(CertificationReview::STATUS_MET, $this->line($review, 'name_ambiguous')['status']);
        $this->assertSame(CertificationReview::STATUS_MET, $this->line($review, 'location_ambiguous')['status']);
    }

    public function test_location_variants_need_a_human_and_provenance_may_arrive_as_json(): void
    {
        $json = json_encode(['raw_spellings' => ['One name'], 'location_variants' => ['Mbeya', 'Mbeya town']]);
        $review = $this->review($this->business(['merged_from' => $json]));

        $line = $this->line($review, 'location_ambiguous');
        $this->assertSame(CertificationReview::STATUS_HUMAN, $line['status']);
        $this->assertStringContainsString('Mbeya town', $line['evidence']);
        $this->assertSame(
            ['One name'],
            CertificationReview::spellings(['merged_from' => $json]),
            'The provenance decoder handles a JSON string as well as an array.'
        );
    }

    public function test_a_non_active_migration_row_needs_a_human(): void
    {
        $this->assertSame(
            CertificationReview::STATUS_HUMAN,
            $this->line($this->review($this->business(['migration_status' => 'needs_review'])), 'migration')['status']
        );
        $this->assertSame(
            CertificationReview::STATUS_HUMAN,
            $this->line($this->review($this->business(['migration_status' => null])), 'migration')['status']
        );
        $this->assertSame(
            CertificationReview::STATUS_MET,
            $this->line($this->review($this->business()), 'migration')['status']
        );
    }

    /* ------------------------------------------------------------------ */
    /* Unread rows                                                         */
    /* ------------------------------------------------------------------ */

    public function test_an_unread_row_is_never_presented_as_met_or_missing(): void
    {
        $review = CertificationReview::review(['locale' => 'en', 'business' => null, 'product_count' => null]);

        foreach ($review['requirements'] as $line) {
            if ($line['rule'] === 'document') {
                // The document item is decided by its RULE, not by the row: it
                // can never be verified here whether or not the row loaded.
                $this->assertSame(CertificationReview::STATUS_HUMAN, $line['status']);
                continue;
            }
            $this->assertSame(CertificationReview::STATUS_UNKNOWN, $line['status'], $line['id'].' must be unknown.');
            $this->assertStringContainsString('not read', $line['evidence']);
        }
        $requiredIds = array_column(array_filter($review['requirements'], static fn (array $l): bool => $l['severity'] === 'required'), 'id');
        $humanIds = array_column($review['human_checks'], 'id');

        $this->assertSame(count($requiredIds), $review['counts']['required_total']);
        foreach ($requiredIds as $id) {
            $this->assertContains($id, $humanIds, 'An unread requirement must be handed to a human: '.$id);
        }
        $this->assertContains('document', $humanIds, 'The document item is always a human check.');
        $this->assertSame(count($requiredIds) + 1, count($humanIds));
        $this->assertSame([], $review['missing']);
    }

    /* ------------------------------------------------------------------ */
    /* Bounded reads                                                       */
    /* ------------------------------------------------------------------ */

    public function test_a_capped_member_read_is_never_reported_as_missing(): void
    {
        $review = CertificationReview::review([
            'locale' => 'en',
            'business' => $this->business(),
            'members' => [],
            'members_read' => false,
            'product_count' => 3,
        ]);

        $line = $this->line($review, 'staff');
        $this->assertSame(CertificationReview::STATUS_UNKNOWN, $line['status']);
        $this->assertNotContains('staff', $review['missing'], 'An unread member list must not be called missing.');
        $this->assertContains('staff', array_column($review['human_checks'], 'id'));
    }

    public function test_a_capped_read_is_stated_as_a_limitation(): void
    {
        $plain = $this->review($this->business());
        $this->assertNotContains(CertificationReview::msg('lim_read_capped', 'en'), $plain['limitations']);

        $capped = CertificationReview::review([
            'locale' => 'en',
            'business' => $this->business(),
            'members' => [$this->member()],
            'product_count' => null,
            'reads_capped' => true,
        ]);

        $this->assertContains(CertificationReview::msg('lim_read_capped', 'en'), $capped['limitations']);
        $this->assertSame(CertificationReview::STATUS_UNKNOWN, $this->line($capped, 'products')['status']);
    }

    public function test_the_read_cap_message_exists_in_every_locale(): void
    {
        foreach (array_keys(CertificationReview::MESSAGES) as $locale) {
            $this->assertNotSame('lim_read_capped', CertificationReview::msg('lim_read_capped', $locale));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Counts, sources and configuration                                   */
    /* ------------------------------------------------------------------ */

    public function test_counts_and_completeness_match_the_lines(): void
    {
        $review = $this->review($this->business(['business_type' => null, 'business_logo_url' => null]));

        $this->assertSame(2, $review['counts']['missing']);
        $this->assertSame(count($review['missing']), $review['counts']['missing']);
        $this->assertSame(
            $review['counts']['required_total'] - $review['counts']['required_met'],
            $review['counts']['missing']
        );
        $this->assertSame(
            (int) round(($review['counts']['required_met'] / $review['counts']['required_total']) * 100),
            $review['completeness']
        );
        $this->assertContains(CertificationReview::msg('sum_missing', 'en', ['count' => 2]), $review['summary']);
    }

    public function test_every_line_names_a_source_and_sources_are_unique(): void
    {
        $review = $this->review($this->business());

        foreach ($review['requirements'] as $line) {
            $this->assertNotSame('', (string) $line['source']);
        }
        $this->assertSame(array_values(array_unique($review['sources'])), $review['sources']);
        $this->assertContains('businesses.business_name', $review['sources']);
    }

    public function test_an_operator_requirement_list_replaces_the_default_one(): void
    {
        $review = CertificationReview::review([
            'locale' => 'en',
            'business' => $this->business(['business_type' => null]),
            'requirements' => [
                ['id' => 'name', 'rule' => 'text', 'severity' => 'required', 'source' => 'businesses.business_name'],
                ['id' => 'bogus', 'rule' => 'teleport', 'severity' => 'required', 'source' => 'nowhere'],
                ['id' => 'type', 'rule' => 'text', 'severity' => 'sideways', 'source' => 'businesses.business_type'],
            ],
        ]);

        $ids = array_column($review['requirements'], 'id');
        $this->assertSame(['name'], $ids, 'Unknown rules and severities are dropped, never guessed.');
        $this->assertSame(CertificationReview::STATUS_MET, $review['requirements'][0]['status']);
    }

    public function test_an_empty_operator_list_falls_back_to_the_built_in_set(): void
    {
        $review = CertificationReview::review([
            'locale' => 'en',
            'business' => $this->business(),
            'requirements' => [],
        ]);

        $this->assertSame(CertificationReview::requirementIds(), array_column($review['requirements'], 'id'));
    }

    public function test_labels_and_statuses_are_translated(): void
    {
        $review = $this->review($this->business(['business_type' => null]), [], 3, 'sw');
        $line = $this->line($review, 'type');

        $this->assertSame('Haipo', $line['status_label']);
        $this->assertSame('Lazima', $line['severity_label']);
        $this->assertStringContainsString('Aina ya biashara', $line['label']);
    }
}
