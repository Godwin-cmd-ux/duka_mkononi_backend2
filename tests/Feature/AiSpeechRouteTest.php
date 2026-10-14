<?php

namespace Tests\Feature;

use App\Services\Ai\Locale;
use App\Services\Ai\Speech;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #23 - Voice Input & Voice Answers, at the HTTP/website seam.
 *
 * What these tests pin:
 *  - the capability endpoint is public (the public concierge is where the mic
 *    is offered) but throttled, exactly like the other public AI surfaces;
 *  - it never echoes a locale outside the allow-list;
 *  - every locale gets its own translated strings;
 *  - the privacy facts survive the round trip;
 *  - switching the feature off is a graceful 200, not a broken page;
 *  - the website actually ships the voice module and mounts the mic on the
 *    concierge widget's input.
 */
class AiSpeechRouteTest extends TestCase
{
    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    public function test_the_capability_endpoint_is_public_and_throttled(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(
            fn ($route) => $route->uri() === 'api/ai/speech/capabilities' && in_array('GET', $route->methods(), true)
        );

        $this->assertCount(1, $routes, 'api/ai/speech/capabilities must stay registered exactly once.');

        $middleware = implode(',', $routes->first()->gatherMiddleware());
        $this->assertStringNotContainsString('auth.jwt', $middleware, 'The public concierge needs voice without an account.');
        $this->assertStringContainsString('throttle', $middleware);
    }

    public function test_it_returns_the_documented_capability_contract(): void
    {
        $data = $this->getJson('/api/ai/speech/capabilities?locale=en')->assertOk()->json();

        $this->assertTrue($data['success']);
        $this->assertSame('voice', $data['capability']);
        $this->assertTrue($data['enabled']);
        $this->assertSame('platform', $data['provider']);
        $this->assertSame('none', $data['credentials'], 'No speech credential may ever be required or exposed.');
        $this->assertSame('en', $data['locale']);
        $this->assertSame('ltr', $data['direction']);
        $this->assertSame('en-TZ', $data['tag']);

        $this->assertSame('verified', $data['stt']['support']);
        $this->assertSame('single_utterance', $data['stt']['mode']);
        $this->assertNotEmpty($data['limits']['max_seconds']);
        $this->assertNotEmpty($data['limits']['max_chars']);

        $this->assertFalse($data['privacy']['audio_sent_to_server']);
        $this->assertFalse($data['privacy']['audio_retained']);
        $this->assertFalse($data['privacy']['continuous_listening']);
        $this->assertTrue($data['privacy']['text_alternative']);

        $this->assertSame(Speech::allMessages('en'), $data['messages']);
    }

    public function test_every_locale_is_accepted_and_answered_in_its_own_strings(): void
    {
        $seen = [];

        foreach (self::LOCALES as $locale) {
            $data = $this->getJson('/api/ai/speech/capabilities?locale='.$locale)->assertOk()->json();

            $this->assertSame($locale, $data['locale'], "The endpoint must answer in {$locale}.");
            $this->assertSame(Locale::direction($locale), $data['direction']);
            $this->assertSame(Locale::tag($locale), $data['tag']);
            $this->assertNotSame('', trim((string) $data['messages']['listen']));
            $this->assertSame(Speech::msg('listen', $locale), $data['messages']['listen']);

            $seen[] = $data['messages']['listen'];
        }

        // Not a single language may silently fall back to Swahili/English.
        $this->assertGreaterThan(4, count(array_unique($seen)), 'The strings must actually be translated per language.');
    }

    public function test_an_unsupported_locale_falls_back_instead_of_leaking(): void
    {
        $data = $this->getJson('/api/ai/speech/capabilities?locale=xx-YY')->assertOk()->json();

        $this->assertContains($data['locale'], Locale::supported());
        $this->assertSame('sw', $data['locale']);

        // The response is about the RESOLVED locale, whose own coverage is
        // reported truthfully (Swahili is the verified default)...
        $this->assertSame(Speech::sttSupport('sw'), $data['stt']['support']);
        // ...and the unknown code itself is never claimed as supported.
        $this->assertSame('device_dependent', Speech::sttSupport('xx-YY'));
        $this->assertNotContains('xx-YY', $data['stt']['verified_locales']);
    }

    public function test_urdu_is_reported_right_to_left(): void
    {
        $data = $this->getJson('/api/ai/speech/capabilities?locale=ur')->assertOk()->json();

        $this->assertSame('rtl', $data['direction']);
        $this->assertSame('ur-PK', $data['tag']);
    }

    public function test_a_locale_without_verified_stt_is_not_advertised_as_supported(): void
    {
        // French is not in the verified recognition list by default.
        $data = $this->getJson('/api/ai/speech/capabilities?locale=fr')->assertOk()->json();

        $this->assertSame('device_dependent', $data['stt']['support']);
        $this->assertContains('sw', $data['stt']['verified_locales']);
        $this->assertNotContains('fr', $data['stt']['verified_locales']);
    }

    public function test_turning_the_feature_off_is_graceful_not_an_error(): void
    {
        config(['ai.features.voice' => false]);

        $data = $this->getJson('/api/ai/speech/capabilities?locale=en')->assertOk()->json();

        $this->assertFalse($data['enabled']);
        $this->assertSame('FEATURE_DISABLED', $data['code']);
        $this->assertNotSame('', trim((string) $data['error']));
        // The keyboard path is still declared, so the UI cannot lose it.
        $this->assertTrue($data['privacy']['text_alternative']);
    }

    public function test_the_website_ships_the_voice_module_and_mounts_the_mic(): void
    {
        foreach (['/', '/shop', '/businesses', '/advertisements'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('/api/ai/speech/capabilities', $html, "{$path} must load the voice module.");
            $this->assertStringContainsString('DM.voice', $html, "{$path} must expose DM.voice.");
            $this->assertStringContainsString('dmcMic', $html, "{$path} must mount the microphone on the concierge input.");
            $this->assertStringContainsString('dmcVoice', $html, "{$path} must have somewhere to show a voice message.");
        }
    }

    public function test_the_microphone_is_never_shown_before_it_is_proven(): void
    {
        // The button starts hidden and is only revealed after the capability
        // endpoint confirms the device has a recognizer - so a device without
        // one never shows a dead control.
        $html = view('partials.public-concierge')->render();

        $this->assertMatchesRegularExpression(
            '/id="dmcMic"[^>]*hidden/',
            $html,
            'The mic must start hidden.'
        );
    }
}
