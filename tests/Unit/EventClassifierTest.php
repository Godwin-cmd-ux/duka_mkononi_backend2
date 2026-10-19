<?php

namespace Tests\Unit;

use App\Services\Monitoring\EventClassifier;
use Tests\TestCase;

/**
 * The AuditLogger -> activity_events bridge classifier decides what the
 * monitoring timeline shows (and what it deliberately ignores), so its rules
 * are pinned here:
 *  - meaningful actions classify with the right category/outcome/entity;
 *  - routine reads, probes and per-request noise are NOT mirrored;
 *  - security-relevant failures carry the risk kind SecurityMonitor escalates;
 *  - unknown action shapes mirror nothing (events are never invented).
 */
class EventClassifierTest extends TestCase
{
    public function test_successful_product_update_is_a_business_operation_with_entity(): void
    {
        $c = EventClassifier::classify(
            'PRODUCT_UPDATE',
            'success',
            ['product_id' => 'p-784', 'fields_updated' => ['price'], 'price_change' => [15000, 18000]],
            '/api/products/:id?secret=1'
        );

        $this->assertNotNull($c, 'A product update must be mirrored.');
        $this->assertSame('business', $c['category']);
        $this->assertSame('success', $c['outcome']);
        $this->assertSame('product', $c['entity_type']);
        $this->assertSame('p-784', $c['entity_id']);
        $this->assertFalse($c['is_security']);
        $this->assertNull($c['risk']);
        $this->assertSame('/api/products/:id', $c['route'], 'Query strings must never survive into the route.');
        $this->assertSame('Products', $c['page']);
        $this->assertSame('Product update', $c['description']);
    }

    public function test_sale_update_before_after_details_reach_the_metadata_layer_untouched(): void
    {
        $details = [
            'items' => [['product_name' => 'Filter', 'old_quantity' => 4, 'new_quantity' => 9]],
            'old_total_amount' => 15000,
            'total_amount' => 18000,
        ];

        $c = EventClassifier::classify('SALE_UPDATE', 'success', $details, '/api/sales/9');

        $this->assertSame('business', $c['category']);
        $this->assertSame('sale', $c['entity_type']);
        // The classifier only extracts identity; before/after values ride
        // along in $details and are sanitized by ActivityTracker, not dropped.
        $this->assertSame(4, $details['items'][0]['old_quantity']);
        $this->assertSame(18000, $details['total_amount']);
    }

    public function test_failed_login_carries_the_login_failure_risk(): void
    {
        $c = EventClassifier::classify('LOGIN_FAILED', 'failed', ['email' => 'x@y.z', 'role' => 'seller'], '/api/login');

        $this->assertNotNull($c);
        $this->assertSame('authentication', $c['category']);
        $this->assertSame('LOGIN_FAILED', $c['event']);
        $this->assertSame('failed', $c['outcome']);
        $this->assertTrue($c['is_security'], 'Failed logins are security events (Phase 4B).');
        $this->assertSame('login_failure', $c['risk']);
        $this->assertSame('Authentication', $c['page']);
    }

    public function test_successful_login_is_also_a_security_record(): void
    {
        $c = EventClassifier::classify('LOGIN_SUCCESS', 'success', ['role' => 'admin'], '/api/login');

        $this->assertTrue($c['is_security'], 'Phase 4A requires successful authentication to be recorded.');
        $this->assertNull($c['risk'], 'Success is recorded but never escalated.');
        $this->assertSame('success', $c['outcome']);
    }

    public function test_unauthorized_attempts_are_authorization_security_events(): void
    {
        $c = EventClassifier::classify('ADMIN_LOGS_UNAUTHORIZED', 'failed', ['reason' => 'Non-admin access attempt'], '/api/admin/logs');

        $this->assertSame('authorization', $c['category']);
        $this->assertTrue($c['is_security']);
        $this->assertSame('authz_denied', $c['risk'], 'Authorization denials feed the IP denial ladder.');
        $this->assertSame('failed', $c['outcome']);
    }

    public function test_password_change_is_a_security_event_with_low_base_severity(): void
    {
        $c = EventClassifier::classify('PASSWORD_CHANGED', 'success', ['success' => true], '/api/user/password');

        $this->assertSame('authentication', $c['category']);
        $this->assertTrue($c['is_security']);
        $this->assertSame('success', $c['outcome']);

        $failed = EventClassifier::classify('PASSWORD_CHANGE_FAILED', 'failed', ['reason' => 'Current password incorrect'], '/api/user/password');
        $this->assertTrue($failed['is_security']);
        $this->assertSame('LOW', $failed['severity'], 'Failed credential changes deserve more than INFO.');
    }

    public function test_failed_registration_is_security_but_completed_registration_is_not(): void
    {
        $failed = EventClassifier::classify('REGISTER_VERIFY_FAILED', 'failed', ['email' => 'a@b.c'], '/api/register/verify-otp');
        $this->assertTrue($failed['is_security'], 'OTP/registration failures are the code-guessing signal.');

        $done = EventClassifier::classify('REGISTER_SUCCESS', 'success', ['email' => 'a@b.c'], '/api/register');
        $this->assertFalse($done['is_security'], 'A completed registration is an ordinary account event.');
        $this->assertSame('authentication', $done['category']);
    }

    public function test_admin_status_change_is_security_with_user_entity(): void
    {
        $c = EventClassifier::classify('ADMIN_STATUS_UPDATE', 'success', [
            'target_user_id' => 'u-9',
            'old_status' => 'pending',
            'new_status' => 'approved',
        ], '/api/admin/users/u-9/status');

        $this->assertSame('admin', $c['category']);
        $this->assertTrue($c['is_security'], 'Account state changes are Phase 4D security events.');
        $this->assertSame('user', $c['entity_type']);
        $this->assertSame('u-9', $c['entity_id'], 'The TARGET user must be the entity, not the acting admin.');
    }

    public function test_routine_reads_and_probes_are_never_mirrored(): void
    {
        $noise = [
            'AUTH_SUCCESS',        // one row per API request
            'HEALTH_CHECK',
            'TEST_ENDPOINT',
            'PRODUCTS_VIEW',
            'SALES_VIEW_ERROR',
            'BUSINESS_VIEW',
            'CHECK_BUSINESS_SUCCESS',
            'WS_STATUS_FETCH',
            'AI_REQUEST',
            'LOGIN_THEME_SYNC_SKIPPED',
            'ACTIVITY_VIEW',
        ];

        foreach ($noise as $action) {
            $this->assertNull(
                EventClassifier::classify($action, 'success', [], '/api/x'),
                "Noise action {$action} must not be mirrored into the timeline."
            );
        }
    }

    public function test_admin_reads_are_still_mirrored_because_auditing_the_auditor_matters(): void
    {
        $c = EventClassifier::classify('ADMIN_LOGS_VIEW', 'success', ['log_count' => 100], '/api/admin/logs');

        $this->assertNotNull($c, 'Who opened the audit logs is itself worth auditing.');
        $this->assertSame('admin', $c['category']);
        $this->assertSame('success', $c['outcome']);
    }

    public function test_unknown_action_shapes_are_never_invented(): void
    {
        $this->assertNull(EventClassifier::classify('Xyzzy-Strange', 'success', [], null));
        $this->assertNull(EventClassifier::classify('lowercase_only', 'success', [], null));
        $this->assertNull(EventClassifier::classify('', 'success', [], null));
    }

    public function test_ai_capability_operations_are_classified(): void
    {
        $commit = EventClassifier::classify('AI_ENTRY_COMMIT', 'success', ['record_id' => 'r1'], '/api/ai/entry/commit');
        $this->assertSame('ai', $commit['category']);
        $this->assertSame('record', $commit['entity_type']);
        $this->assertSame('r1', $commit['entity_id']);

        $leak = EventClassifier::classify('AI_ADMIN_ANALYTICS_LEAK_BLOCKED', 'failed', [], '/api/ai/platform-analytics/ask');
        $this->assertSame('authorization', $leak['category'], 'Blocked disclosure attempts are authorization events.');
        $this->assertTrue($leak['is_security']);
    }

    public function test_outcome_mapping_covers_the_status_vocabulary_in_use(): void
    {
        $this->assertSame('success', EventClassifier::outcome('success'));
        $this->assertSame('success', EventClassifier::outcome('ok'));
        $this->assertSame('failed', EventClassifier::outcome('failed'));
        $this->assertSame('failed', EventClassifier::outcome('error'));
        $this->assertSame('denied', EventClassifier::outcome('blocked'));
        $this->assertSame('rejected', EventClassifier::outcome('rate_limited'));
        $this->assertSame('observed', EventClassifier::outcome('unknown'));
        $this->assertSame('observed', EventClassifier::outcome(''));
    }

    public function test_label_and_route_helpers_are_safe(): void
    {
        $this->assertSame('Product update', EventClassifier::label('PRODUCT_UPDATE'));
        $this->assertSame('/api/login', EventClassifier::route('/api/login?token=abc123&code=999'));
        $this->assertSame('/api/login', EventClassifier::route('api/login'));
        $this->assertNull(EventClassifier::route(null));
        $this->assertStringNotContainsString('token', (string) EventClassifier::route('/api/login?token=abc123'));
    }
}
