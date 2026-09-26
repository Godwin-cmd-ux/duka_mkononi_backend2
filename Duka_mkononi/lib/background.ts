/**
 * Background sync — keeps the SQLite cache fresh even when the app is closed or
 * in the background (Android ~15 min minimum interval, iOS scheduled by the OS).
 *
 * The task re-fetches the role's core datasets and writes them into the same
 * SQLite cache the screens read from, so the next time the app opens every page
 * renders instantly with up-to-date data.
 *
 * Registration happens once from the root layout (`setupBackgroundSync`).
 */

import AsyncStorage from '@react-native-async-storage/async-storage';
import * as BackgroundFetch from 'expo-background-fetch';
import * as TaskManager from 'expo-task-manager';
import { Platform } from 'react-native';
import { API_BASE_URL } from '../constants/api';
import { setCache } from '../db/cache';
import { fetchWithTimeout } from './network';

export const BACKGROUND_SYNC_TASK = 'dukamkononi-background-sync';

const TASK_TIMEOUT_MS = 9 * 60 * 1000;

function authHeaders(token: string): Record<string, string> {
  return {
    Authorization: `Bearer ${token}`,
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
}

async function fetchJson<T>(url: string, token: string): Promise<T | null> {
  try {
    const res = await fetchWithTimeout(url, { headers: authHeaders(token) }, 20_000);
    if (!res.ok) return null;
    return (await res.json()) as T;
  } catch {
    return null;
  }
}

/** Fetch the datasets relevant to the logged-in role and refresh the cache. */
export async function runBackgroundSync(): Promise<boolean> {
  const token = await AsyncStorage.getItem('userToken');
  const role = await AsyncStorage.getItem('userRole');
  if (!token || !role) return false;

  const base = API_BASE_URL;
  const run = async (items: Array<[string, () => Promise<unknown>]>) => {
    for (const [key, fn] of items) {
      const data = await fn();
      if (data !== null && data !== undefined) {
        await setCache(key, data).catch(() => {});
      }
    }
  };

  if (role === 'seller') {
    await run([
      ['user:profile', () => fetchJson(base + '/api/user/profile', token)],
      ['sales:my', () => fetchJson(base + '/api/sales/my', token)],
      ['products:my', () => fetchJson(base + '/api/products/my', token)],
      ['expenses:categories', () => fetchJson(base + '/api/office-expenses/categories', token)],
      ['expenses:today', () => fetchJson(base + '/api/office-expenses/today', token)],
    ]);
    return true;
  }

  if (role === 'admin') {
    await run([
      ['user:profile', () => fetchJson(base + '/api/user/profile', token)],
      ['admin:users', () => fetchJson(base + '/api/admin/users', token)],
      ['admin:products', () => fetchJson(base + '/api/admin/products', token)],
      ['admin:sales', () => fetchJson(base + '/api/admin/sales', token)],
      ['admin:stats', () => fetchJson(base + '/api/admin/stats', token)],
      ['sales:my', () => fetchJson(base + '/api/sales/my', token)],
      ['expenses:categories', () => fetchJson(base + '/api/office-expenses/categories', token)],
    ]);
    return true;
  }

  // customer (and any unknown role) — public feeds
  await run([
    ['businesses', () => fetchJson(base + '/api/businesses', token)],
    ['matangazo', () => fetchJson(base + '/api/matangazo', token)],
    ['user:profile', () => fetchJson(base + '/api/user/profile', token)],
  ]);
  return true;
}

TaskManager.defineTask(BACKGROUND_SYNC_TASK, async () => {
  try {
    const start = Date.now();
    const timeout = setTimeout(() => {
      TaskManager.unregisterTaskAsync(BACKGROUND_SYNC_TASK).catch(() => {});
    }, TASK_TIMEOUT_MS);

    const ok = await runBackgroundSync();
    clearTimeout(timeout);

    if (!ok) return BackgroundFetch.BackgroundFetchResult.NoData;
    console.log(`📦 Background sync completed in ${Date.now() - start}ms`);
    return BackgroundFetch.BackgroundFetchResult.NewData;
  } catch (e) {
    console.warn('⚠️ Background sync failed:', e);
    return BackgroundFetch.BackgroundFetchResult.Failed;
  }
});

/** Register the background task once on app start. */
export async function setupBackgroundSync(): Promise<void> {
  if (Platform.OS === 'web') return;
  if (!(await TaskManager.isTaskRegisteredAsync(BACKGROUND_SYNC_TASK))) {
    try {
      await BackgroundFetch.registerTaskAsync(BACKGROUND_SYNC_TASK, {
        minimumInterval: 15, // minutes (Android)
        stopOnTerminate: false,
        startOnBoot: true,
      });
      console.log('📦 Background sync registered');
    } catch (e) {
      console.warn('⚠️ Background sync registration failed:', e);
    }
  }
}