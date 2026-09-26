#!/bin/sh
set -e

# Ensure any missing framework + schema tables are created. All migrations are
# guarded (create-if-not-exists), so this is safe and idempotent on an
# existing Supabase/Postgres database.
if [ "${APP_ENV:-}" = "production" ]; then
    echo "Running guarded migrations..."
    php artisan migrate --force || echo "migrations skipped/failed (will retry next boot)"

    echo "Optimizing for production..."
    php artisan optimize
fi

exec "$@"