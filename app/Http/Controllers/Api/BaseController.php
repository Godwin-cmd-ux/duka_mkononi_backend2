<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\Locale;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseController
{
    protected function user(Request $request)
    {
        return $request->attributes->get('jwt_user');
    }

    protected function userId(Request $request): ?string
    {
        return $request->attributes->get('jwt_user_id');
    }

    protected function role(Request $request): ?string
    {
        return $request->attributes->get('jwt_role');
    }

    protected function businessName(Request $request): ?string
    {
        return $request->attributes->get('jwt_business_name');
    }

    /**
     * Stable business identity for the authenticated user. Never accept this
     * from client input - scope every business query with this instead.
     */
    protected function businessId(Request $request): ?string
    {
        return $request->attributes->get('jwt_business_id');
    }

    protected function ip(Request $request): ?string
    {
        return $request->attributes->get('jwt_ip');
    }

    /**
     * Capability #22 - the single locale resolution for every AI endpoint.
     *
     * Order (see App\Services\Ai\Locale::resolve): the request's own `locale`
     * field, then the caller's stored account language surfaced by the JWT
     * middleware, then the configured application default. Capabilities must
     * call this instead of carrying their own copy, so the rule can only exist
     * in one place.
     */
    protected function resolveLocale(Request $request): string
    {
        return Locale::resolve($request, (string) $request->attributes->get('jwt_language', ''));
    }

    protected function isoNow(string $format = 'millis'): string
    {
        return substr(now()->toISOString(true), 0, 23) . '';
    }

    protected function touchLastSeen(string $userId): void
    {
        try {
            \App\Models\User::where('id', $userId)->update([
                'last_seen' => now()->toISOString(true),
                'updated_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $e) {
        }
    }

    protected function log(?string $userId, string $action, string $endpoint, array $details = [], ?string $ip = null, string $status = 'success'): void
    {
        AuditLogger::log($userId, $action, $endpoint, $details, $ip, $status);
    }

    protected function json($data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function error(string $message, int $status = 500, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['error' => $message], $extra), $status);
    }

    protected function dbError(\Throwable $e, string $fallback = 'Hitilafu ya ndani ya server'): JsonResponse
    {
        return response()->json(['error' => $fallback, 'details' => $e->getMessage()], 500);
    }

    protected function take(\Illuminate\Http\Request $request, array $keys, array $defaults = []): array
    {
        $out = [];
        foreach ($keys as $k) {
            $v = $request->input($k);
            $out[$k] = $v ?? ($defaults[$k] ?? null);
        }
        return $out;
    }
}