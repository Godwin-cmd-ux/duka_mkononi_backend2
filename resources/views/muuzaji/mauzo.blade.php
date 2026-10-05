@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_mauzo.page_title">Mauzo - Dukamkononi Muuzaji</title>
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

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            color: #7f8c8d;
            font-size: 14px;
        }

        /* Header */
        .header {
            background: white;
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 20px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
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
            font-size: 22px;
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

        .date-title {
            font-size: 14px;
            color: #7f8c8d;
            text-align: center;
            margin-bottom: 16px;
        }

        .closed-badge {
            color: #e74c3c;
            font-weight: 700;
        }

        /* Stats */
        .stats-container {
            display: flex;
            justify-content: space-around;
            background: #f8f9fa;
            padding: 16px;
            border-radius: 16px;
        }

        .stat-item {
            text-align: center;
            flex: 1;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #2196F3;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
        }

        /* Sales List */
        .sales-list {
            flex: 1;
        }

        .sale-item {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
        }

        .sale-item.closed {
            background: #f8f9fa;
            opacity: 0.8;
        }

        .sale-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .invoice-number {
            font-weight: 700;
            color: #2c3e50;
        }

        .sale-time {
            font-size: 12px;
            color: #95a5a6;
        }

        .sale-total {
            font-weight: 700;
            color: #27ae60;
            font-size: 16px;
        }

        .product-row, .customer-row, .payment-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .product-name {
            flex: 1;
            font-size: 14px;
            color: #2c3e50;
        }

        .quantity {
            font-size: 13px;
            color: #7f8c8d;
        }

        .customer-name {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 500;
        }

        .payment-text {
            font-size: 12px;
            color: #3498db;
        }

        .notes-text {
            font-size: 11px;
            color: #95a5a6;
            font-style: italic;
        }

        .sale-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #ecf0f1;
        }

        .date-text {
            font-size: 11px;
            color: #95a5a6;
        }

        .sale-actions {
            display: flex;
            gap: 10px;
        }

        .edit-btn, .receipt-btn {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }

        .edit-btn {
            background: #e8f4fd;
            color: #3498db;
        }

        .receipt-btn {
            background: #e8f7ef;
            color: #27ae60;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 20px;
        }

        .empty-icon {
            font-size: 64px;
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

        .go-sell-btn {
            background: #2ecc71;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            cursor: pointer;
            border: none;
        }

        /* Close Sales Button */
        .close-sales-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: #e74c3c;
            margin: 16px 0;
            padding: 14px;
            border-radius: 14px;
            cursor: pointer;
            color: white;
            font-weight: 700;
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

        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
        }

        .form-input.readonly {
            background: #f5f5f5;
            color: #666;
        }

        .field-hint {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 4px;
        }

        .summary-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-top: 10px;
        }

        .summary-title {
            font-weight: 700;
            margin-bottom: 10px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .summary-divider {
            height: 1px;
            background: #ecf0f1;
            margin: 10px 0;
        }

        .total-label {
            font-weight: 700;
        }

        .total-value {
            font-weight: 700;
            color: #27ae60;
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
            background: #2196F3;
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        /* Confirm Modal */
        .confirm-modal {
            background: white;
            border-radius: 24px;
            width: 85%;
            max-width: 400px;
            padding: 24px;
            text-align: center;
        }

        .warning-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .confirm-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .confirm-text {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .confirm-buttons {
            display: flex;
            gap: 12px;
        }

        .confirm-cancel {
            flex: 1;
            padding: 12px;
            background: #f8f9fa;
            border: none;
            border-radius: 12px;
            cursor: pointer;
        }

        .confirm-close {
            flex: 1;
            padding: 12px;
            background: #e74c3c;
            border: none;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
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
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.toast')
@include('partials.photo-viewer')
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
                    <div class="logo-icon" data-i18n="muuzaji_mauzo.logo_short">D</div>
                    <div class="logo-text">
                        <h2 data-i18n="muuzaji_mauzo.logo_brand">DukaMkononi</h2>
                        <p data-i18n="muuzaji_mauzo.logo_tagline">Muuzaji Portal</p>
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
                    <span class="nav-label" data-i18n="muuzaji_mauzo.nav_profile">Profaili</span>
                </a>
                <a href="mauzo" class="nav-item active">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="2.5" y="6" width="19" height="12" rx="2.5"/>
                            <circle cx="12" cy="12" r="2.6"/>
                            <path d="M6 9.5V9.51M18 14.5V14.51" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="muuzaji_mauzo.nav_sales">Mauzo</span>
                </a>
                <a href="matumizi" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z"/>
                            <path d="M8 7V17M12 7V17M16 7V17"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="muuzaji_mauzo.nav_expenses">Matumizi</span>
                </a>
                <a href="uza" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="9" cy="20" r="1.6"/>
                            <circle cx="17" cy="20" r="1.6"/>
                            <path d="M3 3H5L7.4 15.2C7.55 15.95 8.2 16.5 8.97 16.5H17.6C18.32 16.5 18.94 16 19.08 15.3L21 7H6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="muuzaji_mauzo.nav_sell">Uza</span>
                </a>
                <a href="orders" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M6 2.5h12a1.5 1.5 0 0 1 1.5 1.5V21l-3-2-3 2-3-2-3 2V4a1.5 1.5 0 0 1 1.5-1.5Z" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M8.5 7.5h7M8.5 11h7M8.5 14.5h4" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="muuzaji_orders.nav_orders">Oda</span>
                </a>
                <a href="huduma-nyingine" class="nav-item">
                    <div class="nav-icon">🧩</div>
                    <span class="nav-label" data-i18n="muuzaji_huduma.nav_other">Huduma Nyingine</span>
                </a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar">M</div>
                    <div class="user-details">
                        <div class="user-name" id="userName">Muuzaji</div>
                        <div class="user-role" data-i18n="muuzaji_mauzo.nav_seller">Muuzaji</div>
                    </div>
                </div>
                <div class="logout-btn" id="logoutBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M15 3H19C20.1 3 21 3.9 21 5V19C21 20.1 20.1 21 19 21H15M10 17L15 12L10 7M15 12H3"/>
                    </svg>
                    <span data-i18n="muuzaji_mauzo.btn_logout">Ondoka</span>
                </div>>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-container" id="mauzoContent">
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <div class="loading-text" data-i18n="muuzaji_mauzo.loading">Inapakua data ya mauzo...</div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" data-i18n="muuzaji_mauzo.modal_title">Hariri Mauzo</div>
                <div class="close-modal" onclick="closeEditModal()"><i class="fa-solid fa-xmark" style="font-size:20px;" aria-hidden="true"></i></div>
            </div>
            <div class="modal-body" id="editModalBody"></div>
            <div class="modal-footer">
                <button class="cancel-btn" id="cancelEditSaleBtn" onclick="closeEditModal()" data-i18n="muuzaji_mauzo.btn_cancel">Ghairi</button>
                <button class="save-btn" id="saveEditSaleBtn" onclick="updateSale()" data-i18n="muuzaji_mauzo.btn_save_changes">Hifadhi Mabadiliko</button>
            </div>
        </div>
    </div>

    <!-- Confirm Close Modal -->
    <div id="confirmModal" class="modal-overlay">
        <div class="confirm-modal">
            <div class="warning-icon"><i class="fa-solid fa-triangle-exclamation" style="font-size:44px;color:#f39c12;" aria-hidden="true"></i></div>
            <div class="confirm-title" data-i18n="muuzaji_mauzo.confirm_title">Funga Mauzo Ya Leo?</div>
            <div class="confirm-text" id="confirmText"></div>
            <div class="confirm-buttons">
                <button class="confirm-cancel" onclick="closeConfirmModal()" data-i18n="muuzaji_mauzo.btn_cancel">Ghairi</button>
                <button class="confirm-close" id="confirmCloseBtn" onclick="closeTodaySales()"><i class="fa-solid fa-lock" style="font-size:13px;" aria-hidden="true"></i> <span data-i18n="muuzaji_mauzo.btn_close_sales">Funga Mauzo</span></button>
            </div>
        </div>
    </div>

    <script>
        const API_BASE_URL = '';

        const SW = {
            loading: 'Inapakua data ya mauzo...',
            nav_seller: 'Muuzaji',
            nav_sales: 'Mauzo',
            photo_alt: 'Picha',
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la Muuzaji.',
            customer_unknown: 'Mteja Bila Jina',
            product_fallback: 'Bidhaa',
            btn_saving: 'Inahifadhi...',
            btn_save_changes: 'Hifadhi Mabadiliko',
            msg_edit_success: 'Mauzo yamehaririwa kikamilifu!',
            err_server_not_updated: 'Server haijasasishwa bado, kwa hivyo kuhariri mauzo hakujawasilishwa. Mwambie msimamizi wa mfumo asasishe server.',
            err_edit_retry: 'Imeshindikana kuhariri mauzo. Jaribu tena.',
            err_edit_failed: 'Imeshindikana kuhariri mauzo',
            err_edit_network: 'Imeshindikana kuhariri mauzo. Angalia muunganisho na ujaribu tena.',
            msg_closed_success: 'Mauzo ya leo yamefungwa kikamilifu!',
            err_closed_no_edit: 'Mauzo ya leo yamefungwa, hayawezi kuhaririwa tena.',
            receipt_brand: 'DUKAMKONONI',
            receipt_title: 'RISITI YA MAUZO',
            receipt_number: 'Nambari:',
            receipt_date: 'Tarehe:',
            receipt_time: 'Muda:',
            label_customer: 'Mteja:',
            label_quantity: 'Kiasi',
            price_header: 'Bei',
            total_header: 'Jumla',
            receipt_total: 'Jumla:',
            receipt_thanks: 'Asante kwa Kununua Nasi!',
            receipt_doc_title: 'Risiti -',
            confirm_close_text: 'Mauzo {count} ya leo yatafungwa na hayawezi kuhaririwa tena.',
            confirm_total_label: 'Jumla ya leo:',
            confirm_customers_label: 'Wateja walionunua leo:',
            label_invoice_number: 'Nambari ya Ankra',
            label_product_name: 'Jina la Bidhaa',
            label_selling_price: 'Bei ya Uuzaji (TZS)',
            label_customer_name: 'Jina la Mteja *',
            placeholder_customer_name: 'Weka jina la mteja',
            summary_title: 'Muhtasari wa Mabadiliko',
            label_product_colon: 'Bidhaa:',
            label_qty_old: 'Kiasi (Zamani):',
            label_qty_new: 'Kiasi (Mpya):',
            label_selling_price_colon: 'Bei ya Uuzaji:',
            nameless: 'Bila Jina',
            label_grand_total: 'Jumla kamili:',
            summary_hint: 'Kiongeza kiasi = kuuza zaidi; kupunguza = kurudisha stoo',
            title_today_sales: 'Mauzo Ya Leo',
            badge_closed: 'IMEFUNGWA',
            stat_total_label: 'Jumla Ya Leo',
            stat_customers_label: 'Wateja',
            empty_title: 'Hakuna Mauzo Ya Leo',
            empty_text: 'Bado haujafanya mauzo yoyote leo. Nenda kwenye ukurasa wa kuuza.',
            btn_go_sell: 'Nenda Kuuza',
            btn_close_today: 'FUNGA MAUZO YA LEO',
            btn_edit: 'Hariri',
            btn_receipt: 'Dai Risiti',
            payment_cash: 'Fedha Taslimu',
            payment_card: 'Malipo ya Kadi'
        };
        // Accepts either a bare key ('err_edit_failed') or a fully qualified one
        // ('muuzaji_mauzo.err_edit_failed'); {placeholders} are filled from params
        // (the SW path; DM.t already applies them when the catalog resolves).
        function t(key, params) {
            const full = key.indexOf('muuzaji_mauzo.') === 0 ? key : 'muuzaji_mauzo.' + key;
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
        
        let salesData = [];
        let loading = true;
        let todayClosed = false;
        let selectedSale = null;
        let editForm = { quantity: '', unit_price: '', customer_name: '', product_name: '', sale_item_id: '' };
        
        const today = new Date().toISOString().split('T')[0];

        // ============================== ICONS ==============================
        // Font Awesome 6 icon helper (same convention as the msimamizi pages).
        const FA_MAP = {
            user: 'fa-user', money: 'fa-money-bill-1', cart: 'fa-cart-shopping', box: 'fa-box',
            doc: 'fa-file-lines', refresh: 'fa-rotate-right', edit: 'fa-pen-to-square',
            check: 'fa-check', x: 'fa-xmark', warning: 'fa-triangle-exclamation',
            receipt: 'fa-receipt', lock: 'fa-lock', save: 'fa-floppy-disk',
            spinner: 'fa-spinner', card: 'fa-credit-card'
        };
        function ic(name, size = 16, cls = '') {
            return `<i class="fa-solid ${FA_MAP[name] || 'fa-circle-info'} ${cls}" style="font-size:${size}px;" aria-hidden="true"></i>`;
        }

        // Edit-modal button busy state: Hifadhi shows a spinner while the
        // request runs; Ghairi is locked (and dimmed) so nothing can be
        // double-clicked mid-save.
        function setEditSaleBusy(busy) {
            const save = document.getElementById('saveEditSaleBtn');
            const cancel = document.getElementById('cancelEditSaleBtn');
            if (save) {
                save.innerHTML = busy ? ic('spinner', 14, 'fa-spin') + ' ' + t('btn_saving') : t('btn_save_changes');
                save.style.opacity = busy ? '0.7' : '1';
                save.style.pointerEvents = busy ? 'none' : 'auto';
            }
            if (cancel) {
                cancel.style.opacity = busy ? '0.5' : '1';
                cancel.style.pointerEvents = busy ? 'none' : 'auto';
            }
        }

        function getCurrentUser() {
            const token = localStorage.getItem('userToken');
            const userData = localStorage.getItem('userData');
            if (token && userData) {
                try {
                    return JSON.parse(userData);
                } catch(e) { return null; }
            }
            return null;
        }

        function updateSidebarUser() {
            const user = getCurrentUser();
            if (user) {
                const displayName = user.full_name || user.business_name || user.email?.split('@')[0] || t('nav_seller');
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

        function checkAuth() {
            const user = getCurrentUser();
            if (!user) {
                window.location.href = '/login?role=muuzaji';
                return false;
            }
            const userRole = user.role || '';
            if (userRole !== 'seller' && userRole !== 'muuzaji') {
                showToast(t('err_no_permission'), 'error');
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
            try {
                return new Date(dateStr).toLocaleDateString('sw-TZ', {
                    day: 'numeric', month: 'long', year: 'numeric'
                });
            } catch { return dateStr; }
        }

        function formatTime(dateStr) {
            try {
                return new Date(dateStr).toLocaleTimeString('sw-TZ', { hour: '2-digit', minute: '2-digit' });
            } catch { return ''; }
        }

        function getCustomerName(sale) {
            if (sale.customer_name && sale.customer_name.trim()) return sale.customer_name.trim();
            if (sale.notes && sale.notes.includes('Mteja:')) {
                const match = sale.notes.match(/Mteja:\s*(.+)/);
                if (match && match[1]) return match[1].trim();
            }
            if (sale.customer?.name) return sale.customer.name;
            if (sale.customer_data?.name) return sale.customer_data.name;
            return t('customer_unknown');
        }

        async function loadSalesData() {
            const token = localStorage.getItem('userToken');
            if (!token) return;
            
            loading = true;
            render();
            
            try {
                const response = await fetch(`${API_BASE_URL}/api/sales/my`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                if (!response.ok) throw new Error('API Error');
                
                const data = await response.json();
                if (!Array.isArray(data)) {
                    salesData = [];
                } else {
                    const processedData = data.map(sale => ({
                        ...sale,
                        display_customer_name: getCustomerName(sale)
                    }));
                    salesData = processedData.filter(sale => {
                        const saleDate = sale.sale_date || sale.created_at?.split('T')[0];
                        return saleDate === today;
                    });
                }
                
                checkIfTodaySalesClosed();
            } catch (error) {
                console.error('Error loading sales:', error);
                salesData = [];
            } finally {
                loading = false;
                render();
            }
        }

        function checkIfTodaySalesClosed() {
            const closedKey = `closed_sales_${today}`;
            todayClosed = localStorage.getItem(closedKey) === 'true';
            render();
        }

        async function updateSale() {
            if (!selectedSale) return;
            
            // PUT /api/sales/:id edits the sale line and moves stock by the
            // difference. A 404 now means "sale not found / not yours".
            const token = localStorage.getItem('userToken');
            const quantity = parseInt(editForm.quantity) || selectedSale.sale_items?.[0]?.quantity || 1;
            
            const updateData = {
                customer_name: editForm.customer_name.trim(),
                notes: `Mteja: ${editForm.customer_name.trim()}`,
                total_amount: quantity * (parseFloat(editForm.unit_price) || 0)
            };
            
            if (editForm.sale_item_id) {
                updateData.sale_items = [{
                    id: editForm.sale_item_id,
                    quantity: quantity,
                    unit_price: parseFloat(editForm.unit_price) || 0,
                    total_price: quantity * (parseFloat(editForm.unit_price) || 0)
                }];
            }
            
            setEditSaleBusy(true);
            
            try {
                const response = await fetch(`${API_BASE_URL}/api/sales/${selectedSale.id}`, {
                    method: 'PUT',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(updateData)
                });
                
                if (response.ok) {
                    showToast(t('msg_edit_success'), 'success');
                    closeEditModal();
                    await loadSalesData();
                } else if (response.status === 404) {
                    // Two very different 404s arrive here: the sale is missing
                    // or not ours (the API answers with `error`), or PUT
                    // /api/sales/:id is not on the deployed server yet (Laravel
                    // answers "The route ... could not be found.").
                    const notFoundData = await response.json().catch(() => null);
                    const routeMissing = !!notFoundData && !notFoundData.error &&
                        typeof notFoundData.message === 'string' &&
                        /could not be found/i.test(notFoundData.message);
                    showToast(
                        routeMissing
                            ? t('err_server_not_updated')
                            : (notFoundData && (notFoundData.error || notFoundData.message))
                                || t('err_edit_retry'),
                        'error'
                    );
                    closeEditModal();
                } else {
                    const errData = await response.json().catch(() => ({}));
                    showToast(errData.error || t('err_edit_failed'), 'error');
                }
            } catch (error) {
                // Non-JSON / network failure.
                showToast(t('err_edit_network'), 'error');
                closeEditModal();
            } finally {
                setEditSaleBusy(false);
            }
        }

        async function closeTodaySales() {
            const closedKey = `closed_sales_${today}`;
            localStorage.setItem(closedKey, 'true');
            todayClosed = true;
            closeConfirmModal();
            showToast(t('msg_closed_success'), 'success');
            render();
        }

        async function generateReceipt(sale) {
            const customerName = sale.display_customer_name || getCustomerName(sale);
            const saleItem = sale.sale_items?.[0];
            const productName = saleItem?.products?.name || t('product_fallback');
            const quantity = saleItem?.quantity || 0;
            const unitPrice = saleItem?.unit_price || 0;
            const total = sale.total_amount || 0;
            const date = new Date(sale.sale_date || sale.created_at);
            
            const receiptHtml = `
                <div style="text-align:center;padding:20px;font-family:Arial;">
                    <h2>${t('receipt_brand')}</h2>
                    <h3>${t('receipt_title')}</h3>
                    <hr>
                    <p><strong>${t('receipt_number')}</strong> ${sale.invoice_number || sale.id.substring(0,8)}</p>
                    <p><strong>${t('receipt_date')}</strong> ${date.toLocaleDateString('sw-TZ')}</p>
                    <p><strong>${t('receipt_time')}</strong> ${date.toLocaleTimeString('sw-TZ')}</p>
                    <p><strong>${t('label_customer')}</strong> ${customerName}</p>
                    <hr>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><th>${t('product_fallback')}</th><th>${t('label_quantity')}</th><th>${t('price_header')}</th><th>${t('total_header')}</th></tr>
                        <tr><td>${productName}</td><td>${quantity}</td><td>${formatCurrency(unitPrice)}</td><td>${formatCurrency(quantity * unitPrice)}</td></tr>
                    </table>
                    <hr>
                    <p><strong>${t('receipt_total')}</strong> ${formatCurrency(total)}</p>
                    <hr>
                    <p>${t('receipt_thanks')}</p>
                    <p style="font-size:12px;">www.dukamkononi.com</p>
                </div>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html><head><title>${t('receipt_doc_title')} ${sale.invoice_number || sale.id}</title></head>
                <body onload="window.print();window.close();">${receiptHtml}</body></html>
            `);
            printWindow.document.close();
        }

        function openEditModal(sale) {
            if (todayClosed) {
                showToast(t('err_closed_no_edit'), 'warning');
                return;
            }
            
            selectedSale = sale;
            const saleItem = sale.sale_items?.[0];
            const customerName = sale.display_customer_name || getCustomerName(sale);
            
            editForm = {
                quantity: saleItem?.quantity?.toString() || '1',
                unit_price: saleItem?.unit_price?.toString() || '',
                customer_name: customerName,
                product_name: saleItem?.products?.name || t('product_fallback'),
                sale_item_id: saleItem?.id || ''
            };
            
            renderEditModal();
            document.getElementById('editModal').style.display = 'flex';
        }

        // Live total = quantity × unit price, so the summary total follows the
        // quantity the seller is typing (the server recomputes the same value on
        // save). Falls back to the recorded total when there is no unit price.
        function updateEditTotal() {
            const el = document.getElementById('newTotalDisplay');
            if (!el) return;
            // Same quantity resolution as updateSale(), so the live total always
            // matches what saving would produce.
            const qty = parseFloat(editForm.quantity) || selectedSale?.sale_items?.[0]?.quantity || 1;
            const price = parseFloat(editForm.unit_price) || 0;
            const total = price > 0 ? qty * price : (selectedSale?.total_amount || 0);
            el.innerText = formatCurrency(total);
        }

        function renderEditModal() {
            const body = document.getElementById('editModalBody');
            const editUnitPrice = parseFloat(editForm.unit_price) || 0;
            const editQuantity = parseFloat(editForm.quantity) || selectedSale?.sale_items?.[0]?.quantity || 1;
            const editTotal = editUnitPrice > 0 ? editQuantity * editUnitPrice : (selectedSale?.total_amount || 0);
            body.innerHTML = `
                <div class="form-group">
                    <label class="form-label">${t('label_invoice_number')}</label>
                    <input class="form-input readonly" value="${escapeHtml(selectedSale?.invoice_number || selectedSale?.id)}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">${t('label_product_name')}</label>
                    <input class="form-input readonly" value="${escapeHtml(editForm.product_name)}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">${t('label_quantity')}</label>
                    <input type="number" id="editQuantity" class="form-input" value="${editForm.quantity}">
                </div>
                <div class="form-group">
                    <label class="form-label">${t('label_selling_price')}</label>
                    <input class="form-input readonly" value="${editForm.unit_price}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">${t('label_customer_name')}</label>
                    <input type="text" id="editCustomerName" class="form-input" value="${escapeHtml(editForm.customer_name)}" placeholder="${t('placeholder_customer_name')}">
                </div>
                <div class="summary-box">
                    <div class="summary-title">${t('summary_title')}</div>
                    <div class="summary-row"><span>${t('label_product_colon')}</span><span>${escapeHtml(editForm.product_name)}</span></div>
                    <div class="summary-row"><span>${t('label_qty_old')}</span><span>${selectedSale?.sale_items?.[0]?.quantity || 0}</span></div>
                    <div class="summary-row"><span>${t('label_qty_new')}</span><span id="newQuantityDisplay">${editForm.quantity}</span></div>
                    <div class="summary-row"><span>${t('label_selling_price_colon')}</span><span>${formatCurrency(editUnitPrice)}</span></div>
                    <div class="summary-row"><span>${t('label_customer')}</span><span id="newCustomerDisplay">${escapeHtml(editForm.customer_name) || t('nameless')}</span></div>
                    <div class="summary-divider"></div>
                    <div class="summary-row total-label"><span>${t('label_grand_total')}</span><span class="total-value" id="newTotalDisplay">${formatCurrency(editTotal)}</span></div>
                    <div style="font-size:11px;color:#7f8c8d;font-style:italic;margin-top:8px;line-height:1.4;">${t('summary_hint')}</div>
                </div>
            `;
            
            const quantityInput = document.getElementById('editQuantity');
            const customerInput = document.getElementById('editCustomerName');
            
            if (quantityInput) {
                quantityInput.addEventListener('input', (e) => {
                    editForm.quantity = e.target.value;
                    document.getElementById('newQuantityDisplay').innerText = e.target.value;
                    updateEditTotal();
                });
            }
            if (customerInput) {
                customerInput.addEventListener('input', (e) => {
                    editForm.customer_name = e.target.value;
                    document.getElementById('newCustomerDisplay').innerText = e.target.value || t('nameless');
                });
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            selectedSale = null;
        }

        function openConfirmModal() {
            const total = salesData.reduce((sum, s) => sum + (s.total_amount || 0), 0);
            // Known customers + one "unknown customer" per sale with no customer data.
            const knownCustomers = new Set(salesData
                .map(s => getCustomerName(s))
                .filter(n => n && n !== t('customer_unknown'))).size;
            const unknownCustomers = salesData
                .filter(s => { const n = getCustomerName(s); return !n || n === t('customer_unknown'); }).length;
            const customers = knownCustomers + unknownCustomers;
            document.getElementById('confirmText').innerHTML = `
                ${t('confirm_close_text', { count: salesData.length })}<br><br>
                ${t('confirm_total_label')} ${formatCurrency(total)}<br><br>
                ${t('confirm_customers_label')} ${customers}
            `;
            document.getElementById('confirmModal').style.display = 'flex';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        function render() {
            const container = document.getElementById('mauzoContent');
            
            if (loading) {
                container.innerHTML = `<div class="loading-container"><div class="loading-spinner"></div><div class="loading-text">${t('loading')}</div></div>`;
                return;
            }
            
            const totalAmount = salesData.reduce((sum, s) => sum + (s.total_amount || 0), 0);
            const uniqueCustomers = new Set(salesData.map(s => getCustomerName(s))).size;
            
            const salesHtml = salesData.map(sale => {
                const saleItem = sale.sale_items?.[0];
                const customerName = sale.display_customer_name || getCustomerName(sale);
                return `
                    <div class="sale-item ${todayClosed ? 'closed' : ''}">
                        <div class="sale-header">
                            <div><span class="invoice-number">#${escapeHtml(sale.invoice_number || sale.id.substring(0,8))}</span> <span class="sale-time">${formatTime(sale.created_at || sale.sale_date)}</span></div>
                            <div class="sale-total">${formatCurrency(sale.total_amount)}</div>
                        </div>
                        <div class="product-row">${ic('box', 14)}<span class="product-name">${escapeHtml(saleItem?.products?.name || t('product_fallback'))}</span><span class="quantity">${saleItem?.quantity || 0} × ${formatCurrency(saleItem?.unit_price || 0)}</span></div>
                        <div class="customer-row">${ic('user', 14)}<span class="customer-name">${escapeHtml(customerName)}</span></div>
                        ${sale.payment_method ? `<div class="payment-row">${ic('card', 14)}<span class="payment-text">${sale.payment_method === 'cash' ? t('payment_cash') : t('payment_card')}</span></div>` : ''}
                        ${sale.notes ? `<div class="notes-text">${ic('doc', 12)} ${escapeHtml(sale.notes)}</div>` : ''}
                        <div class="sale-footer">
                            <div class="date-text">${formatDate(sale.sale_date || sale.created_at)}</div>
                            <div class="sale-actions">
                                ${!todayClosed ? `<div class="edit-btn" onclick="openEditModal(${JSON.stringify(sale).replace(/"/g, '&quot;')})">${ic('edit', 12)} ${t('btn_edit')}</div>` : ''}
                                <div class="receipt-btn" onclick="generateReceipt(${JSON.stringify(sale).replace(/"/g, '&quot;')})">${ic('receipt', 12)} ${t('btn_receipt')}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = `
                <div class="header">
                    <div class="header-top">
                        <a href="profaili" class="back-btn">←</a>
                        <div class="title">${t('title_today_sales')}</div>
                        <button class="refresh-btn" onclick="loadSalesData()">${ic('refresh', 16)}</button>
                    </div>
                    <div class="date-title">${formatDate(today)}${todayClosed ? `<span class="closed-badge"> • ${t('badge_closed')}</span>` : ''}</div>
                    <div class="stats-container">
                        <div class="stat-item"><div class="stat-value">${salesData.length}</div><div class="stat-label">${t('nav_sales')}</div></div>
                        <div class="stat-item"><div class="stat-value">${formatCurrency(totalAmount)}</div><div class="stat-label">${t('stat_total_label')}</div></div>
                        <div class="stat-item"><div class="stat-value">${uniqueCustomers}</div><div class="stat-label">${t('stat_customers_label')}</div></div>
                    </div>
                </div>
                <div class="sales-list">
                    ${salesData.length === 0 ? `
                        <div class="empty-state">
                        <div class="empty-icon" style="color:#bdc3c7;">${ic('doc', 48)}</div>
                        <div class="empty-title">${t('empty_title')}</div>
                        <div class="empty-text">${t('empty_text')}</div>
                        <a href="uza" class="go-sell-btn">${ic('cart', 15)} ${t('btn_go_sell')}</a>
                        </div>
                    ` : salesHtml}
                </div>
                ${salesData.length > 0 && !todayClosed ? `<div class="close-sales-btn" onclick="openConfirmModal()">${ic('lock', 15)} ${t('btn_close_today')}</div>` : ''}
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
                        !sidebar.contains(e.target) && toggle && !toggle.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                }
            });
        }

        window.loadSalesData = loadSalesData;
        window.openEditModal = openEditModal;
        window.closeEditModal = closeEditModal;
        window.updateSale = updateSale;
        window.openConfirmModal = openConfirmModal;
        window.closeConfirmModal = closeConfirmModal;
        window.closeTodaySales = closeTodaySales;
        window.generateReceipt = generateReceipt;

        async function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
            await loadSalesData();
        }

        // Follow the language: static markup is handled by data-i18n, but the
        // sales list, stats and modals are built from script.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => {
                // display_customer_name was resolved at load time; recompute so
                // the unknown-customer fallback follows the language too.
                salesData = salesData.map(sale => ({
                    ...sale,
                    display_customer_name: getCustomerName(sale)
                }));
                render();
                updateSidebarUser();
                const confirmEl = document.getElementById('confirmModal');
                if (confirmEl && confirmEl.style.display === 'flex') openConfirmModal();
                const editEl = document.getElementById('editModal');
                if (editEl && editEl.style.display === 'flex') renderEditModal();
            });
        }
        
        init();
    </script>
</body>
</html>
@endverbatim