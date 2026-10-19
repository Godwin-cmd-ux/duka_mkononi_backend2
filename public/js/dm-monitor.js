/*
 * DukaMkononi activity monitor (Phase 2 / 8 of the monitoring system).
 *
 * One script, included once from partials/dm-locale.blade.php, instruments
 * EVERY page through document-level event delegation - dynamically rendered
 * buttons, forms and rows are covered without per-page code, and rerenders
 * can never multiply listeners (each listener is attached exactly once).
 *
 * Event schema (POST /api/monitoring/events):
 *   { event, category, outcome?, description?, page, route, element?,
 *     entity_type?, entity_id?, metadata?, client_ts }
 *   - event:     SCREAMING_SNAKE, validated server-side against [A-Z0-9_]
 *   - category:  page|navigation|input|control|search|form|file|session|system
 *   - route:     location.pathname ONLY (never query strings or hashes, so
 *                reset tokens / codes / auth fragments are never sent)
 *   - page:      document.title
 *   - metadata:  bounded {field_type, milestone, ...} - NEVER field values
 *
 * Privacy rules enforced HERE:
 *   - password/hidden/otp/token/card-classified fields emit focus/blur only;
 *     their content and even "changed" signals are never captured;
 *   - ordinary fields emit "modified <label>" metadata, never the value;
 *   - search events never carry the typed query;
 *   - file events carry only field labels + file count/extension;
 *   - no keystroke, pointer, clipboard, media or screen recording anywhere.
 *
 * Transport rules:
 *   - events batch (12s window, 20/flush, 60 queued) and flush with
 *     keepalive on pagehide; network failures re-queue with a cap and never
 *     throw; 4xx responses drop the batch (no poison retry loops);
 *   - mutations to /api/* carry an X-Correlation-Id so server-side audits can
 *     be joined to the browser interaction that requested them;
 *   - after logout (token removed) emission stops completely;
 *   - HEARTBEAT events prove liveness only (server touches session state and
 *     stores no row).
 *
 * The server treats every payload as untrusted: the authenticated actor is
 * derived there from a verified JWT only, never from this file.
 */
(function (global) {
    'use strict';

    if (global.__dmMonitorLoaded) return;
    global.__dmMonitorLoaded = true;

    var CFG = {
        endpoint: '/api/monitoring/events',
        flushMs: 12000,
        maxBatch: 20,
        maxQueue: 60,
        dedupeMs: 2500,
        heartbeatMs: 60000,
        scrollThrottleMs: 300,
        maxConsecutiveFailures: 8,
        maxResourceFailuresPerPage: 5
    };

    var queue = [];
    var flushTimer = null;
    var stopped = false;
    var hadToken = false;
    var lastToken = null; // last seen token: lets the final logout flush stay attributed
    var failures = 0;
    var dedupeMap = {};
    var installed = false;
    var pageHiding = false;
    var lastRoute = null;
    var scrollState = { started: false, buckets: {}, last: 0 };
    var resourceFailures = 0;

    /* ------------------------------ utils ------------------------------ */

    function storageGet(key) {
        try { return global.localStorage ? global.localStorage.getItem(key) : null; }
        catch (e) { return null; }
    }

    function storageSet(key, value) {
        try { if (global.localStorage) global.localStorage.setItem(key, value); }
        catch (e) { /* storage unavailable */ }
    }

    function token() { return storageGet('userToken'); }

    function uuid() {
        try {
            if (global.crypto && typeof global.crypto.randomUUID === 'function') {
                return global.crypto.randomUUID();
            }
        } catch (e) { /* fall through */ }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : ((r & 0x3) | 0x8);
            return v.toString(16);
        });
    }

    /** Anonymous correlation label (server validates the format; user_id stays null). */
    function anonId() {
        var existing = storageGet('dm_monitor_sid');
        if (existing && /^[A-Za-z0-9-]{8,64}$/.test(existing)) return existing;
        var fresh = uuid();
        storageSet('dm_monitor_sid', fresh);
        return fresh;
    }

    /** Current page title (capped), never URL contents. */
    function pageTitle() {
        try { return (document.title || '').replace(/\s+/g, ' ').trim().slice(0, 120); }
        catch (e) { return ''; }
    }

    /** Path only: query strings and hashes are deliberately never observed. */
    function routePath() {
        try { return (global.location && global.location.pathname) || '/'; }
        catch (e) { return '/'; }
    }

    function cleanText(value, max) {
        if (!value) return '';
        return String(value).replace(/\s+/g, ' ').trim().slice(0, max || 60);
    }

    function closestFrom(node, selector) {
        if (!node) return null;
        if (node.nodeType !== 1) node = node.parentElement;
        if (node && typeof node.closest === 'function') {
            try { return node.closest(selector); } catch (e) { /* invalid selector */ }
        }
        // Minimal fallback: walk up matching tag/attr parts we actually use.
        var tags = selector.split(',').map(function (s) { return s.trim(); });
        while (node && node.nodeType === 1) {
            for (var i = 0; i < tags.length; i++) {
                var part = tags[i];
                if (part.charAt(0) === '[') {
                    var name = part.slice(1, part.indexOf('=') > -1 ? part.indexOf('=') : -1).replace(/["'\]]/g, '');
                    if (node.hasAttribute && node.hasAttribute(name)) return node;
                } else if (part.charAt(0) === '.') {
                    if (node.classList && node.classList.contains(part.slice(1))) return node;
                } else if (part.charAt(0) === 'a' && part.indexOf('[download]') > -1) {
                    if (node.tagName === 'A' && node.hasAttribute('download')) return node;
                } else if (node.tagName === part.toUpperCase()) {
                    return node;
                }
            }
            node = node.parentElement;
        }
        return null;
    }

    /* ------------------------------ emit ------------------------------- */

    function emit(name, fields) {
        if (stopped || !name || !/^[A-Z][A-Z0-9_]{0,63}$/.test(name)) return false;

        var event = {};
        var key = name;
        if (fields) {
            for (var k in fields) {
                if (Object.prototype.hasOwnProperty.call(fields, k) && fields[k] !== undefined) {
                    event[k] = fields[k];
                }
            }
            key = name + '|' + (event.route || '') + '|' + (event.element || '') + '|' + (event.description || '');
        }

        event.event = name;
        event.page = event.page || pageTitle();
        event.route = event.route || routePath();
        event.client_ts = new Date().toISOString();

        // Deduplicate identical bursts (rerenders, double clicks, scroll
        // jitter) inside a short window.
        var now = Date.now();
        pruneDedupe(now);
        if (dedupeMap[key] && now - dedupeMap[key] < CFG.dedupeMs) return false;
        dedupeMap[key] = now;

        queue.push(event);
        if (queue.length > CFG.maxQueue) {
            queue.splice(0, queue.length - CFG.maxQueue); // drop oldest, keep newest
        }
        scheduleFlush();
        return true;
    }

    function pruneDedupe(now) {
        var keys = Object.keys(dedupeMap);
        if (keys.length < 400) return;
        for (var i = 0; i < keys.length; i++) {
            if (now - dedupeMap[keys[i]] > CFG.dedupeMs * 4) delete dedupeMap[keys[i]];
        }
    }

    /* ----------------------------- flushing ---------------------------- */

    function scheduleFlush() {
        if (flushTimer || stopped || queue.length === 0) return;
        flushTimer = setTimeout(function () { flushTimer = null; flush(); }, CFG.flushMs);
    }

    /**
     * Logout path: record the browser-observed session end, deliver it (and
     * anything still queued) with the token that was just cleared so it stays
     * attributed, then stop ALL further emission.
     */
    function finalLogoutFlush() {
        if (stopped) return;
        emit('SESSION_END', { category: 'session', description: 'Signed out (browser observation)' });
        stopped = true;
        if (flushTimer) { clearTimeout(flushTimer); flushTimer = null; }
        dispatch(lastToken || token());
        queue.length = 0;
    }

    function dispatch(authToken) {
        if (!queue.length) return;
        if (failures >= CFG.maxConsecutiveFailures) { stopEmission(); return; }

        var batch = queue.splice(0, CFG.maxBatch);
        var body = JSON.stringify({ events: batch, anon_id: anonId() });
        var headers = { 'Content-Type': 'application/json' };
        if (authToken) headers['Authorization'] = 'Bearer ' + authToken;

        var request;
        try {
            request = global.fetch(CFG.endpoint, {
                method: 'POST',
                headers: headers,
                body: body,
                keepalive: pageHiding,
                credentials: 'same-origin'
            });
        } catch (e) {
            failures++;
            requeue(batch);
            scheduleFlush();
            return;
        }

        if (request && typeof request.then === 'function') {
            request.then(function (res) {
                var status = res && res.status ? res.status : 0;
                if (status >= 500) {
                    failures++;
                    requeue(batch);
                } else {
                    // 4xx: drop the batch (retrying a rejected payload would
                    // only loop). 2xx: delivered.
                    failures = 0;
                }
                scheduleFlush();
            }, function () {
                failures++;
                requeue(batch);
                scheduleFlush();
            });
        }

        if (queue.length) scheduleFlush();
    }

    function flush() {
        if (stopped) return;

        var tk = token();
        if (tk) lastToken = tk;

        // Logout detection without relying on storage events (same-tab
        // removals do not fire them): if the token vanished, end cleanly
        // (finalLogoutFlush delivers SESSION_END before stopping).
        if (hadToken && !tk) {
            finalLogoutFlush();
            return;
        }
        if (!queue.length) return;

        dispatch(tk);
    }

    function requeue(batch) {
        if (failures >= CFG.maxConsecutiveFailures) { stopEmission(); return; }
        for (var i = batch.length - 1; i >= 0; i--) queue.unshift(batch[i]);
        if (queue.length > CFG.maxQueue) queue.length = CFG.maxQueue;
    }

    function stopEmission() {
        stopped = true;
        queue.length = 0;
        if (flushTimer) { clearTimeout(flushTimer); flushTimer = null; }
    }

    /* --------------------------- page / nav ---------------------------- */

    function onVisibilityChange() {
        try {
            if (document.visibilityState === 'hidden') {
                emit('PAGE_HIDDEN', { category: 'page', description: 'Page hidden' });
                flush();
            } else if (document.visibilityState === 'visible') {
                emit('PAGE_VISIBLE', { category: 'page', description: 'Page visible again' });
            }
        } catch (e) { /* not observable */ }
    }

    function onRouteChange() {
        var next = routePath();
        if (next === lastRoute) return;
        var previous = lastRoute;
        lastRoute = next;

        scrollState.buckets = {};      // new view: fresh milestones
        scrollState.started = false;

        emit('ROUTE_CHANGE', {
            category: 'navigation',
            route: next,
            description: previous ? 'Navigated between sections' : 'Route changed',
            metadata: previous ? { from: previous } : undefined
        });
    }

    function wrapHistory(method) {
        try {
            if (!global.history || typeof global.history[method] !== 'function') return;
            if (global.history['__dm_' + method]) return;
            global.history['__dm_' + method] = true;
            var original = global.history[method];
            global.history[method] = function () {
                var result = original.apply(this, arguments);
                try { onRouteChange(); } catch (e) { /* observability only */ }
                return result;
            };
        } catch (e) { /* never break navigation */ }
    }

    function onPageHide() {
        pageHiding = true;
        emit('PAGE_HIDE', { category: 'page', description: 'Page closing or backgrounded' });
        flush();
    }

    /* ---------------------------- controls ----------------------------- */

    /** Semantic action words used by this app's Swahili + English UI. */
    var ACTION_WORDS = [
        ['delete', /delete|kufuta|\bfuta\b|ondoa/i],
        ['create', /create|ongeza|add\b|mpya|register|jisajili/i],
        ['edit', /edit|hariri|badilisha/i],
        ['update', /update|sasisha|hassisha/i],
        ['save', /save|hifadhi/i],
        ['submit', /submit|wasilisha|tuma/i],
        ['approve', /approve|idhinisha|kubali/i],
        ['reject', /reject|kataa|kanusha/i],
        ['search', /search|tafuta|\bfind\b/i],
        ['filter', /filter|ichujio|chuja/i],
        ['export', /export|hamisha/i],
        ['download', /download|pakua/i],
        ['upload', /upload|pakia/i],
        ['print', /print|chapisha/i],
        ['payment', /pay\b|lipa|payment|malipo/i],
        ['login', /login|ingia|sign in/i],
        ['logout', /logout|toka|sign out/i],
        ['open', /open|angalia|angazia|\bview\b|fungua|details|maelezo/i]
    ];

    function actionFor(label, extra) {
        var haystack = (label || '') + ' ' + (extra || '');
        for (var i = 0; i < ACTION_WORDS.length; i++) {
            if (ACTION_WORDS[i][1].test(haystack)) return ACTION_WORDS[i][0];
        }
        return null;
    }

    function elementLabel(el) {
        if (!el) return '';
        var explicit = el.getAttribute && el.getAttribute('data-dm-label');
        if (explicit) return cleanText(explicit, 60);
        if (el.tagName === 'INPUT' || el.tagName === 'BUTTON') {
            return cleanText(el.value || el.textContent || (el.getAttribute && el.getAttribute('aria-label')) || '', 60);
        }
        return cleanText(el.textContent || (el.getAttribute && el.getAttribute('aria-label')) || '', 60);
    }

    function onDocumentClick(e) {
        if (stopped) return;
        var target = e.target;
        if (!target) return;

        // 1. Explicitly marked controls (stable semantic identifiers).
        var tracked = closestFrom(target, '[data-dm-track]');
        if (tracked) {
            var trackName = cleanText(tracked.getAttribute('data-dm-track') || '', 40).replace(/[^A-Z0-9_]/g, '');
            var trackLabel = elementLabel(tracked);
            emit(trackName || 'CONTROL_CLICK', {
                category: tracked.getAttribute('data-dm-category') || 'control',
                element: trackLabel,
                description: cleanText(tracked.getAttribute('data-dm-desc') || ('Control: ' + trackLabel), 160)
            });
            return;
        }

        // 2. Downloads.
        var download = closestFrom(target, 'a[download]');
        if (download) {
            emit('FILE_DOWNLOAD_REQUESTED', {
                category: 'file',
                element: elementLabel(download),
                description: 'Download requested (observed in browser)'
            });
            return;
        }

        // 3. Heuristic buttons/chips/tabs with meaningful action words.
        var button = closestFrom(target, 'button, [role="button"], input[type="submit"], input[type="button"], .action-btn, .tab-btn, .filter-chip, .header-btn');
        if (!button) return;
        var label = elementLabel(button);
        if (!label) return;
        var action = actionFor(label, button.getAttribute ? (button.getAttribute('type') || '') : '');
        if (!action) return;

        emit('CONTROL_CLICK', {
            category: 'control',
            element: label,
            description: 'Observed control action: ' + action,
            metadata: { action: action }
        });
    }

    /* ----------------------------- fields ------------------------------ */

    function isField(el) {
        return !!(el && el.tagName && (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA'));
    }

    /** Explicit sensitive classification - these are never content-monitored. */
    function isSensitiveField(el) {
        var type = ((el.getAttribute && el.getAttribute('type')) || '').toLowerCase();
        if (type === 'password' || type === 'hidden') return true;
        if ((el.getAttribute && el.getAttribute('autocomplete') || '').toLowerCase() === 'one-time-code') return true;
        if (el.hasAttribute && el.hasAttribute('data-dm-sensitive')) return true;
        var hint = [el.name, el.id, el.getAttribute && el.getAttribute('aria-label'), el.getAttribute && el.getAttribute('placeholder')]
            .filter(Boolean).join(' ');
        return /passw|pwd|secret|token|otp|one.?time|cvv|card.?number|iban|\bpin\b|api.?key|auth/i.test(hint);
    }

    /** A human label for the field - the VALUE is never read, ever. */
    function fieldLabel(el) {
        var explicit = el.getAttribute && el.getAttribute('data-dm-label');
        if (explicit) return cleanText(explicit, 80);
        var aria = el.getAttribute && el.getAttribute('aria-label');
        if (aria) return cleanText(aria, 80);
        try {
            if (el.labels && el.labels.length && el.labels[0]) {
                var labelText = cleanText(el.labels[0].textContent, 80);
                if (labelText) return labelText;
            }
        } catch (e) { /* no labels support */ }
        var placeholder = el.getAttribute && el.getAttribute('placeholder');
        if (placeholder) return cleanText(placeholder, 80);
        var raw = el.name || el.id || '';
        if (!raw) return '';
        return cleanText(String(raw).replace(/[_\-]+/g, ' '), 80);
    }

    function onFocusIn(e) {
        var el = e.target;
        if (!isField(el)) return;
        var label = fieldLabel(el);
        if (!label) return;
        emit('FIELD_FOCUS', {
            category: 'input',
            element: label,
            description: 'Focused field: ' + label,
            metadata: { field_type: (el.getAttribute && el.getAttribute('type')) || el.tagName.toLowerCase() }
        });
    }

    function onFocusOut(e) {
        var el = e.target;
        if (!isField(el)) return;
        var label = fieldLabel(el);
        if (!label) return;
        emit('FIELD_BLUR', {
            category: 'input',
            element: label,
            description: 'Left field: ' + label,
            metadata: { field_type: (el.getAttribute && el.getAttribute('type')) || el.tagName.toLowerCase() }
        });
    }

    function onChange(e) {
        var el = e.target;
        if (!isField(el)) return;
        var label = fieldLabel(el);
        if (!label) return;

        var type = ((el.getAttribute && el.getAttribute('type')) || '').toLowerCase();
        if (type === 'file') {
            var count = 1;
            try { count = el.files ? el.files.length : 1; } catch (err) { count = 1; }
            var ext = '';
            try {
                var name = el.files && el.files[0] && el.files[0].name ? String(el.files[0].name) : '';
                var dot = name.lastIndexOf('.');
                ext = dot > -1 ? name.slice(dot + 1).toLowerCase().replace(/[^a-z0-9]/g, '').slice(0, 8) : '';
            } catch (err2) { ext = ''; }
            // Extension only: file NAMES and contents are never recorded.
            emit('FILE_SELECTED', {
                category: 'file',
                element: label,
                description: 'File selection observed (' + count + ' file' + (count === 1 ? '' : 's') + ')',
                metadata: { field_type: 'file', file_count: count, extension: ext }
            });
            return;
        }

        // Sensitive fields: content signals are never captured - not even
        // that the value changed.
        if (isSensitiveField(el)) return;

        emit('FIELD_CHANGED', {
            category: 'input',
            element: label,
            description: 'User modified the ' + label + ' field',
            metadata: { field_type: type || el.tagName.toLowerCase() }
        });
    }

    /* ------------------------------ forms ------------------------------ */

    function formLabel(form) {
        if (!form) return '';
        var explicit = form.getAttribute && form.getAttribute('data-dm-label');
        if (explicit) return cleanText(explicit, 80);
        var aria = form.getAttribute && form.getAttribute('aria-label');
        if (aria) return cleanText(aria, 80);
        if (form.id) return cleanText(form.id.replace(/[_\-]+/g, ' '), 80);
        if (form.getAttribute && form.getAttribute('name')) return cleanText(form.getAttribute('name'), 80);
        if (form.getAttribute && form.getAttribute('action')) {
            return cleanText(String(form.getAttribute('action')).split('/').pop() || 'form', 80);
        }
        return 'form';
    }

    function isSearchForm(form) {
        if (!form) return false;
        if (form.getAttribute && form.getAttribute('role') === 'search') return true;
        try {
            if (form.querySelector('input[type="search"]')) return true;
            var named = form.querySelector('input[name="q"], input[name="query"], input[name="search"]');
            if (named) return true;
        } catch (e) { /* querySelector unavailable */ }
        return false;
    }

    function onSubmit(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        var label = formLabel(form);

        if (isSearchForm(form)) {
            // The typed query itself is deliberately never recorded.
            emit('SEARCH_SUBMITTED', {
                category: 'search',
                element: label,
                description: 'Search submitted (query text not recorded)'
            });
            return;
        }

        emit('FORM_SUBMITTED', {
            category: 'form',
            element: label,
            description: 'Form submission initiated (browser observation)'
        });
    }

    function onInvalid(e) {
        var el = e.target;
        if (!isField(el)) return;
        var form = el.form || null;
        var label = fieldLabel(el) || formLabel(form);
        emit('FORM_VALIDATION_FAILED', {
            category: 'form',
            element: label,
            description: 'Client-side validation failed (no values recorded)',
            metadata: { form: formLabel(form) }
        });
    }

    /* ---------------------------- scrolling ---------------------------- */

    function onScroll() {
        if (stopped) return;
        var now = Date.now();
        if (now - scrollState.last < CFG.scrollThrottleMs) return;
        scrollState.last = now;

        if (!scrollState.started) {
            scrollState.started = true;
            emit('SCROLL_START', { category: 'page', description: 'Began scrolling' });
        }

        var docEl = document.documentElement;
        var body = document.body;
        var height = Math.max(
            (docEl && docEl.scrollHeight) || 0,
            (body && body.scrollHeight) || 0
        );
        var view = global.innerHeight || (docEl && docEl.clientHeight) || 0;
        if (!height || !view || height <= view) return;

        var pos = (global.scrollY || (docEl && docEl.scrollTop) || 0) + view;
        var pct = pos / height;

        // Milestones fire exactly once each (dynamic pages that grow taller
        // later cannot re-emit an already reached bucket).
        var milestones = [25, 50, 75, 90];
        for (var i = 0; i < milestones.length; i++) {
            var bucket = milestones[i];
            if (pct * 100 >= bucket && !scrollState.buckets[bucket]) {
                scrollState.buckets[bucket] = true;
                emit('SCROLL_MILESTONE', {
                    category: 'page',
                    description: 'Reached ' + bucket + '% of the page',
                    metadata: { milestone: bucket }
                });
            }
        }
    }

    /* -------------------------- failed resources ----------------------- */

    function onResourceError(e) {
        if (stopped) return;
        if (resourceFailures >= CFG.maxResourceFailuresPerPage) return;
        var el = e.target;
        if (!el || !el.tagName) return;
        if (['IMG', 'SCRIPT', 'LINK', 'IFRAME'].indexOf(el.tagName) === -1) return;

        var src = el.src || (el.href || '');
        if (!src) return;
        // Same-origin only (external CDN noise), and path-only so signed
        // query parameters are never observed.
        var path = src;
        try {
            var parsed = new URL(src, global.location ? global.location.href : undefined);
            if (global.location && parsed.origin !== global.location.origin) return;
            path = parsed.pathname;
        } catch (err) { return; }

        resourceFailures++;
        emit('PAGE_RESOURCE_FAILED', {
            category: 'page',
            outcome: 'failed',
            element: el.tagName.toLowerCase(),
            description: 'Resource failed to load (observed in browser)',
            metadata: { resource: path.slice(0, 160) }
        });
    }

    /* ---------------------- correlation id injection ------------------- */

    function installFetchHook() {
        if (global.__dmFetchHooked || typeof global.fetch !== 'function') return;
        global.__dmFetchHooked = true;
        var original = global.fetch;

        global.fetch = function (input, init) {
            try {
                var url = typeof input === 'string' ? input : (input && input.url) || '';
                var method = ((init && init.method) || (input && input.method) || 'GET').toUpperCase();
                var isApi = url.indexOf('/api/') !== -1 || url.indexOf('/api') === 0;
                var isOwn = url.indexOf(CFG.endpoint) !== -1;

                if (isApi && !isOwn && method !== 'GET' && method !== 'HEAD' && typeof global.Headers === 'function') {
                    var headers = new global.Headers(init && init.headers);
                    if (!headers.has('X-Correlation-Id')) headers.set('X-Correlation-Id', uuid());
                    if (!headers.has('X-DM-Source')) headers.set('X-DM-Source', 'web');
                    init = init || {};
                    init.headers = headers;
                }
            } catch (e) { /* instrumentation must never break a request */ }
            return original.call(global, input, init);
        };
    }

    /* --------------------------- session end --------------------------- */

    function onStorage(e) {
        if (stopped || !e) return;
        if (e.key !== 'userToken') return;
        if (!e.newValue && hadToken) {
            finalLogoutFlush();
        } else if (e.newValue) {
            hadToken = true;
            lastToken = e.newValue;
        }
    }

    function heartbeatTick() {
        if (stopped) return;
        var tk = token();
        if (hadToken && !tk) {
            finalLogoutFlush();
            return;
        }
        if (!tk) return; // anonymous visitors get no heartbeat
        try {
            if (document.visibilityState === 'hidden') return;
        } catch (e) { /* treat as visible */ }

        emit('HEARTBEAT', { category: 'session', description: 'Active session heartbeat' });
    }

    /* ------------------------------ start ------------------------------ */

    function start() {
        if (installed) return;
        installed = true;

        hadToken = !!token();
        lastRoute = routePath();

        emit('PAGE_VIEW', { category: 'page', description: 'Page opened' });

        // Single delegated listeners: dynamic content is covered, rerenders
        // can never attach a second copy.
        document.addEventListener('click', onDocumentClick, true);
        document.addEventListener('submit', onSubmit, true);
        document.addEventListener('invalid', onInvalid, true);
        document.addEventListener('focusin', onFocusIn, true);
        document.addEventListener('focusout', onFocusOut, true);
        document.addEventListener('change', onChange, true);
        document.addEventListener('error', onResourceError, true);
        document.addEventListener('visibilitychange', onVisibilityChange);

        global.addEventListener('scroll', onScroll, { passive: true });
        global.addEventListener('hashchange', onRouteChange);
        global.addEventListener('popstate', onRouteChange);
        global.addEventListener('pagehide', onPageHide);
        global.addEventListener('storage', onStorage);

        wrapHistory('pushState');
        wrapHistory('replaceState');
        installFetchHook();

        global.setInterval(heartbeatTick, CFG.heartbeatMs);
        scheduleFlush();
    }

    /* Public surface (console/tests): emit is additive, flush is force. */
    global.DMMonitor = {
        version: 1,
        emit: emit,
        flush: function () { if (flushTimer) { clearTimeout(flushTimer); flushTimer = null; } flush(); },
        stop: stopEmission,
        config: CFG,
        _state: function () {
            return { queued: queue.length, stopped: stopped, failures: failures, hadToken: hadToken };
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(typeof window !== 'undefined' ? window : this);
