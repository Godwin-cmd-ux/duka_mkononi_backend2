<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Businesses live in Supabase, so the local application database holds only
 * this tiny block flag. It is deliberately keyed by the business id (never by
 * the display name) and is checked on login and on every authenticated API
 * request, which makes a block apply to existing sessions as well as new ones.
 */
class BusinessBlock
{
    private static bool $ensured = false;

    /** @var array<string,bool> */
    private static array $cache = [];

    /**
     * Create the table on demand as well as through the migration. Production
     * may deploy before running migrations; failing to read the flag must not
     * take the whole API down.
     */
    public static function ensureTable(): void
    {
        if (self::$ensured) {
            return;
        }

        self::$ensured = true;

        try {
            if (! Schema::hasTable('business_blocks')) {
                Schema::create('business_blocks', function (Blueprint $table) {
                    $table->string('business_id')->primary();
                    $table->string('blocked_by')->nullable();
                    $table->timestamp('blocked_at')->nullable();
                });
            }
        } catch (\Throwable $e) {
            // No local database (or a read-only one): block checks fail open,
            // exactly like the rest of the best-effort audit storage.
        }
    }

    public static function isBlocked(?string $businessId): bool
    {
        if (! $businessId) {
            return false;
        }

        if (array_key_exists($businessId, self::$cache)) {
            return self::$cache[$businessId];
        }

        self::ensureTable();

        try {
            $blocked = DB::table('business_blocks')->where('business_id', $businessId)->exists();
        } catch (\Throwable $e) {
            $blocked = false;
        }

        self::$cache[$businessId] = $blocked;

        return $blocked;
    }

    public static function block(string $businessId, ?string $adminId = null): bool
    {
        self::ensureTable();

        try {
            DB::table('business_blocks')->updateOrInsert(
                ['business_id' => $businessId],
                ['blocked_by' => $adminId, 'blocked_at' => now()]
            );
            self::$cache[$businessId] = true;

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function unblock(string $businessId): bool
    {
        self::ensureTable();

        try {
            DB::table('business_blocks')->where('business_id', $businessId)->delete();
            self::$cache[$businessId] = false;

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param  array<int,string|null>  $businessIds
     * @return array<int,string>
     */
    public static function blockedIds(array $businessIds): array
    {
        $businessIds = array_values(array_unique(array_filter($businessIds)));

        if ($businessIds === []) {
            return [];
        }

        self::ensureTable();

        try {
            return DB::table('business_blocks')
                ->whereIn('business_id', $businessIds)
                ->pluck('business_id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
