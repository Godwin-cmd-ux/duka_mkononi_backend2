/**
 * Client-side live sync engine.
 *
 * Pages register a cache key, a network fetcher and a state listener. The engine
 * guarantees that:
 *
 *   1. The page reads its local SQLite cache instantly (fast page open).
 *   2. A background fetch runs immediately and again every SYNC_INTERVAL_MS while
 *      the app is in the foreground and the page is still mounted.
 *   3. Every successful fetch writes the row into SQLite and pushes the fresh
 *      payload to the page listeners, so numbers change silently on screen —
 *      no page refresh, no spinner, no pull-to-refresh needed.
 *   4. When the network is down the fetcher rejects, the cached data is kept as-is
 *      and nothing is shown to the user.
 *
 * The polling loop pauses automatically when the app moves to the background and
 * resumes when it returns to the foreground, so it never drains the battery while
 * the phone is idle.
 */

import { AppState } from 'react-native';
import { setCache } from '../db/cache';

export const SYNC_INTERVAL_MS = 20_000;

type Fetcher<T = unknown> = () => Promise<T>;
type Listener<T = unknown> = (data: T) => void;

interface Registration {
  fetcher: Fetcher;
  listeners: Set<Listener>;
  lastSync: number;
  syncing: boolean;
}

const registrations = new Map<string, Registration>();

let timer: ReturnType<typeof setInterval> | null = null;

AppState.addEventListener('change', (state) => {
  if (state === 'active') {
    startLoop();
  } else if (timer) {
    clearInterval(timer);
    timer = null;
  }
});

function startLoop() {
  if (timer) return;
  timer = setInterval(() => {
    tick().catch(() => {});
  }, SYNC_INTERVAL_MS);
  tick().catch(() => {});
}

async function tick() {
  const now = Date.now();
  for (const key of registrations.keys()) {
    const reg = registrations.get(key);
    if (reg && now - reg.lastSync >= SYNC_INTERVAL_MS) {
      try {
        await syncNow(key);
      } catch {
        // individual key failures are swallowed here
      }
    }
  }
}

/**
 * Register a live data set. Returns an unsubscribe function.
 *
 * `fetcher` must be the network call PLUS any processing the page needs to
 * produce the exact shape the page renders. Every successful result is cached
 * under `key` and delivered to `listener`.
 */
export function registerLive<T = unknown>(
  key: string,
  fetcher: Fetcher<T>,
  listener: Listener<T>
): () => void {
  let reg = registrations.get(key);
  if (!reg) {
    reg = { fetcher, listeners: new Set(), lastSync: 0, syncing: false };
    registrations.set(key, reg);
  }
  reg.fetcher = fetcher;
  reg.listeners.add(listener);

  if (AppState.currentState === 'active') startLoop();

  // Kick off an immediate silent refresh (page already shows cached data).
  syncNow(key).catch(() => {});

  return () => {
    const current = registrations.get(key);
    if (!current) return;
    current.listeners.delete(listener);
    if (current.listeners.size === 0) {
      registrations.delete(key);
    }
  };
}

/** Force a background sync for a registered key (used after local writes). */
export async function syncNow(key: string): Promise<boolean> {
  const reg = registrations.get(key);
  if (!reg || reg.syncing) return false;
  reg.syncing = true;
  try {
    const data = await reg.fetcher();
    reg.lastSync = Date.now();
    await setCache(key, data).catch(() => {});
    for (const listener of [...reg.listeners]) {
      try {
        listener(data);
      } catch {
        // listener errors must never break the sync loop
      }
    }
    return true;
  } catch {
    // Network error/timeout: keep showing the cached data, stay silent.
    return false;
  } finally {
    reg.syncing = false;
  }
}

/** Stop all live subscriptions (used on logout). */
export function stopAllSync(): void {
  registrations.clear();
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
}