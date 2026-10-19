<?php

namespace Tests\Unit;

use App\Services\Monitoring\ActivityTracker;
use Tests\TestCase;

/**
 * The privacy pipeline every event passes through before storage:
 *  - sensitive keys are redacted, not merely truncated;
 *  - strings lose control characters (log forging) and are length-capped;
 *  - oversized structures are bounded instead of stored whole;
 *  - event names are normalized to a safe token or rejected outright;
 *  - routes lose query strings (reset tokens, codes, auth fragments);
 *  - the session reference is a hash, never the token itself.
 */
class MonitoringSanitizeTest extends TestCase
{
    public function test_sensitive_metadata_keys_are_redacted_not_truncated(): void
    {
        $json = ActivityTracker::sanitizeMetadata([
            'password' => 'Sup3rSecretValue!',
            'new_password' => 'AnotherSecret99',
            'api_key' => 'sk-live-123',
            'access_token' => 'eyJhbGciOi.payload',
            'reset_code' => '483920',
            'cvv' => '123',
            'note' => 'price changed',
        ]);

        $this->assertNotNull($json);
        foreach (['Sup3rSecretValue!', 'AnotherSecret99', 'sk-live-123', 'payload', '483920', '123"'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, "Secret value leaked: {$secret}");
        }
        $this->assertStringContainsString('[redacted]', $json);
        $this->assertStringContainsString('price changed', $json, 'Ordinary values must survive redaction.');
    }

    public function test_control_characters_and_newlines_are_stripped_from_strings(): void
    {
        $clean = ActivityTracker::sanitizeString("hello\r\n forged line\t\x00\x1B[31m");

        $this->assertNotNull($clean);
        $this->assertStringNotContainsString("\r", $clean);
        $this->assertStringNotContainsString("\n", $clean);
        $this->assertStringNotContainsString("\x00", $clean);
        $this->assertStringNotContainsString("\x1B", $clean);
        $this->assertStringContainsString('forged line', $clean);
    }

    public function test_strings_are_length_capped(): void
    {
        $long = str_repeat('a', 500);
        $this->assertSame(20, strlen((string) ActivityTracker::sanitizeString($long, 20)));
        $this->assertSame('café', ActivityTracker::sanitizeString('café', 10), 'Multibyte text must not be cut mid-character into mojibake.');
    }

    public function test_oversized_metadata_is_bounded_and_marked(): void
    {
        $json = ActivityTracker::sanitizeMetadata([
            'first' => 'small',
            'huge' => str_repeat('x', 4000),
            'later' => 'tail',
        ], 512);

        $this->assertNotNull($json);
        $this->assertLessThanOrEqual(512 + 64, strlen($json) + 40, 'Byte cap must hold even with a huge entry.');
        $this->assertStringContainsString('first', $json);
    }

    public function test_metadata_depth_and_breadth_are_capped(): void
    {
        $deep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'gone']]]]]];
        $json = (string) ActivityTracker::sanitizeMetadata($deep, 2048);
        $this->assertStringContainsString('[truncated]', $json);
        $this->assertStringNotContainsString('gone', $json);

        $wide = [];
        for ($i = 0; $i < 60; $i++) {
            $wide['k' . $i] = $i;
        }
        $wideJson = (string) ActivityTracker::sanitizeMetadata($wide, 4096);
        $decoded = json_decode($wideJson, true);
        $this->assertIsArray($decoded);
        $this->assertLessThanOrEqual(42, count($decoded), 'Breadth must be capped (40 entries + marker).');
    }

    public function test_event_names_normalize_to_safe_tokens_or_are_rejected(): void
    {
        $this->assertSame('PAGE_VIEW', ActivityTracker::normalizeEventName('page view'));
        $this->assertSame('FIELD_CHANGED', ActivityTracker::normalizeEventName('  field_changed  '));
        $this->assertSame('A_B', ActivityTracker::normalizeEventName('a-b'));
        $this->assertSame('', ActivityTracker::normalizeEventName('!!!'), 'Pure symbols can never become an event.');
        $this->assertSame('', ActivityTracker::normalizeEventName(''));
        $this->assertSame('', ActivityTracker::normalizeEventName(str_repeat('Z', 80)), 'Overlong names are rejected, not stored.');
    }

    public function test_routes_never_keep_query_strings_or_fragments(): void
    {
        $this->assertSame('/reset/password', ActivityTracker::sanitizeRoute('/reset/password?token=abc123&code=9'));
        $this->assertSame('/shop', ActivityTracker::sanitizeRoute('/shop#access_token=xyz'));
        $this->assertSame('/api/orders', ActivityTracker::sanitizeRoute('api/orders'));
        $this->assertNull(ActivityTracker::sanitizeRoute(null));
        $this->assertNull(ActivityTracker::sanitizeRoute(''));
        $this->assertNull(ActivityTracker::sanitizeRoute('?only=query'));
    }

    public function test_session_reference_is_a_hash_and_never_the_token(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiJ9.eyJ1c2VySWQiOiJ1In0.signaturepart';

        $ref = ActivityTracker::sessionRef($token);

        $this->assertNotNull($ref);
        $this->assertSame(32, strlen($ref));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $ref);
        $this->assertStringNotContainsString($token, $ref);
        $this->assertNotSame($ref, $token);
        $this->assertSame($ref, ActivityTracker::sessionRef($token), 'The same token always maps to the same session.');
        $this->assertNotSame($ref, ActivityTracker::sessionRef($token . 'x'), 'A different token maps to a different session.');
        $this->assertNull(ActivityTracker::sessionRef(null));
        $this->assertNull(ActivityTracker::sessionRef('   '));
    }
}
