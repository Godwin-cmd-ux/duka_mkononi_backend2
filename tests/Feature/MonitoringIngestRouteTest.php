<?php

namespace Tests\Feature;

use App\Services\JwtToken;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Browser event ingestion (POST /api/monitoring/events).
 *
 * What is pinned here:
 *  - the actor is derived ONLY from a verified JWT; payload identity fields
 *    are ignored (no impersonation into anyone's audit history);
 *  - invalid payloads are rejected individually, oversized batches/bytes are
 *    refused, and the master switch disables ingestion entirely;
 *  - routes lose query strings, event names are normalized, events are always
 *    source=browser with server-generated timestamps;
 *  - heartbeat batches update session liveness WITHOUT storing rows.
 */
class MonitoringIngestRouteTest extends TestCase
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
            'jwt.secret' => 'monitoring-ingest-test-secret',
        ]);

        $requests = &$this->requests;
        Http::fake(function ($request) use (&$requests) {
            $requests[] = [
                'url' => $request->url(),
                'method' => $request->method(),
                'body' => $request->body(),
            ];

            $url = $request->url();
            if (str_contains($url, '/rest/v1/activity_events')) {
                return Http::response([], $request->method() === 'GET' ? 200 : 201);
            }
            if (str_contains($url, '/rest/v1/session_activity')) {
                return Http::response([], $request->method() === 'GET' ? 200 : 201);
            }
            if (str_contains($url, '/rest/v1/user_logs')) {
                return Http::response([], 201);
            }

            return Http::response([], 200);
        });
    }

    /** @return list<array{url:string,method:string,body:string}> */
    private function callsTo(string $needle, ?string $method = null): array
    {
        return array_values(array_filter($this->requests, function ($request) use ($needle, $method) {
            return str_contains($request['url'], $needle)
                && ($method === null || $request['method'] === $method);
        }));
    }

    public function test_anonymous_events_are_stored_without_an_actor_and_without_query_strings(): void
    {
        $response = $this->postJson('/api/monitoring/events', [
            'anon_id' => 'aaaaaaaa-1111-4222-8333-444444444444',
            'events' => [
                ['event' => 'page_view', 'category' => 'page', 'route' => '/shop?token=reset-abc123', 'description' => 'Opened shop'],
                ['event' => 'control_click', 'category' => 'control', 'element' => 'Futa', 'user_id' => 'someone-else'],
            ],
        ]);

        $response->assertOk()->assertJson(['accepted' => 2, 'rejected' => 0]);

        $inserts = $this->callsTo('/rest/v1/activity_events', 'POST');
        $this->assertCount(1, $inserts, 'A batch inserts in one request.');

        $rows = json_decode($inserts[0]['body'], true);
        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);

        $this->assertNull($rows[0]['user_id'], 'Anonymous events must have no actor.');
        $this->assertSame('browser', $rows[0]['source']);
        $this->assertSame('INFO', $rows[0]['severity']);
        $this->assertSame('PAGE_VIEW', $rows[0]['event_name'], 'Event names are normalized server-side.');
        $this->assertSame('/shop', $rows[0]['route'], 'Query strings (reset tokens!) must never be stored.');
        $this->assertStringNotContainsString('reset-abc123', $inserts[0]['body']);

        $this->assertNull($rows[1]['user_id'], 'A user_id inside the payload must be ignored.');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $rows[0]['created_at'], 'Timestamps are server-generated.');
    }

    public function test_a_verified_token_attributes_events_and_touches_the_session(): void
    {
        config(['jwt.secret' => 'monitoring-ingest-test-secret']);
        $token = JwtToken::encode(['userId' => 'user-42', 'email' => 'a@b.c', 'role' => 'seller']);

        $response = $this->postJson('/api/monitoring/events', [
            'events' => [
                ['event' => 'field_changed', 'category' => 'input', 'element' => 'Price'],
            ],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertOk()->assertJson(['accepted' => 1, 'rejected' => 0]);

        $rows = json_decode($this->callsTo('/rest/v1/activity_events', 'POST')[0]['body'], true);
        $this->assertSame('user-42', $rows[0]['user_id'], 'Actor comes from the verified token only.');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) $rows[0]['session_ref']);

        $touched = array_merge(
            $this->callsTo('/rest/v1/session_activity', 'GET'),
            $this->callsTo('/rest/v1/session_activity', 'POST')
        );
        $this->assertNotEmpty($touched, 'Authenticated ingest refreshes session liveness.');
    }

    public function test_an_invalid_token_falls_back_to_anonymous_attribution(): void
    {
        $response = $this->postJson('/api/monitoring/events', [
            'events' => [['event' => 'page_view', 'category' => 'page']],
        ], ['Authorization' => 'Bearer not.a.token']);

        $response->assertOk();

        $rows = json_decode($this->callsTo('/rest/v1/activity_events', 'POST')[0]['body'], true);
        $this->assertNull($rows[0]['user_id'], 'An unverifiable token must never produce an actor.');
    }

    public function test_heartbeat_batches_update_liveness_without_storing_rows(): void
    {
        $token = JwtToken::encode(['userId' => 'user-42', 'role' => 'seller']);

        $response = $this->postJson('/api/monitoring/events', [
            'events' => [['event' => 'HEARTBEAT', 'category' => 'session']],
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertOk()->assertJson(['accepted' => 0]);

        $this->assertSame([], $this->callsTo('/rest/v1/activity_events', 'POST'), 'Heartbeats must not create activity rows.');
        $this->assertNotEmpty($this->callsTo('/rest/v1/session_activity'), 'Heartbeats still refresh the session.');
    }

    public function test_invalid_payloads_are_rejected_individually(): void
    {
        $this->postJson('/api/monitoring/events', ['events' => 'not-an-array'])
            ->assertStatus(422);

        $response = $this->postJson('/api/monitoring/events', [
            'events' => [
                ['event' => 'page_view', 'category' => 'page'],        // valid
                ['event' => 'weird_event', 'category' => 'not-a-category'], // invalid category
                ['event' => '!!!', 'category' => 'page'],              // unnormalizable name
            ],
        ]);
        $response->assertOk()->assertJson(['accepted' => 1, 'rejected' => 2]);
    }

    public function test_oversized_batches_and_payloads_are_refused(): void
    {
        $events = [];
        for ($i = 0; $i < 30; $i++) {
            $events[] = ['event' => 'control_click', 'category' => 'control', 'element' => 'Btn' . $i];
        }
        $this->postJson('/api/monitoring/events', ['events' => $events])
            ->assertOk()
            ->assertJson(['accepted' => 25, 'rejected' => 5]);

        $big = [];
        for ($i = 0; $i < 8; $i++) {
            $big[] = ['event' => 'control_click', 'category' => 'control', 'description' => str_repeat('x', 6000)];
        }
        $this->postJson('/api/monitoring/events', ['events' => $big])->assertStatus(413);
    }

    public function test_the_master_switch_disables_ingestion_without_touching_the_database(): void
    {
        config(['monitoring.enabled' => false]);

        $this->postJson('/api/monitoring/events', [
            'events' => [['event' => 'page_view', 'category' => 'page']],
        ])->assertOk()->assertJson(['disabled' => true]);

        $this->assertSame([], $this->callsTo('/rest/v1/activity_events'), 'Disabled monitoring must write nothing.');
    }
}
