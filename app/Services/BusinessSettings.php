<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;

/**
 * The ONE place that answers "is this optional module on for this business?".
 *
 * Every gate - API controllers, the web navigation bootstrap, the mobile
 * feature context - reads through here, so a feature can never be enabled in
 * one surface and disabled in another. A missing row means "all defaults", so
 * existing businesses keep today's behaviour without a backfill.
 *
 * The read is cached per business for a short time because navigation and
 * every fridge request ask for it. Every successful write forgets that key, so
 * a settings change takes effect on the next request rather than after the TTL.
 */
class BusinessSettings
{
    /** Optional modules that can be switched on or off. */
    public const FEATURES = ['fridge', 'scanner', 'services'];

    /**
     * Safe defaults that preserve live behaviour:
     *   fridge/services were already available before this module existed and
     *   are still in use, so they default ON. Scanner workflows are available
     *   but opt-in, so a business never gains new barcode UI without choosing
     *   it; the API refuses scanner operations until it is enabled.
     */
    public const DEFAULTS = [
        'fridge_enabled' => true,
        'scanner_enabled' => false,
        'services_enabled' => true,
        'product_management_enabled' => false,
        'notify_low_stock' => true,
        'notify_fridge_stock' => true,
    ];

    private const CACHE_TTL_SECONDS = 1800;

    /** @return array<string,bool> */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * The effective settings for a business, defaults merged with any stored
     * row. A null business id (customer account, legacy row) gets defaults.
     *
     * @return array<string,bool>
     */
    public static function forBusiness(?string $businessId): array
    {
        $defaults = self::DEFAULTS;

        if (! $businessId) {
            return $defaults;
        }

        return Cache::remember(self::cacheKey($businessId), self::CACHE_TTL_SECONDS, function () use ($businessId, $defaults) {
            try {
                $row = BusinessSetting::where('business_id', $businessId)->first();
            } catch (\Throwable $e) {
                // The settings table may not be migrated yet on an older
                // deployment. Falling back to defaults is safer than taking
                // the whole dashboard down and matches pre-settings behaviour.
                return $defaults;
            }

            if (! $row) {
                return $defaults;
            }

            $settings = $defaults;
            foreach (array_keys($defaults) as $key) {
                if ($row->{$key} !== null) {
                    $settings[$key] = self::toBool($row->{$key});
                }
            }

            return $settings;
        });
    }

    /** Is one optional module enabled for the business? */
    public static function enabled(string $feature, ?string $businessId): bool
    {
        $key = $feature . '_enabled';

        return (bool) (self::forBusiness($businessId)[$key] ?? true);
    }

    /**
     * The feature map shared with web and mobile clients.
     *
     * @return array{fridge:bool,scanner:bool,services:bool}
     */
    public static function features(?string $businessId): array
    {
        $settings = self::forBusiness($businessId);

        return [
            'fridge' => (bool) $settings['fridge_enabled'],
            'scanner' => (bool) $settings['scanner_enabled'],
            'services' => (bool) $settings['services_enabled'],
            'product_management' => (bool) $settings['product_management_enabled'],
        ];
    }

    /**
     * Persist a partial settings change.
     *
     * Only keys in DEFAULTS are accepted; anything else is ignored so a client
     * can never add an arbitrary column. Returns the effective settings and
     * the subset that actually changed (used for the audit row).
     *
     * @param  array<string,mixed>  $changes
     * @return array{settings:array<string,bool>,changed:array<string,array{from:bool,to:bool}>}
     */
    public static function update(string $businessId, array $changes, ?string $userId): array
    {
        $current = self::forBusiness($businessId);
        $next = $current;
        $changed = [];

        foreach (self::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $changes) || $changes[$key] === null) {
                continue;
            }

            $value = self::toBool($changes[$key]);

            if ($value !== $current[$key]) {
                $next[$key] = $value;
                $changed[$key] = ['from' => $current[$key], 'to' => $value];
            }
        }

        if ($changed === []) {
            return ['settings' => $current, 'changed' => []];
        }

        $payload = $next;
        $payload['updated_by'] = $userId;
        $payload['updated_at'] = now()->toISOString(true);

        try {
            $existing = BusinessSetting::where('business_id', $businessId)->first();

            if ($existing) {
                BusinessSetting::where('business_id', $businessId)->update($payload);
            } else {
                $payload['business_id'] = $businessId;
                $payload['created_at'] = now()->toISOString(true);
                BusinessSetting::query()->insert($payload);
            }
        } finally {
            // Even a failed write must not leave a stale cached value behind:
            // the next read has to observe the database.
            Cache::forget(self::cacheKey($businessId));
        }

        return ['settings' => $next, 'changed' => $changed];
    }

    public static function forget(?string $businessId): void
    {
        if ($businessId) {
            Cache::forget(self::cacheKey($businessId));
        }
    }

    private static function cacheKey(string $businessId): string
    {
        return 'business_settings:v1:' . $businessId;
    }

    /** PostgREST returns booleans, but older rows/clients may send 1/"true". */
    private static function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 't', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
