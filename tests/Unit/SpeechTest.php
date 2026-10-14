<?php

namespace Tests\Unit;

use App\Services\Ai\Locale;
use App\Services\Ai\Speech;
use Tests\TestCase;

/**
 * Capability #23 - Voice Input & Voice Answers.
 *
 * What these tests pin:
 *  - speech needs no credential and never records or uploads audio - the
 *    privacy block is a contract, not a comment;
 *  - coverage is reported HONESTLY: a locale is `verified` only when the shared
 *    allow-list and the speech config agree, and everything else is
 *    `device_dependent` rather than an over-claim;
 *  - the limits and the BCP-47 tag follow the shared Locale service (#22);
 *  - all eight languages carry the same non-empty message keys, and the
 *    brand "Duka Mkononi" survives every translation;
 *  - the three copies of those strings (this service, the website catalogue and
 *    the mobile catalogue) cannot drift apart.
 */
class SpeechTest extends TestCase
{
    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    public function test_the_provider_is_the_platform_and_needs_no_credential(): void
    {
        $this->assertSame('voice', Speech::CAPABILITY);
        $this->assertSame('platform', Speech::provider());

        $caps = Speech::capabilities('sw');
        $this->assertSame('none', $caps['credentials'], 'The platform engines must not require a key.');
    }

    public function test_no_audio_is_recorded_or_sent_to_the_server(): void
    {
        $privacy = Speech::capabilities('en')['privacy'];

        $this->assertFalse($privacy['audio_sent_to_server']);
        $this->assertFalse($privacy['audio_retained']);
        $this->assertFalse(Speech::retainsAudio());
        $this->assertFalse($privacy['continuous_listening'], 'Recognition is one utterance, never continuous.');
        $this->assertTrue($privacy['text_alternative'], 'The keyboard path must always remain.');
    }

    public function test_swahili_speech_to_text_is_verified_and_other_locales_are_reported_honestly(): void
    {
        // The catalogue requires Swahili; it is the first verified entry.
        $this->assertContains('sw', Speech::sttVerifiedLocales());
        $this->assertSame('verified', Speech::sttSupport('sw'));
        $this->assertSame('verified', Speech::sttSupport('en'));

        // Everything not on the verified list must NOT be claimed.
        $this->assertSame('device_dependent', Speech::sttSupport('fr'));
        $this->assertSame('device_dependent', Speech::sttSupport('zh'));
        // A code outside the allow-list can never be "verified".
        $this->assertSame('device_dependent', Speech::sttSupport('xx'));

        // Speech synthesis has broader, verified coverage by default.
        foreach (self::LOCALES as $locale) {
            $this->assertSame('verified', Speech::ttsSupport($locale), "{$locale} TTS coverage should be verified.");
        }
    }

    public function test_an_operator_can_widen_verified_coverage_by_configuration(): void
    {
        config(['ai.speech.stt_verified_locales' => ['sw', 'en', 'fr']]);

        $this->assertSame('verified', Speech::sttSupport('fr'));
        $this->assertSame('device_dependent', Speech::sttSupport('de'));

        // A junk entry is dropped, never treated as supported.
        config(['ai.speech.stt_verified_locales' => ['sw', 'klingon', 'de']]);
        $this->assertSame(['sw', 'de'], Speech::sttVerifiedLocales());
    }

    public function test_limits_and_tag_follow_the_shared_locale_service(): void
    {
        config(['ai.speech.max_seconds' => 20, 'ai.speech.max_chars' => 400]);

        $caps = Speech::capabilities('ur');
        $this->assertSame(20, $caps['limits']['max_seconds']);
        $this->assertSame(400, $caps['limits']['max_chars']);
        $this->assertSame(20, $caps['stt']['max_seconds']);
        $this->assertSame(400, $caps['tts']['max_chars']);

        // #22 owns the tag and the direction.
        $this->assertSame(Locale::tag('ur'), $caps['tag']);
        $this->assertSame('rtl', $caps['direction']);
        $this->assertSame('ltr', Speech::capabilities('de')['direction']);
        $this->assertSame('single_utterance', $caps['stt']['mode']);
    }

    public function test_the_feature_flag_switches_the_whole_capability_off(): void
    {
        $this->assertTrue(Speech::isEnabled());

        config(['ai.features.voice' => false]);
        $this->assertFalse(Speech::isEnabled());
        $this->assertFalse(Speech::capabilities('sw')['enabled']);

        config(['ai.features.voice' => true, 'ai.speech.enabled' => false]);
        $this->assertFalse(Speech::isEnabled());
    }

    public function test_every_language_carries_the_same_non_empty_messages(): void
    {
        $this->assertTrue(Speech::messagesComplete());

        $reference = array_keys(Speech::allMessages('en'));
        sort($reference);
        $this->assertNotEmpty($reference);

        foreach (self::LOCALES as $locale) {
            $messages = Speech::allMessages($locale);
            $keys = array_keys($messages);
            sort($keys);
            $this->assertSame($reference, $keys, "voice/{$locale} must have the same keys.");

            foreach ($messages as $key => $value) {
                $this->assertNotSame('', trim($value), "voice/{$locale}.{$key} must not be empty.");
            }
        }

        // The brand is never translated.
        foreach (self::LOCALES as $locale) {
            $this->assertStringContainsString(
                'Duka Mkononi',
                Speech::msg('privacy', $locale),
                "voice/{$locale}.privacy must keep the brand literal."
            );
        }
    }

    public function test_msg_substitutes_params_and_falls_back_safely(): void
    {
        $this->assertSame('Voice', Speech::msg('title', 'en'));
        $this->assertSame('Sauti', Speech::msg('title', 'sw'));
        $this->assertSame('no-such-key', Speech::msg('no-such-key', 'en'));
        // An unknown locale falls back to English rather than throwing.
        $this->assertSame('Voice', Speech::msg('title', 'xx'));
    }

    /**
     * The microphone strings live in three places (this service, locales.json
     * for the website, and the mobile catalogues). This is the guard that keeps
     * them identical - a drift would silently show raw keys or the wrong
     * language in the UI.
     */
    public function test_the_service_and_both_catalogues_agree_on_every_string(): void
    {
        $web = json_decode((string) file_get_contents(base_path('locales.json')), true);
        $this->assertIsArray($web, 'locales.json must be valid JSON.');

        foreach (self::LOCALES as $locale) {
            $expected = Speech::allMessages($locale);

            $fromWeb = (array) ($web['translations'][$locale]['voice'] ?? []);
            $this->assertSame($expected, $fromWeb, "locales.json voice/{$locale} must match the service.");

            $mobileFile = base_path("Duka_mkononi/locales/{$locale}.json");
            $this->assertFileExists($mobileFile);
            $fromMobile = (array) (json_decode((string) file_get_contents($mobileFile), true)['voice'] ?? []);
            $this->assertSame($expected, $fromMobile, "mobile voice/{$locale} must match the service.");
        }
    }

    public function test_the_script_that_writes_the_catalogues_exists(): void
    {
        $this->assertFileExists(base_path('Duka_mkononi/scripts/add-voice-locales.js'));
    }
}
