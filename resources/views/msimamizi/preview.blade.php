@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Rejea ya Biashara - Dukamkononi Msimamizi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: #f8f9fa;
        }

        /* Layout */
        .msimamizi-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background-color: #ffffff;
            border-right: 1px solid #ecf0f1;
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.05);
        }

        .sidebar-header {
            padding: 30px 24px;
            border-bottom: 1px solid #ecf0f1;
            margin-bottom: 20px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .logo-text h2 {
            font-size: 18px;
            font-weight: 800;
            color: #2c3e50;
        }

        .logo-text p {
            font-size: 12px;
            color: #7f8c8d;
        }

        .nav-items {
            flex: 1;
            padding: 0 16px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            margin-bottom: 8px;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #5d6d7e;
            font-weight: 500;
            text-decoration: none;
        }

        .nav-item:hover {
            background-color: #fdeaea;
        }

        .nav-item.active {
            background-color: #fdeaea;
            color: #e74c3c;
        }

        .nav-icon { font-size: 20px; width: 24px; }
        .nav-label { font-size: 15px; font-weight: 600; }

        .sidebar-footer {
            padding: 20px 16px;
            border-top: 1px solid #ecf0f1;
            margin-top: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background-color: #fdeaea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e74c3c;
            font-weight: bold;
            font-size: 18px;
        }

        .user-name {
            font-size: 14px;
            font-weight: 700;
            color: #2c3e50;
        }

        .user-role {
            font-size: 12px;
            color: #e74c3c;
            font-weight: 600;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background-color: #f8f9fa;
            border-radius: 12px;
            cursor: pointer;
            color: #e74c3c;
            font-weight: 600;
            font-size: 14px;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            background-color: #f8f9fa;
            padding: 20px 24px 40px;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 200;
            background: white;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
        }

        /* Preview Screen Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 20px;
        }

        .title {
            font-size: 24px;
            font-weight: 800;
            color: #2c3e50;
        }

        .business-name {
            font-size: 16px;
            color: #3498db;
            margin-top: 4px;
        }

        .business-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }

        .stat-badge {
            background: #f8f9fa;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .refresh-btn {
            padding: 8px;
            cursor: pointer;
        }

        .info-card {
            background: #e8f4fd;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 12px;
            margin-bottom: 16px;
            padding: 4px;
            display: flex;
        }

        .tab-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            color: #7f8c8d;
        }

        .tab-btn.active {
            background: #3498db10;
            color: #3498db;
            border: 1px solid #3498db30;
        }

        /* Day Cards */
        .day-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
        }

        .date-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .date-badge {
            background: #3498db;
            padding: 6px 12px;
            border-radius: 20px;
            color: white;
            font-size: 12px;
        }

        .summary-grid {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }

        .summary-box {
            flex: 1;
            text-align: center;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 12px;
        }

        .sum-ic { color: #7a8ba0; display: flex; justify-content: center; margin-bottom: 4px; }
        .event-ic {
            width: 30px; height: 30px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .ev-sale { background: #eafaf1; color: #27ae60; }
        .ev-product { background: #eef4fa; color: #3498db; }
        .ev-warn { background: #fdf3e7; color: #e67e22; }
        .stat-badge { display: inline-flex; align-items: center; gap: 5px; }
        svg.icon { flex-shrink: 0; }

        .summary-number {
            font-size: 18px;
            font-weight: 700;
        }

        .net-profit-row {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
        }

        .modal-body {
            padding: 20px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 12px;
        }

        .expense-item, .sale-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ddd;
            border-radius: 50%;
            border-top-color: #3498db;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Date Range Filter */
        .date-filter-card { background: white; border-radius: 16px; padding: 16px; margin-bottom: 16px; }
        .date-filter-title { font-weight: 700; font-size: 14px; color: #2c3e50; margin-bottom: 12px; }
        .date-filter-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
        .date-field { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 140px; }
        .date-field label { font-size: 11px; color: #7f8c8d; font-weight: 600; }
        .date-field input {
            padding: 10px 12px; border: 1px solid #ecf0f1; border-radius: 10px;
            font-family: inherit; font-size: 13px; color: #2c3e50; background: #f8f9fa;
        }
        .date-field input:focus { outline: none; border-color: #3498db; background: white; }
        .date-sep { color: #95a5a6; padding-bottom: 10px; }
        .date-filter-actions { display: flex; gap: 8px; }
        .date-btn { padding: 10px 16px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 13px; background: #f8f9fa; color: #5d6d7e; }
        .date-btn.primary { background: #3498db; color: white; }
        .date-btn.primary:hover { background: #2980b9; }
        .date-filter-active {
            margin-top: 10px; font-size: 12px; color: #1e8449; background: #eafaf1;
            padding: 8px 12px; border-radius: 8px; display: inline-block;
        }

        /* Taarifa (events) read state */
        .taarifa-header {
            display: flex; justify-content: space-between; align-items: center;
            background: white; border-radius: 12px; padding: 12px 16px;
            margin-bottom: 12px; flex-wrap: wrap; gap: 10px;
        }
        .zimesomwa-btn {
            background: #27ae60; color: white; padding: 10px 18px; border-radius: 24px;
            cursor: pointer; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .zimesomwa-btn:hover { background: #219a52; }
        .new-badge {
            background: #e74c3c; color: white; font-size: 9px; font-weight: 700;
            padding: 2px 8px; border-radius: 10px; margin-left: 6px; vertical-align: middle;
        }

        /* Per-day print button */
        .day-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; gap: 8px; }
        .print-day-btn {
            background: #3498db; color: white; padding: 8px 14px; border-radius: 20px;
            cursor: pointer; font-weight: 700; font-size: 12px; white-space: nowrap;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .print-day-btn:hover { background: #2980b9; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 16px; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
@endverbatim
@include('partials.photo-viewer')
@verbatim
<body>
    <div class="mobile-menu-toggle" id="mobileMenuToggle">☰</div>

    <div class="msimamizi-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-area">
                    <div class="logo-icon">D</div>
                    <div class="logo-text">
                        <h2>DukaMkononi</h2>
                        <p>Msimamizi Portal</p>
                    </div>
                </div>
            </div>
            <div class="nav-items">
                <a href="index" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-house"></i></div>
                    <span class="nav-label">Nyumbani</span>
                </a>
                <a href="ripoti" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-chart-simple"></i></div>
                    <span class="nav-label">Ripoti</span>
                </a>
                <a href="preview" class="nav-item active">
                    <div class="nav-icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <span class="nav-label">Rejea</span>
                </a>
                <a href="bidhaa-mpya" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-circle-plus"></i></div>
                    <span class="nav-label">Bidhaa Mpya</span>
                </a>
                <a href="tangaza" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-bullhorn"></i></div>
                    <span class="nav-label">Tangaza</span>
                </a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar">M</div>
                    <div class="user-details">
                        <div class="user-name" id="userName">Msimamizi</div>
                        <div class="user-role">Msimamizi</div>
                    </div>
                </div>
                <div class="logout-btn" id="logoutBtn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Ondoka</div>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <div id="previewContainer">Loading...</div>
        </main>
    </div>

    <div id="dayModal" class="modal-overlay">
        <div class="modal-content" id="modalContent"></div>
    </div>

    <script>
        const API_BASE_URL = '';
        
        let userData = { businessName: '', businessLocation: '', userId: '', userRole: '' };
        let dailySummaries = [];
        let businessEvents = [];
        let businessStats = { totalSellers: 0, totalProducts: 0, totalSalesAmount: 0, totalProfit: 0, totalNetProfit: 0 };
        let loading = true;
        let activeTab = 'days';

        // Applied date-range filter (YYYY-MM-DD); empty strings = no filter.
        let filterStart = '';
        let filterEnd = '';

        // "Zimesomwa" read-state for the Taarifa tab: server-backed with a
        // localStorage fallback so it survives reloads.
        let lastReadAt = localStorage.getItem('msimamiziEventsReadAt') || null;

        function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }

        // ============================== ICONS ==============================
        // Font Awesome 6 icon helper (https://fontawesome.com).
        const FA_MAP = {
            home: 'fa-house', report: 'fa-chart-simple', calendar: 'fa-calendar-days', plus: 'fa-circle-plus',
            megaphone: 'fa-bullhorn', logout: 'fa-arrow-right-from-bracket', search: 'fa-magnifying-glass',
            money: 'fa-money-bill-1', chart: 'fa-chart-line', users: 'fa-users', user: 'fa-user',
            box: 'fa-box', doc: 'fa-file-lines', mail: 'fa-envelope', phone: 'fa-phone',
            building: 'fa-building', refresh: 'fa-rotate-right', edit: 'fa-pen-to-square',
            trash: 'fa-trash-can', settings: 'fa-gear', camera: 'fa-camera', location: 'fa-location-dot',
            check: 'fa-check', x: 'fa-xmark', shield: 'fa-shield-halved', clock: 'fa-clock',
            bell: 'fa-bell', warning: 'fa-triangle-exclamation', print: 'fa-print', filter: 'fa-filter',
            folder: 'fa-folder-open', image: 'fa-image', video: 'fa-video', thumb: 'fa-thumbs-up',
            flag: 'fa-flag', crown: 'fa-crown', info: 'fa-circle-info', filePdf: 'fa-file-pdf',
            excel: 'fa-file-excel', plusSm: 'fa-plus', ai: 'fa-robot', scan: 'fa-barcode', save: 'fa-floppy-disk'
        };
        function ic(name, size = 16, cls = '') {
            return `<i class="fa-solid ${FA_MAP[name] || 'fa-circle-info'} ${cls}" style="font-size:${size}px;" aria-hidden="true"></i>`;
        }

        function formatCurrency(amount) { return `TSh ${(amount || 0).toLocaleString()}`; }

        function formatDate(dateStr) {
            try {
                return new Date(dateStr).toLocaleDateString('sw-TZ', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            } catch { return dateStr; }
        }

        function getShortDate(dateStr) {
            try {
                return new Date(dateStr).toLocaleDateString('sw-TZ', { weekday: 'short', month: 'short', day: 'numeric' });
            } catch { return dateStr; }
        }

        function showAlert(title, message) {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:2000;';
            const box = document.createElement('div');
            box.style.cssText = 'background:white;border-radius:28px;width:85%;max-width:320px;padding:24px;text-align:center;';
            box.innerHTML = `<div style="font-size:20px;font-weight:800;margin-bottom:12px;">${escapeHtml(title)}</div>
                <div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;">${escapeHtml(message)}</div>
                <div style="background:#e74c3c;padding:12px;border-radius:40px;color:white;cursor:pointer;">Sawa</div>`;
            box.querySelector('div:last-child').onclick = () => overlay.remove();
            overlay.appendChild(box);
            document.body.appendChild(overlay);
        }

        async function loadUserData() {
            const token = localStorage.getItem('userToken');
            const userStr = localStorage.getItem('userData');
            if (!token || !userStr) {
                showAlert('Hitilafu', 'Tafadhali ingia tena');
                setTimeout(() => window.location.href = '../login?role=msimamizi', 1500);
                return false;
            }
            const user = JSON.parse(userStr);
            userData = {
                businessName: user.businessName || user.business_name || 'Biashara Kuu',
                businessLocation: user.businessLocation || user.business_location || 'Makao Makuu',
                userId: user.id || '',
                userRole: user.role || ''
            };
            document.getElementById('userName').innerHTML = escapeHtml(user.full_name || user.email?.split('@')[0] || 'Msimamizi');
            const avatarEl = document.getElementById('userAvatar');
            const displayName = user.full_name || user.business_name || user.email?.split('@')[0] || 'Msimamizi';
            if (user.business_logo_url) {
                avatarEl.classList.add('js-avatar-view');
avatarEl.setAttribute('data-full', user.business_logo_url);
avatarEl.setAttribute('data-name', displayName);
avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="Picha">`;
            } else {
                avatarEl.innerHTML = (user.full_name || user.email?.charAt(0) || 'M').charAt(0).toUpperCase();
            }
            return token;
        }

        async function fetchData(token) {
            try {
                // Fetch sellers
                let sellers = [];
                const sellersRes = await fetch(`${API_BASE_URL}/api/admin/users?business=${encodeURIComponent(userData.businessName)}&role=seller,admin`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                // Server-side business filter (?business=): only this
                // business's users leave the database. Admin is included so
                // their own sales/products match, like the products endpoint.
                if (sellersRes.ok) {
                    const data = await sellersRes.json();
                    const users = data.users || (Array.isArray(data) ? data : []);
                    sellers = users.filter(u => (u.role === 'seller' || u.role === 'admin') && u.business_name === userData.businessName && u.status === 'approved');
                }

                // Fetch products — server-side business filter (?business_name=):
                // only this business's products are downloaded (previously ALL
                // businesses' products arrived and were filtered in the browser,
                // which is what made the page crawl).
                let products = [];
                const productsRes = await fetch(`${API_BASE_URL}/api/admin/products?business_name=${encodeURIComponent(userData.businessName)}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (productsRes.ok) {
                    const data = await productsRes.json();
                    products = data.products || (Array.isArray(data) ? data : []);
                }
                const productsById = new Map(products.map(p => [p.id, p]));

                // Fetch sales — server-side business + date-range filter, so the
                // database returns exactly the rows this page displays.
                let sales = [];
                const salesParams = new URLSearchParams({ business_name: userData.businessName });
                if (filterStart) salesParams.set('date_from', filterStart);
                if (filterEnd) salesParams.set('date_to', filterEnd);
                const salesRes = await fetch(`${API_BASE_URL}/api/admin/sales?${salesParams.toString()}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (salesRes.ok) {
                    const data = await salesRes.json();
                    const salesArray = data.sales || (Array.isArray(data) ? data : []);
                    
                    for (const sale of salesArray) {
                        const seller = sellers.find(s => s.id === sale.seller_id);
                        if (seller) {
                            if (sale.sale_items && sale.sale_items.length) {
                                for (const item of sale.sale_items) {
                                    const product = productsById.get(item.product_id);
                                    sales.push({
                                        id: sale.id,
                                        product_name: product?.name || item.products?.name || 'Bidhaa',
                                        quantity: item.quantity || 1,
                                        unit_price: item.unit_price || 0,
                                        total_amount: item.total_price || (item.unit_price * item.quantity),
                                        sale_date: sale.sale_date?.split('T')[0] || new Date().toISOString().split('T')[0],
                                        customer_name: sale.customers?.name || 'Mteja',
                                        seller_name: seller.full_name || seller.email,
                                        cost_price: product?.cost_price || product?.price || 0
                                    });
                                }
                            } else {
                                sales.push({
                                    id: sale.id,
                                    product_name: 'Bidhaa',
                                    quantity: 1,
                                    unit_price: sale.total_amount || 0,
                                    total_amount: sale.total_amount || 0,
                                    sale_date: sale.sale_date?.split('T')[0] || new Date().toISOString().split('T')[0],
                                    customer_name: sale.customers?.name || 'Mteja',
                                    seller_name: seller.full_name || seller.email,
                                    cost_price: 0
                                });
                            }
                        }
                    }
                }                // Get unique dates
                const dates = [...new Set(sales.map(s => s.sale_date))];
                
                // Fetch expenses once for the whole date range (single request
                // instead of one per date — the old per-date loop made the page
                // hang for minutes when there were many sale dates).
                // Expense dates matter on their own: a day with NO sales but
                // WITH expenses still gets a day card (negative net), so the
                // expense window must start from the earliest of (sales dates,
                // expense dates), not sales alone.
                let allExpenseDates = [];
                const expensesByDate = {};
                {
                    // Figure out the window to request: sales dates plus, with
                    // no active filter, every date that has an expense.
                    const salesMin = dates.length ? dates.reduce((a, b) => a < b ? a : b) : null;
                    const salesMax = dates.length ? dates.reduce((a, b) => a > b ? a : b) : null;
                    let minDate = filterStart || salesMin;
                    let maxDate = filterEnd || salesMax;

                    if (!filterStart && !filterEnd) {
                        // No filter: also cover expense-only days. Fetch the
                        // full expense list once and note its dates.
                        try {
                            const expRes = await fetch(`${API_BASE_URL}/api/office-expenses/range?start_date=2000-01-01&end_date=2100-01-01`, {
                                headers: { 'Authorization': `Bearer ${token}` }
                            });
                            if (expRes.ok) {
                                const expData = await expRes.json();
                                if (expData.success && Array.isArray(expData.expenses)) {
                                    allExpenseDates = [...new Set(expData.expenses.map(e => (e.expense_date || '').split('T')[0]).filter(Boolean))];
                                    for (const d of allExpenseDates) {
                                        expensesByDate[d] = expData.expenses.filter(e => (e.expense_date || '').split('T')[0] === d);
                                    }
                                }
                            }
                        } catch { /* expenses stay empty */ }
                        if (allExpenseDates.length) {
                            const expMin = allExpenseDates.reduce((a, b) => a < b ? a : b);
                            const expMax = allExpenseDates.reduce((a, b) => a > b ? a : b);
                            if (!minDate || expMin < minDate) minDate = expMin;
                            if (!maxDate || expMax > maxDate) maxDate = expMax;
                        }
                    } else {
                        // With an active filter, request exactly that window.
                        try {
                            const expRes = await fetch(`${API_BASE_URL}/api/office-expenses/range?start_date=${minDate}&end_date=${maxDate}`, {
                                headers: { 'Authorization': `Bearer ${token}` }
                            });
                            if (expRes.ok) {
                                const expData = await expRes.json();
                                if (expData.success && Array.isArray(expData.expenses)) {
                                    for (const e of expData.expenses) {
                                        const d = (e.expense_date || '').split('T')[0];
                                        if (!d) continue;
                                        if (!expensesByDate[d]) expensesByDate[d] = [];
                                        expensesByDate[d].push(e);
                                    }
                                }
                            }
                        } catch { /* expenses stay empty */ }
                    }
                }

                // Process daily summaries — include BOTH kinds of days:
                //   - days with sales, and
                //   - days with NO sales but WITH expenses (negative net).
                // Fully zero days (no sales AND no expenses) stay hidden.
                const salesByDate = {};
                sales.forEach(sale => {
                    if (!salesByDate[sale.sale_date]) salesByDate[sale.sale_date] = [];
                    salesByDate[sale.sale_date].push(sale);
                });

                const summaryDates = [...new Set([...Object.keys(salesByDate), ...Object.keys(expensesByDate)])];

                const summaries = summaryDates.map(date => {
                    const daySales = salesByDate[date] || [];
                    const dayExpenses = expensesByDate[date] || [];
                    const totalSales = daySales.reduce((s, sale) => s + (sale.total_amount || 0), 0);
                    const totalProducts = daySales.reduce((s, sale) => s + (sale.quantity || 0), 0);
                    const totalProfit = daySales.reduce((s, sale) => {
                        const profit = (sale.unit_price - (sale.cost_price || 0)) * sale.quantity;
                        return s + Math.max(0, profit);
                    }, 0);
                    const totalExpenses = dayExpenses.reduce((s, e) => s + (e.amount || 0), 0);
                    const netProfit = totalProfit - totalExpenses;
                    // Only names actually recorded — drop the "Mteja" placeholder
                    // so the daily report lists real customers when they exist.
                    const customers = [...new Set(daySales.map(s => s.customer_name).filter(n => n && n !== 'Mteja'))];
                    const daySellers = [...new Map(daySales.map(s => [s.seller_name, { name: s.seller_name }])).values()];
                    
                    return { date, totalSales, totalProducts, totalProfit, totalExpenses, netProfit, sales: daySales, customers, sellers: daySellers, expenses: dayExpenses };
                });

                // Drop fully-empty days (defensive: neither sales nor expenses).
                const nonEmpty = summaries.filter(s => s.sales.length > 0 || s.expenses.length > 0);
                const summaries2 = nonEmpty;
                
                summaries2.sort((a,b) => new Date(b.date) - new Date(a.date));
                dailySummaries = summaries2;

                // Build events
                const events = [];
                sales.slice(0, 20).forEach(sale => {
                    events.push({ id: sale.id, type: 'sale', title: 'Mauzo', description: `${sale.quantity} ${sale.product_name} imeuza kwa ${sale.customer_name}`, amount: sale.total_amount, seller_name: sale.seller_name, customer_name: sale.customer_name, product_name: sale.product_name, event_date: sale.sale_date });
                });
                products.slice(0, 10).forEach(product => {
                    events.push({ id: product.id + 1000, type: 'product_added', title: 'Bidhaa Mpya', description: `Bidhaa ${product.name} imeongezwa`, amount: product.price, product_name: product.name, event_date: product.created_at?.split('T')[0] || new Date().toISOString().split('T')[0] });
                });
                products.filter(p => p.stock < 5).forEach(product => {
                    events.push({ id: product.id + 2000, type: 'low_stock', title: 'Stock Inakaribia Kuisha', description: `Bidhaa ${product.name} ina stock ${product.stock} pekee`, product_name: product.name, event_date: new Date().toISOString().split('T')[0] });
                });
                events.sort((a,b) => new Date(b.event_date) - new Date(a.event_date));
                businessEvents = events;

                // Stats
                const totalSalesAmount = sales.reduce((s, sale) => s + (sale.total_amount || 0), 0);
                const totalProfit = sales.reduce((s, sale) => s + Math.max(0, (sale.unit_price - (sale.cost_price || 0)) * sale.quantity), 0);
                let totalExpensesAll = 0;
                Object.values(expensesByDate).forEach(dayExps => dayExps.forEach(e => totalExpensesAll += e.amount));
                businessStats = {
                    totalSellers: sellers.filter(s => s.role === 'seller').length,
                    totalProducts: products.length,
                    totalSalesAmount,
                    totalProfit,
                    totalNetProfit: totalProfit - totalExpensesAll
                };

            } catch (error) {
                console.error('Fetch error:', error);
            }
        }

        function renderDaysTab() {
            if (dailySummaries.length === 0) {
                const filtered = filterStart || filterEnd;
                return `<div style="text-align:center;padding:40px;background:white;border-radius:16px;color:#7a8ba0;">
                    ${ic('report', 44)}
                    <div style="margin-top:12px;font-weight:600;color:#2c3e50;">${filtered ? 'Hakuna mauzo katika kipindi hiki' : 'Hakuna mauzo ya biashara bado'}</div>
                    <div style="font-size:13px;color:#95a5a6;margin-top:8px;">${filtered ? 'Jaribu kubadilisha kipindi cha tarehe kilichochaguliwa.' : `Biashara "${escapeHtml(userData.businessName)}" haina mauzo yaliyorekodiwa.`}</div>
                </div>`;
            }
            
            return dailySummaries.map(day => {
                const expenseOnlyDay = day.sales.length === 0 && day.expenses.length > 0;
                return `
                <div class="day-card" onclick="window.showDayModal('${day.date}')" ${expenseOnlyDay ? 'style="border-left:4px solid #e67e22;"' : ''}>
                    <div class="date-header">
                        <span class="date-badge" ${expenseOnlyDay ? 'style="background:#e67e22;"' : ''}>${ic('calendar', 13)} ${getShortDate(day.date)}</span>
                        <span style="font-size:12px;color:#7f8c8d;">${expenseOnlyDay ? 'Hakuna mauzo — matumizi tu' : day.sales.length + ' mauzo'}</span>
                    </div>
                    <div class="summary-grid">
                        <div class="summary-box"><div class="sum-ic">${ic('box', 16)}</div><div class="summary-number">${day.totalProducts}</div><div>Bidhaa</div></div>
                        <div class="summary-box"><div class="sum-ic">${ic('money', 16)}</div><div class="summary-number">${formatCurrency(day.totalSales)}</div><div>Mauzo</div></div>
                        <div class="summary-box"><div class="sum-ic">${ic('chart', 16)}</div><div class="summary-number">${formatCurrency(day.totalProfit)}</div><div>Faida</div></div>
                    </div>
                    <div class="net-profit-row">
                        <span>Faida Halisi:</span>
                        <strong style="color:${day.netProfit >= 0 ? '#27ae60' : '#e74c3c'}">${formatCurrency(day.netProfit)}</strong>
                        ${day.expenses.length ? `<span style="font-size:11px;color:#e67e22;">(Matumizi: ${formatCurrency(day.totalExpenses)})</span>` : ''}
                    </div>
                    ${expenseOnlyDay
                        ? `<div style="font-size:12px;color:#e67e22;">${ic('doc', 12)} Matumizi ya siku hii: ${day.expenses.map(e => escapeHtml(e.category || 'Matumizi')).slice(0,3).join(', ')}${day.expenses.length > 3 ? '...' : ''}</div>`
                        : `<div style="font-size:12px;color:#3498db;">${ic('users', 12)} Wateja: ${day.customers.length ? day.customers.slice(0,3).join(', ') + (day.customers.length > 3 ? '...' : '') : 'Hawajarekodiwa'}</div>`}
                    <div class="day-card-footer">
                        <span style="font-size:11px;color:#9b59b6;">Bonyeza kwa muhtasari kamili →</span>
                        <div class="print-day-btn" onclick="event.stopPropagation(); window.printDayReport('${day.date}')">${ic('print', 13)} Chapisha Taarifa</div>
                    </div>
                </div>
            `;
            }).join('');
        }

        function renderEventsTab() {
            const unreadCount = businessEvents.filter(isEventUnread).length;
            const header = `
                <div class="taarifa-header">
                    <div style="font-weight:700;font-size:14px;color:#2c3e50;display:flex;align-items:center;gap:6px;">
                        ${ic('bell', 16)} Taarifa zote
                        ${unreadCount ? `<span class="new-badge" style="font-size:11px;padding:3px 10px;">${unreadCount} mpya</span>` : `<span style="font-size:11px;color:#27ae60;font-weight:600;margin-left:8px;">zote zimesomwa</span>`}
                    </div>
                    <div class="zimesomwa-btn" onclick="window.markAllEventsRead()">${ic('check', 14)} Zimesomwa</div>
                </div>
            `;

            if (businessEvents.length === 0) {
                return header + `<div style="text-align:center;padding:40px;background:white;border-radius:16px;color:#7a8ba0;">
                    ${ic('bell', 44)}
                    <div style="margin-top:12px;font-weight:600;">Hakuna matukio bado</div>
                </div>`;
            }
            
            return header + businessEvents.map(event => `
                <div style="background:white;border-radius:12px;padding:16px;margin-bottom:12px;${isEventUnread(event) ? 'border-left:4px solid #3498db;' : 'opacity:0.75;'}">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <div style="display:flex;gap:8px;align-items:center;">
                            <span class="event-ic ${event.type === 'sale' ? 'ev-sale' : event.type === 'product_added' ? 'ev-product' : 'ev-warn'}">${event.type === 'sale' ? ic('money', 15) : event.type === 'product_added' ? ic('box', 15) : ic('warning', 15)}</span>
                            <div><strong>${event.title}</strong>${isEventUnread(event) ? '<span class="new-badge">MPYA</span>' : ''}<div style="font-size:11px;color:#7f8c8d;">${event.event_date}</div></div>
                        </div>
                        ${event.amount ? `<strong style="color:#27ae60;">${formatCurrency(event.amount)}</strong>` : ''}
                    </div>
                    <div style="font-size:14px;color:#2c3e50;">${event.description}</div>
                    ${event.seller_name ? `<div style="font-size:11px;color:#7f8c8d;margin-top:8px;">${ic('user', 12)} ${event.seller_name}</div>` : ''}
                </div>
            `).join('');
        }

        function isEventUnread(event) {
            if (!lastReadAt) return true;
            try { return new Date(event.event_date) > new Date(lastReadAt); }
            catch { return true; }
        }

        window.markAllEventsRead = async () => {
            lastReadAt = new Date().toISOString();
            localStorage.setItem('msimamiziEventsReadAt', lastReadAt);
            render();
            try {
                const token = localStorage.getItem('userToken');
                const res = await fetch(`${API_BASE_URL}/api/admin/notifications/mark-all-read`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({})
                });
                const data = await res.json().catch(() => ({}));
                if (data && data.last_read_at) {
                    lastReadAt = data.last_read_at;
                    localStorage.setItem('msimamiziEventsReadAt', lastReadAt);
                }
            } catch { /* offline: local read-state still applies */ }
        };

        function renderDateFilter() {
            const active = filterStart || filterEnd;
            return `
                <div class="date-filter-card">
                    <div class="date-filter-title">${ic('filter', 14)} Chuja kwa Kipindi cha Tarehe</div>
                    <div class="date-filter-row">
                        <div class="date-field">
                            <label>Kuanzia</label>
                            <input type="date" id="dateFilterStart" value="${filterStart}">
                        </div>
                        <span class="date-sep">hadi</span>
                        <div class="date-field">
                            <label>Hadi</label>
                            <input type="date" id="dateFilterEnd" value="${filterEnd}">
                        </div>
                        <div class="date-filter-actions">
                            <div class="date-btn primary" onclick="window.applyDateFilter()">Tafuta</div>
                            <div class="date-btn" onclick="window.clearDateFilter()">Futa</div>
                        </div>
                    </div>
                    ${active ? `<div class="date-filter-active">✓ Inaonyesha kipindi: <strong>${filterStart || 'mwanzo'}</strong> hadi <strong>${filterEnd || 'leo'}</strong></div>` : ''}
                </div>
            `;
        }

        function render() {
            const container = document.getElementById('previewContainer');
            const unreadCount = businessEvents.filter(isEventUnread).length;
            container.innerHTML = `
                <div class="header">
                    <div>
                        <h1 class="title">Rejea ya Biashara</h1>
                        <div class="business-name">${escapeHtml(userData.businessName)}</div>
                        <div class="business-stats">
                            <span class="stat-badge">${ic('users', 12)} ${businessStats.totalSellers} wauzaji</span>
                            <span class="stat-badge">${ic('box', 12)} ${businessStats.totalProducts} bidhaa</span>
                            <span class="stat-badge">${ic('money', 12)} ${formatCurrency(businessStats.totalSalesAmount)} mauzo</span>
                            <span class="stat-badge">${ic('chart', 12)} ${formatCurrency(businessStats.totalProfit)} faida</span>
                            <span class="stat-badge">${ic('report', 12)} ${formatCurrency(businessStats.totalNetProfit)} halisi</span>
                        </div>
                    </div>
                    <div class="refresh-btn" id="refreshBtn">${refreshing ? '<div class=\"loading-spinner\"></div>' : ic('check', 16)}</div>
                </div>
                <div class="info-card">
                    <div style="font-weight:600;margin-bottom:6px;display:flex;align-items:center;gap:6px;">${ic('doc', 14)} Muhtasari wa Biashara</div>
                    <div style="font-size:13px;">Data ya biashara nzima "${escapeHtml(userData.businessName)}". Faida halisi ni baada ya kutoa matumizi ya ofisi.</div>
                </div>
                <div class="tabs-container">
                    <div class="tab-btn ${activeTab === 'days' ? 'active' : ''}" onclick="window.setTab('days')">${ic('calendar', 15)} Siku Zangu</div>
                    <div class="tab-btn ${activeTab === 'events' ? 'active' : ''}" onclick="window.setTab('events')">${ic('bell', 15)} Taarifa (${unreadCount})${unreadCount ? ` <span class="new-badge">MPYA</span>` : ''}</div>
                </div>
                <div id="tabContent">${activeTab === 'days' ? renderDaysTab() : renderEventsTab()}</div>
            `;
            
            document.getElementById('refreshBtn')?.addEventListener('click', () => { location.reload(); });
        }

        window.setTab = (tab) => {
            activeTab = tab;
            render();
        };

        // Date-range filter: re-fetch from the API with date_from/date_to so
        // the DATABASE filters sales (fast) instead of the browser.
        window.applyDateFilter = async () => {
            const start = document.getElementById('dateFilterStart')?.value || '';
            const end = document.getElementById('dateFilterEnd')?.value || '';
            if (start && end && start > end) {
                showAlert('Hitilafu', 'Tarehe ya kuanzia iko kabla ya tarehe ya mwisho');
                return;
            }
            filterStart = start;
            filterEnd = end;
            const container = document.getElementById('previewContainer');
            container.innerHTML = '<div style="text-align:center;padding:60px;"><div class="loading-spinner"></div><div>Inapakua ripoti ya kipindi...</div></div>';
            const token = localStorage.getItem('userToken');
            await fetchData(token);
            render();
        };

        window.clearDateFilter = async () => {
            filterStart = '';
            filterEnd = '';
            const container = document.getElementById('previewContainer');
            container.innerHTML = '<div style="text-align:center;padding:60px;"><div class="loading-spinner"></div><div>Inapakua ripoti zote...</div></div>';
            const token = localStorage.getItem('userToken');
            await fetchData(token);
            render();
        };

        window.showDayModal = (date) => {
            const day = dailySummaries.find(d => d.date === date);
            if (!day) return;
            
            const modal = document.getElementById('dayModal');
            const modalContent = document.getElementById('modalContent');
            
            modalContent.innerHTML = `
                <div class="modal-header">
                    <div><strong>${formatDate(day.date)}</strong><div style="font-size:12px;color:#3498db;">${escapeHtml(userData.businessName)}</div></div>
                    <span style="cursor:pointer;font-size:24px;" onclick="document.getElementById('dayModal').style.display='none'">✖</span>
                </div>
                <div class="modal-body">
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px;">
                        <div class="stat-card"><div class="sum-ic">${ic('box', 18)}</div><div style="font-size:20px;font-weight:700;">${day.totalProducts}</div><div>Bidhaa</div></div>
                        <div class="stat-card"><div class="sum-ic">${ic('money', 18)}</div><div style="font-size:20px;font-weight:700;">${formatCurrency(day.totalSales)}</div><div>Mauzo</div></div>
                        <div class="stat-card"><div class="sum-ic">${ic('chart', 18)}</div><div style="font-size:20px;font-weight:700;">${formatCurrency(day.totalProfit)}</div><div>Faida Ghafi</div></div>
                        <div class="stat-card"><div class="sum-ic">${ic('report', 18)}</div><div style="font-size:20px;font-weight:700;color:${day.netProfit >= 0 ? '#27ae60' : '#e74c3c'}">${formatCurrency(day.netProfit)}</div><div>Faida Halisi</div></div>
                    </div>
                    
                    ${day.expenses.length ? `
                        <div style="margin-bottom:16px;"><strong>Matumizi ya Ofisi</strong></div>
                        ${day.expenses.map(e => `<div class="expense-item"><span>${escapeHtml(e.category)}: ${escapeHtml(e.description)}</span><strong>${formatCurrency(e.amount)}</strong></div>`).join('')}
                    ` : ''}
                    
                    <div style="margin-bottom:16px;"><strong>Wateja (${day.customers.length})</strong></div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:12px;margin-bottom:16px;">
                        ${day.customers.length ? day.customers.map(c => `<div>• ${escapeHtml(c)}</div>`).join('') : '<div style="color:#95a5a6;font-size:13px;">Hakuna majina ya wateja yaliyorekodiwa siku hii</div>'}
                    </div>
                    
                    <div style="margin-bottom:16px;"><strong>Mauzo ya Siku Hii</strong></div>
                    ${day.sales.length ? day.sales.slice(0,5).map(sale => `
                        <div class="sale-item">
                            <div><strong>${escapeHtml(sale.product_name)}</strong><br><span style="font-size:11px;color:#7f8c8d;">${escapeHtml(sale.customer_name)}</span></div>
                            <div style="text-align:right;"><strong>${formatCurrency(sale.total_amount)}</strong><br><span style="font-size:11px;">${sale.quantity} × ${formatCurrency(sale.unit_price)}</span></div>
                        </div>
                    `).join('') : '<div style="color:#95a5a6;font-size:13px;background:#fdf3e7;border-radius:10px;padding:10px 12px;">Hakuna mauzo siku hii — matumizi tu ya ofisi yaliyorekodiwa.</div>'}
                    
                    <div style="margin-top:16px;padding-top:12px;border-top:1px solid #ecf0f1;">
                        <div style="display:flex;justify-content:space-between;"><span>Jumla ya Bidhaa:</span><strong>${day.totalProducts}</strong></div>
                        <div style="display:flex;justify-content:space-between;"><span>Jumla ya Mauzo:</span><strong>${formatCurrency(day.totalSales)}</strong></div>
                        <div style="display:flex;justify-content:space-between;"><span>Faida Ghafi:</span><strong>${formatCurrency(day.totalProfit)}</strong></div>
                        <div style="display:flex;justify-content:space-between;"><span>Jumla ya Matumizi:</span><strong>${formatCurrency(day.totalExpenses)}</strong></div>
                        <div style="display:flex;justify-content:space-between;margin-top:8px;padding-top:8px;border-top:2px solid #3498db;"><span>Faida Halisi:</span><strong style="color:${day.netProfit >= 0 ? '#27ae60' : '#e74c3c'}">${formatCurrency(day.netProfit)}</strong></div>
                    </div>
                </div>
            `;
            modal.style.display = 'flex';
        };

        // "Chapisha Taarifa": builds a standalone printable document for one
        // day and opens the browser's print dialog (destination includes
        // "Save as PDF"). Uses ALL of that day's sales, not just the modal's
        // 5-row preview.
        window.printDayReport = (date) => {
            const day = dailySummaries.find(d => d.date === date);
            if (!day) { showAlert('Hitilafu', 'Taarifa ya siku haijapatikana'); return; }

            const esc = (v) => escapeHtml(String(v ?? ''));
            const row = (label, value, color) => `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #ecf0f1;"><span>${esc(label)}</span><strong${color ? ` style="color:${color}"` : ''}>${esc(value)}</strong></div>`;
            const th = (t) => `<th style="border:1px solid #bdc3c7;padding:6px 10px;background:#f4f6f7;text-align:left;">${esc(t)}</th>`;
            const td = (v) => `<td style="border:1px solid #ecf0f1;padding:6px 10px;">${esc(v)}</td>`;

            const salesRows = day.sales.map(s =>
                `<tr><td>${esc(s.product_name)}</td><td>${esc(s.customer_name || 'Mteja')}</td><td>${esc(s.seller_name || '')}</td><td>${esc(s.quantity)}</td><td>${esc(formatCurrency(s.unit_price))}</td><td>${esc(formatCurrency(s.total_amount))}</td></tr>`
            ).join('');

            const expensesSection = day.expenses.length ? `
                <h3 style="margin:18px 0 8px;">Matumizi ya Ofisi</h3>
                ${day.expenses.map(e => row(`${esc(e.category)}: ${esc(e.description)}`, formatCurrency(e.amount), '#e67e22')).join('')}
                ${row('Jumla ya Matumizi', formatCurrency(day.totalExpenses), '#e67e22')}
            ` : '';

            const html = `<html><head><meta charset="UTF-8"><title>Taarifa ya Siku - ${esc(day.date)}</title></head>
                <body style="font-family:sans-serif;color:#2c3e50;max-width:800px;margin:0 auto;padding:20px;">
                    <div style="text-align:center;border-bottom:3px solid #3498db;padding-bottom:12px;margin-bottom:16px;">
                        <h2 style="margin:0;">Taarifa ya Siku</h2>
                        <div style="font-size:14px;color:#3498db;font-weight:700;">${esc(userData.businessName)}</div>
                        <div style="font-size:13px;color:#7f8c8d;">${esc(formatDate(day.date))}</div>
                        ${userData.businessLocation ? `<div style="font-size:12px;color:#95a5a6;">${ic('location', 12)} ${esc(userData.businessLocation)}</div>` : ''}
                    </div>

                    <h3 style="margin:0 0 8px;">Muhtasari</h3>
                    ${row('Jumla ya Bidhaa Zilizouzwa', day.totalProducts)}
                    ${row('Jumla ya Mauzo', formatCurrency(day.totalSales), '#27ae60')}
                    ${row('Faida Ghafi', formatCurrency(day.totalProfit), '#27ae60')}
                    ${day.expenses.length ? row('Jumla ya Matumizi', formatCurrency(day.totalExpenses), '#e67e22') : ''}
                    ${row('Faida Halisi', formatCurrency(day.netProfit), day.netProfit >= 0 ? '#27ae60' : '#e74c3c')}

                    ${expensesSection}

                    <h3 style="margin:18px 0 8px;">Wateja wa Siku Hii</h3>
                    ${day.customers.length
                        ? day.customers.map(c => `<div style="padding:4px 0;border-bottom:1px solid #ecf0f1;">• ${esc(c)}</div>`).join('')
                        : '<div style="color:#95a5a6;">Hakuna majina ya wateja yaliyorekodiwa</div>'}

                    <h3 style="margin:18px 0 8px;">Mauzo Yote ya Siku Hii (${day.sales.length})</h3>
                    ${day.sales.length
                        ? `<table style="border-collapse:collapse;width:100%;font-size:12px;">
                        <thead><tr>${th('Bidhaa')}${th('Mteja')}${th('Muuzaji')}${th('Idadi')}${th('Bei')}${th('Jumla')}</tr></thead>
                        <tbody>${salesRows}</tbody>
                    </table>`
                        : `<div style="color:#95a5a6;font-size:13px;">Hakuna mauzo yaliyorekodiwa siku hii — ripoti hii inaonyesha matumizi tu.</div>`}

                    <div style="margin-top:20px;padding-top:10px;border-top:1px solid #ecf0f1;font-size:11px;color:#95a5a6;text-align:center;">
                        Imechapishwa ${esc(new Date().toLocaleString('sw-TZ'))} — DukaMkononi
                    </div>
                </body></html>`;

            const w = window.open('', '_blank');
            if (!w) { showAlert('Hitilafu', 'Tafadhali ruhusu popups ili kuchapisha taarifa'); return; }
            w.document.open();
            w.document.write(html);
            w.document.close();
            w.focus();
            setTimeout(() => { try { w.print(); } catch (e) {} }, 400);
        };

        async function init() {
            const token = await loadUserData();
            if (!token) return;
            
            document.getElementById('logoutBtn').addEventListener('click', () => {
                localStorage.clear();
                window.location.href = '../login?role=msimamizi';
            });
            
            const toggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
            
            await fetchData(token);
            loading = false;
            render();
        }
        
        init();
    </script>
</body>
</html>
@endverbatim