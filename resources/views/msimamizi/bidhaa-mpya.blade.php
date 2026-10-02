@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="msimamizi_bidhaa.page_title">Bidhaa Mpya - Dukamkononi Msimamizi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',-apple-system,sans-serif;background:#f8f9fa}.msimamizi-layout{display:flex;min-height:100vh}.sidebar{width:280px;background:#fff;border-right:1px solid #ecf0f1;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;transition:transform .3s;box-shadow:2px 0 12px rgba(0,0,0,.05)}.sidebar-header{padding:30px 24px;border-bottom:1px solid #ecf0f1}.logo-area{display:flex;align-items:center;gap:12px}.logo-icon{width:45px;height:45px;background:linear-gradient(135deg,#e74c3c,#c0392b);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px;font-weight:700}.logo-text h2{font-size:18px;font-weight:800;color:#2c3e50}.logo-text p{font-size:12px;color:#7f8c8d}.nav-items{flex:1;padding:0 16px}.nav-item{display:flex;align-items:center;gap:14px;padding:14px 18px;margin-bottom:8px;border-radius:14px;cursor:pointer;transition:all .2s;color:#5d6d7e;font-weight:500;text-decoration:none}.nav-item:hover,.nav-item.active{background:#fdeaea;color:#e74c3c}.nav-icon{font-size:20px;width:24px}.nav-label{font-size:15px;font-weight:600}.sidebar-footer{padding:20px 16px;border-top:1px solid #ecf0f1;margin-top:auto}.user-info{display:flex;align-items:center;gap:12px;margin-bottom:15px}.user-avatar{width:45px;height:45px;background:#fdeaea;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#e74c3c;font-weight:700;font-size:18px;overflow:hidden}.user-name{font-size:14px;font-weight:700;color:#2c3e50}.user-role{font-size:12px;color:#e74c3c;font-weight:600}.logout-btn{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f8f9fa;border-radius:12px;cursor:pointer;color:#e74c3c;font-weight:600;font-size:14px}.main-content{flex:1;margin-left:280px;min-height:100vh;background:#f8f9fa;padding:20px 24px 40px}.mobile-menu-toggle{display:none;position:fixed;top:16px;left:16px;z-index:200;background:#fff;padding:12px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.1);cursor:pointer}.bidhaa-container{max-width:800px;margin:0 auto}.form-card{background:#fff;border-radius:24px;padding:24px;margin-bottom:20px;box-shadow:0 2px 12px rgba(0,0,0,.05)}.input-group{margin-bottom:20px}.label{font-size:15px;font-weight:600;color:#2c3e50;margin-bottom:8px;display:block}input,select{width:100%;padding:14px 16px;font-size:15px;border:1px solid #ddd;border-radius:14px;font-family:'Inter',sans-serif}input:focus,select:focus{outline:none;border-color:#e74c3c;box-shadow:0 0 0 3px rgba(231,76,60,.1)}input:disabled{background:#f5f5f5;color:#999}.price-container{position:relative}.price-preview{position:absolute;right:16px;top:50%;transform:translateY(-50%);text-align:right}.categories-scroll{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}.category-chip{background:#fff;border:1px solid #ddd;padding:8px 16px;border-radius:30px;font-size:13px;cursor:pointer;transition:all .2s}.category-chip.selected{background:#2ecc71;border-color:#27ae60;color:#fff;font-weight:600}.submit-btn{width:100%;padding:16px;border-radius:16px;border:none;font-weight:800;font-size:16px;display:flex;align-items:center;justify-content:center;gap:10px;cursor:pointer;color:#fff;transition:all .2s}.card-ic{display:flex;align-items:center;justify-content:center;flex-shrink:0}
svg.icon{flex-shrink:0}
.submit-btn.add{background:#2ecc71}.submit-btn.update{background:#3498db}.submit-btn.addstock{background:#f39c12}.submit-btn.disabled{background:#95a5a6;opacity:.6;cursor:not-allowed}.existing-btn{background:#fff;border:2px dashed #e8f6f3;border-radius:16px;padding:20px;margin-bottom:24px;cursor:pointer;display:flex;align-items:center;gap:15px;transition:all .2s}.existing-btn:hover{border-color:#2ecc71;background:#f0faf5}.selected-banner{background:#f0f8f0;border-radius:16px;padding:16px;margin-bottom:20px;border-left:5px solid #2ecc71}.selected-banner.non-owner{background:#fff9e6;border-left-color:#f39c12}.selected-banner-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.selected-banner-title{font-weight:700;color:#27ae60;font-size:14px}.selected-banner-close{cursor:pointer;color:#e74c3c;font-size:20px;padding:4px}.price-diff{padding:12px 16px;border-radius:12px;margin:10px 0 16px}.price-diff.positive{background:#e8f6f3;border-left:4px solid #27ae60}.price-diff.negative{background:#fdeaea;border-left:4px solid #e74c3c}.price-diff-row{display:flex;justify-content:space-between;margin-top:6px;font-size:14px}.preview-card{background:#e8f4fd;border-radius:16px;padding:20px;margin-top:24px;border-left:5px solid #3498db}.preview-inner{background:#fff;border-radius:12px;padding:16px}.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:none;align-items:flex-end;justify-content:center;z-index:1000}.modal-overlay.show{display:flex}.modal-content{background:#fff;border-radius:20px 20px 0 0;width:100%;max-width:600px;max-height:80vh;display:flex;flex-direction:column}.modal-header{display:flex;justify-content:space-between;align-items:center;padding:20px;border-bottom:1px solid #ecf0f1}.modal-title{font-size:18px;font-weight:700;color:#2c3e50}.modal-close{cursor:pointer;font-size:24px;color:#7f8c8d;padding:4px}.modal-search{margin:16px 20px;padding:14px;border:1px solid #ddd;border-radius:12px;font-size:15px}.modal-body{flex:1;overflow-y:auto;padding:0 20px 20px}.product-item{display:flex;align-items:center;justify-content:space-between;padding:14px;border-bottom:1px solid #ecf0f1;cursor:pointer;border-radius:12px;transition:background .2s}.product-item:hover{background:#f8f9fa}.product-item.owner{background:#f0f8f0}.product-item-info{flex:1;margin-right:12px}.product-item-name{font-weight:700;color:#2c3e50;font-size:15px}.product-item-category{font-size:12px;color:#7f8c8d;margin-top:2px}.product-item-details{display:flex;gap:12px;margin-top:6px;font-size:12px}.product-item-stock{color:#3498db;font-weight:500}.product-item-price{color:#27ae60;font-weight:500}.product-actions{display:flex;gap:8px}.product-action-btn{padding:6px 10px;border-radius:8px;border:none;cursor:pointer;font-size:18px;background:0 0}.owner-badge{background:#d5f4e6;color:#27ae60;font-size:11px;font-weight:600;padding:2px 8px;border-radius:8px;margin-left:8px}.no-products{text-align:center;padding:40px;color:#7f8c8d}.loading-spinner{display:inline-block;width:20px;height:20px;border:2px solid rgba(255,255,255,.3);border-radius:50%;border-top-color:#fff;animation:spin .6s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:768px){.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.main-content{margin-left:0;padding:20px 16px;padding-top:70px}.mobile-menu-toggle{display:block}}
    </style>
<style>
.ai-import-btn{background:#fff;border:2px dashed #e8e3f6;border-radius:16px;padding:20px;margin-bottom:24px;cursor:pointer;display:flex;align-items:center;gap:15px;transition:all .2s}.ai-import-btn:hover{border-color:#7c5cbf;background:#f7f3ff}.ai-modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:1500}.ai-modal-overlay.show{display:flex}.ai-modal-content{background:#fff;border-radius:22px;width:100%;max-width:820px;max-height:92vh;display:flex;flex-direction:column}.ai-modal-header{display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #ecf0f1;background:#fff;border-radius:22px 22px 0 0}.ai-modal-title{font-size:18px;font-weight:800;color:#2c3e50}.ai-modal-close{cursor:pointer;font-size:24px;color:#7f8c8d;padding:4px;line-height:1}.ai-modal-body{flex:1;overflow-y:auto;padding:20px 24px 28px;-webkit-overflow-scrolling:touch}.ai-biz-card{background:#fbfaff;border:1px solid #ece7f7;border-radius:14px;padding:14px 16px;margin-bottom:18px}.ai-biz-title{font-size:14px;font-weight:700;color:#5b3fa8;margin-bottom:10px}.ai-biz-grid{display:flex;flex-wrap:wrap;gap:12px}.ai-biz-field{flex:1;min-width:210px}.ai-biz-label{font-size:12px;font-weight:600;color:#7f8c8d;margin-bottom:4px;display:block}.ai-biz-save{margin-top:10px;padding:9px 20px;background:#7c5cbf;border:none;border-radius:20px;color:#fff;font-weight:700;font-size:13px;cursor:pointer}.ai-biz-save:active{opacity:.85}.ai-biz-hint{font-size:12px;color:#95a5a6;margin-top:8px;line-height:1.4}.ai-upload-zone{border:2px dashed #d3c9f0;border-radius:16px;padding:30px 16px;text-align:center;cursor:pointer;background:#fcfaff;transition:all .2s}.ai-upload-zone:hover,.ai-upload-zone.drag{background:#f5f0ff;border-color:#7c5cbf}.ai-uz-icon{font-size:38px}.ai-uz-main{font-weight:700;color:#2c3e50;margin-top:6px;font-size:15px}.ai-uz-sub{font-size:12px;color:#7f8c8d;margin-top:6px;line-height:1.5}.ai-preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;margin-top:16px}.ai-preview-item{position:relative;border-radius:12px;overflow:hidden;border:1px solid #ecf0f1;background:#f8f9fa}.ai-preview-item img{width:100%;height:96px;object-fit:cover;display:block;background:#eceff1}.ai-preview-remove{position:absolute;top:6px;right:6px;width:24px;height:24px;border-radius:50%;background:rgba(0,0,0,.55);border:none;color:#fff;font-size:14px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center}.ai-preview-name{font-size:11px;color:#5d6d7e;padding:6px 8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ai-controls{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.ai-btn{flex:1;min-width:150px;padding:14px 16px;border-radius:14px;border:none;font-weight:800;font-size:15px;cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s}.ai-btn:disabled{opacity:.6;cursor:not-allowed}.ai-btn-process{background:#e74c3c}.ai-btn-camera{width:100%;background:#2ecc71;margin-top:10px}.ai-progress{padding:36px 20px;text-align:center}.ai-dots{display:flex;gap:9px;justify-content:center}.ai-dots span{width:11px;height:11px;border-radius:50%;background:#e74c3c;opacity:.3;animation:aiWave 1.2s ease-in-out infinite}.ai-dots span:nth-child(1){animation-delay:0s}.ai-dots span:nth-child(2){animation-delay:.15s}.ai-dots span:nth-child(3){animation-delay:.3s}.ai-dots span:nth-child(4){animation-delay:.45s}@keyframes aiWave{0%,100%{transform:translateY(0);opacity:.3}50%{transform:translateY(-9px);opacity:1}}.ai-progress-text{margin-top:16px;font-size:13px;color:#7f8c8d;font-weight:400}.ai-banner{padding:12px 14px;border-radius:12px;font-size:13px;margin-bottom:14px;line-height:1.45;display:none}.ai-banner.error{display:block;background:#fdeaea;color:#c0392b;border-left:4px solid #e74c3c}.ai-banner.info{display:block;background:#e8f4fd;color:#2471a3;border-left:4px solid #3498db}.ai-results-summary{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px}.ai-summary-chip{background:#f0f8f0;color:#27ae60;font-size:12px;font-weight:700;padding:6px 12px;border-radius:20px}.ai-summary-chip.ok{background:#27ae60;color:#fff}.ai-summary-chip.existing{background:#e8f4fd;color:#2471a3}.ai-summary-chip.review{background:#fff9e6;color:#b9770e}.ai-table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid #ecf0f1;border-radius:14px}.ai-table{width:100%;border-collapse:collapse;min-width:660px;background:#fff}.ai-table th{background:#f8f9fa;color:#2c3e50;font-size:12px;font-weight:700;text-align:left;padding:10px 10px;border-bottom:1px solid #ecf0f1;white-space:nowrap}.ai-table td{padding:10px;border-bottom:1px solid #f1f3f5;font-size:14px;vertical-align:middle}.ai-table input{width:100%;padding:8px 10px;font-size:14px;border:1px solid #ddd;border-radius:10px;font-family:'Inter',sans-serif}.ai-table input:focus{outline:none;border-color:#e74c3c}.ai-status{display:inline-block;font-size:11px;font-weight:700;padding:4px 10px;border-radius:14px;white-space:nowrap}.ai-status.existing{background:#e8f4fd;color:#2471a3}.ai-status.new{background:#f0f8f0;color:#27ae60}.ai-status.review{background:#fff9e6;color:#b9770e}.ai-conf{display:inline-block;padding:3px 10px;border-radius:14px;font-size:11px;font-weight:600;background:#f4f6f8;color:#5d6d7e;margin:2px 4px 0 0}.ai-conf-row{font-size:12px;color:#7f8c8d;margin-top:4px}.ai-verify-btn{background:#2ecc71;border:none;color:#fff;font-weight:800;font-size:13px;padding:10px 16px;border-radius:12px;cursor:pointer;white-space:nowrap}.ai-verify-btn:disabled{background:#95a5a6;cursor:not-allowed}.ai-verified-tag{color:#27ae60;font-weight:800;font-size:12px;white-space:nowrap}.ai-warn{font-size:11px;color:#b9770e;line-height:1.35;margin-top:3px}.ai-reset{color:#7c5cbf;font-weight:700;font-size:13px;cursor:pointer;background:none;border:none;padding:8px 6px}@media(max-width:639px){.ai-modal-overlay{align-items:flex-end}.ai-modal-content{border-radius:22px 22px 0 0;max-height:94vh}.ai-modal-body{padding:16px 16px 24px}}
@media(max-width:480px){.ai-biz-grid{flex-direction:column}.ai-biz-field{min-width:0}}
.photo-pick-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:14px;border:2px dashed #cbd5e1;background:#f8fafc;color:#334155;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s}.photo-pick-btn:hover{border-color:#e74c3c;background:#fdeaea;color:#e74c3c}.photo-pick-btn:disabled{opacity:.6;cursor:not-allowed}
</style>
</head>
@endverbatim
@include('partials.photo-viewer')
@include('partials.cloudinary-config')
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget')
@verbatim
<div class="mobile-menu-toggle" id="mobileMenuToggle"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2"><path d="M3 12H21M3 6H21M3 18H21"/></svg></div>
<div class="msimamizi-layout">
<aside class="sidebar" id="sidebar">
<div class="sidebar-header"><div class="logo-area"><div class="logo-icon" data-i18n="msimamizi_bidhaa.logo_short">D</div><div class="logo-text"><h2 data-i18n="msimamizi_bidhaa.logo_brand">DukaMkononi</h2><p data-i18n="msimamizi_bidhaa.logo_tagline">Msimamizi Portal</p></div></div></div>
<div class="nav-items"><a href="index" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-house"></i></div><span class="nav-label" data-i18n="msimamizi_bidhaa.nav_home">Nyumbani</span></a><a href="ripoti" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-chart-simple"></i></div><span class="nav-label" data-i18n="msimamizi_bidhaa.nav_reports">Ripoti</span></a><a href="preview" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-calendar-days"></i></div><span class="nav-label" data-i18n="msimamizi_bidhaa.nav_reviews">Rejea</span></a><a href="bidhaa-mpya" class="nav-item active"><div class="nav-icon"><i class="fa-solid fa-circle-plus"></i></div><span class="nav-label" data-i18n="msimamizi_bidhaa.nav_new_products">Bidhaa Mpya</span></a><a href="tangaza" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-bullhorn"></i></div><span class="nav-label" data-i18n="msimamizi_bidhaa.nav_advertise">Tangaza</span></a></div>
<div class="sidebar-footer"><div class="user-info"><div class="user-avatar" id="userAvatar">M</div><div class="user-details"><div class="user-name" id="userName" data-i18n="msimamizi_bidhaa.nav_admin">Msimamizi</div><div class="user-role">Msimamizi</div></div></div><div class="logout-btn" id="logoutBtn"><span><i class="fa-solid fa-arrow-right-from-bracket"></i></span><span data-i18n="msimamizi_bidhaa.btn_logout">Ondoka</span></div></div>
</aside>
<main class="main-content"><div class="bidhaa-container" id="bidhaaContainer"><div style="text-align:center;padding:60px;" data-i18n="msimamizi_bidhaa.loading_products">Loading...</div></div></main>
</div>
<div id="productModal" class="modal-overlay"><div class="modal-content"><div class="modal-header"><div class="modal-title" data-i18n="msimamizi_bidhaa.title_select_sold_product">Chagua Bidhaa Ilipo</div><div class="modal-close" onclick="closeProductModal()">✖</div></div><input type="text" class="modal-search" id="productSearch" placeholder="Tafuta bidhaa..." data-i18n="msimamizi_bidhaa.placeholder_search_products" data-i18n-attr="placeholder" oninput="filterProducts()"><div class="modal-body" id="productModalBody"><div class="no-products" data-i18n="msimamizi_bidhaa.loading_products_list">Inapakia bidhaa...</div></div></div></div>
<script>
const API_BASE_URL='';
const CLOUDINARY_CONFIG=(window.CLOUDINARY_CONFIG||{cloudName:'',uploadPreset:'react_native_uploads'});
        // Swahili source strings, used until the shared runtime has fetched the
        // catalog. t() prefers window.DM (all 8 locales) and falls back to these.
        const SW = {
            ai_status_almost_done: "Karibu kuisha...",
            ai_status_gemini_scanning: "Gemini inachambua orodha...",
            ai_status_matching: "Inalinganisha bidhaa zilizopo...",
            ai_status_please_wait: "Tafadhali subiri...",
            ai_status_preparing: "Inatayarisha matokeo...",
            ai_status_reading_list: "Inasoma orodha ya bidhaa...",
            ai_status_reading_text: "Inasoma maandishi...",
            ai_status_sending: "Inatuma taarifa kwa Gemini...",
            ai_status_verifying: "Inahakiki kurudiwa kwa bidhaa...",
            alert_error_title: "Hitilafu",
            alert_sign_in_again: "Tafadhali ingia tena",
            alert_success_title: "Mafanikio!",
            bidhaa_kijamii: "Bidhaa za Kijamii",
            bidhaa_urembo: "Bidhaa za Urembo",
            bidhaa_watoto: "Bidhaa za Watoto",
            btn_add_new_product: "Ongeza Bidhaa Mpya",
            btn_add_stock: "Ongeza Hisa",
            btn_cancel: "Ghairi",
            btn_confirm: "Thibitisha",
            btn_delete_product: "Futa Bidhaa",
            btn_edit_product: "Hariri Bidhaa",
            btn_not_editing: "Unahariri",
            btn_ok: "Sawa",
            btn_save_new_product: "ONGEZA BIDHAA MPYA",
            btn_save_product: "SASISHA BIDHAA",
            btn_save_stock: "ONGEZA HISA",
            btn_scan_image: "Chambua Picha",
            btn_set_quantity: "Weka idadi",
            btn_update_info: "Sasisha taarifa",
            confirm_all_products: "Thibitisha Zote?",
            confirm_delete_product: "Una uhakika unataka kufuta {name}?",
            confirm_discard_current_results: "Matokeo ya sasa yataondolewa ({count} za bidhaa hazijathibitishwa). Picha zako zitabaki.",
            confirm_duplicate_add: "Bidhaa {name} tayariipo.\nKuongeza kama mpya?",
            confirm_rescan: "Chambua tena?",
            duka_dawa: "Duka la Dawa",
            duka_kompyuta: "Duka la Kompyuta",
            duka_simu: "Duka la Simu",
            elektroniki: "Elektroniki",
            empty_no_products: "Hakuna bidhaa zilizopatikana",
            empty_out_of_stock: "Bidhaa Ipopo",
            engine: "Engine",
            err_business_type_needed: "Tafadhali chagua au andika aina ya biashara.",
            err_buying_price_negative: "Bei ya kununua haifai — tumia nambari isiyo hasi.",
            err_buying_price_required: "Bei ya kununua inahitajika",
            err_category_required: "Kategoria inahitajika",
            err_delete_failed: "Imeshindwa",
            err_duplicate_product: "Bidhaa {name} tayari ipo kwenye biashara yako — imehifadhiwa.",
            err_fix_before_confirm: "Rekebisha kabla ya kuthibitisha zote:\n{problems}",
            err_image_still_large: "Picha {name} ni kubwa mno baada ya kubanwa.",
            err_image_too_large: "Picha {filename} ni kubwa mno.",
            err_image_type_rejected: "Picha {filename} imekataliwa — aina ya faili haikubaliki.",
            err_images_total_large: "Picha zote ni kubwa mno kwa jumla. Ondoa baadhi ya picha.",
            err_name_empty: "Jina la bidhaa halipaswi kuwa tupu.",
            err_name_required: "Jina linahitajika",
            err_network_save: "Hitilafu ya mtandao wakati wa kuhifadhi.",
            err_network_try_again: "Hitilafu ya mtandao. Tafadhali jaribu tena.",
            err_network_verify: "Hitilafu ya mtandao wakati wa kuthibitisha. Jaribu tena.",
            err_no_permission: "Huna ruhusa",
            err_no_products_detected: "Hakuna bidhaa zilizotambuliwa kutoka kwenye picha. Jaribu picha nyingine zenye mwanga mzuri.",
            err_pick_image_first: "Chagua angalau picha moja kwanza.",
            err_quantity_invalid: "Idadi lazima iwe nambari nzima kubwa kuliko sifuri.",
            err_quantity_required: "Idadi inahitajika",
            err_row_buying_price: "Mstari {line}: bei ya kununua haifai",
            err_row_name_empty: "Mstari {line}: jina tupu",
            err_row_quantity_invalid: "Mstari {line}: idadi si sahihi",
            err_row_selling_price: "Mstari {line}: bei ya kuuzia inahitajika",
            err_save_business_info: "Imeshindwa kuhifadhi taarifa za biashara.",
            err_scan_failed: "Uchambuzi umeshindikana. Tafadhali jaribu tena.",
            err_scan_failed_gemini: "Uchambuzi umeshindikana (Gemini ilikuwa na tatizo). Tafadhali jaribu tena baada ya muda mfupi.",
            err_scan_image_first: "Chambua picha kwanza.",
            err_selling_below_buying: "Bei ya kuuzia ndogo kuliko bei ya kununua",
            err_selling_price_first: "Weka bei ya kuuzia kabla ya kuthibitisha.",
            err_selling_price_required: "Bei ya kuuzia inahitajika",
            err_verify_failed: "Uthibitisho umeshindikana. Jaribu tena.",
            hint_ai_results_present: "Matokeo ya AI yapo. Hakiki kila bidhaa kisha ubonyeze Thibitisha.",
            hint_price_fixed_on_add_stock: "Bei haibadilishwi unapoongeza hisa",
            jumla_wholesale: "Jumla (Wholesale)",
            karakana_magari: "Karakana ya Magari",
            kilimo: "Kilimo",
            kinyozi: "Kinyozi",
            label_buying_price: "Bei ya Kununua *",
            label_category: "Kategoria *",
            label_loss: "Hasara",
            label_new_quantity: "Idadi Mpya",
            label_percentage: "Asilimia:",
            label_product_name: "Jina la Bidhaa *",
            label_product_quantity: "Idadi ya Bidhaa",
            label_profit: "Faida",
            label_profit_per_product: "Faida/bidhaa:",
            label_quantity_add: "Idadi ya Kuongeza",
            label_selling_price: "Bei ya Kuuzia *",
            lbl_buying_inline: "• Bei ya Kununua:",
            lbl_buying_price: "Kununua:",
            lbl_category: "Kategoria:",
            lbl_check_colon: "Hakiki:",
            lbl_selling_inline: "• Kuuzia:",
            lbl_selling_price: "Kuuzia:",
            lbl_stock: "Hisa:",
            lbl_stock_inline: "• Hisa:",
            maembe_dodo: "Maembe Dodo",
            maji_kunywa: "Maji ya Kunywa",
            manukato: "Manukato",
            matibabu: "Matibabu",
            matunda: "Matunda",
            mavazi: "Mavazi",
            mboga: "Mboga",
            mchele_super: "Mchele Super",
            mgahawa: "Mgahawa",
            michezo_burudani: "Michezo na Burudani",
            msg_all_saved: "Zote {count} bidhaa zimehifadhiwa kikamilifu!",
            msg_all_verified: "Bidhaa zote zilizothibitishwa tayari.",
            msg_bulk_saved_once: "{count} bidhaa zitahifadhiwa kwenye stoo mara moja.",
            msg_business_info_saved_ai: "Taarifa za biashara zimehifadhiwa. AI itazitumia kuchambua picha zako.",
            msg_confidence: "Uaminifu: {confidence}%",
            msg_current_stock: "Hisa ya sasa: {stock}",
            msg_new_product_added: "Bidhaa mpya imeongezwa",
            msg_partial_save: "{saved} zimehifadhiwa, {failed} zilishindikana — rekebisha na ujaribu tena.",
            msg_product_added_details: "Bidhaa imeongezwa\nKununua: {buying}\nKuuzia: {selling}\nHisa: {stock}",
            msg_product_deleted: "Bidhaa imefutwa",
            msg_product_updated: "Bidhaa imesasishwa",
            msg_product_verified: "Bidhaa imethibitishwa.",
            msg_stock_added: "Hisa imeongezwa: {name}\nJumla: {total}",
            msg_total_stock: "Hisa jumla: {total}",
            nguo: "Nguo",
            not_available: "N/A",
            nyanya_fresh: "Nyanya Fresh",
            nyingine: "Nyingine",
            nyingine_andika_mwenyewe: "Nyingine (andika mwenyewe)",
            opt_select_business_type: "— Chagua Aina ya Biashara —",
            photo: "Picha",
            placeholder_buying_price: "Bei ya kununua",
            placeholder_category: "Andika kategoria yako",
            placeholder_full_name: "Weka jina kamili",
            placeholder_quantity_add: "Weka idadi ya kuongeza",
            placeholder_selling_price: "Bei unayotaka kauzia",
            rejareja_jumla: "Rejareja ya Jumla",
            saluni_urembo: "Saluni ya Urembo",
            samani: "Samani",
            sehemu_gari: "Sehemu za Gari",
            select_products_count: "Chagua bidhaa ({count} bidhaa)",
            simu_vifaa: "Simu na Vifaa",
            spea_pikipiki: "Spea za Pikipiki",
            status_preparing_image: "Inatayarisha picha...",
            status_saving_progress: "Inahifadhi {current}/{total}...",
            status_verified: "Imethibitishwa",
            subtitle_ai_list_scan: "Chambua orodha ya bidhaa kutoka kwenye picha na uongeze hisa kwa haraka",
            summary_current_total: "Sasa: {current} → Jumla: {total}",
            summary_existing_count: "Zilizopo: {count}",
            summary_need_check_count: "Hakiki inahitajika: {count}",
            summary_new_count: "Mpya: {count}",
            summary_total_count: "Jumla: {count}",
            summary_verified_count: "Zilizothibitishwa: {count}",
            supermarket: "Supermarket",
            t_shirt_rangi: "T-Shirt Rangi",
            th_buying_price: "Bei ya Kununua:",
            th_check: "Hakiki",
            th_name: "Jina",
            th_new: "Mpya",
            th_present: "Iliyopo",
            th_profit: "Faida:",
            th_quantity: "Idadi:",
            title_ai_image_input: "Ingiza Kwa Picha (AI)",
            title_existing_products: "Bidhaa Zilizopo",
            title_fill_product_info: "Jaza taarifa za bidhaa",
            viatu: "Viatu",
            viatu_kawaida: "Viatu vya Kawaida",
            vifaa_baiskeli: "Vifaa vya Baiskeli",
            vifaa_biashara: "Vifaa vya Biashara",
            vifaa_hotelini: "Vifaa vya Hotelini",
            vifaa_huduma: "Vifaa vya Huduma",
            vifaa_jikoni: "Vifaa vya Jikoni",
            vifaa_kilimo: "Vifaa vya Kilimo",
            vifaa_kudumisha_usalama: "Vifaa vya Kudumisha Usalama",
            vifaa_kukarabati: "Vifaa vya Kukarabati",
            vifaa_kupimia: "Vifaa vya Kupimia",
            vifaa_kusafiri: "Vifaa vya Kusafiri",
            vifaa_muziki: "Vifaa vya Muziki",
            vifaa_nyumbani: "Vifaa vya Nyumbani",
            vifaa_ofisi: "Vifaa vya Ofisi",
            vifaa_ofisi_shule: "Vifaa vya Ofisi na Shule",
            vifaa_pikipiki: "Vifaa vya Pikipiki",
            vifaa_redio_tv: "Vifaa vya Redio na TV",
            vifaa_simu: "Vifaa vya Simu",
            vifaa_teknolojia: "Vifaa vya Teknolojia",
            vifaa_uchoraji: "Vifaa vya Uchoraji",
            vifaa_ufundi: "Vifaa vya Ufundi",
            vifaa_ujenzi: "Vifaa vya Ujenzi",
            vifaa_umeme: "Vifaa vya Umeme",
            vifaa_umeme_nyumbani: "Vifaa vya Umeme wa Nyumbani",
            vifaa_usafi: "Vifaa vya Usafi",
            vifaa_usalama: "Vifaa vya Usalama",
            vifaa_ushonaji: "Vifaa vya Ushonaji",
            vifaa_vinene_hardware: "Vifaa Vinene (Hardware)",
            vifaa_viwanda: "Vifaa vya Viwanda",
            vinywaji: "Vinywaji",
            vipodozi: "Vipodozi",
            vitabu_vifaa_kuelimisha: "Vitabu na Vifaa vya Kuelimisha",
            vyakula: "Vyakula",
            wanyama_kufugwa: "Wanyama wa Kufugwa",
            warn_image_count_max: "Umechagua picha {count}. Upeo ni picha {max}.",
            warn_products_saving: "Bidhaa zinahifadhiwa tayari. Subiri kidogo.",
            warn_scan_in_progress: "Tafutani inaendelea. Subiri kidogo.",
            yako: "Yako",
            label_product_photo: "Picha ya Bidhaa (si lazima)",
            btn_choose_photo: "Chagua Picha",
            btn_remove_photo: "Ondoa Picha",
            photo_optional_hint: "Hiari — unaweza kuongeza picha ya bidhaa hii.",
            status_uploading_photo: "Inapakia picha...",
            err_photo_upload_failed: "Imeshindwa kupakia picha. Tafadhali jaribu tena.",
        };
        function t(key, params) {
            const full = key.indexOf('msimamizi_bidhaa.') === 0 ? key : 'msimamizi_bidhaa.' + key;
            if (window.DM && typeof window.DM.t === 'function') {
                const hit = window.DM.t(full, params);
                if (hit !== full) return hit;
            }
            let value = SW[key.replace('msimamizi_bidhaa.', '')];
            if (value === undefined) return key;
            if (params) {
                Object.keys(params).forEach(p => {
                    value = value.split('{' + p + '}').join(params[p] == null ? '' : params[p]);
                });
            }
            return value;
        }
        
let categories=[],OTHER_CATEGORY='',quickFillData={};
function buildCategories(){categories=[t('msimamizi_bidhaa.vyakula'),t('msimamizi_bidhaa.vinywaji'),t('msimamizi_bidhaa.matunda'),t('msimamizi_bidhaa.mboga'),t('msimamizi_bidhaa.nguo'),t('msimamizi_bidhaa.viatu'),t('msimamizi_bidhaa.vifaa_nyumbani'),t('msimamizi_bidhaa.vifaa_umeme'),t('msimamizi_bidhaa.simu_vifaa'),t('msimamizi_bidhaa.matibabu'),t('msimamizi_bidhaa.vifaa_usafi'),t('msimamizi_bidhaa.engine'),t('msimamizi_bidhaa.sehemu_gari'),t('msimamizi_bidhaa.vifaa_ujenzi'),t('msimamizi_bidhaa.vifaa_kilimo'),t('msimamizi_bidhaa.vifaa_ofisi'),t('msimamizi_bidhaa.vitabu_vifaa_kuelimisha'),t('msimamizi_bidhaa.bidhaa_watoto'),t('msimamizi_bidhaa.bidhaa_urembo'),t('msimamizi_bidhaa.bidhaa_kijamii'),t('msimamizi_bidhaa.michezo_burudani'),t('msimamizi_bidhaa.wanyama_kufugwa'),t('msimamizi_bidhaa.vifaa_kusafiri'),t('msimamizi_bidhaa.vifaa_teknolojia'),t('msimamizi_bidhaa.vifaa_kudumisha_usalama'),t('msimamizi_bidhaa.vifaa_redio_tv'),t('msimamizi_bidhaa.vifaa_muziki'),t('msimamizi_bidhaa.vifaa_pikipiki'),t('msimamizi_bidhaa.vifaa_baiskeli'),t('msimamizi_bidhaa.vifaa_ushonaji'),t('msimamizi_bidhaa.vifaa_uchoraji'),t('msimamizi_bidhaa.vifaa_ufundi'),t('msimamizi_bidhaa.vifaa_umeme_nyumbani'),t('msimamizi_bidhaa.vifaa_jikoni'),t('msimamizi_bidhaa.vifaa_kupimia'),t('msimamizi_bidhaa.vifaa_kukarabati'),t('msimamizi_bidhaa.vifaa_usalama'),t('msimamizi_bidhaa.vifaa_biashara'),t('msimamizi_bidhaa.vifaa_hotelini'),t('msimamizi_bidhaa.vifaa_huduma'),t('msimamizi_bidhaa.vifaa_viwanda'),t('msimamizi_bidhaa.nyingine')];OTHER_CATEGORY=t('msimamizi_bidhaa.nyingine');}
function buildQuickFillData(){quickFillData={[t('msimamizi_bidhaa.vyakula')]:{name:t('msimamizi_bidhaa.mchele_super'),price:'2500',expected_selling_price:'3000',stock:'50'},[t('msimamizi_bidhaa.vinywaji')]:{name:t('msimamizi_bidhaa.maji_kunywa'),price:'500',expected_selling_price:'700',stock:'100'},[t('msimamizi_bidhaa.matunda')]:{name:t('msimamizi_bidhaa.maembe_dodo'),price:'800',expected_selling_price:'1000',stock:'30'},[t('msimamizi_bidhaa.mboga')]:{name:t('msimamizi_bidhaa.nyanya_fresh'),price:'1200',expected_selling_price:'1500',stock:'25'},[t('msimamizi_bidhaa.nguo')]:{name:t('msimamizi_bidhaa.t_shirt_rangi'),price:'8000',expected_selling_price:'10000',stock:'15'},[t('msimamizi_bidhaa.viatu')]:{name:t('msimamizi_bidhaa.viatu_kawaida'),price:'25000',expected_selling_price:'30000',stock:'10'}};}
buildCategories();
buildQuickFillData();
let userData=null,token=null,loading=false,existingProducts=[],selectedProduct=null,isOwnerOfSelected=false,modalMode='add',formData={name:'',category:'',price:'',expected_selling_price:'',stock:'',image_url:''},showCustomCategory=false,customCategory='',photoUploading=false;
function escapeHtml(s){if(!s)return'';return s.replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));}
// ============================== ICONS ==============================
// Font Awesome 6 icon helper (https://fontawesome.com).
const FA_MAP={
home:'fa-house',report:'fa-chart-simple',calendar:'fa-calendar-days',plus:'fa-circle-plus',
megaphone:'fa-bullhorn',logout:'fa-arrow-right-from-bracket',search:'fa-magnifying-glass',
money:'fa-money-bill-1',chart:'fa-chart-line',users:'fa-users',user:'fa-user',
box:'fa-box',doc:'fa-file-lines',mail:'fa-envelope',phone:'fa-phone',
building:'fa-building',refresh:'fa-rotate-right',edit:'fa-pen-to-square',
trash:'fa-trash-can',settings:'fa-gear',camera:'fa-camera',location:'fa-location-dot',
check:'fa-check',x:'fa-xmark',clock:'fa-clock',bell:'fa-bell',
warning:'fa-triangle-exclamation',print:'fa-print',filter:'fa-filter',
folder:'fa-folder-open',image:'fa-image',video:'fa-video',thumb:'fa-thumbs-up',
flag:'fa-flag',info:'fa-circle-info',plusSm:'fa-plus',ai:'fa-robot',scan:'fa-barcode',save:'fa-floppy-disk'
};
function ic(name,size,cls){size=size||16;return '<i class="fa-solid '+(FA_MAP[name]||'fa-circle-info')+' '+(cls||'')+'" style="font-size:'+size+'px;" aria-hidden="true"></i>';}
function jsq(v){return"'"+String(v).replace(/\\/g,'\\\\').replace(/'/g,"\\'")+"'";}
function formatCurrency(a){return'TZS '+parseInt(a||0).toLocaleString();}
function showAlert(title,message,onOk){const o=document.createElement('div');o.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:2000;';const b=document.createElement('div');b.style.cssText='background:#fff;border-radius:28px;width:85%;max-width:340px;padding:24px 20px;text-align:center;';b.innerHTML='<div style="font-size:20px;font-weight:800;margin-bottom:12px;">'+escapeHtml(title)+'</div><div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;white-space:pre-line;">'+escapeHtml(message)+'</div><div style="background:#e74c3c;padding:12px;border-radius:40px;color:#fff;font-weight:700;cursor:pointer;">'+t('msimamizi_bidhaa.btn_ok')+'</div>';b.querySelector('div:last-child').onclick=()=>{o.remove();if(onOk)onOk();};o.appendChild(b);document.body.appendChild(o);}
function showConfirm(title,message,onConfirm){const o=document.createElement('div');o.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:2000;';const b=document.createElement('div');b.style.cssText='background:#fff;border-radius:28px;width:85%;max-width:340px;padding:24px 20px;text-align:center;';b.innerHTML='<div style="font-size:18px;font-weight:800;margin-bottom:12px;">'+escapeHtml(title)+'</div><div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;white-space:pre-line;">'+escapeHtml(message)+'</div><div style="display:flex;gap:12px;"><div id="cNo" style="flex:1;background:#95a5a6;padding:12px;border-radius:40px;color:#fff;cursor:pointer;">'+t('msimamizi_bidhaa.btn_cancel')+'</div><div id="cYes" style="flex:1;background:#e74c3c;padding:12px;border-radius:40px;color:#fff;cursor:pointer;">'+t('msimamizi_bidhaa.btn_confirm')+'</div></div>';o.appendChild(b);document.body.appendChild(o);document.getElementById('cYes').onclick=()=>{o.remove();onConfirm();};document.getElementById('cNo').onclick=()=>o.remove();}
function loadUserData(){token=localStorage.getItem('userToken');const u=localStorage.getItem('userData');if(!token||!u){showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.alert_sign_in_again'),()=>window.location.href='../login?role=msimamizi');return false;}userData=JSON.parse(u);const n=userData.full_name||userData.email?.split('@')[0]||'Msimamizi';document.getElementById('userName').innerHTML=escapeHtml(n);const av=document.getElementById('userAvatar');if(userData.business_logo_url){av.classList.add('js-avatar-view');av.setAttribute('data-full',userData.business_logo_url);av.setAttribute('data-name',n);av.innerHTML='<img src="'+escapeHtml(userData.business_logo_url)+'" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="'+t('msimamizi_bidhaa.photo')+'">';}else av.innerHTML=n.charAt(0).toUpperCase();return true;}
async function fetchExistingProducts(){try{const r=await fetch(API_BASE_URL+'/api/business/my/all-products',{headers:{'Authorization':'Bearer '+token,'Content-Type':'application/json'}});if(r.ok)existingProducts=await r.json();}catch(e){}}
function openProductModal(){document.getElementById('productModal').classList.add('show');document.getElementById('productSearch').value='';renderProductList();}
function closeProductModal(){document.getElementById('productModal').classList.remove('show');}
function filterProducts(){renderProductList();}
function renderProductList(){const q=(document.getElementById('productSearch')?.value||'').toLowerCase();const f=existingProducts.filter(p=>p.name.toLowerCase().includes(q)||(p.category||'').toLowerCase().includes(q));const b=document.getElementById('productModalBody');if(!f.length){b.innerHTML='<div class="no-products">'+t('msimamizi_bidhaa.empty_no_products')+'</div>';return;}b.innerHTML=f.map(p=>{const ow=p.seller_id==userData?.id;return '<div class="product-item '+(ow?'owner':'')+'" onclick="selectExistingProduct('+jsq(p.id)+')"><div class="product-item-info"><div class="product-item-name">'+escapeHtml(p.name)+(ow?'<span class="owner-badge">'+t('msimamizi_bidhaa.yako')+'</span>':'')+'</div><div class="product-item-category">'+escapeHtml(p.category||'N/A')+'</div><div class="product-item-details"><span class="product-item-stock">'+t('msimamizi_bidhaa.lbl_stock')+' '+(p.stock||0)+'</span><span class="product-item-price">'+t('msimamizi_bidhaa.lbl_buying_price')+' '+formatCurrency(p.price)+'</span>'+(p.expected_selling_price?'<span style="color:#f39c12">'+t('msimamizi_bidhaa.lbl_selling_price')+' '+formatCurrency(p.expected_selling_price)+'</span>':'')+'</div></div><div class="product-actions" onclick="event.stopPropagation()"><button class="product-action-btn" onclick="handleAddStock('+jsq(p.id)+')" style="color:#2ecc71">'+ic('plusSm',18)+'</button>'+(ow?'<button class="product-action-btn" onclick="handleEditProduct('+jsq(p.id)+')" style="color:#3498db">'+ic('edit',17)+'</button><button class="product-action-btn" onclick="handleDeleteProduct('+jsq(p.id)+','+jsq(p.name)+')" style="color:#e74c3c">'+ic('trash',17)+'</button>':'')+'</div></div>';}).join('');}
function selectExistingProduct(id){const p=existingProducts.find(x=>x.id===id);if(!p)return;selectedProduct=p;isOwnerOfSelected=p.seller_id==userData?.id;modalMode='add';formData={name:p.name,category:p.category||'',price:p.price?.toString()||'',expected_selling_price:p.expected_selling_price?.toString()||'',stock:'',image_url:p.image_url||''};closeProductModal();renderUI();}
function handleAddStock(id){const p=existingProducts.find(x=>x.id===id);if(!p)return;selectedProduct=p;isOwnerOfSelected=p.seller_id==userData?.id;modalMode='add';formData={name:p.name,category:p.category||'',price:p.price?.toString()||'',expected_selling_price:p.expected_selling_price?.toString()||'',stock:'',image_url:p.image_url||''};closeProductModal();renderUI();}
function handleEditProduct(id){const p=existingProducts.find(x=>x.id===id);if(!p)return;if(p.seller_id!=userData?.id){showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.err_no_permission'));return;}selectedProduct=p;isOwnerOfSelected=true;modalMode='edit';formData={name:p.name,category:p.category||'',price:p.price?.toString()||'',expected_selling_price:p.expected_selling_price?.toString()||'',stock:p.stock?.toString()||'',image_url:p.image_url||''};closeProductModal();renderUI();}
function handleDeleteProduct(id,nm){const p=existingProducts.find(x=>x.id===id);if(!p||p.seller_id!=userData?.id){showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.err_no_permission'));return;}showConfirm(t('msimamizi_bidhaa.btn_delete_product'),t('msimamizi_bidhaa.confirm_delete_product', {name: nm}),async()=>{try{const r=await fetch(API_BASE_URL+'/api/products/'+id,{method:'DELETE',headers:{'Authorization':'Bearer '+token}});if(r.ok){showAlert(t('msimamizi_bidhaa.alert_success_title'),t('msimamizi_bidhaa.msg_product_deleted'));await fetchExistingProducts();resetForm();}else showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.err_delete_failed'));}catch(e){showAlert(t('msimamizi_bidhaa.alert_error_title'),e.message);}});}
function clearSelectedProduct(){selectedProduct=null;isOwnerOfSelected=false;modalMode='add';renderUI();}
function isPriceEditable(){if(!selectedProduct)return true;if(modalMode==='edit'&&isOwnerOfSelected)return true;return false;}
function getPriceDiff(){const cp=parseFloat(formData.price||'0'),ep=parseFloat(formData.expected_selling_price||'0');if(cp>0&&ep>0){const d=ep-cp,p=(d/cp)*100;return{diff:d,pct:p,formatted:(d>=0?'+':'')+'TZS '+Math.abs(d).toLocaleString(),pctFormatted:(p>=0?'+':'')+p.toFixed(2)+'%'};}return null;}
function validateForm(){const e=[];if(!formData.name.trim())e.push(t('msimamizi_bidhaa.err_name_required'));if(!formData.category)e.push(t('msimamizi_bidhaa.err_category_required'));if(!formData.price||parseFloat(formData.price)<=0)e.push(t('msimamizi_bidhaa.err_buying_price_required'));if(!formData.expected_selling_price||parseFloat(formData.expected_selling_price)<=0)e.push(t('msimamizi_bidhaa.err_selling_price_required'));if(!formData.stock||parseInt(formData.stock)<0)e.push(t('msimamizi_bidhaa.err_quantity_required'));if(parseFloat(formData.expected_selling_price)<parseFloat(formData.price))e.push(t('msimamizi_bidhaa.err_selling_below_buying'));return e;}
function isFormValid(){return !!(formData.name&&formData.category&&formData.price&&formData.expected_selling_price&&formData.stock);}
async function handleSubmit(){const errs=validateForm();if(errs.length){showAlert(t('msimamizi_bidhaa.alert_error_title'),errs.join('\n'));return;}if(loading)return;loading=true;renderUI();try{const pd={name:formData.name.trim(),category:formData.category,price:parseFloat(formData.price),stock:parseInt(formData.stock),expected_selling_price:parseFloat(formData.expected_selling_price),image_url:formData.image_url||null};const h={'Content-Type':'application/json','Authorization':'Bearer '+token};if(selectedProduct&&modalMode==='add'){const qty=parseInt(formData.stock||'0');if(qty<=0){showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.btn_set_quantity'));loading=false;renderUI();return;}const ns=parseInt(selectedProduct.stock||'0')+qty;const r=await fetch(API_BASE_URL+'/api/products/'+selectedProduct.id,{method:'PUT',headers:h,body:JSON.stringify({name:selectedProduct.name,category:selectedProduct.category,stock:ns})});if(r.ok){showAlert(t('msimamizi_bidhaa.alert_success_title'),t('msimamizi_bidhaa.msg_stock_added', {name: selectedProduct.name, total: ns}));await fetchExistingProducts();resetForm();}else showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.err_delete_failed'));loading=false;renderUI();return;}if(selectedProduct&&modalMode==='edit'){const r=await fetch(API_BASE_URL+'/api/products/'+selectedProduct.id,{method:'PUT',headers:h,body:JSON.stringify(pd)});if(r.ok){showAlert(t('msimamizi_bidhaa.alert_success_title'),t('msimamizi_bidhaa.msg_product_updated'));await fetchExistingProducts();resetForm();}else showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.err_delete_failed'));loading=false;renderUI();return;}const ex=existingProducts.find(p=>p.name.toLowerCase()===formData.name.toLowerCase().trim());if(ex&&ex.seller_id==userData?.id){const ns=parseInt(ex.stock||'0')+parseInt(formData.stock);const r=await fetch(API_BASE_URL+'/api/products/'+ex.id,{method:'PUT',headers:h,body:JSON.stringify({...pd,stock:ns,image_url:formData.image_url||(ex.image_url||null)})});if(r.ok){showAlert(t('msimamizi_bidhaa.alert_success_title'),t('msimamizi_bidhaa.msg_total_stock', {total: ns}));await fetchExistingProducts();resetForm();}else showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.err_delete_failed'));loading=false;renderUI();return;}const r=await fetch(API_BASE_URL+'/api/products',{method:'POST',headers:h,body:JSON.stringify(pd)});const txt=await r.text();if(r.ok){showAlert(t('msimamizi_bidhaa.alert_success_title'),t('msimamizi_bidhaa.msg_product_added_details', {buying: formatCurrency(formData.price), selling: formatCurrency(formData.expected_selling_price), stock: formData.stock}));await fetchExistingProducts();resetForm();}else if(r.status===403){showAlert(t('msimamizi_bidhaa.empty_out_of_stock'),t('msimamizi_bidhaa.confirm_duplicate_add', {name: formData.name}),async()=>{const r2=await fetch(API_BASE_URL+'/api/products',{method:'POST',headers:h,body:JSON.stringify(pd)});if(r2.ok){showAlert(t('msimamizi_bidhaa.alert_success_title'),t('msimamizi_bidhaa.msg_new_product_added'));await fetchExistingProducts();resetForm();}else showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.err_delete_failed'));});}else showAlert(t('msimamizi_bidhaa.alert_error_title'),txt||t('msimamizi_bidhaa.err_delete_failed'));}catch(e){showAlert(t('msimamizi_bidhaa.alert_error_title'),e.message);}loading=false;renderUI();}
function resetForm(){formData={name:'',category:'',price:'',expected_selling_price:'',stock:'',image_url:''};customCategory='';showCustomCategory=false;selectedProduct=null;isOwnerOfSelected=false;modalMode='add';photoUploading=false;renderUI();}
function pickProductPhoto(){const el=document.getElementById('productPhotoInput');if(el)el.click();}
function removeProductPhoto(){formData.image_url='';renderUI();}
async function onProductPhotoSelected(input){const f=input.files&&input.files[0];input.value='';if(!f)return;if(f.type.indexOf('image/')!==0){showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.err_photo_upload_failed'));return;}photoUploading=true;renderUI();try{formData.image_url=await uploadProductPhoto(f);}catch(e){showAlert(t('msimamizi_bidhaa.alert_error_title'),t('msimamizi_bidhaa.err_photo_upload_failed'));}photoUploading=false;renderUI();}
async function uploadProductPhoto(file){const fd=new FormData();fd.append('file',file);fd.append('upload_preset',CLOUDINARY_CONFIG.uploadPreset);const url='https://api.cloudinary.com/v1_1/'+CLOUDINARY_CONFIG.cloudName+'/image/upload';const r=await fetch(url,{method:'POST',body:fd});if(!r.ok)throw new Error('upload failed');const d=await r.json();return d.secure_url;}
function renderUI(){const c=document.getElementById('bidhaaContainer');const pe=isPriceEditable();const pd=getPriceDiff();const iv=isFormValid();const sc=modalMode==='edit'?'update':(selectedProduct&&modalMode==='add'?'addstock':'add');const sl=modalMode==='edit'?ic('refresh')+' '+t('msimamizi_bidhaa.btn_save_product')+'':(selectedProduct&&modalMode==='add'?ic('plus')+' '+t('msimamizi_bidhaa.btn_save_stock')+'':ic('plus')+' '+t('msimamizi_bidhaa.btn_save_new_product')+'');const ch=categories.map(cat=>'<div class="category-chip '+(formData.category===cat?'selected':'')+'" data-cat="'+cat+'">'+escapeHtml(cat)+'</div>').join('');const showPhoto=!(selectedProduct&&modalMode==='add');const photoHtml=showPhoto?'<input type="file" id="productPhotoInput" accept="image/*" style="display:none" onchange="onProductPhotoSelected(this)">'+(formData.image_url?'<div style="display:flex;align-items:center;gap:14px;"><div style="position:relative;"><img src="'+escapeHtml(formData.image_url)+'" alt="" style="width:104px;height:104px;object-fit:cover;border-radius:14px;border:1px solid #ecf0f1;display:block;"><button type="button" onclick="removeProductPhoto()" title="'+t('msimamizi_bidhaa.btn_remove_photo')+'" style="position:absolute;top:-8px;right:-8px;width:26px;height:26px;border-radius:50%;border:none;background:#e74c3c;color:#fff;font-size:13px;line-height:1;cursor:pointer;">✖</button></div><button type="button" class="photo-pick-btn" onclick="pickProductPhoto()" '+(photoUploading?'disabled':'')+'>'+ic('camera',16)+' '+t('msimamizi_bidhaa.btn_choose_photo')+'</button></div>':'<button type="button" class="photo-pick-btn" onclick="pickProductPhoto()" '+(photoUploading?'disabled':'')+'>'+ic('camera',16)+' '+(photoUploading?t('msimamizi_bidhaa.status_uploading_photo'):t('msimamizi_bidhaa.btn_choose_photo'))+'</button>')+'<div style="margin-top:8px;font-size:12px;color:#7f8c8d;">'+(photoUploading?t('msimamizi_bidhaa.status_uploading_photo'):t('msimamizi_bidhaa.photo_optional_hint'))+'</div>':'';const photoGroup='<div class="input-group"><label class="label">'+t('msimamizi_bidhaa.label_product_photo')+'</label>'+photoHtml+'</div>';c.innerHTML='<div class="form-card"><h2 style="color:#2c3e50;margin-bottom:4px">'+(modalMode==='edit'?ic('edit',20)+' '+t('msimamizi_bidhaa.btn_edit_product')+'':ic('plus',20)+' '+t('msimamizi_bidhaa.btn_add_new_product')+'')+'</h2><p style="color:#7f8c8d;margin-bottom:20px">'+(modalMode==='edit'?t('msimamizi_bidhaa.btn_update_info'):t('msimamizi_bidhaa.title_fill_product_info'))+'</p><div class="ai-import-btn" onclick="openAiImportModal()"><span class="card-ic" style="color:#7c5cbf">'+ic('ai',26)+'</span><div style="flex:1"><div style="font-weight:700;color:#2c3e50">'+t('msimamizi_bidhaa.title_ai_image_input')+'</div><div style="font-size:13px;color:#7f8c8d">'+t('msimamizi_bidhaa.subtitle_ai_list_scan')+'</div></div><span style="color:#95a5a6;font-size:20px">›</span></div><div class="existing-btn" onclick="openProductModal()"><span class="card-ic" style="color:#28a745">'+ic('folder',26)+'</span><div style="flex:1"><div style="font-weight:700;color:#2c3e50">'+t('msimamizi_bidhaa.title_existing_products')+'</div><div style="font-size:13px;color:#7f8c8d">'+t('msimamizi_bidhaa.select_products_count',{count:existingProducts.length})+'</div></div><span style="color:#95a5a6;font-size:20px">›</span></div>'+(selectedProduct?'<div class="selected-banner '+(isOwnerOfSelected?'':'non-owner')+'"><div class="selected-banner-header"><div class="selected-banner-title">'+(modalMode==='edit'?ic('edit',13)+' '+t('msimamizi_bidhaa.btn_not_editing')+'':ic('box',13)+' '+t('msimamizi_bidhaa.btn_add_stock')+'')+': '+escapeHtml(selectedProduct.name)+'</div><div class="selected-banner-close" onclick="clearSelectedProduct()">✖</div></div><div style="font-size:12px;color:#7f8c8d">'+t('msimamizi_bidhaa.lbl_category')+' '+escapeHtml(selectedProduct.category||'N/A')+' '+t('msimamizi_bidhaa.lbl_stock_inline')+' '+(selectedProduct.stock||0)+' '+t('msimamizi_bidhaa.lbl_buying_inline')+' '+formatCurrency(selectedProduct.price)+(selectedProduct.expected_selling_price?' '+t('msimamizi_bidhaa.lbl_selling_inline')+' '+formatCurrency(selectedProduct.expected_selling_price):'')+'</div></div>':'')+'<div class="input-group"><label class="label">'+t('msimamizi_bidhaa.label_product_name')+'</label><input type="text" id="fName" placeholder="'+t('msimamizi_bidhaa.placeholder_full_name')+'" value="'+escapeHtml(formData.name)+'" '+(selectedProduct&&modalMode==='add'?'disabled':'')+'></div>'+photoGroup+'<div class="input-group"><label class="label">'+t('msimamizi_bidhaa.label_category')+'</label><div class="categories-scroll">'+ch+'</div><div id="customCatWrap" style="margin-top:12px;padding:12px;background:#e8f4fd;border-radius:14px;border-left:4px solid #3498db;display:'+(showCustomCategory?'block':'none')+'"><input type="text" id="fCustomCat" placeholder="'+t('msimamizi_bidhaa.placeholder_category')+'" value="'+escapeHtml(customCategory)+'"></div></div><div class="input-group"><label class="label">'+t('msimamizi_bidhaa.label_buying_price')+'</label><div class="price-container"><input type="number" id="fPrice" placeholder="'+t('msimamizi_bidhaa.placeholder_buying_price')+'" value="'+formData.price+'" '+(!pe?'disabled':'')+'><div class="price-preview" id="pp1" style="display:'+(formData.price?'block':'none')+'"><small>TZS</small> <strong id="ppv1">'+(formData.price?parseInt(formData.price).toLocaleString():'')+'</strong></div></div>'+(!pe&&selectedProduct?'<div style="font-size:12px;color:#e74c3c;margin-top:4px;font-style:italic">'+t('msimamizi_bidhaa.hint_price_fixed_on_add_stock')+'</div>':'')+'</div><div class="input-group"><label class="label">'+t('msimamizi_bidhaa.label_selling_price')+'</label><div class="price-container"><input type="number" id="fExpPrice" placeholder="'+t('msimamizi_bidhaa.placeholder_selling_price')+'" value="'+formData.expected_selling_price+'" '+(!pe?'disabled':'')+'><div class="price-preview" id="pp2" style="display:'+(formData.expected_selling_price?'block':'none')+'"><small>TZS</small> <strong id="ppv2">'+(formData.expected_selling_price?parseInt(formData.expected_selling_price).toLocaleString():'')+'</strong></div></div></div>'+(pd?'<div class="price-diff '+(pd.diff>=0?'positive':'negative')+'"><div style="font-weight:700;color:#2c3e50">'+(pd.diff>=0?t('msimamizi_bidhaa.label_profit'):t('msimamizi_bidhaa.label_loss'))+'</div><div class="price-diff-row"><span>'+t('msimamizi_bidhaa.label_profit_per_product')+'</span><strong style="color:'+(pd.diff>=0?'#27ae60':'#e74c3c')+'">'+pd.formatted+'</strong></div><div class="price-diff-row"><span>'+t('msimamizi_bidhaa.label_percentage')+'</span><strong style="color:'+(pd.diff>=0?'#27ae60':'#e74c3c')+'">'+pd.pctFormatted+'</strong></div></div>':'')+'<div class="input-group"><label class="label">'+(modalMode==='edit'?t('msimamizi_bidhaa.label_new_quantity'):(selectedProduct&&modalMode==='add'?t('msimamizi_bidhaa.label_quantity_add'):t('msimamizi_bidhaa.label_product_quantity')))+' *</label><input type="number" id="fStock" placeholder="'+(selectedProduct&&modalMode==='add'?t('msimamizi_bidhaa.placeholder_quantity_add'):t('msimamizi_bidhaa.btn_set_quantity'))+'" value="'+formData.stock+'">'+(selectedProduct&&modalMode==='add'?'<div style="font-size:13px;color:#27ae60;margin-top:6px">'+t('msimamizi_bidhaa.summary_current_total',{current:(selectedProduct.stock||0),total:(parseInt(selectedProduct.stock||0)+parseInt(formData.stock||0))})+'</div>':'')+'</div><button id="submitBtn" class="submit-btn '+sc+' '+(!iv?'disabled':'')+'" '+(!iv?'disabled':'')+'>'+(loading?'<div class="loading-spinner"></div>':sl)+'</button></div><div id="previewArea"></div>';
document.getElementById('submitBtn')?.addEventListener('click',handleSubmit);document.getElementById('fName')?.addEventListener('input',e=>{formData.name=e.target.value;updateDynamic();});document.getElementById('fPrice')?.addEventListener('input',e=>{formData.price=e.target.value.replace(/[^0-9]/g,'');updateDynamic();});document.getElementById('fExpPrice')?.addEventListener('input',e=>{formData.expected_selling_price=e.target.value.replace(/[^0-9]/g,'');updateDynamic();});document.getElementById('fStock')?.addEventListener('input',e=>{formData.stock=e.target.value.replace(/[^0-9]/g,'');updateDynamic();});document.getElementById('fCustomCat')?.addEventListener('input',e=>{customCategory=e.target.value;formData.category=customCategory;updateDynamic();});document.querySelectorAll('.category-chip').forEach(el=>{el.addEventListener('click',()=>{const cat=el.getAttribute('data-cat');if(cat===OTHER_CATEGORY){showCustomCategory=true;formData.category='';document.getElementById('customCatWrap').style.display='block';}else{showCustomCategory=false;formData.category=cat;document.getElementById('customCatWrap').style.display='none';const q=quickFillData[cat];if(q&&!formData.name){formData.name=q.name;formData.price=q.price;formData.expected_selling_price=q.expected_selling_price;formData.stock=q.stock;const fn=document.getElementById('fName'),fp=document.getElementById('fPrice'),fe=document.getElementById('fExpPrice'),fs=document.getElementById('fStock');if(fn)fn.value=q.name;if(fp)fp.value=q.price;if(fe)fe.value=q.expected_selling_price;if(fs)fs.value=q.stock;}}updateDynamic();});});updateDynamic();}
function updateDynamic(){document.querySelectorAll('.category-chip').forEach(el=>{el.classList.toggle('selected',formData.category===el.getAttribute('data-cat'));});const p1=document.getElementById('pp1'),v1=document.getElementById('ppv1');if(p1&&v1){p1.style.display=formData.price?'block':'none';v1.textContent=formData.price?parseInt(formData.price).toLocaleString():'';}const p2=document.getElementById('pp2'),v2=document.getElementById('ppv2');if(p2&&v2){p2.style.display=formData.expected_selling_price?'block':'none';v2.textContent=formData.expected_selling_price?parseInt(formData.expected_selling_price).toLocaleString():'';}const iv=isFormValid();const sb=document.getElementById('submitBtn');if(sb){sb.disabled=!iv;sb.classList.toggle('disabled',!iv);}const pa=document.getElementById('previewArea');if(pa&&(formData.name||formData.price||formData.expected_selling_price)){const d=getPriceDiff();pa.innerHTML='<div class="preview-card"><div class="preview-inner"><div style="font-weight:700;margin-bottom:12px">'+t('msimamizi_bidhaa.lbl_check_colon')+'</div><div><strong>'+escapeHtml(formData.name||t('msimamizi_bidhaa.th_name'))+'</strong></div><div style="font-size:13px;color:#7f8c8d">'+t('msimamizi_bidhaa.lbl_category')+' '+escapeHtml(formData.category||t('msimamizi_bidhaa.not_available'))+'</div><div style="display:flex;justify-content:space-between;margin-top:10px"><div>'+t('msimamizi_bidhaa.th_buying_price')+' <strong style="color:#3498db">'+formatCurrency(formData.price)+'</strong></div><div>'+t('msimamizi_bidhaa.lbl_selling_price')+' <strong style="color:#27ae60">'+formatCurrency(formData.expected_selling_price)+'</strong></div></div>'+(d?'<div style="margin-top:8px;font-size:13px">'+t('msimamizi_bidhaa.th_profit')+' <strong style="color:'+(d.diff>=0?'#27ae60':'#e74c3c')+'">'+d.formatted+' ('+d.pctFormatted+')</strong></div>':'')+'<div style="margin-top:8px">'+t('msimamizi_bidhaa.th_quantity')+' '+(formData.stock||0)+'</div></div></div>';}else if(pa)pa.innerHTML='';}
function setupSidebar(){document.getElementById('mobileMenuToggle')?.addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('open'));document.getElementById('logoutBtn')?.addEventListener('click',()=>{localStorage.clear();window.location.href='../login?role=msimamizi';});}
async function init(){buildCategories();buildQuickFillData();setupSidebar();if(!loadUserData())return;await fetchExistingProducts();renderUI();}

// Every dynamic string on this page is rebuilt by renderUI()/renderAiResults(),
// so rebuilding the load-time catalogs and re-rendering covers a locale switch.
// The product modal and AI modal are re-painted too, otherwise a user who has
// one open would keep seeing the old language inside it.
if(window.DM&&typeof window.DM.onChange==='function'){
window.DM.onChange(function(){
buildCategories();
buildQuickFillData();
if(typeof window.dmRefreshAiLocale==='function')window.dmRefreshAiLocale();
if(document.getElementById('productModal').classList.contains('show'))renderProductList();
if(!loading)renderUI();
});
}

init();
</script>
<div id="aiImportModal" class="ai-modal-overlay">
<div class="ai-modal-content">
<div class="ai-modal-header"><div class="ai-modal-title" data-i18n="msimamizi_bidhaa.title_ai_image_input">Ingiza Kwa Picha (AI)</div><div class="ai-modal-close" onclick="closeAiImportModal()">✖</div></div>
<div class="ai-modal-body">
<div id="aiBanner" class="ai-banner"></div>
<div class="ai-biz-card">
<div class="ai-biz-title" data-i18n="msimamizi_bidhaa.hint_business_info_ai">Taarifa za Biashara (husaidia AI kufahamu bidhaa zako)</div>
<div class="ai-biz-grid">
<div class="ai-biz-field"><label class="ai-biz-label" data-i18n="msimamizi_bidhaa.label_business_type">Aina ya Biashara</label><select id="aiBizType"></select></div>
<div class="ai-biz-field" id="aiBizTypeCustomWrap" style="display:none;"><label class="ai-biz-label" data-i18n="msimamizi_bidhaa.placeholder_business_type">Andika Aina Yako ya Biashara</label><input type="text" id="aiBizTypeCustom" placeholder="Mfano: Spea za Pikipiki" data-i18n="msimamizi_bidhaa.placeholder_business_type_example" data-i18n-attr="placeholder"></div>
<div class="ai-biz-field"><label class="ai-biz-label" data-i18n="msimamizi_bidhaa.label_business_description">Maelezo mafupi ya Biashara</label><input type="text" id="aiBizDesc" placeholder="Mfano: Tunauza sehemu za magari ya Toyota na Nissan..." data-i18n="msimamizi_bidhaa.placeholder_business_description" data-i18n-attr="placeholder"></div>
</div>
<button type="button" class="ai-biz-save" onclick="saveAiBusinessProfile()" data-i18n="msimamizi_bidhaa.btn_save_info">Hifadhi Taarifa</button>
</div>
<div id="aiUploadSection">
<div id="aiUploadZone" class="ai-upload-zone">
<div class="ai-uz-icon" style="display:flex;justify-content:center;color:#7c5cbf;"><i class="fa-solid fa-camera" style="font-size:38px;"></i></div>
<div class="ai-uz-main" data-i18n="msimamizi_bidhaa.title_choose_or_capture">Chagua au piga picha ya orodha ya bidhaa</div>
<div class="ai-uz-sub" data-i18n-html="msimamizi_bidhaa.hint_multiple_images">Unaweza kuchagua picha nyingi (hadi 6). Picha zenye mwanga mzuri huwa bora zaidi.<br>Picha hazichakatwi mpaka ubonyeze kitufe cha "Chambua Picha".</div>
</div>
<button type="button" class="ai-btn ai-btn-camera" onclick="captureAiImage()"><i class="fa-solid fa-camera" style="font-size:17px;"></i> <span data-i18n="msimamizi_bidhaa.btn_capture_camera">Piga Picha Sasa (Kamera)</span></button>
<input type="file" id="aiFilesInput" accept="image/*" multiple style="display:none">
<div id="aiPreviewGrid" class="ai-preview-grid"></div>
<div class="ai-controls"><button type="button" class="ai-btn ai-btn-process" id="aiProcessBtn" onclick="startAiProcess()"><i class="fa-solid fa-barcode" style="font-size:17px;"></i> <span data-i18n="msimamizi_bidhaa.btn_scan_image">Chambua Picha</span></button></div>
</div>
<div id="aiProgress" class="ai-progress" style="display:none">
<div class="ai-dots"><span></span><span></span><span></span><span></span></div>
<div id="aiProgressText" class="ai-progress-text" data-i18n="msimamizi_bidhaa.status_preparing_image">Inatayarisha picha...</div>
</div>
<div id="aiResultsWrap" style="display:none">
<div class="ai-results-summary" id="aiResultsSummary"></div>
<div class="ai-table-scroll">
<table class="ai-table">
<thead><tr><th data-i18n="msimamizi_bidhaa.label_product_name_col">Jina la Bidhaa</th><th data-i18n="msimamizi_bidhaa.label_quantity_col">Idadi</th><th data-i18n="msimamizi_bidhaa.label_buying_col">Bei ya Kununua</th><th data-i18n="msimamizi_bidhaa.label_selling_col">Bei ya Kuuzia</th><th data-i18n="msimamizi_bidhaa.label_status">Hali</th><th data-i18n="msimamizi_bidhaa.th_check">Hakiki</th><th data-i18n="msimamizi_bidhaa.th_verification">Uthibitisho</th></tr></thead>
<tbody id="aiTableBody"></tbody>
</table>
</div>
<div style="margin-top:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
<span style="font-size:12px;color:#7f8c8d" data-i18n="msimamizi_bidhaa.hint_edit_then_confirm">Hariri taarifa kwenye meza, kisha bonyeza "Thibitisha" kwa kila bidhaa au "Thibitisha Zote". Bidhaa haziwezi kuongezwa kabla ya uthibitisho wako.</span>
<button type="button" class="ai-reset" onclick="resetAiSession()" data-i18n="msimamizi_bidhaa.btn_start_over">Anza Upya</button>
</div>
<div style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
<button type="button" class="ai-verify-btn" id="aiVerifyAllBtn" style="background:#27ae60;padding:12px 22px;font-size:14px" onclick="verifyAllAiRows()" data-i18n="msimamizi_bidhaa.btn_confirm_all">Thibitisha Zote</button>
<span id="aiVerifyAllStatus" style="font-size:12px;color:#7f8c8d"></span>
</div>
</div>
</div>
</div>
</div>
<script>
(function(){
var AI_MAX_IMAGES=6;
var AI_MAX_PER_IMAGE_B64=6*1024*1024;
var AI_MAX_TOTAL_B64=9*1024*1024;
var AI_PROGRESS_MSGS=[],AI_BIZ_TYPES=[];
function buildAiCatalog(){
AI_PROGRESS_MSGS=[t('msimamizi_bidhaa.status_preparing_image'),t('msimamizi_bidhaa.ai_status_reading_text'),t('msimamizi_bidhaa.ai_status_reading_list'),t('msimamizi_bidhaa.ai_status_sending'),t('msimamizi_bidhaa.ai_status_gemini_scanning'),t('msimamizi_bidhaa.ai_status_matching'),t('msimamizi_bidhaa.ai_status_verifying'),t('msimamizi_bidhaa.ai_status_preparing'),t('msimamizi_bidhaa.ai_status_almost_done'),t('msimamizi_bidhaa.ai_status_please_wait')];
AI_BIZ_TYPES=[['spare_parts',t('msimamizi_bidhaa.sehemu_gari')],['motorcycle_spares',t('msimamizi_bidhaa.spea_pikipiki')],['supermarket',t('msimamizi_bidhaa.supermarket')],['pharmacy',t('msimamizi_bidhaa.duka_dawa')],['electronics',t('msimamizi_bidhaa.elektroniki')],['clothing',t('msimamizi_bidhaa.mavazi')],['hardware',t('msimamizi_bidhaa.vifaa_vinene_hardware')],['cosmetics',t('msimamizi_bidhaa.vipodozi')],['perfume',t('msimamizi_bidhaa.manukato')],['restaurant',t('msimamizi_bidhaa.mgahawa')],['furniture',t('msimamizi_bidhaa.samani')],['stationery',t('msimamizi_bidhaa.vifaa_ofisi_shule')],['mobile_accessories',t('msimamizi_bidhaa.vifaa_simu')],['computer_shop',t('msimamizi_bidhaa.duka_kompyuta')],['phone_shop',t('msimamizi_bidhaa.duka_simu')],['agriculture',t('msimamizi_bidhaa.kilimo')],['construction_materials',t('msimamizi_bidhaa.vifaa_ujenzi')],['beauty_salon',t('msimamizi_bidhaa.saluni_urembo')],['barbershop',t('msimamizi_bidhaa.kinyozi')],['auto_repair',t('msimamizi_bidhaa.karakana_magari')],['general_retail',t('msimamizi_bidhaa.rejareja_jumla')],['wholesale',t('msimamizi_bidhaa.jumla_wholesale')],['other',t('msimamizi_bidhaa.nyingine')],['__custom__',t('msimamizi_bidhaa.nyingine_andika_mwenyewe')]];
}
buildAiCatalog();
// Called by the host page when the visitor switches language, so the catalog
// labels, the progress messages and the results table are rebuilt in place.
window.dmRefreshAiLocale=function(){buildAiCatalog();populateAiBizTypes();refreshAiUploadUi();renderAiResults();};
var aiImages=[];
var aiRows=[];
var aiProcessing=false;
var aiVerifyBusy=false;
var aiProgressTimer=null;
var aiUploadHandlersReady=false;
var aiTableSyncReady=false;
var aiBatchBusy=false;
function $(id){return document.getElementById(id);}
function esc(s){if(s===null||s===undefined)return'';return String(s).replace(/[&<>"']/g,function(m){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];});}
function showAiBanner(type,msg){var b=$('aiBanner');if(!b)return;b.className='ai-banner '+(type==='info'?'info':'error');b.innerHTML=esc(msg);}
function hideAiBanner(){var b=$('aiBanner');if(b)b.className='ai-banner';}
function stopAiProgress(){if(aiProgressTimer){clearInterval(aiProgressTimer);aiProgressTimer=null;}}
function startAiProgress(){
stopAiProgress();
var idx=0;var el=$('aiProgressText');if(el)el.textContent=t('msimamizi_bidhaa.status_preparing_image');
aiProgressTimer=setInterval(function(){idx=(idx+1)%AI_PROGRESS_MSGS.length;var e=$('aiProgressText');if(e)e.textContent=AI_PROGRESS_MSGS[idx];},2400);
}
function aiConfirm(title,msg){
return new Promise(function(resolve){
var ov=document.createElement('div');ov.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:3000;';
var box=document.createElement('div');box.style.cssText='background:#fff;border-radius:28px;width:85%;max-width:340px;padding:24px 20px;text-align:center;';
box.innerHTML='<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c3e50;">'+esc(title)+'</div><div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;white-space:pre-line;text-align:left;"></div><div style="display:flex;gap:12px;"><div style="flex:1;background:#95a5a6;padding:12px;border-radius:40px;color:#fff;cursor:pointer;font-weight:700;" class="aiCfmNo">'+t('msimamizi_bidhaa.btn_cancel')+'</div><div style="flex:1;background:#e74c3c;padding:12px;border-radius:40px;color:#fff;cursor:pointer;font-weight:700;" class="aiCfmYes">'+t('msimamizi_bidhaa.btn_confirm')+'</div></div>';
ov.appendChild(box);document.body.appendChild(ov);
box.querySelector('div:nth-child(2)').textContent=msg;
function done(v){ov.remove();resolve(v);}
box.querySelector('.aiCfmYes').onclick=function(){done(true);};
box.querySelector('.aiCfmNo').onclick=function(){done(false);};
ov.addEventListener('click',function(e){if(e.target===ov)done(false);});
});
}
function populateAiBizTypes(){
var sel=$('aiBizType');if(!sel)return;
// Rebuild the option labels on every call: after a language switch the labels
// are stale, but the user's current choice must survive the rebuild.
var keep=sel.value;
sel.innerHTML='<option value="">'+t('msimamizi_bidhaa.opt_select_business_type')+'</option>'+AI_BIZ_TYPES.map(function(pair){return '<option value="'+esc(pair[0])+'">'+esc(pair[1])+'</option>';}).join('');
if(keep)sel.value=keep;
if(!sel.onchange)sel.onchange=function(){var w=$('aiBizTypeCustomWrap');if(w)w.style.display=sel.value==='__custom__'?'block':'none';};
}
// Sets the business-type select from a stored value; unknown/custom values
// switch the select to "Nyingine (andika mwenyewe)" and fill the free-text box.
function setAiBizTypeValue(v){
var sel=$('aiBizType'),wrap=$('aiBizTypeCustomWrap'),ci=$('aiBizTypeCustom');
if(!sel)return;
if(!v){sel.value='';if(wrap)wrap.style.display='none';return;}
var known=AI_BIZ_TYPES.some(function(pair){return pair[0]===v;});
if(known&&v!=='__custom__'){sel.value=v;if(wrap)wrap.style.display='none';}
else{sel.value='__custom__';if(ci)ci.value=v;if(wrap)wrap.style.display='block';}
}
async function loadAiBusinessProfile(){
var sel=$('aiBizType'),desc=$('aiBizDesc');if(!sel||!desc)return;
// Prefill from cached userData first (instant), then refresh from the
// profile API so taarifa za biashara zilizohifadhiwa appear automatically
// and the user never re-enters them.
try{if(typeof userData!=='undefined'&&userData){if(userData.business_type)setAiBizTypeValue(userData.business_type);if(userData.business_description)desc.value=userData.business_description;}}catch(e){}
try{
var r=await fetch(API_BASE_URL+'/api/user/profile',{headers:{'Authorization':'Bearer '+token}});
if(r.ok){var u=await r.json();if(u.business_type){setAiBizTypeValue(u.business_type);if(typeof userData!=='undefined'&&userData){userData.business_type=u.business_type;}}if(u.business_description){desc.value=u.business_description;if(typeof userData!=='undefined'&&userData){userData.business_description=u.business_description;}}}
}catch(e){}
}
async function saveAiBusinessProfile(){
var sel=$('aiBizType'),desc=$('aiBizDesc');
var btype=sel.value,bdesc=(desc.value||'').trim();
if(btype==='__custom__'){var ci=$('aiBizTypeCustom');btype=(ci&&ci.value||'').trim();}
if(!btype){showAiBanner('error',t('msimamizi_bidhaa.err_business_type_needed'));return;}
try{
var r=await fetch(API_BASE_URL+'/api/user/profile',{method:'PUT',headers:{'Content-Type':'application/json','Authorization':'Bearer '+token},body:JSON.stringify({business_type:btype,business_description:bdesc})});
var d=await r.json().catch(function(){return{};});
if(r.ok){
showAiBanner('info',t('msimamizi_bidhaa.msg_business_info_saved_ai'));
if(typeof userData!=='undefined'&&userData){try{userData.business_type=btype;userData.business_description=bdesc;localStorage.setItem('userData',JSON.stringify(userData));}catch(_){}}
}else{showAiBanner('error',d.error||t('msimamizi_bidhaa.err_save_business_info'));}
}catch(e){showAiBanner('error',t('msimamizi_bidhaa.err_network_save'));}
}
function checkAiB64(name,b64,cb){
var total=aiImages.reduce(function(s,i){return s+(i.base64?i.base64.length:0);},0)+b64.length;
if(b64.length>AI_MAX_PER_IMAGE_B64){showAiBanner('error',t('msimamizi_bidhaa.err_image_still_large', {name: name}));cb(null);return;}
if(total>AI_MAX_TOTAL_B64){showAiBanner('error',t('msimamizi_bidhaa.err_images_total_large'));cb(null);return;}
cb(b64);
}
function compressAiImage(file,cb){
if(file.size>14*1024*1024){showAiBanner('error',t('msimamizi_bidhaa.err_image_too_large', {filename: file.name}));cb(null);return;}
var reader=new FileReader();
reader.onerror=function(){cb(null);};
reader.onload=function(){
var original=reader.result;
var img=new Image();
img.onerror=function(){
var b64=original.indexOf(',')>=0?original.slice(original.indexOf(',')+1):original;
checkAiB64(file.name,b64,cb);
};
img.onload=function(){
try{
var maxDim=1600;var w=img.naturalWidth,h=img.naturalHeight;
var scale=Math.max(w,h)>maxDim?maxDim/Math.max(w,h):1;
var canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(w*scale));canvas.height=Math.max(1,Math.round(h*scale));
var ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(img,0,0,canvas.width,canvas.height);
var out=canvas.toDataURL('image/jpeg',0.85);
var b64=out.indexOf(',')>=0?out.slice(out.indexOf(',')+1):out;
checkAiB64(file.name,b64,cb);
}catch(e){cb(null);}
};
img.src=original;
};
reader.readAsDataURL(file);
}
function addAiFiles(files){
if(aiProcessing)return;
var list=Array.prototype.slice.call(files||[]);
for(var i=0;i<list.length;i++){
var f=list[i];
var okType=f.type.indexOf('image/')===0||/\.(png|jpe?g|webp|heic|heif|bmp|tiff?)$/i.test(f.name);
if(!okType){showAiBanner('error',t('msimamizi_bidhaa.err_image_type_rejected',{filename:(f.name||'')}));continue;}
if(aiImages.length>=AI_MAX_IMAGES){showAiBanner('error',t('msimamizi_bidhaa.warn_image_count_max', {count: aiImages.length, max: AI_MAX_IMAGES}));break;}
var item={id:'ai'+Date.now()+'_'+i+Math.random().toString(36).slice(2,6),name:f.name||('image'+(i+1)),base64:null,preview:null};
aiImages.push(item);
compressAiImage(f,function(b64){
var ix=aiImages.findIndex(function(x){return x.id===item.id;});
if(ix<0)return;
if(!b64){aiImages.splice(ix,1);refreshAiUploadUi();return;}
aiImages[ix].base64=b64;aiImages[ix].preview='data:image/jpeg;base64,'+b64;refreshAiUploadUi();
});
}
refreshAiUploadUi();
}
function removeAiImage(id){aiImages=aiImages.filter(function(im){return im.id!==id;});refreshAiUploadUi();}
function refreshAiUploadUi(){
var grid=$('aiPreviewGrid');if(!grid)return;
if(aiImages.length===0){grid.innerHTML='';}
else{
grid.innerHTML=aiImages.map(function(im){
var src=im.preview||'';
return '<div class="ai-preview-item"><img src="'+esc(src)+'" alt=""><span class="ai-preview-remove" onclick="removeAiImage(\''+im.id+'\')">✕</span><div class="ai-preview-name">'+esc(im.name)+'</div></div>';
}).join('');
}
var pb=$('aiProcessBtn');
if(pb){pb.disabled=aiImages.length===0||aiProcessing;pb.innerHTML=ic('scan',17)+' '+t('msimamizi_bidhaa.btn_scan_image')+''+(aiImages.length?' ('+aiImages.length+')':'');}
}
function initAiUploadHandlers(){
if(aiUploadHandlersReady)return;
aiUploadHandlersReady=true;
var zone=$('aiUploadZone'),input=$('aiFilesInput');
if(zone)zone.addEventListener('click',function(){if(!aiProcessing)input.click();});
['dragover','dragenter'].forEach(function(ev){zone.addEventListener(ev,function(e){e.preventDefault();zone.classList.add('drag');});});
['dragleave','drop'].forEach(function(ev){zone.addEventListener(ev,function(e){e.preventDefault();zone.classList.remove('drag');});});
zone.addEventListener('drop',function(e){if(e.dataTransfer&&e.dataTransfer.files)addAiFiles(e.dataTransfer.files);});
input.addEventListener('change',function(){addAiFiles(input.files);input.value='';});
}
function captureAiImage(){
if(aiProcessing)return;
var ci=document.createElement('input');ci.type='file';ci.accept='image/*';ci.setAttribute('capture','environment');
ci.onchange=function(){if(ci.files&&ci.files.length)addAiFiles(ci.files);};
ci.click();
}
function aiErrorMessage(data){
if(!data||!data.code)return t('msimamizi_bidhaa.err_scan_failed');
var c=String(data.code).toUpperCase();
if(['GEMINI_RATE_LIMIT','GEMINI_TIMEOUT','GEMINI_API_ERROR','GEMINI_NETWORK','GEMINI_KEY_MISSING','AI_IMPORT_ERROR','UPLOAD_FAILED'].indexOf(c)>=0)return t('msimamizi_bidhaa.err_scan_failed_gemini');
return data.error||t('msimamizi_bidhaa.err_scan_failed');
}
async function startAiProcess(){
if(aiProcessing)return;
var ready=aiImages.filter(function(im){return im.base64;});
if(!ready.length){showAiBanner('error',t('msimamizi_bidhaa.err_pick_image_first'));return;}
if(aiRows.length){
var unverified=aiRows.filter(function(r){return !r.verified;}).length;
if(unverified>0){
var ok=await aiConfirm(t('msimamizi_bidhaa.confirm_rescan'),t('msimamizi_bidhaa.confirm_discard_current_results', {count: unverified}));
if(!ok)return;
}
}
aiProcessing=true;aiRows=[];
$('aiResultsWrap').style.display='none';
$('aiUploadSection').style.display='none';
$('aiProgress').style.display='block';
hideAiBanner();
startAiProgress();
try{
var payload={images:ready.map(function(im){return{name:im.name,base64:im.base64};})};
var r=await fetch(API_BASE_URL+'/api/inventory/ai-import',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+token},body:JSON.stringify(payload)});
var data=await r.json().catch(function(){return{};});
$('aiProgress').style.display='none';
$('aiUploadSection').style.display='block';
if(r.ok&&data&&Array.isArray(data.items)&&data.items.length>0){
aiRows=data.items.map(function(it){return Object.assign({verified:false,verifiedResult:null},it);});
$('aiResultsWrap').style.display='block';
renderAiResults();
showAiBanner('info',t('msimamizi_bidhaa.hint_ai_results_present'));
}else if(r.ok){
showAiBanner('error',t('msimamizi_bidhaa.err_no_products_detected'));
}else{
showAiBanner('error',aiErrorMessage(data));
}
}catch(e){
$('aiProgress').style.display='none';
$('aiUploadSection').style.display='block';
showAiBanner('error',t('msimamizi_bidhaa.err_network_try_again'));
}finally{
stopAiProgress();aiProcessing=false;refreshAiUploadUi();
}
}
function updateAiSummary(){
var total=aiRows.length;
var existing=aiRows.filter(function(r){return r.status==='EXISTING';}).length;
var review=aiRows.filter(function(r){return r.needsReview;}).length;
var verified=aiRows.filter(function(r){return r.verified;}).length;
var s=$('aiResultsSummary');if(!s)return;
s.innerHTML='<span class="ai-summary-chip">'+t('msimamizi_bidhaa.summary_total_count',{count:total})+'</span><span class="ai-summary-chip existing">'+t('msimamizi_bidhaa.summary_existing_count',{count:existing})+'</span><span class="ai-summary-chip">'+t('msimamizi_bidhaa.summary_new_count',{count:(total-existing)})+'</span><span class="ai-summary-chip review">'+t('msimamizi_bidhaa.summary_need_check_count',{count:review})+'</span><span class="ai-summary-chip ok">'+t('msimamizi_bidhaa.summary_verified_count',{count:verified})+'</span>';
}
// Keep aiRows in sync while the user edits cells, so verifying one row
// (or any re-render) never wipes unsaved edits in the other rows.
function attachAiTableSync(){
if(aiTableSyncReady)return;var body=$('aiTableBody');if(!body)return;
aiTableSyncReady=true;
body.addEventListener('input',function(e){
var t=e.target;if(!t||!t.id)return;
var m=t.id.match(/^ai(Name|Qty|Buy|Sell)(\d+)$/);if(!m)return;
var row=aiRows[parseInt(m[2],10)];if(!row)return;
var v=t.value;
if(m[1]==='Name')row.name=v;
else if(m[1]==='Qty')row.quantity=v===''?null:v;
else if(m[1]==='Buy')row.buyingPrice=v===''?null:v;
else if(m[1]==='Sell')row.sellingPrice=v===''?null:v;
});
}
function renderAiResults(){
var body=$('aiTableBody');if(!body)return;
updateAiSummary();
attachAiTableSync();
body.innerHTML=aiRows.map(function(row,i){
var stBadge=row.status==='EXISTING'?'<span class="ai-status existing">'+t('msimamizi_bidhaa.th_present')+'</span>':'<span class="ai-status new">'+t('msimamizi_bidhaa.th_new')+'</span>';
var revBadge=row.needsReview?'<span class="ai-status review" style="margin-left:4px">'+t('msimamizi_bidhaa.th_check')+'</span>':'';
var conf=Math.round((row.confidence||0)*100);
var exp=(row.status==='EXISTING'&&row.currentStock!==null&&row.currentStock!==undefined)?('<div class="ai-conf-row">'+t('msimamizi_bidhaa.msg_current_stock',{stock:esc(row.currentStock)})+'</div>'):'';
var src=row.sourceImage?'<div class="ai-conf-row">'+t('msimamizi_bidhaa.photo')+': '+esc(row.sourceImage)+'</div>':'';
var warnings=(row.warnings||[]).map(function(w){return '<div class="ai-warn">'+esc(w)+'</div>';}).join('');
var bp=(row.buyingPrice===null||row.buyingPrice===undefined)?'':esc(row.buyingPrice);
var sp=(row.sellingPrice===null||row.sellingPrice===undefined)?'':esc(row.sellingPrice);
var qty=(row.quantity===null||row.quantity===undefined)?'':esc(row.quantity);
var action;
if(row.verified){action='<span class="ai-verified-tag">'+t('msimamizi_bidhaa.status_verified')+'</span>';}
else{action='<button type="button" class="ai-verify-btn" id="aiVerify'+i+'" onclick="verifyAiRow('+i+')">'+t('msimamizi_bidhaa.btn_confirm')+'</button>';}
return '<tr>'+
'<td><input type="text" value="'+esc(row.name)+'" id="aiName'+i+'"></td>'+
'<td style="min-width:82px"><input type="number" min="0" step="1" value="'+qty+'" id="aiQty'+i+'"></td>'+
'<td style="min-width:110px"><input type="number" min="0" value="'+bp+'" id="aiBuy'+i+'"></td>'+
'<td style="min-width:110px"><input type="number" min="0" value="'+sp+'" id="aiSell'+i+'"></td>'+
'<td>'+stBadge+revBadge+'<div class="ai-conf-row">'+t('msimamizi_bidhaa.msg_confidence',{confidence:conf})+'</div>'+exp+'</td>'+
'<td style="max-width:220px">'+warnings+src+'</td>'+
'<td style="width:140px">'+action+'</td>'+
'</tr>';
}).join('');
}
async function verifyAiRow(i,fromBatch){
// aiBatchBusy is set by verifyAllAiRows, which then calls this function for each
// row. Without the fromBatch flag the batch blocks the very rows it is meant to
// submit, so nothing would ever be saved.
if(aiProcessing)return;
if(aiVerifyBusy&&!fromBatch)return;
if(aiBatchBusy&&!fromBatch)return;
var row=aiRows[i];
if(!row||row.verified)return;
var nameEl=$('aiName'+i),qtyEl=$('aiQty'+i),buyEl=$('aiBuy'+i),sellEl=$('aiSell'+i);
var name=(nameEl?(nameEl.value||''):'').trim();
var qty=parseInt((qtyEl?(qtyEl.value||''):''),10);
if(!name){showAiBanner('error',t('msimamizi_bidhaa.err_name_empty'));return;}
if(!Number.isInteger(qty)||qty<=0){showAiBanner('error',t('msimamizi_bidhaa.err_quantity_invalid'));return;}
var buyRaw=buyEl?(buyEl.value||''):'';
var sellRaw=sellEl?(sellEl.value||''):'';
var buy=buyRaw===''?null:Number(buyRaw);
var sell=sellRaw===''?null:Number(sellRaw);
if(sell===null||!Number.isFinite(sell)||sell<=0){showAiBanner('error',t('msimamizi_bidhaa.err_selling_price_first'));return;}
if(buy!==null&&(!Number.isFinite(buy)||buy<0)){showAiBanner('error',t('msimamizi_bidhaa.err_buying_price_negative'));return;}
var payloadItem={
verificationToken:row.verificationToken,
action:row.status,
name:name,
category:row.category||'',
description:row.description||'',
quantity:qty,
buyingPrice:buy,
sellingPrice:sell,
price:sell,
matchedExistingItemId:row.matchedExistingItemId||null
};
aiVerifyBusy=true;
var allBtns=document.querySelectorAll('.ai-verify-btn');
allBtns.forEach(function(b){b.disabled=true;});
var btn=$('aiVerify'+i);
if(btn){btn.innerHTML='...';}
try{
var r=await fetch(API_BASE_URL+'/api/inventory/ai-import/verify',{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+token},body:JSON.stringify({item:payloadItem})});
var data=await r.json().catch(function(){return{};});
if(r.ok){
row.verified=true;row.verifiedResult=data;
// Update only this row's action cell + summary — a full re-render would
// reset edits the user has typed in the other rows.
allBtns.forEach(function(b){b.disabled=false;});
var td=btn?btn.closest('td'):null;
if(td)td.innerHTML='<span class="ai-verified-tag">'+t('msimamizi_bidhaa.status_verified')+'</span>';
updateAiSummary();
await fetchExistingProducts();
showAiBanner('info',(data.message||t('msimamizi_bidhaa.msg_product_verified')));
}else{
allBtns.forEach(function(b){b.disabled=false;});
if(btn){btn.innerHTML=t('msimamizi_bidhaa.btn_confirm');}
var msg=data.error||t('msimamizi_bidhaa.err_verify_failed');
if(data.code==='PRODUCT_EXISTS'){
// The product is already in the business — treat the row as saved
// instead of showing an error the user cannot act on.
row.verified=true;row.verifiedResult={alreadyVerified:true};
allBtns.forEach(function(b){b.disabled=false;});
var td2=btn?btn.closest('td'):null;
if(td2)td2.innerHTML='<span class="ai-verified-tag">'+t('msimamizi_bidhaa.status_verified')+'</span>';
updateAiSummary();
await fetchExistingProducts();
showAiBanner('info',t('msimamizi_bidhaa.err_duplicate_product', {name: name}));
return;
}
showAiBanner('error',msg);
}
}catch(e){
allBtns.forEach(function(b){b.disabled=false;});
if(btn){btn.innerHTML=t('msimamizi_bidhaa.btn_confirm');}
showAiBanner('error',t('msimamizi_bidhaa.err_network_verify'));
}finally{
aiVerifyBusy=false;
}
}
// "Thibitisha Zote": confirms first, then verifies every unverified row
// one-by-one using the same validation and per-row logic as a single
// "Thibitisha" click. In-row edits are already synced to aiRows state.
async function verifyAllAiRows(){
if(aiProcessing){showAiBanner('info',t('msimamizi_bidhaa.err_scan_image_first'));return;}
if(aiVerifyBusy){showAiBanner('info',t('msimamizi_bidhaa.warn_scan_in_progress'));return;}
if(aiBatchBusy){showAiBanner('info',t('msimamizi_bidhaa.warn_products_saving'));return;}
var pending=aiRows.filter(function(r){return !r.verified;});
if(!pending.length){showAiBanner('info',t('msimamizi_bidhaa.msg_all_verified'));return;}

// Validate every pending row BEFORE asking for confirmation so the user
// fixes problems first instead of getting a wall of partial errors.
var problems=[];
for(var i=0;i<aiRows.length;i++){
var row=aiRows[i];if(row.verified)continue;
var nameEl=$('aiName'+i),qtyEl=$('aiQty'+i),sellEl=$('aiSell'+i),buyEl=$('aiBuy'+i);
var name=(nameEl?(nameEl.value||''):'').trim();
var qtyRaw=qtyEl?(qtyEl.value||''):'';
var qty=parseInt(qtyRaw,10);
var sellRaw=sellEl?(sellEl.value||''):'';
var sell=sellRaw===''?null:Number(sellRaw);
if(!name){problems.push(t('msimamizi_bidhaa.err_row_name_empty', {line: (i+1)}));continue;}
if(!Number.isInteger(qty)||qty<=0){problems.push(t('msimamizi_bidhaa.err_row_quantity_invalid', {line: (i+1)}));continue;}
if(sell===null||!Number.isFinite(sell)||sell<=0){problems.push(t('msimamizi_bidhaa.err_row_selling_price', {line: (i+1)}));continue;}
var buyRaw=buyEl?(buyEl.value||''):'';
var buy=buyRaw===''?null:Number(buyRaw);
if(buy!==null&&(!Number.isFinite(buy)||buy<0)){problems.push(t('msimamizi_bidhaa.err_row_buying_price', {line: (i+1)}));}
}
if(problems.length){showAiBanner('error',t('msimamizi_bidhaa.err_fix_before_confirm',{problems:problems.join('\n')}));return;}

var ok=await aiConfirm(t('msimamizi_bidhaa.confirm_all_products'),t('msimamizi_bidhaa.msg_bulk_saved_once',{count:pending.length}));
if(!ok)return;

aiBatchBusy=true;
var statusEl=$('aiVerifyAllStatus');
var allBtn=$('aiVerifyAllBtn');
var allBtnHtml=allBtn?allBtn.innerHTML:'';
var saved=0,failed=0;
try{
for(var j=0;j<aiRows.length;j++){
if(aiRows[j].verified)continue;
if(statusEl)statusEl.textContent=t('msimamizi_bidhaa.status_saving_progress', {current: (saved+failed+1), total: pending.length});
// verifyAiRow re-enables every .ai-verify-btn, including this one, so the
// disabled + spinner state has to be reapplied on each pass.
if(allBtn){allBtn.disabled=true;allBtn.innerHTML=ic('refresh',14)+' '+t('msimamizi_bidhaa.status_saving_progress',{current:(saved+failed+1),total:pending.length});}
var before=aiRows.filter(function(r){return r.verified;}).length;
try{
await verifyAiRow(j,true);
}catch(e){}
var after=aiRows.filter(function(r){return r.verified;}).length;
if(after>before)saved++;else failed++;
}
}finally{
// Must always run: if the loop throws, a stuck aiBatchBusy would silently
// disable both this button and every single-row "Thibitisha" button.
aiBatchBusy=false;
if(allBtn){allBtn.disabled=false;allBtn.innerHTML=allBtnHtml;}
}
if(statusEl)statusEl.textContent='';
if(failed===0){
showAiBanner('info',t('msimamizi_bidhaa.msg_all_saved', {count: saved}));
}else{
showAiBanner('error',t('msimamizi_bidhaa.msg_partial_save',{saved:saved,failed:failed}));
}
}
function resetAiSession(){
if(aiProcessing)return;
aiRows=[];aiImages=[];
var uw=$('aiResultsWrap');if(uw)uw.style.display='none';
hideAiBanner();
refreshAiUploadUi();
}
function openAiImportModal(){
var m=$('aiImportModal');if(m)m.classList.add('show');
// Clear any lock left behind by an interrupted run, otherwise every verify
// button in the modal would be silently inert.
aiBatchBusy=false;aiVerifyBusy=false;
initAiUploadHandlers();
populateAiBizTypes();
loadAiBusinessProfile();
refreshAiUploadUi();
var uw=$('aiResultsWrap');if(uw)uw.style.display=aiRows.length?'block':'none';
hideAiBanner();
}
function closeAiImportModal(){
var m=$('aiImportModal');if(m)m.classList.remove('show');
stopAiProgress();
aiBatchBusy=false;aiVerifyBusy=false;
}
window.openAiImportModal=openAiImportModal;
window.closeAiImportModal=closeAiImportModal;
window.saveAiBusinessProfile=saveAiBusinessProfile;
window.startAiProcess=startAiProcess;
window.captureAiImage=captureAiImage;
window.removeAiImage=removeAiImage;
window.verifyAiRow=verifyAiRow;
window.verifyAllAiRows=verifyAllAiRows;
window.resetAiSession=resetAiSession;
})();
</script>
</body>
</html>
@endverbatim