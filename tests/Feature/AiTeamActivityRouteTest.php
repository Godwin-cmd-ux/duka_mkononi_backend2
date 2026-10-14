<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #17: the team-activity endpoints must stay registered and
 * authenticated - they expose one business's own staff activity log, so
 * neither may be reachable anonymously.
 */
class AiTeamActivityRouteTest extends TestCase
{
    public function test_digest_route_is_registered_for_get_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/activity/digest'
                && in_array('GET', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'GET api/ai/activity/digest must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware, 'The digest must require a logged-in manager.');
    }

    public function test_history_route_is_registered_for_get_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/activity/history'
                && in_array('GET', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'GET api/ai/activity/history must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware, 'The audit history must require a logged-in manager.');
    }
}
