@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_tangaza.page_title">Tangaza - Dukamkononi Muuzaji</title>
    <link rel="stylesheet" href="/css/themes.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        .muuzaji-layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 280px; background-color: #ffffff; border-right: 1px solid #ecf0f1;
            display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0;
            z-index: 100; transition: transform 0.3s ease; box-shadow: 2px 0 12px rgba(0,0,0,0.05);
        }
        .sidebar-header { padding: 30px 24px; border-bottom: 1px solid #ecf0f1; margin-bottom: 20px; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 45px; height: 45px; background: linear-gradient(135deg, #2ecc71, #27ae60);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 24px; font-weight: bold;
        }
        .logo-text h2 { font-size: 18px; font-weight: 800; color: #2c3e50; }
        .logo-text p { font-size: 12px; color: #7f8c8d; }

        .nav-items { flex: 1; padding: 0 16px; overflow-y: auto; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin-bottom: 6px;
            border-radius: 14px; cursor: pointer; transition: all 0.2s ease; color: #5d6d7e;
            font-weight: 500; text-decoration: none;
        }
        .nav-item:hover { background-color: #e8f8f0; }
        .nav-item.active { background-color: #e8f8f0; color: #2ecc71; }
        .nav-icon { font-size: 20px; width: 26px; }
        .nav-label { font-size: 15px; font-weight: 600; }

        .sidebar-footer { padding: 20px 16px; border-top: 1px solid #ecf0f1; margin-top: auto; }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .user-avatar {
            width: 45px; height: 45px; background-color: #e8f8f0; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: #2ecc71; font-weight: bold; font-size: 18px;
        }
        .user-details { flex: 1; }
        .user-name { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .user-role { font-size: 12px; color: #2ecc71; font-weight: 600; }
        .logout-btn {
            display: flex; align-items: center; gap: 10px; padding: 12px 16px; background-color: #f8f9fa;
            border-radius: 12px; cursor: pointer; color: #e74c3c; font-weight: 600; font-size: 14px;
        }
        .logout-btn:hover { background-color: #fdeaea; }

        .main-content { flex: 1; margin-left: 280px; min-height: 100vh; background-color: #f8f9fa; padding: 20px 24px 40px; }
        .mobile-menu-toggle {
            display: none; position: fixed; top: 16px; left: 16px; z-index: 200; background: white;
            padding: 12px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer;
        }
        .page-container { max-width: 900px; margin: 0 auto; width: 100%; }

        .loading-container { text-align: center; padding: 60px 20px; }
        .loading-spinner {
            width: 50px; height: 50px; border: 3px solid #e0e0e0; border-top-color: #2ecc71;
            border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { color: #7f8c8d; font-size: 14px; }

        .header { background: white; padding: 16px 20px; border-radius: 16px; margin-bottom: 16px; }
        .header-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .back-btn {
            background: #f5f5f5; width: 40px; height: 40px; border-radius: 20px; display: flex;
            align-items: center; justify-content: center; cursor: pointer; text-decoration: none; color: #2c3e50; font-size: 20px;
        }
        .title { font-size: 20px; font-weight: 700; color: #2c3e50; flex: 1; }

        .banner { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 14px; margin-bottom: 14px; font-size: 13px; font-weight: 600; }
        .banner.ok { background: #e8f8f0; color: #1e8449; }
        .banner.warn { background: #fef5e7; color: #b9770e; }

        .card { background: white; border-radius: 18px; padding: 20px; margin-bottom: 16px; }
        .card-title { font-size: 16px; font-weight: 700; color: #2c3e50; margin-bottom: 14px; }
        .section-hint { font-size: 12px; color: #95a5a6; margin-top: -8px; margin-bottom: 14px; }

        .product-pick { display: flex; flex-direction: column; gap: 8px; }
        .product-option {
            display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 2px solid #ecf0f1;
            border-radius: 14px; cursor: pointer; transition: all 0.15s ease; background: #fff;
        }
        .product-option:hover { border-color: #a9dfbf; }
        .product-option.selected { border-color: #2ecc71; background: #e8f8f0; }
        .product-option input { accent-color: #2ecc71; }
        .product-main { flex: 1; min-width: 0; }
        .product-name { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .product-meta { font-size: 12px; color: #7f8c8d; margin-top: 2px; }
        .product-price { font-size: 14px; font-weight: 700; color: #27ae60; white-space: nowrap; }
        .stock-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #e8f8f0; color: #1e8449; margin-left: 8px; }
        .stock-pill.out { background: #fdeaea; color: #c0392b; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-field { display: flex; flex-direction: column; gap: 6px; }
        .form-field.full { grid-column: 1 / -1; }
        .form-label { font-size: 12px; font-weight: 700; color: #5d6d7e; }
        .form-select, .form-input {
            border: 1.5px solid #e0e0e0; border-radius: 12px; padding: 10px 12px; font-size: 14px;
            font-family: 'Inter', sans-serif; color: #2c3e50; background: #fff; outline: none; width: 100%;
        }
        .form-select:focus, .form-input:focus { border-color: #2ecc71; }
        .chip-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip {
            padding: 8px 14px; border-radius: 20px; border: 1.5px solid #e0e0e0; background: #fff;
            font-size: 13px; font-weight: 600; color: #5d6d7e; cursor: pointer; transition: all 0.15s ease;
        }
        .chip:hover { border-color: #a9dfbf; }
        .chip.selected { background: #2ecc71; border-color: #2ecc71; color: #fff; }

        .generate-row { display: flex; gap: 10px; margin-top: 16px; }
        .btn {
            border: none; border-radius: 12px; padding: 12px 22px; font-size: 14px; font-weight: 700;
            cursor: pointer; font-family: 'Inter', sans-serif; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .btn-primary { background: #2ecc71; color: #fff; }
        .btn-ghost { background: #f5f5f5; color: #2c3e50; }
        .btn-accent { background: #2980b9; color: #fff; }

        .preview-media {
            width: 100%; max-height: 220px; object-fit: cover; border-radius: 14px; margin-bottom: 14px;
            border: 1px solid #ecf0f1;
        }
        .preview-field { margin-bottom: 12px; }
        .preview-field-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .preview-label { font-size: 12px; font-weight: 700; color: #5d6d7e; }
        .char-count { font-size: 11px; color: #95a5a6; font-variant-numeric: tabular-nums; }
        .char-count.over { color: #e74c3c; font-weight: 700; }
        .preview-area {
            width: 100%; border: 1.5px solid #e0e0e0; border-radius: 12px; padding: 12px; font-size: 14px;
            line-height: 1.55; font-family: 'Inter', sans-serif; color: #2c3e50; resize: vertical; outline: none;
            background: #fdfefe;
        }
        .preview-area:focus { border-color: #2ecc71; }
        .preview-area.headline-area { font-weight: 700; font-size: 16px; min-height: 52px; }

        .flag-row {
            display: flex; gap: 8px; align-items: flex-start; padding: 9px 12px; border-radius: 10px;
            background: #fef5e7; color: #b9770e; font-size: 12.5px; font-weight: 600; margin-bottom: 8px;
        }
        .flag-row i { margin-top: 2px; }

        .action-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }

        .draft-row {
            display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #ecf0f1;
            border-radius: 14px; margin-bottom: 8px;
        }
        .draft-main { flex: 1; min-width: 0; }
        .draft-title { font-size: 13.5px; font-weight: 700; color: #2c3e50; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .draft-meta { font-size: 11.5px; color: #95a5a6; margin-top: 2px; }
        .draft-actions { display: flex; gap: 6px; }
        .mini-btn {
            border: none; border-radius: 9px; padding: 7px 12px; font-size: 12px; font-weight: 700; cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        .mini-btn.load { background: #eaf4fc; color: #2980b9; }
        .mini-btn.del { background: #fdeaea; color: #c0392b; }

        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; }
        .empty-icon { font-size: 48px; margin-bottom: 14px; color: #bdc3c7; }
        .empty-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; color: #2c3e50; }
        .empty-text { font-size: 14px; color: #7f8c8d; }
        .retry-btn { margin-top: 16px; background: #2ecc71; color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; }

        .limit-list { margin-top: 4px; }
        .limit-item { display: flex; gap: 8px; font-size: 12.5px; color: #7f8c8d; line-height: 1.55; margin-bottom: 6px; }
        .limit-item i { color: #bdc3c7; margin-top: 3px; }
        .generated { text-align: center; font-size: 11px; color: #bdc3c7; margin-top: 12px; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 16px; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])
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
            <div class="sidebar-header">
                <div class="logo-area">
                    <div class="logo-icon" data-i18n="muuzaji_matumizi.logo_short">D</div>
                    <div class="logo-text">
                        <h2 data-i18n="muuzaji_matumizi.logo_brand">DukaMkononi</h2>
                        <p data-i18n="muuzaji_matumizi.logo_tagline">Muuzaji Portal</p>
                    </div>
                </div>
            </div>

            <div class="nav-items">
                <a href="profaili" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></div>
                    <span class="nav-label" data-i18n="muuzaji_matumizi.nav_profile">Profaili</span>
                </a>
                <a href="mauzo" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-money-bill-1" aria-hidden="true"></i></div>
                    <span class="nav-label" data-i18n="muuzaji_matumizi.nav_sales">Mauzo</span>
                </a>
                <a href="matumizi" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                    <span class="nav-label" data-i18n="muuzaji_matumizi.nav_expenses">Matumizi</span>
                </a>
                <a href="uza" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></div>
                    <span class="nav-label" data-i18n="muuzaji_matumizi.nav_sell">Uza</span>
                </a>
                <a href="orders" class="nav-item">
                    <div class="nav-icon"><i class="fa-solid fa-box" aria-hidden="true"></i></div>
                    <span class="nav-label" data-i18n="muuzaji_orders.nav_orders">Oda</span>
                </a>
                <a href="huduma-nyingine" class="nav-item active">
                    <div class="nav-icon">🧩</div>
                    <span class="nav-label" data-i18n="muuzaji_huduma.nav_other">Huduma Nyingine</span>
                </a>
            </div>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar">M</div>
                    <div class="user-details">
                        <div class="user-name" id="userName">Muuzaji</div>
                        <div class="user-role" data-i18n="muuzaji_matumizi.nav_seller">Muuzaji</div>
                    </div>
                </div>
                <div class="logout-btn" id="logoutBtn">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    <span data-i18n="muuzaji_matumizi.btn_logout">Ondoka</span>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-container" id="studioContent">
                <div class="loading-container">
                    <div class="loading-spinner dm-sc"></div>
                    <div class="loading-text" data-i18n="muuzaji_tangaza.loading">Inapakia studio ya matangazo...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // ============================================
        // TANGAZA (Ad & Product-Copy Studio) - Muuzaji Web
        // Backed by POST /api/ai/ads/generate (read-only draft).
        // Publishing goes through the existing POST /api/matangazo +
        // Pesapal initiate flow, only on the seller's explicit click.
        // ============================================

        const API_BASE_URL = '';
        // Mirrors config('pesapal.matangazo_price') default - the same value
        // msimamizi/tangaza sends for an authorised advertisement.
        const AD_PRICE = 3000;

        const CONTENT_TYPES = ['headline', 'description', 'product_description', 'promo'];
        const TONES = ['friendly', 'professional', 'urgent', 'playful'];
        const LENGTHS = ['short', 'medium', 'long'];
        const LANGS = [
            { code: 'sw', label: 'KISWAHILI' },
            { code: 'en', label: 'ENGLISH' },
            { code: 'fr', label: 'FRANÇAIS' },
            { code: 'hi', label: 'हिन्दी' },
            { code: 'es', label: 'ESPAÑOL' },
            { code: 'ur', label: 'اردو' },
            { code: 'de', label: 'DEUTSCH' },
            { code: 'zh', label: '中文' }
        ];

        // Swahili fallback for script-built strings. Mirrors the sw catalog of
        // the muuzaji_tangaza section in locales.json.
        const SW = {
            loading: 'Inapakia studio ya matangazo...',
            title: 'Studio ya Matangazo',
            subtitle: 'Tengeneza vichwa vya habari na maelezo ya bidhaa yako kwa AI, kisha hariri na kuthibitisha.',
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la Muuzaji.',
            err_load_failed: 'Imeshindwa kupakia bidhaa zako.',
            err_network: 'Hitilafu ya mtandao. Angalia muunganisho wako.',
            err_auth: 'Tafadhali ingia tena.',
            nav_seller: 'Muuzaji',
            btn_retry: 'Jaribu Tena',
            btn_go_sell: 'Nenda Kuuza',
            no_products_title: 'Hakuna bidhaa',
            no_products_text: 'Ongeza bidhaa kwenye duka lako kwanza ili kupata nakala ya matangazo.',
            section_product: '1. Chagua bidhaa',
            product_pick_hint: 'Nakala huhusisha kwa bidhaa moja kwa wakati kwa bei halisi na idadi ya stock.',
            label_price: 'Bei',
            label_stock: 'Stock',
            label_category: 'Aina',
            label_out_of_stock: 'Imeisha',
            section_controls: '2. Vidhibiti vya nakala',
            label_content_type: 'Aina ya maandishi',
            ct_headline: 'Kichwa cha habari',
            ct_description: 'Maelezo ya matangazo',
            ct_product_description: 'Maelezo ya bidhaa',
            ct_promo: 'Ujumbe mfupi wa kupromote',
            label_tone: 'Mtindo',
            tone_friendly: 'Kirafiki',
            tone_professional: 'Kitaalamu',
            tone_urgent: 'Na haraka',
            tone_playful: 'Chezeshi',
            label_length: 'Urefu',
            len_short: 'Mfupi',
            len_medium: 'Wastani',
            len_long: 'Marefu',
            label_audience: 'Hadhira (hiari)',
            ph_audience: 'Mfano: familia, wanafunzi, biashara ndogo',
            label_objective: 'Lengo la matangazo (hiari)',
            ph_objective: 'Mfano: kuuza mwisho wa mwezi, bidhaa mpya',
            label_language: 'Lugha',
            btn_generate: 'Tengeneza nakala',
            btn_generating: 'Inatengeneza...',
            err_pick_product: 'Chagua bidhaa kwanza.',
            section_preview: '3. Hakikisha na hariri',
            preview_hint: 'Badilisha kile unachotaka kuchapisha. Nakala haijachapishwa bila idhini yako.',
            lbl_headline: 'Kichwa',
            lbl_body: 'Maelezo',
            ph_headline: 'Andika kichwa hapa...',
            ph_body: 'Andika maelezo hapa...',
            ai_ready: 'Nakala ya AI tayari',
            ai_degraded_title: 'Nakala ya AI haipatikani',
            err_no_copy: 'Hakuna nakala iliyotengenezwa. Jaribu tena au badilisha vidhibiti.',
            btn_regenerate: 'Tengeneza upya',
            btn_save_draft: 'Hifadhi rasimu',
            btn_publish: 'Chapisha...',
            draft_saved: 'Rasimu imehifadhiwa.',
            section_drafts: 'Rasimu zilizohifadhiwa',
            drafts_empty: 'Hakuna rasimu bado.',
            draft_load: 'Fungua',
            draft_delete: 'Futa',
            publish_confirm: 'Chapisha nakala hii kupitia mfumo wa matangazo uliopo? Itahitaji malipo ya matangazo.',
            publish_no_image: 'Bidhaa hii haina picha. Weka picha kwanza ili tangazo liwe na media.',
            publish_no_copy: 'Hakuna nakala ya kuchapisha. Tengeneza nakala kwanza.',
            publish_pending: 'Tangazo limehifadhiwa lakini malipo hayakamilika - halijaanza kufanya kazi bado. Malipo yanahitajika ili lianze.',
            publish_failed: 'Imeshindwa kutuma tangazo. Jaribu tena.',
            publish_redirect: 'Inaelekezwa kwenye malipo...',
            limitations_title: 'Mipaka ya zana hii',
            generated_at: 'Imetengenezwa: {date}'
        };

        function t(key, params) {
            const full = key.indexOf('muuzaji_tangaza.') === 0 ? key : 'muuzaji_tangaza.' + key;
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

        let products = [];
        let selectedId = null;
        let result = null;          // last /api/ai/ads/generate response
        let editing = { headline: '', body: '' };
        let generating = false;
        let publishing = false;
        let loadError = null;
        let loading = true;
        let controls = { content_type: 'description', tone: 'friendly', length: 'medium', audience: '', objective: '', locale: 'sw' };
        let drafts = loadDrafts();

        function getCurrentUser() {
            const token = localStorage.getItem('userToken');
            const userData = localStorage.getItem('userData');
            if (token && userData) {
                try { return JSON.parse(userData); } catch (e) { return null; }
            }
            return null;
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>\"]/g, function (m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;' }[m];
            });
        }

        function updateSidebarUser() {
            const user = getCurrentUser();
            if (!user) return;
            const displayName = user.full_name || user.business_name || (user.email ? user.email.split('@')[0] : t('nav_seller'));
            const nameEl = document.getElementById('userName');
            if (nameEl) nameEl.innerHTML = escapeHtml(displayName);
            const avatarEl = document.getElementById('userAvatar');
            if (avatarEl) {
                if (user.business_logo_url) {
                    avatarEl.classList.add('js-avatar-view');
                    avatarEl.setAttribute('data-full', user.business_logo_url);
                    avatarEl.setAttribute('data-name', displayName);
                    avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="">`;
                } else {
                    avatarEl.innerHTML = displayName.charAt(0).toUpperCase();
                }
            }
        }

        function checkAuth() {
            const user = getCurrentUser();
            if (!user) {
                window.location.href = '/login?role=muuzaji';
                return false;
            }
            const role = user.role || '';
            if (role !== 'seller' && role !== 'muuzaji') {
                showToast(t('err_no_permission'), 'error');
                window.location.href = '/home';
                return false;
            }
            return true;
        }

        function currentLocale() {
            if (window.DM && typeof window.DM.locale === 'function') {
                const code = window.DM.locale();
                if (code) return code;
            }
            return 'sw';
        }

        function formatCurrency(amount) {
            if (typeof amount !== 'number' || !isFinite(amount)) return '-';
            return 'TZS ' + amount.toLocaleString('en-TZ', { maximumFractionDigits: 0 });
        }

        function loadDrafts() {
            try {
                const raw = JSON.parse(localStorage.getItem('dm_ad_drafts') || '[]');
                return Array.isArray(raw) ? raw : [];
            } catch (e) { return []; }
        }

        function persistDrafts() {
            localStorage.setItem('dm_ad_drafts', JSON.stringify(drafts.slice(0, 20)));
        }

        function selectedProduct() {
            return products.find(function (p) { return String(p.id) === String(selectedId); }) || null;
        }

        async function loadProducts() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            loading = true;
            loadError = null;
            render();
            try {
                const res = await fetch(`${API_BASE_URL}/api/products/my`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                });
                if (res.status === 401) { window.location.href = '/login?role=muuzaji'; return; }
                if (!res.ok) throw new Error('http ' + res.status);
                const data = await res.json();
                products = Array.isArray(data) ? data : (data.products || []);
                if (products.length > 0 && !selectedProduct()) {
                    selectedId = String(products[0].id);
                }
            } catch (error) {
                console.error('Error loading products:', error);
                loadError = t('err_load_failed');
            } finally {
                loading = false;
                render();
            }
        }

        function readControls() {
            const aud = document.getElementById('ctlAudience');
            const obj = document.getElementById('ctlObjective');
            const lang = document.getElementById('ctlLanguage');
            if (aud) controls.audience = aud.value.trim().slice(0, 60);
            if (obj) controls.objective = obj.value.trim().slice(0, 140);
            if (lang) controls.locale = lang.value;
            // Chips are buttons, not inputs - only overwrite from a checked
            // input when one exists so current selections are never reset.
            const ctEl = document.querySelector('input[name=ctlCt]:checked');
            const toneEl = document.querySelector('input[name=ctlTone]:checked');
            const lenEl = document.querySelector('input[name=ctlLen]:checked');
            if (ctEl) controls.content_type = ctEl.value;
            if (toneEl) controls.tone = toneEl.value;
            if (lenEl) controls.length = lenEl.value;
        }

        async function generateCopy() {
            if (generating) return;
            readControls();
            if (!selectedId) { showToast(t('err_pick_product'), 'warning'); return; }

            generating = true;
            result = null;
            render();

            try {
                const token = localStorage.getItem('userToken');
                const res = await fetch(`${API_BASE_URL}/api/ai/ads/generate`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        locale: controls.locale,
                        product_id: String(selectedId),
                        content_type: controls.content_type,
                        tone: controls.tone,
                        length: controls.length,
                        audience: controls.audience,
                        objective: controls.objective
                    })
                });
                if (res.status === 401) { window.location.href = '/login?role=muuzaji'; return; }
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.success) {
                    showToast((data && data.error) || t('err_network'), 'error');
                    return;
                }
                result = data;
                editing = { headline: (data.copy && data.copy.headline) || '', body: (data.copy && data.copy.body) || '' };
                if (!editing.headline && !editing.body) {
                    showToast((data.ai && data.ai.reason) || t('err_no_copy'), 'warning');
                }
            } catch (error) {
                showToast(t('err_network'), 'error');
            } finally {
                generating = false;
                render();
            }
        }

        function buildDescription() {
            const headline = (editing.headline || '').trim();
            const body = (editing.body || '').trim();
            if (headline && body) return headline + '\n\n' + body;
            return headline || body || '';
        }

        function saveDraft() {
            const text = buildDescription();
            if (!text) { showToast(t('publish_no_copy'), 'warning'); return; }
            readControls();
            const product = selectedProduct();
            drafts.unshift({
                id: String(Date.now()),
                ts: Date.now(),
                productId: String(selectedId),
                productName: product ? product.name : '',
                image_url: (result && result.product && result.product.image_url) || '',
                controls: Object.assign({}, controls),
                headline: (editing.headline || '').trim(),
                body: (editing.body || '').trim()
            });
            persistDrafts();
            showToast(t('draft_saved'), 'success');
            render();
        }

        function loadDraft(index) {
            const draft = drafts[Number(index)];
            if (!draft) return;
            selectedId = draft.productId;
            controls = Object.assign({}, controls, draft.controls || {});
            editing = { headline: draft.headline || '', body: draft.body || '' };
            result = {
                product: {
                    id: draft.productId,
                    name: draft.productName,
                    image_url: draft.image_url || ''
                },
                copy: { headline: editing.headline, body: editing.body },
                flags: [],
                ai: { available: true, degraded: false },
                limitations: [],
                generated_at: new Date(draft.ts).toISOString()
            };
            render();
            const el = document.getElementById('previewCard');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function deleteDraft(index) {
            drafts.splice(Number(index), 1);
            persistDrafts();
            render();
        }

        async function publishCopy() {
            if (publishing) return;
            readControls();
            const text = buildDescription();
            if (!text) { showToast(t('publish_no_copy'), 'warning'); return; }

            const product = (result && result.product) || null;
            const imageUrl = product && product.image_url ? product.image_url : '';
            if (!imageUrl) { showToast(t('publish_no_image'), 'warning'); return; }

            if (!window.confirm(t('publish_confirm'))) return;

            publishing = true;
            render();
            try {
                const token = localStorage.getItem('userToken');
                const user = getCurrentUser() || {};
                const res = await fetch(`${API_BASE_URL}/api/matangazo`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        description: text,
                        media_url: imageUrl,
                        media_type: 'image',
                        thumbnail_url: imageUrl,
                        payment_status: 'pending'
                    })
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.data || !data.data.id) {
                    showToast(t('publish_failed'), 'error');
                    return;
                }

                showToast(t('publish_redirect'), 'success');
                try {
                    const payRes = await fetch(`${API_BASE_URL}/api/payments/pesapal/initiate`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            amount: AD_PRICE,
                            description: text.slice(0, 900),
                            matangazo_id: data.data.id,
                            phone: user.phone || '',
                            email: user.email || '',
                            name: user.full_name || user.business_name || ''
                        })
                    });
                    const payData = await payRes.json().catch(function () { return {}; });
                    if (payRes.ok && payData.redirect_url) {
                        window.location.href = payData.redirect_url;
                        return;
                    }
                } catch (payError) {
                    // Fall through to the honest pending message below.
                }
                showToast(t('publish_pending'), 'warning');
            } catch (error) {
                showToast(t('publish_failed'), 'error');
            } finally {
                publishing = false;
                render();
            }
        }

        function renderProductPicker() {
            const rows = products.map(function (p) {
                const out = (Number(p.stock) || 0) <= 0;
                const selected = String(p.id) === String(selectedId);
                return `
                    <label class="product-option ${selected ? 'selected' : ''}">
                        <input type="radio" name="ctlProduct" value="${escapeHtml(p.id)}" ${selected ? 'checked' : ''} onchange="onPickProduct(this.value)">
                        <div class="product-main">
                            <div class="product-name">${escapeHtml(p.name)}<span class="stock-pill ${out ? 'out' : ''}">${out ? escapeHtml(t('label_out_of_stock')) : escapeHtml(t('label_stock')) + ': ' + escapeHtml(String(p.stock))}</span></div>
                            <div class="product-meta">${escapeHtml(t('label_category'))}: ${escapeHtml(p.category || '-')}</div>
                        </div>
                        <div class="product-price">${formatCurrency(Number(p.price))}</div>
                    </label>`;
            }).join('');

            return `
                <div class="card">
                    <div class="card-title">${escapeHtml(t('section_product'))}</div>
                    <div class="section-hint">${escapeHtml(t('product_pick_hint'))}</div>
                    <div class="product-pick">${rows}</div>
                </div>`;
        }

        function renderControls() {
            const ctChips = CONTENT_TYPES.map(function (ct) {
                const on = controls.content_type === ct;
                return `<button type="button" class="chip ${on ? 'selected' : ''}" onclick="onCtl('content_type','${ct}')">${escapeHtml(t('ct_' + ct))}</button>`;
            }).join('');
            const toneChips = TONES.map(function (tone) {
                const on = controls.tone === tone;
                return `<button type="button" class="chip ${on ? 'selected' : ''}" onclick="onCtl('tone','${tone}')">${escapeHtml(t('tone_' + tone))}</button>`;
            }).join('');
            const lenChips = LENGTHS.map(function (len) {
                const on = controls.length === len;
                return `<button type="button" class="chip ${on ? 'selected' : ''}" onclick="onCtl('length','${len}')">${escapeHtml(t('len_' + len))}</button>`;
            }).join('');
            const langOpts = LANGS.map(function (l) {
                return `<option value="${l.code}" ${controls.locale === l.code ? 'selected' : ''}>${escapeHtml(l.label)}</option>`;
            }).join('');

            return `
                <div class="card">
                    <div class="card-title">${escapeHtml(t('section_controls'))}</div>
                    <div class="form-grid">
                        <div class="form-field full">
                            <span class="form-label">${escapeHtml(t('label_content_type'))}</span>
                            <div class="chip-row">${ctChips}</div>
                        </div>
                        <div class="form-field full">
                            <span class="form-label">${escapeHtml(t('label_tone'))}</span>
                            <div class="chip-row">${toneChips}</div>
                        </div>
                        <div class="form-field full">
                            <span class="form-label">${escapeHtml(t('label_length'))}</span>
                            <div class="chip-row">${lenChips}</div>
                        </div>
                        <div class="form-field">
                            <span class="form-label">${escapeHtml(t('label_audience'))}</span>
                            <input class="form-input" id="ctlAudience" maxlength="60" value="${escapeHtml(controls.audience)}" placeholder="${escapeHtml(t('ph_audience'))}">
                        </div>
                        <div class="form-field">
                            <span class="form-label">${escapeHtml(t('label_language'))}</span>
                            <select class="form-select" id="ctlLanguage">${langOpts}</select>
                        </div>
                        <div class="form-field full">
                            <span class="form-label">${escapeHtml(t('label_objective'))}</span>
                            <input class="form-input" id="ctlObjective" maxlength="140" value="${escapeHtml(controls.objective)}" placeholder="${escapeHtml(t('ph_objective'))}">
                        </div>
                    </div>
                    <div class="generate-row">
                        <button class="btn btn-primary" id="generateBtn" ${generating ? 'disabled' : ''} onclick="generateCopy()">
                            <i class="fa-solid ${generating ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'}" aria-hidden="true"></i>
                            ${escapeHtml(generating ? t('btn_generating') : t('btn_generate'))}
                        </button>
                    </div>
                </div>`;
        }

        function renderPreview() {
            if (!result) return '';
            const ai = result.ai || {};
            const copy = result.copy || {};
            const limits = result.limits || { headline: 90, body: 480 };
            const hasCopy = !!(copy.headline || copy.body);
            const flags = (result.flags || []).map(function (f) {
                return `<div class="flag-row"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>${escapeHtml(f.message || f.code)}</span></div>`;
            }).join('');

            let banner;
            if (ai.available && hasCopy) {
                banner = `<div class="banner ok"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> ${escapeHtml(t('ai_ready'))}</div>`;
            } else {
                banner = `<div class="banner warn"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> ${escapeHtml(t('ai_degraded_title'))}${ai.reason ? ' &mdash; ' + escapeHtml(ai.reason) : ''}</div>`;
            }

            const product = result.product || {};
            const media = product.image_url
                ? `<img class="preview-media" src="${escapeHtml(product.image_url)}" alt="${escapeHtml(product.name || '')}">`
                : '';
            const prodMeta = `
                <div class="product-meta" style="margin-bottom:12px;">
                    <b>${escapeHtml(product.name || '')}</b> &middot; ${escapeHtml(t('label_price'))}: ${formatCurrency(Number(product.price))} &middot; ${escapeHtml(t('label_stock'))}: ${escapeHtml(String(product.stock))}
                </div>`;

            const headlineField = (controls.content_type === 'product_description') ? '' : `
                <div class="preview-field">
                    <div class="preview-field-head">
                        <span class="preview-label">${escapeHtml(t('lbl_headline'))}</span>
                        <span class="char-count" id="countHeadline">0/${limits.headline}</span>
                    </div>
                    <textarea class="preview-area headline-area" id="editHeadline" rows="2" placeholder="${escapeHtml(t('ph_headline'))}">${escapeHtml(editing.headline)}</textarea>
                </div>`;
            const bodyField = (controls.content_type === 'headline') ? '' : `
                <div class="preview-field">
                    <div class="preview-field-head">
                        <span class="preview-label">${escapeHtml(t('lbl_body'))}</span>
                        <span class="char-count" id="countBody">0/${limits.body}</span>
                    </div>
                    <textarea class="preview-area" id="editBody" rows="6" placeholder="${escapeHtml(t('ph_body'))}">${escapeHtml(editing.body)}</textarea>
                </div>`;

            const limitations = (result.limitations && result.limitations.length)
                ? `<div class="card"><div class="card-title">${escapeHtml(t('limitations_title'))}</div>
                    <div class="limit-list">${result.limitations.map(function (line) {
                        return `<div class="limit-item"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>${escapeHtml(line)}</span></div>`;
                    }).join('')}</div></div>`
                : '';

            const generated = result.generated_at
                ? `<div class="generated">${escapeHtml(t('generated_at', { date: new Date(result.generated_at).toLocaleString() }))}</div>`
                : '';

            return `
                <div class="card" id="previewCard">
                    <div class="card-title">${escapeHtml(t('section_preview'))}</div>
                    <div class="section-hint">${escapeHtml(t('preview_hint'))}</div>
                    ${banner}
                    ${media}
                    ${prodMeta}
                    ${flags}
                    ${headlineField}
                    ${bodyField}
                    <div class="action-row">
                        <button class="btn btn-primary" ${publishing || !hasCopy ? 'disabled' : ''} onclick="publishCopy()">
                            <i class="fa-solid fa-bullhorn" aria-hidden="true"></i> ${escapeHtml(t('btn_publish'))}
                        </button>
                        <button class="btn btn-accent" ${!hasCopy ? 'disabled' : ''} onclick="saveDraft()">
                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> ${escapeHtml(t('btn_save_draft'))}
                        </button>
                        <button class="btn btn-ghost" ${generating ? 'disabled' : ''} onclick="generateCopy()">
                            <i class="fa-solid fa-rotate" aria-hidden="true"></i> ${escapeHtml(t('btn_regenerate'))}
                        </button>
                    </div>
                    ${generated}
                </div>
                ${limitations}`;
        }

        function renderDrafts() {
            if (drafts.length === 0) {
                return `
                    <div class="card">
                        <div class="card-title">${escapeHtml(t('section_drafts'))}</div>
                        <div class="section-hint">${escapeHtml(t('drafts_empty'))}</div>
                    </div>`;
            }
            const rows = drafts.map(function (d, i) {
                const title = d.headline || d.body || '';
                const when = new Date(d.ts).toLocaleString();
                return `
                    <div class="draft-row">
                        <div class="draft-main">
                            <div class="draft-title">${escapeHtml(title.slice(0, 90))}</div>
                            <div class="draft-meta">${escapeHtml(d.productName || '')} &middot; ${escapeHtml(when)}</div>
                        </div>
                        <div class="draft-actions">
                            <button class="mini-btn load" onclick="loadDraft(${i})">${escapeHtml(t('draft_load'))}</button>
                            <button class="mini-btn del" onclick="deleteDraft(${i})">${escapeHtml(t('draft_delete'))}</button>
                        </div>
                    </div>`;
            }).join('');
            return `
                <div class="card">
                    <div class="card-title">${escapeHtml(t('section_drafts'))}</div>
                    ${rows}
                </div>`;
        }

        // ===================== CONTENT REVIEW (capability #16) =============
        // A moderation AID, not an authority: the findings come from the
        // server's deterministic checks (with an optional advisory AI note on
        // flagged claims), and every approve / reject / request-edits press is
        // a HUMAN decision that the server re-verifies and records.
        const CR = {
            title: 'Ukaguzi wa Maudhui',
            subtitle: 'Hakiki matangazo na bidhaa zako kabla ya kuchapisha',
            btn_check: 'Kagua Sasa',
            checking: 'Inakagua...',
            err_check: 'Imeshindwa kukagua. Jaribu tena.',
            stat_checked: 'Vilivyokaguliwa',
            stat_violations: 'Kukiukwa',
            stat_concerns: 'Mambo ya kuangalia',
            stat_clean: 'Safi',
            sev_violation: 'Kukiukwa',
            sev_concern: 'Hakiki',
            tab_ads: 'Matangazo ({count})',
            tab_products: 'Bidhaa ({count})',
            verdict_label: 'Maoni ya AI',
            verdict_supported: 'Imeungwa mkono na maandishi',
            verdict_unsupported: 'Haiungwi mkono',
            verdict_uncertain: 'Haijajulikana',
            no_items: 'Hakuna matangazo wala bidhaa za kukagua.',
            no_findings: 'Hakuna tatizo lolote lilolopatikana.',
            dec_pick: 'Chagua uamuzi: Idhini, Kataa au Omba Marekebisho.',
            dec_approve: 'Idhini',
            dec_reject: 'Kataa',
            dec_edit: 'Omba Marekebisho',
            dec_note_placeholder: 'Sababu (inahitajika kukataa)',
            dec_done: 'Uamuzi umerekodiwa.',
            reviewed_confirm: 'Nimesoma mabao yote',
            active_label: 'Inaonyeshwa',
            inactive_label: 'Imefichwa',
            advisory_note: 'AI ni msaada tu; kila uamuzi ni wako na umerekodiwa.'
        };

        function tc(key, params) {
            if (window.DM && typeof window.DM.t === 'function') {
                const full = 'content_review.' + key;
                const hit = window.DM.t(full, params);
                if (hit !== full) return hit;
            }
            let value = CR[key] !== undefined ? CR[key] : key;
            if (params) {
                for (const k in params) value = String(value).split('{' + k + '}').join(String(params[k]));
            }
            return value;
        }

        function reviewToast(message) {
            const el = document.createElement('div');
            el.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#2c3e50;color:white;padding:11px 20px;border-radius:24px;font-size:13px;font-weight:600;z-index:3000;max-width:86%;text-align:center;box-shadow:0 6px 20px rgba(0,0,0,0.2);';
            el.textContent = message;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3200);
        }

        let reviewState = { data: null, loading: false, error: false, tab: 'ads', decisions: {}, notes: {}, reviewed: {} };

        function reviewLocale() {
            if (window.DM && typeof window.DM.locale === 'function') return window.DM.locale();
            return document.documentElement.getAttribute('data-dm-locale') || 'sw';
        }

        async function runReviewCheck() {
            const token = localStorage.getItem('userToken');
            if (!token || reviewState.loading) return;
            reviewState.loading = true;
            reviewState.error = false;
            fillReviewPanel();
            try {
                const res = await fetch(`${API_BASE_URL}/api/ai/reviews/check`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
                    body: JSON.stringify({ target: 'all', locale: reviewLocale() })
                });
                if (!res.ok) throw new Error('review failed');
                const json = await res.json();
                if (!json || !json.success) throw new Error('review failed');
                reviewState.data = json;
            } catch (e) {
                reviewState.data = null;
                reviewState.error = true;
            } finally {
                reviewState.loading = false;
                fillReviewPanel();
            }
        }

        async function submitReviewDecision(type, id) {
            const decision = reviewState.decisions[id];
            if (!decision) { reviewToast(tc('dec_pick')); return; }
            const token = localStorage.getItem('userToken');
            if (!token) { reviewToast(tc('err_check')); return; }
            try {
                const res = await fetch(`${API_BASE_URL}/api/ai/reviews/decision`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
                    body: JSON.stringify({
                        target_type: type,
                        target_id: id,
                        decision: decision,
                        note: (reviewState.notes[id] || '').trim(),
                        reviewed: !!reviewState.reviewed[id]
                    })
                });
                const json = await res.json().catch(() => null);
                if (!res.ok) { reviewToast((json && json.error) || tc('err_check')); return; }
                const item = ((reviewState.data && reviewState.data.items) || []).find(i => i.id === id);
                if (item && json.target) item.is_active = json.target.is_active;
                reviewState.decisions[id] = '';
                reviewToast(tc('dec_done'));
                fillReviewPanel();
            } catch (e) {
                reviewToast(tc('err_check'));
            }
        }

        function reviewItemCard(item) {
            const activeBadge = item.is_active
                ? `<span style="background:#27ae6018;color:#27ae60;padding:2px 9px;border-radius:10px;font-size:10.5px;font-weight:700;">${tc('active_label')}</span>`
                : `<span style="background:#95a5a622;color:#7f8c8d;padding:2px 9px;border-radius:10px;font-size:10.5px;font-weight:700;">${tc('inactive_label')}</span>`;

            const findings = (item.findings || []).map(f => {
                const violation = f.severity === 'violation';
                const sevChip = `<span style="background:${violation ? '#e74c3c18' : '#f39c1218'};color:${violation ? '#e74c3c' : '#b9770e'};padding:2px 9px;border-radius:10px;font-size:10.5px;font-weight:700;">${violation ? tc('sev_violation') : tc('sev_concern')}</span>`;
                const excerpt = f.excerpt ? `<div style="margin-top:4px;font-size:11px;color:#7f8c8d;background:#f8f9fa;border-left:3px solid #dfe6e9;padding:4px 8px;border-radius:0 8px 8px 0;">${escapeHtml(f.excerpt)}</div>` : '';
                const aiPill = f.ai ? `<div style="margin-top:4px;font-size:11px;color:#667eea;">${tc('verdict_label')}: ${tc('verdict_' + f.ai.verdict)}${f.ai.reason ? ' — ' + escapeHtml(f.ai.reason) : ''}</div>` : '';
                return `<div style="padding:8px 0;border-top:1px dashed #ecf0f1;">
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">${sevChip}<span style="font-size:12.5px;color:#2c3e50;">${escapeHtml(f.message)}</span></div>
                    ${excerpt}${aiPill}</div>`;
            }).join('');

            const decision = reviewState.decisions[item.id] || '';
            const chip = (val, label, color) => `<div onclick="setReviewDecision('${item.id}','${val}')" style="flex:1;text-align:center;padding:8px 4px;border-radius:10px;border:1.5px solid ${decision === val ? color : '#ecf0f1'};background:${decision === val ? color + '14' : 'white'};color:${decision === val ? color : '#7f8c8d'};font-size:11.5px;font-weight:700;cursor:pointer;">${label}</div>`;
            const submitLabel = decision ? tc({ approve: 'dec_approve', reject: 'dec_reject', request_edit: 'dec_edit' }[decision]) : tc('dec_title');

            return `<div style="background:white;border-radius:14px;padding:14px;margin-bottom:10px;border:1px solid #f1f3f4;">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                    <div style="font-weight:700;font-size:14px;color:#2c3e50;">${escapeHtml(item.title || '-')}</div>
                    ${activeBadge}
                </div>
                ${item.subtitle ? `<div style="font-size:11.5px;color:#7f8c8d;margin-top:2px;">${escapeHtml(item.subtitle)}</div>` : ''}
                <div style="display:flex;gap:6px;margin-top:6px;">
                    <span style="background:#e74c3c12;color:#e74c3c;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">${tc('stat_violations')}: ${item.violations}</span>
                    <span style="background:#f39c1215;color:#b9770e;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">${tc('stat_concerns')}: ${item.concerns}</span>
                </div>
                ${findings || `<div style="margin-top:8px;font-size:12px;color:#27ae60;">✓ ${tc('no_findings')}</div>`}
                <div style="margin-top:10px;display:flex;gap:8px;">
                    ${chip('approve', tc('dec_approve'), '#27ae60')}
                    ${chip('reject', tc('dec_reject'), '#e74c3c')}
                    ${chip('request_edit', tc('dec_edit'), '#f39c12')}
                </div>
                <input type="text" maxlength="300" placeholder="${escapeHtml(tc('dec_note_placeholder'))}" value="${escapeHtml(reviewState.notes[item.id] || '')}" oninput="setReviewNote('${item.id}', this.value)" style="width:100%;margin-top:8px;padding:9px 12px;border:1px solid #ecf0f1;border-radius:10px;font-size:12.5px;box-sizing:border-box;">
                <label style="display:flex;gap:8px;align-items:center;margin-top:8px;font-size:12px;color:#5d6d7e;cursor:pointer;">
                    <input type="checkbox" ${reviewState.reviewed[item.id] ? 'checked' : ''} onchange="setReviewReviewed('${item.id}', this.checked)">
                    ${tc('reviewed_confirm')}
                </label>
                <div onclick="submitReviewDecision('${item.type}','${item.id}')" style="margin-top:10px;text-align:center;padding:10px;border-radius:12px;background:#667eea;color:white;font-weight:700;font-size:13px;cursor:pointer;">${submitLabel}</div>
            </div>`;
        }

        function fillReviewPanel() {
            const host = document.getElementById('reviewPanel');
            if (!host) return;
            const d = reviewState.data;
            const ads = d ? (d.items || []).filter(i => i.type === 'ad') : [];
            const products = d ? (d.items || []).filter(i => i.type === 'product') : [];
            const items = reviewState.tab === 'ads' ? ads : products;

            const tab = (val, label) => `<div onclick="setReviewTab('${val}')" style="padding:7px 14px;border-radius:20px;font-size:12px;font-weight:700;cursor:pointer;background:${reviewState.tab === val ? '#667eea' : '#f4f6f7'};color:${reviewState.tab === val ? 'white' : '#5d6d7e'};">${label}</div>`;

            let body;
            if (reviewState.loading) {
                body = `<div style="text-align:center;padding:16px;color:#7f8c8d;">${tc('checking')}</div>`;
            } else if (reviewState.error || !d) {
                body = `<div style="text-align:center;padding:14px;">
                    <div style="color:#e74c3c;font-size:13px;">${escapeHtml(tc('err_check'))}</div>
                    <div onclick="runReviewCheck()" style="display:inline-block;margin-top:8px;padding:7px 18px;border-radius:20px;background:#667eea;color:white;font-size:12px;font-weight:600;cursor:pointer;">${tc('btn_check')}</div>
                </div>`;
            } else if ((d.items || []).length === 0) {
                body = `<div style="text-align:center;padding:16px;color:#7f8c8d;">${tc('no_items')}</div>`;
            } else {
                const s = d.stats || {};
                const stat = (value, label, color) => `<div style="flex:1;min-width:76px;background:#f8f9fa;border-radius:12px;padding:8px;text-align:center;"><div style="font-weight:800;color:${color};">${value}</div><div style="font-size:10px;color:#7f8c8d;">${label}</div></div>`;
                body = `
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        ${stat(s.items_checked || 0, tc('stat_checked'), '#2c3e50')}
                        ${stat(s.violations || 0, tc('stat_violations'), '#e74c3c')}
                        ${stat(s.concerns || 0, tc('stat_concerns'), '#b9770e')}
                        ${stat(s.clean || 0, tc('stat_clean'), '#27ae60')}
                    </div>
                    <div style="display:flex;gap:8px;margin-top:10px;">${tab('ads', tc('tab_ads', { count: ads.length }))}${tab('products', tc('tab_products', { count: products.length }))}</div>
                    <div style="margin-top:10px;">${items.length ? items.map(reviewItemCard).join('') : `<div style="text-align:center;padding:14px;color:#7f8c8d;">${tc('no_items')}</div>`}</div>
                    <div style="margin-top:8px;font-size:10.5px;color:#95a5a6;">${tc('advisory_note')}</div>`;
            }

            host.innerHTML = `
                <div style="background:white;border-radius:16px;padding:16px;margin-bottom:16px;border-left:4px solid #667eea;box-shadow:0 2px 10px rgba(0,0,0,0.04);">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="color:#667eea;font-size:15px;">✨</span><div style="font-weight:800;font-size:15px;color:#2c3e50;">${tc('title')}</div>
                        <div onclick="runReviewCheck()" style="margin-left:auto;padding:7px 16px;border-radius:20px;background:#667eea;color:white;font-size:12px;font-weight:700;cursor:pointer;user-select:none;">${reviewState.loading ? tc('checking') : tc('btn_check')}</div>
                    </div>
                    <div style="font-size:11.5px;color:#7f8c8d;margin-top:2px;">${escapeHtml(tc('subtitle'))}</div>
                    <div style="margin-top:10px;">${body}</div>
                </div>`;
        }

        window.runReviewCheck = runReviewCheck;
        window.submitReviewDecision = submitReviewDecision;
        window.setReviewTab = (tab) => { reviewState.tab = tab; fillReviewPanel(); };
        window.setReviewDecision = (id, decision) => { reviewState.decisions[id] = decision; fillReviewPanel(); };
        window.setReviewNote = (id, value) => { reviewState.notes[id] = value; };
        window.setReviewReviewed = (id, checked) => { reviewState.reviewed[id] = !!checked; fillReviewPanel(); };

        function render() {
            const container = document.getElementById('studioContent');
            if (!container) return;

            let body;
            if (loading) {
                body = `<div class="loading-container"><div class="loading-spinner dm-sc"></div><div class="loading-text">${escapeHtml(t('loading'))}</div></div>`;
            } else if (loadError && products.length === 0) {
                body = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i></div>
                        <div class="empty-title">${escapeHtml(loadError)}</div>
                        <button class="retry-btn" onclick="loadProducts()">${escapeHtml(t('btn_retry'))}</button>
                    </div>`;
            } else if (products.length === 0) {
                body = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></div>
                        <div class="empty-title">${escapeHtml(t('no_products_title'))}</div>
                        <div class="empty-text">${escapeHtml(t('no_products_text'))}</div>
                        <button class="retry-btn" onclick="window.location.href='uza'">${escapeHtml(t('btn_go_sell'))}</button>
                    </div>`;
            } else {
                body = renderProductPicker() + renderControls() + renderPreview() + renderDrafts();
            }

            container.innerHTML = `
                <div class="header">
                    <div class="header-top">
                        <a href="huduma-nyingine" class="back-btn"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                        <div class="title">${escapeHtml(t('title'))}</div>
                    </div>
                    <div class="product-meta" style="margin-top:8px;">${escapeHtml(t('subtitle'))}</div>
                </div>
                <div id="reviewPanel"></div>
                ${body}`;

            wireEditor();
            if (!reviewState.data && !reviewState.loading) runReviewCheck();
            else fillReviewPanel();
        }

        function wireEditor() {
            const headline = document.getElementById('editHeadline');
            const body = document.getElementById('editBody');
            const limits = (result && result.limits) || { headline: 90, body: 480 };

            function bind(el, field, counterId, cap) {
                if (!el) return;
                updateCounter(counterId, el.value.length, cap);
                el.addEventListener('input', function () {
                    editing[field] = el.value;
                    updateCounter(counterId, el.value.length, cap);
                });
            }
            bind(headline, 'headline', 'countHeadline', limits.headline);
            bind(body, 'body', 'countBody', limits.body);
        }

        function updateCounter(id, len, cap) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = len + '/' + cap;
            el.classList.toggle('over', len > cap);
        }

        function onPickProduct(value) {
            selectedId = String(value);
            render();
        }

        function onCtl(key, value) {
            controls[key] = value;
            // Keep typed values before the targeted re-render.
            readControls();
            controls[key] = value;
            render();
        }

        function setupSidebar() {
            const toggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            if (toggle && sidebar) {
                toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
            }
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function () {
                    localStorage.clear();
                    window.location.href = '/login?role=muuzaji';
                });
            }
            document.addEventListener('click', function (e) {
                if (window.innerWidth <= 768) {
                    const bar = document.getElementById('sidebar');
                    const btn = document.getElementById('mobileMenuToggle');
                    if (bar && bar.classList.contains('open') && !bar.contains(e.target) && btn && !btn.contains(e.target)) {
                        bar.classList.remove('open');
                    }
                }
            });
        }

        window.generateCopy = generateCopy;
        window.loadProducts = loadProducts;
        window.onPickProduct = onPickProduct;
        window.onCtl = onCtl;
        window.saveDraft = saveDraft;
        window.loadDraft = loadDraft;
        window.deleteDraft = deleteDraft;
        window.publishCopy = publishCopy;

        async function init() {
            if (!checkAuth()) return;
            controls.locale = currentLocale();
            updateSidebarUser();
            setupSidebar();
            await loadProducts();
            render();
        }

        // Re-run in the visitor's language when the floating widget changes it.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(function () {
                controls.locale = currentLocale();
                updateSidebarUser();
                render();
            });
        }

        init();
    </script>
</body>
</html>
@endverbatim
