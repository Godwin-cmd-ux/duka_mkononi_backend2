<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The ad-copy studio is only reachable through POST /api/ai/ads/generate.
 * This fails loudly if the route is dropped or loses its authentication
 * middleware. Publishing itself must stay with the existing matangazo
 * workflow, so no other ad write path may appear here.
 */
class AiAdCopyRouteTest extends TestCase
{
    public function test_generate_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/ads/generate'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/ads/generate must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }

    public function test_no_ai_ad_write_path_exists(): void
    {
        // Capability #8 must stay read-only: advertisements are only ever
        // created through the pre-existing matangazo workflow.
        $aiWriteRoutes = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            $isWrite = collect($route->methods())->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])->isNotEmpty();

            return $isWrite && str_contains($route->uri(), 'api/ai/ad');
        });

        $this->assertCount(1, $aiWriteRoutes, 'Only POST api/ai/ads/generate may exist under api/ai/ads.');
        $this->assertSame('api/ai/ads/generate', $aiWriteRoutes->first()->uri());
    }
}
