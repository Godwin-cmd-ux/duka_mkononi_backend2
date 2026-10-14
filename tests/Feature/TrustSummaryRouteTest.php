<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Business & Review Summariser (capability #12) exposes exactly ONE
 * PUBLIC, throttled route - any customer must be able to read a trust
 * summary without an account, so it must NOT sit behind auth.jwt. This fails
 * loudly if the route is dropped, auth is wrongly required, throttling
 * disappears, or a write route appears under api/ai/trust.
 */
class TrustSummaryRouteTest extends TestCase
{
    /** @return array<int, string> */
    private function trustRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return str_starts_with($route->uri(), 'api/ai/trust');
            })
            ->map(function ($route) {
                $methods = array_values(array_filter($route->methods(), function ($method) {
                    return $method !== 'HEAD';
                }));

                return strtolower(implode('|', $methods)).' '.$route->uri();
            })
            ->sort()
            ->values()
            ->all();
    }

    public function test_trust_route_surface_is_exactly_as_documented(): void
    {
        $this->assertSame(
            ['get api/ai/trust/summary'],
            $this->trustRoutes(),
            'The trust summariser API surface changed unexpectedly.'
        );
    }

    public function test_route_is_public_by_design_but_rate_limited(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return str_starts_with($route->uri(), 'api/ai/trust');
            });

        $this->assertCount(1, $routes);

        $middleware = implode(',', $routes->first()->gatherMiddleware());

        // Public on purpose: a shopper reads trust information without an account.
        $this->assertStringNotContainsString('auth.jwt', $middleware);

        // ...but throttled, like the other public AI endpoints.
        $this->assertStringContainsString('throttle', $middleware);
    }

    public function test_the_business_summary_feature_flag_defaults_to_enabled(): void
    {
        $this->assertTrue((bool) config('ai.features.business_summary', false));
    }

    public function test_trust_cache_defaults_are_sane(): void
    {
        $trust = config('ai.trust');

        $this->assertIsArray($trust);
        $this->assertGreaterThanOrEqual(1, $trust['cache_minutes'], 'Summaries must expire; zero/negative TTL is forbidden.');
        $this->assertGreaterThanOrEqual(10, $trust['max_reviews']);
        $this->assertGreaterThanOrEqual(2, $trust['theme_min_reviews'], 'A theme needs at least two reviews to be recurring.');
        $this->assertGreaterThanOrEqual(1, $trust['theme_limit']);
    }
}
