<?php

namespace Tests\Feature;

use App\Services\JwtToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 4 authentication monitoring, exercised through the real HTTP login
 * flow:
 *  - failures are recorded as security events with counted severity and a
 *    stated reason;
 *  - repeated failures escalate INFO -> LOW -> MEDIUM and open exactly ONE
 *    aggregated alert (occurrence bumps, no duplicates);
 *  - passwords NEVER appear in any stored record (only in the login request
 *    itself);
 *  - success opens a server-derived session and records the authentication;
 *  - unknown accounts are recorded without exposing anything to the caller.
 */
class LoginSecurityMonitoringTest extends TestCase
{
    private const PASSWORD = 'WrongPass!NotStored';

    /** @var list<array{url:string,method:string,body:string}> */
    private array $requests = [];

    private int $failedLogins = 0;

    /** @var array<string, mixed>|null */
    private ?array $openAlert = null;

    /** @var array<string, mixed>|null */
    private ?array $sessionRow = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requests = [];
        $this->failedLogins = 0;
        $this->openAlert = null;
        $this->sessionRow = null;

        config([
            'supabase.url' => 'http://supabase.test',
            'supabase.service_role_key' => 'test-service-key',
            'jwt.secret' => 'monitoring-login-test-secret',
        ]);

        $self = $this;
        Http::fake(function ($request) use ($self) {
            $body = $request->body();
            $url = urldecode($request->url());
            $get = $request->method() === 'GET';

            $self->requests[] = ['url' => $request->url(), 'method' => $request->method(), 'body' => $body];

            if (str_contains($url, '/rest/v1/users')) {
                if ($get) {
                    // Only the known account resolves (unknown emails get []).
                    return Http::response(
                        str_contains($url, 'seller@dukamkononi.test') ? [$self->sellerRow()] : [],
                        200
                    );
                }

                return Http::response([], 204);
            }

            if (str_contains($url, '/rest/v1/activity_events')) {
                if ($get) {
                    // Risk assessment count query: report failures so far.
                    return Http::response([], 200, ['Content-Range' => '0/' . $self->failedLogins]);
                }
                $row = json_decode($body, true);
                if (is_array($row) && ($row['event_name'] ?? '') === 'LOGIN_FAILED') {
                    $self->failedLogins++;
                }

                return Http::response([], 201);
            }

            if (str_contains($url, '/rest/v1/security_alerts')) {
                if ($get) {
                    return Http::response($self->openAlert ? [$self->openAlert] : [], 200);
                }
                if ($request->method() === 'POST') {
                    $self->openAlert = json_decode($body, true) ?: null;
                }

                return Http::response([], $request->method() === 'POST' ? 201 : 204);
            }

            if (str_contains($url, '/rest/v1/session_activity')) {
                if ($get) {
                    return Http::response($self->sessionRow ? [$self->sessionRow] : [], 200);
                }
                $self->sessionRow = json_decode($body, true) ?: null;

                return Http::response([], 201);
            }

            return Http::response([], 201); // user_logs and everything else
        });
    }

    private function sellerRow(): array
    {
        return [
            'id' => 'u-1',
            'email' => 'seller@dukamkononi.test',
            'password' => password_hash('CorrectHorse1', PASSWORD_BCRYPT, ['cost' => 4]),
            'role' => 'seller',
            'status' => 'approved',
            'full_name' => 'Frank Mushi',
            'business_name' => 'Mushi Spares',
            'language' => 'sw',
            'preferred_theme' => null,
        ];
    }

    /** @return list<array{url:string,method:string,body:string}> */
    private function callsTo(string $needle, ?string $method = null): array
    {
        return array_values(array_filter($this->requests, function ($request) use ($needle, $method) {
            return str_contains(urldecode($request['url']), $needle)
                && ($method === null || $request['method'] === $method);
        }));
    }

    /** @return array<string, mixed> the nth stored LOGIN_FAILED activity row */
    private function failedLoginRow(int $index): array
    {
        $rows = array_map(
            fn ($r) => json_decode($r['body'], true),
            $this->callsTo('/rest/v1/activity_events', 'POST')
        );
        $rows = array_values(array_filter($rows, fn ($row) => ($row['event_name'] ?? '') === 'LOGIN_FAILED'));
        $this->assertArrayHasKey($index, $rows, "Expected LOGIN_FAILED row #{$index}.");

        return $rows[$index];
    }

    private function attemptLogin(string $email = 'seller@dukamkononi.test', string $password = self::PASSWORD): void
    {
        $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
            'role' => 'seller',
        ])->assertStatus(401);
    }

    public function test_failed_login_is_recorded_and_the_password_never_enters_any_record(): void
    {
        $this->attemptLogin();

        $row = $this->failedLoginRow(0);
        $this->assertSame('LOGIN_FAILED', $row['event_name']);
        $this->assertSame('authentication', $row['category']);
        $this->assertTrue($row['is_security']);
        $this->assertSame('INFO', $row['severity'], 'A first isolated failure stays informational.');
        $this->assertStringContainsString('10 minutes', (string) $row['severity_reason'], 'The reason must state the counted window.');
        $this->assertSame('u-1', $row['user_id']);

        // Legacy row still written (compat)...
        $this->assertNotEmpty($this->callsTo('/rest/v1/user_logs', 'POST'));

        // ...but NO stored record anywhere contains the submitted password.
        foreach (array_merge($this->callsTo('/rest/v1/activity_events'), $this->callsTo('/rest/v1/user_logs'), $this->callsTo('/rest/v1/security_alerts')) as $record) {
            $this->assertStringNotContainsString(
                self::PASSWORD,
                $record['body'],
                'Passwords must never enter any log or monitoring record.'
            );
        }

        // Nor is it echoed back to the client.
        $response = $this->postJson('/api/login', ['email' => 'seller@dukamkononi.test', 'password' => self::PASSWORD, 'role' => 'seller']);
        $this->assertStringNotContainsString(self::PASSWORD, (string) $response->getContent());
    }

    public function test_repeated_failures_escalate_and_open_exactly_one_aggregated_alert(): void
    {
        // 1st failure -> INFO, no alert.
        $this->attemptLogin();
        $this->assertSame('INFO', $this->failedLoginRow(0)['severity']);
        $this->assertEmpty($this->callsTo('/rest/v1/security_alerts', 'POST'));

        // 2nd -> LOW (assessment cache cleared as if the window advanced).
        Cache::flush();
        $this->attemptLogin();
        $this->assertSame('LOW', $this->failedLoginRow(1)['severity']);

        // 3rd -> MEDIUM: an alert opens.
        Cache::flush();
        $this->attemptLogin();
        $this->assertSame('MEDIUM', $this->failedLoginRow(2)['severity']);
        $this->assertStringContainsString(
            'failed login(s)',
            (string) $this->failedLoginRow(2)['severity_reason'],
            'Escalation must explain the count behind the severity.'
        );

        $alertPosts = $this->callsTo('/rest/v1/security_alerts', 'POST');
        $this->assertCount(1, $alertPosts, 'Crossing the threshold opens exactly one incident.');
        $alert = json_decode($alertPosts[0]['body'], true);
        $this->assertSame('login_failure', $alert['rule']);
        $this->assertSame('user:u-1', $alert['subject']);
        $this->assertSame('MEDIUM', $alert['severity']);
        $this->assertSame('open', $alert['status']);

        // 4th -> still MEDIUM; the OPEN incident is bumped, not duplicated.
        Cache::flush();
        $this->attemptLogin();
        $this->assertSame('MEDIUM', $this->failedLoginRow(3)['severity']);
        $this->assertCount(1, $this->callsTo('/rest/v1/security_alerts', 'POST'), 'Dedupe: repeated failures never duplicate the alert.');

        $patches = $this->callsTo('/rest/v1/security_alerts', 'PATCH');
        $this->assertNotEmpty($patches);
        $bump = json_decode($patches[0]['body'], true);
        $this->assertSame(2, $bump['occurrence_count'] ?? null, 'The open incident counts the new occurrence.');
    }

    public function test_successful_login_records_the_authentication_and_opens_a_session(): void
    {
        $androidChrome = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.230 Mobile Safari/537.36';

        $response = $this->postJson('/api/login', [
            'email' => 'seller@dukamkononi.test',
            'password' => 'CorrectHorse1',
            'role' => 'seller',
        ], ['User-Agent' => $androidChrome]);

        $response->assertOk();
        $token = (string) $response->json('token');
        $this->assertNotSame('', $token);

        // The authentication event.
        $authRows = array_values(array_filter(
            array_map(fn ($r) => json_decode($r['body'], true), $this->callsTo('/rest/v1/activity_events', 'POST')),
            fn ($row) => ($row['event_name'] ?? '') === 'LOGIN_SUCCESS'
        ));
        $this->assertNotEmpty($authRows, 'Successful authentication must be recorded (Phase 4A).');
        $this->assertSame('authentication', $authRows[0]['category']);
        $this->assertTrue($authRows[0]['is_security']);

        // The session row: keyed by the hash of the ISSUED token, never the token.
        $sessionPosts = $this->callsTo('/rest/v1/session_activity', 'POST');
        $this->assertNotEmpty($sessionPosts, 'Login must open a session record.');
        $session = json_decode($sessionPosts[0]['body'], true);
        $this->assertSame('u-1', $session['user_id']);
        $this->assertSame(substr(hash('sha256', $token), 0, 32), $session['session_ref']);
        $this->assertSame('active', $session['status']);
        $this->assertStringNotContainsString($token, (string) $session['session_ref']);
        $device = json_decode((string) $session['device'], true);
        $this->assertSame('Chrome', $device['browser'] ?? null, 'Device facts come from the observed User-Agent.');
        $this->assertSame('Unknown model', $device['model'] ?? null, 'Handset models are never claimed.');
    }

    public function test_unknown_accounts_are_recorded_without_exposing_account_existence(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'ghost@dukamkononi.test',
            'password' => self::PASSWORD,
            'role' => 'seller',
        ]);
        $response->assertStatus(401);

        $row = $this->failedLoginRow(0);
        $this->assertNull($row['user_id'], 'No actor exists for an unknown account.');
        $this->assertSame('LOGIN_FAILED', $row['event_name']);
        $this->assertTrue($row['is_security']);

        // The failure was isolated: no incident is opened below the threshold.
        $this->assertSame([], $this->callsTo('/rest/v1/security_alerts', 'POST'));

        // The caller learns nothing beyond the pre-existing generic message.
        $this->assertStringNotContainsString(self::PASSWORD, (string) $response->getContent());
        $this->assertStringNotContainsString('stack', strtolower((string) $response->getContent()));
    }
}
