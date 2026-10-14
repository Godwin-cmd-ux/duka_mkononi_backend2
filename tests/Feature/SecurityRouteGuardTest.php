<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DebugController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The two exposures found while shipping the AI capability suite:
 *
 *  - `api/temp/*` (schema, table list, row counts and a per-table data dump)
 *    was registered OUTSIDE auth.jwt and curled the service_role key, so anyone
 *    on the internet could read the whole schema and any table's rows. It was
 *    only ever meant to produce a one-off SYSTEM_SCHEMA.sql snapshot, so it has
 *    been deleted rather than guarded;
 *
 *  - `api/debug/*` (account lookup by email, and the current business' expense
 *    summary) was also public. The business one is self-scoped but reads the
 *    caller from the JWT, and the mobile admin screen already sends its Bearer
 *    token, so both now sit inside auth.jwt - and the account lookup is limited
 *    to the super admin, because by-email user enumeration is a gift to anyone
 *    holding a customer login.
 *
 * These tests fail if either is quietly put back in the open.
 */
class SecurityRouteGuardTest extends TestCase
{
    /** @return array<int, string> the middleware of the single matching route */
    private function middlewareFor(string $uri, string $method = 'GET'): array
    {
        $matches = collect(Route::getRoutes()->getRoutes())->filter(
            fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
        );

        $this->assertCount(1, $matches, "{$method} /{$uri} must stay registered exactly once.");

        return $matches->first()->gatherMiddleware();
    }

    private function roleOf(string $uri, string $role): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/' . $uri, 'GET');
        $request->attributes->set('jwt_user_id', 'user-1');
        $request->attributes->set('jwt_role', $role);
        $request->attributes->set('jwt_ip', '10.0.0.1');

        return (new DebugController)->checkUser($request, 'someone@example.com');
    }

    public function test_the_temporary_dump_routes_are_gone(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->all();

        foreach ([
            'api/temp/schema',
            'api/temp/tables',
            'api/temp/dump/{table}',
            'api/temp/count/{table}',
        ] as $uri) {
            $this->assertNotContains(
                $uri,
                $uris,
                "/{$uri} exposed schema and table contents with the service_role key and must not come back."
            );
        }
    }

    public function test_the_debug_helpers_sit_behind_auth(): void
    {
        foreach (['api/debug/check-user/{email}', 'api/debug/check-business'] as $uri) {
            $middleware = $this->middlewareFor($uri);

            $this->assertStringContainsString(
                'auth.jwt',
                implode(',', $middleware),
                "/{$uri} reveals other people's account or business data and must never be public."
            );
        }
    }

    public function test_the_account_lookup_is_for_the_super_admin_only(): void
    {
        config(['supabase.url' => '', 'supabase.service_role_key' => '']);

        $denied = $this->roleOf('api/debug/check-user/someone@example.com', 'seller');
        $this->assertSame(403, $denied->getStatusCode());
        $this->assertSame('FORBIDDEN_ROLE', $denied->getData(true)['code']);

        $alsoDenied = $this->roleOf('api/debug/check-user/someone@example.com', 'customer');
        $this->assertSame(403, $alsoDenied->getStatusCode());

        // The super admin passes the gate (and then fails on the blanked
        // Supabase in this hermetic suite, which is the point: the guard is the
        // only thing being asserted here).
        $allowed = $this->roleOf('api/debug/check-user/someone@example.com', 'system_admin');
        $this->assertNotSame(403, $allowed->getStatusCode());
    }
}
