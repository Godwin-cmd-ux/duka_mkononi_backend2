@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="system_admin_inquiries.page_title">Maswali - DukaMkononi Super Admin</title>
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
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .toolbar select, .toolbar input { padding: 11px 13px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; min-height: 44px; }
        .toolbar input { flex: 1 1 220px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 16px; border-radius: 10px; border: 1px solid transparent; font-weight: 600; font-size: 14px; cursor: pointer; min-height: 44px; }
        .btn-primary { background: #4f46e5; color: #fff; } .btn-ok { background: #059669; color: #fff; } .btn-ghost { background: #fff; border-color: #e2e8f0; color: #2c3e50; } .btn-danger { background: #dc2626; color: #fff; }
        .btn-sm { padding: 8px 12px; min-height: 36px; font-size: 13px; }
        .card { background: #fff; border: 1px solid #ecf0f1; border-radius: 14px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .inq-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .inq-table th, .inq-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .inq-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #7f8c8d; }
        .inq-table tr:hover td { background: #fafbff; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .b-warn { background: #fffbeb; color: #b45309; } .b-info { background: #eff6ff; color: #1d4ed8; } .b-ok { background: #ecfdf5; color: #047857; } .b-muted { background: #f1f5f9; color: #64748b; }
        .inq-cards { display: none; flex-direction: column; gap: 12px; }
        .inq-card { padding: 16px; }
        .inq-card .oc-row { display: flex; justify-content: space-between; gap: 8px; padding: 4px 0; font-size: 13.5px; flex-wrap: wrap; }
        .empty { text-align: center; padding: 48px 20px; color: #64748b; } .empty i { font-size: 40px; color: #cbd5e1; display: block; margin-bottom: 12px; }
        .overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: none; align-items: center; justify-content: center; padding: 14px; z-index: 900; }
        .overlay.open { display: flex; }
        .modal { background: #fff; border-radius: 16px; width: 100%; max-width: 640px; max-height: 92vh; overflow-y: auto; }
        .modal-head { padding: 16px 18px; border-bottom: 1px solid #ecf0f1; display: flex; align-items: center; justify-content: space-between; gap: 10px; position: sticky; top: 0; background: #fff; }
        .modal-body { padding: 18px; }
        .modal-foot { padding: 14px 18px; border-top: 1px solid #ecf0f1; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; position: sticky; bottom: 0; background: #fff; }
        .field { margin-bottom: 14px; } .field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; padding: 11px 13px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14.5px; min-height: 44px; font-family: inherit; }
        .field textarea { min-height: 90px; resize: vertical; }
        .detail-row { display: flex; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; }
        .detail-row b { min-width: 110px; color: #64748b; font-weight: 600; font-size: 13px; }
        .msg-box { background: #f8fafc; border: 1px solid #eef2f7; border-radius: 10px; padding: 12px; white-space: pre-wrap; overflow-wrap: anywhere; }
        .alert { padding: 11px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
        .alert-ok { background: #ecfdf5; color: #065f46; } .alert-err { background: #fef2f2; color: #991b1b; } .alert-info { background: #eff6ff; color: #1e40af; }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; } .hamburger { display: inline-flex; align-items: center; justify-content: center; }
            .inq-table { display: none; } .inq-cards { display: flex; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
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
            <a class="nav-item active" href="/system_admin/inquiries"><i class="fa-solid fa-envelope-open-text"></i><span data-i18n="system_admin_inquiries.nav_inquiries">Inquiries</span></a>
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
            <h1 data-i18n="system_admin_inquiries.title">Customer Inquiries</h1>
        </div>
        <div class="content">
            <div id="dmAlert"></div>
            <div class="toolbar">
                <select id="dmStatusFilter">
                    <option value="" data-i18n="system_admin_inquiries.filter_all">All</option>
                    <option value="new" data-i18n="system_admin_inquiries.filter_new">New</option>
                    <option value="reviewed" data-i18n="system_admin_inquiries.filter_reviewed">Reviewed</option>
                    <option value="published" data-i18n="system_admin_inquiries.filter_published">Published</option>
                </select>
                <input type="search" id="dmSearch" placeholder="Search email, phone or subject" data-i18n="system_admin_inquiries.search_placeholder" data-i18n-attr="placeholder">
                <button class="btn btn-ghost" id="dmRefresh"><i class="fa-solid fa-rotate-right"></i><span data-i18n="system_admin_inquiries.refresh">Refresh</span></button>
            </div>

            <div class="card" id="dmTableCard">
                <div style="overflow-x:auto">
                    <table class="inq-table">
                        <thead><tr>
                            <th data-i18n="system_admin_inquiries.col_subject">Subject</th>
                            <th data-i18n="system_admin_inquiries.col_customer">Customer</th>
                            <th data-i18n="system_admin_inquiries.col_status">Status</th>
                            <th data-i18n="system_admin_inquiries.col_date">Date</th>
                            <th></th>
                        </tr></thead>
                        <tbody id="dmInqBody"></tbody>
                    </table>
                </div>
                <div class="inq-cards" id="dmInqCards"></div>
            </div>
            <div id="dmEmpty" style="display:none" class="empty">
                <i class="fa-solid fa-envelope-open-text"></i>
                <h3 data-i18n="system_admin_inquiries.empty">No inquiries yet</h3>
            </div>
            <div id="dmMore" style="text-align:center;margin-top:18px"></div>
        </div>
    </div>
</div>

<div class="overlay" id="dmInqOverlay">
    <div class="modal">
        <div class="modal-head">
            <h3 data-i18n="system_admin_inquiries.detail_title">Inquiry details</h3>
            <button class="btn btn-ghost btn-sm" data-dm-close="dmInqOverlay" data-i18n="system_admin_inquiries.close">Close</button>
        </div>
        <div class="modal-body" id="dmInqDetail"></div>
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
    function alertBox(cls, msg) { document.getElementById('dmAlert').innerHTML = msg ? '<div class="alert alert-' + cls + '">' + esc(msg) + '</div>' : ''; }
    function statusBadge(i) { if (i.is_published) return '<span class="badge b-ok">' + esc(t('system_admin_inquiries.published_badge')) + '</span>'; var map = { new: 'b-warn', reviewed: 'b-info', published: 'b-ok', archived: 'b-muted' }; return '<span class="badge ' + (map[i.status] || 'b-muted') + '">' + esc(i.status) + '</span>'; }

    var state = { page: 1, lastPage: 1, status: '', search: '' };

    function load(reset) {
        if (reset) state.page = 1;
        var q = '/api/system-admin/inquiries?page=' + state.page + (state.status ? '&status=' + encodeURIComponent(state.status) : '') + (state.search ? '&search=' + encodeURIComponent(state.search) : '');
        fetch(q, { headers: auth() }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok) { alertBox('err', (res.d && res.d.error) || t('system_admin_inquiries.err_generic')); return; }
                var data = (res.d && res.d.data) || [];
                state.lastPage = (res.d && res.d.meta && res.d.meta.last_page) || 1;
                if (reset) render(data); else appendRows(data);
                document.getElementById('dmEmpty').style.display = (data.length === 0 && state.page === 1) ? 'block' : 'none';
                document.getElementById('dmTableCard').style.display = (data.length === 0 && state.page === 1) ? 'none' : 'block';
                var more = document.getElementById('dmMore'); more.innerHTML = '';
                if (state.page < state.lastPage) { var b = document.createElement('button'); b.className = 'btn btn-ghost'; b.textContent = t('system_admin_inquiries.load_more'); b.onclick = function () { state.page++; load(false); }; more.appendChild(b); }
            }).catch(function () { alertBox('err', t('system_admin_inquiries.err_generic')); });
    }
    function rowHtml(i) {
        return '<td class="dm-wrap-anywhere"><b>' + esc(i.subject) + '</b></td>' +
            '<td class="dm-wrap-anywhere">' + esc(i.email) + '<br><span style="color:#7f8c8d;font-size:12px">' + esc(i.phone) + '</span></td>' +
            '<td>' + statusBadge(i) + '</td>' +
            '<td style="white-space:nowrap">' + esc(fmtDate(i.created_at)) + '</td>' +
            '<td><button class="btn btn-ghost btn-sm" data-view="' + esc(i.id) + '">' + esc(t('system_admin_inquiries.view')) + '</button></td>';
    }
    function render(list) {
        document.getElementById('dmInqBody').innerHTML = '';
        var cards = document.getElementById('dmInqCards'); cards.innerHTML = '';
        list.forEach(function (i) {
            var tr = document.createElement('tr'); tr.innerHTML = rowHtml(i); document.getElementById('dmInqBody').appendChild(tr);
            var c = document.createElement('div'); c.className = 'card inq-card';
            c.innerHTML = '<div class="oc-row"><b class="dm-wrap-anywhere">' + esc(i.subject) + '</b>' + statusBadge(i) + '</div>' +
                '<div class="oc-row"><span>' + esc(t('system_admin_inquiries.col_customer')) + '</span><span>' + esc(i.email) + '</span></div>' +
                '<div class="oc-row"><span>' + esc(t('system_admin_inquiries.col_date')) + '</span><span>' + esc(fmtDate(i.created_at)) + '</span></div>' +
                '<button class="btn btn-primary btn-sm" style="margin-top:10px" data-view="' + esc(i.id) + '">' + esc(t('system_admin_inquiries.view')) + '</button>';
            cards.appendChild(c);
        });
    }
    function appendRows(list) { var tb = document.getElementById('dmInqBody'); list.forEach(function (i) { var tr = document.createElement('tr'); tr.innerHTML = rowHtml(i); tb.appendChild(tr); }); }

    function openDetail(id) {
        document.getElementById('dmInqDetail').innerHTML = '<div class="empty"><i class="fa-solid fa-spinner fa-spin"></i></div>';
        document.getElementById('dmInqOverlay').classList.add('open');
        fetch('/api/system-admin/inquiries/' + encodeURIComponent(id), { headers: auth() }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.inquiry) { document.getElementById('dmInqDetail').innerHTML = '<div class="alert alert-err">' + esc(t('system_admin_inquiries.err_generic')) + '</div>'; return; }
            renderDetail(d.inquiry);
        }).catch(function () { document.getElementById('dmInqDetail').innerHTML = '<div class="alert alert-err">' + esc(t('system_admin_inquiries.err_generic')) + '</div>'; });
    }
    function renderDetail(i) {
        var review = i.review || {};
        var published = i.is_published && review.is_published;
        document.getElementById('dmInqDetail').innerHTML =
            '<div id="dmDetailAlert"></div>' +
            '<div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px"><b>' + esc(i.subject) + '</b>' + statusBadge(i) + '</div>' +
            '<div class="detail-row"><b>' + esc(t('system_admin_inquiries.detail_email')) + '</b><span class="dm-wrap-anywhere">' + esc(i.email) + '</span></div>' +
            '<div class="detail-row"><b>' + esc(t('system_admin_inquiries.detail_phone')) + '</b><span>' + esc(i.phone) + '</span></div>' +
            '<div class="detail-row"><b>' + esc(t('system_admin_inquiries.detail_date')) + '</b><span>' + esc(fmtDate(i.created_at)) + '</span></div>' +
            '<div style="margin:14px 0 6px"><b>' + esc(t('system_admin_inquiries.detail_message')) + '</b></div>' +
            '<div class="msg-box">' + esc(i.message) + '</div>' +
            (published ? '<div class="alert alert-info" style="margin-top:14px">' + esc(t('system_admin_inquiries.published_hint')) + '</div>' : '') +
            '<h4 style="margin:16px 0 8px">' + esc(t('system_admin_inquiries.publish_title')) + '</h4>' +
            '<p style="color:#64748b;font-size:13px;margin-bottom:10px">' + esc(t('system_admin_inquiries.publish_subtitle')) + '</p>' +
            '<div class="field"><label>' + esc(t('system_admin_inquiries.publish_display_name')) + '</label><input id="dmPubName" value="' + esc(review.display_name || '') + '"></div>' +
            '<div class="field"><label>' + esc(t('system_admin_inquiries.publish_rating')) + '</label><select id="dmPubRating"><option value="">-</option><option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option></select></div>' +
            '<div style="display:flex;gap:10px;flex-wrap:wrap">' +
                '<button class="btn btn-ghost" id="dmMarkReviewed">' + esc(t('system_admin_inquiries.mark_reviewed')) + '</button>' +
                (published
                    ? '<button class="btn btn-danger" id="dmUnpublish">' + esc(t('system_admin_inquiries.unpublish')) + '</button>'
                    : '<button class="btn btn-ok" id="dmPublish">' + esc(t('system_admin_inquiries.publish')) + '</button>') +
            '</div>';
        var sel = document.getElementById('dmPubRating'); if (review.rating && sel) sel.value = String(review.rating);
        document.getElementById('dmMarkReviewed').onclick = function () { act('/api/system-admin/inquiries/' + encodeURIComponent(i.id) + '/reviewed', 'PUT', {}, 'reviewed'); };
        var pb = document.getElementById('dmPublish');
        if (pb) pb.onclick = function () { act('/api/system-admin/inquiries/' + encodeURIComponent(i.id) + '/publish', 'POST', { display_name: document.getElementById('dmPubName').value, rating: document.getElementById('dmPubRating').value }, 'published'); };
        var ub = document.getElementById('dmUnpublish');
        if (ub) ub.onclick = function () { act('/api/system-admin/inquiries/' + encodeURIComponent(i.id) + '/unpublish', 'POST', {}, 'unpublished'); };
    }
    function act(url, method, body, kind) {
        var box = document.getElementById('dmDetailAlert');
        fetch(url, { method: method, headers: Object.assign({ 'Content-Type': 'application/json' }, auth()), body: JSON.stringify(body || {}) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok) { box.innerHTML = '<div class="alert alert-err">' + esc((res.d && res.d.error) || t('system_admin_inquiries.err_generic')) + '</div>'; return; }
                var msg = kind === 'published' ? t('system_admin_inquiries.msg_published') : kind === 'unpublished' ? t('system_admin_inquiries.msg_unpublished') : t('system_admin_inquiries.msg_reviewed');
                box.innerHTML = '<div class="alert alert-ok">' + esc(msg) + '</div>';
                load(true);
            }).catch(function () { box.innerHTML = '<div class="alert alert-err">' + esc(t('system_admin_inquiries.err_generic')) + '</div>'; });
    }

    document.addEventListener('click', function (e) {
        var v = e.target.closest('[data-view]'); if (v) openDetail(v.getAttribute('data-view'));
        var c = e.target.closest('[data-dm-close]'); if (c) document.getElementById(c.getAttribute('data-dm-close')).classList.remove('open');
        if (e.target.classList.contains('overlay')) e.target.classList.remove('open');
    });
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('dmUserName').textContent = user.full_name || user.email || '-';
        document.getElementById('dmAvatar').textContent = (user.full_name || user.email || 'A').trim().charAt(0).toUpperCase();
        document.getElementById('dmMenuBtn').onclick = function () { document.getElementById('dmSidebar').classList.toggle('open'); };
        document.getElementById('dmLogout').onclick = function () { localStorage.clear(); window.location.href = '/login?role=system_admin'; };
        document.getElementById('dmRefresh').onclick = function () { load(true); };
        var s = document.getElementById('dmStatusFilter'); s.value = state.status;
        s.onchange = function () { state.status = s.value; load(true); };
        var search = document.getElementById('dmSearch'); var h;
        search.oninput = function () { clearTimeout(h); h = setTimeout(function () { state.search = search.value.trim(); load(true); }, 400); };
        load(true);
        if (window.DM) DM.onChange(function () { load(true); });
    });
})();
</script>
</body>
</html>
@endverbatim
