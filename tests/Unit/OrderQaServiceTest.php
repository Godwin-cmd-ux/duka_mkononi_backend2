<?php

namespace Tests\Unit;

use App\Services\Ai\OrderQaService;
use App\Services\OrderService;
use PHPUnit\Framework\TestCase;

/**
 * Capability #13: reference extraction, order focus selection, status labels
 * and the bounded context handed to the model must be exact - the model only
 * words what this service computes. Pure-function tests: no database, no
 * HTTP, no Laravel helpers.
 */
class OrderQaServiceTest extends TestCase
{
    public function test_extract_reference_finds_and_uppercases_a_reference(): void
    {
        $found = OrderQaService::extractReference('Hello, where is order dm-20260115-abc123 right now?');

        $this->assertSame('DM-20260115-ABC123', $found);
        $this->assertNull(OrderQaService::extractReference('Where is my order?'), 'No reference means no reference.');
        $this->assertNull(OrderQaService::extractReference('DM-123 too short'), 'A malformed reference must not match.');
    }

    public function test_focus_order_picks_the_referenced_order_else_the_first(): void
    {
        $orders = [
            ['order_reference' => 'DM-20260115-AAAAAA', 'total_amount' => 1000],
            ['order_reference' => 'DM-20260116-BBBBBB', 'total_amount' => 2000],
        ];

        $focused = OrderQaService::focusOrder($orders, 'DM-20260116-BBBBBB');
        $this->assertSame('DM-20260116-BBBBBB', $focused['order_reference']);

        $first = OrderQaService::focusOrder($orders, null);
        $this->assertSame('DM-20260115-AAAAAA', $first['order_reference'], 'Without a reference, the most recent (first) order is focused.');

        $this->assertNull(OrderQaService::focusOrder($orders, 'DM-99999999-ZZZZZZ'), 'An unknown reference must not silently fall back to another order.');
        $this->assertNull(OrderQaService::focusOrder([], null));
    }

    public function test_status_guide_covers_every_recorded_status_with_a_label(): void
    {
        $guide = OrderQaService::statusGuide('en');

        $this->assertSame(OrderService::STATUSES, array_column($guide, 'status'), 'The guide must list every recorded status in order.');
        foreach ($guide as $row) {
            $this->assertNotSame('', $row['label'], "Status {$row['status']} needs a human label.");
        }
    }

    public function test_unknown_status_falls_back_to_the_recorded_value(): void
    {
        $this->assertSame('weird_status', OrderQaService::statusLabel('weird_status', 'en'), 'Unknown statuses are shown honestly, never guessed.');
        $this->assertNotSame('', OrderQaService::statusLabel('delivered', 'en'));
        $this->assertNotSame('', OrderQaService::statusLabel('delivered', 'sw'));
    }

    public function test_context_for_bounds_items_and_timeline_and_truncates_notes(): void
    {
        $items = [];
        for ($i = 0; $i < 30; $i++) {
            $items[] = ['product_name' => 'Item '.$i, 'quantity' => 1, 'unit_price' => 100, 'total_price' => 100];
        }

        $timeline = [];
        for ($i = 0; $i < 12; $i++) {
            $timeline[] = ['status' => 'pending', 'note' => str_repeat('n', 300), 'at' => '2026-01-01'];
        }

        $ctx = OrderQaService::contextFor([
            'order_reference' => 'DM-20260115-ABC123',
            'order_date' => '2026-01-01',
            'status' => 'out_for_delivery',
            'total_amount' => 4500,
            'currency' => 'TZS',
            'business_name' => 'Duka Moja',
            'items' => $items,
            'timeline' => $timeline,
            'latest_note' => str_repeat('x', 400),
        ], 'en');

        $this->assertSame('DM-20260115-ABC123', $ctx['order_reference']);
        $this->assertCount(20, $ctx['items'], 'At most 20 item lines reach the model.');
        $this->assertCount(8, $ctx['timeline'], 'At most 8 timeline entries reach the model.');
        $this->assertSame('Item 0', $ctx['items'][0]['name']);
        $this->assertSame(100.0, $ctx['items'][0]['line_total']);
        $this->assertSame(200, mb_strlen($ctx['latest_note']), 'Customer notes are capped at 200 characters.');
        $this->assertSame(200, mb_strlen($ctx['timeline'][0]['note']), 'Timeline notes are capped too.');
    }

    public function test_fallback_answer_states_recorded_facts_in_english(): void
    {
        $answer = OrderQaService::fallbackAnswer([
            'order_reference' => 'DM-20260115-ABC123',
            'order_date' => '2026-01-01',
            'status' => 'out_for_delivery',
            'total_amount' => 4500,
            'currency' => 'TZS',
            'business_name' => 'Duka Moja',
            'items' => [['product_name' => 'Sukuki', 'quantity' => 1, 'unit_price' => 4500, 'total_price' => 4500]],
            'timeline' => [],
        ], 'en');

        $this->assertNotSame('', trim($answer));
        $this->assertStringContainsString('DM-20260115-ABC123', $answer, 'The fallback answer must quote the order reference it is about.');
        $this->assertLessThanOrEqual(800, mb_strlen($answer), 'The deterministic answer is bounded.');
    }

    public function test_fallback_answer_appends_the_latest_timeline_update(): void
    {
        $with = OrderQaService::fallbackAnswer([
            'order_reference' => 'DM-20260115-ABC123',
            'items' => [],
            'timeline' => [['status' => 'delivered', 'at' => '2026-01-03']],
        ], 'en');
        $without = OrderQaService::fallbackAnswer([
            'order_reference' => 'DM-20260115-ABC123',
            'items' => [],
            'timeline' => [],
        ], 'en');

        $this->assertNotSame($without, $with, 'A recorded status change must be mentioned.');
    }

    public function test_localized_messages_and_limitations_are_non_empty(): void
    {
        foreach (['sw', 'en'] as $locale) {
            $this->assertNotSame('', OrderQaService::noOrdersText($locale));
            $this->assertNotSame('', OrderQaService::noPhoneText($locale));
            $this->assertNotSame('', OrderQaService::referenceNotFoundText('DM-20260115-ABC123', $locale));
            $this->assertNotSame([], OrderQaService::limitations($locale));
        }
    }
}
