@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Biashara - Dukamkononi Mteja</title>
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

        /* Navigation items */
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-label {
            font-size: 15px;
            font-weight: 600;
        }

        /* Sidebar footer */
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

        /* Page content */
        .page-container {
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        /* Search Container */
        .search-container {
            margin-bottom: 24px;
            position: relative;
        }

        .search-container .fa-solid {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 14px 18px;
            font-size: 15px;
            border: 1px solid #ddd;
            border-radius: 14px;
            font-family: 'Inter', sans-serif;
            background: white;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Section Title */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 16px;
            text-align: center;
        }

        /* Business Card */
        .business-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }

        .business-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .business-name {
            font-size: 20px;
            font-weight: 800;
            color: #2c3e50;
            flex: 1;
        }

        .contact-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 10px 20px;
            border-radius: 12px;
            border: none;
            color: white;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
        }

        .contact-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Twende Dukani Button */
        .twende-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            padding: 12px 20px;
            border-radius: 12px;
            border: none;
            color: white;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            margin-bottom: 14px;
            box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
            transition: all 0.2s;
        }

        .twende-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
        }

        /* Business Logo */
        .business-logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #e8f4fd;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .business-logo-placeholder {
            border: 2px dashed #d0e8f7;
            color: #667eea;
            font-size: 22px;
        }

        .business-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Details */
        .details-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-size: 14px;
            font-weight: 600;
            color: #34495e;
        }

        .detail-value {
            font-size: 14px;
            color: #7f8c8d;
            text-align: right;
            word-break: break-word;
            max-width: 60%;
        }

        .phone-link {
            color: #667eea;
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
        }

        .approved-text {
            color: #27ae60;
            font-weight: 700;
        }

        /* Empty State */
        .empty-container {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 20px;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .empty-text {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .refresh-btn {
            background: #667eea;
            padding: 12px 24px;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            border: none;
            cursor: pointer;
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
        }

        /* Contact Modal */
        .contact-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .contact-modal-content {
            background: white;
            border-radius: 28px;
            width: 85%;
            max-width: 340px;
            padding: 24px;
            text-align: center;
        }

        .contact-modal-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .contact-modal-sub {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .contact-modal-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .contact-call-btn, .contact-sms-btn, .contact-cancel-btn {
            padding: 14px;
            border-radius: 40px;
            font-weight: 700;
            cursor: pointer;
            border: none;
        }

        .contact-call-btn {
            background: #27ae60;
            color: white;
        }

        .contact-sms-btn {
            background: #3498db;
            color: white;
        }

        .contact-cancel-btn {
            background: #95a5a6;
            color: white;
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
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .detail-value {
                text-align: left;
                max-width: 100%;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .contact-btn {
                align-self: stretch;
                text-align: center;
            }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.toast')
@include('partials.photo-viewer')
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
                    <div class="logo-icon">D</div>
                    <div class="logo-text">
                        <h2>DukaMkononi</h2>
                        <p>Mteja Portal</p>
                    </div>
                </div>
            </div>

            <div class="nav-items">
                <a href="biashara" class="nav-item active">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 9L4.5 3.5H19.5L21 9" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4.5 9V20.5H19.5V9"/>
                            <path d="M3 9H21"/>
                            <path d="M9.5 20.5V14.5H14.5V20.5"/>
                        </svg>
                    </div>
                    <span class="nav-label">Biashara</span>
                </a>
                <a href="matangazo" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 10V14M3 10L13 5V19L3 14" stroke-linejoin="round"/>
                            <path d="M13 7C15.5 7 17.5 9.2 17.5 12C17.5 14.8 15.5 17 13 17" stroke-linecap="round"/>
                            <path d="M16 16.5L17.5 20.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label">Matangazo</span>
                </a>
                <a href="profaili" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21C4 16.6 7.6 13 12 13C16.4 13 20 16.6 20 21" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label">Profaili</span>
                </a>
            </div>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar">M</div>
                    <div class="user-details">
                        <div class="user-name" id="userName">Mteja</div>
                        <div class="user-role">Mteja</div>
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

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="page-container" id="biasharaContent">
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <div class="loading-text">Inapakua orodha ya biashara...</div>
                </div>
            </div>
        </main>
    </div>

    <!-- Contact Modal -->
    <div id="contactModal" class="contact-modal">
        <div class="contact-modal-content">
            <div class="contact-modal-title" id="modalBusinessName">Wasiliana na Biashara</div>
            <div class="contact-modal-sub">Chagua njia ya kuwasiliana</div>
            <div class="contact-modal-buttons">
                <button class="contact-call-btn" id="callBtn"><i class="fa-solid fa-phone" style="font-size:14px;" aria-hidden="true"></i> PIGA SIMU</button>
                <button class="contact-sms-btn" id="smsBtn"><i class="fa-solid fa-comment-dots" style="font-size:14px;" aria-hidden="true"></i> TUMA UJUMBE</button>
                <button class="contact-cancel-btn" id="cancelModalBtn">GHAIRI</button>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // BIASHARA SCREEN - Mteja Web Replica
        // With Sidebar Integration
        // ============================================

        const API_BASE_URL = '';

        // ============================== ICONS ==============================
        // Font Awesome 6 icon helper (same convention as msimamizi/muuzaji).
        const FA_MAP = {
            store: 'fa-store', megaphone: 'fa-bullhorn', user: 'fa-user',
            check: 'fa-check', warning: 'fa-triangle-exclamation',
            phone: 'fa-phone', sms: 'fa-comment-dots', compass: 'fa-compass',
            search: 'fa-magnifying-glass', refresh: 'fa-rotate-right', spinner: 'fa-spinner'
        };
        function ic(name, size = 16, cls = '') {
            return `<i class="fa-solid ${FA_MAP[name] || 'fa-circle-info'} ${cls}" style="font-size:${size}px;" aria-hidden="true"></i>`;
        }

        let businesses = [];
        let searchQuery = '';
        let loading = true;
        let currentBusiness = null;

        // DOM Elements
        const container = document.getElementById('biasharaContent');
        const contactModal = document.getElementById('contactModal');
        const modalBusinessName = document.getElementById('modalBusinessName');
        const callBtn = document.getElementById('callBtn');
        const smsBtn = document.getElementById('smsBtn');
        const cancelModalBtn = document.getElementById('cancelModalBtn');

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
                const displayName = user.full_name || user.email?.split('@')[0] || 'Mteja';
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

        // Check authentication
        function checkAuth() {
            const user = getCurrentUser();
            if (!user) {
                window.location.href = '/login?role=mteja';
                return false;
            }
            const userRole = user.role || '';
            if (userRole !== 'customer' && userRole !== 'client' && userRole !== 'mteja') {
                showToast('Huna ruhusa ya kuingia kwenye eneo la Mteja.', 'error');
                window.location.href = '/home';
                return false;
            }
            return true;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        function formatPhoneNumber(phone) {
            if (!phone || phone === 'null' || phone === 'undefined' || phone === 'Haijajazwa') {
                return null;
            }
            let clean = phone.replace(/[\s\-\(\)]/g, '');
            if (!clean.startsWith('+255') && !clean.startsWith('255')) {
                if (clean.length === 9) {
                    clean = `255${clean}`;
                } else if (clean.length === 10 && clean.startsWith('0')) {
                    clean = `255${clean.substring(1)}`;
                }
            }
            return clean;
        }

        function makePhoneCall(phoneNumber) {
            const formatted = formatPhoneNumber(phoneNumber);
            if (!formatted) {
                showToast('Hakuna namba ya simu inayopatikana kwa biashara hii.', 'warning');
                return;
            }
            window.location.href = `tel:${formatted}`;
        }

        function sendSMS(phoneNumber, businessName) {
            const formatted = formatPhoneNumber(phoneNumber);
            if (!formatted) {
                showToast('Hakuna namba ya simu inayopatikana kwa biashara hii.', 'warning');
                return;
            }
            const message = `Habari ${businessName}, naomba kufahamu zaidi kuhusu huduma zako.`;
            window.location.href = `sms:${formatted}?body=${encodeURIComponent(message)}`;
        }

        function showContactModal(business) {
            currentBusiness = business;
            modalBusinessName.textContent = `Wasiliana na ${business.business_name || 'Biashara'}`;
            contactModal.style.display = 'flex';
        }

        function hideContactModal() {
            contactModal.style.display = 'none';
            currentBusiness = null;
        }

        function handleCall() {
            if (currentBusiness) {
                makePhoneCall(currentBusiness.phone);
            }
            hideContactModal();
        }

        function handleSMS() {
            if (currentBusiness) {
                sendSMS(currentBusiness.phone, currentBusiness.business_name || 'Biashara');
            }
            hideContactModal();
        }

        // Twende Dukani - Open Google Maps directions.
        // The button shows a spinner + click-lock while geolocation resolves
        // and until the Maps tab actually opens (popup blockers would eat a
        // window.open fired long after the tap, so on failure we fall back
        // to navigating the SAME tab, which browsers always allow).
        let twendeBusy = false;
        function handleTwendeDukani(btn, businessName, lat, lng, location) {
            if (twendeBusy) return; // one lookup at a time (double-tap guard)
            const hasCoords = typeof lat === 'number' && typeof lng === 'number';
            const hasAddress = !!location && location !== 'null';

            if (!hasCoords && !hasAddress) {
                showToast('Hakuna taarifa za eneo kwa biashara hii.', 'warning');
                return;
            }

            const original = btn.innerHTML;
            twendeBusy = true;
            btn.innerHTML = ic('spinner', 14, 'fa-spin') + ' Inapata njia...';
            btn.style.opacity = '0.75';
            btn.style.pointerEvents = 'none';

            const finish = () => {
                twendeBusy = false;
                btn.innerHTML = original;
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            };

            const openMaps = (origin) => {
                const destination = hasCoords ? `${lat},${lng}` : encodeURIComponent(location);
                const mapsUrl = origin
                    ? `https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${destination}&travelmode=driving`
                    : `https://www.google.com/maps/dir/?api=1&destination=${destination}&travelmode=driving`;
                const win = window.open(mapsUrl, '_blank');
                finish();
                if (!win) {
                    // Popup blocked: same-tab navigation always succeeds.
                    showToast('Hakuna dirisha jipya lililofunguliwa — nakupeleka Google Maps hapo hapa.', 'info');
                    window.location.href = mapsUrl;
                }
            };

            // Try to get user's current location first (with a hard timeout so
            // the button can never stay stuck spinning).
            if (navigator.geolocation) {
                showToast('Inapata eneo lako la sasa...', 'info');
                let settled = false;
                const geoTimeout = setTimeout(() => {
                    if (!settled) { settled = true; openMaps(null); }
                }, 8000);
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        if (settled) return;
                        settled = true;
                        clearTimeout(geoTimeout);
                        openMaps(`${position.coords.latitude},${position.coords.longitude}`);
                    },
                    () => {
                        if (settled) return;
                        settled = true;
                        clearTimeout(geoTimeout);
                        openMaps(null);
                    },
                    { enableHighAccuracy: true, timeout: 7000 }
                );
            } else {
                openMaps(null);
            }
        }

        // Fetch businesses from API
        async function fetchBusinesses() {
            loading = true;
            render();

            try {
                const response = await fetch(`${API_BASE_URL}/api/businesses`);

                if (!response.ok) {
                    if (response.status === 404) {
                        throw new Error('Endpoint ya biashara haipo kwenye server');
                    }
                    throw new Error(`Server error: ${response.status}`);
                }

                const data = await response.json();
                businesses = data || [];
            } catch (error) {
                console.error('Hitilafu ya biashara:', error);
                showToast(`Imeshindwa kuleta biashara: ${error.message}. Tafadhali jaribu tena.`, 'error');
                businesses = [];
            } finally {
                loading = false;
                render();
            }
        }

        // Filter businesses based on search query
        function getFilteredBusinesses() {
            if (!searchQuery.trim()) return businesses;

            const query = searchQuery.toLowerCase();
            return businesses.filter(business =>
                (business.business_name && business.business_name.toLowerCase().includes(query)) ||
                (business.business_location && business.business_location.toLowerCase().includes(query)) ||
                (business.full_name && business.full_name.toLowerCase().includes(query)) ||
                (business.phone && business.phone.includes(query)) ||
                (business.email && business.email.toLowerCase().includes(query))
            );
        }

        // One business card (shared by initial render and live re-filter)
        function businessCardHtml(business) {
            return `
                <div class="business-card" data-business-id="${business.id}">
                    <div class="card-header">
                        <div style="display:flex;align-items:center;gap:12px;flex:1;">
                            <div class="business-logo ${business.business_logo_url ? '' : 'business-logo-placeholder'}">
                                ${business.business_logo_url ? `<div class="js-avatar-view" data-full="${escapeHtml(business.business_logo_url)}" data-name="${escapeHtml(business.business_name || 'Biashara')}" style="width:100%;height:100%;border-radius:12px;overflow:hidden;"><img src="${escapeHtml(business.business_logo_url)}" alt="Logo" style="width:100%;height:100%;object-fit:cover;pointer-events:none;"></div>` : ic('store', 22)}
                            </div>
                            <div class="business-name">${escapeHtml(business.business_name || 'Biashara Bila Jina')}</div>
                        </div>
                        <button class="contact-btn" data-id="${business.id}" data-phone="${escapeHtml(business.phone || '')}" data-name="${escapeHtml(business.business_name || 'Biashara')}">Wasiliana</button>
                    </div>
                    <button class="twende-btn" data-twende='${JSON.stringify({lat: business.business_latitude, lng: business.business_longitude, loc: business.business_location || ''}).replace(/'/g, "&#39;")}' data-name="${escapeHtml(business.business_name || 'Biashara')}">
                        ${ic('compass', 14)} Twende Dukani
                    </button>
                    <div class="details-container">
                        <div class="detail-row">
                            <span class="detail-label">Jina la Msimamizi:</span>
                            <span class="detail-value">${escapeHtml(business.full_name || 'Haijajazwa')}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Eneo la Biashara:</span>
                            <span class="detail-value">${escapeHtml(business.business_location || 'Haijajazwa')}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Namba ya Simu:</span>
                            <span class="detail-value phone-link" data-phone="${escapeHtml(business.phone || '')}">${escapeHtml(business.phone || 'Haijajazwa')}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Barua Pepe:</span>
                            <span class="detail-value">${escapeHtml(business.email || 'Haijajazwa')}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Hali:</span>
                            <span class="detail-value approved-text">${ic('check', 13)} Imethibitishwa</span>
                        </div>
                    </div>
                </div>
            `;
        }

        // Render ONLY the results region (counter + cards) WITHOUT touching
        // the search <input>. Re-rendering the whole page on every keystroke
        // destroyed the input element and threw the cursor out mid-word.
        function renderResultsRegion() {
            const listHost = document.getElementById('businessListHost');
            if (!listHost) return;

            const filtered = getFilteredBusinesses();
            const counter = document.getElementById('businessCount');
            if (counter) counter.textContent = `Biashara Zilizosajiliwa (${filtered.length})`;

            if (businesses.length === 0) {
                listHost.innerHTML = `
                    <div class="empty-container">
                        <div class="empty-title">Hakuna Biashara Zilizosajiliwa</div>
                        <div class="empty-text">Hakuna biashara zilizosajiliwa bado.\n\nMsimamizi anahitaji kujisajili kwanza.</div>
                        <button class="refresh-btn" id="refreshBtn">Pakia Upya</button>
                    </div>
                `;
                const refreshBtn = document.getElementById('refreshBtn');
                if (refreshBtn) refreshBtn.addEventListener('click', () => fetchBusinesses());
                return;
            }

            if (filtered.length === 0) {
                listHost.innerHTML = `
                    <div class="empty-container">
                        <div class="empty-title">Hakuna Biashara Iliyopatikana</div>
                        <div class="empty-text">Hakuna biashara iliyo na "${escapeHtml(searchQuery)}".\nTafadhali jaribu neno tofauti.</div>
                    </div>
                `;
                return;
            }

            listHost.innerHTML = filtered.map(businessCardHtml).join('');
            bindCardEvents();
        }

        // Click handlers for the cards inside the results region. Rebinding
        // after each region render is safe: the old elements are discarded
        // with the old DOM, so listeners never stack.
        // NB: business ids are UUID STRINGS — parseInt mangles them
        // ("19bf6afe-..." became 19) so lookups compare strings.
        function bindCardEvents() {
            document.querySelectorAll('.contact-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const id = btn.getAttribute('data-id');
                    const business = businesses.find(b => String(b.id) === id);
                    if (business) showContactModal(business);
                });
            });

            document.querySelectorAll('.phone-link').forEach(phoneEl => {
                phoneEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const phone = phoneEl.getAttribute('data-phone');
                    const parentCard = phoneEl.closest('.business-card');
                    if (parentCard && phone && phone !== 'Haijajazwa') {
                        const id = parentCard.getAttribute('data-business-id');
                        const business = businesses.find(b => String(b.id) === id);
                        if (business) makePhoneCall(business.phone);
                    }
                });
            });

            document.querySelectorAll('.twende-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    try {
                        const data = JSON.parse(btn.getAttribute('data-twende'));
                        handleTwendeDukani(btn, btn.getAttribute('data-name'), data.lat, data.lng, data.loc);
                    } catch (err) {
                        console.error('Twende Dukani error:', err);
                    }
                });
            });
        }

        // Render the UI (FULL page). The search bar is created ONCE here —
        // typing afterwards only re-fills the results host below it, so the
        // cursor can never jump mid-word.
        function render() {
            if (loading) {
                container.innerHTML = `
                    <div class="loading-container">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">Inapakua orodha ya biashara...</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="search-container" style="position:relative;">
                    ${ic('search', 15)}
                    <input type="text" id="searchInput" class="search-input" placeholder="Tafuta biashara... (jina, eneo, au namba ya simu)" value="${escapeHtml(searchQuery)}" style="padding-left:42px;">
                </div>
                <div class="section-title" id="businessCount"></div>
                <div id="businessListHost"></div>
            `;

            // The input handler must NEVER call render(): only the region
            // below the input changes while typing.
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    searchQuery = e.target.value;
                    renderResultsRegion();
                });
            }

            renderResultsRegion();
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
                logoutBtn.addEventListener('click', () => {
                    localStorage.clear();
                    window.location.href = '/login?role=mteja';
                });
            }

            // Close sidebar when clicking outside on mobile
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

        // Modal event listeners
        function setupModal() {
            if (callBtn) callBtn.addEventListener('click', handleCall);
            if (smsBtn) smsBtn.addEventListener('click', handleSMS);
            if (cancelModalBtn) cancelModalBtn.addEventListener('click', hideContactModal);

            // Close modal when clicking outside
            contactModal.addEventListener('click', (e) => {
                if (e.target === contactModal) {
                    hideContactModal();
                }
            });
        }

        // Initialize
        async function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
            setupModal();
            await fetchBusinesses();
        }

        init();

        // Expose for any external use
        window.refreshBusinesses = fetchBusinesses;
    </script>
</body>
</html>
@endverbatim
