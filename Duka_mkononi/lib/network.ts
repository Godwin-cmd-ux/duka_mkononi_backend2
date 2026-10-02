/**
 * Network helpers.
 *
 * - `pingServer()`     – cheap connectivity probe against the API health route.
 * - `isNetworkStable()`– cached probe result (short TTL) so writes don't block
 *                        on a slow network before showing the offline alert.
 * - `requireNetwork()` – used by every WRITE operation. When the network is not
 *                        stable it shows the user-facing alert and returns false
 *                        so the write is aborted instead of silently failing.
 * - `fetchWithTimeout()`– fetch wrapper with an abort timeout so pages fail fast
 *                        and fall back to cached data instead of hanging.
 */

import { Alert, Platform } from 'react-native';
import { API_BASE_URLS, API_FALLBACK_URL } from '../constants/api';

export const UNSTABLE_NETWORK_TITLE = 'Unstable Network';
export const UNSTABLE_NETWORK_MESSAGE =
  'unstable network please check your connection';

const PROBE_TTL_MS = 15_000;
const PROBE_TIMEOUT_MS = 5_000;
const FETCH_TIMEOUT_MS = 15_000;

/** Per-host budget for `fetchApiWithFallback` (two hosts → ~40s worst case). */
export const API_FETCH_TIMEOUT_MS = 20_000;

let lastProbe = 0;
let lastStable = true;
let probing: Promise<boolean> | null = null;

/**
 * The host that last answered successfully.
 *
 * Every request is rewritten onto this host before it is sent, so once one
 * call discovers which of `API_BASE_URLS` is reachable (e.g. login falling
 * back to the branded domain on a carrier with a broken route to the primary
 * host), the rest of the app goes straight there instead of each page burning
 * its whole timeout on the dead host first.
 */
let preferredHost: string | null = null;

/** Host prefix of `url`, when it is one of the configured API hosts. */
function hostOf(url: string): string | null {
  return API_BASE_URLS.find((host) => !!host && url.startsWith(host)) || null;
}

/** Point a URL at the host that most recently worked. */
function withPreferredHost(url: string): string {
  const host = hostOf(url);
  if (!host || !preferredHost || host === preferredHost) return url;
  return preferredHost + url.slice(host.length);
}

/** Remember the host behind a URL once it has answered. */
function rememberHost(url: string): void {
  const host = hostOf(url);
  if (host) preferredHost = host;
}

export function getPreferredHost(): string | null {
  return preferredHost;
}

export async function pingServer(): Promise<boolean> {
  for (const host of API_BASE_URLS.filter(Boolean)) {
    try {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), PROBE_TIMEOUT_MS);
      try {
        const res = await fetch(`${host}/api/test`, { signal: controller.signal });
        if (res.ok) {
          rememberHost(host);
          return true;
        }
      } finally {
        clearTimeout(timer);
      }
    } catch {
      // Try the next host.
    }
  }
  return false;
}

/** True when the app can reach the backend (results cached for a few seconds). */
export async function isNetworkStable(): Promise<boolean> {
  const now = Date.now();
  if (now - lastProbe < PROBE_TTL_MS) return lastStable;
  if (!probing) {
    probing = pingServer().then((ok) => {
      lastProbe = Date.now();
      lastStable = ok;
      probing = null;
      return ok;
    });
  }
  return probing;
}

/**
 * Write guards call this before hitting the API. When offline it shows the
 * required alert once and indicates the operation should be aborted.
 *
 * Note: react-native-web does not implement `Alert`, so on the web build we
 * fall back to the browser `alert()` so the user still sees the message.
 */
export async function requireNetwork(): Promise<boolean> {
  const ok = await isNetworkStable();
  if (!ok) {
    if (Platform.OS === 'web') {
      globalThis.alert?.(`${UNSTABLE_NETWORK_MESSAGE}`);
    } else {
      Alert.alert(UNSTABLE_NETWORK_TITLE, UNSTABLE_NETWORK_MESSAGE);
    }
  }
  return ok;
}

/** Single `fetch` with a hard timeout so a hung request can't block the UI. */
async function fetchRaw(
  url: string,
  init: RequestInit,
  timeoutMs: number
): Promise<Response> {
  const target = withPreferredHost(url);
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const res = await fetch(target, { ...init, signal: controller.signal });
    // Any response (even an HTTP error) proves this host is reachable, so
    // later requests can skip the unreachable one.
    rememberHost(target);
    return res;
  } finally {
    clearTimeout(timer);
  }
}

// Transient gateway errors that mean "this host is not ready" (Render cold start).
const RETRYABLE_STATUSES = [502, 503, 504];

/**
 * `fetch` with a hard timeout so a hung request can't block the UI forever.
 *
 * For idempotent reads (GET/HEAD) it also retries against `API_FALLBACK_URL`
 * when the primary host is unreachable or answers with a transient gateway
 * error. Reads are safe to repeat; writes are never retried here.
 */
export async function fetchWithTimeout(
  url: string,
  init: RequestInit = {},
  timeoutMs: number = FETCH_TIMEOUT_MS
): Promise<Response> {
  const method = String(init.method || 'GET').toUpperCase();
  const isRead = method === 'GET' || method === 'HEAD';
  // A read is retried on the alternate host when the host it went to is
  // unreachable. The URL may already have been rewritten to `preferredHost`
  // by `fetchRaw`, so compare against any configured host, not just the
  // primary one.
  const canFallback =
    isRead &&
    !!API_FALLBACK_URL &&
    hostOf(withPreferredHost(url)) !== null;

  // Build the fallback from the host actually being tried first, so a URL
  // already rewritten to `preferredHost` falls back to the *other* host.
  const attempted = withPreferredHost(url);
  const attemptedHost = hostOf(attempted);
  const effectiveFallbackUrl =
    canFallback && attemptedHost
      ? API_BASE_URLS.find((h) => !!h && h !== attemptedHost)! +
        attempted.slice(attemptedHost.length)
      : null;

  try {
    const res = await fetchRaw(url, init, timeoutMs);
    if (effectiveFallbackUrl && RETRYABLE_STATUSES.includes(res.status)) {
      console.warn(`⚠️ ${res.status} from host; retrying on fallback host`);
      try {
        return await fetchRaw(effectiveFallbackUrl, init, timeoutMs);
      } catch {
        return res;
      }
    }
    return res;
  } catch (error) {
    if (!effectiveFallbackUrl) throw error;
    console.warn(`⚠️ Host unreachable (${String((error as Error)?.message)}); retrying on fallback host`);
    return await fetchRaw(effectiveFallbackUrl, init, timeoutMs);
  }
}

/**
 * True when an error came from an aborted (timed-out) request rather than from
 * a server response. React Native reports this as an `AbortError` whose message
 * is the bare word "Aborted", which is unhelpful to show to a user.
 */
export function isAbortError(error: unknown): boolean {
  const err = error as { name?: string; message?: string } | null | undefined;
  if (!err) return false;
  if (err.name === 'AbortError') return true;
  return /abort/i.test(String(err.message || ''));
}

/**
 * Fetch an API path, falling back to an alternate host when the primary one is
 * unreachable.
 *
 * The primary host (`API_BASE_URL`) is IPv4-only to avoid that path; the
 * fallback is only contacted if the primary is unreachable.
 *
 * `fetchWithTimeout` only rejects on network/abort errors (an HTTP error
 * status resolves normally), so any rejection here means the request never
 * completed and is safe to try on the next host. This is opt-in — use it for
 * idempotent calls like login, not for one-shot writes.
 */
export async function fetchApiWithFallback(
  path: string,
  init: RequestInit = {},
  timeoutMs: number = API_FETCH_TIMEOUT_MS
): Promise<Response> {
  const hosts = API_BASE_URLS.filter((host): host is string => !!host);
  let lastError: unknown;

  for (let i = 0; i < hosts.length; i++) {
    try {
      return await fetchWithTimeout(`${hosts[i]}${path}`, init, timeoutMs);
    } catch (error) {
      lastError = error;
      if (i === hosts.length - 1) throw error;
    }
  }

  throw lastError;
}