@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Matangazo - Dukamkononi Mteja</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
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

        /* Matangazo specific styles */
        .page-container {
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        .list-header {
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 16px;
        }

        .screen-title {
            font-size: 28px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .screen-subtitle {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 12px;
        }

        .info-text {
            font-size: 13px;
            color: #2c3e50;
            background: #e8f4fd;
            padding: 10px;
            border-radius: 10px;
            margin: 8px 0;
            font-weight: 600;
        }

        .login-warning {
            background: #fff3cd;
            padding: 10px;
            border-radius: 10px;
            border-left: 4px solid #ffc107;
            margin-top: 8px;
        }

        .login-warning-text {
            font-size: 12px;
            color: #856404;
        }

        /* Post Card */
        .post-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
        }

        .business-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .business-details {
            flex: 1;
        }

        .business-name {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
        }

        .owner-name {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 2px;
        }

        .order-btn {
            background: #27ae60;
            padding: 8px 20px;
            border-radius: 10px;
            border: none;
            color: white;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .order-btn:hover {
            background: #2ecc71;
        }

        /* Media */
        .media-container {
            position: relative;
            background: #000;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .media-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
        }

        .media-video {
            width: 100%;
            max-height: 400px;
            background: #000;
        }

        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: none;
        }

        .play-button {
            width: 60px;
            height: 60px;
            background: rgba(0,0,0,0.6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            transition: opacity 0.2s;
        }

        /* Actions */
        .post-actions {
            display: flex;
            gap: 16px;
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .action-btn.liked {
            background: #ffe6e6;
            color: #e74c3c;
        }

        .action-btn.liked .fa-heart {
            color: #e74c3c;
        }

        /* Description */
        .description-box {
            padding: 16px;
        }

        .description-text {
            font-size: 14px;
            line-height: 1.5;
            color: #2c3e50;
        }

        .business-name-highlight {
            font-weight: 700;
            color: #2c3e50;
        }

        /* Contact */
        .contact-box {
            padding: 0 16px 12px 16px;
        }

        .contact-title {
            font-weight: 700;
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 6px;
        }

        .contact-phone {
            color: #667eea;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            cursor: pointer;
        }

        .contact-email {
            color: #3498db;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .post-footer {
            padding: 12px 16px;
            border-top: 1px solid #f0f0f0;
        }

        .timestamp {
            font-size: 11px;
            color: #95a5a6;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
        }

        .empty-icon {
            color: #bdc3c7;
            margin-bottom: 16px;
        }

        .empty-text {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }

        .empty-sub {
            font-size: 14px;
            color: #95a5a6;
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

        /* Contact Modal (same behaviour as biashara Wasiliana) */
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
                <a href="biashara" class="nav-item">
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
                <a href="matangazo" class="nav-item active">
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
            <div class="page-container" id="matangazoContent">
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <div class="loading-text">Inapakua matangazo...</div>
                </div>
            </div>
        </main>
    </div>

    <!-- Contact Modal (PIGA SIMU / TUMA UJUMBE / GHAIRI — same as biashara) -->
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
        // MATANGAZO SCREEN - Mteja Web Replica
        // With Sidebar Integration
        // ============================================

        const API_BASE_URL = '';

        // ============================== ICONS ==============================
        // Font Awesome 6 icon helper (same convention as msimamizi/muuzaji).
        const FA_MAP = {
            store: 'fa-store', megaphone: 'fa-bullhorn', user: 'fa-user',
            check: 'fa-check', x: 'fa-xmark', warning: 'fa-triangle-exclamation',
            phone: 'fa-phone', sms: 'fa-comment-dots', heart: 'fa-heart',
            report: 'fa-flag', refresh: 'fa-rotate-right', play: 'fa-play',
            chart: 'fa-chart-simple', mail: 'fa-envelope'
        };
        function ic(name, size = 16, cls = '') {
            return `<i class="fa-solid ${FA_MAP[name] || 'fa-circle-info'} ${cls}" style="font-size:${size}px;" aria-hidden="true"></i>`;
        }

        let matangazo = [];
        let loading = true;
        let refreshing = false;
        let likedPosts = new Set();
        let userToken = null;
        let userName = null;
        let currentBusiness = null;
        const likeBusy = new Set(); // per-post double-tap guard

        // DOM Elements
        const container = document.getElementById('matangazoContent');
        const contactModal = document.getElementById('contactModal');
        const modalBusinessName = document.getElementById('modalBusinessName');
        const callBtn = document.getElementById('callBtn');
        const smsBtn = document.getElementById('smsBtn');
        const cancelModalBtn = document.getElementById('cancelModalBtn');

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // Business avatar for post cards: the business's profile photo when
        // one is set, otherwise a placeholder letter (first character of the
        // business name). If the photo URL exists but fails to load, the img
        // swaps itself to the letter (data-fallback) — never a broken icon.
        function avatarHtml(businessName, logoUrl) {
            const name = businessName || 'Biashara';
            const letter = escapeHtml((name.trim().charAt(0) || 'B').toUpperCase());
            if (logoUrl) {
                return `<div class="js-avatar-view" data-full="${escapeHtml(logoUrl)}" data-name="${escapeHtml(name)}" style="width:100%;height:100%;border-radius:50%;overflow:hidden;"><img src="${escapeHtml(logoUrl)}" alt="${escapeHtml(name)}" style="width:100%;height:100%;object-fit:cover;pointer-events:none;" data-fallback="${letter}" onerror="this.onerror=null;this.style.display='none';this.parentElement.textContent=this.dataset.fallback;"></div>`;
            }
            return letter;
        }

        function formatPhoneNumber(phone) {
            if (!phone || phone === 'null' || phone === 'undefined' || phone === 'Haijajazwa' || phone === 'Hakuna namba ya simu') {
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
            const message = `Habari ${businessName}, naomba kufahamu zaidi kuhusu matangazo yako.`;
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
                userName = displayName;
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
            userToken = localStorage.getItem('userToken');
            return true;
        }

        // Fetch user likes
        async function fetchUserLikes() {
            if (!userToken) return;

            try {
                const response = await fetch(`${API_BASE_URL}/api/reactions/user/likes`, {
                    headers: {
                        'Authorization': `Bearer ${userToken}`,
                        'Accept': 'application/json',
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    likedPosts = new Set((data.likedPosts || []).map(String));
                }
            } catch (error) {
                console.error('Error fetching likes:', error);
            }
        }

        // Paint one post's like button + count in place (NO page re-render —
        // re-rendering would reset any video the user is watching).
        function paintLikeState(post) {
            const btn = document.getElementById('likeBtn_' + post.id);
            if (!btn) return;
            const liked = likedPosts.has(String(post.id));
            btn.classList.toggle('liked', liked);
            btn.innerHTML = `<i class="${liked ? 'fa-solid' : 'fa-regular'} fa-heart" style="font-size:13px;" aria-hidden="true"></i> Penda (${post.like_count || 0})`;
        }

        // Like toggle. Optimistic UI first (heart turns red instantly and the
        // count moves), then the SERVER's authoritative liked + like_count
        // from the response wins — so the number shown is always accurate
        // even when several people are liking at once.
        async function handleLike(postId) {
            if (!userToken) {
                showToast('Tafadhali ingia kwenye akaunti yako kupenda matangazo.', 'warning');
                return;
            }
            const idStr = String(postId);
            const post = matangazo.find(p => String(p.id) === idStr);
            if (!post) return;
            if (likeBusy.has(idStr)) return; // one request at a time per post
            likeBusy.add(idStr);

            const wasLiked = likedPosts.has(idStr);

            // Optimistic update
            if (wasLiked) {
                likedPosts.delete(idStr);
                post.like_count = Math.max((post.like_count || 0) - 1, 0);
            } else {
                likedPosts.add(idStr);
                post.like_count = (post.like_count || 0) + 1;
            }
            paintLikeState(post);

            try {
                const response = await fetch(`${API_BASE_URL}/api/reactions/like`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${userToken}`,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ matangazo_id: post.id }),
                });

                if (response.ok) {
                    const data = await response.json();
                    // Server is the source of truth.
                    if (data.liked) likedPosts.add(idStr);
                    else likedPosts.delete(idStr);
                    post.like_count = (data.like_count !== undefined) ? data.like_count : post.like_count;
                    post.report_count = (data.report_count !== undefined) ? data.report_count : post.report_count;
                } else {
                    // Revert optimistic change
                    if (wasLiked) {
                        likedPosts.add(idStr);
                        post.like_count = (post.like_count || 0) + 1;
                    } else {
                        likedPosts.delete(idStr);
                        post.like_count = Math.max((post.like_count || 0) - 1, 0);
                    }
                    showToast('Imeshindwa kupenda tangazo. Tafadhali jaribu tena.', 'error');
                }
            } catch (error) {
                // Revert optimistic change
                if (wasLiked) {
                    likedPosts.add(idStr);
                    post.like_count = (post.like_count || 0) + 1;
                } else {
                    likedPosts.delete(idStr);
                    post.like_count = Math.max((post.like_count || 0) - 1, 0);
                }
                showToast('Imeshindwa kupenda tangazo. Angalia muunganisho wako.', 'error');
            } finally {
                likeBusy.delete(idStr);
                paintLikeState(post);
            }
        }

        // Report a post (server rejects duplicates with a clear message).
        async function handleReport(postId) {
            if (!userToken) {
                showToast('Tafadhali ingia kwenye akaunti yako kuripoti matangazo.', 'warning');
                return;
            }
            const idStr = String(postId);
            const post = matangazo.find(p => String(p.id) === idStr);
            if (!post) return;

            if (confirm(`Una hakika unataka kuripoti matangazo ya "${post.users?.business_name || 'Biashara'}"?`)) {
                try {
                    const response = await fetch(`${API_BASE_URL}/api/reactions/report`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${userToken}`,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ matangazo_id: post.id }),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (response.ok) {
                        post.report_count = (data.report_count !== undefined) ? data.report_count : (post.report_count || 0) + 1;
                        const rBtn = document.getElementById('reportBtn_' + post.id);
                        if (rBtn) rBtn.innerHTML = `${ic('report', 13)} Ripoti (${post.report_count})`;
                        showToast('Asante! Tangazo limeripotiwa.', 'success');
                    } else {
                        showToast(data.error || 'Imeshindwa kuwasilisha ripoti.', 'error');
                    }
                } catch (error) {
                    showToast('Imeshindwa kuwasilisha ripoti. Angalia muunganisho wako.', 'error');
                }
            }
        }

        // Agiza — opens the SAME contact modal as biashara's Wasiliana
        // (PIGA SIMU / TUMA UJUMBE / GHAIRI) so phone users can call or SMS
        // the advertiser without copying numbers.
        function handleOrder(postId) {
            const idStr = String(postId);
            const post = matangazo.find(p => String(p.id) === idStr);
            if (!post) return;

            const phoneNumber = post.users?.phone || '';
            const businessName = post.users?.business_name || post.title || 'Biashara';

            if (!phoneNumber || phoneNumber.trim() === '' || phoneNumber === 'Hakuna namba ya simu') {
                showToast('Hakuna namba ya simu inayopatikana kwa biashara hii.', 'warning');
                return;
            }

            showContactModal({ phone: phoneNumber, business_name: businessName });
        }

        // Fetch advertisements from API
        async function fetchMatangazo() {
            loading = true;
            render();

            try {
                const response = await fetch(`${API_BASE_URL}/api/matangazo`, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                matangazo = data || [];

                if (userToken) {
                    await fetchUserLikes();
                }
            } catch (error) {
                console.error('Error fetching matangazo:', error);
                showToast('Imeshindwa kupakua matangazo. Tafadhali jaribu tena.', 'error');
                matangazo = [];
            } finally {
                loading = false;
                refreshing = false;
                render();
            }
        }

        function refreshData() {
            if (refreshing) return;
            refreshing = true;
            fetchMatangazo();
        }

        // ---------- VIDEO STREAMING (segment buffering) ----------
        // Videos get data-src instead of src, and are attached ONLY when the
        // card scrolls near the viewport (IntersectionObserver). The browser
        // then streams the file progressively via HTTP Range requests
        // (Cloudinary supports ranges), buffering segments as needed instead
        // of downloading every video on the page up front. .m3u8 sources get
        // real segment streaming through hls.js.
        function attachVideoElement(video) {
            const src = video.getAttribute('data-src');
            if (!src || video.dataset.attached === '1') return;
            video.dataset.attached = '1';

            if (/\.m3u8(\?|$)/i.test(src)) {
                if (video.canPlayType('application/vnd.apple.mpegurl')) {
                    video.src = src; // Safari native HLS
                } else if (window.Hls && window.Hls.isSupported()) {
                    const hls = new window.Hls();
                    hls.loadSource(src);
                    hls.attachMedia(video);
                } else if (!document.getElementById('hlsJsScript')) {
                    const s = document.createElement('script');
                    s.id = 'hlsJsScript';
                    s.src = 'https://cdn.jsdelivr.net/npm/hls.js@1';
                    s.onload = () => {
                        const hls = new window.Hls();
                        hls.loadSource(src);
                        hls.attachMedia(video);
                    };
                    document.head.appendChild(s);
                }
            } else {
                video.src = src; // native progressive streaming (Range requests)
            }
        }

        function setupVideoLazyLoading() {
            const videos = container.querySelectorAll('video[data-src]');
            if (videos.length === 0) return;

            if (!('IntersectionObserver' in window)) {
                videos.forEach(attachVideoElement);
                return;
            }

            const io = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        attachVideoElement(entry.target);
                        io.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '250px 0px' });

            videos.forEach(v => io.observe(v));
        }

        // Render the UI
        function render() {
            if (loading) {
                container.innerHTML = `
                    <div class="loading-container">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">Inapakua matangazo...</div>
                    </div>
                `;
                return;
            }

            if (matangazo.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">${ic('megaphone', 56)}</div>
                        <div class="empty-text">Hakuna matangazo yanayopatikana</div>
                        <div class="empty-sub">Wa kwanza kutangaza!</div>
                        <button class="refresh-btn" id="refreshBtn" style="margin-top:20px;background:#667eea;color:white;padding:10px 24px;border-radius:10px;border:none;cursor:pointer;">${ic('refresh', 13)} Pakia Upya</button>
                    </div>
                `;
                const refreshBtn = document.getElementById('refreshBtn');
                if (refreshBtn) refreshBtn.addEventListener('click', () => refreshData());
                return;
            }

            const postsHtml = matangazo.map(post => {
                const isLiked = likedPosts.has(String(post.id));
                const businessName = post.users?.business_name || post.title || 'Biashara';
                const ownerName = post.users?.full_name || 'Mfanyabiashara';
                const phoneNumber = post.users?.phone || 'Hakuna namba ya simu';
                const email = post.users?.email || '';
                const createdAt = new Date(post.created_at).toLocaleDateString('sw-TZ');
                const isVideo = post.media_type === 'video';
                const poster = post.thumbnail_url || '';

                return `
                    <div class="post-card" data-post-id="${post.id}">
                        <div class="post-header">
                            <div class="business-info">
                                <div class="avatar">
                                    ${avatarHtml(businessName, post.users?.business_logo_url)}
                                </div>
                                <div class="business-details">
                                    <div class="business-name">${escapeHtml(businessName)}</div>
                                    <div class="owner-name">${escapeHtml(ownerName)}</div>
                                </div>
                            </div>
                            <button class="order-btn" data-order="${post.id}">Agiza</button>
                        </div>

                        <div class="media-container">
                            ${isVideo ?
                                `<video class="media-video" controls playsinline preload="metadata" poster="${escapeHtml(poster)}" data-src="${escapeHtml(post.media_url)}"></video>` :
                                `<img class="media-image" src="${escapeHtml(post.media_url)}" alt="Tangazo" loading="lazy" onerror="this.style.display='none'">`
                            }
                        </div>

                        <div class="post-actions">
                            <button class="action-btn ${isLiked ? 'liked' : ''}" id="likeBtn_${post.id}" data-like="${post.id}">
                                <i class="${isLiked ? 'fa-solid' : 'fa-regular'} fa-heart" style="font-size:13px;" aria-hidden="true"></i> Penda (${post.like_count || 0})
                            </button>
                            <button class="action-btn" id="reportBtn_${post.id}" data-report="${post.id}">
                                ${ic('report', 13)} Ripoti (${post.report_count || 0})
                            </button>
                        </div>

                        <div class="description-box">
                            <div class="description-text">
                                <span class="business-name-highlight">${escapeHtml(businessName)}:</span> ${escapeHtml(post.description)}
                            </div>
                        </div>

                        <div class="contact-box">
                            <div class="contact-title">${ic('phone', 12)} Wasiliana Nasi:</div>
                            <div class="contact-phone" data-phone="${escapeHtml(phoneNumber)}" data-name="${escapeHtml(businessName)}">${escapeHtml(phoneNumber)}</div>
                            ${email ? `<div class="contact-email">${ic('mail', 12)} ${escapeHtml(email)}</div>` : ''}
                            <div class="contact-phone" style="font-style:italic;color:#7f8c8d;cursor:default;">Biashara: ${escapeHtml(businessName)}</div>
                        </div>

                        <div class="post-footer">
                            <div class="timestamp">Imechapishwa: ${createdAt}</div>
                        </div>
                    </div>
                `;
            }).join('');

            const userGreeting = userName ? `Karibu, ${escapeHtml(userName)}! ` : '';
            const loginWarningHtml = !userToken ? `
                <div class="login-warning">
                    <div class="login-warning-text">${ic('warning', 12)} Ingia kwenye akaunti yako kupenda au kuripoti matangazo</div>
                </div>
            ` : '';

            container.innerHTML = `
                <div class="list-header">
                    <div class="screen-title">Matangazo</div>
                    <div class="screen-subtitle">${userGreeting}Gundua bidhaa na huduma mpya</div>
                    <div class="info-text">${ic('chart', 13)} ${matangazo.length} matangazo yanapatikana</div>
                    ${loginWarningHtml}
                </div>
                ${postsHtml}
                <div style="text-align:center;margin-top:16px;">
                    <button id="refreshBtn" style="background:#667eea;color:white;padding:10px 24px;border-radius:10px;border:none;cursor:pointer;">${ic('refresh', 13)} Pakia Upya</button>
                </div>
            `;

            // Event delegation on the stable container: survives any partial
            // DOM updates, and ids are matched as STRINGS (UUIDs — parseInt
            // mangles them, which is why Agiza/like silently did nothing).
            container.querySelectorAll('[data-like]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    handleLike(btn.getAttribute('data-like'));
                });
            });

            container.querySelectorAll('[data-report]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    handleReport(btn.getAttribute('data-report'));
                });
            });

            container.querySelectorAll('[data-order]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    handleOrder(btn.getAttribute('data-order'));
                });
            });

            container.querySelectorAll('.contact-phone[data-phone]').forEach(phoneEl => {
                phoneEl.addEventListener('click', (e) => {
                    const phone = phoneEl.getAttribute('data-phone');
                    const name = phoneEl.getAttribute('data-name');
                    if (phone && phone !== 'Hakuna namba ya simu') {
                        showContactModal({ phone: phone, business_name: name });
                    }
                });
            });

            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) refreshBtn.addEventListener('click', () => refreshData());

            // Attach lazy video streaming AFTER the cards are in the DOM.
            setupVideoLazyLoading();
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
            await fetchMatangazo();
        }

        init();
    </script>
</body>
</html>
@endverbatim
