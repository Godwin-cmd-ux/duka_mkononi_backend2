<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\ActivityTracker;
use App\Services\Monitoring\SecurityMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Central security-event recorder for responses the application itself
 * rejects (Phase 4D): permission denied (403), CSRF validation failed (419)
 * and rate-limit exceeded (429). Nothing in the code base logs these today,
 * and a central middleware means every endpoint - present and future -
 * produces one consistent, normalized record without per-controller edits.
 *
 * Deliberately NOT handled here: 401/invalid tokens, which AuthenticateJwt
 * already audits (avoids double-recording), and 404s, which are too noisy to
 * call security incidents without more evidence.
 *
 * The write goes through ActivityTracker, so it inherits actor derivation
 * from trusted auth state, sanitization, flood suppression and best-effort
 * failure handling. Recording must never alter the response.
 */
class RecordAuthorizationEvents
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $status = $response->getStatusCode();
            if (in_array($status, [403, 419, 429], true) && strtoupper($request->getMethod()) !== 'OPTIONS') {
                $this->record($request, $response, $status);
            }
        } catch (\Throwable $e) {
            // Observability must never break the response path.
        }

        return $response;
    }

    private function record(Request $request, Response $response, int $status): void
    {
        [$event, $category, $severity, $risk, $description] = match ($status) {
            403 => ['AUTHZ_DENIED', 'authorization', 'LOW', 'authz_denied', 'Permission denied by the server'],
            419 => ['CSRF_FAILURE', 'security', 'MEDIUM', null, 'CSRF token validation failed'],
            default => ['RATE_LIMIT_EXCEEDED', 'security', 'MEDIUM', 'rate_limit', 'Rate limit exceeded'],
        };

        // 429 storms are floods by definition: keep them bounded.
        if ($risk === 'rate_limit' && ! SecurityMonitor::shouldRecord($risk, $request->ip())) {
            return;
        }

        // 403 keeps the assessment's counted reason ("N denials in the last
        // N minutes"); 419/429 are not counted risks, so they carry their own
        // fixed, truthful reason instead of a misleading default.
        $severityReason = $status === 403 ? null : $description;

        $error = null;
        if ($response->getContent() && str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            $decoded = json_decode((string) $response->getContent(), true);
            $error = is_array($decoded) ? ($decoded['error'] ?? null) : null;
            if (! is_string($error)) {
                $error = null;
            }
        }

        ActivityTracker::record([
            'user_id' => $request->attributes->get('jwt_user_id'),
            'category' => $category,
            'event' => $event,
            'description' => $description,
            'page' => null,
            'route' => '/' . $request->path(),
            'outcome' => 'denied',
            'severity' => $severity,
            'severity_reason' => $severityReason,
            'is_security' => true,
            'risk' => $risk,
            'metadata' => [
                'method' => $request->method(),
                'status' => $status,
                'response_error' => $error !== null ? mb_substr($error, 0, 160) : null,
                'role' => $request->attributes->get('jwt_role'),
            ],
        ]);
    }
}
