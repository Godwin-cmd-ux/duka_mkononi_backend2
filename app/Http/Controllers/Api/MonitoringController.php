<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityEvent;
use App\Models\SecurityAlert;
use App\Models\SessionActivity;
use App\Models\User;
use App\Services\JwtToken;
use App\Services\Monitoring\ActivityTracker;
use App\Services\Monitoring\EventClassifier;
use App\Services\Monitoring\MonitoringSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Monitoring endpoints (Phases 2-7).
 *
 * POST /api/monitoring/events           public, throttled browser ingestion
 * GET  /api/admin/monitoring/*          admin-only investigation surface
 *
 * Security posture:
 *  - every admin endpoint re-checks the server-side role (a hidden menu item
 *    is never the control) and audits unauthorized attempts;
 *  - ingest derives the actor ONLY from a verified JWT - a payload can never
 *    attribute events to another user;
 *  - all client input is validated/allow-listed/sanitized before storage and
 *    routes are stored path-only (no query strings, no reset tokens);
 *  - monitoring responses never echo SQL, stack traces or internal messages;
 *    when the monitoring tables are missing the endpoints answer
 *    {unavailable: true} with a generic, actionable message;
 *  - exports and incident status changes are themselves audited.
 */
class MonitoringController extends BaseController
{
    /* ================================================================ */
    /* Browser event ingestion (public + throttled)                      */
    /* ================================================================ */

    public function ingest(Request $request): JsonResponse
    {
        if (! config('monitoring.enabled', true)) {
            return $this->json(['accepted' => 0, 'rejected' => 0, 'disabled' => true]);
        }

        $maxBytes = (int) config('monitoring.ingest.max_payload_bytes', 32768);
        if (strlen((string) $request->getContent()) > $maxBytes) {
            return $this->json(['accepted' => 0, 'rejected' => 0, 'error' => 'Payload too large'], 413);
        }

        $events = $request->input('events');
        if (! is_array($events)) {
            return $this->json(['accepted' => 0, 'rejected' => 0, 'error' => 'events must be an array'], 422);
        }

        $max = (int) config('monitoring.ingest.max_events_per_request', 25);
        $rejected = max(0, count($events) - $max);
        $events = array_slice($events, 0, $max);

        /* ---- actor: verified token only, never the payload ---- */

        $ip = $request->ip();
        $ua = ActivityTracker::sanitizeString($request->userAgent(), 256);
        $ipSource = config('monitoring.trusted_proxies') ? 'trusted_proxy' : 'remote_addr';

        $userId = null;
        $sessionRef = null;

        $token = ActivityTracker::bearerToken($request);
        if ($token) {
            try {
                $decoded = JwtToken::decode($token);
                $candidate = $decoded->userId ?? null;
                if (is_string($candidate) && $candidate !== '') {
                    $userId = mb_substr($candidate, 0, 64);
                    $sessionRef = ActivityTracker::sessionRef($token);
                    // Server-observed liveness for this session (throttled).
                    ActivityTracker::touchSession($userId, $token, $ua, $ip);
                }
            } catch (\Throwable $e) {
                // Expired/invalid token: browser events fall back to
                // anonymous attribution - they are observations, never proof.
                $userId = null;
            }
        }
        if (! $sessionRef) {
            $anon = (string) $request->input('anon_id', '');
            if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $anon)) {
                // Correlation label for an anonymous visitor only; user_id
                // stays null regardless of what the client claims.
                $sessionRef = $anon;
            }
        }

        /* ---- validate + build rows ---- */

        $rows = [];
        foreach ($events as $event) {
            if (! is_array($event)) {
                $rejected++;
                continue;
            }
            // Heartbeats only prove liveness: touch above, no stored row.
            if (($event['event'] ?? '') === 'HEARTBEAT') {
                continue;
            }
            $row = ActivityTracker::buildBrowserRow($event, $userId, $sessionRef, $ip, $ua, $ipSource);
            if ($row === null) {
                $rejected++;
                continue;
            }
            $rows[] = $row;
        }

        if (! ActivityTracker::insertRows($rows)) {
            return $this->json([
                'accepted' => 0,
                'rejected' => count($events),
                'error' => 'Collection temporarily unavailable',
            ], 503);
        }

        return $this->json(['accepted' => count($rows), 'rejected' => $rejected]);
    }

    /* ================================================================ */
    /* Admin: dashboard summary                                          */
    /* ================================================================ */

    public function summary(Request $request): JsonResponse
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        $from = $this->timeParam($request, 'from') ?? now()->subDay()->toISOString(true);
        $to = $this->timeParam($request, 'to') ?? now()->toISOString(true);

        try {
            $base = ['from' => $from, 'to' => $to];

            $data = [
                'from' => $from,
                'to' => $to,
                'total_events' => $this->eventCount($base),
                'security_events' => $this->eventCount($base + ['is_security' => 'true']),
                'logins' => $this->eventCount($base + ['event_name' => 'LOGIN_SUCCESS']),
                'failed_logins' => $this->eventCount($base + ['event_name' => 'LOGIN_FAILED']),
                'high_risk_events' => $this->eventCount($base + ['severity_in' => ['HIGH', 'CRITICAL']]),
                'critical_events' => $this->eventCount($base + ['severity_in' => ['CRITICAL']]),
                'failed_operations' => $this->eventCount($base + ['category' => 'business', 'outcome' => 'failed']),
                'admin_changes' => $this->eventCount($base + ['category' => 'admin', 'outcome' => 'success']),
                'active_sessions' => (int) SessionActivity::where('last_seen_at', '>=', $from)->count(),
                'active_users' => $this->distinctSessionUsers($from),
                'open_alerts' => (int) SecurityAlert::where('status', 'open')->count(),
                'critical_open_alerts' => (int) SecurityAlert::where('status', 'open')->where('severity', 'CRITICAL')->count(),
            ];

            return $this->json($data);
        } catch (\Throwable $e) {
            return $this->unavailable('summary', $e);
        }
    }

    /* ================================================================ */
    /* Admin: paged activity timeline                                    */
    /* ================================================================ */

    public function timeline(Request $request): JsonResponse
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        $filters = $this->readFilters($request);
        [$page, $perPage] = $this->paging($request);

        try {
            $base = ActivityEvent::query();
            $this->applyEventFilters($base, $filters);
            $total = (clone $base)->count();

            $items = (clone $base)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $rows = [];
            foreach ($items as $item) {
                $rows[] = $this->presentEvent($item->toArray());
            }
            $rows = $this->attachUsers($rows);

            return $this->json([
                'items' => $rows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'meta' => $this->filterMeta(),
            ]);
        } catch (\Throwable $e) {
            return $this->unavailable('timeline', $e);
        }
    }

    /* ================================================================ */
    /* Admin: single event with related events                           */
    /* ================================================================ */

    public function eventDetail(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        try {
            $row = ActivityEvent::where('id', mb_substr($id, 0, 64))->first();
            if (! $row) {
                return $this->json(['error' => 'Event not found'], 404);
            }

            $event = $this->presentEvent($row->toArray());
            $related = [];

            try {
                if (! empty($event['correlation_id'])) {
                    $relatedRows = ActivityEvent::where('correlation_id', $event['correlation_id'])
                        ->where('id', '!=', $event['id'])
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get();
                    foreach ($relatedRows as $r) {
                        $related[] = $this->presentEvent($r->toArray());
                    }
                } elseif (! empty($event['session_ref'])) {
                    $relatedRows = ActivityEvent::where('session_ref', $event['session_ref'])
                        ->where('id', '!=', $event['id'])
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get();
                    foreach ($relatedRows as $r) {
                        $related[] = $this->presentEvent($r->toArray());
                    }
                }
            } catch (\Throwable $e) {
                $related = []; // detail is still useful without relations
            }

            $event = $this->attachUsers([$event])[0] ?? $event;

            return $this->json(['event' => $event, 'related' => $related]);
        } catch (\Throwable $e) {
            return $this->unavailable('event_detail', $e);
        }
    }

    /* ================================================================ */
    /* Admin: user investigation                                         */
    /* ================================================================ */

    public function userTimeline(Request $request, string $userId): JsonResponse
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        $userId = mb_substr($userId, 0, 64);
        $filters = $this->readFilters($request);
        $filters['user_id'] = $userId;
        [$page, $perPage] = $this->paging($request);

        try {
            $base = ActivityEvent::query();
            $this->applyEventFilters($base, $filters);
            $total = (clone $base)->count();

            $items = (clone $base)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $rows = [];
            foreach ($items as $item) {
                $rows[] = $this->presentEvent($item->toArray());
            }
            $rows = $this->attachUsers($rows);

            $sessions = [];
            try {
                $sessionRows = SessionActivity::where('user_id', $userId)
                    ->orderByDesc('last_seen_at')
                    ->limit(20)
                    ->get();
                foreach ($sessionRows as $session) {
                    $sessions[] = $this->presentSession($session->toArray());
                }
            } catch (\Throwable $e) {
                $sessions = [];
            }

            $user = null;
            try {
                $found = User::where('id', $userId)->select('id', 'email', 'full_name', 'role', 'status')->first();
                if ($found) {
                    $user = $found->toArray();
                }
            } catch (\Throwable $e) {
                $user = null;
            }

            return $this->json([
                'items' => $rows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'sessions' => $sessions,
                'user' => $user,
                'meta' => $this->filterMeta(),
            ]);
        } catch (\Throwable $e) {
            return $this->unavailable('user_timeline', $e);
        }
    }

    /* ================================================================ */
    /* Admin: sessions                                                   */
    /* ================================================================ */

    public function sessions(Request $request): JsonResponse
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        $filters = $this->readFilters($request);
        [$page, $perPage] = $this->paging($request);

        try {
            $base = SessionActivity::query();

            if (! empty($filters['user_id'])) {
                $base->where('user_id', $filters['user_id']);
            }
            if (! empty($filters['status'])) {
                $base->where('status', $filters['status']);
            }
            if (! empty($filters['from'])) {
                $base->where('last_seen_at', '>=', $filters['from']);
            }
            if (! empty($filters['to'])) {
                $base->where('started_at', '<=', $filters['to']);
            }

            $total = (clone $base)->count();
            $items = (clone $base)
                ->orderByDesc('last_seen_at')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $rows = [];
            foreach ($items as $item) {
                $rows[] = $this->presentSession($item->toArray());
            }

            $rows = $this->attachUsers($rows, 'user_id');

            return $this->json([
                'items' => $rows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'meta' => [
                    'statuses' => ['active', 'ended', 'expired'],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->unavailable('sessions', $e);
        }
    }

    public function sessionDetail(Request $request, string $sessionRef): JsonResponse
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        $sessionRef = mb_substr($sessionRef, 0, 64);

        try {
            $row = SessionActivity::where('session_ref', $sessionRef)->first();
            if (! $row) {
                return $this->json(['error' => 'Session not found'], 404);
            }

            $session = $this->presentSession($row->toArray());
            $session = $this->attachUsers([$session], 'user_id')[0] ?? $session;

            $events = [];
            try {
                $eventRows = ActivityEvent::where('session_ref', $sessionRef)
                    ->orderBy('created_at', 'desc')
                    ->limit(100)
                    ->get();
                foreach ($eventRows as $event) {
                    $events[] = $this->presentEvent($event->toArray());
                }
                $events = $this->attachUsers($events);
            } catch (\Throwable $e) {
                $events = [];
            }

            return $this->json(['session' => $session, 'events' => $events]);
        } catch (\Throwable $e) {
            return $this->unavailable('session_detail', $e);
        }
    }

    /* ================================================================ */
    /* Admin: security incidents                                         */
    /* ================================================================ */

    public function alerts(Request $request): JsonResponse
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        $status = (string) $request->input('status', '');
        $severity = (string) $request->input('severity', '');
        [$page, $perPage] = $this->paging($request);

        try {
            $base = SecurityAlert::query();
            if (in_array($status, ['open', 'acknowledged', 'resolved'], true)) {
                $base->where('status', $status);
            }
            if (in_array($severity, EventClassifier::SEVERITIES, true)) {
                $base->where('severity', $severity);
            }

            $total = (clone $base)->count();
            $items = (clone $base)
                ->orderByDesc('last_seen')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $rows = [];
            foreach ($items as $item) {
                $rows[] = $this->presentAlert($item->toArray());
            }

            $rows = $this->attachAlertUsers($rows);

            return $this->json([
                'items' => $rows,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'meta' => [
                    'statuses' => ['open', 'acknowledged', 'resolved'],
                    'severities' => EventClassifier::SEVERITIES,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->unavailable('alerts', $e);
        }
    }

    /**
     * Acknowledge / investigate / resolve / reopen an incident. Every status
     * change is itself audited through the existing audit stream.
     */
    public function alertStatus(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        $adminId = $this->userId($request);
        $id = mb_substr($id, 0, 64);
        $status = (string) $request->input('status', '');
        $note = ActivityTracker::sanitizeString($request->input('note'), 400);
        $resolution = ActivityTracker::sanitizeString($request->input('resolution'), 400);

        if (! in_array($status, ['open', 'acknowledged', 'resolved'], true)) {
            return $this->json(['error' => 'Status must be open, acknowledged or resolved'], 422);
        }

        try {
            $alert = SecurityAlert::where('id', $id)->first();
            if (! $alert) {
                return $this->json(['error' => 'Alert not found'], 404);
            }

            $now = now()->toISOString(true);
            $updates = ['status' => $status, 'updated_at' => $now];

            if ($status === 'acknowledged') {
                if (($alert->acknowledged_at ?? null) === null) {
                    // First acknowledgement only - note re-saves must not
                    // overwrite who acknowledged it or when.
                    $updates['acknowledged_by'] = $adminId;
                    $updates['acknowledged_at'] = $now;
                }
                $updates['resolved_by'] = null;
                $updates['resolved_at'] = null;
                $updates['resolution'] = null;
            } elseif ($status === 'resolved') {
                if (($alert->resolved_at ?? null) === null) {
                    $updates['resolved_by'] = $adminId;
                    $updates['resolved_at'] = $now;
                }
                if ($resolution !== null && $resolution !== '') {
                    $updates['resolution'] = $resolution;
                }
                if (($alert->acknowledged_at ?? null) === null) {
                    $updates['acknowledged_by'] = $adminId;
                    $updates['acknowledged_at'] = $now;
                }
            } else { // reopened
                $updates['resolved_by'] = null;
                $updates['resolved_at'] = null;
                $updates['resolution'] = null;
            }

            if ($note !== null && $note !== '') {
                $notes = json_decode((string) ($alert->notes ?? ''), true);
                if (! is_array($notes)) {
                    $notes = [];
                }
                $notes[] = ['at' => $now, 'by' => $adminId, 'action' => $status, 'text' => $note];
                $notes = array_slice($notes, -20);
                $updates['notes'] = json_encode($notes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            SecurityAlert::where('id', $id)->update($updates);

            // Audit the administrative action itself (mirrored into the
            // monitoring stream by the classifier as an admin event).
            $this->log($adminId, 'SECURITY_ALERT_STATUS', '/api/admin/monitoring/alerts/' . $id . '/status', [
                'alert_id' => $id,
                'rule' => $alert->rule,
                'subject' => $alert->subject,
                'old_status' => $alert->status,
                'new_status' => $status,
                'has_note' => $note !== null && $note !== '',
            ], $this->ip($request), 'success');

            $fresh = SecurityAlert::where('id', $id)->first();
            $row = $fresh ? $this->presentAlert($fresh->toArray()) : [];
            $row = $this->attachAlertUsers([$row])[0] ?? $row;

            return $this->json(['alert' => $row]);
        } catch (\Throwable $e) {
            $this->log($adminId, 'SECURITY_ALERT_STATUS_ERROR', '/api/admin/monitoring/alerts/' . $id . '/status', [
                'alert_id' => $id,
            ], $this->ip($request), 'failed');

            return $this->error('Unable to update the alert right now', 500);
        }
    }

    /* ================================================================ */
    /* Admin: CSV export (audited, bounded)                              */
    /* ================================================================ */

    public function export(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        $filters = $this->readFilters($request);
        $limit = min(5000, max(1, (int) $request->input('limit', 2000)));

        try {
            $query = ActivityEvent::query();
            $this->applyEventFilters($query, $filters);
            $items = $query
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get();

            $rows = [];
            foreach ($items as $item) {
                $rows[] = $item->toArray();
            }
            $rows = $this->attachUsers($rows);
            $count = count($rows);

            $handle = fopen('php://temp', 'r+');
            $header = [
                'created_at', 'user_email', 'user_name', 'category', 'event',
                'description', 'page', 'route', 'element', 'entity_type', 'entity_id',
                'source', 'outcome', 'severity', 'severity_reason', 'is_security',
                'ip_address', 'device', 'session_ref', 'correlation_id', 'metadata',
            ];
            fputcsv($handle, $header);
            foreach ($rows as $row) {
                $deviceInfo = is_array($row['device'] ?? null)
                    ? $row['device']
                    : (is_string($row['device'] ?? null) ? (json_decode($row['device'], true) ?: []) : []);
                fputcsv($handle, array_map(fn ($value) => $this->csvCell($value), [
                    $row['created_at'] ?? '',
                    $row['user']['email'] ?? '',
                    $row['user']['full_name'] ?? '',
                    $row['category'] ?? '',
                    $row['event_name'] ?? '',
                    $row['description'] ?? '',
                    $row['page'] ?? '',
                    $row['route'] ?? '',
                    $row['element'] ?? '',
                    $row['entity_type'] ?? '',
                    $row['entity_id'] ?? '',
                    $row['source'] ?? '',
                    $row['outcome'] ?? '',
                    $row['severity'] ?? '',
                    $row['severity_reason'] ?? '',
                    isset($row['is_security']) && $row['is_security'] ? 'yes' : 'no',
                    $row['ip_address'] ?? '',
                    $deviceInfo['display'] ?? '',
                    $row['session_ref'] ?? '',
                    $row['correlation_id'] ?? '',
                    is_string($row['metadata'] ?? null) ? $row['metadata'] : '',
                ]));
            }
            rewind($handle);
            $csv = (string) stream_get_contents($handle);
            fclose($handle);

            // Exports are sensitive administrative actions: audit them.
            $this->log($this->userId($request), 'MONITORING_EXPORT', '/api/admin/monitoring/export', [
                'filters' => array_filter($filters),
                'row_count' => $count,
                'limit' => $limit,
            ], $this->ip($request), 'success');

            $filename = 'activity-events-' . now()->format('Ymd-His') . '.csv';

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return $this->unavailable('export', $e);
        }
    }

    /* ================================================================ */
    /* Admin: runtime table setup (project precedent)                    */
    /* ================================================================ */

    public function setup(Request $request): JsonResponse
    {
        if ($denied = $this->guardAdmin($request)) {
            return $denied;
        }

        $adminId = $this->userId($request);

        try {
            $created = MonitoringSchema::ensure();

            $this->log($adminId, 'MONITORING_SETUP', '/api/admin/monitoring/setup', [
                'created' => $created,
                'tables' => MonitoringSchema::TABLES,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'created' => $created,
                'tables' => MonitoringSchema::TABLES,
                'message' => $created === []
                    ? 'Monitoring tables already exist.'
                    : 'Created: ' . implode(', ', $created),
            ]);
        } catch (\Throwable $e) {
            $this->log($adminId, 'MONITORING_SETUP_ERROR', '/api/admin/monitoring/setup', [
                'failed' => true,
            ], $this->ip($request), 'failed');

            return $this->error('Unable to create the monitoring tables', 500);
        }
    }

    /* ================================================================ */
    /* Shared helpers                                                    */
    /* ================================================================ */

    /** Server-side role gate (never a UI concern) + unauthorized audit. */
    private function guardAdmin(Request $request): ?JsonResponse
    {
        if ($this->role($request) !== 'admin') {
            $this->log(
                $this->userId($request),
                'ADMIN_MONITORING_UNAUTHORIZED',
                '/api/admin/monitoring',
                ['reason' => 'Non-admin monitoring access attempt'],
                $this->ip($request),
                'failed'
            );

            return $this->json(['error' => 'Unauthorized'], 403);
        }

        return null;
    }

    /**
     * Generic, leak-free unavailable response for monitoring data problems
     * (typically: migrations not run yet). Never echoes the exception.
     */
    private function unavailable(string $endpoint, \Throwable $e): JsonResponse
    {
        try {
            if (\Illuminate\Support\Facades\Cache::add('mon:unavailable_warn:' . $endpoint, 1, 60)) {
                Log::warning('monitoring.endpoint_unavailable', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $ignored) {
        }

        return $this->json([
            'unavailable' => true,
            'items' => [],
            'total' => 0,
            'error' => 'Monitoring data is unavailable. Create the monitoring tables from the dashboard setup action or run the migrations.',
        ]);
    }

    /** @return array{0:int,1:int} */
    private function paging(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(1, (int) $request->input('per_page', 25)));

        return [$page, $perPage];
    }

    private function timeParam(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));
        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?(\.\d{1,3})?(Z|[+-]\d{2}:?\d{2})?)?$/', $value)
            ? $value
            : null;
    }

    /** Read + validate/normalize every timeline filter the UI may send. */
    private function readFilters(Request $request): array
    {
        // Only plain strings are ever considered: query parameters arriving
        // as arrays (user_id[]=...) are treated as absent.
        $str = function (string $key): string {
            $value = $request->input($key);

            return is_string($value) ? $value : '';
        };
        $clean = function ($value, int $max) {
            $value = ActivityTracker::sanitizeString($value, $max);

            return $value === '' ? null : $value;
        };

        $q = trim($str('q'));
        $q = $q === '' ? null : mb_substr((string) preg_replace('/[^0-9A-Za-z\s\-_.@\/]/', '', $q), 0, 64);
        if ($q === '') {
            $q = null;
        }

        $severityMin = $str('severity_min');
        $severityIn = null;
        if (in_array($severityMin, EventClassifier::SEVERITIES, true)) {
            $severityIn = array_slice(EventClassifier::SEVERITIES, array_search($severityMin, EventClassifier::SEVERITIES, true));
        }
        $severityExact = $str('severity');
        if ($severityIn === null && in_array($severityExact, EventClassifier::SEVERITIES, true)) {
            $severityIn = [$severityExact];
        }

        $category = $str('category');
        $outcome = $str('outcome');
        $source = $str('source');
        $eventName = $str('event_name');
        $sessionRef = $str('session_ref');
        $isSecurity = $str('is_security');

        return array_filter([
            'from' => $this->timeParam($request, 'from'),
            'to' => $this->timeParam($request, 'to'),
            'user_id' => $clean($str('user_id'), 64),
            'category' => in_array($category, EventClassifier::CATEGORIES, true) ? $category : null,
            'event_name' => preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $eventName) ? $eventName : null,
            'outcome' => in_array($outcome, EventClassifier::OUTCOMES, true) ? $outcome : null,
            'source' => in_array($source, ['browser', 'server'], true) ? $source : null,
            'severity_in' => $severityIn,
            'is_security' => in_array($isSecurity, ['true', 'false'], true) ? $isSecurity : null,
            'session_ref' => preg_match('/^[A-Za-z0-9-]{8,64}$/', $sessionRef) ? $sessionRef : null,
            'ip' => $clean($str('ip'), 45),
            'entity_type' => $clean($str('entity_type'), 48),
            'entity_id' => $clean($str('entity_id'), 64),
            'route' => $clean($str('route'), 160),
            'page' => $clean($str('page_name'), 120),
            'q' => $q,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function applyEventFilters($query, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (! empty($filters['event_name'])) {
            $query->where('event_name', $filters['event_name']);
        }
        if (! empty($filters['outcome'])) {
            $query->where('outcome', $filters['outcome']);
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (! empty($filters['severity_in'])) {
            $query->whereIn('severity', $filters['severity_in']);
        }
        if (isset($filters['is_security']) && in_array($filters['is_security'], ['true', 'false'], true)) {
            $query->where('is_security', $filters['is_security'] === 'true');
        }
        if (! empty($filters['session_ref'])) {
            $query->where('session_ref', $filters['session_ref']);
        }
        if (! empty($filters['ip'])) {
            $query->where('ip_address', $filters['ip']);
        }
        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }
        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }
        if (! empty($filters['route'])) {
            $query->where('route', 'like', '%' . $filters['route'] . '%');
        }
        if (! empty($filters['page'])) {
            $query->where('page', $filters['page']);
        }
        if (! empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $query->where(function ($inner) use ($term) {
                $inner->where('event_name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('page', 'like', $term);
            });
        }
    }

    private function eventCount(array $filters): int
    {
        $query = ActivityEvent::query();
        $this->applyEventFilters($query, $filters);

        return (int) $query->count();
    }

    private function distinctSessionUsers(string $from): int
    {
        $ids = [];
        foreach (SessionActivity::where('last_seen_at', '>=', $from)->select('user_id')->limit(2000)->get() as $row) {
            if (! empty($row->user_id)) {
                $ids[$row->user_id] = true;
            }
        }

        return count($ids);
    }

    /** Decode stored JSON columns for the API response. */
    private function presentEvent(array $row): array
    {
        $row['metadata'] = json_decode((string) ($row['metadata'] ?? ''), true) ?: null;
        $row['device'] = json_decode((string) ($row['device'] ?? ''), true) ?: null;
        $row['is_security'] = filter_var($row['is_security'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $row['severity'] = strtoupper((string) ($row['severity'] ?? 'INFO'));
        $row['user_id'] = $row['user_id'] ?? null;
        $row['user'] = null;

        return $row;
    }

    private function presentSession(array $row): array
    {
        $row['device'] = json_decode((string) ($row['device'] ?? ''), true) ?: null;
        $row['user'] = null;

        $start = strtotime((string) ($row['started_at'] ?? ''));
        $last = strtotime((string) ($row['last_seen_at'] ?? ''));
        $row['duration_seconds'] = ($start && $last && $last >= $start) ? ($last - $start) : null;

        return $row;
    }

    private function presentAlert(array $row): array
    {
        $row['context'] = json_decode((string) ($row['context'] ?? ''), true) ?: null;
        $row['notes'] = json_decode((string) ($row['notes'] ?? ''), true) ?: [];
        $row['occurrence_count'] = (int) ($row['occurrence_count'] ?? 1);
        $row['user'] = null;

        return $row;
    }

    /** One batched user lookup (same pattern as AdminController::embedLogUsers). */
    private function attachUsers(array $rows, string $field = 'user_id'): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = $row[$field] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return $rows;
        }

        try {
            $usersById = [];
            foreach (User::whereIn('id', array_keys($ids))->select('id', 'email', 'full_name', 'role')->get() as $user) {
                $usersById[$user->id] = [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'role' => $user->role,
                ];
            }
        } catch (\Throwable $e) {
            $usersById = [];
        }

        foreach ($rows as $index => $row) {
            $id = $row[$field] ?? null;
            $rows[$index]['user'] = isset($usersById[$id]) ? $usersById[$id] : null;
        }

        return $rows;
    }

    /** Alerts carry "user:<id>" subjects instead of a user_id column. */
    private function attachAlertUsers(array $rows): array
    {
        $ids = [];
        foreach ($rows as $index => $row) {
            $subject = (string) ($row['subject'] ?? '');
            $subjectUserId = str_starts_with($subject, 'user:') ? substr($subject, 5) : null;
            $rows[$index]['subject_user_id'] = $subjectUserId;
            $rows[$index]['subject_user'] = null;
            if ($subjectUserId) {
                $ids[$subjectUserId] = true;
            }
        }
        if ($ids === []) {
            return $rows;
        }

        try {
            $usersById = [];
            foreach (User::whereIn('id', array_keys($ids))->select('id', 'email', 'full_name', 'role')->get() as $user) {
                $usersById[$user->id] = [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'role' => $user->role,
                ];
            }
        } catch (\Throwable $e) {
            $usersById = [];
        }

        foreach ($rows as $index => $row) {
            $sid = $row['subject_user_id'] ?? null;
            $rows[$index]['subject_user'] = isset($usersById[$sid]) ? $usersById[$sid] : null;
        }

        return $rows;
    }

    private function filterMeta(): array
    {
        return [
            'categories' => EventClassifier::CATEGORIES,
            'severities' => EventClassifier::SEVERITIES,
            'outcomes' => EventClassifier::OUTCOMES,
            'sources' => ['browser', 'server'],
        ];
    }

    /**
     * Neutralize CSV formula injection. Applied PER CELL while building rows
     * (a mid-line cell like `=HYPERLINK(...)` is just as executable in a
     * spreadsheet as a line-leading one).
     */
    private function csvCell($value)
    {
        if (is_string($value) && preg_match('/^[\t\r\n]*[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
