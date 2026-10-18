@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="certification_ai.title">Business certification - DukaMkononi Super Admin</title>
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
        .content { padding: 22px 20px 60px; }
        .subtitle { color: #64748b; font-size: 13.5px; margin-bottom: 16px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 16px; border-radius: 10px; border: 1px solid transparent; font-weight: 600; font-size: 14px; cursor: pointer; min-height: 44px; }
        .btn-primary { background: #4f46e5; color: #fff; } .btn-ghost { background: #fff; border-color: #e2e8f0; color: #2c3e50; }
        .btn-sm { padding: 8px 12px; min-height: 36px; font-size: 13px; }
        .btn:disabled { opacity: .6; cursor: progress; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .stat { background: #fff; border: 1px solid #ecf0f1; border-radius: 14px; padding: 14px 16px; }
        .stat span { display: block; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .stat b { font-size: 22px; }
        .card { background: #fff; border: 1px solid #ecf0f1; border-radius: 14px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .cert-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .cert-table th, .cert-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .cert-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #7f8c8d; }
        .cert-table tr:hover td { background: #fafbff; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .b-warn { background: #fffbeb; color: #b45309; } .b-info { background: #eff6ff; color: #1d4ed8; } .b-ok { background: #ecfdf5; color: #047857; } .b-muted { background: #f1f5f9; color: #64748b; }
        .bar { height: 8px; border-radius: 999px; background: #eef2f7; overflow: hidden; min-width: 90px; margin-top: 6px; }
        .bar i { display: block; height: 100%; background: #4f46e5; }
        .chip { display: inline-block; background: #fff7ed; color: #9a3412; border-radius: 999px; padding: 2px 9px; font-size: 12px; margin: 2px 4px 2px 0; }
        .chip-human { background: #eff6ff; color: #1e40af; }
        .cert-cards { display: none; flex-direction: column; gap: 12px; }
        .cert-card { padding: 16px; }
        .cert-card .oc-row { display: flex; justify-content: space-between; gap: 8px; padding: 4px 0; font-size: 13.5px; flex-wrap: wrap; }
        .empty { text-align: center; padding: 48px 20px; color: #64748b; }
        .empty i { font-size: 40px; color: #cbd5e1; display: block; margin-bottom: 12px; }
        .overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: none; align-items: center; justify-content: center; padding: 14px; z-index: 900; }
        .overlay.open { display: flex; }
        .modal { background: #fff; border-radius: 16px; width: 100%; max-width: 720px; max-height: 92vh; overflow-y: auto; }
        .modal-head { padding: 16px 18px; border-bottom: 1px solid #ecf0f1; display: flex; align-items: center; justify-content: space-between; gap: 10px; position: sticky; top: 0; background: #fff; }
        .modal-body { padding: 18px; }
        .detail-row { display: flex; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; }
        .detail-row b { min-width: 120px; color: #64748b; font-weight: 600; font-size: 13px; }
        .alert { padding: 11px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
        .alert-ok { background: #ecfdf5; color: #065f46; } .alert-err { background: #fef2f2; color: #991b1b; } .alert-info { background: #eff6ff; color: #1e40af; }
        .req { border: 1px solid #eef2f7; border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; background: #fff; }
        .req-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .req-head b { font-size: 14px; }
        .req-ev { margin-top: 6px; font-size: 13px; color: #475569; overflow-wrap: anywhere; }
        .req-why { margin-top: 6px; font-size: 13px; color: #1e40af; background: #eff6ff; border-radius: 8px; padding: 8px 10px; }
        .tri-sec { margin-top: 14px; }
        .tri-sec > b { font-size: 13px; color: #334155; }
        .tri-list { margin: 6px 0 0 18px; font-size: 13px; color: #475569; overflow-wrap: anywhere; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: #64748b; overflow-wrap: anywhere; }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; } .hamburger { display: inline-flex; align-items: center; justify-content: center; }
            .cert-table { display: none; } .cert-cards { display: flex; }
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
            <a class="nav-item active" href="/system_admin/certification"><i class="fa-solid fa-certificate"></i><span data-i18n="certification_ai.nav_certification">Certification</span></a>
            <a class="nav-item" href="/system_admin/analytics"><i class="fa-solid fa-chart-line"></i><span data-i18n="admin_analytics.nav_analytics">Analytics</span></a>
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
            <h1 data-i18n="certification_ai.title">Business certification review</h1>
        </div>
        <div class="content">
            <p class="subtitle" data-i18n="certification_ai.subtitle"></p>
            <div id="dmAlert"></div>
            <div class="stats" id="dmCertStats"></div>
            <div class="card" id="dmTableCard" style="display:none">
                <div style="overflow-x:auto">
                    <table class="cert-table">
                        <thead><tr>
                            <th data-i18n="certification_ai.col_business">Business</th>
                            <th data-i18n="certification_ai.col_location">Location</th>
                            <th data-i18n="certification_ai.col_completeness">Complete</th>
                            <th data-i18n="certification_ai.col_missing">Missing</th>
                            <th data-i18n="certification_ai.col_human">Human check</th>
                            <th></th>
                        </tr></thead>
                        <tbody id="dmCertBody"></tbody>
                    </table>
                </div>
                <div class="cert-cards" id="dmCertCards"></div>
            </div>
            <div id="dmEmpty" style="display:none" class="empty">
                <i class="fa-solid fa-certificate"></i>
                <h3 data-i18n="certification_ai.empty">No business is waiting for certification.</h3>
            </div>
        </div>
    </div>
</div>

<div class="overlay" id="dmCertOverlay" role="dialog" aria-modal="true">
    <div class="modal">
        <div class="modal-head">
            <h3 id="dmCertTitle" data-i18n="certification_ai.title">Review</h3>
            <button class="btn btn-ghost btn-sm" data-dm-close="dmCertOverlay" data-i18n="certification_ai.close">Close</button>
        </div>
        <div class="modal-body" id="dmCertDetail"></div>
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
    function fmtDate(s) { if (!s) return '-'; var d = new Date(s); return isNaN(d) ? String(s) : d.toLocaleString((window.DM && DM.locale()) || 'sw'); }
    function auth() { return { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }; }
    function locale() { return (window.DM && DM.locale()) || 'sw'; }
    function alertBox(cls, msg) { document.getElementById('dmAlert').innerHTML = msg ? '<div class="alert alert-' + cls + '">' + esc(msg) + '</div>' : ''; }
    function statusClass(status) {
        return ({ met: 'b-ok', missing: 'b-warn', unknown: 'b-muted', human_required: 'b-info' })[status] || 'b-muted';
    }

    var state = { items: [], counts: null, truncated: false, capped: false };

    function load() {
        alertBox('ok', '');
        document.getElementById('dmCertStats').innerHTML = '<div class="stat"><span>' + esc(t('certification_ai.loading')) + '</span><b>&nbsp;</b></div>';
        fetch('/api/ai/certification/queue?locale=' + encodeURIComponent(locale()), { headers: auth() })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok) { alertBox('err', (res.d && res.d.error) || t('certification_ai.error')); return; }
                state.items = res.d.items || [];
                state.counts = res.d.counts || {};
                state.truncated = !!res.d.truncated;
                state.capped = !!res.d.reads_capped;
                renderStats();
                renderList();
                if (state.truncated) { alertBox('info', t('certification_ai.truncated_note')); }
                else if (state.capped) { alertBox('info', t('certification_ai.reads_capped_note')); }
            })
            .catch(function () { alertBox('err', t('certification_ai.error')); });
    }

    function statCell(label, value) {
        return '<div class="stat"><span>' + esc(label) + '</span><b>' + esc(String(value)) + '</b></div>';
    }
    function renderStats() {
        var c = state.counts || {};
        document.getElementById('dmCertStats').innerHTML =
            statCell(t('certification_ai.stat_pending'), c.pending || 0) +
            statCell(t('certification_ai.stat_ready'), c.ready || 0) +
            statCell(t('certification_ai.stat_certified'), c.certified || 0) +
            statCell(t('certification_ai.stat_human'), c.with_human_checks || 0);
    }

    function missingHtml(item) {
        var labels = item.missing_labels || [];
        if (labels.length === 0) { return '<span class="badge b-ok">' + esc(t('certification_ai.no_missing')) + '</span>'; }
        return labels.map(function (l) { return '<span class="chip">' + esc(l.label) + '</span>'; }).join('');
    }
    function humanHtml(item) {
        var labels = item.human_check_labels || [];
        if (labels.length === 0) { return '<span class="badge b-ok">-</span>'; }
        return '<span class="badge b-info">' + esc(String(labels.length)) + '</span>';
    }
    function rowHtml(item) {
        return '<td class="dm-wrap-anywhere"><b>' + esc(item.business_name) + '</b><div class="mono">' + esc(item.id) + '</div></td>' +
            '<td>' + esc(item.business_location || '-') + '</td>' +
            '<td><b>' + esc(String(item.completeness)) + '%</b><div class="bar"><i style="width:' + Math.max(0, Math.min(100, Number(item.completeness) || 0)) + '%"></i></div></td>' +
            '<td>' + missingHtml(item) + '</td>' +
            '<td>' + humanHtml(item) + '</td>' +
            '<td><button class="btn btn-primary btn-sm" data-review="' + esc(item.id) + '">' + esc(t('certification_ai.btn_review')) + '</button></td>';
    }
    function renderList() {
        var body = document.getElementById('dmCertBody'); body.innerHTML = '';
        var cards = document.getElementById('dmCertCards'); cards.innerHTML = '';
        document.getElementById('dmTableCard').style.display = state.items.length ? 'block' : 'none';
        document.getElementById('dmEmpty').style.display = state.items.length ? 'none' : 'block';

        state.items.forEach(function (item) {
            var tr = document.createElement('tr'); tr.innerHTML = rowHtml(item); body.appendChild(tr);

            var c = document.createElement('div'); c.className = 'card cert-card';
            c.innerHTML = '<div class="oc-row"><b class="dm-wrap-anywhere">' + esc(item.business_name) + '</b><span>' + esc(String(item.completeness)) + '%</span></div>' +
                '<div class="oc-row"><span>' + esc(t('certification_ai.col_location')) + '</span><span>' + esc(item.business_location || '-') + '</span></div>' +
                '<div class="oc-row"><span>' + esc(t('certification_ai.col_missing')) + '</span><span>' + missingHtml(item) + '</span></div>' +
                '<div class="oc-row"><span>' + esc(t('certification_ai.col_human')) + '</span><span>' + humanHtml(item) + '</span></div>' +
                '<button class="btn btn-primary btn-sm" style="margin-top:10px" data-review="' + esc(item.id) + '">' + esc(t('certification_ai.btn_review')) + '</button>';
            cards.appendChild(c);
        });
    }

    function openReview(id) {
        document.getElementById('dmCertDetail').innerHTML = '<div class="empty"><div class="dm-sc" style="width:44px;height:44px;margin:0 auto 12px"></div></div>';
        document.getElementById('dmCertTitle').textContent = t('certification_ai.reviewing');
        document.getElementById('dmCertOverlay').classList.add('open');
        fetch('/api/ai/certification/' + encodeURIComponent(id) + '/review?locale=' + encodeURIComponent(locale()), {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, auth()),
            body: JSON.stringify({ locale: locale() })
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok) {
                    document.getElementById('dmCertDetail').innerHTML = '<div class="alert alert-err">' + esc((res.d && res.d.error) || t('certification_ai.error')) + '</div>';
                    return;
                }
                renderReview(res.d);
            })
            .catch(function () {
                document.getElementById('dmCertDetail').innerHTML = '<div class="alert alert-err">' + esc(t('certification_ai.error')) + '</div>';
            });
    }

    function renderReview(d) {
        var b = d.business || {}, r = d.review || {};
        document.getElementById('dmCertTitle').textContent = b.business_name || t('certification_ai.title');

        var html = '';
        html += '<div class="alert alert-info">' + esc(d.notice || t('certification_ai.notice_no_decision')) + '</div>';
        html += '<div class="alert alert-info">' + esc(t('certification_ai.notice_decision_endpoint')) + '</div>';
        if (d.reads_capped) { html += '<div class="alert alert-info">' + esc(t('certification_ai.reads_capped_note')) + '</div>'; }

        html += '<div class="detail-row"><b>' + esc(t('certification_ai.col_business')) + '</b><span class="dm-wrap-anywhere">' + esc(b.business_name || '-') + '</span></div>';
        html += '<div class="detail-row"><b>' + esc(t('certification_ai.label_type')) + '</b><span>' + esc(b.business_type || '-') + '</span></div>';
        html += '<div class="detail-row"><b>' + esc(t('certification_ai.col_location')) + '</b><span>' + esc(b.business_location || '-') + '</span></div>';
        html += '<div class="detail-row"><b>' + esc(t('certification_ai.label_created')) + '</b><span>' + esc(fmtDate(b.created_at)) + '</span></div>';
        html += '<div class="detail-row"><b>' + esc(t('certification_ai.label_status')) + '</b><span>' +
            (b.is_certified
                ? '<span class="badge b-ok">' + esc(t('certification_ai.badge_certified')) + '</span>'
                : '<span class="badge b-warn">' + esc(t('certification_ai.badge_pending')) + '</span>') + '</span></div>';
        if (b.certified_at) {
            html += '<div class="detail-row"><b>' + esc(t('certification_ai.label_certified_at')) + '</b><span>' + esc(fmtDate(b.certified_at)) + '</span></div>';
        }
        html += '<div class="detail-row"><b>' + esc(t('certification_ai.col_completeness')) + '</b><span><b>' + esc(String(r.completeness || 0)) + '%</b> ' +
            '<span class="badge ' + ((r.completeness || 0) >= 100 ? 'b-ok' : 'b-warn') + '">' + esc(t('certification_ai.col_missing')) + ': ' + esc(String((r.missing || []).length)) + '</span></span></div>';

        html += '<div class="tri-sec"><b>' + esc(t('certification_ai.section_requirements')) + '</b>';
        (r.requirements || []).forEach(function (line) {
            html += '<div class="req">' +
                '<div class="req-head"><b>' + esc(line.label) + '</b>' +
                '<span><span class="badge ' + statusClass(line.status) + '">' + esc(line.status_label) + '</span> ' +
                '<span class="badge b-muted">' + esc(line.severity_label) + '</span></span></div>' +
                '<div class="req-ev">' + esc(line.evidence) + '</div>' +
                '<div class="mono">' + esc(line.source) + '</div>' +
                (line.why ? '<div class="req-why">' + esc(line.why) + '</div>' : '') +
                '</div>';
        });
        html += '</div>';

        if ((r.human_checks || []).length) {
            html += '<div class="tri-sec"><b>' + esc(t('certification_ai.section_human_checks')) + '</b><ul class="tri-list">';
            r.human_checks.forEach(function (h) { html += '<li>' + esc(h.label) + (h.why ? ' - ' + esc(h.why) : '') + '</li>'; });
            html += '</ul></div>';
        }

        if ((r.sources || []).length) {
            html += '<div class="tri-sec"><b>' + esc(t('certification_ai.section_sources')) + '</b><ul class="tri-list">';
            r.sources.forEach(function (s) { html += '<li class="mono">' + esc(s) + '</li>'; });
            html += '</ul></div>';
        }

        if ((r.limitations || []).length) {
            html += '<div class="tri-sec"><b>' + esc(t('certification_ai.section_limitations')) + '</b><ul class="tri-list">';
            r.limitations.forEach(function (l) { html += '<li>' + esc(l) + '</li>'; });
            html += '</ul></div>';
        }

        if (d.checklist_hash) {
            html += '<div class="tri-sec"><span class="mono">' + esc(d.checklist_hash) + '</span></div>';
        }

        document.getElementById('dmCertDetail').innerHTML = html;
    }

    document.addEventListener('click', function (e) {
        var v = e.target.closest('[data-review]'); if (v) openReview(v.getAttribute('data-review'));
        var c = e.target.closest('[data-dm-close]'); if (c) document.getElementById(c.getAttribute('data-dm-close')).classList.remove('open');
        if (e.target.classList.contains('overlay')) e.target.classList.remove('open');
    });
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('dmUserName').textContent = user.full_name || user.email || '-';
        document.getElementById('dmAvatar').textContent = (user.full_name || user.email || 'A').trim().charAt(0).toUpperCase();
        document.getElementById('dmMenuBtn').onclick = function () { document.getElementById('dmSidebar').classList.toggle('open'); };
        document.getElementById('dmLogout').onclick = function () { (window.DMTheme ? DMTheme.clearSession() : localStorage.clear()); window.location.href = '/login?role=system_admin'; };
        load();
        if (window.DM) DM.onChange(function () { load(); });
    });
})();
</script>
</body>
</html>
@endverbatim
