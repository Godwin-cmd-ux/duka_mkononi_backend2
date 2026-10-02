@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="system_admin_notify.page_title">Notisi - DukaMkononi System Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        /* Page content */
        .page-content { max-width: 1000px; margin: 0 auto; width: 100%; }

        /* Header */
        .header {
            background: white; border-radius: 20px; padding: 20px;
            margin-bottom: 20px; display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 16px;
        }
        .header-title { font-size: 22px; font-weight: 800; color: #2c3e50; }
        .header-subtitle { font-size: 12px; color: #7f8c8d; margin-top: 4px; }
        .header-actions { display: flex; gap: 12px; }
        .test-btn {
            background: #2ecc71; color: white; border: none;
            padding: 10px 16px; border-radius: 10px; cursor: pointer;
            display: flex; align-items: center; gap: 8px; font-weight: 600;
        }
        .logout-btn { background: none; border: none; font-size: 24px; cursor: pointer; }

        /* Stats */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px; margin-bottom: 20px;
        }
        .stat-card {
            background: white; border-radius: 16px; padding: 16px;
            text-align: center; border-left: 4px solid #3498db;
        }
        .stat-value { font-size: 24px; font-weight: 800; margin-top: 8px; }
        .stat-label { font-size: 12px; color: #7f8c8d; margin-top: 4px; }

        /* Form Section */
        .form-card {
            background: white; border-radius: 20px; padding: 24px;
            margin-bottom: 20px;
        }
        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; }
        .input-group { margin-bottom: 20px; }
        .input-label { font-size: 14px; font-weight: 600; margin-bottom: 6px; display: block; }
        input, textarea {
            width: 100%; padding: 14px; border: 1px solid #ddd;
            border-radius: 12px; font-size: 14px; font-family: inherit;
        }
        textarea { min-height: 100px; resize: vertical; }
        .char-count { font-size: 11px; color: #95a5a6; text-align: right; margin-top: 4px; }

        /* Type Selector */
        .type-selector {
            display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px;
        }
        .type-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 18px; border-radius: 30px; border: 1.5px solid #3498db;
            background: white; cursor: pointer;
        }
        .type-btn.active { background: #3498db; color: white; border: none; }
        .recipient-count { font-size: 13px; color: #7f8c8d; margin-top: 8px; }

        /* Select Recipients Button */
        .select-recipients {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px; background: #f8f9fa; border-radius: 12px;
            border: 1.5px dashed #3498db; cursor: pointer;
        }
        .send-btn {
            width: 100%; padding: 16px; background: #3498db;
            border: none; border-radius: 14px; color: white;
            font-weight: 700; font-size: 16px; display: flex;
            align-items: center; justify-content: center; gap: 8px;
            cursor: pointer; margin-top: 10px;
        }
        .send-btn.disabled { background: #95a5a6; cursor: not-allowed; }

        /* Notifications List */
        .notif-card {
            background: #f8f9fa; border-radius: 16px; padding: 16px;
            margin-bottom: 12px; border-left: 4px solid #3498db;
        }
        .notif-header { display: flex; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 8px; }
        .notif-title { font-weight: 700; font-size: 16px; }
        .type-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .notif-time { font-size: 11px; color: #95a5a6; }
        .notif-message { font-size: 14px; color: #555; margin-bottom: 12px; line-height: 1.5; }
        .notif-footer { display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #7f8c8d; }

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
        .filter-row { padding: 16px; border-bottom: 1px solid #ecf0f1; }
        .search-box {
            display: flex; align-items: center; background: #f8f9fa;
            border-radius: 25px; padding: 10px 16px; margin-bottom: 12px;
            border: 1px solid #ddd;
        }
        .search-box input { flex: 1; border: none; background: transparent; padding: 0; }
        .toggle-group { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
        .toggle-item { display: flex; align-items: center; gap: 8px; }
        .select-all {
            padding: 10px; background: #e3f2fd; border-radius: 10px;
            text-align: center; cursor: pointer; margin-bottom: 12px;
        }
        .user-list { max-height: 400px; overflow-y: auto; }
        .user-item {
            display: flex; align-items: center; padding: 14px;
            border-bottom: 1px solid #ecf0f1; cursor: pointer;
        }
        .user-item.selected { background: #e3f2fd; }
        .user-checkbox { width: 30px; }
        .user-info { flex: 1; }
        .user-name { font-weight: 600; }
        .user-email { font-size: 12px; color: #7f8c8d; }
        .role-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .done-btn {
            background: #3498db; color: white; text-align: center;
            padding: 16px; margin: 16px; border-radius: 14px; font-weight: 700;
            cursor: pointer;
        }

        .empty-state { text-align: center; padding: 40px; color: #95a5a6; }

        .loading-spinner {
            width: 50px; height: 50px; border: 3px solid #e0e0e0;
            border-top-color: #3498db; border-radius: 50%;
            animation: spin 0.8s linear infinite; margin: 40px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .type-selector { overflow-x: auto; flex-wrap: nowrap; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.toast')
@include('partials.photo-viewer')
@verbatim
    <div class="page-content" id="notifyContent">
        <div class="loading-spinner"></div>
        <div data-i18n="system_admin_notify.loading" style="text-align: center; color: #7f8c8d;">Inapakia...</div>
    </div>

    <!-- Recipient Selection Modal -->
    <div id="recipientModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" data-i18n="system_admin_notify.modal_recipient_title" style="font-weight:700;">Chagua Wapokeaji</div>
                <span data-i18n="system_admin_notify.modal_close" style="cursor:pointer;font-size:24px;" onclick="closeRecipientModal()">✖</span>
            </div>
            <div class="filter-row">
                <div class="toggle-group" id="roleToggles"></div>
                <div class="search-box">
                    <span>🔍</span>
                    <input type="text" id="userSearch" data-i18n="system_admin_notify.search_placeholder" data-i18n-attr="placeholder" placeholder="Tafuta watumiaji...">
                </div>
                <div id="selectAllBtn" class="select-all">✓ Teua Wote</div>
            </div>
            <div id="userListContainer" class="user-list"></div>
            <div id="doneBtn" class="done-btn" onclick="finishRecipientSelection()">Imekamilika (0 wamechaguliwa)</div>
        </div>
    </div>

    <script>
        const API_BASE_URL = '';

        // Swahili fallback used when the language widget is not present.
        // Values mirror the sw catalog of the system_admin_notify section in locales.json.
        const SW = {
            loading: "Inapakia...",
            select_all: "✓ Teua Wote",
            selected_count: "Imekamilika ({count} wamechaguliwa)",
            err_no_permission: "Huna ruhusa ya kuingia kwenye eneo la System Admin.",
            err_title_required: "Tafadhali andika kichwa",
            err_message_required: "Tafadhali andika ujumbe",
            err_recipient_required: "Tafadhali chagua angalau mtumiaji mmoja",
            msg_sent: "Taarifa imetumwa kikamilifu!",
            err_send_failed: "Imeshindwa kutuma taarifa",
            err_network: "Hitilafu ya mtandao",
            filter_all: "Wote",
            filter_admins: "Wasimamizi",
            filter_sellers: "Wauzaji",
            filter_clients: "Wateja",
            filter_custom: "Maalum",
            time_now: "Sasa hivi",
            time_minutes: "Dakika {mins} zilizopita",
            time_hours: "Saa {hours} zilizopita",
            time_yesterday: "Jana",
            time_days: "Siku {days} zilizopita",
            err_pick_recipient: "Chagua angalau mpokeaji mmoja",
            empty_users: "Hakuna watumiaji",
            check_on: "☑️",
            check_off: "⬜",
            role_admin: "Msimamizi",
            role_seller: "Muuzaji",
            role_client: "Mteja",
            btn_clear_all: "✗ Futa Teua Zote",
            btn_send: "📢 Tuma Taarifa",
            header_brand: "Msimamizi Mkuu - Mfumo wa Taarifa",
            btn_test_send: "📧 Tuma Majaribio",
            nav_logout: "🚪",
            stat_sent: "Zimetumwa",
            stat_today: "Leo",
            stat_recipients: "Wapokeaji",
            stat_success: "Mafanikio",
            btn_new: "✏️ Tuma Taarifa Mpya",
            label_title: "Kichwa cha Taarifa",
            placeholder_title: "Andika kichwa hapa...",
            title_counter: "{count}/100",
            label_message: "Ujumbe wa Taarifa",
            placeholder_message: "Andika ujumbe hapa...",
            message_counter: "{count}/500",
            section_recipients: "Wapokeaji wa Taarifa",
            chip_all: "👥 Wote",
            chip_admins: "🛡️ Wasimamizi",
            chip_sellers: "🛒 Wauzaji",
            chip_clients: "👤 Wateja",
            chip_custom: "🎯 Maalum",
            recipients_summary: "📊 Watapokea: {count} watumiaji",
            selected_users_count: "{count} watumiaji wamechaguliwa",
            select_custom_hint: "Chagua watumiaji maalum",
            arrow: "→",
            btn_sending: "⏳ Inatuma...",
            btn_send_footer: "📤 Tuma Taarifa",
            sent_title: "📋 Taarifa Zilizotumwa",
            refresh_icon: "⟳",
            empty_sent: "Hakuna taarifa zilizotumwa bado",
            status_sent: "✓ Imetumwa",
            status_failed: "✗ Imeshindwa",
            test_email_prompt: "Andika barua pepe utakayotumia kupokea taarifa ya majaribio:",
            test_email_sent: "Barua pepe ya majaribio imetumwa kwa {email}",
            test_email_failed: "Imeshindwa kutuma barua pepe ya majaribio",
            confirm_logout: "Una uhakika unataka kutoka?"
        };

        function t(key, params) {
            const full = key.indexOf('system_admin_notify.') === 0 ? key : 'system_admin_notify.' + key;
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

        let loading = true;
        let users = [];
        let notifications = [];
        let stats = { totalSent: 0, sentToday: 0, recipientsCount: 0, successRate: 100 };
        
        let notificationTitle = '';
        let notificationMessage = '';
        let notificationType = 'all';
        let selectedUsers = [];
        let sending = false;
        
        let searchQuery = '';
        let showAdmins = true;
        let showSellers = true;
        let showClients = true;

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

        async function fetchUsers() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/users`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (res.ok) {
                    const data = await res.json();
                    users = (data.users || (Array.isArray(data) ? data : [])).filter(u => u.status === 'approved');
                }
            } catch(e) { console.error(e); }
        }

        async function fetchNotifications() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/notifications`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (res.ok) notifications = await res.json();
            } catch(e) { console.error(e); }
        }

        async function fetchStats() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/notifications/stats`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (res.ok) {
                    const data = await res.json();
                    stats = {
                        totalSent: data.total_sent || 0,
                        sentToday: data.sent_today || 0,
                        recipientsCount: data.total_recipients || 0,
                        successRate: data.success_rate || 100
                    };
                }
            } catch(e) { console.error(e); }
        }

        async function sendNotification() {
            if (!notificationTitle.trim()) { showToast(t('err_title_required'), 'warning'); return; }
            if (!notificationMessage.trim()) { showToast(t('err_message_required'), 'warning'); return; }
            if (notificationType === 'specific' && selectedUsers.length === 0) {
                showToast(t('err_recipient_required'), 'warning');
                return;
            }
            
            sending = true;
            render();
            
            const token = localStorage.getItem('userToken');
            // Server requires translations object with 6+ language keys
            const titleText = notificationTitle;
            const messageText = notificationMessage;
            const translations = {
                sw: { title: titleText, message: messageText },
                en: { title: titleText, message: messageText },
                fr: { title: titleText, message: messageText },
                de: { title: titleText, message: messageText },
                zh: { title: titleText, message: messageText },
                es: { title: titleText, message: messageText },
                hi: { title: titleText, message: messageText },
                ur: { title: titleText, message: messageText }
            };
            const body = {
                translations,
                notification_type: notificationType,
                delivery_method: 'email'
            };
            if (notificationType === 'specific') body.recipient_ids = selectedUsers;
            
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/notifications`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                if (res.ok) {
                    showToast(t('msg_sent'), 'success');
                    notificationTitle = '';
                    notificationMessage = '';
                    notificationType = 'all';
                    selectedUsers = [];
                    await fetchNotifications();
                    await fetchStats();
                } else {
                    showToast(t('err_send_failed'), 'error');
                }
            } catch(e) { showToast(t('err_network'), 'error'); }
            finally { sending = false; render(); }
        }

        function getRecipientCount() {
            if (notificationType === 'all') return users.length;
            if (notificationType === 'admins') return users.filter(u => u.role === 'admin').length;
            if (notificationType === 'sellers') return users.filter(u => u.role === 'seller').length;
            if (notificationType === 'clients') return users.filter(u => u.role === 'client').length;
            if (notificationType === 'specific') return selectedUsers.length;
            return 0;
        }

        function getTypeColor(type) {
            const colors = { all: '#3498db', admins: '#9b59b6', sellers: '#f39c12', clients: '#2ecc71', specific: '#e74c3c' };
            return colors[type] || '#95a5a6';
        }

        function getTypeText(type) {
            const texts = { all: t('filter_all'), admins: t('filter_admins'), sellers: t('filter_sellers'), clients: t('filter_clients'), specific: t('filter_custom') };
            return texts[type] || type;
        }

        function formatTimeAgo(dateStr) {
            if (!dateStr) return '';
            try {
                const date = new Date(dateStr);
                const now = new Date();
                const diffMins = Math.floor((now - date) / 60000);
                if (diffMins < 1) return t('time_now');
                if (diffMins < 60) return t('time_minutes', { mins: diffMins });
                const diffHours = Math.floor(diffMins / 60);
                if (diffHours < 24) return t('time_hours', { hours: diffHours });
                const diffDays = Math.floor(diffHours / 24);
                if (diffDays === 1) return t('time_yesterday');
                if (diffDays < 7) return t('time_days', { days: diffDays });
                return date.toLocaleDateString('sw-TZ');
            } catch { return ''; }
        }

        function getFilteredUsers() {
            return users.filter(u => {
                const matchesSearch = u.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
                    (u.full_name && u.full_name.toLowerCase().includes(searchQuery.toLowerCase()));
                const matchesRole = (showAdmins && u.role === 'admin') ||
                    (showSellers && u.role === 'seller') ||
                    (showClients && u.role === 'client');
                return matchesSearch && matchesRole;
            });
        }

        function openRecipientModal() {
            selectedUsers = [];
            searchQuery = '';
            showAdmins = true; showSellers = true; showClients = true;
            renderRecipientModal();
            document.getElementById('recipientModal').style.display = 'flex';
        }

        function closeRecipientModal() {
            document.getElementById('recipientModal').style.display = 'none';
            render();
        }

        function finishRecipientSelection() {
            if (selectedUsers.length === 0) { showToast(t('err_pick_recipient'), 'warning'); return; }
            closeRecipientModal();
        }

        function toggleUserSelection(userId) {
            if (selectedUsers.includes(userId)) {
                selectedUsers = selectedUsers.filter(id => id !== userId);
            } else {
                selectedUsers.push(userId);
            }
            renderRecipientModal();
        }

        function selectAllUsers() {
            const filtered = getFilteredUsers();
            const allIds = filtered.map(u => u.id);
            if (selectedUsers.length === allIds.length && allIds.length > 0) {
                selectedUsers = [];
            } else {
                selectedUsers = allIds;
            }
            renderRecipientModal();
        }

        function renderRecipientModal() {
            const filtered = getFilteredUsers();
            const container = document.getElementById('userListContainer');
            const selectAllDiv = document.getElementById('selectAllBtn');
            const doneBtn = document.getElementById('doneBtn');
            const toggleContainer = document.getElementById('roleToggles');
            
            if (toggleContainer) {
                toggleContainer.innerHTML = `
                    <div class="toggle-item"><input type="checkbox" id="toggleAdmins" ${showAdmins ? 'checked' : ''}> <label>${t('filter_admins')}</label></div>
                    <div class="toggle-item"><input type="checkbox" id="toggleSellers" ${showSellers ? 'checked' : ''}> <label>${t('filter_sellers')}</label></div>
                    <div class="toggle-item"><input type="checkbox" id="toggleClients" ${showClients ? 'checked' : ''}> <label>${t('filter_clients')}</label></div>
                `;
                document.getElementById('toggleAdmins')?.addEventListener('change', (e) => { showAdmins = e.target.checked; renderRecipientModal(); });
                document.getElementById('toggleSellers')?.addEventListener('change', (e) => { showSellers = e.target.checked; renderRecipientModal(); });
                document.getElementById('toggleClients')?.addEventListener('change', (e) => { showClients = e.target.checked; renderRecipientModal(); });
            }
            
            const searchInput = document.getElementById('userSearch');
            if (searchInput && !searchInput._listener) {
                searchInput._listener = true;
                searchInput.addEventListener('input', (e) => { searchQuery = e.target.value; renderRecipientModal(); });
            }
            
            if (container) {
                container.innerHTML = filtered.length === 0 ? '<div class="empty-state">' + t('empty_users') + '</div>' :
                    filtered.map(u => `
                        <div class="user-item ${selectedUsers.includes(u.id) ? 'selected' : ''}" onclick="toggleUserSelection('${u.id}')">
                            <div class="user-checkbox">${selectedUsers.includes(u.id) ? t('check_on') : t('check_off')}</div>
                            <div class="user-info">
                                <div class="user-name">${escapeHtml(u.full_name || u.email)}</div>
                                <div class="user-email">${escapeHtml(u.email)}</div>
                            </div>
                            <div class="role-badge" style="background:${getTypeColor(u.role)}20; color:${getTypeColor(u.role)}">${u.role === 'admin' ? t('role_admin') : u.role === 'seller' ? t('role_seller') : t('role_client')}</div>
                        </div>
                    `).join('');
            }
            
            if (selectAllDiv) {
                const allFiltered = getFilteredUsers();
                const isAllSelected = selectedUsers.length === allFiltered.length && allFiltered.length > 0;
                selectAllDiv.innerHTML = isAllSelected ? t('btn_clear_all') : t('select_all');
                selectAllDiv.onclick = selectAllUsers;
            }
            
            if (doneBtn) doneBtn.innerHTML = t('selected_count', { count: selectedUsers.length });
        }

        function render() {
            const container = document.getElementById('notifyContent');
            if (loading) {
                container.innerHTML = `<div class="loading-spinner"></div><div style="text-align:center;">${t('loading')}</div>`;
                return;
            }
            
            const recipientCount = getRecipientCount();
            const isFormValid = notificationTitle.trim() && notificationMessage.trim() && (notificationType !== 'specific' || selectedUsers.length > 0);
            
            container.innerHTML = `
                <div class="header">
                    <div><div class="header-title">${t('btn_send')}</div><div class="header-subtitle">${t('header_brand')}</div></div>
                    <div class="header-actions">
                        <button class="test-btn" onclick="sendTestNotification()">${t('btn_test_send')}</button>
                        <button class="logout-btn" onclick="handleLogout()">${t('nav_logout')}</button>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card"><span>📨</span><div class="stat-value">${stats.totalSent}</div><div class="stat-label">${t('stat_sent')}</div></div>
                    <div class="stat-card"><span>📅</span><div class="stat-value">${stats.sentToday}</div><div class="stat-label">${t('stat_today')}</div></div>
                    <div class="stat-card"><span>👥</span><div class="stat-value">${stats.recipientsCount}</div><div class="stat-label">${t('stat_recipients')}</div></div>
                    <div class="stat-card"><span>📈</span><div class="stat-value">${stats.successRate}%</div><div class="stat-label">${t('stat_success')}</div></div>
                </div>
                
                <div class="form-card">
                    <div class="section-title">${t('btn_new')}</div>
                    <div class="input-group"><label class="input-label">${t('label_title')}</label><input type="text" id="notifTitle" placeholder="${t('placeholder_title')}" maxlength="100"><div class="char-count" id="titleCount">0/100</div></div>
                    <div class="input-group"><label class="input-label">${t('label_message')}</label><textarea id="notifMessage" placeholder="${t('placeholder_message')}" maxlength="500"></textarea><div class="char-count" id="msgCount">0/500</div></div>
                    <div class="input-group"><label class="input-label">${t('section_recipients')}</label>
                        <div class="type-selector">
                            <div class="type-btn ${notificationType === 'all' ? 'active' : ''}" onclick="setType('all')">${t('chip_all')}</div>
                            <div class="type-btn ${notificationType === 'admins' ? 'active' : ''}" onclick="setType('admins')">${t('chip_admins')}</div>
                            <div class="type-btn ${notificationType === 'sellers' ? 'active' : ''}" onclick="setType('sellers')">${t('chip_sellers')}</div>
                            <div class="type-btn ${notificationType === 'clients' ? 'active' : ''}" onclick="setType('clients')">${t('chip_clients')}</div>
                            <div class="type-btn ${notificationType === 'specific' ? 'active' : ''}" onclick="setType('specific')">${t('chip_custom')}</div>
                        </div>
                        <div class="recipient-count">${t('recipients_summary', { count: '<strong>' + recipientCount + '</strong>' })}</div>
                    </div>
                    ${notificationType === 'specific' ? `
                        <div class="select-recipients" onclick="openRecipientModal()">
                            <span>👥 ${selectedUsers.length > 0 ? t('selected_users_count', { count: selectedUsers.length }) : t('select_custom_hint')}</span>
                            <span>${t('arrow')}</span>
                        </div>
                    ` : ''}
                    <button class="send-btn ${!isFormValid || sending ? 'disabled' : ''}" id="sendNotifBtn" onclick="sendNotification()" ${!isFormValid || sending ? 'disabled' : ''}>
                        ${sending ? t('btn_sending') : t('btn_send_footer')}
                    </button>
                </div>
                
                <div class="form-card">
                    <div class="section-header" style="display:flex; justify-content:space-between;">
                        <div class="section-title">${t('sent_title')}</div>
                        <button onclick="refreshData()" style="background:none; border:none; font-size:18px; cursor:pointer;">${t('refresh_icon')}</button>
                    </div>
                    ${notifications.length === 0 ? '<div class="empty-state">' + t('empty_sent') + '</div>' :
                        notifications.map(n => `
                            <div class="notif-card">
                                <div class="notif-header">
                                    <div class="notif-title">${escapeHtml(n.title)}</div>
                                    <div class="type-badge" style="background:${getTypeColor(n.notification_type)}20; color:${getTypeColor(n.notification_type)}">${getTypeText(n.notification_type)}</div>
                                </div>
                                <div class="notif-message">${escapeHtml(n.message)}</div>
                                <div class="notif-footer">
                                    <span>${formatTimeAgo(n.sent_at)}</span>
                                    <span>${n.status === 'sent' ? t('status_sent') : t('status_failed')}</span>
                                </div>
                            </div>
                        `).join('')
                    }
                </div>
            `;
            
            // Attach input listeners. Typing must NOT re-render the page —
            // that destroyed the input and threw the cursor out mid-word.
            // State, character counters and the send button's enabled state
            // are updated in place instead.
            const updateSendBtn = () => {
                const btn = document.getElementById('sendNotifBtn');
                if (!btn) return;
                const valid = notificationTitle.trim() && notificationMessage.trim() && (notificationType !== 'specific' || selectedUsers.length > 0);
                btn.disabled = !valid || sending;
                btn.classList.toggle('disabled', !valid || sending);
            };
            const titleInput = document.getElementById('notifTitle');
            const msgInput = document.getElementById('notifMessage');
            if (titleInput) {
                titleInput.value = notificationTitle;
                titleInput.addEventListener('input', (e) => {
                    notificationTitle = e.target.value;
                    const cc = document.getElementById('titleCount');
                    if (cc) cc.innerText = t('title_counter', { count: notificationTitle.length });
                    updateSendBtn();
                });
                document.getElementById('titleCount').innerText = t('title_counter', { count: notificationTitle.length });
            }
            if (msgInput) {
                msgInput.value = notificationMessage;
                msgInput.addEventListener('input', (e) => {
                    notificationMessage = e.target.value;
                    const cc = document.getElementById('msgCount');
                    if (cc) cc.innerText = t('message_counter', { count: notificationMessage.length });
                    updateSendBtn();
                });
                document.getElementById('msgCount').innerText = t('message_counter', { count: notificationMessage.length });
            }
        }

        window.setType = (type) => { notificationType = type; if (type !== 'specific') selectedUsers = []; render(); };
        window.sendNotification = sendNotification;
        window.openRecipientModal = openRecipientModal;
        window.closeRecipientModal = closeRecipientModal;
        window.finishRecipientSelection = finishRecipientSelection;
        window.toggleUserSelection = toggleUserSelection;
        window.selectAllUsers = selectAllUsers;
        window.refreshData = () => { loadAllData(); };
        
        async function sendTestNotification() {
            const email = prompt(t('test_email_prompt'), '');
            if (!email) return;
            const token = localStorage.getItem('userToken');
            try {
                const res = await fetch(`${API_BASE_URL}/api/admin/notifications/test`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                if (res.ok) showToast(t('test_email_sent', { email }), 'success');
                else showToast(t('test_email_failed'), 'error');
            } catch(e) { showToast(t('err_network'), 'error'); }
        }

        function handleLogout() {
            if (confirm(t('confirm_logout'))) {
                localStorage.clear();
                window.location.href = '/login?role=msimamizi';
            }
        }

        function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }

        async function loadAllData() {
            loading = true;
            render();
            await Promise.all([fetchUsers(), fetchNotifications(), fetchStats()]);
            loading = false;
            render();
        }

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => {
                render();
                const rm = document.getElementById('recipientModal');
                if (rm && rm.style.display === 'flex') renderRecipientModal();
            });
        }

        async function init() {
            if (!checkSystemAdmin()) return;
            await loadAllData();
        }
        
        window.sendTestNotification = sendTestNotification;
        window.handleLogout = handleLogout;
        
        init();
    </script></body>
</html>
@endverbatim