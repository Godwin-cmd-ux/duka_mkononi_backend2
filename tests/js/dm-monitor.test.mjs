/*
 * Behavioral harness for the REAL public/js/dm-monitor.js, run in a VM with a
 * stubbed DOM. Execute with: node tests/js/dm-monitor.test.mjs
 *
 * Covers: page views, delegated clicks on dynamic content, dedupe, semantic
 * button actions, sensitive-field exclusion (no values, ever), ordinary field
 * metadata, file extension-only capture, search query exclusion, scroll
 * milestones (throttled, exactly once), route changes, visibility, correlation
 * header injection, network failure resilience, logout stop, heartbeat rules,
 * queue caps and resource-failure redaction.
 */
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert';

const code = readFileSync(new URL('../../public/js/dm-monitor.js', import.meta.url), 'utf8');

/* ---------------------------------------------------------------- env ---- */

function matchesPart(node, part) {
    part = part.trim();
    if (part.startsWith('.')) return node.classList.contains(part.slice(1));

    const tagMatch = part.match(/^([a-zA-Z]+)((?:\[[^\]]*\])*)$/);
    let tag = null;
    let attrClause = '';
    if (tagMatch) {
        tag = tagMatch[1];
        attrClause = tagMatch[2] || '';
    } else if (part.startsWith('[')) {
        attrClause = part;
    } else {
        tag = part;
    }

    if (tag && node.tagName !== tag.toUpperCase()) return false;

    const attrs = [...attrClause.matchAll(/\[([a-zA-Z-]+)(?:="([^"]*)")?\]/g)];
    if (attrClause && attrs.length === 0) return false;
    for (const [, name, value] of attrs) {
        if (!node.hasAttribute(name)) return false;
        if (value !== undefined && node.getAttribute(name) !== value) return false;
    }
    return true;
}

function makeEnv(options = {}) {
    const docListeners = Object.create(null);
    const winListeners = Object.create(null);
    const storage = new Map();
    if (options.token !== false) storage.set('userToken', options.token || 'test-jwt-token');

    const fetchCalls = [];
    const intervals = [];
    let fetchBehavior = options.fetchBehavior || 'ok';
    let now = 1700000000000;

    class FakeDate extends Date {
        constructor(...args) {
            if (args.length) super(...args);
            else super(now);
        }
        static now() { return now; }
    }

    const location = {
        pathname: options.pathname || '/muuzaji/mauzo',
        origin: 'http://localhost',
        href: 'http://localhost/muuzaji/mauzo',
    };

    function makeElement(spec = {}) {
        const attrs = new Map(Object.entries(spec.attrs || {}));
        return {
            nodeType: 1,
            tagName: (spec.tagName || 'DIV').toUpperCase(),
            textContent: spec.textContent || '',
            value: spec.value !== undefined ? spec.value : '',
            src: spec.src || '',
            href: spec.href || '',
            name: spec.name || '',
            id: spec.id || '',
            labels: spec.labels || null,
            files: spec.files || null,
            form: spec.form || null,
            parentElement: spec.parent || null,
            classList: { contains: (c) => (spec.classes || []).includes(c) },
            hasAttribute: (k) => attrs.has(k),
            getAttribute: (k) => (attrs.has(k) ? attrs.get(k) : null),
            setAttribute: (k, v) => attrs.set(k, String(v)),
            querySelector: spec.querySelector || (() => null),
            closest(selector) {
                let node = this;
                while (node) {
                    for (const part of selector.split(',')) {
                        if (matchesPart(node, part)) return node;
                    }
                    node = node.parentElement;
                }
                return null;
            },
        };
    }

    const document = {
        readyState: 'complete',
        title: options.title || 'Dashbodi ya Msimamizi',
        visibilityState: options.visibility || 'visible',
        documentElement: { scrollHeight: options.scrollHeight || 4000, clientHeight: 800, scrollTop: 0 },
        body: { scrollHeight: options.scrollHeight || 4000 },
        addEventListener(type, fn) { (docListeners[type] = docListeners[type] || []).push(fn); },
        querySelector: () => null,
        createElement: (tag) => makeElement({ tagName: tag }),
    };

    function fetchStub(url, opts) {
        fetchCalls.push({ url, opts });
        if (fetchBehavior === 'fail') return Promise.reject(new Error('network down'));
        if (fetchBehavior === 'server500') return Promise.resolve({ status: 500, ok: false });
        return Promise.resolve({ status: 200, ok: true });
    }

    const win = {
        document,
        location,
        localStorage: {
            getItem: (k) => (storage.has(k) ? storage.get(k) : null),
            setItem: (k, v) => storage.set(k, String(v)),
            removeItem: (k) => storage.delete(k),
        },
        fetch: fetchStub,
        Headers,
        URL,
        crypto: globalThis.crypto,
        console,
        Date: FakeDate,
        setTimeout,
        clearTimeout,
        setInterval: (fn, ms) => { intervals.push({ fn, ms }); return intervals.length; },
        clearInterval: () => {},
        scrollY: options.scrollY || 0,
        innerHeight: 800,
        addEventListener(type, fn) { (winListeners[type] = winListeners[type] || []).push(fn); },
        removeEventListener() {},
        history: {
            pushState(_s, _t, url) { if (url) location.pathname = new URL(url, location.origin).pathname; },
            replaceState(_s, _t, url) { if (url) location.pathname = new URL(url, location.origin).pathname; },
        },
        makeElement,
    };
    win.window = win;
    win.globalThis = win;

    vm.createContext(win);
    vm.runInContext(code, win, { filename: 'dm-monitor.js' });

    return {
        win,
        storage,
        fetchCalls,
        intervals,
        makeElement,
        doc: {
            fire(type, event) { (docListeners[type] || []).forEach((fn) => fn(event)); },
            listeners: docListeners,
        },
        w: {
            fire(type, event) { (winListeners[type] || []).forEach((fn) => fn(event)); },
        },
        advance(ms) { now += ms; },
        setVisibility(state) { document.visibilityState = state; },
        setFetchBehavior(b) { fetchBehavior = b; },
    };
}

async function settle() {
    for (let i = 0; i < 6; i++) await new Promise((r) => setImmediate(r));
}

/** Force a flush and return everything actually sent (across all fetches). */
async function flushRead(env) {
    const before = env.fetchCalls.length;
    env.win.DMMonitor.flush();
    await settle();
    const events = [];
    for (const call of env.fetchCalls.slice(before)) {
        try {
            const body = JSON.parse(call.opts.body);
            for (const ev of body.events || []) events.push(ev);
        } catch { /* ignore */ }
    }
    return { events, calls: env.fetchCalls.slice(before) };
}

function byName(events, name) {
    return events.filter((e) => e.event === name);
}

/* --------------------------------------------------------------- tests ---- */

const tests = [];
const test = (name, fn) => tests.push([name, fn]);

test('page view carries title, path-only route, anon id and the auth token', async () => {
    const env = makeEnv();
    const { events, calls } = await flushRead(env);

    const pageViews = byName(events, 'PAGE_VIEW');
    assert.strictEqual(pageViews.length, 1, 'exactly one page view on load');
    assert.strictEqual(pageViews[0].page, 'Dashbodi ya Msimamizi');
    assert.strictEqual(pageViews[0].route, '/muuzaji/mauzo');
    assert.strictEqual(pageViews[0].category, 'page');
    assert.ok(pageViews[0].client_ts, 'client timestamp present');

    const body = JSON.parse(calls[0].opts.body);
    assert.match(body.anon_id, /^[A-Za-z0-9-]{8,40}$/, 'anonymous correlation id sent');
    assert.strictEqual(calls[0].opts.headers['Authorization'], 'Bearer test-jwt-token');
    assert.strictEqual(calls[0].opts.headers['Content-Type'], 'application/json');
});

test('delegated clicks cover elements added after load, with stable names', async () => {
    const env = makeEnv();
    await flushRead(env); // clear the page view

    // Element created AFTER init (dynamic render).
    const button = env.makeElement({
        tagName: 'BUTTON',
        textContent: 'Hifadhi',
        attrs: { 'data-dm-track': 'PRODUCT_SAVE', 'data-dm-label': 'Hifadhi Bidhaa' },
    });
    env.doc.fire('click', { target: button });

    const { events } = await flushRead(env);
    const saves = byName(events, 'PRODUCT_SAVE');
    assert.strictEqual(saves.length, 1, 'explicit track name honoured for a dynamic element');
    assert.strictEqual(saves[0].element, 'Hifadhi Bidhaa');
    assert.strictEqual(saves[0].category, 'control');
});

test('identical bursts are deduplicated', async () => {
    const env = makeEnv();
    await flushRead(env);

    const button = env.makeElement({
        tagName: 'BUTTON',
        textContent: 'Futa',
        attrs: { 'data-dm-track': 'DELETE_ITEM' },
    });
    env.doc.fire('click', { target: button });
    env.doc.fire('click', { target: button }); // double click / rerender burst

    const { events } = await flushRead(env);
    assert.strictEqual(byName(events, 'DELETE_ITEM').length, 1, 'duplicate within the window collapses');
});

test('heuristic action words classify Swahili buttons', async () => {
    const env = makeEnv();
    await flushRead(env);

    const del = env.makeElement({ tagName: 'BUTTON', textContent: 'Futa Bidhaa' });
    env.doc.fire('click', { target: del });

    const search = env.makeElement({ tagName: 'BUTTON', textContent: 'Tafuta' });
    env.doc.fire('click', { target: search });

    const { events } = await flushRead(env);
    const clicks = byName(events, 'CONTROL_CLICK');
    assert.strictEqual(clicks.length, 2);
    const actions = clicks.map((c) => c.metadata && c.metadata.action).sort();
    assert.deepStrictEqual(actions, ['delete', 'search']);
});

test('sensitive fields emit focus only - never content, never a change signal', async () => {
    const env = makeEnv();
    await flushRead(env);

    const password = env.makeElement({ tagName: 'INPUT', name: 'password', attrs: { type: 'password' } });
    env.doc.fire('focusin', { target: password });
    password.value = 'Sup3rSecret!';
    env.doc.fire('change', { target: password }); // must NOT be recorded
    env.doc.fire('focusout', { target: password });

    const { events, calls } = await flushRead(env);
    assert.strictEqual(byName(events, 'FIELD_FOCUS').length, 1);
    assert.strictEqual(byName(events, 'FIELD_BLUR').length, 1);
    assert.strictEqual(byName(events, 'FIELD_CHANGED').length, 0, 'password changes are never observed');
    assert.ok(!calls[0].opts.body.includes('Sup3rSecret!'), 'the password value never leaves the page');
});

test('ordinary fields record the label, not the value', async () => {
    const env = makeEnv();
    await flushRead(env);

    const price = env.makeElement({
        tagName: 'INPUT',
        name: 'product_price',
        value: '15000',
        labels: [{ textContent: 'Bei ya Bidhaa' }],
    });
    env.doc.fire('change', { target: price });

    const { events, calls } = await flushRead(env);
    const changed = byName(events, 'FIELD_CHANGED');
    assert.strictEqual(changed.length, 1);
    assert.strictEqual(changed[0].element, 'Bei ya Bidhaa', 'accessible label is used');
    assert.strictEqual(changed[0].metadata.field_type, 'input');
    assert.ok(!calls[0].opts.body.includes('15000'), 'field values are never captured');
});

test('file selection records count and extension only', async () => {
    const env = makeEnv();
    await flushRead(env);

    const file = env.makeElement({
        tagName: 'INPUT',
        name: 'attachment',
        attrs: { type: 'file' },
        files: [{ name: 'Kimbiipassport-labda-siri.pdf' }],
    });
    env.doc.fire('change', { target: file });

    const { events, calls } = await flushRead(env);
    const selected = byName(events, 'FILE_SELECTED');
    assert.strictEqual(selected.length, 1);
    assert.strictEqual(selected[0].metadata.extension, 'pdf');
    assert.strictEqual(selected[0].metadata.file_count, 1);
    assert.ok(!calls[0].opts.body.includes('Kimbiipassport'), 'file NAMES are never recorded');
});

test('search submissions never carry the typed query', async () => {
    const env = makeEnv();
    await flushRead(env);

    const searchForm = env.makeElement({
        tagName: 'FORM',
        id: 'catalog-search',
        querySelector: (sel) => (sel.includes('search') ? { tagName: 'INPUT' } : null),
    });
    env.doc.fire('submit', { target: searchForm });

    const { events, calls } = await flushRead(env);
    const searches = byName(events, 'SEARCH_SUBMITTED');
    assert.strictEqual(searches.length, 1);
    assert.strictEqual(searches[0].element, 'catalog search');
    assert.ok(!calls[0].opts.body.includes('iPhone'), 'no query text is even readable from the payload');
});

test('regular form submission is recorded as an observation', async () => {
    const env = makeEnv();
    await flushRead(env);

    const form = env.makeElement({ tagName: 'FORM', id: 'order-form', querySelector: () => null });
    env.doc.fire('submit', { target: form });
    env.doc.fire('invalid', { target: env.makeElement({ tagName: 'INPUT', name: 'phone', form }) });

    const { events } = await flushRead(env);
    assert.strictEqual(byName(events, 'FORM_SUBMITTED').length, 1);
    const invalid = byName(events, 'FORM_VALIDATION_FAILED');
    assert.strictEqual(invalid.length, 1);
    assert.strictEqual(invalid[0].element, 'phone');
});

test('scroll milestones fire throttled and exactly once each', async () => {
    const env = makeEnv();
    await flushRead(env);

    env.w.fire('scroll', {});            // start, 20% -> no milestone
    env.advance(400); env.win.scrollY = 400;  env.w.fire('scroll', {}); // 30% -> 25
    env.advance(400); env.w.fire('scroll', {});                     // same spot -> nothing
    env.advance(400); env.win.scrollY = 1200; env.w.fire('scroll', {}); // 50%
    env.advance(400); env.win.scrollY = 2600; env.w.fire('scroll', {}); // 85% -> 75
    env.advance(400); env.win.scrollY = 3200; env.w.fire('scroll', {}); // 100% -> 90

    const { events } = await flushRead(env);
    const starts = byName(events, 'SCROLL_START');
    assert.strictEqual(starts.length, 1, 'scroll start once');

    const milestones = byName(events, 'SCROLL_MILESTONE').map((e) => e.metadata.milestone).sort((a, b) => a - b);
    assert.deepStrictEqual(milestones, [25, 50, 75, 90], 'each milestone exactly once, none missing, none repeated');
});

test('route changes are recorded and reset scroll milestones', async () => {
    const env = makeEnv();
    await flushRead(env);

    env.win.history.pushState(null, '', '/muuzaji/uza');

    const { events } = await flushRead(env);
    const routes = byName(events, 'ROUTE_CHANGE');
    assert.strictEqual(routes.length, 1);
    assert.strictEqual(routes[0].route, '/muuzaji/uza');
    assert.strictEqual(routes[0].metadata.from, '/muuzaji/mauzo');
    assert.strictEqual(routes[0].category, 'navigation');
});

test('visibility loss emits and flushes immediately', async () => {
    const env = makeEnv();
    await flushRead(env);

    env.setVisibility('hidden');
    env.doc.fire('visibilitychange', {});
    await settle();

    const hiddenCall = env.fetchCalls[env.fetchCalls.length - 1];
    assert.ok(hiddenCall, 'hidden flushes without waiting for the batch window');
    assert.ok(byName(JSON.parse(hiddenCall.opts.body).events, 'PAGE_HIDDEN').length === 1);

    env.setVisibility('visible');
    env.doc.fire('visibilitychange', {});
    const { events } = await flushRead(env);
    assert.strictEqual(byName(events, 'PAGE_VISIBLE').length, 1);
});

test('mutations carry a correlation id; GETs and the ingest endpoint do not', async () => {
    const env = makeEnv();
    await flushRead(env);

    await env.win.fetch('/api/products', { method: 'POST', headers: { 'X-Custom': '1' } });
    await env.win.fetch('/api/products/my', { method: 'GET' });
    await flushRead(env); // our own ingest POST

    const post = env.fetchCalls.find((c) => c.url === '/api/products' && c.opts.method === 'POST');
    assert.ok(post.opts.headers instanceof Headers, 'headers upgraded');
    assert.match(post.opts.headers.get('X-Correlation-Id') || '', /^[A-Za-z0-9-]{8,64}$/);
    assert.strictEqual(post.opts.headers.get('X-DM-Source'), 'web');
    assert.strictEqual(post.opts.headers.get('X-Custom'), '1', 'existing headers preserved');

    const get = env.fetchCalls.find((c) => c.url === '/api/products/my');
    assert.strictEqual(get.opts.headers, undefined, 'GETs are untouched');

    const ingest = env.fetchCalls.find((c) => c.url.includes('/api/monitoring/events'));
    assert.ok(!ingest.opts.headers['X-Correlation-Id'], 'the ingest endpoint is excluded from the hook');
});

test('network failures re-queue without throwing, then stop after the cap', async () => {
    const env = makeEnv({ fetchBehavior: 'fail' });
    env.doc.fire('click', { target: env.makeElement({ tagName: 'BUTTON', textContent: 'Futa', attrs: { 'data-dm-track': 'DEL' } }) });

    for (let i = 0; i < 9; i++) {
        env.win.DMMonitor.flush();
        await settle();
    }

    const state = env.win.DMMonitor._state();
    assert.strictEqual(state.stopped, true, 'ingestion stops after repeated failures (no infinite retry)');
    assert.ok(state.failures >= 8);
    const sentWhileFailing = env.fetchCalls.length;
    assert.strictEqual(sentWhileFailing, 8, 'exactly the configured number of attempts');

    // No exception escaped at any point (harness reaching here is the proof).
});

test('logout delivers SESSION_END with the last token, then emission stops', async () => {
    const env = makeEnv();
    await flushRead(env); // establishes lastToken

    env.storage.delete('userToken'); // same-tab logout: no storage event fires
    env.win.DMMonitor.flush();
    await settle();

    const last = env.fetchCalls[env.fetchCalls.length - 1];
    const body = JSON.parse(last.opts.body);
    assert.ok(byName(body.events, 'SESSION_END').length === 1, 'session end is delivered, not silently dropped');
    assert.strictEqual(last.opts.headers['Authorization'], 'Bearer test-jwt-token', 'final flush stays attributed to the signing-out user');

    const state = env.win.DMMonitor._state();
    assert.strictEqual(state.stopped, true);

    env.doc.fire('click', { target: env.makeElement({ tagName: 'BUTTON', textContent: 'Futa', attrs: { 'data-dm-track': 'AFTER_LOGOUT' } }) });
    const after = await flushRead(env);
    assert.strictEqual(after.events.length, 0, 'nothing is emitted after logout');
});

test('heartbeat only fires with a token on a visible page', async () => {
    const env = makeEnv();
    await flushRead(env);
    const tick = env.intervals.find((i) => i.ms === 60000).fn;

    env.setVisibility('hidden');
    tick();
    let flushed = await flushRead(env);
    assert.strictEqual(byName(flushed.events, 'HEARTBEAT').length, 0, 'hidden tabs send no heartbeat');

    env.setVisibility('visible');
    tick();
    flushed = await flushRead(env);
    assert.strictEqual(byName(flushed.events, 'HEARTBEAT').length, 1, 'visible + token = liveness heartbeat');
});

test('anonymous sessions send no heartbeat', async () => {
    const env = makeEnv({ token: false });
    await flushRead(env);
    const tick = env.intervals.find((i) => i.ms === 60000).fn;

    tick();
    const flushed = await flushRead(env);
    assert.strictEqual(byName(flushed.events, 'HEARTBEAT').length, 0);
});

test('queue is capped and invalid event names are rejected', async () => {
    const env = makeEnv({ token: false });
    await flushRead(env);

    for (let i = 0; i < 100; i++) {
        env.win.DMMonitor.emit('CONTROL_CLICK', { category: 'control', element: 'el' + i });
    }
    assert.strictEqual(env.win.DMMonitor._state().queued, 60, 'queue bounded at maxQueue');

    const before = env.win.DMMonitor._state().queued;
    assert.strictEqual(env.win.DMMonitor.emit('bad name', {}), false, 'invalid names never queue');
    assert.strictEqual(env.win.DMMonitor._state().queued, before);
});

test('resource failures keep path only and are capped per page', async () => {
    const env = makeEnv({ token: false });
    await flushRead(env);

    for (let i = 0; i < 7; i++) {
        const img = env.makeElement({ tagName: 'IMG', src: 'http://localhost/img/hero' + i + '.png?sig=TOPSECRET' });
        env.doc.fire('error', { target: img });
    }

    const { events, calls } = await flushRead(env);
    const failures = byName(events, 'PAGE_RESOURCE_FAILED');
    assert.ok(failures.length > 0 && failures.length <= 5, 'capped per page load');
    assert.ok(!calls[0].opts.body.includes('TOPSECRET'), 'signed query strings never recorded');
    assert.match(failures[0].metadata.resource, /^\/img\/hero\d\.png$/, 'path only');
});

/* ------------------------------------------------------------- runner ----- */

let passed = 0;
const failures = [];
for (const [name, fn] of tests) {
    try {
        await fn();
        passed++;
        console.log(`  PASS  ${name}`);
    } catch (err) {
        failures.push([name, err]);
        console.log(`  FAIL  ${name}\n        ${err.message}`);
    }
}

console.log(`\ndm-monitor: ${passed}/${tests.length} passed`);
if (failures.length) process.exit(1);
