import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { AppState } from 'react-native';
import { API_BASE_URL } from '../constants/api';
import { fetchWithTimeout } from '../lib/network';

/**
 * Centralized business feature state for the mobile app.
 *
 * The Laravel backend is the authority. This context only mirrors the three
 * switches so navigation and screens agree with the web portal, caches the
 * last value for offline launch, and refreshes whenever the app returns to the
 * foreground. A stale cache can never ENABLE a disabled module: the API still
 * answers 403 FEATURE_DISABLED.
 */
export interface BusinessFeatures {
  fridge: boolean;
  scanner: boolean;
  services: boolean;
  product_management: boolean;
}

const DEFAULTS: BusinessFeatures = { fridge: true, scanner: false, services: true, product_management: false };
const CACHE_KEY = 'dm_business_features_v1';

interface FeatureContextValue {
  features: BusinessFeatures;
  isEnabled: (name: keyof BusinessFeatures) => boolean;
  refresh: () => Promise<void>;
}

const FeatureContext = createContext<FeatureContextValue | null>(null);

function normalize(raw: any): BusinessFeatures | null {
  if (!raw || typeof raw !== 'object') return null;
  return {
    fridge: raw.fridge !== false,
    scanner: raw.scanner === true,
    services: raw.services !== false,
    product_management: raw.product_management === true,
  };
}

export function FeatureProvider({ children }: { children: ReactNode }) {
  const [features, setFeatures] = useState<BusinessFeatures>(DEFAULTS);

  const persist = useCallback((next: BusinessFeatures) => {
    setFeatures(next);
    AsyncStorage.setItem(CACHE_KEY, JSON.stringify(next)).catch(() => {});
  }, []);

  const refresh = useCallback(async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/settings/features`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true',
        },
      }).catch(() => null);

      if (!response || !response.ok) return;

      const body = await response.json().catch(() => null);
      const next = normalize(body && body.features);
      if (next) persist(next);
    } catch {
      // Offline / transient failure: keep the cached value. The server still
      // rejects a disabled feature, so this cannot open a closed module.
    }
  }, [persist]);

  useEffect(() => {
    let cancelled = false;

    AsyncStorage.getItem(CACHE_KEY)
      .then((raw) => {
        if (cancelled || !raw) return;
        try {
          const cached = normalize(JSON.parse(raw));
          if (cached) setFeatures(cached);
        } catch {
          /* corrupt cache: keep defaults and refresh below */
        }
      })
      .catch(() => {});

    refresh();

    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') refresh();
    });

    return () => {
      cancelled = true;
      subscription.remove();
    };
  }, [refresh]);

  const value = useMemo<FeatureContextValue>(
    () => ({
      features,
      isEnabled: (name) => features[name] !== false,
      refresh,
    }),
    [features, refresh]
  );

  return <FeatureContext.Provider value={value}>{children}</FeatureContext.Provider>;
}

export function useFeatures(): FeatureContextValue {
  const ctx = useContext(FeatureContext);
  if (!ctx) {
    throw new Error('useFeatures must be used inside <FeatureProvider>');
  }
  return ctx;
}
