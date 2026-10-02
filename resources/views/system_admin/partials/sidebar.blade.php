@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="system_admin_sidebar.page_title">Dukamkononi - System Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* Main layout wrapper - sidebar + content */
        .system-admin-layout {
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

        /* Sidebar header / logo area */
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
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
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
            background-color: #f3e8ff;
        }

        .nav-item.active {
            background-color: #f3e8ff;
            color: #9b59b6;
        }

        .nav-item.active .nav-icon {
            color: #9b59b6;
        }

        .nav-icon {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .nav-label {
            font-size: 15px;
            font-weight: 600;
        }

        /* Sidebar footer (user info / logout) */
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
            background-color: #f3e8ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9b59b6;
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
            color: #9b59b6;
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
        }

        /* Top bar for mobile responsive */
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
            padding: 24px 32px;
            width: 100%;
            min-height: 100vh;
        }

        /* Loading overlay */
        .loading-overlay {
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
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #9b59b6;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-menu-toggle {
                display: block;
            }
            .page-container {
                padding: 20px 16px;
                padding-top: 70px;
            }
        }

        /* Active link indicator */
        .nav-item {
            position: relative;
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.toast')
@verbatim
    <!-- Mobile menu toggle button -->
    <div class="mobile-menu-toggle" id="mobileMenuToggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="2">
            <path d="M3 12H21M3 6H21M3 18H21"/>
        </svg>
    </div>

    <div class="system-admin-layout">
        <!-- Sidebar (persistent navigation) -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-area">
                    <div class="logo-icon" data-i18n="system_admin_sidebar.logo_short">D</div>
                    <div class="logo-text">
                        <h2 data-i18n="system_admin_sidebar.logo_brand">DukaMkononi</h2>
                        <p data-i18n="system_admin_sidebar.logo_tagline">System Admin</p>
                    </div>
                </div>
            </div>

            <div class="nav-items">
                <!-- Navigation items for System Admin -->
                <a href="dashboard" class="nav-item" data-page="dashboard">
                    <div class="nav-icon">📊</div>
                    <span class="nav-label" data-i18n="system_admin_sidebar.nav_dashboard">Dashbodi</span>
                </a>

                <a href="index" class="nav-item" data-page="index">
                    <div class="nav-icon">🏠</div>
                    <span class="nav-label" data-i18n="system_admin_sidebar.nav_home">Nyumbani</span>
                </a>

                <a href="notify" class="nav-item" data-page="notify">
                    <div class="nav-icon">🔔</div>
                    <span class="nav-label" data-i18n="system_admin_sidebar.nav_notify">Notisi</span>
                </a>
            </div>

            <div class="sidebar-footer">
                <div class="user-info" id="userInfo">
                    <div class="user-avatar" id="userAvatar">A</div>
                    <div class="user-details">
                        <div class="user-name" id="userName">System Admin</div>
                        <div class="user-role" data-i18n="system_admin_sidebar.user_role">Msimamizi Mkuu</div>
                    </div>
                </div>
                <div class="logout-btn" id="logoutBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M15 3H19C20.1 3 21 3.9 21 5V19C21 20.1 20.1 21 19 21H15M10 17L15 12L10 7M15 12H3"/>
                    </svg>
                    <span data-i18n="system_admin_sidebar.btn_logout">Ondoka</span>
                </div>
            </div>
        </aside>

        <!-- Main Content: Dynamic page loader -->
        <main class="main-content">
            <div class="page-container" id="pageContainer">
                <!-- Dynamic content loads here -->
                <div style="text-align: center; padding: 60px 20px;">
                    <div class="spinner" style="border-top-color: #9b59b6;"></div>
                    <p data-i18n="system_admin_sidebar.loading" style="margin-top: 20px; color: #7f8c8d;">Loading...</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <script>
        // ============================================
        // SYSTEM ADMIN LAYOUT
        // Handles: 
        // - Navigation between pages (dashboard, index, notify)
        // - Authentication check (user must be logged in as system admin)
        // - Active state for sidebar items
        // - Page loading without full refresh
        // - Logout functionality
        // - Mobile sidebar toggle
        // ============================================

        const API_BASE_URL = '';

        // Swahili fallback used when the language widget is not present.
        // Values mirror the sw catalog of the system_admin_sidebar section in locales.json.
        const SW = {
            err_no_permission: "Huna ruhusa ya kuingia kwenye eneo la System Admin.",
            user_fallback: "System Admin",
            error_title: "Hitilafu",
            err_page_load: "Huwezi kupakia ukurasa huu. Hakikisha faili zote zipo.",
            btn_retry: "Jaribu Tena",
            confirm_logout: "Una uhakika unataka kutoka?",
            title_default: "DukaMkononi - System Admin",
            title_dashboard: "Dashbodi - DukaMkononi System Admin",
            title_home: "Nyumbani - DukaMkononi System Admin",
            title_notify: "Notisi - DukaMkononi System Admin"
        };

        function t(key, params) {
            const full = key.indexOf('system_admin_sidebar.') === 0 ? key : 'system_admin_sidebar.' + key;
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

        // Get current user from localStorage (set during login)
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

        // Check authentication and role (must be system admin)
        function checkAuth() {
            const user = getCurrentUser();
            if (!user) {
                // Not logged in, redirect to login
                window.location.href = '/login?role=msimamizi';
                return false;
            }
            
            // Check if user is system admin (email check)
            const userEmail = user.email || '';
            const isSystemAdmin = userEmail === "cosmavictorini1994@gmail.com";
            
            if (!isSystemAdmin && user.role !== 'system_admin') {
                showToast(t('err_no_permission'), 'error');
                window.location.href = '/home';
                return false;
            }
            
            // Update sidebar with user info
            updateUserInfo(user);
            return true;
        }

        function updateUserInfo(user) {
            const userNameEl = document.getElementById('userName');
            const userAvatarEl = document.getElementById('userAvatar');
            const displayName = user.full_name || user.email?.split('@')[0] || t('user_fallback');
            if (userNameEl) userNameEl.textContent = displayName;
            if (userAvatarEl) {
                if (user.business_logo_url) {
                    userAvatarEl.innerHTML = `<img src="${user.business_logo_url}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
                } else {
                    userAvatarEl.textContent = displayName.charAt(0).toUpperCase();
                }
            }
        }

        // Page routes mapping (paths relative to system_admin folder)
        const routes = {
            dashboard: '/system_admin/dashboard',
            index: '/system_admin/index',
            notify: '/system_admin/notify'
        };

        // Page titles
        const pageTitles = {
            dashboard: 'title_dashboard',
            index: 'title_home',
            notify: 'title_notify'
        };

        let currentPage = 'dashboard';

        // Show loading overlay
        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = show ? 'flex' : 'none';
            }
        }

        // Load-failure panel (state kept so a language switch can repaint it)
        let lastLoadError = null;
        function renderLoadError() {
            const pageContainer = document.getElementById('pageContainer');
            if (!pageContainer) return;
            pageContainer.innerHTML = `
                        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 20px;">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 8V12M12 16H12.01"/>
                            </svg>
                            <h3 style="margin-top: 20px; color: #9b59b6;">${t('error_title')}</h3>
                            <p style="margin-top: 10px; color: #7f8c8d;">${t('err_page_load')}</p>
                            <p style="margin-top: 8px; font-size: 12px; color: #95a5a6;">${lastLoadError}</p>
                            <button onclick="location.reload()" style="margin-top: 20px; padding: 10px 24px; background: #9b59b6; color: white; border: none; border-radius: 8px; cursor: pointer;">${t('btn_retry')}</button>
                        </div>
                    `;
        }

        // Load page content dynamically via fetch
        async function loadPage(pageName) {
            const pageUrl = routes[pageName];
            if (!pageUrl) {
                console.error('Page not found:', pageName);
                return;
            }

            showLoading(true);
            currentPage = pageName;

            try {
                // Fetch the HTML content of the page
                const response = await fetch(pageUrl);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                let html = await response.text();
                
                // Extract content inside body or main area
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Try to find the main content
                let content = doc.querySelector('.page-content') || doc.querySelector('.container') || doc.querySelector('body');
                
                let contentHtml = '';
                if (content && content.tagName === 'BODY') {
                    contentHtml = content.innerHTML;
                } else if (content) {
                    contentHtml = content.outerHTML;
                } else {
                    contentHtml = html;
                }
                
                // Inject into page container
                const pageContainer = document.getElementById('pageContainer');
                if (pageContainer) {
                    pageContainer.innerHTML = contentHtml;
                    lastLoadError = null;
                    
                    // Re-execute any scripts that were in the loaded content
                    const scripts = pageContainer.querySelectorAll('script');
                    scripts.forEach(oldScript => {
                        const newScript = document.createElement('script');
                        if (oldScript.src) {
                            newScript.src = oldScript.src;
                        } else {
                            newScript.textContent = oldScript.textContent;
                        }
                        document.body.appendChild(newScript);
                        oldScript.remove();
                    });
                }
                
                // Update page title
                document.title = t(pageTitles[pageName] || 'title_default');
                
                // Update active state in sidebar
                updateActiveNavItem(pageName);
                
                // Update URL without reload
                const newUrl = `/system_admin/${pageName}`;
                window.history.pushState({ page: pageName }, '', newUrl);
                
            } catch (error) {
                console.error('Failed to load page:', error);
                lastLoadError = error.message;
                renderLoadError();
            } finally {
                showLoading(false);
            }
        }

        // Update active navigation item
        function updateActiveNavItem(pageName) {
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                const itemPage = item.getAttribute('data-page');
                if (itemPage === pageName) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        }

        // Navigation handler
        function navigateTo(pageName) {
            if (pageName === currentPage) return;
            loadPage(pageName);
        }

        // Logout function
        function handleLogout() {
            if (confirm(t('confirm_logout'))) {
                // Clear all auth data
                localStorage.removeItem('userToken');
                localStorage.removeItem('userData');
                localStorage.removeItem('userId');
                localStorage.removeItem('userEmail');
                localStorage.removeItem('userRole');
                localStorage.removeItem('userStatus');
                localStorage.removeItem('userName');
                localStorage.removeItem('userBusiness');
                
                // Also clear any saved credentials from login
                const keys = ['savedEmail_msimamizi', 'savedPassword_msimamizi', 'rememberMe_msimamizi'];
                keys.forEach(key => localStorage.removeItem(key));
                
                // Redirect to home
                window.location.href = '/home';
            }
        }

        // Setup event listeners
        function setupEventListeners() {
            // Navigation clicks
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = item.getAttribute('data-page');
                    if (page) {
                        navigateTo(page);
                    }
                    // Close sidebar on mobile after click
                    if (window.innerWidth <= 768) {
                        document.getElementById('sidebar')?.classList.remove('open');
                    }
                });
            });
            
            // Logout button
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', handleLogout);
            }
            
            // Mobile menu toggle
            const menuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
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
            
            // Handle browser back/forward buttons
            window.addEventListener('popstate', (event) => {
                const path = window.location.pathname;
                if (path.includes('/system_admin/')) {
                    const pageName = path.split('/').pop();
                    if (routes[pageName]) {
                        loadPage(pageName);
                    }
                }
            });
        }

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => {
                document.title = t(pageTitles[currentPage] || 'title_default');
                if (lastLoadError !== null) renderLoadError();
            });
        }

        // Initialize: check auth, then load default page (dashboard)
        async function init() {
            if (!checkAuth()) return;
            
            setupEventListeners();
            
            // Determine which page to load from URL path
            let initialPage = 'dashboard';
            const path = window.location.pathname;
            if (path.includes('/system_admin/')) {
                const pageFromUrl = path.split('/').pop();
                if (routes[pageFromUrl]) {
                    initialPage = pageFromUrl;
                }
            }
            
            await loadPage(initialPage);
        }
        
        // Run init
        init();
        
        // Expose navigateTo for any dynamic links inside loaded pages
        window.systemAdminNavigate = navigateTo;
    </script>
</body>
</html>
@endverbatim