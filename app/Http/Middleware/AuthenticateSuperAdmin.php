<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtToken;
use Closure;
use Illuminate\Http\Request;

/**
 * Server-side super-admin gate for the backup actions.
 *
 * The rest of /system_admin is rendered as static Blade and guards itself in
 * the browser, but a backup endpoint can stream the entire database, so it
 * cannot rely on a client-side check. The page puts the signed-in JWT into a
 * short-lived same-site cookie (and the same token is accepted as a bearer
 * token or ?token= so a script can call these routes too), and this middleware
 * verifies the signature and re-reads the role from the database — a token
 * claiming super_admin is not enough on its own.
 */
class AuthenticateSuperAdmin
{
    private const COOKIE = 'dm_admin_token';

    public function handle(Request $request, Closure $next)
    {
        $token = $this->token($request);

        if ($token === null) {
            return $this->deny($request, 'A super admin session is required.');
        }

        try {
            $decoded = JwtToken::decode($token);
        } catch (\Throwable $e) {
            return $this->deny($request, 'The session token is invalid or has expired.');
        }

        $user = User::where('id', $decoded->userId ?? '')->select('id', 'role')->first();

        if (! $user || ! in_array((string) $user->role, ['super_admin', 'system_admin'], true)) {
            return $this->deny($request, 'This area is restricted to the platform super admin.');
        }

        $request->attributes->set('jwt_user_id', $user->id);
        $request->attributes->set('jwt_role', $user->role);

        return $next($request);
    }

    private function token(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }

        $query = (string) $request->query('token', '');

        if ($query !== '') {
            return $query;
        }

        $cookie = (string) $request->cookie(self::COOKIE, '');

        return $cookie !== '' ? $cookie : null;
    }

    private function deny(Request $request, string $reason)
    {
        if ($request->expectsJson() || $request->isMethod('get')) {
            return response()->json(['error' => $reason], 403);
        }

        return redirect()->route('system_admin.backup.index')->with('error', $reason);
    }
}
