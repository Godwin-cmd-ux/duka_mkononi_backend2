<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Shopping Assistant (capability #11) exposes exactly two PUBLIC,
 * throttled routes - a shopper must be able to ask without an account, so
 * these must NOT sit behind auth.jwt (identity is optional and handled
 * inside the controller). This fails loudly if a route is dropped, auth is
 * wrongly required, throttling disappears, or a write route appears under
 * api/ai/shopping.
 */
class ShoppingAssistantRouteTest extends TestCase
{
    /** @return array<int, string> */
    private function shoppingRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return str_starts_with($route->uri(), 'api/ai/shopping');
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

    public function test_shopping_route_surface_is_exactly_as_documented(): void
    {
        $expected = [
            'get api/ai/shopping/suggestions',
            'post api/ai/shopping/ask',
        ];

        $this->assertSame($expected, $this->shoppingRoutes(), 'The shopping assistant API surface changed unexpectedly.');
    }

    public function test_routes_are_public_by_design_but_rate_limited(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return str_starts_with($route->uri(), 'api/ai/shopping');
            });

        $this->assertCount(2, $routes);

        foreach ($routes as $route) {
            $middleware = implode(',', $route->gatherMiddleware());

            // Public on purpose: any shopper may ask, account or not.
            $this->assertStringNotContainsString('auth.jwt', $middleware);

            // ...but throttled, like the other public write-ish endpoints.
            $this->assertStringContainsString('throttle', $middleware);
        }
    }

    public function test_the_shopping_feature_flag_defaults_to_enabled(): void
    {
        $this->assertTrue((bool) config('ai.features.shopping_assistant', false));
    }
}
