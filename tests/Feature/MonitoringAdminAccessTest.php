<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\MonitoringController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Server-side authorization and administrative actions on the monitoring
 * surface. A hidden menu item is never the control: every method re-checks
 * the role itself, unauthorized attempts are audited, and the sensitive
 * administrative actions (incident status changes, exports, setup) are
 * themselves audited. Monitoring responses must never leak internals.
 */
class MonitoringAdminAccessTest extends TestCase
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
            'jwt.secret' => 'monitoring-admin-test-secret',
        ]);

        $requests = &$this->requests;
        Http::fake(function ($request) use (&$requests) {
            $requests[] = [
                'url' => $request->url(),
                'method' => $request->method(),
                'body' => $request->body(),
            ];

            $url = $request->url();
            $get = $request->method() === 'GET';

            if (str_contains($url, '/rest/v1/security_alerts')) {
                if ($get) {
                    // One pre-existing open incident for the status-change test.
                    return Http::response([[
                        'id' => 'alert-1',
                        'rule' => 'login_failure',
                        'subject' => 'user:u-9',
                        'severity' => 'MEDIUM',
                        'status' => 'open',
                        'summary' => 'Repeated failed logins',
                        'occurrence_count' => 3,
                        'first_seen' => '2026-10-18T08:00:00.000Z',
                        'last_seen' => '2026-10-18T09:00:00.000Z',
                    ]]);
                }

                return Http::response([], 204);
            }
            if (str_contains($url, '/rest/v1/activity_events')) {
                $sample = [[
                    'id' => 'evt-1',
                    'user_id' => 'u-1',
                    'category' => 'business',
                    'event_name' => 'PRODUCT_UPDATE',
                    'description' => '=SUM(A1)',
                    'created_at' => '2026-10-18T09:30:00.000Z',
                    'severity' => 'INFO',
                    'outcome' => 'success',
                    'source' => 'server',
                    'is_security' => false,
                    'ip_address' => '203.0.113.9',
                ]];
                if ($get) {
                    // List/count/lookup reads all receive the sample row; the
                    // Content-Range header drives the count() path.
                    return Http::response($sample, 200, ['Content-Range' => '0/1']);
                }

                return Http::response([], 201);
            }
            if (str_contains($url, '/rest/v1/users')) {
                if ($get) {
                    return Http::response([[
                        'id' => 'u-1',
                        'email' => 'frank@dukamkononi.test',
                        'full_name' => 'Frank Mushi',
                        'role' => 'seller',
                    ]]);
                }

                return Http::response([], 204);
            }
            if (str_contains($url, '/rest/v1/session_activity')) {
                return Http::response([], $get ? 200 : 201);
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

    private function adminRequest(string $uri, string $method = 'GET', string $role = 'admin'): Request
    {
        $request = Request::create($uri, $method);
        $request->attributes->set('jwt_user_id', 'admin-1');
        $request->attributes->set('jwt_role', $role);
        $request->attributes->set('jwt_ip', '127.0.0.1');

        return $request;
    }

    public function test_non_admins_are_denied_and_the_attempt_is_audited(): void
    {
        $response = (new MonitoringController)->timeline($this->adminRequest('/api/admin/monitoring/timeline', 'GET', 'seller'));

        $this->assertSame(403, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame(['error' => 'Unauthorized'], $payload, 'A denial must not leak internals.');
        $this->assertArrayNotHasKey('details', $payload);

        $audits = array_filter($this->callsTo('/rest/v1/user_logs', 'POST'), fn ($r) => str_contains($r['body'], 'ADMIN_MONITORING_UNAUTHORIZED'));
        $this->assertNotEmpty($audits, 'Unauthorized access to the audit surface must itself be audited.');
    }

    public function test_timeline_for_admin_returns_normalized_page(): void
    {
        $response = (new MonitoringController)->timeline($this->adminRequest('/api/admin/monitoring/timeline'));

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertArrayHasKey('items', $payload);
        $this->assertArrayHasKey('total', $payload);
        $this->assertArrayHasKey('meta', $payload);
        $this->assertContains('authentication', $payload['meta']['categories']);
        $this->assertContains('CRITICAL', $payload['meta']['severities']);
    }

    public function test_summary_reports_the_documented_counting_keys(): void
    {
        $response = (new MonitoringController)->summary($this->adminRequest('/api/admin/monitoring/summary'));

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        foreach (['total_events', 'logins', 'failed_logins', 'high_risk_events', 'critical_events', 'failed_operations', 'admin_changes', 'active_sessions', 'open_alerts'] as $key) {
            $this->assertArrayHasKey($key, $payload, "Summary counter {$key} is part of the dashboard contract.");
        }
    }

    public function test_alert_status_change_is_admin_only_and_audited(): void
    {
        // Non-admin denied.
        $denied = (new MonitoringController)->alertStatus(
            $this->adminRequest('/api/admin/monitoring/alerts/alert-1/status', 'POST', 'seller'),
            'alert-1'
        );
        $this->assertSame(403, $denied->getStatusCode());
        $before = count($this->callsTo('/rest/v1/user_logs', 'POST'));

        // Admin succeeds.
        $request = $this->adminRequest('/api/admin/monitoring/alerts/alert-1/status', 'POST');
        $request->merge(['status' => 'acknowledged', 'note' => 'Checking the source address']);
        $response = (new MonitoringController)->alertStatus($request, 'alert-1');

        $this->assertSame(200, $response->getStatusCode());

        $patches = $this->callsTo('/rest/v1/security_alerts', 'PATCH');
        $this->assertNotEmpty($patches, 'The incident row must be updated server-side.');
        $this->assertStringContainsString('acknowledged', $patches[0]['body']);
        $this->assertStringContainsString('Checking the source address', $patches[0]['body'], 'The investigation note is stored.');

        $afterAudits = array_slice($this->callsTo('/rest/v1/user_logs', 'POST'), $before);
        $mirrors = array_filter(
            array_map(fn ($r) => json_decode($r['body'], true), $this->callsTo('/rest/v1/activity_events', 'POST')),
            fn ($row) => is_array($row) && ($row['event_name'] ?? '') === 'SECURITY_ALERT_STATUS'
        );
        $this->assertNotEmpty($afterAudits, 'Incident status changes must be audited.');
        $this->assertNotEmpty($mirrors, 'The incident change must appear in the monitoring timeline as an admin event.');
        $this->assertSame('admin', $mirrors[0]['category'] ?? null);
    }

    public function test_alert_status_validation_rejects_unknown_states(): void
    {
        $request = $this->adminRequest('/api/admin/monitoring/alerts/alert-1/status', 'POST');
        $request->merge(['status' => 'whatever-they-want']);

        $response = (new MonitoringController)->alertStatus($request, 'alert-1');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([], $this->callsTo('/rest/v1/security_alerts', 'PATCH'), 'Invalid states must not touch the row.');
    }

    public function test_export_produces_csv_and_is_audited_with_formula_defusing(): void
    {
        $response = (new MonitoringController)->export($this->adminRequest('/api/admin/monitoring/export'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = (string) $response->getContent();
        $this->assertStringContainsString('created_at', $csv);
        $this->assertStringContainsString('frank@dukamkononi.test', $csv);
        $this->assertStringContainsString("'=SUM(A1)", $csv, 'Formula-leading cells must be defused.');
        $this->assertStringNotContainsString("\n=SUM", $csv);

        $audits = array_filter($this->callsTo('/rest/v1/user_logs', 'POST'), fn ($r) => str_contains($r['body'], 'MONITORING_EXPORT'));
        $this->assertNotEmpty($audits, 'Exports of audit data must be audited.');
    }

    public function test_runtime_setup_creates_tables_and_is_audited(): void
    {
        $this->assertFalse(Schema::hasTable('activity_events'), 'Precondition: fresh database.');

        $response = (new MonitoringController)->setup($this->adminRequest('/api/admin/monitoring/setup', 'POST'));

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertTrue(Schema::hasTable('activity_events'));
        $this->assertTrue(Schema::hasTable('session_activity'));
        $this->assertTrue(Schema::hasTable('security_alerts'));

        $audits = array_filter($this->callsTo('/rest/v1/user_logs', 'POST'), fn ($r) => str_contains($r['body'], 'MONITORING_SETUP'));
        $this->assertNotEmpty($audits, 'Schema changes from the UI must be audited.');
    }
}
