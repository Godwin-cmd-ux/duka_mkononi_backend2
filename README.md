# DukaMkononi Backend (Laravel)

The DukaMkononi backend, now running on **Laravel 11** (PHP). It exposes the exact same routes and API responses as the previous Node/Express server, so all existing web pages and mobile clients work unchanged.

- **Web UI** — Laravel Blade views (`resources/views`), self-contained, served at the same URLs (including `.html` aliases and redirects).
- **API** — Eloquent models + controllers under `app/Http/Controllers/Api`, auth via a HS256 JWT (`app/Services/JwtToken.php`) signed with the same secret/payload as the old Node server.
- **Database** — Eloquent over the existing Supabase Postgres schema (`pgsql`), or `sqlite` for local development. Migrations are guarded (`create-if-not-exists`) so they never touch existing tables.

## Requirements

- PHP 8.2+ with `pdo_pgsql`, `openssl`, `mbstring`
- Composer

## Setup (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate        # guarded, safe on existing DBs
php artisan serve          # http://127.0.0.1:8000
```

Local development defaults to `sqlite` (`database/database.sqlite`). For Supabase set in `.env`:

```
DB_CONNECTION=pgsql
DB_HOST=db.yourproject.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=...
SUPABASE_URL=https://yourproject.supabase.co
```

## Key environment variables

| Variable | Description |
| --- | --- |
| `JWT_SECRET` | HS256 signing secret (keep the same value the Node backend used) |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Database connection |
| `PESAPAL_ENV` / `PESAPAL_CONSUMER_KEY` / `PESAPAL_CONSUMER_SECRET` / `PESAPAL_NOTIFICATION_ID` | PesaPal payment gateway (`sandbox` or `live`) |
| `PESAPAL_CALLBACK_URL` / `PESAPAL_IPN_URL` | Public callback / IPN endpoints |
| `MATANGAZO_PRICE` / `MATANGAZO_DURATION_DAYS` | Paid-ad pricing (default `3000` TZS / `30` days) |
| `ENABLE_TEST_MODE` | When `true` (or `APP_ENV=local`), OTP flows accept the test code |
| `EMAIL_HOST` / `EMAIL_PORT` / `EMAIL_USER` / `EMAIL_PASSWORD` / `EMAIL_FROM` | SMTP for OTP e-mails (falls back to log mailer) |
| `GEMINI_API_KEY` / `GEMINI_MODEL` | AI product import (Gemini) |
| `SUPABASE_URL` | Project URL (used in backup headers / health payload) |

## Deployment notes

- Run `php artisan config:cache` for production; all controller reads go through `config/*` files so caching is safe.
- The old Node/Express artifacts (`server.js`, `.html`, `js/`, `ai/`, `locales/`, `package.json`, `node_modules/`) were removed after conversion; they are preserved in git tag **`pre-laravel-migration`**.

## Docker deployment

The repo ships a production-ready image:

| File | Purpose |
| --- | --- |
| `Dockerfile` | Multi-stage build: Composer deps + `php:8.2-fpm` with PDO Postgres/opcache |
| `docker/nginx.conf` | Nginx vhost proxying to PHP-FPM |
| `docker/php.ini` | PHP limits + OPcache |
| `docker/docker-entrypoint.sh` | Boot script: guarded migrations + `php artisan optimize` (only when `APP_ENV=production`) |
| `docker-compose.yml` | `app` (PHP-FPM) + `nginx` sidecar, external Supabase/Postgres |

```bash
# 1. Fill in .env (Supabase/pgsql host, JWT_SECRET, APP_KEY, PESAPAL_*, EMAIL_*)
cp .env.example .env
php artisan key:generate        # sets APP_KEY in .env

# 2. Build and start
docker compose up -d --build

# 3. App is served on http://localhost:80
```

- Code is baked into the image; `storage/` and `backup/` are persisted via named volumes.
- On boot (in production) the entrypoint runs the guarded migrations (safe on an existing DB) then caches config/routes/views.
- Point your VPS/Render/AWS host at the app ports, or terminate TLS in a reverse proxy in front of Nginx.

## API surface

- ~99 API routes under `api/` (login, OTP registration, products, sales, customers, matangazo, reactions, payments/PesaPal, notifications, reports, expenses, profit, revenue, admin, backups, AI import, debug, health…) — see `routes/api.php`.
- Pages and `.html` aliases are registered in `routes/web.php`.

## Payment flow (unchanged)

1. App uploads media, creates ad with `payment_status: 'pending'`.
2. App calls `POST /api/payments/pesapal/initiate` `{ amount, matangazo_id, description }` → server returns `redirect_url` (server always charges `MATANGAZO_PRICE`).
3. PesaPal IPN / callback activates the ad with `expires_at = now + MATANGAZO_DURATION_DAYS` (renewals extend from the current expiry).
4. App polls `GET /api/payments/pesapal/status/:order_tracking_id` for the result.