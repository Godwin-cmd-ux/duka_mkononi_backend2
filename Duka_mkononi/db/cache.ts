/**
 * SQLite-backed JSON cache.
 *
 * Every screen that renders remote data stores its payload here so the page can
 * open instantly (fast local read) while a fresh network copy is fetched in the
 * background. When the fresh copy arrives the page is updated silently and the
 * cache row is overwritten, so the next launch is even faster.
 *
 * - Native (Android/iOS): stored in the bundled SQLite database (expo-sqlite).
 * - Web: expo-sqlite has no web implementation yet, so we transparently fall
 *   back to AsyncStorage with the same key space.
 */

import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SQLite from 'expo-sqlite';
import { Platform } from 'react-native';

const DB_NAME = 'dukamkononi.db';
const WEB_PREFIX = 'sqlite_cache:';
const MAX_CACHE_AGE_MS = 30 * 24 * 60 * 60 * 1000; // 30 days

let dbPromise: Promise<SQLite.SQLiteDatabase | null> | null = null;

function getDb(): Promise<SQLite.SQLiteDatabase | null> {
  if (!dbPromise) {
    dbPromise = (async () => {
      if (Platform.OS === 'web') return null;
      try {
        const db = await SQLite.openDatabaseAsync(DB_NAME);
        await db.execAsync(`
          PRAGMA journal_mode = WAL;
          CREATE TABLE IF NOT EXISTS cache (
            key        TEXT PRIMARY KEY NOT NULL,
            body       TEXT NOT NULL,
            updated_at INTEGER NOT NULL
          );
          CREATE INDEX IF NOT EXISTS idx_cache_updated ON cache (updated_at);
        `);
        return db;
      } catch (e) {
        console.warn('⚠️ SQLite open failed, falling back to AsyncStorage cache:', e);
        return null;
      }
    })();
  }
  return dbPromise;
}

function webKey(key: string): string {
  return WEB_PREFIX + key;
}

/** Read a cached JSON payload (or `null` when nothing is stored). */
export async function getCache<T = unknown>(key: string): Promise<T | null> {
  try {
    const db = await getDb();
    if (db) {
      const row = await db.getFirstAsync<{ body: string }>(
        'SELECT body FROM cache WHERE key = ?',
        [key]
      );
      if (!row) return null;
      return JSON.parse(row.body) as T;
    }
    const raw = await AsyncStorage.getItem(webKey(key));
    return raw ? (JSON.parse(raw) as T) : null;
  } catch (e) {
    console.warn('⚠️ Cache read failed:', key, e);
    return null;
  }
}

/** Read a cached payload that was updated within `maxAgeMs` or return null. */
export async function getFreshCache<T = unknown>(
  key: string,
  maxAgeMs: number
): Promise<T | null> {
  try {
    const db = await getDb();
    if (db) {
      const row = await db.getFirstAsync<{ body: string; updated_at: number }>(
        'SELECT body, updated_at FROM cache WHERE key = ?',
        [key]
      );
      if (!row) return null;
      if (Date.now() - Number(row.updated_at) > maxAgeMs) return null;
      return JSON.parse(row.body) as T;
    }
    const meta = await AsyncStorage.getItem(webKey(key) + ':meta');
    if (meta) {
      const updatedAt = Number(meta);
      if (Date.now() - updatedAt > maxAgeMs) return null;
    }
    const raw = await AsyncStorage.getItem(webKey(key));
    return raw ? (JSON.parse(raw) as T) : null;
  } catch (e) {
    console.warn('⚠️ Fresh cache read failed:', key, e);
    return null;
  }
}

/** Store (or replace) a JSON payload for a cache key. */
export async function setCache(key: string, body: unknown): Promise<void> {
  const data = typeof body === 'string' ? body : JSON.stringify(body);
  try {
    const db = await getDb();
    if (db) {
      await db.runAsync(
        `INSERT INTO cache (key, body, updated_at) VALUES (?, ?, ?)
         ON CONFLICT(key) DO UPDATE SET body = excluded.body, updated_at = excluded.updated_at`,
        [key, data, Date.now()]
      );
      return;
    }
    await AsyncStorage.setItem(webKey(key), data);
    await AsyncStorage.setItem(webKey(key) + ':meta', String(Date.now()));
  } catch (e) {
    console.warn('⚠️ Cache write failed:', key, e);
  }
}

/** Delete every cache row whose key starts with `prefix`. */
export async function removeCacheKeys(prefix: string): Promise<void> {
  try {
    const db = await getDb();
    if (db) {
      await db.runAsync('DELETE FROM cache WHERE key LIKE ?', [`${prefix}%`]);
      return;
    }
    const keys = await AsyncStorage.getAllKeys();
    const targets = keys.filter(
      (k) => k.startsWith(webKey(prefix)) && !k.endsWith(':meta')
    );
    const metaTargets = keys.filter(
      (k) => k.startsWith(webKey(prefix)) && k.endsWith(':meta')
    );
    if (targets.length || metaTargets.length) {
      await AsyncStorage.multiRemove([...targets, ...metaTargets]);
    }
  } catch (e) {
    console.warn('⚠️ Cache remove failed:', prefix, e);
  }
}

/** Drop the entire cache (used on logout so users never see another person's data). */
export async function clearAllCache(): Promise<void> {
  try {
    const db = await getDb();
    if (db) {
      await db.runAsync('DELETE FROM cache');
      return;
    }
    const keys = await AsyncStorage.getAllKeys();
    const targets = keys.filter((k) => k.startsWith(WEB_PREFIX));
    if (targets.length) await AsyncStorage.multiRemove(targets);
  } catch (e) {
    console.warn('⚠️ Cache clear failed:', e);
  }
}

/** Housekeeping: drop rows older than the retention window. */
export async function pruneCache(): Promise<void> {
  try {
    const db = await getDb();
    const cutoff = Date.now() - MAX_CACHE_AGE_MS;
    if (db) {
      await db.runAsync('DELETE FROM cache WHERE updated_at < ?', [cutoff]);
    }
  } catch (e) {
    console.warn('⚠️ Cache prune failed:', e);
  }
}

/** Debug helper: number of rows currently in the cache. */
export async function cacheSize(): Promise<number> {
  try {
    const db = await getDb();
    if (db) {
      const row = await db.getFirstAsync<{ n: number }>(
        'SELECT COUNT(*) AS n FROM cache'
      );
      return row?.n ?? 0;
    }
    const keys = await AsyncStorage.getAllKeys();
    return keys.filter((k) => k.startsWith(WEB_PREFIX) && !k.endsWith(':meta')).length;
  } catch (e) {
    console.warn('⚠️ Cache size failed:', e);
    return 0;
  }
}