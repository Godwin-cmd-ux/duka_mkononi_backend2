/**
 * Dukamkononi centralized feature visibility (web).
 *
 * ONE script, loaded from the universal locale partial, applies the business's
 * feature switches to every page. It does not decide security: the API rejects
 * a disabled module (403 FEATURE_DISABLED) even when this script is bypassed.
 * Its job is to keep the navigation honest.
 *
 *   fridge   hides links to the Fridge page and blocks a direct visit
 *   services hides service-selling/creation entry points only; historical
 *            service sales remain readable
 *   scanner  hides barcode scan/receiving controls; the API also refuses
 *            scanner operations when the switch is off
 *
 * The value is cached in sessionStorage for the tab so moving between pages
 * does not re-fetch, and a stale cache can never ENABLE something the server
 * has disabled (the API still refuses). On a network failure the last known
 * value is used; with no cache at all the pre-settings default (enabled) is
 * used so an offline seller keeps the navigation they had before.
 */
(function () {
    'use strict';

    var CACHE_KEY = 'dm_business_features_v1';
    var DEFAULTS = { fridge: true, scanner: false, services: true, product_management: false };

    function readCache() {
        try {
            var raw = sessionStorage.getItem(CACHE_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function writeCache(features) {
        try {
            sessionStorage.setItem(CACHE_KEY, JSON.stringify(features));
        } catch (e) { /* storage unavailable */ }
    }

    function token() {
        try { return localStorage.getItem('userToken'); } catch (e) { return null; }
    }

    function apply(features) {
        var fridge = features.fridge !== false;
        var services = features.services !== false;
        var scanner = features.scanner === true;
        var productManagement = features.product_management === true;

        if (!fridge) {
            document.querySelectorAll('[data-feature-nav="fridge"], a[href="friji"], a[href="/muuzaji/friji"]').forEach(function (el) {
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
            });
        }

        if (!productManagement) {
            document.querySelectorAll('[data-feature-nav="product_management"], a[href="/msimamizi/bidhaa-mpya"], a[href="/bidhaa-mpya"]').forEach(function (el) {
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
            });
        }

        if (!services) {
            document.querySelectorAll('[data-feature-nav="services"], a[href="uza-huduma"], a[href="/muuzaji/uza-huduma"], a[href="huduma"], a[href="/msimamizi/huduma"]').forEach(function (el) {
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
            });
        }

        document.querySelectorAll('[data-feature="scanner"]').forEach(function (el) {
            if (scanner) {
                el.style.display = '';
                el.removeAttribute('aria-hidden');
            } else {
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
            }
        });

        // Pages that render their scanner control in JavaScript (e.g. the sell
        // screen) listen for this and re-render with the authoritative value.
        try {
            window.dispatchEvent(new CustomEvent('dm:features', { detail: features }));
        } catch (e) { /* old browser */ }

        // A direct visit to a switched-off module: the page must not look
        // usable. The API refuses every call, and this explains why.
        var onFridgePage = /^\/muuzaji\/friji\/?$/.test(window.location.pathname) || /^\/friji\/?$/.test(window.location.pathname);
        var onProductManagementPage = /^\/msimamizi\/bidhaa-mpya\/?$/.test(window.location.pathname) || /^\/bidhaa-mpya\/?$/.test(window.location.pathname);
        // Only a seller visiting the shared product-management page should be
        // blocked when the switch is off; administrators always manage products.
        var sellerContext = !!document.querySelector('[data-feature-nav="product_management"]');
        if (onFridgePage && !fridge) {
            blockPage(
                'Kipengele cha Friji kimezimwa',
                'Msimamizi wa biashara amezima kipengele cha Friji/Freezer. Stock na historia yote imehifadhiwa; zinaonekana tena mara kipengele kitakapowashwa.',
                '/muuzaji/profaili'
            );
        }

        if (onProductManagementPage && !productManagement && sellerContext) {
            blockPage(
                'Kipengele cha Usimamizi wa Bidhaa kimezimwa',
                'Msimamizi wa biashara amezima kipengele cha usimamizi wa bidhaa. Bidhaa zilizopo zinabaki salama.',
                '/muuzaji/profaili'
            );
        }
    }

    function blockPage(title, message, backHref) {
        if (document.getElementById('dmFeatureBlock')) return;

        var overlay = document.createElement('div');
        overlay.id = 'dmFeatureBlock';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.style.cssText = [
            'position:fixed', 'inset:0', 'z-index:2147483000',
            'background:rgba(15,23,42,.55)', 'display:flex',
            'align-items:center', 'justify-content:center', 'padding:20px',
            'font-family:Inter,system-ui,sans-serif'
        ].join(';');

        var card = document.createElement('div');
        card.style.cssText = [
            'max-width:440px', 'width:100%', 'background:#fff',
            'border-radius:18px', 'padding:26px', 'text-align:center',
            'box-shadow:0 24px 60px rgba(15,23,42,.35)'
        ].join(';');

        var h = document.createElement('div');
        h.textContent = title;
        h.style.cssText = 'font-size:17px;font-weight:800;color:#0f172a;margin-bottom:10px;';

        var p = document.createElement('p');
        p.textContent = message;
        p.style.cssText = 'font-size:13px;line-height:1.6;color:#475569;margin:0 0 20px;';

        var a = document.createElement('a');
        a.href = backHref;
        a.textContent = 'Rudi Profaili';
        a.style.cssText = [
            'display:inline-block', 'padding:11px 18px', 'border-radius:10px',
            'background:#1e3a8a', 'color:#fff', 'text-decoration:none',
            'font-size:13px', 'font-weight:700'
        ].join(';');

        card.appendChild(h);
        card.appendChild(p);
        card.appendChild(a);
        overlay.appendChild(card);
        document.body.appendChild(overlay);
    }

    function boot() {
        var cached = readCache();
        if (cached) apply(cached);

        if (!token()) return;

        fetch('/api/settings/features', {
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token()
            }
        }).then(function (response) {
            if (!response.ok) return null;
            return response.json();
        }).then(function (body) {
            if (!body || !body.features) return;                var features = {
                    fridge: body.features.fridge !== false,
                    services: body.features.services !== false,
                    scanner: body.features.scanner === true,
                    product_management: body.features.product_management === true
                };
            writeCache(features);
            apply(features);
        }).catch(function () {
            // Offline: keep the cached/default navigation. The server remains
            // the authority and will refuse anything disabled.
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    // Exposed for pages that want to gate their own controls (e.g. the sell
    // screen's "sell a service" button) using the same cached snapshot.
    window.DMFeatures = {
        defaults: DEFAULTS,
        cached: readCache,
        isEnabled: function (name) {
            var features = readCache();
            if (!features) return DEFAULTS[name] !== false;
            return features[name] !== false;
        }
    };
})();
