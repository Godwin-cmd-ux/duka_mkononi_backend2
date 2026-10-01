@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="msimamizi_index.page_title">Dashbodi ya Msimamizi - Dukamkononi</title>
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

        /* Main layout wrapper - sidebar + content */
        .msimamizi-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR - Persistent navigation */
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

        /* Navigation items */
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

        .nav-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .nav-label {
            font-size: 15px;
            font-weight: 600;
        }

        /* Sidebar footer */
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

        .user-details {
            flex: 1;
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
            transition: all 0.2s;
            color: #e74c3c;
            font-weight: 600;
            font-size: 14px;
        }

        .logout-btn:hover {
            background-color: #fdeaea;
        }

        /* MAIN CONTENT AREA */
        .main-content {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            background-color: #f8f9fa;
            padding: 20px 24px 40px;
        }

        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 200;
            background: white;
            padding: 12px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        /* Dashboard specific styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-left {
            flex: 1;
        }

        .title {
            font-size: 24px;
            font-weight: 800;
            color: #2c3e50;
        }

        .subtitle {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .system-admin-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #f3e8ff;
            padding: 6px 12px;
            border-radius: 8px;
            margin-top: 8px;
            cursor: pointer;
            border: 1px solid #d8b4fe;
            font-size: 12px;
            font-weight: 600;
            color: #9b59b6;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .icon-btn {
            background: white;
            padding: 8px;
            border-radius: 40px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .logout-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }

        .logout-area span {
            font-size: 10px;
            color: #e74c3c;
            margin-top: 2px;
        }

        /* Business Card */
        .business-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .business-name {
            font-size: 18px;
            font-weight: 800;
            color: #2c3e50;
        }

        .business-location {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .business-phone {
            font-size: 13px;
            color: #95a5a6;
            margin-top: 4px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e8f6ef;
            padding: 6px 12px;
            border-radius: 20px;
            margin-top: 10px;
            font-size: 12px;
            color: #27ae60;
            font-weight: 600;
        }

        .filter-info {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #e3f2fd;
            padding: 8px 12px;
            border-radius: 10px;
            margin-top: 12px;
            font-size: 11px;
            color: #1976d2;
        }

        /* Sellers Section */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
        }

        .seller-count {
            background: #e74c3c;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .seller-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }

        .seller-info {
            flex: 1;
        }

        .seller-header {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }

        .seller-name {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .status-approved { background: #e8f6ef; color: #27ae60; }
        .status-pending { background: #fff4e6; color: #f39c12; }
        .status-rejected { background: #fdedec; color: #e74c3c; }
        .status-inactive { background: #f5f5f5; color: #95a5a6; }

        .seller-email, .seller-phone, .seller-business {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .seller-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 14px;
            border-radius: 10px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            color: white;
        }

        .btn-approve { background: #27ae60; }
        .btn-reject { background: #e74c3c; }
        .btn-delete { background: #95a5a6; }
        .btn-permanent { background: #c0392b; }

        .no-sellers {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 20px;
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
            border-radius: 24px;
            width: 90%;
            max-width: 450px;
            padding: 24px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .modal-title {
            font-size: 20px;
            font-weight: 700;
        }
        .input-group {
            margin-bottom: 16px;
        }
        .input-label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
        }
        .input-field {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
        }
        .helper-text {
            font-size: 10px;
            color: #f39c12;
            margin-top: 4px;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .modal-btn {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-cancel { background: #95a5a6; color: white; }
        .btn-save { background: #3498db; color: white; }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px 16px;
                padding-top: 70px;
            }
            .mobile-menu-toggle {
                display: block;
            }
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes btnSpin { to { transform: rotate(360deg); } }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
@endverbatim
@include('partials.photo-viewer')
@include('partials.cloudinary-config')
@verbatim
<body>
    <div class="mobile-menu-toggle" id="mobileMenuToggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2">
            <path d="M3 12H21M3 6H21M3 18H21"/>
        </svg>
    </div>

    <div class="msimamizi-layout">
        <!-- SIDEBAR - Persistent navigation with relative paths -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-area">
                    <div class="logo-icon" data-i18n="msimamizi_index.logo_short">D</div>
                    <div class="logo-text">
                        <h2 data-i18n="msimamizi_index.logo_brand">DukaMkononi</h2>
                        <p data-i18n="msimamizi_index.logo_tagline">Msimamizi Portal</p>
                    </div>
                </div>
            </div>
            <div class="nav-items">
                <a href="index" class="nav-item active">
                    <div class="nav-icon"><i class="fa-solid fa-house"></i></div>
                    <span class="nav-label" data-i18n="msimamizi_index.nav_home">Nyumbani</span>
                </a>
                <a href="ripoti" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-chart-simple"></i></div>
                    <span class="nav-label" data-i18n="msimamizi_index.nav_reports">Ripoti</span>
                </a>
                <a href="preview" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <span class="nav-label" data-i18n="msimamizi_index.nav_reviews">Rejea</span>
                </a>
                <a href="bidhaa-mpya" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-circle-plus"></i></div>
                    <span class="nav-label" data-i18n="msimamizi_index.nav_new_products">Bidhaa Mpya</span>
                </a>
                <a href="tangaza" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-bullhorn"></i></div>
                    <span class="nav-label" data-i18n="msimamizi_index.nav_advertise">Tangaza</span>
                </a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar">M</div>
                    <div class="user-details">
                        <div class="user-name" id="userName" data-i18n="msimamizi_index.nav_admin">Msimamizi</div>
                        <div class="user-role">Msimamizi</div>
                    </div>
                </div>
                <div class="logout-btn" id="logoutBtn">
                    <span><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
                    <span data-i18n="msimamizi_index.btn_logout">Ondoka</span>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content" id="mainContent">
            <div id="dashboardContainer">
                <div style="text-align: center; padding: 60px;" data-i18n="msimamizi_index.loading_dashboard">Loading dashboard...</div>
            </div>
        </main>
    </div>

    <script>
        // ============================================
        // MSIMAMIZI HOME SCREEN - Full Web Replica
        // Includes sidebar + dashboard functionality
        // ============================================
        
        const API_BASE_URL = '';
        // ---- i18n ----
        // Swahili source strings, used until the shared runtime has fetched the
        // catalog. t() prefers window.DM (all 8 locales) and falls back to these.
        const SW = {
            btn_ok: "Sawa",
            btn_cancel: "Ghairi",
            btn_confirm: "Thibitisha",
            alert_sign_in_again: "Tafadhali ingia tena",
            title_main_business: "Biashara Kuu",
            title_headquarters: "Makao Makuu",
            no_phone: "Hakuna namba",
            photo: "Picha",
            msg_seller_status: "Muuzaji {action_name} kikamilifu",
            err_change_seller_status: "Imeshindikana kubadilisha hali ya muuzaji",
            btn_delete_permanent: "FUTA KABISA",
            confirm_delete_seller: "UNATAKA KUMFUTA KABISA MUUZAJI {seller_name}?\n\nKITENDO HIKI HAKIWEZI KUTENDULIWA!",
            msg_seller_deleted: "Muuzaji {seller_name} amefutwa kabisa",
            err_delete_seller: "Imeshindikana kumfuta muuzaji kabisa",
            err_fill_business_location: "Tafadhali jaza jina la biashara na eneo",
            msg_profile_updated: "Wasifu umesasishwa",
            err_update_profile: "Imeshindikana kusasisha wasifu",
            type_auto_parts: "Sehemu za Gari",
            type_motorcycle_spare: "Spea za Pikipiki",
            type_supermarket: "Supermarket",
            type_pharmacy: "Duka la Dawa",
            type_electronics: "Elektroniki",
            type_clothing: "Mavazi",
            type_hardware: "Vifaa Vinene (Hardware)",
            type_cosmetics: "Vipodozi",
            type_perfumes: "Manukato",
            type_restaurant: "Mgahawa",
            type_furniture: "Samani",
            type_office_school: "Vifaa vya Ofisi na Shule",
            type_phone_accessories: "Vifaa vya Simu",
            type_computer_shop: "Duka la Kompyuta",
            type_mobile_shop: "Duka la Simu",
            type_agriculture: "Kilimo",
            type_building_materials: "Vifaa vya Ujenzi",
            type_beauty_salon: "Saluni ya Urembo",
            type_barbershop: "Kinyozi",
            type_car_workshop: "Karakana ya Magari",
            type_retail_general: "Rejareja ya Jumla",
            type_wholesale: "Jumla (Wholesale)",
            type_other: "Nyingine",
            opt_select_business_type: "— Chagua Aina ya Biashara —",
            saving: "Inahifadhi...",
            err_refresh_data: "Imeshindikana kusasisha data. Hakikisha umeunganishwa kwenye internet kisha ujaribu tena.",
            logout_title: "Toka",
            confirm_logout: "Unahakika unataka kutoka kwenye akaunti yako?",
            photo_only: "Chagua picha tu",
            photo_too_large: "Picha ni kubwa sana (max 5MB)",
            msg_business_photo_updated: "Picha ya biashara imesasishwa!",
            err_upload_photo: "Imeshindwa kupakia picha: {message}",
            gps_unavailable: "GPS haipatikani kwenye kifaa chako",
            confirm_location: "Eneo lako: {area_name}\n\nUhakikiwa?",
            msg_location_saved: "Eneo limehifadhiwa! {area_name}",
            err_get_location: "Imeshindwa kupata eneo: {message}",
            verified: "Imethibitishwa",
            pending: "Inasubiri",
            rejected: "Haijakubaliwa",
            unknown: "Unknown",
            no_business: "Hakuna biashara",
            no_location: "Hakuna eneo",
            btn_verify: "✓ Thibitisha",
            btn_remove: "Ondoa",
            btn_reject: "✗ Batilisha",
            btn_restore: "⟳ Rudisha",
            btn_delete_forever: "Futa Kabisa",
            dashboard_title: "Dashbodi ya Msimamizi",
            system_management: "Simamia Mfumo",
            system_admin: "Msimamizi Mkuu wa Mfumo",
            super_admin: "Msimamizi Mkuu",
            sellers_list_caption: "Wauzaji wanaonyeshwa: Jina la biashara = {business_name}",
            getting_location: "Inapata eneo...",
            btn_change_location: "Badili Eneo",
            btn_get_gps_location: "Pata Eneo la GPS",
            sellers_of_business: "Wauzaji Wa Biashara Hii",
            no_sellers: "Hakuna Wauzaji",
            no_sellers_registered: "Hakuna wauzaji waliosajiliwa kwenye biashara {business_name} bado.",
            status_verified: "amethibitishwa",
            status_unverified: "amebatilishwa",
            remove_seller: "Ondoa Muuzaji",
            confirm_remove_seller: "Unahakika unataka kumfuta muuzaji {name}?",
            status_deleted: "amefutwa"
        };
        function t(key, params) {
            const full = key.indexOf('msimamizi_index.') === 0 ? key : 'msimamizi_index.' + key;
            if (window.DM && typeof window.DM.t === 'function') {
                const hit = window.DM.t(full, params);
                if (hit !== full) return hit;
            }
            let value = SW[key.replace('msimamizi_index.', '')];
            if (value === undefined) return key;
            if (params) {
                Object.keys(params).forEach(p => {
                    value = value.split('{' + p + '}').join(params[p] == null ? '' : params[p]);
                });
            }
            return value;
        }


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
            bell: 'fa-bell', warning: 'fa-triangle-exclamation', print: 'fa-print', filter: 'fa-filter'
        };
        function ic(name, size = 16, cls = '') {
            return `<i class="fa-solid ${FA_MAP[name] || 'fa-circle-info'} ${cls}" style="font-size:${size}px;" aria-hidden="true"></i>`;
        }
        
        let userData = {
            id: '', email: '', businessName: '', businessLocation: '', phone: '', role: ''
        };
        let isSystemAdmin = false;
        let sellersData = [];
        let loading = true;
        let updatingSellerStatus = null;
        let deletingSellerId = null;
        let updatingProfile = false;
        let refreshing = false;
        
        let editFormData = { name: '', phone: '', businessName: '', businessLocation: '', businessType: '', businessDescription: '' };
        let logoUploading = false;
        let locationTracking = false;
        // Real values come from the Blade-processed partial (this file's
        // script is inside @verbatim, so {{ }} here would never interpolate).
        const CLOUDINARY_CONFIG = (window.CLOUDINARY_CONFIG || { cloudName: '', uploadPreset: 'react_native_uploads' });

        function showAlert(title, message, onOk = null) {
            const existing = document.querySelector('.custom-alert');
            if (existing) existing.remove();
            const overlay = document.createElement('div');
            overlay.className = 'custom-alert';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:2000;';
            const box = document.createElement('div');
            box.style.cssText = 'background:white;border-radius:28px;width:85%;max-width:320px;padding:24px 20px;text-align:center;';
            box.innerHTML = `
                <div style="font-size:20px;font-weight:800;margin-bottom:12px;">${escapeHtml(title)}</div>
                <div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;">${escapeHtml(message)}</div>
                <div style="background:#e74c3c;padding:12px;border-radius:40px;color:white;font-weight:700;cursor:pointer;">${t('msimamizi_index.btn_ok')}</div>
            `;
            const btn = box.querySelector('div:last-child');
            btn.onclick = () => { overlay.remove(); if(onOk) onOk(); };
            overlay.appendChild(box);
            document.body.appendChild(overlay);
        }

        function showConfirm(title, message, onConfirm) {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:2000;';
            const box = document.createElement('div');
            box.style.cssText = 'background:white;border-radius:28px;width:85%;max-width:320px;padding:24px 20px;text-align:center;';
            box.innerHTML = `
                <div style="font-size:18px;font-weight:800;margin-bottom:12px;">${escapeHtml(title)}</div>
                <div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;">${escapeHtml(message)}</div>
                <div style="display:flex;gap:12px;">
                    <div id="confirmNo" style="flex:1;background:#95a5a6;padding:12px;border-radius:40px;color:white;cursor:pointer;">${t('msimamizi_index.btn_cancel')}</div>
                    <div id="confirmYes" style="flex:1;background:#e74c3c;padding:12px;border-radius:40px;color:white;cursor:pointer;">${t('msimamizi_index.btn_confirm')}</div>
                </div>
            `;
            overlay.appendChild(box);
            document.body.appendChild(overlay);
            document.getElementById('confirmYes').onclick = () => { overlay.remove(); onConfirm(); };
            document.getElementById('confirmNo').onclick = () => overlay.remove();
        }

        async function loadUserData() {
            const token = localStorage.getItem('userToken');
            const userStr = localStorage.getItem('userData');
            if (!token || !userStr) {
                showAlert('Hitilafu', t('msimamizi_index.alert_sign_in_again'), () => { window.location.href = '../login?role=msimamizi'; });
                return false;
            }
            const user = JSON.parse(userStr);
            const businessName = user.businessName || user.business_name || t('msimamizi_index.title_main_business');
            const businessLocation = user.businessLocation || user.business_location || t('msimamizi_index.title_headquarters');
            
            userData = {
                id: user.id || '',
                email: user.email || '',
                businessName: businessName,
                businessLocation: businessLocation,
                phone: user.phone || t('msimamizi_index.no_phone'),
                role: user.role || '',
                businessLogo: user.business_logo_url || '',
                businessLatitude: typeof user.business_latitude === 'number' ? user.business_latitude : null,
                businessLongitude: typeof user.business_longitude === 'number' ? user.business_longitude : null
            };
            
            isSystemAdmin = user.email === "cosmavictorini1994@gmail.com";
            
            editFormData = {
                name: user.full_name || 'Msimamizi',
                phone: user.phone || '',
                businessName: businessName,
                businessLocation: businessLocation,
                businessType: user.business_type || '',
                businessDescription: user.business_description || ''
            };
            
            document.getElementById('userName').innerHTML = escapeHtml(user.full_name || user.email.split('@')[0] || 'Msimamizi');
            const avatarEl = document.getElementById('userAvatar');
            const displayName = user.full_name || user.business_name || user.email?.split('@')[0] || 'Msimamizi';
            if (user.business_logo_url) {
                avatarEl.classList.add('js-avatar-view');
avatarEl.setAttribute('data-full', user.business_logo_url);
avatarEl.setAttribute('data-name', displayName);
avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="${t('msimamizi_index.photo')}">`;
            } else {
                avatarEl.innerHTML = (user.full_name || user.email.charAt(0) || 'M').charAt(0).toUpperCase();
            }
            
            return true;
        }

        async function loadSellersData() {
            const token = localStorage.getItem('userToken');
            if (!token) return false;
            
            try {
                // Business scoping comes from the verified JWT (business_id) on
                // the server. This used to send ?business=<name> and re-filter on
                // user.business_name, but that comparison was byte-exact, so
                // sellers whose spelling differed from their admin's were dropped
                // (e.g. "Shirima Spare Part" vs "Shirima spare part"). Do not
                // re-add a name filter: AdminController::users() already returns
                // only this admin's business.
                const response = await fetch(`${API_BASE_URL}/api/admin/users?role=seller`, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                });
                
                if (response.ok) {
                    const data = await response.json();
                    const usersArray = data.users || [];
                    
                    const sellers = usersArray.filter(user => 
                        user.role === 'seller' && 
                        user.status !== 'deleted'
                    );
                    
                    sellersData = sellers;
                    return true;
                } else {
                    console.error('Failed to load sellers: HTTP ' + response.status);
                    return false;
                }
            } catch (error) {
                console.error('Error loading sellers:', error);
                return false;
            }
        }

        async function updateSellerStatus(sellerId, status, actionName) {
            const token = localStorage.getItem('userToken');
            if (!token) return false;
            
            updatingSellerStatus = sellerId;
            renderDashboard();
            
            try {
                const response = await fetch(`${API_BASE_URL}/api/admin/users/${sellerId}/status`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: status }),
                });
                
                if (response.ok) {
                    showAlert('Mafanikio', t('msimamizi_index.msg_seller_status', {action_name: actionName}));
                    await loadSellersData();
                    renderDashboard();
                    return true;
                } else {
                    throw new Error('Failed to update status');
                }
            } catch (error) {
                showAlert('Hitilafu', t('msimamizi_index.err_change_seller_status'));
                return false;
            } finally {
                updatingSellerStatus = null;
                renderDashboard();
            }
        }

        async function deleteSellerPermanently(sellerId, sellerName) {
            showConfirm(t('msimamizi_index.btn_delete_permanent'), t('msimamizi_index.confirm_delete_seller', {seller_name: sellerName}), async () => {
                const token = localStorage.getItem('userToken');
                if (!token) return;
                
                deletingSellerId = sellerId;
                renderDashboard();
                
                try {
                    const response = await fetch(`${API_BASE_URL}/api/admin/users/${sellerId}`, {
                        method: 'DELETE',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                    });
                    
                    if (response.ok) {
                        showAlert('Mafanikio', t('msimamizi_index.msg_seller_deleted', {seller_name: sellerName}));
                        await loadSellersData();
                        renderDashboard();
                    } else {
                        throw new Error('Delete failed');
                    }
                } catch (error) {
                    showAlert('Hitilafu', t('msimamizi_index.err_delete_seller'));
                } finally {
                    deletingSellerId = null;
                    renderDashboard();
                }
            });
        }

        async function updateProfile() {
            if (updatingProfile) return;
            if (!editFormData.businessName.trim() || !editFormData.businessLocation.trim()) {
                showAlert('Hitilafu', t('msimamizi_index.err_fill_business_location'));
                return;
            }
            
            // Trim optional business-info fields used by the AI import feature.
            editFormData.businessType = (editFormData.businessType || '').trim();
            editFormData.businessDescription = (editFormData.businessDescription || '').trim();
            
            updatingProfile = true;
            setSaveBtnState(true);
            const token = localStorage.getItem('userToken');
            
            try {
                const response = await fetch(`${API_BASE_URL}/api/user/profile`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        full_name: editFormData.name,
                        phone: editFormData.phone,
                        business_name: editFormData.businessName,
                        business_location: editFormData.businessLocation,
                        business_type: editFormData.businessType,
                        business_description: editFormData.businessDescription
                    }),
                });
                
                if (response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    const saved = payload.user || {};
                    // Prefer the server's stored values so a rename rejected or
                    // normalised server-side cannot leave the cache lying.
                    const savedBusinessName = saved.business_name || editFormData.businessName;
                    const savedBusinessLocation = saved.business_location || editFormData.businessLocation;

                    const currentUser = JSON.parse(localStorage.getItem('userData') || '{}');
                    const updatedUser = {
                        ...currentUser,
                        business_name: savedBusinessName,
                        businessName: savedBusinessName,
                        business_location: savedBusinessLocation,
                        businessLocation: savedBusinessLocation,
                        phone: editFormData.phone,
                        full_name: editFormData.name,
                        business_type: editFormData.businessType,
                        business_description: editFormData.businessDescription
                    };
                    localStorage.setItem('userData', JSON.stringify(updatedUser));
                    
                    userData.businessName = savedBusinessName;
                    userData.businessLocation = savedBusinessLocation;
                    userData.phone = editFormData.phone;
                    editFormData.businessName = savedBusinessName;
                    editFormData.businessLocation = savedBusinessLocation;
                    
                    document.getElementById('userName').innerHTML = escapeHtml(editFormData.name);
                    document.getElementById('userAvatar').innerHTML = editFormData.name.charAt(0).toUpperCase();
                    
                    closeEditModal();
                    showAlert('Mafanikio', t('msimamizi_index.msg_profile_updated'));
                    await loadSellersData();
                    renderDashboard();
                } else {
                    // Was a blanket 'Imeshindikana kusasisha wasifu', which hid
                    // the reason (e.g. a business name already taken).
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.error || t('msimamizi_index.err_update_profile'));
                }
            } catch (error) {
                showAlert('Hitilafu', error.message || t('msimamizi_index.err_update_profile'));
            } finally {
                updatingProfile = false;
                setSaveBtnState(false);
            }
        }

        // Same option list as the AI-import modal (bidhaa-mpya) so the two
        // stay consistent; value '' = Nyingine/haijabainishwa.
        const BUSINESS_TYPES = [
            ['spare_parts',t('msimamizi_index.type_auto_parts')],['motorcycle_spares',t('msimamizi_index.type_motorcycle_spare')],
            ['supermarket',t('msimamizi_index.type_supermarket')],['pharmacy',t('msimamizi_index.type_pharmacy')],
            ['electronics',t('msimamizi_index.type_electronics')],['clothing',t('msimamizi_index.type_clothing')],['hardware',t('msimamizi_index.type_hardware')],
            ['cosmetics',t('msimamizi_index.type_cosmetics')],['perfume',t('msimamizi_index.type_perfumes')],['restaurant',t('msimamizi_index.type_restaurant')],['furniture',t('msimamizi_index.type_furniture')],
            ['stationery',t('msimamizi_index.type_office_school')],['mobile_accessories',t('msimamizi_index.type_phone_accessories')],['computer_shop',t('msimamizi_index.type_computer_shop')],
            ['phone_shop',t('msimamizi_index.type_mobile_shop')],['agriculture',t('msimamizi_index.type_agriculture')],['construction_materials',t('msimamizi_index.type_building_materials')],
            ['beauty_salon',t('msimamizi_index.type_beauty_salon')],['barbershop',t('msimamizi_index.type_barbershop')],['auto_repair',t('msimamizi_index.type_car_workshop')],
            ['general_retail',t('msimamizi_index.type_retail_general')],['wholesale',t('msimamizi_index.type_wholesale')],['other',t('msimamizi_index.type_other')]
        ];

        function populateBusinessTypeOptions(selectEl) {
            if (!selectEl) return;
            selectEl.innerHTML = '<option value="">' + t('msimamizi_index.opt_select_business_type') + '</option>' +
                BUSINESS_TYPES.map(t => `<option value="${escapeHtml(t[0])}">${escapeHtml(t[1])}</option>`).join('');
        }

        function openEditModal() {
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('editName').value = editFormData.name;
            document.getElementById('editPhone').value = editFormData.phone;
            document.getElementById('editBusinessName').value = editFormData.businessName;
            document.getElementById('editBusinessLocation').value = editFormData.businessLocation;
            const typeSelect = document.getElementById('editBusinessType');
            if (typeSelect) {
                populateBusinessTypeOptions(typeSelect);
                typeSelect.value = editFormData.businessType || '';
            }
            const descEl = document.getElementById('editBusinessDescription');
            if (descEl) descEl.value = editFormData.businessDescription || '';
            setSaveBtnState(false);
        }

        // The save button used to be a static div, so `updatingProfile` was
        // never read: clicking gave no feedback and a double click fired the
        // same PUT twice.
        function setSaveBtnState(saving) {
            const btn = document.getElementById('editSaveBtn');
            if (!btn) return;
            btn.style.opacity = saving ? '0.75' : '1';
            btn.style.cursor = saving ? 'wait' : 'pointer';
            btn.innerHTML = saving
                ? '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.45);border-top-color:#fff;border-radius:50%;animation:btnSpin 0.7s linear infinite;vertical-align:-2px;margin-right:7px;"></span>' + t('msimamizi_index.saving') + '
                : 'Hifadhi';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function saveEditModal() {
            editFormData.name = document.getElementById('editName').value;
            editFormData.phone = document.getElementById('editPhone').value;
            editFormData.businessName = document.getElementById('editBusinessName').value;
            editFormData.businessLocation = document.getElementById('editBusinessLocation').value;
            const typeSelect = document.getElementById('editBusinessType');
            if (typeSelect) editFormData.businessType = typeSelect.value;
            const descEl = document.getElementById('editBusinessDescription');
            if (descEl) editFormData.businessDescription = descEl.value;
            updateProfile();
        }

        async function refreshData() {
            if (refreshing) return;
            refreshing = true;
            renderDashboard();
            try {
                const ok = await loadSellersData();
                if (!ok) {
                    showAlert('Hitilafu', t('msimamizi_index.err_refresh_data'));
                }
            } finally {
                refreshing = false;
                renderDashboard();
            }
        }

        function handleLogout() {
            showConfirm(t('msimamizi_index.logout_title'), t('msimamizi_index.confirm_logout'), () => {
                localStorage.clear();
                window.location.href = '../login?role=msimamizi';
            });
        }

        // Logo Upload via Cloudinary
        async function handleLogoUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) { showAlert('Hitilafu', t('msimamizi_index.photo_only')); return; }
            if (file.size > 5*1024*1024) { showAlert('Hitilafu', t('msimamizi_index.photo_too_large')); return; }

            logoUploading = true;
            renderDashboard();
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('upload_preset', CLOUDINARY_CONFIG.uploadPreset);
                const uploadUrl = 'https://api.cloudinary.com/v1_1/' + CLOUDINARY_CONFIG.cloudName + '/image/upload';
                const res = await fetch(uploadUrl, { method: 'POST', body: formData });
                if (!res.ok) throw new Error('Upload failed');
                const data = await res.json();
                const logoUrl = data.secure_url;

                const token = localStorage.getItem('userToken');
                const profileRes = await fetch(API_BASE_URL + '/api/user/profile', {
                    method: 'PUT',
                    headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ business_logo_url: logoUrl })
                });
                if (!profileRes.ok) throw new Error('Profile update failed');
                const updated = await profileRes.json();
                const newLogo = updated.user?.business_logo_url || logoUrl;

                userData.businessLogo = newLogo;
                const cached = JSON.parse(localStorage.getItem('userData') || '{}');
                cached.business_logo_url = newLogo;
                localStorage.setItem('userData', JSON.stringify(cached));

                document.getElementById('userName').innerHTML = escapeHtml(editFormData.name || userData.email);
                const av = document.getElementById('userAvatar');
                av.innerHTML = '<img src="' + escapeHtml(newLogo) + '" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">';

                showAlert('Mafanikio', t('msimamizi_index.msg_business_photo_updated'));
                renderDashboard();
            } catch(e) { showAlert('Hitilafu', t('msimamizi_index.err_upload_photo', {message: e.message})); }
            finally { logoUploading = false; }
        }

        // GPS Location Tracking
        async function handleTrackLocation() {
            if (!navigator.geolocation) { showAlert('Hitilafu', t('msimamizi_index.gps_unavailable')); return; }
            locationTracking = true;
            renderDashboard();
            try {
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 15000 });
                });
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                let areaName = '';
                try {
                    const geoRes = await fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng);
                    if (geoRes.ok) { const geo = await geoRes.json(); areaName = geo.display_name || ''; }
                } catch(e) {}

                if (!confirm(t('msimamizi_index.confirm_location', {area_name: areaName || lat.toFixed(5) + ', ' + lng.toFixed(5)}))) {
                    locationTracking = false;
                    renderDashboard();
                    return;
                }

                const token = localStorage.getItem('userToken');
                const res = await fetch(API_BASE_URL + '/api/user/profile', {
                    method: 'PUT',
                    headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ business_latitude: lat, business_longitude: lng, ...(areaName ? { business_location: areaName } : {}) })
                });
                if (!res.ok) throw new Error('Save failed');
                const updated = await res.json();

                userData.businessLatitude = updated.user?.business_latitude ?? lat;
                userData.businessLongitude = updated.user?.business_longitude ?? lng;
                if (areaName) userData.businessLocation = areaName;

                const cached = JSON.parse(localStorage.getItem('userData') || '{}');
                cached.business_latitude = userData.businessLatitude;
                cached.business_longitude = userData.businessLongitude;
                if (areaName) cached.business_location = areaName;
                localStorage.setItem('userData', JSON.stringify(cached));

                showAlert('Mafanikio', t('msimamizi_index.msg_location_saved', {area_name: areaName || lat.toFixed(5) + ', ' + lng.toFixed(5)}));
                renderDashboard();
            } catch(e) { showAlert('Hitilafu', t('msimamizi_index.err_get_location', {message: e.message || e})); }
            finally { locationTracking = false; renderDashboard(); }
        }

        function goToSystemAdmin() {
            window.location.href = '../system_admin/index';
        }

        function escapeHtml(str) { 
            if(!str) return ''; 
            return str.replace(/[&<>]/g, function(m){ 
                return {'&':'&amp;','<':'&lt;','>':'&gt;'}[m]; 
            }); 
        }

        function renderDashboard() {
            const container = document.getElementById('dashboardContainer');
            
            const allSellersHtml = sellersData.map(seller => {
                let statusClass = '', statusText = '', statusIcon = '';
                switch(seller.status) {
                    case 'approved': statusClass = 'status-approved'; statusText = t('msimamizi_index.verified'); statusIcon = ic('check', 11); break;
                    case 'pending': statusClass = 'status-pending'; statusText = t('msimamizi_index.pending'); statusIcon = ic('clock', 11); break;
                    case 'rejected': statusClass = 'status-rejected'; statusText = t('msimamizi_index.rejected'); statusIcon = ic('x', 11); break;
                    default: statusClass = 'status-inactive'; statusText = seller.status || t('msimamizi_index.unknown'); statusIcon = ic('x', 11);
                }
                
                const isUpdating = updatingSellerStatus === seller.id;
                const isDeleting = deletingSellerId === seller.id;
                
                return `
                    <div class="seller-card">
                        <div class="seller-info">
                            <div class="seller-header">
                                <div style="display:flex;align-items:center;gap:10px;flex:1;">
                                    ${seller.business_logo_url ? '<div class="js-avatar-view" data-full="'+escapeHtml(seller.business_logo_url)+'" data-name="'+escapeHtml(seller.full_name || seller.email || '')+'" style="width:32px;height:32px;border-radius:50%;overflow:hidden;"><img src="'+escapeHtml(seller.business_logo_url)+'" style="width:32px;height:32px;border-radius:50%;object-fit:cover;pointer-events:none;"></div>' : '<div style="width:32px;height:32px;border-radius:50%;background:#eef4fa;color:#7a8ba0;display:flex;align-items:center;justify-content:center;">'+ic('user', 16)+'</div>'}
                                    <span class="seller-name">${escapeHtml(seller.full_name || seller.email)}</span>
                                </div>
                                <span class="status-chip ${statusClass}" style="display:inline-flex;align-items:center;gap:4px;">${statusIcon} ${statusText}</span>
                            </div>
                            <div class="seller-email">${ic('mail', 12)} ${escapeHtml(seller.email)}</div>
                            <div class="seller-phone">${ic('phone', 12)} ${escapeHtml(seller.phone || t('msimamizi_index.no_phone'))}</div>
                            <div class="seller-business">${ic('building', 12)} ${escapeHtml(seller.business_name || t('msimamizi_index.no_business'))} - ${escapeHtml(seller.business_location || t('msimamizi_index.no_location'))}</div>
                        </div>
                        <div class="seller-actions">
                            ${seller.status === 'pending' ? `
                                <button class="action-btn btn-approve" onclick="window.approveSeller('${seller.id}')" ${isUpdating ? 'disabled' : ''}>
                                    ${isUpdating ? '<div class="loading-spinner"></div>' : t('msimamizi_index.btn_verify')}
                                </button>
                                <button class="action-btn btn-delete" onclick="window.deleteSeller('${seller.id}', '${escapeHtml(seller.full_name || seller.email)}')" ${isUpdating ? 'disabled' : ''}>
                                    ${ic('trash', 13)} ${t('msimamizi_index.btn_remove')}
                                </button>
                            ` : seller.status === 'approved' ? `
                                <button class="action-btn btn-reject" onclick="window.rejectSeller('${seller.id}')" ${isUpdating ? 'disabled' : ''}>
                                    ${isUpdating ? '<div class="loading-spinner"></div>' : t('msimamizi_index.btn_reject')}
                                </button>
                            ` : seller.status === 'rejected' ? `
                                <button class="action-btn btn-approve" onclick="window.approveSeller('${seller.id}')" ${isUpdating ? 'disabled' : ''}>
                                    ${isUpdating ? '<div class="loading-spinner"></div>' : t('msimamizi_index.btn_restore')}
                                </button>
                                <button class="action-btn btn-permanent" onclick="window.deletePermanent('${seller.id}', '${escapeHtml(seller.full_name || seller.email)}')" ${isDeleting ? 'disabled' : ''}>
                                    ${isDeleting ? '<div class="loading-spinner"></div>' : ic('trash', 13) + ' ' + t('msimamizi_index.btn_delete_forever')}
                                </button>
                            ` : seller.status === 'inactive' ? `
                                <button class="action-btn btn-permanent" onclick="window.deletePermanent('${seller.id}', '${escapeHtml(seller.full_name || seller.email)}')" ${isDeleting ? 'disabled' : ''}>
                                    ${isDeleting ? '<div class="loading-spinner"></div>' : ic('trash', 13) + ' ' + t('msimamizi_index.btn_delete_forever')}
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = `
                <div class="header">
                    <div class="header-left">
                        <h1 class="title">${t('msimamizi_index.dashboard_title')}</h1>
                        <div class="subtitle">${escapeHtml(userData.email)}</div>
                        ${isSystemAdmin ? `<div class="system-admin-btn" id="systemAdminBtn">${ic('settings', 12)} ${t('msimamizi_index.system_management')}</div>` : ''}
                    </div>
                    <div class="header-right">
                        <div class="icon-btn" id="refreshBtn">${refreshing ? '<div class="loading-spinner"></div>' : ic('refresh', 16)}</div>
                        <div class="icon-btn" id="editProfileBtn">${ic('edit', 16)}</div>
                        <div class="logout-area" id="logoutArea">
                            <div>${ic('user', 18)}</div>
                            <span>${t('msimamizi_index.logout_title')}</span>
                        </div>
                    </div>
                </div>                    <div class="business-card">
                        <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px;">
                            <div id="businessLogoArea" style="width:70px;height:70px;border-radius:14px;background:#e8f4fd;display:flex;align-items:center;justify-content:center;border:2px dashed #3498db;cursor:pointer;overflow:hidden;" onclick="document.getElementById('logoFileInput').click()">
                                ${userData.businessLogo ? '<img src="'+escapeHtml(userData.businessLogo)+'" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">' : '<div style="color:#7a8ba0;display:flex;align-items:center;justify-content:center;">'+ic('camera', 26)+'</div>'}
                            </div>
                            <div style="flex:1;">
                                <div class="business-name">${escapeHtml(userData.businessName)}</div>
                                <div class="business-location">${ic('location', 12)} ${escapeHtml(userData.businessLocation)}</div>
                                <div class="business-phone">${ic('phone', 12)} ${escapeHtml(userData.phone)}</div>
                            </div>
                        </div>
                        <input type="file" id="logoFileInput" accept="image/*" style="display:none" onchange="handleLogoUpload(event)">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                            <div class="status-badge">${ic('shield', 12)} ${isSystemAdmin ? t('msimamizi_index.system_admin') : t('msimamizi_index.super_admin')}</div>
                        </div>
                        <div class="filter-info">${ic('search', 12)} ${t('msimamizi_index.sellers_list_caption', {business_name: escapeHtml(userData.businessName)})}</div>
                        <button id="trackLocationBtn" style="width:100%;margin-top:12px;padding:12px;background:#e67e22;color:white;border:none;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;" onclick="handleTrackLocation()">
                            ${locationTracking ? t('msimamizi_index.getting_location') : (userData.businessLatitude ? ic('refresh', 14) + ' ' + t('msimamizi_index.btn_change_location') : ic('location', 14) + ' ' + t('msimamizi_index.btn_get_gps_location'))}
                        </button>
                    </div>
                
                <div class="section-header">
                    <h2 class="section-title">${t('msimamizi_index.sellers_of_business')}</h2>
                    <div class="seller-count">${sellersData.length}</div>
                </div>
                
                ${sellersData.length > 0 ? allSellersHtml : `
                    <div class="no-sellers">
                        <div style="color:#7a8ba0;margin-bottom:12px;">${ic('users', 44)}</div>
                        <div style="font-weight:700; margin-bottom:8px;">${t('msimamizi_index.no_sellers')}</div>
                        <div style="font-size:13px; color:#95a5a6;">${t('msimamizi_index.no_sellers_registered', {business_name: escapeHtml(userData.businessName)})}</div>
                    </div>
                `}
            `;
            
            if (isSystemAdmin) document.getElementById('systemAdminBtn')?.addEventListener('click', goToSystemAdmin);
            document.getElementById('refreshBtn')?.addEventListener('click', () => refreshData());
            document.getElementById('editProfileBtn')?.addEventListener('click', () => openEditModal());
            document.getElementById('logoutArea')?.addEventListener('click', handleLogout);
        }
        
        window.approveSeller = (id) => updateSellerStatus(id, 'approved', t('msimamizi_index.status_verified'));
        window.rejectSeller = (id) => updateSellerStatus(id, 'rejected', t('msimamizi_index.status_unverified'));
        window.deleteSeller = (id, name) => {
            showConfirm(t('msimamizi_index.remove_seller'), t('msimamizi_index.confirm_remove_seller', {name: name}), () => {
                updateSellerStatus(id, 'inactive', t('msimamizi_index.status_deleted'));
            });
        };
        window.deletePermanent = (id, name) => deleteSellerPermanently(id, name);
        
        function setupSidebar() {
            const toggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            if (toggle && sidebar) {
                toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
            }
            // The persistent sidebar logout button had no listener
            // attached anywhere — clicking it did nothing.
            document.getElementById('logoutBtn')?.addEventListener('click', handleLogout);
        }
        
        // Pull the authoritative profile (includes business_type and
        // business_description saved from the AI-import page or this modal)
        // so the edit form always prefills the stored values.
        async function loadProfileIntoEditForm() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/user/profile`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const profile = await res.json();
                if (!profile) return;
                editFormData.businessType = profile.business_type || '';
                editFormData.businessDescription = profile.business_description || '';
                // The stored business_name is the canonical one from
                // `businesses`. localStorage still holds the legacy spelling
                // from login, so adopting it here stops the modal from echoing
                // a stale name back on every save.
                if (profile.business_name) {
                    editFormData.businessName = profile.business_name;
                }
                if (profile.business_location) {
                    editFormData.businessLocation = profile.business_location;
                }
                // Keep the localStorage cache in sync for other pages.
                try {
                    const cached = JSON.parse(localStorage.getItem('userData') || '{}');
                    cached.business_type = editFormData.businessType;
                    cached.business_description = editFormData.businessDescription;
                    if (editFormData.businessName) {
                        cached.businessName = editFormData.businessName;
                        cached.business_name = editFormData.businessName;
                    }
                    if (editFormData.businessLocation) {
                        cached.businessLocation = editFormData.businessLocation;
                        cached.business_location = editFormData.businessLocation;
                    }
                    localStorage.setItem('userData', JSON.stringify(cached));
                } catch (e) {}
            } catch (e) { /* offline: modal falls back to cached values */ }
        }

        async function init() {
            setupSidebar();
            const success = await loadUserData();
            if (!success) return;
            // The profile refresh only feeds the edit modal (which already
            // falls back to the localStorage cache), so it must NOT delay
            // the dashboard's first paint — run it in the background.
            const sellersPromise = loadSellersData();
            loadProfileIntoEditForm();
            await sellersPromise;
            loading = false;
            renderDashboard();
        }
        
        // renderDashboard() rebuilds every dynamic string on this page (seller
        // cards, header, badges, GPS button), so one call covers a locale switch.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => { if (!loading) renderDashboard(); });
        }

        init();
    </script>

    <!-- Edit Profile Modal -->
    <div id="editModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" data-i18n="msimamizi_index.edit_profile">Hariri Wasifu</h3>
                <span style="cursor:pointer; font-size:24px;" onclick="closeEditModal()">✖</span>
            </div>
            <div class="input-group">
                <label class="input-label" data-i18n="msimamizi_index.label_full_name">Jina Kamili</label>
                <input type="text" id="editName" class="input-field" placeholder="Jina lako kamili" data-i18n="msimamizi_index.placeholder_full_name" data-i18n-attr="placeholder">
            </div>
            <div class="input-group">
                <label class="input-label" data-i18n="msimamizi_index.label_phone">Namba ya Simu</label>
                <input type="tel" id="editPhone" class="input-field" placeholder="Namba ya simu" data-i18n="msimamizi_index.placeholder_phone" data-i18n-attr="placeholder">
            </div>
            <div class="input-group">
                <label class="input-label" data-i18n="msimamizi_index.label_business_name">Jina la Biashara</label>
                <input type="text" id="editBusinessName" class="input-field" placeholder="Jina la biashara" data-i18n="msimamizi_index.placeholder_business_name" data-i18n-attr="placeholder">
                <div class="helper-text" data-i18n="msimamizi_index.hint_business_name_filter">Jina hili litatumika ku-filter wauzaji wako</div>
            </div>
            <div class="input-group">
                <label class="input-label" data-i18n="msimamizi_index.label_business_location">Eneo la Biashara</label>
                <input type="text" id="editBusinessLocation" class="input-field" placeholder="Eneo la biashara" data-i18n="msimamizi_index.placeholder_business_location" data-i18n-attr="placeholder">
            </div>
            <div class="input-group">
                <label class="input-label" data-i18n="msimamizi_index.label_business_type">Aina ya Biashara</label>
                <select id="editBusinessType" class="input-field"></select>
                <div class="helper-text" data-i18n="msimamizi_index.hint_ai_understands">Husaidia kipengele cha AI kufahamu bidhaa zako</div>
            </div>
            <div class="input-group">
                <label class="input-label" data-i18n="msimamizi_index.label_business_description">Maelezo mafupi ya Biashara</label>
                <textarea id="editBusinessDescription" class="input-field" rows="2" placeholder="Mfano: Tunauza sehemu za magari ya Toyota na Nissan..." data-i18n="msimamizi_index.placeholder_business_description" data-i18n-attr="placeholder" style="resize:vertical;"></textarea>
            </div>
            <div class="modal-buttons">
                <div class="modal-btn btn-cancel" onclick="closeEditModal()" data-i18n="msimamizi_index.btn_cancel">Ghairi</div>
                <div class="modal-btn btn-save" id="editSaveBtn" onclick="saveEditModal()" data-i18n="msimamizi_index.btn_save">Hifadhi</div>
            </div>
        </div>
    </div>
</body>
</html>
@endverbatim