@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dukamkononi - Mteja</title>
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
        .mteja-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR - acts like bottom tab bar on mobile, but vertical sidebar for web */
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

        /* Navigation items (replaces Tabs.Screen) */
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

        .nav-item.active .nav-icon {
            color: #667eea;
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
            border-top-color: #667eea;
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
@include('partials.toast')
@verbatim
    <!-- Mobile menu toggle button -->
    <div class="mobile-menu-toggle" id="mobileMenuToggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="2">
            <path d="M3 12H21M3 6H21M3 18H21"/>
        </svg>
    </div>

    <div class="mteja-layout">
        <!-- Sidebar (replaces _layout.tsx Tabs) -->
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
                <!-- Navigation items mirroring Tabs.Screen components -->
                <a href="biashara" class="nav-item" data-page="biashara">
                    <div class="nav-icon">🏪</div>
                    <span class="nav-label">Biashara</span>
                </a>

                <a href="matangazo" class="nav-item" data-page="matangazo">
                    <div class="nav-icon">📢</div>
                    <span class="nav-label">Matangazo</span>
                </a>

                <a href="profaili" class="nav-item" data-page="profaili">
                    <div class="nav-icon">👤</div>
                    <span class="nav-label">Profaili</span>
                </a>
            </div>

            <div class="sidebar-footer">
                <div class="user-info" id="userInfo">
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

        <!-- Main Content: Dynamic page loader -->
        <main class="main-content">
            <div class="page-container" id="pageContainer">
                <!-- Dynamic content loads here -->
                <div style="text-align: center; padding: 60px 20px;">
                    <div class="spinner" style="border-top-color: #667eea;"></div>
                    <p style="margin-top: 20px; color: #7f8c8d;">Loading...</p>
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
        // MTEJA LAYOUT (Replaces _layout.tsx Tabs)
        // Handles: 
        // - Navigation between pages (biashara, matangazo, profaili)
        // - Authentication check (user must be logged in as customer)
        // - Active state for sidebar items
        // - Page loading without full refresh
        // - Logout functionality
        // - Mobile sidebar toggle
        // ============================================

        const API_BASE_URL = '';

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
                // Not logged in, redirect to login with customer role
                window.location.href = '/login?role=mteja';
                return false;
            }
            // Check if user is customer/mteja
            const userRole = user.role || '';
            if (userRole !== 'customer' && userRole !== 'client' && userRole !== 'mteja') {
                showToast('Huna ruhusa ya kuingia kwenye eneo la Mteja.', 'error');
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
            const displayName = user.full_name || user.email?.split('@')[0] || 'Mteja';
            if (userNameEl) userNameEl.textContent = displayName;
            if (userAvatarEl) {
                if (user.business_logo_url) {
                    userAvatarEl.innerHTML = `<img src="${user.business_logo_url}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
                } else {
                    userAvatarEl.textContent = displayName.charAt(0).toUpperCase();
                }
            }
        }

        // Page routes mapping (paths relative to mteja folder)
        const routes = {
            biashara: '/mteja/biashara',
            matangazo: '/mteja/matangazo',
            profaili: '/mteja/profaili'
        };

        // Page titles
        const pageTitles = {
            biashara: 'Biashara - DukaMkononi Mteja',
            matangazo: 'Matangazo - DukaMkononi Mteja',
            profaili: 'Profaili - DukaMkononi Mteja'
        };

        let currentPage = 'biashara';

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
                document.title = pageTitles[pageName] || 'DukaMkononi - Mteja';
                
                // Update active state in sidebar
                updateActiveNavItem(pageName);
                
                // Update URL without reload
                const newUrl = `/mteja/${pageName}`;
                window.history.pushState({ page: pageName }, '', newUrl);
                
            } catch (error) {
                console.error('Failed to load page:', error);
                const pageContainer = document.getElementById('pageContainer');
                if (pageContainer) {
                    pageContainer.innerHTML = `
                        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 20px;">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="1.5">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 8V12M12 16H12.01"/>
                            </svg>
                            <h3 style="margin-top: 20px; color: #667eea;">Hitilafu</h3>
                            <p style="margin-top: 10px; color: #7f8c8d;">Huwezi kupakia ukurasa huu. Hakikisha faili zote zipo.</p>
                            <p style="margin-top: 8px; font-size: 12px; color: #95a5a6;">${error.message}</p>
                            <button onclick="location.reload()" style="margin-top: 20px; padding: 10px 24px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer;">Jaribu Tena</button>
                        </div>
                    `;
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
            const keys = ['savedEmail_mteja', 'savedPassword_mteja', 'rememberMe_mteja'];
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
                if (path.includes('/mteja/')) {
                    const pageName = path.split('/').pop();
                    if (routes[pageName]) {
                        loadPage(pageName);
                    }
                }
            });
        }

        // Initialize: check auth, then load default page (biashara)
        async function init() {
            if (!checkAuth()) return;
            
            setupEventListeners();
            
            // Determine which page to load from URL path
            let initialPage = 'biashara';
            const path = window.location.pathname;
            if (path.includes('/mteja/')) {
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
        window.mtejaNavigate = navigateTo;
    </script>
</body>
</html>
@endverbatim