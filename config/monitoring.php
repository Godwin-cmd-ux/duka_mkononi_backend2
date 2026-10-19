<?php

/*
|--------------------------------------------------------------------------
| User Activity Monitoring & Security Audit configuration
|--------------------------------------------------------------------------
|
| Central knobs for the monitoring system (see MONITORING.md). Everything
| has a safe default so no environment variable is required; the env keys
| below only let an operator tune thresholds without a code change.
|
| Nothing here ever contains credentials - thresholds and retention windows
| only.
|
*/

return [

    // Master switch for BROWSER event ingestion and server-side mirroring.
    // Admin dashboards still work (they just show no new rows when off).
    'enabled' => (bool) env('MONITORING_ENABLED', true),

    /*
    | Trusted reverse proxies (comma-separated IPs, CIDRs, or '*'). When the
    | app runs behind a proxy/load balancer, list it here so Laravel's
    | TrustProxies middleware resolves the REAL client address for
    | $request->ip(). Without it, monitoring records the address it can
    | actually see (the proxy) and keeps any X-Forwarded-For value only as a
    | labelled untrusted observation - a client-supplied forwarding header is
    | never trusted on its own (Phase 5).
    */
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))),

    'ingest' => [
        // Hard caps enforced server-side on POST /api/monitoring/events.
        'max_events_per_request' => 25,
        'max_payload_bytes' => 32768,
        'max_metadata_bytes' => 2048,
        'max_string_chars' => 256,
        'max_description_chars' => 400,
        // A browser flush window (~every 12s) = ~5 requests/minute/user;
        // 120/min per IP leaves headroom for users behind a shared NAT.
        'per_minute_limit' => 120,
    ],

    /*
    | Repeated-failure detection (Phase 4). Counts are evaluated inside a
    | sliding window. `account` counts failures against a KNOWN account id,
    | `ip` counts failures from one source address. NAT/shared-network note:
    | the ip bucket deliberately needs a higher threshold before escalating
    | so one office or carrier NAT does not paint a single user as malicious.
    | Privileged roles (admin / system_admin) escalate earlier.
    */
    'login_failures' => [
        'window_minutes' => 10,
        // count -> severity: 1-2 failures = INFO/LOW, then these steps.
        'account' => ['medium' => 3, 'high' => 5, 'critical' => 10],
        'privileged' => ['medium' => 2, 'high' => 3, 'critical' => 5],
        'ip' => ['medium' => 6, 'high' => 12, 'critical' => 30],
        // An alert is raised at this severity and above.
        'min_alert_severity' => 'MEDIUM',
    ],

    'authorization_failures' => [
        'window_minutes' => 10,
        'ip' => ['medium' => 5, 'high' => 15, 'critical' => 40],
        'min_alert_severity' => 'MEDIUM',
    ],

    // Flood suppression: per source+kind buckets kept in cache so a burst
    // of rejected requests cannot turn into a burst of database writes.
    'flood' => [
        'window_seconds' => 300,
        'max_events_per_window' => 40,
        // How often (seconds) the risk assessment may re-count the window.
        'assess_cache_seconds' => 30,
    ],

    'session' => [
        // API heartbeat (server-observed activity) write throttle per session.
        'heartbeat_min_seconds' => 60,
        // Browser-side heartbeat cadence sent with dm-monitor.
        'browser_heartbeat_seconds' => 60,
    ],

    /*
    | Retention (Phase 9). Days are counted from created_at. Security events
    | and alerts are NOT auto-deleted unless delete_security is true - they
    | are the records most likely to be needed for investigations, and
    | deleting them automatically is an organizational decision, not a
    | technical default. Every prune run is itself audited.
    */
    'retention' => [
        'activity_days' => (int) env('MONITORING_RETENTION_ACTIVITY_DAYS', 180),
        'browser_event_days' => (int) env('MONITORING_RETENTION_BROWSER_DAYS', 90),
        'session_days' => (int) env('MONITORING_RETENTION_SESSION_DAYS', 120),
        'security_days' => (int) env('MONITORING_RETENTION_SECURITY_DAYS', 365),
        'delete_security' => (bool) env('MONITORING_RETENTION_DELETE_SECURITY', false),
        'batch_limit' => 1000,
        'max_batches_per_run' => 50,
    ],
];
