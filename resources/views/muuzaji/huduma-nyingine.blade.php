@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_huduma.page_title">Huduma Nyingine - Dukamkononi Muuzaji</title>
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
        .nav-icon { font-size: 20px; width: 26px; text-align: center; }
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

        .hub-header { background: white; border-radius: 18px; padding: 22px 24px; margin-bottom: 18px; }
        .hub-title-main { font-size: 22px; font-weight: 800; color: #2c3e50; }
        .hub-subtitle { margin-top: 8px; font-size: 14px; color: #7f8c8d; line-height: 1.55; }

        .hub-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .hub-card {
            display: flex; align-items: center; gap: 14px; padding: 18px; border-radius: 18px;
            text-decoration: none; border: 1.5px solid transparent;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .hub-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.08); }
        .hub-icon {
            width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center;
            justify-content: center; font-size: 26px; color: #fff; flex-shrink: 0;
        }
        .hub-info { flex: 1; }
        .hub-title { font-size: 16px; font-weight: 700; }
        .hub-desc { margin-top: 4px; font-size: 13px; color: #5d6d7e; line-height: 1.5; }
        .hub-arrow { font-size: 26px; font-weight: 700; }

        .hub-price { background: #eaf4fc; border-color: #2980b9; }
        .hub-price .hub-icon { background: #2980b9; }
        .hub-price .hub-title, .hub-price .hub-arrow { color: #1f618d; }

        .hub-restock { background: #fdf1e3; border-color: #e67e22; }
        .hub-restock .hub-icon { background: #e67e22; }
        .hub-restock .hub-title, .hub-restock .hub-arrow { color: #b9770e; }

        .hub-ask { background: #f5ecfa; border-color: #8e44ad; }
        .hub-ask .hub-icon { background: #8e44ad; }
        .hub-ask .hub-title, .hub-ask .hub-arrow { color: #6c3483; }

        .hub-health { background: #e6f7f3; border-color: #16a085; }
        .hub-health .hub-icon { background: #16a085; }
        .hub-health .hub-title, .hub-health .hub-arrow { color: #117a65; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 16px; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
            .hub-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.toast')
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
                <a href="huduma-nyingine" class="nav-item active"><div class="nav-icon">🧩</div><span class="nav-label" data-i18n="muuzaji_huduma.nav_other">Huduma Nyingine</span></a>
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
                <div class="hub-header">
                    <div class="hub-title-main" data-i18n="muuzaji_huduma.title">Huduma Nyingine</div>
                    <div class="hub-subtitle" data-i18n="muuzaji_huduma.subtitle">Zana za ziada zinazotumia AI kukusaidia kuendesha biashara yako vizuri zaidi.</div>
                </div>
                <div class="hub-grid">
                    <a href="ushauri-bei" class="hub-card hub-price">
                        <div class="hub-icon">💹</div>
                        <div class="hub-info">
                            <div class="hub-title" data-i18n="muuzaji_huduma.svc_price_title">Ushauri wa Bei</div>
                            <div class="hub-desc" data-i18n="muuzaji_huduma.svc_price_desc">Pata ushauri wa bei na faida kwa bidhaa zako.</div>
                        </div>
                        <div class="hub-arrow">›</div>
                    </a>
                    <a href="mpango-stock" class="hub-card hub-restock">
                        <div class="hub-icon">📦</div>
                        <div class="hub-info">
                            <div class="hub-title" data-i18n="muuzaji_huduma.svc_restock_title">Mpango wa Kuagiza</div>
                            <div class="hub-desc" data-i18n="muuzaji_huduma.svc_restock_desc">Jua bidhaa gani za kuagiza na kiasi kinachopendekezwa.</div>
                        </div>
                        <div class="hub-arrow">›</div>
                    </a>
                    <a href="uliza-biashara" class="hub-card hub-ask">
                        <div class="hub-icon">💬</div>
                        <div class="hub-info">
                            <div class="hub-title" data-i18n="muuzaji_huduma.svc_ask_title">Uliza Biashara</div>
                            <div class="hub-desc" data-i18n="muuzaji_huduma.svc_ask_desc">Uliza maswali kuhusu mauzo na upate majibu kwa data yako.</div>
                        </div>
                        <div class="hub-arrow">›</div>
                    </a>
                    <a href="afya-biashara" class="hub-card hub-health">
                        <div class="hub-icon">🩺</div>
                        <div class="hub-info">
                            <div class="hub-title" data-i18n="muuzaji_huduma.svc_health_title">Afya ya Biashara</div>
                            <div class="hub-desc" data-i18n="muuzaji_huduma.svc_health_desc">Fuatilia afya ya biashara yako kwa alama na mapendekezo.</div>
                        </div>
                        <div class="hub-arrow">›</div>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // ============================================
        // HUDUMA NYINGINE (Other Services) - Muuzaji Web
        // Hub page: each card links to a dedicated capability page.
        // ============================================

        // Swahili fallback for script-built strings. Mirrors the sw catalog of
        // the muuzaji_huduma section in locales.json.
        const SW = {
            nav_seller: 'Muuzaji',
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la Muuzaji.'
        };

        function t(key, params) {
            const full = key.indexOf('muuzaji_huduma.') === 0 ? key : 'muuzaji_huduma.' + key;
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

        function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
        }

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(function () {
                updateSidebarUser();
            });
        }

        init();
    </script>
</body>
</html>
@endverbatim
