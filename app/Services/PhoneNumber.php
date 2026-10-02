<?php

namespace App\Services;

/**
 * Consistent phone-number handling for order placement and tracking.
 *
 * The platform's numbers arrive in many shapes ("0616255980", "+255 769 404
 * 040", "255769513861"). Tracking must match the same person across all of
 * them, so every number is reduced to one canonical digits-only form rooted at
 * the country code (255 for Tanzania).
 */
class PhoneNumber
{
    /** Reduce any input to canonical digits (TZ: 255xxxxxxxxx). */
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || $digits === null) {
            return '';
        }

        // Already country-coded.
        if (str_starts_with($digits, '255')) {
            return $digits;
        }

        // Local format starting with a trunk zero: 0XXXXXXXXX.
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // 9-digit local mobile (6XXXXXXXX / 7XXXXXXXX) -> prefix the country code.
        if (strlen($digits) === 9) {
            return '255' . $digits;
        }

        return $digits;
    }

    /**
     * A usable mobile number. Lenient enough for the many stored shapes but
     * strict enough to reject obvious junk for the tracking endpoint.
     */
    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);

        return strlen($normalized) >= 9 && strlen($normalized) <= 15;
    }
}
