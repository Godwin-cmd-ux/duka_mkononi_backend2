@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Watumiaji - DukaMkononi System Admin</title>
@endverbatim
    <link rel="stylesheet" href="/css/themes.css?v={{ filemtime(public_path('css/themes.css')) }}">
@verbatim
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }

        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, .55);
            display: none; align-items: flex-end; justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: #fff; width: 100%; max-width: 520px;
            max-height: 86vh; overflow-y: auto; border-radius: 20px 20px 0 0;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 18px 20px; border-bottom: 1px solid #e2e8f0;
            position: sticky; top: 0; background: #fff; z-index: 1;
        }
        .modal-title { font-size: 15px; font-weight: 800; }
        .modal-close {
            border: 0; background: transparent; font-size: 20px;
            color: #64748b; cursor: pointer; line-height: 1;
        }
        .modal-body { padding: 20px; }
        .modal-avatar {
            width: 76px; height: 76px; border-radius: 50%;
            background: #e0e7ff; color: #4338ca; display: grid;
            place-items: center; font-size: 28px; font-weight: 800;
            margin: 0 auto 14px; overflow: hidden;
        }
        .modal-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .modal-identity { text-align: center; margin-bottom: 18px; }
        .modal-identity h3 { font-size: 17px; font-weight: 800; color: #0f172a; }
        .modal-identity p { font-size: 13px; color: #64748b; margin-top: 2px; }
        .detail-row {
            display: flex; gap: 12px; padding: 10px 0;
            border-bottom: 1px solid #f1f5f9; font-size: 13px;
        }
        .detail-label { width: 128px; flex: 0 0 128px; color: #64748b; }
        .detail-value { flex: 1; color: #0f172a; font-weight: 500; word-break: break-word; }
        .modal-actions { display: flex; gap: 8px; margin-top: 18px; flex-wrap: wrap; }
        .modal-actions .sa-btn { flex: 1; justify-content: center; }
        .loading-spinner {
            width: 46px; height: 46px; border: 3px solid #e2e8f0;
            border-top-color: #4f46e5; border-radius: 50%;
            animation: spin .8s linear infinite; margin: 40px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .sa-status-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    </style>
</head>
<body class="sa-shell">
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])
@include('partials.toast')
@include('system_admin.partials.sidebar', ['saActive' => 'users'])
@verbatim
    <main class="page-content">
        <div class="sa-page-head">
            <div>
                <div class="sa-page-title">Watumiaji</div>
                <div class="sa-page-sub" id="usersSubtitle">Watumiaji wote waliosajiliwa kwenye jukwaa.</div>
            </div>
            <div class="sa-page-actions">
                <button type="button" class="sa-btn" onclick="loadUsers()">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                    Sasisha
                </button>
            </div>
        </div>

        <div class="sa-toolbar">
            <label class="sa-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="text" id="userSearch" placeholder="Tafuta jina au barua pepe..." autocomplete="off">
            </label>
            <div class="filter-buttons" id="roleFilters"></div>
        </div>

        <div class="sa-list-card">
            <div class="sa-list-head">
                <div>Mtumiaji</div>
                <div>Wajukumu</div>
                <div>Hali</div>
                <div style="text-align:right;">Vitendo</div>
            </div>
            <div id="userRowsHost">
                <div class="loading-spinner dm-sc"></div>
            </div>
        </div>
    </main>

    <div id="userModal" class="modal-overlay" onclick="if (event.target === this) closeUserModal()">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Maelezo ya Mtumiaji</div>
                <button type="button" class="modal-close" onclick="closeUserModal()" aria-label="Funga">&times;</button>
            </div>
            <div class="modal-body" id="userModalBody"></div>
        </div>
    </div>

    <script>
        const API_BASE_URL = '';

        // Swahili fallback for script-built strings; locales.json wins when the
        // language widget is loaded and has the key.
        const SW = {
            title: 'Watumiaji',
            subtitle: 'Watumiaji wote waliosajiliwa kwenye jukwaa.',
            subtitle_count: 'Watumiaji {total} · {online} wapo mtandaoni sasa.',
            search_placeholder: 'Tafuta jina au barua pepe...',
            all: 'Wote',
            administrators: 'Wasimamizi',
            sellers: 'Wauzaji',
            customers: 'Wateja',
            online: 'Online',
            no_users: 'Hakuna watumiaji waliopatikana',
            loading: 'Inapakia watumiaji...',
            col_user: 'Mtumiaji',
            col_role: 'Wajukumu',
            col_status: 'Hali',
            col_actions: 'Vitendo',
            action_details: 'Maelezo',
            action_activity: 'Shughuli',
            action_block: 'Zuia',
            action_enable: 'Wezesha',
            modal_title: 'Maelezo ya Mtumiaji',
            without_name: 'Bila Jina',
            label_phone: 'Simu',
            label_business: 'Biashara',
            label_location: 'Eneo',
            label_registered: 'Imejisajiliwa',
            label_status: 'Hali',
            label_last_seen: 'Mwisho kuonekana',
            label_role: 'Wajukumu',
            not_set: 'Haijawekwa',
            never: 'Hajawahi',
            time_mins_ago: 'Dakika {mins} zilizopita',
            time_hours_ago: 'Saa {hours} zilizopita',
            time_days_ago: 'Siku {days} zilizopita',
            open_activity: 'Fungua shughuli',
            confirm_enable: 'Una uhakika unataka kuwezesha {userName}?',
            confirm_block: 'Una uhakika unataka kuzuia {userName}?',
            status_enabled: '{userName} imewezeshwa kikamilifu!',
            status_blocked: '{userName} imezuiwa kikamilifu!',
            err_status_failed: 'Imeshindwa kubadilisha hali',
            err_network: 'Hitilafu ya mtandao',
            user_generic: 'Mtumiaji',
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

        const DATE_LOCALES = { sw: 'sw-TZ', en: 'en-GB', fr: 'fr-FR', hi: 'hi-IN', es: 'es-ES', ur: 'ur-PK', de: 'de-DE', zh: 'zh-CN' };
        function dateLocale() {
            const code = (window.DM && typeof window.DM.locale === 'function' && window.DM.locale()) || document.documentElement.getAttribute('data-dm-locale') || 'sw';
            return DATE_LOCALES[code] || 'sw-TZ';
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"]/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));
        }

        function escapeAttr(str) {
            return escapeHtml(str).replace(/'/g, '&#39;');
        }

        function formatDate(value) {
            if (!value) return t('not_set');
            const date = new Date(value);
            if (isNaN(date.getTime())) return t('not_set');
            return date.toLocaleDateString(dateLocale(), { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function formatTimeAgo(value) {
            if (!value) return t('never');
            const date = new Date(value);
            if (isNaN(date.getTime())) return t('never');
            const mins = Math.floor((new Date() - date) / 60000);
            if (mins < 1) return 'Sasa hivi';
            if (mins < 60) return t('time_mins_ago', { mins });
            const hours = Math.floor(mins / 60);
            if (hours < 24) return t('time_hours_ago', { hours });
            return t('time_days_ago', { days: Math.floor(hours / 24) });
        }

        const ROLE_COLORS = { admin: '#9b59b6', super_admin: '#7c3aed', system_admin: '#7c3aed', seller: '#2563eb', client: '#059669', customer: '#059669' };
        const STATUS_COLORS = { approved: '#059669', pending: '#b45309', rejected: '#dc2626', suspended: '#ea580c', inactive: '#64748b' };
        function roleColor(role) { return ROLE_COLORS[role] || '#64748b'; }
        function statusColor(status) { return STATUS_COLORS[status] || '#64748b'; }

        const ICONS = {
            eye: '<svg viewBox="0 0 24 24"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>',
            pulse: '<svg viewBox="0 0 24 24"><path d="M3 12h4l2.5-6 5 12L17 12h4"/></svg>',
            lock: '<svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
            unlock: '<svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 7.3-2.2"/></svg>',
        };

        let users = [];
        let onlineIds = new Set();
        let usersById = {};
        let searchQuery = '';
        let roleFilter = 'all';
        let loading = true;

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
            if (!['super_admin', 'system_admin'].includes(user.role || '')) {
                showToast(t('err_no_permission'), 'error');
                window.location.href = '/home';
                return false;
            }
            return true;
        }

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
                    usersById = {};
                    users.forEach((u) => { usersById[String(u.id)] = u; });
                }
            } catch (e) { console.error(e); }
        }

        // The server owns the online window, so this badge means the same thing
        // as the dashboard's "Mtandaoni Sasa" counter.
        async function fetchOnlineUsers() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/online-users`, {
                    headers: { 'Authorization': `Bearer ${token}` },
                });
                if (res.ok) {
                    const data = await res.json();
                    onlineIds = new Set((data.online_users || []).map((u) => String(u.id)));
                }
            } catch (e) { console.error(e); }
        }

        function avatarInner(user) {
            if (user.business_logo_url) {
                return `<img src="${escapeHtml(user.business_logo_url)}" alt="">`;
            }
            const source = user.full_name || user.email || '?';
            return escapeHtml(String(source).charAt(0).toUpperCase());
        }

        function filteredUsers() {
            const q = searchQuery.trim().toLowerCase();
            return users.filter((u) => {
                if (roleFilter !== 'all' && u.role !== roleFilter) return false;
                if (!q) return true;
                return String(u.full_name || '').toLowerCase().includes(q)
                    || String(u.email || '').toLowerCase().includes(q);
            });
        }

        // One row per user: identity, email, role, status and icon actions —
        // never a second block of buttons under the data.
        function userRow(user) {
            const isOnline = onlineIds.has(String(user.id));
            const color = roleColor(user.role);
            const sColor = statusColor(user.status);
            const id = escapeAttr(user.id);
            const canBlock = user.status === 'approved';
            const toggleLabel = canBlock ? t('action_block') : t('action_enable');
            const toggleClass = canBlock ? 'danger' : 'ok';
            const toggleIcon = canBlock ? ICONS.lock : ICONS.unlock;
            return `
                <div class="sa-user-row" data-user-id="${id}">
                    <div class="sa-user-cell">
                        <div class="sa-avatar sm">
                            ${avatarInner(user)}
                            <span class="sa-presence ${isOnline ? 'on' : ''}"></span>
                        </div>
                        <div class="sa-user-meta">
                            <div class="sa-user-name">
                                <span style="overflow:hidden;text-overflow:ellipsis;">${escapeHtml(user.full_name || t('without_name'))}</span>
                                ${isOnline ? `<span class="sa-online-badge">${t('online')}</span>` : ''}
                            </div>
                            <div class="sa-user-email">${escapeHtml(user.email || '')}</div>
                        </div>
                    </div>
                    <div class="sa-cell sa-col-role">
                        <span class="badge" style="background:${color}18;color:${color};border-color:${color}33;">${escapeHtml(user.role || '-')}</span>
                    </div>
                    <div class="sa-cell sa-col-status">
                        <span class="badge" style="background:${sColor}14;color:${sColor};border-color:${sColor}33;">
                            <span class="sa-status-dot" style="background:${sColor};"></span>${escapeHtml(user.status || '-')}
                        </span>
                    </div>
                    <div class="sa-row-actions">
                        <button type="button" class="sa-icon-btn" title="${t('action_details')}" aria-label="${t('action_details')}" onclick="openUser('${id}')">${ICONS.eye}</button>
                        <a class="sa-icon-btn" title="${t('action_activity')}" aria-label="${t('action_activity')}" href="/system_admin/logs?user=${encodeURIComponent(user.id)}">${ICONS.pulse}</a>
                        <button type="button" class="sa-icon-btn ${toggleClass}" title="${toggleLabel}" aria-label="${toggleLabel}" onclick="toggleStatus('${id}')">${toggleIcon}</button>
                    </div>
                </div>`;
        }

        function renderRows() {
            const host = document.getElementById('userRowsHost');
            if (!host) return;
            const rows = filteredUsers();
            host.innerHTML = rows.length
                ? rows.map(userRow).join('')
                : `<div class="empty-state">${t('no_users')}</div>`;
        }

        function renderRoleFilters() {
            const host = document.getElementById('roleFilters');
            if (!host) return;
            const options = [
                ['all', t('all')],
                ['admin', t('administrators')],
                ['seller', t('sellers')],
                ['client', t('customers')],
            ];
            host.innerHTML = options.map(([value, label]) =>
                `<div class="filter-chip ${roleFilter === value ? 'active' : ''}" onclick="setRoleFilter('${value}')">${label}</div>`
            ).join('');
        }

        function renderSubtitle() {
            const el = document.getElementById('usersSubtitle');
            if (!el) return;
            el.textContent = loading
                ? t('loading')
                : t('subtitle_count', { total: users.length, online: onlineIds.size });
        }

        function render() {
            renderSubtitle();
            renderRoleFilters();
            const input = document.getElementById('userSearch');
            if (input && input.value !== searchQuery) input.value = searchQuery;
            renderRows();
        }

        function setRoleFilter(value) {
            roleFilter = value;
            render();
        }
        window.setRoleFilter = setRoleFilter;

        function openUser(id) {
            const user = usersById[String(id)];
            if (!user) return;
            const isOnline = onlineIds.has(String(user.id));
            const color = roleColor(user.role);
            const sColor = statusColor(user.status);
            const canBlock = user.status === 'approved';
            const body = document.getElementById('userModalBody');
            body.innerHTML = `
                <div class="modal-avatar">${avatarInner(user)}</div>
                <div class="modal-identity">
                    <h3>${escapeHtml(user.full_name || t('without_name'))}
                        ${isOnline ? `<span class="sa-online-badge" style="vertical-align:middle;">${t('online')}</span>` : ''}
                    </h3>
                    <p>${escapeHtml(user.email || '')}</p>
                    <div style="display:flex;gap:6px;justify-content:center;margin-top:8px;flex-wrap:wrap;">
                        <span class="badge" style="background:${color}18;color:${color};border-color:${color}33;">${escapeHtml(user.role || '-')}</span>
                        <span class="badge" style="background:${sColor}14;color:${sColor};border-color:${sColor}33;">${escapeHtml(user.status || '-')}</span>
                    </div>
                </div>
                <div class="detail-row"><div class="detail-label">${t('label_phone')}</div><div class="detail-value">${escapeHtml(user.phone || t('not_set'))}</div></div>
                <div class="detail-row"><div class="detail-label">${t('label_business')}</div><div class="detail-value">${escapeHtml(user.business_name || t('not_set'))}</div></div>
                <div class="detail-row"><div class="detail-label">${t('label_location')}</div><div class="detail-value">${escapeHtml(user.business_location || t('not_set'))}</div></div>
                <div class="detail-row"><div class="detail-label">${t('label_registered')}</div><div class="detail-value">${formatDate(user.created_at)}</div></div>
                <div class="detail-row"><div class="detail-label">${t('label_last_seen')}</div><div class="detail-value">${formatTimeAgo(user.last_seen)}</div></div>
                <div class="modal-actions">
                    <a class="sa-btn" href="/system_admin/logs?user=${encodeURIComponent(user.id)}">${ICONS.pulse} ${t('open_activity')}</a>
                    <button type="button" class="sa-btn ${canBlock ? '' : 'primary'}" onclick="toggleStatus('${escapeAttr(user.id)}')">${canBlock ? ICONS.lock + ' ' + t('action_block') : ICONS.unlock + ' ' + t('action_enable')}</button>
                </div>
            `;
            document.getElementById('userModal').style.display = 'flex';
        }
        window.openUser = openUser;

        function closeUserModal() {
            document.getElementById('userModal').style.display = 'none';
        }
        window.closeUserModal = closeUserModal;

        async function toggleStatus(id) {
            const user = usersById[String(id)];
            if (!user) return;
            const nextStatus = user.status === 'approved' ? 'pending' : 'approved';
            const action = nextStatus === 'approved' ? 'enable' : 'disable';
            const userName = user.full_name || user.email || t('user_generic');
            if (!confirm(t(action === 'enable' ? 'confirm_enable' : 'confirm_block', { userName }))) return;

            const token = localStorage.getItem('userToken');
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/users/${encodeURIComponent(user.id)}/status`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: nextStatus }),
                });
                if (res.ok) {
                    showToast(t(action === 'enable' ? 'status_enabled' : 'status_blocked', { userName }), 'success');
                    closeUserModal();
                    await loadUsers();
                } else {
                    showToast(t('err_status_failed'), 'error');
                }
            } catch (e) {
                showToast(t('err_network'), 'error');
            }
        }
        window.toggleStatus = toggleStatus;

        async function loadUsers() {
            loading = true;
            render();
            await Promise.all([fetchUsers(), fetchOnlineUsers()]);
            loading = false;
            render();
        }
        window.loadUsers = loadUsers;

        function handleLogout() {
            if (!confirm(t('confirm_logout'))) return;
            ['userToken', 'userData', 'userId', 'userEmail', 'userRole', 'userName', 'userBusiness', 'userStatus'].forEach((key) => localStorage.removeItem(key));
            window.location.href = '/login?role=admin';
        }
        window.handleLogout = handleLogout;

        const searchInput = document.getElementById('userSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (event) => {
                searchQuery = event.target.value;
                // Only the rows re-render — the input keeps focus and caret.
                renderRows();
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeUserModal();
        });

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => render());
        }

        (async function init() {
            if (!checkSystemAdmin()) return;
            await loadUsers();
        })();
    </script>
</body>
</html>
@endverbatim
