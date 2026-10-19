<?php

namespace App\Services\Monitoring;

/**
 * The bridge contract between the EXISTING audit stream (user_logs rows
 * written by AuditLogger::log) and the new normalized activity_events store.
 *
 * classify() is a pure function so it can be unit-tested without a database:
 *
 *   - returns null  -> the action is routine read/noise and is NOT mirrored
 *     (the legacy row still exists; nothing is deleted or rewritten);
 *   - returns array -> a normalized event: category, event name, human
 *     description, outcome, base severity, security flag, optional risk
 *     kind (which lets SecurityMonitor escalate repeated failures), and the
 *     business entity the action touched.
 *
 * The rules are prefix/pattern based so the ~300 existing action names are
 * covered without a hand-maintained per-action table; anything unrecognised
 * is deliberately NOT mirrored (never invent events).
 */
class EventClassifier
{
    /** Normalized categories used by the dashboard filters. */
    public const CATEGORIES = [
        'authentication', 'authorization', 'account', 'admin', 'business',
        'ai', 'system', 'page', 'navigation', 'input', 'control', 'search',
        'form', 'file', 'session', 'security',
    ];

    public const OUTCOMES = ['success', 'failed', 'rejected', 'denied', 'observed'];

    public const SEVERITIES = ['INFO', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];

    /** Never mirrored: per-request/read/probe noise. */
    private const SKIP_EXACT = [
        'AUTH_SUCCESS',          // one row per API request; heartbeat covers liveness
        'HEALTH_CHECK',
        'TEST_ENDPOINT',
        'LOGIN_THEME_SYNC_SKIPPED',
        'ACTIVITY_VIEW',
        'AI_REQUEST',            // mirrored below via the AI_ prefix rule instead
    ];

    /** action prefix -> entity type, with the details key that carries its id. */
    private const ENTITIES = [
        'PRODUCT' => ['product', ['product_id', 'id']],
        'SALE' => ['sale', ['sale_id', 'id']],
        'CUSTOMER' => ['customer', ['customer_id', 'id']],
        'PAYMENT' => ['payment', ['payment_id', 'id']],
        'ORDER' => ['order', ['order_id', 'id']],
        'EXPENSE' => ['expense', ['expense_id', 'id']],
        'MATANGAZO' => ['advertisement', ['matangazo_id', 'id']],
        'INQUIRY' => ['inquiry', ['inquiry_id', 'id']],
        'PROFILE' => ['user', ['user_id', 'id']],
        'ONBOARDING' => ['user', ['user_id', 'id']],
        'ADMIN_STATUS' => ['user', ['target_user_id']],
        'ADMIN_USER_DELETE' => ['user', ['target_user_id']],
        'LIKE' => ['advertisement', ['matangazo_id', 'id']],
        'REACTION' => ['advertisement', ['matangazo_id', 'id']],
        'AI_ENTRY_COMMIT' => ['record', ['record_id', 'id']],
        'AI_SALE_COMMIT' => ['sale', ['sale_id', 'id']],
    ];

    /** endpoint prefix -> human module shown in the timeline "page/module" column. */
    private const MODULES = [
        '/api/login' => 'Authentication',
        '/api/register' => 'Authentication',
        '/api/password-reset' => 'Authentication',
        '/api/forgot-password' => 'Authentication',
        '/api/reset-password' => 'Authentication',
        '/api/user/password' => 'Authentication',
        '/api/admin' => 'Admin',
        '/api/products' => 'Products',
        '/api/business' => 'Products',
        '/api/sales' => 'Sales',
        '/api/customers' => 'Customers',
        '/api/payments' => 'Payments',
        '/api/orders' => 'Orders',
        '/api/seller/orders' => 'Orders',
        '/api/office-expenses' => 'Expenses',
        '/api/matangazo' => 'Advertisements',
        '/api/inquiries' => 'Inquiries',
        '/api/system-admin' => 'Admin',
        '/api/user' => 'Account',
        '/api/profile' => 'Account',
        '/api/backup' => 'Backup',
        '/api/notifications' => 'Notifications',
    ];

    /**
     * @return array{
     *   category:string, event:string, description:string, outcome:string,
     *   severity:string, is_security:bool, risk:?string,
     *   entity_type:?string, entity_id:?string, page:?string, route:?string
     * }|null null = do not mirror
     */
    public static function classify(string $action, string $status, array $details = [], ?string $endpoint = null): ?array
    {
        $action = trim($action);
        if ($action === '' || in_array($action, self::SKIP_EXACT, true)) {
            return null;
        }

        $outcome = self::outcome($status);
        $route = self::route($endpoint);
        $module = self::module($endpoint);
        $entity = self::entity($action, $details);

        /* ---- 1. authorization decisions & blocked disclosures ---- */

        if (preg_match('/UNAUTHORIZED|FORBIDDEN|LEAK_BLOCKED|SELF_DELETE_ATTEMPT/', $action)) {
            return self::make('authorization', $action, $outcome, 'LOW', true, 'authz_denied', $entity, $module, $route);
        }

        /* ---- 2. authentication & security-setting events ---- */

        if (preg_match('/^(LOGIN|AUTH|REGISTER|PASSWORD|RESET|FORGOT|RESET_CODE)/', $action)) {
            $isAuth = $action === 'LOGIN_SUCCESS' || $action === 'LOGIN_FAILED'
                || $action === 'AUTH_FAILED' || str_starts_with($action, 'PASSWORD');

            $risk = null;
            $severity = 'INFO';
            if ($action === 'LOGIN_FAILED') {
                $risk = 'login_failure';
            } elseif ($action === 'AUTH_FAILED') {
                $risk = 'token_rejected';
            } elseif ($action === 'PASSWORD_CHANGE_FAILED') {
                $severity = 'LOW';
            }

            // Successful authentication is a security-relevant record too
            // (Phase 4A); FAILED registrations/OTP verifications are kept
            // security-relevant because they are the signal for code-guessing
            // abuse; completed registrations stay ordinary account events.
            $security = $isAuth
                || str_starts_with($action, 'PASSWORD')
                || str_starts_with($action, 'RESET')
                || (str_starts_with($action, 'REGISTER') && $outcome !== 'success');

            return self::make('authentication', $action, $outcome, $severity, $security, $risk, $entity, $module, $route);
        }

        /* ---- 3. rate limiting / CSRF recorded by the response middleware ---- */

        if (preg_match('/RATE_LIMIT|CSRF/', $action)) {
            return self::make('security', $action, $outcome, 'MEDIUM', true, null, $entity, $module, $route);
        }

        /* ---- 4. administrative operations (incl. admin reads = meta-audit) -- */

        if (preg_match('/^(ADMIN_|SETUP_|DATABASE_MIGRATION|REAL_TIME_STATS|PRESENCE|UPDATE_PRESENCE|NOTIFICATION|TEST_NOTIFICATION|MONITORING_|RETENTION_|SECURITY_)/', $action)) {
            // Account state changes and deletions are security-relevant
            // (privilege/status changes, Phase 4D).
            $sensitiveAdmin = (bool) preg_match('/ADMIN_STATUS_UPDATE|ADMIN_USER_DELETE/', $action);

            return self::make(
                'admin',
                $action,
                $outcome,
                $sensitiveAdmin ? 'LOW' : 'INFO',
                $sensitiveAdmin,
                null,
                $entity,
                $module,
                $route
            );
        }

        /* ---- 5. routine reads and probes: legacy-only, not mirrored ----
           (Admin reads were already handled in rule 4 above, so anything
           reaching this rule is an ordinary *_VIEW / *_FETCH / probe.) ---- */

        if (preg_match('/(_VIEW|_FETCH|_STATS|_STATUS_CHECK|^CHECK_|^WS_STATUS|^HEALTH)/', $action)) {
            return null;
        }

        /* ---- 6. business operations ---- */

        if (preg_match('/^(PRODUCT|SALE|CUSTOMER|PAYMENT|ORDER|EXPENSE|ADD_EXPENSE|DELETE_EXPENSE|MATANGAZO|INQUIRY_PUBLISH|INQUIRY_CREATE|LIKE|REACTION|CLOUDINARY|PESAPAL)/', $action)) {
            return self::make('business', $action, $outcome, 'INFO', false, null, $entity, $module, $route);
        }

        /* ---- 7. AI capability operations ---- */

        if (str_starts_with($action, 'AI_')) {
            return self::make('ai', $action, $outcome, 'INFO', false, null, $entity, $module, $route);
        }

        /* ---- 8. anything else falls to system, only if it looks deliberate -- */

        if (preg_match('/^[A-Z][A-Z0-9_]{3,}$/', $action)) {
            return self::make('system', $action, $outcome, 'INFO', false, null, $entity, $module, $route);
        }

        return null;
    }

    /* ------------------------------------------------------------------ */

    /** success/failed/rejected/denied/observed from an arbitrary status string. */
    public static function outcome(string $status): string
    {
        $s = strtolower(trim($status));

        return match (true) {
            in_array($s, ['success', 'ok', 'completed', 'verified', 'processed', 'applied'], true) => 'success',
            in_array($s, ['denied', 'forbidden', 'unauthorized', 'blocked', 'leak_blocked'], true) => 'denied',
            in_array($s, ['rejected', 'invalid', 'rate_limited', 'suppressed'], true) => 'rejected',
            in_array($s, ['failed', 'error'], true) => 'failed',
            default => 'observed',
        };
    }

    /** "PRODUCT_UPDATE" -> "Product update" (never invented beyond the words). */
    public static function label(string $action): string
    {
        $words = explode('_', strtolower(str_replace(['-', ' '], '_', trim($action))));
        $words = array_map(
            fn ($w) => $w === 'ai' ? 'AI' : ucfirst($w),
            $words
        );

        $label = implode(' ', $words);

        return mb_substr($label, 0, 200);
    }

    /** Path-only form of a legacy endpoint (query strings are never kept). */
    public static function route(?string $endpoint): ?string
    {
        if (! $endpoint) {
            return null;
        }
        $path = parse_url($endpoint, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . ltrim($path, '/');

        return mb_substr($path, 0, 160);
    }

    private static function module(?string $endpoint): ?string
    {
        $route = self::route($endpoint);
        if (! $route) {
            return null;
        }
        foreach (self::MODULES as $prefix => $name) {
            if (str_starts_with($route, $prefix)) {
                return $name;
            }
        }
        $segments = array_values(array_filter(explode('/', $route)));
        if (($segments[0] ?? '') === 'api') {
            $segments = array_slice($segments, 1);
        }

        return $segments ? mb_substr(ucfirst($segments[0]), 0, 48) : null;
    }

    /** @return array{0:?string,1:?string} [entity_type, entity_id] */
    private static function entity(string $action, array $details): array
    {
        foreach (self::ENTITIES as $prefix => [$type, $keys]) {
            if (str_starts_with($action, $prefix)) {
                foreach ($keys as $key) {
                    $value = $details[$key] ?? null;
                    if ((is_string($value) || is_numeric($value)) && strlen((string) $value) <= 64) {
                        return [$type, (string) $value];
                    }
                }

                return [$type, null];
            }
        }

        return [null, null];
    }

    private static function make(
        string $category,
        string $action,
        string $outcome,
        string $severity,
        bool $isSecurity,
        ?string $risk,
        array $entity,
        ?string $module,
        ?string $route
    ): array {
        return [
            'category' => $category,
            'event' => $action,
            'description' => self::label($action),
            'outcome' => $outcome,
            'severity' => $severity,
            'is_security' => $isSecurity,
            'risk' => $risk,
            'entity_type' => $entity[0],
            'entity_id' => $entity[1],
            'page' => $module,
            'route' => $route,
        ];
    }
}
