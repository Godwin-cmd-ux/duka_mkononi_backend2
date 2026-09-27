<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;

/**
 * Single source of truth for "which business does this request belong to".
 *
 * Identity is the businesses.id UUID. business_name is display text only and is
 * never used to scope data, because the legacy users.business_name column
 * fragments one real shop across many spellings.
 *
 * legacy_name_key = lower(btrim(name)) reproduces the legacy check-business gate
 * exactly, so migrating a name through this service preserves the behaviour users
 * already relied on at signup.
 */
class BusinessResolver
{
    /** Normalise a business name to the legacy gate's key. */
    public static function key(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    /** Find a business by legacy key, falling back to an exact-name match. */
    public static function findByName(?string $name): ?SupabaseRow
    {
        $key = self::key($name);

        if ($key === '') {
            return null;
        }

        $business = Business::where('legacy_name_key', $key)->first();

        if ($business) {
            return $business;
        }

        return Business::whereRaw('LOWER(business_name) = ?', [$key])->first();
    }

    /** The business a user belongs to, or null for accounts with none. */
    public static function forUser(?string $userId): ?SupabaseRow
    {
        if (! $userId) {
            return null;
        }

        $businessId = self::idForUser($userId);

        if ($businessId) {
            $business = Business::where('id', $businessId)->first();

            if ($business) {
                return $business;
            }
        }

        $user = User::where('id', $userId)->select('id', 'business_name')->first();

        return $user ? self::findByName($user->business_name) : null;
    }

    public static function idForUser(?string $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        $user = User::where('id', $userId)->select('id', 'business_id')->first();

        return $user?->business_id ?: null;
    }

    /**
     * Every user id inside a business. Ownership chains (products.seller_id,
     * sales.seller_id, customers.seller_id) are scoped with this so one shop's
     * report includes all of its sellers' rows.
     *
     * @return array<int,string>
     */
    public static function memberIds(string $businessId, array $roles = [], string $status = ''): array
    {
        if (! $businessId) {
            return [];
        }

        $query = User::where('business_id', $businessId);

        // Reports and business dashboards only ever aggregate approved
        // admins/sellers, which is what the pre-migration
        // `where('business_name', $name)->whereIn('role', ...)` filters did.
        // Callers that need every member simply omit these arguments.
        if ($roles !== []) {
            $query->whereIn('role', $roles);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        return $query->pluck('id')->toArray();
    }

    /** Point a user at a business. */
    public static function attach(string $userId, string $businessId): void
    {
        User::where('id', $userId)->update(['business_id' => $businessId]);
    }

    /**
     * Resolve the business for an incoming signup, creating it only when no
     * business already owns that name. Mirrors the legacy gate: matching is
     * case- and whitespace-insensitive.
     */
    public static function resolveOrCreate(string $name, array $attributes = []): ?SupabaseRow
    {
        $existing = self::findByName($name);

        if ($existing) {
            return $existing;
        }

        $key = self::key($name);

        if ($key === '') {
            return null;
        }

        $payload = array_merge([
            'business_name' => trim($name),
            'legacy_name_key' => $key,
            'merged_from' => ['source' => 'signup'],
        ], array_filter($attributes, fn ($v) => $v !== null && $v !== ''));

        try {
            return Business::create($payload);
        } catch (\Throwable $e) {
            return self::findByName($name);
        }
    }
}
