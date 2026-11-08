<?php

namespace App\Services\Monitoring;

use App\Models\UserLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only views over the legacy user_logs audit stream.
 *
 * The normalized monitoring tables (activity_events, session_activity,
 * security_alerts) are optional in deployments that have not run the
 * monitoring setup. The audit stream, however, is always written by
 * AuditLogger. These helpers let the monitoring dashboard stay useful by
 * deriving timeline events, sessions and security alerts from user_logs
 * instead of returning an empty "unavailable" payload.
 *
 * Nothing here writes or mutates data; user_logs stays the source of truth.
 */
class LegacyAuditFallback
{
    private const MAX_ROWS = 3000;

    private const CACHE_SECONDS = 60;

    private const SESSION_GAP_SECONDS = 1800;

    private const NOISE_ACTIONS = [
        'AUTH_SUCCESS',
        'HEALTH_CHECK',
        'TEST_ENDPOINT',
        'LOGIN_THEME_SYNC_SKIPPED',
        'ACTIVITY_VIEW',
    ];

    /**
     * Classified events for the given dashboard filters (from/to/user_id/ip
     * are pushed to the API, the remaining dashboard filters are applied here
     * because they only exist after classification).
     */
    public function events(array $filters, int $limit = self::MAX_ROWS): array
    {
        $rows = $this->rawLogs($filters, $limit);

        $events = [];
        foreach ($rows as $row) {
            $event = $this->toEvent($row);
            if ($event !== null && $this->matchesEvent($event, $filters)) {
                $events[] = $event;
            }
        }

        return $events;
    }

    public function event(string $id): ?array
    {
        foreach (UserLog::where('id', $id)->limit(1)->get() as $row) {
            return $this->toEvent($row->toArray());
        }

        return null;
    }

    public function summary(array $filters, string $from, string $to): array
    {
        $filters['from'] = $filters['from'] ?? $from;
        $filters['to'] = $filters['to'] ?? $to;

        $events = $this->events($filters);

        $count = fn (callable $predicate): int => count(array_filter($events, $predicate));

        $sessions = $this->sessions($filters);
        $activeSessions = array_filter($sessions, fn ($s) => ($s['status'] ?? '') === 'active');
        $activeUsers = [];
        foreach ($sessions as $session) {
            if (! empty($session['user_id'])) {
                $activeUsers[$session['user_id']] = true;
            }
        }

        $alerts = $this->alerts([
            'from' => $filters['from'],
            'to' => $filters['to'],
        ], $events);
        $openAlerts = array_filter($alerts, fn ($a) => ($a['status'] ?? '') === 'open');

        return [
            'from' => $filters['from'],
            'to' => $filters['to'],
            'total_events' => count($events),
            'security_events' => $count(fn ($e) => ! empty($e['is_security'])),
            'logins' => $count(fn ($e) => ($e['event_name'] ?? '') === 'LOGIN_SUCCESS'),
            'failed_logins' => $count(fn ($e) => ($e['event_name'] ?? '') === 'LOGIN_FAILED'),
            'high_risk_events' => $count(fn ($e) => in_array($e['severity'] ?? '', ['HIGH', 'CRITICAL'], true)),
            'critical_events' => $count(fn ($e) => ($e['severity'] ?? '') === 'CRITICAL'),
            'failed_operations' => $count(fn ($e) => ($e['category'] ?? '') === 'business' && ($e['outcome'] ?? '') === 'failed'),
            'admin_changes' => $count(fn ($e) => ($e['category'] ?? '') === 'admin' && ($e['outcome'] ?? '') === 'success'),
            'active_sessions' => count($activeSessions),
            'active_users' => count($activeUsers),
            'open_alerts' => count($openAlerts),
            'critical_open_alerts' => count(array_filter($openAlerts, fn ($a) => ($a['severity'] ?? '') === 'CRITICAL')),
            'legacy_fallback' => true,
        ];
    }

    /**
     * Approximate sessions by grouping a user's audit rows into activity
     * windows separated by SESSION_GAP_SECONDS of inactivity.
     */
    public function sessions(array $filters): array
    {
        $filters['from'] = $filters['from'] ?? Carbon::now()->subDay()->toISOString();
        $filters['to'] = $filters['to'] ?? Carbon::now()->toISOString();

        $rows = array_reverse($this->rawLogs($filters));

        $groups = [];
        $lastIndexByUser = [];
        foreach ($rows as $row) {
            $userId = $row['user_id'] ?? null;
            if (! is_string($userId) || $userId === '') {
                continue;
            }
            $at = $this->ts($row['created_at'] ?? null);
            if ($at === null) {
                continue;
            }

            $index = $lastIndexByUser[$userId] ?? null;
            if ($index === null || ($at - $groups[$index]['last_ts']) > self::SESSION_GAP_SECONDS) {
                $groups[] = [
                    'user_id' => $userId,
                    'start_ts' => $at,
                    'started_at' => $row['created_at'],
                    'last_ts' => $at,
                    'last_seen_at' => $row['created_at'],
                    'ip_address' => $row['ip_address'] ?? null,
                ];
                $lastIndexByUser[$userId] = count($groups) - 1;
                continue;
            }

            $groups[$index]['last_ts'] = $at;
            $groups[$index]['last_seen_at'] = $row['created_at'];
        }

        $now = Carbon::now()->getTimestamp();
        $sessions = [];
        foreach ($groups as $group) {
            $ended = ($now - $group['last_ts']) > self::SESSION_GAP_SECONDS;
            $ref = 'legacy-' . $group['user_id'] . '-' . $group['start_ts'];
            $sessions[] = [
                'id' => $ref,
                'session_ref' => $ref,
                'user_id' => $group['user_id'],
                'started_at' => $group['started_at'],
                'last_seen_at' => $group['last_seen_at'],
                'expires_at' => null,
                'ended_at' => $ended ? $group['last_seen_at'] : null,
                'status' => $ended ? 'ended' : 'active',
                'end_reason' => null,
                'ip_address' => $group['ip_address'],
                'user_agent' => null,
                'device' => null,
                'created_at' => $group['started_at'],
                'updated_at' => $group['last_seen_at'],
                'duration_seconds' => max(0, $group['last_ts'] - $group['start_ts']),
                'user' => null,
                'legacy_fallback' => true,
            ];
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['active', 'ended', 'expired'], true)) {
            $sessions = array_values(array_filter($sessions, fn ($s) => $s['status'] === $status));
        }

        usort($sessions, fn ($a, $b) => strcmp((string) $b['last_seen_at'], (string) $a['last_seen_at']));

        return $sessions;
    }

    public function sessionDetail(string $sessionRef): ?array
    {
        if (! preg_match('/^legacy-([0-9a-fA-F-]{36})-(\d+)$/', $sessionRef, $m)) {
            return null;
        }

        $userId = $m[1];
        $startTs = (int) $m[2];
        $from = Carbon::createFromTimestamp($startTs)->toISOString();
        $to = Carbon::createFromTimestamp($startTs + 86400)->toISOString();

        $rows = array_reverse($this->rawLogs(['user_id' => $userId, 'from' => $from, 'to' => $to]));

        $sessionEvents = [];
        $startedAt = null;
        $lastSeenAt = null;
        $lastTs = $startTs;
        foreach ($rows as $row) {
            $at = $this->ts($row['created_at'] ?? null);
            if ($at === null) {
                continue;
            }
            if ($startedAt === null) {
                $startedAt = $row['created_at'];
                $lastTs = $at;
            } elseif (($at - $lastTs) > self::SESSION_GAP_SECONDS) {
                break;
            }
            $lastTs = $at;
            $lastSeenAt = $row['created_at'];

            $event = $this->toEvent($row);
            if ($event !== null) {
                $sessionEvents[] = $event;
            }
        }

        if ($startedAt === null) {
            return null;
        }

        $now = Carbon::now()->getTimestamp();
        $ended = ($now - $lastTs) > self::SESSION_GAP_SECONDS;

        return [
            'session' => [
                'id' => $sessionRef,
                'session_ref' => $sessionRef,
                'user_id' => $userId,
                'started_at' => $startedAt,
                'last_seen_at' => $lastSeenAt,
                'expires_at' => null,
                'ended_at' => $ended ? $lastSeenAt : null,
                'status' => $ended ? 'ended' : 'active',
                'end_reason' => null,
                'ip_address' => $rows[0]['ip_address'] ?? null,
                'user_agent' => null,
                'device' => null,
                'created_at' => $startedAt,
                'updated_at' => $lastSeenAt,
                'duration_seconds' => max(0, $lastTs - $startTs),
                'user' => null,
                'legacy_fallback' => true,
            ],
            'events' => $sessionEvents,
        ];
    }

    /**
     * Derive open security alerts by grouping failed/denied audit events per
     * subject (user, ip or system) and rule.
     */
    public function alerts(array $filters, ?array $events = null): array
    {
        $filters['from'] = $filters['from'] ?? Carbon::now()->subDays(30)->toISOString();
        $filters['to'] = $filters['to'] ?? Carbon::now()->toISOString();

        $events = $events ?? $this->events($filters);

        $groups = [];
        foreach ($events as $event) {
            $action = (string) ($event['event_name'] ?? '');
            $outcome = (string) ($event['outcome'] ?? '');
            $isSecurity = ! empty($event['is_security']);
            $looksSuspicious = (bool) preg_match('/FAILED|UNAUTHORIZED|FORBIDDEN|RATE_LIMIT|CSRF|LEAK_BLOCKED|SELF_DELETE/', $action);

            if (! $isSecurity && ! $looksSuspicious
                && ! in_array($outcome, ['failed', 'denied', 'rejected'], true)) {
                continue;
            }

            $userId = $event['user_id'] ?? null;
            $ip = $event['ip_address'] ?? null;
            $subject = $userId ? 'user:' . $userId : ($ip ? 'ip:' . $ip : 'system');
            $rule = $this->alertRule($action, $outcome);
            $key = $rule . '|' . $subject;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'rule' => $rule,
                    'subject' => $subject,
                    'severity' => $event['severity'] ?? 'LOW',
                    'first_seen' => $event['created_at'] ?? null,
                    'last_seen' => $event['created_at'] ?? null,
                    'occurrence_count' => 0,
                    'actions' => [],
                    'ip' => $ip,
                    'user_id' => $userId,
                ];
            }

            $groups[$key]['occurrence_count']++;
            $groups[$key]['severity'] = $this->maxSeverity($groups[$key]['severity'], $event['severity'] ?? 'LOW');
            $groups[$key]['last_seen'] = $event['created_at'] ?? $groups[$key]['last_seen'];
            $groups[$key]['actions'][$action] = true;
            if ($ip) {
                $groups[$key]['ip'] = $ip;
            }
        }

        $status = (string) ($filters['status'] ?? '');
        $severity = (string) ($filters['severity'] ?? '');

        $alerts = [];
        foreach ($groups as $key => $group) {
            $count = $group['occurrence_count'];
            $severityValue = $group['severity'];
            if ($count >= 10) {
                $severityValue = 'CRITICAL';
            } elseif ($count >= 5 && ! in_array($severityValue, ['HIGH', 'CRITICAL'], true)) {
                $severityValue = 'HIGH';
            } elseif ($count >= 2 && $severityValue === 'INFO') {
                $severityValue = 'LOW';
            }

            $summary = EventClassifier::label($group['rule']) . ' (' . $count . 'x)';

            $alerts[] = [
                'id' => 'legacy-' . substr(sha1($key), 0, 32),
                'rule' => $group['rule'],
                'subject' => $group['subject'],
                'severity' => $severityValue,
                'status' => 'open',
                'summary' => $summary,
                'context' => [
                    'occurrence_count' => $count,
                    'actions' => array_keys($group['actions']),
                    'ip' => $group['ip'],
                    'legacy_fallback' => true,
                ],
                'occurrence_count' => $count,
                'first_seen' => $group['first_seen'],
                'last_seen' => $group['last_seen'],
                'notes' => [],
                'acknowledged_by' => null,
                'acknowledged_at' => null,
                'resolved_by' => null,
                'resolved_at' => null,
                'resolution' => null,
                'created_at' => $group['first_seen'],
                'updated_at' => $group['last_seen'],
                'user_id' => $group['user_id'],
                'user' => null,
                'legacy_fallback' => true,
            ];
        }

        foreach ($alerts as $index => $alert) {
            $override = Cache::get('mon:legacy_alert:' . $alert['id']);
            if (is_array($override)) {
                $alerts[$index] = array_replace($alert, $override);
            }
        }

        if (in_array($status, ['open', 'acknowledged', 'resolved'], true)) {
            $alerts = array_values(array_filter($alerts, fn ($a) => $a['status'] === $status));
        }
        if (in_array($severity, EventClassifier::SEVERITIES, true)) {
            $alerts = array_values(array_filter($alerts, fn ($a) => $a['severity'] === $severity));
        }

        usort($alerts, fn ($a, $b) => strcmp((string) $b['last_seen'], (string) $a['last_seen']));

        return $alerts;
    }

    public function alert(string $id): ?array
    {
        foreach ($this->alerts([]) as $alert) {
            if ($alert['id'] === $id) {
                return $alert;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ */

    private function rawLogs(array $filters, int $limit = self::MAX_ROWS): array
    {
        $cacheKey = 'mon:legacy_logs:' . sha1(json_encode([$this->cacheFilters($filters), $limit]));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $rows = [];
        $attempt = 0;
        while (true) {
            try {
                $query = UserLog::query();

                if (! empty($filters['from'])) {
                    $query->where('created_at', '>=', $filters['from']);
                }
                if (! empty($filters['to'])) {
                    $query->where('created_at', '<=', $filters['to']);
                }
                if (! empty($filters['user_id'])) {
                    $query->where('user_id', $filters['user_id']);
                }
                if (! empty($filters['ip'])) {
                    $query->where('ip_address', $filters['ip']);
                }

                $rows = [];
                foreach ($query->orderByDesc('created_at')->limit($limit)->get() as $row) {
                    $rows[] = $row->toArray();
                }
                break;
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                if (++$attempt >= 2) {
                    throw $e;
                }
                usleep(400000);
            }
        }

        Cache::put($cacheKey, $rows, now()->addSeconds(self::CACHE_SECONDS));

        // The dashboard expects newest first for events, but sessions are
        // grouped chronologically. rawLogs keeps newest-first and sessions()
        // reverses locally where needed.
        return $rows;
    }

    /**
     * Round from/to to the minute so parallel dashboard requests share the
     * short-lived raw-log cache even though each computes "now" separately.
     */
    private function cacheFilters(array $filters): array
    {
        foreach (['from', 'to'] as $key) {
            if (! empty($filters[$key])) {
                $ts = strtotime((string) $filters[$key]);
                if ($ts !== false) {
                    $filters[$key] = gmdate('YmdHi', $ts);
                }
            }
        }

        ksort($filters);

        return $filters;
    }

    private function toEvent(array $row): ?array
    {
        $action = (string) ($row['action'] ?? '');
        if ($action === '' || in_array($action, self::NOISE_ACTIONS, true)) {
            return null;
        }

        $details = $row['details'] ?? [];
        if (is_string($details)) {
            $decoded = json_decode($details, true);
            $details = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($details)) {
            $details = [];
        }

        $status = (string) ($row['status'] ?? '');
        $endpoint = $row['endpoint'] ?? null;
        $classified = EventClassifier::classify($action, $status, $details, $endpoint);

        $isSecurity = $classified['is_security'] ?? false;
        if (! $isSecurity) {
            $isSecurity = (bool) preg_match('/FAILED|UNAUTHORIZED|FORBIDDEN|RATE_LIMIT|CSRF|LEAK_BLOCKED|SELF_DELETE/', $action);
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'user_id' => $row['user_id'] ?? null,
            'session_ref' => null,
            'correlation_id' => null,
            'category' => $classified['category'] ?? 'system',
            'event_name' => $classified['event'] ?? $action,
            'description' => $classified['description'] ?? EventClassifier::label($action),
            'page' => $classified['page'] ?? null,
            'route' => $classified['route'] ?? EventClassifier::route($endpoint),
            'element' => null,
            'entity_type' => $classified['entity_type'] ?? null,
            'entity_id' => $classified['entity_id'] ?? null,
            'source' => 'server',
            'outcome' => $classified['outcome'] ?? EventClassifier::outcome($status),
            'severity' => $classified['severity'] ?? 'INFO',
            'severity_reason' => null,
            'is_security' => $isSecurity,
            'metadata' => $details,
            'ip_address' => $row['ip_address'] ?? null,
            'user_agent' => null,
            'device' => null,
            'related_event_id' => null,
            'created_at' => $row['created_at'] ?? null,
            'user' => null,
            'legacy_fallback' => true,
        ];
    }

    private function matchesEvent(array $event, array $filters): bool
    {
        if (! empty($filters['category']) && ($event['category'] ?? null) !== $filters['category']) {
            return false;
        }
        if (! empty($filters['event_name']) && ($event['event_name'] ?? null) !== $filters['event_name']) {
            return false;
        }
        if (! empty($filters['outcome']) && ($event['outcome'] ?? null) !== $filters['outcome']) {
            return false;
        }
        if (! empty($filters['source']) && ($event['source'] ?? null) !== $filters['source']) {
            return false;
        }
        if (! empty($filters['severity_in']) && ! in_array($event['severity'] ?? '', $filters['severity_in'], true)) {
            return false;
        }
        if (isset($filters['is_security']) && is_string($filters['is_security'])) {
            $expected = $filters['is_security'] === 'true';
            if ((bool) ($event['is_security'] ?? false) !== $expected) {
                return false;
            }
        }
        if (! empty($filters['session_ref']) && ($event['session_ref'] ?? null) !== $filters['session_ref']) {
            return false;
        }
        if (! empty($filters['entity_type']) && ($event['entity_type'] ?? null) !== $filters['entity_type']) {
            return false;
        }
        if (! empty($filters['entity_id']) && ($event['entity_id'] ?? null) !== $filters['entity_id']) {
            return false;
        }
        if (! empty($filters['route']) && stripos((string) ($event['route'] ?? ''), (string) $filters['route']) === false) {
            return false;
        }
        if (! empty($filters['page']) && ($event['page'] ?? null) !== $filters['page']) {
            return false;
        }
        if (! empty($filters['q'])) {
            $needle = mb_strtolower((string) $filters['q']);
            $haystack = mb_strtolower(implode(' ', [
                (string) ($event['event_name'] ?? ''),
                (string) ($event['description'] ?? ''),
                (string) ($event['page'] ?? ''),
                (string) ($event['route'] ?? ''),
            ]));
            if (! str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    private function alertRule(string $action, string $outcome): string
    {
        if ($action === 'LOGIN_FAILED') {
            return 'login_failure';
        }
        if ($action === 'AUTH_FAILED') {
            return 'token_rejected';
        }
        if (str_contains($action, 'UNAUTHORIZED')) {
            return 'unauthorized_access';
        }
        if (str_contains($action, 'FORBIDDEN')) {
            return 'forbidden_access';
        }
        if (str_contains($action, 'RATE_LIMIT') || str_contains($action, 'CSRF')) {
            return 'abuse_attempt';
        }

        return $action !== '' ? $action : ('failed_' . ($outcome !== '' ? $outcome : 'event'));
    }

    private function maxSeverity(string $a, string $b): string
    {
        $rank = array_flip(EventClassifier::SEVERITIES);
        $aRank = $rank[$a] ?? 0;
        $bRank = $rank[$b] ?? 0;

        return $bRank > $aRank ? $b : $a;
    }

    private function ts($value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : $ts;
    }
}
