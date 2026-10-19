<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Route guards for the monitoring system. Both properties are security
 * controls, not conveniences:
 *  - the ingest endpoint is public (any visitor's page views) but throttled;
 *  - every investigation endpoint sits behind auth.jwt (and each method
 *    re-checks the admin role server-side - see MonitoringAdminAccessTest).
 * A route quietly losing either property would expose the audit surface.
 */
class MonitoringRoutesTest extends TestCase
{
    /** @return array<int, \Illuminate\Routing\Route> */
    private function routesFor(string $uri, string $method): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true))
            ->values()
            ->all();
    }

    public function test_ingest_is_registered_public_and_throttled(): void
    {
        $routes = $this->routesFor('api/monitoring/events', 'POST');
        $this->assertCount(1, $routes, 'POST api/monitoring/events must stay registered exactly once.');

        $middleware = implode(',', $routes[0]->gatherMiddleware());
        $this->assertStringContainsString('throttle', $middleware, 'Ingest must be rate limited.');
        $this->assertStringNotContainsString('auth.jwt', $middleware, 'Anonymous page views must be recordable (actor still derived server-side).');
    }

    public function test_every_admin_monitoring_endpoint_is_behind_auth_jwt(): void
    {
        $endpoints = [
            ['api/admin/monitoring/summary', 'GET'],
            ['api/admin/monitoring/timeline', 'GET'],
            ['api/admin/monitoring/events/{id}', 'GET'],
            ['api/admin/monitoring/users/{userId}/timeline', 'GET'],
            ['api/admin/monitoring/sessions', 'GET'],
            ['api/admin/monitoring/sessions/{sessionRef}', 'GET'],
            ['api/admin/monitoring/alerts', 'GET'],
            ['api/admin/monitoring/alerts/{id}/status', 'POST'],
            ['api/admin/monitoring/export', 'GET'],
            ['api/admin/monitoring/setup', 'POST'],
        ];

        foreach ($endpoints as [$uri, $method]) {
            $routes = $this->routesFor($uri, $method);
            $this->assertCount(1, $routes, "{$method} /{$uri} must stay registered exactly once.");

            $middleware = implode(',', $routes[0]->gatherMiddleware());
            $this->assertStringContainsString(
                'auth.jwt',
                $middleware,
                "/{$uri} exposes audit/security data and must require authentication."
            );
        }
    }

    public function test_the_legacy_logs_endpoints_are_untouched(): void
    {
        // Backward compatibility: existing consumers (manager report page,
        // older clients) depend on these shapes.
        foreach ([['api/admin/logs', 'GET'], ['api/admin/logs/search', 'GET'], ['api/admin/logs/cleanup', 'DELETE']] as [$uri, $method]) {
            $routes = $this->routesFor($uri, $method);
            $this->assertCount(1, $routes, "Legacy {$method} /{$uri} must keep working.");
        }
    }
}
