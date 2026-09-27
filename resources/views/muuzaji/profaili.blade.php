@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Profaili - Dukamkononi Muuzaji</title>
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
        .muuzaji-layout {
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
            background: linear-gradient(135deg, #2ecc71, #27ae60);
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
            background-color: #e8f8f0;
        }

        .nav-item.active {
            background-color: #e8f8f0;
            color: #2ecc71;
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
            background-color: #e8f8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2ecc71;
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
            color: #2ecc71;
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
            padding: 20px 24px 40px;
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
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        /* Loading */
        .loading-container {
            text-align: center;
            padding: 60px 20px;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #e0e0e0;
            border-top-color: #2ecc71;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { color: #7f8c8d; font-size: 14px; }

        /* Header */
        .header {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .title { font-size: 24px; font-weight: 800; color: #2c3e50; }
        .header-actions { display: flex; gap: 12px; }
        .header-btn {
            background: #f5f5f5;
            width: 40px; height: 40px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* Profile Card */
        .profile-card {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .avatar-section { text-align: center; }
        .avatar {
            width: 90px; height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        .avatar-text { color: white; font-size: 32px; font-weight: bold; }
        .role-badge {
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .user-info { flex: 1; }
        .user-name { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .user-email { font-size: 14px; color: #7f8c8d; margin-bottom: 8px; }
        .business-info { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
        .edit-profile-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #e8f4fd;
            padding: 10px 16px;
            border-radius: 10px;
            margin-top: 12px;
            cursor: pointer;
            width: fit-content;
        }

        /* Stats Section */
        .stats-section { margin-bottom: 20px; }
        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 16px; }
        .stats-subtitle { font-size: 13px; color: #7f8c8d; font-weight: normal; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }
        .stat-card {
            background: white;
            padding: 16px;
            border-radius: 16px;
            text-align: center;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
        }
        .stat-value { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .stat-label { font-size: 12px; color: #7f8c8d; }
        .stats-note { font-size: 12px; color: #95a5a6; text-align: center; margin-top: 12px; font-style: italic; }

        /* Info Card */
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { display: flex; align-items: center; gap: 8px; color: #7f8c8d; }
        .info-value { font-weight: 500; color: #2c3e50; text-align: right; }
        .status-badge { padding: 4px 12px; border-radius: 20px; }
        .status-text { font-size: 12px; font-weight: 600; }

        /* Logout Button */
        .logout-section { margin: 20px 0; }
        .logout-btn-main {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #e74c3c;
            padding: 16px;
            border-radius: 14px;
            cursor: pointer;
            color: white;
            font-weight: 700;
        }

        /* Footer */
        .footer { text-align: center; padding: 20px; }
        .footer-text { font-size: 12px; color: #95a5a6; }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 450px;
            padding: 24px;
        }
        .modal-title { font-size: 20px; font-weight: 800; text-align: center; margin-bottom: 4px; }
        .modal-subtitle { font-size: 13px; color: #7f8c8d; text-align: center; margin-bottom: 20px; }
        .input-label { font-size: 14px; font-weight: 600; margin-bottom: 6px; display: block; }
        .input-field {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .modal-buttons { display: flex; gap: 12px; margin-top: 8px; }
        .modal-btn {
            flex: 1;
            padding: 14px;
            border-radius: 12px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-cancel { background: #95a5a6; color: white; }
        .btn-save { background: #3498db; color: white; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 16px; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
            .profile-card { flex-direction: column; align-items: center; text-align: center; }
            .user-info { text-align: center; }
            .business-info { justify-content: center; }
            .edit-profile-btn { margin: 12px auto 0; }
            .info-row { flex-direction: column; gap: 8px; }
            .info-value { text-align: left; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.toast')
@include('partials.photo-viewer')
@include('partials.cloudinary-config')
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
                    <div class="logo-icon">D</div>
                    <div class="logo-text">
                        <h2>DukaMkononi</h2>
                        <p>Muuzaji Portal</p>
                    </div>
                </div>
            </div>
            <div class="nav-items">
                <a href="profaili" class="nav-item active">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21C4 16.6 7.6 13 12 13C16.4 13 20 16.6 20 21" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label">Profaili</span>
                </a>
                <a href="mauzo" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="2.5" y="6" width="19" height="12" rx="2.5"/>
                            <circle cx="12" cy="12" r="2.6"/>
                            <path d="M6 9.5V9.51M18 14.5V14.51" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label">Mauzo</span>
                </a>
                <a href="matumizi" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z"/>
                            <path d="M8 7V17M12 7V17M16 7V17"/>
                        </svg>
                    </div>
                    <span class="nav-label">Matumizi</span>
                </a>
                <a href="uza" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="9" cy="20" r="1.6"/>
                            <circle cx="17" cy="20" r="1.6"/>
                            <path d="M3 3H5L7.4 15.2C7.55 15.95 8.2 16.5 8.97 16.5H17.6C18.32 16.5 18.94 16 19.08 15.3L21 7H6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="nav-label">Uza</span>
                </a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar">M</div>
                    <div class="user-details">
                        <div class="user-name" id="userName">Muuzaji</div>
                        <div class="user-role">Muuzaji</div>
                    </div>
                </div>
                <div class="logout-btn" id="logoutBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M15 3H19C20.1 3 21 3.9 21 5V19C21 20.1 20.1 21 19 21H15M10 17L15 12L10 7M15 12H3"/>
                    </svg>
                    <span>Ondoka</span>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-container" id="profailiContent">
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <div class="loading-text">Inapakua taarifa zako...</div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-title">Badili Wasifu Wako</div>
            <div class="modal-subtitle">Unaweza kubadili picha yako, jina lako na namba ya simu. Taarifa nyingine za biashara zinasimamiwa na msimamizi.</div>
            <label class="input-label">Picha ya Profaili</label>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div id="editAvatarPreview" style="width:60px;height:60px;border-radius:50%;background:#2ecc71;display:flex;align-items:center;justify-content:center;color:white;font-size:24px;font-weight:bold;"></div>
                <div style="flex:1;">
                    <input type="file" id="profileImageInput" accept="image/*" style="display:none;">
                    <div id="changePhotoBtn" style="padding:10px 16px;background:#e8f4fd;border-radius:10px;cursor:pointer;text-align:center;font-size:13px;font-weight:600;color:#3498db;"><i class="fa-solid fa-camera" style="font-size:13px;" aria-hidden="true"></i> Badilisha Picha</div>
                </div>
            </div>
            <label class="input-label">Jina Kamili *</label>
            <input type="text" id="editFullName" class="input-field" placeholder="Weka jina lako kamili">
            <label class="input-label">Namba ya Simu</label>
            <input type="tel" id="editPhone" class="input-field" placeholder="Weka namba yako ya simu">
            <div class="modal-buttons">
                <div class="modal-btn btn-cancel" id="cancelEditBtn" onclick="closeEditModal()">Ghairi</div>
                <div class="modal-btn btn-save" id="saveProfileBtn" onclick="updateProfile()">Hifadhi</div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE_URL = '';
        
        // ============================== ICONS ==============================
        // Font Awesome 6 icon helper (same convention as the msimamizi pages).
        const FA_MAP = {
            user: 'fa-user', users: 'fa-users', money: 'fa-money-bill-1', chart: 'fa-chart-line',
            cart: 'fa-cart-shopping', box: 'fa-box', doc: 'fa-file-lines', mail: 'fa-envelope',
            phone: 'fa-phone', building: 'fa-building', location: 'fa-location-dot', id: 'fa-id-card',
            refresh: 'fa-rotate-right', edit: 'fa-pen-to-square', trash: 'fa-trash-can',
            check: 'fa-check', x: 'fa-xmark', clock: 'fa-clock', shield: 'fa-shield-halved',
            warning: 'fa-triangle-exclamation', camera: 'fa-camera', search: 'fa-magnifying-glass',
            logout: 'fa-arrow-right-from-bracket', save: 'fa-floppy-disk', spinner: 'fa-spinner',
            receipt: 'fa-receipt', lock: 'fa-lock', tag: 'fa-tag', calendar: 'fa-calendar-days', plus: 'fa-plus'
        };
        function ic(name, size = 16, cls = '') {
            return `<i class="fa-solid ${FA_MAP[name] || 'fa-circle-info'} ${cls}" style="font-size:${size}px;" aria-hidden="true"></i>`;
        }

        // Edit-modal button busy state: Hifadhi shows a spinner while the
        // request runs; Ghairi is locked (and dimmed) so nothing can be
        // double-clicked mid-save. Ghairi closes instantly on its own —
        // a spinner there would only flash, so it gets click-lock instead.
        function setProfileModalBusy(busy) {
            const save = document.getElementById('saveProfileBtn');
            const cancel = document.getElementById('cancelEditBtn');
            if (save) {
                save.innerHTML = busy ? ic('spinner', 15, 'fa-spin') + ' Inahifadhi...' : 'Hifadhi';
                save.style.opacity = busy ? '0.7' : '1';
                save.style.pointerEvents = busy ? 'none' : 'auto';
            }
            if (cancel) {
                cancel.style.opacity = busy ? '0.5' : '1';
                cancel.style.pointerEvents = busy ? 'none' : 'auto';
            }
        }

        let userData = null;
        let businessData = null;
        let stats = { totalProducts: 0, totalSales: 0, totalCustomers: 0, totalRevenue: 0 };
        let loading = true;

        function getCurrentUser() {
            const token = localStorage.getItem('userToken');
            const userDataStr = localStorage.getItem('userData');
            if (token && userDataStr) {
                try { return JSON.parse(userDataStr); } catch(e) { return null; }
            }
            return null;
        }

        function updateSidebarUser() {
            const user = getCurrentUser();
            if (user) {
                const displayName = user.full_name || user.business_name || user.email?.split('@')[0] || 'Muuzaji';
                document.getElementById('userName').innerHTML = escapeHtml(displayName);
                const avatarEl = document.getElementById('userAvatar');
                if (user.business_logo_url) {
                    avatarEl.classList.add('js-avatar-view');
avatarEl.setAttribute('data-full', user.business_logo_url);
avatarEl.setAttribute('data-name', displayName);
avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="Picha">`;
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
            const userRole = user.role || '';
            if (userRole !== 'seller' && userRole !== 'muuzaji') {
                showToast('Huna ruhusa ya kuingia kwenye eneo la Muuzaji.', 'error');
                window.location.href = '/home';
                return false;
            }
            return true;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));
        }

        function formatCurrency(amount) {
            return `TZS ${(amount || 0).toLocaleString()}`;
        }

        function formatDate(dateStr) {
            if (!dateStr) return 'Haijawekwa';
            try {
                return new Date(dateStr).toLocaleDateString('sw-TZ', {
                    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                });
            } catch { return dateStr; }
        }

        function getRoleDisplayName(role) {
            const map = { customer: 'MTEJA', seller: 'MUUZAJI', admin: 'MSIMAMIZI' };
            return map[role] || role?.toUpperCase() || 'MTUMIAJI';
        }

        function getRoleColor(role) {
            const map = { customer: '#3498db', seller: '#2ecc71', admin: '#e74c3c' };
            return map[role] || '#2c3e50';
        }

        function getInitials(name) {
            return name?.split(' ').map(w => w.charAt(0)).join('').toUpperCase().substring(0,2) || 'MU';
        }

        async function loadUserData() {
            const token = localStorage.getItem('userToken');
            if (!token) {
                loading = false;
                render();
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE_URL}/api/user/profile`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                if (response.ok) {
                    userData = await response.json();
                    localStorage.setItem('userData', JSON.stringify(userData));
                    updateSidebarUser();
                } else {
                    const stored = localStorage.getItem('userData');
                    if (stored) userData = JSON.parse(stored);
                }
            } catch (error) {
                console.error('Error loading user data:', error);
                const stored = localStorage.getItem('userData');
                if (stored) userData = JSON.parse(stored);
            }
            
            if (userData) await loadBusinessData();
            else loading = false;
        }

        async function loadBusinessData() {
            const token = localStorage.getItem('userToken');
            if (!userData?.business_name) {
                businessData = { business_name: userData?.business_name || 'Personal', is_admin: userData?.role === 'admin' };
                await loadUserStats();
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE_URL}/api/business/by-name/${encodeURIComponent(userData.business_name)}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                if (response.ok) {
                    const bizInfo = await response.json();
                    if (bizInfo.exists && bizInfo.hasAdmin) {
                        businessData = {
                            id: bizInfo.admin.id,
                            business_name: bizInfo.admin.business_name,
                            is_admin: true,
                            admin_email: bizInfo.admin.email
                        };
                    } else {
                        businessData = { business_name: userData.business_name, is_admin: userData.role === 'admin' };
                    }
                } else {
                    businessData = { business_name: userData.business_name, is_admin: userData.role === 'admin' };
                }
            } catch (error) {
                businessData = { business_name: userData.business_name, is_admin: userData.role === 'admin' };
            }
            
            await loadUserStats();
        }

        async function loadUserStats() {
            const token = localStorage.getItem('userToken');
            let totalProducts = 0, totalSales = 0, totalRevenue = 0, totalCustomers = 0;
            
            try {
                // Load products
                const productsRes = await fetch(`${API_BASE_URL}/api/products/my`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (productsRes.ok) {
                    const products = await productsRes.json();
                    totalProducts = products.length || 0;
                }
                
                // Load TODAY's sales only — server-side filtered by sale_date
                // so we don't download the seller's entire sales history.
                const todayStr = new Date().toISOString().split('T')[0];
                const salesRes = await fetch(`${API_BASE_URL}/api/sales/my?date_from=${todayStr}&date_to=${todayStr}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (salesRes.ok) {
                    const daySales = await salesRes.json();
                    const list = Array.isArray(daySales) ? daySales : [];
                    totalSales = list.length || 0;
                    totalRevenue = list.reduce((sum, sale) => sum + (sale.total_amount || 0), 0);
                    // Customers served today = distinct recorded customers in today's
                    // sales, plus one "unknown customer" per sale with no customer data.
                    const ids = new Set();
                    let unknownCustomers = 0;
                    list.forEach(s => {
                        if (s.customer_id) ids.add(s.customer_id);
                        else unknownCustomers += 1;
                    });
                    totalCustomers = ids.size + unknownCustomers;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
            
            stats = { totalProducts, totalSales, totalCustomers, totalRevenue };
            loading = false;
            render();
        }

        let profileImageUrl = '';

        async function updateProfile() {
            const full_name = document.getElementById('editFullName').value;
            const phone = document.getElementById('editPhone').value;
            
            if (!full_name.trim()) {
                showToast('Tafadhali weka jina lako kamili', 'warning');
                return;
            }
            
            const token = localStorage.getItem('userToken');
            if (!token) return;
            
            setProfileModalBusy(true);
            
            // A seller may edit their own name, phone and profile photo. The
            // rest of the business profile (name, location, type, description,
            // coordinates) is read-only here - it belongs to the msimamizi.
            const updateData = { full_name, phone };
            if (profileImageUrl) updateData.business_logo_url = profileImageUrl;
            
            try {
                const response = await fetch(`${API_BASE_URL}/api/user/profile`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify(updateData)
                });
                
                if (response.ok) {
                    const result = await response.json();
                    userData = result.user || { ...userData, ...updateData };
                    localStorage.setItem('userData', JSON.stringify(userData));
                    if (userData.business_logo_url) localStorage.setItem('userProfileImage', userData.business_logo_url);
                    updateSidebarUser();
                    closeEditModal();
                    showToast('Wasifu wako umesasishwa kikamilifu!', 'success');
                    location.reload();
                } else {
                    showToast('Imeshindikana kusasisha wasifu', 'error');
                }
            } catch (error) {
                showToast('Hitilafu ya mtandao', 'error');
            } finally {
                setProfileModalBusy(false);
            }
        }

        function openEditModal() {
            document.getElementById('editFullName').value = userData?.full_name || '';
            document.getElementById('editPhone').value = userData?.phone || '';
            profileImageUrl = userData?.business_logo_url || '';
            const avatarPreview = document.getElementById('editAvatarPreview');
            if (profileImageUrl) {
                avatarPreview.innerHTML = `<img src="${profileImageUrl}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
            } else {
                const initials = getInitials(userData?.full_name || userData?.business_name || 'U');
                avatarPreview.innerHTML = `<span>${initials}</span>`;
            }
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Upload profile image to Cloudinary
        async function uploadProfileImage(file) {
            const cloudName = (window.CLOUDINARY_CONFIG ? window.CLOUDINARY_CONFIG.cloudName : '');
            const uploadPreset = 'react_native_uploads';
            const formData = new FormData();
            formData.append('file', file);
            formData.append('upload_preset', uploadPreset);
            try {
                const response = await fetch(`https://api.cloudinary.com/v1_1/${cloudName}/image/upload`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.secure_url) {
                    profileImageUrl = data.secure_url;
                    document.getElementById('editAvatarPreview').innerHTML = `<img src="${profileImageUrl}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
                }
            } catch (error) {
                console.error('Upload error:', error);
                showToast('Imeshindikana kupakia picha', 'error');
            }
        }

        function handleLogout() {
            if (confirm('Una uhakika unataka kutoka?')) {
                localStorage.clear();
                window.location.href = '/login?role=muuzaji';
            }
        }

        function refreshData() {
            loading = true;
            render();
            loadUserData();
        }

        function render() {
            const container = document.getElementById('profailiContent');
            
            if (loading || !userData) {
                container.innerHTML = `<div class="loading-container"><div class="loading-spinner"></div><div class="loading-text">Inapakua taarifa zako...</div></div>`;
                return;
            }
            
            const roleColor = getRoleColor(userData.role);
            const roleName = getRoleDisplayName(userData.role);
            const initials = getInitials(userData.full_name || userData.business_name || userData.email);
            const isAdmin = businessData?.is_admin || false;
            
            container.innerHTML = `
                <div class="header">
                    <div class="header-top">
                        <div class="title">Wasifu Wangu</div>
                        <div class="header-actions">
                            <div class="header-btn" onclick="refreshData()">${ic('refresh', 16)}</div>
                            <div class="header-btn" onclick="openEditModal()">${ic('edit', 16)}</div>
                        </div>
                    </div>
                    <div class="profile-card">
                        <div class="avatar-section">
                            <div class="avatar" style="background: ${roleColor};">${userData.business_logo_url ? `<div class="js-avatar-view" data-full="${escapeHtml(userData.business_logo_url)}" data-name="${escapeHtml(userData.full_name || userData.business_name || userData.email || '')}" style="width:100%;height:100%;border-radius:50%;overflow:hidden;"><img src="${escapeHtml(userData.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;"></div>` : `<div class="avatar-text">${escapeHtml(initials)}</div>`}</div>
                            <div class="role-badge">${escapeHtml(roleName)}</div>
                        </div>
                        <div class="user-info">
                            <div class="user-name">${escapeHtml(userData.full_name || userData.business_name || userData.email)}</div>
                            <div class="user-email">${escapeHtml(userData.email)}</div>
                            ${businessData?.business_name ? `<div class="business-info">${ic('building', 14)} <span>${escapeHtml(businessData.business_name)}${isAdmin ? ' (Msimamizi)' : ''}</span></div>` : ''}
                            ${userData.business_location ? `<div class="business-info">${ic('location', 14)} <span>${escapeHtml(userData.business_location)}</span></div>` : ''}
                            <div class="edit-profile-btn" onclick="openEditModal()">${ic('edit', 14)} <span>Badili Taarifa za Wasifu</span></div>
                        </div>
                    </div>
                </div>
                
                <div class="stats-section">
                    <div class="section-title">Takwimu za Leo <span class="stats-subtitle">${isAdmin ? '(Zako za Leo)' : '(Zako Binafsi)'}</span></div>
                    <div class="stats-grid">
                        <div class="stat-card"><div class="stat-icon" style="background:#e8f4fd;color:#3498db;">${ic('box', 20)}</div><div class="stat-value">${stats.totalProducts}</div><div class="stat-label">Bidhaa za Biashara</div></div>
                        <div class="stat-card"><div class="stat-icon" style="background:#f0f8f0;color:#27ae60;">${ic('cart', 20)}</div><div class="stat-value">${stats.totalSales}</div><div class="stat-label">Mauzo ya Leo</div></div>
                        <div class="stat-card"><div class="stat-icon" style="background:#fff8e1;color:#f39c12;">${ic('users', 20)}</div><div class="stat-value">${stats.totalCustomers}</div><div class="stat-label">Wateja wa Leo</div></div>
                        <div class="stat-card"><div class="stat-icon" style="background:#fce4ec;color:#e74c3c;">${ic('money', 20)}</div><div class="stat-value">${formatCurrency(stats.totalRevenue)}</div><div class="stat-label">Mapato ya Leo</div></div>
                    </div>
                    <div class="stats-note">Mauzo na wateja wa leo tu — kuanzia usiku wa manane</div>
                </div>
                
                <div class="stats-section">
                    <div class="section-title">Taarifa za Akaunti</div>
                    <div class="info-card">
                        <div class="info-row"><div class="info-label">${ic('user', 14)} Jina Kamili:</div><div class="info-value">${escapeHtml(userData.full_name || 'Haijawekwa')}</div></div>
                        <div class="info-row"><div class="info-label">${ic('mail', 14)} Barua Pepe:</div><div class="info-value">${escapeHtml(userData.email)}</div></div>
                        <div class="info-row"><div class="info-label">${ic('phone', 14)} Namba ya Simu:</div><div class="info-value">${escapeHtml(userData.phone || 'Haijawekwa')}</div></div>
                        <div class="info-row"><div class="info-label">${ic('id', 14)} Kitambulisho cha Akaunti:</div><div class="info-value">#${userData.id || 'N/A'}</div></div>
                        ${businessData?.business_name ? `<div class="info-row"><div class="info-label">${ic('building', 14)} Biashara:</div><div class="info-value">${escapeHtml(businessData.business_name)}</div></div>` : ''}
                        <div class="info-row"><div class="info-label">${ic('location', 14)} Eneo la Biashara:</div><div class="info-value">${escapeHtml(userData.business_location || 'Haijawekwa')}</div></div>
                        <div class="info-row"><div class="info-label">${ic('clock', 14)} Imejisajiliwa:</div><div class="info-value">${formatDate(userData.created_at)}</div></div>
                        <div class="info-row"><div class="info-label">${ic('shield', 14)} Hali ya Akaunti:</div><div class="info-value"><span class="status-badge" style="background: ${userData.status === 'approved' ? '#e8f6f3' : userData.status === 'pending' ? '#fef9e7' : '#fdedec'};"><span class="status-text" style="color: ${userData.status === 'approved' ? '#27ae60' : userData.status === 'pending' ? '#f39c12' : '#e74c3c'};">${userData.status === 'approved' ? 'Imethibitishwa' : userData.status === 'pending' ? 'Inasubiri' : 'Imekataliwa'}</span></span></div></div>
                    </div>
                </div>
                
                <div class="logout-section">
                    <div class="logout-btn-main" onclick="handleLogout()">${ic('logout', 16)} Toka Kwenye Akaunti</div>
                </div>
                
                <div class="footer">
                    <div class="footer-text">Duka Mkononi • ${new Date().getFullYear()}</div>
                    <div class="footer-text" style="font-size: 11px;">Inafanya kazi kwa muuzaji${businessData?.business_name ? ` • ${businessData.business_name}` : ''}</div>
                </div>
            `;
        }

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
            // Profile photo: the seller may change their own photo. The rest of
            // the business fields (name, location, type, description, GPS
            // coordinates) are read-only, so there is no GPS button to wire.
            const changePhotoBtn = document.getElementById('changePhotoBtn');
            const profileImageInput = document.getElementById('profileImageInput');
            if (changePhotoBtn && profileImageInput) {
                changePhotoBtn.addEventListener('click', () => profileImageInput.click());
                profileImageInput.addEventListener('change', (e) => {
                    if (e.target.files && e.target.files[0]) {
                        uploadProfileImage(e.target.files[0]);
                    }
                });
            }
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    const sidebar = document.getElementById('sidebar');
                    const toggle = document.getElementById('mobileMenuToggle');
                    if (sidebar && sidebar.classList.contains('open') && 
                        !sidebar.contains(e.target) && toggle && !toggle.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                }
            });
        }

        window.openEditModal = openEditModal;
        window.closeEditModal = closeEditModal;
        window.updateProfile = updateProfile;
        window.handleLogout = handleLogout;
        window.refreshData = refreshData;

        async function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
            await loadUserData();
        }
        
        init();
    </script>
</body>
</html>
@endverbatim