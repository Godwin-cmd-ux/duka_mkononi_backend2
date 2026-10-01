@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="system_admin_dashboard.page_title">Dashbodi - DukaMkononi System Admin</title>
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

        /* Tabs */
        .tabs-container {
            display: flex; gap: 8px; background: white;
            border-radius: 16px; padding: 6px; margin-bottom: 24px;
        }
        .tab-btn {
            flex: 1; display: flex; align-items: center;
            justify-content: center; gap: 8px; padding: 12px;
            border-radius: 12px; cursor: pointer; color: #7f8c8d;
            font-weight: 500; transition: all 0.2s;
        }
        .tab-btn.active { background: #3498db; color: white; }

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
        
        /* Filters */
        .filter-bar {
            display: flex; gap: 12px; margin-bottom: 20px;
            flex-wrap: wrap; align-items: center;
        }
        .search-box {
            flex: 1; display: flex; align-items: center;
            background: #f8f9fa; border-radius: 12px; padding: 0 16px;
            border: 1px solid #e0e0e0;
        }
        .search-box input {
            flex: 1; padding: 12px; border: none; background: transparent;
            font-size: 14px; outline: none;
        }
        .filter-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .filter-chip {
            padding: 8px 16px; border-radius: 30px; background: #f8f9fa;
            cursor: pointer; font-size: 13px;
        }
        .filter-chip.active { background: #3498db; color: white; }

        /* User Card */
        .user-card {
            background: #f8f9fa; border-radius: 16px; padding: 16px;
            margin-bottom: 12px; border: 1px solid #e0e0e0;
        }
        .user-header { display: flex; gap: 16px; margin-bottom: 12px; }
        .user-avatar {
            width: 50px; height: 50px; border-radius: 25px;
            background: #3498db; display: flex; align-items: center;
            justify-content: center; color: white; font-weight: bold;
            font-size: 18px; position: relative;
        }
        .online-dot {
            position: absolute; bottom: 0; right: 0;
            width: 12px; height: 12px; border-radius: 6px;
            background: #2ecc71; border: 2px solid white;
        }
        .user-info { flex: 1; }
        .user-name { font-weight: 700; margin-bottom: 4px; }
        .user-email { font-size: 12px; color: #7f8c8d; margin-bottom: 6px; }
        .user-badges { display: flex; gap: 8px; flex-wrap: wrap; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .user-actions {
            display: flex; gap: 8px; margin-top: 12px;
            padding-top: 12px; border-top: 1px solid #e0e0e0;
        }
        .action-btn {
            flex: 1; padding: 8px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            gap: 6px; cursor: pointer; font-size: 12px; font-weight: 500;
        }
        .btn-details { background: #f0f0f0; color: #666; }
        .btn-logs { background: #e3f2fd; color: #1976d2; }
        .btn-disable { background: #fdeaea; color: #e74c3c; }
        .btn-enable { background: #e8f6ef; color: #27ae60; }

        /* Log Card */
        .log-card {
            background: #f8f9fa; border-radius: 12px; padding: 16px;
            margin-bottom: 12px; border: 1px solid #e0e0e0;
        }
        .log-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .log-action { font-weight: 600; font-size: 14px; }
        .log-time { font-size: 11px; color: #95a5a6; }
        .log-endpoint { font-size: 11px; color: #3498db; margin-bottom: 8px; }
        .log-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
        .log-status { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }

        /* Modal */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); display: none; align-items: flex-end;
            z-index: 1000;
        }
        .modal-content {
            background: white; border-radius: 24px 24px 0 0;
            width: 100%; max-height: 85vh; overflow-y: auto;
        }
        .modal-header {
            display: flex; justify-content: space-between;
            padding: 20px; border-bottom: 1px solid #ecf0f1;
        }
        .modal-body { padding: 20px; }
        .modal-avatar {
            width: 80px; height: 80px; border-radius: 40px;
            background: #3498db; display: flex; align-items: center;
            justify-content: center; color: white; font-size: 32px;
            font-weight: bold; margin: 0 auto 16px;
        }
        .detail-row { display: flex; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-label { width: 120px; color: #7f8c8d; }
        .detail-value { flex: 1; font-weight: 500; }

        .empty-state { text-align: center; padding: 40px; color: #95a5a6; }

        .loading-spinner {
            width: 50px; height: 50px; border: 3px solid #e0e0e0;
            border-top-color: #3498db; border-radius: 50%;
            animation: spin 0.8s linear infinite; margin: 40px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .filter-bar { flex-direction: column; }
            .filter-buttons { width: 100%; overflow-x: auto; }
            .user-header { flex-direction: column; align-items: center; text-align: center; }
            .user-badges { justify-content: center; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.toast')
@include('partials.photo-viewer')
@verbatim
    <div class="page-content" id="dashboardContent">
        <div class="loading-spinner"></div>
        <div style="text-align: center; color: #7f8c8d;" data-i18n="system_admin_dashboard.loading">Inapakua dashbodi...</div>
    </div>

    <!-- User Detail Modal -->
    <div id="userModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" style="font-weight:700;" data-i18n="system_admin_dashboard.modal_user_details">Maelezo ya Mtumiaji</div>
                <span style="cursor:pointer;font-size:24px;" onclick="closeUserModal()" data-i18n="system_admin_dashboard.close_x">✖</span>
            </div>
            <div class="modal-body" id="userModalBody"></div>
        </div>
    </div>

    <!-- Logs Modal -->
    <div id="logsModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" style="font-weight:700;" data-i18n="system_admin_dashboard.modal_activity_logs">Rekodi za Shughuli</div>
                <span style="cursor:pointer;font-size:24px;" onclick="closeLogsModal()" data-i18n="system_admin_dashboard.close_x">✖</span>
            </div>
            <div class="modal-body" id="logsModalBody"></div>
        </div>
    </div>

    <script>
        const API_BASE_URL = '';

        // Swahili fallback used when the language widget is not present (and
        // for script-built strings). Values mirror the sw catalog of the
        // system_admin_dashboard section in locales.json.
        const SW = {
            page_title: 'Dashbodi - DukaMkononi System Admin',
            loading: 'Inapakua dashbodi...',
            modal_user_details: 'Maelezo ya Mtumiaji',
            close_x: '✖',
            modal_activity_logs: 'Rekodi za Shughuli',
            click_photo: 'Bofya kuona picha',
            without_name: 'Bila Jina',
            label_phone: 'Simu:',
            not_set: 'Haijawekwa',
            label_business: 'Biashara:',
            label_location: 'Eneo:',
            label_registered: 'Imejisajiliwa:',
            label_status: 'Hali:',
            btn_view_logs: '📋 Angalia Logs',
            btn_block: '🔒 Zuia',
            btn_enable: '✅ Wezesha',
            total_records: 'Jumla ya rekodi: {count}',
            no_activity_records: 'Hakuna rekodi za shughuli',
            label_ip: 'IP: ',
            not_available: 'N/A',
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
            btn_details: '📋 Maelezo',
            btn_logs: '📜 Logs',
            no_users_found: 'Hakuna watumiaji waliopatikana',
            all_users_title: '👥 Watumiaji Wote ({count})',
            search_placeholder: 'Tafuta mtumiaji...',
            online_users_title: '🌐 Watumiaji Mtandaoni ({count})',
            btn_refresh: '🔄 Sasisha',
            label_websocket: '🔌 WebSocket: ',
            ws_connected: '✓ Imeungana',
            ws_disconnected: '✗ Haijaungana',
            label_realtime: '📡 Wakati Halisi: ',
            no_online_users: 'Hakuna watumiaji mtandaoni kwa sasa',
            label_last_seen: 'Ilionekana mwisho: ',
            system_logs_title: '📋 Rekodi za Mfumo ({count})',
            no_records_found: 'Hakuna rekodi zilizopatikana',
            guest: 'Guest',
            header_title: 'System Admin Dashboard',
            header_subtitle: 'Data Halisi kutoka Database',
            icon_refresh: '⟳',
            icon_home: '🏠',
            icon_logout: '🚪',
            tab_dashboard: '📊 Dashbodi',
            tab_users: '👥 Watumiaji',
            tab_online: '🌐 Mtandaoni',
            tab_logs: '📋 Rekodi',
            filter_all: 'Wote',
            filter_all_status: 'Zote',
            filter_success: 'Zilizofaulu',
            filter_failed: 'Zilizoshindwa',
            filter_today: 'Leo',
            filter_week: 'Wiki',
            filter_month: 'Mwezi',
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la System Admin.',
            never: 'Hajawahi',
            time_mins_ago: 'Dakika {mins} zilizopita',
            time_hours_ago: 'Saa {hours} zilizopita',
            time_days_ago: 'Siku {days} zilizopita',
            user_generic: 'Mtumiaji',
            confirm_enable: 'Una uhakika unataka kuwezesha {userName}?',
            confirm_block: 'Una uhakika unataka kuzuia {userName}?',
            status_enabled: '{userName} imewezeshwa kikamilifu!',
            status_blocked: '{userName} imezuiwa kikamilifu!',
            err_status_failed: 'Imeshindwa kubadilisha hali',
            err_network: 'Hitilafu ya mtandao',
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

        // Date locale follows the selected language (was hardcoded 'sw-TZ').
        const DATE_LOCALES = { sw: 'sw-TZ', en: 'en-GB', fr: 'fr-FR', hi: 'hi-IN', es: 'es-ES', ur: 'ur-PK', de: 'de-DE', zh: 'zh-CN' };
        function dateLocale() {
            const code = (window.DM && typeof window.DM.locale === 'function' && window.DM.locale()) || document.documentElement.getAttribute('data-dm-locale') || 'sw';
            return DATE_LOCALES[code] || 'sw-TZ';
        }

        let activeTab = 'dashboard';
        let users = [];
        let userLogs = [];
        let onlineUsers = [];
        let systemStats = {
            totalUsers: 0, totalAdmins: 0, totalSellers: 0,
            totalClients: 0, activeUsers: 0, pendingUsers: 0,
            reportedPosts: 0, todayActivities: 0, todayRevenue: 0,
            onlineUsers: 0, connectedNow: 0
        };
        let webSocketConnected = false;
        
        let searchQuery = '';
        let selectedUserType = 'all';
        let selectedLogStatus = 'all';
        let selectedTimeRange = 'today';
        
        let loading = true;
        let selectedUser = null;
        let userSpecificLogs = [];

        function getCurrentUser() {
            const token = localStorage.getItem('userToken');
            const userData = localStorage.getItem('userData');
            if (token && userData) {
                try { return JSON.parse(userData); } catch(e) { return null; }
            }
            return null;
        }

        function checkSystemAdmin() {
            const user = getCurrentUser();
            if (!user) { window.location.href = '/login?role=msimamizi'; return false; }
            if (user.email !== "cosmavictorini1994@gmail.com") {
                showToast(t('err_no_permission'), 'error');
                window.location.href = '/home';
                return false;
            }
            return true;
        }

        function formatCurrency(amount) { return `TZS ${(amount || 0).toLocaleString()}`; }
        function formatDate(dateStr) {
            if (!dateStr) return t('not_available');
            try { return new Date(dateStr).toLocaleDateString(dateLocale()); }
            catch { return t('not_available'); }
        }
        function formatTimeAgo(dateStr) {
            if (!dateStr) return t('never');
            try {
                const date = new Date(dateStr);
                const now = new Date();
                const diffMins = Math.floor((now - date) / 60000);
                if (diffMins < 60) return t('time_mins_ago', { mins: diffMins });
                const diffHours = Math.floor(diffMins / 60);
                if (diffHours < 24) return t('time_hours_ago', { hours: diffHours });
                return t('time_days_ago', { days: Math.floor(diffHours / 24) });
            } catch { return t('never'); }
        }

        function getStatusColor(status) {
            const colors = { approved: '#27ae60', pending: '#f39c12', rejected: '#e74c3c', suspended: '#e67e22', inactive: '#95a5a6', success: '#27ae60', failed: '#e74c3c' };
            return colors[status] || '#95a5a6';
        }

        function getRoleColor(role) {
            const colors = { admin: '#9b59b6', seller: '#3498db', client: '#2ecc71' };
            return colors[role] || '#95a5a6';
        }

        async function fetchUsers() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/users`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (res.ok) {
                    const data = await res.json();
                    users = data.users || (Array.isArray(data) ? data : []);
                }
            } catch(e) { console.error(e); }
        }

        async function fetchSystemStats() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/stats`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (res.ok) {
                    const data = await res.json();
                    systemStats = {
                        totalUsers: data.totalUsers || 0, totalAdmins: data.totalAdmins || 0,
                        totalSellers: data.totalSellers || 0, totalClients: data.totalClients || 0,
                        activeUsers: data.activeUsers || 0, pendingUsers: data.pendingUsers || 0,
                        reportedPosts: data.reportedPosts || 0, todayActivities: data.todayActivities || 0,
                        todayRevenue: data.todayRevenue || 0, onlineUsers: data.onlineUsers || 0,
                        connectedNow: data.connectedNow || 0
                    };
                }
            } catch(e) { console.error(e); }
        }

        async function fetchOnlineUsers() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/online-users`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (res.ok) {
                    const data = await res.json();
                    onlineUsers = data.online_users || [];
                    webSocketConnected = data.webSocket_connected || false;
                }
            } catch(e) { console.error(e); }
        }

        async function fetchUserLogs() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/logs`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (res.ok) {
                    const data = await res.json();
                    userLogs = Array.isArray(data) ? data : [];
                }
            } catch(e) { console.error(e); }
        }

        async function updateUserStatus(userId, status) {
            const token = localStorage.getItem('userToken');
            const action = status === 'approved' ? 'enable' : 'disable';
            const u = users.find(usr => String(usr.id) === String(userId));
            const userName = (u && (u.full_name || u.email)) || t('user_generic');
            if (!confirm(t(action === 'enable' ? 'confirm_enable' : 'confirm_block', { userName }))) return;
            
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/users/${userId}/status`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status })
                });
                if (res.ok) {
                    showToast(t(action === 'enable' ? 'status_enabled' : 'status_blocked', { userName }), 'success');
                    await loadAllData();
                } else { showToast(t('err_status_failed'), 'error'); }
            } catch(e) { showToast(t('err_network'), 'error'); }
        }

        function handleLogout() {
            if (confirm(t('confirm_logout'))) {
                localStorage.removeItem('userToken');
                localStorage.removeItem('userData');
                localStorage.removeItem('userId');
                localStorage.removeItem('userEmail');
                localStorage.removeItem('userRole');
                localStorage.removeItem('userName');
                localStorage.removeItem('userBusiness');
                localStorage.removeItem('userStatus');
                const keys = ['savedEmail_admin', 'savedPassword_admin', 'rememberMe_admin'];
                keys.forEach(key => localStorage.removeItem(key));
                window.location.href = '/login?role=admin';
            }
        }

        async function loadAllData() {
            loading = true;
            render();
            await Promise.all([fetchUsers(), fetchSystemStats(), fetchOnlineUsers(), fetchUserLogs()]);
            loading = false;
            render();
        }

        function getFilteredUsers() {
            let filtered = [...users];
            if (selectedUserType !== 'all') {
                filtered = filtered.filter(u => u.role === selectedUserType);
            }
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                filtered = filtered.filter(u => u.email?.toLowerCase().includes(q) || u.full_name?.toLowerCase().includes(q));
            }
            return filtered;
        }

        function getFilteredLogs() {
            let filtered = [...userLogs];
            if (selectedLogStatus !== 'all') {
                filtered = filtered.filter(log => log.status === selectedLogStatus);
            }
            const now = new Date();
            if (selectedTimeRange === 'today') {
                const todayStr = now.toISOString().split('T')[0];
                filtered = filtered.filter(log => log.created_at?.startsWith(todayStr));
            } else if (selectedTimeRange === 'week') {
                const weekAgo = new Date(now.setDate(now.getDate() - 7));
                filtered = filtered.filter(log => new Date(log.created_at) >= weekAgo);
            }
            return filtered.slice(0, 30);
        }

        function viewUserDetails(user) {
            selectedUser = user;
            const modal = document.getElementById('userModal');
            const body = document.getElementById('userModalBody');
            const roleColor = getRoleColor(user.role);
            body.innerHTML = `
                <div class="modal-avatar js-avatar-view" data-full="${user.business_logo_url || ''}" data-name="${escapeHtml(user.full_name || user.email)}" title="${t('click_photo')}">${user.business_logo_url ? `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;">` : (user.full_name?.charAt(0) || user.email.charAt(0)).toUpperCase()}</div>
                <div style="text-align:center; margin-bottom:20px;">
                    <div style="font-size:18px; font-weight:700;">${escapeHtml(user.full_name || t('without_name'))}</div>
                    <div style="color:#7f8c8d;">${escapeHtml(user.email)}</div>
                    <div style="margin-top:8px;"><span class="badge" style="background:${roleColor}20; color:${roleColor};">${user.role}</span></div>
                </div>
                <div class="detail-row"><div class="detail-label">${t('label_phone')}</div><div class="detail-value">${user.phone || t('not_set')}</div></div>
                ${user.business_name ? `<div class="detail-row"><div class="detail-label">${t('label_business')}</div><div class="detail-value">${escapeHtml(user.business_name)}</div></div>` : ''}
                ${user.business_location ? `<div class="detail-row"><div class="detail-label">${t('label_location')}</div><div class="detail-value">${escapeHtml(user.business_location)}</div></div>` : ''}
                <div class="detail-row"><div class="detail-label">${t('label_registered')}</div><div class="detail-value">${formatDate(user.created_at)}</div></div>
                <div class="detail-row"><div class="detail-label">${t('label_status')}</div><div class="detail-value"><span class="badge" style="background:${getStatusColor(user.status)}20; color:${getStatusColor(user.status)};">${user.status}</span></div></div>
                <div style="display:flex; gap:12px; margin-top:20px;">
                    <button class="action-btn btn-logs" style="flex:1;" onclick="viewUserLogs(${JSON.stringify(user).replace(/"/g, '&quot;')})">${t('btn_view_logs')}</button>
                    ${user.status === 'approved' ? 
                        `<button class="action-btn btn-disable" style="flex:1;" onclick="updateUserStatus('${user.id}', 'pending')">${t('btn_block')}</button>` :
                        `<button class="action-btn btn-enable" style="flex:1;" onclick="updateUserStatus('${user.id}', 'approved')">${t('btn_enable')}</button>`
                    }
                </div>
            `;
            modal.style.display = 'flex';
        }

        function closeUserModal() { document.getElementById('userModal').style.display = 'none'; }

        function viewUserLogs(user) {
            selectedUser = user;
            userSpecificLogs = userLogs.filter(log => log.user_id === user.id);
            const modal = document.getElementById('logsModal');
            const body = document.getElementById('logsModalBody');
            body.innerHTML = `
                <div style="margin-bottom:16px;">
                    <div style="font-weight:700;">${escapeHtml(user.full_name || user.email)}</div>
                    <div style="font-size:12px; color:#7f8c8d;">${t('total_records', { count: userSpecificLogs.length })}</div>
                </div>
                ${userSpecificLogs.length === 0 ? '<div class="empty-state">' + t('no_activity_records') + '</div>' :
                    userSpecificLogs.map(log => `
                        <div class="log-card">
                            <div class="log-header"><span class="log-action">${escapeHtml(log.action)}</span><span class="log-time">${formatTimeAgo(log.created_at)}</span></div>
                            <div class="log-endpoint">${escapeHtml(log.endpoint)}</div>
                            <div class="log-footer">
                                <span class="log-status" style="background:${getStatusColor(log.status)}20; color:${getStatusColor(log.status)};">${log.status}</span>
                                <span style="font-size:10px; color:#95a5a6;">${t('label_ip')}${log.ip_address || t('not_available')}</span>
                            </div>
                        </div>
                    `).join('')
                }
            `;
            modal.style.display = 'flex';
        }

        function closeLogsModal() { document.getElementById('logsModal').style.display = 'none'; }

        function renderDashboardTab() {
            return `
                <div class="welcome-card">
                    <div class="welcome-title">${t('welcome_title')}</div>
                    <div>${t('welcome_subtitle')}</div>
                </div>
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value">${systemStats.totalUsers}</div><div class="stat-label">${t('stat_total_users')}</div><div>${systemStats.activeUsers} ${t('using')}</div></div>
                    <div class="stat-card"><div class="stat-icon">🌐</div><div class="stat-value">${systemStats.onlineUsers}</div><div class="stat-label">${t('stat_online_now')}</div><div>${systemStats.connectedNow} ${t('connected')}</div></div>
                    <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-value">${formatCurrency(systemStats.todayRevenue)}</div><div class="stat-label">${t('stat_today_revenue')}</div><div>${systemStats.todayActivities} ${t('activities')}</div></div>
                    <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-value">${systemStats.pendingUsers}</div><div class="stat-label">${t('stat_pending')}</div><div>${t('need_approval')}</div></div>
                </div>
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

        // One user card (shared by initial render and live search re-filter).
        function userCardHtml(user) {
            const roleColor = getRoleColor(user.role);
            const statusColor = getStatusColor(user.status);
            return `
                <div class="user-card">
                    <div class="user-header">
                        <div class="user-avatar js-avatar-view" data-full="${user.business_logo_url || ''}" data-name="${escapeHtml(user.full_name || user.email)}">${user.business_logo_url ? `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;">` : (user.full_name?.charAt(0) || user.email.charAt(0)).toUpperCase()}<div class="online-dot" style="background:${user.is_online ? '#2ecc71' : '#95a5a6'}"></div></div>
                        <div class="user-info">
                            <div class="user-name">${escapeHtml(user.full_name || t('without_name'))}</div>
                            <div class="user-email">${escapeHtml(user.email)}</div>
                            <div class="user-badges">
                                <span class="badge" style="background:${roleColor}20; color:${roleColor};">${user.role}</span>
                                <span class="badge" style="background:${statusColor}20; color:${statusColor};">${user.status}</span>
                            </div>
                        </div>
                    </div>
                    <div class="user-actions">
                        <button class="action-btn btn-details" onclick="viewUserDetails(${JSON.stringify(user).replace(/"/g, '&quot;')})">${t('btn_details')}</button>
                        <button class="action-btn btn-logs" onclick="viewUserLogs(${JSON.stringify(user).replace(/"/g, '&quot;')})">${t('btn_logs')}</button>
                        ${user.status === 'approved' ? 
                            `<button class="action-btn btn-disable" onclick="updateUserStatus('${user.id}', 'pending')">${t('btn_block')}</button>` :
                            `<button class="action-btn btn-enable" onclick="updateUserStatus('${user.id}', 'approved')">${t('btn_enable')}</button>`
                        }
                    </div>
                </div>
            `;
        }

        // Re-renders ONLY the user list inside its host — never the search
        // input. The old code called render() on every keystroke, which
        // destroyed the input and threw the cursor out mid-word.
        function renderUserList() {
            const listHost = document.getElementById('userListHost');
            if (!listHost) { render(); return; }
            const filtered = getFilteredUsers();
            listHost.innerHTML = filtered.length === 0
                ? '<div class="empty-state">' + t('no_users_found') + '</div>'
                : filtered.map(userCardHtml).join('');
        }

        function renderUsersTab() {
            const filtered = getFilteredUsers();
            return `
                <div class="section-card">
                    <div class="section-header"><div class="section-title">${t('all_users_title', { count: users.length })}</div></div>
                    <div class="filter-bar">
                        <div class="search-box"><span>🔍</span><input type="text" id="userSearch" placeholder="${t('search_placeholder')}"></div>
                        <div class="filter-buttons" id="userTypeFilters"></div>
                    </div>
                    <div id="userListHost">
                    ${filtered.length === 0 ? '<div class="empty-state">' + t('no_users_found') + '</div>' :
                        filtered.map(user => userCardHtml(user)).join('')
                    }
                    </div>
                </div>
            `;
        }

        function renderOnlineTab() {
            return `
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title">${t('online_users_title', { count: onlineUsers.length })}</div>
                        <button class="action-btn btn-details" onclick="loadAllData()" style="padding:6px 12px;">${t('btn_refresh')}</button>
                    </div>
                    <div class="filter-info" style="margin-bottom:16px; padding:12px; background:#f8f9fa; border-radius:12px;">
                        <span>${t('label_websocket')}${webSocketConnected ? t('ws_connected') : t('ws_disconnected')}</span>
                        <span style="margin-left:20px;">${t('label_realtime')}${onlineUsers.length} ${t('using')}</span>
                    </div>
                    ${onlineUsers.length === 0 ? '<div class="empty-state">' + t('no_online_users') + '</div>' :
                        onlineUsers.map(user => `
                            <div class="user-card">
                                <div class="user-header">
                                    <div class="user-avatar">${(user.full_name?.charAt(0) || user.email.charAt(0)).toUpperCase()}<div class="online-dot" style="background:#2ecc71"></div></div>
                                    <div class="user-info">
                                        <div class="user-name">${escapeHtml(user.full_name || user.email)}</div>
                                        <div class="user-email">${escapeHtml(user.email)}</div>
                                        <div class="user-badges"><span class="badge" style="background:${getRoleColor(user.role)}20; color:${getRoleColor(user.role)};">${user.role}</span></div>
                                    </div>
                                </div>
                                <div class="user-footer" style="margin-top:8px; font-size:11px; color:#95a5a6;">
                                    ${t('label_last_seen')}${formatTimeAgo(user.last_seen)}
                                </div>
                            </div>
                        `).join('')
                    }
                </div>
            `;
        }

        function renderLogsTab() {
            const filtered = getFilteredLogs();
            return `
                <div class="section-card">
                    <div class="section-header"><div class="section-title">${t('system_logs_title', { count: userLogs.length })}</div></div>
                    <div class="filter-bar">
                        <div class="filter-buttons" id="logStatusFilters"></div>
                        <div class="filter-buttons" id="timeRangeFilters"></div>
                    </div>
                    ${filtered.length === 0 ? '<div class="empty-state">' + t('no_records_found') + '</div>' :
                        filtered.map(log => {
                            const statusColor = getStatusColor(log.status);
                            return `
                                <div class="log-card">
                                    <div class="log-header">
                                        <span class="log-action">${escapeHtml(log.action)}</span>
                                        <span class="log-time">${formatTimeAgo(log.created_at)}</span>
                                    </div>
                                    <div class="log-endpoint">${escapeHtml(log.endpoint)}</div>
                                    <div class="log-footer">
                                        <span class="log-status" style="background:${statusColor}20; color:${statusColor};">${log.status}</span>
                                        <span style="font-size:10px;">👤 ${escapeHtml(log.user_email || t('guest'))}</span>
                                    </div>
                                </div>
                            `;
                        }).join('')
                    }
                </div>
            `;
        }

        function render() {
            const container = document.getElementById('dashboardContent');
            if (loading) {
                container.innerHTML = `<div class="loading-spinner"></div><div style="text-align:center;">${t('loading')}</div>`;
                return;
            }
            
            container.innerHTML = `
                <div class="dashboard-header">
                    <div><div class="header-title">${t('header_title')}</div><div class="header-subtitle">${t('header_subtitle')}</div></div>
                    <div class="header-actions">
                        <div class="header-btn" onclick="loadAllData()">${t('icon_refresh')}</div>
                        <div class="header-btn" onclick="window.location.href='/home'">${t('icon_home')}</div>
                        <div class="header-btn" onclick="handleLogout()" style="color:#e74c3c;font-weight:700;">${t('icon_logout')}</div>
                    </div>
                </div>
                <div class="tabs-container">
                    <div class="tab-btn ${activeTab === 'dashboard' ? 'active' : ''}" onclick="setTab('dashboard')">${t('tab_dashboard')}</div>
                    <div class="tab-btn ${activeTab === 'users' ? 'active' : ''}" onclick="setTab('users')">${t('tab_users')}</div>
                    <div class="tab-btn ${activeTab === 'online' ? 'active' : ''}" onclick="setTab('online')">${t('tab_online')}</div>
                    <div class="tab-btn ${activeTab === 'logs' ? 'active' : ''}" onclick="setTab('logs')">${t('tab_logs')}</div>
                </div>
                ${activeTab === 'dashboard' ? renderDashboardTab() : 
                  activeTab === 'users' ? renderUsersTab() : 
                  activeTab === 'online' ? renderOnlineTab() : renderLogsTab()}
            `;
            
            // Attach event listeners for filters
            if (activeTab === 'users') {
                const searchInput = document.getElementById('userSearch');
                if (searchInput) searchInput.addEventListener('input', (e) => { searchQuery = e.target.value; renderUserList(); });
                const filterContainer = document.getElementById('userTypeFilters');
                if (filterContainer) {
                    const types = [{id:'all',label:t('filter_all')},{id:'admin',label:t('administrators')},{id:'seller',label:t('sellers')},{id:'client',label:t('customers')}];
                    filterContainer.innerHTML = types.map(t => `<div class="filter-chip ${selectedUserType === t.id ? 'active' : ''}" onclick="setUserType('${t.id}')">${t.label}</div>`).join('');
                }
            }
            if (activeTab === 'logs') {
                const logFilterContainer = document.getElementById('logStatusFilters');
                if (logFilterContainer) {
                    const statuses = [{id:'all',label:t('filter_all_status')},{id:'success',label:t('filter_success')},{id:'failed',label:t('filter_failed')}];
                    logFilterContainer.innerHTML = statuses.map(s => `<div class="filter-chip ${selectedLogStatus === s.id ? 'active' : ''}" onclick="setLogStatus('${s.id}')">${s.label}</div>`).join('');
                }
                const timeFilterContainer = document.getElementById('timeRangeFilters');
                if (timeFilterContainer) {
                    const ranges = [{id:'today',label:t('filter_today')},{id:'week',label:t('filter_week')},{id:'month',label:t('filter_month')}];
                    timeFilterContainer.innerHTML = ranges.map(r => `<div class="filter-chip ${selectedTimeRange === r.id ? 'active' : ''}" onclick="setTimeRange('${r.id}')">${r.label}</div>`).join('');
                }
            }
        }

        window.setTab = (tab) => { activeTab = tab; render(); };
        window.setUserType = (type) => { selectedUserType = type; render(); };
        window.setLogStatus = (status) => { selectedLogStatus = status; render(); };
        window.setTimeRange = (range) => { selectedTimeRange = range; render(); };
        window.updateUserStatus = updateUserStatus;
        window.viewUserDetails = viewUserDetails;
        window.viewUserLogs = viewUserLogs;
        window.closeUserModal = closeUserModal;
        window.closeLogsModal = closeLogsModal;
        window.loadAllData = loadAllData;

        function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }

        async function init() {
            if (!checkSystemAdmin()) return;
            await loadAllData();
        }
        
        // Follow the language: the whole dashboard is script-built.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => {
                render();
                if (selectedUser && document.getElementById('userModal').style.display === 'flex') viewUserDetails(selectedUser);
                if (selectedUser && document.getElementById('logsModal').style.display === 'flex') viewUserLogs(selectedUser);
            });
        }

        init();
    </script>
</body>
</html>
@endverbatim