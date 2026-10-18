<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the theme system: a dedicated preference column on `users`.
 *
 * Adds `preferred_theme` so an account can remember which of the approved
 * themes its owner selected, mirroring how `language` remembers the preferred
 * locale (AuthController::login).
 *
 *  - Supports exactly the five approved identifiers:
 *      'white-professional' | 'royal-champagne-cream'
 *      | 'midnight-royal-gold' | 'emerald-bazaar' | 'oceanic-sapphire'
 *  - Defaults new users to 'white-professional' (the existing Duka Mkononi
 *    design stays the default experience).
 *  - Existing rows backfill: the column default covers engines that rewrite
 *    existing rows on ADD COLUMN, and the defensive UPDATE below covers the
 *    ones that do not (and clears any value outside the allow-list).
 *  - No unrelated column is touched and no existing user data is lost.
 *  - down() cleanly removes the column again.
 *
 * Run with:  php artisan migrate
 * (see THEME_MIGRATION.md at the project root)
 */
return new class extends Migration
{
    private const DEFAULT_THEME = 'white-professional';

    private const ALLOWED = [
        'white-professional',
        'royal-champagne-cream',
        'midnight-royal-gold',
        'emerald-bazaar',
        'oceanic-sapphire',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('preferred_theme')->default(self::DEFAULT_THEME);
        });

        // Existing accounts: make sure every row ends up on a valid default.
        // (NOT NULL + DEFAULT already rewrites existing rows on most engines;
        // this keeps behaviour identical on engines where it does not, and
        // resets any unexpected value to the default. Only preferred_theme
        // is written — nothing else on the row is modified.)
        DB::table('users')
            ->where(function ($query) {
                $query->whereNull('preferred_theme')
                    ->orWhereNotIn('preferred_theme', self::ALLOWED);
            })
            ->update(['preferred_theme' => self::DEFAULT_THEME]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferred_theme');
        });
    }
};