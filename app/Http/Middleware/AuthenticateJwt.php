<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\JwtToken;
use Closure;
use Illuminate\Http\Request;

class AuthenticateJwt
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');
        $token = preg_match('/Bearer\s+(.+)/i', $header, $m) ? trim($m[1]) : null;
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        if (!$token) {
            AuditLogger::log(null, 'AUTH_FAILED', $request->getRequestUri(), ['reason' => 'Token missing'], $ip, 'failed');
            return response()->json(['error' => 'Token inahitajika'], 401);
        }

        try {
            $decoded = JwtToken::decode($token);
            $user = User::where('id', $decoded->userId)->select('id', 'email', 'role', 'full_name', 'business_name', 'status', 'is_online', 'last_seen')->first();

            if (!$user) {
                AuditLogger::log($decoded->userId ?? null, 'AUTH_FAILED', $request->getRequestUri(), ['reason' => 'User not found'], $ip, 'failed');
                return response()->json(['error' => 'Token si sahihi'], 401);
            }

            $request->attributes->set('jwt_user', $user);
            $request->attributes->set('jwt_user_id', $user->id);
            $request->attributes->set('jwt_email', $user->email);
            $request->attributes->set('jwt_role', $user->role);
            $request->attributes->set('jwt_business_name', $user->business_name);
            $request->attributes->set('jwt_ip', $ip);

            AuditLogger::log($user->id, 'AUTH_SUCCESS', $request->getRequestUri(), ['role' => $user->role], $ip, 'success');

            return $next($request);
        } catch (\Exception $e) {
            AuditLogger::log(null, 'AUTH_FAILED', $request->getRequestUri(), ['reason' => $e->getMessage()], $ip, 'failed');
            return response()->json(['error' => 'Token si sahihi au imekwisha'], 401);
        }
    }
}