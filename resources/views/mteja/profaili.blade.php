@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="mteja_profaili.page_title">Profaili - Dukamkononi Mteja</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        /* Main layout wrapper - sidebar + content */
        .mteja-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR - Persistent navigation */
        .sidebar {
            width: 280px;
            background-color: #ffffff;
            border-right: 1px solid #ecf0f1;
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.05);
        }

        .sidebar-header {
            padding: 30px 24px;
            border-bottom: 1px solid #ecf0f1;
            margin-bottom: 20px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .logo-text h2 {
            font-size: 18px;
            font-weight: 800;
            color: #2c3e50;
        }

        .logo-text p {
            font-size: 12px;
            color: #7f8c8d;
        }

        .nav-items {
            flex: 1;
            padding: 0 16px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            margin-bottom: 8px;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #5d6d7e;
            font-weight: 500;
            text-decoration: none;
        }

        .nav-item:hover {
            background-color: #ede7f6;
        }

        .nav-item.active {
            background-color: #ede7f6;
            color: #667eea;
        }

        .nav-icon {
            font-size: 22px;
            width: 28px;
        }

        .nav-label {
            font-size: 15px;
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 20px 16px;
            border-top: 1px solid #ecf0f1;
            margin-top: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background-color: #ede7f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-weight: bold;
            font-size: 18px;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-size: 14px;
            font-weight: 700;
            color: #2c3e50;
        }

        .user-role {
            font-size: 12px;
            color: #667eea;
            font-weight: 600;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background-color: #f8f9fa;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            color: #e74c3c;
            font-weight: 600;
            font-size: 14px;
        }

        .logout-btn:hover {
            background-color: #fdeaea;
        }

        /* MAIN CONTENT AREA */
        .main-content {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            background-color: #f8f9fa;
            padding: 24px 32px;
        }

        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 200;
            background: white;
            padding: 12px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        /* Page container */
        .page-container {
            max-width: 700px;
            margin: 0 auto;
            width: 100%;
        }

        /* Profile Styles */
        .profile-header {
            background: white;
            padding: 30px;
            border-radius: 24px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .avatar-wrap {
            position: relative;
            width: 90px;
            height: 90px;
            margin: 0 auto 16px;
        }

        .avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-text {
            color: white;
            font-size: 38px;
            font-weight: 700;
        }

        .camera-badge {
            position: absolute;
            right: -4px;
            bottom: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #667eea;
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 2;
        }

        .camera-badge:hover {
            background: #5a6fd6;
        }

        .camera-badge span {
            font-size: 14px;
        }

        #photoUploadInput {
            display: none;
        }

        .upload-progress {
            font-size: 12px;
            color: #667eea;
            text-align: center;
            margin-top: 8px;
        }

        .greeting {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 6px;
        }

        .role {
            font-size: 15px;
            color: #667eea;
            font-weight: 600;
        }

        /* Section */
        .section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
        }

        .info-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .field {
            margin-bottom: 18px;
        }

        .label {
            font-size: 13px;
            font-weight: 600;
            color: #7f8c8d;
            margin-bottom: 4px;
        }

        .value {
            font-size: 15px;
            color: #2c3e50;
            padding: 6px 0;
        }

        .input-field {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }

        .input-field:focus {
            outline: none;
            border-color: #667eea;
        }

        .role-badge {
            background: #e3f2fd;
            color: #1976d2;
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-inactive {
            background: #ffebee;
            color: #c62828;
        }

        /* Setting Row */
        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .setting-info {
            flex: 1;
            margin-right: 15px;
        }

        .setting-label {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .setting-description {
            font-size: 12px;
            color: #7f8c8d;
        }

        /* Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #667eea;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        /* Buttons */
        .button-row {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .btn {
            flex: 1;
            padding: 14px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            text-align: center;
        }

        .btn-edit {
            background: #667eea;
            color: white;
        }

        .btn-cancel {
            background: #95a5a6;
            color: white;
        }

        .btn-save {
            background: #27ae60;
            color: white;
        }

        .btn-logout {
            background: #e74c3c;
            color: white;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            cursor: pointer;
        }

        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 20px;
        }

        .footer-text {
            font-size: 12px;
            color: #95a5a6;
        }

        .loading-container {
            text-align: center;
            padding: 60px 20px;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #e0e0e0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            color: #7f8c8d;
            font-size: 14px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px 16px;
                padding-top: 70px;
            }
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.toast')
@include('partials.photo-viewer')
@include('partials.cloudinary-config')
@verbatim
    <!-- Mobile menu toggle -->
    <div class="mobile-menu-toggle" id="mobileMenuToggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="2">
            <path d="M3 12H21M3 6H21M3 18H21"/>
        </svg>
    </div>

    <div class="mteja-layout">
        <!-- SIDEBAR - Persistent navigation -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-area">
                    <div class="logo-icon" data-i18n="mteja_profaili.logo_short">D</div>
                    <div class="logo-text">
                        <h2 data-i18n="mteja_profaili.logo_brand">DukaMkononi</h2>
                        <p data-i18n="mteja_profaili.logo_tagline">Mteja Portal</p>
                    </div>
                </div>
            </div>

            <div class="nav-items">
                <a href="biashara" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 9L4.5 3.5H19.5L21 9" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4.5 9V20.5H19.5V9"/>
                            <path d="M3 9H21"/>
                            <path d="M9.5 20.5V14.5H14.5V20.5"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="mteja_profaili.nav_business">Biashara</span>
                </a>
                <a href="matangazo" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 10V14M3 10L13 5V19L3 14" stroke-linejoin="round"/>
                            <path d="M13 7C15.5 7 17.5 9.2 17.5 12C17.5 14.8 15.5 17 13 17" stroke-linecap="round"/>
                            <path d="M16 16.5L17.5 20.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="mteja_profaili.nav_ads">Matangazo</span>
                </a>
                <a href="profaili" class="nav-item active">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21C4 16.6 7.6 13 12 13C16.4 13 20 16.6 20 21" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="mteja_profaili.nav_profile">Profaili</span>
                </a>
            </div>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar">M</div>
                    <div class="user-details">
                        <div class="user-name" id="userName">Mteja</div>
                        <div class="user-role" data-i18n="mteja_profaili.nav_customer">Mteja</div>
                    </div>
                </div>
                <div class="logout-btn" id="logoutBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M15 3H19C20.1 3 21 3.9 21 5V19C21 20.1 20.1 21 19 21H15M10 17L15 12L10 7M15 12H3"/>
                    </svg>
                    <span data-i18n="mteja_profaili.btn_logout">Ondoka</span>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="page-container" id="profileContent">
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <div class="loading-text" data-i18n="mteja_profaili.loading">Inapakua wasifu...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // ============================================
        // PROFAILI SCREEN - Mteja Web Replica
        // With Sidebar Integration
        // ============================================
        
        const API_BASE_URL = '';

        // Swahili fallback used when the language widget is not present (and
        // for script-built strings). Values mirror the sw catalog of the
        // mteja_profaili section in locales.json.
        const SW = {
            page_title: 'Profaili - Dukamkononi Mteja',
            logo_short: 'D',
            logo_brand: 'DukaMkononi',
            logo_tagline: 'Mteja Portal',
            nav_business: 'Biashara',
            nav_ads: 'Matangazo',
            nav_profile: 'Profaili',
            nav_customer: 'Mteja',
            btn_logout: 'Ondoka',
            loading: 'Inapakua wasifu...',
            photo_alt: 'Picha',
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la Mteja.',
            err_signin_view: 'Tafadhali ingia kwenye akaunti yako kuona wasifu',
            err_load_profile: 'Imeshindwa kupakua wasifu',
            err_load_profile_info: 'Imeshindwa kupakua taarifa za wasifu',
            err_name_required: 'Jina kamili linahitajika',
            err_phone_invalid: 'Tafadhali weka nambari ya simu sahihi',
            err_signin_update: 'Tafadhali ingia kwenye akaunti yako kusasisha wasifu',
            msg_profile_updated: 'Wasifu umesasishwa kikamilifu!',
            err_update_failed: 'Imeshindwa kusasisha wasifu',
            confirm_logout: 'Una uhakika unataka kutoka?',
            msg_uploading_photo: 'Inapakia picha...',
            err_upload_photo: 'Imeshindwa kupakia picha',
            msg_photo_updated: 'Picha ya wasifu imesasishwa!',
            err_prefix: 'Hitilafu: ',
            err_upload_prefix: 'Imeshindikana kupakia picha: ',
            try_again: 'jaribu tena',
            not_available: 'Haipatikani',
            status_approved: 'imeidhinishwa',
            status_pending: 'inasubiri',
            role_user: 'Mtumiaji',
            role_admin: 'Msimamizi',
            not_set: 'Haijawekwa',
            no_profile_info: 'Hakuna taarifa za wasifu',
            btn_try_again: 'Jaribu Tena',
            btn_change_photo: 'Badilisha picha',
            greeting: 'Habari, {name}!',
            section_profile_info: 'Taarifa za Wasifu',
            label_full_name: 'Jina Kamili',
            placeholder_full_name: 'Weka jina lako kamili',
            label_email: 'Barua Pepe',
            label_phone: 'Nambari ya Simu',
            placeholder_phone: 'Weka nambari yako ya simu',
            label_role: 'Wadhifa',
            label_status: 'Hali',
            label_business_location: 'Eneo la Biashara',
            label_map_coords: 'Koordineti za Ramani',
            label_member_since: 'Mwanachama Tangu',
            section_settings: 'Mipangilio',
            setting_notifications: 'Arifa za Kujiongeza',
            setting_notifications_desc: 'Pokewa taarifa kuhusu matangazo mapya na promosheni',
            btn_cancel: 'Ghairi',
            btn_edit_profile: 'Hariri Wasifu',
            btn_saving: 'Inahifadhi...',
            btn_save: 'Hifadhi',
            section_account_actions: 'Vitendo vya Akaunti',
            btn_logout_account: 'Toka kwenye Akaunti',
            footer_copyright: 'Duka Mkononi © 2026',
            version: 'Toleo 1.0.0',
        };

        function t(key, params) {
            const full = key.indexOf('mteja_profaili.') === 0 ? key : 'mteja_profaili.' + key;
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

        // Date locale follows the active language (member-since date).
        const DATE_LOCALES = { sw: 'sw-TZ', en: 'en-GB', fr: 'fr-FR', hi: 'hi-IN', es: 'es-ES', ur: 'ur-PK', de: 'de-DE', zh: 'zh-CN' };
        function dateLocale() {
            const code = (window.DM && typeof window.DM.locale === 'function' && window.DM.locale()) || document.documentElement.getAttribute('data-dm-locale') || 'sw';
            return DATE_LOCALES[code] || 'sw-TZ';
        }

        // ============================== ICONS ==============================
        // Font Awesome 6 icon helper (same convention as msimamizi/muuzaji).
        const FA_MAP = {
            store: 'fa-store', megaphone: 'fa-bullhorn', user: 'fa-user',
            camera: 'fa-camera', logout: 'fa-arrow-right-from-bracket',
            save: 'fa-floppy-disk', spinner: 'fa-spinner', edit: 'fa-pen-to-square',
            warning: 'fa-triangle-exclamation', refresh: 'fa-rotate-right'
        };
        function ic(name, size = 16, cls = '') {
            return `<i class="fa-solid ${FA_MAP[name] || 'fa-circle-info'} ${cls}" style="font-size:${size}px;" aria-hidden="true"></i>`;
        }

        let profile = null;
        let loading = true;
        let editing = false;
        let saving = false;
        let notifications = true;
        let formData = { full_name: '', phone: '' };

        // DOM Elements
        const container = document.getElementById('profileContent');

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        function formatDate(dateStr) {
            if (!dateStr) return t('not_available');
            try {
                return new Date(dateStr).toLocaleDateString(dateLocale(), {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            } catch {
                return dateStr;
            }
        }

        function getRoleName(role) {
            if (role === 'customer') return t('nav_customer');
            if (role === 'admin') return t('role_admin');
            return role || t('role_user');
        }

        function getStatusName(status) {
            if (status === 'approved') return t('status_approved');
            if (status === 'pending') return t('status_pending');
            return status || t('status_pending');
        }

        function getStatusClass(status) {
            if (status === 'approved') return 'status-approved';
            if (status === 'pending') return 'status-pending';
            return 'status-inactive';
        }

        // Get current user from localStorage
        function getCurrentUser() {
            const token = localStorage.getItem('userToken');
            const userData = localStorage.getItem('userData');
            if (token && userData) {
                try {
                    return JSON.parse(userData);
                } catch(e) {
                    return null;
                }
            }
            return null;
        }

        // Update sidebar user info
        function updateSidebarUser() {
            const user = getCurrentUser();
            if (user) {
                const displayName = user.full_name || user.email?.split('@')[0] || t('nav_customer');
                document.getElementById('userName').innerHTML = escapeHtml(displayName);
                const avatarEl = document.getElementById('userAvatar');
                if (user.business_logo_url) {
                    avatarEl.classList.add('js-avatar-view');
avatarEl.setAttribute('data-full', user.business_logo_url);
avatarEl.setAttribute('data-name', displayName);
avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="${t('photo_alt')}">`;
                } else {
                    avatarEl.innerHTML = displayName.charAt(0).toUpperCase();
                }
            }
        }

        // Check authentication
        function checkAuth() {
            const user = getCurrentUser();
            if (!user) {
                window.location.href = '/login?role=mteja';
                return false;
            }
            const userRole = user.role || '';
            if (userRole !== 'customer' && userRole !== 'client' && userRole !== 'mteja') {
                showToast(t('err_no_permission'), 'error');
                window.location.href = '/home';
                return false;
            }
            return true;
        }

        // Load notification settings
        function loadNotificationSettings() {
            try {
                const settings = localStorage.getItem('notificationSettings');
                if (settings) {
                    notifications = JSON.parse(settings);
                }
            } catch (error) {
                console.error('Error loading notification settings:', error);
            }
        }

        function saveNotificationSettings(value) {
            try {
                localStorage.setItem('notificationSettings', JSON.stringify(value));
            } catch (error) {
                console.error('Error saving notification settings:', error);
            }
        }

        // Load user profile
        async function loadUserProfile() {
            const token = localStorage.getItem('userToken');
            
            if (!token) {
                showToast(t('err_signin_view'), 'warning');
                loading = false;
                render();
                return;
            }

            try {
                const response = await fetch(`${API_BASE_URL}/api/user/profile`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                });

                if (response.ok) {
                    const userData = await response.json();
                    profile = userData;
                    formData = {
                        full_name: userData.full_name || '',
                        phone: userData.phone || ''
                    };
                    
                    // Update localStorage with latest info
                    if (userData.full_name) {
                        localStorage.setItem('userName', userData.full_name);
                    }
                } else {
                    throw new Error(t('err_load_profile'));
                }
            } catch (error) {
                console.error('Error loading profile:', error);
                showToast(t('err_load_profile_info'), 'error');
            } finally {
                loading = false;
                render();
            }
        }

        function validateForm() {
            if (!formData.full_name.trim()) {
                showToast(t('err_name_required'), 'warning');
                return false;
            }
            if (formData.phone && !/^[0-9+\-\s()]{10,}$/.test(formData.phone)) {
                showToast(t('err_phone_invalid'), 'warning');
                return false;
            }
            return true;
        }

        async function handleSaveProfile() {
            if (!validateForm()) return;

            saving = true;
            render();

            const token = localStorage.getItem('userToken');
            
            if (!token) {
                showToast(t('err_signin_update'), 'warning');
                saving = false;
                render();
                return;
            }

            try {
                const response = await fetch(`${API_BASE_URL}/api/user/profile`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(formData),
                });

                if (response.ok) {
                    const result = await response.json();
                    profile = result.user;
                    editing = false;
                    showToast(t('msg_profile_updated'), 'success');
                    
                    if (result.user.full_name) {
                        localStorage.setItem('userName', result.user.full_name);
                        updateSidebarUser();
                    }
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || t('err_update_failed'));
                }
            } catch (error) {
                console.error('Error updating profile:', error);
                showToast(error.message || t('err_update_failed'), 'error');
            } finally {
                saving = false;
                render();
            }
        }

        function handleEditToggle() {
            if (editing) {
                formData = {
                    full_name: profile?.full_name || '',
                    phone: profile?.phone || ''
                };
            }
            editing = !editing;
            render();
        }

        function toggleNotifications(value) {
            notifications = value;
            saveNotificationSettings(value);
            render();
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
                localStorage.removeItem('notificationSettings');
                window.location.href = '/login?role=mteja';
            }
        }

        // Cloudinary Photo Upload
        const CLOUDINARY_CLOUD_NAME = (window.CLOUDINARY_CONFIG ? window.CLOUDINARY_CONFIG.cloudName : '');
        const CLOUDINARY_UPLOAD_PRESET = 'react_native_uploads';

        function setupPhotoUpload() {
            const cameraBadge = document.getElementById('cameraBadge');
            const photoInput = document.getElementById('photoUploadInput');
            
            if (cameraBadge && photoInput) {
                cameraBadge.addEventListener('click', () => photoInput.click());
                photoInput.addEventListener('change', handlePhotoUpload);
            }
        }

        let uploadingPhoto = false;

        async function handlePhotoUpload(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            if (uploadingPhoto) return; // one upload at a time
            uploadingPhoto = true;

            const progressEl = document.getElementById('uploadProgress');
            const cameraBadge = document.getElementById('cameraBadge');
            if (progressEl) progressEl.textContent = t('msg_uploading_photo');
            if (cameraBadge) {
                cameraBadge.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="font-size:12px;color:white;" aria-hidden="true"></i>';
                cameraBadge.style.pointerEvents = 'none';
            }

            const restoreBadge = () => {
                if (cameraBadge) {
                    cameraBadge.innerHTML = ic('camera', 13);
                    cameraBadge.style.pointerEvents = 'auto';
                }
            };

            try {
                const formDataUpload = new FormData();
                formDataUpload.append('file', file);
                formDataUpload.append('upload_preset', CLOUDINARY_UPLOAD_PRESET);

                // NB: Cloudinary's upload API version is v1_1 (v1_0 does not
                // exist — every upload 404'd and the photo never saved).
                const response = await fetch(
                    `https://api.cloudinary.com/v1_1/${CLOUDINARY_CLOUD_NAME}/image/upload`,
                    { method: 'POST', body: formDataUpload }
                );

                if (!response.ok) {
                    const errBody = await response.json().catch(() => ({}));
                    throw new Error(errBody?.error?.message || t('err_upload_photo'));
                }

                const data = await response.json();
                const imageUrl = data.secure_url;

                // Update profile with new logo URL
                const token = localStorage.getItem('userToken');
                const updateResponse = await fetch(`${API_BASE_URL}/api/user/profile`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ business_logo_url: imageUrl }),
                });

                if (updateResponse.ok) {
                    const result = await updateResponse.json();
                    profile = result.user;
                    // Update localStorage
                    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
                    userData.business_logo_url = imageUrl;
                    localStorage.setItem('userData', JSON.stringify(userData));
                    updateSidebarUser();
                    if (progressEl) progressEl.textContent = '';
                    showToast(t('msg_photo_updated'), 'success');
                    render();
                } else {
                    const errBody = await updateResponse.json().catch(() => ({}));
                    throw new Error(errBody?.error?.message || t('err_update_failed'));
                }
            } catch (error) {
                console.error('Photo upload error:', error);
                if (progressEl) progressEl.textContent = t('err_prefix') + error.message;
                showToast(t('err_upload_prefix') + (error.message || t('try_again')), 'error');
            } finally {
                uploadingPhoto = false;
                restoreBadge();
            }
        }

        // Render the UI
        function render() {
            if (loading) {
                container.innerHTML = `
                    <div class="loading-container">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">${t('loading')}</div>
                    </div>
                `;
                return;
            }

            if (!profile) {
                container.innerHTML = `
                    <div class="loading-container">
                        <div class="loading-text">${t('no_profile_info')}</div>
                        <button onclick="location.reload()" style="margin-top:20px;padding:10px 20px;background:#667eea;color:white;border:none;border-radius:10px;cursor:pointer;">${t('btn_try_again')}</button>
                    </div>
                `;
                return;
            }

            const displayName = profile.full_name || profile.email?.split('@')[0] || t('role_user');
            const initial = (profile.full_name?.charAt(0) || profile.email?.charAt(0) || 'U').toUpperCase();
            const roleName = getRoleName(profile.role);
            const statusName = getStatusName(profile.status);
            const statusClass = getStatusClass(profile.status);
            const createdAt = formatDate(profile.created_at);

            container.innerHTML = `
                <div class="profile-header">
                    <div class="avatar-wrap">
                        <div class="avatar">
                            ${profile.business_logo_url ? `<div class="js-avatar-view" data-full="${escapeHtml(profile.business_logo_url)}" data-name="${escapeHtml(profile.full_name || profile.email || '')}" style="width:100%;height:100%;border-radius:50%;overflow:hidden;"><img src="${escapeHtml(profile.business_logo_url)}" alt="Profile" style="width:100%;height:100%;object-fit:cover;pointer-events:none;"></div>` : `<div class="avatar-text">${escapeHtml(initial)}</div>`}
                        </div>
                        <div class="camera-badge" id="cameraBadge" title="${t('btn_change_photo')}">
                            ${ic('camera', 13)}
                        </div>
                        <input type="file" id="photoUploadInput" accept="image/*">
                    </div>
                    <div class="upload-progress" id="uploadProgress"></div>
                    <div class="greeting">${t('greeting', { name: escapeHtml(displayName) })}</div>
                    <div class="role">${escapeHtml(roleName)}</div>
                </div>

                <div class="section">
                    <div class="section-title">${t('section_profile_info')}</div>
                    <div class="info-card">
                        <div class="field">
                            <div class="label">${t('label_full_name')}</div>
                            ${editing ? 
                                `<input type="text" id="fullNameInput" class="input-field" value="${escapeHtml(formData.full_name)}" placeholder="${t('placeholder_full_name')}">` :
                                `<div class="value">${escapeHtml(profile.full_name || t('not_set'))}</div>`
                            }
                        </div>

                        <div class="field">
                            <div class="label">${t('label_email')}</div>
                            <div class="value">${escapeHtml(profile.email)}</div>
                        </div>

                        <div class="field">
                            <div class="label">${t('label_phone')}</div>
                            ${editing ? 
                                `<input type="tel" id="phoneInput" class="input-field" value="${escapeHtml(formData.phone)}" placeholder="${t('placeholder_phone')}">` :
                                `<div class="value">${escapeHtml(profile.phone || t('not_set'))}</div>`
                            }
                        </div>

                        <div class="field">
                            <div class="label">${t('label_role')}</div>
                            <div><span class="role-badge">${escapeHtml(roleName)}</span></div>
                        </div>

                        <div class="field">
                            <div class="label">${t('label_status')}</div>
                            <div><span class="status-badge ${statusClass}">${escapeHtml(statusName)}</span></div>
                        </div>

                        ${profile.business_location ? `<div class="field"><div class="label">${t('label_business_location')}</div><div class="value">${escapeHtml(profile.business_location)}</div></div>` : ''}
                        ${profile.business_latitude && profile.business_longitude ? `<div class="field"><div class="label">${t('label_map_coords')}</div><div class="value">${escapeHtml(String(profile.business_latitude))}, ${escapeHtml(String(profile.business_longitude))}</div></div>` : ''}

                        <div class="field">
                            <div class="label">${t('label_member_since')}</div>
                            <div class="value">${escapeHtml(createdAt)}</div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">${t('section_settings')}</div>
                    <div class="info-card">
                        <div class="setting-row">
                            <div class="setting-info">
                                <div class="setting-label">${t('setting_notifications')}</div>
                                <div class="setting-description">${t('setting_notifications_desc')}</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="notificationSwitch" ${notifications ? 'checked' : ''}>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="button-row">
                            <button class="btn ${editing ? 'btn-cancel' : 'btn-edit'}" id="editBtn">
                                ${editing ? t('btn_cancel') : ic('edit', 13) + ' ' + t('btn_edit_profile')}
                            </button>
                            ${editing ? `
                                <button class="btn btn-save" id="saveBtn" ${saving ? 'disabled' : ''}>
                                    ${saving ? ic('spinner', 13, 'fa-spin') + ' ' + t('btn_saving') : t('btn_save')}
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">${t('section_account_actions')}</div>
                    <div class="info-card">
                        <button class="btn-logout" id="logoutAccountBtn">${ic('logout', 14)} ${t('btn_logout_account')}</button>
                    </div>
                </div>

                <div class="footer">
                    <div class="footer-text">${t('footer_copyright')}</div>
                    <div class="footer-text" style="font-size: 11px;">${t('version')}</div>
                </div>
            `;

            // Attach event listeners
            if (editing) {
                const fullNameInput = document.getElementById('fullNameInput');
                const phoneInput = document.getElementById('phoneInput');
                if (fullNameInput) {
                    fullNameInput.addEventListener('input', (e) => { formData.full_name = e.target.value; });
                }
                if (phoneInput) {
                    phoneInput.addEventListener('input', (e) => { formData.phone = e.target.value; });
                }
            }

            const editBtn = document.getElementById('editBtn');
            if (editBtn) editBtn.addEventListener('click', handleEditToggle);

            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn) saveBtn.addEventListener('click', handleSaveProfile);

            const notificationSwitch = document.getElementById('notificationSwitch');
            if (notificationSwitch) {
                notificationSwitch.addEventListener('change', (e) => toggleNotifications(e.target.checked));
            }

            const logoutAccountBtn = document.getElementById('logoutAccountBtn');
            if (logoutAccountBtn) logoutAccountBtn.addEventListener('click', handleLogout);

            // Setup photo upload
            setupPhotoUpload();
        }

        // Sidebar functions
        function setupSidebar() {
            const toggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            if (toggle && sidebar) {
                toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
            }
            
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', handleLogout);
            }
            
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    const sidebar = document.getElementById('sidebar');
                    const toggle = document.getElementById('mobileMenuToggle');
                    if (sidebar && sidebar.classList.contains('open') && 
                        !sidebar.contains(e.target) && 
                        toggle && !toggle.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                }
            });
        }

        // Initialize
        async function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
            loadNotificationSettings();
            await loadUserProfile();
        }
        
        // Follow the language: static markup is handled by data-i18n, but the
        // profile card, settings and actions are script-built.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => {
                render();
                updateSidebarUser();
            });
        }

        init();
    </script>
</body>
</html>
@endverbatim