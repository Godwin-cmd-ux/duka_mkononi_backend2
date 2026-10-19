<?php

namespace App\Services\Monitoring;

use App\Models\ActivityEvent;
use App\Models\SecurityAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase 4: repeated-failure detection, risk classification and alert
 * aggregation.
 *
 * Severity ladder (config/monitoring.php):
 *   1-2 occurrences .............. INFO / LOW (isolated, not malicious)
 *   >= account.medium ............ MEDIUM (repeated, needs attention)
 *   >= account.high .............. HIGH
 *   >= account.critical .......... CRITICAL
 *   privileged accounts (admin) escalate earlier; the ip ladder needs higher
 *   counts so shared NATs do not paint one user as malicious.
 *
 * Every escalation carries a human `reason` (why the severity was chosen) and
 * every alert OPENED at or above min_alert_severity aggregates into ONE row
 * per (rule, subject) while open - repeated failures bump occurrence_count
 * instead of creating duplicate alerts.
 *
 * All database/cache work is best effort: a monitoring outage must never
 * break a login or an API request. When a SECURITY-critical write fails it is
 * forwarded to the framework log so it is not silently discarded.
 */
class SecurityMonitor
{
    public const RANK = ['INFO' => 0, 'LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3, 'CRITICAL' => 4];

    /**
     * Assess the risk of a failure that is ABOUT to be recorded.
     *
     * @return array{severity:string, reason:string, account_failures:int, ip_failures:int}
     */
    public static function assess(string $risk, ?string $userId, ?string $ip, array $context = []): array
    {
        unset($context['account_exists']); // counted via $userId, not a flag

        $cached = self::cachedAssessment($risk, $userId, $ip);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = match ($risk) {
                'login_failure' => self::assessLogin($userId, $ip, $context),
                'token_rejected' => self::assessAuthorization($ip, ['AUTH_FAILED'], (string) config('monitoring.authorization_failures.ip.medium'), (string) config('monitoring.authorization_failures.ip.high'), (string) config('monitoring.authorization_failures.ip.critical'), 'rejected tokens'),
                'authz_denied' => self::assessAuthorization($ip, ['AUTHZ_DENIED', 'ADMIN_UNAUTHORIZED', 'UPDATE_PRESENCE_UNAUTHORIZED', 'PRESENCE_HISTORY_UNAUTHORIZED', 'ONLINE_USERS_UNAUTHORIZED', 'REAL_TIME_STATS_UNAUTHORIZED', 'SEND_NOTIFICATION_UNAUTHORIZED', 'GET_NOTIFICATIONS_UNAUTHORIZED', 'WS_STATUS_UNAUTHORIZED', 'AI_ADMIN_ANALYTICS_UNAUTHORIZED'], (string) config('monitoring.authorization_failures.ip.medium'), (string) config('monitoring.authorization_failures.ip.high'), (string) config('monitoring.authorization_failures.ip.critical'), 'permission denials'),
                default => ['severity' => 'INFO', 'reason' => 'Isolated event', 'account_failures' => 0, 'ip_failures' => 0],
            };

            self::rememberAssessment($risk, $userId, $ip, $result);

            return $result;
        } catch (\Throwable $e) {
            // Assessment unavailable: keep the base severity, never fail the caller.
            return ['severity' => 'INFO', 'reason' => 'Risk assessment unavailable', 'account_failures' => 0, 'ip_failures' => 0];
        }
    }

    /**
     * Flood suppression: at most config('monitoring.flood.max_events_per_window')
     * security events per risk+source per window are written; beyond that the
     * incident is already on record and further identical rows would only be
     * storage amplification.
     */
    public static function shouldRecord(string $risk, ?string $ip): bool
    {
        $max = (int) config('monitoring.flood.max_events_per_window', 40);
        $window = (int) config('monitoring.flood.window_seconds', 300);
        $key = 'mon:flood:' . $risk . ':' . md5((string) $ip);

        try {
            Cache::add($key, 0, $window);
            $count = Cache::increment($key);

            return ! is_int($count) || $count <= $max;
        } catch (\Throwable $e) {
            return true; // fail open: recording must not depend on cache
        }
    }

    /**
     * Create or aggregate a security alert. Silently no-ops below the
     * configured alert severity. Returns the alert id or null.
     */
    public static function raiseAlert(
        string $rule,
        string $subject,
        string $severity,
        string $summary,
        array $context = []
    ): ?string {
        if (self::rank($severity) < self::rank((string) config('monitoring.login_failures.min_alert_severity', 'MEDIUM'))) {
            return null;
        }

        try {
            $now = now()->toISOString(true);
            $open = SecurityAlert::where('rule', $rule)
                ->where('subject', $subject)
                ->where('status', 'open')
                ->first();

            if ($open) {
                $currentRank = self::rank((string) ($open->severity ?? 'INFO'));
                SecurityAlert::where('id', $open->id)->update([
                    'occurrence_count' => (int) ($open->occurrence_count ?? 1) + 1,
                    'last_seen' => $now,
                    'severity' => self::rank($severity) > $currentRank ? $severity : $open->severity,
                    'context' => self::encodeContext($context, $open->context),
                    'updated_at' => $now,
                ]);

                return (string) $open->id;
            }

            $row = SecurityAlert::create([
                'id' => (string) Str::uuid(),
                'rule' => $rule,
                'subject' => $subject,
                'severity' => $severity,
                'status' => 'open',
                'summary' => mb_substr($summary, 0, 480),
                'context' => self::encodeContext($context),
                'occurrence_count' => 1,
                'first_seen' => $now,
                'last_seen' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return (string) ($row->id ?? null);
        } catch (\Throwable $e) {
            // Security-critical: do not discard silently.
            Log::error('monitoring.alert_write_failed', ['rule' => $rule, 'subject' => $subject, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The pure severity ladder (unit-tested directly).
     *
     * @param array<string,int> $steps ['medium'=>3,'high'=>5,'critical'=>10]
     */
    public static function ladder(int $count, array $steps): string
    {
        if ($count >= (int) ($steps['critical'] ?? PHP_INT_MAX)) {
            return 'CRITICAL';
        }
        if ($count >= (int) ($steps['high'] ?? PHP_INT_MAX)) {
            return 'HIGH';
        }
        if ($count >= (int) ($steps['medium'] ?? PHP_INT_MAX)) {
            return 'MEDIUM';
        }
        if ($count >= 2) {
            return 'LOW';
        }

        return 'INFO';
    }

    public static function rank(string $severity): int
    {
        return self::RANK[strtoupper($severity)] ?? 0;
    }

    /** max() of two severities by rank. */
    public static function maxSeverity(string $a, string $b): string
    {
        return self::rank($b) > self::rank($a) ? strtoupper($b) : strtoupper($a);
    }

    /* ------------------------------------------------------------------ */

    private static function assessLogin(?string $userId, ?string $ip, array $context): array
    {
        $window = (int) config('monitoring.login_failures.window_minutes', 10);
        $since = now()->subMinutes($window)->toISOString(true);

        $accountFailures = 0;
        if ($userId) {
            $accountFailures = ActivityEvent::where('event_name', 'LOGIN_FAILED')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $since)
                ->count() + 1; // +1 = this attempt
        }

        $ipFailures = 0;
        if ($ip) {
            $ipFailures = ActivityEvent::where('event_name', 'LOGIN_FAILED')
                ->where('ip_address', $ip)
                ->where('created_at', '>=', $since)
                ->count() + 1;
        }

        $privileged = in_array((string) ($context['role'] ?? ''), ['admin', 'system_admin'], true);

        // Known-account ladder (privileged accounts escalate earlier), but
        // never below what the ip ladder says for the same burst: a single
        // source hammering many accounts is also suspicious.
        $accountSteps = $privileged
            ? (array) config('monitoring.login_failures.privileged', [])
            : (array) config('monitoring.login_failures.account', []);
        $ipSteps = (array) config('monitoring.login_failures.ip', []);

        // With no known account id the account bucket stays at 0 (INFO), so
        // max() naturally yields the source-only ladder for unknown accounts.
        $severity = self::maxSeverity(
            self::ladder($accountFailures, $accountSteps),
            self::ladder($ipFailures, $ipSteps)
        );

        $reason = sprintf(
            '%d failed login(s) for this account and %d from this source in the last %d minutes%s',
            $accountFailures,
            $ipFailures,
            $window,
            $privileged ? ' (privileged account)' : ''
        );

        return [
            'severity' => $severity,
            'reason' => $reason,
            'account_failures' => $accountFailures,
            'ip_failures' => $ipFailures,
        ];
    }

    private static function assessAuthorization(?string $ip, array $eventNames, string $medium, string $high, string $critical, string $noun): array
    {
        $window = (int) config('monitoring.authorization_failures.window_minutes', 10);
        $since = now()->subMinutes($window)->toISOString(true);

        $count = 0;
        if ($ip) {
            // Sum per event name (the REST client ANDs wheres, so one query
            // per name keeps this exact without an OR shape it cannot build).
            foreach ($eventNames as $name) {
                $count += ActivityEvent::where('created_at', '>=', $since)
                    ->where('ip_address', $ip)
                    ->where('event_name', $name)
                    ->count();
            }
        }
        $count += 1; // this occurrence

        $severity = self::ladder($count, ['medium' => (int) $medium, 'high' => (int) $high, 'critical' => (int) $critical]);
        $reason = sprintf('%d %s from this source in the last %d minutes', $count, $noun, $window);

        return [
            'severity' => $severity,
            'reason' => $reason,
            'account_failures' => 0,
            'ip_failures' => $count,
        ];
    }

    private static function cachedAssessment(string $risk, ?string $userId, ?string $ip): ?array
    {
        $seconds = (int) config('monitoring.flood.assess_cache_seconds', 30);

        try {
            $value = Cache::get(self::assessmentKey($risk, $userId, $ip));

            return is_array($value) ? $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function rememberAssessment(string $risk, ?string $userId, ?string $ip, array $result): void
    {
        $seconds = (int) config('monitoring.flood.assess_cache_seconds', 30);

        try {
            Cache::put(self::assessmentKey($risk, $userId, $ip), $result, $seconds);
        } catch (\Throwable $e) {
            // cache unavailable: next event just re-counts
        }
    }

    private static function assessmentKey(string $risk, ?string $userId, ?string $ip): string
    {
        return 'mon:assess:' . $risk . ':' . md5((string) $userId) . ':' . md5((string) $ip);
    }

    /** Merge a fresh context into the stored one, keeping it bounded. */
    private static function encodeContext(array $context, ?string $existing = null): ?string
    {
        $merged = [];
        if ($existing) {
            $previous = json_decode($existing, true);
            $merged = is_array($previous) ? $previous : [];
        }
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $merged[$key] = $value;
            }
        }

        $json = json_encode(array_slice($merged, 0, 30, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? null : mb_substr($json, 0, 4000);
    }
}
