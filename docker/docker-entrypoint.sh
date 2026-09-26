#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# 1. Fail fast if critical credentials are missing — a clear message in the
#    platform logs instead of confusing 500s once traffic arrives.
# ---------------------------------------------------------------------------
for var in APP_KEY JWT_SECRET SUPABASE_URL SUPABASE_SERVICE_ROLE_KEY; do
    eval "value=\${$var:-}"
    if [ -z "$value" ]; then
        echo "FATAL: environment variable $var is not set." >&2
        exit 1
    fi
done

# ---------------------------------------------------------------------------
# 2. Production-safe defaults. Platform-provided env vars always win; these
#    defaults only apply when the platform sets nothing (Render, etc.).
#    Your local .env is never loaded into Docker, so local dev is unaffected.
# ---------------------------------------------------------------------------
if [ -z "${APP_ENV:-}" ]; then
    APP_ENV=production
    export APP_ENV
fi
if [ -z "${APP_DEBUG:-}" ]; then
    APP_DEBUG=false
    export APP_DEBUG
fi
echo "entrypoint: APP_ENV=$APP_ENV APP_DEBUG=$APP_DEBUG"

# Safety guarantee: debug mode is NEVER on in production, even if some env
# source (a stale .env, a platform template) supplied APP_DEBUG=true.
# Local dev is unaffected — it runs APP_ENV=local.
if [ "$APP_ENV" = "production" ] && [ "$APP_DEBUG" != "false" ]; then
    APP_DEBUG=false
    export APP_DEBUG
    echo "entrypoint: forced APP_DEBUG=false for production"
fi

# ---------------------------------------------------------------------------
# 3. One-time boot work in production. `php artisan optimize` bakes the env
#    (including the defaults above) into the config cache, so it MUST run
#    after step 2. Migrations are guarded/create-if-not-exists — safe to
#    re-run against the existing Supabase schema.
# ---------------------------------------------------------------------------
if [ "$APP_ENV" = "production" ] && [ "${SKIP_BOOT_OPS:-}" != "1" ]; then
    echo "entrypoint: php artisan optimize"
    php artisan optimize || echo "entrypoint: optimize failed (continuing)"
    echo "entrypoint: guarded migrations"
    php artisan migrate --force || echo "entrypoint: migrate failed (will retry next boot)"
fi

exec "$@"
