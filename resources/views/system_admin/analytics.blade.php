@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="admin_analytics.title">Platform analytics - DukaMkononi Super Admin</title>
@endverbatim
    <link rel="stylesheet" href="/css/themes.css?v={{ filemtime(public_path('css/themes.css')) }}">
@verbatim
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #2c3e50; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 270px; background: #1f2937; color: #cbd5e1; position: fixed; inset: 0 auto 0 0; display: flex; flex-direction: column; z-index: 100; transition: transform .3s ease; }
        .sidebar-header { padding: 22px 20px; border-bottom: 1px solid rgba(255,255,255,.08); display: flex; align-items: center; gap: 12px; }
        .logo-icon { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff; display: grid; place-items: center; font-weight: 800; }
        .logo-text h2 { color: #fff; font-size: 17px; font-weight: 800; }
        .logo-text p { font-size: 12px; color: #94a3b8; }
        .nav-items { flex: 1; padding: 14px 12px; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 13px; padding: 13px 16px; margin-bottom: 6px; border-radius: 12px; color: #cbd5e1; font-weight: 500; text-decoration: none; }
        .nav-item i { width: 22px; text-align: center; font-size: 17px; }
        .nav-item:hover { background: rgba(255,255,255,.06); }
        .nav-item.active { background: rgba(99,102,241,.22); color: #fff; font-weight: 600; }
        .sidebar-footer { padding: 16px; border-top: 1px solid rgba(255,255,255,.08); }
        .user-chip { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; background: rgba(99,102,241,.25); color: #c7d2fe; display: grid; place-items: center; font-weight: 700; }
        .user-meta b { display: block; font-size: 14px; color: #fff; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .user-meta span { font-size: 12px; color: #94a3b8; }
        .logout-btn { width: 100%; padding: 11px; border-radius: 11px; border: 1px solid rgba(255,255,255,.14); background: transparent; color: #fca5a5; font-weight: 600; cursor: pointer; }
        .main { flex: 1; margin-left: 270px; min-width: 0; }
        .topbar { background: #fff; border-bottom: 1px solid #ecf0f1; padding: 14px 20px; display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 50; }
        .hamburger { display: none; width: 42px; height: 42px; border-radius: 10px; border: 1px solid #ecf0f1; background: #fff; cursor: pointer; }
        .topbar h1 { font-size: 19px; font-weight: 800; }
        .content { padding: 22px 20px 60px; max-width: 1100px; }
        .subtitle { color: #64748b; font-size: 13.5px; margin-bottom: 16px; }
        .card { background: #fff; border: 1px solid #ecf0f1; border-radius: 14px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .ask-card { padding: 16px; margin-bottom: 16px; }
        .ask-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .ask-row input { flex: 1; min-width: 220px; padding: 13px 14px; border-radius: 11px; border: 1px solid #e2e8f0; font-size: 15px; font-family: inherit; }
        .ask-row input:focus { outline: 2px solid #c7d2fe; border-color: #4f46e5; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 16px; border-radius: 10px; border: 1px solid transparent; font-weight: 600; font-size: 14px; cursor: pointer; min-height: 44px; font-family: inherit; }
        .btn-primary { background: #4f46e5; color: #fff; } .btn-ghost { background: #fff; border-color: #e2e8f0; color: #2c3e50; }
        .btn-sm { padding: 8px 12px; min-height: 36px; font-size: 13px; }
        .btn:disabled { opacity: .6; cursor: progress; }
        .chip-btn { display: inline-block; background: #eef2ff; color: #3730a3; border: 1px solid #e0e7ff; border-radius: 999px; padding: 7px 12px; font-size: 12.5px; margin: 3px 5px 3px 0; cursor: pointer; font-family: inherit; text-align: left; }
        .chip-btn:hover { background: #e0e7ff; }
        .section-title { font-size: 12.5px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: .05em; margin: 18px 0 8px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .b-info { background: #eff6ff; color: #1d4ed8; } .b-warn { background: #fffbeb; color: #b45309; } .b-ok { background: #ecfdf5; color: #047857; } .b-muted { background: #f1f5f9; color: #64748b; }
        .answer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 16px; border-bottom: 1px solid #f1f5f9; }
        .answer-head .k { font-size: 12.5px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .answer-head .v { font-size: 30px; font-weight: 800; line-height: 1.15; }
        .answer-body { padding: 16px; }
        .explain { font-size: 15px; line-height: 1.5; }
        .ack { margin-top: 10px; font-size: 13.5px; color: #1e40af; background: #eff6ff; border-radius: 10px; padding: 10px 12px; }
        table.dm-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.dm-table th, table.dm-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        table.dm-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #7f8c8d; }
        .chart-row { display: grid; grid-template-columns: minmax(90px, 1fr) 3fr auto; gap: 10px; align-items: center; font-size: 13px; padding: 4px 0; }
        .chart-bar { height: 10px; border-radius: 999px; background: #eef2f7; overflow: hidden; }
        .chart-bar i { display: block; height: 100%; background: #4f46e5; }
        .chart-value { font-variant-numeric: tabular-nums; color: #334155; font-weight: 600; }
        ul.dm-list { margin: 4px 0 0 18px; font-size: 13px; color: #475569; }
        .alert { padding: 11px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
        .alert-ok { background: #ecfdf5; color: #065f46; } .alert-err { background: #fef2f2; color: #991b1b; } .alert-info { background: #eff6ff; color: #1e40af; }
        .empty { text-align: center; padding: 48px 20px; color: #64748b; }
        .empty i { font-size: 40px; color: #cbd5e1; display: block; margin-bottom: 12px; }
        .note { font-size: 12.5px; color: #64748b; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: #64748b; overflow-wrap: anywhere; }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; } .hamburger { display: inline-flex; align-items: center; justify-content: center; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])
@verbatim
<div class="layout">
    <aside class="sidebar" id="dmSidebar">
        <div class="sidebar-header">
            <div class="logo-icon">D</div>
            <div class="logo-text">
                <h2 data-i18n="system_admin_inquiries.brand">DukaMkononi</h2>
                <p data-i18n="system_admin_inquiries.portal">Super Admin</p>
            </div>
        </div>
        <nav class="nav-items">
            <a class="nav-item" href="/system_admin/dashboard"><i class="fa-solid fa-gauge-high"></i><span data-i18n="system_admin_inquiries.nav_dashboard">Dashboard</span></a>
            <a class="nav-item" href="/system_admin/inquiries"><i class="fa-solid fa-envelope-open-text"></i><span data-i18n="system_admin_inquiries.nav_inquiries">Inquiries</span></a>
            <a class="nav-item" href="/system_admin/certification"><i class="fa-solid fa-certificate"></i><span data-i18n="certification_ai.nav_certification">Certification</span></a>
            <a class="nav-item active" href="/system_admin/analytics"><i class="fa-solid fa-chart-line"></i><span data-i18n="admin_analytics.nav_analytics">Analytics</span></a>
            <a class="nav-item" href="/system_admin/notify"><i class="fa-solid fa-bullhorn"></i><span data-i18n="system_admin_inquiries.nav_notify">Notifications</span></a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="user-avatar" id="dmAvatar">A</div>
                <div class="user-meta">
                    <b id="dmUserName">-</b>
                    <span data-i18n="system_admin_inquiries.user_role">Super Admin</span>
                </div>
            </div>
            <button class="logout-btn" id="dmLogout" data-i18n="system_admin_inquiries.logout">Logout</button>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <button class="hamburger" id="dmMenuBtn" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
            <h1 data-i18n="admin_analytics.title">Platform analytics</h1>
        </div>
        <div class="content">
            <p class="subtitle" data-i18n="admin_analytics.subtitle"></p>
            <div id="dmAlert"></div>

            <div class="card ask-card">
                <div class="ask-row">
                    <input id="dmQuestion" type="text" autocomplete="off" data-i18n="admin_analytics.ask_placeholder" data-i18n-attr="placeholder" placeholder="e.g. How many sellers joined last month?">
                    <button class="btn btn-primary" id="dmAskBtn"><i class="fa-solid fa-paper-plane"></i><span data-i18n="admin_analytics.ask_button">Ask</span></button>
                    <button class="btn btn-ghost" id="dmClearBtn"><span data-i18n="admin_analytics.clear">Clear</span></button>
                </div>
                <div style="margin-top:12px">
                    <span class="badge b-info" data-i18n="admin_analytics.scope_badge">Platform-wide - Super Admin only</span>
                    <span class="badge b-muted" id="dmAnswerKind" style="display:none"></span>
                </div>
                <div class="section-title" data-i18n="admin_analytics.examples_title">Example questions</div>
                <div id="dmExamples"></div>
                <p class="note" style="margin-top:10px" data-i18n="admin_analytics.no_sql_note"></p>
                <p class="note" data-i18n="admin_analytics.privacy_note"></p>
            </div>

            <div class="card" id="dmAnswerCard" style="display:none">
                <div class="answer-head">
                    <div>
                        <div class="k" id="dmAnswerLabel"></div>
                        <div class="v" id="dmAnswerValue">-</div>
                        <div class="mono" id="dmAnswerRange"></div>
                    </div>
                    <div style="text-align:right">
                        <span class="badge b-muted" id="dmAnswerTool"></span>
                    </div>
                </div>
                <div class="answer-body" id="dmAnswerBody"></div>
            </div>

            <div id="dmEmpty" class="empty">
                <i class="fa-solid fa-chart-line"></i>
                <p data-i18n="admin_analytics.empty_state">Ask a question to see the numbers, the table and the chart.</p>
            </div>
        </div>
    </div>
</div>
@endverbatim
@verbatim
<script>
(function () {
    'use strict';
    var t = function (k, p) { return window.DM ? DM.t(k, p) : k; };
    var token = localStorage.getItem('userToken');
    var user = {};
    try { user = JSON.parse(localStorage.getItem('userData') || '{}'); } catch (e) { user = {}; }
    var role = (user.role || '').toLowerCase();
    if (!token) { window.location.href = '/login?role=system_admin'; return; }
    if (['admin', 'system_admin'].indexOf(role) === -1) { window.location.href = '/home'; return; }

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
    function auth() { return { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }; }
    function locale() { return (window.DM && DM.locale()) || 'sw'; }
    function alertBox(cls, msg) { document.getElementById('dmAlert').innerHTML = msg ? '<div class="alert alert-' + cls + '">' + esc(msg) + '</div>' : ''; }
    function num(v) { if (v === null || v === undefined || v === '') return '-'; var n = Number(v); return isNaN(n) ? String(v) : n.toLocaleString(locale()); }

    function loadExamples() {
        fetch('/api/ai/platform-analytics/examples?locale=' + encodeURIComponent(locale()), { headers: auth() })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !Array.isArray(d.examples)) return;
                document.getElementById('dmExamples').innerHTML = d.examples.map(function (q) {
                    return '<button class="chip-btn" data-example="' + esc(q) + '">' + esc(q) + '</button>';
                }).join('');
            })
            .catch(function () {});
    }

    function chartHtml(chart) {
        if (!chart || !Array.isArray(chart.labels) || !chart.labels.length) return '';
        var values = (chart.values || []).map(Number);
        var max = Math.max.apply(null, values.concat([0])) || 1;
        return '<div class="section-title">' + esc(t('admin_analytics.chart_title')) + (chart.metric ? ' - ' + esc(chart.metric) : '') + '</div>' +
            chart.labels.map(function (label, i) {
                var v = values[i] || 0;
                var pct = Math.max(0, Math.min(100, (v / max) * 100));
                return '<div class="chart-row"><span>' + esc(label) + '</span>' +
                    '<span class="chart-bar"><i style="width:' + pct.toFixed(1) + '%"></i></span>' +
                    '<span class="chart-value">' + esc(num(v)) + '</span></div>';
            }).join('');
    }

    function tableHtml(table) {
        if (!table || !Array.isArray(table.rows) || !table.rows.length) return '';
        var cols = table.columns || [];
        return '<div class="section-title">' + esc(t('admin_analytics.table_title')) + '</div><div style="overflow-x:auto"><table class="dm-table"><thead><tr>' +
            cols.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') +
            '</tr></thead><tbody>' +
            table.rows.map(function (row) {
                return '<tr>' + (row || []).map(function (cell) {
                    return '<td class="dm-wrap-anywhere">' + esc(typeof cell === 'number' ? num(cell) : cell) + '</td>';
                }).join('') + '</tr>';
            }).join('') +
            '</tbody></table></div>';
    }

    function listHtml(title, items) {
        if (!Array.isArray(items) || !items.length) return '';
        return '<div class="section-title">' + esc(title) + '</div><ul class="dm-list">' +
            items.map(function (i) { return '<li>' + esc(i) + '</li>'; }).join('') + '</ul>';
    }

    function render(d) {
        var r = d.result || null;
        var kind = document.getElementById('dmAnswerKind');

        if (!r) {
            /* No tool matched: say so, and show the supported examples instead of
             * inventing an answer. */
            alertBox('info', d.notice || t('admin_analytics.error'));
            document.getElementById('dmAnswerCard').style.display = 'none';
            document.getElementById('dmEmpty').style.display = 'block';
            kind.style.display = 'none';
            return;
        }

        alertBox('ok', '');
        document.getElementById('dmEmpty').style.display = 'none';
        document.getElementById('dmAnswerCard').style.display = 'block';

        kind.style.display = 'inline-block';
        kind.textContent = r.live ? t('admin_analytics.live_badge') : t('admin_analytics.period_badge');
        kind.className = 'badge ' + (r.live ? 'b-ok' : 'b-muted');

        document.getElementById('dmAnswerLabel').textContent = r.value_label || d.parsed.tool_label || '';
        document.getElementById('dmAnswerValue').textContent = num(r.value);
        document.getElementById('dmAnswerRange').textContent = r.range_label || '';
        document.getElementById('dmAnswerTool').textContent = d.parsed.tool_label || '';

        var html = '<div class="explain">' + esc(r.explanation || '') + '</div>';
        if (d.acknowledgement) { html += '<div class="ack">' + esc(d.acknowledgement) + '</div>'; }
        html += chartHtml(r.chart);
        html += tableHtml(r.table);
        html += listHtml(t('admin_analytics.sources_title'), r.sources);
        html += listHtml(t('admin_analytics.limitations_title'), r.limitations);
        html += '<p class="note" style="margin-top:14px">' + esc(d.scope_note || '') + ' ' + esc(d.notice_no_sql || '') + '</p>';
        if (d.truncated) { html += '<p class="note">' + esc(num(d.rows_read)) + '</p>'; }
        document.getElementById('dmAnswerBody').innerHTML = html;
    }

    function ask(question) {
        var q = String(question || '').trim();
        if (q.length < 3) { alertBox('err', t('admin_analytics.error')); return; }
        document.getElementById('dmQuestion').value = q;
        document.getElementById('dmAskBtn').disabled = true;
        alertBox('info', t('admin_analytics.loading'));
        fetch('/api/ai/platform-analytics/ask', {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, auth()),
            body: JSON.stringify({ locale: locale(), question: q })
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok) { alertBox('err', (res.d && res.d.error) || t('admin_analytics.error')); return; }
                render(res.d);
            })
            .catch(function () { alertBox('err', t('admin_analytics.error')); })
            .then(function () { document.getElementById('dmAskBtn').disabled = false; });
    }

    document.addEventListener('click', function (e) {
        var ex = e.target.closest('[data-example]'); if (ex) ask(ex.getAttribute('data-example'));
    });
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('dmUserName').textContent = user.full_name || user.email || '-';
        document.getElementById('dmAvatar').textContent = (user.full_name || user.email || 'A').trim().charAt(0).toUpperCase();
        document.getElementById('dmMenuBtn').onclick = function () { document.getElementById('dmSidebar').classList.toggle('open'); };
        document.getElementById('dmLogout').onclick = function () { localStorage.clear(); window.location.href = '/login?role=system_admin'; };
        document.getElementById('dmAskBtn').onclick = function () { ask(document.getElementById('dmQuestion').value); };
        document.getElementById('dmQuestion').addEventListener('keydown', function (e) { if (e.key === 'Enter') ask(e.target.value); });
        document.getElementById('dmClearBtn').onclick = function () {
            document.getElementById('dmQuestion').value = '';
            document.getElementById('dmAnswerCard').style.display = 'none';
            document.getElementById('dmEmpty').style.display = 'block';
            alertBox('ok', '');
        };
        loadExamples();
        if (window.DM) DM.onChange(function () { loadExamples(); });
    });
})();
</script>
</body>
</html>
@endverbatim
