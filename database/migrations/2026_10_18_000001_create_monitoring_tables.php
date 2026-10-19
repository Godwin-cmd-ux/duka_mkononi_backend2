<?php

use App\Services\Monitoring\MonitoringSchema;
use Illuminate\Database\Migrations\Migration;

/**
 * Monitoring tables (advanced user activity monitoring, audit logging and
 * security intelligence). The full definition lives in MonitoringSchema so the
 * runtime setup endpoint (POST /api/admin/monitoring/setup) can initialise the
 * same tables when a deployment has not run its migrations.
 *
 * Non-destructive: creates three new tables only and never touches the
 * existing user_logs table or any other pre-existing data. Reversible by
 * dropping only the three tables this migration created (kept as a no-op by
 * this project's convention: down() never destroys data).
 */
return new class extends Migration
{
    public function up(): void
    {
        MonitoringSchema::ensure();
    }

    public function down(): void
    {
        // Non-destructive: never drop tables (project convention).
    }
};
