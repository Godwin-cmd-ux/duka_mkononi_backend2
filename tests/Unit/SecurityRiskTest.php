<?php

namespace Tests\Unit;

use App\Services\Monitoring\SecurityMonitor;
use Tests\TestCase;

/**
 * The pure severity ladder behind Phase 4 risk classification. Thresholds
 * come from config/monitoring.php; these tests pin the semantics:
 *  - 1-2 occurrences stay informational/low (isolated failures are not
 *    attacks);
 *  - configured steps escalate MEDIUM/HIGH/CRITICAL;
 *  - missing steps can never be reached (no accidental escalation).
 */
class SecurityRiskTest extends TestCase
{
    private const ACCOUNT = ['medium' => 3, 'high' => 5, 'critical' => 10];

    public function test_isolated_failures_stay_informational(): void
    {
        $this->assertSame('INFO', SecurityMonitor::ladder(1, self::ACCOUNT));
        $this->assertSame('LOW', SecurityMonitor::ladder(2, self::ACCOUNT));
    }

    public function test_configured_steps_escalate_in_order(): void
    {
        $this->assertSame('MEDIUM', SecurityMonitor::ladder(3, self::ACCOUNT));
        $this->assertSame('MEDIUM', SecurityMonitor::ladder(4, self::ACCOUNT));
        $this->assertSame('HIGH', SecurityMonitor::ladder(5, self::ACCOUNT));
        $this->assertSame('HIGH', SecurityMonitor::ladder(9, self::ACCOUNT));
        $this->assertSame('CRITICAL', SecurityMonitor::ladder(10, self::ACCOUNT));
        $this->assertSame('CRITICAL', SecurityMonitor::ladder(500, self::ACCOUNT));
    }

    public function test_privileged_thresholds_escalate_earlier_than_ordinary_ones(): void
    {
        $privileged = ['medium' => 2, 'high' => 3, 'critical' => 5];

        // A brute force on an admin account must read as worse than the same
        // count against a customer account.
        $this->assertSame('MEDIUM', SecurityMonitor::ladder(3, self::ACCOUNT));
        $this->assertSame('HIGH', SecurityMonitor::ladder(3, $privileged));
        $this->assertSame('CRITICAL', SecurityMonitor::ladder(5, $privileged));
        $this->assertSame('HIGH', SecurityMonitor::ladder(5, self::ACCOUNT));
    }

    public function test_missing_steps_are_unreachable_instead_of_infinite(): void
    {
        $this->assertSame('INFO', SecurityMonitor::ladder(1000, []), 'No configured steps = never escalates.');
        $this->assertSame('MEDIUM', SecurityMonitor::ladder(1000, ['medium' => 3]));
        $this->assertSame('LOW', SecurityMonitor::ladder(2, ['medium' => 999]));
    }

    public function test_severity_rank_ordering_is_consistent(): void
    {
        $this->assertSame(0, SecurityMonitor::rank('INFO'));
        $this->assertSame(1, SecurityMonitor::rank('LOW'));
        $this->assertSame(2, SecurityMonitor::rank('MEDIUM'));
        $this->assertSame(3, SecurityMonitor::rank('HIGH'));
        $this->assertSame(4, SecurityMonitor::rank('CRITICAL'));
        $this->assertSame(0, SecurityMonitor::rank('NOT_A_LEVEL'), 'Unknown severities must degrade, never throw.');

        $this->assertSame('HIGH', SecurityMonitor::maxSeverity('INFO', 'HIGH'));
        $this->assertSame('CRITICAL', SecurityMonitor::maxSeverity('HIGH', 'critical'));
        $this->assertSame('MEDIUM', SecurityMonitor::maxSeverity('medium', 'LOW'));
    }
}
