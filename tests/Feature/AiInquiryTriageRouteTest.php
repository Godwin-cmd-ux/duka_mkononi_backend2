<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #18: the inquiry-triage endpoints expose customer contact details,
 * the triage decision and the reply-sending action, so every one of them must
 * stay behind authentication (and the controller itself restricts them to the
 * Super Admin trust boundary the existing inquiry endpoints use).
 */
class AiInquiryTriageRouteTest extends TestCase
{
    private function assertRouteIsRegistered(string $uri, string $method, string $message): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) use ($uri, $method) {
            return $route->uri() === $uri && in_array($method, $route->methods(), true);
        });

        $this->assertCount(1, $matches, $message);

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware, 'This endpoint must require a logged-in Super Admin.');
    }

    public function test_queue_route_is_registered_for_get_behind_auth(): void
    {
        $this->assertRouteIsRegistered('api/ai/inquiries/queue', 'GET', 'GET api/ai/inquiries/queue must stay registered.');
    }

    public function test_triage_route_is_registered_for_post_behind_auth(): void
    {
        $this->assertRouteIsRegistered('api/ai/inquiries/{id}/triage', 'POST', 'POST api/ai/inquiries/{id}/triage must stay registered.');
    }

    public function test_reply_route_is_registered_for_post_behind_auth(): void
    {
        $this->assertRouteIsRegistered('api/ai/inquiries/{id}/reply', 'POST', 'POST api/ai/inquiries/{id}/reply must stay registered.');
    }
}
