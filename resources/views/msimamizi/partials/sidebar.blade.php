@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title data-i18n="msimamizi_sidebar.page_title">Dukamkononi - Msimamizi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
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
        .msimamizi-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR (acts like bottom tab bar on mobile, but vertical sidebar for web) */
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
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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
            margin-top: 2px;
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
        }

        .nav-item:hover {
            background-color: #fdeaea;
        }

        .nav-item.active {
            background-color: #fdeaea;
            color: #e74c3c;
        }

        .nav-item.active .nav-icon {
            color: #e74c3c;
        }

        .nav-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background-color: #fdeaea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e74c3c;
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
            color: #e74c3c;
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

        /* MAIN CONTENT AREA (where page content loads) */
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

        /* Iframe container for page content (or we can use dynamic div loading) */
        .page-container {
            padding: 24px 32px;
            width: 100%;
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
            border-top-color: #e74c3c;
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
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2">
            <path d="M3 12H21M3 6H21M3 18H21"/>
        </svg>
    </div>

    <div class="msimamizi-layout">
        <!-- Sidebar (replaces _layout.tsx tabs) -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-area">
                    <div class="logo-icon" data-i18n="msimamizi_sidebar.logo_short">D</div>
                    <div class="logo-text">
                        <h2 data-i18n="msimamizi_sidebar.logo_brand">DukaMkononi</h2>
                        <p data-i18n="msimamizi_sidebar.logo_tagline">Msimamizi Portal</p>
                    </div>
                </div>
            </div>

            <div class="nav-items">
                <!-- Navigation items mirroring Tabs.Screen components -->
                <div class="nav-item" data-page="index" data-title="Nyumbani"
                     data-i18n-attr="data-title" data-i18n="msimamizi_sidebar.nav_home">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 9L12 3L21 9L12 15L3 9Z" stroke-linecap="round"/>
                            <path d="M5 10.5V16.5L12 21L19 16.5V10.5" stroke-linecap="round"/>
                            <path d="M12 15V21" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="msimamizi_sidebar.nav_home">Nyumbani</span>
                </div>

                <div class="nav-item" data-page="ripoti" data-title="Ripoti"
                     data-i18n-attr="data-title" data-i18n="msimamizi_sidebar.nav_reports">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z"/>
                            <path d="M8 7V17M12 7V17M16 7V17"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="msimamizi_sidebar.nav_reports">Ripoti</span>
                </div>

                <div class="nav-item" data-page="preview" data-title="Rejea"
                     data-i18n-attr="data-title" data-i18n="msimamizi_sidebar.nav_reviews">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M8 2V5M16 2V5M3 9H21M5 3H19C20.1 3 21 3.9 21 5V19C21 20.1 20.1 21 19 21H5C3.9 21 3 20.1 3 19V5C3 3.9 3.9 3 5 3Z"/>
                            <circle cx="12" cy="13" r="2"/>
                            <path d="M12 15V18M9 21H15"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="msimamizi_sidebar.nav_reviews">Rejea</span>
                </div>

                <div class="nav-item" data-page="bidhaa-mpya" data-title="Bidhaa Mpya"
                     data-i18n-attr="data-title" data-i18n="msimamizi_sidebar.nav_new_products">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 5V19M5 12H19" stroke-linecap="round"/>
                            <circle cx="12" cy="12" r="9"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="msimamizi_sidebar.nav_new_products">Bidhaa Mpya</span>
                </div>

                <div class="nav-item" data-page="tangaza" data-title="Tangaza"
                     data-i18n-attr="data-title" data-i18n="msimamizi_sidebar.nav_advertise">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 11L7 9L14 4L19 9L14 14L7 19L3 17V11Z" stroke-linejoin="round"/>
                            <path d="M14 14L19 19M19 9L14 14" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="msimamizi_sidebar.nav_advertise">Tangaza</span>
                </div>
            </div>

            <div class="sidebar-footer">
                <div class="user-info" id="userInfo">
                    <div class="user-avatar" id="userAvatar">M</div>
                    <div class="user-details">
                        <div class="user-name" id="userName" data-i18n="msimamizi_sidebar.nav_admin">Msimamizi</div>
                        <div class="user-role" data-i18n="msimamizi_sidebar.nav_admin">Msimamizi</div>
                    </div>
                </div>
                <div class="logout-btn" id="logoutBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M15 3H19C20.1 3 21 3.9 21 5V19C21 20.1 20.1 21 19 21H15M10 17L15 12L10 7M15 12H3"/>
                    </svg>
                    <span data-i18n="msimamizi_sidebar.btn_logout">Ondoka</span>
                </div>
            </div>
        </aside>

        <!-- Main Content: Dynamic page loader -->
        <main class="main-content">
            <div class="page-container" id="pageContainer">
                <!-- Dynamic content loads here -->
                <div style="text-align: center; padding: 60px 20px;">
                    <div class="spinner" style="border-top-color: #e74c3c;"></div>
                    <p style="margin-top: 20px; color: #7f8c8d;" data-i18n="msimamizi_sidebar.loading">Loading...</p>
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
        // MSIMAMIZI LAYOUT (Replaces _layout.tsx Tabs)
        // Handles: 
        // - Navigation between pages (index, ripoti, preview, bidhaa-mpya, tangaza)
        // - Authentication check (user must be logged in as admin)
        // - Active state for sidebar items
        // - Page loading without full refresh (fetch HTML snippets or full pages)
        // - Logout functionality
        // - Mobile sidebar toggle
        // ============================================

        const API_BASE_URL = '';
        // Set only while the fetch-failure panel is on screen, so a language switch
        // can rebuild it; cleared as soon as a page loads successfully.
        let loadErrorMessage = null;

        const SW = {
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la Msimamizi.',
            error_title: 'Hitilafu',
            err_page_load: 'Huwezi kupakia ukurasa huu. Hakikisha faili zote zipo.',
            nav_admin: 'Msimamizi',
            page_title: 'Dukamkononi - Msimamizi',
            title_home: 'Nyumbani - DukaMkononi',
            title_reports: 'Ripoti - DukaMkononi',
            title_reviews: 'Rejea - DukaMkononi',
            title_new_products: 'Bidhaa Mpya - DukaMkononi',
            title_advertise: 'Tangaza - DukaMkononi'
        };
        // Accepts either a bare key ('error_title') or a fully qualified one
        // ('msimamizi_sidebar.title_home'), so call sites can read naturally.
        function t(key, params) {
            const full = key.indexOf('msimamizi_sidebar.') === 0 ? key : 'msimamizi_sidebar.' + key;
            let value = key;
            if (window.DM && typeof window.DM.t === 'function') {
                const hit = window.DM.t(full, params);
                if (hit !== full) value = hit;
            }
            if (value === key) {
                const bare = key.indexOf('.') > -1 ? key.split('.').pop() : key;
                if (Object.prototype.hasOwnProperty.call(SW, bare)) value = SW[bare];
            }
            return value;
        }
        // The fetch-failure panel is built as markup, so it has to be rebuilt to
        // follow the language instead of being translated once at load time.
        function renderLoadError(message) {
            return `
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 20px;">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8V12M12 16H12.01"/>
                    </svg>
                    <h3 style="margin-top: 20px; color: #e74c3c;">${t('error_title')}</h3>
                    <p style="margin-top: 10px; color: #7f8c8d;">${t('err_page_load')}</p>
                    <p style="margin-top: 8px; font-size: 12px; color: #95a5a6;">${message}</p>
                </div>
            `;
        }
        function paintDynamicText() {
            const nameEl = document.getElementById('userName');
            const roleEl = document.querySelector('.user-role');
            const user = getCurrentUser();
            const fallback = t('nav_admin');
            if (nameEl && (!nameEl.dataset.dmUserName || !user)) {
                nameEl.textContent = user ? (user.full_name || user.business_name ||
                    (user.email || '').split('@')[0] || fallback) : fallback;
            }
            if (roleEl) roleEl.textContent = fallback;
            if (loadErrorMessage) {
                const pageContainer = document.getElementById('pageContainer');
                if (pageContainer) pageContainer.innerHTML = renderLoadError(loadErrorMessage);
            }
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

        // Check authentication and role
        function checkAuth() {
            const user = getCurrentUser();
            if (!user) {
                // Not logged in, redirect to login
                window.location.href = '/login?role=msimamizi';
                return false;
            }
            // Check if user is admin/msimamizi
            if (user.role !== 'admin' && user.role !== 'msimamizi') {
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
            const displayName = user.full_name || user.business_name || user.email?.split('@')[0] || t('nav_admin');
            if (userNameEl) {
                userNameEl.textContent = displayName;
                // Marks the name as visitor data, so a later language switch leaves
                // it alone instead of replacing it with the generic fallback.
                userNameEl.dataset.dmUserName = '1';
            }
            if (userAvatarEl) {
                if (user.business_logo_url) {
                    userAvatarEl.innerHTML = `<img src="${user.business_logo_url}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
                } else {
                    userAvatarEl.textContent = displayName.charAt(0).toUpperCase();
                }
            }
        }

        // Page routes mapping (paths relative to msimamizi folder)
        const routes = {
            index: 'msimamizi/index',
            ripoti: '/msimamizi/ripoti',
            preview: '/msimamizi/preview',
            'bidhaa-mpya': '/msimamizi/bidhaa-mpya',
            tangaza: '/msimamizi/tangaza'
        };

        // Page titles
        // Keys, not strings: the label is looked up so it follows the language.
        const pageTitleKeys = {
            index: 'msimamizi_sidebar.title_home',
            ripoti: 'msimamizi_sidebar.title_reports',
            preview: 'msimamizi_sidebar.title_reviews',
            'bidhaa-mpya': 'msimamizi_sidebar.title_new_products',
            tangaza: 'msimamizi_sidebar.title_advertise'
        };
        const pageTitles = {};
        Object.keys(pageTitleKeys).forEach(name => {
            pageTitles[name] = t(pageTitleKeys[name]);
        });

        let currentPage = 'index';

        // Show loading overlay
        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = show ? 'flex' : 'none';
            }
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
                
                // Extract content inside body or main area if needed, but we can inject full HTML
                // However to avoid duplicating entire page layout, we can extract the main content.
                // For simplicity and because each page might be a full HTML, we'll extract the body content.
                // Better approach: create a temporary div to parse and extract the main content area.
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Try to find the main content (page-container or .container or body content)
                let content = doc.querySelector('.container') || doc.querySelector('.page-content') || doc.querySelector('body');
                
                let contentHtml = '';
                if (content && content.tagName === 'BODY') {
                    // If body, get innerHTML but remove scripts to avoid double execution (we'll handle scripts)
                    contentHtml = content.innerHTML;
                } else if (content) {
                    contentHtml = content.outerHTML;
                } else {
                    contentHtml = html;
                }
                
                loadErrorMessage = null;

                // Inject into page container
                const pageContainer = document.getElementById('pageContainer');
                if (pageContainer) {
                    pageContainer.innerHTML = contentHtml;
                    
                    // Re-execute any scripts that were in the loaded content (for page-specific JS)
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
                document.title = pageTitles[pageName] || t('page_title');
                
                // Update active state in sidebar
                updateActiveNavItem(pageName);
                
            } catch (error) {
                console.error('Failed to load page:', error);
                loadErrorMessage = error.message;
                const pageContainer = document.getElementById('pageContainer');
                if (pageContainer) {
                    pageContainer.innerHTML = renderLoadError(error.message);

                }
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

        // Setup event listeners
        function setupEventListeners() {
            // Navigation clicks
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', (e) => {
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
            
            // Close sidebar when clicking outside on mobile (optional)
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    const sidebar = document.getElementById('sidebar');
                    const toggle = document.getElementById('mobileMenuToggle');
                    if (sidebar && sidebar.classList.contains('open') && 
                        !sidebar.contains(e.target) && 
                        !toggle.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                }
            });
        }

        // Initialize: check auth, then load default page (index)
        async function init() {
            if (!checkAuth()) return;
            
            setupEventListeners();
            
            // Determine which page to load from URL hash or default to index
            let initialPage = 'index';
            const hash = window.location.hash.substring(1);
            if (hash && routes[hash]) {
                initialPage = hash;
            }
            
            await loadPage(initialPage);
        }
        
        // Follow the language: the sidebar markup is handled by data-i18n, but the
        // document title and the fetch-failure panel are built in script.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => {
                Object.keys(pageTitleKeys).forEach(name => {
                    pageTitles[name] = t(pageTitleKeys[name]);
                });
                paintDynamicText();
                if (currentPage && pageTitleKeys[currentPage]) {
                    document.title = pageTitles[currentPage];
                }
            });
        }
        paintDynamicText();

        // Run init
        init();
        
        // Expose navigateTo for any dynamic links inside loaded pages
        window.msimamiziNavigate = navigateTo;
    </script>
</body>
</html>
@endverbatim