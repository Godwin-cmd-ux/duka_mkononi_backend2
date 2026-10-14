<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AiAdminAnalyticsController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #20: the natural-language analytics endpoints answer with
 * PLATFORM-WIDE numbers, so they must stay behind authentication, must be wired
 * to the analytics controller only, and must offer no write verb at all - an
 * analytics question can never change a row.
 *
 * The prefix is `ai/platform-analytics` (not `ai/admin-analytics`) on purpose:
 * the advertisement guard in AiAdCopyRouteTest matches any write route whose uri
 * contains "api/ai/ad", so an `ai/admin-analytics` prefix would have collided
 * with a guard that belongs to a different capability. Renaming was chosen over
 * weakening that guard.
 */
class AiAdminAnalyticsRouteTest extends TestCase
{
    private function routeFor(string $uri, string $method)
    {
        return collect(Route::getRoutes()->getRoutes())->first(function ($route) use ($uri, $method) {
            return $route->uri() === $uri && in_array($method, $route->methods(), true);
        });
    }

    public function test_ask_route_is_registered_for_post_behind_auth(): void
    {
        $route = $this->routeFor('api/ai/platform-analytics/ask', 'POST');

        $this->assertNotNull($route, 'POST api/ai/platform-analytics/ask must stay registered.');
        $this->assertStringContainsString('auth.jwt', implode(',', $route->gatherMiddleware()), 'The ask endpoint must require a logged-in user.');
        $this->assertSame(AiAdminAnalyticsController::class.'@ask', $route->getActionName());
    }

    public function test_examples_route_is_registered_for_get_behind_auth(): void
    {
        $route = $this->routeFor('api/ai/platform-analytics/examples', 'GET');

        $this->assertNotNull($route, 'GET api/ai/platform-analytics/examples must stay registered.');
        $this->assertStringContainsString('auth.jwt', implode(',', $route->gatherMiddleware()), 'The examples endpoint must require a logged-in user.');
        $this->assertSame(AiAdminAnalyticsController::class.'@examples', $route->getActionName());
    }

    /**
     * Read-only by construction: the analytics surface exposes no verb that
     * could create, change or delete anything.
     */
    public function test_the_analytics_surface_has_no_write_verb(): void
    {
        $writeVerbs = ['PUT', 'PATCH', 'DELETE'];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_contains($route->uri(), 'ai/platform-analytics')) {
                continue;
            }

            $this->assertSame(
                [],
                array_values(array_intersect($writeVerbs, $route->methods())),
                "{$route->uri()} must not accept a write verb."
            );
        }
    }

    /**
     * The page is a real page: it renders the analytics view, which carries the
     * ask form wired to the capability's own endpoint.
     */
    public function test_the_website_analytics_page_renders_the_analytics_view(): void
    {
        $this->assertNotNull($this->routeFor('system_admin/analytics', 'GET'), 'GET /system_admin/analytics must stay registered.');
        $this->assertTrue(view()->exists('system_admin.analytics'), 'The analytics view must exist.');

        $html = $this->get('/system_admin/analytics')->assertOk()->getContent();

        $this->assertStringContainsString('admin_analytics.title', $html, 'The page must be translated by the shared string catalogue.');
        $this->assertStringContainsString('/api/ai/platform-analytics/ask', $html, 'The page must post to the capability endpoint.');
        $this->assertStringContainsString('/api/ai/platform-analytics/examples', $html, 'The page must load the supported examples.');
    }

    public function test_the_feature_is_enabled_by_default_and_can_be_switched_off(): void
    {
        $this->assertTrue((bool) config('ai.features.admin_analytics'), 'Capability #20 must be on unless explicitly disabled.');
        $this->assertSame(20000, (int) config('ai.admin_analytics.max_rows'), 'The row cap is part of the cost contract.');
        $this->assertSame(366, (int) config('ai.admin_analytics.max_range_days'), 'The longest allowed window is part of the contract.');
        $this->assertLessThanOrEqual(5, (int) config('ai.admin_analytics.max_named_businesses'), 'At most five businesses may be named.');
    }
}
