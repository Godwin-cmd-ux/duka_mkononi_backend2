@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_uza.page_title">Uza - Dukamkononi Muuzaji</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        /* Layout */
        .muuzaji-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px; background: white; border-right: 1px solid #ecf0f1;
            position: fixed; left: 0; top: 0; bottom: 0; z-index: 100;
            transition: transform 0.3s; display: flex; flex-direction: column;
            box-shadow: 2px 0 12px rgba(0,0,0,0.05);
        }
        .sidebar-header { padding: 30px 24px; border-bottom: 1px solid #ecf0f1; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 45px; height: 45px; background: linear-gradient(135deg, #2ecc71, #27ae60);
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
        .nav-item:hover, .nav-item.active { background-color: #e8f8f0; color: #2ecc71; }
        .nav-icon { font-size: 22px; width: 28px; }
        .nav-label { font-size: 15px; font-weight: 600; }
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid #ecf0f1; margin-top: auto; }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .user-avatar {
            width: 45px; height: 45px; background: #e8f8f0; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #2ecc71; font-weight: bold; font-size: 18px;
        }
        .user-name { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .user-role { font-size: 12px; color: #2ecc71; font-weight: 600; }
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
        .page-container { max-width: 900px; margin: 0 auto; }

        /* Uza Styles */
        .header-card { background: white; padding: 20px; border-radius: 20px; margin-bottom: 20px; }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .title { font-size: 22px; font-weight: 800; }
        .refresh-btn { background: none; border: none; font-size: 20px; cursor: pointer; padding: 8px; }
        .cart-badge {
            background: #2ecc71; margin: 0 0 16px 0; border-radius: 14px; padding: 12px 20px;
            cursor: pointer; color: white; display: flex; justify-content: space-between; align-items: center;
        }
        .form-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 20px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .label { font-size: 16px; font-weight: 600; margin-bottom: 8px; display: block; }
        .search-box { display: flex; align-items: center; background: #f8f9fa; border-radius: 14px; padding: 0 16px; margin-bottom: 16px; border: 1px solid #ddd; }
        .search-input { flex: 1; padding: 14px; border: none; background: transparent; font-size: 14px; outline: none; }
        
        /* Product Grid */
        .products-grid { display: flex; flex-direction: column; gap: 12px; }
        .product-item {
            background: #f8f9fa; border-radius: 16px; padding: 16px; border: 1px solid #ddd;
            cursor: pointer; transition: all 0.2s;
        }
        .product-item.selected { background: #e8f8f0; border-color: #2ecc71; border-width: 2px; }
        .product-item.out-of-stock { opacity: 0.6; background: #f5f5f5; }
        .product-header { display: flex; justify-content: space-between; margin-bottom: 6px; flex-wrap: wrap; gap: 8px; }
        .product-name { font-weight: 700; font-size: 16px; }
        .stock-badge { font-size: 11px; padding: 4px 8px; border-radius: 20px; background: #e8f4fd; color: #3498db; }
        .stock-badge.low { background: #fff3cd; color: #f39c12; }
        .stock-badge.out { background: #fdeaea; color: #e74c3c; }
        .product-category { font-size: 13px; color: #7f8c8d; margin-bottom: 8px; }
        .product-footer { display: flex; justify-content: space-between; align-items: center; }
        .price { font-weight: 700; color: #27ae60; }
        .owner-badge { font-size: 11px; background: #e8f4fd; padding: 4px 8px; border-radius: 12px; }
        
        /* Selected Product Section */
        .selected-product-card { background: #e8f4fd; border-radius: 16px; padding: 16px; margin: 20px 0; border-left: 4px solid #3498db; }
        .quantity-control { display: flex; align-items: center; gap: 12px; margin: 12px 0; }
        .qty-btn { width: 36px; height: 36px; border-radius: 18px; background: white; border: 1px solid #ddd; cursor: pointer; font-size: 18px; }
        .qty-input { width: 60px; text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 10px; font-size: 16px; }
        .add-cart-btn { background: #2ecc71; color: white; border: none; padding: 12px 20px; border-radius: 12px; font-weight: 700; cursor: pointer; width: 100%; margin-top: 8px; }
        .add-cart-btn.disabled { background: #95a5a6; cursor: not-allowed; }

        /* ===== Floating cart dock (follows the scroll) ===== */
        #uzaContent { padding-bottom: 150px; }
        .floating-dock {
            position: fixed; bottom: 12px; left: 50%; transform: translateX(-50%);
            width: min(720px, calc(100vw - 20px));
            background: #ffffff; border-radius: 18px;
            box-shadow: 0 10px 34px rgba(15, 23, 42, 0.22), 0 2px 8px rgba(15, 23, 42, 0.10);
            padding: 10px 12px; z-index: 900;
            display: flex; flex-direction: column; gap: 8px;
            animation: dockIn 0.25s ease-out;
        }
        @keyframes dockIn { from { transform: translateX(-50%) translateY(16px); opacity: 0; } to { transform: translateX(-50%) translateY(0); opacity: 1; } }
        .dock-row { display: flex; align-items: center; gap: 10px; }
        .dock-prod { flex: 1; min-width: 0; }
        .dock-name { font-weight: 700; font-size: 14px; color: #2c3e50; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dock-price { font-size: 12px; color: #27ae60; font-weight: 600; }
        .dock-qty { margin: 0; gap: 8px; }
        .dock-qty .qty-input { width: 52px; padding: 7px; }
        .dock-add { width: auto; margin-top: 0; padding: 11px 18px; white-space: nowrap; display: flex; align-items: center; gap: 6px; }
        .dock-cart-info {
            flex: 1; display: flex; align-items: center; gap: 8px; cursor: pointer;
            background: #e8f8f0; border-radius: 12px; padding: 10px 14px; font-weight: 700; color: #1e8449; font-size: 14px;
        }
        .dock-cart-badge { background: #2ecc71; color: #fff; min-width: 26px; height: 26px; border-radius: 13px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; padding: 0 6px; }
        .dock-checkout {
            background: #2ecc71; color: #fff; border: none; border-radius: 12px; padding: 12px 20px;
            font-weight: 800; font-size: 14px; cursor: pointer; white-space: nowrap;
        }
        .dock-checkout.disabled { background: #95a5a6; cursor: not-allowed; }
        @media (max-width: 560px) {
            .floating-dock { width: calc(100vw - 16px); padding: 8px 10px; }
            .dock-row { flex-wrap: wrap; }
            .dock-add { flex: 1; justify-content: center; }
            .dock-checkout { flex: 1; }
            #uzaContent { padding-bottom: 190px; }
        }
        
        /* Input Fields */
        .input-field { width: 100%; padding: 14px; border: 1px solid #ddd; border-radius: 14px; font-size: 14px; margin-top: 6px; }
        .sale-btn {
            background: #2ecc71; padding: 18px; border-radius: 16px; text-align: center;
            color: white; font-weight: 800; font-size: 16px; cursor: pointer; margin: 20px 0;
        }
        .sale-btn.disabled { background: #95a5a6; cursor: not-allowed; }
        
        /* Instructions */
        .instructions-box { background: #fff8e1; padding: 16px; border-radius: 16px; border-left: 4px solid #f1c40f; margin-top: 20px; }

        /* ===== AI sales import ===== */
        .ai-entry-btn{background:#fff;border:2px dashed #e8e3f6;border-radius:16px;padding:18px;margin-top:20px;cursor:pointer;display:flex;align-items:center;gap:14px;transition:all .2s}
        .ai-entry-btn:hover{border-color:#7c5cbf;background:#f7f3ff}
        .ai-entry-ic{width:46px;height:46px;border-radius:12px;background:#f3edff;color:#7c5cbf;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
        .ai-modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:1500}
        .ai-modal-overlay.show{display:flex}
        .ai-modal-content{background:#fff;border-radius:22px;width:100%;max-width:860px;max-height:92vh;display:flex;flex-direction:column}
        .ai-modal-header{display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #ecf0f1}
        .ai-modal-title{font-size:18px;font-weight:800;color:#2c3e50}
        .ai-modal-close{cursor:pointer;font-size:24px;color:#7f8c8d;padding:4px;line-height:1}
        .ai-modal-body{flex:1;overflow-y:auto;padding:20px 24px 28px;-webkit-overflow-scrolling:touch}
        .ai-upload-zone{border:2px dashed #d3c9f0;border-radius:16px;padding:30px 16px;text-align:center;cursor:pointer;background:#fcfaff;transition:all .2s}
        .ai-upload-zone:hover,.ai-upload-zone.drag{background:#f5f0ff;border-color:#7c5cbf}
        .ai-uz-main{font-weight:700;color:#2c3e50;margin-top:8px;font-size:15px}
        .ai-uz-sub{font-size:12px;color:#7f8c8d;margin-top:6px;line-height:1.5}
        .ai-preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;margin-top:16px}
        .ai-preview-item{position:relative;border-radius:12px;overflow:hidden;border:1px solid #ecf0f1;background:#f8f9fa}
        .ai-preview-item img{width:100%;height:96px;object-fit:cover;display:block;background:#eceff1}
        .ai-preview-remove{position:absolute;top:6px;right:6px;width:24px;height:24px;border-radius:50%;background:rgba(0,0,0,.55);border:none;color:#fff;font-size:14px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center}
        .ai-preview-name{font-size:11px;color:#5d6d7e;padding:6px 8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .ai-controls{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
        .ai-btn{flex:1;min-width:150px;padding:14px 16px;border-radius:14px;border:none;font-weight:800;font-size:15px;cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;gap:8px}
        .ai-btn:disabled{opacity:.6;cursor:not-allowed}
        .ai-btn-process{background:#e74c3c}
        .ai-btn-camera{width:100%;background:#2ecc71;margin-top:10px}
        .ai-progress{padding:36px 20px;text-align:center}
        .ai-dots{display:flex;gap:9px;justify-content:center}
        .ai-dots span{width:11px;height:11px;border-radius:50%;background:#e74c3c;opacity:.3;animation:aiWave 1.2s ease-in-out infinite}
        .ai-dots span:nth-child(1){animation-delay:0s}.ai-dots span:nth-child(2){animation-delay:.15s}.ai-dots span:nth-child(3){animation-delay:.3s}.ai-dots span:nth-child(4){animation-delay:.45s}
        @keyframes aiWave{0%,100%{transform:translateY(0);opacity:.3}50%{transform:translateY(-9px);opacity:1}}
        .ai-progress-text{margin-top:16px;font-size:13px;color:#7f8c8d}
        .ai-banner{padding:12px 14px;border-radius:12px;font-size:13px;margin-bottom:14px;line-height:1.45;display:none;white-space:pre-line}
        .ai-banner.error{display:block;background:#fdeaea;color:#c0392b;border-left:4px solid #e74c3c}
        .ai-banner.info{display:block;background:#e8f4fd;color:#2471a3;border-left:4px solid #3498db}
        .ai-results-summary{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px}
        .ai-summary-chip{background:#f0f8f0;color:#27ae60;font-size:12px;font-weight:700;padding:6px 12px;border-radius:20px}
        .ai-summary-chip.ok{background:#27ae60;color:#fff}
        .ai-summary-chip.existing{background:#e8f4fd;color:#2471a3}
        .ai-summary-chip.review{background:#fff9e6;color:#b9770e}
        .ai-table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid #ecf0f1;border-radius:14px}
        .ai-table{width:100%;border-collapse:collapse;min-width:720px;background:#fff}
        .ai-table th{background:#f8f9fa;color:#2c3e50;font-size:12px;font-weight:700;text-align:left;padding:10px;border-bottom:1px solid #ecf0f1;white-space:nowrap}
        .ai-table td{padding:10px;border-bottom:1px solid #f1f3f5;font-size:14px;vertical-align:middle}
        .ai-table input,.ai-table select{padding:8px 10px;font-size:14px;border:1px solid #ddd;border-radius:10px;font-family:'Inter',sans-serif;width:100%;box-sizing:border-box}
        .ai-table input:focus,.ai-table select:focus{outline:none;border-color:#7c5cbf}
        .ai-status{display:inline-block;font-size:11px;font-weight:700;padding:4px 10px;border-radius:14px;white-space:nowrap}
        .ai-status.existing{background:#e8f4fd;color:#2471a3}
        .ai-status.review{background:#fff9e6;color:#b9770e}
        .ai-conf-row{font-size:12px;color:#7f8c8d;margin-top:4px}
        .ai-warn{font-size:11px;color:#b9770e;line-height:1.35;margin-top:3px}
        .ai-verify-btn{background:#2ecc71;border:none;color:#fff;font-weight:800;font-size:13px;padding:10px 16px;border-radius:12px;cursor:pointer;white-space:nowrap}
        .ai-verify-btn:disabled{background:#95a5a6;cursor:not-allowed}
        .ai-verified-tag{color:#27ae60;font-weight:800;font-size:12px;white-space:nowrap}
        .ai-reset{color:#7c5cbf;font-weight:700;font-size:13px;cursor:pointer;background:none;border:none;padding:8px 6px}
        @media(max-width:639px){.ai-modal-overlay{align-items:flex-end}.ai-modal-content{border-radius:22px 22px 0 0;max-height:94vh}.ai-modal-body{padding:16px 16px 24px}}
        .instructions-title { font-weight: 700; margin-bottom: 8px; }
        .instructions-text { font-size: 13px; color: #7f8c8d; line-height: 1.5; }
        
        /* Modal */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); display: none; align-items: flex-end;
            z-index: 1000;
        }
        .modal-content {
            background: white; border-radius: 24px 24px 0 0; width: 100%;
            max-height: 80vh; overflow-y: auto; padding-bottom: 20px;
        }
        .modal-header { display: flex; justify-content: space-between; padding: 20px; border-bottom: 1px solid #ecf0f1; }
        .cart-item { display: flex; justify-content: space-between; padding: 16px; border-bottom: 1px solid #ecf0f1; }
        .cart-actions { display: flex; gap: 8px; align-items: center; }
        .cart-qty-btn { width: 30px; height: 30px; border-radius: 15px; background: #f8f9fa; border: 1px solid #ddd; cursor: pointer; }
        .cart-remove { background: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; }
        .cart-summary { padding: 20px; background: #f8f9fa; border-top: 1px solid #ecf0f1; }
        .checkout-modal-btn { background: #2ecc71; margin: 16px; padding: 16px; border-radius: 14px; text-align: center; color: white; font-weight: 700; cursor: pointer; }
        
        .loading-spinner { width: 40px; height: 40px; border: 3px solid #e0e0e0; border-top-color: #2ecc71; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 20px auto; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 16px; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.toast')
@include('partials.photo-viewer')
@verbatim
    <div class="mobile-menu-toggle" id="mobileMenuToggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2">
            <path d="M3 12H21M3 6H21M3 18H21"/>
        </svg>
    </div>
    <div class="muuzaji-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><div class="logo-area"><div class="logo-icon" data-i18n="muuzaji_uza.logo_short">D</div><div class="logo-text"><h2 data-i18n="muuzaji_uza.logo_brand">DukaMkononi</h2><p data-i18n="muuzaji_uza.logo_tagline">Muuzaji Portal</p></div></div></div>
            <div class="nav-items">
                <a href="profaili" class="nav-item"><div class="nav-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21C4 16.6 7.6 13 12 13C16.4 13 20 16.6 20 21" stroke-linecap="round"/></svg></div><span class="nav-label" data-i18n="muuzaji_uza.nav_profile">Profaili</span></a>
                <a href="mauzo" class="nav-item"><div class="nav-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5V9.51M18 14.5V14.51" stroke-linecap="round"/></svg></div><span class="nav-label" data-i18n="muuzaji_uza.nav_sales">Mauzo</span></a>
                <a href="matumizi" class="nav-item"><div class="nav-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z"/><path d="M8 7V17M12 7V17M16 7V17"/></svg></div><span class="nav-label" data-i18n="muuzaji_uza.nav_expenses">Matumizi</span></a>
                <a href="uza" class="nav-item active"><div class="nav-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M3 3H5L7.4 15.2C7.55 15.95 8.2 16.5 8.97 16.5H17.6C18.32 16.5 18.94 16 19.08 15.3L21 7H6" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span class="nav-label" data-i18n="muuzaji_uza.nav_sell">Uza</span></a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info"><div class="user-avatar" id="userAvatar">M</div><div class="user-details"><div class="user-name" id="userName">Muuzaji</div><div class="user-role" data-i18n="muuzaji_uza.nav_seller">Muuzaji</div></div></div>
                <div class="logout-btn" id="logoutBtn"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 3H19C20.1 3 21 3.9 21 5V19C21 20.1 20.1 21 19 21H15M10 17L15 12L10 7M15 12H3"/></svg> <span data-i18n="muuzaji_uza.btn_logout">Ondoka</span></div>
            </div>
        </aside>
        <main class="main-content"><div class="page-container" id="uzaContent"></div></main>
    </div>

    <!-- Cart Modal -->
    <div id="cartModal" class="modal-overlay"><div class="modal-content" id="cartModalContent"></div></div>

    <script>
        const API_BASE_URL = '';

        // Swahili fallback used when the language widget is not present (and
        // for script-built strings). Values mirror the sw catalog of the
        // muuzaji_uza section in locales.json.
        const SW = {
            page_title: 'Uza - Dukamkononi Muuzaji',
            logo_short: 'D',
            logo_brand: 'DukaMkononi',
            logo_tagline: 'Muuzaji Portal',
            nav_profile: 'Profaili',
            nav_sales: 'Mauzo',
            nav_expenses: 'Matumizi',
            nav_sell: 'Uza',
            nav_seller: 'Muuzaji',
            btn_logout: 'Ondoka',
            photo_alt: 'Picha',
            err_no_permission: 'Huna ruhusa',
            not_set: 'Haijawekwa',
            seller_generic: 'Muuza',
            no_products: 'Hakuna bidhaa zilizopo',
            no_category: 'Hakuna kategoria',
            yours: 'Yako',
            err_select_product_first: 'Chagua bidhaa kwanza',
            err_no_selling_price: '"{product}" hana bei ya kuuzia. Weka bei ya kuuzia kwanza (kwenye Bidhaa Mpya) ili usiue bei ya kununua.',
            err_invalid_qty: 'Weka kiasi sahihi',
            err_insufficient_qty: 'Kiasi hakitoshi. Inabakia: {available}',
            msg_added_to_cart: 'Imeongezwa kikapuni! Kikapu kina bidhaa {count}',
            err_empty_cart: 'Tafadhali ongeza bidhaa kwenye kikapu',
            err_server: 'Server error {status}',
            msg_sale_complete: 'Mauzo yamekamilika! Jumla: {total}',
            err_server_retry: 'Server imekutana na hitilafu ({status}). Jaribu tena.',
            err_sale_failed: 'Mauzo yameshindikana ({status}).',
            err_network: 'Hitilafu ya mtandao',
            cart_title: 'Kikapu chako',
            cart_empty: 'Kikapu tupu',
            btn_delete: 'Futa',
            cart_product_types: 'Aina za Bidhaa:',
            cart_total_qty: 'Jumla ya Kiasi:',
            cart_total_payment: 'JUMLA YA MALIPO:',
            btn_continue_sale: 'Endelea na Mauzo',
            title_sell_product: 'Uza Bidhaa',
            label_business: 'Biashara:',
            default_business: 'Biashara Yako',
            label_selling_as: 'Unauza kama:',
            label_select_product: 'Chagua Bidhaa',
            btn_refresh: 'Sasisha',
            search_placeholder: 'Tafuta bidhaa...',
            err_load_products: 'Imeshindikana kupakia bidhaa',
            err_load_products_sub: 'Hitilafu ya mtandao au server. Hakikisha umeunganishwa kwenye internet kisha jaribu tena.',
            btn_try_again: '🔄 Jaribu tena',
            products_shown: '{count} bidhaa zinaonyeshwa',
            label_selected_product: 'Bidhaa Iliyochaguliwa:',
            label_stock: 'Stock: {stock}',
            label_selling_price: 'Bei ya kuuzia:',
            hint_dock: 'Tumia dock ya chini kuweka idadi na kuongeza kikapuni — inafuata scroll yako.',
            label_customer_name: 'Jina la Mteja',
            label_optional: '(Si-Lazima)',
            placeholder_customer_name: 'Weka jina la mteja (au acha tupu)',
            label_customer_phone: 'Namba ya Simu ya Mteja',
            placeholder_customer_phone: 'Weka namba ya simu',
            label_sale_date: 'Tarehe ya Mauzo',
            out_of_stock: 'Hakuna Stock',
            btn_add: 'Ongeza',
            cart_link: 'Kikapu ›',
            btn_complete: 'KAMILISHA',
            ai_entry_title: 'Ingiza Mauzo kwa Picha (AI)',
            ai_entry_sub: 'Ulioandika kwenye karatasi? Piga picha, AI itayafananisha na bidhaa zako na kuyaweka kikapuni',
            instructions_title: 'Maelekezo ya Kikapu:',
            instruction_1: '1. Chagua bidhaa kutoka kwenye orodha',
            instruction_2: '2. Weka kiasi unachouza',
            instruction_3: '3. Bofya "Ongeza Kikapuni"',
            instruction_4: '4. Rudia kwa bidhaa zingine',
            instruction_5: '5. Kamilisha mauzo',
            btn_close_x: '✖',
            ai_camera_title: 'Piga picha ya mauzo yaliyoandikwa kwenye karatasi',
            ai_camera_desc: 'AI itasoma maandishi, kuyafananisha na bidhaa zako zilizopo, na kutumia bei uliyoandika — au bei ya kuuzia ya mfumo ukawa hauna.',
            ai_max_photos: 'Unaweza kutuma picha hadi 6.',
            btn_remove_x: '✕',
            btn_take_photo: 'Piga Picha Sasa (Kamera)',
            btn_process_sales: 'Chambua Mauzo',
            ai_progress_preparing: 'Inatayarisha picha...',
            ai_progress_reading: 'Inasoma maandishi kwenye karatasi...',
            ai_progress_matching: 'Inatafuta bidhaa zinazofanana...',
            ai_progress_sending: 'Inatuma kwa Gemini...',
            ai_progress_comparing: 'Inafananisha na bidhaa zako...',
            ai_progress_calculating: 'Inakokotoa bei...',
            ai_progress_preparing_results: 'Inatayarisha matokeo...',
            ai_progress_almost: 'Karibu kuisha...',
            ai_progress_wait: 'Tafadhali subiri...',
            ai_col_written: 'Ulioandika',
            ai_col_product: 'Bidhaa (mfumo)',
            ai_col_qty: 'Idadi',
            ai_col_price: 'Bei (kipimo)',
            ai_col_total: 'Jumla',
            ai_col_correct: 'Haki',
            ai_col_save: 'Hifadhi',
            ai_hint: 'Rekebisha idadi au bei kama inahitajika, kisha bonyeza "Hifadhi" kwa kila mstari au "Hifadhi Zote". Mauzo yataingia kwenye rekodi na hisa itapungua.',
            btn_start_over: 'Anza Upya',
            btn_save_all: 'Hifadhi Zote',
            btn_cancel: 'Ghairi',
            btn_continue: 'Endelea',
            err_photo_too_large_compressed: 'Picha "{name}" ni kubwa mno baada ya kubanwa.',
            err_photos_total_too_large: 'Picha zote ni kubwa mno kwa jumla. Ondoa baadhi.',
            err_photo_too_large: 'Picha "{name}" ni kubwa mno.',
            err_photo_rejected: 'Picha "{name}" imekataliwa.',
            err_too_many_photos: 'Umechagua picha {selected}. Upeo ni {max}.',
            err_analysis_failed: 'Uchambuzi umeshindikana. Jaribu tena.',
            err_analysis_failed_gemini: 'Uchambuzi umeshindikana (Gemini ilikuwa na tatizo). Jaribu tena baada ya muda mfupi.',
            err_select_photo_first: 'Chagua angalau picha moja kwanza.',
            ai_confirm_reprocess_title: 'Chambua tena?',
            ai_confirm_reprocess_msg: 'Matokeo ya sasa yataondolewa. Picha zako zitabaki.',
            ai_results_ready: 'Matokeo yapo. Hakiki kila mstari kisha ubonyeze "Hifadhi".',
            err_no_sales_recognized: 'Hakuna mauzo yaliyotambuliwa. Jaribu picha nyepesi zenye mwanga mzuri.',
            err_network_retry: 'Hitilafu ya mtandao. Jaribu tena.',
            ai_summary_rows: 'Mistari: {count}',
            ai_summary_matched: 'Zilizofananishwa: {count}',
            ai_badge_verify: 'Hakiki',
            ai_summary_review: 'Hakiki: {count}',
            ai_summary_saved: 'Zimehifadhiwa: {count}',
            ai_row_stock: 'Hisa: {stock}',
            ai_row_default_price: 'Bei chaguomsingi: {price}',
            ai_select_placeholder: '— Chagua bidhaa —',
            product_generic: 'Bidhaa',
            ai_no_match: 'Hakuna fananio',
            ai_saved_tag: 'Imehifadhiwa',
            ai_default_word: 'chaguomsingi',
            err_row_select_product: 'Mstari "{row}": chagua bidhaa ya mfumo kwanza.',
            err_row_invalid_qty: 'Mstari "{row}": idadi si sahihi.',
            err_row_no_price: 'Mstari "{row}": bei haipo (wala bei chaguomsingi).',
            err_stock_insufficient: '"{product}": hisa inatosha tu {stock} (ulioomba {qty}).',
            msg_row_saved: '"{product}" × {qty} imehifadhiwa ({total}).',
            err_save_failed: 'Imeshindikana kuhifadhi.',
            err_stock_issue: '"{product}": hisa inatosha tu {available}.',
            msg_already_saved: 'Mauzo haya yameshahifadhiwa tayari.',
            ai_nothing_pending: 'Hakuna mistari inayosubiri (au hakuna iliyofananishwa).',
            ai_problem_no_product: 'Mstari {row}: hakuna bidhaa ya mfumo iliyochaguliwa',
            ai_problem_bad_qty: 'Mstari {row}: idadi si sahihi',
            ai_problem_no_price: 'Mstari {row}: bei haipo',
            ai_problem_stock: 'Mstari {row}: hisa ya "${prod.name}" inatosha tu ${prod.stock}',
            ai_fix_before_save: 'Rekebisha kabla ya kuhifadhi zote:',
            ai_confirm_save_all_title: 'Hifadhi Zote?',
            ai_confirm_save_all_msg: '{count} mistari ya mauzo itaingia kwenye rekodi na hisa itapungua.',
            ai_saving_progress: 'Inahifadhi {index}/{total}',
            msg_all_saved: 'Mistari yote {count} imehifadhiwa kikamilifu!',
            msg_some_failed: '{saved} zimehifadhiwa, {failed} zilishindikana — rekebisha na ujaribu tena.',
            ai_import_notes: 'AI import: {name}',
            sale_notes_seller: 'Muuzaji: {name}',
            business_generic: 'Biashara',
            stock_left: '{count} imebaki',
            in_cart_suffix: '({count} kikapuni)',
        };

        function t(key, params) {
            const full = key.indexOf('muuzaji_uza.') === 0 ? key : 'muuzaji_uza.' + key;
            if (window.DM && typeof window.DM.t === 'function') {
                const hit = window.DM.t(full, params);
                if (hit !== full) return hit;
            }
            const bare = key.indexOf('.') > -1 ? key.split('.').pop() : key;
            let value = Object.prototype.hasOwnProperty.call(SW, bare) ? SW[bare] : key;
            if (params) {
                for (const k in params) value = String(value).split('{' + k + '}').join(String(params[k]));
            }
            return value;
        }

        // ============================== ICONS ==============================
        // Font Awesome 6 icon helper (same convention as the msimamizi pages).
        const FA_MAP = {
            user: 'fa-user', money: 'fa-money-bill-1', cart: 'fa-cart-shopping', box: 'fa-box',
            doc: 'fa-file-lines', refresh: 'fa-rotate-right', check: 'fa-check', x: 'fa-xmark',
            warning: 'fa-triangle-exclamation', search: 'fa-magnifying-glass',
            save: 'fa-floppy-disk', spinner: 'fa-spinner'
        };
        function ic(name, size = 16, cls = '') {
            return `<i class="fa-solid ${FA_MAP[name] || 'fa-circle-info'} ${cls}" style="font-size:${size}px;" aria-hidden="true"></i>`;
        }

        let userData = null;
        let products = [];
        let filteredProducts = [];
        let cart = [];
        let searchQuery = '';
        let loadingProducts = true;
        let loading = false;
        let selectedProduct = null;
        let quantity = '1';
        let customerName = '';
        let customerPhone = '';
        let saleDate = new Date().toISOString().split('T')[0];
        let showCart = false;
        let businessData = null;

        function getCurrentUser() {
            const token = localStorage.getItem('userToken');
            const userStr = localStorage.getItem('userData');
            if (token && userStr) {
                try { return JSON.parse(userStr); } catch(e) { return null; }
            }
            return null;
        }

        function updateSidebarUser() {
            const user = getCurrentUser();
            if (user) {
                const displayName = user.full_name || user.business_name || user.email?.split('@')[0] || t('nav_seller');
                document.getElementById('userName').innerHTML = escapeHtml(displayName);
                const avatarEl = document.getElementById('userAvatar');
                if (user.business_logo_url) {
                    avatarEl.classList.add('js-avatar-view');
avatarEl.setAttribute('data-full', user.business_logo_url);
avatarEl.setAttribute('data-name', displayName);
avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="${t('photo_alt')}">`;
                } else {
                    avatarEl.innerHTML = displayName.charAt(0).toUpperCase();
                }
            }
        }

        function checkAuth() {
            const user = getCurrentUser();
            if (!user) { window.location.href = '/login?role=muuzaji'; return false; }
            const role = user.role || '';
            if (role !== 'seller' && role !== 'muuzaji') { showToast(t('err_no_permission'), 'error'); window.location.href = '/home'; return false; }
            return true;
        }

        function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
        function formatCurrency(amount) { return `TZS ${(amount || 0).toLocaleString()}`; }

        // A product only has a selling price if one was actually recorded.
        // 0 is a real value, so an explicit null check is required.
        function hasSellingPrice(p) {
            return p && p.expected_selling_price !== null && p.expected_selling_price !== undefined && p.expected_selling_price !== '';
        }

        // Never show the buying price as if it were the selling price. When the
        // selling price was never set, say so instead of inventing a number.
        function sellingPriceLabel(p) {
            return hasSellingPrice(p) ? formatCurrency(p.expected_selling_price) : '<span style="color:#f39c12">' + t('not_set') + '</span>';
        }

        function getCartTotal() { return cart.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0); }
        function getTotalItems() { return cart.reduce((sum, item) => sum + item.quantity, 0); }
        function getInCartQty(productId) { const item = cart.find(i => i.product_id === productId); return item ? item.quantity : 0; }

        async function loadUserData() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/user/profile`, { headers: { 'Authorization': `Bearer ${token}` } });
                if (res.ok) userData = await res.json();
                else { const stored = localStorage.getItem('userData'); if (stored) userData = JSON.parse(stored); }
            } catch(e) { const stored = localStorage.getItem('userData'); if (stored) userData = JSON.parse(stored); }
            if (userData) await loadProducts();
        }

        let productsError = false;

        async function loadProducts() {
            const token = localStorage.getItem('userToken');
            loadingProducts = true;
            render();
            try {
                const res = await fetch(`${API_BASE_URL}/api/products/my`, { headers: { 'Authorization': `Bearer ${token}` } });
                if (res.ok) {
                    let data = await res.json();
                    if (!Array.isArray(data)) data = [];
                    products = data.filter(p => p.is_active !== false).map(p => ({
                        // A missing selling price stays null. Falling back to
                        // p.price would show the BUYING price as the selling price
                        // and would record the sale at cost.
                        ...p, expected_selling_price: p.expected_selling_price ?? null,
                        seller_name: p.seller_name || t('seller_generic')
                    }));
                    productsError = false;
                } else { products = []; productsError = true; }
                filteredProducts = products;
            } catch(e) { products = []; filteredProducts = []; productsError = true; }
            finally { loadingProducts = false; render(); }
        }

        function filterProducts() {
            if (!searchQuery.trim()) filteredProducts = products;
            else {
                const q = searchQuery.toLowerCase();
                filteredProducts = products.filter(p => p.name.toLowerCase().includes(q) || (p.category && p.category.toLowerCase().includes(q)));
            }
            // Typing must only refresh the product list — never re-render the
            // whole page, which would destroy the search input mid-word.
            renderProductGrid();
        }

        // Re-renders ONLY the product list inside its host (never the search
        // input itself), so typing stays cursor-stable.
        function renderProductGrid() {
            const gridHost = document.getElementById('productGridHost');
            if (!gridHost) { render(); return; }
            gridHost.innerHTML = filteredProducts.length === 0 ? `<div style="text-align:center;padding:40px;">${t('no_products')}</div>` :
                filteredProducts.map(p => {
                    const inCart = getInCartQty(p.id);
                    const avail = p.stock - inCart;
                    const stockClass = avail <= 0 ? 'out' : (avail <= 5 ? 'low' : '');
                    return `
                        <div class="product-item ${selectedProduct?.id === p.id ? 'selected' : ''} ${avail <= 0 ? 'out-of-stock' : ''}" onclick="${avail > 0 ? `selectProduct('${p.id}')` : ''}">
                            <div class="product-header"><span class="product-name">${escapeHtml(p.name)}</span><span class="stock-badge ${stockClass}">${t('stock_left', { count: avail })}${inCart > 0 ? ' ' + t('in_cart_suffix', { count: inCart }) : ''}</span></div>
                            <div class="product-category">${escapeHtml(p.category || t('no_category'))}</div>
                            <div class="product-footer"><span class="price">${sellingPriceLabel(p)}</span><span class="owner-badge">${p.seller_id === userData?.id ? t('yours') : t('business_generic')}</span></div>
                        </div>
                    `;
                }).join('');
        }

        function addToCart() {
            if (!selectedProduct) { showToast(t('err_select_product_first'), 'warning'); return; }
            if (!hasSellingPrice(selectedProduct)) {
                showToast(t('err_no_selling_price', { product: selectedProduct.name }), 'warning');
                return;
            }
            const qty = parseInt(quantity);
            if (isNaN(qty) || qty <= 0) { showToast(t('err_invalid_qty'), 'warning'); return; }
            const inCart = getInCartQty(selectedProduct.id);
            const available = selectedProduct.stock - inCart;
            if (qty > available) { showToast(t('err_insufficient_qty', { available: available }), 'warning'); return; }
            
            const existing = cart.find(i => i.product_id === selectedProduct.id);
            if (existing) {
                existing.quantity += qty;
                existing.total_price = existing.quantity * existing.unit_price;
            } else {
                cart.push({
                    product_id: selectedProduct.id, name: selectedProduct.name, quantity: qty,
                    unit_price: selectedProduct.expected_selling_price,
                    total_price: qty * selectedProduct.expected_selling_price,
                    original_stock: selectedProduct.stock, seller_id: selectedProduct.seller_id, seller_name: selectedProduct.seller_name
                });
            }
            selectedProduct = null;
            quantity = '1';
            render();
            showToast(t('msg_added_to_cart', { count: cart.length }), 'success');
        }

        function removeFromCart(productId) { cart = cart.filter(i => i.product_id !== productId); render(); }
        function updateCartQty(productId, delta) {
            const item = cart.find(i => i.product_id === productId);
            if (!item) return;
            const product = products.find(p => p.id === productId);
            if (!product) return;
            const newQty = item.quantity + delta;
            const otherInCart = cart.filter(i => i.product_id !== productId).reduce((s, i) => s + i.quantity, 0);
            const available = product.stock - otherInCart;
            if (newQty < 1) { removeFromCart(productId); return; }
            if (newQty > available) { showToast(t('err_insufficient_qty', { available: available }), 'warning'); return; }
            item.quantity = newQty;
            item.total_price = newQty * item.unit_price;
            render();
        }

        let customers = [];

        async function loadCustomers() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/customers/my`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (res.ok) { customers = await res.json(); }
            } catch(e) { console.error('Error loading customers:', e); }
        }

        async function getOrCreateCustomer(name, phone) {
            const token = localStorage.getItem('userToken');
            if (!token) return null;
            try {
                const existing = customers.find(c => c.name?.toLowerCase() === name.toLowerCase());
                if (existing) return existing.id;
                const res = await fetch(`${API_BASE_URL}/api/customers`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name.trim(), phone: phone?.trim() || '', email: '' })
                });
                if (res.ok) {
                    const result = await res.json();
                    const newId = result.customer?.id || result.id;
                    await loadCustomers();
                    return newId || null;
                }
            } catch(e) { console.error('Error creating customer:', e); }
            return null;
        }

        async function handleSale() {
            if (cart.length === 0) { showToast(t('err_empty_cart'), 'warning'); return; }
            if (loading) return; // double-submit guard: one request per tap
            loading = true;
            const clientSaleKey = 'uza-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
            render();
            const token = localStorage.getItem('userToken');
            try {
                const items = cart.map(i => ({ product_id: i.product_id, quantity: i.quantity, unit_price: i.unit_price }));
                const saleData = { items, sale_date: saleDate, payment_method: 'cash', notes: t('sale_notes_seller', { name: userData?.full_name || userData?.email }), clientSaleKey };
                
                // Create/get customer and attach customer_id if name provided
                if (customerName.trim()) {
                    const customerId = await getOrCreateCustomer(customerName.trim(), customerPhone.trim());
                    if (customerId) { saleData.customer_id = customerId; }
                    saleData.customer_name = customerName.trim();
                    saleData.customer_phone = customerPhone.trim();
                }
                
                const sendSale = async () => {
                    const r = await fetch(`${API_BASE_URL}/api/sales`, {
                        method: 'POST', headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                        body: JSON.stringify(saleData)
                    });
                    let body = null;
                    const text = await r.text();
                    try { body = text ? JSON.parse(text) : null; } catch (e) { body = null; }
                    return { res: r, body };
                };
                let res, result;
                try {
                    ({ res, body: result } = await sendSale());
                } catch (e1) {
                    // Network hiccup — ONE automatic retry. Safe from double
                    // charging because clientSaleKey makes the request
                    // idempotent on the server.
                    ({ res, body: result } = await sendSale());
                }
                if (!result && res && res.status >= 500) {
                    throw new Error(t('err_server', { status: res.status }));
                }
                if (res.ok) {
                    const total = getCartTotal();
                    showToast(t('msg_sale_complete', { total: formatCurrency(total) }), 'success');
                    cart = []; customerName = ''; customerPhone = '';
                    await loadProducts();
                } else if (result && result.error) {
                    showToast(result.error, 'error');
                } else {
                    showToast(res.status >= 500 ? t('err_server_retry', { status: res.status }) : t('err_sale_failed', { status: res.status }), 'error');
                }
            } catch(e) { showToast(e && e.message ? e.message : t('err_network'), 'error'); }
            finally { loading = false; render(); }
        }

        function openCartModal() { showCart = true; renderCartModal(); }
        function closeCartModal() { showCart = false; render(); }

        function renderCartModal() {
            const modal = document.getElementById('cartModal');
            const content = document.getElementById('cartModalContent');
            if (!showCart) { modal.style.display = 'none'; return; }
            modal.style.display = 'flex';
            content.innerHTML = `
                <div class="modal-header"><div class="modal-title">${ic('cart', 17)} ${t('cart_title')}</div><span style="cursor:pointer;" onclick="closeCartModal()"><i class="fa-solid fa-xmark" style="font-size:22px;" aria-hidden="true"></i></span></div>
                ${cart.length === 0 ? `<div style="text-align:center;padding:40px;">${t('cart_empty')}</div>` : `
                    ${cart.map(item => `
                        <div class="cart-item">
                            <div><div style="font-weight:700;">${escapeHtml(item.name)}</div><div>${formatCurrency(item.unit_price)} × ${item.quantity}</div></div>
                            <div class="cart-actions">
                                <button class="cart-qty-btn" onclick="updateCartQty('${item.product_id}', -1)">-</button>
                                <span style="min-width:30px;text-align:center;">${item.quantity}</span>
                                <button class="cart-qty-btn" onclick="updateCartQty('${item.product_id}', 1)">+</button>
                                <button class="cart-remove" onclick="removeFromCart('${item.product_id}')">${t('btn_delete')}</button>
                            </div>
                        </div>
                    `).join('')}
                    <div class="cart-summary">
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span>${t('cart_product_types')}</span><strong>${cart.length}</strong></div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span>${t('cart_total_qty')}</span><strong>${getTotalItems()}</strong></div>
                        <div style="height:1px;background:#ecf0f1;margin:10px 0;"></div>
                        <div style="display:flex;justify-content:space-between;"><span style="font-weight:700;">${t('cart_total_payment')}</span><strong style="color:#27ae60;">${formatCurrency(getCartTotal())}</strong></div>
                    </div>
                    <div class="checkout-modal-btn" onclick="closeCartModal();document.getElementById('saleBtn').scrollIntoView({behavior:'smooth'});">${t('btn_continue_sale')}</div>
                `}
            `;
        }

        function render() {
            const container = document.getElementById('uzaContent');
            const inCartQty = selectedProduct ? getInCartQty(selectedProduct.id) : 0;
            const availableStock = selectedProduct ? selectedProduct.stock - inCartQty : 0;
            const isOut = selectedProduct ? availableStock <= 0 : false;
            
            container.innerHTML = `
                <div class="header-card">
                    <div class="header-top"><div class="title">${ic('cart', 18)} ${t('title_sell_product')}</div><button class="refresh-btn" onclick="loadProducts()">${ic('refresh', 16)}</button></div>
                    <div>${t('label_business')} <strong>${escapeHtml(userData?.business_name || t('default_business'))}</strong></div>
                    <div style="font-size:12px;color:#7f8c8d;"${t('label_selling_as')} ${escapeHtml(userData?.full_name || userData?.email)}</div>
                </div>
                
                <div class="form-card">
                    <div class="section-header"><span class="label">${t('label_select_product')}</span><button class="refresh-btn" style="font-size:14px;" onclick="loadProducts()">${ic('refresh', 13)} ${t('btn_refresh')}</button></div>
                    <div class="search-box">${ic('search', 15)}<input type="text" id="searchInput" class="search-input" placeholder="${t('search_placeholder')}" value="${escapeHtml(searchQuery)}"></div>
                    
                    ${loadingProducts ? `<div class="loading-spinner"></div>` : productsError ? `
                        <div style="text-align:center;padding:30px;">
                            <div style="color:#f39c12;">${ic('warning', 32)}</div>
                            <div style="margin:8px 0;font-weight:600;">${t('err_load_products')}</div>
                            <div style="font-size:12px;color:#7f8c8d;margin-bottom:12px;">${t('err_load_products_sub')}</div>
                            <button class="refresh-btn" style="padding:8px 20px;" onclick="loadProducts()">${t('btn_try_again')}</button>
                        </div>` : `
                        <div class="products-grid" id="productGridHost">
                            ${filteredProducts.length === 0 ? `<div style="text-align:center;padding:40px;">${t('no_products')}</div>` : 
                                filteredProducts.map(p => {
                                    const inCart = getInCartQty(p.id);
                                    const avail = p.stock - inCart;
                                    const stockClass = avail <= 0 ? 'out' : (avail <= 5 ? 'low' : '');
                                    return `
                                        <div class="product-item ${selectedProduct?.id === p.id ? 'selected' : ''} ${avail <= 0 ? 'out-of-stock' : ''}" onclick="${avail > 0 ? `selectProduct('${p.id}')` : ''}">
                                            <div class="product-header"><span class="product-name">${escapeHtml(p.name)}</span><span class="stock-badge ${stockClass}">${t('stock_left', { count: avail })}${inCart > 0 ? ' ' + t('in_cart_suffix', { count: inCart }) : ''}</span></div>
                                            <div class="product-category">${escapeHtml(p.category || t('no_category'))}</div>
                                            <div class="product-footer"><span class="price">${sellingPriceLabel(p)}</span><span class="owner-badge">${p.seller_id === userData?.id ? t('yours') : t('business_generic')}</span></div>
                                        </div>
                                    `;
                                }).join('')
                            }
                        </div>
                        <div style="font-size:12px;color:#7f8c8d;margin-top:12px;text-align:center;">${t('products_shown', { count: filteredProducts.length })}</div>
                    `}
                </div>
                
                ${selectedProduct ? `
                    <div class="selected-product-card">
                        <div style="font-weight:700;">${ic('box', 15)} ${t('label_selected_product')}</div>
                        <div><strong>${escapeHtml(selectedProduct.name)}</strong> <span style="font-size:12px;color:#7f8c8d;">${t('label_stock', { stock: availableStock })}</span></div>
                        <div>${t('label_selling_price')} ${sellingPriceLabel(selectedProduct)}</div>
                        <div style="font-size:12px;color:#7f8c8d;margin-top:6px;">${t('hint_dock')}</div>
                    </div>
                ` : ''}
                
                <div class="form-card">
                    <div class="label">${t('label_customer_name')} <span style="font-size:11px;color:#95a5a6;">${t('label_optional')}</span></div>
                    <input type="text" id="customerNameInput" class="input-field" placeholder="${t('placeholder_customer_name')}" value="${escapeHtml(customerName)}">
                    
                    <div class="label" style="margin-top:16px;">${t('label_customer_phone')}</div>
                    <input type="tel" id="customerPhoneInput" class="input-field" placeholder="${t('placeholder_customer_phone')}" value="${escapeHtml(customerPhone)}">
                    
                    <div class="label" style="margin-top:16px;">${t('label_sale_date')}</div>
                    <input type="date" id="saleDateInput" class="input-field" value="${saleDate}">
                </div>
                
                <div class="floating-dock" id="floatingDock">
                    ${selectedProduct ? `
                        <div class="dock-row">
                            <div class="dock-prod">
                                <div class="dock-name">${escapeHtml(selectedProduct.name)}</div>
                                <div class="dock-price">${sellingPriceLabel(selectedProduct)} • ${t('label_stock', { stock: availableStock })}</div>
                            </div>
                            <div class="quantity-control dock-qty">
                                <button class="qty-btn" onclick="changeQty(-1)">−</button>
                                <input type="number" id="qtyInput" class="qty-input" value="${quantity}" min="1" max="${availableStock}">
                                <button class="qty-btn" onclick="changeQty(1)">+</button>
                            </div>
                            <button class="add-cart-btn dock-add ${isOut ? 'disabled' : ''}" onclick="addToCart()" ${isOut ? 'disabled' : ''}>${isOut ? t('out_of_stock') : '<i class="fa-solid fa-plus" style="font-size:13px"></i> ' + t('btn_add')}</button>
                        </div>
                    ` : ''}
                    <div class="dock-row">
                        <div class="dock-cart-info" onclick="openCartModal()">
                            <span class="dock-cart-badge">${getTotalItems()}</span>
                            <span>${formatCurrency(getCartTotal())}</span>
                            <span style="margin-left:auto;font-size:12px;opacity:.8;">${t('cart_link')}</span>
                        </div>
                        <button class="dock-checkout ${cart.length === 0 || loading ? 'disabled' : ''}" ${cart.length === 0 || loading ? 'disabled' : ''} onclick="handleSale()">
                            ${loading ? '<div class="loading-spinner" style="width:18px;height:18px;margin:0 auto;"></div>' : t('btn_complete')}
                        </button>
                    </div>
                </div>
                
                <div class="ai-entry-btn" onclick="openAiSaleModal()">
                    <span class="ai-entry-ic"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                    <div style="flex:1">
                        <div style="font-weight:700;color:#2c3e50">${t('ai_entry_title')}</div>
                        <div style="font-size:12px;color:#7f8c8d">${t('ai_entry_sub')}</div>
                    </div>
                    <span style="color:#95a5a6;font-size:20px">›</span>
                </div>
                
                <div class="instructions-box">
                    <div class="instructions-title">${ic('doc', 14)} ${t('instructions_title')}</div>
                    <div class="instructions-text">
                        ${t('instruction_1')}<br>
                        ${t('instruction_2')}<br>
                        ${t('instruction_3')}<br>
                        ${t('instruction_4')}<br>
                        ${t('instruction_5')}
                    </div>
                </div>
            `;
            
            document.getElementById('searchInput')?.addEventListener('input', (e) => { searchQuery = e.target.value; filterProducts(); });
            document.getElementById('customerNameInput')?.addEventListener('input', (e) => { customerName = e.target.value; });
            document.getElementById('customerPhoneInput')?.addEventListener('input', (e) => { customerPhone = e.target.value; });
            document.getElementById('saleDateInput')?.addEventListener('input', (e) => { saleDate = e.target.value; });
            const qtyInput = document.getElementById('qtyInput');
            if (qtyInput) qtyInput.addEventListener('input', (e) => { let val = parseInt(e.target.value); if (isNaN(val) || val < 1) val = 1; quantity = val.toString(); e.target.value = quantity; });
        }

        window.selectProduct = (id) => {
            const product = products.find(p => p.id === id);
            if (product) { selectedProduct = product; quantity = '1'; render(); }
        };
        window.changeQty = (delta) => {
            let q = parseInt(quantity) || 1;
            q += delta;
            if (q < 1) q = 1;
            const inCart = selectedProduct ? getInCartQty(selectedProduct.id) : 0;
            const max = selectedProduct ? selectedProduct.stock - inCart : 999;
            if (q > max) q = max;
            quantity = q.toString();
            render();
        };
        window.addToCart = addToCart;
        window.removeFromCart = removeFromCart;
        window.updateCartQty = updateCartQty;
        window.openCartModal = openCartModal;
        window.closeCartModal = closeCartModal;
        window.handleSale = handleSale;
        window.loadProducts = loadProducts;

        function setupSidebar() {
            document.getElementById('mobileMenuToggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
            document.getElementById('logoutBtn').addEventListener('click', () => { localStorage.clear(); window.location.href = '/login?role=muuzaji'; });
        }

        async function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
            await loadUserData();
            await loadCustomers();
        }
        // Follow the language: static markup is handled by data-i18n, but the
        // product grid, floating dock, cart modal and AI modal are script-built.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => {
                render();
                updateSidebarUser();
                if (window.refreshAiSaleUi) window.refreshAiSaleUi();
            });
        }

        init();
    </script>
<!-- ===== AI Sales Import Modal ===== -->
<div id="aiSaleModal" class="ai-modal-overlay">
<div class="ai-modal-content">
<div class="ai-modal-header"><div class="ai-modal-title" data-i18n="muuzaji_uza.ai_entry_title">Ingiza Mauzo kwa Picha (AI)</div><div class="ai-modal-close" onclick="closeAiSaleModal()" data-i18n="muuzaji_uza.btn_close_x">✖</div></div>
<div class="ai-modal-body">
<div id="aiSaleBanner" class="ai-banner"></div>
<div id="aiSaleUploadSection">
<div id="aiSaleUploadZone" class="ai-upload-zone">
<div style="display:flex;justify-content:center;color:#7c5cbf;"><i class="fa-solid fa-camera" style="font-size:38px;"></i></div>
<div class="ai-uz-main" data-i18n="muuzaji_uza.ai_camera_title">Piga picha ya mauzo yaliyoandikwa kwenye karatasi</div>
<div class="ai-uz-sub"><span data-i18n="muuzaji_uza.ai_camera_desc">AI itasoma maandishi, kuyafananisha na bidhaa zako zilizopo, na kutumia bei uliyoandika — au bei ya kuuzia ya mfumo ukawa hauna.</span><br><span data-i18n="muuzaji_uza.ai_max_photos">Unaweza kutuma picha hadi 6.</span></div>
</div>
<button type="button" class="ai-btn ai-btn-camera" onclick="captureAiSaleImage()"><i class="fa-solid fa-camera" style="font-size:17px;"></i> <span data-i18n="muuzaji_uza.btn_take_photo">Piga Picha Sasa (Kamera)</span></button>
<input type="file" id="aiSaleFilesInput" accept="image/*" multiple style="display:none">
<div id="aiSalePreviewGrid" class="ai-preview-grid"></div>
<div class="ai-controls"><button type="button" class="ai-btn ai-btn-process" id="aiSaleProcessBtn" onclick="startAiSaleProcess()"><i class="fa-solid fa-barcode" style="font-size:17px;"></i> <span data-i18n="muuzaji_uza.btn_process_sales">Chambua Mauzo</span></button></div>
</div>
<div id="aiSaleProgress" class="ai-progress" style="display:none">
<div class="ai-dots"><span></span><span></span><span></span><span></span></div>
<div id="aiSaleProgressText" class="ai-progress-text" data-i18n="muuzaji_uza.ai_progress_preparing">Inatayarisha picha...</div>
</div>
<div id="aiSaleResultsWrap" style="display:none">
<div class="ai-results-summary" id="aiSaleResultsSummary"></div>
<div class="ai-table-scroll">
<table class="ai-table">
<thead><tr><th data-i18n="muuzaji_uza.ai_col_written">Ulioandika</th><th data-i18n="muuzaji_uza.ai_col_product">Bidhaa (mfumo)</th><th data-i18n="muuzaji_uza.ai_col_qty">Idadi</th><th data-i18n="muuzaji_uza.ai_col_price">Bei (kipimo)</th><th data-i18n="muuzaji_uza.ai_col_total">Jumla</th><th data-i18n="muuzaji_uza.ai_col_correct">Haki</th><th data-i18n="muuzaji_uza.ai_col_save">Hifadhi</th></tr></thead>
<tbody id="aiSaleTableBody"></tbody>
</table>
</div>
<div style="margin-top:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
<span style="font-size:12px;color:#7f8c8d" data-i18n="muuzaji_uza.ai_hint">Rekebisha idadi au bei kama inahitajika, kisha bonyeza "Hifadhi" kwa kila mstari au "Hifadhi Zote". Mauzo yataingia kwenye rekodi na hisa itapungua.</span>
<button type="button" class="ai-reset" onclick="resetAiSaleSession()" data-i18n="muuzaji_uza.btn_start_over">Anza Upya</button>
</div>
<div style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
<button type="button" class="ai-verify-btn" id="aiSaleCommitAllBtn" style="background:#27ae60;padding:12px 22px;font-size:14px" onclick="commitAllAiSaleRows()" data-i18n="muuzaji_uza.btn_save_all">Hifadhi Zote</button>
<span id="aiSaleCommitAllStatus" style="font-size:12px;color:#7f8c8d"></span>
</div>
</div>
</div>
</div>
</div>
<script>
(function(){
var AI_MAX_IMAGES=6;
var AI_MAX_PER_IMAGE_B64=6*1024*1024;
var AI_MAX_TOTAL_B64=9*1024*1024;
var AI_PROGRESS_MSGS=[t('ai_progress_preparing'),t('ai_progress_reading'),t('ai_progress_matching'),t('ai_progress_sending'),t('ai_progress_comparing'),t('ai_progress_calculating'),t('ai_progress_preparing_results'),t('ai_progress_almost'),t('ai_progress_wait')];
var aiImages=[];var aiRows=[];var aiProcessing=false;var aiCommitBusy=false;var aiBatchBusy=false;
var aiProgressTimer=null;var aiUploadReady=false;var aiTableSyncReady=false;
function aiToken(){return localStorage.getItem('userToken');}
function $(id){return document.getElementById(id);}
function esc(s){if(s===null||s===undefined)return'';return String(s).replace(/[&<>"']/g,function(m){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];});}
function fmt(n){return'TZS '+(parseInt(n||0)||0).toLocaleString();}
function showBanner(type,msg){var b=$('aiSaleBanner');if(!b)return;b.className='ai-banner '+(type==='info'?'info':'error');b.innerHTML=esc(msg);}
function hideBanner(){var b=$('aiSaleBanner');if(b)b.className='ai-banner';}
function stopProgress(){if(aiProgressTimer){clearInterval(aiProgressTimer);aiProgressTimer=null;}}
function startProgress(){stopProgress();var i=0;var el=$('aiSaleProgressText');if(el)el.textContent=AI_PROGRESS_MSGS[0];aiProgressTimer=setInterval(function(){i=(i+1)%AI_PROGRESS_MSGS.length;var e=$('aiSaleProgressText');if(e)e.textContent=AI_PROGRESS_MSGS[i];},2400);}
function confirmBox(title,msg){return new Promise(function(res){var ov=document.createElement('div');ov.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:3000;';var box=document.createElement('div');box.style.cssText='background:#fff;border-radius:28px;width:85%;max-width:340px;padding:24px 20px;text-align:center;';box.innerHTML='<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c3e50;">'+esc(title)+'</div><div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;white-space:pre-line;text-align:left;"></div><div style="display:flex;gap:12px;"><div style="flex:1;background:#95a5a6;padding:12px;border-radius:40px;color:#fff;cursor:pointer;font-weight:700;" class="aCNo">'+t('btn_cancel')+'</div><div style="flex:1;background:#27ae60;padding:12px;border-radius:40px;color:#fff;cursor:pointer;font-weight:700;" class="aCYes">'+t('btn_continue')+'</div></div>';ov.appendChild(box);document.body.appendChild(ov);box.querySelector('div:nth-child(2)').textContent=msg;box.querySelector('.aCYes').onclick=function(){ov.remove();res(true);};box.querySelector('.aCNo').onclick=function(){ov.remove();res(false);};ov.addEventListener('click',function(e){if(e.target===ov){ov.remove();res(false);}});});}
function checkB64(name,b64,cb){var total=aiImages.reduce(function(s,i){return s+(i.base64?i.base64.length:0);},0)+b64.length;if(b64.length>AI_MAX_PER_IMAGE_B64){showBanner('error',t('err_photo_too_large_compressed',{name:name}));cb(null);return;}if(total>AI_MAX_TOTAL_B64){showBanner('error',t('err_photos_total_too_large'));cb(null);return;}cb(b64);}
function compressImage(file,cb){if(file.size>14*1024*1024){showBanner('error',t('err_photo_too_large',{name:file.name}));cb(null);return;}var reader=new FileReader();reader.onerror=function(){cb(null);};reader.onload=function(){var original=reader.result;var img=new Image();img.onerror=function(){var b64=original.indexOf(',')>=0?original.slice(original.indexOf(',')+1):original;checkB64(file.name,b64,cb);};img.onload=function(){try{var maxDim=1600;var w=img.naturalWidth,h=img.naturalHeight;var scale=Math.max(w,h)>maxDim?maxDim/Math.max(w,h):1;var canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(w*scale));canvas.height=Math.max(1,Math.round(h*scale));var ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(img,0,0,canvas.width,canvas.height);var out=canvas.toDataURL('image/jpeg',0.85);var b64=out.indexOf(',')>=0?out.slice(out.indexOf(',')+1):out;checkB64(file.name,b64,cb);}catch(e){cb(null);}};img.src=original;};reader.readAsDataURL(file);}
function addFiles(files){if(aiProcessing)return;var list=Array.prototype.slice.call(files||[]);for(var i=0;i<list.length;i++){var f=list[i];var okType=f.type.indexOf('image/')===0||/\.(png|jpe?g|webp|heic|heif|bmp|tiff?)$/i.test(f.name);if(!okType){showBanner('error',t('err_photo_rejected',{name:f.name||''}));continue;}if(aiImages.length>=AI_MAX_IMAGES){showBanner('error',t('err_too_many_photos',{selected:aiImages.length,max:AI_MAX_IMAGES}));break;}var item={id:'ais'+Date.now()+'_'+i+Math.random().toString(36).slice(2,6),name:f.name||('image'+(i+1)),base64:null,preview:null};aiImages.push(item);compressImage(f,function(b64){var ix=aiImages.findIndex(function(x){return x.id===item.id;});if(ix<0)return;if(!b64){aiImages.splice(ix,1);refreshUploadUi();return;}aiImages[ix].base64=b64;aiImages[ix].preview='data:image/jpeg;base64,'+b64;refreshUploadUi();});}refreshUploadUi();}
function removeImage(id){aiImages=aiImages.filter(function(im){return im.id!==id;});refreshUploadUi();}
function refreshUploadUi(){var grid=$('aiSalePreviewGrid');if(grid){grid.innerHTML=aiImages.length===0?'':aiImages.map(function(im){return '<div class="ai-preview-item"><img src="'+esc(im.preview||'')+'" alt=""><span class="ai-preview-remove" onclick="removeAiSaleImage(\''+im.id+'\')">'+t('btn_remove_x')+'</span><div class="ai-preview-name">'+esc(im.name)+'</div></div>';}).join('');}var pb=$('aiSaleProcessBtn');if(pb){pb.disabled=aiImages.length===0||aiProcessing;pb.innerHTML='<i class="fa-solid fa-barcode" style="font-size:17px;"></i> <span data-i18n="muuzaji_uza.btn_process_sales">'+t('btn_process_sales')+'</span>'+(aiImages.length?' ('+aiImages.length+')':'');}}
function initUploadHandlers(){if(aiUploadReady)return;aiUploadReady=true;var zone=$('aiSaleUploadZone'),input=$('aiSaleFilesInput');if(zone)zone.addEventListener('click',function(){if(!aiProcessing)input.click();});['dragover','dragenter'].forEach(function(ev){zone.addEventListener(ev,function(e){e.preventDefault();zone.classList.add('drag');});});['dragleave','drop'].forEach(function(ev){zone.addEventListener(ev,function(e){e.preventDefault();zone.classList.remove('drag');});});zone.addEventListener('drop',function(e){if(e.dataTransfer&&e.dataTransfer.files)addFiles(e.dataTransfer.files);});input.addEventListener('change',function(){addFiles(input.files);input.value='';});}
function captureImage(){if(aiProcessing)return;var ci=document.createElement('input');ci.type='file';ci.accept='image/*';ci.setAttribute('capture','environment');ci.onchange=function(){if(ci.files&&ci.files.length)addFiles(ci.files);};ci.click();}
function aiErrMsg(data){if(!data||!data.code)return t('err_analysis_failed');var c=String(data.code).toUpperCase();if(['GEMINI_RATE_LIMIT','GEMINI_TIMEOUT','GEMINI_API_ERROR','GEMINI_NETWORK','GEMINI_KEY_MISSING','AI_SALES_IMPORT_ERROR','AI_IMPORT_ERROR'].indexOf(c)>=0)return t('err_analysis_failed_gemini');return data.error||t('err_analysis_failed');}
async function startProcess(){if(aiProcessing)return;var ready=aiImages.filter(function(im){return im.base64;});if(!ready.length){showBanner('error',t('err_select_photo_first'));return;}if(aiRows.length){var ok=await confirmBox(t('ai_confirm_reprocess_title'),t('ai_confirm_reprocess_msg'));if(!ok)return;}aiProcessing=true;aiRows=[];$('aiSaleResultsWrap').style.display='none';$('aiSaleUploadSection').style.display='none';$('aiSaleProgress').style.display='block';hideBanner();startProgress();try{var payload={images:ready.map(function(im){return{name:im.name,base64:im.base64};})};var r=await fetch(API_BASE_URL+'/api/sales/ai-import',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+aiToken()},body:JSON.stringify(payload)});var data=await r.json().catch(function(){return{};});$('aiSaleProgress').style.display='none';$('aiSaleUploadSection').style.display='block';if(r.ok&&data&&Array.isArray(data.items)&&data.items.length>0){aiRows=data.items.map(function(it){return Object.assign({committed:false},it);});$('aiSaleResultsWrap').style.display='block';renderResults();showBanner('info',t('ai_results_ready'));}else if(r.ok){showBanner('error',t('err_no_sales_recognized'));}else{showBanner('error',aiErrMsg(data));}}catch(e){$('aiSaleProgress').style.display='none';$('aiSaleUploadSection').style.display='block';showBanner('error',t('err_network_retry'));}finally{stopProgress();aiProcessing=false;refreshUploadUi();}}
function updateSummary(){var total=aiRows.length;var matched=aiRows.filter(function(r){return r.status==='MATCHED';}).length;var review=aiRows.filter(function(r){return r.needsReview;}).length;var done=aiRows.filter(function(r){return r.committed;}).length;var s=$('aiSaleResultsSummary');if(!s)return;s.innerHTML='<span class="ai-summary-chip">'+t('ai_summary_rows',{count:total})+'</span><span class="ai-summary-chip existing">'+t('ai_summary_matched',{count:matched})+'</span><span class="ai-summary-chip review">'+t('ai_summary_review',{count:review})+'</span><span class="ai-summary-chip ok">'+t('ai_summary_saved',{count:done})+'</span>';}
function attachSync(){if(aiTableSyncReady)return;aiTableSyncReady=true;var body=$('aiSaleTableBody');if(!body)return;body.addEventListener('input',function(e){var t=e.target;if(!t||!t.id)return;var m=t.id.match(/^aiS(Qty|Price|Prod)(\d+)$/);if(!m)return;var row=aiRows[parseInt(m[2],10)];if(!row)return;var v=t.value;if(m[1]==='Qty')row.quantity=v===''?null:parseInt(v,10);else if(m[1]==='Price')row.unitPrice=v===''?null:Number(v);else if(m[1]==='Prod')row.matchedProductId=v||null;});}
function renderResults(){var body=$('aiSaleTableBody');if(!body)return;updateSummary();attachSync();body.innerHTML=aiRows.map(function(row,i){var revBadge=row.needsReview?'<span class="ai-status review">'+t('ai_badge_verify')+'</span>':'';var conf=Math.round((row.confidence||0)*100);var stock=(row.currentStock!==null&&row.currentStock!==undefined)?('<div class="ai-conf-row">'+t('ai_row_stock',{stock:esc(row.currentStock)})+'</div>'):'';var defP=(row.unitPrice===null||row.unitPrice===undefined)?' <div class="ai-conf-row">'+t('ai_row_default_price',{price:fmt(row.defaultUnitPrice)})+'</div>':'';var warn=(row.warnings||[]).map(function(w){return '<div class="ai-warn">'+esc(w)+'</div>';}).join('');var prodOpts;if(row.status==='MATCHED'&&row.matchedProductId){prodOpts='<span class="ai-status existing">'+esc(row.matchedProductName||t('product_generic'))+'</span>'+stock;}else{var options=products.filter(function(p){return true;}).slice(0,400).map(function(p){return '<option value="'+p.id+'">'+esc(p.name)+'</option>';}).join('');prodOpts='<select id="aiSProd'+i+'" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:10px;font-size:13px"><option value="">'+t('ai_select_placeholder')+'</option>'+options+'</select>';}var qty=(row.quantity===null||row.quantity===undefined)?'':esc(row.quantity);var price=(row.unitPrice===null||row.unitPrice===undefined)?'':esc(row.unitPrice);var lineTotal=row.quantity&&row.unitPrice?(row.quantity*row.unitPrice):(row.quantity&&row.defaultUnitPrice?(row.quantity*row.defaultUnitPrice):null);var action=row.committed?'<span class="ai-verified-tag">'+t('ai_saved_tag')+'</span>':(row.status==='MATCHED'?'<button type="button" class="ai-verify-btn" id="aiSCommit'+i+'" onclick="commitAiSaleRow('+i+')">'+t('ai_col_save')+'</button>':'<span class="ai-status review">'+t('ai_no_match')+'</span>');return '<tr>'+'<td style="max-width:140px"><strong style="font-size:13px">'+esc(row.handwrittenName)+'</strong>'+defP+'</td>'+'<td style="min-width:150px">'+prodOpts+'</td>'+'<td style="min-width:80px"><input type="number" min="1" step="1" value="'+qty+'" id="aiSQty'+i+'"></td>'+'<td style="min-width:110px"><input type="number" min="0" value="'+price+'" id="aiSPrice'+i+'" placeholder="'+t('ai_default_word')+'"></td>'+'<td>'+(lineTotal?fmt(lineTotal):'—')+'</td>'+'<td>'+revBadge+'<div class="ai-conf-row">'+conf+'%</div>'+warn+'</td>'+'<td style="width:120px">'+action+'</td>'+'</tr>';}).join('');}
async function commitRow(i,fromBatch){if(aiProcessing)return;if((aiCommitBusy||aiBatchBusy)&&!fromBatch)return;var row=aiRows[i];if(!row||row.committed)return;var productId=row.matchedProductId;if(!productId){showBanner('error',t('err_row_select_product',{row:row.handwrittenName}));return;}var qty=parseInt(row.quantity,10);if(!Number.isInteger(qty)||qty<=0){showBanner('error',t('err_row_invalid_qty',{row:row.handwrittenName}));return;}var price=(row.unitPrice!==null&&row.unitPrice!==undefined)?Number(row.unitPrice):Number(row.defaultUnitPrice);if(!Number.isFinite(price)||price<=0){showBanner('error',t('err_row_no_price',{row:row.handwrittenName}));return;}var prod=products.find(function(p){return p.id===productId;});if(prod&&qty>prod.stock){showBanner('error',t('err_stock_insufficient',{product:prod.name,stock:prod.stock,qty:qty}));return;}aiCommitBusy=true;var btn=$('aiSCommit'+i);if(btn){btn.disabled=true;btn.innerHTML='...';}try{var r=await fetch(API_BASE_URL+'/api/sales/ai-commit',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+aiToken()},body:JSON.stringify({items:[{product_id:productId,quantity:qty,unit_price:price}],clientSaleKey:row.id,customer_name:(customerName||'').trim(),customer_phone:(customerPhone||'').trim(),sale_date:saleDate,notes:t('ai_import_notes',{name:row.handwrittenName})})});var data=await r.json().catch(function(){return{};});if(r.ok){row.committed=true;var td=btn?btn.closest('td'):null;if(td)td.innerHTML='<span class="ai-verified-tag">'+t('ai_saved_tag')+'</span>';updateSummary();await loadProducts();showBanner('info',t('msg_row_saved',{product:row.matchedProductName||row.handwrittenName,qty:qty,total:fmt(qty*price)}));}else{if(btn){btn.disabled=false;btn.innerHTML=t('ai_col_save');}var msg=data.error||t('err_save_failed');if(data.code==='STOCK_ISSUES'&&data.stockIssues&&data.stockIssues[0]){msg=t('err_stock_issue',{product:data.stockIssues[0].product_name||t('product_generic'),available:data.stockIssues[0].available});}if(data.code==='DUPLICATE'){msg=t('msg_already_saved');row.committed=true;var td2=btn?btn.closest('td'):null;if(td2)td2.innerHTML='<span class="ai-verified-tag">'+t('ai_saved_tag')+'</span>';updateSummary();}showBanner('error',msg);}}catch(e){if(btn){btn.disabled=false;btn.innerHTML=t('ai_col_save');}showBanner('error',t('err_network_retry'));}finally{aiCommitBusy=false;}}
async function commitAll(){if(aiCommitBusy||aiBatchBusy||aiProcessing)return;var pending=aiRows.filter(function(r){return !r.committed&&r.status==='MATCHED';});if(!pending.length){showBanner('info',t('ai_nothing_pending'));return;}var problems=[];for(var i=0;i<aiRows.length;i++){var row=aiRows[i];if(row.committed||row.status!=='MATCHED')continue;var productId=row.matchedProductId;var qty=parseInt(row.quantity,10);var price=(row.unitPrice!==null&&row.unitPrice!==undefined)?Number(row.unitPrice):Number(row.defaultUnitPrice);if(!productId){problems.push(t('ai_problem_no_product',{row:i+1}));continue;}if(!Number.isInteger(qty)||qty<=0){problems.push(t('ai_problem_bad_qty',{row:i+1}));continue;}if(!Number.isFinite(price)||price<=0){problems.push(t('ai_problem_no_price',{row:i+1}));continue;}var prod=products.find(function(p){return p.id===productId;});if(prod&&qty>prod.stock){problems.push(t('ai_problem_stock',{row:i+1,product:prod.name,stock:prod.stock}));}}if(problems.length){showBanner('error',t('ai_fix_before_save')+'\n'+problems.join('\n'));return;}var ok=await confirmBox(t('ai_confirm_save_all_title'),t('ai_confirm_save_all_msg',{count:pending.length}));if(!ok)return;aiBatchBusy=true;var st=$('aiSaleCommitAllStatus');var allBtn=$('aiSaleCommitAllBtn');var allBtnHtml=allBtn?allBtn.innerHTML:'';var saved=0,failed=0;try{for(var j=0;j<aiRows.length;j++){if(aiRows[j].committed||aiRows[j].status!=='MATCHED')continue;if(st)st.textContent=t('ai_saving_progress',{index:saved+failed+1,total:pending.length})+'...';if(allBtn){allBtn.disabled=true;allBtn.innerHTML=ic('refresh',14)+' '+t('ai_saving_progress',{index:saved+failed+1,total:pending.length});}var before=aiRows.filter(function(r){return r.committed;}).length;try{await commitRow(j,true);}catch(e){}var after=aiRows.filter(function(r){return r.committed;}).length;if(after>before)saved++;else failed++;}}finally{aiBatchBusy=false;if(allBtn){allBtn.disabled=false;allBtn.innerHTML=allBtnHtml;}}if(st)st.textContent='';if(failed===0){showBanner('info',t('msg_all_saved',{count:saved}));resetAiSaleSession();}else{showBanner('error',t('msg_some_failed',{saved:saved,failed:failed}));}}
function resetSession(){if(aiProcessing)return;aiRows=[];aiImages=[];$('aiSaleResultsWrap').style.display='none';hideBanner();refreshUploadUi();}
function openModal(){var m=$('aiSaleModal');if(m)m.classList.add('show');initUploadHandlers();refreshUploadUi();var uw=$('aiSaleResultsWrap');if(uw)uw.style.display=aiRows.length?'block':'none';hideBanner();}
function closeModal(){var m=$('aiSaleModal');if(m)m.classList.remove('show');stopProgress();}
window.refreshAiSaleUi=function(){refreshUploadUi();if(aiRows.length){renderResults();}};window.openAiSaleModal=openModal;window.closeAiSaleModal=closeModal;window.startAiSaleProcess=startProcess;window.captureAiSaleImage=captureImage;window.removeAiSaleImage=removeImage;window.commitAiSaleRow=commitRow;window.commitAllAiSaleRows=commitAll;window.resetAiSaleSession=resetSession;
})();
</script>
</body>
</html>
@endverbatim