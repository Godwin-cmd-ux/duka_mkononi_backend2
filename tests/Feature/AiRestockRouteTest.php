<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Restock & Stock-Out planner is only reachable through
 * POST /api/ai/restock/list. This fails loudly if the route is dropped or
 * loses its authentication middleware.
 */
class AiRestockRouteTest extends TestCase
{
    public function test_restock_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/restock/list'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/restock/list must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }
}
