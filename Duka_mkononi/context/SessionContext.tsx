import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { clearAllCache } from '../db/cache';
import { stopAllSync } from '../lib/syncer';
import { SESSION_KEYS } from '../constants/session';

// 🔐 SessionContext — single source of truth for the login session.
//
// The root layout (app/_layout.tsx) reads this while the native splash screen is
// showing, then renders role-scoped route guards (<Stack.Protected />), so the
// home screen can never appear when a user is logged in. Logging in calls
// signIn(), logging out calls signOut() — this keeps the guards permanently in
// sync with whatever is actually stored in AsyncStorage.

export interface SessionContextValue {
  /** 'loading' while the cached session is being read on app launch. */
  status: 'loading' | 'ready';
  isLoggedIn: boolean;
  /** Cached backend role: 'admin' | 'seller' | 'customer' | null */
  role: string | null;
  /** Mark the user as logged in (call right after a successful login). */
  signIn: (role: string) => void;
  /** Clear the cached session everywhere and mark the user as logged out. */
  signOut: () => Promise<void>;
}

const SessionContext = createContext<SessionContextValue | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<'loading' | 'ready'>('loading');
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [role, setRole] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    let safety: ReturnType<typeof setTimeout>;

    (async () => {
      try {
        const [loggedIn, storedRole, token] = await Promise.all([
          AsyncStorage.getItem('isLoggedIn'),
          AsyncStorage.getItem('userRole'),
          AsyncStorage.getItem('userToken'),
        ]);
        if (cancelled) return;

        const valid = loggedIn === 'true' && !!storedRole && !!token;
        setIsLoggedIn(valid);
        setRole(valid ? storedRole : null);
      } catch (error) {
        console.warn('⚠️ Session load failed:', error);
        if (!cancelled) {
          setIsLoggedIn(false);
          setRole(null);
        }
      } finally {
        // Never leave the user stuck on the splash screen
        if (!cancelled) setStatus('ready');
      }
    })();

    // Safety net in case the AsyncStorage read ever hangs
    safety = setTimeout(() => {
      if (!cancelled) setStatus('ready');
    }, 5000);

    return () => {
      cancelled = true;
      clearTimeout(safety);
    };
  }, []);

  const value = useMemo<SessionContextValue>(
    () => ({
      status,
      isLoggedIn,
      role,
      signIn: (r: string) => {
        setIsLoggedIn(true);
        setRole(r);
      },
      signOut: async () => {
        try {
          stopAllSync();
          await clearAllCache();
          await AsyncStorage.multiRemove([...SESSION_KEYS]);
        } catch (error) {
          console.warn('⚠️ Failed to clear session storage:', error);
        }
        setIsLoggedIn(false);
        setRole(null);
      },
    }),
    [status, isLoggedIn, role]
  );

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionContextValue {
  const ctx = useContext(SessionContext);
  if (!ctx) {
    throw new Error('useSession must be used inside <SessionProvider>');
  }
  return ctx;
}
