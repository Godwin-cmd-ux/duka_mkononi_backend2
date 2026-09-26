@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Matumizi - Dukamkononi Muuzaji</title>
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
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        /* Matumizi specific styles */
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

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            color: #7f8c8d;
            font-size: 14px;
            text-align: center;
        }

        /* Header */
        .header {
            background: white;
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 16px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-btn {
            background: #f5f5f5;
            width: 40px;
            height: 40px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
            color: #2c3e50;
            font-size: 20px;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }

        .refresh-btn {
            background: #e8f8f0;
            width: 40px;
            height: 40px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            font-size: 18px;
        }

        /* Date Selector */
        .date-selector {
            background: white;
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .date-nav {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            color: #2ecc71;
        }

        .date-nav.disabled {
            color: #bdc3c7;
            cursor: not-allowed;
        }

        .date-display {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            padding: 8px 16px;
            border-radius: 25px;
        }

        .today-badge {
            background: #2ecc71;
            color: white;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
        }

        /* Profit Card */
        .profit-card {
            background: white;
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 16px;
        }

        .profit-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 16px;
        }

        .profit-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .profit-label {
            color: #7f8c8d;
            font-size: 14px;
        }

        .profit-value {
            font-weight: 600;
            font-size: 14px;
        }

        .cost-text { color: #e74c3c; }
        .gross-profit-text { color: #27ae60; }
        .expense-text { color: #e67e22; }
        .net-value { font-size: 18px; font-weight: 800; color: #27ae60; }
        .net-loss { color: #e74c3c; }

        .profit-divider {
            height: 1px;
            background: #ecf0f1;
            margin: 12px 0;
        }

        .stats-row {
            display: flex;
            justify-content: space-around;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #ecf0f1;
        }

        .stat-box {
            text-align: center;
        }

        .stat-number {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
        }

        /* Add Button */
        .add-btn {
            background: #27ae60;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            border-radius: 14px;
            margin-bottom: 20px;
            cursor: pointer;
            border: none;
            width: 100%;
            color: white;
            font-weight: 700;
        }

        /* Expenses Section */
        .expenses-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .expenses-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
        }

        .expenses-total {
            color: #27ae60;
            font-weight: 600;
        }

        /* Expense Item */
        .expense-item {
            background: white;
            padding: 16px;
            border-radius: 16px;
            margin-bottom: 10px;
        }

        .expense-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .expense-category {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .category-text {
            color: #3498db;
            font-weight: 600;
            font-size: 13px;
        }

        .delete-icon {
            color: #e74c3c;
            cursor: pointer;
            font-size: 18px;
        }

        .expense-description {
            font-size: 15px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .expense-footer {
            display: flex;
            justify-content: space-between;
        }

        .expense-amount {
            font-size: 16px;
            font-weight: 700;
        }

        .expense-time {
            font-size: 12px;
            color: #95a5a6;
        }

        .notes-box {
            display: flex;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ecf0f1;
            font-size: 12px;
            color: #7f8c8d;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 20px;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .empty-add {
            background: #e8f8f0;
            padding: 10px 20px;
            border-radius: 10px;
            color: #2ecc71;
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
        }

        /* Modal */
        .modal-overlay {
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

        .modal-content {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
        }

        .close-modal {
            cursor: pointer;
            font-size: 24px;
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
        }

        .required-star {
            color: #e74c3c;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
        }

        textarea.form-input {
            min-height: 80px;
            resize: vertical;
        }

        /* Categories */
        .category-scroll {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .category-chip {
            background: #f8f9fa;
            padding: 8px 16px;
            border-radius: 25px;
            border: 1px solid #ddd;
            cursor: pointer;
            font-size: 13px;
        }

        .category-chip.selected {
            background: #2ecc71;
            border-color: #2ecc71;
            color: white;
        }

        .selected-category {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px;
            background: #f0f7ff;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 6px;
        }

        .preview-box {
            background: #f0f7ff;
            padding: 12px;
            border-radius: 12px;
            margin-top: 12px;
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            padding: 20px;
            border-top: 1px solid #ecf0f1;
        }

        .cancel-btn {
            flex: 1;
            padding: 12px;
            background: #f8f9fa;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
        }

        .save-btn {
            flex: 1;
            padding: 12px;
            background: #2ecc71;
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        .save-btn.disabled {
            background: #bdc3c7;
            cursor: not-allowed;
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
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2">
            <path d="M3 12H21M3 6H21M3 18H21"/>
        </svg>
    </div>

    <div class="muuzaji-layout">
        <!-- SIDEBAR - Persistent navigation -->
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
                <a href="profaili" class="nav-item">
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
                <a href="matumizi" class="nav-item active">
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

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="page-container" id="matumiziContent">
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <div class="loading-text">Inapakia matumizi...</div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Expense Modal -->
    <div id="expenseModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Ongeza Matumizi</div>
                <div class="close-modal" onclick="closeModal()"><i class="fa-solid fa-xmark" style="font-size:20px;" aria-hidden="true"></i></div>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Kiasi (TZS) <span class="required-star">*</span></label>
                    <input type="number" id="expenseAmount" class="form-input" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Aina ya Matumizi <span class="required-star">*</span></label>
                    <div id="categoriesContainer" class="category-scroll"></div>
                    <div id="selectedCategoryDisplay" class="selected-category">
                        <span>✓</span> <span id="selectedCategoryText">Bado hujachagua</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Maelezo <span class="required-star">*</span></label>
                    <input type="text" id="expenseDesc" class="form-input" placeholder="Mfano: Kodisho za umeme">
                </div>
                <div class="form-group">
                    <label class="form-label">Maelezo ya Ziada</label>
                    <textarea id="expenseNotes" class="form-input" placeholder="Maelezo mengine (si lazima)"></textarea>
                </div>
                <div id="previewBox" class="preview-box" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button class="cancel-btn" id="cancelExpenseBtn" onclick="closeModal()">Ghairi</button>
                <button class="save-btn" id="saveExpenseBtn">Hifadhi</button>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // MATUMIZI SCREEN - Muuzaji Web Replica
        // With Sidebar Integration
        // ============================================
        
        const API_BASE_URL = '';

        // ============================== ICONS ==============================
        // Font Awesome 6 icon helper (same convention as the msimamizi pages).
        const FA_MAP = {
            user: 'fa-user', money: 'fa-money-bill-1', chart: 'fa-chart-line', cart: 'fa-cart-shopping',
            doc: 'fa-file-lines', refresh: 'fa-rotate-right', check: 'fa-check', x: 'fa-xmark',
            calendar: 'fa-calendar-days', plus: 'fa-plus', tag: 'fa-tag', trash: 'fa-trash-can',
            save: 'fa-floppy-disk', spinner: 'fa-spinner', warning: 'fa-triangle-exclamation'
        };
        function ic(name, size = 16, cls = '') {
            return `<i class="fa-solid ${FA_MAP[name] || 'fa-circle-info'} ${cls}" style="font-size:${size}px;" aria-hidden="true"></i>`;
        }

        // Expense-modal button busy state: Hifadhi shows a spinner while the
        // request runs; Ghairi is locked (and dimmed) so nothing can be
        // double-clicked mid-save.
        function setExpenseBusy(busy) {
            const save = document.getElementById('saveExpenseBtn');
            const cancel = document.getElementById('cancelExpenseBtn');
            if (save) {
                save.innerHTML = busy ? ic('spinner', 14, 'fa-spin') + ' Inahifadhi...' : 'Hifadhi';
                save.style.opacity = busy ? '0.7' : '1';
                save.style.pointerEvents = busy ? 'none' : 'auto';
            }
            if (cancel) {
                cancel.style.opacity = busy ? '0.5' : '1';
                cancel.style.pointerEvents = busy ? 'none' : 'auto';
            }
        }
        
        let expenses = [];
        let dailyProfit = null;
        let categories = [];
        let selectedDate = new Date().toISOString().split('T')[0];
        let loading = true;
        let selectedCategory = '';
        
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

        // Check authentication
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
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        function formatCurrency(amount) {
            return `TZS ${(amount || 0).toLocaleString()}`;
        }

        function formatTime(dateStr) {
            try {
                return new Date(dateStr).toLocaleTimeString('sw-TZ', { hour: '2-digit', minute: '2-digit' });
            } catch {
                return '';
            }
        }

        function formatDate(dateStr) {
            try {
                return new Date(dateStr).toLocaleDateString('sw-TZ', {
                    day: 'numeric', month: 'long', year: 'numeric'
                });
            } catch {
                return dateStr;
            }
        }

        // Load categories
        async function loadCategories() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            
            try {
                const response = await fetch(`${API_BASE_URL}/api/office-expenses/categories`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const data = await response.json();
                if (data.success) {
                    categories = data.categories;
                    if (categories.length > 0) {
                        selectedCategory = categories[0];
                    }
                }
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        }

        // Load data
        async function loadData() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            
            loading = true;
            render();
            
            try {
                // Load expenses
                const expensesRes = await fetch(
                    `${API_BASE_URL}/api/office-expenses/range?start_date=${selectedDate}&end_date=${selectedDate}`,
                    { headers: { 'Authorization': `Bearer ${token}` } }
                );
                
                // Load daily profit
                const profitRes = await fetch(
                    `${API_BASE_URL}/api/profit/daily/${selectedDate}`,
                    { headers: { 'Authorization': `Bearer ${token}` } }
                );
                
                if (expensesRes.ok) {
                    const data = await expensesRes.json();
                    expenses = data.expenses || [];
                }
                
                if (profitRes.ok) {
                    dailyProfit = await profitRes.json();
                }
            } catch (error) {
                console.error('Error loading data:', error);
                showToast('Hitilafu ya kupakia data', 'error');
            } finally {
                loading = false;
                render();
            }
        }

        // Add expense
        async function addExpense() {
            const amount = document.getElementById('expenseAmount')?.value;
            const description = document.getElementById('expenseDesc')?.value;
            const notes = document.getElementById('expenseNotes')?.value;
            
            if (!amount || parseFloat(amount) <= 0) {
                showToast('Tafadhali weka kiasi sahihi', 'warning');
                return;
            }
            if (!description.trim()) {
                showToast('Tafadhali weka maelezo ya matumizi', 'warning');
                return;
            }
            if (!selectedCategory) {
                showToast('Tafadhali chagua aina ya matumizi', 'warning');
                return;
            }
            
            const token = localStorage.getItem('userToken');
            
            setExpenseBusy(true);
            
            try {
                const response = await fetch(`${API_BASE_URL}/api/office-expenses`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        amount: parseFloat(amount),
                        description: description.trim(),
                        category: selectedCategory,
                        notes: notes.trim() || null
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Matumizi yameongezwa!', 'success');
                    closeModal();
                    await loadData();
                } else {
                    showToast(data.error || 'Imeshindikana kuongeza matumizi', 'error');
                }
            } catch (error) {
                console.error('Error adding expense:', error);
                showToast('Hitilafu ya mtandao', 'error');
            } finally {
                setExpenseBusy(false);
            }
        }

        // Delete expense
        async function deleteExpense(expenseId, amount) {
            if (confirm(`Unahakika unataka kufuta matumizi ya ${formatCurrency(amount)}?`)) {
                const token = localStorage.getItem('userToken');
                try {
                    const response = await fetch(`${API_BASE_URL}/api/office-expenses/${expenseId}`, {
                        method: 'DELETE',
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    const data = await response.json();
                    if (data.success) {
                        showToast('Matumizi yamefutwa!', 'success');
                        await loadData();
                    } else {
                        showToast(data.error || 'Imeshindikana kufuta', 'error');
                    }
                } catch (error) {
                    showToast('Hitilafu ya mtandao', 'error');
                }
            }
        }

        function changeDate(days) {
            const date = new Date(selectedDate);
            date.setDate(date.getDate() + days);
            selectedDate = date.toISOString().split('T')[0];
            loadData();
        }

        function openModal() {
            document.getElementById('expenseAmount').value = '';
            document.getElementById('expenseDesc').value = '';
            document.getElementById('expenseNotes').value = '';
            if (categories.length > 0) selectedCategory = categories[0];
            renderCategoryChips();
            document.getElementById('expenseModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('expenseModal').style.display = 'none';
        }

        function selectCategory(cat) {
            selectedCategory = cat;
            renderCategoryChips();
        }

        function renderCategoryChips() {
            const container = document.getElementById('categoriesContainer');
            const selectedText = document.getElementById('selectedCategoryText');
            
            if (container) {
                container.innerHTML = categories.map(cat => `
                    <div class="category-chip ${selectedCategory === cat ? 'selected' : ''}" onclick="selectCategory('${cat}')">
                        ${escapeHtml(cat)}
                    </div>
                `).join('');
            }
            
            if (selectedText) {
                selectedText.textContent = selectedCategory ? `Umechagua: ${selectedCategory}` : 'Bado hujachagua aina ya matumizi';
            }
            
            // Preview
            const amount = document.getElementById('expenseAmount')?.value;
            const previewBox = document.getElementById('previewBox');
            if (amount && selectedCategory && document.getElementById('expenseDesc')?.value) {
                previewBox.style.display = 'block';
                previewBox.innerHTML = `
                    <strong>Muhtasari</strong><br>
                    ${escapeHtml(selectedCategory)}: ${formatCurrency(parseFloat(amount) || 0)}<br>
                    <span style="font-size:13px;color:#666;">${escapeHtml(document.getElementById('expenseDesc').value)}</span>
                `;
            } else {
                previewBox.style.display = 'none';
            }
        }

        // Render UI
        function render() {
            const container = document.getElementById('matumiziContent');
            const today = new Date().toISOString().split('T')[0];
            const isToday = selectedDate === today;
            
            if (loading) {
                container.innerHTML = `
                    <div class="loading-container">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">Inapakia matumizi...</div>
                    </div>
                `;
                return;
            }
            
            const totalExpenses = expenses.reduce((sum, e) => sum + e.amount, 0);
            
            const expensesHtml = expenses.map(exp => `
                <div class="expense-item">
                    <div class="expense-header">
                        <div class="expense-category">
                            ${ic('tag', 13)}
                            <span class="category-text">${escapeHtml(exp.category)}</span>
                        </div>
                        <div class="delete-icon" id="delExpense_${exp.id}" onclick="deleteExpense('${exp.id}', ${exp.amount})" title="Futa">${ic('trash', 15)}</div>
                    </div>
                    <div class="expense-description">${escapeHtml(exp.description)}</div>
                    <div class="expense-footer">
                        <div class="expense-amount">${formatCurrency(exp.amount)}</div>
                        <div class="expense-time">${formatTime(exp.created_at)}</div>
                    </div>
                    ${exp.notes ? `<div class="notes-box">${ic('doc', 12)} <span>${escapeHtml(exp.notes)}</span></div>` : ''}
                </div>
            `).join('');
            
            container.innerHTML = `
                <div class="header">
                    <div class="header-top">
                        <a href="profaili" class="back-btn"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M19 12H5M11 18L5 12L11 6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                        <div class="title">Matumizi ya Ofisi</div>
                        <button class="refresh-btn" onclick="loadData()">${ic('refresh', 16)}</button>
                    </div>
                </div>
                
                <div class="date-selector">
                    <button class="date-nav" onclick="changeDate(-1)">←</button>
                    <div class="date-display">
                        ${ic('calendar', 14)}
                        <span>${formatDate(selectedDate)}</span>
                        ${isToday ? '<span class="today-badge">Leo</span>' : ''}
                    </div>
                    <button class="date-nav ${isToday ? 'disabled' : ''}" ${isToday ? 'disabled' : ''} onclick="changeDate(1)">→</button>
                </div>
                
                ${dailyProfit ? `
                    <div class="profit-card">
                        <div class="profit-title">Muhtasari wa Faida</div>
                        <div class="profit-row"><span class="profit-label">Mauzo (Jumla):</span><span class="profit-value">${formatCurrency(dailyProfit.revenue?.gross || 0)}</span></div>
                        <div class="profit-row"><span class="profit-label">Gharama za Bidhaa:</span><span class="profit-value cost-text">-${formatCurrency(dailyProfit.revenue?.cost_of_goods || 0)}</span></div>
                        <div class="profit-divider"></div>
                        <div class="profit-row"><span class="profit-label">Faida Ghafi:</span><span class="profit-value gross-profit-text">${formatCurrency(dailyProfit.gross_profit || 0)}</span></div>
                        <div class="profit-row"><span class="profit-label">Matumizi ya Ofisi:</span><span class="profit-value expense-text">-${formatCurrency(dailyProfit.expenses?.total || 0)}</span></div>
                        <div class="profit-divider"></div>
                        <div class="profit-row"><span class="profit-label" style="font-weight:700;">Faida Halisi:</span><span class="profit-value ${dailyProfit.net_profit < 0 ? 'net-loss' : 'net-value'}">${formatCurrency(dailyProfit.net_profit || 0)}${dailyProfit.net_profit < 0 ? ' (Hasara)' : ''}</span></div>
                        <div class="stats-row">
                            <div class="stat-box"><div class="stat-number">${dailyProfit.sales_count || 0}</div><div class="stat-label">Mauzo</div></div>
                            <div class="stat-box"><div class="stat-number">${dailyProfit.expenses_count || 0}</div><div class="stat-label">Matumizi</div></div>
                        </div>
                    </div>
                ` : ''}
                
                ${isToday ? `
                    <button class="add-btn" onclick="openModal()">
                        ${ic('plus', 14)} ONGEZA MATUMIZI
                    </button>
                ` : ''}
                
                <div class="expenses-header">
                    <div class="expenses-title">Matumizi ya ${isToday ? 'Leo' : 'Siku hii'}</div>
                    ${expenses.length > 0 ? `<div class="expenses-total">Jumla: ${formatCurrency(totalExpenses)}</div>` : ''}
                </div>
                
                ${expenses.length === 0 ? `
                    <div class="empty-state">
                        <div class="empty-icon" style="color:#bdc3c7;">${ic('doc', 44)}</div>
                        <div class="empty-title">Hakuna Matumizi</div>
                        <div class="empty-text">${isToday ? 'Bado haujaweka matumizi yoyote leo.' : 'Hakuna matumizi ya siku hii.'}</div>
                        ${isToday ? `<div class="empty-add" onclick="openModal()">${ic('plus', 13)} Ongeza Matumizi</div>` : ''}
                    </div>
                ` : expensesHtml}
            `;
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
                    window.location.href = '/login?role=muuzaji';
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

        // Expose functions to global scope
        window.loadData = loadData;
        window.changeDate = changeDate;
        window.openModal = openModal;
        window.closeModal = closeModal;
        window.selectCategory = selectCategory;
        window.deleteExpense = deleteExpense;
        
        // Initialize
        async function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
            await loadCategories();
            await loadData();
            
            // Set up save button listener
            document.getElementById('saveExpenseBtn').addEventListener('click', addExpense);
            
            // Live preview
            const amountInput = document.getElementById('expenseAmount');
            const descInput = document.getElementById('expenseDesc');
            if (amountInput && descInput) {
                const updatePreview = () => renderCategoryChips();
                amountInput.addEventListener('input', updatePreview);
                descInput.addEventListener('input', updatePreview);
            }
        }
        
        init();
    </script>
</body>
</html>
@endverbatim