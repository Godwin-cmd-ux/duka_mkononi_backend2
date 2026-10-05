<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The natural-language reporting endpoint must stay registered and protected.
 */
class AiReportRouteTest extends TestCase
{
    public function test_report_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/report/ask'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/report/ask must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }
}
