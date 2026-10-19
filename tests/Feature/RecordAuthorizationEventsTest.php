<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordAuthorizationEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Central security-event recording for responses the SERVER rejects:
 * 403 permission denied, 419 CSRF failure, 429 rate limit. Nothing else in
 * the code base logs these, and the middleware must never alter the response
 * it observes. Flood suppression is asserted too: a 429 storm cannot become
 * a database-write storm.
 */
class RecordAuthorizationEventsTest extends TestCase
{
    /** @var list<array{url:string,method:string,body:string}> */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->requests = [];
        config([
            'supabase.url' => 'http://supabase.test',
            'supabase.service_role_key' => 'test-service-key',
        ]);

        $requests = &$this->requests;
        Http::fake(function ($request) use (&$requests) {
            $requests[] = [
                'url' => $request->url(),
                'method' => $request->method(),
                'body' => $request->body(),
            ];

            if (str_contains($request->url(), '/rest/v1/activity_events')) {
                return Http::response(
                    [],
                    $request->method() === 'GET' ? 200 : 201,
                    $request->method() === 'GET' ? ['Content-Range' => '0/0'] : []
                );
            }

            return Http::response([], 201);
        });
    }

    /** @return list<string> activity_events insert bodies */
    private function inserts(): array
    {
        return array_map(
            fn ($r) => $r['body'],
            array_values(array_filter(
                $this->requests,
                fn ($r) => str_contains($r['url'], '/rest/v1/activity_events') && $r['method'] === 'POST'
            ))
        );
    }

    private function requestWithActor(): Request
    {
        $request = Request::create('/api/admin/products', 'GET');
        $request->attributes->set('jwt_user_id', 'u-1');
        $request->attributes->set('jwt_role', 'seller');

        return $request;
    }

    public function test_permission_denied_is_recorded_without_altering_the_response(): void
    {
        $middleware = new RecordAuthorizationEvents();
        $original = response()->json(['error' => 'Forbidden'], 403);

        $response = $middleware->handle($this->requestWithActor(), fn () => $original);

        $this->assertSame(403, $response->getStatusCode(), 'The observed response must pass through untouched.');
        $this->assertSame(['error' => 'Forbidden'], $response->getData(true));

        $inserts = $this->inserts();
        $this->assertCount(1, $inserts);
        $row = json_decode($inserts[0], true);
        $this->assertSame('AUTHZ_DENIED', $row['event_name']);
        $this->assertSame('authorization', $row['category']);
        $this->assertTrue($row['is_security']);
        $this->assertSame('denied', $row['outcome']);
        $this->assertSame('LOW', $row['severity']);
        $this->assertSame('u-1', $row['user_id'], 'The actor comes from trusted auth attributes.');
        $this->assertSame('/api/admin/products', $row['route']);
        $this->assertSame(403, $row['metadata']['status'] ?? null);
        $this->assertStringContainsString('permission denials', (string) $row['severity_reason'], 'The reason must name what was counted.');
    }

    public function test_csrf_failure_is_a_medium_security_event_even_without_an_actor(): void
    {
        $middleware = new RecordAuthorizationEvents();

        $response = $middleware->handle(
            Request::create('/language/en', 'POST'),
            fn () => response()->json(['message' => 'CSRF token mismatch'], 419)
        );

        $this->assertSame(419, $response->getStatusCode());

        $row = json_decode($this->inserts()[0], true);
        $this->assertSame('CSRF_FAILURE', $row['event_name']);
        $this->assertSame('security', $row['category']);
        $this->assertSame('MEDIUM', $row['severity']);
        $this->assertSame('CSRF token validation failed', $row['severity_reason']);
        $this->assertNull($row['user_id'], 'Anonymous observations keep a null actor.');
    }

    public function test_rate_limit_hits_are_recorded_with_a_truthful_reason(): void
    {
        $middleware = new RecordAuthorizationEvents();

        $response = $middleware->handle(
            $this->requestWithActor(),
            fn () => response()->json(['error' => 'Too Many Attempts.'], 429)
        );

        $this->assertSame(429, $response->getStatusCode());

        $row = json_decode($this->inserts()[0], true);
        $this->assertSame('RATE_LIMIT_EXCEEDED', $row['event_name']);
        $this->assertSame('MEDIUM', $row['severity']);
        $this->assertSame('Rate limit exceeded', $row['severity_reason']);
    }

    public function test_successful_responses_record_nothing(): void
    {
        $middleware = new RecordAuthorizationEvents();

        $middleware->handle($this->requestWithActor(), fn () => response()->json(['ok' => true], 200));

        $this->assertSame([], $this->inserts(), 'Normal traffic must never generate security events.');
    }

    public function test_a_rate_limit_storm_is_flood_suppressed(): void
    {
        $middleware = new RecordAuthorizationEvents();
        $max = (int) config('monitoring.flood.max_events_per_window', 40);

        for ($i = 0; $i < $max + 5; $i++) {
            $middleware->handle(
                $this->requestWithActor(),
                fn () => response()->json(['error' => 'Too Many Attempts.'], 429)
            );
        }

        $count = count($this->inserts());
        $this->assertLessThanOrEqual($max, $count, 'Beyond the window cap, further identical events must be suppressed.');
        $this->assertGreaterThan(0, $count, 'The incident itself must still be recorded.');
    }
}
