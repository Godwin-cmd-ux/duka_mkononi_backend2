import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { API_BASE_URLS } from '../constants/api';

/**
 * 🎨 Theme system (mobile) — the same two themes as the website.
 *
 *   white-professional    the existing Duka Mkononi design (DEFAULT, unchanged)
 *   royal-champagne-cream warm cream / ivory / royal gold
 *
 * The identifiers, the localStorage/AsyncStorage key ("dukamkononi_theme") and
 * the sync endpoint (PUT /api/user/theme) are shared with the website, so a
 * preference made on one platform is adopted by the other at next sync.
 *
 * White Professional's values below are the app's current production hexes,
 * so the default theme renders exactly as before this system existed.
 * Royal values mirror public/css/themes.css on the website, one palette.
 *
 * Screens opt in through useTheme().colors; screens that still use their own
 * literal colors are unaffected (documented boundary, same as the web
 * portal pages' inline styles).
 */

export type ThemeId = 'white-professional' | 'royal-champagne-cream';

export const THEME_IDS: readonly ThemeId[] = ['white-professional', 'royal-champagne-cream'];

export interface ThemeColors {
  /** Page background (screens, layouts). */
  page: string;
  /** Card / surface background. */
  card: string;
  /** Soft inset surface (chips, stat tiles). */
  soft: string;
  /** Primary text. */
  ink: string;
  /** Secondary text. */
  muted: string;
  /** Faint/decorative text. */
  muted2: string;
  /** Borders, dividers, hairlines. */
  line: string;
  /** Stronger borders. */
  line2: string;
  /** Brand accent (tabs, links, active states). */
  primary: string;
  /** Darker accent partner (gradients, pressed states). */
  primaryDark: string;
  /** Accent-tinted background (active nav, avatars, highlights). */
  tint: string;
  /** Semantic status colors — identical hues in both themes by design. */
  success: string;
  danger: string;
  warn: string;
}

export const PALETTES: Record<ThemeId, ThemeColors> = {
  'white-professional': {
    page: '#f8f9fa',
    card: '#ffffff',
    soft: '#f8f9fa',
    ink: '#2c3e50',
    muted: '#7f8c8d',
    muted2: '#95a5a6',
    line: '#ecf0f1',
    line2: '#e0e0e0',
    primary: '#667eea',
    primaryDark: '#764ba2',
    tint: '#ede7f6',
    success: '#27ae60',
    danger: '#e74c3c',
    warn: '#f39c12',
  },
  'royal-champagne-cream': {
    page: '#f3f2ed',
    card: '#fcfbf7',
    soft: '#f6e8ce',
    ink: '#171717',
    muted: '#5f636e',
    muted2: '#95a5a6',
    line: '#d9d8cf',
    line2: '#e7e1d0',
    primary: '#8b6f23',
    primaryDark: '#6e591b',
    tint: '#f6e8ce',
    success: '#27ae60',
    danger: '#e74c3c',
    warn: '#f39c12',
  },
};

/** Same storage key as the website — one preference across platforms. */
export const THEME_STORAGE_KEY = 'dukamkononi_theme';
/** Pre-release key, migrated once during hydration (see ThemeProvider). */
const LEGACY_THEME_STORAGE_KEY = 'lukamkononi_theme';

/** Allow-list: stored/request data is never trusted as a theme id. */
export function normalizeThemeId(value: unknown): ThemeId | null {
  return typeof value === 'string' && (THEME_IDS as readonly string[]).includes(value)
    ? (value as ThemeId)
    : null;
}

interface ThemeContextValue {
  theme: ThemeId;
  colors: ThemeColors;
  /** Switch theme: persists locally and best-effort syncs to the backend. */
  setTheme: (id: ThemeId) => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

/** Best-effort server sync (mirrors the website's dm-theme.js rules). */
async function syncThemeToServer(id: ThemeId): Promise<void> {
  try {
    const token = await AsyncStorage.getItem('userToken');
    if (!token) return; // logged out: local preference only
    for (const host of API_BASE_URLS) {
      try {
        const res = await fetch(`${host}/api/user/theme`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({ theme: id }),
        });
        if (res.ok) return;
      } catch {
        // try the next host
      }
    }
  } catch {
    // storage unavailable: the local choice still applies
  }
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<ThemeId>('white-professional');

  // Hydrate the stored preference before the first paint decision is made.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        let stored = normalizeThemeId(await AsyncStorage.getItem(THEME_STORAGE_KEY));
        if (!stored) {
          // One-time migration from the pre-release key, so an early tester's
          // saved choice survives the brand-corrected key.
          const legacy = normalizeThemeId(await AsyncStorage.getItem(LEGACY_THEME_STORAGE_KEY));
          if (legacy) {
            stored = legacy;
            await AsyncStorage.setItem(THEME_STORAGE_KEY, legacy);
            await AsyncStorage.removeItem(LEGACY_THEME_STORAGE_KEY);
          }
        }
        if (!cancelled && stored) setThemeState(stored);
      } catch {
        // storage unavailable: stay on White Professional
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const setTheme = useCallback((id: ThemeId) => {
    if (!normalizeThemeId(id)) return;
    setThemeState(id);
    AsyncStorage.setItem(THEME_STORAGE_KEY, id).catch(() => {});
    syncThemeToServer(id).catch(() => {});
  }, []);

  const value = useMemo<ThemeContextValue>(
    () => ({ theme, colors: PALETTES[theme], setTheme }),
    [theme, setTheme]
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext);
  if (!ctx) {
    // A screen rendered outside the provider must not crash; default to the
    // original design so behaviour is identical to before the theme system.
    return { theme: 'white-professional', colors: PALETTES['white-professional'], setTheme: () => {} };
  }
  return ctx;
}
