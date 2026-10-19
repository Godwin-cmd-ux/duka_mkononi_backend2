<?php

namespace App\Services\Monitoring;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The single definition of the monitoring tables (Phase 6 / database
 * architecture), shared by:
 *
 *  - the migration (database/migrations/*_create_monitoring_tables.php), and
 *  - POST /api/admin/monitoring/setup, which follows this project's existing
 *    runtime-setup precedent (ResetPasswordController::setupTable,
 *    NotificationController::setup) so a deployment that has not run its
 *    migrations can still initialise the system from the admin dashboard.
 *
 * Conventions mirror database/migrations/0001_01_01_000000_create_dukamkononi_
 * schema.php: uuid primary keys, text columns, nullable timestamps, explicit
 * indexes, hasTable guards, and a strictly non-destructive lifecycle.
 *
 * Indexes cover exactly the query patterns the admin dashboard issues
 * (time range, user, category, severity, outcome, session, correlation,
 * entity, ip) and deliberately do NOT index the metadata blob.
 */
class MonitoringSchema
{
    public const TABLES = ['activity_events', 'session_activity', 'security_alerts'];

    /**
     * Create any missing monitoring tables. Idempotent; never alters or
     * drops existing tables.
     *
     * @return list<string> tables that were created by this call
     */
    public static function ensure(): array
    {
        $created = [];

        if (! Schema::hasTable('activity_events')) {
            Schema::create('activity_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->nullable()->index();
                $table->text('session_ref')->nullable()->index();
                $table->text('correlation_id')->nullable()->index();
                $table->text('category')->index();
                $table->text('event_name')->index();
                $table->text('description')->nullable();
                $table->text('page')->nullable();
                $table->text('route')->nullable();
                $table->text('element')->nullable();
                $table->text('entity_type')->nullable();
                $table->text('entity_id')->nullable();
                $table->text('source')->nullable();
                $table->text('outcome')->nullable()->index();
                $table->text('severity')->default('INFO')->index();
                $table->text('severity_reason')->nullable();
                $table->boolean('is_security')->default(false)->index();
                // Sanitised JSON string (same pattern as user_logs.details).
                $table->text('metadata')->nullable();
                $table->text('ip_address')->nullable()->index();
                $table->text('user_agent')->nullable();
                // Parsed device facts as JSON (browser/os/device category).
                $table->text('device')->nullable();
                $table->text('related_event_id')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('created_at');
                $table->index(['entity_type', 'entity_id']);
            });
            $created[] = 'activity_events';
        }

        if (! Schema::hasTable('session_activity')) {
            Schema::create('session_activity', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->nullable()->index();
                // Server-derived reference: sha256 of the presented JWT. The
                // client never chooses it, and it is not the token itself.
                $table->text('session_ref')->unique();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->text('status')->default('active')->index();
                $table->text('end_reason')->nullable();
                $table->text('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->text('device')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
            $created[] = 'session_activity';
        }

        if (! Schema::hasTable('security_alerts')) {
            Schema::create('security_alerts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('rule')->index();
                // e.g. "user:<id>", "acct:<identifier>", "ip:<address>".
                $table->text('subject')->index();
                $table->text('severity')->index();
                $table->text('status')->default('open')->index();
                $table->text('summary')->nullable();
                // Sanitised JSON context (reason counts, role, window).
                $table->text('context')->nullable();
                $table->integer('occurrence_count')->default(1);
                $table->timestamp('first_seen')->nullable();
                $table->timestamp('last_seen')->nullable();
                // Investigation trail as JSON [{at, by, action, text}].
                $table->text('notes')->nullable();
                $table->text('acknowledged_by')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->text('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
            $created[] = 'security_alerts';
        }

        return $created;
    }
}
