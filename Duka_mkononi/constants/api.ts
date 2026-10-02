/**
 * API Base URL — the Laravel backend that serves Duka Mkononi.
 *
 * Requests go through the service's Render hostname rather than the branded
 * https://www.dukamkononi.com domain. Both reach the SAME backend, but the
 * Render hostname is served IPv4-only, whereas www publishes IPv6 (AAAA)
 * records.
 *
 * Some mobile carriers (e.g. Halotel) advertise IPv6 but route it badly, so a
 * native fetch stalls on the IPv6 address until it times out — the user sees a
 * bare "Aborted" error — while a browser succeeds because it races IPv4 and
 * IPv6. Preferring the IPv4-only host avoids that path entirely.
 *
 * For local development against `php artisan serve`, temporarily override this
 * value (e.g. Android emulator: http://10.0.2.2:8000).
 */
export const API_BASE_URL: string =
  process.env.EXPO_PUBLIC_API_BASE_URL ||
  'https://duka-mkononi-backend2.onrender.com';

/**
 * Branded-domain fallback, used only if the primary host is unreachable (e.g.
 * the Render hostname is ever retired). It resolves to the same backend via
 * Cloudflare.
 */
export const API_FALLBACK_URL: string =
  process.env.EXPO_PUBLIC_API_FALLBACK_URL || 'https://www.dukamkononi.com';

/** Hosts to try, in order, for API requests that need to survive a bad route. */
export const API_BASE_URLS: string[] = [API_BASE_URL, API_FALLBACK_URL];
