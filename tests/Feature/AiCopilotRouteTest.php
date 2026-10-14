<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Seller Copilot (capability #10) exposes an exact, tiny API surface:
 * one POST that runs the read-only tools, and conversation list/read/delete
 * endpoints. This fails loudly if a route is dropped, if auth is lost, or if
 * an extra write route appears under api/ai/copilot.
 */
class AiCopilotRouteTest extends TestCase
{
    /** @return array<int, string> */
    private function copilotRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return str_starts_with($route->uri(), 'api/ai/copilot');
            })
            ->map(function ($route) {
                $methods = array_values(array_filter($route->methods(), function ($method) {
                    return $method !== 'HEAD';
                }));

                return strtolower(implode('|', $methods)).' '.$route->uri();
            })
            ->sort()
            ->values()
            ->all();
    }

    public function test_copilot_route_surface_is_exactly_as_documented(): void
    {
        $expected = [
            'delete api/ai/copilot/conversations',
            'delete api/ai/copilot/conversations/{conversationId}',
            'get api/ai/copilot/conversations',
            'get api/ai/copilot/conversations/{conversationId}',
            'post api/ai/copilot/chat',
        ];

        $this->assertSame($expected, $this->copilotRoutes(), 'The copilot API surface changed unexpectedly.');
    }

    public function test_every_copilot_route_is_behind_auth_jwt(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return str_starts_with($route->uri(), 'api/ai/copilot');
            });

        $this->assertCount(5, $routes);

        foreach ($routes as $route) {
            $middleware = implode(',', $route->gatherMiddleware());
            $this->assertStringContainsString(
                'auth.jwt',
                $middleware,
                $route->methods()[0].' '.$route->uri().' must require authentication.'
            );
        }
    }

    public function test_conversation_deletion_never_targets_another_path_style(): void
    {
        // Deletion is scoped in the controller by business_id AND user_id;
        // the routes must stay collection-style (no model binding surprises).
        $deleteUris = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/ai/copilot') && in_array('DELETE', $route->methods(), true)) {
                $deleteUris[] = $route->uri();
            }
        }

        sort($deleteUris);
        $this->assertSame([
            'api/ai/copilot/conversations',
            'api/ai/copilot/conversations/{conversationId}',
        ], $deleteUris);
    }
}
