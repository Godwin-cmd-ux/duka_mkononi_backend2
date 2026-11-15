@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_friji.page_title">Friji - Dukamkononi Muuzaji</title>
@endverbatim
    <link rel="stylesheet" href="/css/themes.css?v={{ filemtime(public_path('css/themes.css')) }}">
@verbatim
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f8; color: #24323f; }
        .muuzaji-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 270px; background: #fff; border-right: 1px solid #e8edf1;
            display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0;
            z-index: 100; transition: transform .3s ease; box-shadow: 2px 0 14px rgba(0,0,0,.04);
        }
        .sidebar-header { padding: 26px 22px; border-bottom: 1px solid #eef2f5; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 44px; height: 44px; border-radius: 13px; display: flex; align-items: center;
            justify-content: center; color: #fff; font-size: 20px;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        .logo-text h2 { font-size: 17px; font-weight: 800; }
        .logo-text p { font-size: 11px; color: #8291a0; }
        .nav-items { flex: 1; padding: 14px 12px; overflow-y: auto; }
        .nav-item {
            display: flex; align-items: center; gap: 12px; padding: 12px 14px; margin-bottom: 4px;
            border-radius: 12px; color: #5d6d7e; font-weight: 600; font-size: 14px; text-decoration: none;
        }
        .nav-item:hover { background: #edf9f2; color: #27ae60; }
        .nav-item.active { background: #e8f8f0; color: #1f9d55; }
        .nav-icon { width: 24px; text-align: center; font-size: 16px; }
        .sidebar-footer { padding: 16px; border-top: 1px solid #eef2f5; }
        .user-info { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: #e8f8f0; color: #27ae60;
            display: flex; align-items: center; justify-content: center; font-weight: 800;
            overflow: hidden;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-name { font-weight: 700; font-size: 13px; }
        .user-role { font-size: 11px; color: #8291a0; }
        .logout-btn {
            display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-radius: 11px;
            color: #e74c3c; font-weight: 600; font-size: 13px; cursor: pointer; background: #fff5f4;
        }
        .main-content { margin-left: 270px; width: calc(100% - 270px); padding: 28px 30px 60px; }
        .mobile-menu-toggle {
            display: none; position: fixed; top: 14px; left: 14px; z-index: 101; background: #fff;
            border: none; border-radius: 12px; padding: 10px 12px; box-shadow: 0 4px 16px rgba(0,0,0,.08);
            color: #27ae60; cursor: pointer;
        }
        .page-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
        .page-title { font-size: 26px; font-weight: 800; letter-spacing: -.4px; }
        .page-sub { color: #7f8c8d; font-size: 13px; margin-top: 5px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 11px;
            padding: 11px 16px; font-weight: 700; font-size: 13px; cursor: pointer;
            font-family: inherit; transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn:active { transform: scale(.98); }
        .btn-primary { background: linear-gradient(135deg, #2ecc71, #27ae60); color: #fff; box-shadow: 0 8px 18px rgba(39,174,96,.22); }
        .btn-light { background: #fff; color: #334155; border: 1px solid #e3e9ee; }
        .btn-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-sm { padding: 7px 10px; font-size: 12px; border-radius: 9px; }
        .stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: #fff; border-radius: 16px; padding: 16px; border: 1px solid #edf1f4; }
        .stat-label { font-size: 11px; color: #8291a0; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
        .stat-value { font-size: 24px; font-weight: 800; margin-top: 6px; }
        .stat-value.green { color: #219150; }
        .stat-value.blue { color: #2563eb; }
        .stat-value.amber { color: #d97706; }
        .stat-value.red { color: #dc2626; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 220px; position: relative; }
        .search-box input {
            width: 100%; border: 1px solid #e3e9ee; background: #fff; border-radius: 12px;
            padding: 11px 14px 11px 38px; font-size: 13px; font-family: inherit; outline: none;
        }
        .search-box i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9aa7b4; }
        .fridge-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(255px, 1fr)); gap: 14px; }
        .fridge-card {
            background: #fff; border-radius: 18px; border: 1px solid #edf1f4; padding: 18px;
            display: flex; flex-direction: column; gap: 12px; transition: box-shadow .2s ease;
        }
        .fridge-card:hover { box-shadow: 0 10px 28px rgba(15,23,42,.07); }
        .fridge-top { display: flex; align-items: center; gap: 12px; }
        .fridge-icon {
            width: 46px; height: 46px; border-radius: 14px; background: #eef5ff; color: #2563eb;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .fridge-name { font-weight: 800; font-size: 15px; }
        .fridge-meta { font-size: 11px; color: #8291a0; margin-top: 3px; }
        .pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; border-radius: 99px; font-size: 10px; font-weight: 800; }
        .pill.ok { background: #e9f9f0; color: #15803d; }
        .pill.warn { background: #fff7ed; color: #c2410c; }
        .pill.muted { background: #f1f5f9; color: #64748b; }
        .fridge-numbers { display: flex; justify-content: space-between; padding: 10px 0; border-top: 1px dashed #edf1f4; border-bottom: 1px dashed #edf1f4; }
        .fridge-num { text-align: center; flex: 1; }
        .fridge-num strong { display: block; font-size: 18px; }
        .fridge-num span { font-size: 10px; color: #8291a0; }
        .fridge-actions { display: flex; gap: 8px; }
        .fridge-actions .btn { flex: 1; justify-content: center; }
        .section-title { font-size: 15px; font-weight: 800; margin: 26px 0 12px; display: flex; align-items: center; gap: 8px; }
        .panel { background: #fff; border-radius: 18px; border: 1px solid #edf1f4; padding: 18px; }
        .alert-group { margin-bottom: 12px; }
        .alert-title { font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 7px; }
        .alert-row {
            display: flex; justify-content: space-between; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: 11px; background: #fff8f1; margin-bottom: 6px;
            font-size: 12px; border: 1px solid #ffedd5;
        }
        .alert-row.red { background: #fef2f2; border-color: #fee2e2; }
        .alert-row.blue { background: #eff6ff; border-color: #dbeafe; }
        .muted { color: #94a3b8; font-size: 12px; }
        .empty { text-align: center; padding: 44px 20px; color: #94a3b8; }
        .empty i { font-size: 42px; margin-bottom: 12px; color: #cbd5e1; }
        .modal {
            display: none; position: fixed; inset: 0; z-index: 200; background: rgba(15,23,42,.45);
            align-items: center; justify-content: center; padding: 16px;
        }
        .modal.open { display: flex; }
        .modal-card {
            background: #fff; width: 100%; max-width: 640px; border-radius: 20px; max-height: 90vh;
            overflow-y: auto; box-shadow: 0 24px 70px rgba(15,23,42,.25);
        }
        .modal-head { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; border-bottom: 1px solid #eef2f5; position: sticky; top: 0; background: #fff; z-index: 2; }
        .modal-head h3 { font-size: 17px; font-weight: 800; }
        .modal-close { background: #f1f5f9; border: none; width: 34px; height: 34px; border-radius: 10px; cursor: pointer; color: #64748b; }
        .modal-body { padding: 18px 20px 22px; }
        .modal-foot { display: flex; gap: 10px; justify-content: flex-end; padding: 14px 20px; border-top: 1px solid #eef2f5; position: sticky; bottom: 0; background: #fff; }
        .field { margin-bottom: 13px; }
        .field label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 5px; }
        .field input, .field select {
            width: 100%; border: 1px solid #dbe3ea; border-radius: 11px; padding: 10px 12px;
            font-size: 13px; font-family: inherit; outline: none; background: #fff;
        }
        .field input:focus, .field select:focus { border-color: #2ecc71; box-shadow: 0 0 0 3px rgba(46,204,113,.12); }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { text-align: left; color: #8291a0; font-size: 10px; letter-spacing: .5px; text-transform: uppercase; padding: 8px 8px; border-bottom: 1px solid #edf1f4; white-space: nowrap; }
        td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        td.actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .movement-row { display: flex; justify-content: space-between; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
        .movement-in { color: #15803d; font-weight: 800; }
        .movement-out { color: #c2410c; font-weight: 800; }
        .loading { text-align: center; padding: 40px; color: #94a3b8; display: flex; align-items: center; justify-content: center; min-height: 80px; }
        .btn { min-width: 0; }
        .btn .loading-spinner.dm-sc, .btn .dm-sc { width: 18px; height: 18px; display: inline-block; vertical-align: middle; }
        @media (max-width: 640px) {
            .btn { width: 100%; justify-content: center; }
            .fridge-actions { flex-wrap: wrap; }
            .fridge-actions .btn { flex: 1 1 45%; min-width: 120px; }
            .page-head { flex-direction: column; align-items: stretch; }
            .page-head .btn { width: 100%; }
        }
        @media (max-width: 1050px) { .stats { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 70px 16px 50px; }
            .mobile-menu-toggle { display: block; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            .page-title { font-size: 22px; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])
@include('partials.toast')
@verbatim
<button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>

<div class="muuzaji-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-area">
                <div class="logo-icon"><i class="fa-solid fa-store"></i></div>
                <div class="logo-text">
                    <h2 data-i18n="muuzaji_sidebar.logo_brand">DukaMkononi</h2>
                    <p data-i18n="muuzaji_sidebar.logo_tagline">Muuzaji Portal</p>
                </div>
            </div>
        </div>
        <div class="nav-items">
            <a href="profaili" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-user"></i></span><span data-i18n="muuzaji_sidebar.nav_profile">Profaili</span></a>
            <a href="mauzo" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-receipt"></i></span><span data-i18n="muuzaji_sidebar.nav_sales">Mauzo</span></a>
            <a href="matumizi" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-wallet"></i></span><span data-i18n="muuzaji_sidebar.nav_expenses">Matumizi</span></a>
            <a href="uza" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-cart-shopping"></i></span><span data-i18n="muuzaji_sidebar.nav_sell">Uza</span></a>
            <a href="friji" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-snowflake"></i></span><span data-i18n="muuzaji_sidebar.nav_fridge">Friji</span></a>
            <a href="orders" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-truck-fast"></i></span><span data-i18n="muuzaji_sidebar.nav_orders">Oda</span></a>
            <a href="huduma-nyingine" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-layer-group"></i></span><span data-i18n="muuzaji_sidebar.nav_other">Huduma Nyingine</span></a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar" id="userAvatar">M</div>
                <div>
                    <div class="user-name" id="userName">Muuzaji</div>
                    <div class="user-role" data-i18n="muuzaji_sidebar.nav_seller">Muuzaji</div>
                </div>
            </div>
            <div class="logout-btn" id="logoutBtn"><i class="fa-solid fa-arrow-right-from-bracket"></i><span data-i18n="muuzaji_sidebar.btn_logout">Ondoka</span></div>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-head">
            <div>
                <div class="page-title" data-i18n="muuzaji_friji.title">Friji / Freezer</div>
                <div class="page-sub" data-i18n="muuzaji_friji.subtitle">Simamia stock iliyo ndani ya friji na nje yake.</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="btn btn-light" onclick="runWithButton(this, loadAll)"><i class="fa-solid fa-rotate"></i><span data-i18n="muuzaji_friji.refresh">Sasisha</span></button>
                <button class="btn btn-primary" onclick="openAddFridge()"><i class="fa-solid fa-plus"></i><span data-i18n="muuzaji_friji.add_fridge">Ongeza Friji</span></button>
            </div>
        </div>

        <div class="stats" id="stats"></div>

        <div class="toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="searchInput" type="search" placeholder="Tafuta friji..." data-i18n-placeholder="muuzaji_friji.search_placeholder" oninput="renderFridges()">
            </div>
        </div>

        <div class="fridge-grid" id="fridgeGrid"><div class="loading"><div class="loading-spinner dm-sc" style="width:44px;height:44px;"></div></div></div>

        <div class="section-title"><i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b"></i><span data-i18n="muuzaji_friji.alerts_title">Tahadhari za Stock</span></div>
        <div class="panel" id="alertsPanel"><div class="muted" data-i18n="muuzaji_friji.no_alerts">Hakuna tahadhari kwa sasa.</div></div>

        <div class="section-title"><i class="fa-solid fa-clock-rotate-left" style="color:#64748b"></i><span data-i18n="muuzaji_friji.recent_activity">Shughuli za Hivi Karibuni</span></div>
        <div class="panel" id="activityPanel"><div class="muted" data-i18n="muuzaji_friji.no_activity">Hakuna shughuli bado.</div></div>
    </main>
</div>

<!-- Add / rename fridge -->
<div class="modal" id="fridgeModal">
    <div class="modal-card" style="max-width:460px;">
        <div class="modal-head"><h3 id="fridgeModalTitle" data-i18n="muuzaji_friji.add_fridge">Ongeza Friji</h3><button class="modal-close" onclick="closeModal('fridgeModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <div class="field">
                <label data-i18n="muuzaji_friji.fridge_name">Jina la Friji</label>
                <input id="fridgeNameInput" maxlength="80" placeholder="Mf. Friji ya Nyama">
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-light" onclick="closeModal('fridgeModal')" data-i18n="muuzaji_friji.cancel">Ghairi</button>
            <button class="btn btn-primary" id="saveFridgeBtn" onclick="saveFridge()" data-i18n="muuzaji_friji.save">Hifadhi</button>
        </div>
    </div>
</div>

<!-- Fridge detail -->
<div class="modal" id="detailModal">
    <div class="modal-card" style="max-width:900px;">
        <div class="modal-head"><h3 id="detailTitle">Friji</h3><button class="modal-close" onclick="closeModal('detailModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body" id="detailBody"></div>
    </div>
</div>

<!-- Transfer -->
<div class="modal" id="transferModal">
    <div class="modal-card" style="max-width:520px;">
        <div class="modal-head"><h3 id="transferTitle" data-i18n="muuzaji_friji.transfer_title">Hamisha Stock</h3><button class="modal-close" onclick="closeModal('transferModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <input type="hidden" id="transferFridgeId">
            <input type="hidden" id="transferDirection" value="in">
            <div class="field" data-feature="scanner">
                <label>Scan barcode / QR</label>
                <input id="transferScanInput" type="text" autocomplete="off" placeholder="Changanua barcode kisha Enter" onkeydown="scanTransferBarcode(event)">
                <div class="muted" style="margin-top:5px">Scan huchagua bidhaa iliyopo tu; haiongezi stock mpya.</div>
            </div>
            <div class="field">
                <label data-i18n="muuzaji_friji.product">Bidhaa</label>
                <select id="transferProduct"></select>
            </div>
            <div class="field-row">
                <div class="field">
                    <label data-i18n="muuzaji_friji.quantity">Kiasi</label>
                    <input id="transferQuantity" type="number" min="0.001" step="0.001" value="1">
                </div>
                <div class="field">
                    <label data-i18n="muuzaji_friji.min_stock">Kiwango cha Chini</label>
                    <input id="transferMinStock" type="number" min="0" step="0.001" value="0">
                </div>
            </div>
            <div class="field" data-feature="scanner">
                <label>Njia ya kuweka kiasi</label>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="btn btn-light" id="transferModeManual" onclick="setTransferMode('manual')">Kiasi kwa mkono</button>
                    <button type="button" class="btn btn-light" id="transferModeScan" onclick="setTransferMode('scan')">Skani moja baada ya nyingine</button>
                </div>
                <button type="button" class="btn btn-primary" id="transferScanBtn" style="display:none;width:100%;margin-top:8px;" onclick="startTransferScan()">Anza Kuskani</button>
                <div class="muted" style="margin-top:5px">Kila kipande kilichoskaniwa kinahamishwa mara moja. Kiasi cha mkono kimezimwa kwenye hali ya skena.</div>
            </div>
            <div class="field">
                <label data-i18n="muuzaji_friji.notes">Maelezo (si lazima)</label>
                <input id="transferNotes" maxlength="240">
            </div>
            <div class="muted" id="transferHint"></div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-light" onclick="closeModal('transferModal')" data-i18n="muuzaji_friji.cancel">Ghairi</button>
            <button class="btn btn-primary" id="transferBtn" onclick="submitTransfer()" data-i18n="muuzaji_friji.confirm">Thibitisha</button>
        </div>
    </div>
</div>

<script>
    const API_BASE_URL = '';
    // `loaded` separates "no data yet" from "the server says there is nothing".
    // Locale/theme callbacks can fire before the first fetch resolves; rendering
    // then would paint zeros and "no fridges" over the loading state and make a
    // slow or failed load look like lost data.
    const state = { fridges: [], summary: {}, alerts: [], movements: [], current: null, editId: null, products: [], loaded: false, loadSeq: 0, error: null };

    function t(key, params) {
        const full = key.indexOf('muuzaji_friji.') === 0 ? key : 'muuzaji_friji.' + key;
        if (window.DM && typeof window.DM.t === 'function') {
            const hit = window.DM.t(full, params);
            if (hit !== full) return hit;
        }
        return key;
    }
    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function fmt(n) {
        const value = Number(n || 0);
        return value.toLocaleString(undefined, { maximumFractionDigits: 3 });
    }
    function toast(message, type) {
        if (typeof window.showToast === 'function') return window.showToast(message, type || 'info');
        alert(message);
    }
    function token() { return localStorage.getItem('userToken') || ''; }
    function currentUser() {
        try { return JSON.parse(localStorage.getItem('userData') || 'null'); } catch (e) { return null; }
    }
    function authHeaders(extra) {
        return Object.assign({ 'Authorization': 'Bearer ' + token(), 'Content-Type': 'application/json', 'ngrok-skip-browser-warning': 'true' }, extra || {});
    }
    async function api(path, options) {
        const response = await fetch(API_BASE_URL + path, Object.assign({ headers: authHeaders() }, options || {}));
        const text = await response.text();
        let data = {};
        try { data = text ? JSON.parse(text) : {}; } catch (e) { data = { error: text }; }
        if (!response.ok || data.success === false) {
            throw new Error(data.error || ('Server error ' + response.status));
        }
        return data;
    }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }
    function openModal(id) { document.getElementById(id).classList.add('open'); }

    function buttonLoading(btn, on) {
        if (!btn) return;
        if (on) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = btn.dataset.originalHtml + ' <span class="loading-spinner dm-sc" style="width:16px;height:16px;display:inline-block;vertical-align:middle;"></span>';
        } else {
            if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
            btn.disabled = false;
            delete btn.dataset.originalHtml;
        }
    }
    async function runWithButton(btn, fn) {
        buttonLoading(btn, true);
        try { await fn(); } finally { buttonLoading(btn, false); }
    }

    async function loadAll() {
        const seq = ++state.loadSeq;
        try {
            const data = await api('/api/fridges');
            // A newer refresh already started; its response is authoritative.
            if (seq !== state.loadSeq) return;
            state.fridges = data.fridges || [];
            state.summary = data.summary || {};
            state.alerts = data.alerts || [];
            state.movements = data.recent_movements || [];
            state.loaded = true;
            state.error = null;
            renderStats();
            renderFridges();
            renderAlerts();
            renderActivity();
        } catch (error) {
            if (seq !== state.loadSeq) return;
            state.error = error.message || t('load_failed');
            // Keep whatever was already on screen. A transient network or auth
            // failure must not replace real stock with zeros or an empty grid.
            if (state.loaded) {
                toast(state.error, 'error');
            } else {
                document.getElementById('fridgeGrid').innerHTML = '<div class="empty"><i class="fa-solid fa-triangle-exclamation"></i><div>' + esc(state.error) + '</div><div class="muted" style="margin-top:8px"><button class="btn btn-light btn-sm" onclick="runWithButton(this, loadAll)">' + esc(t('refresh')) + '</button></div></div>';
            }
        }
    }

    function renderStats() {
        if (!state.loaded) return;
        const s = state.summary || {};
        const counts = { business_low: 0, outside_low: 0, fridge_low: 0 };
        state.alerts.forEach(a => { if (counts[a.alert_type] != null) counts[a.alert_type]++; });
        document.getElementById('stats').innerHTML = `
            <div class="stat-card"><div class="stat-label">${t('business_stock')}</div><div class="stat-value">${fmt(s.business_units)}</div></div>
            <div class="stat-card"><div class="stat-label">${t('outside_stock')}</div><div class="stat-value blue">${fmt(s.outside_units)}</div></div>
            <div class="stat-card"><div class="stat-label">${t('inside_stock')}</div><div class="stat-value green">${fmt(s.inside_units)}</div></div>
            <div class="stat-card"><div class="stat-label">${t('fridges')}</div><div class="stat-value">${fmt(s.fridge_count)}</div></div>
            <div class="stat-card"><div class="stat-label">${t('alerts')}</div><div class="stat-value ${state.alerts.length ? 'amber' : 'green'}">${fmt(state.alerts.length)}</div></div>`;
    }

    function renderFridges() {
        if (!state.loaded) return;
        const search = (document.getElementById('searchInput').value || '').toLowerCase().trim();
        const fridges = state.fridges.filter(f => !search || String(f.name || '').toLowerCase().includes(search));
        const grid = document.getElementById('fridgeGrid');
        if (!fridges.length) {
            grid.innerHTML = `<div class="empty" style="grid-column:1/-1"><i class="fa-solid fa-snowflake"></i><div>${t('no_fridges')}</div><div class="muted" style="margin-top:6px">${t('no_fridges_hint')}</div></div>`;
            return;
        }
        grid.innerHTML = fridges.map(f => `
            <div class="fridge-card">
                <div class="fridge-top">
                    <div class="fridge-icon"><i class="fa-solid fa-snowflake"></i></div>
                    <div style="flex:1">
                        <div class="fridge-name">${esc(f.name)}</div>
                        <div class="fridge-meta">${f.status === 'archived' ? t('archived') : t('active')}</div>
                    </div>
                    <span class="pill ${f.status === 'archived' ? 'muted' : 'ok'}">${f.status === 'archived' ? t('archived') : t('active')}</span>
                </div>
                <div class="fridge-numbers">
                    <div class="fridge-num"><strong>${fmt(f.total_units)}</strong><span>${t('units')}</span></div>
                    <div class="fridge-num"><strong>${fmt(f.product_count)}</strong><span>${t('products')}</span></div>
                    <div class="fridge-num"><strong style="color:${f.low_stock_count ? '#d97706' : '#24323f'}">${fmt(f.low_stock_count)}</strong><span>${t('low')}</span></div>
                </div>
                <div class="fridge-actions">
                    <button class="btn btn-light btn-sm" onclick="openDetail('${esc(f.id)}', this)"><i class="fa-solid fa-eye"></i> ${t('manage')}</button>
                    <button class="btn btn-light btn-sm" onclick="openTransfer('${esc(f.id)}','in', this)"><i class="fa-solid fa-arrow-down"></i> ${t('in')}</button>
                    <button class="btn btn-light btn-sm" onclick="openTransfer('${esc(f.id)}','out', this)"><i class="fa-solid fa-arrow-up"></i> ${t('out')}</button>
                    <button class="btn btn-light btn-sm" onclick="renameFridge('${esc(f.id)}', ${JSON.stringify(f.name).replace(/"/g,'&quot;')})"><i class="fa-solid fa-pen"></i> ${t('edit_fridge')}</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteFridge('${esc(f.id)}', this)"><i class="fa-solid fa-trash"></i> ${t('delete_fridge')}</button>
                </div>
            </div>`).join('');
    }

    function renderAlerts() {
        if (!state.loaded) return;
        const panel = document.getElementById('alertsPanel');
        if (!state.alerts.length) {
            panel.innerHTML = `<div class="muted">${t('no_alerts')}</div>`;
            return;
        }
        const groups = { business_low: [], outside_low: [], fridge_low: [] };
        state.alerts.forEach(a => { (groups[a.alert_type] || (groups[a.alert_type] = [])).push(a); });
        const titles = { business_low: t('alert_business'), outside_low: t('alert_outside'), fridge_low: t('alert_fridge') };
        panel.innerHTML = Object.keys(groups).filter(k => groups[k].length).map(k => `
            <div class="alert-group">
                <div class="alert-title">${titles[k] || k}</div>
                ${groups[k].map(a => `<div class="alert-row ${k === 'business_low' ? 'red' : (k === 'outside_low' ? 'blue' : '')}">
                    <span><strong>${esc(a.product_name || a.product_id)}</strong>${a.fridge_id ? ' &middot; ' + esc(a.fridge_id) : ''}</span>
                    <span>${t('left')}: <strong>${fmt(a.quantity)}</strong> / ${fmt(a.threshold)}</span>
                </div>`).join('')}
            </div>`).join('');
    }

    function renderActivity() {
        if (!state.loaded) return;
        const panel = document.getElementById('activityPanel');
        if (!state.movements.length) { panel.innerHTML = `<div class="muted">${t('no_activity')}</div>`; return; }
        panel.innerHTML = state.movements.map(m => `
            <div class="movement-row">
                <div><strong>${esc(m.product_name || m.product_id)}</strong><div class="muted">${esc(m.fridge_name || 'Outside')} &middot; ${esc((m.created_at || '').replace('T',' ').substring(0,16))}</div></div>
                <div class="${m.movement_type === 'transfer_out' || m.source_location === 'fridge' ? 'movement-out' : 'movement-in'}">${m.movement_type === 'transfer_out' ? '-' : '+'}${fmt(m.quantity)}</div>
            </div>`).join('');
    }

    async function openDetail(id, btn) {
        buttonLoading(btn, true);
        try {
            const data = await api('/api/fridges/' + encodeURIComponent(id));
            state.current = data;
            state.products = (data.stock || []).map(s => ({ id: s.product_id, name: s.product_name }));
            document.getElementById('detailTitle').textContent = (data.fridge && data.fridge.name) || 'Friji';
            document.getElementById('detailBody').innerHTML = renderDetail(data);
            openModal('detailModal');
        } catch (error) { toast(error.message, 'error'); }
        finally { buttonLoading(btn, false); }
    }

    function renderDetail(data) {
        const rows = (data.stock || []).map(row => `
            <tr>
                <td><strong>${esc(row.product_name || row.product_id)}</strong><div class="muted">${esc(row.category || '')}</div></td>
                <td>${fmt(row.quantity)}</td>
                <td>${fmt(row.business_stock)}</td>
                <td>${fmt(row.min_stock)}</td>
                <td class="actions">
                    <button class="btn btn-light btn-sm" onclick="openTransfer('${esc(data.fridge.id)}','out', this)">${t('return')}</button>
                    <button class="btn btn-light btn-sm" onclick="setThreshold('${esc(data.fridge.id)}','${esc(row.product_id)}',${Number(row.min_stock || 0)})">${t('threshold')}</button>
                </td>
            </tr>`).join('');
        const movements = (data.movements || []).slice(0, 25).map(m => `
            <div class="movement-row">
                <div><strong>${esc(m.product_name || '')}</strong><div class="muted">${esc(m.movement_type)} &middot; ${esc((m.created_at || '').replace('T',' ').substring(0,16))}</div></div>
                <div class="${m.source_location === 'fridge' ? 'movement-out' : 'movement-in'}">${fmt(m.quantity)}</div>
            </div>`).join('');
        return `
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
                <button class="btn btn-primary" onclick="openTransfer('${esc(data.fridge.id)}','in', this)"><i class="fa-solid fa-arrow-down"></i> ${t('transfer_in')}</button>
                <button class="btn btn-light" onclick="openTransfer('${esc(data.fridge.id)}','out', this)"><i class="fa-solid fa-arrow-up"></i> ${t('transfer_out')}</button>
                <button class="btn btn-light" onclick="renameFridge('${esc(data.fridge.id)}', ${JSON.stringify(data.fridge.name).replace(/"/g,'&quot;')})"><i class="fa-solid fa-pen"></i> ${t('edit_fridge')}</button>
                <button class="btn btn-danger" onclick="deleteFridge('${esc(data.fridge.id)}', this)"><i class="fa-solid fa-trash"></i> ${t('delete_fridge')}</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>${t('product')}</th><th>${t('inside')}</th><th>${t('business_stock')}</th><th>${t('min_stock')}</th><th></th></tr></thead>
                    <tbody>${rows || `<tr><td colspan="5"><div class="muted">${t('no_stock')}</div></td></tr>`}</tbody>
                </table>
            </div>
            <div class="section-title" style="margin-top:22px">${t('movements')}</div>
            ${movements || `<div class="muted">${t('no_activity')}</div>`}`;
    }

    async function openTransfer(fridgeId, direction, btn) {
        buttonLoading(btn, true);
        const fridge = state.fridges.find(f => String(f.id) === String(fridgeId));
        document.getElementById('transferFridgeId').value = fridgeId;
        document.getElementById('transferDirection').value = direction;
        document.getElementById('transferTitle').textContent = direction === 'in' ? t('transfer_in') : t('transfer_out');
        document.getElementById('transferHint').textContent = direction === 'in'
            ? t('transfer_in_hint')
            : t('transfer_out_hint');
        document.getElementById('transferMinStock').parentElement.style.display = direction === 'in' ? '' : 'none';
        setTransferMode('manual');

        try {
            const data = await api('/api/fridges/availability');
            state.products = data.products || [];
            const select = document.getElementById('transferProduct');
            select.innerHTML = state.products.filter(p => Number(p.stock) > 0 || direction === 'out').map(p => {
                const available = direction === 'in' ? p.outside_stock : (p.fridges.find(f => String(f.fridge_id) === String(fridgeId)) || {}).quantity;
                return `<option value="${esc(p.product_id)}">${esc(p.name)} (${direction === 'in' ? t('outside') : t('inside')}: ${fmt(available || 0)})</option>`;
            }).join('');
            if (!select.innerHTML) select.innerHTML = `<option value="">${t('no_products')}</option>`;
        } catch (error) { toast(error.message, 'error'); }
        finally { buttonLoading(btn, false); }
        openModal('transferModal');
    }

    async function scanTransferBarcode(event) {
        if (!event || event.key !== 'Enter') return;
        event.preventDefault();
        const input = event.target;
        const code = String(input.value || '').trim();
        input.value = '';
        if (!code) return;

        try {
            const token = localStorage.getItem('userToken') || '';
            const res = await fetch('/api/products/lookup?barcode=' + encodeURIComponent(code) + '&purpose=fridge_transfer', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok || !data.product) {
                toast((data && data.error) || 'Bidhaa haijapatikana kwa barcode hii', 'error');
                return;
            }
            const product = state.products.find(p => String(p.product_id) === String(data.product.id));
            if (!product) {
                toast('Bidhaa hii haipatikani kwenye stock ya tawi lako.', 'error');
                return;
            }
            const select = document.getElementById('transferProduct');
            select.value = String(product.product_id);
            document.getElementById('transferQuantity').value = '1';
        } catch (e) {
            toast('Imeshindikana kuchanganua barcode', 'error');
        }
    }

    async function submitTransfer() {
        const fridgeId = document.getElementById('transferFridgeId').value;
        const direction = document.getElementById('transferDirection').value;
        const productId = document.getElementById('transferProduct').value;
        const quantity = Number(document.getElementById('transferQuantity').value);
        const minStock = Number(document.getElementById('transferMinStock').value || 0);
        const notes = document.getElementById('transferNotes').value;
        if (!productId || !(quantity > 0)) { toast(t('invalid_transfer'), 'error'); return; }
        const btn = document.getElementById('transferBtn');
        buttonLoading(btn, true);
        try {
            const path = '/api/fridges/' + encodeURIComponent(fridgeId) + '/stock' + (direction === 'out' ? '/remove' : '');
            await api(path, { method: 'POST', body: JSON.stringify({ product_id: productId, quantity, min_stock: minStock, notes }) });
            closeModal('transferModal');
            toast(t('transfer_saved'), 'success');
            await loadAll();
            if (document.getElementById('detailModal').classList.contains('open')) await openDetail(fridgeId);
        } catch (error) { toast(error.message, 'error'); }
        finally { buttonLoading(btn, false); }
    }

    /**
     * Manual and scanner quantity entry are mutually exclusive for this
     * operation: choosing one disables the other so the same units can never be
     * transferred twice (once by typing, once by scanning).
     */
    function setTransferMode(mode) {
        state.transferMode = mode === 'scan' ? 'scan' : 'manual';
        const scanning = state.transferMode === 'scan';
        const qty = document.getElementById('transferQuantity');
        const confirm = document.getElementById('transferBtn');
        const scanBtn = document.getElementById('transferScanBtn');
        if (qty) { qty.disabled = scanning; qty.style.opacity = scanning ? '.5' : '1'; }
        if (confirm) { confirm.style.display = scanning ? 'none' : ''; }
        if (scanBtn) { scanBtn.style.display = scanning ? '' : 'none'; }
        ['transferModeManual', 'transferModeScan'].forEach(id => {
            const btn = document.getElementById(id);
            if (!btn) return;
            const active = (id === 'transferModeScan') === scanning;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-light', !active);
        });
    }

    async function startTransferScan() {
        const fridgeId = document.getElementById('transferFridgeId').value;
        const direction = document.getElementById('transferDirection').value;
        const productId = document.getElementById('transferProduct').value;
        const fridge = state.fridges.find(f => String(f.id) === String(fridgeId));
        const product = state.products.find(p => String(p.product_id) === String(productId));
        if (!productId) { toast(t('invalid_transfer'), 'error'); return; }
        if (!window.DMScanner) { toast('Scanner haijapakiwa. Sasisha ukurasa.', 'error'); return; }

        const fridgeName = (fridge && fridge.name) || t('fridge');
        const productName = (product && product.name) || '';

        window.DMScanner.open({
            title: (direction === 'in' ? 'Entering into ' : 'Removing from ') + fridgeName + ' - ' + productName,
            hint: direction === 'in'
                ? 'Kipande lazima kiwe kwenye stock ya nje ya friji.'
                : 'Kipande lazima kiwe ndani ya friji hii.',
            onScan: async function (token) {
                const auth = localStorage.getItem('userToken') || '';
                const res = await fetch('/api/scanner/fridge/' + (direction === 'out' ? 'out' : 'in'), {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + auth, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ fridge_id: fridgeId, product_id: productId, qr_token: token })
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.success) {
                    return { ok: false, message: (data && data.error) || 'Kipande hakikuhamishwa' };
                }

                return { ok: true, message: (data.message || 'Kipande kimehamishwa') };
            },
            onClose: async function () {
                setTransferMode('manual');
                await loadAll();
                if (document.getElementById('detailModal').classList.contains('open')) await openDetail(fridgeId);
            }
        });
    }

    function openAddFridge() {
        state.editId = null;
        document.getElementById('fridgeModalTitle').textContent = t('add_fridge');
        document.getElementById('fridgeNameInput').value = '';
        openModal('fridgeModal');
    }

    function renameFridge(id, name) {
        state.editId = id;
        document.getElementById('fridgeModalTitle').textContent = t('rename_fridge');
        document.getElementById('fridgeNameInput').value = name || '';
        openModal('fridgeModal');
    }

    async function saveFridge() {
        const name = document.getElementById('fridgeNameInput').value.trim();
        if (!name) { toast(t('name_required'), 'error'); return; }
        const btn = document.getElementById('saveFridgeBtn');
        buttonLoading(btn, true);
        try {
            if (state.editId) {
                await api('/api/fridges/' + encodeURIComponent(state.editId), { method: 'PUT', body: JSON.stringify({ name }) });
            } else {
                await api('/api/fridges', { method: 'POST', body: JSON.stringify({ name }) });
            }
            closeModal('fridgeModal');
            toast(t('saved'), 'success');
            await loadAll();
            if (document.getElementById('detailModal').classList.contains('open') && state.current) {
                await openDetail(state.current.fridge.id);
            }
        } catch (error) { toast(error.message, 'error'); }
        finally { buttonLoading(btn, false); }
    }

    async function deleteFridge(id, btn) {
        if (!confirm(t('confirm_delete'))) return;
        buttonLoading(btn, true);
        try {
            const result = await api('/api/fridges/' + encodeURIComponent(id), { method: 'DELETE' });
            closeModal('detailModal');
            const dumped = Number((result && result.dumped_units) || 0);
            toast(dumped > 0 ? t('archived_done') + ' (' + fmt(dumped) + ' ' + t('units') + ')' : t('archived_done'), 'success');
            await loadAll();
        } catch (error) { toast(error.message, 'error'); }
        finally { buttonLoading(btn, false); }
    }

    async function setThreshold(fridgeId, productId, current) {
        const value = prompt(t('threshold_prompt'), String(current || 0));
        if (value === null) return;
        try {
            await api('/api/fridges/' + encodeURIComponent(fridgeId) + '/stock/' + encodeURIComponent(productId) + '/threshold', {
                method: 'PUT', body: JSON.stringify({ min_stock: Number(value) })
            });
            toast(t('saved'), 'success');
            await loadAll();
            if (state.current) await openDetail(state.current.fridge.id);
        } catch (error) { toast(error.message, 'error'); }
    }

    async function setOutsideThreshold(productId, current) {
        const value = prompt(t('outside_threshold_prompt'), String(current || 0));
        if (value === null) return;
        try {
            await api('/api/fridges/outside-threshold/' + encodeURIComponent(productId), {
                method: 'PUT', body: JSON.stringify({ min_outside_stock: Number(value) })
            });
            toast(t('saved'), 'success');
            await loadAll();
        } catch (error) { toast(error.message, 'error'); }
    }

    function setupSidebar() {
        const toggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        if (toggle && sidebar) toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) logoutBtn.addEventListener('click', () => {
            (window.DMTheme ? DMTheme.clearSession() : localStorage.clear());
            window.location.href = '/login?role=muuzaji';
        });
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open') && !sidebar.contains(e.target) && toggle && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    function setupUser() {
        const user = currentUser();
        if (!user) { window.location.href = '/login?role=muuzaji'; return false; }
        if (user.role && user.role !== 'seller' && user.role !== 'muuzaji') {
            window.location.href = '/home'; return false;
        }
        const name = user.full_name || user.business_name || (user.email || '').split('@')[0] || 'Muuzaji';
        document.getElementById('userName').textContent = name;
        const avatar = document.getElementById('userAvatar');
        if (user.business_logo_url) {
            avatar.innerHTML = `<img src="${esc(user.business_logo_url)}" alt="">`;
        } else {
            avatar.textContent = name.charAt(0).toUpperCase();
        }
        return true;
    }

    if (window.DM && typeof window.DM.onChange === 'function') {
        window.DM.onChange(() => {
            if (!state.loaded) return;
            renderStats(); renderFridges(); renderAlerts(); renderActivity();
        });
    }
    if (setupUser()) {
        setupSidebar();
        loadAll();
    }
</script>
</body>
</html>
@endverbatim
