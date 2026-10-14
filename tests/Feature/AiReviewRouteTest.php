<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #16: the content-review endpoints must stay registered and
 * authenticated - the findings and the decision touch a business's own
 * advertisements and product listings, so neither may be reachable
 * anonymously.
 */
class AiReviewRouteTest extends TestCase
{
    public function test_check_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/reviews/check'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/reviews/check must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware, 'The check must require a logged-in manager.');
    }

    public function test_decision_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/reviews/decision'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/reviews/decision must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware, 'A publish/reject decision must require a logged-in manager.');
    }
}
