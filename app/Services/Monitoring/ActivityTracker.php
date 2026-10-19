<?php

namespace App\Services\Monitoring;

use App\Models\ActivityEvent;
use App\Models\SessionActivity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The single write path for activity_events (Phase 2/3/5 engine).
 *
 * Trust rules enforced here:
 *  - the actor (user_id) only ever comes from server-side authentication
 *    state (jwt attributes or a verified JWT), never from client payloads;
 *  - ip/user-agent come from the server-side request; any client-supplied
 *    forwarding hint is kept only as a labelled, untrusted observation;
 *  - timestamps are always server-generated;
 *  - metadata passes a redaction + size pipeline before storage (passwords,
 *    tokens, cookies, secrets never survive it);
 *  - a monitoring failure never breaks the caller: HIGH/CRITICAL write
 *    failures are forwarded to the framework log (not silently discarded),
 *    everything else degrades quietly.
 */
class ActivityTracker
{
    /** Keys whose VALUES are always replaced before storage. */
    private const SENSITIVE_KEY_PATTERN = '/password|passwd|pwd|token|secret|apikey|api[_-]?key|authorization|cookie|otp|reset[_-]?code|verification[_-]?token|private[_-]?key|card[_-]?number|cvv|\bpin\b|iban|account[_-]?number|\bssn\b/i';

    private const SEVERITIES = ['INFO', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
    private const OUTCOMES = ['success', 'failed', 'rejected', 'denied', 'observed'];
    private const CATEGORY_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/';
    private const EVENT_PATTERN = '/^[A-Z][A-Z0-9_]{0,63}$/';
    private const CORRELATION_PATTERN = '/^[A-Za-z0-9-]{8,64}$/';
    private const REF_PATTERN = '/^[A-Za-z0-9-]{8,64}$/';

    /* ------------------------------------------------------------------ */
    /* Pure normalization helpers (unit-tested directly)                  */
    /* ------------------------------------------------------------------ */

    /**
     * Single-line, length-capped, control-character-free string. Prevents
     * log forging through embedded newlines and keeps columns bounded.
     */
    public static function sanitizeString($value, int $maxChars = 256): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value);

        return mb_strlen($value) > $maxChars ? mb_substr($value, 0, $maxChars) : $value;
    }

    /**
     * Recursive redaction + bounding. Returns a JSON string ready for the
     * metadata column, or null when nothing safe remains.
     *
     * - values under sensitive keys become "[redacted]";
     * - depth, breadth and per-string length are capped;
     * - the final JSON is byte-capped (drop entries until it fits).
     */
    public static function sanitizeMetadata($metadata, int $maxBytes = 2048): ?string
    {
        if ($metadata === null || $metadata === '' || $metadata === []) {
            return null;
        }

        if (is_string($metadata)) {
            // Legacy callers sometimes hand over an encoded object.
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : ['value' => $metadata];
        }
        if (! is_array($metadata)) {
            return null;
        }

        $clean = self::sanitizeNode($metadata, 0, true);
        if ($clean === null || $clean === []) {
            return null;
        }

        $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return null;
        }
        if (strlen($json) <= $maxBytes) {
            return $json;
        }

        // Too large: keep the leading entries that fit, marked as truncated.
        $entries = is_array($clean) ? $clean : [];
        $kept = [];
        foreach ($entries as $key => $value) {
            $candidate = array_merge($kept, [$key => $value]);
            $encoded = json_encode($candidate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false && strlen($encoded) + 40 <= $maxBytes) {
                $kept = $candidate;
            } else {
                break;
            }
        }
        if ($kept === []) {
            return null;
        }
        $kept['_truncated'] = true;

        $json = json_encode($kept, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? null : $json;
    }

    /** sha256 of the presented JWT, truncated: a safe session reference. */
    public static function sessionRef(?string $token): ?string
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        return substr(hash('sha256', $token), 0, 32);
    }

    /* ------------------------------------------------------------------ */
    /* Server-side request context                                        */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{
     *   ip:?string, ip_source:string, forwarded_for:?string,
     *   user_agent:?string, correlation_id:?string, session_ref:?string
     * }
     */
    public static function requestContext(): array
    {
        try {
            $request = request();
        } catch (\Throwable $e) {
            return ['ip' => null, 'ip_source' => 'unknown', 'forwarded_for' => null, 'user_agent' => null, 'correlation_id' => null, 'session_ref' => null];
        }

        $ipSource = config('monitoring.trusted_proxies') ? 'trusted_proxy' : 'remote_addr';

        $forwarded = null;
        $xff = (string) $request->header('X-Forwarded-For', '');
        if ($xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                $forwarded = $first;
            }
        }

        $correlation = (string) $request->header('X-Correlation-Id', '');
        if (! preg_match(self::CORRELATION_PATTERN, $correlation)) {
            $correlation = null;
        }

        $token = self::bearerToken($request);

        return [
            'ip' => $request->ip(),
            'ip_source' => $ipSource,
            'forwarded_for' => $forwarded,
            'user_agent' => self::sanitizeString($request->userAgent(), 256),
            'correlation_id' => $correlation,
            'session_ref' => self::sessionRef($token),
        ];
    }

    public static function bearerToken($request): ?string
    {
        try {
            $header = (string) $request->header('Authorization', '');
        } catch (\Throwable $e) {
            return null;
        }
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Recording                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Record one normalized event. Returns the event id, or null when the
     * event was disabled, suppressed by flood control, or the write failed.
     *
     * Expected keys (all optional except `event`):
     * user_id, category, event, description, page, route, element,
     * entity_type, entity_id, source, outcome, severity, severity_reason,
     * is_security, risk, metadata, session_ref, correlation_id, ip,
     * user_agent, related_event_id.
     */
    public static function record(array $event): ?string
    {
        if (! config('monitoring.enabled', true)) {
            return null;
        }

        try {
            $context = self::requestContext();

            $event['event'] = self::normalizeEventName((string) ($event['event'] ?? ''));
            if ($event['event'] === '') {
                return null;
            }

            $isSecurity = (bool) ($event['is_security'] ?? false);
            $risk = is_string($event['risk'] ?? null) && $event['risk'] !== '' ? $event['risk'] : null;
            $ip = self::sanitizeString($event['ip'] ?? $context['ip'], 45) ?: $context['ip'];

            // Flood control only gates FAILURE risks (repeated rejections).
            if ($risk !== null && in_array($risk, ['login_failure', 'token_rejected', 'authz_denied'], true)) {
                if (! SecurityMonitor::shouldRecord($risk, $ip)) {
                    return null;
                }
            }

            $severity = strtoupper((string) ($event['severity'] ?? 'INFO'));
            if (! in_array($severity, self::SEVERITIES, true)) {
                $severity = 'INFO';
            }
            $severityReason = self::sanitizeString($event['severity_reason'] ?? null, 240);

            if ($isSecurity && $risk !== null) {
                $assessment = SecurityMonitor::assess(
                    $risk,
                    isset($event['user_id']) ? (string) $event['user_id'] : null,
                    $ip,
                    (array) ($event['risk_context'] ?? [])
                );
                $severity = SecurityMonitor::maxSeverity($severity, $assessment['severity']);
                $severityReason = $severityReason ?: $assessment['reason'];
            } elseif ($isSecurity && ! $severityReason) {
                $severityReason = 'Security-relevant event';
            }

            $outcome = (string) ($event['outcome'] ?? 'observed');
            if (! in_array($outcome, self::OUTCOMES, true)) {
                $outcome = 'observed';
            }
            $category = (string) ($event['category'] ?? 'system');
            if (! preg_match(self::CATEGORY_PATTERN, $category) || ! in_array($category, EventClassifier::CATEGORIES, true)) {
                $category = 'system';
            }

            $metadata = (array) ($event['metadata'] ?? []);
            if (isset($context['ip_source']) || $context['forwarded_for']) {
                $metadata['ip_source'] = $context['ip_source'];
                if ($context['forwarded_for'] && $context['forwarded_for'] !== $ip) {
                    // Explicitly untrusted: a client can send this header.
                    $metadata['forwarded_for_untrusted'] = $context['forwarded_for'];
                }
            }

            $id = (string) Str::uuid();
            $row = [
                'id' => $id,
                'user_id' => isset($event['user_id']) && $event['user_id'] !== '' ? self::sanitizeString($event['user_id'], 64) : null,
                'session_ref' => self::sanitizeString($event['session_ref'] ?? $context['session_ref'], 64),
                'correlation_id' => self::sanitizeString($event['correlation_id'] ?? $context['correlation_id'], 64),
                'category' => $category,
                'event_name' => $event['event'],
                'description' => self::sanitizeString($event['description'] ?? null, 400),
                'page' => self::sanitizeString($event['page'] ?? null, 120),
                'route' => self::sanitizeRoute($event['route'] ?? null),
                'element' => self::sanitizeString($event['element'] ?? null, 120),
                'entity_type' => self::sanitizeString($event['entity_type'] ?? null, 48),
                'entity_id' => self::sanitizeString($event['entity_id'] ?? null, 64),
                'source' => self::sanitizeString($event['source'] ?? 'server', 16) ?: 'server',
                'outcome' => $outcome,
                'severity' => $severity,
                'severity_reason' => $severityReason,
                'is_security' => $isSecurity,
                'metadata' => self::sanitizeMetadata($metadata, (int) config('monitoring.ingest.max_metadata_bytes', 2048)),
                'ip_address' => $ip,
                'user_agent' => self::sanitizeString($event['user_agent'] ?? $context['user_agent'], 256),
                'device' => null,
                'related_event_id' => self::sanitizeString($event['related_event_id'] ?? null, 64),
                'created_at' => now()->toISOString(true), // server-generated
            ];

            $ua = $row['user_agent'];
            if ($ua) {
                $device = UserAgentInfo::parse($ua);
                $row['device'] = mb_substr(json_encode($device, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}', 0, 1000);
            }

            if (! self::insertRow($row, $severity)) {
                return null;
            }

            // Successful authentication starts a session (Phase 5).
            if ($event['event'] === 'LOGIN_SUCCESS' && ! empty($event['user_id'])) {
                $token = null;
                try {
                    $token = self::bearerToken(request());
                } catch (\Throwable $e) {
                    // login requests do not carry a bearer token; the freshly
                    // issued one is passed explicitly by the caller instead.
                }
                if (! $token && ! empty($event['token'])) {
                    $token = (string) $event['token'];
                }
                if ($token) {
                    self::startSession((string) $event['user_id'], $token, $row['user_agent'], $ip);
                }
            }

            // Raise/aggregate the incident alert AFTER the event is stored so
            // the alert never references a record that does not exist.
            if ($isSecurity && $risk !== null) {
                self::maybeRaiseAlert($risk, $event, $severity, $severityReason, $ip);
            }

            return $id;
        } catch (\Throwable $e) {
            self::reportWriteFailure($e, (string) ($event['event'] ?? '?'), (string) ($event['severity'] ?? 'INFO'));

            return null;
        }
    }

    /**
     * Bridge from the existing AuditLogger::log() stream. Keeps the legacy
     * user_logs row untouched and adds a normalized projection for MEANINGFUL
     * actions only (reads/probes return early in the classifier).
     */
    public static function mirrorLegacy(
        ?string $userId,
        string $action,
        string $endpoint,
        array $details,
        ?string $ip,
        string $status,
        ?array $context = null
    ): void {
        if (! config('monitoring.enabled', true)) {
            return;
        }

        try {
            $classified = EventClassifier::classify($action, $status, $details, $endpoint);
            if ($classified === null) {
                return;
            }

            $context = $context ?? [];
            $requestContext = self::requestContext();

            // Failure risks need the attempted identifier (alert subject) and
            // the targeted role (earlier escalation for privileged accounts).
            // Both are already present in the legacy details for auth actions.
            $risk = array_key_exists('risk', $context) ? $context['risk'] : $classified['risk'];
            $riskContext = $context['risk_context'] ?? [];
            if ($risk !== null && $riskContext === []) {
                $riskContext = [
                    'role' => $details['role'] ?? null,
                    'identifier' => $details['email'] ?? $details['attempted_identifier'] ?? null,
                ];
            }

            $metadata = $details;
            if (isset($context['metadata']) && is_array($context['metadata'])) {
                $metadata = array_merge($metadata, $context['metadata']);
            }
            if ($ip && $requestContext['ip'] && $ip !== $requestContext['ip']) {
                // Legacy call sites pass the raw X-Forwarded-For value; keep it
                // labelled rather than presenting it as the trusted source.
                $metadata['legacy_ip'] = $ip;
            }

            self::record([
                'user_id' => $userId,
                'category' => (string) ($context['category'] ?? $classified['category']),
                'event' => (string) ($context['event'] ?? $classified['event']),
                'description' => (string) ($context['description'] ?? $classified['description']),
                'page' => (string) ($context['page'] ?? ($classified['page'] ?? '')) ?: null,
                'route' => (string) ($context['route'] ?? ($classified['route'] ?? '')) ?: null,
                'element' => $context['element'] ?? null,
                'entity_type' => $context['entity_type'] ?? $classified['entity_type'],
                'entity_id' => $context['entity_id'] ?? $classified['entity_id'],
                'source' => (string) ($context['source'] ?? 'server'),
                'outcome' => (string) ($context['outcome'] ?? $classified['outcome']),
                'severity' => (string) ($context['severity'] ?? $classified['severity']),
                'severity_reason' => $context['severity_reason'] ?? null,
                'is_security' => (bool) ($context['is_security'] ?? $classified['is_security']),
                'risk' => $risk,
                'risk_context' => $riskContext,
                'metadata' => $metadata,
                'session_ref' => $requestContext['session_ref'],
                'correlation_id' => $context['correlation_id'] ?? $requestContext['correlation_id'],
                'ip' => $requestContext['ip'],
                'user_agent' => $requestContext['user_agent'],
            ]);
        } catch (\Throwable $e) {
            self::reportWriteFailure($e, $action, 'INFO');
        }
    }

    /**
     * Build one validated, sanitized row for a BROWSER-observed event.
     * Returns null when the event must be dropped (invalid/oversized).
     * Caller derives the actor separately - a client can never set user_id.
     */
    public static function buildBrowserRow(array $event, ?string $userId, ?string $sessionRef, ?string $ip, ?string $ua, string $ipSource): ?array
    {
        $name = self::normalizeEventName((string) ($event['event'] ?? ''));
        if ($name === '' || ! preg_match(self::EVENT_PATTERN, $name)) {
            return null;
        }

        $category = (string) ($event['category'] ?? '');
        if (! preg_match(self::CATEGORY_PATTERN, $category) || ! in_array($category, EventClassifier::CATEGORIES, true)) {
            return null;
        }

        $outcome = (string) ($event['outcome'] ?? 'observed');
        if (! in_array($outcome, self::OUTCOMES, true)) {
            $outcome = 'observed';
        }

        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $metadata['ip_source'] = $ipSource;
        if (isset($event['client_ts'])) {
            // Advisory only; created_at below stays server-generated.
            $metadata['client_ts'] = self::sanitizeString($event['client_ts'], 40);
        }

        $row = [
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'session_ref' => $sessionRef,
            'correlation_id' => null,
            'category' => $category,
            'event_name' => $name,
            'description' => self::sanitizeString($event['description'] ?? null, (int) config('monitoring.ingest.max_description_chars', 400)),
            'page' => self::sanitizeString($event['page'] ?? null, 120),
            'route' => self::sanitizeRoute($event['route'] ?? null),
            'element' => self::sanitizeString($event['element'] ?? null, 120),
            'entity_type' => self::sanitizeString($event['entity_type'] ?? null, 48),
            'entity_id' => self::sanitizeString($event['entity_id'] ?? null, 64),
            'source' => 'browser',
            'outcome' => $outcome,
            'severity' => 'INFO',
            'severity_reason' => 'Browser-observed interaction (not a server confirmation)',
            'is_security' => false,
            'metadata' => self::sanitizeMetadata($metadata, (int) config('monitoring.ingest.max_metadata_bytes', 2048)),
            'ip_address' => $ip,
            'user_agent' => $ua,
            'device' => null,
            'related_event_id' => null,
            'created_at' => now()->toISOString(true),
        ];

        if ($ua) {
            $row['device'] = mb_substr(json_encode(UserAgentInfo::parse($ua), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}', 0, 1000);
        }

        return $row;
    }

    /** Bulk insert pre-built rows in ONE request. Returns false on failure. */
    public static function insertRows(array $rows): bool
    {
        if ($rows === []) {
            return true;
        }
        try {
            ActivityEvent::query()->insert(array_values($rows));

            return true;
        } catch (\Throwable $e) {
            $maxSeverity = 'INFO';
            foreach ($rows as $row) {
                if (SecurityMonitor::rank((string) ($row['severity'] ?? 'INFO')) > SecurityMonitor::rank($maxSeverity)) {
                    $maxSeverity = (string) $row['severity'];
                }
            }
            self::reportWriteFailure($e, 'BATCH_INGEST', $maxSeverity);

            return false;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Session lifecycle (Phase 5)                                         */
    /* ------------------------------------------------------------------ */

    /** Called on LOGIN_SUCCESS with the freshly issued token. */
    public static function startSession(string $userId, string $token, ?string $ua, ?string $ip): ?string
    {
        $ref = self::sessionRef($token);
        if (! $ref) {
            return null;
        }

        try {
            $now = now()->toISOString(true);
            $existing = SessionActivity::where('session_ref', $ref)->first();
            if ($existing) {
                SessionActivity::where('id', $existing->id)->update([
                    'last_seen_at' => $now,
                    'status' => 'active',
                    'ended_at' => null,
                    'end_reason' => null,
                    'updated_at' => $now,
                ]);

                return $ref;
            }

            SessionActivity::create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'session_ref' => $ref,
                'started_at' => $now,
                'last_seen_at' => $now,
                'status' => 'active',
                'ip_address' => $ip,
                'user_agent' => self::sanitizeString($ua, 256),
                'device' => self::deviceJson($ua),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $ref;
        } catch (\Throwable $e) {
            self::reportWriteFailure($e, 'SESSION_START', 'INFO');

            return null;
        }
    }

    /**
     * Server-observed activity for an authenticated request (API heartbeat),
     * throttled to at most one write per session per heartbeat_min_seconds.
     * Creates the row on first sight for tokens issued before this feature.
     */
    public static function touchSession(?string $userId, ?string $token, ?string $ua, ?string $ip): void
    {
        $ref = self::sessionRef($token);
        if (! $ref || ! $userId) {
            return;
        }

        $seconds = (int) config('monitoring.session.heartbeat_min_seconds', 60);
        try {
            if (! Cache::add('mon:hb:' . $ref, 1, $seconds)) {
                return; // heartbeat already refreshed within the window
            }
        } catch (\Throwable $e) {
            // cache unavailable: still throttle by simply proceeding
        }

        try {
            $now = now()->toISOString(true);
            $existing = SessionActivity::where('session_ref', $ref)->first();

            if ($existing) {
                $updates = ['last_seen_at' => $now, 'updated_at' => $now];
                if (($existing->ip_address ?? null) && $ip && $existing->ip_address !== $ip) {
                    $updates['ip_address'] = $ip; // current address; changes visible in events
                }
                SessionActivity::where('id', $existing->id)->update($updates);

                return;
            }

            // First observed activity for this token: started_at honestly
            // means "first seen", not "logged in" (login predates the feature).
            SessionActivity::create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'session_ref' => $ref,
                'started_at' => $now,
                'last_seen_at' => $now,
                'status' => 'active',
                'ip_address' => $ip,
                'user_agent' => self::sanitizeString($ua, 256),
                'device' => self::deviceJson($ua),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            self::reportWriteFailure($e, 'SESSION_TOUCH', 'INFO');
        }
    }

    /** Client-reported session end (browser logout); labelled as such. */
    public static function endSession(?string $sessionRef, string $reason = 'client_reported'): void
    {
        if (! $sessionRef) {
            return;
        }

        try {
            $now = now()->toISOString(true);
            SessionActivity::where('session_ref', $sessionRef)
                ->where('status', 'active')
                ->update([
                    'status' => 'ended',
                    'ended_at' => $now,
                    'end_reason' => $reason,
                    'updated_at' => $now,
                ]);
        } catch (\Throwable $e) {
            self::reportWriteFailure($e, 'SESSION_END', 'INFO');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /** Uppercase token safe for the event_name column, or '' when invalid. */
    public static function normalizeEventName(string $name): string
    {
        $name = strtoupper(preg_replace('/[^A-Za-z0-9_]+/', '_', trim($name)) ?? '');
        $name = trim($name, '_');
        if ($name === '' || ! preg_match(self::EVENT_PATTERN, $name)) {
            return '';
        }

        return $name;
    }

    /** Path-only route: query strings (reset tokens, codes) never stored. */
    public static function sanitizeRoute($route): ?string
    {
        $route = self::sanitizeString($route, 200);
        if ($route === null || $route === '') {
            return null;
        }
        $path = parse_url($route, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        return mb_substr('/' . ltrim($path, '/'), 0, 160);
    }

    private static function insertRow(array $row, string $severity): bool
    {
        try {
            ActivityEvent::query()->insert($row);

            return true;
        } catch (\Throwable $e) {
            self::reportWriteFailure($e, (string) $row['event_name'], $severity);

            return false;
        }
    }

    private static function maybeRaiseAlert(string $risk, array $event, string $severity, ?string $reason, ?string $ip): void
    {
        $map = [
            'login_failure' => 'login_failure',
            'token_rejected' => 'token_rejected',
            'authz_denied' => 'authorization_denied',
        ];
        $rule = $map[$risk] ?? null;
        if (! $rule) {
            return;
        }

        $userId = isset($event['user_id']) && $event['user_id'] !== '' ? (string) $event['user_id'] : null;
        $attempted = (string) ($event['risk_context']['identifier'] ?? '');
        $subject = $userId
            ? 'user:' . $userId
            : ($attempted !== '' ? 'acct:' . mb_strtolower($attempted) : ($ip ? 'ip:' . $ip : 'unknown'));

        SecurityMonitor::raiseAlert(
            $rule,
            $subject,
            $severity,
            sprintf('%s (%s)', EventClassifier::label((string) ($event['event'] ?? $rule)), $reason ?? 'repeated failures'),
            [
                'event' => $event['event'] ?? null,
                'ip' => $ip,
                'identifier' => $attempted !== '' ? mb_substr($attempted, 0, 120) : null,
                'role' => $event['risk_context']['role'] ?? null,
                'reason' => $reason,
            ]
        );
    }

    /** Structured failure reporting: HIGH/CRITICAL never silently dropped. */
    private static function reportWriteFailure(\Throwable $e, string $event, string $severity): void
    {
        try {
            if (SecurityMonitor::rank($severity) >= SecurityMonitor::rank('HIGH')) {
                Log::error('monitoring.event_write_failed', [
                    'event' => $event,
                    'severity' => $severity,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            // Rate-limited warning so an outage does not flood the log.
            if (Cache::add('mon:write_fail_warn', 1, 60)) {
                Log::warning('monitoring.event_write_failed', [
                    'event' => $event,
                    'severity' => $severity,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $ignored) {
            // reporting must never throw
        }
    }

    private static function deviceJson(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }

        return mb_substr(json_encode(UserAgentInfo::parse($ua), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}', 0, 1000);
    }

    /** @param mixed $node @param bool $isRoot */
    private static function sanitizeNode($node, int $depth, bool $isRoot = false)
    {
        if ($depth > 4) {
            return '[truncated]';
        }

        if (is_array($node)) {
            $clean = [];
            $count = 0;
            foreach ($node as $key => $value) {
                if (++$count > 40) {
                    $clean['_truncated'] = true;
                    break;
                }
                $keyString = self::sanitizeString((string) $key, 80) ?? '';
                if ($keyString === '') {
                    continue;
                }
                if (preg_match(self::SENSITIVE_KEY_PATTERN, $keyString)) {
                    $clean[$keyString] = '[redacted]';
                    continue;
                }
                $clean[$keyString] = self::sanitizeNode($value, $depth + 1);
            }

            return $clean;
        }

        if (is_string($node)) {
            return self::sanitizeString($node, 512);
        }
        if (is_int($node) || is_float($node) || is_bool($node)) {
            return $node;
        }
        if ($node === null) {
            return null;
        }

        // Objects (e.g. decoded stdClass) -> array view.
        if (is_object($node)) {
            return self::sanitizeNode(get_object_vars($node), $depth + 1);
        }

        return null;
    }
}
