import '../lib/web-alert';
import { Stack, useRouter } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect, useRef, useState } from 'react';
import { pruneCache } from '../db/cache';
import { setupBackgroundSync } from '../lib/background';
import { pingServer } from '../lib/network';
import { LanguageProvider } from '../context/LanguageContext';
import { SessionProvider, useSession } from '../context/SessionContext';
import { getDashboardRouteForRole } from '../constants/session';
import { ThemeProvider } from '../lib/theme';

// Keep the native splash screen visible until the session has been resolved
SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  return (
    // NOTE: GestureHandlerRootView was removed — nothing in the app uses
    // react-native-gesture-handler, and on RN 0.81 (New Architecture) its root
    // wrapper intercepts touches in a way that broke TextInput tap-to-focus
    // on real Android devices (no cursor, no keyboard).
    <SessionProvider>
      <ThemeProvider>
        <LanguageProvider>
          <RootNavigator />
        </LanguageProvider>
      </ThemeProvider>
    </SessionProvider>
  );
}

function RootNavigator() {
  const { status, isLoggedIn, role } = useSession();
  const router = useRouter();
  const [splashMinElapsed, setSplashMinElapsed] = useState(false);

  // 📦 Offline-first engine: register the background task (keeps SQLite fresh
  // even when the app is closed) and prune stale cache rows on launch.
  useEffect(() => {
    setupBackgroundSync().catch(() => {});
    // Probe the API hosts once at launch so the reachable one is remembered
    // before the first data screen mounts. On carriers where the primary host
    // is unreachable, this spares every screen from waiting out its own
    // timeout against the dead host before falling back.
    pingServer().catch(() => {});
    const timer = setTimeout(() => pruneCache().catch(() => {}), 60_000);
    return () => clearTimeout(timer);
  }, []);

  // Let the splash breathe for at least ~1s even when the session read is instant
  useEffect(() => {
    const timer = setTimeout(() => setSplashMinElapsed(true), 1000);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    if (status === 'ready' && splashMinElapsed) {
      SplashScreen.hideAsync().catch(() => {});
    }
  }, [status, splashMinElapsed]);

  // 🚪 Logout safety net: the moment the session flips to logged-out, send the
  // user home. This makes logout reliable even if a handler's own
  // router.replace('/(tabs)') fires before the route guards have re-rendered.
  // ⚠️ IMPORTANT: only navigate once the <Stack> is actually mounted
  // (splashMinElapsed), otherwise expo-router throws 'Attempted to navigate
  // before mounting the Root Layout component', which crashes the tree and
  // leaves the app stuck on the native splash screen.
  useEffect(() => {
    if (status === 'ready' && splashMinElapsed && !isLoggedIn) {
      router.replace('/(tabs)');
    }
  }, [status, splashMinElapsed, isLoggedIn, router]);

  // 🔐 Route newly signed-in users to their role's dashboard. This MUST live
  // here, not in the login screen: the login screen unmounts the instant the
  // session flips (its <Stack.Protected> guard turns false), so navigating from
  // it races the guard — the target route group (muuzaji / mteja / msimamizi)
  // isn't mounted yet, the replace lands on nothing, and the screen goes blank.
  // Here the guard has already re-rendered and mounted the role group before
  // this effect runs, so the dashboard route always exists. The ref makes it a
  // one-shot per login, so it never hijacks normal in-app navigation.
  const wasLoggedIn = useRef(false);
  useEffect(() => {
    if (status !== 'ready' || !splashMinElapsed) return;
    if (isLoggedIn && !wasLoggedIn.current) {
      router.replace(getDashboardRouteForRole(role) as any);
    }
    wasLoggedIn.current = isLoggedIn;
  }, [status, splashMinElapsed, isLoggedIn, role, router]);

  if (status !== 'ready' || !splashMinElapsed) {
    // Native splash stays visible until the cached session has been resolved.
    // From here the router sends the user straight to the correct screen, so the
    // home screen (app/(tabs)/index.tsx) can never flash when logged in.
    return null;
  }

  // 🛡️ A platform super admin (super_admin, or the legacy system_admin
  // spelling) owns the msimamizi portal too — getDashboardRouteForRole() sends
  // them to /msimamizi, so the guard must mount that group for them as well,
  // otherwise they route to a screen that is not rendered and land blank.
  const isAdmin =
    isLoggedIn && (role === 'admin' || role === 'super_admin' || role === 'system_admin');
  const isSeller = isLoggedIn && role === 'seller';
  const isCustomer = isLoggedIn && role === 'customer';
  const knownRole = isAdmin || isSeller || isCustomer;

  return (
    <Stack screenOptions={{ headerShown: false }}>
      {/* 🔓 Public screens — reachable when logged OUT (or role is unknown, so
          the user lands back on the home screen instead of a blank screen) */}
      <Stack.Protected guard={!isLoggedIn || !knownRole}>
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen
          name="login"
          options={{
            headerShown: false,
            animation: 'slide_from_right',
          }}
        />
        <Stack.Screen
          name="admin_signup"
          options={{
            headerShown: false,
            animation: 'slide_from_right',
          }}
        />
        <Stack.Screen
          name="cashier_signup"
          options={{
            headerShown: false,
            animation: 'slide_from_right',
          }}
        />
        <Stack.Screen
          name="client_signup"
          options={{
            headerShown: false,
            animation: 'slide_from_right',
          }}
        />
        {/* 🎠 Post-registration onboarding slideshow (profile photo + business map location) */}
        <Stack.Screen
          name="onboarding"
          options={{
            headerShown: false,
            animation: 'fade',
          }}
        />
        <Stack.Screen
          name="forgot"
          options={{
            headerShown: false,
            animation: 'slide_from_right',
          }}
        />
        <Stack.Screen
          name="modal"
          options={{
            presentation: 'modal',
          }}
        />
      </Stack.Protected>

      {/* 👑 Admin (msimamizi) + System Admin */}
      <Stack.Protected guard={isAdmin}>
        <Stack.Screen name="msimamizi" options={{ headerShown: false }} />
        <Stack.Screen name="system_admin" options={{ headerShown: false }} />
      </Stack.Protected>

      {/* 🛒 Seller (muuzaji) */}
      <Stack.Protected guard={isSeller}>
        <Stack.Screen name="muuzaji" options={{ headerShown: false }} />
      </Stack.Protected>

      {/* 👤 Customer (mteja) */}
      <Stack.Protected guard={isCustomer}>
        <Stack.Screen name="mteja" options={{ headerShown: false }} />
      </Stack.Protected>
    </Stack>
  );
}
