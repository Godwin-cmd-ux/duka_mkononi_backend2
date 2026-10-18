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
}