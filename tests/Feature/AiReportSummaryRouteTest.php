<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #15: the report summary endpoint must stay registered and
 * authenticated - a summary of a business's books (revenue, profit,
 * expenses) must never be reachable anonymously.
 */
class AiReportSummaryRouteTest extends TestCase
{
    public function test_summary_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/reports/summary'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/reports/summary must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware, 'The summary must require a logged-in manager.');
    }
}
