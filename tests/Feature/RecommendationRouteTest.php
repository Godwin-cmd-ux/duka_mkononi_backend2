<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Personalised Recommendations (capability #14) expose exactly three
 * routes: one PUBLIC read (a shopper may see a cold-start feed without an
 * account, so it must NOT sit behind auth.jwt - identity is optional and
 * handled inside the controller) and two authenticated writes (dismissals
 * and feedback are per-account state). This fails loudly if a route is
 * dropped, auth is wrongly required on the read, throttling disappears, or
 * a write leaks into the public surface.
 */
class RecommendationRouteTest extends TestCase
{
    /** @return array<int, string> */
    private function recommendationRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return str_starts_with($route->uri(), 'api/ai/recommendations');
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

    public function test_route_surface_is_exactly_as_documented(): void
    {
        $expected = [
            'get api/ai/recommendations',
            'post api/ai/recommendations/dismiss',
            'post api/ai/recommendations/feedback',
        ];

        $this->assertSame($expected, $this->recommendationRoutes(), 'The recommendations API surface changed unexpectedly.');
    }

    public function test_read_is_public_and_rate_limited_but_writes_require_jwt(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return str_starts_with($route->uri(), 'api/ai/recommendations');
            });

        $this->assertCount(3, $routes);

        foreach ($routes as $route) {
            $middleware = implode(',', $route->gatherMiddleware());

            if ($route->uri() === 'api/ai/recommendations') {
                // Public on purpose: any shopper may browse, account or not.
                $this->assertStringNotContainsString('auth.jwt', $middleware);
                $this->assertStringContainsString('throttle', $middleware);
            } else {
                // Writes are per-account state: identity is mandatory.
                $this->assertStringContainsString('auth.jwt', $middleware);
            }
        }
    }

    public function test_the_recommendations_feature_flag_defaults_to_enabled(): void
    {
        $this->assertTrue((bool) config('ai.features.recommendations', false));
    }

    public function test_ranking_limits_and_retention_have_defaults(): void
    {
        $this->assertGreaterThan(0, (int) config('ai.recommendations.max_products'));
        $this->assertGreaterThan(0, (int) config('ai.recommendations.max_businesses'));
        $this->assertGreaterThan(0, (int) config('ai.recommendations.history_orders'));
        $this->assertGreaterThan(0, (int) config('ai.recommendations.dismiss_ttl_days'));
        $this->assertGreaterThan(0, (int) config('ai.recommendations.dismiss_max'));
    }
}
