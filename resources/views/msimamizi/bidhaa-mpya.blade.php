@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Bidhaa Mpya - Dukamkononi Msimamizi</title>
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
</style>
</head>
@endverbatim
@include('partials.photo-viewer')
@verbatim
<body>
<div class="mobile-menu-toggle" id="mobileMenuToggle"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2"><path d="M3 12H21M3 6H21M3 18H21"/></svg></div>
<div class="msimamizi-layout">
<aside class="sidebar" id="sidebar">
<div class="sidebar-header"><div class="logo-area"><div class="logo-icon">D</div><div class="logo-text"><h2>DukaMkononi</h2><p>Msimamizi Portal</p></div></div></div>
<div class="nav-items"><a href="index" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-house"></i></div><span class="nav-label">Nyumbani</span></a><a href="ripoti" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-chart-simple"></i></div><span class="nav-label">Ripoti</span></a><a href="preview" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-calendar-days"></i></div><span class="nav-label">Rejea</span></a><a href="bidhaa-mpya" class="nav-item active"><div class="nav-icon"><i class="fa-solid fa-circle-plus"></i></div><span class="nav-label">Bidhaa Mpya</span></a><a href="tangaza" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-bullhorn"></i></div><span class="nav-label">Tangaza</span></a></div>
<div class="sidebar-footer"><div class="user-info"><div class="user-avatar" id="userAvatar">M</div><div class="user-details"><div class="user-name" id="userName">Msimamizi</div><div class="user-role">Msimamizi</div></div></div><div class="logout-btn" id="logoutBtn"><span><i class="fa-solid fa-arrow-right-from-bracket"></i></span><span>Ondoka</span></div></div>
</aside>
<main class="main-content"><div class="bidhaa-container" id="bidhaaContainer"><div style="text-align:center;padding:60px;">Loading...</div></div></main>
</div>
<div id="productModal" class="modal-overlay"><div class="modal-content"><div class="modal-header"><div class="modal-title">Chagua Bidhaa Ilipo</div><div class="modal-close" onclick="closeProductModal()">✖</div></div><input type="text" class="modal-search" id="productSearch" placeholder="Tafuta bidhaa..." oninput="filterProducts()"><div class="modal-body" id="productModalBody"><div class="no-products">Inapakia bidhaa...</div></div></div></div>
<script>
const API_BASE_URL='';const categories=['Vyakula','Vinywaji','Matunda','Mboga','Nguo','Viatu','Vifaa vya Nyumbani','Vifaa vya Umeme','Simu na Vifaa','Matibabu','Vifaa vya Usafi','Engine','Sehemu za Gari','Vifaa vya Ujenzi','Vifaa vya Kilimo','Vifaa vya Ofisi','Vitabu na Vifaa vya Kuelimisha','Bidhaa za Watoto','Bidhaa za Urembo','Bidhaa za Kijamii','Michezo na Burudani','Wanyama wa Kufugwa','Vifaa vya Kusafiri','Vifaa vya Teknolojia','Vifaa vya Kudumisha Usalama','Vifaa vya Redio na TV','Vifaa vya Muziki','Vifaa vya Pikipiki','Vifaa vya Baiskeli','Vifaa vya Ushonaji','Vifaa vya Uchoraji','Vifaa vya Ufundi','Vifaa vya Umeme wa Nyumbani','Vifaa vya Jikoni','Vifaa vya Kupimia','Vifaa vya Kukarabati','Vifaa vya Usalama','Vifaa vya Biashara','Vifaa vya Hotelini','Vifaa vya Huduma','Vifaa vya Viwanda','Nyingine'];
const quickFillData={'Vyakula':{name:'Mchele Super',price:'2500',expected_selling_price:'3000',stock:'50'},'Vinywaji':{name:'Maji ya Kunywa',price:'500',expected_selling_price:'700',stock:'100'},'Matunda':{name:'Maembe Dodo',price:'800',expected_selling_price:'1000',stock:'30'},'Mboga':{name:'Nyanya Fresh',price:'1200',expected_selling_price:'1500',stock:'25'},'Nguo':{name:'T-Shirt Rangi',price:'8000',expected_selling_price:'10000',stock:'15'},'Viatu':{name:'Viatu vya Kawaida',price:'25000',expected_selling_price:'30000',stock:'10'}};
let userData=null,token=null,loading=false,existingProducts=[],selectedProduct=null,isOwnerOfSelected=false,modalMode='add',formData={name:'',category:'',price:'',expected_selling_price:'',stock:''},showCustomCategory=false,customCategory='';
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
function showAlert(t,m,ok){const o=document.createElement('div');o.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:2000;';const b=document.createElement('div');b.style.cssText='background:#fff;border-radius:28px;width:85%;max-width:340px;padding:24px 20px;text-align:center;';b.innerHTML='<div style="font-size:20px;font-weight:800;margin-bottom:12px;">'+escapeHtml(t)+'</div><div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;white-space:pre-line;">'+escapeHtml(m)+'</div><div style="background:#e74c3c;padding:12px;border-radius:40px;color:#fff;font-weight:700;cursor:pointer;">Sawa</div>';b.querySelector('div:last-child').onclick=()=>{o.remove();if(ok)ok();};o.appendChild(b);document.body.appendChild(o);}
function showConfirm(t,m,fn){const o=document.createElement('div');o.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:2000;';const b=document.createElement('div');b.style.cssText='background:#fff;border-radius:28px;width:85%;max-width:340px;padding:24px 20px;text-align:center;';b.innerHTML='<div style="font-size:18px;font-weight:800;margin-bottom:12px;">'+escapeHtml(t)+'</div><div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;white-space:pre-line;">'+escapeHtml(m)+'</div><div style="display:flex;gap:12px;"><div id="cNo" style="flex:1;background:#95a5a6;padding:12px;border-radius:40px;color:#fff;cursor:pointer;">Ghairi</div><div id="cYes" style="flex:1;background:#e74c3c;padding:12px;border-radius:40px;color:#fff;cursor:pointer;">Thibitisha</div></div>';o.appendChild(b);document.body.appendChild(o);document.getElementById('cYes').onclick=()=>{o.remove();fn();};document.getElementById('cNo').onclick=()=>o.remove();}
function loadUserData(){token=localStorage.getItem('userToken');const u=localStorage.getItem('userData');if(!token||!u){showAlert('Hitilafu','Tafadhali ingia tena',()=>window.location.href='../login?role=msimamizi');return false;}userData=JSON.parse(u);const n=userData.full_name||userData.email?.split('@')[0]||'Msimamizi';document.getElementById('userName').innerHTML=escapeHtml(n);const av=document.getElementById('userAvatar');if(userData.business_logo_url){av.classList.add('js-avatar-view');av.setAttribute('data-full',userData.business_logo_url);av.setAttribute('data-name',n);av.innerHTML='<img src="'+escapeHtml(userData.business_logo_url)+'" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="Picha">';}else av.innerHTML=n.charAt(0).toUpperCase();return true;}
async function fetchExistingProducts(){try{const r=await fetch(API_BASE_URL+'/api/business/my/all-products',{headers:{'Authorization':'Bearer '+token,'Content-Type':'application/json'}});if(r.ok)existingProducts=await r.json();}catch(e){}}
function openProductModal(){document.getElementById('productModal').classList.add('show');document.getElementById('productSearch').value='';renderProductList();}
function closeProductModal(){document.getElementById('productModal').classList.remove('show');}
function filterProducts(){renderProductList();}
function renderProductList(){const q=(document.getElementById('productSearch')?.value||'').toLowerCase();const f=existingProducts.filter(p=>p.name.toLowerCase().includes(q)||(p.category||'').toLowerCase().includes(q));const b=document.getElementById('productModalBody');if(!f.length){b.innerHTML='<div class="no-products">Hakuna bidhaa zilizopatikana</div>';return;}b.innerHTML=f.map(p=>{const ow=p.seller_id==userData?.id;return '<div class="product-item '+(ow?'owner':'')+'" onclick="selectExistingProduct('+jsq(p.id)+')"><div class="product-item-info"><div class="product-item-name">'+escapeHtml(p.name)+(ow?'<span class="owner-badge">Yako</span>':'')+'</div><div class="product-item-category">'+escapeHtml(p.category||'N/A')+'</div><div class="product-item-details"><span class="product-item-stock">Hisa: '+(p.stock||0)+'</span><span class="product-item-price">Kununua: '+formatCurrency(p.price)+'</span>'+(p.expected_selling_price?'<span style="color:#f39c12">Kuuzia: '+formatCurrency(p.expected_selling_price)+'</span>':'')+'</div></div><div class="product-actions" onclick="event.stopPropagation()"><button class="product-action-btn" onclick="handleAddStock('+jsq(p.id)+')" style="color:#2ecc71">'+ic('plusSm',18)+'</button>'+(ow?'<button class="product-action-btn" onclick="handleEditProduct('+jsq(p.id)+')" style="color:#3498db">'+ic('edit',17)+'</button><button class="product-action-btn" onclick="handleDeleteProduct('+jsq(p.id)+','+jsq(p.name)+')" style="color:#e74c3c">'+ic('trash',17)+'</button>':'')+'</div></div>';}).join('');}
function selectExistingProduct(id){const p=existingProducts.find(x=>x.id===id);if(!p)return;selectedProduct=p;isOwnerOfSelected=p.seller_id==userData?.id;modalMode='add';formData={name:p.name,category:p.category||'',price:p.price?.toString()||'',expected_selling_price:p.expected_selling_price?.toString()||'',stock:''};closeProductModal();renderUI();}
function handleAddStock(id){const p=existingProducts.find(x=>x.id===id);if(!p)return;selectedProduct=p;isOwnerOfSelected=p.seller_id==userData?.id;modalMode='add';formData={name:p.name,category:p.category||'',price:p.price?.toString()||'',expected_selling_price:p.expected_selling_price?.toString()||'',stock:''};closeProductModal();renderUI();}
function handleEditProduct(id){const p=existingProducts.find(x=>x.id===id);if(!p)return;if(p.seller_id!=userData?.id){showAlert('Hitilafu','Huna ruhusa');return;}selectedProduct=p;isOwnerOfSelected=true;modalMode='edit';formData={name:p.name,category:p.category||'',price:p.price?.toString()||'',expected_selling_price:p.expected_selling_price?.toString()||'',stock:p.stock?.toString()||''};closeProductModal();renderUI();}
function handleDeleteProduct(id,nm){const p=existingProducts.find(x=>x.id===id);if(!p||p.seller_id!=userData?.id){showAlert('Hitilafu','Huna ruhusa');return;}showConfirm('Futa Bidhaa','Una uhakika unataka kufuta "'+nm+'"?',async()=>{try{const r=await fetch(API_BASE_URL+'/api/products/'+id,{method:'DELETE',headers:{'Authorization':'Bearer '+token}});if(r.ok){showAlert('Mafanikio!','Bidhaa imefutwa');await fetchExistingProducts();resetForm();}else showAlert('Hitilafu','Imeshindwa');}catch(e){showAlert('Hitilafu',e.message);}});}
function clearSelectedProduct(){selectedProduct=null;isOwnerOfSelected=false;modalMode='add';renderUI();}
function isPriceEditable(){if(!selectedProduct)return true;if(modalMode==='edit'&&isOwnerOfSelected)return true;return false;}
function getPriceDiff(){const cp=parseFloat(formData.price||'0'),ep=parseFloat(formData.expected_selling_price||'0');if(cp>0&&ep>0){const d=ep-cp,p=(d/cp)*100;return{diff:d,pct:p,formatted:(d>=0?'+':'')+'TZS '+Math.abs(d).toLocaleString(),pctFormatted:(p>=0?'+':'')+p.toFixed(2)+'%'};}return null;}
function validateForm(){const e=[];if(!formData.name.trim())e.push('Jina linahitajika');if(!formData.category)e.push('Kategoria inahitajika');if(!formData.price||parseFloat(formData.price)<=0)e.push('Bei ya kununua inahitajika');if(!formData.expected_selling_price||parseFloat(formData.expected_selling_price)<=0)e.push('Bei ya kuuzia inahitajika');if(!formData.stock||parseInt(formData.stock)<0)e.push('Idadi inahitajika');if(parseFloat(formData.expected_selling_price)<parseFloat(formData.price))e.push('Bei ya kuuzia ndogo kuliko bei ya kununua');return e;}
async function handleSubmit(){const errs=validateForm();if(errs.length){showAlert('Hitilafu',errs.join('\n'));return;}if(loading)return;loading=true;renderUI();try{const pd={name:formData.name.trim(),category:formData.category,price:parseFloat(formData.price),stock:parseInt(formData.stock),expected_selling_price:parseFloat(formData.expected_selling_price)};const h={'Content-Type':'application/json','Authorization':'Bearer '+token};if(selectedProduct&&modalMode==='add'){const qty=parseInt(formData.stock||'0');if(qty<=0){showAlert('Hitilafu','Weka idadi');loading=false;renderUI();return;}const ns=parseInt(selectedProduct.stock||'0')+qty;const r=await fetch(API_BASE_URL+'/api/products/'+selectedProduct.id,{method:'PUT',headers:h,body:JSON.stringify({name:selectedProduct.name,category:selectedProduct.category,stock:ns})});if(r.ok){showAlert('Mafanikio!','Hisa imeongezwa: '+selectedProduct.name+'\nJumla: '+ns);await fetchExistingProducts();resetForm();}else showAlert('Hitilafu','Imeshindwa');loading=false;renderUI();return;}if(selectedProduct&&modalMode==='edit'){const r=await fetch(API_BASE_URL+'/api/products/'+selectedProduct.id,{method:'PUT',headers:h,body:JSON.stringify(pd)});if(r.ok){showAlert('Mafanikio!','Bidhaa imesasishwa');await fetchExistingProducts();resetForm();}else showAlert('Hitilafu','Imeshindwa');loading=false;renderUI();return;}const ex=existingProducts.find(p=>p.name.toLowerCase()===formData.name.toLowerCase().trim());if(ex&&ex.seller_id==userData?.id){const ns=parseInt(ex.stock||'0')+parseInt(formData.stock);const r=await fetch(API_BASE_URL+'/api/products/'+ex.id,{method:'PUT',headers:h,body:JSON.stringify({...pd,stock:ns})});if(r.ok){showAlert('Mafanikio!','Hisa jumla: '+ns);await fetchExistingProducts();resetForm();}else showAlert('Hitilafu','Imeshindwa');loading=false;renderUI();return;}const r=await fetch(API_BASE_URL+'/api/products',{method:'POST',headers:h,body:JSON.stringify(pd)});const txt=await r.text();if(r.ok){showAlert('Mafanikio!','Bidhaa imeongezwa\nKununua: '+formatCurrency(formData.price)+'\nKuuzia: '+formatCurrency(formData.expected_selling_price)+'\nHisa: '+formData.stock);await fetchExistingProducts();resetForm();}else if(r.status===403){showAlert('Bidhaa Ipopo','Bidhaa "'+formData.name+'" tayariipo.\nKuongeza kama mpya?',async()=>{const r2=await fetch(API_BASE_URL+'/api/products',{method:'POST',headers:h,body:JSON.stringify(pd)});if(r2.ok){showAlert('Mafanikio!','Bidhaa mpya imeongezwa');await fetchExistingProducts();resetForm();}else showAlert('Hitilafu','Imeshindwa');});}else showAlert('Hitilafu',txt||'Imeshindwa');}catch(e){showAlert('Hitilafu',e.message);}loading=false;renderUI();}
function resetForm(){formData={name:'',category:'',price:'',expected_selling_price:'',stock:''};customCategory='';showCustomCategory=false;selectedProduct=null;isOwnerOfSelected=false;modalMode='add';renderUI();}
function renderUI(){const c=document.getElementById('bidhaaContainer');const pe=isPriceEditable();const pd=getPriceDiff();const iv=formData.name&&formData.category&&formData.price&&formData.expected_selling_price&&formData.stock;const sc=modalMode==='edit'?'update':(selectedProduct&&modalMode==='add'?'addstock':'add');const sl=modalMode==='edit'?ic('refresh')+' SASISHA BIDHAA':(selectedProduct&&modalMode==='add'?ic('plus')+' ONGEZA HISA':ic('plus')+' ONGEZA BIDHAA MPYA');const ch=categories.map(cat=>'<div class="category-chip '+(formData.category===cat?'selected':'')+'" data-cat="'+cat+'">'+escapeHtml(cat)+'</div>').join('');c.innerHTML='<div class="form-card"><h2 style="color:#2c3e50;margin-bottom:4px">'+(modalMode==='edit'?ic('edit',20)+' Hariri Bidhaa':ic('plus',20)+' Ongeza Bidhaa Mpya')+'</h2><p style="color:#7f8c8d;margin-bottom:20px">'+(modalMode==='edit'?'Sasisha taarifa':'Jaza taarifa za bidhaa')+'</p><div class="ai-import-btn" onclick="openAiImportModal()"><span class="card-ic" style="color:#7c5cbf">'+ic('ai',26)+'</span><div style="flex:1"><div style="font-weight:700;color:#2c3e50">Ingiza Kwa Picha (AI)</div><div style="font-size:13px;color:#7f8c8d">Chambua orodha ya bidhaa kutoka kwenye picha na uongeze hisa kwa haraka</div></div><span style="color:#95a5a6;font-size:20px">›</span></div><div class="existing-btn" onclick="openProductModal()"><span class="card-ic" style="color:#28a745">'+ic('folder',26)+'</span><div style="flex:1"><div style="font-weight:700;color:#2c3e50">Bidhaa Zilizopo</div><div style="font-size:13px;color:#7f8c8d">Chagua bidhaa ('+existingProducts.length+' bidhaa)</div></div><span style="color:#95a5a6;font-size:20px">›</span></div>'+(selectedProduct?'<div class="selected-banner '+(isOwnerOfSelected?'':'non-owner')+'"><div class="selected-banner-header"><div class="selected-banner-title">'+(modalMode==='edit'?ic('edit',13)+' Unahariri':ic('box',13)+' Ongeza Hisa')+': '+escapeHtml(selectedProduct.name)+'</div><div class="selected-banner-close" onclick="clearSelectedProduct()">✖</div></div><div style="font-size:12px;color:#7f8c8d">Kategoria: '+escapeHtml(selectedProduct.category||'N/A')+' • Hisa: '+(selectedProduct.stock||0)+' • Bei ya Kununua: '+formatCurrency(selectedProduct.price)+(selectedProduct.expected_selling_price?' • Kuuzia: '+formatCurrency(selectedProduct.expected_selling_price):'')+'</div></div>':'')+'<div class="input-group"><label class="label">Jina la Bidhaa *</label><input type="text" id="fName" placeholder="Weka jina kamili" value="'+escapeHtml(formData.name)+'" '+(selectedProduct&&modalMode==='add'?'disabled':'')+'></div><div class="input-group"><label class="label">Kategoria *</label><div class="categories-scroll">'+ch+'</div><div id="customCatWrap" style="margin-top:12px;padding:12px;background:#e8f4fd;border-radius:14px;border-left:4px solid #3498db;display:'+(showCustomCategory?'block':'none')+'"><input type="text" id="fCustomCat" placeholder="Andika kategoria yako" value="'+escapeHtml(customCategory)+'"></div></div><div class="input-group"><label class="label">Bei ya Kununua *</label><div class="price-container"><input type="number" id="fPrice" placeholder="Bei ya kununua" value="'+formData.price+'" '+(!pe?'disabled':'')+'><div class="price-preview" id="pp1" style="display:'+(formData.price?'block':'none')+'"><small>TZS</small> <strong id="ppv1">'+(formData.price?parseInt(formData.price).toLocaleString():'')+'</strong></div></div>'+(!pe&&selectedProduct?'<div style="font-size:12px;color:#e74c3c;margin-top:4px;font-style:italic">Bei haibadilishwi unapoongeza hisa</div>':'')+'</div><div class="input-group"><label class="label">Bei ya Kuuzia *</label><div class="price-container"><input type="number" id="fExpPrice" placeholder="Bei unayotaka kauzia" value="'+formData.expected_selling_price+'" '+(!pe?'disabled':'')+'><div class="price-preview" id="pp2" style="display:'+(formData.expected_selling_price?'block':'none')+'"><small>TZS</small> <strong id="ppv2">'+(formData.expected_selling_price?parseInt(formData.expected_selling_price).toLocaleString():'')+'</strong></div></div></div>'+(pd?'<div class="price-diff '+(pd.diff>=0?'positive':'negative')+'"><div style="font-weight:700;color:#2c3e50">'+(pd.diff>=0?'Faida':'Hasara')+'</div><div class="price-diff-row"><span>Faida/bidhaa:</span><strong style="color:'+(pd.diff>=0?'#27ae60':'#e74c3c')+'">'+pd.formatted+'</strong></div><div class="price-diff-row"><span>Asilimia:</span><strong style="color:'+(pd.diff>=0?'#27ae60':'#e74c3c')+'">'+pd.pctFormatted+'</strong></div></div>':'')+'<div class="input-group"><label class="label">'+(modalMode==='edit'?'Idadi Mpya':(selectedProduct&&modalMode==='add'?'Idadi ya Kuongeza':'Idadi ya Bidhaa'))+' *</label><input type="number" id="fStock" placeholder="'+(selectedProduct&&modalMode==='add'?'Weka idadi ya kuongeza':'Weka idadi')+'" value="'+formData.stock+'">'+(selectedProduct&&modalMode==='add'?'<div style="font-size:13px;color:#27ae60;margin-top:6px">Sasa: '+(selectedProduct.stock||0)+' → Jumla: '+(parseInt(selectedProduct.stock||0)+parseInt(formData.stock||0))+'</div>':'')+'</div><button id="submitBtn" class="submit-btn '+sc+' '+(!iv?'disabled':'')+'" '+(!iv?'disabled':'')+'>'+(loading?'<div class="loading-spinner"></div>':sl)+'</button></div><div id="previewArea"></div>';
document.getElementById('submitBtn')?.addEventListener('click',handleSubmit);document.getElementById('fName')?.addEventListener('input',e=>{formData.name=e.target.value;updateDynamic();});document.getElementById('fPrice')?.addEventListener('input',e=>{formData.price=e.target.value.replace(/[^0-9]/g,'');updateDynamic();});document.getElementById('fExpPrice')?.addEventListener('input',e=>{formData.expected_selling_price=e.target.value.replace(/[^0-9]/g,'');updateDynamic();});document.getElementById('fStock')?.addEventListener('input',e=>{formData.stock=e.target.value.replace(/[^0-9]/g,'');updateDynamic();});document.getElementById('fCustomCat')?.addEventListener('input',e=>{customCategory=e.target.value;formData.category=customCategory;updateDynamic();});document.querySelectorAll('.category-chip').forEach(el=>{el.addEventListener('click',()=>{const cat=el.getAttribute('data-cat');if(cat==='Nyingine'){showCustomCategory=true;formData.category='';document.getElementById('customCatWrap').style.display='block';}else{showCustomCategory=false;formData.category=cat;document.getElementById('customCatWrap').style.display='none';const q=quickFillData[cat];if(q&&!formData.name){formData.name=q.name;formData.price=q.price;formData.expected_selling_price=q.expected_selling_price;formData.stock=q.stock;const fn=document.getElementById('fName'),fp=document.getElementById('fPrice'),fe=document.getElementById('fExpPrice'),fs=document.getElementById('fStock');if(fn)fn.value=q.name;if(fp)fp.value=q.price;if(fe)fe.value=q.expected_selling_price;if(fs)fs.value=q.stock;}}updateDynamic();});});updateDynamic();}
function updateDynamic(){document.querySelectorAll('.category-chip').forEach(el=>{el.classList.toggle('selected',formData.category===el.getAttribute('data-cat'));});const p1=document.getElementById('pp1'),v1=document.getElementById('ppv1');if(p1&&v1){p1.style.display=formData.price?'block':'none';v1.textContent=formData.price?parseInt(formData.price).toLocaleString():'';}const p2=document.getElementById('pp2'),v2=document.getElementById('ppv2');if(p2&&v2){p2.style.display=formData.expected_selling_price?'block':'none';v2.textContent=formData.expected_selling_price?parseInt(formData.expected_selling_price).toLocaleString():'';}const sb=document.getElementById('submitBtn');if(sb){sb.disabled=!iv;sb.classList.toggle('disabled',!iv);}const pa=document.getElementById('previewArea');if(pa&&(formData.name||formData.price||formData.expected_selling_price)){const d=getPriceDiff();pa.innerHTML='<div class="preview-card"><div class="preview-inner"><div style="font-weight:700;margin-bottom:12px">Hakiki:</div><div><strong>'+escapeHtml(formData.name||'Jina')+'</strong></div><div style="font-size:13px;color:#7f8c8d">Kategoria: '+escapeHtml(formData.category||'N/A')+'</div><div style="display:flex;justify-content:space-between;margin-top:10px"><div>Bei ya Kununua: <strong style="color:#3498db">'+formatCurrency(formData.price)+'</strong></div><div>Kuuzia: <strong style="color:#27ae60">'+formatCurrency(formData.expected_selling_price)+'</strong></div></div>'+(d?'<div style="margin-top:8px;font-size:13px">Faida: <strong style="color:'+(d.diff>=0?'#27ae60':'#e74c3c')+'">'+d.formatted+' ('+d.pctFormatted+')</strong></div>':'')+'<div style="margin-top:8px">Idadi: '+(formData.stock||0)+'</div></div></div>';}else if(pa)pa.innerHTML='';}
function setupSidebar(){document.getElementById('mobileMenuToggle')?.addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('open'));document.getElementById('logoutBtn')?.addEventListener('click',()=>{localStorage.clear();window.location.href='../login?role=msimamizi';});}
async function init(){setupSidebar();if(!loadUserData())return;await fetchExistingProducts();renderUI();}
init();
</script>
<div id="aiImportModal" class="ai-modal-overlay">
<div class="ai-modal-content">
<div class="ai-modal-header"><div class="ai-modal-title">Ingiza Kwa Picha (AI)</div><div class="ai-modal-close" onclick="closeAiImportModal()">✖</div></div>
<div class="ai-modal-body">
<div id="aiBanner" class="ai-banner"></div>
<div class="ai-biz-card">
<div class="ai-biz-title">Taarifa za Biashara (husaidia AI kufahamu bidhaa zako)</div>
<div class="ai-biz-grid">
<div class="ai-biz-field"><label class="ai-biz-label">Aina ya Biashara</label><select id="aiBizType"></select></div>
<div class="ai-biz-field" id="aiBizTypeCustomWrap" style="display:none;"><label class="ai-biz-label">Andika Aina Yako ya Biashara</label><input type="text" id="aiBizTypeCustom" placeholder="Mfano: Spea za Pikipiki"></div>
<div class="ai-biz-field"><label class="ai-biz-label">Maelezo mafupi ya Biashara</label><input type="text" id="aiBizDesc" placeholder="Mfano: Tunauza sehemu za magari ya Toyota na Nissan..."></div>
</div>
<button type="button" class="ai-biz-save" onclick="saveAiBusinessProfile()">Hifadhi Taarifa</button>
</div>
<div id="aiUploadSection">
<div id="aiUploadZone" class="ai-upload-zone">
<div class="ai-uz-icon" style="display:flex;justify-content:center;color:#7c5cbf;"><i class="fa-solid fa-camera" style="font-size:38px;"></i></div>
<div class="ai-uz-main">Chagua au piga picha ya orodha ya bidhaa</div>
<div class="ai-uz-sub">Unaweza kuchagua picha nyingi (hadi 6). Picha zenye mwanga mzuri huwa bora zaidi.<br>Picha hazichakatwi mpaka ubonyeze kitufe cha "Chambua Picha".</div>
</div>
<button type="button" class="ai-btn ai-btn-camera" onclick="captureAiImage()"><i class="fa-solid fa-camera" style="font-size:17px;"></i> Piga Picha Sasa (Kamera)</button>
<input type="file" id="aiFilesInput" accept="image/*" multiple style="display:none">
<div id="aiPreviewGrid" class="ai-preview-grid"></div>
<div class="ai-controls"><button type="button" class="ai-btn ai-btn-process" id="aiProcessBtn" onclick="startAiProcess()"><i class="fa-solid fa-barcode" style="font-size:17px;"></i> Chambua Picha</button></div>
</div>
<div id="aiProgress" class="ai-progress" style="display:none">
<div class="ai-dots"><span></span><span></span><span></span><span></span></div>
<div id="aiProgressText" class="ai-progress-text">Inatayarisha picha...</div>
</div>
<div id="aiResultsWrap" style="display:none">
<div class="ai-results-summary" id="aiResultsSummary"></div>
<div class="ai-table-scroll">
<table class="ai-table">
<thead><tr><th>Jina la Bidhaa</th><th>Idadi</th><th>Bei ya Kununua</th><th>Bei ya Kuuzia</th><th>Hali</th><th>Hakiki</th><th>Uthibitisho</th></tr></thead>
<tbody id="aiTableBody"></tbody>
</table>
</div>
<div style="margin-top:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
<span style="font-size:12px;color:#7f8c8d">Hariri taarifa kwenye meza, kisha bonyeza "Thibitisha" kwa kila bidhaa au "Thibitisha Zote". Bidhaa haziwezi kuongezwa kabla ya uthibitisho wako.</span>
<button type="button" class="ai-reset" onclick="resetAiSession()">Anza Upya</button>
</div>
<div style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
<button type="button" class="ai-verify-btn" id="aiVerifyAllBtn" style="background:#27ae60;padding:12px 22px;font-size:14px" onclick="verifyAllAiRows()">Thibitisha Zote</button>
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
var AI_PROGRESS_MSGS=['Inatayarisha picha...','Inasoma maandishi...','Inasoma orodha ya bidhaa...','Inatuma taarifa kwa Gemini...','Gemini inachambua orodha...','Inalinganisha bidhaa zilizopo...','Inahakiki kurudiwa kwa bidhaa...','Inatayarisha matokeo...','Karibu kuisha...','Tafadhali subiri...'];
var AI_BIZ_TYPES=[['spare_parts','Sehemu za Gari'],['motorcycle_spares','Spea za Pikipiki'],['supermarket','Supermarket'],['pharmacy','Duka la Dawa'],['electronics','Elektroniki'],['clothing','Mavazi'],['hardware','Vifaa Vinene (Hardware)'],['cosmetics','Vipodozi'],['perfume','Manukato'],['restaurant','Mgahawa'],['furniture','Samani'],['stationery','Vifaa vya Ofisi na Shule'],['mobile_accessories','Vifaa vya Simu'],['computer_shop','Duka la Kompyuta'],['phone_shop','Duka la Simu'],['agriculture','Kilimo'],['construction_materials','Vifaa vya Ujenzi'],['beauty_salon','Saluni ya Urembo'],['barbershop','Kinyozi'],['auto_repair','Karakana ya Magari'],['general_retail','Rejareja ya Jumla'],['wholesale','Jumla (Wholesale)'],['other','Nyingine'],['__custom__','Nyingine (andika mwenyewe)']];
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
var idx=0;var el=$('aiProgressText');if(el)el.textContent='Inatayarisha picha...';
aiProgressTimer=setInterval(function(){idx=(idx+1)%AI_PROGRESS_MSGS.length;var e=$('aiProgressText');if(e)e.textContent=AI_PROGRESS_MSGS[idx];},2400);
}
function aiConfirm(title,msg){
return new Promise(function(resolve){
var ov=document.createElement('div');ov.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:3000;';
var box=document.createElement('div');box.style.cssText='background:#fff;border-radius:28px;width:85%;max-width:340px;padding:24px 20px;text-align:center;';
box.innerHTML='<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c3e50;">'+esc(title)+'</div><div style="font-size:14px;color:#5d6d7e;margin-bottom:24px;white-space:pre-line;text-align:left;"></div><div style="display:flex;gap:12px;"><div style="flex:1;background:#95a5a6;padding:12px;border-radius:40px;color:#fff;cursor:pointer;font-weight:700;" class="aiCfmNo">Ghairi</div><div style="flex:1;background:#e74c3c;padding:12px;border-radius:40px;color:#fff;cursor:pointer;font-weight:700;" class="aiCfmYes">Endelea</div></div>';
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
if(sel.options.length===0){sel.innerHTML='<option value="">— Chagua Aina ya Biashara —</option>'+AI_BIZ_TYPES.map(function(t){return '<option value="'+esc(t[0])+'">'+esc(t[1])+'</option>';}).join('');}
sel.onchange=function(){var w=$('aiBizTypeCustomWrap');if(w)w.style.display=sel.value==='__custom__'?'block':'none';};
}
// Sets the business-type select from a stored value; unknown/custom values
// switch the select to "Nyingine (andika mwenyewe)" and fill the free-text box.
function setAiBizTypeValue(v){
var sel=$('aiBizType'),wrap=$('aiBizTypeCustomWrap'),ci=$('aiBizTypeCustom');
if(!sel)return;
if(!v){sel.value='';if(wrap)wrap.style.display='none';return;}
var known=AI_BIZ_TYPES.some(function(t){return t[0]===v;});
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
if(!btype){showAiBanner('error','Tafadhali chagua au andika aina ya biashara.');return;}
try{
var r=await fetch(API_BASE_URL+'/api/user/profile',{method:'PUT',headers:{'Content-Type':'application/json','Authorization':'Bearer '+token},body:JSON.stringify({business_type:btype,business_description:bdesc})});
var d=await r.json().catch(function(){return{};});
if(r.ok){
showAiBanner('info','Taarifa za biashara zimehifadhiwa. AI itazitumia kuchambua picha zako.');
if(typeof userData!=='undefined'&&userData){try{userData.business_type=btype;userData.business_description=bdesc;localStorage.setItem('userData',JSON.stringify(userData));}catch(_){}}
}else{showAiBanner('error',d.error||'Imeshindwa kuhifadhi taarifa za biashara.');}
}catch(e){showAiBanner('error','Hitilafu ya mtandao wakati wa kuhifadhi.');}
}
function checkAiB64(name,b64,cb){
var total=aiImages.reduce(function(s,i){return s+(i.base64?i.base64.length:0);},0)+b64.length;
if(b64.length>AI_MAX_PER_IMAGE_B64){showAiBanner('error','Picha "'+name+'" ni kubwa mno baada ya kubanwa.');cb(null);return;}
if(total>AI_MAX_TOTAL_B64){showAiBanner('error','Picha zote ni kubwa mno kwa jumla. Ondoa baadhi ya picha.');cb(null);return;}
cb(b64);
}
function compressAiImage(file,cb){
if(file.size>14*1024*1024){showAiBanner('error','Picha "'+file.name+'" ni kubwa mno.');cb(null);return;}
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
if(!okType){showAiBanner('error','Picha "'+(f.name||'')+'" imekataliwa — aina ya faili haikubaliki.');continue;}
if(aiImages.length>=AI_MAX_IMAGES){showAiBanner('error','Umechagua picha '+aiImages.length+'. Upeo ni picha '+AI_MAX_IMAGES+'.');break;}
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
if(pb){pb.disabled=aiImages.length===0||aiProcessing;pb.innerHTML=ic('scan',17)+' Chambua Picha'+(aiImages.length?' ('+aiImages.length+')':'');}
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
if(!data||!data.code)return 'Uchambuzi umeshindikana. Tafadhali jaribu tena.';
var c=String(data.code).toUpperCase();
if(['GEMINI_RATE_LIMIT','GEMINI_TIMEOUT','GEMINI_API_ERROR','GEMINI_NETWORK','GEMINI_KEY_MISSING','AI_IMPORT_ERROR','UPLOAD_FAILED'].indexOf(c)>=0)return 'Uchambuzi umeshindikana (Gemini ilikuwa na tatizo). Tafadhali jaribu tena baada ya muda mfupi.';
return data.error||'Uchambuzi umeshindikana. Tafadhali jaribu tena.';
}
async function startAiProcess(){
if(aiProcessing)return;
var ready=aiImages.filter(function(im){return im.base64;});
if(!ready.length){showAiBanner('error','Chagua angalau picha moja kwanza.');return;}
if(aiRows.length){
var unverified=aiRows.filter(function(r){return !r.verified;}).length;
if(unverified>0){
var ok=await aiConfirm('Chambua tena?','Matokeo ya sasa yataondolewa ('+unverified+' za bidhaa hazijathibitishwa). Picha zako zitabaki.');
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
showAiBanner('info','Matokeo ya AI yapo. Hakiki kila bidhaa kisha ubonyeze "Thibitisha".');
}else if(r.ok){
showAiBanner('error','Hakuna bidhaa zilizotambuliwa kutoka kwenye picha. Jaribu picha nyingine zenye mwanga mzuri.');
}else{
showAiBanner('error',aiErrorMessage(data));
}
}catch(e){
$('aiProgress').style.display='none';
$('aiUploadSection').style.display='block';
showAiBanner('error','Hitilafu ya mtandao. Tafadhali jaribu tena.');
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
s.innerHTML='<span class="ai-summary-chip">Jumla: '+total+'</span><span class="ai-summary-chip existing">Zilizopo: '+existing+'</span><span class="ai-summary-chip">Mpya: '+(total-existing)+'</span><span class="ai-summary-chip review">Hakiki inahitajika: '+review+'</span><span class="ai-summary-chip ok">Zilizothibitishwa: '+verified+'</span>';
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
var stBadge=row.status==='EXISTING'?'<span class="ai-status existing">Iliyopo</span>':'<span class="ai-status new">Mpya</span>';
var revBadge=row.needsReview?'<span class="ai-status review" style="margin-left:4px">Hakiki</span>':'';
var conf=Math.round((row.confidence||0)*100);
var exp=(row.status==='EXISTING'&&row.currentStock!==null&&row.currentStock!==undefined)?('<div class="ai-conf-row">Hisa ya sasa: '+esc(row.currentStock)+'</div>'):'';
var src=row.sourceImage?'<div class="ai-conf-row">Picha: '+esc(row.sourceImage)+'</div>':'';
var warnings=(row.warnings||[]).map(function(w){return '<div class="ai-warn">'+esc(w)+'</div>';}).join('');
var bp=(row.buyingPrice===null||row.buyingPrice===undefined)?'':esc(row.buyingPrice);
var sp=(row.sellingPrice===null||row.sellingPrice===undefined)?'':esc(row.sellingPrice);
var qty=(row.quantity===null||row.quantity===undefined)?'':esc(row.quantity);
var action;
if(row.verified){action='<span class="ai-verified-tag">Imethibitishwa</span>';}
else{action='<button type="button" class="ai-verify-btn" id="aiVerify'+i+'" onclick="verifyAiRow('+i+')">Thibitisha</button>';}
return '<tr>'+
'<td><input type="text" value="'+esc(row.name)+'" id="aiName'+i+'"></td>'+
'<td style="min-width:82px"><input type="number" min="0" step="1" value="'+qty+'" id="aiQty'+i+'"></td>'+
'<td style="min-width:110px"><input type="number" min="0" value="'+bp+'" id="aiBuy'+i+'"></td>'+
'<td style="min-width:110px"><input type="number" min="0" value="'+sp+'" id="aiSell'+i+'"></td>'+
'<td>'+stBadge+revBadge+'<div class="ai-conf-row">Uaminifu: '+conf+'%</div>'+exp+'</td>'+
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
if(!name){showAiBanner('error','Jina la bidhaa halipaswi kuwa tupu.');return;}
if(!Number.isInteger(qty)||qty<=0){showAiBanner('error','Idadi lazima iwe nambari nzima kubwa kuliko sifuri.');return;}
var buyRaw=buyEl?(buyEl.value||''):'';
var sellRaw=sellEl?(sellEl.value||''):'';
var buy=buyRaw===''?null:Number(buyRaw);
var sell=sellRaw===''?null:Number(sellRaw);
if(sell===null||!Number.isFinite(sell)||sell<=0){showAiBanner('error','Weka bei ya kuuzia kabla ya kuthibitisha.');return;}
if(buy!==null&&(!Number.isFinite(buy)||buy<0)){showAiBanner('error','Bei ya kununua haifai — tumia nambari isiyo hasi.');return;}
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
if(td)td.innerHTML='<span class="ai-verified-tag">Imethibitishwa</span>';
updateAiSummary();
await fetchExistingProducts();
showAiBanner('info',(data.message||'Bidhaa imethibitishwa.'));
}else{
allBtns.forEach(function(b){b.disabled=false;});
if(btn){btn.innerHTML='Thibitisha';}
var msg=data.error||'Uthibitisho umeshindikana. Jaribu tena.';
if(data.code==='PRODUCT_EXISTS'){
// The product is already in the business — treat the row as saved
// instead of showing an error the user cannot act on.
row.verified=true;row.verifiedResult={alreadyVerified:true};
allBtns.forEach(function(b){b.disabled=false;});
var td2=btn?btn.closest('td'):null;
if(td2)td2.innerHTML='<span class="ai-verified-tag">Imethibitishwa</span>';
updateAiSummary();
await fetchExistingProducts();
showAiBanner('info','Bidhaa "'+name+'" tayari ipo kwenye biashara yako — imehifadhiwa.');
return;
}
showAiBanner('error',msg);
}
}catch(e){
allBtns.forEach(function(b){b.disabled=false;});
if(btn){btn.innerHTML='Thibitisha';}
showAiBanner('error','Hitilafu ya mtandao wakati wa kuthibitisha. Jaribu tena.');
}finally{
aiVerifyBusy=false;
}
}
// "Thibitisha Zote": confirms first, then verifies every unverified row
// one-by-one using the same validation and per-row logic as a single
// "Thibitisha" click. In-row edits are already synced to aiRows state.
async function verifyAllAiRows(){
if(aiProcessing){showAiBanner('info','Chambua picha kwanza.');return;}
if(aiVerifyBusy){showAiBanner('info','Tafutani inaendelea. Subiri kidogo.');return;}
if(aiBatchBusy){showAiBanner('info','Bidhaa zinahifadhiwa tayari. Subiri kidogo.');return;}
var pending=aiRows.filter(function(r){return !r.verified;});
if(!pending.length){showAiBanner('info','Bidhaa zote zilizothibitishwa tayari.');return;}

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
if(!name){problems.push('Mstari '+(i+1)+': jina tupu');continue;}
if(!Number.isInteger(qty)||qty<=0){problems.push('Mstari '+(i+1)+': idadi si sahihi');continue;}
if(sell===null||!Number.isFinite(sell)||sell<=0){problems.push('Mstari '+(i+1)+': bei ya kuuzia inahitajika');continue;}
var buyRaw=buyEl?(buyEl.value||''):'';
var buy=buyRaw===''?null:Number(buyRaw);
if(buy!==null&&(!Number.isFinite(buy)||buy<0)){problems.push('Mstari '+(i+1)+': bei ya kununua haifai');}
}
if(problems.length){showAiBanner('error','Rekebisha kabla ya kuthibitisha zote:\n'+problems.join('\n'));return;}

var ok=await aiConfirm('Thibitisha Zote?',pending.length+' bidhaa zitahifadhiwa kwenye stoo mara moja.');
if(!ok)return;

aiBatchBusy=true;
var statusEl=$('aiVerifyAllStatus');
var allBtn=$('aiVerifyAllBtn');
var allBtnHtml=allBtn?allBtn.innerHTML:'';
var saved=0,failed=0;
try{
for(var j=0;j<aiRows.length;j++){
if(aiRows[j].verified)continue;
if(statusEl)statusEl.textContent='Inahifadhi '+(saved+failed+1)+'/'+pending.length+'...';
// verifyAiRow re-enables every .ai-verify-btn, including this one, so the
// disabled + spinner state has to be reapplied on each pass.
if(allBtn){allBtn.disabled=true;allBtn.innerHTML=ic('refresh',14)+' Inahifadhi '+(saved+failed+1)+'/'+pending.length;}
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
showAiBanner('info','Zote '+saved+' bidhaa zimehifadhiwa kikamilifu!');
}else{
showAiBanner('error',saved+' zimehifadhiwa, '+failed+' zilishindikana — rekebisha na ujaribu tena.');
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