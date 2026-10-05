<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Pricing & Margin Advisor is only reachable through
 * POST /api/ai/price/suggest. Like the sale-edit guard, this fails loudly if
 * the route is dropped or loses its authentication middleware.
 */
class AiPricingRouteTest extends TestCase
{
    public function test_price_advisor_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/price/suggest'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/price/suggest must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }
}
