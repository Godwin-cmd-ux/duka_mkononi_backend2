@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_orders.page_title">Oda - DukaMkononi Muuzaji</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #2c3e50; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 270px; background: #fff; border-right: 1px solid #ecf0f1; position: fixed; inset: 0 auto 0 0; display: flex; flex-direction: column; z-index: 100; transition: transform .3s ease; box-shadow: 2px 0 12px rgba(0,0,0,.04); }
        .sidebar-header { padding: 22px 20px; border-bottom: 1px solid #ecf0f1; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg,#2ecc71,#27ae60); color: #fff; display: grid; place-items: center; font-weight: 800; }
        .logo-text h2 { font-size: 17px; font-weight: 800; }
        .logo-text p { font-size: 12px; color: #7f8c8d; }
        .nav-items { flex: 1; padding: 14px 12px; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 13px; padding: 13px 16px; margin-bottom: 6px; border-radius: 12px; color: #5d6d7e; font-weight: 500; text-decoration: none; }
        .nav-item i { width: 22px; font-size: 18px; text-align: center; }
        .nav-item:hover { background: #f1f5f9; }
        .nav-item.active { background: #e8f8f0; color: #2ecc71; font-weight: 600; }
        .sidebar-footer { padding: 16px; border-top: 1px solid #ecf0f1; }
        .user-chip { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; background: #e8f8f0; color: #2ecc71; display: grid; place-items: center; font-weight: 700; }
        .user-meta { min-width: 0; }
        .user-meta b { display: block; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }
        .user-meta span { font-size: 12px; color: #7f8c8d; }
        .logout-btn { width: 100%; padding: 11px; border-radius: 11px; border: 1px solid #ecf0f1; background: #fff; color: #e74c3c; font-weight: 600; cursor: pointer; }
        .main { flex: 1; margin-left: 270px; min-width: 0; }
        .topbar { background: #fff; border-bottom: 1px solid #ecf0f1; padding: 14px 20px; display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 50; }
        .hamburger { display: none; width: 42px; height: 42px; border-radius: 10px; border: 1px solid #ecf0f1; background: #fff; cursor: pointer; }
        .topbar h1 { font-size: 19px; font-weight: 800; }
        .content { padding: 22px 20px 60px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .toolbar select, .toolbar input { padding: 11px 13px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; min-height: 44px; }
        .toolbar input { flex: 1 1 200px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 16px; border-radius: 10px; border: 1px solid transparent; font-weight: 600; font-size: 14px; cursor: pointer; min-height: 44px; }
        .btn-primary { background: #2ecc71; color: #fff; }
        .btn-ghost { background: #fff; border-color: #e2e8f0; color: #2c3e50; }
        .btn-sm { padding: 8px 12px; min-height: 36px; font-size: 13px; }
        .card { background: #fff; border: 1px solid #ecf0f1; border-radius: 14px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .orders-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .orders-table th, .orders-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .orders-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #7f8c8d; }
        .orders-table tr:hover td { background: #fafdfb; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .b-warn { background: #fffbeb; color: #b45309; } .b-info { background: #eff6ff; color: #1d4ed8; } .b-ok { background: #ecfdf5; color: #047857; } .b-danger { background: #fef2f2; color: #b91c1c; } .b-muted { background: #f1f5f9; color: #64748b; }
        .order-cards { display: none; flex-direction: column; gap: 12px; }
        .order-card { padding: 16px; }
        .order-card .oc-head { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
        .order-card .oc-row { display: flex; justify-content: space-between; gap: 8px; padding: 4px 0; font-size: 13.5px; flex-wrap: wrap; }
        .empty { text-align: center; padding: 48px 20px; color: #64748b; }
        .empty i { font-size: 40px; color: #cbd5e1; margin-bottom: 12px; display: block; }
        .overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: none; align-items: center; justify-content: center; padding: 14px; z-index: 900; }
        .overlay.open { display: flex; }
        .modal { background: #fff; border-radius: 16px; width: 100%; max-width: 640px; max-height: 92vh; overflow-y: auto; }
        .modal-head { padding: 16px 18px; border-bottom: 1px solid #ecf0f1; display: flex; align-items: center; justify-content: space-between; gap: 10px; position: sticky; top: 0; background: #fff; }
        .modal-body { padding: 18px; }
        .modal-foot { padding: 14px 18px; border-top: 1px solid #ecf0f1; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; position: sticky; bottom: 0; background: #fff; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; padding: 11px 13px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14.5px; min-height: 44px; font-family: inherit; }
        .field textarea { min-height: 90px; resize: vertical; }
        .line { display: flex; justify-content: space-between; gap: 10px; padding: 7px 0; border-bottom: 1px dashed #eef2f7; font-size: 14px; flex-wrap: wrap; }
        .alert { padding: 11px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
        .alert-ok { background: #ecfdf5; color: #065f46; } .alert-err { background: #fef2f2; color: #991b1b; } .alert-info { background: #eff6ff; color: #1e40af; }
        .timeline { list-style: none; border-left: 2px solid #e2e8f0; padding-left: 14px; margin-top: 10px; }
        .timeline li { position: relative; padding-bottom: 10px; font-size: 13px; }
        .timeline li::before { content: ''; position: absolute; left: -20px; top: 4px; width: 8px; height: 8px; border-radius: 50%; background: #2ecc71; }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .hamburger { display: inline-flex; align-items: center; justify-content: center; }
            .orders-table { display: none; }
            .order-cards { display: flex; }
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
            <div class="logo-area">
                <div class="logo-icon">DM</div>
                <div class="logo-text">
                    <h2 data-i18n="muuzaji_orders.brand">DukaMkononi</h2>
                    <p data-i18n="muuzaji_orders.portal">Muuzaji</p>
                </div>
            </div>
        </div>
        <nav class="nav-items">
            <a class="nav-item active" href="/muuzaji/orders"><i class="fa-solid fa-receipt"></i><span data-i18n="muuzaji_orders.nav_orders">Orders</span></a>
            <a class="nav-item" href="/muuzaji/huduma-nyingine"><i class="fa-solid fa-puzzle-piece"></i><span data-i18n="muuzaji_huduma.nav_other">Other Services</span></a>
            <a class="nav-item" href="/muuzaji/uza"><i class="fa-solid fa-cash-register"></i><span data-i18n="muuzaji_orders.nav_sell">Sell</span></a>
            <a class="nav-item" href="/muuzaji/mauzo"><i class="fa-solid fa-chart-line"></i><span data-i18n="muuzaji_orders.nav_sales">Sales</span></a>
            <a class="nav-item" href="/muuzaji/matumizi"><i class="fa-solid fa-money-bill-wave"></i><span data-i18n="muuzaji_orders.nav_expenses">Expenses</span></a>
            <a class="nav-item" href="/muuzaji/profaili"><i class="fa-solid fa-user"></i><span data-i18n="muuzaji_orders.nav_profile">Profile</span></a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="user-avatar" id="dmAvatar">M</div>
                <div class="user-meta">
                    <b id="dmUserName">-</b>
                    <span data-i18n="muuzaji_orders.user_role">Seller</span>
                </div>
            </div>
            <button class="logout-btn" id="dmLogout" data-i18n="muuzaji_orders.logout">Logout</button>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <button class="hamburger" id="dmMenuBtn" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
            <h1 data-i18n="muuzaji_orders.title">Customer Orders</h1>
        </div>
        <div class="content">
            <div id="dmAlert"></div>
            <div class="toolbar">
                <select id="dmStatusFilter">
                    <option value="" data-i18n="muuzaji_orders.filter_all">All statuses</option>
                    <option value="pending" data-i18n="muuzaji_orders.status_pending">Pending</option>
                    <option value="confirmed" data-i18n="muuzaji_orders.status_confirmed">Confirmed</option>
                    <option value="processing" data-i18n="muuzaji_orders.status_processing">Processing</option>
                    <option value="ready" data-i18n="muuzaji_orders.status_ready">Ready</option>
                    <option value="out_for_delivery" data-i18n="muuzaji_orders.status_out_for_delivery">Out for delivery</option>
                    <option value="delivered" data-i18n="muuzaji_orders.status_delivered">Delivered</option>
                    <option value="completed" data-i18n="muuzaji_orders.status_completed">Completed</option>
                    <option value="cancelled" data-i18n="muuzaji_orders.status_cancelled">Cancelled</option>
                </select>
                <input type="search" id="dmSearch" placeholder="Search reference, name or phone" data-i18n="muuzaji_orders.search_placeholder" data-i18n-attr="placeholder">
                <button class="btn btn-ghost" id="dmRefresh"><i class="fa-solid fa-rotate-right"></i><span data-i18n="muuzaji_orders.refresh">Refresh</span></button>
            </div>

            <div class="card" id="dmTableCard">
                <div class="dm-table-wrap" style="overflow-x:auto">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th data-i18n="muuzaji_orders.col_reference">Reference</th>
                                <th data-i18n="muuzaji_orders.col_customer">Customer</th>
                                <th data-i18n="muuzaji_orders.col_total">Total</th>
                                <th data-i18n="muuzaji_orders.col_status">Status</th>
                                <th data-i18n="muuzaji_orders.col_date">Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="dmOrdersBody"></tbody>
                    </table>
                </div>
                <div class="order-cards" id="dmOrderCards"></div>
            </div>
            <div id="dmEmpty" style="display:none" class="empty">
                <i class="fa-solid fa-inbox"></i>
                <h3 data-i18n="muuzaji_orders.empty">No orders yet</h3>
                <p data-i18n="muuzaji_orders.empty_hint">New customer orders from the website and app will appear here.</p>
            </div>
            <div id="dmMore" style="text-align:center;margin-top:18px"></div>
        </div>
    </div>
</div>

<div class="overlay" id="dmOrderOverlay">
    <div class="modal">
        <div class="modal-head">
            <h3 id="dmOrderTitle" data-i18n="muuzaji_orders.detail_title">Order details</h3>
            <button class="btn btn-ghost btn-sm" data-dm-close="dmOrderOverlay" data-i18n="muuzaji_orders.close">Close</button>
        </div>
        <div class="modal-body" id="dmOrderDetail"></div>
    </div>
</div>
@endverbatim
@verbatim
<script>
(function () {
    'use strict';
    var API = '';
    var t = function (k, p) { return window.DM ? DM.t(k, p) : k; };
    var token = localStorage.getItem('userToken');
    var user = {};
    try { user = JSON.parse(localStorage.getItem('userData') || '{}'); } catch (e) { user = {}; }
    var role = (user.role || '').toLowerCase();
    if (!token) { window.location.href = '/login?role=muuzaji'; return; }
    if (['seller', 'muuzaji', 'admin', 'msimamizi', 'system_admin'].indexOf(role) === -1) { window.location.href = '/home'; return; }

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
    function money(n) { try { return 'TZS ' + Number(n).toLocaleString('en-US'); } catch (e) { return 'TZS ' + n; } }
    function statusLabel(s) { var k = 'muuzaji_orders.status_' + s; var v = t(k); return v === k ? s : v; }
    function statusClass(s) { return ({ pending: 'b-warn', confirmed: 'b-info', processing: 'b-info', ready: 'b-info', out_for_delivery: 'b-info', delivered: 'b-ok', completed: 'b-ok', cancelled: 'b-danger' })[s] || 'b-muted'; }
    function fmtDate(s) { if (!s) return '-'; var d = new Date(s); return isNaN(d) ? String(s) : d.toLocaleString((window.DM && DM.locale()) || 'sw'); }
    function auth() { return { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }; }
    function alertBox(cls, msg) { document.getElementById('dmAlert').innerHTML = msg ? '<div class="alert alert-' + cls + '">' + esc(msg) + '</div>' : ''; }

    var state = { page: 1, lastPage: 1, status: '', search: '' };

    function load(reset) {
        if (reset) state.page = 1;
        var q = '/api/seller/orders?page=' + state.page + (state.status ? '&status=' + encodeURIComponent(state.status) : '') + (state.search ? '&search=' + encodeURIComponent(state.search) : '');
        fetch(API + q, { headers: auth() }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok) { alertBox('err', (res.d && res.d.error) || t('muuzaji_orders.err_generic')); return; }
                var data = (res.d && res.d.data) || [];
                state.lastPage = (res.d && res.d.meta && res.d.meta.last_page) || 1;
                if (reset) render(data); else appendRows(data);
                document.getElementById('dmEmpty').style.display = (data.length === 0 && state.page === 1) ? 'block' : 'none';
                document.getElementById('dmTableCard').style.display = (data.length === 0 && state.page === 1) ? 'none' : 'block';
                var more = document.getElementById('dmMore');
                more.innerHTML = '';
                if (state.page < state.lastPage) {
                    var b = document.createElement('button'); b.className = 'btn btn-ghost'; b.textContent = t('muuzaji_orders.load_more');
                    b.onclick = function () { state.page++; load(false); }; more.appendChild(b);
                }
            }).catch(function () { alertBox('err', t('muuzaji_orders.err_generic')); });
    }

    function rowHtml(o) {
        return '<td class="dm-wrap-anywhere"><b>' + esc(o.order_reference) + '</b></td>' +
            '<td class="dm-wrap-anywhere">' + esc(o.customer_name || '-') + '<br><span style="color:#7f8c8d;font-size:12px">' + esc(o.customer_phone || '') + '</span></td>' +
            '<td>' + money(o.total_amount) + '</td>' +
            '<td><span class="badge ' + statusClass(o.status) + '">' + esc(statusLabel(o.status)) + '</span></td>' +
            '<td style="white-space:nowrap">' + esc(fmtDate(o.placed_at)) + '</td>' +
            '<td><button class="btn btn-ghost btn-sm" data-view="' + esc(o.id) + '">' + esc(t('muuzaji_orders.view')) + '</button></td>';
    }
    function render(list) {
        document.getElementById('dmOrdersBody').innerHTML = '';
        var cards = document.getElementById('dmOrderCards');
        cards.innerHTML = '';
        list.forEach(function (o) {
            var tr = document.createElement('tr'); tr.innerHTML = rowHtml(o); document.getElementById('dmOrdersBody').appendChild(tr);
            var c = document.createElement('div'); c.className = 'card order-card';
            c.innerHTML = '<div class="oc-head"><b class="dm-wrap-anywhere">' + esc(o.order_reference) + '</b><span class="badge ' + statusClass(o.status) + '">' + esc(statusLabel(o.status)) + '</span></div>' +
                '<div class="oc-row"><span>' + esc(t('muuzaji_orders.customer')) + '</span><b>' + esc(o.customer_name || '-') + '</b></div>' +
                '<div class="oc-row"><span>' + esc(t('muuzaji_orders.col_total')) + '</span><b>' + money(o.total_amount) + '</b></div>' +
                '<div class="oc-row"><span>' + esc(t('muuzaji_orders.col_date')) + '</span><span>' + esc(fmtDate(o.placed_at)) + '</span></div>' +
                '<button class="btn btn-primary btn-sm" style="margin-top:10px" data-view="' + esc(o.id) + '">' + esc(t('muuzaji_orders.view')) + '</button>';
            cards.appendChild(c);
        });
    }
    function appendRows(list) {
        var tb = document.getElementById('dmOrdersBody');
        list.forEach(function (o) { var tr = document.createElement('tr'); tr.innerHTML = rowHtml(o); tb.appendChild(tr); });
    }

    var STATUSES = ['pending', 'confirmed', 'processing', 'ready', 'out_for_delivery', 'delivered', 'completed', 'cancelled'];
    function openDetail(id) {
        document.getElementById('dmOrderDetail').innerHTML = '<div class="empty"><i class="fa-solid fa-spinner fa-spin"></i></div>';
        document.getElementById('dmOrderOverlay').classList.add('open');
        fetch(API + '/api/seller/orders/' + encodeURIComponent(id), { headers: auth() }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.order) { document.getElementById('dmOrderDetail').innerHTML = '<div class="alert alert-err">' + esc(t('muuzaji_orders.err_generic')) + '</div>'; return; }
            renderDetail(d);
        }).catch(function () { document.getElementById('dmOrderDetail').innerHTML = '<div class="alert alert-err">' + esc(t('muuzaji_orders.err_generic')) + '</div>'; });
    }
    function renderDetail(d) {
        var o = d.order, history = d.history || [];
        var items = (o.items || []).map(function (it) {
            return '<div class="line"><span class="dm-wrap-anywhere">' + esc(it.product_name) + ' &times; ' + it.quantity + '</span><b>' + money(it.line_total) + '</b></div>';
        }).join('');
        var opts = STATUSES.map(function (s) { return '<option value="' + s + '"' + (s === o.status ? ' selected' : '') + '>' + esc(statusLabel(s)) + '</option>'; }).join('');
        var hist = history.map(function (h) {
            return '<li><b>' + esc(statusLabel(h.status)) + '</b>' + (h.note ? '<div style="color:#64748b">' + esc(h.note) + '</div>' : '') + (h.internal_note ? '<div style="color:#b45309">' + esc(t('muuzaji_orders.internal_note')) + ': ' + esc(h.internal_note) + '</div>' : '') + '<time style="color:#94a3b8;font-size:12px">' + esc(fmtDate(h.created_at)) + '</time></li>';
        }).join('');
        document.getElementById('dmOrderDetail').innerHTML =
            '<div id="dmDetailAlert"></div>' +
            '<div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px"><div><b class="dm-wrap-anywhere">' + esc(o.order_reference) + '</b><div style="color:#7f8c8d;font-size:13px">' + esc(fmtDate(o.placed_at)) + '</div></div><span class="badge ' + statusClass(o.status) + '">' + esc(statusLabel(o.status)) + '</span></div>' +
            '<div class="field"><label>' + esc(t('muuzaji_orders.customer')) + '</label><div>' + esc(o.customer_name || '-') + ' &middot; ' + esc(o.customer_phone || '') + (o.customer_email ? ' &middot; ' + esc(o.customer_email) : '') + '</div></div>' +
            (o.customer_note ? '<div class="alert alert-info">' + esc(o.customer_note) + '</div>' : '') +
            '<h4 style="margin-bottom:6px">' + esc(t('muuzaji_orders.products')) + '</h4>' + items +
            '<div class="line" style="border:none;font-weight:800;font-size:16px;margin-top:6px"><span>' + esc(t('muuzaji_orders.total')) + '</span><span>' + money(o.total_amount) + '</span></div>' +
            '<hr style="border:none;border-top:1px solid #eef2f7;margin:16px 0">' +
            '<div class="field"><label>' + esc(t('muuzaji_orders.update_status')) + '</label><select id="dmNewStatus">' + opts + '</select></div>' +
            '<div class="field"><label>' + esc(t('muuzaji_orders.note_label')) + '</label><textarea id="dmStatusNote" placeholder="' + esc(t('muuzaji_orders.note_placeholder')) + '"></textarea></div>' +
            '<div class="field"><label>' + esc(t('muuzaji_orders.internal_note_label')) + '</label><textarea id="dmInternalNote" placeholder="' + esc(t('muuzaji_orders.internal_note_placeholder')) + '"></textarea></div>' +
            '<div style="display:flex;gap:10px;flex-wrap:wrap"><button class="btn btn-primary" id="dmSaveStatus">' + esc(t('muuzaji_orders.save')) + '</button><button class="btn btn-ghost" id="dmSaveNote">' + esc(t('muuzaji_orders.add_note')) + '</button></div>' +
            (hist ? '<h4 style="margin:18px 0 4px">' + esc(t('muuzaji_orders.history')) + '</h4><ul class="timeline">' + hist + '</ul>' : '');
        document.getElementById('dmSaveStatus').onclick = function () { save(o.id, 'status'); };
        document.getElementById('dmSaveNote').onclick = function () { save(o.id, 'note'); };
    }
    function save(id, kind) {
        var box = document.getElementById('dmDetailAlert');
        var payload = {};
        if (kind === 'status') {
            payload.status = document.getElementById('dmNewStatus').value;
            payload.note = document.getElementById('dmStatusNote').value;
            payload.internal_note = document.getElementById('dmInternalNote').value;
        } else {
            payload.note = document.getElementById('dmStatusNote').value;
            payload.internal_note = document.getElementById('dmInternalNote').value;
        }
        var url = kind === 'status' ? '/api/seller/orders/' + encodeURIComponent(id) + '/status' : '/api/seller/orders/' + encodeURIComponent(id) + '/notes';
        var method = kind === 'status' ? 'PUT' : 'POST';
        fetch(API + url, { method: method, headers: Object.assign({ 'Content-Type': 'application/json' }, auth()), body: JSON.stringify(payload) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok) { box.innerHTML = '<div class="alert alert-err">' + esc((res.d && res.d.error) || t('muuzaji_orders.err_generic')) + '</div>'; return; }
                box.innerHTML = '<div class="alert alert-ok">' + esc(kind === 'status' ? t('muuzaji_orders.msg_status') : t('muuzaji_orders.msg_note')) + '</div>';
                load(true);
            }).catch(function () { box.innerHTML = '<div class="alert alert-err">' + esc(t('muuzaji_orders.err_generic')) + '</div>'; });
    }

    document.addEventListener('click', function (e) {
        var v = e.target.closest('[data-view]');
        if (v) openDetail(v.getAttribute('data-view'));
        var c = e.target.closest('[data-dm-close]');
        if (c) document.getElementById(c.getAttribute('data-dm-close')).classList.remove('open');
        if (e.target.classList.contains('overlay')) e.target.classList.remove('open');
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('dmUserName').textContent = user.full_name || user.email || '-';
        document.getElementById('dmAvatar').textContent = (user.full_name || user.email || 'M').trim().charAt(0).toUpperCase();
        document.getElementById('dmMenuBtn').onclick = function () { document.getElementById('dmSidebar').classList.toggle('open'); };
        document.getElementById('dmLogout').onclick = function () { localStorage.clear(); window.location.href = '/login?role=muuzaji'; };
        document.getElementById('dmRefresh').onclick = function () { load(true); };
        var s = document.getElementById('dmStatusFilter'); s.value = state.status;
        s.onchange = function () { state.status = s.value; load(true); };
        var search = document.getElementById('dmSearch');
        var h; search.oninput = function () { clearTimeout(h); h = setTimeout(function () { state.search = search.value.trim(); load(true); }, 400); };
        load(true);
        if (window.DM) DM.onChange(function () { load(true); });
    });
})();
</script>
</body>
</html>
@endverbatim
