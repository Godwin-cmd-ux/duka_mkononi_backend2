/**
 * Web shim for react-native's `Alert`.
 *
 * react-native-web does NOT implement `Alert` — every `Alert.alert(...)` is a
 * no-op in the browser, so buttons whose flow depends on the confirm dialog
 * (logout, deletes, approvals, …) appeared "dead". This module re-binds
 * `Alert.alert` on the web build to the browser's native `alert`/`confirm` so
 * those flows work in the browser exactly like they do on Android/iOS.
 *
 * It must be imported BEFORE any screen renders (see app/_layout.tsx).
 */

import { Alert, Platform } from 'react-native';

type AlertButton = {
  text?: string;
  onPress?: () => void;
  style?: 'default' | 'cancel' | 'destructive';
};

if (Platform.OS === 'web') {
  const webAlert = (title: string, message?: string, buttons?: AlertButton[], _options?: unknown) => {
    const text = [title, message].filter(Boolean).join('\n');
    const actionable = (buttons ?? []).filter((b) => typeof b?.onPress === 'function');
    const hasConfirmFlow = Array.isArray(buttons) && actionable.length > 0;

    if (hasConfirmFlow) {
      const primary = actionable[actionable.length - 1];
      const ok = typeof globalThis.confirm === 'function' ? globalThis.confirm(text) : true;
      if (ok && primary) primary.onPress?.();
    } else if (typeof globalThis.alert === 'function') {
      globalThis.alert(text);
    }
  };

  (Alert as unknown as { alert: typeof webAlert }).alert = webAlert;
}

export {};