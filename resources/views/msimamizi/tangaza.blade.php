@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="msimamizi_tangaza.page_title">Tangaza - Dukamkononi Msimamizi</title>
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
            color: #e74c3c; font-weight: bold; font-size: 18px;
        }
        .user-name { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .user-role { font-size: 12px; color: #e74c3c; font-weight: 600; }
        .logout-btn {
            display: flex; align-items: center; gap: 10px; padding: 12px 16px;
            background: #f8f9fa; border-radius: 12px; cursor: pointer;
            color: #e74c3c; font-weight: 600; font-size: 14px;
        }
        .main-content { flex: 1; margin-left: 280px; min-height: 100vh; background: #f8f9fa; padding: 20px 24px 40px; }
        .mobile-menu-toggle {
            display: none; position: fixed; top: 16px; left: 16px; z-index: 200;
            background: white; padding: 12px; border-radius: 10px; cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
        }

        /* Tangaza Screen Styles */
        .container { max-width: 700px; margin: 0 auto; }
        .tab-container {
            display: flex; background: white; border-radius: 16px; margin-bottom: 20px;
            overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .tab {
            flex: 1; text-align: center; padding: 16px; cursor: pointer;
            font-weight: 700; color: #666; transition: all 0.2s;
            border-bottom: 3px solid transparent;
        }
        .tab.active { color: #FF6B35; border-bottom-color: #FF6B35; background: #fff5f0; }
        .card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .title { font-size: 22px; font-weight: 800; color: #1a1a1a; margin-bottom: 6px; }
        .subtitle { font-size: 14px; color: #666; margin-bottom: 16px; }
        
        .payment-banner { background: #fff3cd; padding: 15px; border-radius: 12px; border-left: 4px solid #f0ad4e; margin-top: 10px; }
        .payment-banner-title { font-size: 16px; font-weight: 700; color: #856404; margin-bottom: 8px; }
        .payment-banner-text { font-size: 13px; color: #856404; line-height: 1.5; }
        .payment-price-row { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
        .payment-price { background: #f0ad4e; color: white; font-weight: 700; font-size: 14px; padding: 4px 10px; border-radius: 6px; }
        .payment-duration { font-size: 12px; color: #856404; font-weight: 600; }
        
        .network-warning { background: #fff3cd; padding: 12px; border-radius: 10px; margin-top: 12px; color: #856404; font-size: 13px; }
        
        .section-title { font-size: 18px; font-weight: 700; color: #1a1a1a; margin-bottom: 12px; }
        
        .media-picker {
            border: 2px dashed #28a745; border-radius: 16px; padding: 30px;
            text-align: center; cursor: pointer; background: #f8fafc;
        }
        .media-picker-icon { font-size: 48px; margin-bottom: 8px; }
        .media-picker-text { color: #28a745; font-weight: 700; margin-bottom: 4px; }
        .file-hint { font-size: 11px; color: #6c757d; }
        
        .media-preview { position: relative; margin-top: 12px; }
        .media-preview-img { width: 100%; max-height: 250px; object-fit: cover; border-radius: 12px; }
        .media-preview-video { width: 100%; max-height: 250px; border-radius: 12px; background: #000; }
        .clear-media {
            position: absolute; top: 8px; right: 8px; background: #FF3B30;
            padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600;
            cursor: pointer; font-size: 12px;
        }
        
        textarea { width: 100%; border: 1px solid #ddd; border-radius: 12px; padding: 16px; font-size: 14px; font-family: inherit; resize: vertical; min-height: 120px; }
        .char-count { text-align: right; font-size: 11px; color: #95a5a6; margin-top: 6px; }
        
        .upload-btn {
            background: #28a745; color: white; border: none; padding: 16px;
            border-radius: 12px; font-weight: 800; font-size: 16px; width: 100%;
            cursor: pointer; text-align: center; margin-top: 10px;
        }
        .upload-btn.disabled { background: #95a5a6; cursor: not-allowed; }
        
        .instructions { background: #e8f4fd; padding: 16px; border-radius: 12px; margin-top: 20px; }
        .instructions-title { font-weight: 700; color: #007AFF; margin-bottom: 8px; }
        .instruction { font-size: 13px; color: #2c3e50; margin-bottom: 6px; }
        
        /* My Posts */
        .posts-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .refresh-btn { background: #FF6B35; padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; font-size: 12px; cursor: pointer; }
        
        .ad-card { background: white; border-radius: 16px; padding: 16px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .ad-header { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .ad-title { font-weight: 700; color: #2c3e50; }
        .ad-date { font-size: 11px; color: #95a5a6; }
        .ad-description { font-size: 14px; color: #34495e; line-height: 1.5; margin-bottom: 12px; }
        .ad-media { margin-bottom: 12px; border-radius: 12px; overflow: hidden; background: #f8f9fa; max-height: 180px; }
        .ad-media img { width: 100%; height: auto; max-height: 180px; object-fit: cover; }
        .video-placeholder { display: flex; align-items: center; justify-content: center; padding: 40px; background: #e9ecef; }
        
        .stats-row { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; margin-top: 8px; border-top: 1px solid #eee; }
        .reactions { display: flex; gap: 16px; }
        .stat-badge { display: flex; align-items: center; gap: 4px; font-size: 12px; }
        .stat-badge.likes { color: #3498db; }
        .stat-badge.reports { color: #e74c3c; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-free { background: #d4edda; color: #155724; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .renew-btn { background: #f0ad4e; color: white; padding: 10px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; text-align: center; margin-top: 12px; width: 100%; border: none; }
        .pay-now-btn { background: #FF6B35; color: white; padding: 10px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; text-align: center; margin-top: 12px; width: 100%; border: none; }
        /* In-button busy state: shows the user their click was registered */
        .btn-busy { opacity: 0.75; cursor: wait !important; pointer-events: none; }
        .btn-spinner {
            display: inline-block; width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.4); border-top-color: white;
            border-radius: 50%; animation: spin 0.6s linear infinite;
            vertical-align: middle; margin-right: 8px;
        }
        
        .delete-btn { background: #FF6B35; padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; font-size: 12px; cursor: pointer; display: inline-block; margin-top: 12px; }
        
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 16px; }
        .empty-icon { font-size: 64px; opacity: 0.5; margin-bottom: 16px; }
        .empty-text { font-size: 18px; color: #666; margin-bottom: 8px; }
        .empty-sub { font-size: 14px; color: #999; margin-bottom: 20px; }
        .create-btn { background: #FF6B35; padding: 12px 24px; border-radius: 10px; color: white; font-weight: 700; cursor: pointer; display: inline-block; }
        
        .loading-spinner { border: 2px solid #ddd; border-top-color: #FF6B35; border-radius: 50%; width: 40px; height: 40px; animation: spin 0.6s linear infinite; margin: 0 auto 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { text-align: center; color: #666; margin-top: 12px; }
        
        .hidden { display: none; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
@endverbatim
@include('partials.photo-viewer')
@include('partials.cloudinary-config')
@verbatim
<body>
@endverbatim
    @include('partials.dm-lang-widget')
@verbatim
    <div class="mobile-menu-toggle" id="mobileMenuToggle">☰</div>
    <div class="msimamizi-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><div class="logo-area"><div class="logo-icon">D</div><div class="logo-text"><h2 data-i18n="msimamizi_tangaza.brand_name">DukaMkononi</h2><p data-i18n="msimamizi_tangaza.portal_name">Msimamizi Portal</p></div></div></div>
            <div class="nav-items">
                <a href="index" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-house"></i></div><span class="nav-label" data-i18n="msimamizi_tangaza.nav_home">Nyumbani</span></a>
                <a href="ripoti" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-chart-simple"></i></div><span class="nav-label" data-i18n="msimamizi_tangaza.nav_reports">Ripoti</span></a>
                <a href="preview" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-calendar-days"></i></div><span class="nav-label" data-i18n="msimamizi_tangaza.nav_review">Rejea</span></a>
                <a href="bidhaa-mpya" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-circle-plus"></i></div><span class="nav-label" data-i18n="msimamizi_tangaza.nav_new_product">Bidhaa Mpya</span></a>
                <a href="tangaza" class="nav-item active"><div class="nav-icon"><i class="fa-solid fa-bullhorn"></i></div><span class="nav-label" data-i18n="msimamizi_tangaza.nav_advertise">Tangaza</span></a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info"><div class="user-avatar" id="userAvatar">M</div><div class="user-details"><div class="user-name" id="userName" data-i18n="msimamizi_tangaza.user_admin">Msimamizi</div><div class="user-role" data-i18n="msimamizi_tangaza.user_admin">Msimamizi</div></div></div>
                <div class="logout-btn" id="logoutBtn"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span data-i18n="msimamizi_tangaza.logout">Ondoka</span></div>
            </div>
        </aside>
        <main class="main-content" id="mainContent"><div style="text-align:center;padding:60px;"><div class="loading-spinner"></div><div data-i18n="msimamizi_tangaza.loading_initial">Inapakia...</div></div></main>
    </div>

    <script>
        const API_BASE_URL = '';
        const SW = {
            'msimamizi_tangaza': {
            page_title: "Tangaza - Dukamkononi Msimamizi",
            brand_name: "DukaMkononi",
            portal_name: "Msimamizi Portal",
            nav_home: "Nyumbani",
            nav_reports: "Ripoti",
            nav_review: "Rejea",
            nav_new_product: "Bidhaa Mpya",
            nav_advertise: "Tangaza",
            user_admin: "Msimamizi",
            logout: "Ondoka",
            loading_initial: "Inapakia...",
            alert_ok: "Sawa",
            alert_error_title: "Hitilafu",
            success_title: "Imefanikiwa",
            no_connection_title: "Hakuna Muunganiko",
            alert_sign_in_again: "Tafadhali ingia tena",
            alert_login_first: "Tafadhali ingia kwanza",
            alert_write_description: "Tafadhali andika maelezo",
            alert_pick_media: "Tafadhali chagua picha au video",
            check_your_internet: "Angalia intaneti yako",
            ad_created_title: "Matangazo Yameundwa",
            open_new_tab_pay: "Fungua kichupo kipya kulipa TZS 3,000. Baada ya kulipa, boresha ukurasa.",
            ad_created_pay_later: "Matangazo yameundwa. Lipa upya baada ya kuingia.",
            ad_created_success: "Matangazo yamewekwa kikamilifu!",
            upload_failed: "Imeshindwa kupakia faili",
            save_failed: "Imeshindikana kuhifadhi tangazo",
            check_payment_title: "Angalia Malipo",
            complete_payment_new_tab: "Fungua kichupo kipya kukamilisha malipo ya TZS 3,000. Baada ya kulipa, boresha ukurasa.",
            start_payment_failed: "Imeshindwa kuanzisha malipo",
            failed_prefix: "Imeshindwa: ",
            delete_ad_title: "Futa Matangazo",
            delete_ad_confirm: "Una uhakika unataka kufuta tangazo hili?",
            delete_ad_no_refund: "Usajili wako hautarudishwa fedha baada ya kufuta tangazo hili.",
            ad_deleted_success: "Tangazo limefutwa kikamilifu!",
            delete_failed: "Imeshindikana kufuta tangazo",
            confirm_no: "Ghairi",
            confirm_yes: "Thibitisha",
            user_generic: "Mtumiaji",
            avatar_alt: "Picha",
            currency_prefix: "TSh ",
            saving: "Inahifadhi...",
            starting_payment: "Inaanza malipo...",
            deleting: "Inafuta...",
            payment_description: "Malipo ya matangazo",
            image_or_video_only: "Chagua picha au video tu",
            file_too_big: "Faili ni kubwa! Kiwango cha juu: {size}",
            max_size_image: "5MB",
            max_size_video: "20MB",
            max_size_video_time: "20MB (sekunde 60)",
            create_ad: "Tengeneza Matangazo",
            create_ad_subtitle: "Weka maelezo na media ya matangazo yako",
            ad_payment_title: "Malipo ya Matangazo",
            pay_via_pesapal: "• Matangazo yanalipwa kupitia PesaPal",
            duration_30_days: "• Mudumu: Siku 30 baada ya malipo",
            open_marketplace: "• Fungua soko lako kwa urahisi",
            ad_price: "TZS 3,000",
            for_30_days: "kwa siku 30",
            no_internet_warning: "Hakuna muunganiko wa intaneti",
            media_video: "Video",
            media_image: "Picha",
            clear_media: "Futa Media",
            pick_image_or_video: "Chagua Picha au Video",
            max_size: "Ukubwa wa juu: {size}",
            no_internet: "Hakuna intaneti",
            description_label: "Maelezo",
            description_placeholder: "Elezea bidhaa au huduma yako kwa kina...",
            uploading: "Inapakia...",
            submit_ad: "TUMIA MATANGAZO",
            help_title: "Usaidizi:",
            tip_image: "• Picha: Chagua picha chini ya 5MB",
            tip_video: "• Video: Chini ya 20MB na sekunde 60",
            tip_network: "• Muunganiko: Hakikisha una intaneti nzuri",
            tip_wait: "• Subiri: Upakiaji unaweza kuchukua sekunde kadhaa",
            preview_alt: "Hakiki",
            ad_image_alt: "Tangazo",
            loading_ads: "Inapakia matangazo...",
            no_ads_yet: "Hakuna matangazo bado",
            no_ads_sub: "Tengeneza matangazo yako ya kwanza",
            create_ad_caps: "TENGENEZA MATANGAZO",
            status_free: "Bila malipo",
            status_pending: "Inasubiri malipo",
            status_expired: "Imeisha muda",
            status_paid: "Imelipwa",
            ad_default_title: "Matangazo #{id}",
            days_left: "Siku {count} zimebaki",
            ad_video_label: "Video Tangazo",
            likes: "{count} Likes",
            reports: "{count} Ripoti",
            renew_pay: "Lipa Upya (TZS 3,000)",
            pay_now: "Lipa Sasa (TZS 3,000)",
            delete: "Futa",
            tab_create: "TENGENEZA",
            tab_my_ads: "MATANGAZO YANGU ({count})",
            my_ads_title: "Matangazo Yangu",
            refresh: "Pakia Upya",
            }
        };
        // Mirrors DM.t(): prefer the live locale, fall back to Swahili.
        function t(key, params) {
            const p = params || {};
            const full = 'msimamizi_tangaza.' + key;
            if (window.DM && typeof window.DM.t === 'function') return window.DM.t(full, p);
            let out = (SW.msimamizi_tangaza[key] !== undefined) ? SW.msimamizi_tangaza[key] : full;
            for (const k in p) out = String(out).split('{' + k + '}').join(p[k]);
            return out;
        }
        const CLOUDINARY_CONFIG = (window.CLOUDINARY_CONFIG || { cloudName: '', uploadPreset: 'react_native_uploads' });
        
        let userToken = null;
        let currentUser = null;
        let activeTab = 'post';
        let description = '';
        let mediaFile = null;
        let mediaType = null;
        let mediaPreview = null;
        let uploading = false;
        let isOnline = true;
        let myAdvertisements = [];
        let loadingAds = false;
        let loadingUser = true;

        // Per-button busy states so the clicked button shows a spinner while
        // the request runs — the page stays exactly where it is.
        let payingAdId = null;   // "Lipa Sasa" / "Lipa Upya" in flight
        let deletingAdId = null; // "Futa" in flight (after confirm)

        function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
        function formatDate(dateStr) { try { return new Date(dateStr).toLocaleDateString('sw-TZ'); } catch { return dateStr; } }
        function formatCurrency(amount) { return t('currency_prefix') + (amount || 0).toLocaleString(); }

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
            flag: 'fa-flag', crown: 'fa-crown', info: 'fa-circle-info'
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
                <div style="background:#FF6B35;padding:12px;border-radius:40px;color:white;cursor:pointer;">${t('alert_ok')}</div>`;
            box.querySelector('div:last-child').onclick = () => { overlay.remove(); if(onOk) onOk(); };
            overlay.appendChild(box);
            document.body.appendChild(overlay);
        }

        // Delete confirmation: warns about the non-refundable subscription
        // when the post still has active paid days remaining.
        function showConfirmDelete(title, message, subMessage, onConfirm) {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:2000;';
            const box = document.createElement('div');
            box.style.cssText = 'background:white;border-radius:28px;width:85%;max-width:340px;padding:24px;text-align:center;';
            box.innerHTML = `
                <div style="font-size:18px;font-weight:800;margin-bottom:12px;">${escapeHtml(title)}</div>
                <div style="font-size:14px;color:#5d6d7e;margin-bottom:12px;">${escapeHtml(message)}</div>
                ${subMessage ? `<div style="font-size:13px;color:#b3261e;background:#fdecea;border-radius:10px;padding:10px 12px;margin-bottom:20px;">${escapeHtml(subMessage)}</div>` : '<div style="margin-bottom:20px;"></div>'}
                <div style="display:flex;gap:12px;">
                    <div id="confirmNo" style="flex:1;background:#95a5a6;padding:12px;border-radius:40px;color:white;cursor:pointer;font-weight:700;">${t('confirm_no')}</div>
                    <div id="confirmYes" style="flex:1;background:#e74c3c;padding:12px;border-radius:40px;color:white;cursor:pointer;font-weight:700;">${t('confirm_yes')}</div>
                </div>`;
            overlay.appendChild(box);
            document.body.appendChild(overlay);
            document.getElementById('confirmYes').onclick = () => { overlay.remove(); onConfirm(); };
            document.getElementById('confirmNo').onclick = () => overlay.remove();
            overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
        }

        async function loadUserData() {
            userToken = localStorage.getItem('userToken');
            const userStr = localStorage.getItem('userData');
            if (!userToken || !userStr) {
                showAlert(t('alert_error_title'), t('alert_sign_in_again'), () => window.location.href = '../login?role=msimamizi');
                return false;
            }
            const user = JSON.parse(userStr);
            currentUser = {
                // userId is a UUID, so parseInt() produced NaN. Nothing read
                // this field, but it was a lie that invited the same bug on a
                // real code path later.
                id: localStorage.getItem('userId') || user.id || '',
                email: user.email || '',
                name: user.full_name || user.business_name || t('user_generic'),
                role: user.role || '',
                phone: user.phone || '',
                business_name: user.business_name || ''
            };
            document.getElementById('userName').innerHTML = escapeHtml(currentUser.name);
            const avatarEl = document.getElementById('userAvatar');
            const displayName = user.full_name || user.business_name || user.email?.split('@')[0] || t('user_admin');
            if (user.business_logo_url) {
                avatarEl.classList.add('js-avatar-view');
avatarEl.setAttribute('data-full', user.business_logo_url);
avatarEl.setAttribute('data-name', displayName);
avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="${t('avatar_alt')}">`;
            } else {
                avatarEl.innerHTML = (currentUser.name.charAt(0) || 'M').toUpperCase();
            }
            return true;
        }

        async function uploadToCloudinary(file, type) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('upload_preset', CLOUDINARY_CONFIG.uploadPreset);
            
            const uploadUrl = `https://api.cloudinary.com/v1_1/${CLOUDINARY_CONFIG.cloudName}/${type}/upload`;
            const response = await fetch(uploadUrl, { method: 'POST', body: formData });
            if (!response.ok) throw new Error(t('upload_failed'));
            const data = await response.json();
            return { secure_url: data.secure_url, public_id: data.public_id };
        }

        async function handleUpload() {
            if (!currentUser) { showAlert(t('alert_error_title'), t('alert_login_first')); return; }
            if (!description.trim()) { showAlert(t('alert_error_title'), t('alert_write_description')); return; }
            if (!mediaFile) { showAlert(t('alert_error_title'), t('alert_pick_media')); return; }
            if (!isOnline) { showAlert(t('no_connection_title'), t('check_your_internet')); return; }

            uploading = true;
            render();

            try {
                // Upload the media file
                const cloudResult = await uploadToCloudinary(mediaFile, mediaType);
                // Save to backend
                const response = await fetch(`${API_BASE_URL}/api/matangazo`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${userToken}` },
                    body: JSON.stringify({
                        description: description.trim(),
                        media_url: cloudResult.secure_url,
                        media_type: mediaType,
                        thumbnail_url: cloudResult.secure_url,
                        payment_status: 'pending'
                    })
                });
                
                if (response.ok) {
                    const matangazoData = await response.json();
                    const matangazoId = matangazoData?.data?.id;
                    if (matangazoId) {
                        try {
                            const payRes = await fetch(API_BASE_URL + '/api/payments/pesapal/initiate', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + userToken },
                                body: JSON.stringify({ amount: 3000, description: description.trim(), matangazo_id: matangazoId, phone: currentUser?.phone || '', email: currentUser?.email || '', name: currentUser?.name || '' })
                            });
                            const payData = await payRes.json();
                            if (payRes.ok && payData.redirect_url) {
                                // Same popup-block fallback as handleRenewPayment.
                                const payWin = window.open(payData.redirect_url, '_blank');
                                if (!payWin) { window.location.href = payData.redirect_url; return; }
                                showAlert(t('ad_created_title'), t('open_new_tab_pay'));
                            } else {
                                showAlert(t('success_title'), t('ad_created_pay_later'));
                            }
                        } catch(e) { showAlert(t('success_title'), t('ad_created_pay_later')); }
                    } else {
                        showAlert(t('success_title'), t('ad_created_success'));
                    }
                    description = '';
                    mediaFile = null;
                    mediaType = null;
                    mediaPreview = null;
                    await loadMyAdvertisements();
                    activeTab = 'myPosts';
                } else {
                    throw new Error(t('save_failed'));
                }
            } catch (err) {
                showAlert(t('alert_error_title'), err.message);
            } finally {
                uploading = false;
                render();
            }
        }

        async function loadMyAdvertisements() {
            if (!userToken) return;
            loadingAds = true;
            render();
            try {
                const response = await fetch(`${API_BASE_URL}/api/matangazo/my`, {
                    headers: { 'Authorization': `Bearer ${userToken}` }
                });
                if (response.ok) {
                    // The endpoint has returned both a bare array and
                    // {data:[...]} shapes over time; renderMyPosts maps over it,
                    // so anything else would throw and blank the page.
                    const payload = await response.json();
                    myAdvertisements = Array.isArray(payload) ? payload
                        : (Array.isArray(payload?.data) ? payload.data : []);
                }
            } catch (err) { console.error(err); }
            finally { loadingAds = false; render(); }
        }

        function setButtonBusy(id, busy, label) {
            const btn = document.getElementById(id);
            if (!btn) return;
            if (busy) {
                btn.dataset.originalHtml = btn.innerHTML;
                btn.classList.add('btn-busy');
                btn.innerHTML = '<span class="btn-spinner"></span>' + (label || t('saving'));
            } else {
                btn.classList.remove('btn-busy');
                if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
            }
        }

        async function handleRenewPayment(adId) {
            if (!userToken) { showAlert(t('alert_error_title'), t('alert_sign_in_again')); return; }
            if (payingAdId || deletingAdId) return; // one action at a time
            payingAdId = adId;
            setButtonBusy('payBtn-' + adId, true, t('starting_payment'));
            setButtonBusy('renewBtn-' + adId, true, t('starting_payment'));
            try {
                const res = await fetch(API_BASE_URL + '/api/payments/pesapal/initiate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + userToken },
                    body: JSON.stringify({ amount: 3000, description: t('payment_description'), matangazo_id: adId, phone: currentUser?.phone || '', email: currentUser?.email || '', name: currentUser?.name || '' })
                });
                const data = await res.json();
                if (res.ok && data.redirect_url) {
                    // Try a new tab first; if the browser blocks the popup
                    // (window.open after an async call often is), fall back to
                    // navigating in the same tab so payment always starts.
                    const win = window.open(data.redirect_url, '_blank');
                    if (!win) { window.location.href = data.redirect_url; return; }
                    showAlert(t('check_payment_title'), t('complete_payment_new_tab'));
                } else {
                    showAlert(t('alert_error_title'), data.error || t('start_payment_failed'));
                }
            } catch(e) { showAlert(t('alert_error_title'), t('failed_prefix') + e.message); }
            finally {
                payingAdId = null;
                setButtonBusy('payBtn-' + adId, false);
                setButtonBusy('renewBtn-' + adId, false);
            }
        }

        async function deleteAdvertisement(id) {
            const ad = myAdvertisements.find(a => String(a.id) === String(id));
            // Active subscription = paid days still remaining (< 30 left of a
            // paid post). Warn that the money will NOT come back.
            const hasActiveSub = ad && ad.expires_at &&
                (ad.payment_status === 'completed') &&
                (new Date(ad.expires_at).getTime() > Date.now());

            showConfirmDelete(
                t('delete_ad_title'),
                t('delete_ad_confirm'),
                hasActiveSub ? t('delete_ad_no_refund') : null,
                async () => {
                    if (payingAdId || deletingAdId) return;
                    deletingAdId = id;
                    setButtonBusy('deleteBtn-' + id, true, t('deleting'));
                    try {
                        const response = await fetch(`${API_BASE_URL}/api/matangazo/${id}`, {
                            method: 'DELETE',
                            headers: { 'Authorization': `Bearer ${userToken}` }
                        });
                        const data = await response.json().catch(() => ({}));
                        if (response.ok) {
                            showAlert(t('success_title'), t('ad_deleted_success'));
                            await loadMyAdvertisements();
                        } else {
                            showAlert(t('alert_error_title'), data.error || t('delete_failed'));
                        }
                    } catch (err) { showAlert(t('alert_error_title'), err.message); }
                    finally {
                        deletingAdId = null;
                        setButtonBusy('deleteBtn-' + id, false);
                    }
                }
            );
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const fileType = file.type.startsWith('image/') ? 'image' : (file.type.startsWith('video/') ? 'video' : null);
            if (!fileType) { showAlert(t('alert_error_title'), t('image_or_video_only')); return; }
            
            const maxSize = fileType === 'image' ? 5 * 1024 * 1024 : 20 * 1024 * 1024;
            if (file.size > maxSize) {
                showAlert(t('alert_error_title'), t('file_too_big', { size: fileType === 'image' ? t('max_size_image') : t('max_size_video') }));
                return;
            }
            
            mediaFile = file;
            mediaType = fileType;
            mediaPreview = URL.createObjectURL(file);
            render();
        }

        function clearMedia() {
            mediaFile = null;
            mediaType = null;
            if (mediaPreview) URL.revokeObjectURL(mediaPreview);
            mediaPreview = null;
            render();
        }

        function renderPostNew() {
            return `
                <div class="card">
                    <div class="title">${t('create_ad')}</div>
                    <div class="subtitle">${t('create_ad_subtitle')}</div>
                    
                    <div class="payment-banner">
                        <div class="payment-banner-title">${t('ad_payment_title')}</div>
                        <div class="payment-banner-text">${t('pay_via_pesapal')}<br>${t('duration_30_days')}<br>${t('open_marketplace')}</div>
                        <div class="payment-price-row">
                            <span class="payment-price">${t('ad_price')}</span>
                            <span class="payment-duration">${t('for_30_days')}</span>
                        </div>
                    </div>
                    
                    ${!isOnline ? `<div class="network-warning">${t('no_internet_warning')}</div>` : ''}
                </div>
                
                <div class="card">
                    <div class="section-title" style="display:flex;align-items:center;gap:8px;">${mediaType === 'video' ? ic('video', 17) : ic('image', 17)} ${mediaType === 'video' ? t('media_video') : t('media_image')}</div>
                    ${mediaPreview ? `
                        <div class="media-preview">
                            ${mediaType === 'image' ? `<img src="${mediaPreview}" class="media-preview-img" alt="${t('preview_alt')}">` : `<video src="${mediaPreview}" class="media-preview-video" controls></video>`}
                            <div class="clear-media" onclick="clearMedia()">${t('clear_media')}</div>
                        </div>
                    ` : `
                        <div class="media-picker" onclick="document.getElementById('fileInput').click()">
                            <div class="media-picker-icon" style="display:flex;justify-content:center;color:#28a745;">${ic('folder', 44)}</div>
                            <div class="media-picker-text">${t('pick_image_or_video')}</div>
                            <div class="file-hint">${t('max_size', { size: mediaType === 'video' ? t('max_size_video_time') : t('max_size_image') })}</div>
                            ${!isOnline ? `<div class="file-hint" style="color:#dc3545;">${t('no_internet')}</div>` : ''}
                        </div>
                        <input type="file" id="fileInput" style="display:none" accept="image/*,video/*" onchange="handleFileSelect(event)">
                    `}
                </div>
                
                <div class="card">
                    <div class="section-title" style="display:flex;align-items:center;gap:8px;">${ic('edit', 17)} ${t('description_label')}</div>
                    <textarea id="descriptionInput" placeholder="${t('description_placeholder')}" maxlength="500">${escapeHtml(description)}</textarea>
                    <div class="char-count" id="charCount">${description.length} / 500</div>
                </div>
                
                <button class="upload-btn ${(uploading || !isOnline) ? 'disabled' : ''}" onclick="handleUpload()" ${(uploading || !isOnline) ? 'disabled' : ''}>
                    ${uploading ? '<div class="loading-spinner" style="width:20px;height:20px;display:inline-block;margin-right:8px;"></div> ' + t('uploading') : ic('megaphone', 17) + ' ' + t('submit_ad')}
                </button>
                
                <div class="instructions">
                    <div class="instructions-title" style="display:flex;align-items:center;gap:6px;">${ic('info', 14)} ${t('help_title')}</div>
                    <div class="instruction">${t('tip_image')}</div>
                    <div class="instruction">${t('tip_video')}</div>
                    <div class="instruction">${t('tip_network')}</div>
                    <div class="instruction">${t('tip_wait')}</div>
                </div>
            `;
        }

        function renderMyPosts() {
            if (loadingAds) {
                return `<div style="text-align:center;padding:60px;"><div class="loading-spinner"></div><div>${t('loading_ads')}</div></div>`;
            }
            
            if (myAdvertisements.length === 0) {
                return `
                    <div class="empty-state">
                        <div class="empty-icon" style="display:flex;justify-content:center;color:#b8c4cf;">${ic('megaphone', 56)}</div>
                        <div class="empty-text">${t('no_ads_yet')}</div>
                        <div class="empty-sub">${t('no_ads_sub')}</div>
                        <div class="create-btn" onclick="setTab('post')">${t('create_ad_caps')}</div>
                    </div>
                `;
            }
            
            return myAdvertisements.map(ad => {
                const isFree = ad.is_free === true || ad.payment_status === 'free';
                const isExpired = ad.expires_at && new Date(ad.expires_at).getTime() <= Date.now();
                const isPending = ad.payment_status === 'pending' || ad.payment_status === 'failed';
                const daysLeft = ad.expires_at ? Math.max(0, Math.ceil((new Date(ad.expires_at).getTime() - Date.now()) / (24*60*60*1000))) : 0;
                
                let statusLabel, statusClass;
                if (isFree) { statusLabel = t('status_free'); statusClass = 'status-free'; }
                else if (isPending) { statusLabel = t('status_pending'); statusClass = 'status-pending'; }
                else if (isExpired) { statusLabel = t('status_expired'); statusClass = 'status-expired'; }
                else { statusLabel = t('status_paid'); statusClass = 'status-paid'; }
                
                return `<div class="ad-card">
                    <div class="ad-header">
                        <div class="ad-title">${escapeHtml(ad.title || t('ad_default_title', { id: ad.id }))}</div>
                        <div class="ad-date">${daysLeft > 0 ? t('days_left', { count: daysLeft }) : formatDate(ad.created_at)}</div>
                    </div>
                    <div class="ad-description">${escapeHtml(ad.description)}</div>
                    <div class="ad-media">
                        ${ad.media_type === 'video' ? '<div class="video-placeholder" style="display:flex;flex-direction:column;align-items:center;gap:8px;color:#7a8ba0;">' + ic('video', 28) + '<span style="font-size:13px;">' + t('ad_video_label') + '</span></div>' : '<img src="' + ad.media_url + '" alt="' + t('ad_image_alt') + '" onerror="this.src=\'https://via.placeholder.com/300?text=No+Image\'">'}
                    </div>
                    <div class="stats-row">
                        <div class="reactions">
                            <div class="stat-badge likes">${ic('thumb', 13)} ${t('likes', { count: ad.like_count || 0 })}</div>
                            <div class="stat-badge reports">${ic('flag', 13)} ${t('reports', { count: ad.report_count || 0 })}</div>
                        </div>
                        <div class="status-badge ${statusClass}">${statusLabel}</div>
                    </div>
                    ${isExpired ? `<button id="renewBtn-${ad.id}" class="renew-btn" onclick="handleRenewPayment('${ad.id}')">${ic('money', 14)} ${t('renew_pay')}</button>` : ''}
                    ${isPending ? `<button id="payBtn-${ad.id}" class="pay-now-btn" onclick="handleRenewPayment('${ad.id}')">${ic('money', 14)} ${t('pay_now')}</button>` : ''}
                    <div id="deleteBtn-${ad.id}" class="delete-btn" onclick="deleteAdvertisement('${ad.id}')">${t('delete')}</div>
                </div>`;
            }).join('');
        }

        function render() {
            const container = document.getElementById('mainContent');
            container.innerHTML = `
                <div class="container">
                    <div class="tab-container">
                        <div class="tab ${activeTab === 'post' ? 'active' : ''}" onclick="setTab('post')">${ic('megaphone', 15)} ${t('tab_create')}</div>
                        <div class="tab ${activeTab === 'myPosts' ? 'active' : ''}" onclick="setTab('myPosts')">${ic('doc', 15)} ${t('tab_my_ads', { count: myAdvertisements.length })}</div>
                    </div>
                    ${activeTab === 'post' ? renderPostNew() : `
                        <div class="posts-header">
                            <div class="section-title">${t('my_ads_title')}</div>
                            <div class="refresh-btn" onclick="refreshAds()">${ic('refresh', 13)} ${t('refresh')}</div>
                        </div>
                        ${renderMyPosts()}
                    `}
                </div>
            `;
            if (activeTab === 'post') setupDescriptionListener();
        }

        window.setTab = (tab) => {
            activeTab = tab;
            if (tab === 'myPosts') loadMyAdvertisements();
            else render();
        };
        
        window.refreshAds = () => { loadMyAdvertisements(); };
        window.handleFileSelect = handleFileSelect;
        window.clearMedia = clearMedia;
        window.handleUpload = handleUpload;
        window.deleteAdvertisement = deleteAdvertisement;
        window.handleRenewPayment = handleRenewPayment;
        
        // Update description on input — WITHOUT re-rendering. Re-rendering on
        // every keystroke destroyed the textarea (cursor jumped out and the
        // page scrolled up); only the char counter needs to change.
        function setupDescriptionListener() {
            const descInput = document.getElementById('descriptionInput');
            if (descInput) {
                descInput.addEventListener('input', (e) => {
                    description = e.target.value;
                    const cc = document.getElementById('charCount');
                    if (cc) cc.textContent = description.length + ' / 500';
                });
            }
        }

        async function refreshAds() { await loadMyAdvertisements(); }

        function setupSidebar() {
            document.getElementById('mobileMenuToggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
            document.getElementById('logoutBtn').addEventListener('click', () => { localStorage.clear(); window.location.href = '../login?role=msimamizi'; });
        }

        // Check network — re-render ONLY when the status actually flips.
        // The old version re-rendered every 15s, which threw away the
        // textarea and anything the user was typing at that moment.
        async function checkNetwork() {
            let online = true;
            try {
                await fetch('https://www.google.com', { method: 'HEAD', mode: 'no-cors' });
                online = true;
            } catch { online = false; }
            if (online !== isOnline) {
                isOnline = online;
                render();
            }
        }
        
        async function init() {
            setupSidebar();
            const success = await loadUserData();
            if (!success) return;
            await loadMyAdvertisements();
            loadingUser = false;
            render();
            checkNetwork();
            setInterval(checkNetwork, 15000);
        }
        
        // Re-render dynamic content when the user switches language.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => { if (!loadingUser) render(); });
        }

        init();
    </script>
</body>
</html>
@endverbatim