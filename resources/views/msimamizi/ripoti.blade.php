@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Ripoti - Dukamkononi Msimamizi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        /* Layout */
        .msimamizi-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px; background: white; border-right: 1px solid #ecf0f1;
            position: fixed; left: 0; top: 0; bottom: 0; z-index: 100;
            transition: transform 0.3s; display: flex; flex-direction: column;
            box-shadow: 2px 0 12px rgba(0,0,0,0.05);
        }
        .sidebar-header { padding: 30px 24px; border-bottom: 1px solid #ecf0f1; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 45px; height: 45px; background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 24px; font-weight: bold;
        }
        .logo-text h2 { font-size: 18px; font-weight: 800; color: #2c3e50; }
        .logo-text p { font-size: 12px; color: #7f8c8d; }
        .nav-items { flex: 1; padding: 0 16px; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 14px 18px;
            margin-bottom: 8px; border-radius: 14px; text-decoration: none;
            color: #5d6d7e; font-weight: 500; transition: all 0.2s;
        }
        .nav-item:hover, .nav-item.active { background-color: #fdeaea; color: #e74c3c; }
        .nav-icon { font-size: 20px; width: 24px; }
        .nav-label { font-size: 15px; font-weight: 600; }
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid #ecf0f1; margin-top: auto; }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .user-avatar {
            width: 45px; height: 45px; background: #fdeaea; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #e74c3c; font-weight: bold; font-size: 18px; overflow: hidden;
        }
        .customer-avatar {
            width: 42px; height: 42px; border-radius: 50%; background: #eef4fa;
            color: #7a8ba0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        svg.icon { flex-shrink: 0; }
        .user-name { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .user-role { font-size: 12px; color: #e74c3c; font-weight: 600; }
        .logout-btn {
            display: flex; align-items: center; gap: 10px; padding: 12px 16px;
            background: #f8f9fa; border-radius: 12px; cursor: pointer;
            color: #e74c3c; font-weight: 600; font-size: 14px;
        }
        .main-content { flex: 1; margin-left: 280px; padding: 20px 24px 40px; }
        .mobile-menu-toggle {
            display: none; position: fixed; top: 16px; left: 16px; z-index: 200;
            background: white; padding: 12px; border-radius: 10px; cursor: pointer;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
        }

        /* Report Styles */
        .container { max-width: 1200px; margin: 0 auto; }
        .header-card { background: white; padding: 20px; border-radius: 20px; margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: 800; color: #2c3e50; }
        .user-email { font-size: 14px; color: #7f8c8d; margin-top: 4px; }
        .role-badge { font-size: 12px; color: #3498db; margin-top: 4px; font-weight: 600; }
        
        /* Navigation Tabs */
        .report-nav {
            display: flex; gap: 8px; background: white; padding: 8px;
            border-radius: 16px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .nav-tab {
            flex: 1; display: flex; align-items: center; justify-content: center;
            gap: 8px; padding: 12px; border-radius: 12px; cursor: pointer;
            color: #666; font-weight: 600; transition: all 0.2s;
        }
        .nav-tab.active { background: #3498db; color: white; }

        /* Search Bar */
        .report-search { position: relative; margin-bottom: 20px; }
        .report-search input {
            width: 100%; padding: 14px 44px; border: 1px solid #ecf0f1;
            border-radius: 14px; font-family: 'Inter', sans-serif; font-size: 14px;
            color: #2c3e50; background: white; outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .report-search input:focus { border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,0.15); }
        .report-search .search-icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            font-size: 15px; color: #95a5a6; pointer-events: none;
        }
        .report-search .search-clear {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: #95a5a6; font-size: 14px; font-weight: 700; padding: 4px;
        }
        .report-search .search-clear:hover { color: #e74c3c; }
        .no-results { text-align: center; padding: 40px; background: white; border-radius: 16px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card {
            background: white; padding: 16px; border-radius: 16px; text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-icon { width: 48px; height: 48px; border-radius: 24px; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; }
        .stat-number { font-size: 20px; font-weight: 800; color: #2c3e50; }
        .stat-label { font-size: 12px; color: #7f8c8d; margin-top: 4px; }
        
        .extra-stats { background: #f8f9fa; padding: 12px; border-radius: 12px; margin-top: 12px; }
        .extra-stat { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 13px; }
        
        /* Business Header */
        .business-card { background: white; padding: 20px; border-radius: 16px; margin-bottom: 20px; }
        .business-name { font-size: 18px; font-weight: 700; color: #2c3e50; }
        .business-location { font-size: 14px; color: #7f8c8d; margin-top: 4px; }
        
        /* Section */
        .section { margin-bottom: 24px; }
        .section-title { font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 16px; }
        
        /* Product Tabs */
        .product-tabs { display: flex; gap: 8px; background: white; padding: 6px; border-radius: 12px; margin-bottom: 16px; }
        .product-tab {
            flex: 1; display: flex; align-items: center; justify-content: center;
            gap: 6px; padding: 10px; border-radius: 10px; cursor: pointer;
            color: #666; font-weight: 600; font-size: 13px;
        }
        .product-tab.active { background: #3498db; color: white; }
        
        /* List Items */
        .item-card {
            background: white; padding: 16px; border-radius: 12px; margin-bottom: 10px;
            display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .item-info { flex: 1; }
        .item-title { font-size: 16px; font-weight: 700; color: #2c3e50; }
        .item-subtitle { font-size: 13px; color: #7f8c8d; margin-top: 4px; }
        .item-meta { font-size: 11px; color: #95a5a6; margin-top: 6px; }
        .item-side { text-align: right; min-width: 120px; }
        .item-amount { font-size: 16px; font-weight: 700; color: #27ae60; }
        .profit-text { font-size: 12px; font-weight: 600; margin-top: 4px; }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 10px; margin-top: 8px; }
        .badge-success { background: #27ae6020; color: #27ae60; }
        .badge-warning { background: #f39c1220; color: #f39c12; }
        
        .customer-item { display: flex; gap: 12px; background: white; padding: 16px; border-radius: 12px; margin-bottom: 10px; }
        .customer-info { flex: 1; }
        .customer-name { font-size: 16px; font-weight: 700; color: #2c3e50; }
        .customer-contact { display: flex; gap: 12px; margin-top: 6px; flex-wrap: wrap; }
        .customer-meta { display: flex; gap: 12px; margin-top: 6px; font-size: 11px; color: #95a5a6; }
        .customer-stats { text-align: right; }
        .customer-total { font-size: 16px; font-weight: 700; color: #27ae60; }
        
        .no-data { text-align: center; padding: 40px; background: white; border-radius: 16px; }
        .no-data-icon { font-size: 48px; margin-bottom: 12px; }
        
        .export-buttons { display: flex; gap: 12px; margin-top: 16px; }
        .export-btn {
            flex: 1; display: flex; align-items: center; justify-content: center;
            gap: 8px; padding: 12px; background: white; border-radius: 12px;
            cursor: pointer; font-weight: 600;
        }
        
        .status-card { display: flex; align-items: center; gap: 12px; background: white; padding: 16px; border-radius: 12px; margin-top: 20px; }
        
        .loading-spinner { border: 2px solid #ddd; border-top-color: #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 0.6s linear infinite; margin: 0 auto 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .hidden { display: none; }
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
            <div class="sidebar-header"><div class="logo-area"><div class="logo-icon">D</div><div class="logo-text"><h2>DukaMkononi</h2><p>Msimamizi Portal</p></div></div></div>
            <div class="nav-items">
                <a href="index" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-house"></i></div><span class="nav-label">Nyumbani</span></a>
                <a href="ripoti" class="nav-item active"><div class="nav-icon"><i class="fa-solid fa-chart-simple"></i></div><span class="nav-label">Ripoti</span></a>
                <a href="preview" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-calendar-days"></i></div><span class="nav-label">Rejea</span></a>
                <a href="bidhaa-mpya" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-circle-plus"></i></div><span class="nav-label">Bidhaa Mpya</span></a>
                <a href="tangaza" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-bullhorn"></i></div><span class="nav-label">Tangaza</span></a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info"><div class="user-avatar" id="userAvatar">M</div><div class="user-details"><div class="user-name" id="userName">Msimamizi</div><div class="user-role">Msimamizi</div></div></div>
                <div class="logout-btn" id="logoutBtn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Ondoka</div>
            </div>
        </aside>
        <main class="main-content" id="mainContent"><div style="text-align:center;padding:60px;"><div class="loading-spinner"></div><div>Inapakua ripoti...</div></div></main>
    </div>

    <script>
        const API_BASE_URL = '';
        let userData = { id: '', email: '', businessName: '', businessLocation: '', role: '' };
        let businessStats = null;
        let allProducts = [], soldProducts = [], unsoldProducts = [];
        let customers = [], sales = [], sellers = [];
        let loading = true, refreshing = false;
        let activeReport = 'overview';
        let activeProductTab = 'sold';
        let searchTerm = '';
        let dataSource = 'seller';
        let userToken = null;

        function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
        function formatCurrency(amount) { return `TSh ${(amount || 0).toLocaleString()}`; }
        function formatDate(dateStr) { try { return new Date(dateStr).toLocaleDateString('sw-TZ'); } catch { return dateStr; } }

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

        function showAlert(title, message, onOk) {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:2000;';
            const box = document.createElement('div');
            box.style.cssText = 'background:white;border-radius:28px;width:85%;max-width:320px;padding:24px;text-align:center;';
            box.innerHTML = `<div style="font-size:20px;font-weight:800;margin-bottom:12px;">${escapeHtml(title)}</div>
                <div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;">${escapeHtml(message)}</div>
                <div style="background:#e74c3c;padding:12px;border-radius:40px;color:white;cursor:pointer;">Sawa</div>`;
            box.querySelector('div:last-child').onclick = () => { overlay.remove(); if(onOk) onOk(); };
            overlay.appendChild(box);
            document.body.appendChild(overlay);
        }

        async function loadUserData() {
            userToken = localStorage.getItem('userToken');
            const userStr = localStorage.getItem('userData');
            if (!userToken || !userStr) {
                showAlert('Hitilafu', 'Tafadhali ingia tena', () => window.location.href = '../login?role=msimamizi');
                return false;
            }
            const user = JSON.parse(userStr);
            userData = {
                id: user.id || user.userId || '',
                email: user.email || '',
                businessName: user.businessName || user.business_name || 'Biashara Yangu',
                businessLocation: user.businessLocation || user.business_location || 'Eneo haijajazwa',
                role: user.role || ''
            };
            document.getElementById('userName').innerHTML = escapeHtml(user.full_name || user.email?.split('@')[0] || 'Msimamizi');
            const avatarEl = document.getElementById('userAvatar');
            if (user.business_logo_url) {
                avatarEl.classList.add('js-avatar-view');
avatarEl.setAttribute('data-full', user.business_logo_url);
avatarEl.setAttribute('data-name', displayName);
avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="Picha">`;
            } else {
                avatarEl.innerHTML = (user.full_name || user.email?.charAt(0) || 'M').charAt(0).toUpperCase();
            }
            return true;
        }

        async function fetchAllReports() {
            if (!userToken) return;
            loading = true; renderLoading();
            try {
                const headers = { 'Content-Type': 'application/json', 'Authorization': `Bearer ${userToken}` };
                if (userData.role === 'admin') {
                    await fetchBusinessData(headers);
                    dataSource = 'admin';
                } else {
                    await fetchSellerData(headers);
                    dataSource = 'seller';
                }
            } catch(e) { console.error(e); showAlert('Hitilafu', 'Imeshindikana kupakua ripoti'); }
            finally { loading = false; render(); }
        }

        async function fetchBusinessData(headers) {
            // Fetch users — server-side business filter (?business=) so only
            // this business's users leave the database.
            const sellersRes = await fetch(`${API_BASE_URL}/api/admin/users?business=${encodeURIComponent(userData.businessName)}`, { headers });
            if (sellersRes.ok) {
                const data = await sellersRes.json();
                const users = data.users || (Array.isArray(data) ? data : []);
                sellers = users.filter(u => u.business_name === userData.businessName && u.status === 'approved');
            }
            
            // Fetch products (server-side business filter avoids downloading
            // every business's products and PostgREST's 1000-row cap cutting
            // this business's rows off)
            const bizQuery = `business_name=${encodeURIComponent(userData.businessName)}`;
            const productsRes = await fetch(`${API_BASE_URL}/api/admin/products?${bizQuery}`, { headers });
            let rawProducts = [];
            if (productsRes.ok) {
                const data = await productsRes.json();
                const prods = data.products || (Array.isArray(data) ? data : []);
                rawProducts = prods.filter(p => sellers.some(s => s.id === p.seller_id)).map(p => ({
                    id: p.id, name: p.name, price: p.price || 0, expected_selling_price: p.expected_selling_price || p.price || 0,
                    category: p.category, stock: p.stock || 0, seller_id: p.seller_id, created_at: p.created_at
                }));
            }
            
            // Fetch sales (server-side business filter)
            const salesRes = await fetch(`${API_BASE_URL}/api/admin/sales?${bizQuery}`, { headers });
            let allSales = [];
            if (salesRes.ok) {
                const data = await salesRes.json();
                const salesData = data.sales || (Array.isArray(data) ? data : []);
                salesData.forEach(sale => {
                    const seller = sellers.find(s => s.id === sale.seller_id);
                    if (seller && seller.business_name === userData.businessName && sale.sale_items) {
                        sale.sale_items.forEach(item => {
                            const product = rawProducts.find(p => p.id === item.product_id);
                            allSales.push({
                                id: sale.id, product_id: item.product_id, product_name: product?.name || 'Bidhaa',
                                quantity: item.quantity || 1, unit_price: item.unit_price || 0,
                                total_amount: item.total_price || (item.unit_price * item.quantity),
                                sale_date: sale.sale_date?.split('T')[0] || new Date().toISOString().split('T')[0],
                                customer_name: sale.customers?.name || 'Mteja', seller_name: seller.full_name || seller.email,
                                cost_price: product?.price || 0, profit: 0
                            });
                        });
                    }
                });
                allSales = allSales.map(s => ({ ...s, profit: (s.unit_price - s.cost_price) * s.quantity }));
                sales = allSales;
            }
            
            // Process products
            const productSalesMap = new Map();
            sales.forEach(sale => {
                if (!productSalesMap.has(sale.product_id)) productSalesMap.set(sale.product_id, { totalSold: 0, totalRevenue: 0, totalProfit: 0 });
                const data = productSalesMap.get(sale.product_id);
                data.totalSold += sale.quantity;
                data.totalRevenue += sale.total_amount;
                data.totalProfit += sale.profit;
            });
            allProducts = rawProducts.map(p => {
                const salesData = productSalesMap.get(p.id);
                return { ...p, total_sold: salesData?.totalSold || 0, total_revenue: salesData?.totalRevenue || 0, total_profit: salesData?.totalProfit || 0, has_sales: !!salesData };
            });
            soldProducts = allProducts.filter(p => p.has_sales);
            unsoldProducts = allProducts.filter(p => !p.has_sales);
            
            // Fetch customers (server-side business filter)
            const customersRes = await fetch(`${API_BASE_URL}/api/admin/customers?${bizQuery}`, { headers });
            if (customersRes.ok) {
                const data = await customersRes.json();
                const custs = data.customers || (Array.isArray(data) ? data : []);
                customers = custs.filter(c => sellers.some(s => s.id === c.seller_id)).map(c => ({ ...c, total_purchases: c.total_purchases / 2, purchases_count: Math.round(c.purchases_count / 2) }));
            }
            
            // Stats
            const totalSales = sales.reduce((s, sale) => s + sale.total_amount, 0);
            const totalProfit = sales.reduce((s, sale) => s + sale.profit, 0);
            const today = new Date().toISOString().split('T')[0];
            const todaySales = sales.filter(s => s.sale_date === today).reduce((s, sale) => s + sale.total_amount, 0);
            const todayProfit = sales.filter(s => s.sale_date === today).reduce((s, sale) => s + sale.profit, 0);
            const totalCost = sales.reduce((s, sale) => s + (sale.cost_price * sale.quantity), 0);
            const avgMargin = totalCost > 0 ? (totalProfit / totalCost) * 100 : 0;
            businessStats = { totalSales, totalProfit, totalCustomers: customers.length, totalProducts: allProducts.length, totalSellers: sellers.length, todaySales, todayProfit, averageProfitMargin: avgMargin };
        }

        async function fetchSellerData(headers) {
            // Products
            const productsRes = await fetch(`${API_BASE_URL}/api/products/my`, { headers });
            let rawProducts = [];
            if (productsRes.ok) {
                const data = await productsRes.json();
                rawProducts = (Array.isArray(data) ? data : []).map(p => ({
                    id: p.id, name: p.name, price: p.price || 0, expected_selling_price: p.expected_selling_price || p.price || 0,
                    category: p.category, stock: p.stock || 0, seller_id: userData.id
                }));
            }
            // Sales
            const salesRes = await fetch(`${API_BASE_URL}/api/sales/my`, { headers });
            let allSales = [];
            if (salesRes.ok) {
                const data = await salesRes.json();
                const salesData = Array.isArray(data) ? data : [];
                salesData.forEach(sale => {
                    if (sale.sale_items) {
                        sale.sale_items.forEach(item => {
                            const product = rawProducts.find(p => p.id === item.product_id);
                            allSales.push({
                                id: sale.id, product_id: item.product_id, product_name: product?.name || 'Bidhaa',
                                quantity: item.quantity || 1, unit_price: item.unit_price || 0,
                                total_amount: item.total_price || (item.unit_price * item.quantity),
                                sale_date: sale.sale_date?.split('T')[0] || new Date().toISOString().split('T')[0],
                                customer_name: sale.customers?.name || 'Mteja', seller_name: userData.businessName,
                                cost_price: product?.price || 0, profit: 0
                            });
                        });
                    }
                });
                allSales = allSales.map(s => ({ ...s, profit: (s.unit_price - s.cost_price) * s.quantity }));
                sales = allSales;
            }
            // Process products
            const productSalesMap = new Map();
            sales.forEach(sale => {
                if (!productSalesMap.has(sale.product_id)) productSalesMap.set(sale.product_id, { totalSold: 0, totalRevenue: 0, totalProfit: 0 });
                const data = productSalesMap.get(sale.product_id);
                data.totalSold += sale.quantity;
                data.totalRevenue += sale.total_amount;
                data.totalProfit += sale.profit;
            });
            allProducts = rawProducts.map(p => {
                const salesData = productSalesMap.get(p.id);
                return { ...p, total_sold: salesData?.totalSold || 0, total_revenue: salesData?.totalRevenue || 0, total_profit: salesData?.totalProfit || 0, has_sales: !!salesData };
            });
            soldProducts = allProducts.filter(p => p.has_sales);
            unsoldProducts = allProducts.filter(p => !p.has_sales);
            // Customers
            const customersRes = await fetch(`${API_BASE_URL}/api/customers/my`, { headers });
            if (customersRes.ok) {
                const data = await customersRes.json();
                customers = (Array.isArray(data) ? data : []).map(c => ({ ...c, total_purchases: c.total_purchases / 2, purchases_count: Math.round(c.purchases_count / 2) }));
            }
            // Stats
            const totalSales = sales.reduce((s, sale) => s + sale.total_amount, 0);
            const totalProfit = sales.reduce((s, sale) => s + sale.profit, 0);
            const today = new Date().toISOString().split('T')[0];
            const todaySales = sales.filter(s => s.sale_date === today).reduce((s, sale) => s + sale.total_amount, 0);
            const todayProfit = sales.filter(s => s.sale_date === today).reduce((s, sale) => s + sale.profit, 0);
            const totalCost = sales.reduce((s, sale) => s + (sale.cost_price * sale.quantity), 0);
            const avgMargin = totalCost > 0 ? (totalProfit / totalCost) * 100 : 0;
            businessStats = { totalSales, totalProfit, totalCustomers: customers.length, totalProducts: allProducts.length, totalSellers: 1, todaySales, todayProfit, averageProfitMargin: avgMargin };
            sellers = [{ id: userData.id, full_name: userData.businessName, email: userData.email }];
        }

        function refreshData() { fetchAllReports(); }

        function renderOverview() {
            const isAdmin = userData.role === 'admin';
            return `
                <div class="business-card">
                    <div class="business-name">${escapeHtml(userData.businessName)}</div>
                    <div class="business-location">${ic('location', 13)} ${escapeHtml(userData.businessLocation)}</div>
                    <div style="font-size:12px;color:#3498db;margin-top:6px;">Ripoti ya ${isAdmin ? 'Biashara' : 'Binafsi'} - ${new Date().toLocaleDateString('sw-TZ')}</div>
                    ${isAdmin ? `<div style="font-size:11px;color:#7f8c8d;margin-top:4px;">${ic('users', 12)} Wauzaji ${sellers.length} waliosajiliwa</div>` : ''}
                </div>
                ${businessStats ? `
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon" style="background:#3498db20;color:#3498db;">${ic('money', 22)}</div><div class="stat-number">${formatCurrency(businessStats.totalSales)}</div><div class="stat-label">Jumla ya Mauzo</div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:#27ae6020;color:#27ae60;">${ic('chart', 22)}</div><div class="stat-number" style="color:#27ae60;">${formatCurrency(businessStats.totalProfit)}</div><div class="stat-label">Jumla ya Faida</div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:#2ecc7120;color:#2c7e5f;">${ic('users', 22)}</div><div class="stat-number">${businessStats.totalCustomers}</div><div class="stat-label">Wateja</div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:#e74c3c20;color:#e74c3c;">${ic('box', 22)}</div><div class="stat-number">${businessStats.totalProducts}</div><div class="stat-label">Bidhaa Zote</div></div>
                </div>
                <div class="extra-stats">
                    <div class="extra-stat">${ic('calendar', 13)} Mauzo ya Leo: ${formatCurrency(businessStats.todaySales)}</div>
                    <div class="extra-stat">${ic('chart', 13)} Faida ya Leo: ${formatCurrency(businessStats.todayProfit)}</div>
                    <div class="extra-stat">${ic('report', 13)} Wastani wa Margin: ${businessStats.averageProfitMargin.toFixed(1)}%</div>
                </div>
                ` : ''}
                <div class="section"><div class="section-title">Mauzo ya Hivi Karibuni</div>
                ${sales.length > 0 ? sales.slice(0,5).map(sale => `
                    <div class="item-card"><div class="item-info"><div class="item-title">${escapeHtml(sale.product_name)}</div><div class="item-subtitle">${formatDate(sale.sale_date)} - ${escapeHtml(sale.customer_name)}</div><div class="item-meta">${sale.quantity} × ${formatCurrency(sale.unit_price)}</div></div>
                    <div class="item-side"><div class="item-amount">${formatCurrency(sale.total_amount)}</div><div class="profit-text" style="color:${sale.profit >= 0 ? '#27ae60' : '#e74c3c'}">Faida: ${formatCurrency(sale.profit)}</div></div></div>
                `).join('') : '<div class="no-data"><div class="no-data-icon">' + ic('doc', 44) + '</div><div>Hakuna mauzo bado</div></div>'}</div>
            `;
        }

        // ============================== SEARCH ==============================
        // Client-side search across the mauzo / bidhaa / wateja report tabs.
        function getFilteredSales() {
            if (!searchTerm) return sales;
            return sales.filter(s =>
                (s.product_name || '').toLowerCase().includes(searchTerm) ||
                (s.customer_name || '').toLowerCase().includes(searchTerm) ||
                (s.seller_name || '').toLowerCase().includes(searchTerm) ||
                (s.sale_date || '').includes(searchTerm)
            );
        }

        function getFilteredProducts() {
            const list = activeProductTab === 'sold' ? soldProducts : unsoldProducts;
            if (!searchTerm) return list;
            return list.filter(p =>
                (p.name || '').toLowerCase().includes(searchTerm) ||
                (p.category || '').toLowerCase().includes(searchTerm)
            );
        }

        function getFilteredCustomers() {
            if (!searchTerm) return customers;
            return customers.filter(c =>
                (c.name || '').toLowerCase().includes(searchTerm) ||
                (c.phone || '').toLowerCase().includes(searchTerm) ||
                (c.email || '').toLowerCase().includes(searchTerm)
            );
        }

        function noResultsHtml(iconName) {
            return `<div class="no-results"><div class="no-data-icon">${ic(iconName, 44)}</div><div>Hakuna matokeo yanayolingana na utafutaji wako</div></div>`;
        }

        function searchResultsText(filteredCount, totalCount) {
            if (!searchTerm || filteredCount === totalCount) return '';
            return `<div style="font-size:12px;color:#7f8c8d;margin:-6px 0 12px;">Matokeo: ${filteredCount} kati ya ${totalCount}</div>`;
        }

        function renderSearchBar() {
            const placeholders = {
                sales: 'Tafuta mauzo kwa bidhaa, mteja, muuzaji au tarehe...',
                products: 'Tafuta bidhaa kwa jina au kategoria...',
                customers: 'Tafuta mteja kwa jina, simu au barua pepe...'
            };
            const safeTerm = String(searchTerm).replace(/"/g, '&quot;');
            return `
                <div class="report-search">
                    <span class="search-icon">${ic('search', 15)}</span>
                    <input type="text" id="reportSearch" placeholder="${placeholders[activeReport] || 'Tafuta...'}" value="${safeTerm}" oninput="handleSearchInput(this)">
                    <span class="search-clear" id="searchClear" style="display:${searchTerm ? 'block' : 'none'};" onclick="clearSearch()" title="Futa utafutaji">✕</span>
                </div>
            `;
        }

        function renderSalesReport() {
            const filtered = getFilteredSales();
            const listHtml = sales.length === 0
                ? '<div class="no-data"><div class="no-data-icon">' + ic('doc', 44) + '</div><div>Hakuna mauzo bado</div></div>'
                : filtered.length === 0
                    ? noResultsHtml('doc')
                    : filtered.map(sale => `
                    <div class="item-card"><div class="item-info"><div class="item-title">${escapeHtml(sale.product_name)}</div><div class="item-subtitle">${escapeHtml(sale.customer_name)} • ${formatDate(sale.sale_date)}</div><div class="item-meta">${sale.quantity} × ${formatCurrency(sale.unit_price)}</div></div>
                    <div class="item-side"><div class="item-amount">${formatCurrency(sale.total_amount)}</div><div class="profit-text" style="color:${sale.profit >= 0 ? '#27ae60' : '#e74c3c'}">Faida: ${formatCurrency(sale.profit)}</div><div style="font-size:11px;color:#7f8c8d;">${((sale.profit/(sale.cost_price*sale.quantity||1))*100).toFixed(1)}% margin</div></div></div>
                `).join('');
            return `<div class="section"><div class="section-title">Ripoti ya Mauzo - ${escapeHtml(userData.businessName)}</div>
                ${searchResultsText(filtered.length, sales.length)}${listHtml}</div>`;
        }

        function renderProductsReport() {
            const allInTab = activeProductTab === 'sold' ? soldProducts : unsoldProducts;
            const currentProducts = getFilteredProducts();
            const isSold = activeProductTab === 'sold';
            return `
                ${searchResultsText(currentProducts.length, allInTab.length)}
                <div class="product-tabs">
                    <div class="product-tab ${activeProductTab === 'sold' ? 'active' : ''}" onclick="setProductTab('sold')">✓ Zimeuzwa (${soldProducts.length})</div>
                    <div class="product-tab ${activeProductTab === 'unsold' ? 'active' : ''}" onclick="setProductTab('unsold')">${ic('clock', 14)} Hazijauzwa (${unsoldProducts.length})</div>
                </div>
                ${allInTab.length === 0 ? `<div class="no-data"><div class="no-data-icon">${ic('box', 44)}</div><div>${isSold ? 'Hakuna bidhaa zilizouzwa bado' : 'Bidhaa zote zimeuzwa!'}</div></div>` : currentProducts.length === 0 ? noResultsHtml('box') : currentProducts.map(p => {
                    const profitPerUnit = p.expected_selling_price - p.price;
                    return `<div class="item-card" style="${!isSold ? 'border-left:3px solid #f39c12' : ''}">
                        <div class="item-info"><div class="item-title">${escapeHtml(p.name)}</div><div class="item-subtitle">${p.category || 'Hakuna kategoria'}</div>
                        <div class="item-meta">Hisa: ${p.stock} • Bei Ununuzi: ${formatCurrency(p.price)} • Bei Kuuzia: ${formatCurrency(p.expected_selling_price)}</div>
                        ${isSold ? `<div class="item-meta">Zimeuzwa: ${p.total_sold} • Mapato: ${formatCurrency(p.total_revenue)} • Faida: ${formatCurrency(p.total_profit)}</div>` : `<div class="item-meta">Inatarajiwa faida: ${formatCurrency(profitPerUnit)} (${((profitPerUnit/p.price)*100).toFixed(1)}%)</div>`}
                        </div>
                        <div class="item-side"><div class="item-amount">${isSold ? formatCurrency(p.total_revenue) : 'Bado'}</div>
                        <div class="profit-text" style="color:${isSold && p.total_profit >= 0 ? '#27ae60' : '#f39c12'}">${isSold ? `Faida: ${formatCurrency(p.total_profit)}` : `Faida/Bidhaa: ${formatCurrency(profitPerUnit)}`}</div>
                        <div class="badge ${isSold ? 'badge-success' : 'badge-warning'}">${isSold ? ic('check', 10) + ' Imeuzwa' : ic('clock', 10) + ' Hajauzwa'}</div></div>
                    </div>`;
                }).join('')}
                <div style="background:white;padding:16px;border-radius:12px;margin-top:16px;"><div style="font-weight:700;text-align:center;margin-bottom:12px;">Muhtasari wa Bidhaa</div>
                <div style="display:flex;justify-content:space-around;"><div><div style="font-size:20px;font-weight:700;">${allProducts.length}</div><div>Jumla</div></div>
                <div><div style="font-size:20px;font-weight:700;color:#27ae60;">${soldProducts.length}</div><div>Zimeuzwa</div></div>
                <div><div style="font-size:20px;font-weight:700;color:#f39c12;">${unsoldProducts.length}</div><div>Hazijauzwa</div></div>
                <div><div style="font-size:20px;font-weight:700;">${allProducts.length ? Math.round((soldProducts.length/allProducts.length)*100) : 0}%</div><div>% Zimeuzwa</div></div></div></div>
            `;
        }

        function renderCustomersReport() {
            const filtered = getFilteredCustomers();
            const listHtml = customers.length === 0
                ? '<div class="no-data"><div class="no-data-icon">' + ic('users', 44) + '</div><div>Hakuna wateja bado</div></div>'
                : filtered.length === 0
                    ? noResultsHtml('users')
                    : filtered.map(c => `
                    <div class="customer-item"><div class="customer-avatar">${ic('user', 26)}</div><div class="customer-info"><div class="customer-name">${escapeHtml(c.name)}</div>
                    <div class="customer-contact">${c.phone ? `${ic('phone', 12)} ${escapeHtml(c.phone)}` : ''} ${c.email ? `${ic('mail', 12)} ${escapeHtml(c.email)}` : ''}</div>
                    <div class="customer-meta">${ic('doc', 12)} ${c.purchases_count} mauzo • ${ic('calendar', 12)} ${c.last_purchase_date ? formatDate(c.last_purchase_date) : 'Hajapata'}</div></div>
                    <div class="customer-stats"><div class="customer-total">${formatCurrency(c.total_purchases)}</div><div>Jumla ya Kununua</div></div></div>
                `).join('');
            return `<div class="section"><div class="section-title">Ripoti ya Wateja - ${escapeHtml(userData.businessName)}</div>
                ${searchResultsText(filtered.length, customers.length)}${listHtml}</div>`;
        }

        function render() {
            const isAdmin = userData.role === 'admin';
            const container = document.getElementById('mainContent');
            container.innerHTML = `
                <div class="container">
                    <div class="header-card"><div class="title">Ripoti Kamili</div><div class="user-email">${escapeHtml(userData.email)}</div><div class="role-badge">${isAdmin ? ic('crown', 12) + ' Admin' : ic('user', 12) + ' Seller'} • ${dataSource === 'admin' ? 'Data ya Biashara Nzima' : 'Data ya Seller'}</div></div>
                    <div class="report-nav">
                        <div class="nav-tab ${activeReport === 'overview' ? 'active' : ''}" onclick="setReport('overview')">${ic('report', 15)} Mapitio</div>
                        <div class="nav-tab ${activeReport === 'sales' ? 'active' : ''}" onclick="setReport('sales')">${ic('money', 15)} Mauzo (${sales.length})</div>
                        <div class="nav-tab ${activeReport === 'products' ? 'active' : ''}" onclick="setReport('products')">${ic('box', 15)} Bidhaa (${allProducts.length})</div>
                        <div class="nav-tab ${activeReport === 'customers' ? 'active' : ''}" onclick="setReport('customers')">${ic('users', 15)} Wateja (${customers.length})</div>
                    </div>
                    ${activeReport !== 'overview' ? renderSearchBar() : ''}
                    <div id="reportContent"></div>
                    <div class="section"><div class="section-title">Toa Ripoti</div><div class="export-buttons"><div class="export-btn" onclick="exportPDF()">${ic('filePdf', 15)} PDF</div><div class="export-btn" onclick="exportExcel()">${ic('excel', 15)} Excel</div><div class="export-btn" onclick="exportPrint()">${ic('print', 15)} Print</div></div></div>
                    <div class="status-card"><div>${dataSource === 'admin' ? ic('check', 13) + ' Inaonyesha data ya biashara yote' : ic('info', 13) + ' Inaonyesha data ya seller mmoja tu'}</div></div>
                </div>
            `;
            renderReportContent();
        }

        function renderReportContent() {
            const reportContent = document.getElementById('reportContent');
            if (!reportContent) return;
            if (activeReport === 'overview') reportContent.innerHTML = renderOverview();
            else if (activeReport === 'sales') reportContent.innerHTML = renderSalesReport();
            else if (activeReport === 'products') reportContent.innerHTML = renderProductsReport();
            else if (activeReport === 'customers') reportContent.innerHTML = renderCustomersReport();
        }

        function renderLoading() { document.getElementById('mainContent').innerHTML = '<div style="text-align:center;padding:60px;"><div class="loading-spinner"></div><div>Inapakua ripoti...</div></div>'; }

        // ============================== EXPORTS ==============================
        // Builds a standalone HTML document (title + table) for the report tab
        // currently active. Used by both the PDF and Print buttons.
        function buildExportTable() {
            const th = (t) => `<th style="border:1px solid #bdc3c7;padding:6px 10px;background:#f4f6f7;text-align:left;">${escapeHtml(t)}</th>`;
            const td = (v) => `<td style="border:1px solid #ecf0f1;padding:6px 10px;">${escapeHtml(String(v ?? ''))}</td>`;
            let title = 'Ripoti';
            let rows = [];
            if (activeReport === 'sales') {
                title = 'Ripoti ya Mauzo - ' + userData.businessName;
                rows = [['Bidhaa', 'Mteja', 'Muuzaji', 'Tarehe', 'Idadi', 'Bei', 'Jumla', 'Faida'],
                    ...sales.map(s => [s.product_name, s.customer_name, s.seller_name, s.sale_date, s.quantity, s.unit_price, s.total_amount, s.profit])];
            } else if (activeReport === 'products') {
                const list = activeProductTab === 'sold' ? soldProducts : unsoldProducts;
                title = (activeProductTab === 'sold' ? 'Bidhaa Zilizouzwa' : 'Bidhaa Zisizouzwa') + ' - ' + userData.businessName;
                rows = [['Bidhaa', 'Kategoria', 'Hisa', 'Bei Ununuzi', 'Bei Kuuzia', 'Zilizouzwa', 'Mapato', 'Faida'],
                    ...list.map(p => [p.name, p.category || '', p.stock, p.price, p.expected_selling_price, p.total_sold || 0, p.total_revenue || 0, p.total_profit || 0])];
            } else if (activeReport === 'customers') {
                title = 'Ripoti ya Wateja - ' + userData.businessName;
                rows = [['Jina', 'Simu', 'Email', 'Mauzo', 'Jumla'],
                    ...customers.map(c => [c.name, c.phone || '', c.email || '', c.purchases_count || 0, c.total_purchases || 0])];
            } else {
                title = 'Mapitio - ' + userData.businessName;
                rows = [['Kipimo', 'Thamani'],
                    ['Jumla ya Mauzo', businessStats ? businessStats.totalSales : 0],
                    ['Jumla ya Faida', businessStats ? businessStats.totalProfit : 0],
                    ['Wateja', businessStats ? businessStats.totalCustomers : 0],
                    ['Bidhaa Zote', businessStats ? businessStats.totalProducts : 0],
                    ['Wauzaji', businessStats ? businessStats.totalSellers : 0],
                    ['Mauzo ya Leo', businessStats ? businessStats.todaySales : 0],
                    ['Faida ya Leo', businessStats ? businessStats.todayProfit : 0],
                    ['Wastani wa Margin %', businessStats ? businessStats.averageProfitMargin.toFixed(1) : 0]];
            }
            const head = rows[0].map(th).join('');
            const body = rows.slice(1).map(r => `<tr>${r.map(td).join('')}</tr>`).join('');
            return `<html><head><meta charset="UTF-8"><title>${escapeHtml(title)}</title></head>
                <body><h2 style="font-family:sans-serif;">${escapeHtml(title)}</h2>
                <p style="font-family:sans-serif;font-size:12px;color:#555;">Imetolewa ${new Date().toLocaleString('sw-TZ')} — DukaMkononi</p>
                <table style="border-collapse:collapse;font-family:sans-serif;font-size:12px;"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></body></html>`;
        }

        // PDF: opens the report in a new window and triggers the browser's
        // print dialog, whose destination includes "Save as PDF".
        window.exportPDF = function () {
            const w = window.open('', '_blank');
            if (!w) { showAlert('Hitilafu', 'Tafadhali ruhusu popups ili kupakua PDF'); return; }
            w.document.open();
            w.document.write(buildExportTable());
            w.document.close();
            w.focus();
            setTimeout(() => { try { w.print(); } catch (e) {} }, 400);
        };

        // Print: same document, print dialog in the popup.
        window.exportPrint = function () {
            const w = window.open('', '_blank');
            if (!w) { showAlert('Hitilafu', 'Tafadhali ruhusu popups ili kuchapisha'); return; }
            w.document.open();
            w.document.write(buildExportTable());
            w.document.close();
            w.focus();
            setTimeout(() => { try { w.print(); } catch (e) {} }, 400);
        };

        // Excel: CSV download (opens natively in Excel). RFC 4180 escaping and
        // a BOM so Excel handles commas/quotes/newlines and UTF-8 correctly.
        window.exportExcel = function () {
            const esc = (v) => { const s = String(v ?? ''); return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
            const row = (arr) => arr.map(esc).join(',');
            const lines = [];
            if (activeReport === 'sales') {
                lines.push(row(['Bidhaa', 'Mteja', 'Muuzaji', 'Tarehe', 'Idadi', 'Bei', 'Jumla', 'Faida']));
                sales.forEach(s => lines.push(row([s.product_name, s.customer_name, s.seller_name, s.sale_date, s.quantity, s.unit_price, s.total_amount, s.profit])));
            } else if (activeReport === 'products') {
                const list = activeProductTab === 'sold' ? soldProducts : unsoldProducts;
                lines.push(row(['Bidhaa', 'Kategoria', 'Hisa', 'Bei Ununuzi', 'Bei Kuuzia', 'Zilizouzwa', 'Mapato', 'Faida']));
                list.forEach(p => lines.push(row([p.name, p.category || '', p.stock, p.price, p.expected_selling_price, p.total_sold || 0, p.total_revenue || 0, p.total_profit || 0])));
            } else if (activeReport === 'customers') {
                lines.push(row(['Jina', 'Simu', 'Email', 'Mauzo', 'Jumla']));
                customers.forEach(c => lines.push(row([c.name, c.phone || '', c.email || '', c.purchases_count || 0, c.total_purchases || 0])));
            } else {
                lines.push(row(['Kipimo', 'Thamani']));
                lines.push(row(['Jumla ya Mauzo', businessStats ? businessStats.totalSales : 0]));
                lines.push(row(['Jumla ya Faida', businessStats ? businessStats.totalProfit : 0]));
                lines.push(row(['Wateja', businessStats ? businessStats.totalCustomers : 0]));
                lines.push(row(['Bidhaa Zote', businessStats ? businessStats.totalProducts : 0]));
                lines.push(row(['Wauzaji', businessStats ? businessStats.totalSellers : 0]));
                lines.push(row(['Mauzo ya Leo', businessStats ? businessStats.todaySales : 0]));
                lines.push(row(['Faida ya Leo', businessStats ? businessStats.todayProfit : 0]));
                lines.push(row(['Wastani wa Margin %', businessStats ? businessStats.averageProfitMargin.toFixed(1) : 0]));
            }
            const blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'DukaMkononi-' + activeReport + '-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
        };

        window.setReport = (report) => { activeReport = report; searchTerm = ''; render(); };

        // Search input handlers: only re-render the report list so the input
        // keeps focus while typing.
        window.handleSearchInput = (el) => {
            searchTerm = el.value.trim().toLowerCase();
            const clearBtn = document.getElementById('searchClear');
            if (clearBtn) clearBtn.style.display = el.value ? 'block' : 'none';
            renderReportContent();
        };

        window.clearSearch = () => { searchTerm = ''; render(); };
        window.setProductTab = (tab) => { activeProductTab = tab; render(); };

        function setupSidebar() {
            document.getElementById('mobileMenuToggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
            document.getElementById('logoutBtn').addEventListener('click', () => { localStorage.clear(); window.location.href = '../login?role=msimamizi'; });
        }

        async function init() {
            setupSidebar();
            const success = await loadUserData();
            if (success) await fetchAllReports();
            else renderLoading();
        }
        init();
    </script>
</body>
</html>
@endverbatim