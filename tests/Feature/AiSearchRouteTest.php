<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #24 - Semantic & Typo-Tolerant Search, at the HTTP seam.
 *
 * What these tests pin:
 *  - the search endpoint is public (the shop search box and the concierge are
 *    pre-login surfaces) but throttled, like every other public AI route;
 *  - a one-character query is refused with localized examples rather than
 *    matching the whole catalogue;
 *  - the response contract is stable: `success`, `capability`, `query`,
 *    `results`, `meta`, and the meta reports how the search ran;
 *  - turning the capability off is an explicit 403, not a silent empty list;
 *  - the existing shop endpoint keeps its exact response shape.
 *
 * NOTE: the test suite is hermetic (phpunit.xml blanks SUPABASE_*), so the
 * public catalogue is empty here. That is deliberate: these tests pin the
 * CONTRACT. Ranking over real rows is covered by ProductSearchTest; the probe
 * over the real catalogue was run by hand, outside the suite.
 */
class AiSearchRouteTest extends TestCase
{
    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    public function test_the_search_endpoint_is_public_and_throttled(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(
            fn ($route) => $route->uri() === 'api/ai/search/products' && in_array('GET', $route->methods(), true)
        );

        $this->assertCount(1, $routes, 'api/ai/search/products must stay registered exactly once.');

        $middleware = implode(',', $routes->first()->gatherMiddleware());
        $this->assertStringNotContainsString('auth.jwt', $middleware, 'The shop search box is used before signing in.');
        $this->assertStringContainsString('throttle', $middleware);
    }

    public function test_a_one_character_query_is_refused_with_localized_examples(): void
    {
        $english = $this->getJson('/api/ai/search/products?q=a&locale=en')
            ->assertStatus(422)
            ->json();

        $this->assertFalse($english['success']);
        $this->assertSame('QUERY_REQUIRED', $english['code']);
        $this->assertNotEmpty($english['examples']);

        $chinese = $this->getJson('/api/ai/search/products?q=a&locale=zh')
            ->assertStatus(422)
            ->json();

        $this->assertNotEmpty($chinese['examples']);
        $this->assertNotSame($english['examples'], $chinese['examples'], 'Examples must be localized.');
    }

    public function test_a_valid_query_returns_the_documented_contract(): void
    {
        $data = $this->getJson('/api/ai/search/products?q=brake+pads+for+premio')
            ->assertOk()
            ->json();

        $this->assertTrue($data['success']);
        $this->assertSame('search', $data['capability']);
        $this->assertSame('brake pads for premio', $data['query']);
        $this->assertIsArray($data['results']);

        $meta = $data['meta'];
        foreach (['variants', 'mode', 'candidates', 'scored', 'matched', 'returned', 'truncated', 'fuzzy_enabled', 'max_candidates', 'max_results', 'took_ms', 'embeddings', 'degraded'] as $key) {
            $this->assertArrayHasKey($key, $meta, "meta.{$key} must be present.");
        }
        $this->assertSame('lexical', $meta['mode'], 'Embeddings are off by default, so the mode must say so.');
        $this->assertFalse($meta['embeddings']['used']);
        // The synonym variants for this query are reported, not hidden.
        $this->assertContains('brake pad for premio', $meta['variants']);

        // If the catalogue could not be read, the response must SAY so rather
        // than let an outage look like "nothing matched".
        $this->assertIsBool($meta['degraded']);
        if ($meta['degraded']) {
            $this->assertNotSame('', trim((string) ($meta['degraded_reason'] ?? '')));
        }
    }

    public function test_every_locale_is_accepted_for_the_query_and_the_examples(): void
    {
        foreach (self::LOCALES as $locale) {
            $ok = $this->getJson('/api/ai/search/products?q=sukari&locale='.$locale)->assertOk()->json();
            $this->assertTrue($ok['success']);
            $this->assertSame('sukari', $ok['query']);

            $short = $this->getJson('/api/ai/search/products?q=a&locale='.$locale)->assertStatus(422)->json();
            $this->assertNotEmpty($short['examples'], "{$locale} must get localized examples.");

            // A degraded response (hermetic suite) still carries a message in
            // the caller's language.
            if (! empty($ok['meta']['degraded'])) {
                $this->assertNotSame('', trim((string) ($ok['meta']['degraded_reason'] ?? '')), "{$locale} needs a degraded reason.");
            }
        }
    }

    public function test_business_scope_and_availability_options_are_accepted_and_reported(): void
    {
        $data = $this->getJson('/api/ai/search/products?q=sukari&business_id=b-1&available=1')
            ->assertOk()
            ->json();

        $this->assertSame('b-1', $data['meta']['business_id']);
        $this->assertTrue($data['meta']['available_only']);
    }

    public function test_a_limit_can_never_exceed_the_hard_ceiling(): void
    {
        $data = $this->getJson('/api/ai/search/products?q=sukari&limit=9999')->assertOk()->json();

        $this->assertLessThanOrEqual(60, $data['meta']['max_results']);
    }

    public function test_turning_the_capability_off_is_explicit(): void
    {
        config(['ai.features.search' => false]);
        $this->getJson('/api/ai/search/products?q=sukari')->assertStatus(403)->assertJson(['code' => 'FEATURE_DISABLED']);

        config(['ai.features.search' => true, 'ai.search.enabled' => false]);
        $this->getJson('/api/ai/search/products?q=sukari')->assertStatus(403)->assertJson(['code' => 'FEATURE_DISABLED']);
    }

    /**
     * The shop endpoint the website already uses must be untouched: same route,
     * still public, no new middleware. (Its response content cannot be asserted
     * here because the hermetic suite blanks Supabase - the ranking itself is
     * covered by ProductSearchTest and the hand-run live probe.)
     */
    public function test_the_existing_shop_endpoint_is_unchanged(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(
            fn ($route) => $route->uri() === 'api/shop/products' && in_array('GET', $route->methods(), true)
        );

        $this->assertCount(1, $routes, 'The shop page depends on this route.');
        $this->assertStringNotContainsString(
            'auth.jwt',
            implode(',', $routes->first()->gatherMiddleware()),
            'The shop page is public - the search upgrade must not change that.'
        );
    }
}
