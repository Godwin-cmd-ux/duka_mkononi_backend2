{{--
    Dukamkononi website language selector.

    Usage in a page Blade file: include partials.dm-locale first, wrap the page
    markup in a verbatim block, then include this partial as the first element
    inside <body>. The active locale is read from the data-dm-locale attribute
    that partials.dm-locale puts on <html>.

    Provides the floating language button (left side, gentle vertical float) with
    its eight animated child buttons, plus window.DM for the page's own strings:

      DM.t('login.err_bad_credentials')            -> text in the active language
      DM.t('login.alert_success_msg', {userName})  -> with {placeholders} filled
      DM.locale()                                  -> active locale code
      DM.setLocale('fr')                           -> switch language and persist
      DM.onChange(fn)                              -> re-run fn on every switch

    The active locale lives in the Laravel session (routes/web.php), so a choice
    made on one page is still in force on the next one. "Dukamkononi" is a brand
    literal and is never translated.
--}}
@verbatim
<style>
    /* ===== Floating language selector (Dukamkononi website locale system) ===== */
    .dm-lang-widget {
        position: fixed;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 9999;
        font-family: 'Poppins', sans-serif;
    }
    .dm-lang-main {
        position: relative;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1px;
        background: linear-gradient(135deg, #2c3e50, #1a2530);
        color: #fff;
        box-shadow: 0 10px 24px rgba(44, 62, 80, 0.35), 0 0 0 4px rgba(255, 255, 255, 0.7);
        animation: dmFloat 7s ease-in-out infinite;
        transition: box-shadow 0.3s ease;
    }
    .dm-lang-main:hover {
        box-shadow: 0 14px 30px rgba(44, 62, 80, 0.45), 0 0 0 4px rgba(255, 255, 255, 0.95);
    }
    .dm-lang-code {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1px;
        opacity: 0.9;
        line-height: 1;
    }
    @keyframes dmFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }
    .dm-lang-menu {
        position: absolute;
        left: 72px;
        top: 50%;
        transform: translateY(-50%);
        display: flex;
        flex-direction: column;
        gap: 8px;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.28s ease, visibility 0.28s ease;
    }
    .dm-lang-menu.open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .dm-lang-item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 9px 15px;
        border-radius: 999px;
        border: 1px solid rgba(44, 62, 80, 0.08);
        background: rgba(255, 255, 255, 0.96);
        color: #2c3e50;
        font-size: 14px;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
        box-shadow: 0 6px 16px rgba(44, 62, 80, 0.12);
        opacity: 0;
        transform: translateX(-14px);
        transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1),
                    background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    }
    .dm-lang-menu.open .dm-lang-item {
        opacity: 1;
        transform: translateX(0);
        animation: dmItemFloat 7s ease-in-out infinite;
    }
    .dm-lang-menu.open .dm-lang-item:nth-child(2) { transition-delay: 0.03s; animation-delay: 0.4s; }
    .dm-lang-menu.open .dm-lang-item:nth-child(3) { transition-delay: 0.06s; animation-delay: 0.8s; }
    .dm-lang-menu.open .dm-lang-item:nth-child(4) { transition-delay: 0.09s; animation-delay: 1.2s; }
    .dm-lang-menu.open .dm-lang-item:nth-child(5) { transition-delay: 0.12s; animation-delay: 1.6s; }
    .dm-lang-menu.open .dm-lang-item:nth-child(6) { transition-delay: 0.15s; animation-delay: 2.0s; }
    .dm-lang-menu.open .dm-lang-item:nth-child(7) { transition-delay: 0.18s; animation-delay: 2.4s; }
    .dm-lang-menu.open .dm-lang-item:nth-child(8) { transition-delay: 0.21s; animation-delay: 2.8s; }
    .dm-lang-item:hover {
        background: #2c3e50;
        color: #fff;
        box-shadow: 0 10px 22px rgba(44, 62, 80, 0.3);
    }
    .dm-lang-item.active {
        background: linear-gradient(45deg, #FF9800, #FF5722);
        color: #fff;
    }
    .dm-lang-flag { font-size: 16px; line-height: 1; }
    .dm-lang-name { font-size: 14px; }
    @keyframes dmItemFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
    }
    @media (max-width: 600px) {
        .dm-lang-widget { left: 10px; }
        .dm-lang-main { width: 50px; height: 50px; }
        .dm-lang-menu {
            left: 60px;
            gap: 6px;
            max-height: 78vh;
            overflow-y: auto;
            padding: 4px;
        }
        .dm-lang-item { padding: 8px 12px; }
        .dm-lang-name { font-size: 13px; }
    }
</style>

<div class="dm-lang-widget" id="dmLangWidget">
    <button type="button" class="dm-lang-main" id="dmLangToggle" aria-haspopup="true" aria-expanded="false">
        <svg class="dm-lang-globe" width="26" height="26" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M3 12H21"></path>
            <path d="M12 3C14.6 5.6 14.6 18.4 12 21C9.4 18.4 9.4 5.6 12 3Z"></path>
        </svg>
        <span class="dm-lang-code" id="dmLangCurrent">SW</span>
    </button>
    <div class="dm-lang-menu" id="dmLangMenu" role="menu"></div>
</div>
@endverbatim
<script>
(function (global) {
    'use strict';

    // Drop any widget markup left behind by a previous copy, keeping the first.
    // This must run before the boot guard below: a duplicate include still has to
    // remove its own markup even though it will not boot a second runtime.
    (function dropDuplicateWidgets() {
        var widgets = document.querySelectorAll('#dmLangWidget');
        for (var i = 1; i < widgets.length; i++) {
            if (widgets[i] && widgets[i].parentNode) widgets[i].parentNode.removeChild(widgets[i]);
        }
    })();

    // This partial is also included by the fragment pages under msimamizi/, which
    // the sidebar layout fetches and injects.  That means a second copy of the
    // markup and this script can arrive after the first one has booted.  Bailing
    // out here keeps a single runtime, so DM.onChange listeners registered by the
    // shell survive and the fragment's own copy of the catalog is never fetched.
    if (global.DM && global.DM.__booted) return;

    var root = document.documentElement;

    // Mirrors the mobile app's language list (Duka_mkononi/context/
    // LanguageContext.tsx) and the allow-list in routes/web.php — never add or
    // remove one.
    var DEFAULT = 'sw';
    var ALLOWED = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];
    var INITIAL = root.getAttribute('data-dm-locale') || DEFAULT;

    // The language a logged-in visitor chose is stored on their account in
    // Supabase; the login response carries it back and the login page keeps it
    // in localStorage. That account language wins over the browsing session so
    // authenticated pages stay in the user's own language for as long as they
    // are logged in — even after the session cookie expires. Switching with
    // the picker only changes the session and reverts on the next page.
    // Guests (no token) fall back to the session, then to Swahili.
    function accountLocale() {
        try {
            if (!global.localStorage || !localStorage.getItem('userToken')) return null;
            var raw = localStorage.getItem('userData');
            if (!raw) return null;
            var user = JSON.parse(raw);
            var code = user && user.language ? String(user.language).toLowerCase() : '';
            return ALLOWED.indexOf(code) > -1 ? code : null;
        } catch (e) {
            return null;
        }
    }

    var catalog = null;
    var ready = false;
    var booted = false;
    var listeners = [];
    var current = accountLocale() || (ALLOWED.indexOf(INITIAL) > -1 ? INITIAL : DEFAULT);

    function bucket(locale) {
        if (!catalog) return null;
        return catalog.translations[locale] || catalog.translations[DEFAULT] || null;
    }

    function lookup(dict, path) {
        if (!dict) return null;
        var parts = String(path).split('.');
        var node = dict;
        for (var i = 0; i < parts.length; i++) {
            if (node && typeof node === 'object' && parts[i] in node) {
                node = node[parts[i]];
            } else {
                return null;
            }
        }
        return typeof node === 'string' ? node : null;
    }

    // Translated text for the active language. Falls back to Swahili and then
    // to the key itself, so a missing entry can never blank out the UI.
    function t(key, params) {
        var value = lookup(bucket(current), key);
        if (value === null) value = lookup(bucket(DEFAULT), key);
        if (value === null) return key;
        if (!params) return value;
        Object.keys(params).forEach(function (name) {
            value = value.split('{' + name + '}').join(String(params[name]));
        });
        return value;
    }

    function translateStaticText() {
        if (!catalog) return;

        Array.prototype.forEach.call(document.querySelectorAll('[data-i18n]'), function (el) {
            var key = el.getAttribute('data-i18n');
            var value = t(key);
            if (value === key) return;
            var attr = el.getAttribute('data-i18n-attr');
            if (attr) el.setAttribute(attr, value);
            else el.textContent = value;
        });

        Array.prototype.forEach.call(document.querySelectorAll('[data-i18n-html]'), function (el) {
            var key = el.getAttribute('data-i18n-html');
            var value = t(key);
            if (value !== key) el.innerHTML = value;
        });

        var label = t('ui.change_language');
        if (label) {
            var toggle = document.getElementById('dmLangToggle');
            var menu = document.getElementById('dmLangMenu');
            if (toggle) toggle.setAttribute('aria-label', label);
            if (menu) menu.setAttribute('aria-label', label);
        }
    }

    function paintActiveState() {
        var badge = document.getElementById('dmLangCurrent');
        if (badge) badge.textContent = current.toUpperCase();
        Array.prototype.forEach.call(document.querySelectorAll('.dm-lang-item'), function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-code') === current);
        });
    }

    function notify() {
        listeners.slice().forEach(function (fn) {
            try { fn(current); } catch (e) { /* a page bug must not block a switch */ }
        });
    }

    function render() {
        translateStaticText();
        paintActiveState();
        root.lang = current;
        root.setAttribute('data-dm-locale', current);
        notify();
    }

    function buildMenu() {
        var menu = document.getElementById('dmLangMenu');
        if (!menu || !catalog || !catalog.languages) return;
        menu.innerHTML = '';
        catalog.languages.forEach(function (lang) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dm-lang-item' + (lang.code === current ? ' active' : '');
            btn.setAttribute('data-code', lang.code);
            btn.setAttribute('role', 'menuitem');

            var flag = document.createElement('span');
            flag.className = 'dm-lang-flag';
            flag.textContent = lang.flag || '';

            var name = document.createElement('span');
            name.className = 'dm-lang-name';
            name.textContent = lang.name || lang.code;

            btn.appendChild(flag);
            btn.appendChild(name);
            btn.addEventListener('click', function () {
                setLocale(lang.code);
                closeMenu();
            });
            menu.appendChild(btn);
        });
    }

    function closeMenu() {
        var menu = document.getElementById('dmLangMenu');
        var toggle = document.getElementById('dmLangToggle');
        if (menu) menu.classList.remove('open');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    // Switch language, then persist it in the Laravel session so the choice
    // follows the visitor to the next page and survives a refresh.
    function setLocale(code) {
        if (ALLOWED.indexOf(code) === -1) return;
        if (code !== current) {
            current = code;
            render();
            try {
                fetch('/language/' + encodeURIComponent(code), {
                    headers: { 'Accept': 'application/json' }
                }).catch(function () {});
            } catch (e) {}
            return;
        }
        render();
    }

    function boot() {
        if (!ready) return;
        buildMenu();
        render();
        booted = true;
    }

    function onDomReady() {
        ready = true;
        if (booted) render();
        else boot();
    }

    function wireToggle() {
        var toggle = document.getElementById('dmLangToggle');
        var widget = document.getElementById('dmLangWidget');
        var menu = document.getElementById('dmLangMenu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (widget && !widget.contains(e.target)) closeMenu();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });
    }

    wireToggle();
    paintActiveState();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onDomReady);
    } else {
        onDomReady();
    }

    global.DM = {
        __booted: true,
        locales: function () { return catalog; },
        locale: function () { return current; },
        languages: function () { return (catalog && catalog.languages) || []; },
        t: t,
        setLocale: setLocale,
        onChange: function (fn) {
            if (typeof fn !== 'function') return;
            listeners.push(fn);
            if (booted) fn(current);
        }
    };

    fetch('/locales.json', { headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.json(); })
        .then(function (json) { catalog = json; boot(); })
        .catch(function () { /* keep the language already baked into the page */ });
})(window);
</script>
