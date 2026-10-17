@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_msaidizi.page_title">Msaidizi wa Biashara - Dukamkononi Muuzaji</title>
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

        .header { background: white; padding: 16px 20px; border-radius: 16px; margin-bottom: 16px; }
        .header-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .title { font-size: 20px; font-weight: 700; color: #2c3e50; }
        .subtitle { font-size: 13px; color: #7f8c8d; margin-top: 4px; }
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border-radius: 10px; border: 1px solid transparent; font-weight: 600; font-size: 13px; cursor: pointer; min-height: 40px; background: white; color: #5d6d7e; }
        .btn:hover { background: #e8f8f0; color: #2ecc71; }
        .btn-danger:hover { background: #fdeaea; color: #e74c3c; }

        .chat-card { background: white; border-radius: 18px; padding: 18px; margin-bottom: 16px; min-height: 320px; display: flex; flex-direction: column; }
        .messages { flex: 1; display: flex; flex-direction: column; gap: 14px; }
        .msg { max-width: 88%; padding: 12px 14px; border-radius: 16px; font-size: 14.5px; line-height: 1.55; }
        .msg.user { align-self: flex-end; background: #2ecc71; color: white; border-bottom-right-radius: 4px; }
        .msg.assistant { align-self: flex-start; background: #f4f9ff; color: #2c3e50; border-bottom-left-radius: 4px; }
        .msg-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
        .badge { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; background: #e8f8f0; color: #1e8449; padding: 3px 9px; border-radius: 20px; }
        .period { font-size: 11px; color: #7f8c8d; }
        .msg-text { white-space: pre-wrap; word-break: break-word; }
        .next-steps { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
        .next-step { display: flex; gap: 8px; font-size: 13px; color: #1e8449; }
        .next-step i { margin-top: 3px; }
        .quick-actions { margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap; }
        .quick-chip { display: inline-flex; align-items: center; gap: 6px; background: white; border: 1px solid #c8f0da; color: #27ae60; border-radius: 20px; padding: 7px 12px; font-size: 12.5px; font-weight: 600; text-decoration: none; }
        .quick-chip:hover { background: #e8f8f0; }

        .highlights { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .highlight { background: white; border: 1px solid #ecf0f1; border-radius: 10px; padding: 7px 10px; font-size: 12px; color: #5d6d7e; }
        .highlight b { color: #2c3e50; }

        .tool-card { background: white; border: 1px solid #ecf0f1; border-radius: 14px; padding: 14px; margin-top: 10px; }
        .tool-card-title { font-size: 13px; font-weight: 700; color: #2c3e50; margin-bottom: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { text-align: left; padding: 7px 9px; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
        table.data th { color: #2c3e50; font-weight: 700; border-bottom: 1px solid #ecf0f1; }
        table.data td { color: #5d6d7e; }

        .bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .bar-label { width: 120px; font-size: 12px; color: #5d6d7e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bar-track { flex: 1; height: 12px; background: #ecf0f1; border-radius: 6px; overflow: hidden; }
        .bar-fill { height: 12px; background: #2ecc71; border-radius: 6px; }
        .bar-value { width: 120px; font-size: 12px; color: #2c3e50; text-align: right; }

        .meta-list { display: flex; flex-direction: column; gap: 5px; margin-top: 8px; }
        .meta-line { font-size: 12.5px; color: #7f8c8d; display: flex; gap: 7px; flex-wrap: wrap; align-items: center; }
        .tag { background: #f4f9ff; color: #2980b9; border-radius: 8px; padding: 2px 8px; font-size: 11.5px; font-weight: 600; }
        .banner { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-radius: 12px; margin-top: 10px; font-size: 12.5px; font-weight: 600; }
        .banner.warn { background: #fef5e7; color: #b9770e; }

        .ask-bar { display: flex; gap: 10px; margin-top: 14px; }
        .ask-input { flex: 1; padding: 14px 16px; border: 1px solid #dfe4e6; border-radius: 14px; font-size: 15px; font-family: inherit; }
        .ask-btn { background: #2ecc71; color: white; border: none; padding: 0 22px; border-radius: 14px; font-weight: 700; cursor: pointer; font-size: 15px; }
        .ask-btn:disabled { opacity: 0.6; cursor: not-allowed; }

        .empty-state { text-align: center; padding: 44px 20px; }
        .empty-icon { font-size: 46px; color: #bdc3c7; margin-bottom: 14px; }
        .empty-title { font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 8px; }
        .empty-text { font-size: 14px; color: #7f8c8d; max-width: 460px; margin: 0 auto; }

        .loading-container { text-align: center; padding: 26px 10px 10px; }
        .loading-spinner { width: 42px; height: 42px; border: 3px solid #e0e0e0; border-top-color: #2ecc71; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 12px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { color: #7f8c8d; font-size: 13.5px; }

        .chip { display: inline-flex; align-items: center; gap: 8px; background: white; border: 1px solid #ecf0f1; border-radius: 20px; padding: 8px 14px; margin: 0 8px 8px 0; cursor: pointer; font-size: 13px; color: #2c3e50; }
        .chip:hover { background: #e8f8f0; border-color: #c8f0da; }
        .examples-title { font-size: 13px; font-weight: 600; color: #7f8c8d; margin-bottom: 10px; }

        .overlay { position: fixed; inset: 0; background: rgba(44,62,80,0.45); display: none; align-items: center; justify-content: center; z-index: 300; padding: 20px; }
        .overlay.open { display: flex; }
        .modal { background: white; border-radius: 18px; width: 100%; max-width: 520px; max-height: 82vh; display: flex; flex-direction: column; overflow: hidden; }
        .modal-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; border-bottom: 1px solid #ecf0f1; }
        .modal-title { font-size: 16px; font-weight: 700; color: #2c3e50; }
        .modal-body { padding: 12px 18px 18px; overflow-y: auto; }
        .conv-row { display: flex; align-items: center; gap: 10px; padding: 12px 10px; border-radius: 12px; cursor: pointer; }
        .conv-row:hover { background: #f4f9ff; }
        .conv-main { flex: 1; min-width: 0; }
        .conv-title { font-size: 14px; font-weight: 600; color: #2c3e50; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .conv-meta { font-size: 11.5px; color: #95a5a6; margin-top: 2px; }
        .conv-del { background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 15px; padding: 8px; }
        .modal-foot { display: flex; justify-content: space-between; gap: 10px; padding: 12px 18px; border-top: 1px solid #ecf0f1; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 16px; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
            .msg { max-width: 96%; }
            .bar-label { width: 80px; }
            .bar-value { width: 90px; }
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
                <a href="profaili" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_matumizi.nav_profile">Profaili</span></a>
                <a href="mauzo" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-money-bill-1" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_matumizi.nav_sales">Mauzo</span></a>
                <a href="matumizi" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_matumizi.nav_expenses">Matumizi</span></a>
                <a href="uza" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_matumizi.nav_sell">Uza</span></a>
                <a href="orders" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-box" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_orders.nav_orders">Oda</span></a>
                <a href="huduma-nyingine" class="nav-item active"><div class="nav-icon">🧭</div><span class="nav-label" data-i18n="muuzaji_huduma.nav_other">Huduma Nyingine</span></a>
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
            <div class="page-container">
                <div class="header">
                    <div class="header-row">
                        <div>
                            <div class="title" data-i18n="muuzaji_msaidizi.title">Msaidizi wa Biashara</div>
                            <div class="subtitle" data-i18n="muuzaji_msaidizi.subtitle">Uliza kuhusu mauzo, stock, faida, matumizi na afya ya biashara yako.</div>
                        </div>
                        <div class="header-actions">
                            <button class="btn" id="btnHistory"><i class="fa-solid fa-clock-rotate-left"></i> <span data-i18n="muuzaji_msaidizi.btn_history">Mazungumzo</span></button>
                            <button class="btn" id="btnNew"><i class="fa-solid fa-plus"></i> <span data-i18n="muuzaji_msaidizi.btn_new">Mpya</span></button>
                            <button class="btn btn-danger" id="btnDelete"><i class="fa-solid fa-trash"></i> <span data-i18n="muuzaji_msaidizi.delete_conversation">Futa</span></button>
                        </div>
                    </div>
                </div>

                <div class="chat-card">
                    <div class="messages" id="messages"></div>
                    <div class="ask-bar">
                        <input type="text" id="questionInput" class="ask-input" maxlength="400" data-i18n="muuzaji_msaidizi.placeholder" data-i18n-attr="placeholder" placeholder="Uliza swali kuhusu biashara yako...">
                        <button class="ask-btn" id="askBtn"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="overlay" id="historyOverlay">
        <div class="modal">
            <div class="modal-head">
                <div class="modal-title" data-i18n="muuzaji_msaidizi.history_title">Mazungumzo yako</div>
                <button class="btn" id="btnCloseHistory"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="historyList"></div>
            <div class="modal-foot">
                <button class="btn btn-danger" id="btnDeleteAll"><i class="fa-solid fa-trash"></i> <span data-i18n="muuzaji_msaidizi.delete_conversation">Futa</span></button>
                <button class="btn" id="btnHistoryNew"><i class="fa-solid fa-plus"></i> <span data-i18n="muuzaji_msaidizi.btn_new">Mpya</span></button>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // MSAIDIZI WA BIASHARA (Seller Copilot Q&A) - Muuzaji Web
        // POST /api/ai/copilot/chat + GET/DELETE /api/ai/copilot/conversations
        // ============================================

        const API_BASE_URL = '';

        const SW = {
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la Muuzaji.',
            err_load_failed: 'Imeshindwa kupata jibu. Jaribu tena.',
            err_network: 'Hitilafu ya mtandao. Angalia muunganisho wako.',
            err_auth: 'Tafadhali ingia tena.',
            err_question: 'Tafadhali andika swali kwanza.',
            loading: 'Inapitia data yako...',
            empty_title: 'Uliza swali lako la kwanza',
            empty_hint: 'Msaidizi anatumia data halisi ya duka lako pekee - mauzo, stock, faida, matumizi na afya ya biashara.',
            ai_label: 'Msaidizi wa AI',
            sources_title: 'Vyanzo',
            quick_actions_title: 'Endelea hapa',
            next_steps_title: 'Hatua zinazofuata',
            degraded_title: 'AI haipatikani sasa hivi',
            history_title: 'Mazungumzo yako',
            no_history: 'Hakuna mazungumzo bado.',
            btn_cancel: 'Ghairi',
            btn_confirm: 'Futa',
            deleted_msg: 'Mazungumzo yamefutwa.',
            delete_confirm: 'Una uhakika unataka kufuta mazungumzo haya?',
            examples_title: 'Jaribu kuuliza',
            src_sales: 'Mauzo',
            src_products: 'Bidhaa',
            src_expenses: 'Matumizi',
            src_customers: 'Wateja'
        };

        function t(key, params) {
            const full = key.indexOf('.') > -1 ? key : 'muuzaji_msaidizi.' + key;
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

        const EXAMPLE_KEYS = ['example_1', 'example_2', 'example_3', 'example_4', 'example_5', 'example_6'];

        const COLUMN_KEY = {
            name: 'muuzaji_uliza.col_name',
            units: 'muuzaji_uliza.col_units',
            revenue: 'muuzaji_uliza.col_revenue',
            profit: 'muuzaji_uliza.col_profit',
            category: 'muuzaji_uliza.col_category',
            amount: 'muuzaji_uliza.col_amount',
            total_purchases: 'muuzaji_uliza.col_total_purchases',
            purchases_count: 'muuzaji_uliza.col_purchases',
            stock: 'muuzaji_restock.label_stock',
            days: 'muuzaji_restock.label_days_left',
            proposed: 'muuzaji_restock.label_proposed',
            component: 'muuzaji_msaidizi.col_component',
            score: 'muuzaji_msaidizi.col_score'
        };

        const SOURCE_KEY = {
            sales: 'src_sales', sale_items: 'src_sales', products: 'src_products',
            office_expenses: 'src_expenses', customers: 'src_customers'
        };

        const MONEY_COLUMNS = ['amount', 'revenue', 'profit', 'total_purchases'];

        let messages = [];          // {role, content, tool?, toolResult?, nextSteps?, actions?, ai?}
        let conversationId = null;
        let conversationTitle = '';
        let currency = 'TZS';
        let sending = false;
        let loadError = null;

        function getCurrentUser() {
            const token = localStorage.getItem('userToken');
            const userData = localStorage.getItem('userData');
            if (token && userData) { try { return JSON.parse(userData); } catch (e) { return null; } }
            return null;
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"']/g, function (m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
            });
        }

        function updateSidebarUser() {
            const user = getCurrentUser();
            if (!user) return;
            const displayName = user.full_name || user.business_name || (user.email ? user.email.split('@')[0] : 'Muuzaji');
            const nameEl = document.getElementById('userName');
            if (nameEl) nameEl.textContent = displayName;
            const avatarEl = document.getElementById('userAvatar');
            if (avatarEl) avatarEl.textContent = displayName.charAt(0).toUpperCase();
        }

        function checkAuth() {
            const user = getCurrentUser();
            if (!user) { window.location.href = '/login?role=muuzaji'; return false; }
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

        function formatMoney(amount, currency) {
            if (typeof amount !== 'number' || !isFinite(amount)) return '-';
            return (currency || 'TZS') + ' ' + amount.toLocaleString('en-TZ', { maximumFractionDigits: 0 });
        }

        function formatCell(column, value) {
            if (value === null || value === undefined || value === '') return '-';
            if (MONEY_COLUMNS.indexOf(column) > -1) {
                return typeof value === 'number' ? formatMoney(value, currency) : String(value);
            }
            return String(value);
        }

        /* ---------------- rendering ---------------- */

        function renderEmpty() {
            return '<div class="empty-state">' +
                '<div class="empty-icon"><i class="fa-solid fa-comments"></i></div>' +
                '<div class="empty-title">' + escapeHtml(t('empty_title')) + '</div>' +
                '<div class="empty-text">' + escapeHtml(t('empty_hint')) + '</div>' +
                '<div style="margin-top:16px;"><div class="examples-title">' + escapeHtml(t('examples_title')) + '</div>' +
                EXAMPLE_KEYS.map(function (k) {
                    return '<span class="chip" data-example="' + escapeHtml(t('muuzaji_uliza.' + k)) + '"><i class="fa-solid fa-comment-dots"></i> ' + escapeHtml(t('muuzaji_uliza.' + k)) + '</span>';
                }).join('') + '</div></div>';
        }

        function renderTable(tr) {
            if (!tr || !tr.table || !tr.table.rows || tr.table.rows.length === 0) return '';
            const head = tr.table.columns.map(function (c) {
                return '<th>' + escapeHtml(t(COLUMN_KEY[c] || 'muuzaji_uliza.col_name')) + '</th>';
            }).join('');
            const body = tr.table.rows.map(function (row) {
                return '<tr>' + row.map(function (cell, i) {
                    return '<td>' + escapeHtml(formatCell(tr.table.columns[i], cell)) + '</td>';
                }).join('') + '</tr>';
            }).join('');
            return '<div class="tool-card"><div class="tool-card-title"><i class="fa-solid fa-table"></i> ' +
                escapeHtml(t('muuzaji_uliza.table_title')) + '</div><table class="data"><thead><tr>' + head +
                '</tr></thead><tbody>' + body + '</tbody></table></div>';
        }

        function renderChart(tr) {
            if (!tr || !tr.chart || !tr.chart.values || tr.chart.values.length === 0) return '';
            const max = Math.max.apply(null, tr.chart.values.map(function (v) { return Math.abs(v); }).concat([1]));
            const rows = tr.chart.labels.map(function (label, i) {
                const value = tr.chart.values[i] || 0;
                const pct = Math.max(4, Math.round((Math.abs(value) / max) * 100));
                const shown = (tr.chart.metric === 'expenses' || tr.chart.metric === 'revenue') ? formatMoney(value, currency) : String(value);
                return '<div class="bar-row"><div class="bar-label" title="' + escapeHtml(label) + '">' + escapeHtml(label) +
                    '</div><div class="bar-track"><div class="bar-fill" style="width:' + pct + '%;"></div></div>' +
                    '<div class="bar-value">' + escapeHtml(shown) + '</div></div>';
            }).join('');
            return '<div class="tool-card"><div class="tool-card-title"><i class="fa-solid fa-chart-bar"></i> ' +
                escapeHtml(t('muuzaji_uliza.chart_title')) + '</div>' + rows + '</div>';
        }

        function renderAssistant(m) {
            let html = '<div class="msg assistant">';
            if (m.toolLabel || m.periodLabel) {
                html += '<div class="msg-meta">';
                if (m.toolLabel) html += '<span class="badge"><i class="fa-solid fa-wand-magic-sparkles"></i> ' + escapeHtml(m.toolLabel) + '</span>';
                if (m.periodLabel) html += '<span class="period">' + escapeHtml(t('muuzaji_uliza.range_label')) + ': ' + escapeHtml(m.periodLabel) + '</span>';
                html += '</div>';
            }
            html += '<div class="msg-text">' + escapeHtml(m.content) + '</div>';

            const tr = m.toolResult;
            if (tr) {
                if (tr.highlights && tr.highlights.length) {
                    html += '<div class="highlights">' + tr.highlights.map(function (h) {
                        let value = h.value;
                        if (h.kind === 'percent') value = value + '%';
                        else if (typeof value === 'number') value = value.toLocaleString('en-TZ');
                        return '<span class="highlight">' + escapeHtml(h.label || '') + ': <b>' + escapeHtml(String(value)) + '</b></span>';
                    }).join('') + '</div>';
                }
                html += renderTable(tr);
                html += renderChart(tr);
                if (tr.sources && tr.sources.length) {
                    const tags = [];
                    tr.sources.forEach(function (s) {
                        const key = SOURCE_KEY[s];
                        const label = key ? t(key) : s;
                        if (tags.indexOf(label) === -1) tags.push(label);
                    });
                    html += '<div class="meta-list"><div class="meta-line"><b>' + escapeHtml(t('sources_title')) + ':</b> ' +
                        tags.map(function (x) { return '<span class="tag">' + escapeHtml(x) + '</span>'; }).join('') + '</div></div>';
                }
                if (tr.limitations && tr.limitations.length) {
                    html += '<div class="meta-list">' + tr.limitations.map(function (x) {
                        return '<div class="meta-line"><i class="fa-solid fa-circle-info"></i> ' + escapeHtml(x) + '</div>';
                    }).join('') + '</div>';
                }
            }

            if (m.nextSteps && m.nextSteps.length) {
                html += '<div class="next-steps"><div class="examples-title">' + escapeHtml(t('next_steps_title')) + '</div>' +
                    m.nextSteps.map(function (s) {
                        return '<div class="next-step"><i class="fa-solid fa-arrow-right"></i><span>' + escapeHtml(s) + '</span></div>';
                    }).join('') + '</div>';
            }

            if (m.actions && m.actions.length) {
                html += '<div class="quick-actions">' + m.actions.map(function (a) {
                    return '<a class="quick-chip" href="' + escapeHtml(a.href) + '"><i class="fa-solid fa-arrow-up-right-from-square"></i> ' +
                        escapeHtml(a.label || '') + '</a>';
                }).join('') + '</div>';
            }

            if (m.ai && m.ai.degraded) {
                html += '<div class="banner warn"><i class="fa-solid fa-circle-info"></i> ' +
                    escapeHtml(t('degraded_title')) + (m.ai.reason ? ' &mdash; ' + escapeHtml(m.ai.reason) : '') + '</div>';
            }

            return html + '</div>';
        }

        function render() {
            const container = document.getElementById('messages');
            if (!container) return;

            let html = '';
            if (messages.length === 0 && !sending && !loadError) {
                html = renderEmpty();
            } else {
                html = '<div class="messages">';
                messages.forEach(function (m) {
                    if (m.role === 'user') {
                        html += '<div class="msg user">' + escapeHtml(m.content) + '</div>';
                    } else {
                        html += renderAssistant(m);
                    }
                });
                if (sending) {
                    html += '<div class="loading-container"><div class="loading-spinner dm-sc"></div><div class="loading-text">' +
                        escapeHtml(t('loading')) + '</div></div>';
                }
                if (loadError) {
                    html += '<div class="banner warn"><i class="fa-solid fa-triangle-exclamation"></i> ' + escapeHtml(loadError) + '</div>';
                }
                html += '</div>';
            }
            container.innerHTML = html;
        }

        /* ---------------- API ---------------- */

        async function ask(rawQuestion) {
            const input = document.getElementById('questionInput');
            const question = (rawQuestion !== undefined ? rawQuestion : (input ? input.value : '')).trim();
            if (question === '') { showToast(t('err_question'), 'warning'); return; }

            const token = localStorage.getItem('userToken');
            if (!token) { showToast(t('err_auth'), 'error'); return; }

            if (input) input.value = '';
            messages.push({ role: 'user', content: question });
            sending = true;
            loadError = null;
            render();

            try {
                const body = { question: question, locale: currentLocale() };
                if (conversationId) body.conversation_id = conversationId;

                const res = await fetch(`${API_BASE_URL}/api/ai/copilot/chat`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                const payload = await res.json().catch(function () { return null; });
                if (!res.ok || !payload) {
                    loadError = (payload && payload.error) ? payload.error : t('err_load_failed');
                    render();
                    return;
                }

                conversationId = payload.conversation.id;
                conversationTitle = payload.conversation.title || question;
                currency = payload.currency || 'TZS';
                try { localStorage.setItem('dmCopilotId', conversationId); } catch (e) { /* private mode */ }

                const tr = payload.tool_result;
                let periodLabel = '';
                if (tr && tr.period) {
                    if (tr.period.from) periodLabel = tr.period.from + ' – ' + tr.period.to;
                    else if (tr.period.window_days) periodLabel = tr.period.window_days + ' d';
                }

                messages.push({
                    role: 'assistant',
                    content: payload.message.content,
                    toolLabel: payload.message.tool_label,
                    periodLabel: periodLabel,
                    toolResult: tr,
                    nextSteps: payload.next_steps || [],
                    actions: payload.quick_actions || [],
                    ai: payload.ai || null
                });
            } catch (error) {
                console.error('Error asking copilot:', error);
                loadError = t('err_network');
            } finally {
                sending = false;
                render();
            }
        }

        async function loadHistory() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            const listEl = document.getElementById('historyList');
            if (listEl) listEl.innerHTML = '<div class="loading-container"><div class="loading-spinner dm-sc"></div></div>';

            try {
                const res = await fetch(`${API_BASE_URL}/api/ai/copilot/conversations`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const payload = await res.json().catch(function () { return null; });
                const rows = (payload && payload.conversations) ? payload.conversations : [];
                if (!listEl) return;
                if (rows.length === 0) {
                    listEl.innerHTML = '<div class="empty-text" style="padding:14px 4px;">' + escapeHtml(t('no_history')) + '</div>';
                    return;
                }
                listEl.innerHTML = rows.map(function (row) {
                    return '<div class="conv-row" data-id="' + escapeHtml(row.id) + '">' +
                        '<div class="conv-main"><div class="conv-title">' + escapeHtml(row.title || row.id.slice(0, 8)) + '</div>' +
                        '<div class="conv-meta">' + escapeHtml((row.updated_at || '').slice(0, 10)) + ' · ' + row.message_count + '</div></div>' +
                        '<button class="conv-del" data-del="' + escapeHtml(row.id) + '" title="' + escapeHtml(t('delete_conversation')) + '"><i class="fa-solid fa-trash"></i></button>' +
                        '</div>';
                }).join('');
            } catch (error) {
                if (listEl) listEl.innerHTML = '<div class="empty-text" style="padding:14px 4px;">' + escapeHtml(t('err_network')) + '</div>';
            }
        }

        async function openConversation(id) {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/ai/copilot/conversations/${encodeURIComponent(id)}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const payload = await res.json().catch(function () { return null; });
                if (!res.ok || !payload) { showToast(t('err_load_failed'), 'error'); return; }

                conversationId = id;
                messages = (payload.messages || []).map(function (row) {
                    return { role: row.role, content: row.content, toolLabel: row.tool_label || '' };
                });
                closeHistory();
                render();
            } catch (error) {
                showToast(t('err_network'), 'error');
            }
        }

        async function deleteConversation(id) {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                await fetch(`${API_BASE_URL}/api/ai/copilot/conversations/${encodeURIComponent(id)}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (conversationId === id) {
                    conversationId = null;
                    conversationTitle = '';
                    messages = [];
                    try { localStorage.removeItem('dmCopilotId'); } catch (e) { /* ignore */ }
                }
                showToast(t('deleted_msg'), 'success');
                render();
                loadHistory();
            } catch (error) {
                showToast(t('err_network'), 'error');
            }
        }

        async function deleteAllConversations() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                await fetch(`${API_BASE_URL}/api/ai/copilot/conversations`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                conversationId = null;
                conversationTitle = '';
                messages = [];
                try { localStorage.removeItem('dmCopilotId'); } catch (e) { /* ignore */ }
                showToast(t('deleted_msg'), 'success');
                render();
                loadHistory();
            } catch (error) {
                showToast(t('err_network'), 'error');
            }
        }

        function newConversation() {
            conversationId = null;
            conversationTitle = '';
            messages = [];
            try { localStorage.removeItem('dmCopilotId'); } catch (e) { /* ignore */ }
            closeHistory();
            render();
        }

        function openHistory() {
            const overlay = document.getElementById('historyOverlay');
            if (overlay) overlay.classList.add('open');
            loadHistory();
        }

        function closeHistory() {
            const overlay = document.getElementById('historyOverlay');
            if (overlay) overlay.classList.remove('open');
        }

        /* ---------------- setup ---------------- */

        function setupSidebar() {
            const toggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            if (toggle && sidebar) toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) logoutBtn.addEventListener('click', function () { localStorage.clear(); window.location.href = '/login?role=muuzaji'; });
            document.addEventListener('click', function (e) {
                if (window.innerWidth <= 768) {
                    const bar = document.getElementById('sidebar');
                    const btn = document.getElementById('mobileMenuToggle');
                    if (bar && bar.classList.contains('open') && !bar.contains(e.target) && btn && !btn.contains(e.target)) bar.classList.remove('open');
                }
            });
        }

        function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();

            try {
                const savedId = localStorage.getItem('dmCopilotId');
                if (savedId) conversationId = savedId;
            } catch (e) { /* ignore */ }
            render();

            document.getElementById('askBtn').addEventListener('click', function () { ask(); });
            document.getElementById('questionInput').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') ask();
            });
            document.getElementById('btnNew').addEventListener('click', newConversation);
            document.getElementById('btnHistory').addEventListener('click', openHistory);
            document.getElementById('btnCloseHistory').addEventListener('click', closeHistory);
            document.getElementById('btnHistoryNew').addEventListener('click', newConversation);
            document.getElementById('btnDeleteAll').addEventListener('click', function () {
                if (window.confirm(t('delete_confirm'))) deleteAllConversations();
            });
            document.getElementById('btnDelete').addEventListener('click', function () {
                if (!conversationId) { newConversation(); return; }
                if (window.confirm(t('delete_confirm'))) deleteConversation(conversationId);
            });

            document.getElementById('historyOverlay').addEventListener('click', function (e) {
                if (e.target === this) closeHistory();
                const del = e.target.closest ? e.target.closest('[data-del]') : null;
                if (del) { e.stopPropagation(); if (window.confirm(t('delete_confirm'))) deleteConversation(del.getAttribute('data-del')); return; }
                const row = e.target.closest ? e.target.closest('.conv-row') : null;
                if (row) openConversation(row.getAttribute('data-id'));
            });

            document.addEventListener('click', function (e) {
                const chip = e.target.closest ? e.target.closest('.chip[data-example]') : null;
                if (chip) ask(chip.getAttribute('data-example'));
            });
        }

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(function () { render(); updateSidebarUser(); });
        }

        init();
    </script>
</body>
</html>
@endverbatim
