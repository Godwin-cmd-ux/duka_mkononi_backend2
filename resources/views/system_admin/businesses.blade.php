@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Biashara - DukaMkononi System Admin</title>
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
            background: #fff; width: 100%; max-width: 720px;
            max-height: 88vh; overflow-y: auto; border-radius: 20px 20px 0 0;
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
            width: 76px; height: 76px; border-radius: 18px;
            background: #e0e7ff; color: #4338ca; display: grid;
            place-items: center; font-size: 28px; font-weight: 800;
            margin: 0 auto 14px; overflow: hidden;
        }
        .modal-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .modal-identity { text-align: center; margin-bottom: 18px; }
        .modal-identity h3 { font-size: 17px; font-weight: 800; color: #0f172a; }
        .modal-identity p { font-size: 13px; color: #64748b; margin-top: 3px; }
        .detail-row {
            display: flex; gap: 12px; padding: 10px 0;
            border-bottom: 1px solid #f1f5f9; font-size: 13px;
        }
        .detail-label { width: 148px; flex: 0 0 148px; color: #64748b; }
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

        .biz-stats {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 9px; margin: 16px 0 20px;
        }
        .biz-stat {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 12px; padding: 11px 12px;
        }
        .biz-stat span {
            display: block; font-size: 10px; font-weight: 800;
            letter-spacing: .05em; text-transform: uppercase; color: #64748b;
        }
        .biz-stat b { display: block; margin-top: 3px; font-size: 15px; color: #0f172a; }

        .biz-section-title {
            display: flex; justify-content: space-between; align-items: center;
            gap: 10px; margin: 18px 0 8px; font-size: 12px;
            font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #475569;
        }
        .biz-users { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .biz-user-row {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9; font-size: 12.5px;
        }
        .biz-user-row:last-child { border-bottom: 0; }
        .biz-user-main { flex: 1; min-width: 0; }
        .biz-user-main b { display: block; font-size: 12.5px; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .biz-user-main span { display: block; font-size: 11px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .activity-card {
            border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 12px 14px; margin-bottom: 9px; background: #fff;
        }
        .activity-head { display: flex; justify-content: space-between; gap: 10px; }
        .activity-action { font-size: 12.5px; font-weight: 800; color: #0f172a; word-break: break-word; }
        .activity-time { font-size: 10.5px; color: #94a3b8; white-space: nowrap; }
        .activity-meta { font-size: 11px; color: #64748b; margin-top: 4px; word-break: break-word; }
        .activity-endpoint { font-size: 10.5px; color: #4f46e5; margin-top: 4px; word-break: break-all; }

        @media (max-width: 640px) {
            .biz-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .detail-label { width: 112px; flex: 0 0 112px; }
            .modal-body { padding: 16px; }
        }
    </style>
</head>
<body class="sa-shell">
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])
@include('partials.toast')
@include('system_admin.partials.sidebar', ['saActive' => 'businesses'])
@verbatim
    <main class="page-content">
        <div class="sa-page-head">
            <div>
                <div class="sa-page-title">Biashara</div>
                <div class="sa-page-sub" id="businessesSubtitle">Biashara zote zilizosajiliwa kwenye jukwaa.</div>
            </div>
            <div class="sa-page-actions">
                <button type="button" class="sa-btn" onclick="loadBusinesses()">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                    Sasisha
                </button>
            </div>
        </div>

        <div class="sa-toolbar">
            <label class="sa-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="text" id="businessSearch" placeholder="Tafuta biashara, mmiliki au eneo..." autocomplete="off">
            </label>
            <div class="filter-buttons" id="businessFilters"></div>
        </div>

        <div class="sa-list-card">
            <div class="sa-list-head">
                <div>Biashara</div>
                <div>Mmiliki</div>
                <div>Hali</div>
                <div style="text-align:right;">Vitendo</div>
            </div>
            <div id="businessRowsHost">
                <div class="loading-spinner dm-sc"></div>
            </div>
        </div>
    </main>

    <div id="businessModal" class="modal-overlay" onclick="if (event.target === this) closeBusinessModal()">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="businessModalTitle">Maelezo ya Biashara</div>
                <button type="button" class="modal-close" onclick="closeBusinessModal()" aria-label="Funga">&times;</button>
            </div>
            <div class="modal-body" id="businessModalBody"></div>
        </div>
    </div>

    <div id="activityModal" class="modal-overlay" onclick="if (event.target === this) closeActivityModal()">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="activityModalTitle">Shughuli za Biashara</div>
                <button type="button" class="modal-close" onclick="closeActivityModal()" aria-label="Funga">&times;</button>
            </div>
            <div class="modal-body" id="activityModalBody"></div>
        </div>
    </div>

    <script>
        const API_BASE_URL = '';

        // Swahili fallback for script-built strings; locales.json wins when the
        // language widget is loaded and has the key.
        const SW = {
            title: 'Biashara',
            subtitle: 'Biashara zote zilizosajiliwa kwenye jukwaa.',
            subtitle_count: 'Biashara {total} · {blocked} zimezuiwa · {users} watumiaji.',
            search_placeholder: 'Tafuta biashara, mmiliki au eneo...',
            all: 'Zote',
            active: 'Hai',
            blocked: 'Zimezuiwa',
            certified: 'Zimethibitishwa',
            loading: 'Inapakia biashara...',
            no_businesses: 'Hakuna biashara zilizopatikana',
            col_business: 'Biashara',
            col_owner: 'Mmiliki',
            col_status: 'Hali',
            col_actions: 'Vitendo',
            action_view: 'Maelezo',
            action_activity: 'Shughuli',
            action_block: 'Zuia biashara',
            action_unblock: 'Ondoa zuio',
            action_delete: 'Futa biashara',
            status_active: 'Hai',
            status_blocked: 'Imezuiwa',
            status_certified: 'Imethibitishwa',
            users_count: 'Watumiaji {count}',
            modal_title: 'Maelezo ya Biashara',
            label_location: 'Eneo',
            label_type: 'Aina',
            label_owner: 'Mmiliki',
            label_created: 'Imesajiliwa',
            label_public: 'Inaonekana hadharani',
            stat_users: 'Watumiaji',
            stat_products: 'Bidhaa',
            stat_sales: 'Mauzo',
            stat_sales_total: 'Jumla ya mauzo',
            stat_expenses: 'Matumizi',
            stat_expenses_total: 'Jumla ya matumizi',
            section_users: 'Watumiaji walio kwenye biashara',
            no_users: 'Hakuna watumiaji kwenye biashara hii.',
            yes: 'Ndiyo',
            no: 'Hapana',
            not_set: 'Haijawekwa',
            activity_title: 'Shughuli za Biashara',
            activity_empty: 'Hakuna shughuli zilizorekodiwa kwa watumiaji wa biashara hii.',
            activity_loading: 'Inapakia shughuli...',
            confirm_block: 'Una uhakika unataka kuzuia {name}? Watumiaji wote wa biashara hii hawataweza kuingia kwenye akaunti zao.',
            confirm_unblock: 'Una uhakika unataka kuondoa zuio la {name}? Watumiaji wake wataweza kuingia tena.',
            block_success: '{name} imezuiwa. Watumiaji wake wote hawawezi kuingia.',
            unblock_success: 'Zuio la {name} limeondolewa.',
            confirm_delete: 'Futa {name}? Watumiaji wote wa biashara hii na data zao (mauzo, matumizi, bidhaa, n.k.) zitafutwa kabisa. Hatua hii haiwezi kutenduliwa.',
            delete_success: '{name} na watumiaji wake {count} wamefutwa.',
            delete_failed: 'Imeshindwa kufuta biashara',
            load_failed: 'Imeshindwa kupata biashara',
            status_failed: 'Imeshindwa kubadilisha hali ya biashara',
            err_network: 'Hitilafu ya mtandao',
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la System Admin.',
            confirm_logout: 'Una uhakika unataka kutoka?',
            time_mins_ago: 'Dakika {mins} zilizopita',
            time_hours_ago: 'Saa {hours} zilizopita',
            time_days_ago: 'Siku {days} zilizopita',
            never: 'Hajawahi',
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
        function formatCurrency(value) {
            if (value === null || value === undefined || value === '' || isNaN(Number(value))) return '-';
            try {
                return new Intl.NumberFormat(dateLocale(), { style: 'currency', currency: 'TZS', maximumFractionDigits: 0 }).format(Number(value));
            } catch (e) {
                return 'TZS ' + Number(value).toLocaleString();
            }
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
            trash: '<svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v5M14 11v5"/></svg>',
        };

        let businesses = [];
        let businessesById = {};
        let searchQuery = '';
        let statusFilter = 'all';
        let loading = true;
        let selectedBusinessId = null;

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

        function logoInner(business) {
            if (business.business_logo_url) {
                return `<img src="${escapeHtml(business.business_logo_url)}" alt="">`;
            }
            const source = business.business_name || '?';
            return escapeHtml(String(source).charAt(0).toUpperCase());
        }

        function filteredBusinesses() {
            const q = searchQuery.trim().toLowerCase();
            return businesses.filter((b) => {
                if (statusFilter === 'blocked' && !b.is_blocked) return false;
                if (statusFilter === 'active' && b.is_blocked) return false;
                if (statusFilter === 'certified' && !b.is_certified) return false;
                if (!q) return true;
                return String(b.business_name || '').toLowerCase().includes(q)
                    || String(b.business_location || '').toLowerCase().includes(q)
                    || String(b.owner_name || '').toLowerCase().includes(q)
                    || String(b.owner_email || '').toLowerCase().includes(q);
            });
        }

        function statusMeta(business) {
            if (business.is_blocked) return { label: t('status_blocked'), color: '#dc2626' };
            if (business.is_certified) return { label: t('status_certified'), color: '#059669' };
            return { label: t('status_active'), color: '#2563eb' };
        }

        function businessRow(business) {
            const id = escapeAttr(business.id);
            const status = statusMeta(business);
            const toggleLabel = business.is_blocked ? t('action_unblock') : t('action_block');
            const toggleIcon = business.is_blocked ? ICONS.unlock : ICONS.lock;
            const toggleClass = business.is_blocked ? 'ok' : 'danger';
            return `
                <div class="sa-user-row" data-business-id="${id}">
                    <div class="sa-user-cell">
                        <div class="sa-avatar sm">${logoInner(business)}</div>
                        <div class="sa-user-meta">
                            <div class="sa-user-name">
                                <span style="overflow:hidden;text-overflow:ellipsis;">${escapeHtml(business.business_name || '-')}</span>
                            </div>
                            <div class="sa-user-email">${escapeHtml(business.business_location || business.business_type || '')}</div>
                        </div>
                    </div>
                    <div class="sa-cell sa-col-role">
                        <div style="font-size:12.5px;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(business.owner_name || '-')}</div>
                        <div class="sa-user-email">${escapeHtml(business.owner_email || '')}</div>
                    </div>
                    <div class="sa-cell sa-col-status">
                        <span class="badge" style="background:${status.color}14;color:${status.color};border-color:${status.color}33;">
                            <span class="sa-status-dot" style="background:${status.color};"></span>${escapeHtml(status.label)}
                        </span>
                        <div class="sa-user-email">${escapeHtml(t('users_count', { count: business.users_count || 0 }))}</div>
                    </div>
                    <div class="sa-row-actions">
                        <button type="button" class="sa-icon-btn" title="${t('action_view')}" aria-label="${t('action_view')}" onclick="openBusiness('${id}')">${ICONS.eye}</button>
                        <button type="button" class="sa-icon-btn" title="${t('action_activity')}" aria-label="${t('action_activity')}" onclick="openActivity('${id}')">${ICONS.pulse}</button>
                        <button type="button" class="sa-icon-btn ${toggleClass}" title="${toggleLabel}" aria-label="${toggleLabel}" onclick="toggleBlock('${id}')">${toggleIcon}</button>
                        <button type="button" class="sa-icon-btn danger" title="${t('action_delete')}" aria-label="${t('action_delete')}" onclick="deleteBusiness('${id}')">${ICONS.trash}</button>
                    </div>
                </div>`;
        }

        function renderRows() {
            const host = document.getElementById('businessRowsHost');
            if (!host) return;
            const rows = filteredBusinesses();
            host.innerHTML = rows.length
                ? rows.map(businessRow).join('')
                : `<div class="empty-state">${t('no_businesses')}</div>`;
        }

        function renderFilters() {
            const host = document.getElementById('businessFilters');
            if (!host) return;
            const options = [
                ['all', t('all')],
                ['active', t('active')],
                ['blocked', t('blocked')],
                ['certified', t('certified')],
            ];
            host.innerHTML = options.map(([value, label]) =>
                `<div class="filter-chip ${statusFilter === value ? 'active' : ''}" onclick="setBusinessFilter('${value}')">${label}</div>`
            ).join('');
        }

        function renderSubtitle() {
            const el = document.getElementById('businessesSubtitle');
            if (!el) return;
            if (loading) {
                el.textContent = t('loading');
                return;
            }
            const blocked = businesses.filter((b) => b.is_blocked).length;
            const users = businesses.reduce((sum, b) => sum + (Number(b.users_count) || 0), 0);
            el.textContent = t('subtitle_count', { total: businesses.length, blocked, users });
        }

        function render() {
            renderSubtitle();
            renderFilters();
            const input = document.getElementById('businessSearch');
            if (input && input.value !== searchQuery) input.value = searchQuery;
            renderRows();
        }

        function setBusinessFilter(value) {
            statusFilter = value;
            render();
        }
        window.setBusinessFilter = setBusinessFilter;

        async function fetchJson(url, options) {
            const token = localStorage.getItem('userToken');
            const response = await fetch(API_BASE_URL + url, Object.assign({
                headers: { 'Authorization': `Bearer ${token}` },
            }, options || {}));
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.error || t('load_failed'));
            }
            return data;
        }

        async function loadBusinesses() {
            loading = true;
            render();
            try {
                const data = await fetchJson('/api/system-admin/businesses');
                businesses = Array.isArray(data) ? data : (data.businesses || []);
                businessesById = {};
                businesses.forEach((b) => { businessesById[String(b.id)] = b; });
            } catch (e) {
                showToast(e.message || t('load_failed'), 'error');
            }
            loading = false;
            render();
        }
        window.loadBusinesses = loadBusinesses;

        function openBusiness(id) {
            const business = businessesById[String(id)];
            if (!business) return;
            selectedBusinessId = String(business.id);
            document.getElementById('businessModalTitle').textContent = t('modal_title');
            document.getElementById('businessModalBody').innerHTML = '<div class="loading-spinner dm-sc"></div>';
            document.getElementById('businessModal').style.display = 'flex';

            fetchJson('/api/system-admin/businesses/' + encodeURIComponent(business.id))
                .then((detail) => renderBusinessDetail(detail))
                .catch((e) => {
                    document.getElementById('businessModalBody').innerHTML =
                        `<div class="empty-state">${escapeHtml(e.message || t('load_failed'))}</div>`;
                });
        }
        window.openBusiness = openBusiness;

        function closeBusinessModal() {
            document.getElementById('businessModal').style.display = 'none';
        }
        window.closeBusinessModal = closeBusinessModal;

        function statCard(label, value) {
            return `<div class="biz-stat"><span>${escapeHtml(label)}</span><b>${escapeHtml(value)}</b></div>`;
        }

        function userRow(user) {
            const role = user.role || '-';
            const color = roleColor(role);
            const sColor = statusColor(user.status);
            return `
                <div class="biz-user-row">
                    <div class="sa-avatar sm" style="width:32px;height:32px;flex:0 0 32px;font-size:12px;">${escapeHtml(String(user.full_name || user.email || '?').charAt(0).toUpperCase())}</div>
                    <div class="biz-user-main">
                        <b>${escapeHtml(user.full_name || user.email || '-')}</b>
                        <span>${escapeHtml(user.email || '')}</span>
                    </div>
                    <span class="badge" style="background:${color}18;color:${color};border-color:${color}33;">${escapeHtml(role)}</span>
                    <span class="badge" style="background:${sColor}14;color:${sColor};border-color:${sColor}33;">${escapeHtml(user.status || '-')}</span>
                </div>`;
        }

        function renderBusinessDetail(business) {
            const status = statusMeta(business);
            const counts = business.counts || {};
            const users = Array.isArray(business.users) ? business.users : [];
            const toggleLabel = business.is_blocked ? t('action_unblock') : t('action_block');
            const toggleIcon = business.is_blocked ? ICONS.unlock : ICONS.lock;
            const toggleClass = business.is_blocked ? 'ok' : 'danger';

            document.getElementById('businessModalTitle').textContent = business.business_name || t('modal_title');
            document.getElementById('businessModalBody').innerHTML = `
                <div class="modal-avatar">${logoInner(business)}</div>
                <div class="modal-identity">
                    <h3>${escapeHtml(business.business_name || '-')}</h3>
                    <p>${escapeHtml(business.business_location || t('not_set'))}${business.business_type ? ' · ' + escapeHtml(business.business_type) : ''}</p>
                    <div style="display:flex;gap:6px;justify-content:center;margin-top:8px;flex-wrap:wrap;">
                        <span class="badge" style="background:${status.color}14;color:${status.color};border-color:${status.color}33;">${escapeHtml(status.label)}</span>
                        <span class="badge" style="background:#eef2ff;color:#4338ca;border-color:#c7d2fe;">${escapeHtml(t('users_count', { count: counts.users || 0 }))}</span>
                    </div>
                </div>

                <div class="biz-stats">
                    ${statCard(t('stat_users'), String(counts.users ?? users.length))}
                    ${statCard(t('stat_products'), counts.products === null ? '-' : String(counts.products ?? 0))}
                    ${statCard(t('stat_sales'), counts.sales === null ? '-' : String(counts.sales ?? 0))}
                    ${statCard(t('stat_sales_total'), formatCurrency(counts.sales_total))}
                    ${statCard(t('stat_expenses'), counts.expenses === null ? '-' : String(counts.expenses ?? 0))}
                    ${statCard(t('stat_expenses_total'), formatCurrency(counts.expenses_total))}
                </div>

                <div class="detail-row"><div class="detail-label">${t('label_owner')}</div><div class="detail-value">${escapeHtml(business.owner_name || '-')}${business.owner_email ? ' · ' + escapeHtml(business.owner_email) : ''}</div></div>
                <div class="detail-row"><div class="detail-label">${t('label_location')}</div><div class="detail-value">${escapeHtml(business.business_location || t('not_set'))}</div></div>
                <div class="detail-row"><div class="detail-label">${t('label_type')}</div><div class="detail-value">${escapeHtml(business.business_type || t('not_set'))}</div></div>
                <div class="detail-row"><div class="detail-label">${t('label_created')}</div><div class="detail-value">${formatDate(business.created_at)}</div></div>
                <div class="detail-row"><div class="detail-label">${t('label_public')}</div><div class="detail-value">${business.is_public ? t('yes') : t('no')}</div></div>

                <div class="biz-section-title"><span>${t('section_users')}</span><span>${users.length}</span></div>
                <div class="biz-users">${users.length ? users.map(userRow).join('') : `<div class="empty-state">${t('no_users')}</div>`}</div>

                <div class="modal-actions">
                    <button type="button" class="sa-btn" onclick="openActivity('${escapeAttr(business.id)}')">${ICONS.pulse} ${t('action_activity')}</button>
                    <button type="button" class="sa-btn ${toggleClass === 'danger' ? '' : 'primary'}" onclick="toggleBlock('${escapeAttr(business.id)}')">${toggleIcon} ${toggleLabel}</button>
                    <button type="button" class="sa-btn" style="color:#b91c1c;border-color:#fecaca;" onclick="deleteBusiness('${escapeAttr(business.id)}')">${ICONS.trash} ${t('action_delete')}</button>
                </div>
            `;
        }

        function openActivity(id) {
            const business = businessesById[String(id)];
            const name = business ? business.business_name : '';
            document.getElementById('activityModalTitle').textContent = t('activity_title') + (name ? ' · ' + name : '');
            document.getElementById('activityModalBody').innerHTML = '<div class="loading-spinner dm-sc"></div>';
            document.getElementById('activityModal').style.display = 'flex';

            fetchJson('/api/system-admin/businesses/' + encodeURIComponent(id) + '/activity')
                .then((data) => {
                    const logs = Array.isArray(data.logs) ? data.logs : [];
                    document.getElementById('activityModalBody').innerHTML = logs.length
                        ? logs.map(activityCard).join('')
                        : `<div class="empty-state">${t('activity_empty')}</div>`;
                })
                .catch((e) => {
                    document.getElementById('activityModalBody').innerHTML =
                        `<div class="empty-state">${escapeHtml(e.message || t('load_failed'))}</div>`;
                });
        }
        window.openActivity = openActivity;

        function closeActivityModal() {
            document.getElementById('activityModal').style.display = 'none';
        }
        window.closeActivityModal = closeActivityModal;

        function activityCard(log) {
            const user = log.user ? (log.user.full_name || log.user.email) : '';
            const color = log.status === 'failed' ? '#dc2626' : '#059669';
            return `
                <div class="activity-card">
                    <div class="activity-head">
                        <div class="activity-action">${escapeHtml(log.action || '-')}</div>
                        <div class="activity-time">${escapeHtml(formatTimeAgo(log.created_at))}</div>
                    </div>
                    ${log.endpoint ? `<div class="activity-endpoint">${escapeHtml(log.endpoint)}</div>` : ''}
                    <div class="activity-meta">
                        ${user ? '👤 ' + escapeHtml(user) + ' · ' : ''}
                        <span style="color:${color};font-weight:700;">${escapeHtml(log.status || '-')}</span>
                    </div>
                </div>`;
        }

        async function toggleBlock(id) {
            const business = businessesById[String(id)];
            if (!business) return;
            const nextBlocked = !business.is_blocked;
            const name = business.business_name || t('title');
            if (!confirm(t(nextBlocked ? 'confirm_block' : 'confirm_unblock', { name }))) return;

            try {
                await fetchJson('/api/system-admin/businesses/' + encodeURIComponent(business.id) + '/status', {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('userToken')}`,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ blocked: nextBlocked }),
                });
                showToast(t(nextBlocked ? 'block_success' : 'unblock_success', { name }), 'success');
                closeBusinessModal();
                closeActivityModal();
                await loadBusinesses();
            } catch (e) {
                showToast(e.message || t('status_failed'), 'error');
            }
        }
        window.toggleBlock = toggleBlock;

        async function deleteBusiness(id) {
            const business = businessesById[String(id)];
            if (!business) return;
            const name = business.business_name || t('title');
            if (!confirm(t('confirm_delete', { name }))) return;

            try {
                const data = await fetchJson('/api/system-admin/businesses/' + encodeURIComponent(business.id), {
                    method: 'DELETE',
                });
                const count = (data.deleted && Number(data.deleted.users)) || business.users_count || 0;
                showToast(t('delete_success', { name, count }), 'success');
                closeBusinessModal();
                closeActivityModal();
                await loadBusinesses();
            } catch (e) {
                showToast(e.message || t('delete_failed'), 'error');
            }
        }
        window.deleteBusiness = deleteBusiness;

        function handleLogout() {
            if (!confirm(t('confirm_logout'))) return;
            ['userToken', 'userData', 'userId', 'userEmail', 'userRole', 'userName', 'userBusiness', 'userStatus'].forEach((key) => localStorage.removeItem(key));
            window.location.href = '/login?role=admin';
        }
        window.handleLogout = handleLogout;

        const searchInput = document.getElementById('businessSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (event) => {
                searchQuery = event.target.value;
                renderRows();
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeBusinessModal();
                closeActivityModal();
            }
        });

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => render());
        }

        (async function init() {
            if (!checkSystemAdmin()) return;
            await loadBusinesses();
        })();
    </script>
</body>
</html>
@endverbatim
