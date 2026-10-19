/*
 * Structural checks on the rebuilt Admin User Logs dashboard
 * (resources/views/system_admin/dashboard.blade.php). Execute with:
 *   node tests/js/dashboard-monitoring.test.mjs
 *
 * The inline script is huge and template-driven, so beyond parsing it we pin
 * the wiring that a typo could silently break: every monitoring endpoint the
 * UI promises, every onclick handler having a real definition, and the
 * legacy compatibility surface still being present.
 */
import { readFileSync } from 'node:fs';
import assert from 'node:assert';

const src = readFileSync(new URL('../../resources/views/system_admin/dashboard.blade.php', import.meta.url), 'utf8');
const tests = [];
const test = (name, fn) => tests.push([name, fn]);

test('script block parses as JavaScript', () => {
    const blocks = [...src.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]);
    assert.strictEqual(blocks.length, 1, 'one inline script');
    assert.doesNotThrow(() => new Function(blocks[0]), 'inline script must be valid JS');
    assert.ok(!blocks[0].includes('{{'), 'no unescaped blade output inside the script');
});

test('every monitoring endpoint the dashboard promises is referenced', () => {
    const endpoints = [
        'admin/monitoring/summary',
        'admin/monitoring/timeline',
        'admin/monitoring/events/',
        'admin/monitoring/users/',
        'admin/monitoring/sessions',
        'admin/monitoring/alerts',
        'admin/monitoring/setup',
        'admin/monitoring/export',
    ];
    for (const endpoint of endpoints) {
        assert.ok(src.includes(endpoint), `missing endpoint wiring: ${endpoint}`);
    }
});

test('every onclick handler has a definition', () => {
    const handlers = new Set();
    for (const m of src.matchAll(/onclick="([A-Za-z_$][\w$]*)\s*\(/g)) handlers.add(m[1]);
    // Also handlers built inside template literals (onclick="${...}( ... )").
    for (const m of src.matchAll(/onclick="([A-Za-z_$][\w$]*)\(/g)) handlers.add(m[1]);

    const builtins = new Set(['window', 'confirm', 'alert', 'showToast']);
    const missing = [];
    for (const name of handlers) {
        if (builtins.has(name)) continue;
        const defined =
            src.includes(`function ${name}(`) ||
            src.includes(`window.${name} =`) ||
            src.includes(`window.${name} = `) ||
            new RegExp(`\\b${name}\\s*=`).test(src.match(/<script>[\s\S]*<\/script>/)[0]);
        if (!defined) missing.push(name);
    }
    assert.deepStrictEqual(missing, [], `undefined handlers: ${missing.join(', ')}`);
    assert.ok(handlers.size >= 20, `expected the dashboard's handlers, found ${handlers.size}`);
});

test('dynamic content is escaped before rendering', () => {
    const script = src.match(/<script>[\s\S]*<\/script>/)[0];
    assert.ok(script.includes('function escapeHtml('), 'escapeHtml exists');
    assert.ok(script.includes('function escapeAttr('), 'escapeAttr exists for attribute contexts');
    // Raw interpolations of remote fields must go through escaping: spot-check
    // the rows that render user-controlled audit data.
    assert.ok(script.includes('escapeHtml(ev.event_name'), 'event names escaped in rows');
    assert.ok(script.includes('escapeHtml(ev.description'), 'descriptions escaped in rows');
    assert.ok(script.includes('escapeHtml(ev.ip_address'), 'ip addresses escaped in timeline/detail');
    assert.ok(script.includes('escapeHtml(s.ip_address'), 'ip addresses escaped in sessions');
    assert.ok(script.includes("escapeHtml(log.ip_address"), 'ip addresses escaped in the legacy view');
});

test('the four sub-views and the legacy fallback exist', () => {
    for (const fn of ['renderLogsTab', 'renderTimelineView', 'renderSecurityView', 'renderSessionsView', 'renderLegacyLogsView']) {
        assert.ok(src.includes(`function ${fn}(`), `missing ${fn}`);
    }
    assert.ok(src.includes("logsView = 'timeline'"), 'timeline is the default view');
    assert.ok(src.includes('userActivityLegacyHtml('), 'legacy per-user fallback kept for un-migrated deployments');
});

test('legacy consumers stay intact', () => {
    assert.ok(src.includes('/api/admin/logs'), 'legacy logs fetch preserved');
    assert.ok(src.includes('function getFilteredLogs('), 'legacy filtering preserved');
    assert.ok(src.includes('renderLogsTab') && src.includes('logStatusFilters'), 'legacy filter chips preserved');
});

test('incident actions post to the audited status endpoint', () => {
    const script = src.match(/<script>[\s\S]*<\/script>/)[0];
    assert.ok(script.includes("admin/monitoring/alerts/${encodeURIComponent(id)}/status"), 'status changes go through the API');
    for (const status of ['acknowledged', 'resolved', "'open'"]) {
        assert.ok(script.includes(status), `status ${status} supported`);
    }
    assert.ok(script.includes('monNoteInput'), 'investigation notes captured');
    assert.ok(script.includes('monResolutionInput'), 'resolution text captured');
});

test('setup banner is wired to the runtime table creation', () => {
    assert.ok(src.includes('runMonSetup'), 'setup handler present');
    assert.ok(src.includes("monUnavailable && logsView !== 'legacy'"), 'banner only outside the legacy view');
    assert.ok(src.includes('admin/monitoring/setup'), 'setup endpoint called');
});

let passed = 0;
const failures = [];
for (const [name, fn] of tests) {
    try {
        fn();
        passed++;
        console.log(`  PASS  ${name}`);
    } catch (err) {
        failures.push(name);
        console.log(`  FAIL  ${name}\n        ${err.message}`);
    }
}

console.log(`\ndashboard wiring: ${passed}/${tests.length} passed`);
if (failures.length) process.exit(1);
