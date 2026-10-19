<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt' => \App\Http\Middleware\AuthenticateJwt::class,
        ]);

        $middleware->encryptCookies([
            'XSRF-TOKEN',
        ]);

        // Trusted reverse proxy list (TRUSTED_PROXIES env, comma-separated
        // IPs/CIDRs or '*'). When configured, Laravel's TrustProxies resolves
        // the real client address for $request->ip(); when absent, the app
        // keeps using the direct peer address and monitoring labels any
        // X-Forwarded-For value as an untrusted observation. env() (not
        // config()) is used because this closure may run before configuration
        // is loaded.
        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }

        // Central security-response events: 403 (permission denied), 419
        // (CSRF failure) and 429 (rate limit) become normalized security
        // audit records for the admin monitoring dashboard.
        $middleware->append(\App\Http\Middleware\RecordAuthorizationEvents::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();