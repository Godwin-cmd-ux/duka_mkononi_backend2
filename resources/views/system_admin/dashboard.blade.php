@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="system_admin_dashboard.page_title">Dashbodi - DukaMkononi System Admin</title>
@endverbatim
    <link rel="stylesheet" href="/css/themes.css?v={{ filemtime(public_path('css/themes.css')) }}">
@verbatim
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        /* Page content */
        .page-content { max-width: 1400px; margin: 0 auto; width: 100%; }

        /* Header */
        .dashboard-header {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .header-title { font-size: 24px; font-weight: 800; color: #2c3e50; }
        .header-subtitle { font-size: 13px; color: #7f8c8d; margin-top: 4px; }
        .header-actions { display: flex; gap: 12px; }
        .header-btn {
            width: 40px; height: 40px; background: #f8f9fa;
            border-radius: 20px; display: flex; align-items: center;
            justify-content: center; cursor: pointer;
        }

        /* Cards */
        .section-card {
            background: white; border-radius: 20px; padding: 24px;
            margin-bottom: 24px;
        }
        .section-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 20px; flex-wrap: wrap;
            gap: 12px;
        }
        .section-title { font-size: 18px; font-weight: 700; color: #2c3e50; }
        .section-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 24px; padding: 1px 8px; margin-left: 8px;
            border-radius: 999px; background: #eef2ff; color: #4338ca;
            font-size: 11px; font-weight: 800; vertical-align: middle;
        }
        .section-subtitle { font-size: 12.5px; color: #7f8c8d; margin-top: 6px; }

        /* Stats Grid */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px; margin-bottom: 24px;
        }
        .stat-card {
            background: #f8f9fa; border-radius: 16px; padding: 20px;
            border-left: 4px solid #3498db;
        }
        .stat-icon { width: 40px; height: 40px; border-radius: 20px; background: #3498db20; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .stat-value { font-size: 28px; font-weight: 800; margin-bottom: 4px; }
        .stat-label { font-size: 13px; color: #7f8c8d; }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px; padding: 24px; margin-bottom: 24px;
            color: white;
        }
        .welcome-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; }

        /* Users in the last 24 hours */
        .today-list { display: flex; flex-direction: column; }
        .today-row {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 2px; border-bottom: 1px solid #f1f5f9;
        }
        .today-row:last-child { border-bottom: 0; }
        .today-avatar {
            width: 38px; height: 38px; flex: 0 0 38px; border-radius: 50%;
            background: #e0e7ff; color: #4338ca; display: grid;
            place-items: center; font-weight: 800; font-size: 14px;
            overflow: hidden; position: relative;
        }
        .today-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .today-presence {
            position: absolute; right: -1px; bottom: -1px;
            width: 11px; height: 11px; border-radius: 50%;
            background: #cbd5e1; border: 2px solid white;
        }
        .today-presence.on { background: #10b981; }
        .today-meta { min-width: 0; flex: 1; }
        .today-name {
            display: flex; align-items: center; gap: 7px;
            font-size: 13px; font-weight: 700; color: #0f172a;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .today-email {
            font-size: 11.5px; color: #64748b; margin-top: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .today-side {
            display: flex; align-items: center; gap: 10px; flex: 0 0 auto;
        }
        .today-last-seen { font-size: 11.5px; color: #64748b; white-space: nowrap; }
        .today-online {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 2px 8px; border-radius: 999px;
            background: #dcfce7; border: 1px solid #86efac; color: #047857;
            font-size: 10px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .04em;
        }
        .today-online::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%;
            background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.18);
        }
        .view-more-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 10px;
            border: 1px solid #e2e8f0; background: white; color: #334155;
            font-family: inherit; font-size: 12.5px; font-weight: 700;
            cursor: pointer;
        }
        .view-more-btn:hover { background: #f8fafc; }

        .empty-state { text-align: center; padding: 40px; color: #95a5a6; }

        .loading-spinner {
            width: 50px; height: 50px; border: 3px solid #e0e0e0;
            border-top-color: #3498db; border-radius: 50%;
            animation: spin 0.8s linear infinite; margin: 40px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        }
        @media (max-width: 640px) {
            .today-side .badge { display: none; }
        }
    </style>
</head>
<body class="sa-shell">
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])
@include('partials.toast')
@include('system_admin.partials.sidebar', ['saActive' => 'dashboard'])
@verbatim
    <div class="page-content" id="dashboardContent">
        <div class="loading-spinner dm-sc"></div>
        <div style="text-align: center; color: #7f8c8d;" data-i18n="system_admin_dashboard.loading">Inapakua dashbodi...</div>
    </div>

    <script>
        const API_BASE_URL = '';

        // Swahili fallback used when the language widget is not present (and
        // for script-built strings). Values mirror the sw catalog of the
        // system_admin_dashboard section in locales.json.
        const SW = {
            page_title: 'Dashbodi - DukaMkononi System Admin',
            loading: 'Inapakua dashbodi...',
            header_title: 'System Admin Dashboard',
            header_subtitle: 'Data Halisi kutoka Database',
            icon_refresh: '⟳',
            icon_home: '🏠',
            icon_logout: '🚪',
            welcome_title: '👋 Karibu, System Admin',
            welcome_subtitle: 'Unayo uwezo wa kusimamia mfumo mzima wa DukaMkononi',
            stat_total_users: 'Jumla ya Watumiaji',
            using: 'wanaotumia',
            stat_online_now: 'Mtandaoni Sasa',
            connected: 'wameungana',
            stat_today_revenue: 'Mapato ya Leo',
            activities: 'shughuli',
            stat_pending: 'Wanasubiri',
            need_approval: 'Wanahitaji idhini',
            section_system_status: '📊 Hali ya Mfumo',
            all_people: 'Watu wote',
            sellers: 'Wauzaji',
            customers: 'Wateja',
            administrators: 'Wasimamizi',
            today_users_title: 'Users in the last 24 hours',
            today_users_sub: 'Watumiaji waliotembelea akaunti zao katika saa 24 zilizopita.',
            today_users_empty: 'Hakuna mtumiaji aliyetembelea akaunti yake katika saa 24 zilizopita.',
            today_users_online: 'Online',
            today_last_seen: 'Mwisho:',
            view_more: 'View more',
            show_less: 'Show less',
            never: 'Hajawahi',
            time_mins_ago: 'Dakika {mins} zilizopita',
            time_hours_ago: 'Saa {hours} zilizopita',
            time_days_ago: 'Siku {days} zilizopita',
            not_available: 'N/A',
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la System Admin.',
            confirm_logout: 'Una uhakika unataka kutoka?',
        };

        function t(key, params) {
            const full = key.indexOf('system_admin_dashboard.') === 0 ? key : 'system_admin_dashboard.' + key;
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

        // Date locale follows the selected language.
        const DATE_LOCALES = { sw: 'sw-TZ', en: 'en-GB', fr: 'fr-FR', hi: 'hi-IN', es: 'es-ES', ur: 'ur-PK', de: 'de-DE', zh: 'zh-CN' };
        function dateLocale() {
            const code = (window.DM && typeof window.DM.locale === 'function' && window.DM.locale()) || document.documentElement.getAttribute('data-dm-locale') || 'sw';
            return DATE_LOCALES[code] || 'sw-TZ';
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"]/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));
        }

        function formatCurrency(amount) { return `TZS ${(amount || 0).toLocaleString()}`; }

        function formatTimeAgo(dateStr) {
            if (!dateStr) return t('never');
            try {
                const date = new Date(dateStr);
                if (isNaN(date.getTime())) return t('never');
                const diffMins = Math.floor((new Date() - date) / 60000);
                if (diffMins < 1) return 'Sasa hivi';
                if (diffMins < 60) return t('time_mins_ago', { mins: diffMins });
                const diffHours = Math.floor(diffMins / 60);
                if (diffHours < 24) return t('time_hours_ago', { hours: diffHours });
                return t('time_days_ago', { days: Math.floor(diffHours / 24) });
            } catch { return t('never'); }
        }

        const ROLE_COLORS = { admin: '#9b59b6', super_admin: '#7c3aed', system_admin: '#7c3aed', seller: '#2563eb', client: '#059669', customer: '#059669' };
        function roleColor(role) { return ROLE_COLORS[role] || '#64748b'; }

        function getCurrentUser() {
            const token = localStorage.getItem('userToken');
            const userData = localStorage.getItem('userData');
            if (token && userData) {
                try { return JSON.parse(userData); } catch (e) { return null; }
            }
            return null;
        }

        function checkSystemAdmin() {
            const user = getCurrentUser();
            if (!user) { window.location.href = '/login?role=msimamizi'; return false; }
            // Super admin is a database role, never a hardcoded email;
            // 'system_admin' is the legacy spelling.
            if (!['super_admin', 'system_admin'].includes(user.role || '')) {
                showToast(t('err_no_permission'), 'error');
                window.location.href = '/home';
                return false;
            }
            return true;
        }

        let users = [];
        let onlineIds = new Set();
        let todayExpanded = false;
        let loading = true;
        let systemStats = {
            totalUsers: 0, totalAdmins: 0, totalSellers: 0,
            totalClients: 0, activeUsers: 0, pendingUsers: 0,
            todayActivities: 0, todayRevenue: 0,
            onlineUsers: 0, connectedNow: 0,
        };

        async function fetchUsers() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/users?scope=platform`, {
                    headers: { 'Authorization': `Bearer ${token}` },
                });
                if (res.ok) {
                    const data = await res.json();
                    users = data.users || (Array.isArray(data) ? data : []);
                }
            } catch (e) { console.error(e); }
        }

        async function fetchSystemStats() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/stats`, {
                    headers: { 'Authorization': `Bearer ${token}` },
                });
                if (res.ok) {
                    const data = await res.json();
                    systemStats = {
                        totalUsers: data.totalUsers || 0, totalAdmins: data.totalAdmins || 0,
                        totalSellers: data.totalSellers || 0, totalClients: data.totalClients || 0,
                        activeUsers: data.activeUsers || 0, pendingUsers: data.pendingUsers || 0,
                        todayActivities: data.todayActivities || 0, todayRevenue: data.todaySales || 0,
                        onlineUsers: data.onlineUsers || 0, connectedNow: data.connectedNow || 0,
                    };
                }
            } catch (e) { console.error(e); }
        }

        // Who is online right now. The endpoint applies the server's own
        // recency window, so the badge here never disagrees with "Mtandaoni Sasa".
        async function fetchOnlineUsers() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/online-users`, {
                    headers: { 'Authorization': `Bearer ${token}` },
                });
                if (res.ok) {
                    const data = await res.json();
                    const list = data.online_users || [];
                    onlineIds = new Set(list.map((u) => String(u.id)));
                }
            } catch (e) { console.error(e); }
        }

        // Users whose account was visited in the last 24 hours, most recent
        // first. last_seen is the timestamp the API already keeps on every hit.
        function todayUsers() {
            const start = new Date(Date.now() - 24 * 60 * 60 * 1000);
            return users
                .filter((u) => {
                    if (!u.last_seen) return false;
                    const seen = new Date(u.last_seen);
                    return !isNaN(seen.getTime()) && seen >= start;
                })
                .sort((a, b) => new Date(b.last_seen) - new Date(a.last_seen));
        }

        function avatarInner(user) {
            if (user.business_logo_url) {
                return `<img src="${escapeHtml(user.business_logo_url)}" alt="">`;
            }
            const source = user.full_name || user.email || '?';
            return escapeHtml(String(source).charAt(0).toUpperCase());
        }

        function todayRow(user) {
            const isOnline = onlineIds.has(String(user.id));
            const color = roleColor(user.role);
            return `
                <div class="today-row">
                    <div class="today-avatar">${avatarInner(user)}<span class="today-presence ${isOnline ? 'on' : ''}"></span></div>
                    <div class="today-meta">
                        <div class="today-name">
                            <span style="overflow:hidden;text-overflow:ellipsis;">${escapeHtml(user.full_name || user.email || '')}</span>
                            ${isOnline ? `<span class="today-online">${t('today_users_online')}</span>` : ''}
                        </div>
                        <div class="today-email">${escapeHtml(user.email || '')}</div>
                    </div>
                    <div class="today-side">
                        <span class="badge" style="background:${color}18;color:${color};border-color:${color}33;">${escapeHtml(user.role || '')}</span>
                        <span class="today-last-seen">${t('today_last_seen')} ${formatTimeAgo(user.last_seen)}</span>
                    </div>
                </div>`;
        }

        function renderTodayUsers() {
            const all = todayUsers();
            const shown = todayExpanded ? all : all.slice(0, 5);
            const rows = shown.length
                ? shown.map(todayRow).join('')
                : `<div class="empty-state">${t('today_users_empty')}</div>`;
            const toggle = all.length > 5
                ? `<button type="button" class="view-more-btn" onclick="toggleTodayUsers()">
                       ${todayExpanded ? t('show_less') : t('view_more') + ' (' + (all.length - 5) + ')'}
                   </button>`
                : '';
            return `
                <div class="section-card">
                    <div class="section-header">
                        <div>
                            <div class="section-title">${t('today_users_title')}<span class="section-count">${all.length}</span></div>
                            <div class="section-subtitle">${t('today_users_sub')}</div>
                        </div>
                        ${toggle}
                    </div>
                    <div class="today-list">${rows}</div>
                </div>`;
        }

        function render() {
            const container = document.getElementById('dashboardContent');
            if (loading) {
                container.innerHTML = `<div class="loading-spinner dm-sc"></div><div style="text-align:center;color:#7f8c8d;">${t('loading')}</div>`;
                return;
            }

            container.innerHTML = `
                <div class="dashboard-header">
                    <div>
                        <div class="header-title">${t('header_title')}</div>
                        <div class="header-subtitle">${t('header_subtitle')}</div>
                    </div>
                    <div class="header-actions">
                        <div class="header-btn" onclick="loadAllData()" title="${t('icon_refresh')}">${t('icon_refresh')}</div>
                        <div class="header-btn" onclick="window.location.href='/home'" title="${t('icon_home')}">${t('icon_home')}</div>
                        <div class="header-btn" onclick="handleLogout()" style="color:#e74c3c;font-weight:700;" title="${t('icon_logout')}">${t('icon_logout')}</div>
                    </div>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-value">${systemStats.totalUsers}</div>
                        <div class="stat-label">${t('stat_total_users')}</div>
                        <div style="font-size:12px;color:#7f8c8d;">${systemStats.activeUsers} ${t('using')}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🌐</div>
                        <div class="stat-value">${systemStats.onlineUsers}</div>
                        <div class="stat-label">${t('stat_online_now')}</div>
                        <div style="font-size:12px;color:#7f8c8d;">${systemStats.connectedNow} ${t('connected')}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-value">${formatCurrency(systemStats.todayRevenue)}</div>
                        <div class="stat-label">${t('stat_today_revenue')}</div>
                        <div style="font-size:12px;color:#7f8c8d;">${systemStats.todayActivities} ${t('activities')}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-value">${systemStats.pendingUsers}</div>
                        <div class="stat-label">${t('stat_pending')}</div>
                        <div style="font-size:12px;color:#7f8c8d;">${t('need_approval')}</div>
                    </div>
                </div>
                ${renderTodayUsers()}
                <div class="section-card">
                    <div class="section-header"><div class="section-title">${t('section_system_status')}</div></div>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px;">
                        <div><div style="font-weight:700;">${t('all_people')}</div><div>${systemStats.totalUsers}</div></div>
                        <div><div style="font-weight:700;">${t('sellers')}</div><div>${systemStats.totalSellers}</div></div>
                        <div><div style="font-weight:700;">${t('customers')}</div><div>${systemStats.totalClients}</div></div>
                        <div><div style="font-weight:700;">${t('administrators')}</div><div>${systemStats.totalAdmins}</div></div>
                    </div>
                </div>
            `;
        }

        function toggleTodayUsers() {
            todayExpanded = !todayExpanded;
            render();
        }
        window.toggleTodayUsers = toggleTodayUsers;

        function handleLogout() {
            if (!confirm(t('confirm_logout'))) return;
            ['userToken', 'userData', 'userId', 'userEmail', 'userRole', 'userName', 'userBusiness', 'userStatus',
             'savedEmail_admin', 'savedPassword_admin', 'rememberMe_admin'].forEach((key) => localStorage.removeItem(key));
            window.location.href = '/login?role=admin';
        }

        async function loadAllData() {
            loading = true;
            render();
            await Promise.all([fetchUsers(), fetchSystemStats(), fetchOnlineUsers()]);
            loading = false;
            render();
        }
        window.loadAllData = loadAllData;

        window.handleLogout = handleLogout;

        // Follow the language: the whole dashboard is script-built.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => render());
        }

        (async function init() {
            if (!checkSystemAdmin()) return;
            await loadAllData();
        })();
    </script>
</body>
</html>
@endverbatim
