/*
 * Duka Mkononi — theme manager (window.DMTheme).
 *
 * One lightweight runtime for the website's approved themes:
 *   white-professional      default (the original Duka Mkononi design)
 *   royal-champagne-cream   warm cream / ivory / royal gold
 *   midnight-royal-gold     premium dark: charcoal / gold / amber-orange
 *   emerald-bazaar          fresh light: mist / white / deep emerald
 *   oceanic-sapphire        icy daylight: ocean navy / sapphire / white
 *
 * Responsibilities:
 *   - validate every stored / incoming identifier against the allow-list
 *     (local storage can never inject CSS, HTML or scripts),
 *   - apply the theme instantly on <html data-theme="..."> without reloads,
 *   - persist the visitor's choice under the dedicated key
 *     "dukamkononi_theme",
 *   - synchronize with the account (PUT /api/user/theme) when a session
 *     token exists, failing silently so the local preference always wins
 *     on failure,
 *   - survive sign-out: clearSession() drops the session keys but keeps the
 *     choice, so logging back in does not restart from White Professional.
 *
 * The early no-flash snippet lives in partials/dm-locale; this module loads
 * after it and takes over interaction, storage and synchronization.
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'dukamkononi_theme';
    // Pre-release key: read once and migrated (see readStored) so an early
    // tester's saved choice is not lost when the brand-corrected key shipped.
    var LEGACY_STORAGE_KEY = 'lukamkononi_theme';
    // Session flag: the visitor picked a theme in THIS tab (the picker was
    // used). It is what tells the login form that the local value is a fresh,
    // explicit choice — not just the theme restored from an earlier page — so
    // the account's stored preference must not overwrite it on sign-in.
    // sessionStorage keeps it across that tab's navigations only.
    var PICKED_KEY = 'dukamkononi_theme_picked';
    var SYNC_URL = '/api/user/theme';
    var DEFAULT = 'white-professional';
    var ALLOWED = ['white-professional', 'royal-champagne-cream', 'midnight-royal-gold', 'emerald-bazaar', 'oceanic-sapphire'];
    var listeners = [];

    function isValid(theme) {
        return ALLOWED.indexOf(String(theme === undefined || theme === null ? '' : theme)) > -1;
    }

    function readStored() {
        try {
            if (!global.localStorage) return null;
            var value = localStorage.getItem(STORAGE_KEY);
            if (!isValid(value)) {
                var legacy = localStorage.getItem(LEGACY_STORAGE_KEY);
                if (isValid(legacy)) {
                    localStorage.setItem(STORAGE_KEY, legacy);
                    value = legacy;
                }
            }
            try { localStorage.removeItem(LEGACY_STORAGE_KEY); } catch (e2) { /* ignore */ }
            return isValid(value) ? value : null;
        } catch (e) {
            return null;
        }
    }

    function current() {
        var attr = document.documentElement.getAttribute('data-theme');
        return isValid(attr) ? attr : DEFAULT;
    }

    function apply(theme) {
        if (!isValid(theme)) theme = DEFAULT;
        document.documentElement.setAttribute('data-theme', theme);
    }

    function persist(theme) {
        if (!isValid(theme)) return;
        try {
            if (global.localStorage) localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) { /* private mode / quota — the in-page theme still holds */ }
    }

    function markPicked() {
        try {
            if (global.sessionStorage) sessionStorage.setItem(PICKED_KEY, '1');
        } catch (e) { /* storage unavailable: login falls back to the account value */ }
    }

    function wasPicked() {
        try {
            return !!(global.sessionStorage && sessionStorage.getItem(PICKED_KEY) === '1');
        } catch (e) {
            return false;
        }
    }

    function clearPicked() {
        try {
            if (global.sessionStorage) sessionStorage.removeItem(PICKED_KEY);
        } catch (e) { /* nothing to clear */ }
    }

    function notify() {
        var theme = current();
        listeners.slice().forEach(function (fn) {
            try { fn(theme); } catch (e) { /* a listener must not break a switch */ }
        });
    }

    /* Best-effort database sync for an authenticated session. Any failure
       (offline, token expired, migration not run yet) is swallowed: the local
       preference has already been applied and must survive. */
    function syncToServer(theme) {
        if (!isValid(theme)) return Promise.resolve(false);
        try {
            if (!global.localStorage || !localStorage.getItem('userToken')) return Promise.resolve(false);
        } catch (e) {
            return Promise.resolve(false);
        }

        return fetch(SYNC_URL, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('userToken')
            },
            body: JSON.stringify({ theme: theme })
        }).then(function (res) {
            return res.ok;
        }).catch(function () {
            return false;
        });
    }

    /* Explicit selection: apply immediately, keep locally, sync when signed in. */
    function set(theme) {
        if (!isValid(theme)) return current();
        apply(theme);
        persist(theme);
        markPicked();
        notify();
        syncToServer(theme);
        return theme;
    }

    /* Server-authoritative value (login response). A valid account preference
       always wins over the browsing device; an absent/invalid one keeps the
       visitor's local choice untouched. */
    function adopt(serverTheme) {
        if (isValid(serverTheme)) {
            apply(serverTheme);
            persist(serverTheme);
            notify();
            return serverTheme;
        }
        var local = readStored();
        if (local) {
            apply(local);
            notify();
        }
        return current();
    }

    /* Sign-out helper used by every portal logout button (they used to call
       localStorage.clear() outright, which also destroyed the visitor's theme
       choice — so the next login started from White Professional and the
       chosen theme looked lost around login). Everything session-related is
       dropped; the validated theme preference is written straight back
       (readStored() has already migrated the pre-release key by then). */
    function clearSession() {
        var keep = readStored();
        try {
            if (global.localStorage) localStorage.clear();
        } catch (e) {
            return false;
        }
        if (keep) {
            try { localStorage.setItem(STORAGE_KEY, keep); } catch (e2) { /* private mode / quota */ }
        }
        return true;
    }

    /* Boot-time restore. The inline snippet in partials/dm-locale already
       applies the stored theme before first paint (for no flash), but this
       module must not depend on that snippet knowing the full allow-list:
       a stored theme is re-applied here as soon as the module loads. Pages
       that include the widget without the snippet, and any theme added to
       ALLOWED later, therefore still carry the preference across pages. */
    (function boot() {
        var stored = readStored();
        if (stored) apply(stored);
    })();

    global.DMTheme = {
        __booted: true,
        STORAGE_KEY: STORAGE_KEY,
        THEMES: ALLOWED.slice(),
        isValid: isValid,
        current: current,
        apply: apply,
        set: set,
        adopt: adopt,
        clearSession: clearSession,
        wasPicked: wasPicked,
        clearPicked: clearPicked,
        syncToServer: syncToServer,
        onChange: function (fn) {
            if (typeof fn !== 'function') return;
            listeners.push(fn);
            fn(current());
        }
    };
})(window);