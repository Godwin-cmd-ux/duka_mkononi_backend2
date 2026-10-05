<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #5 - the weekly digest is only reachable through the health
 * routes, which must stay registered and stay behind authentication.
 */
class AiHealthRouteTest extends TestCase
{
    public function test_weekly_digest_routes_are_registered_behind_auth(): void
    {
        $expected = [
            ['api/ai/reports/weekly', 'POST'],
            ['api/ai/reports/weekly', 'GET'],
            ['api/ai/reports/weekly/history', 'GET'],
            ['api/ai/health/setup', 'POST'],
        ];

        foreach ($expected as [$uri, $method]) {
            $match = collect(Route::getRoutes()->getRoutes())->first(function ($route) use ($uri, $method) {
                return $route->uri() === $uri && in_array($method, $route->methods(), true);
            });

            $this->assertNotNull($match, "{$method} {$uri} must stay registered.");
            $this->assertStringContainsString('auth.jwt', implode(',', $match->gatherMiddleware()));
        }
    }

    public function test_weekly_digest_command_is_registered(): void
    {
        $this->assertArrayHasKey('ai:weekly-digests', Artisan::all());
    }
}
