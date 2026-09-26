<?php

namespace App\Services;

use Illuminate\Support\Str;

class AuditLogger
{
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
    }
}