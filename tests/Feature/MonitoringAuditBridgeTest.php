<?php

namespace Tests\Feature;

use App\Services\AuditLogger;
use App\Services\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The AuditLogger bridge (Phase 3): the EXISTING user_logs row must stay
 * byte-for-byte compatible, meaningful actions gain a normalized
 * activity_events projection, routine reads gain nothing, sensitive detail
 * keys are redacted in the projection, and trust context (correlation id,
 * session ref, UA, forwarded-header labelling) is derived from the request,
 * never from stored values.
 */
class MonitoringAuditBridgeTest extends TestCase
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
            'jwt.secret' => 'monitoring-bridge-test-secret',
        ]);

        $requests = &$this->requests;
        Http::fake(function ($request) use (&$requests) {
            $requests[] = [
                'url' => $request->url(),
                'method' => $request->method(),
                'body' => $request->body(),
            ];

            $url = urldecode($request->url());
            if (str_contains($url, '/rest/v1/activity_events')) {
                if ($request->method() === 'GET') {
                    return Http::response([], 200, ['Content-Range' => '0/0']);
                }

                return Http::response([], 201);
            }
            if (str_contains($url, '/rest/v1/security_alerts')) {
                return Http::response($request->method() === 'GET' ? [] : [], $request->method() === 'GET' ? 200 : 201);
            }

            return Http::response([], 201);
        });
    }

    /** @return list<string> decoded activity_events insert bodies */
    private function projections(): array
    {
        return array_map(
            fn ($r) => $r['body'],
            array_values(array_filter(
                $this->requests,
                fn ($r) => str_contains($r['url'], '/rest/v1/activity_events') && $r['method'] === 'POST'
            ))
        );
    }

    private function legacyRows(): array
    {
        return array_map(
            fn ($r) => json_decode($r['body'], true),
            array_values(array_filter(
                $this->requests,
                fn ($r) => str_contains($r['url'], '/rest/v1/user_logs') && $r['method'] === 'POST'
            ))
        );
    }

    public function test_meaningful_action_writes_the_unchanged_legacy_row_plus_a_normalized_projection(): void
    {
        AuditLogger::log(
            'u-1',
            'PRODUCT_UPDATE',
            '/api/products/p-1',
            ['product_id' => 'p-1', 'fields_updated' => ['price'], 'price_change' => [15000, 18000]],
            '203.0.113.9',
            'success'
        );

        // 1. Legacy row: exact pre-existing shape.
        $legacy = $this->legacyRows();
        $this->assertCount(1, $legacy);
        foreach (['id', 'user_id', 'action', 'endpoint', 'details', 'ip_address', 'status', 'created_at'] as $column) {
            $this->assertArrayHasKey($column, $legacy[0], "user_logs.{$column} must remain for backward compatibility.");
        }
        $this->assertSame('PRODUCT_UPDATE', $legacy[0]['action']);
        $this->assertSame('success', $legacy[0]['status']);
        $this->assertIsString($legacy[0]['details'], 'Legacy details stay a JSON string (existing consumers parse it).');
        $this->assertStringContainsString('price_change', $legacy[0]['details']);

        // 2. Projection.
        $projections = $this->projections();
        $this->assertCount(1, $projections, 'Exactly one normalized row for a meaningful action.');
        $row = json_decode($projections[0], true);
        $this->assertSame('business', $row['category']);
        $this->assertSame('PRODUCT_UPDATE', $row['event_name']);
        $this->assertSame('product', $row['entity_type']);
        $this->assertSame('p-1', $row['entity_id']);
        $this->assertSame('server', $row['source']);
        $this->assertSame('success', $row['outcome']);
        $this->assertSame('INFO', $row['severity']);
        $this->assertFalse($row['is_security']);
        $this->assertSame('/api/products/p-1', $row['route']);
        // Before/after values ride along through sanitized metadata.
        $this->assertStringContainsString('15000', (string) $row['metadata']);
        $this->assertStringContainsString('18000', (string) $row['metadata']);
    }

    public function test_noise_actions_only_keep_their_legacy_row(): void
    {
        AuditLogger::log('u-1', 'AUTH_SUCCESS', '/api/products/my', ['role' => 'seller'], '203.0.113.9', 'success');
        AuditLogger::log('u-1', 'HEALTH_CHECK', '/api/health', [], null, 'success');

        $this->assertCount(2, $this->legacyRows(), 'Legacy behaviour is unchanged for every action.');
        $this->assertSame([], $this->projections(), 'Per-request/read noise must never reach the timeline.');
    }

    public function test_sensitive_detail_keys_are_redacted_in_the_projection(): void
    {
        AuditLogger::log('u-1', 'PROFILE_UPDATE', '/api/user/profile', [
            'user_id' => 'u-1',
            'password' => 'Sup3rSecretValue!',
            'access_token' => 'eyJ.payload.here',
            'changed' => ['full_name'],
        ], '203.0.113.9', 'success');

        $projections = $this->projections();
        $this->assertCount(1, $projections);
        $this->assertStringNotContainsString('Sup3rSecretValue!', $projections[0], 'Secrets must never be stored in the monitoring stream.');
        $this->assertStringNotContainsString('payload.here', $projections[0]);
        $this->assertStringContainsString('[redacted]', $projections[0]);
        $this->assertStringContainsString('full_name', $projections[0], 'Ordinary detail values survive.');
    }

    public function test_trust_context_is_derived_from_the_current_request(): void
    {
        $token = JwtToken::encode(['userId' => 'u-1', 'role' => 'seller']);
        $this->app->instance('request', Request::create('/api/products/p-1', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_X_CORRELATION_ID' => 'corr-1234-abcd',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.77',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
            'REMOTE_ADDR' => '10.0.0.5',
        ]));

        AuditLogger::log('u-1', 'SALE_CREATE', '/api/sales', ['sale_id' => 's-9'], null, 'success');

        $row = json_decode($this->projections()[0], true);

        $this->assertSame('corr-1234-abcd', $row['correlation_id'], 'The correlation id comes from the request header (client intent joined to server outcome).');
        $this->assertSame(32, strlen((string) $row['session_ref']));
        $this->assertStringNotContainsString($token, (string) $row['session_ref'], 'The session reference must be a hash, never the token.');
        $this->assertSame('10.0.0.5', $row['ip_address'], 'The recorded source is the server-observed address.');
        $this->assertStringContainsString('Chrome', (string) $row['device']);
        $this->assertSame('Mobile', json_decode((string) $row['device'], true)['device_category'] ?? null);

        $metadata = (string) $row['metadata'];
        $this->assertStringContainsString('remote_addr', $metadata, 'The trust mode of the recorded IP is labelled.');
        $this->assertStringContainsString('198.51.100.77', $metadata, 'The forwarding hint is kept for investigators...');
        $this->assertStringContainsString('forwarded_for_untrusted', $metadata, '...but explicitly marked untrusted (never used as the source).');
    }

    public function test_a_monitoring_write_failure_never_breaks_the_legacy_log_call(): void
    {
        // Hard failure for everything: the legacy insert and the projection
        // both fail, and the call must still return normally.
        Http::fake(function () {
            throw new \RuntimeException('simulated outage');
        });

        AuditLogger::log('u-1', 'PRODUCT_CREATE', '/api/products', ['product_id' => 'p-2'], null, 'success');

        $this->assertTrue(true, 'AuditLogger::log must swallow monitoring outages (logging never breaks a request).');
    }
}
