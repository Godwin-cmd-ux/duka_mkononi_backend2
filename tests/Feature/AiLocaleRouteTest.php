<?php

namespace Tests\Feature;

use App\Services\Ai\Locale;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #22 - Truly Multilingual Assistant, at the HTTP/website seam.
 *
 * What these tests pin:
 *  - every AI route accepts the eight documented locales, and an unsupported
 *    one can never leak through to the response (it falls back, it is not
 *    echoed);
 *  - the website renders in the chosen language AND mirrors itself for Urdu,
 *    using the `dir` attribute on <html> - the browser mechanism that aligns
 *    text, forms, modals and flex rows;
 *  - the language switcher's own CSS and runtime carry the RTL rules;
 *  - the JWT middleware actually surfaces the account language the shared
 *    resolver needs, so the account-locale fallback is wired end to end.
 */
class AiLocaleRouteTest extends TestCase
{
    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    public function test_every_advertised_locale_is_accepted_by_the_public_ai_route(): void
    {
        foreach (self::LOCALES as $locale) {
            $data = $this->postJson('/api/ai/concierge/ask', [
                'locale' => $locale,
                'question' => 'What is Duka Mkononi?',
            ])->assertOk()->json();

            $this->assertSame($locale, $data['locale'], "The AI route must answer in {$locale}.");
        }
    }

    public function test_an_unsupported_locale_falls_back_instead_of_leaking_through(): void
    {
        $data = $this->postJson('/api/ai/concierge/ask', [
            'locale' => 'xx-YY',
            'question' => 'What is Duka Mkononi?',
        ])->assertOk()->json();

        $this->assertContains($data['locale'], Locale::supported(), 'An unknown locale must never be echoed back.');
        $this->assertSame('sw', $data['locale']);
    }

    public function test_a_missing_locale_uses_the_configured_default(): void
    {
        config(['ai.default_locale' => 'de']);

        $data = $this->postJson('/api/ai/concierge/ask', [
            'question' => 'What is Duka Mkononi?',
        ])->assertOk()->json();

        $this->assertSame('de', $data['locale']);
    }

    public function test_the_website_renders_urdu_right_to_left_and_the_rest_left_to_right(): void
    {
        $rtl = $this->withSession(['locale' => 'ur'])->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="ur" dir="rtl"', $rtl, 'Urdu must flip the whole page.');
        $this->assertStringContainsString('data-dm-locale="ur"', $rtl);

        foreach (['sw', 'de', 'zh'] as $locale) {
            $html = $this->withSession(['locale' => $locale])->get('/')->assertOk()->getContent();
            $this->assertStringContainsString(
                '<html lang="'.$locale.'" dir="ltr"',
                $html,
                "{$locale} must stay left-to-right."
            );
        }
    }

    public function test_the_language_widget_carries_the_rtl_rules_and_runtime(): void
    {
        $html = view('partials.dm-lang-widget')->render();

        $this->assertStringContainsString('[dir="rtl"] .dm-lang-widget', $html, 'The selector must mirror in RTL.');
        $this->assertStringContainsString('var RTL = [\'ur\']', $html, 'The runtime must know Urdu is RTL.');
        $this->assertStringContainsString("root.setAttribute('dir'", $html, 'Switching language must re-apply dir.');
    }

    public function test_the_jwt_middleware_surfaces_the_account_language(): void
    {
        $middleware = file_get_contents(base_path('app/Http/Middleware/AuthenticateJwt.php'));

        $this->assertStringContainsString("'language'", $middleware, 'The user lookup must select the account language.');
        $this->assertStringContainsString("jwt_language", $middleware, 'The account language must reach request attributes.');
    }

    public function test_the_language_switcher_route_only_accepts_the_catalogue_locales(): void
    {
        $this->get('/language/ur')->assertOk()->assertJson(['ok' => true, 'locale' => 'ur']);
        $this->get('/language/xx')->assertStatus(422);
    }

    public function test_the_locale_change_did_not_loosen_ai_authorization(): void
    {
        // The public surfaces are a documented, closed set (the visitor
        // shopping/trust/recommendation helpers and the public concierge).
        // Every other AI route must stay behind auth.jwt - a locale change
        // must never have widened that.
        $public = [
            'api/ai/shopping/ask',
            'api/ai/shopping/suggestions',
            'api/ai/concierge/ask',
            'api/ai/concierge/help',
            'api/ai/trust/summary',
            'api/ai/recommendations',
            // Capability #23: the public concierge offers the mic, so its
            // capability contract must be readable without an account.
            'api/ai/speech/capabilities',
            // Capability #24: the shop search box is public.
            'api/ai/search/products',
        ];

        $aiRoutes = collect(Route::getRoutes()->getRoutes())->filter(
            fn ($route) => str_starts_with($route->uri(), 'api/ai/')
        );

        $this->assertNotEmpty($aiRoutes, 'Expected the AI route group to exist.');

        foreach ($aiRoutes as $route) {
            $middleware = implode(',', $route->gatherMiddleware());
            if (in_array($route->uri(), $public, true)) {
                $this->assertStringNotContainsString('auth.jwt', $middleware, "{$route->uri()} is intentionally public.");
            } else {
                $this->assertStringContainsString('auth.jwt', $middleware, "{$route->uri()} must stay behind auth.jwt.");
            }
        }
    }
}
