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
# 2. Production-safe defaults. Platform-provided env vars (Render dashboard,
#    etc.) still win for APP_ENV; these apply only when nothing is set.
#    Your local .env is never loaded into Docker, so local dev is unaffected.
# ---------------------------------------------------------------------------
if [ -z "${APP_ENV:-}" ]; then
    APP_ENV=production
    export APP_ENV
fi
echo "entrypoint: APP_ENV=$APP_ENV"

# ---------------------------------------------------------------------------
# 3. Debug mode is NEVER on in this container. Stack-trace pages can leak
#    .env secrets to end users. Emergency escape hatch: FORCE_DEBUG=true.
#    (Local dev on your machine runs via `php artisan serve`, not Docker —
#    APP_DEBUG=true in .env keeps working there.)
# ---------------------------------------------------------------------------
if [ "${FORCE_DEBUG:-}" = "true" ]; then
    echo "entrypoint: FORCE_DEBUG=true — leaving APP_DEBUG as-is (${APP_DEBUG:-unset})"
else
    if [ "${APP_DEBUG:-}" != "false" ]; then
        APP_DEBUG=false
        export APP_DEBUG
        echo "entrypoint: forced APP_DEBUG=false (set FORCE_DEBUG=true to override)"
    fi
fi

# ---------------------------------------------------------------------------
# 4. Self-heal values that are categorically broken for this app. It talks
#    to Supabase over REST and has NO SQL database, so sqlite and the
#    database-backed session/cache/queue drivers can never work — typically
#    these arrive via copy-pasted platform env vars. Coerce + warn loudly.
#    (Deliberately NOT fatal: a misconfigured dashboard should not take the
#    site down when the correct value is unambiguous.)
# ---------------------------------------------------------------------------
healed=""
if [ "${DB_CONNECTION:-}" = "sqlite" ] || [ -z "${DB_CONNECTION:-}" ]; then
    DB_CONNECTION=pgsql
    export DB_CONNECTION
    healed="$healed DB_CONNECTION"
fi
if [ "${SESSION_DRIVER:-}" = "database" ]; then
    SESSION_DRIVER=file
    export SESSION_DRIVER
    healed="$healed SESSION_DRIVER"
fi
if [ "${CACHE_STORE:-}" = "database" ]; then
    CACHE_STORE=file
    export CACHE_STORE
    healed="$healed CACHE_STORE"
fi
if [ "${QUEUE_CONNECTION:-}" = "database" ]; then
    QUEUE_CONNECTION=sync
    export QUEUE_CONNECTION
    healed="$healed QUEUE_CONNECTION"
fi
if [ -n "$healed" ]; then
    echo "entrypoint: WARNING — healed broken values:$healed (Supabase REST app; no SQL database)."
    echo "entrypoint: WARNING — remove these from your platform env to silence this."
fi

# ---------------------------------------------------------------------------
# 5. One-time boot work in production. `php artisan optimize` bakes the env
#    into the config cache, so it MUST run after the defaults above.
#    Migrations are guarded/create-if-not-exists — safe on the live schema.
# ---------------------------------------------------------------------------
if [ "$APP_ENV" = "production" ] && [ "${SKIP_BOOT_OPS:-}" != "1" ]; then
    echo "entrypoint: php artisan optimize"
    php artisan optimize || echo "entrypoint: optimize failed (continuing)"
    # Schema changes are applied via the Supabase SQL editor (supabase/*.sql).
    # `migrate` needs real Postgres credentials (DB_HOST/DB_URL) which this
    # app doesn't ship — only attempt it when they exist.
    if [ -n "${DB_HOST:-}" ] || [ -n "${DB_URL:-}" ]; then
        echo "entrypoint: guarded migrations"
        php artisan migrate --force || echo "entrypoint: migrate failed (will retry next boot)"
    else
        echo "entrypoint: no DB_HOST/DB_URL set — skipping migrate (schema managed via Supabase SQL editor)"
    fi
fi

exec "$@"
