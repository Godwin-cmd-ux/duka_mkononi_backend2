@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Rekodi - DukaMkononi System Admin</title>
@endverbatim
    <link rel="stylesheet" href="/css/themes.css?v={{ filemtime(public_path('css/themes.css')) }}">
@verbatim
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }

        .section-card { background: #fff; border-radius: 18px; padding: 22px; border: 1px solid #e2e8f0; }
        .section-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .section-title { font-size: 14px; font-weight: 800; color: #0f172a; }

        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
        .filter-buttons { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .search-box {
            flex: 1; min-width: 200px; display: flex; align-items: center; gap: 8px;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0 12px;
        }
        .search-box input { flex: 1; border: 0 !important; background: transparent !important; box-shadow: none !important; padding: 10px 0 !important; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: #f8fafc; border-radius: 14px; padding: 14px 16px; border-left: 4px solid #3498db; }
        .stat-icon { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; margin-bottom: 8px; }
        .stat-value { font-size: 22px; font-weight: 800; }
        .stat-label { font-size: 11.5px; color: #64748b; }

        .log-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin-bottom: 10px; }
        .log-header { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
        .log-action { font-weight: 700; font-size: 13px; }
        .log-time { font-size: 11px; color: #94a3b8; white-space: nowrap; }
        .log-endpoint { font-size: 11px; color: #3498db; margin-bottom: 6px; word-break: break-all; }
        .log-footer { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 6px; flex-wrap: wrap; }

        .empty-state { text-align: center; padding: 36px 16px; color: #94a3b8; font-size: 13px; }
        .loading-spinner {
            width: 46px; height: 46px; border: 3px solid #e2e8f0; border-top-color: #4f46e5;
            border-radius: 50%; animation: spin .8s linear infinite; margin: 36px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: none; align-items: flex-end; justify-content: center; z-index: 1000; }
        .modal-content { background: #fff; width: 100%; max-width: 640px; max-height: 88vh; overflow-y: auto; border-radius: 20px 20px 0 0; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; background: #fff; z-index: 1; }
        .modal-title { font-size: 15px; font-weight: 800; }
        .modal-body { padding: 20px; }
        .detail-row { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .detail-label { width: 132px; flex: 0 0 132px; color: #64748b; }
        .detail-value { flex: 1; word-break: break-word; }

        @media (max-width: 640px) {
            .section-card { padding: 16px; }
            .detail-label { width: 104px; flex: 0 0 104px; }
        }
    </style>
</head>
<body class="sa-shell">
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])
@include('partials.toast')
@include('system_admin.partials.sidebar', ['saActive' => 'logs'])
@verbatim
    <main class="page-content" id="logsContent">
        <div class="loading-spinner dm-sc"></div>
        <div style="text-align:center;color:#64748b;" id="logsLoadingText">Inapakia rekodi...</div>
    </main>

    <div id="monDetailModal" class="modal-overlay" onclick="if (event.target === this) closeMonDetail()">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="monDetailTitle">Maelezo</div>
                <button type="button" class="modal-close" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:#64748b;" onclick="closeMonDetail()" aria-label="Funga">&times;</button>
            </div>
            <div class="modal-body" id="monDetailBody"></div>
        </div>
    </div>

    <script>
        const API_BASE_URL = '';

        // Swahili fallback for script-built strings; locales.json wins when it
        // has the key. Mirrors the sw catalog of system_admin_dashboard.
        const SW = {
            page_title: 'Rekodi - DukaMkononi System Admin',
            loading: 'Inapakia...',
            logs_title: 'Rekodi',
            logs_subtitle: 'Ufuatiliaji wa matukio, usalama, vikao na rekodi za zamani za mfumo.',
            refresh: 'Sasisha',
            total_records: 'Jumla ya rekodi: {count}',
            no_activity_records: 'Hakuna rekodi za shughuli',
            guest: 'Guest',
            not_available: 'N/A',
            never: 'Hajawahi',
            label_ip: 'IP: ',
            filter_all_status: 'Zote',
            filter_success: 'Zilizofaulu',
            filter_failed: 'Zilizoshindwa',
            filter_today: 'Leo',
            filter_week: 'Wiki',
            filter_month: 'Mwezi',
            time_mins_ago: 'Dakika {mins} zilizopita',
            time_hours_ago: 'Saa {hours} zilizopita',
            time_days_ago: 'Siku {days} zilizopita',
            system_logs_title: '📋 Rekodi za Mfumo ({count})',
            no_records_found: 'Hakuna rekodi zilizopatikana',
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la System Admin.',
            confirm_logout: 'Una uhakika unataka kutoka?',
            err_network: 'Hitilafu ya mtandao',
            tab_activity: '🛡️ Ufuatiliaji',
            tab_security: '⚠️ Usalama',
            tab_sessions: '🔐 Vikao',
            tab_legacy: '📜 Zamani',
            mon_timeline_title: 'Ufuatiliaji wa Matukio',
            mon_security_title: 'Tahadhari za Usalama',
            mon_sessions_title: 'Vikao',
            mon_legacy_title: 'Rekodi za Zamani (user_logs)',
            mon_legacy_note: 'Rekodi zilizokuwepo kabla ya mfumo mpya; bado zinahifadhiwa na kusomeka.',
            mon_summary_events: 'Matukio Yote',
            mon_summary_logins: 'Kuingia Kumefanikiwa',
            mon_summary_failed_logins: 'Kuingia Kulishindwa',
            mon_summary_high_risk: 'Matukio ya Hatari Juu',
            mon_summary_critical: 'Matukio Muhimu Sana',
            mon_summary_failed_ops: 'Shughuli Zilizoshindwa',
            mon_summary_admin_changes: 'Mabadiliko ya Msimamizi',
            mon_summary_sessions: 'Watumiaji Hai (saa 24)',
            mon_summary_open_alerts: 'Tahadhari Zilizofunguliwa',
            mon_range_today: 'Leo',
            mon_range_7: 'Siku 7',
            mon_range_30: 'Siku 30',
            mon_range_all: 'Zote',
            mon_filter_user: 'Mtumiaji',
            mon_filter_category: 'Aina',
            mon_filter_severity: 'Kiwango cha Hatari',
            mon_filter_outcome: 'Matokeo',
            mon_filter_source: 'Chanzo',
            mon_filter_range: 'Kipindi',
            mon_filter_security_only: 'Takwimu za usalama pekee',
            mon_filter_all: 'Zote',
            mon_search: 'Tafuta matukio...',
            mon_apply: '🔍 Tumia',
            mon_reset: 'Ondosha',
            mon_loading: 'Inapakua...',
            mon_no_events: 'Hakuna matukio katika kipindi hiki',
            mon_no_alerts: 'Hakuna tahadhari katika hali hii',
            mon_no_sessions: 'Hakuna vikao vilivyopatikana',
            mon_total: 'Jumla: {count}',
            mon_page_of: 'Ukurasa {page} / {pages}',
            mon_export: '⬇️ Hamisha CSV',
            mon_col_time: 'Muda',
            mon_col_user: 'Mtumiaji',
            mon_col_event: 'Tukio',
            mon_col_module: 'Sehemu',
            mon_col_desc: 'Maelezo',
            mon_source_browser: 'Kivinjari',
            mon_source_server: 'Serveri',
            mon_guest: 'Mgeni',
            mon_detail_title: 'Maelezo ya Tukio',
            mon_detail_desc: 'Maelezo kamili',
            mon_detail_actor: 'Mhusika',
            mon_detail_time: 'Muda',
            mon_detail_source: 'Chanzo',
            mon_detail_module: 'Sehemu / Njia',
            mon_detail_device: 'Kifaa',
            mon_detail_ip: 'Anwani ya IP',
            mon_detail_ip_note: '(imehifadhiwa kutoka kwa mbali, si chanzo cha kuaminika)',
            mon_detail_outcome: 'Matokeo',
            mon_detail_severity: 'Kiwango cha Hatari',
            mon_detail_reason: 'Sababu ya kiwango',
            mon_detail_session: 'Kikao',
            mon_detail_entity: 'Rekodi Husika',
            mon_detail_meta: 'Taarifa za Ziada (zimefichwa bila siri)',
            mon_detail_related: 'Matukio Yanayohusiana',
            mon_related_empty: 'Hakuna matukio yanayohusiana yaliyopatikana',
            mon_device_unknown: 'Kifaa hakijajulikana',
            mon_unknown_model: 'Modeli haijulikani',
            mon_open_session: '🔐 Fungua kikao',
            mon_security_open: 'Zilizofunguliwa',
            mon_security_ack: 'Zinashughulikiwa',
            mon_security_resolved: 'Zimetatuliwa',
            mon_security_all: 'Zote',
            mon_alert_subject: 'Mada',
            mon_alert_count: 'Matukio: {count}',
            mon_alert_first: 'Mara ya kwanza',
            mon_alert_last: 'Mara ya mwisho',
            mon_alert_rule: 'Kanuni',
            mon_alert_ack: '☑ Anza kuchunguza',
            mon_alert_resolve: '✔ Tatua',
            mon_alert_reopen: '↺ Fungua tena',
            mon_alert_note: 'Maelezo ya uchunguzi...',
            mon_alert_resolution: 'Uamuzi wa ufumbuzi...',
            mon_alert_save: '💾 Hifadhi',
            mon_alert_saved: 'Tahadhari imesasishwa',
            mon_alert_save_failed: 'Imeshindwa kusasisha tahadhari',
            mon_alert_notes: 'Historia ya Uchunguzi',
            mon_alert_no_notes: 'Hakuna maelezo bado',
            mon_alert_context: 'Muktadha',
            mon_alert_related: 'Madeni/anuani zinazohusiana',
            mon_sessions_for_user: 'Vikao ({count})',
            mon_session_started: 'Kianzio',
            mon_session_last_seen: 'Mara ya mwisho',
            mon_session_duration: 'Muda',
            mon_session_status: 'Hali',
            mon_session_status_active: 'Hai',
            mon_session_status_ended: 'Kimefungwa',
            mon_session_status_expired: 'Kimeisha',
            mon_session_events: 'Matukio ya Kikao',
            mon_session_no_events: 'Hakuna matukio kwenye kikao hiki',
            mon_session_client_note: 'Kufungwa kwa kikao kunatoka kwa kivinjari; JWT haina muda wa kuisha.',
            mon_d: 'siku',
            mon_h: 'saa',
            mon_m: 'dak',
            mon_s: 'sek',
            mon_setup_banner: 'Vyanzo vya ufuatiliaji bado havijatengenezwa kwenye hii database. Bonyeza ili kuunda - unaacha data iliyopo ipo salama.',
            mon_setup_btn: '🔧 Tengeneza vyanzo',
            mon_setup_done: 'Vyanzo vya ufuatiliaji vimeundwa',
            mon_setup_failed: 'Imeshindwa kutengeneza vyanzo',
            mon_unavailable: 'Takwimu za ufuatiliaji hazipatikani kwa sasa.',
            mon_user_activity: 'Shughuli za {name}',
            mon_open_full_timeline: '➡️ Fungua ufuatiliaji kamili',
            mon_investigate: '🔍 Chunguza',
            mon_correlation: 'Kitambulisho cha uhusiano',
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
        function escapeAttr(str) { return escapeHtml(str).replace(/'/g, '&#39;'); }
        function jsq(value) { return JSON.stringify(String(value)).replace(/"/g, '&quot;'); }

        function formatTimeAgo(dateStr) {
            if (!dateStr) return t('never');
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return t('never');
            const mins = Math.floor((new Date() - date) / 60000);
            if (mins < 1) return 'Sasa hivi';
            if (mins < 60) return t('time_mins_ago', { mins });
            const hours = Math.floor(mins / 60);
            if (hours < 24) return t('time_hours_ago', { hours });
            return t('time_days_ago', { days: Math.floor(hours / 24) });
        }

        function getStatusColor(status) {
            const colors = { approved: '#27ae60', pending: '#f39c12', rejected: '#e74c3c', suspended: '#e67e22', inactive: '#95a5a6', success: '#27ae60', failed: '#e74c3c' };
            return colors[status] || '#95a5a6';
        }

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

        let users = [];
        let userLogs = [];
        let selectedLogStatus = 'all';
        let selectedTimeRange = 'today';

        // ---- Monitoring / audit state ----
        let logsView = 'timeline'; // timeline | security | sessions | legacy
        let monLoading = false;
        let monUnavailable = false;
        let monSummary = null;
        let monTimeline = { items: [], total: 0, page: 1, perPage: 25 };
        let monAlerts = { items: [], total: 0, page: 1, perPage: 25, status: 'open' };
        let monSessions = { items: [], total: 0, page: 1, perPage: 25 };
        let monFilters = { range: '7', q: '', user_id: '', category: '', severity: '', outcome: '', source: '', security: '' };

        const MON_CATEGORIES = ['authentication', 'authorization', 'account', 'admin', 'business', 'ai', 'system', 'page', 'navigation', 'input', 'control', 'search', 'form', 'file', 'session', 'security'];
        let monDetailRerender = null;

        function severityColor(sev) {
            return ({ INFO: '#95a5a6', LOW: '#3498db', MEDIUM: '#f39c12', HIGH: '#e67e22', CRITICAL: '#e74c3c' })[sev] || '#95a5a6';
        }
        function severityBadge(sev) {
            const color = severityColor(sev);
            return `<span style="padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;background:${color}22;color:${color};white-space:nowrap;">${escapeHtml(sev || 'INFO')}</span>`;
        }
        function outcomeColor(outcome) {
            return ({ success: '#27ae60', failed: '#e74c3c', rejected: '#e67e22', denied: '#c0392b', observed: '#7f8c8d' })[outcome] || '#7f8c8d';
        }
        function outcomeBadge(outcome) {
            const color = outcomeColor(outcome);
            return `<span style="padding:3px 9px;border-radius:20px;font-size:10px;font-weight:600;background:${color}22;color:${color};white-space:nowrap;">${escapeHtml(outcome || 'observed')}</span>`;
        }
        function sourceLabel(source) {
            if (source === 'browser') return t('mon_source_browser');
            if (source === 'server') return t('mon_source_server');
            return source || '';
        }
        function deviceText(device) {
            if (!device) return '';
            if (device.model) return `${escapeAttr(device.display || '')} (${t('mon_unknown_model')})`;
            return escapeHtml(device.display || '');
        }
        function formatEventTime(iso) {
            if (!iso) return t('not_available');
            try { return new Date(iso).toLocaleString(dateLocale()); } catch (e) { return String(iso); }
        }
        function formatDuration(seconds) {
            if (seconds === null || seconds === undefined || isNaN(seconds)) return '-';
            seconds = Math.max(0, Math.floor(seconds));
            const d = Math.floor(seconds / 86400);
            const h = Math.floor((seconds % 86400) / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            const parts = [];
            if (d) parts.push(d + ' ' + t('mon_d'));
            if (h) parts.push(h + ' ' + t('mon_h'));
            if (m) parts.push(m + ' ' + t('mon_m'));
            if (!parts.length) parts.push(s + ' ' + t('mon_s'));
            return parts.join(' ');
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
                }
            } catch (e) { console.error(e); }
        }

        async function fetchUserLogs() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/logs`, {
                    headers: { 'Authorization': `Bearer ${token}` },
                });
                if (res.ok) {
                    const data = await res.json();
                    userLogs = Array.isArray(data) ? data : [];
                }
            } catch (e) { console.error(e); }
        }

        function monHeaders(extra) {
            const token = localStorage.getItem('userToken');
            const headers = { 'Authorization': 'Bearer ' + token };
            if (extra) Object.assign(headers, extra);
            return headers;
        }
        async function monFetch(path, params) {
            const qs = new URLSearchParams();
            Object.keys(params || {}).forEach((k) => {
                const v = params[k];
                if (v !== '' && v !== null && v !== undefined) qs.set(k, v);
            });
            const query = qs.toString();
            const res = await fetch(`${API_BASE_URL}/api/${path}${query ? '?' + query : ''}`, { headers: monHeaders() });
            if (!res.ok) throw new Error('http_' + res.status);
            return res.json();
        }
        function monParams(extra) {
            const params = {};
            const now = Date.now();
            if (monFilters.range === 'today') {
                const d = new Date();
                params.from = new Date(d.getFullYear(), d.getMonth(), d.getDate()).toISOString();
            } else if (monFilters.range === '7') {
                params.from = new Date(now - 7 * 86400000).toISOString();
            } else if (monFilters.range === '30') {
                params.from = new Date(now - 30 * 86400000).toISOString();
            }
            ['q', 'user_id', 'category', 'severity', 'outcome', 'source', 'ip'].forEach((k) => {
                if (monFilters[k]) params[k] = monFilters[k];
            });
            if (monFilters.security) params.is_security = 'true';
            return Object.assign(params, extra || {});
        }

        async function loadSummary() {
            try {
                monSummary = await monFetch('admin/monitoring/summary', monParams());
                monUnavailable = !!(monSummary && monSummary.unavailable);
            } catch (e) { monSummary = null; }
        }
        async function loadTimeline() {
            try {
                const data = await monFetch('admin/monitoring/timeline', monParams({ page: monTimeline.page, per_page: monTimeline.perPage }));
                if (data.unavailable) { monUnavailable = true; monTimeline.items = []; monTimeline.total = 0; return; }
                monUnavailable = false;
                monTimeline.items = data.items || [];
                monTimeline.total = data.total || 0;
            } catch (e) { /* keep previous data; empty-state explains */ }
        }
        async function loadAlerts() {
            try {
                const data = await monFetch('admin/monitoring/alerts', { status: monAlerts.status, page: monAlerts.page, per_page: monAlerts.perPage });
                if (data.unavailable) { monUnavailable = true; monAlerts.items = []; monAlerts.total = 0; return; }
                monUnavailable = false;
                monAlerts.items = data.items || [];
                monAlerts.total = data.total || 0;
            } catch (e) { /* empty-state */ }
        }
        async function loadSessions() {
            try {
                const data = await monFetch('admin/monitoring/sessions', monParams({ page: monSessions.page, per_page: monSessions.perPage }));
                if (data.unavailable) { monUnavailable = true; monSessions.items = []; monSessions.total = 0; return; }
                monUnavailable = false;
                monSessions.items = data.items || [];
                monSessions.total = data.total || 0;
            } catch (e) { /* empty-state */ }
        }
        async function monReloadCurrent() {
            if (logsView === 'timeline') { await Promise.all([loadSummary(), loadTimeline()]); }
            else if (logsView === 'security') { await loadAlerts(); }
            else if (logsView === 'sessions') { await loadSessions(); }
        }
        function monRunLoad() {
            monLoading = true;
            render();
            monReloadCurrent().finally(() => { monLoading = false; render(); });
        }
        function selectValue(id) {
            const el = document.getElementById(id);
            return el ? el.value : '';
        }
        window.applyMonFilters = function () {
            monFilters.q = selectValue('monSearch').trim().slice(0, 64);
            monFilters.user_id = selectValue('monFilterUser');
            monFilters.category = selectValue('monFilterCategory');
            monFilters.severity = selectValue('monFilterSeverity');
            monFilters.outcome = selectValue('monFilterOutcome');
            monFilters.source = selectValue('monFilterSource');
            monFilters.range = selectValue('monFilterRange') || '7';
            const securityBox = document.getElementById('monFilterSecurity');
            monFilters.security = securityBox && securityBox.checked ? '1' : '';
            monTimeline.page = 1;
            monSessions.page = 1;
            monRunLoad();
        };
        window.resetMonFilters = function () {
            monFilters = { range: '7', q: '', user_id: '', category: '', severity: '', outcome: '', source: '', security: '' };
            monTimeline.page = 1;
            monSessions.page = 1;
            monRunLoad();
        };
        window.setLogsView = function (view) {
            logsView = view;
            if (view === 'legacy') { render(); return; }
            monRunLoad();
        };
        window.monTimelinePage = function (page) {
            monTimeline.page = Math.max(1, page);
            monRunLoad();
        };
        window.monAlertsPage = function (page) {
            monAlerts.page = Math.max(1, page);
            monRunLoad();
        };
        window.monSessionsPage = function (page) {
            monSessions.page = Math.max(1, page);
            monRunLoad();
        };
        window.setAlertStatusFilter = function (status) {
            monAlerts.status = status;
            monAlerts.page = 1;
            monRunLoad();
        };
        window.runMonSetup = async function () {
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/monitoring/setup`, { method: 'POST', headers: monHeaders() });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    showToast(data.message || t('mon_setup_done'), 'success');
                    monUnavailable = false;
                    monRunLoad();
                } else {
                    showToast(t('mon_setup_failed'), 'error');
                }
            } catch (e) { showToast(t('mon_setup_failed'), 'error'); }
        };
        window.exportEvents = async function () {
            try {
                const qs = new URLSearchParams(monParams({ limit: 2000 }));
                const res = await fetch(`${API_BASE_URL}/api/admin/monitoring/export?${qs.toString()}`, { headers: monHeaders() });
                if (!res.ok) throw new Error('export_failed');
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'activity-events.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(url), 5000);
            } catch (e) { showToast(t('err_network'), 'error'); }
        };

        /* ------------------------------ rendering ------------------------- */

        function monSetupBannerHtml() {
            return `<div style="background:#fff7e6;border:1px solid #f39c12;border-radius:12px;padding:14px 16px;margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <div style="flex:1;min-width:220px;font-size:13px;color:#7d5a17;">⚠️ ${t('mon_setup_banner')}</div>
                <button class="filter-chip" style="background:#f39c12;color:#fff;" onclick="runMonSetup()">${t('mon_setup_btn')}</button>
            </div>`;
        }

        function monSummaryCard(value, label, icon, color) {
            return `<div class="stat-card" style="border-left-color:${color || '#3498db'};">
                <div class="stat-icon" style="background:${(color || '#3498db')}20;">${icon}</div>
                <div class="stat-value" style="color:${color || '#2c3e50'};">${value}</div>
                <div class="stat-label">${label}</div>
            </div>`;
        }

        function monPager(state, action) {
            const pages = Math.max(1, Math.ceil(state.total / state.perPage));
            const info = `<span style="font-size:12px;color:#7f8c8d;">${t('mon_total', { count: state.total })}</span>`;
            if (pages <= 1) return `<div style="margin-top:14px;">${info}</div>`;
            return `<div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                ${info}
                <div style="display:flex;gap:8px;align-items:center;">
                    <button class="filter-chip" ${state.page <= 1 ? 'disabled style="opacity:0.4;"' : ''} onclick="${action}(${state.page - 1})">◀</button>
                    <span style="font-size:12px;">${t('mon_page_of', { page: state.page, pages: pages })}</span>
                    <button class="filter-chip" ${state.page >= pages ? 'disabled style="opacity:0.4;"' : ''} onclick="${action}(${state.page + 1})">▶</button>
                </div>
            </div>`;
        }

        function monFilterBarHtml() {
            const userOptions = ['<option value="">' + t('mon_filter_user') + ': ' + t('mon_filter_all') + '</option>']
                .concat((users || []).map((u) => `<option value="${escapeAttr(u.id)}" ${monFilters.user_id === u.id ? 'selected' : ''}>${escapeHtml(u.full_name || u.email)}</option>`))
                .join('');
            const categoryOptions = ['<option value="">' + t('mon_filter_category') + ': ' + t('mon_filter_all') + '</option>']
                .concat(MON_CATEGORIES.map((c) => `<option value="${c}" ${monFilters.category === c ? 'selected' : ''}>${c}</option>`))
                .join('');
            const severityOptions = ['<option value="">' + t('mon_filter_severity') + ': ' + t('mon_filter_all') + '</option>']
                .concat(['INFO', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'].map((s) => `<option value="${s}" ${monFilters.severity === s ? 'selected' : ''}>${s}</option>`))
                .join('');
            const outcomeOptions = ['<option value="">' + t('mon_filter_outcome') + ': ' + t('mon_filter_all') + '</option>']
                .concat(['success', 'failed', 'rejected', 'denied', 'observed'].map((o) => `<option value="${o}" ${monFilters.outcome === o ? 'selected' : ''}>${o}</option>`))
                .join('');
            const sourceOptions = ['<option value="">' + t('mon_filter_source') + ': ' + t('mon_filter_all') + '</option>']
                .concat(['server', 'browser'].map((s) => `<option value="${s}" ${monFilters.source === s ? 'selected' : ''}>${sourceLabel(s)}</option>`))
                .join('');
            const rangeOptions = [['today', t('mon_range_today')], ['7', t('mon_range_7')], ['30', t('mon_range_30')], ['', t('mon_range_all')]]
                .map(([v, label]) => `<option value="${v}" ${monFilters.range === v ? 'selected' : ''}>${label}</option>`)
                .join('');

            const selectStyle = 'padding:9px 10px;border-radius:10px;border:1px solid #e0e0e0;background:#f8f9fa;font-size:13px;max-width:190px;';
            return `<div class="filter-bar">
                <div class="search-box"><span>🔍</span><input type="text" id="monSearch" placeholder="${t('mon_search')}" value="${escapeAttr(monFilters.q)}"></div>
                <select id="monFilterUser" style="${selectStyle}">${userOptions}</select>
                <select id="monFilterCategory" style="${selectStyle}">${categoryOptions}</select>
                <select id="monFilterSeverity" style="${selectStyle}">${severityOptions}</select>
                <select id="monFilterOutcome" style="${selectStyle}">${outcomeOptions}</select>
                <select id="monFilterSource" style="${selectStyle}">${sourceOptions}</select>
                <select id="monFilterRange" style="${selectStyle}">${rangeOptions}</select>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#7f8c8d;cursor:pointer;">
                    <input type="checkbox" id="monFilterSecurity" ${monFilters.security ? 'checked' : ''}> ${t('mon_filter_security_only')}
                </label>
                <button class="filter-chip active" onclick="applyMonFilters()">${t('mon_apply')}</button>
                <button class="filter-chip" onclick="resetMonFilters()">${t('mon_reset')}</button>
            </div>`;
        }

        function timelineRowHtml(ev) {
            const user = ev.user ? (ev.user.full_name || ev.user.email) : t('mon_guest');
            const module = ev.page || ev.route || '';
            const device = ev.device ? deviceText(ev.device) : '';
            return `<div class="log-card" style="cursor:pointer;border-left:4px solid ${severityColor(ev.severity)};" onclick="openEventDetail(${jsq(ev.id)})">
                <div class="log-header">
                    <span class="log-action">${escapeHtml(ev.event_name || '')} ${ev.is_security ? '🛡️' : ''}</span>
                    <span class="log-time">${escapeHtml(formatEventTime(ev.created_at))}</span>
                </div>
                <div style="font-size:13px;color:#2c3e50;margin-bottom:6px;">${escapeHtml(ev.description || '')}</div>
                <div class="log-footer">
                    <span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;font-size:11px;">
                        ${severityBadge(ev.severity)} ${outcomeBadge(ev.outcome)}
                        <span style="color:#7f8c8d;">👤 ${escapeHtml(user)}</span>
                        <span style="color:#3498db;">${escapeHtml(module)}</span>
                    </span>
                    <span style="font-size:10px;color:#95a5a6;display:flex;gap:8px;">
                        ${sourceLabel(ev.source)}
                        ${ev.ip_address ? '🌐 ' + escapeHtml(ev.ip_address) : ''}
                        ${device ? '🖥️ ' + device : ''}
                    </span>
                </div>
            </div>`;
        }

        function renderTimelineView() {
            if (monLoading && monTimeline.items.length === 0) {
                return `<div class="loading-spinner dm-sc"></div><div style="text-align:center;color:#7f8c8d;">${t('mon_loading')}</div>`;
            }
            const s = monSummary;
            const summaryHtml = (s && !s.unavailable) ? `
                <div class="stats-grid" style="margin-bottom:20px;">
                    ${monSummaryCard(s.total_events, t('mon_summary_events'), '📊', '#3498db')}
                    ${monSummaryCard(s.logins, t('mon_summary_logins'), '✅', '#27ae60')}
                    ${monSummaryCard(s.failed_logins, t('mon_summary_failed_logins'), '🚫', '#e67e22')}
                    ${monSummaryCard(s.high_risk_events, t('mon_summary_high_risk'), '⚠️', '#e67e22')}
                    ${monSummaryCard(s.critical_events, t('mon_summary_critical'), '🔴', '#e74c3c')}
                    ${monSummaryCard(s.failed_operations, t('mon_summary_failed_ops'), '❌', '#c0392b')}
                    ${monSummaryCard(s.admin_changes, t('mon_summary_admin_changes'), '🛠️', '#9b59b6')}
                    ${monSummaryCard(s.active_users, t('mon_summary_sessions'), '👥', '#16a085')}
                    ${monSummaryCard(s.open_alerts, t('mon_summary_open_alerts'), '🔕', '#f39c12')}
                </div>` : '';

            const rows = monTimeline.items.length
                ? monTimeline.items.map(timelineRowHtml).join('')
                : `<div class="empty-state">${t('mon_no_events')}</div>`;

            return summaryHtml + monFilterBarHtml() + rows + monPager(monTimeline, 'monTimelinePage');
        }

        function alertCardHtml(a) {
            const subject = a.subject_user
                ? (a.subject_user.full_name || a.subject_user.email)
                : String(a.subject || '').replace(/^(user|acct|ip):/, '');
            const statusLabel = { open: t('mon_security_open'), acknowledged: t('mon_security_ack'), resolved: t('mon_security_resolved') }[a.status] || a.status;
            const statusColorMap = { open: '#e74c3c', acknowledged: '#f39c12', resolved: '#27ae60' };
            const color = statusColorMap[a.status] || '#95a5a6';
            return `<div class="log-card" style="cursor:pointer;border-left:4px solid ${severityColor(a.severity)};" onclick="openAlertDetail(${jsq(a.id)})">
                <div class="log-header">
                    <span class="log-action">${severityBadge(a.severity)} <span style="font-size:12px;">${escapeHtml(a.rule || '')}</span></span>
                    <span class="log-time">${escapeHtml(formatEventTime(a.last_seen))}</span>
                </div>
                <div style="font-size:13px;margin-bottom:6px;">${escapeHtml(a.summary || '')}</div>
                <div class="log-footer">
                    <span style="font-size:11px;color:#7f8c8d;">${t('mon_alert_subject')}: <b>${escapeHtml(subject)}</b> · ${t('mon_alert_count', { count: a.occurrence_count })}</span>
                    <span style="padding:3px 9px;border-radius:20px;font-size:10px;font-weight:600;background:${color}22;color:${color};">${escapeHtml(statusLabel)}</span>
                </div>
            </div>`;
        }

        function renderSecurityView() {
            if (monLoading && monAlerts.items.length === 0) {
                return `<div class="loading-spinner dm-sc"></div><div style="text-align:center;color:#7f8c8d;">${t('mon_loading')}</div>`;
            }
            const statuses = [
                ['open', t('mon_security_open')],
                ['acknowledged', t('mon_security_ack')],
                ['resolved', t('mon_security_resolved')],
                ['', t('mon_security_all')],
            ];
            const chips = statuses.map(([value, label]) =>
                `<div class="filter-chip ${monAlerts.status === value ? 'active' : ''}" onclick="setAlertStatusFilter('${value}')">${label}</div>`
            ).join('');
            const rows = monAlerts.items.length
                ? monAlerts.items.map(alertCardHtml).join('')
                : `<div class="empty-state">${t('mon_no_alerts')}</div>`;
            return `<div class="filter-bar"><div class="filter-buttons">${chips}</div></div>` + rows + monPager(monAlerts, 'monAlertsPage');
        }

        function sessionCardHtml(s) {
            const user = s.user ? (s.user.full_name || s.user.email) : t('mon_guest');
            const statusLabel = { active: t('mon_session_status_active'), ended: t('mon_session_status_ended'), expired: t('mon_session_status_expired') }[s.status] || s.status;
            const statusColorMap = { active: '#27ae60', ended: '#95a5a6', expired: '#e67e22' };
            const color = statusColorMap[s.status] || '#95a5a6';
            return `<div class="log-card" style="cursor:pointer;border-left:4px solid ${color};" onclick="openSessionDetail(${jsq(s.session_ref)})">
                <div class="log-header">
                    <span class="log-action">🔐 ${escapeHtml(user)}</span>
                    <span class="log-time">${escapeHtml(formatEventTime(s.last_seen_at))}</span>
                </div>
                <div style="font-size:12px;color:#7f8c8d;margin-bottom:6px;">
                    ${t('mon_session_started')}: ${escapeHtml(formatEventTime(s.started_at))} ·
                    ${t('mon_session_duration')}: ${escapeHtml(formatDuration(s.duration_seconds))} ·
                    ${s.ip_address ? '🌐 ' + escapeHtml(s.ip_address) : ''}
                    ${s.device ? ' · 🖥️ ' + deviceText(s.device) : ''}
                </div>
                <div class="log-footer">
                    <span style="padding:3px 9px;border-radius:20px;font-size:10px;font-weight:600;background:${color}22;color:${color};">${escapeHtml(statusLabel)}</span>
                    <span style="font-size:10px;color:#95a5a6;">${escapeHtml(String(s.session_ref || '').slice(0, 8))}…</span>
                </div>
            </div>`;
        }

        function renderSessionsView() {
            if (monLoading && monSessions.items.length === 0) {
                return `<div class="loading-spinner dm-sc"></div><div style="text-align:center;color:#7f8c8d;">${t('mon_loading')}</div>`;
            }
            const rows = monSessions.items.length
                ? monSessions.items.map(sessionCardHtml).join('')
                : `<div class="empty-state">${t('mon_no_sessions')}</div>`;
            return monFilterBarHtml() + rows + monPager(monSessions, 'monSessionsPage');
        }

        function getFilteredLogs() {
            let filtered = [...userLogs];
            if (selectedLogStatus !== 'all') {
                filtered = filtered.filter((log) => log.status === selectedLogStatus);
            }
            const now = new Date();
            if (selectedTimeRange === 'today') {
                const todayStr = now.toISOString().split('T')[0];
                filtered = filtered.filter((log) => log.created_at?.startsWith(todayStr));
            } else if (selectedTimeRange === 'week') {
                const weekAgo = new Date(now.setDate(now.getDate() - 7));
                filtered = filtered.filter((log) => new Date(log.created_at) >= weekAgo);
            } else if (selectedTimeRange === 'month') {
                const monthAgo = new Date(now.setDate(now.getDate() - 30));
                filtered = filtered.filter((log) => new Date(log.created_at) >= monthAgo);
            }
            return filtered.slice(0, 30);
        }

        function latestPruneMessage() {
            const log = userLogs.find((item) => item.action === 'LOGS_AUTO_PRUNED');
            if (!log) return '';
            let details = log.details;
            if (typeof details === 'string') {
                try {
                    details = JSON.parse(details);
                } catch (e) {
                    return '';
                }
            }
            return details && typeof details.message === 'string' ? details.message : '';
        }

        function renderLegacyLogsView() {
            const filtered = getFilteredLogs();
            const pruneMessage = latestPruneMessage();
            return `
                <div style="font-size:12px;color:#7f8c8d;margin-bottom:12px;">${t('mon_legacy_note')}</div>
                ${pruneMessage ? `<div style="background:#eef7ee;border:1px solid #27ae60;border-radius:12px;padding:12px 16px;margin-bottom:12px;font-size:13px;color:#1e6b3a;">${escapeHtml(pruneMessage)}</div>` : ''}
                <div class="section-header"><div class="section-title">${t('system_logs_title', { count: userLogs.length })}</div></div>
                <div class="filter-bar">
                    <div class="filter-buttons" id="logStatusFilters"></div>
                    <div class="filter-buttons" id="timeRangeFilters"></div>
                </div>
                ${filtered.length === 0 ? '<div class="empty-state">' + t('no_records_found') + '</div>' :
                    filtered.map((log) => {
                        const color = getStatusColor(log.status);
                        return `
                            <div class="log-card">
                                <div class="log-header">
                                    <span class="log-action">${escapeHtml(log.action)}</span>
                                    <span class="log-time">${formatTimeAgo(log.created_at)}</span>
                                </div>
                                <div class="log-endpoint">${escapeHtml(log.endpoint)}</div>
                                <div class="log-footer">
                                    <span class="log-status" style="padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;background:${color}20;color:${color};">${escapeHtml(String(log.status))}</span>
                                    <span style="font-size:10px;">👤 ${escapeHtml(log.user_email || t('guest'))}</span>
                                </div>
                            </div>
                        `;
                    }).join('')
                }
            `;
        }

        function viewChips() {
            const views = [
                ['timeline', t('tab_activity')],
                ['security', t('tab_security')],
                ['sessions', t('tab_sessions')],
                ['legacy', t('tab_legacy')],
            ];
            return views.map(([value, label]) =>
                `<div class="filter-chip ${logsView === value ? 'active' : ''}" onclick="setLogsView('${value}')">${label}</div>`
            ).join('');
        }

        // The whole page, rebuilt on every state change: header, view chips and
        // the active view. There are no dashboard tabs here — the sidebar is the
        // only navigation.
        function render() {
            const container = document.getElementById('logsContent');
            let body = '';
            if (logsView === 'timeline') body = renderTimelineView();
            else if (logsView === 'security') body = renderSecurityView();
            else if (logsView === 'sessions') body = renderSessionsView();
            else body = renderLegacyLogsView();

            container.innerHTML = `
                <div class="sa-page-head">
                    <div>
                        <div class="sa-page-title">${t('logs_title')}</div>
                        <div class="sa-page-sub">${t('logs_subtitle')}</div>
                    </div>
                    <div class="sa-page-actions">
                        <button type="button" class="sa-btn" onclick="refreshLogs()">${t('refresh')}</button>
                    </div>
                </div>
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title">${t('mon_timeline_title')}</div>
                        <div class="filter-buttons">
                            ${viewChips()}
                            ${logsView !== 'legacy' ? `<div class="filter-chip" onclick="exportEvents()" style="cursor:pointer;">${t('mon_export')}</div>` : ''}
                        </div>
                    </div>
                    ${monUnavailable && logsView !== 'legacy' ? monSetupBannerHtml() : ''}
                    ${body}
                </div>
            `;

            // Legacy filters are plain chips built after the markup exists.
            if (logsView === 'legacy') {
                const statusHost = document.getElementById('logStatusFilters');
                if (statusHost) {
                    const statuses = [['all', t('filter_all_status')], ['success', t('filter_success')], ['failed', t('filter_failed')]];
                    statusHost.innerHTML = statuses.map(([id, label]) =>
                        `<div class="filter-chip ${selectedLogStatus === id ? 'active' : ''}" onclick="setLogStatus('${id}')">${label}</div>`
                    ).join('');
                }
                const rangeHost = document.getElementById('timeRangeFilters');
                if (rangeHost) {
                    const ranges = [['today', t('filter_today')], ['week', t('filter_week')], ['month', t('filter_month')]];
                    rangeHost.innerHTML = ranges.map(([id, label]) =>
                        `<div class="filter-chip ${selectedTimeRange === id ? 'active' : ''}" onclick="setTimeRange('${id}')">${label}</div>`
                    ).join('');
                }
            }
        }

        window.setLogStatus = (status) => { selectedLogStatus = status; render(); };
        window.setTimeRange = (range) => { selectedTimeRange = range; render(); };
        window.refreshLogs = () => {
            if (logsView === 'legacy') { fetchUserLogs().then(render); return; }
            monRunLoad();
        };

        /* ------------------- detail / incident / session modals -------------- */

        function showMonDetail(title) {
            document.getElementById('monDetailTitle').textContent = title;
            document.getElementById('monDetailBody').innerHTML = `<div class="loading-spinner dm-sc"></div>`;
            document.getElementById('monDetailModal').style.display = 'flex';
        }
        window.closeMonDetail = function () {
            document.getElementById('monDetailModal').style.display = 'none';
            monDetailRerender = null;
        };
        function detailRow(label, value) {
            return `<div class="detail-row"><div class="detail-label">${label}</div><div class="detail-value">${value}</div></div>`;
        }
        function sessionStatusLabel(status) {
            return { active: t('mon_session_status_active'), ended: t('mon_session_status_ended'), expired: t('mon_session_status_expired') }[status] || status || '';
        }

        window.openEventDetail = async function (id) {
            showMonDetail(t('mon_detail_title'));
            try {
                const data = await monFetch('admin/monitoring/events/' + encodeURIComponent(id));
                renderEventDetail(data);
            } catch (e) {
                document.getElementById('monDetailBody').innerHTML = `<div class="empty-state">${t('mon_unavailable')}</div>`;
                monDetailRerender = null;
            }
        };

        function renderEventDetail(data) {
            const ev = (data && data.event) || {};
            const related = (data && data.related) || [];
            const user = ev.user ? (ev.user.full_name || ev.user.email) + (ev.user.role ? ' · ' + ev.user.role : '') : t('mon_guest');
            const device = ev.device
                ? escapeHtml(ev.device.display || '') + (ev.device.model ? ' · ' + t('mon_unknown_model') : '')
                : t('mon_device_unknown');
            const metaJson = ev.metadata ? JSON.stringify(ev.metadata, null, 2) : null;
            const relatedHtml = related.length ? related.map((r) => `
                <div class="log-card" style="cursor:pointer;padding:10px;" onclick="openEventDetail(${jsq(r.id)})">
                    <div style="display:flex;justify-content:space-between;gap:8px;">
                        <b style="font-size:12px;">${escapeHtml(r.event_name || '')}</b>
                        <span style="font-size:10px;color:#95a5a6;">${escapeHtml(formatEventTime(r.created_at))}</span>
                    </div>
                    <div style="font-size:12px;color:#7f8c8d;">${escapeHtml(r.description || '')}</div>
                </div>`).join('') : `<div style="font-size:12px;color:#95a5a6;">${t('mon_related_empty')}</div>`;

            document.getElementById('monDetailBody').innerHTML = `
                <div style="margin-bottom:14px;display:flex;gap:6px;flex-wrap:wrap;">
                    ${severityBadge(ev.severity)} ${outcomeBadge(ev.outcome)}
                    <span class="badge" style="background:#3498db20;color:#3498db;">${escapeHtml(sourceLabel(ev.source))}</span>
                    ${ev.is_security ? '<span class="badge" style="background:#e74c3c20;color:#e74c3c;">🛡️ security</span>' : ''}
                </div>
                ${detailRow(t('mon_detail_desc'), escapeHtml(ev.description || ev.event_name || ''))}
                ${detailRow(t('mon_col_event'), escapeHtml(ev.event_name || ''))}
                ${detailRow(t('mon_detail_actor'), escapeHtml(user))}
                ${detailRow(t('mon_detail_time'), escapeHtml(formatEventTime(ev.created_at)))}
                ${detailRow(t('mon_detail_module'), escapeHtml([ev.page, ev.route, ev.element].filter(Boolean).join(' · ') || '-'))}
                ${detailRow(t('mon_detail_device'), device)}
                ${detailRow(t('mon_detail_ip'), escapeHtml(ev.ip_address || '-') + (ev.metadata && ev.metadata.forwarded_for_untrusted ? ' <span style="font-size:10px;color:#95a5a6;">' + t('mon_detail_ip_note') + '</span>' : ''))}
                ${detailRow(t('mon_detail_outcome'), escapeHtml(ev.outcome || ''))}
                ${detailRow(t('mon_detail_severity'), severityBadge(ev.severity) + '<div style="font-size:11px;color:#7f8c8d;margin-top:6px;">' + escapeHtml(ev.severity_reason || '') + '</div>')}
                ${ev.entity_type ? detailRow(t('mon_detail_entity'), escapeHtml(ev.entity_type + (ev.entity_id ? ' · ' + ev.entity_id : ''))) : ''}
                ${ev.correlation_id ? detailRow(t('mon_correlation'), escapeHtml(ev.correlation_id)) : ''}
                ${ev.session_ref ? detailRow(t('mon_detail_session'), `<button class="filter-chip" onclick="openSessionDetail(${jsq(ev.session_ref)})" style="cursor:pointer;">${t('mon_open_session')} ${escapeHtml(String(ev.session_ref).slice(0, 10))}…</button>`) : ''}
                ${metaJson ? detailRow(t('mon_detail_meta'), `<pre style="white-space:pre-wrap;font-size:11px;max-height:200px;overflow:auto;margin:0;">${escapeHtml(metaJson)}</pre>`) : ''}
                <div style="margin-top:16px;"><div style="font-weight:700;margin-bottom:8px;">${t('mon_detail_related')}</div>${relatedHtml}</div>
            `;
            monDetailRerender = () => renderEventDetail(data);
        }

        window.openSessionDetail = async function (ref) {
            showMonDetail(t('mon_detail_session'));
            try {
                const data = await monFetch('admin/monitoring/sessions/' + encodeURIComponent(ref));
                renderSessionDetail(data);
            } catch (e) {
                document.getElementById('monDetailBody').innerHTML = `<div class="empty-state">${t('mon_unavailable')}</div>`;
                monDetailRerender = null;
            }
        };

        function renderSessionDetail(data) {
            const s = (data && data.session) || {};
            const events = (data && data.events) || [];
            const user = s.user ? (s.user.full_name || s.user.email) + (s.user.role ? ' · ' + s.user.role : '') : t('mon_guest');
            const eventsHtml = events.length ? events.map((ev) => `
                <div class="log-card" style="cursor:pointer;padding:10px;" onclick="openEventDetail(${jsq(ev.id)})">
                    <div style="display:flex;justify-content:space-between;gap:8px;">
                        <b style="font-size:12px;">${escapeHtml(ev.event_name || '')}</b>
                        <span style="font-size:10px;color:#95a5a6;">${escapeHtml(formatEventTime(ev.created_at))}</span>
                    </div>
                    <div style="font-size:12px;color:#7f8c8d;">${escapeHtml(ev.description || '')}</div>
                </div>`).join('') : `<div style="font-size:12px;color:#95a5a6;">${t('mon_session_no_events')}</div>`;

            document.getElementById('monDetailBody').innerHTML = `
                ${detailRow(t('mon_detail_actor'), escapeHtml(user))}
                ${detailRow(t('mon_session_started'), escapeHtml(formatEventTime(s.started_at)))}
                ${detailRow(t('mon_session_last_seen'), escapeHtml(formatEventTime(s.last_seen_at)))}
                ${detailRow(t('mon_session_duration'), escapeHtml(formatDuration(s.duration_seconds)))}
                ${detailRow(t('mon_session_status'), escapeHtml(sessionStatusLabel(s.status)) + (s.end_reason ? ' · ' + escapeHtml(s.end_reason) : ''))}
                ${detailRow(t('mon_detail_ip'), escapeHtml(s.ip_address || '-'))}
                ${detailRow(t('mon_detail_device'), s.device ? deviceText(s.device) : t('mon_device_unknown'))}
                <div style="font-size:11px;color:#95a5a6;margin-top:8px;">${t('mon_session_client_note')}</div>
                <div style="margin-top:16px;"><div style="font-weight:700;margin-bottom:8px;">${t('mon_session_events')}</div>${eventsHtml}</div>
                ${s.user_id ? `<button class="filter-chip" style="margin-top:12px;cursor:pointer;" onclick="investigateUser(${jsq(s.user_id)})">${t('mon_investigate')}</button>` : ''}
            `;
            monDetailRerender = () => renderSessionDetail(data);
        }

        window.openAlertDetail = function (id) {
            showMonDetail(t('mon_security_title'));
            const alert = (monAlerts.items || []).find((a) => String(a.id) === String(id));
            if (!alert) {
                document.getElementById('monDetailBody').innerHTML = `<div class="empty-state">${t('mon_no_alerts')}</div>`;
                monDetailRerender = null;
                return;
            }
            renderAlertDetail(alert);
        };

        function renderAlertDetail(a) {
            const notes = Array.isArray(a.notes) ? a.notes : [];
            const notesHtml = notes.length ? notes.map((n) => `
                <div style="border-left:3px solid #3498db;padding:6px 10px;margin-bottom:6px;font-size:12px;background:#f8f9fa;border-radius:0 8px 8px 0;">
                    <b>${escapeHtml(formatEventTime(n.at))}</b> · ${escapeHtml(n.action || '')}<br>${escapeHtml(n.text || '')}
                </div>`).join('') : `<div style="font-size:12px;color:#95a5a6;">${t('mon_alert_no_notes')}</div>`;
            const contextJson = a.context ? JSON.stringify(a.context, null, 2) : null;
            const actions = [];
            if (a.status !== 'acknowledged') actions.push(`<button class="action-btn btn-details" onclick="updateAlertStatus(${jsq(a.id)}, 'acknowledged')">${t('mon_alert_ack')}</button>`);
            if (a.status !== 'resolved') actions.push(`<button class="action-btn btn-enable" onclick="updateAlertStatus(${jsq(a.id)}, 'resolved')">${t('mon_alert_resolve')}</button>`);
            if (a.status === 'resolved') actions.push(`<button class="action-btn btn-disable" onclick="updateAlertStatus(${jsq(a.id)}, 'open')">${t('mon_alert_reopen')}</button>`);
            const color = ({ open: '#e74c3c', acknowledged: '#f39c12', resolved: '#27ae60' })[a.status] || '#95a5a6';

            document.getElementById('monDetailBody').innerHTML = `
                <div style="margin-bottom:14px;display:flex;gap:6px;flex-wrap:wrap;">
                    ${severityBadge(a.severity)}
                    <span class="badge" style="background:${color}22;color:${color};">${escapeHtml(a.status)}</span>
                    <span class="badge" style="background:#95a5a620;color:#555;">${t('mon_alert_count', { count: a.occurrence_count })}</span>
                </div>
                <div style="font-size:14px;font-weight:600;margin-bottom:10px;">${escapeHtml(a.summary || '')}</div>
                ${detailRow(t('mon_alert_rule'), escapeHtml(a.rule || ''))}
                ${detailRow(t('mon_alert_subject'), escapeHtml(a.subject_user ? (a.subject_user.full_name || a.subject_user.email) : String(a.subject || '').replace(/^(user|acct|ip):/, '')))}
                ${detailRow(t('mon_alert_first') + ' → ' + t('mon_alert_last'), escapeHtml(formatEventTime(a.first_seen)) + ' → ' + escapeHtml(formatEventTime(a.last_seen)))}
                ${a.resolution ? detailRow(t('mon_alert_resolution'), escapeHtml(a.resolution)) : ''}
                ${contextJson ? detailRow(t('mon_alert_context'), `<pre style="white-space:pre-wrap;font-size:11px;max-height:160px;overflow:auto;margin:0;">${escapeHtml(contextJson)}</pre>`) : ''}
                <div style="margin-top:14px;">
                    <div style="font-weight:700;margin-bottom:6px;">${t('mon_alert_notes')}</div>
                    ${notesHtml}
                </div>
                <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
                    <textarea id="monNoteInput" rows="2" placeholder="${t('mon_alert_note')}" style="width:100%;padding:10px;border:1px solid #e0e0e0;border-radius:10px;font-size:13px;font-family:inherit;"></textarea>
                    <textarea id="monResolutionInput" rows="2" placeholder="${t('mon_alert_resolution')}" style="width:100%;padding:10px;border:1px solid #e0e0e0;border-radius:10px;font-size:13px;font-family:inherit;"></textarea>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        ${actions.join('')}
                        <button class="action-btn btn-logs" onclick="updateAlertStatus(${jsq(a.id)}, ${jsq(a.status)})">${t('mon_alert_save')}</button>
                    </div>
                </div>
                <div style="margin-top:14px;">
                    <button class="filter-chip" onclick="investigateAlert(${jsq(a.id)})" style="cursor:pointer;">${t('mon_open_full_timeline')}</button>
                </div>
            `;
            monDetailRerender = () => renderAlertDetail(a);
        }

        window.updateAlertStatus = async function (id, status) {
            const noteEl = document.getElementById('monNoteInput');
            const resEl = document.getElementById('monResolutionInput');
            const note = noteEl ? noteEl.value.trim() : '';
            const resolution = resEl ? resEl.value.trim() : '';
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/monitoring/alerts/${encodeURIComponent(id)}/status`, {
                    method: 'POST',
                    headers: monHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ status, note: note || undefined, resolution: resolution || undefined }),
                });
                if (!res.ok) throw new Error('http_' + res.status);
                const data = await res.json().catch(() => ({}));
                showToast(t('mon_alert_saved'), 'success');
                if (data.alert) renderAlertDetail(data.alert);
                monRunLoad();
            } catch (e) {
                showToast(t('mon_alert_save_failed'), 'error');
            }
        };

        window.investigateAlert = function (id) {
            const a = (monAlerts.items || []).find((x) => String(x.id) === String(id));
            closeMonDetail();
            monFilters = { range: '7', q: '', user_id: '', category: '', severity: '', outcome: '', source: '', security: '1' };
            if (a && a.subject_user_id) monFilters.user_id = a.subject_user_id;
            else if (a && a.context && a.context.ip) monFilters.ip = a.context.ip;
            logsView = 'timeline';
            monTimeline.page = 1;
            monRunLoad();
        };

        window.investigateUser = function (userId) {
            closeMonDetail();
            monFilters.user_id = userId;
            logsView = 'timeline';
            monTimeline.page = 1;
            monRunLoad();
        };

        function handleLogout() {
            if (!confirm(t('confirm_logout'))) return;
            ['userToken', 'userData', 'userId', 'userEmail', 'userRole', 'userName', 'userBusiness', 'userStatus'].forEach((key) => localStorage.removeItem(key));
            window.location.href = '/login?role=admin';
        }
        window.handleLogout = handleLogout;

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeMonDetail();
        });

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => render());
        }

        (async function init() {
            if (!checkSystemAdmin()) return;

            // Deep link from the Watumiaji page: ?user=<id> opens that user's
            // events in the timeline from the start.
            const requestedUser = new URLSearchParams(window.location.search).get('user') || '';

            await Promise.all([fetchUsers(), fetchUserLogs()]);
            if (requestedUser) {
                monFilters.user_id = requestedUser;
                monTimeline.page = 1;
            }
            monRunLoad();
        })();
    </script>
</body>
</html>
@endverbatim
