import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { API_BASE_URLS } from '../constants/api';

/**
 * 🎨 Theme system (mobile) — the same themes as the website.
 *
 *   white-professional    the existing Duka Mkononi design (DEFAULT, unchanged)
 *   royal-champagne-cream warm cream / ivory / royal gold
 *   midnight-royal-gold   near-black canvas / charcoal cards / gold + orange
 *   emerald-bazaar        mist-green daylight / white cards / deep emerald
 *   oceanic-sapphire      icy daylight / white cards / ocean navy + sapphire
 *
 * The identifiers, the localStorage/AsyncStorage key ("dukamkononi_theme") and
 * the sync endpoint (PUT /api/user/theme) are shared with the website, so a
 * preference made on one platform is adopted by the other at next sync.
 *
 * White Professional's values below are the app's current production hexes,
 * so the default theme renders exactly as before this system existed.
 * Every alternative palette mirrors public/css/themes.css on the website,
 * one palette per platform (Oceanic Sapphire copies its token block).
 *
 * Screens opt in through useTheme().colors; screens that still use their own
 * literal colors are unaffected (documented boundary, same as the web
 * portal pages' inline styles).
 */

export type ThemeId =
  | 'white-professional'
  | 'royal-champagne-cream'
  | 'midnight-royal-gold'
  | 'emerald-bazaar'
  | 'oceanic-sapphire';

export const THEME_IDS: readonly ThemeId[] = [
  'white-professional',
  'royal-champagne-cream',
  'midnight-royal-gold',
  'emerald-bazaar',
  'oceanic-sapphire',
];

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
  'midnight-royal-gold': {
    page: '#050505',
    card: '#101010',
    soft: '#282213',
    ink: '#f8f8f8',
    muted: '#a1a1aa',
    muted2: '#8b8b93',
    line: '#303030',
    line2: '#262626',
    primary: '#ff9f1c',
    primaryDark: '#e88b0c',
    tint: '#282213',
    success: '#27ae60',
    danger: '#e74c3c',
    warn: '#f39c12',
  },
  'emerald-bazaar': {
    page: '#f1f7f4',
    card: '#ffffff',
    soft: '#e3f1ea',
    ink: '#0f241d',
    muted: '#4f6b60',
    muted2: '#7e968c',
    line: '#cfdfd7',
    line2: '#deece5',
    primary: '#0e7a5f',
    primaryDark: '#0a5f4a',
    tint: '#dcefe5',
    success: '#27ae60',
    danger: '#e74c3c',
    warn: '#f39c12',
  },
  'oceanic-sapphire': {
    page: '#f4f8ff',
    card: '#ffffff',
    soft: '#eff6ff',
    ink: '#102a43',
    muted: '#64748b',
    muted2: '#94a3b8',
    line: '#d7e3f0',
    line2: '#e4eefa',
    primary: '#2563eb',
    primaryDark: '#1d4ed8',
    tint: '#eaf3ff',
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
