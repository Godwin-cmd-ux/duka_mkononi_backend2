<?php

namespace App\Services\Ai;

use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * Capability #22 - the ONE authoritative locale service for every AI
 * capability.
 *
 * Before this existed, twenty-one AI controllers each carried an identical
 * private `resolveLocale()` that only understood the request body and fell
 * straight back to `sw`. That had two problems the catalogue calls out:
 *   1. "unify language handling" - the rule lived in 21 places and could drift;
 *   2. "fall back to the configured account or application locale" - an
 *      authenticated caller whose account language is `fr` but whose client did
 *      not send a `locale` field was answered in Swahili.
 *
 * `resolve()` fixes both. The order is documented and fixed:
 *   1. the request's own `locale` field, when it is in the allow-list;
 *   2. the caller's stored account language (`users.language`, surfaced by the
 *      JWT middleware as `jwt_language`);
 *   3. `config('ai.default_locale')`, then the hard `sw` default.
 *
 * The allow-list itself is `config('ai.locales')` - the same eight codes used
 * by `locales.json`, the mobile `LanguageContext` and `routes/web.php`. Nothing
 * in this class ever translates a brand: "Duka Mkononi" is a literal that must
 * survive every locale.
 *
 * Formatting is intentionally deterministic rather than provider-dependent (the
 * sandbox has no `intl` extension): each locale maps to a BCP-47 tag that the
 * clients can hand to their own `Intl`, and the server-side helpers format
 * numbers/amounts/dates with that locale's separator and pattern rules.
 */
class Locale
{
    /** The hard fallback when even `config('ai.default_locale')` is unusable. */
    public const FALLBACK = 'sw';

    /** The only right-to-left language in the catalogue. */
    public const RTL = ['ur'];

    /**
     * The eight BCP-47 language tags the clients should format with. A locale
     * code is what our API speaks; a tag is what `Intl` speaks.
     */
    public const TAGS = [
        'sw' => 'sw-TZ',
        'en' => 'en-TZ',
        'fr' => 'fr-FR',
        'hi' => 'hi-IN',
        'es' => 'es-ES',
        'ur' => 'ur-PK',
        'de' => 'de-DE',
        'zh' => 'zh-CN',
    ];

    /** Thousands/decimal separator rules per locale code. */
    private const NUMBERS = [
        // [group separator, decimal separator, indian grouping]
        'sw' => [',', '.', false],
        'en' => [',', '.', false],
        'hi' => [',', '.', true],
        'ur' => [',', '.', false],
        'zh' => [',', '.', false],
        'de' => ['.', ',', false],
        'es' => ['.', ',', false],
        'fr' => ["\u{202F}", ',', false],
    ];

    /** Date output patterns per locale code (PHP `date()` letters). */
    private const DATE_PATTERNS = [
        'zh' => 'Y-m-d',
        'de' => 'd.m.Y',
    ];

    /** @return array<int, string> The authoritative allow-list. */
    public static function supported(): array
    {
        $codes = (array) config('ai.locales', array_keys(self::TAGS));

        return array_values(array_filter($codes, 'is_string'));
    }

    public static function isSupported(?string $code): bool
    {
        return $code !== null && in_array(strtolower(trim($code)), self::supported(), true);
    }

    /** Lower-case, trim and validate; null when the code is not allowed. */
    public static function normalize(?string $code): ?string
    {
        if (! is_string($code)) {
            return null;
        }

        $candidate = strtolower(trim($code));

        return in_array($candidate, self::supported(), true) ? $candidate : null;
    }

    /** The configured default, itself validated against the allow-list. */
    public static function default(): string
    {
        return self::normalize((string) config('ai.default_locale', self::FALLBACK)) ?? self::FALLBACK;
    }

    public static function isRtl(string $locale): bool
    {
        return in_array(self::normalize($locale) ?? '', self::RTL, true);
    }

    /** `rtl` or `ltr` - the value the `dir` attribute/`I18nManager` needs. */
    public static function direction(string $locale): string
    {
        return self::isRtl($locale) ? 'rtl' : 'ltr';
    }

    /** BCP-47 tag for client-side `Intl` formatting. */
    public static function tag(string $locale): string
    {
        $code = self::normalize($locale) ?? self::default();

        return self::TAGS[$code] ?? self::TAGS[self::FALLBACK];
    }

    /**
     * Resolve the locale for an AI request.
     *
     * @param  Request|null  $request  The HTTP request carrying `locale`.
     * @param  string|null  $accountLocale  The caller's stored account language.
     */
    public static function resolve(?Request $request = null, ?string $accountLocale = null): string
    {
        if ($request !== null) {
            $fromRequest = self::normalize((string) $request->input('locale', ''));
            if ($fromRequest !== null) {
                return $fromRequest;
            }
        }

        $fromAccount = self::normalize($accountLocale);
        if ($fromAccount !== null) {
            return $fromAccount;
        }

        return self::default();
    }

    /** The currency code every capability reports amounts in. */
    public static function currency(string $locale): string
    {
        return (string) config('ai.currency', 'TZS');
    }

    /**
     * Locale-aware number formatting: grouping separators (including the Indian
     * lakh/crore grouping for Hindi) and the locale's decimal mark.
     */
    public static function number(float|int $value, string $locale, int $decimals = 0): string
    {
        $code = self::normalize($locale) ?? self::default();
        [$group, $decimal, $indian] = self::NUMBERS[$code] ?? [',', '.', false];

        $negative = $value < 0;
        $formatted = number_format(abs((float) $value), $decimals, '.', '');

        $parts = explode('.', $formatted);
        $whole = $parts[0];
        $fraction = $parts[1] ?? '';

        $grouped = $indian ? self::groupIndian($whole, $group) : self::groupWestern($whole, $group);
        $out = $fraction !== '' ? $grouped.$decimal.$fraction : $grouped;

        return ($negative ? '-' : '').$out;
    }

    /** A localized money string, e.g. `TZS 1,234` / `TZS 1.234`. */
    public static function amount(float|int $value, string $locale): string
    {
        return self::currency($locale).' '.self::number($value, $locale);
    }

    /** The date pattern for a locale, in PHP `date()` letters. */
    public static function datePattern(string $locale): string
    {
        $code = self::normalize($locale) ?? self::default();

        return self::DATE_PATTERNS[$code] ?? 'd/m/Y';
    }

    /** Format a date with the locale's pattern (numeric - no invented words). */
    public static function date(DateTimeInterface|int|string $when, string $locale): string
    {
        $code = self::normalize($locale) ?? self::default();

        if ($when instanceof DateTimeInterface) {
            $timestamp = $when->getTimestamp();
        } elseif (is_int($when)) {
            $timestamp = $when;
        } else {
            $timestamp = strtotime($when) ?: time();
        }

        return date(self::datePattern($code), $timestamp);
    }

    /**
     * Group from the right in threes. Built with substr rather than strrev so a
     * multi-byte separator (French uses U+202F) is never byte-reversed.
     */
    private static function groupWestern(string $digits, string $separator): string
    {
        $groups = [];
        while (strlen($digits) > 3) {
            array_unshift($groups, substr($digits, -3));
            $digits = substr($digits, 0, -3);
        }
        array_unshift($groups, $digits);

        return implode($separator, $groups);
    }

    /** Indian grouping: last 3 digits, then pairs (1,23,45,678). */
    private static function groupIndian(string $digits, string $separator): string
    {
        if (strlen($digits) <= 3) {
            return $digits;
        }

        $head = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $groups = [];
        while (strlen($rest) > 2) {
            array_unshift($groups, substr($rest, -2));
            $rest = substr($rest, 0, -2);
        }
        array_unshift($groups, $rest);

        return implode($separator, $groups).$separator.$head;
    }
}
