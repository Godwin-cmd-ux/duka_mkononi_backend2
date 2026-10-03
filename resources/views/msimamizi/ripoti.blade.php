@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="msimamizi_ripoti.page_title">Ripoti - Dukamkononi Msimamizi</title>
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
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@verbatim
    <div class="mobile-menu-toggle" id="mobileMenuToggle">☰</div>
    <div class="msimamizi-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><div class="logo-area"><div class="logo-icon">D</div><div class="logo-text"><h2 data-i18n="msimamizi_ripoti.brand_name">DukaMkononi</h2><p data-i18n="msimamizi_ripoti.portal_name">Msimamizi Portal</p></div></div></div>
            <div class="nav-items">
                <a href="index" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-house"></i></div><span class="nav-label" data-i18n="msimamizi_ripoti.nav_home">Nyumbani</span></a>
                <a href="ripoti" class="nav-item active"><div class="nav-icon"><i class="fa-solid fa-chart-simple"></i></div><span class="nav-label" data-i18n="msimamizi_ripoti.nav_reports">Ripoti</span></a>
                <a href="preview" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-calendar-days"></i></div><span class="nav-label" data-i18n="msimamizi_ripoti.nav_review">Rejea</span></a>
                <a href="bidhaa-mpya" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-circle-plus"></i></div><span class="nav-label" data-i18n="msimamizi_ripoti.nav_new_product">Bidhaa Mpya</span></a>
                <a href="tangaza" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-bullhorn"></i></div><span class="nav-label" data-i18n="msimamizi_ripoti.nav_advertise">Tangaza</span></a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info"><div class="user-avatar" id="userAvatar">M</div><div class="user-details"><div class="user-name" id="userName" data-i18n="msimamizi_ripoti.user_admin">Msimamizi</div><div class="user-role" data-i18n="msimamizi_ripoti.user_admin">Msimamizi</div></div></div>
                <div class="logout-btn" id="logoutBtn"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span data-i18n="msimamizi_ripoti.logout">Ondoka</span></div>
            </div>
        </aside>
        <main class="main-content" id="mainContent"><div style="text-align:center;padding:60px;"><div class="loading-spinner"></div><div data-i18n="msimamizi_ripoti.loading">Inapakua ripoti...</div></div></main>
    </div>

    <script>
        const API_BASE_URL = '';

        // ---- i18n ----
        // Swahili source strings, used until the shared runtime has fetched the
        // catalog. t() prefers window.DM (all 8 locales) and falls back to these.
        const SW = {
            page_title: "Ripoti - Dukamkononi Msimamizi",
            brand_name: "DukaMkononi",
            portal_name: "Msimamizi Portal",
            nav_home: "Nyumbani",
            nav_reports: "Ripoti",
            nav_review: "Rejea",
            nav_new_product: "Bidhaa Mpya",
            nav_advertise: "Tangaza",
            user_admin: "Msimamizi",
            logout: "Ondoka",
            loading: "Inapakua ripoti...",
            alert_ok: "Sawa",
            alert_error_title: "Hitilafu",
            alert_sign_in_again: "Tafadhali ingia tena",
            business_mine: "Biashara Yangu",
            location_not_filled: "Eneo haijajazwa",
            avatar_alt: "Picha",
            customer_generic: "Mteja",
            product_generic: "Bidhaa",
            currency_prefix: "TSh ",
            unknown_cost_note: "{count} haijaweka bei ya kununua",
            report_load_failed: "Imeshindikana kupakua ripoti",
            report_business: "Biashara",
            report_personal: "Binafsi",
            report_typed_date: "Ripoti ya {type} - {date}",
            sellers_registered: "Wauzaji {count} waliosajiliwa",
            total_sales: "Jumla ya Mauzo",
            total_profit: "Jumla ya Faida",
            customers: "Wateja",
            all_products: "Bidhaa Zote",
            today_sales: "Mauzo ya Leo: {amount}",
            today_profit: "Faida ya Leo: {amount}",
            today_unknown_cost: "({count} haijui bei ya kununua)",
            average_margin: "Wastani wa Margin: {percent}%",
            recent_sales: "Mauzo ya Hivi Karibuni",
            no_sales_yet: "Hakuna mauzo bado",
            profit_amount: "Faida: {amount}",
            search_sales_placeholder: "Tafuta mauzo kwa bidhaa, mteja, muuzaji au tarehe...",
            search_products_placeholder: "Tafuta bidhaa kwa jina au kategoria...",
            search_customers_placeholder: "Tafuta mteja kwa jina, simu au barua pepe...",
            search_placeholder_default: "Tafuta...",
            clear_search: "Futa utafutaji",
            no_results: "Hakuna matokeo yanayolingana na utafutaji wako",
            results_count: "Matokeo: {filtered} kati ya {total}",
            sales_report_title: "Ripoti ya Mauzo - {business_name}",
            buying_price_unknown: "bei ya kununua haijasumbuliwa",
            margin_pct: "{percent}% margin",
            sold_count: "✓ Zimeuzwa ({count})",
            unsold_count: "Hazijauzwa ({count})",
            no_products_sold: "Hakuna bidhaa zilizouzwa bado",
            all_products_sold: "Bidhaa zote zimeuzwa!",
            no_category: "Hakuna kategoria",
            product_meta: "Hisa: {stock} • Bei Ununuzi: {buy} • Bei Kuuzia: {sell}",
            product_sold_meta: "Zimeuzwa: {sold} • Mapato: {revenue} • Faida: {profit}",
            expected_profit: "Inatarajiwa faida: {profit}{percent}",
            pending: "Bado",
            profit_per_product: "Faida/Bidhaa: {amount}",
            sold: "Imeuzwa",
            not_sold: "Hajauzwa",
            product_summary: "Muhtasari wa Bidhaa",
            total: "Jumla",
            sold_short: "Zimeuzwa",
            unsold_short: "Hazijauzwa",
            pct_sold: "% Zimeuzwa",
            customers_report_title: "Ripoti ya Wateja - {business_name}",
            no_customers_yet: "Hakuna wateja bado",
            sales_count: "{count} mauzo",
            none_yet: "Hajapata",
            total_purchases: "Jumla ya Kununua",
            full_report: "Ripoti Kamili",
            admin: "Admin",
            seller: "Seller",
            whole_business_data: "Data ya Biashara Nzima",
            seller_data: "Data ya Seller",
            overview: "Mapitio",
            sales_tab: "Mauzo ({count})",
            products_tab: "Bidhaa ({count})",
            customers_tab: "Wateja ({count})",
            export_report: "Toa Ripoti",
            pdf: "PDF",
            excel: "Excel",
            print: "Print",
            showing_business_data: "Inaonyesha data ya biashara yote",
            showing_seller_data: "Inaonyesha data ya seller mmoja tu",
            sold_products_title: "Bidhaa Zilizouzwa",
            unsold_products_title: "Bidhaa Zisizouzwa",
            overview_title: "Mapitio - {business_name}",
            seller_header: "Muuzaji",
            date_header: "Tarehe",
            quantity_header: "Idadi",
            price_header: "Bei",
            profit_header: "Faida",
            category_header: "Kategoria",
            stock_header: "Hisa",
            buying_price_header: "Bei Ununuzi",
            selling_price_header: "Bei Kuuzia",
            sold_header: "Zilizouzwa",
            revenue_header: "Mapato",
            name_header: "Jina",
            phone_header: "Simu",
            email_header: "Email",
            sales_header: "Mauzo",
            metric_header: "Kipimo",
            value_header: "Thamani",
            sellers_metric: "Wauzaji",
            avg_margin_metric: "Wastani wa Margin %",
            note: "Kumbuka",
            unknown_cost_excluded: "{count} mauzo hayana bei ya kununwa; jumla ya faida hawahesabiwa",
            generated_at: "Imetolewa {date} — DukaMkononi",
            pdf_popup_permission: "Tafadhali ruhusu popups ili kupakua PDF",
            print_popup_permission: "Tafadhali ruhusu popups ili kuchapisha",
            today_sales_label: "Mauzo ya Leo",
            today_profit_label: "Faida ya Leo",
        };
        function t(key, params) {
            const full = key.indexOf('msimamizi_ripoti.') === 0 ? key : 'msimamizi_ripoti.' + key;
            if (window.DM && typeof window.DM.t === 'function') {
                const hit = window.DM.t(full, params);
                if (hit !== full) return hit;
            }
            let value = SW[key.replace('msimamizi_ripoti.', '')];
            if (value === undefined) return key;
            if (params) {
                Object.keys(params).forEach(p => {
                    value = value.split('{' + p + '}').join(params[p] == null ? '' : params[p]);
                });
            }
            return value;
        }
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
        function formatCurrency(amount) { return t('msimamizi_ripoti.currency_prefix') + (amount || 0).toLocaleString(); }
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
                <div style="background:#e74c3c;padding:12px;border-radius:40px;color:white;cursor:pointer;">${t('msimamizi_ripoti.alert_ok')}</div>`;
            box.querySelector('div:last-child').onclick = () => { overlay.remove(); if(onOk) onOk(); };
            overlay.appendChild(box);
            document.body.appendChild(overlay);
        }

        async function loadUserData() {
            userToken = localStorage.getItem('userToken');
            const userStr = localStorage.getItem('userData');
            if (!userToken || !userStr) {
                showAlert(t('msimamizi_ripoti.alert_error_title'), t('msimamizi_ripoti.alert_sign_in_again'), () => window.location.href = '../login?role=msimamizi');
                return false;
            }
            const user = JSON.parse(userStr);
            userData = {
                id: user.id || user.userId || '',
                email: user.email || '',
                businessName: user.businessName || user.business_name || t('msimamizi_ripoti.business_mine'),
                businessLocation: user.businessLocation || user.business_location || t('msimamizi_ripoti.location_not_filled'),
                role: user.role || ''
            };
            document.getElementById('userName').innerHTML = escapeHtml(user.full_name || user.email?.split('@')[0] || t('msimamizi_ripoti.user_admin'));
            const avatarEl = document.getElementById('userAvatar');
            const displayName = user.full_name || user.business_name || user.email?.split('@')[0] || t('msimamizi_ripoti.user_admin');
            if (user.business_logo_url) {
                avatarEl.classList.add('js-avatar-view');
avatarEl.setAttribute('data-full', user.business_logo_url);
avatarEl.setAttribute('data-name', displayName);
avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="${t('msimamizi_ripoti.avatar_alt')}">`;
            } else {
                avatarEl.innerHTML = (user.full_name || user.email?.charAt(0) || 'M').charAt(0).toUpperCase();
            }
            return true;
        }

        // PRICE RULES (confirmed by the owner):
        //   products.price                = BUYING price  -> "Bei ya Kununua"
        //   products.expected_selling_price = SELLING price -> "Bei ya Kuuzia"
        //   profit = selling - buying
        // `price` is NOT NULL, so the buying price is always available and a
        // real sale profit is always computable. What can be missing is the
        // SELLING price, in which case only the *expected* profit is unknown.
        function costBasis(product) {
            if (!product) return null;
            const c = product.price;
            if (c === null || c === undefined || c === '') return null;
            const n = Number(c);
            return Number.isFinite(n) ? n : null;
        }

        function sellingBasis(product) {
            if (!product) return null;
            const s = product.expected_selling_price;
            if (s === null || s === undefined || s === '') return null;
            const n = Number(s);
            return Number.isFinite(n) ? n : null;
        }

        function applyProfit(list) {
            return list.map(s => s.cost_price === null
                ? s
                : { ...s, profit: (s.unit_price - s.cost_price) * s.quantity });
        }

        function sumProfit(list) {
            return list.reduce((s, sale) => s + (typeof sale.profit === 'number' ? sale.profit : 0), 0);
        }

        // The buying price (products.price) is NOT NULL, so this is normally
        // 0. It stays as a guard for any row where it is somehow missing.
        function unknownCostCount(list) {
            return list.filter(s => s.cost_price === null).length;
        }

        // A sale with no recorded customer is one "unknown customer", so the
        // customer count = known customers + sales with no customer data.
        // Sales rows are one per item, so count distinct sale ids.
        function unknownCustomerCount(list) {
            return new Set((list || [])
                .filter(s => !s.customer_name)
                .map(s => s.id)).size;
        }

        // A profit total is only meaningful if every sale it covers had a
        // recorded buying price. Without this the owner sees a clean number
        // that silently excludes unsold-cost rows.
        function profitCoverageNote(unknown) {
            if (!unknown) return '';
            return `<div style="color:#f39c12;font-size:10px;margin-top:2px;">${t('msimamizi_ripoti.unknown_cost_note', { count: unknown })}</div>`;
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
            } catch(e) { console.error(e); showAlert(t('msimamizi_ripoti.alert_error_title'), t('msimamizi_ripoti.report_load_failed')); }
            finally { loading = false; render(); }
        }

        async function fetchBusinessData(headers) {
            const bizQuery = `business_name=${encodeURIComponent(userData.businessName)}`;

            // All four reads fire in PARALLEL (they used to run strictly
            // one-after-another) and products/sales use slim=1 payloads
            // (no embedded user/product objects — those made the page
            // download megabytes before anything could render).
            const [sellersRes, productsRes, salesRes, customersRes] = await Promise.all([
                fetch(`${API_BASE_URL}/api/admin/users?business=${encodeURIComponent(userData.businessName)}&role=seller,admin`, { headers }),
                fetch(`${API_BASE_URL}/api/admin/products?${bizQuery}&slim=1`, { headers }),
                fetch(`${API_BASE_URL}/api/admin/sales?${bizQuery}&slim=1`, { headers }),
                fetch(`${API_BASE_URL}/api/admin/customers?${bizQuery}`, { headers }),
            ]);

            if (sellersRes.ok) {
                const data = await sellersRes.json();
                const users = data.users || (Array.isArray(data) ? data : []);
                // The server already scopes this to the caller's own business
                // (JWT business_id), so the old
                // `u.business_name === userData.businessName` test only ever
                // re-introduced risk: userData.businessName comes from client
                // storage, which the profile screen rewrites with the canonical
                // businesses.business_name while the users rows keep the legacy
                // spelling. A mismatch emptied sellers, and because
                // products/customers/sales are all intersected with sellers,
                // the whole report went blank. Approval is the only real rule.
                sellers = users.filter(u => u.status === 'approved');
            }

            let rawProducts = [];
            if (productsRes.ok) {
                const data = await productsRes.json();
                const prods = data.products || (Array.isArray(data) ? data : []);
                rawProducts = prods.filter(p => sellers.some(s => s.id === p.seller_id)).map(p => ({
                    id: p.id, name: p.name, price: p.price || 0, expected_selling_price: p.expected_selling_price ?? null,
                    category: p.category, stock: p.stock || 0, seller_id: p.seller_id, created_at: p.created_at
                }));
            }

            // Customer names come from the customers payload (the slim sales
            // payload has no embedded relations).
            const customerNameById = new Map();
            if (customersRes.ok) {
                const data = await customersRes.json();
                const custs = data.customers || (Array.isArray(data) ? data : []);
                custs.forEach(c => customerNameById.set(c.id, c.name));
                customers = custs.filter(c => sellers.some(s => s.id === c.seller_id)).map(c => ({ ...c, total_purchases: c.total_purchases / 2, purchases_count: Math.round(c.purchases_count / 2) }));
            }

            let allSales = [];
            if (salesRes.ok) {
                const data = await salesRes.json();
                const salesData = data.sales || (Array.isArray(data) ? data : []);
                salesData.forEach(sale => {
                    const seller = sellers.find(s => s.id === sale.seller_id);
                    if (seller && sale.sale_items) {
                        sale.sale_items.forEach(item => {
                            const product = rawProducts.find(p => p.id === item.product_id);
                            allSales.push({
                                id: sale.id, product_id: item.product_id, product_name: product?.name || t('msimamizi_ripoti.product_generic'),
                                quantity: item.quantity || 1, unit_price: item.unit_price || 0,
                                total_amount: item.total_price || (item.unit_price * item.quantity),
                                sale_date: sale.sale_date?.split('T')[0] || new Date().toISOString().split('T')[0],
                                customer_name: (sale.customer_id && customerNameById.get(sale.customer_id)) || '', seller_name: seller.full_name || seller.email,
                                cost_price: costBasis(product), profit: null
                            });
                        });
                    }
                });
                allSales = applyProfit(allSales);
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
                return { ...p, total_sold: salesData?.totalSold || 0, total_revenue: salesData?.totalRevenue || 0, total_profit: salesData?.totalProfit || 0, total_profit_known: costBasis(p) !== null, has_sales: !!salesData };
            });
            soldProducts = allProducts.filter(p => p.has_sales);
            unsoldProducts = allProducts.filter(p => !p.has_sales);
            
            // Stats
            const totalSales = sales.reduce((s, sale) => s + sale.total_amount, 0);
            const totalProfit = sumProfit(sales);
            const today = new Date().toISOString().split('T')[0];
            const todaySales = sales.filter(s => s.sale_date === today).reduce((s, sale) => s + sale.total_amount, 0);
            const todayProfit = sumProfit(sales.filter(s => s.sale_date === today));
            const totalCost = sales.reduce((s, sale) => s + (typeof sale.cost_price === 'number' ? sale.cost_price * sale.quantity : 0), 0);
            const avgMargin = totalCost > 0 ? (totalProfit / totalCost) * 100 : 0;
            businessStats = { totalSales, totalProfit, totalCustomers: customers.length + unknownCustomerCount(sales), totalProducts: allProducts.length, totalSellers: sellers.length, todaySales, todayProfit, averageProfitMargin: avgMargin, unknownCostSales: unknownCostCount(sales), unknownCostSalesToday: unknownCostCount(sales.filter(s => s.sale_date === today)) };
        }

        async function fetchSellerData(headers) {
            // Products
            const productsRes = await fetch(`${API_BASE_URL}/api/products/my`, { headers });
            let rawProducts = [];
            if (productsRes.ok) {
                const data = await productsRes.json();
                rawProducts = (Array.isArray(data) ? data : []).map(p => ({
                    id: p.id, name: p.name, price: p.price || 0, expected_selling_price: p.expected_selling_price ?? null,
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
                                id: sale.id, product_id: item.product_id, product_name: product?.name || t('msimamizi_ripoti.product_generic'),
                                quantity: item.quantity || 1, unit_price: item.unit_price || 0,
                                total_amount: item.total_price || (item.unit_price * item.quantity),
                                sale_date: sale.sale_date?.split('T')[0] || new Date().toISOString().split('T')[0],
                                customer_name: sale.customers?.name || '', seller_name: userData.businessName,
                                cost_price: costBasis(product), profit: null
                            });
                        });
                    }
                });
                allSales = applyProfit(allSales);
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
                return { ...p, total_sold: salesData?.totalSold || 0, total_revenue: salesData?.totalRevenue || 0, total_profit: salesData?.totalProfit || 0, total_profit_known: costBasis(p) !== null, has_sales: !!salesData };
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
            const totalProfit = sumProfit(sales);
            const today = new Date().toISOString().split('T')[0];
            const todaySales = sales.filter(s => s.sale_date === today).reduce((s, sale) => s + sale.total_amount, 0);
            const todayProfit = sumProfit(sales.filter(s => s.sale_date === today));
            const totalCost = sales.reduce((s, sale) => s + (typeof sale.cost_price === 'number' ? sale.cost_price * sale.quantity : 0), 0);
            const avgMargin = totalCost > 0 ? (totalProfit / totalCost) * 100 : 0;
            businessStats = { totalSales, totalProfit, totalCustomers: customers.length + unknownCustomerCount(sales), totalProducts: allProducts.length, totalSellers: 1, todaySales, todayProfit, averageProfitMargin: avgMargin, unknownCostSales: unknownCostCount(sales), unknownCostSalesToday: unknownCostCount(sales.filter(s => s.sale_date === today)) };
            sellers = [{ id: userData.id, full_name: userData.businessName, email: userData.email }];
        }

        function refreshData() { fetchAllReports(); }

        function renderOverview() {
            const isAdmin = userData.role === 'admin';
            return `
                <div class="business-card">
                    <div class="business-name">${escapeHtml(userData.businessName)}</div>
                    <div class="business-location">${ic('location', 13)} ${escapeHtml(userData.businessLocation)}</div>
                    <div style="font-size:12px;color:#3498db;margin-top:6px;">${t('msimamizi_ripoti.report_typed_date', { type: t(isAdmin ? 'msimamizi_ripoti.report_business' : 'msimamizi_ripoti.report_personal'), date: new Date().toLocaleDateString('sw-TZ') })}</div>
                    ${isAdmin ? `<div style="font-size:11px;color:#7f8c8d;margin-top:4px;">${ic('users', 12)} ${t('msimamizi_ripoti.sellers_registered', { count: sellers.length })}</div>` : ''}
                </div>
                ${businessStats ? `
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon" style="background:#3498db20;color:#3498db;">${ic('money', 22)}</div><div class="stat-number">${formatCurrency(businessStats.totalSales)}</div><div class="stat-label">${t('msimamizi_ripoti.total_sales')}</div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:#27ae6020;color:#27ae60;">${ic('chart', 22)}</div><div class="stat-number" style="color:#27ae60;">${formatCurrency(businessStats.totalProfit)}</div><div class="stat-label">${t('msimamizi_ripoti.total_profit')}</div>${profitCoverageNote(businessStats.unknownCostSales)}</div>
                    <div class="stat-card"><div class="stat-icon" style="background:#2ecc7120;color:#2c7e5f;">${ic('users', 22)}</div><div class="stat-number">${businessStats.totalCustomers}</div><div class="stat-label">${t('msimamizi_ripoti.customers')}</div></div>
                    <div class="stat-card"><div class="stat-icon" style="background:#e74c3c20;color:#e74c3c;">${ic('box', 22)}</div><div class="stat-number">${businessStats.totalProducts}</div><div class="stat-label">${t('msimamizi_ripoti.all_products')}</div></div>
                </div>
                <div class="extra-stats">
                    <div class="extra-stat">${ic('calendar', 13)} ${t('msimamizi_ripoti.today_sales', { amount: formatCurrency(businessStats.todaySales) })}</div>
                    <div class="extra-stat">${ic('chart', 13)} ${t('msimamizi_ripoti.today_profit', { amount: formatCurrency(businessStats.todayProfit) })}${businessStats.unknownCostSalesToday ? ` <span style="color:#f39c12;">${t('msimamizi_ripoti.today_unknown_cost', { count: businessStats.unknownCostSalesToday })}</span>` : ''}</div>
                    <div class="extra-stat">${ic('report', 13)} ${t('msimamizi_ripoti.average_margin', { percent: businessStats.averageProfitMargin.toFixed(1) })}</div>
                </div>
                ` : ''}
                <div class="section"><div class="section-title">${t('msimamizi_ripoti.recent_sales')}</div>
                ${sales.length > 0 ? sales.slice(0,5).map(sale => `
                    <div class="item-card"><div class="item-info"><div class="item-title">${escapeHtml(sale.product_name)}</div><div class="item-subtitle">${formatDate(sale.sale_date)} - ${escapeHtml(sale.customer_name || t('msimamizi_ripoti.customer_generic'))}</div><div class="item-meta">${sale.quantity} × ${formatCurrency(sale.unit_price)}</div></div>
                    <div class="item-side"><div class="item-amount">${formatCurrency(sale.total_amount)}</div><div class="profit-text" style="color:${sale.profit >= 0 ? '#27ae60' : '#e74c3c'}">${t('msimamizi_ripoti.profit_amount', { amount: formatCurrency(sale.profit) })}</div></div></div>
                `).join('') : '<div class="no-data"><div class="no-data-icon">' + ic('doc', 44) + '</div><div>' + t('msimamizi_ripoti.no_sales_yet') + '</div></div>'}</div>
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
            return `<div class="no-results"><div class="no-data-icon">${ic(iconName, 44)}</div><div>${t('msimamizi_ripoti.no_results')}</div></div>`;
        }

        function searchResultsText(filteredCount, totalCount) {
            if (!searchTerm || filteredCount === totalCount) return '';
            return `<div style="font-size:12px;color:#7f8c8d;margin:-6px 0 12px;">${t('msimamizi_ripoti.results_count', { filtered: filteredCount, total: totalCount })}</div>`;
        }

        function renderSearchBar() {
            const placeholders = {
                sales: t('msimamizi_ripoti.search_sales_placeholder'),
                products: t('msimamizi_ripoti.search_products_placeholder'),
                customers: t('msimamizi_ripoti.search_customers_placeholder')
            };
            const safeTerm = String(searchTerm).replace(/"/g, '&quot;');
            return `
                <div class="report-search">
                    <span class="search-icon">${ic('search', 15)}</span>
                    <input type="text" id="reportSearch" placeholder="${placeholders[activeReport] || t('msimamizi_ripoti.search_placeholder_default')}" value="${safeTerm}" oninput="handleSearchInput(this)">
                    <span class="search-clear" id="searchClear" style="display:${searchTerm ? 'block' : 'none'};" onclick="clearSearch()" title="${t('msimamizi_ripoti.clear_search')}">✕</span>
                </div>
            `;
        }

        function renderSalesReport() {
            const filtered = getFilteredSales();
            const listHtml = sales.length === 0
                ? '<div class="no-data"><div class="no-data-icon">' + ic('doc', 44) + '</div><div>' + t('msimamizi_ripoti.no_sales_yet') + '</div></div>'
                : filtered.length === 0
                    ? noResultsHtml('doc')
                    : filtered.map(sale => {
                    const known = typeof sale.profit === 'number';
                    const basis = typeof sale.cost_price === 'number' ? sale.cost_price * sale.quantity : null;
                    const margin = known && basis ? (sale.profit / basis) * 100 : null;
                    return `
                    <div class="item-card"><div class="item-info"><div class="item-title">${escapeHtml(sale.product_name)}</div><div class="item-subtitle">${escapeHtml(sale.customer_name || t('msimamizi_ripoti.customer_generic'))} • ${formatDate(sale.sale_date)}</div><div class="item-meta">${sale.quantity} × ${formatCurrency(sale.unit_price)}</div></div>
                    <div class="item-side"><div class="item-amount">${formatCurrency(sale.total_amount)}</div><div class="profit-text" style="color:${known && sale.profit >= 0 ? '#27ae60' : '#e74c3c'}">${t('msimamizi_ripoti.profit_amount', { amount: known ? formatCurrency(sale.profit) : '-' })}</div><div style="font-size:11px;color:#7f8c8d;">${margin === null ? t('msimamizi_ripoti.buying_price_unknown') : t('msimamizi_ripoti.margin_pct', { percent: margin.toFixed(1) })}</div></div></div>
                `}).join('');
            return `<div class="section"><div class="section-title">${t('msimamizi_ripoti.sales_report_title', { business_name: escapeHtml(userData.businessName) })}</div>
                ${searchResultsText(filtered.length, sales.length)}${listHtml}</div>`;
        }

        function renderProductsReport() {
            const allInTab = activeProductTab === 'sold' ? soldProducts : unsoldProducts;
            const currentProducts = getFilteredProducts();
            const isSold = activeProductTab === 'sold';
            return `
                ${searchResultsText(currentProducts.length, allInTab.length)}
                <div class="product-tabs">
                    <div class="product-tab ${activeProductTab === 'sold' ? 'active' : ''}" onclick="setProductTab('sold')">${t('msimamizi_ripoti.sold_count', { count: soldProducts.length })}</div>
                    <div class="product-tab ${activeProductTab === 'unsold' ? 'active' : ''}" onclick="setProductTab('unsold')">${ic('clock', 14)} ${t('msimamizi_ripoti.unsold_count', { count: unsoldProducts.length })}</div>
                </div>
                ${allInTab.length === 0 ? `<div class="no-data"><div class="no-data-icon">${ic('box', 44)}</div><div>${isSold ? t('msimamizi_ripoti.no_products_sold') : t('msimamizi_ripoti.all_products_sold')}</div></div>` : currentProducts.length === 0 ? noResultsHtml('box') : currentProducts.map(p => {
                    const cost = costBasis(p);
                    const sellTarget = sellingBasis(p);
                    // Projected profit = target selling price - buying price.
                    // Either side being unknown means the profit is unknown.
                    const profitPerUnit = (cost === null || sellTarget === null) ? null : sellTarget - cost;
                    const marginPct = profitPerUnit === null || !cost ? null : (profitPerUnit / cost) * 100;
                    const buyCell = cost === null ? '-' : formatCurrency(cost);
                    const sellCell = sellTarget === null ? '-' : formatCurrency(sellTarget);
                    return `<div class="item-card" style="${!isSold ? 'border-left:3px solid #f39c12' : ''}">
                        <div class="item-info"><div class="item-title">${escapeHtml(p.name)}</div><div class="item-subtitle">${p.category || t('msimamizi_ripoti.no_category')}</div>
                        <div class="item-meta">${t('msimamizi_ripoti.product_meta', { stock: p.stock, buy: buyCell, sell: sellCell })}</div>
                        ${isSold ? `<div class="item-meta">${t('msimamizi_ripoti.product_sold_meta', { sold: p.total_sold, revenue: formatCurrency(p.total_revenue), profit: p.total_profit_known ? formatCurrency(p.total_profit) : '-' })}</div>` : `<div class="item-meta">${t('msimamizi_ripoti.expected_profit', { profit: profitPerUnit === null ? '-' : formatCurrency(profitPerUnit), percent: marginPct === null ? '' : ' (' + marginPct.toFixed(1) + '%)' })}</div>`}
                        </div>
                        <div class="item-side"><div class="item-amount">${isSold ? formatCurrency(p.total_revenue) : t('msimamizi_ripoti.pending')}</div>
                        <div class="profit-text" style="color:${isSold && p.total_profit >= 0 ? '#27ae60' : '#f39c12'}">${isSold ? t('msimamizi_ripoti.profit_amount', { amount: p.total_profit_known ? formatCurrency(p.total_profit) : '-' }) : t('msimamizi_ripoti.profit_per_product', { amount: profitPerUnit === null ? '-' : formatCurrency(profitPerUnit) })}</div>
                        <div class="badge ${isSold ? 'badge-success' : 'badge-warning'}">${isSold ? ic('check', 10) + ' ' + t('msimamizi_ripoti.sold') : ic('clock', 10) + ' ' + t('msimamizi_ripoti.not_sold')}</div></div>
                    </div>`;
                }).join('')}
                <div style="background:white;padding:16px;border-radius:12px;margin-top:16px;"><div style="font-weight:700;text-align:center;margin-bottom:12px;">${t('msimamizi_ripoti.product_summary')}</div>
                <div style="display:flex;justify-content:space-around;"><div><div style="font-size:20px;font-weight:700;">${allProducts.length}</div><div>${t('msimamizi_ripoti.total')}</div></div>
                <div><div style="font-size:20px;font-weight:700;color:#27ae60;">${soldProducts.length}</div><div>${t('msimamizi_ripoti.sold_short')}</div></div>
                <div><div style="font-size:20px;font-weight:700;color:#f39c12;">${unsoldProducts.length}</div><div>${t('msimamizi_ripoti.unsold_short')}</div></div>
                <div><div style="font-size:20px;font-weight:700;">${allProducts.length ? Math.round((soldProducts.length/allProducts.length)*100) : 0}%</div><div>${t('msimamizi_ripoti.pct_sold')}</div></div></div></div>
            `;
        }

        function renderCustomersReport() {
            const filtered = getFilteredCustomers();
            const listHtml = customers.length === 0
                ? '<div class="no-data"><div class="no-data-icon">' + ic('users', 44) + '</div><div>' + t('msimamizi_ripoti.no_customers_yet') + '</div></div>'
                : filtered.length === 0
                    ? noResultsHtml('users')
                    : filtered.map(c => `
                    <div class="customer-item"><div class="customer-avatar">${ic('user', 26)}</div><div class="customer-info"><div class="customer-name">${escapeHtml(c.name)}</div>
                    <div class="customer-contact">${c.phone ? `${ic('phone', 12)} ${escapeHtml(c.phone)}` : ''} ${c.email ? `${ic('mail', 12)} ${escapeHtml(c.email)}` : ''}</div>
                    <div class="customer-meta">${ic('doc', 12)} ${t('msimamizi_ripoti.sales_count', { count: c.purchases_count })} • ${ic('calendar', 12)} ${c.last_purchase_date ? formatDate(c.last_purchase_date) : t('msimamizi_ripoti.none_yet')}</div></div>
                    <div class="customer-stats"><div class="customer-total">${formatCurrency(c.total_purchases)}</div><div>${t('msimamizi_ripoti.total_purchases')}</div></div></div>
                `).join('');
            return `<div class="section"><div class="section-title">${t('msimamizi_ripoti.customers_report_title', { business_name: escapeHtml(userData.businessName) })}</div>
                ${searchResultsText(filtered.length, customers.length)}${listHtml}</div>`;
        }

        function render() {
            const isAdmin = userData.role === 'admin';
            const container = document.getElementById('mainContent');
            container.innerHTML = `
                <div class="container">
                    <div class="header-card"><div class="title">${t('msimamizi_ripoti.full_report')}</div><div class="user-email">${escapeHtml(userData.email)}</div><div class="role-badge">${isAdmin ? ic('crown', 12) + ' ' + t('msimamizi_ripoti.admin') : ic('user', 12) + ' ' + t('msimamizi_ripoti.seller')} • ${dataSource === 'admin' ? t('msimamizi_ripoti.whole_business_data') : t('msimamizi_ripoti.seller_data')}</div></div>
                    <div class="report-nav">
                        <div class="nav-tab ${activeReport === 'overview' ? 'active' : ''}" onclick="setReport('overview')">${ic('report', 15)} ${t('msimamizi_ripoti.overview')}</div>
                        <div class="nav-tab ${activeReport === 'sales' ? 'active' : ''}" onclick="setReport('sales')">${ic('money', 15)} ${t('msimamizi_ripoti.sales_tab', { count: sales.length })}</div>
                        <div class="nav-tab ${activeReport === 'products' ? 'active' : ''}" onclick="setReport('products')">${ic('box', 15)} ${t('msimamizi_ripoti.products_tab', { count: allProducts.length })}</div>
                        <div class="nav-tab ${activeReport === 'customers' ? 'active' : ''}" onclick="setReport('customers')">${ic('users', 15)} ${t('msimamizi_ripoti.customers_tab', { count: customers.length + unknownCustomerCount(sales) })}</div>
                    </div>
                    ${activeReport !== 'overview' ? renderSearchBar() : ''}
                    <div id="reportContent"></div>
                    <div class="section"><div class="section-title">${t('msimamizi_ripoti.export_report')}</div><div class="export-buttons"><div class="export-btn" onclick="exportPDF()">${ic('filePdf', 15)} ${t('msimamizi_ripoti.pdf')}</div><div class="export-btn" onclick="exportExcel()">${ic('excel', 15)} ${t('msimamizi_ripoti.excel')}</div><div class="export-btn" onclick="exportPrint()">${ic('print', 15)} ${t('msimamizi_ripoti.print')}</div></div></div>
                    <div class="status-card"><div>${dataSource === 'admin' ? ic('check', 13) + ' ' + t('msimamizi_ripoti.showing_business_data') : ic('info', 13) + ' ' + t('msimamizi_ripoti.showing_seller_data')}</div></div>
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

        function renderLoading() { document.getElementById('mainContent').innerHTML = '<div style="text-align:center;padding:60px;"><div class="loading-spinner"></div><div>' + t('msimamizi_ripoti.loading') + '</div></div>'; }

        // ============================== EXPORTS ==============================
        // Builds a standalone HTML document (title + table) for the report tab
        // currently active. Used by both the PDF and Print buttons.
        function buildExportTable() {
            const th = (t) => `<th style="border:1px solid #bdc3c7;padding:6px 10px;background:#f4f6f7;text-align:left;">${escapeHtml(t)}</th>`;
            const td = (v) => `<td style="border:1px solid #ecf0f1;padding:6px 10px;">${escapeHtml(String(v ?? ''))}</td>`;
            let title = t('msimamizi_ripoti.full_report');
            let rows = [];
            if (activeReport === 'sales') {
                title = t('msimamizi_ripoti.sales_report_title', { business_name: userData.businessName });
                rows = [[t('msimamizi_ripoti.product_generic'), t('msimamizi_ripoti.customer_generic'), t('msimamizi_ripoti.seller_header'), t('msimamizi_ripoti.date_header'), t('msimamizi_ripoti.quantity_header'), t('msimamizi_ripoti.price_header'), t('msimamizi_ripoti.total'), t('msimamizi_ripoti.profit_header')],
                    ...sales.map(s => [s.product_name, s.customer_name || t('msimamizi_ripoti.customer_generic'), s.seller_name, s.sale_date, s.quantity, s.unit_price, s.total_amount, typeof s.profit === 'number' ? s.profit : ''])];
            } else if (activeReport === 'products') {
                const list = activeProductTab === 'sold' ? soldProducts : unsoldProducts;
                title = t(activeProductTab === 'sold' ? 'msimamizi_ripoti.sold_products_title' : 'msimamizi_ripoti.unsold_products_title') + ' - ' + userData.businessName;
                rows = [[t('msimamizi_ripoti.product_generic'), t('msimamizi_ripoti.category_header'), t('msimamizi_ripoti.stock_header'), t('msimamizi_ripoti.buying_price_header'), t('msimamizi_ripoti.selling_price_header'), t('msimamizi_ripoti.sold_header'), t('msimamizi_ripoti.revenue_header'), t('msimamizi_ripoti.profit_header')],
                    ...list.map(p => [p.name, p.category || t('msimamizi_ripoti.no_category'), p.stock, costBasis(p) === null ? '' : costBasis(p), p.expected_selling_price, p.total_sold || 0, p.total_revenue || 0, p.total_profit_known ? (p.total_profit || 0) : ''])];
            } else if (activeReport === 'customers') {
                title = t('msimamizi_ripoti.customers_report_title', { business_name: userData.businessName });
                rows = [[t('msimamizi_ripoti.name_header'), t('msimamizi_ripoti.phone_header'), t('msimamizi_ripoti.email_header'), t('msimamizi_ripoti.sales_header'), t('msimamizi_ripoti.total')],
                    ...customers.map(c => [c.name, c.phone || '', c.email || '', c.purchases_count || 0, c.total_purchases || 0])];
            } else {
                title = t('msimamizi_ripoti.overview_title', { business_name: userData.businessName });
                rows = [[t('msimamizi_ripoti.metric_header'), t('msimamizi_ripoti.value_header')],
                    [t('msimamizi_ripoti.total_sales'), businessStats ? businessStats.totalSales : 0],
                    [t('msimamizi_ripoti.total_profit'), businessStats ? businessStats.totalProfit : 0],
                    [t('msimamizi_ripoti.customers'), businessStats ? businessStats.totalCustomers : 0],
                    [t('msimamizi_ripoti.all_products'), businessStats ? businessStats.totalProducts : 0],
                    [t('msimamizi_ripoti.sellers_metric'), businessStats ? businessStats.totalSellers : 0],
                    [t('msimamizi_ripoti.today_sales_label'), businessStats ? businessStats.todaySales : 0],
                    [t('msimamizi_ripoti.today_profit_label'), businessStats ? businessStats.todayProfit : 0],
                    [t('msimamizi_ripoti.avg_margin_metric'), businessStats ? businessStats.averageProfitMargin.toFixed(1) : 0]];
                // The profit rows above only cover sales with a recorded
                // buying price, so say so rather than let the number stand.
                if (businessStats && businessStats.unknownCostSales) {
                    rows.push([t('msimamizi_ripoti.note'), t('msimamizi_ripoti.unknown_cost_excluded', { count: businessStats.unknownCostSales })]);
                }
            }
            const head = rows[0].map(th).join('');
            const body = rows.slice(1).map(r => `<tr>${r.map(td).join('')}</tr>`).join('');
            return `<html><head><meta charset="UTF-8"><title>${escapeHtml(title)}</title></head>
                <body><h2 style="font-family:sans-serif;">${escapeHtml(title)}</h2>
                <p style="font-family:sans-serif;font-size:12px;color:#555;">${t('msimamizi_ripoti.generated_at', { date: new Date().toLocaleString('sw-TZ') })}</p>
                <table style="border-collapse:collapse;font-family:sans-serif;font-size:12px;"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></body></html>`;
        }

        // PDF: opens the report in a new window and triggers the browser's
        // print dialog, whose destination includes "Save as PDF".
        window.exportPDF = function () {
            const w = window.open('', '_blank');
            if (!w) { showAlert(t('msimamizi_ripoti.alert_error_title'), t('msimamizi_ripoti.pdf_popup_permission')); return; }
            w.document.open();
            w.document.write(buildExportTable());
            w.document.close();
            w.focus();
            setTimeout(() => { try { w.print(); } catch (e) {} }, 400);
        };

        // Print: same document, print dialog in the popup.
        window.exportPrint = function () {
            const w = window.open('', '_blank');
            if (!w) { showAlert(t('msimamizi_ripoti.alert_error_title'), t('msimamizi_ripoti.print_popup_permission')); return; }
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
                lines.push(row([t('msimamizi_ripoti.product_generic'), t('msimamizi_ripoti.customer_generic'), t('msimamizi_ripoti.seller_header'), t('msimamizi_ripoti.date_header'), t('msimamizi_ripoti.quantity_header'), t('msimamizi_ripoti.price_header'), t('msimamizi_ripoti.total'), t('msimamizi_ripoti.profit_header')]));
                sales.forEach(s => lines.push(row([s.product_name, s.customer_name || t('msimamizi_ripoti.customer_generic'), s.seller_name, s.sale_date, s.quantity, s.unit_price, s.total_amount, typeof s.profit === 'number' ? s.profit : ''])));
            } else if (activeReport === 'products') {
                const list = activeProductTab === 'sold' ? soldProducts : unsoldProducts;
                lines.push(row([t('msimamizi_ripoti.product_generic'), t('msimamizi_ripoti.category_header'), t('msimamizi_ripoti.stock_header'), t('msimamizi_ripoti.buying_price_header'), t('msimamizi_ripoti.selling_price_header'), t('msimamizi_ripoti.sold_header'), t('msimamizi_ripoti.revenue_header'), t('msimamizi_ripoti.profit_header')]));
                list.forEach(p => lines.push(row([p.name, p.category || t('msimamizi_ripoti.no_category'), p.stock, costBasis(p) === null ? '' : costBasis(p), p.expected_selling_price, p.total_sold || 0, p.total_revenue || 0, p.total_profit_known ? (p.total_profit || 0) : ''])));
            } else if (activeReport === 'customers') {
                lines.push(row([t('msimamizi_ripoti.name_header'), t('msimamizi_ripoti.phone_header'), t('msimamizi_ripoti.email_header'), t('msimamizi_ripoti.sales_header'), t('msimamizi_ripoti.total')]));
                customers.forEach(c => lines.push(row([c.name, c.phone || '', c.email || '', c.purchases_count || 0, c.total_purchases || 0])));
            } else {
                lines.push(row([t('msimamizi_ripoti.metric_header'), t('msimamizi_ripoti.value_header')]));
                lines.push(row([t('msimamizi_ripoti.total_sales'), businessStats ? businessStats.totalSales : 0]));
                lines.push(row([t('msimamizi_ripoti.total_profit'), businessStats ? businessStats.totalProfit : 0]));
                lines.push(row([t('msimamizi_ripoti.customers'), businessStats ? businessStats.totalCustomers : 0]));
                lines.push(row([t('msimamizi_ripoti.all_products'), businessStats ? businessStats.totalProducts : 0]));
                lines.push(row([t('msimamizi_ripoti.sellers_metric'), businessStats ? businessStats.totalSellers : 0]));
                lines.push(row([t('msimamizi_ripoti.today_sales_label'), businessStats ? businessStats.todaySales : 0]));
                lines.push(row([t('msimamizi_ripoti.today_profit_label'), businessStats ? businessStats.todayProfit : 0]));
                lines.push(row([t('msimamizi_ripoti.avg_margin_metric'), businessStats ? businessStats.averageProfitMargin.toFixed(1) : 0]));
                if (businessStats && businessStats.unknownCostSales) {
                    lines.push(row([t('msimamizi_ripoti.note'), t('msimamizi_ripoti.unknown_cost_excluded', { count: businessStats.unknownCostSales })]));
                }
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
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => { if (!loading) render(); });
        }

        init();
    </script>
</body>
</html>
@endverbatim