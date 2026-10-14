<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Anomaly & Leak Detection capability is reachable only through
 * GET /api/ai/anomalies (the scan) and POST /api/ai/anomalies/dismiss (the
 * audited decision). These fail loudly if either route is dropped or loses
 * its authentication middleware.
 */
class AiAnomalyRouteTest extends TestCase
{
    public function test_anomaly_scan_route_is_registered_for_get_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/anomalies'
                && in_array('GET', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'GET api/ai/anomalies must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }

    public function test_anomaly_dismiss_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/anomalies/dismiss'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/anomalies/dismiss must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }
}
