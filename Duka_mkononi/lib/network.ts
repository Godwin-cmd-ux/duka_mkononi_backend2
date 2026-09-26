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
import { API_BASE_URL } from '../constants/api';

export const UNSTABLE_NETWORK_TITLE = 'Unstable Network';
export const UNSTABLE_NETWORK_MESSAGE =
  'unstable network please check your connection';

const PROBE_TTL_MS = 15_000;
const PROBE_TIMEOUT_MS = 5_000;
const FETCH_TIMEOUT_MS = 15_000;

let lastProbe = 0;
let lastStable = true;
let probing: Promise<boolean> | null = null;

export async function pingServer(): Promise<boolean> {
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), PROBE_TIMEOUT_MS);
    try {
      const res = await fetch(`${API_BASE_URL}/api/test`, {
        signal: controller.signal,
      });
      return res.ok;
    } finally {
      clearTimeout(timer);
    }
  } catch {
    return false;
  }
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

/** `fetch` with a hard timeout so a hung request can't block the UI forever. */
export async function fetchWithTimeout(
  url: string,
  init: RequestInit = {},
  timeoutMs: number = FETCH_TIMEOUT_MS
): Promise<Response> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await fetch(url, { ...init, signal: controller.signal });
  } finally {
    clearTimeout(timer);
  }
}