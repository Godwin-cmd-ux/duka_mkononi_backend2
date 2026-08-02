# DukaMkononi — New Server (v2.0)

The full, updated backend server for **DukaMkononi** with real **PesaPal** payment integration for advertisements.

## What changed in this version

1. **Paid advertisements (TZS 3,000 / 30 days)**

   - `POST /api/matangazo` now supports an **opt-in paid flow**: send `payment_status: 'pending'` to create an advertisement that is **not live** (`is_active: false`) until payment is confirmed.
   - After a successful PesaPal payment (IPN, callback or status check), the ad is automatically activated with `expires_at = now + 30 days`.
   - **Renewals** extend `expires_at` by another 30 days from the current expiry (if still in the future).
   - Any request **without** `payment_status: 'pending'` keeps the legacy behavior — a live free ad (`payment_status: 'completed'`, `is_free: true`) — so existing web/clients are not broken.

2. **PesaPal fixes**

   - `getPesapalAccessToken()` no longer sends credentials in the `Authorization` header — PesaPal v3 expects them in the JSON body.
   - Default `PESAPAL_CALLBACK_URL` / `PESAPAL_IPN_URL` fixed to the real endpoints (`/api/payments/pesapal-callback`, `/api/payments/pesapal-ipn`).
   - `POST /api/payments/pesapal/initiate` now verifies the user **owns** the `matangazo_id` they are paying for.

3. **Public feed**

   - `GET /api/matangazo` now shows legacy free ads **plus** paid ads that are still within their 30-day window (expired paid ads are hidden automatically).

4. **Configurable pricing (server-enforced)**

   - `MATANGAZO_PRICE` (default `3000`) and `MATANGAZO_DURATION_DAYS` (default `30`) can be overridden with environment variables.
   - For matangazo payments the server **never trusts the client-supplied amount** — it always charges `MATANGAZO_PRICE`.

## Deploy / Host

> ⚠️ **IMPORTANT:** This server is built for **Express 4** (`package.json` pins `express: ^4.21.2`). Do **not** upgrade to Express 5 — the code uses Express-4 route syntax (e.g. `/api/profit/daily/:date?`) that crashes on Express 5 at startup.

### Option A — Render (recommended)

1. Commit `server.js` + `package.json` to your backend repo and push.
2. In the **Render dashboard → your Web Service → Environment**, add the variables below (`.env` is git-ignored and will NOT be deployed — dashboard vars are the only way on Render).
3. Deploy. The server listens on Render's `PORT` automatically.

### Option B — your own server (VPS / local)

1. Put `server.js` into your server directory (the folder that also contains `index.html`, `mteja/`, `muuzaji/`, `msimamizi/`, `admin/` web folders — replace your old `server.js` with this one).
2. Create a `.env` file from `.env.example` next to `server.js` (the server loads it automatically with its built-in loader — no `dotenv` package needed).
3. Install dependencies: `npm install`
4. Start: `npm start` (listens on `PORT`, default `10000`).

## Required environment variables

| Variable | Description |
| --- | --- |
| `SUPABASE_URL` | Supabase project URL |
| `SUPABASE_ANON_KEY` | Supabase anon (publishable) key |
| `SUPABASE_SERVICE_KEY` | Supabase service-role key (server-side only) |
| `JWT_SECRET` | Long random string for signing tokens |
| `EMAIL_USER` / `EMAIL_PASSWORD` | Gmail + App Password for Nodemailer |
| `PESAPAL_ENV` | `sandbox` or `live` |
| `PESAPAL_CONSUMER_KEY` / `PESAPAL_CONSUMER_SECRET` | PesaPal API keys |
| `PESAPAL_NOTIFICATION_ID` | IPN notification ID (register your IPN URL in the PesaPal dashboard) |
| `PESAPAL_CALLBACK_URL` | Public callback URL, e.g. `https://your-domain.com/api/payments/pesapal-callback` |
| `PESAPAL_IPN_URL` | Public IPN URL, e.g. `https://your-domain.com/api/payments/pesapal-ipn` |

## Database notes

The `matangazo` table needs these columns (they already exist in the current production database):

- `payment_status` (text: `'pending'` / `'completed'` / `'free'`)
- `is_free` (boolean)
- `is_active` (boolean)
- `expires_at` (timestamptz, nullable)
- `order_tracking_id` (text, nullable)

The `payments` table needs `matangazo_id` (nullable FK) so a payment can activate the matching advertisement.

## API endpoints added/used by the app

| Method | Endpoint | Auth | Purpose |
| --- | --- | --- | --- |
| POST | `/api/matangazo` | Bearer token | Create an ad (`payment_status: 'pending'` starts the paid flow) |
| POST | `/api/payments/pesapal/initiate` | Bearer token | Create a PesaPal order (TZS 3,000) and get the redirect URL |
| GET | `/api/payments/pesapal/status/:order_tracking_id` | Bearer token | Poll PesaPal for the transaction status |
| POST | `/api/payments/pesapal-ipn` | PesaPal | Instant Payment Notification (activates the ad on completion) |
| POST | `/api/payments/pesapal-callback` | PesaPal | Browser callback after payment (activates the ad on completion) |
| GET | `/api/matangazo` | none | Public feed (free + active paid ads) |
| GET | `/api/matangazo/my` | Bearer token | User's own ads (including pending / expired) |

## Payment flow (how the mobile app uses this)

1. App uploads media to Cloudinary.
2. App creates the ad with `payment_status: 'pending'`.
3. App calls `/api/payments/pesapal/initiate` with `{ amount: 3000, matangazo_id, description }` → server returns `redirect_url`.
4. App opens `redirect_url` (PesaPal payment page). The user enters their mobile-money account and confirms **TZS 3,000**.
5. PesaPal sends the IPN / callback → server activates the ad (`is_active: true`, `expires_at = +30 days`).
6. App polls `/api/payments/pesapal/status/:order_tracking_id` → shows success.
7. When the ad expires, the user can pay again from **My Ads** to renew for another 30 days.
