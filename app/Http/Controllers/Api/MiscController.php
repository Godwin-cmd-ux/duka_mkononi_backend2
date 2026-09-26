<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MiscController extends BaseController
{
    private function requestIp(Request $request): ?string
    {
        return $request->header('x-forwarded-for', '') ?: $request->ip();
    }

    public function test(Request $request): JsonResponse
    {
        $ip = $this->requestIp($request);

        $this->log(null, 'TEST_ENDPOINT', '/api/test', [], $ip, 'success');

        return $this->json([
            'message' => '✅ Backend inafanya kazi kikamilifu!',
            'timestamp' => now()->toISOString(true),
            'database' => 'Supabase PostgreSQL',
            'server' => 'Running successfully',
            'supabaseUrl' => config('supabase.url', env('SUPABASE_URL', '')),
            'webSocket' => [
                'connected' => false,
                'connectedUsers' => 0,
                'adminConnections' => 0,
                'endpoint' => 'ws://' . $request->header('Host', $request->getHost()) . '/ws',
            ],
            'realTimeFeatures' => 'Active with WebSocket support',
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        $ip = $this->requestIp($request);

        try {
            User::count();

            $this->log(null, 'HEALTH_CHECK', '/api/health', ['status' => 'healthy'], $ip, 'success');

            return $this->json([
                'status' => 'healthy',
                'database' => 'connected',
                'provider' => 'Supabase',
                'timestamp' => now()->toISOString(true),
                'url' => config('supabase.url', env('SUPABASE_URL', '')),
                'webSocket' => [
                    'status' => 'stopped',
                    'clients' => 0,
                    'connectedUsers' => 0,
                ],
                'realTime' => [
                    'enabled' => true,
                    'connectedUsers' => 0,
                    'adminConnections' => 0,
                ],
            ]);
        } catch (Throwable $error) {
            $this->log(null, 'HEALTH_CHECK', '/api/health', [
                'status' => 'unhealthy',
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'status' => 'unhealthy',
                'database' => 'disconnected',
                'error' => $error->getMessage(),
                'timestamp' => now()->toISOString(true),
            ], 500);
        }
    }
}