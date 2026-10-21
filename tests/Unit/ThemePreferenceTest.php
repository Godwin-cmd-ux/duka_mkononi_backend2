<?php

namespace Tests\Unit;

use App\Services\ThemePreference;
use PHPUnit\Framework\TestCase;

class ThemePreferenceTest extends TestCase
{
    /**
     * A choice made in the picker just before signing in must survive the
     * login, for every theme — this is the regression that made a picked dark
     * theme snap back to the account's older (default) preference. Without the
     * fix the stored value won and the visitor was sent back to White
     * Professional after the redirect.
     */
    public function test_explicit_choice_beats_the_stored_preference_for_every_theme(): void
    {
        $allowed = ThemePreference::ALLOWED;

        foreach ($allowed as $index => $theme) {
            // Always a DIFFERENT stored value, so the write is observable.
            $stored = $allowed[($index + 1) % count($allowed)];
            $resolved = ThemePreference::resolve($stored, $theme, true);

            $this->assertSame($theme, $resolved['theme'], "explicit {$theme} must win over the stored {$stored}");
            $this->assertTrue($resolved['persist'], "explicit {$theme} must be written to the account");
        }
    }

    public function test_explicit_choice_identical_to_the_stored_value_needs_no_write(): void
    {
        foreach (ThemePreference::ALLOWED as $theme) {
            $resolved = ThemePreference::resolve($theme, $theme, true);

            $this->assertSame($theme, $resolved['theme']);
            $this->assertFalse($resolved['persist'], 'nothing changed, so the account row is left alone');
        }
    }

    /**
     * A value merely restored from storage (no fresh pick) must not overwrite
     * the account: that is how a theme chosen on the phone reaches this
     * browser instead of being replaced by this device's last session.
     */
    public function test_account_preference_wins_when_the_client_did_not_pick(): void
    {
        $resolved = ThemePreference::resolve('midnight-royal-gold', 'white-professional', false);

        $this->assertSame('midnight-royal-gold', $resolved['theme']);
        $this->assertFalse($resolved['persist']);
    }

    public function test_browser_value_is_adopted_when_the_account_has_none(): void
    {
        $resolved = ThemePreference::resolve(null, 'oceanic-sapphire', false);

        $this->assertSame('oceanic-sapphire', $resolved['theme']);
        $this->assertTrue($resolved['persist']);
    }

    public function test_invalid_stored_value_falls_back_to_the_browser_choice(): void
    {
        $resolved = ThemePreference::resolve('neon-unicorn', 'emerald-bazaar', false);

        $this->assertSame('emerald-bazaar', $resolved['theme']);
        $this->assertTrue($resolved['persist']);
    }

    public function test_unapproved_identifiers_are_never_returned(): void
    {
        $resolved = ThemePreference::resolve('neon-unicorn', '"><script>alert(1)</script>', true);

        $this->assertSame(ThemePreference::DEFAULT, $resolved['theme']);
        $this->assertFalse($resolved['persist']);
    }

    public function test_default_is_reported_but_not_written_when_nothing_is_known(): void
    {
        $resolved = ThemePreference::resolve(null, null, false);

        $this->assertSame(ThemePreference::DEFAULT, $resolved['theme']);
        $this->assertFalse($resolved['persist']);
    }
}
