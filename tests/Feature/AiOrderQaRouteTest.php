<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Order & Delivery Q&A capability is only reachable through
 * POST /api/ai/orders/ask, and always behind JWT auth so a customer question
 * can only ever be answered from the caller's own orders. This fails loudly
 * if the route is dropped or loses its authentication middleware.
 */
class AiOrderQaRouteTest extends TestCase
{
    public function test_order_qa_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/orders/ask'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/orders/ask must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }
}
