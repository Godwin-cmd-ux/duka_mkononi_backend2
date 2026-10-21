<?php

namespace App\Services;

/**
 * Server-side validation for the website/app theme preference.
 *
 * Five themes are approved (Phase 1 approved the first two; the public-
 * website phase added Midnight Royal Gold, Emerald Bazaar and Oceanic
 * Sapphire); the identifiers are stable, untranslated strings and mirror:
 *   - the allow-list in public/js/dm-theme.js
 *   - the migration default in 2026_10_17_000001_add_preferred_theme_to_users_table.php
 *   - the <html data-theme="..."> values in public/css/themes.css
 *
 * Works the same way AuthController::normalizeLanguage guards the language
 * allow-list: anything outside the list returns null and callers keep the
 * already-stored value instead, so raw request/storage data never reaches
 * the stylesheet or the database.
 */
class ThemePreference
{
    /**
     * The default theme for every account that has not chosen otherwise —
     * the original Duka Mkononi design ("White Professional").
     */
    public const DEFAULT = 'white-professional';

    /**
     * The approved theme identifiers. Never translate or rename these;
     * display names live in locales.json / locales.json `ui.*` keys.
     */
    public const ALLOWED = [
        'white-professional',
        'royal-champagne-cream',
        'midnight-royal-gold',
        'emerald-bazaar',
        'oceanic-sapphire',
    ];

    /**
     * Returns the incoming theme when it is one of the approved
     * identifiers, otherwise null.
     */
    public static function normalize($theme): ?string
    {
        if (!is_string($theme)) {
            return null;
        }

        $theme = strtolower(trim($theme));

        return in_array($theme, self::ALLOWED, true) ? $theme : null;
    }

    /**
     * Resolves which theme a login answers with (and whether the account row
     * needs writing). One rule for every theme, web and mobile:
     *
     *   1. A theme the client marks as explicit — the visitor just used the
     *      picker — is the freshest expression of intent, so it wins over the
     *      stored value. Without this, picking a dark theme on the login page
     *      and then signing in would snap back to the account's older choice.
     *   2. Otherwise the account's stored preference wins, which is what makes
     *      a theme picked on the phone apply in this browser.
     *   3. When the account has no valid preference yet, the client's value is
     *      adopted instead.
     *   4. Otherwise White Professional — reported but not written, so a later
     *      explicit choice can still be adopted.
     *
     * @param  mixed  $stored    the account's preferred_theme column
     * @param  mixed  $browser   the theme the client sent with the login
     * @param  bool   $explicit  true when the client says it was just picked
     * @return array{theme: string, persist: bool}
     */
    public static function resolve($stored, $browser, bool $explicit = false): array
    {
        $storedTheme = self::normalize($stored);
        $browserTheme = self::normalize($browser);

        if ($browserTheme !== null && ($explicit || $storedTheme === null)) {
            return [
                'theme' => $browserTheme,
                'persist' => $browserTheme !== $storedTheme,
            ];
        }

        return [
            'theme' => $storedTheme ?? self::DEFAULT,
            'persist' => false,
        ];
    }
}