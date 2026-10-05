/**
 * API Base URL — the Laravel backend that serves Duka Mkononi.
 *
 * Requests go through the branded https://www.dukamkononi.com domain. It
 * resolves via Cloudflare to the SAME backend as the Render hostname, but www
 * also publishes IPv6 (AAAA) records while the Render hostname is IPv4-only.
 *
 * Some mobile carriers (e.g. Halotel) advertise IPv6 but route it badly, so a
 * native fetch can stall on the IPv6 address until it times out — the user sees
 * a bare "Aborted" error — while a browser succeeds because it races IPv4 and
 * IPv6. If that regression appears, the Render hostname remains the fallback
 * (see API_FALLBACK_URL) to avoid that path.
 *
 * For local development against `php artisan serve`, temporarily override this
 * value (e.g. Android emulator: http://10.0.2.2:8000).
 */
export const API_BASE_URL: string =
  process.env.EXPO_PUBLIC_API_BASE_URL || 'https://www.dukamkononi.com';

/**
 * Fallback host, used only if the primary host is unreachable. It resolves to
 * the same backend, served IPv4-only via Render, which sidesteps bad IPv6
 * routes on some carriers.
 */
export const API_FALLBACK_URL: string =
  process.env.EXPO_PUBLIC_API_FALLBACK_URL ||
  'https://duka-mkononi-backend2.onrender.com';

/** Hosts to try, in order, for API requests that need to survive a bad route. */
export const API_BASE_URLS: string[] = [API_BASE_URL, API_FALLBACK_URL];
