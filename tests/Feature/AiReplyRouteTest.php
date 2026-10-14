<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The reply drafter is only reachable through POST /api/ai/replies/draft.
 * This fails loudly if the route is dropped or loses its authentication
 * middleware. Sending a note to the customer must stay with the existing
 * seller order workflow, so no other AI write path may appear here.
 */
class AiReplyRouteTest extends TestCase
{
    public function test_draft_route_is_registered_for_post_behind_auth(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/replies/draft'
                && in_array('POST', $route->methods(), true);
        });

        $this->assertCount(1, $matches, 'POST api/ai/replies/draft must stay registered.');

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware);
    }

    public function test_no_ai_reply_write_path_exists(): void
    {
        // Capability #9 must stay read-only: replies only reach the customer
        // through the pre-existing seller order status/notes endpoints.
        $aiWriteRoutes = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            $isWrite = collect($route->methods())->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])->isNotEmpty();

            return $isWrite && str_contains($route->uri(), 'api/ai/replies');
        });

        $this->assertCount(1, $aiWriteRoutes, 'Only POST api/ai/replies/draft may exist under api/ai/replies.');
        $this->assertSame('api/ai/replies/draft', $aiWriteRoutes->first()->uri());
    }
}
