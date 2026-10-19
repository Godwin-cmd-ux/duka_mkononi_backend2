<?php

namespace Tests\Feature;

use App\Services\Monitoring\MonitoringSchema;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The monitoring tables are created by the migration AND by the admin setup
 * endpoint from this single definition - both must stay idempotent and
 * strictly additive (never touching pre-existing tables).
 */
class MonitoringSchemaTest extends TestCase
{
    public function test_ensure_creates_the_three_tables(): void
    {
        $created = MonitoringSchema::ensure();

        $this->assertEqualsCanonicalizing(
            ['activity_events', 'session_activity', 'security_alerts'],
            $created,
            'First run must create exactly the three monitoring tables.'
        );
        foreach (MonitoringSchema::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} must exist after ensure().");
        }
    }

    public function test_ensure_is_idempotent(): void
    {
        MonitoringSchema::ensure();
        $second = MonitoringSchema::ensure();

        $this->assertSame([], $second, 'A second run must not recreate or alter anything.');
    }

    public function test_expected_indexes_and_columns_exist(): void
    {
        MonitoringSchema::ensure();

        foreach (['category', 'severity', 'outcome', 'created_at', 'user_id', 'session_ref', 'is_security'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('activity_events', $column),
                "activity_events.{$column} is a documented query path and must exist."
            );
        }
        $this->assertTrue(Schema::hasColumn('security_alerts', 'occurrence_count'));
        $this->assertTrue(Schema::hasColumn('session_activity', 'last_seen_at'));

        $indexes = Schema::getIndexes('activity_events');
        $indexedColumns = [];
        foreach ($indexes as $index) {
            $indexedColumns = array_merge($indexedColumns, $index['columns']);
        }
        foreach (['created_at', 'severity', 'category', 'outcome'] as $column) {
            $this->assertContains($column, $indexedColumns, "activity_events.{$column} must be indexed for timeline queries.");
        }
    }
}
