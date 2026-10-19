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
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])
@include('partials.toast')
@include('partials.photo-viewer')
@verbatim
    <div class="page-content" id="dashboardContent">
        <div class="loading-spinner dm-sc"></div>
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

    <!-- Monitoring detail modal: event details / session view / incident view -->
    <div id="monDetailModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" style="font-weight:700;" id="monDetailTitle">Maelezo</div>
                <span style="cursor:pointer;font-size:24px;" onclick="closeMonDetail()" data-i18n="system_admin_dashboard.close_x">✖</span>
            </div>
            <div class="modal-body" id="monDetailBody"></div>
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
            // ---- Monitoring / audit dashboard (new User Logs experience) ----
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
            mon_user_sessions: 'Vikao vya mtumiaji',
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

        // ---- Monitoring / audit dashboard state ----
        let logsView = 'timeline'; // timeline | security | sessions | legacy
        let monLoading = false;
        let monUnavailable = false;
        let monSummary = null;
        let monTimeline = { items: [], total: 0, page: 1, perPage: 25 };
        let monAlerts = { items: [], total: 0, page: 1, perPage: 25, status: 'open' };
        let monSessions = { items: [], total: 0, page: 1, perPage: 25 };
        let monFilters = { range: '7', q: '', user_id: '', category: '', severity: '', outcome: '', source: '', security: '' };

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
            // Super admin is a database role (promoted by the technician),
            // never a hardcoded email. 'system_admin' is the legacy spelling.
            if (!['super_admin', 'system_admin'].includes(user.role || '')) {
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
                const res = await fetch(`${API_BASE_URL}/api/admin/users?scope=platform`, {
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

        // User activity investigation: server-side monitoring history first
        // (chronological, paged server-side, with sessions); falls back to the
        // legacy local filter when the monitoring tables are not available.
        function viewUserLogs(user) {
            selectedUser = user;
            const modal = document.getElementById('logsModal');
            const body = document.getElementById('logsModalBody');
            body.innerHTML = `<div class="loading-spinner dm-sc"></div><div style="text-align:center;color:#7f8c8d;">${t('mon_loading')}</div>`;
            modal.style.display = 'flex';
            userActivityHtml(user)
                .then((html) => { body.innerHTML = html; })
                .catch(() => { body.innerHTML = userActivityLegacyHtml(user); });
        }

        async function userActivityHtml(user) {
            const data = await monFetch(`admin/monitoring/users/${encodeURIComponent(user.id)}/timeline`, { per_page: 50 });
            if (data.unavailable) throw new Error('unavailable');
            const items = data.items || [];
            const sessions = data.sessions || [];
            const rows = items.length ? items.map(timelineRowHtml).join('') : `<div class="empty-state">${t('mon_no_events')}</div>`;
            const sessionsHtml = sessions.length
                ? `<div style="font-weight:700;margin:12px 0 8px;">${t('mon_sessions_for_user', { count: sessions.length })}</div>${sessions.map(sessionCardHtml).join('')}`
                : '';
            return `
                <div style="margin-bottom:14px;">
                    <div style="font-weight:700;">${escapeHtml(user.full_name || user.email)}</div>
                    <div style="font-size:12px;color:#7f8c8d;">${t('mon_total', { count: data.total })}</div>
                    <button class="filter-chip" style="margin-top:8px;cursor:pointer;" data-dm-track="MON_USER_TIMELINE" onclick="closeLogsModal(); investigateUser(${jsq(user.id)});">${t('mon_open_full_timeline')}</button>
                </div>
                ${sessionsHtml}
                <div style="font-weight:700;margin:14px 0 8px;">${t('mon_timeline_title')}</div>
                ${rows}
            `;
        }

        // Legacy fallback (pre-monitoring records, still written to user_logs).
        function userActivityLegacyHtml(user) {
            userSpecificLogs = userLogs.filter(log => log.user_id === user.id);
            return `
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
                                <span class="log-status" style="background:${getStatusColor(log.status)}20; color:${getStatusColor(log.status)};">${escapeHtml(String(log.status))}</span>
                                <span style="font-size:10px; color:#95a5a6;">${t('label_ip')}${escapeHtml(log.ip_address || t('not_available'))}</span>
                            </div>
                        </div>
                    `).join('')
                }
            `;
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

        /* ============ Monitoring / audit dashboard (new User Logs) ============ */

        const MON_CATEGORIES = ['authentication', 'authorization', 'account', 'admin', 'business', 'ai', 'system', 'page', 'navigation', 'input', 'control', 'search', 'form', 'file', 'session', 'security'];
        let monDetailRerender = null;

        function escapeAttr(str) { return escapeHtml(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
        function jsq(value) { return JSON.stringify(String(value)).replace(/"/g, '&quot;'); }
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
                <button class="filter-chip" style="background:#f39c12;color:#fff;" data-dm-track="MON_SETUP" onclick="runMonSetup()">${t('mon_setup_btn')}</button>
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
                    <button class="filter-chip" ${state.page <= 1 ? 'disabled style="opacity:0.4;"' : ''} data-dm-track="MON_PAGE_PREV" onclick="${action}(${state.page - 1})">◀</button>
                    <span style="font-size:12px;">${t('mon_page_of', { page: state.page, pages: pages })}</span>
                    <button class="filter-chip" ${state.page >= pages ? 'disabled style="opacity:0.4;"' : ''} data-dm-track="MON_PAGE_NEXT" onclick="${action}(${state.page + 1})">▶</button>
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
                <button class="filter-chip active" data-dm-track="MON_FILTER_APPLY" onclick="applyMonFilters()">${t('mon_apply')}</button>
                <button class="filter-chip" data-dm-track="MON_FILTER_RESET" onclick="resetMonFilters()">${t('mon_reset')}</button>
            </div>`;
        }

        function timelineRowHtml(ev) {
            const user = ev.user ? (ev.user.full_name || ev.user.email) : t('mon_guest');
            const module = ev.page || ev.route || '';
            const device = ev.device ? deviceText(ev.device) : '';
            return `<div class="log-card" style="cursor:pointer;border-left:4px solid ${severityColor(ev.severity)};" data-dm-track="MON_EVENT_OPEN" onclick="openEventDetail(${jsq(ev.id)})">
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
            const statusColor = { open: '#e74c3c', acknowledged: '#f39c12', resolved: '#27ae60' }[a.status] || '#95a5a6';
            return `<div class="log-card" style="cursor:pointer;border-left:4px solid ${severityColor(a.severity)};" data-dm-track="MON_ALERT_OPEN" onclick="openAlertDetail(${jsq(a.id)})">
                <div class="log-header">
                    <span class="log-action">${severityBadge(a.severity)} <span style="font-size:12px;">${escapeHtml(a.rule || '')}</span></span>
                    <span class="log-time">${escapeHtml(formatEventTime(a.last_seen))}</span>
                </div>
                <div style="font-size:13px;margin-bottom:6px;">${escapeHtml(a.summary || '')}</div>
                <div class="log-footer">
                    <span style="font-size:11px;color:#7f8c8d;">${t('mon_alert_subject')}: <b>${escapeHtml(subject)}</b> · ${t('mon_alert_count', { count: a.occurrence_count })}</span>
                    <span style="padding:3px 9px;border-radius:20px;font-size:10px;font-weight:600;background:${statusColor}22;color:${statusColor};">${escapeHtml(statusLabel)}</span>
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
                ['', t('mon_security_all')]
            ];
            const chips = statuses.map(([value, label]) =>
                `<div class="filter-chip ${monAlerts.status === value ? 'active' : ''}" data-dm-track="MON_ALERT_FILTER" onclick="setAlertStatusFilter('${value}')">${label}</div>`
            ).join('');
            const rows = monAlerts.items.length
                ? monAlerts.items.map(alertCardHtml).join('')
                : `<div class="empty-state">${t('mon_no_alerts')}</div>`;
            return `<div class="filter-bar"><div class="filter-buttons">${chips}</div></div>` + rows + monPager(monAlerts, 'monAlertsPage');
        }

        function sessionCardHtml(s) {
            const user = s.user ? (s.user.full_name || s.user.email) : t('mon_guest');
            const statusLabel = { active: t('mon_session_status_active'), ended: t('mon_session_status_ended'), expired: t('mon_session_status_expired') }[s.status] || s.status;
            const statusColor = { active: '#27ae60', ended: '#95a5a6', expired: '#e67e22' }[s.status] || '#95a5a6';
            return `<div class="log-card" style="cursor:pointer;border-left:4px solid ${statusColor};" data-dm-track="MON_SESSION_OPEN" onclick="openSessionDetail(${jsq(s.session_ref)})">
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
                    <span style="padding:3px 9px;border-radius:20px;font-size:10px;font-weight:600;background:${statusColor}22;color:${statusColor};">${escapeHtml(statusLabel)}</span>
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

        function renderLegacyLogsView() {
            const filtered = getFilteredLogs();
            return `
                <div style="font-size:12px;color:#7f8c8d;margin-bottom:12px;">${t('mon_legacy_note')}</div>
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
            `;
        }

        function renderLogsTab() {
            const views = [
                ['timeline', '🛡️ ' + t('tab_activity')],
                ['security', '⚠️ ' + t('tab_security')],
                ['sessions', '🔐 ' + t('tab_sessions')],
                ['legacy', '📜 ' + t('tab_legacy')]
            ];
            const chips = views.map(([value, label]) =>
                `<div class="filter-chip ${logsView === value ? 'active' : ''}" data-dm-track="MON_VIEW_SWITCH" onclick="setLogsView('${value}')" style="cursor:pointer;">${label}</div>`
            ).join('');

            let body = '';
            if (logsView === 'timeline') body = renderTimelineView();
            else if (logsView === 'security') body = renderSecurityView();
            else if (logsView === 'sessions') body = renderSessionsView();
            else body = renderLegacyLogsView();

            return `
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title">${t('mon_timeline_title')}</div>
                        <div class="filter-buttons">
                            ${chips}
                            <div class="filter-chip" data-dm-track="MON_EXPORT" onclick="exportEvents()" style="cursor:pointer;">${t('mon_export')}</div>
                        </div>
                    </div>
                    ${monUnavailable && logsView !== 'legacy' ? monSetupBannerHtml() : ''}
                    ${body}
                </div>
            `;
        }

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
                <div class="log-card" style="cursor:pointer;padding:10px;" data-dm-track="MON_EVENT_OPEN" onclick="openEventDetail(${jsq(r.id)})">
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
                ${ev.session_ref ? detailRow(t('mon_detail_session'), `<button class="filter-chip" data-dm-track="MON_SESSION_OPEN" onclick="openSessionDetail(${jsq(ev.session_ref)})" style="cursor:pointer;">${t('mon_open_session')} ${escapeHtml(String(ev.session_ref).slice(0, 10))}…</button>`) : ''}
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
                <div class="log-card" style="cursor:pointer;padding:10px;" data-dm-track="MON_EVENT_OPEN" onclick="openEventDetail(${jsq(ev.id)})">
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
                ${s.user_id ? `<button class="filter-chip" style="margin-top:12px;cursor:pointer;" data-dm-track="MON_USER_INVESTIGATE" onclick="investigateUser(${jsq(s.user_id)})">${t('mon_investigate')}</button>` : ''}
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
            if (a.status !== 'acknowledged') actions.push(`<button class="action-btn btn-details" data-dm-track="MON_ALERT_ACK" onclick="updateAlertStatus(${jsq(a.id)}, 'acknowledged')">${t('mon_alert_ack')}</button>`);
            if (a.status !== 'resolved') actions.push(`<button class="action-btn btn-enable" data-dm-track="MON_ALERT_RESOLVE" onclick="updateAlertStatus(${jsq(a.id)}, 'resolved')">${t('mon_alert_resolve')}</button>`);
            if (a.status === 'resolved') actions.push(`<button class="action-btn btn-disable" data-dm-track="MON_ALERT_REOPEN" onclick="updateAlertStatus(${jsq(a.id)}, 'open')">${t('mon_alert_reopen')}</button>`);
            const statusColor = { open: '#e74c3c', acknowledged: '#f39c12', resolved: '#27ae60' }[a.status] || '#95a5a6';

            document.getElementById('monDetailBody').innerHTML = `
                <div style="margin-bottom:14px;display:flex;gap:6px;flex-wrap:wrap;">
                    ${severityBadge(a.severity)}
                    <span class="badge" style="background:${statusColor}22;color:${statusColor};">${escapeHtml(a.status)}</span>
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
                        <button class="action-btn btn-logs" data-dm-track="MON_ALERT_NOTE" onclick="updateAlertStatus(${jsq(a.id)}, ${jsq(a.status)})">💾 ${t('mon_alert_save')}</button>
                    </div>
                </div>
                <div style="margin-top:14px;">
                    <button class="filter-chip" data-dm-track="MON_ALERT_INVESTIGATE" onclick="investigateAlert(${jsq(a.id)})" style="cursor:pointer;">${t('mon_open_full_timeline')}</button>
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
                    body: JSON.stringify({ status, note: note || undefined, resolution: resolution || undefined })
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

        function render() {
            const container = document.getElementById('dashboardContent');
            if (loading) {
                container.innerHTML = `<div class="loading-spinner dm-sc"></div><div style="text-align:center;">${t('loading')}</div>`;
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
            if (activeTab === 'logs' && logsView === 'legacy') {
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

        window.setTab = (tab) => {
            const previous = activeTab;
            activeTab = tab;
            render();
            // Fresh server-side data every time the monitoring tab is opened.
            if (tab === 'logs' && logsView !== 'legacy' && previous !== 'logs') {
                monRunLoad();
            }
        };
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
                if (monDetailRerender && document.getElementById('monDetailModal').style.display === 'flex') monDetailRerender();
            });
        }

        init();
    </script>
</body>
</html>
@endverbatim