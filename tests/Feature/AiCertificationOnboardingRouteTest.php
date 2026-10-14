<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Capability #19: the certification endpoints expose a business's own member
 * records and checklist, and the onboarding endpoints read the caller's account,
 * so every one of them must stay behind authentication (the certification
 * controller additionally restricts itself to the Super Admin trust boundary the
 * existing certification endpoint uses).
 */
class AiCertificationOnboardingRouteTest extends TestCase
{
    private function assertRouteIsRegistered(string $uri, string $method, string $message): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(function ($route) use ($uri, $method) {
            return $route->uri() === $uri && in_array($method, $route->methods(), true);
        });

        $this->assertCount(1, $matches, $message);

        $middleware = implode(',', $matches->first()->gatherMiddleware());
        $this->assertStringContainsString('auth.jwt', $middleware, 'This endpoint must require a logged-in user.');
    }

    public function test_certification_queue_route_is_registered_for_get_behind_auth(): void
    {
        $this->assertRouteIsRegistered(
            'api/ai/certification/queue',
            'GET',
            'GET api/ai/certification/queue must stay registered.'
        );
    }

    public function test_certification_review_route_is_registered_for_post_behind_auth(): void
    {
        $this->assertRouteIsRegistered(
            'api/ai/certification/{id}/review',
            'POST',
            'POST api/ai/certification/{id}/review must stay registered.'
        );
    }

    public function test_onboarding_guide_route_is_registered_for_get_behind_auth(): void
    {
        $this->assertRouteIsRegistered(
            'api/ai/onboarding/guide',
            'GET',
            'GET api/ai/onboarding/guide must stay registered.'
        );
    }

    public function test_onboarding_ask_route_is_registered_for_post_behind_auth(): void
    {
        $this->assertRouteIsRegistered(
            'api/ai/onboarding/ask',
            'POST',
            'POST api/ai/onboarding/ask must stay registered.'
        );
    }

    /**
     * The certification review must NOT be wired to the existing certify action:
     * the human decision stays on the system-admin endpoint.
     */
    public function test_the_review_route_does_not_replace_the_human_certify_route(): void
    {
        $certify = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/system-admin/businesses/{id}/certify'
                && in_array('PUT', $route->methods(), true);
        });

        $this->assertCount(1, $certify, 'The human certification decision must stay on the existing endpoint.');

        $review = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return $route->uri() === 'api/ai/certification/{id}/review';
        })->first();

        $this->assertNotNull($review);
        $this->assertNotSame('certify', $review->getActionMethod(), 'The review endpoint must never be the certify action.');
    }

    /**
     * A regression guard for a bug that shipped and was caught live: this data
     * layer returns `App\Services\SupabaseRow`, NOT the model class, so a lookup
     * helper declared `: ?Business` / `: ?Inquiry` raises a TypeError that its own
     * catch block swallows into "not found" - making EVERY review/triage of a
     * real row return 404 while a malformed id still looks correct.
     *
     * The helpers must therefore hand back an ARRAY (as the onboarding
     * controller's `businessRow()` already did). #18's helper is pinned here too
     * because it carried the same defect.
     */
    public function test_lookup_helpers_return_arrays_not_model_instances(): void
    {
        $helpers = [
            [\App\Http\Controllers\Api\AiCertificationController::class, 'findBusiness'],
            [\App\Http\Controllers\Api\AiInquiryTriageController::class, 'findInquiry'],
            [\App\Http\Controllers\Api\AiOnboardingController::class, 'businessRow'],
        ];

        foreach ($helpers as [$class, $method]) {
            $reflection = new \ReflectionMethod($class, $method);
            $type = $reflection->getReturnType();

            $this->assertInstanceOf(\ReflectionNamedType::class, $type, $class.'::'.$method.'() must declare an array return type.');
            $this->assertTrue($type->allowsNull(), $class.'::'.$method.'() must report a missing row as null.');
            $this->assertSame(
                'array',
                $type->getName(),
                $class.'::'.$method.'() must return an array (a model type hint silently turns every real row into "not found").'
            );
        }
    }
}
