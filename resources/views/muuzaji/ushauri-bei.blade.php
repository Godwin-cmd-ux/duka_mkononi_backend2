@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_ushauri_bei.page_title">Ushauri wa Bei - Dukamkononi Muuzaji</title>
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
        .refresh-btn {
            background: #e8f8f0; width: 40px; height: 40px; border-radius: 20px; display: flex;
            align-items: center; justify-content: center; cursor: pointer; border: none; font-size: 18px; color: #2ecc71;
        }

        .banner { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 14px; margin-bottom: 14px; font-size: 13px; font-weight: 600; }
        .banner.ok { background: #e8f8f0; color: #1e8449; }
        .banner.warn { background: #fef5e7; color: #b9770e; }

        .card { background: white; border-radius: 18px; padding: 20px; margin-bottom: 16px; }
        .card-title { font-size: 16px; font-weight: 700; color: #2c3e50; margin-bottom: 14px; }
        .headline-card { background: #f4f9ff; }
        .headline { font-size: 15px; font-weight: 600; color: #2c3e50; line-height: 1.5; }

        .stats-grid { display: flex; justify-content: space-around; gap: 8px; }
        .stat-box { text-align: center; flex: 1; }
        .stat-number { font-size: 22px; font-weight: 700; color: #2c3e50; }
        .stat-label { font-size: 12px; color: #7f8c8d; margin-top: 2px; }
        .divider { height: 1px; background: #ecf0f1; margin: 14px 0; }
        .money-row { display: flex; justify-content: space-between; padding: 4px 0; }
        .money-label { color: #7f8c8d; font-size: 13px; }
        .money-value { font-weight: 600; font-size: 13px; color: #2c3e50; }
        .muted { font-size: 12px; color: #95a5a6; margin-top: 10px; }

        .section-title { font-size: 16px; font-weight: 700; color: #2c3e50; margin: 4px 0 12px; }
        .product-card { background: white; border-radius: 16px; padding: 16px; margin-bottom: 12px; }
        .product-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .product-name { font-size: 15px; font-weight: 700; color: #2c3e50; flex: 1; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; margin-top: 10px; }
        .meta-item { font-size: 12px; color: #5d6d7e; }
        .meta-item b { color: #2c3e50; }
        .suggest-box { margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: #f0f7ff; }
        .suggest-row { display: flex; justify-content: space-between; padding: 2px 0; }
        .suggest-label { font-size: 12px; color: #2980b9; }
        .suggest-value { font-size: 13px; font-weight: 700; color: #1f618d; }
        .ai-note { margin-top: 10px; padding-left: 10px; border-left: 3px solid #3498db; }
        .ai-note-title { font-size: 12px; font-weight: 700; color: #2c3e50; }
        .ai-note-text { font-size: 12px; color: #5d6d7e; margin-top: 3px; line-height: 1.5; }

        .list-row { display: flex; gap: 8px; margin-bottom: 6px; }
        .list-bullet { font-weight: 700; }
        .list-text { flex: 1; font-size: 12px; color: #5d6d7e; line-height: 1.55; }

        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; }
        .empty-icon { font-size: 48px; margin-bottom: 14px; color: #bdc3c7; }
        .empty-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; color: #2c3e50; }
        .empty-text { font-size: 14px; color: #7f8c8d; }
        .retry-btn { margin-top: 16px; background: #2ecc71; color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; }
        .generated { text-align: center; font-size: 11px; color: #bdc3c7; margin-top: 10px; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 16px; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
            .meta-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
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
            <div class="page-container" id="advisorContent">
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <div class="loading-text" data-i18n="muuzaji_ushauri_bei.loading">Inachambua bei na faida...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // ============================================
        // USHAURI WA BEI (Price & Margin Advisor) - Muuzaji Web
        // Backed by POST /api/ai/price/suggest
        // ============================================

        const API_BASE_URL = '';

        // Swahili fallback for script-built strings. Mirrors the sw catalog of
        // the muuzaji_ushauri_bei section in locales.json.
        const SW = {
            loading: 'Inachambua bei na faida...',
            nav_seller: 'Muuzaji',
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la Muuzaji.',
            err_load_failed: 'Imeshindwa kupakia ushauri wa bei.',
            err_network: 'Hitilafu ya mtandao. Angalia muunganisho wako.',
            err_auth: 'Tafadhali ingia tena.',
            title: 'Ushauri wa Bei na Faida',
            empty_title: 'Hakuna bidhaa',
            empty_text: 'Ongeza bidhaa kwenye duka lako ili kupata ushauri wa bei.',
            summary_title: 'Muhtasari wa biashara',
            stat_products: 'Bidhaa',
            stat_at_risk: 'Zinahitaji uangalizi',
            stat_margin: 'Faida wastani',
            stat_inventory: 'Thamani ya bidhaa (gharama)',
            stat_revenue: 'Mauzo (kipindi)',
            stat_gross: 'Faida ghafi (kipindi)',
            window_label: 'Kipindi: siku {days}',
            generated_at: 'Imetengenezwa: {date}',
            ai_ready: 'Ushauri wa AI',
            ai_degraded_title: 'Ushauri wa AI haupatikani',
            products_title: 'Bidhaa zinazohitaji uangalizi',
            label_buying: 'Bei ya kununua',
            label_selling: 'Bei ya kuuzia',
            label_margin: 'Faida',
            label_suggested: 'Bei inayopendekezwa',
            label_floor: 'Bei ya chini kabisa',
            label_stock: 'Idadi iliyopo',
            status_loss: 'Hasara',
            status_zero: 'Faida sifuri',
            status_thin: 'Faida ndogo',
            status_healthy: 'Nzuri',
            status_missing_cost: 'Bei ya kununua haipo',
            status_missing_selling: 'Bei ya kuuzia haipo',
            watchouts_title: 'Mambo ya kuangalia',
            limitations_title: 'Mipaka ya uchambuzi',
            margin_pct: 'Faida {percent}%',
            at_risk_badge: 'Uangalizi',
            btn_retry: 'Jaribu Tena'
        };

        function t(key, params) {
            const full = key.indexOf('muuzaji_ushauri_bei.') === 0 ? key : 'muuzaji_ushauri_bei.' + key;
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

        const STATUS_KEY = {
            LOSS: 'status_loss', ZERO_MARGIN: 'status_zero', THIN: 'status_thin',
            HEALTHY: 'status_healthy', MISSING_COST: 'status_missing_cost',
            MISSING_SELLING_PRICE: 'status_missing_selling'
        };
        const STATUS_COLOR = {
            LOSS: '#e74c3c', ZERO_MARGIN: '#e74c3c', THIN: '#f39c12',
            HEALTHY: '#27ae60', MISSING_COST: '#95a5a6', MISSING_SELLING_PRICE: '#95a5a6'
        };
        const SEVERITY_COLOR = { high: '#e74c3c', medium: '#f39c12', low: '#3498db' };

        let advice = null;
        let loading = true;
        let loadError = null;

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
            return String(str).replace(/[&<>"]/g, function (m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m];
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
            const currency = (advice && advice.currency) || 'TZS';
            return currency + ' ' + amount.toLocaleString('en-TZ', { maximumFractionDigits: 0 });
        }

        function formatPercent(value) {
            return (typeof value === 'number' && isFinite(value)) ? value.toFixed(1) + '%' : '-';
        }

        async function loadAdvice(isRefresh) {
            const token = localStorage.getItem('userToken');
            if (!token) return;

            if (isRefresh) {
                const spinner = document.querySelector('.refresh-btn i');
                if (spinner) spinner.classList.add('fa-spin');
            } else {
                loading = true;
                loadError = null;
                render();
            }

            try {
                const res = await fetch(`${API_BASE_URL}/api/ai/price/suggest`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ locale: currentLocale(), window_days: 30 })
                });
                if (!res.ok) {
                    loadError = t('err_load_failed');
                } else {
                    advice = await res.json();
                    loadError = null;
                }
            } catch (error) {
                console.error('Error loading price advice:', error);
                loadError = t('err_network');
            } finally {
                loading = false;
                render();
            }
        }

        function renderBanner() {
            const ai = advice.ai || {};
            if (ai.available && !ai.degraded) {
                return `<div class="banner ok"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> ${escapeHtml(t('ai_ready'))}</div>`;
            }
            return `<div class="banner warn"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> ${escapeHtml(t('ai_degraded_title'))}${ai.reason ? ' &mdash; ' + escapeHtml(ai.reason) : ''}</div>`;
        }

        function renderSummary() {
            const s = advice.summary;
            return `
                <div class="card">
                    <div class="card-title">${escapeHtml(t('summary_title'))}</div>
                    <div class="stats-grid">
                        <div class="stat-box"><div class="stat-number">${s.product_count}</div><div class="stat-label">${escapeHtml(t('stat_products'))}</div></div>
                        <div class="stat-box"><div class="stat-number" style="color:#e74c3c;">${s.at_risk_count}</div><div class="stat-label">${escapeHtml(t('stat_at_risk'))}</div></div>
                        <div class="stat-box"><div class="stat-number" style="color:#27ae60;">${formatPercent(s.weighted_margin_pct != null ? s.weighted_margin_pct : s.margin_pct_window)}</div><div class="stat-label">${escapeHtml(t('stat_margin'))}</div></div>
                    </div>
                    <div class="divider"></div>
                    <div class="money-row"><span class="money-label">${escapeHtml(t('stat_inventory'))}</span><span class="money-value">${formatCurrency(s.inventory_value_cost)}</span></div>
                    <div class="money-row"><span class="money-label">${escapeHtml(t('stat_revenue'))}</span><span class="money-value">${formatCurrency(s.revenue_window)}</span></div>
                    <div class="money-row"><span class="money-label">${escapeHtml(t('stat_gross'))}</span><span class="money-value" style="color:${s.gross_margin_window < 0 ? '#e74c3c' : '#27ae60'};">${formatCurrency(s.gross_margin_window)}</span></div>
                    <div class="muted">${escapeHtml(t('window_label', { days: s.window_days }))}</div>
                </div>`;
        }

        function renderProduct(product) {
            const statusKey = STATUS_KEY[product.status] || 'status_healthy';
            const statusColor = STATUS_COLOR[product.status] || '#95a5a6';
            const marginText = (typeof product.margin_pct === 'number')
                ? t('margin_pct', { percent: product.margin_pct.toFixed(1) })
                : formatCurrency(product.margin);

            let suggestBox = '';
            if (product.suggested_price !== null && product.suggested_price !== undefined) {
                suggestBox = `
                    <div class="suggest-box">
                        <div class="suggest-row"><span class="suggest-label">${escapeHtml(t('label_suggested'))}</span><span class="suggest-value">${formatCurrency(product.suggested_price)}</span></div>
                        <div class="suggest-row"><span class="suggest-label">${escapeHtml(t('label_floor'))}</span><span class="suggest-value">${formatCurrency(product.floor_price)}</span></div>
                    </div>`;
            }

            let aiNote = '';
            if (product.ai && (product.ai.title || product.ai.action)) {
                const color = SEVERITY_COLOR[product.ai.severity] || '#3498db';
                aiNote = `
                    <div class="ai-note" style="border-left-color:${color};">
                        <div class="ai-note-title"><i class="fa-solid fa-wand-magic-sparkles" style="font-size:11px;" aria-hidden="true"></i> ${escapeHtml(product.ai.title)}</div>
                        <div class="ai-note-text">${escapeHtml(product.ai.action)}</div>
                    </div>`;
            }

            return `
                <div class="product-card">
                    <div class="product-head">
                        <div class="product-name">${escapeHtml(product.name)}</div>
                        <div class="badge" style="background:${statusColor}20;color:${statusColor};">${escapeHtml(t(statusKey))}</div>
                    </div>
                    <div class="meta-grid">
                        <div class="meta-item">${escapeHtml(t('label_buying'))}: <b>${formatCurrency(product.buying_price)}</b></div>
                        <div class="meta-item">${escapeHtml(t('label_selling'))}: <b>${formatCurrency(product.selling_price)}</b></div>
                        <div class="meta-item">${escapeHtml(t('label_margin'))}: <b>${escapeHtml(marginText)}</b></div>
                        <div class="meta-item">${escapeHtml(t('label_stock'))}: <b>${product.stock}</b></div>
                    </div>
                    ${suggestBox}
                    ${aiNote}
                </div>`;
        }

        function renderList(title, items, color) {
            if (!items || items.length === 0) return '';
            return `
                <div style="margin-bottom:16px;">
                    <div class="section-title" style="color:${color};">${escapeHtml(title)}</div>
                    ${items.map(function (item) {
                        return `<div class="list-row"><span class="list-bullet" style="color:${color};">&bull;</span><span class="list-text">${escapeHtml(item)}</span></div>`;
                    }).join('')}
                </div>`;
        }

        function render() {
            const container = document.getElementById('advisorContent');
            if (!container) return;

            if (loading) {
                container.innerHTML = `
                    <div class="loading-container">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">${escapeHtml(t('loading'))}</div>
                    </div>`;
                return;
            }

            let body = '';
            if (loadError && !advice) {
                body = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i></div>
                        <div class="empty-title">${escapeHtml(loadError)}</div>
                        <button class="retry-btn" onclick="loadAdvice(true)">${escapeHtml(t('btn_retry'))}</button>
                    </div>`;
            } else if (!advice || !advice.summary || !advice.products || advice.products.length === 0) {
                body = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-tags" aria-hidden="true"></i></div>
                        <div class="empty-title">${escapeHtml(t('empty_title'))}</div>
                        <div class="empty-text">${escapeHtml(t('empty_text'))}</div>
                    </div>`;
            } else {
                const needsAttention = advice.products.filter(function (p) { return p.status !== 'HEALTHY'; });
                const shown = needsAttention.length > 0 ? needsAttention : advice.products;
                const headline = (advice.ai && advice.ai.headline)
                    ? `<div class="card headline-card"><div class="headline">${escapeHtml(advice.ai.headline)}</div></div>`
                    : '';
                body = renderBanner()
                    + headline
                    + renderSummary()
                    + `<div class="section-title">${escapeHtml(t('products_title'))}</div>`
                    + shown.map(renderProduct).join('')
                    + renderList(t('watchouts_title'), advice.watchouts, '#e67e22')
                    + renderList(t('limitations_title'), advice.limitations, '#95a5a6')
                    + (advice.generated_at ? `<div class="generated">${escapeHtml(t('generated_at', { date: new Date(advice.generated_at).toLocaleString() }))}</div>` : '');
            }

            container.innerHTML = `
                <div class="header">
                    <div class="header-top">
                        <a href="profaili" class="back-btn"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                        <div class="title">${escapeHtml(t('title'))}</div>
                        <button class="refresh-btn" onclick="loadAdvice(true)"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
                    </div>
                </div>
                ${body}`;
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

        window.loadAdvice = loadAdvice;

        async function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
            await loadAdvice(false);
        }

        // Re-run in the visitor's language when the floating widget changes it.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(function () {
                loadAdvice(true);
                updateSidebarUser();
            });
        }

        init();
    </script>
</body>
</html>
@endverbatim
