<?php

namespace Tests\Unit;

use App\Services\Ai\AnomalyDetector;
use Tests\TestCase;

/**
 * Capability #6: the deterministic rules must fire on the synthetic evidence
 * they are named after, stay quiet on healthy data, sort high severity first
 * and keep stable alert keys. The detector stamps detected_at with now(), so
 * this boots the framework - but it touches no database, HTTP or Supabase.
 */
class AnomalyDetectorTest extends TestCase
{
    /** Two adjacent 7-day windows the synthetic rows are dated against. */
    private const WINDOWS = [
        'recent' => ['from' => '2026-09-25', 'to' => '2026-10-01'],
        'prior' => ['from' => '2026-09-18', 'to' => '2026-09-24'],
    ];

    public function test_sale_below_recorded_cost_raises_high_alert(): void
    {
        $alerts = AnomalyDetector::detect(
            [['id' => 'p1', 'name' => 'Sukuki', 'stock' => 10, 'price' => 1000]],
            [[
                'sale_id' => 'S1',
                'product_id' => 'p1',
                'quantity' => 2,
                'unit_price' => 700,
                'total_price' => 1400,
                'sale_date' => '2026-09-27',
            ]],
            [],
            self::WINDOWS
        );

        $this->assertCount(1, $alerts, 'Only the below-cost rule can fire on this data.');
        $this->assertSame(AnomalyDetector::RULE_BELOW_COST, $alerts[0]['rule']);
        $this->assertSame('high', $alerts[0]['severity']);
        $this->assertSame('Sukuki', $alerts[0]['subject']);
        $this->assertSame(2, $alerts[0]['figures']['units']);
        $this->assertSame(600.0, $alerts[0]['figures']['estimated_loss'], '(1000 - 700) x 2 units of estimated loss.');
        $this->assertNotSame('', $alerts[0]['title']);
        $this->assertNotSame('', $alerts[0]['detail']);
        $this->assertNotSame('', $alerts[0]['verify_steps'], 'Every alert must tell the manager how to verify it.');
        $this->assertNotSame('', $alerts[0]['key']);
    }

    public function test_healthy_data_produces_no_alerts(): void
    {
        $alerts = AnomalyDetector::detect(
            [['id' => 'p1', 'name' => 'Sukuki', 'stock' => 10, 'price' => 1000]],
            [[
                'sale_id' => 'S1',
                'product_id' => 'p1',
                'quantity' => 1,
                'unit_price' => 1500,
                'total_price' => 1500,
                'sale_date' => '2026-09-27',
            ]],
            [],
            self::WINDOWS
        );

        $this->assertSame([], $alerts, 'A normal sale above cost must not raise anything.');
    }

    public function test_margin_drop_between_windows_is_detected(): void
    {
        $products = [
            ['id' => 'p1', 'name' => 'P1', 'stock' => 5, 'price' => 600],
            ['id' => 'p2', 'name' => 'P2', 'stock' => 5, 'price' => 600],
            ['id' => 'p3', 'name' => 'P3', 'stock' => 5, 'price' => 600],
        ];

        // Prior window sells at 1000 (40% margin); recent window cuts the
        // price to 700 (still above the 600 cost, so below-cost stays quiet).
        $items = [];
        foreach (['p1', 'p2', 'p3'] as $index => $productId) {
            $items[] = [
                'sale_id' => 'A'.($index + 1),
                'product_id' => $productId,
                'quantity' => 1,
                'unit_price' => 1000,
                'total_price' => 1000,
                'sale_date' => '2026-09-20',
            ];
            $items[] = [
                'sale_id' => 'B'.($index + 1),
                'product_id' => $productId,
                'quantity' => 1,
                'unit_price' => 700,
                'total_price' => 700,
                'sale_date' => '2026-09-28',
            ];
        }

        $alerts = AnomalyDetector::detect($products, $items, [], self::WINDOWS);

        $margin = null;
        foreach ($alerts as $alert) {
            if ($alert['rule'] === AnomalyDetector::RULE_MARGIN_DROP) {
                $margin = $alert;
            }
        }

        $this->assertNotNull($margin, 'A margin collapse across windows must be detected.');
        $this->assertSame('medium', $margin['severity']);
        $this->assertSame(40.0, $margin['figures']['prior_margin_pct']);
        $this->assertGreaterThan(10.0, $margin['figures']['drop_pp'], 'The drop must clear the default 10pp threshold.');
        $this->assertSame(3, $margin['figures']['recent_lines']);
        $this->assertSame(3, $margin['figures']['prior_lines']);
    }

    public function test_duplicate_sale_records_are_grouped_as_possible_duplicates(): void
    {
        $alerts = AnomalyDetector::detect(
            [['id' => 'p1', 'name' => 'Sukuki', 'stock' => 8, 'price' => 1000]],
            [
                ['sale_id' => 'S1', 'product_id' => 'p1', 'quantity' => 1, 'unit_price' => 2000, 'total_price' => 2000, 'sale_date' => '2026-09-26'],
                ['sale_id' => 'S2', 'product_id' => 'p1', 'quantity' => 1, 'unit_price' => 2000, 'total_price' => 2000, 'sale_date' => '2026-09-26'],
            ],
            [],
            self::WINDOWS
        );

        $this->assertCount(1, $alerts);
        $this->assertSame(AnomalyDetector::RULE_DUPLICATE, $alerts[0]['rule']);
        $this->assertSame('medium', $alerts[0]['severity']);
        $this->assertSame(2, $alerts[0]['figures']['record_count']);
    }

    public function test_expense_spike_needs_both_a_ratio_and_an_absolute_jump(): void
    {
        $prior = [['amount' => 20000, 'category' => 'usafiri', 'expense_date' => '2026-09-20']];
        $spike = [['amount' => 60000, 'category' => 'usafiri', 'expense_date' => '2026-09-27']];

        $alerts = AnomalyDetector::detect([], [], array_merge($spike, $prior), self::WINDOWS);

        $this->assertCount(1, $alerts, 'Tripling expenses within a week must be flagged.');
        $this->assertSame(AnomalyDetector::RULE_EXPENSE_SPIKE, $alerts[0]['rule']);
        $this->assertSame('medium', $alerts[0]['severity']);
        $this->assertSame(60000.0, $alerts[0]['figures']['recent_total']);
        $this->assertSame(20000.0, $alerts[0]['figures']['prior_total']);
        $this->assertSame(40000.0, $alerts[0]['figures']['delta']);

        // A small 25% rise is below the default 2x ratio: quiet.
        $smallJump = [['amount' => 25000, 'category' => 'usafiri', 'expense_date' => '2026-09-27']];
        $this->assertSame([], AnomalyDetector::detect([], [], array_merge($smallJump, $prior), self::WINDOWS));
    }

    public function test_negative_stock_is_flagged_high_and_sorted_first(): void
    {
        $products = [
            ['id' => 'pneg', 'name' => 'Hasara', 'stock' => -3, 'price' => 500],
            ['id' => 'pok', 'name' => 'Sawa', 'stock' => 4, 'price' => 500],
        ];
        $expenses = array_merge(
            [['amount' => 60000, 'category' => 'rent', 'expense_date' => '2026-09-27']],
            [['amount' => 20000, 'category' => 'rent', 'expense_date' => '2026-09-20']],
        );

        $alerts = AnomalyDetector::detect($products, [], $expenses, self::WINDOWS);

        $this->assertCount(2, $alerts);
        $this->assertSame(AnomalyDetector::RULE_STOCK_MISMATCH, $alerts[0]['rule'], 'High severity sorts before medium.');
        $this->assertSame('high', $alerts[0]['severity']);
        $this->assertSame(-3.0, $alerts[0]['figures']['stock']);
        $this->assertSame(AnomalyDetector::RULE_EXPENSE_SPIKE, $alerts[1]['rule']);
    }

    public function test_alert_keys_are_stable_for_identical_evidence(): void
    {
        $products = [
            ['id' => 'pneg', 'name' => 'Hasara', 'stock' => -3, 'price' => 500],
            ['id' => 'pok', 'name' => 'Sawa', 'stock' => 4, 'price' => 500],
        ];

        $first = array_column(AnomalyDetector::detect($products, [], [], self::WINDOWS), 'key');
        $second = array_column(AnomalyDetector::detect($products, [], [], self::WINDOWS), 'key');

        $this->assertNotSame([], $first);
        $this->assertSame($first, $second, 'Unchanged evidence must keep the same dismissal key.');
    }

    public function test_max_alerts_caps_the_returned_list(): void
    {
        $products = [
            ['id' => 'a', 'name' => 'A', 'stock' => -1, 'price' => 100],
            ['id' => 'b', 'name' => 'B', 'stock' => -2, 'price' => 100],
            ['id' => 'c', 'name' => 'C', 'stock' => -3, 'price' => 100],
        ];

        $alerts = AnomalyDetector::detect($products, [], [], self::WINDOWS, ['max_alerts' => 2]);

        $this->assertCount(2, $alerts, 'The configured cap must never be exceeded.');
    }

    public function test_alerts_render_in_the_requested_locale(): void
    {
        $products = [['id' => 'pneg', 'name' => 'Hasara', 'stock' => -3, 'price' => 500]];

        foreach (['sw', 'en'] as $locale) {
            $alerts = AnomalyDetector::detect($products, [], [], self::WINDOWS, ['locale' => $locale]);
            $this->assertCount(1, $alerts);
            $this->assertNotSame('', $alerts[0]['title'], "Locale {$locale} must produce a title.");
            $this->assertNotSame('', $alerts[0]['verify_steps'], "Locale {$locale} must produce verify steps.");
        }
    }

    /**
     * Every locale's detail/verify template must consume the figures in the
     * exact placeholder order the rule supplies them (see texts()). A locale
     * that drops or reorders a placeholder would render wrong numbers.
     */
    public function test_placeholder_order(): void
    {
        $texts = null;
        foreach ((new \ReflectionClass(AnomalyDetector::class))->getReflectionConstants() as $constant) {
            if ($constant->getName() === 'TEXTS') {
                $texts = $constant->getValue();
            }
        }

        $this->assertIsArray($texts, 'TEXTS must stay a class constant of the detector.');

        foreach ($texts as $rule => $packs) {
            $this->assertArrayHasKey('en', $packs, "Rule {$rule} needs an English pack as the comparison baseline.");

            foreach ($packs as $locale => $pack) {
                $this->assertSame(
                    [],
                    self::placeholders($pack['title']),
                    "Titles render raw: the {$rule}/{$locale} title must not contain placeholders."
                );
            }

            foreach (['detail', 'verify'] as $field) {
                $this->assertNotSame('', $packs['en'][$field], "The English {$rule}/{$field} template must not be empty.");
                $expected = self::placeholders($packs['en'][$field]);

                foreach ($packs as $locale => $pack) {
                    $this->assertSame(
                        $expected,
                        self::placeholders($pack[$field]),
                        "{$rule}/{$field} in {$locale} must use the same placeholder sequence as English."
                    );
                }
            }
        }
    }

    /** printf tokens of a template, in order (e.g. ['%s', '%s']). */
    private static function placeholders(string $template): array
    {
        preg_match_all('/%(?:\d+\$)?[sdufF]/', $template, $matches);

        return $matches[0];
    }
}
