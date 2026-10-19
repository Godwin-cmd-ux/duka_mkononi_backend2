<?php

namespace App\Services;

use App\Services\Monitoring\ActivityTracker;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * The existing audit write, unchanged in shape and behaviour: one row in
     * `user_logs`, failures swallowed so logging never breaks a request.
     *
     * After the legacy row is stored, MEANINGFUL actions are additionally
     * projected into the normalized `activity_events` table through
     * ActivityTracker (Phase 3 bridge). Reads/probes are filtered out by the
     * classifier, so this adds no duplicate logging system and no per-request
     * noise - it extends the existing one.
     */
    public static function log(?string $userId, string $action, string $endpoint, array $details, ?string $ip = null, string $status = 'success'): void
    {
        try {
            Supabase::table('user_logs')->insert([
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'action' => $action,
                'endpoint' => $endpoint,
                'details' => json_encode($details ?: (object) []),
                'ip_address' => $ip,
                'status' => $status,
                'created_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $e) {
            // logging must never break the request
        }

        try {
            ActivityTracker::mirrorLegacy($userId, $action, $endpoint, $details, $ip, $status);
        } catch (\Throwable $e) {
            // the bridge is best effort; the legacy row above already exists
        }
    }
}