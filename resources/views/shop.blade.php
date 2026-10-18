@include('partials.dm-locale')
@include('partials.site-head', ['titleKey' => 'site_shop.page_title', 'metaKey' => 'site_shop.meta_description', 'titleFallback' => 'Shop - DukaMkononi'])
@verbatim
<body class="dm-public">
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.dm-theme-widget')
@include('partials.dm-voice')
@include('partials.site-navbar')
@verbatim
<style>
    .shop-layout { display: grid; grid-template-columns: 270px 1fr; gap: 26px; align-items: start; }
    .shop-sidebar { position: sticky; top: 84px; background: var(--dm-card, #fff); border: 1px solid var(--dm-line); border-radius: var(--dm-radius); padding: 18px; box-shadow: var(--dm-shadow-sm); }
    .shop-sidebar h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--dm-muted); margin-bottom: 12px; }
    .biz-list { display: flex; flex-direction: column; gap: 4px; max-height: 62vh; overflow-y: auto; }
    .biz-btn { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; text-align: left; padding: 10px 12px; border-radius: 10px; border: 1px solid transparent; background: transparent; cursor: pointer; font-size: 14px; color: var(--dm-ink); }
    .biz-btn:hover { background: var(--dm-hover, #f1f5f9); }
    .biz-btn.active { background: var(--dm-tint, #dbeafe); border-color: var(--dm-tint-line, #bfdbfe); color: var(--dm-tint-ink, #1e40af); font-weight: 600; }
    .biz-btn .cnt { font-size: 12px; color: var(--dm-muted); }
    .shop-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 18px; }
    .shop-toolbar .dm-input { flex: 1 1 220px; }
    .product-card { display: flex; flex-direction: column; overflow: hidden; }
    .product-media { aspect-ratio: 4 / 3; background: var(--dm-hover, #f1f5f9); overflow: hidden; }
    .product-media img { width: 100%; height: 100%; object-fit: cover; }
    .product-media .ph { width: 100%; height: 100%; display: grid; place-items: center; color: #cbd5e1; }
    .product-body { padding: 14px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .product-name { font-weight: 600; color: var(--dm-navy); font-size: 15px; line-height: 1.35; }
    .product-biz { font-size: 12.5px; color: var(--dm-muted); }
    .product-price { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--dm-primary); font-size: 17px; margin-top: auto; }
    .cart-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: var(--dm-card, #fff); border: 1px solid var(--dm-line); border-radius: var(--dm-radius); margin-bottom: 18px; box-shadow: var(--dm-shadow-sm); }
    .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: none; align-items: center; justify-content: center; padding: 16px; z-index: 800; }
    .modal-overlay.open { display: flex; }
    .modal { background: var(--dm-card, #fff); border-radius: var(--dm-radius); width: 100%; max-width: 560px; max-height: 92vh; overflow-y: auto; box-shadow: 0 24px 60px rgba(15, 23, 42, .3); }
    .modal-head { padding: 18px 20px; border-bottom: 1px solid var(--dm-line); display: flex; align-items: center; justify-content: space-between; gap: 12px; position: sticky; top: 0; background: var(--dm-card, #fff); }
    .modal-body { padding: 18px 20px; }
    .modal-foot { padding: 16px 20px; border-top: 1px solid var(--dm-line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; position: sticky; bottom: 0; background: var(--dm-card, #fff); }
    .qty-row { display: flex; align-items: center; gap: 10px; }
    .qty-row button { width: 42px; height: 42px; border-radius: 10px; border: 1px solid var(--dm-line); background: var(--dm-input-bg, #fff); font-size: 20px; cursor: pointer; }
    .cart-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--dm-line); }
    .cart-item .info { flex: 1; }
    .summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14.5px; }
    .summary-row.total { font-weight: 800; font-size: 17px; color: var(--dm-navy); border-top: 1px solid var(--dm-line); margin-top: 6px; padding-top: 12px; }
    .shop-title-row { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
    /* Capability #11 - conversational shopping assistant panel */
    .shop-ai { background: var(--dm-card, #fff); border: 1px solid var(--dm-line); border-radius: var(--dm-radius); padding: 16px; margin-bottom: 18px; box-shadow: var(--dm-shadow-sm); }
    .shop-ai-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
    .shop-ai-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; letter-spacing: .04em; color: var(--dm-ai-ink, #4338ca); background: var(--dm-ai-bg, #eef2ff); border: 1px solid var(--dm-ai-line, #e0e7ff); border-radius: 999px; padding: 4px 10px; }
    .shop-ai-sub { font-size: 13px; color: var(--dm-muted); }
    .shop-ai-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .shop-ai-row .dm-input { flex: 1 1 240px; }
    .shop-ai-sortwrap { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--dm-muted); }
    .shop-ai-sortwrap select { border: 1px solid var(--dm-line); border-radius: 10px; padding: 10px 12px; font-size: 13.5px; background: var(--dm-input-bg, #fff); color: var(--dm-ink); font-family: inherit; }
    .shop-ai-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 10px; }
    .shop-ai-chip { border: 1px solid var(--dm-line); background: var(--dm-bg, #f8fafc); color: var(--dm-ink); border-radius: 999px; font-size: 12.5px; padding: 6px 12px; cursor: pointer; font-family: inherit; }
    .shop-ai-chip:hover { background: var(--dm-ai-bg, #eef2ff); border-color: var(--dm-ai-line-2, #c7d2fe); color: var(--dm-ai-ink, #4338ca); }
    .shop-ai-chip.ask { font-weight: 700; }
    .shop-ai-status { font-size: 13px; color: var(--dm-muted); margin-top: 10px; }
    .shop-ai-filters { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 12px; }
    .shop-ai-filters .lbl { font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--dm-muted); margin-right: 2px; }
    .shop-ai-filter { font-size: 11.5px; font-weight: 700; background: var(--dm-ai-bg, #eef2ff); color: var(--dm-ai-ink, #4338ca); border-radius: 999px; padding: 3px 9px; }
    .shop-ai-answer { background: var(--dm-bg, #f8fafc); border: 1px solid var(--dm-line); border-radius: 12px; padding: 12px 14px; margin-top: 12px; font-size: 14.5px; line-height: 1.55; color: var(--dm-ink); }
    .shop-ai-answer .kind { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--dm-ai-ink, #4338ca); margin-bottom: 5px; }
    .shop-ai-label { font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--dm-muted); margin: 14px 0 8px; }
    .shop-ai-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
    .shop-ai-biz { display: flex; align-items: center; gap: 10px; border: 1px solid var(--dm-line); border-radius: 12px; padding: 10px 12px; background: var(--dm-card, #fff); margin-bottom: 8px; }
    .shop-ai-biz .avatar { width: 40px; height: 40px; border-radius: 10px; background: var(--dm-hover, #f1f5f9); display: grid; place-items: center; color: #94a3b8; overflow: hidden; flex: 0 0 auto; }
    .shop-ai-biz .avatar img { width: 100%; height: 100%; object-fit: cover; }
    .shop-ai-biz .who { flex: 1; min-width: 0; }
    .shop-ai-notes { margin-top: 14px; font-size: 12px; color: var(--dm-muted); line-height: 1.6; }
    .shop-ai-notes .note { font-style: italic; }
    .shop-ai-notes b { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; margin: 8px 0 3px; color: var(--dm-muted); }
    .shop-ai-actions { display: flex; justify-content: flex-end; margin-top: 12px; }
    @media (max-width: 640px) { .shop-ai-row .dm-input { flex-basis: 100%; } }
    @media (max-width: 860px) {
        .shop-layout { grid-template-columns: 1fr; }
        .shop-sidebar { position: static; }
        .biz-list { flex-direction: row; flex-wrap: wrap; max-height: none; }
        .biz-btn { width: auto; }
    }
    @media (max-width: 640px) {
        .modal-foot .dm-btn { flex: 1; }
    }
</style>

<main class="dm-section dm-section--tight">
    <div class="dm-container">
        <div class="shop-title-row">
            <div>
                <div class="dm-eyebrow" data-i18n="site_nav.shop">Shop</div>
                <h1 class="dm-h2" data-i18n="site_shop.title">Shop</h1>
                <p class="dm-muted" data-i18n="site_shop.sub">Browse products from our businesses.</p>
            </div>
            <button type="button" class="dm-btn dm-btn--primary" id="dmCartBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M2 3h2l2.4 12.2a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 2-1.6L21 7H5"/></svg>
                <span data-i18n="site_shop.cart_title">Cart</span>
                <span class="dm-badge dm-badge--ok" id="dmCartCount">0</span>
            </button>
        </div>

        <div class="shop-layout">
            <aside class="shop-sidebar" aria-label="Businesses">
                <h2 data-i18n="site_shop.biz_filter_label">Businesses</h2>
                <div class="biz-list" id="dmBizList">
                    <div class="dm-loading dm-loading--inline">
                        <div class="loading-spinner dm-sc"></div>
                        <p data-i18n="site_common.loading">Loading...</p>
                    </div>
                </div>
            </aside>

            <section>
                <!-- Capability #11 - conversational shopping assistant -->
                <div class="shop-ai" id="dmShopAi">
                    <div class="shop-ai-head">
                        <span class="shop-ai-badge">&#10024; <span data-i18n="site_shop.ai_title">Shopping assistant</span></span>
                        <span class="shop-ai-sub" data-i18n="site_shop.ai_sub">Ask in your own words and get real products from public businesses.</span>
                    </div>
                    <div class="shop-ai-row">
                        <input class="dm-input" type="search" id="dmAiQuery" maxlength="240" data-i18n="site_shop.ai_placeholder" data-i18n-attr="placeholder" placeholder="E.g. brake pads for Toyota Harrier">
                        <span class="shop-ai-sortwrap">
                            <label for="dmAiSort" data-i18n="site_shop.ai_sort_label">Sort</label>
                            <select id="dmAiSort">
                                <option value="relevance" data-i18n="site_shop.ai_sort_relevance">Relevance</option>
                                <option value="price_asc" data-i18n="site_shop.ai_sort_low">Price: low first</option>
                                <option value="price_desc" data-i18n="site_shop.ai_sort_high">Price: high first</option>
                            </select>
                        </span>
                        <button type="button" class="dm-btn dm-btn--primary" id="dmAiAsk" data-i18n="site_shop.ai_ask">Search</button>
                    </div>
                    <div class="shop-ai-label" data-i18n="site_shop.ai_examples" style="margin-top:12px">Try:</div>
                    <div class="shop-ai-chips" id="dmAiChips"></div>
                    <div class="shop-ai-status" id="dmAiStatus" role="status" aria-live="polite"></div>
                    <div id="dmAiBody"></div>
                </div>

                <!-- Capability #14 - personalised recommendations -->
                <div class="shop-ai" id="dmReco" hidden>
                    <div class="shop-ai-head">
                        <span class="shop-ai-badge">&#10024; <span data-i18n="site_reco.title">Recommended for you</span></span>
                        <span class="shop-ai-sub" data-i18n="site_reco.sub">Real suggestions from the catalogue, each with the reason it was picked.</span>
                    </div>
                    <div class="shop-ai-status" id="dmRecoStatus" role="status" aria-live="polite"></div>
                    <div id="dmRecoBody"></div>
                </div>

                <div class="shop-toolbar">
                    <input class="dm-input" type="search" id="dmShopSearch" data-i18n="site_shop.search_placeholder" data-i18n-attr="placeholder" placeholder="Search products...">
                    <span class="dm-muted" id="dmResultCount"></span>
                </div>
                <div class="dm-grid dm-grid--products" id="dmProducts">
                    <div class="dm-loading">
                        <div class="loading-spinner dm-sc"></div>
                        <p data-i18n="site_common.loading">Loading...</p>
                    </div>
                </div>
                <div id="dmShopMore" style="text-align:center;margin-top:22px"></div>
            </section>
        </div>
    </div>
</main>

<!-- Order modal -->
<div class="modal-overlay" id="dmOrderModal">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-head">
            <h3 class="dm-h3" data-i18n="site_shop.order_title">Place order</h3>
            <button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" data-dm-close="dmOrderModal" data-i18n="site_common.close">Close</button>
        </div>
        <div class="modal-body" id="dmOrderBody"></div>
        <div class="modal-foot">
            <button type="button" class="dm-btn dm-btn--ghost" data-dm-close="dmOrderModal" data-i18n="site_common.cancel">Cancel</button>
            <button type="button" class="dm-btn dm-btn--primary" id="dmOrderSubmit" data-i18n="site_shop.order_submit">Submit order</button>
        </div>
    </div>
</div>

<!-- Cart modal -->
<div class="modal-overlay" id="dmCartModal">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-head">
            <h3 class="dm-h3" data-i18n="site_shop.cart_title">Cart</h3>
            <button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" data-dm-close="dmCartModal" data-i18n="site_common.close">Close</button>
        </div>
        <div class="modal-body" id="dmCartBody"></div>
        <div class="modal-foot">
            <button type="button" class="dm-btn dm-btn--ghost" id="dmCartClear" data-i18n="site_shop.cart_clear">Clear</button>
            <button type="button" class="dm-btn dm-btn--ghost" data-dm-close="dmCartModal" data-i18n="site_common.continue_shopping">Continue shopping</button>
            <button type="button" class="dm-btn dm-btn--primary" id="dmCartCheckout" data-i18n="site_shop.order_submit">Submit order</button>
        </div>
    </div>
</div>
@endverbatim
@include('partials.site-footer')
@include('partials.public-concierge')
@verbatim
<script>
(function () {
    'use strict';
    var API = '/api';
    var t = function (k, p) { return window.DM ? DM.t(k, p) : k; };
    var state = { businessId: '', search: '', page: 1, lastPage: 1, businesses: [], products: [] };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
    function money(n) { try { return 'TZS ' + Number(n).toLocaleString('en-US'); } catch (e) { return 'TZS ' + n; } }
    function api(path) { return fetch(API + path, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }); }

    function param(name) { return new URLSearchParams(location.search).get(name) || ''; }

    /* ---------------- businesses ---------------- */
    function loadBusinesses() {
        return api('/shop/businesses').then(function (list) {
            state.businesses = Array.isArray(list) ? list : [];
            renderBusinesses();
        }).catch(renderBusinessesError);
    }
    function renderBusinesses() {
        var box = document.getElementById('dmBizList');
        if (!box) return;
        var total = state.businesses.reduce(function (a, b) { return a + (b.product_count || 0); }, 0);
        var html = '<button type="button" class="biz-btn' + (state.businessId === '' ? ' active' : '') + '" data-biz=""><span>' + esc(t('site_shop.all_businesses')) + '</span><span class="cnt">' + total + '</span></button>';
        state.businesses.forEach(function (b) {
            html += '<button type="button" class="biz-btn' + (state.businessId === b.id ? ' active' : '') + '" data-biz="' + esc(b.id) + '"><span class="dm-wrap-anywhere">' + esc(b.business_name || '') + '</span><span class="cnt">' + (b.product_count || 0) + '</span></button>';
        });
        box.innerHTML = html;
        Array.prototype.forEach.call(box.querySelectorAll('.biz-btn'), function (btn) {
            btn.addEventListener('click', function () {
                state.businessId = btn.getAttribute('data-biz') || '';
                state.page = 1;
                var url = state.businessId ? ('/shop?business_id=' + encodeURIComponent(state.businessId)) : '/shop';
                history.replaceState(null, '', url);
                renderBusinesses();
                loadProducts(true);
            });
        });
    }
    function renderBusinessesError() {
        var box = document.getElementById('dmBizList');
        if (box) box.innerHTML = '<p class="dm-muted">' + esc(t('site_common.error_generic')) + '</p>';
    }

    /* ---------------- products ---------------- */
    function loadProducts(reset) {
        if (reset) state.page = 1;
        var q = '/shop/products?page=' + state.page + '&per_page=24';
        if (state.businessId) q += '&business_id=' + encodeURIComponent(state.businessId);
        if (state.search) q += '&search=' + encodeURIComponent(state.search);
        return api(q).then(function (res) {
            var data = (res && res.data) || [];
            state.lastPage = (res && res.meta && res.meta.last_page) || 1;
            if (reset) state.products = data; else state.products = state.products.concat(data);
            renderProducts(data, reset);
            renderMore(res && res.meta);
        }).catch(function () {
            document.getElementById('dmProducts').innerHTML =
                '<div class="dm-empty" style="grid-column:1/-1"><h3>' + esc(t('site_common.error_generic')) + '</h3><button class="dm-btn dm-btn--ghost" onclick="location.reload()">' + esc(t('site_common.retry')) + '</button></div>';
        });
    }
    function renderProducts(list, reset) {
        var box = document.getElementById('dmProducts');
        if (reset) box.innerHTML = '';
        var cnt = document.getElementById('dmResultCount');
        if (cnt && reset) cnt.textContent = (list.length ? list.length : 0) + ' ' + t('site_common.products');
        if (!list.length && reset) {
            box.innerHTML = '<div class="dm-empty" style="grid-column:1/-1">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/></svg>' +
                '<h3>' + esc(t('site_shop.empty')) + '</h3><p>' + esc(t('site_shop.empty_hint')) + '</p></div>';
            return;
        }
        list.forEach(function (p) {
            var card = document.createElement('article');
            card.className = 'dm-card product-card';
            var media = p.image_url
                ? '<img src="' + esc(p.image_url) + '" alt="' + esc(p.name) + '" loading="lazy">'
                : '<div class="ph"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div>';
            var avail = p.available
                ? '<span class="dm-badge dm-badge--ok">' + esc(t('site_shop.in_stock')) + '</span>'
                : '<span class="dm-badge dm-badge--danger">' + esc(t('site_common.out_of_stock')) + '</span>';
            card.innerHTML =
                '<div class="product-media">' + media + '</div>' +
                '<div class="product-body">' +
                    '<span class="product-biz dm-wrap-anywhere">' + esc(p.business_name || '') + '</span>' +
                    '<h3 class="product-name dm-wrap-anywhere">' + esc(p.name) + '</h3>' +
                    '<div style="display:flex;gap:8px;flex-wrap:wrap">' + avail + '</div>' +
                    '<div class="product-price">' + money(p.selling_price) + '</div>' +
                    '<button type="button" class="dm-btn dm-btn--primary dm-btn--sm dm-btn--block">' + esc(t('site_common.order_now')) + '</button>' +
                '</div>';
            card.querySelector('button').addEventListener('click', function () { addToCart(p, 1); openCart(); });
            box.appendChild(card);
        });
    }
    function renderMore(meta) {
        var box = document.getElementById('dmShopMore');
        if (!box) return;
        box.innerHTML = '';
        if (meta && state.page < meta.last_page) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'dm-btn dm-btn--ghost';
            b.textContent = t('site_common.load_more');
            b.addEventListener('click', function () { state.page += 1; loadProducts(false); });
            box.appendChild(b);
        }
    }

    /* ---------------- cart (localStorage) ---------------- */
    var CART_KEY = 'dmCart';
    function readCart() { try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); } catch (e) { return []; } }
    function writeCart(c) { localStorage.setItem(CART_KEY, JSON.stringify(c)); paintCart(); }
    function addToCart(p, qty) {
        var cart = readCart();
        if (cart.length && cart[0].business_id && cart[0].business_id !== p.business_id) {
            if (!confirm(t('site_shop.cart_clear'))) return;
            cart = [];
        }
        var found = cart.filter(function (x) { return x.product_id === p.id; })[0];
        if (found) found.quantity += qty; else cart.push({ product_id: p.id, name: p.name, business_id: p.business_id, business_name: p.business_name, selling_price: p.selling_price, quantity: qty });
        writeCart(cart);
    }
    function cartTotal() { return readCart().reduce(function (a, x) { return a + x.selling_price * x.quantity; }, 0); }
    function paintCart() {
        var c = readCart();
        var el = document.getElementById('dmCartCount');
        if (el) el.textContent = c.reduce(function (a, x) { return a + x.quantity; }, 0);
    }

    /* ---------------- order modal ---------------- */
    var currentProduct = null;
    function openOrder(p) {
        currentProduct = p;
        var qty = 1;
        function paint() {
            var total = p.selling_price * qty;
            document.getElementById('dmOrderBody').innerHTML =
                '<div style="display:flex;gap:14px;margin-bottom:14px">' +
                    '<div style="width:96px;height:96px;border-radius:12px;overflow:hidden;background:var(--dm-hover,#f1f5f9);flex:0 0 auto">' +
                        (p.image_url ? '<img src="' + esc(p.image_url) + '" alt="" style="width:100%;height:100%;object-fit:cover">' : '') +
                    '</div>' +
                    '<div><h3 class="dm-h3 dm-wrap-anywhere">' + esc(p.name) + '</h3>' +
                    '<p class="dm-muted" style="font-size:13px">' + esc(p.business_name || '') + '</p>' +
                    '<div class="product-price">' + money(p.selling_price) + '</div></div>' +
                '</div>' +
                '<div class="dm-field"><label class="dm-label" data-i18n="site_shop.order_quantity">Quantity</label>' +
                    '<div class="qty-row"><button type="button" id="dmQtyMinus">-</button><input class="dm-input" style="width:90px;text-align:center" id="dmQty" value="' + qty + '" inputmode="numeric"><button type="button" id="dmQtyPlus">+</button>' +
                    '<span class="dm-hint">' + esc(t('site_shop.stock_left', { count: p.stock })) + '</span></div></div>' +
                '<div class="dm-field"><label class="dm-label" data-i18n="site_shop.order_phone">Phone number</label><input class="dm-input" id="dmPhone" inputmode="tel" placeholder="07XXXXXXXX"></div>' +
                '<div class="dm-field"><label class="dm-label" data-i18n="site_shop.order_name">Name</label><input class="dm-input" id="dmName"></div>' +
                '<div class="dm-field"><label class="dm-label" data-i18n="site_shop.order_note">Note</label><textarea class="dm-textarea" id="dmNote"></textarea></div>' +
                '<div class="summary-row total"><span>' + esc(t('site_common.total')) + '</span><span id="dmOrderTotal">' + money(total) + '</span></div>' +
                '<div id="dmOrderAlert"></div>';
            document.getElementById('dmQtyMinus').onclick = function () { qty = Math.max(1, qty - 1); var i = document.getElementById('dmQty'); i.value = qty; document.getElementById('dmOrderTotal').textContent = money(p.selling_price * qty); };
            document.getElementById('dmQtyPlus').onclick = function () { qty = Math.min(p.stock || 9999, qty + 1); var i = document.getElementById('dmQty'); i.value = qty; document.getElementById('dmOrderTotal').textContent = money(p.selling_price * qty); };
            document.getElementById('dmQty').onchange = function () { var v = parseInt(this.value, 10); qty = isNaN(v) || v < 1 ? 1 : Math.min(p.stock || 9999, v); this.value = qty; document.getElementById('dmOrderTotal').textContent = money(p.selling_price * qty); };
            if (window.DM) DM.t && Array.prototype.forEach.call(document.querySelectorAll('#dmOrderBody [data-i18n]'), function (el) { el.textContent = DM.t(el.getAttribute('data-i18n')); });
        }
        paint();
        document.getElementById('dmOrderSubmit').onclick = function () {
            submitOrder([{ product_id: p.id, quantity: qty }], {
                phone: document.getElementById('dmPhone').value,
                name: document.getElementById('dmName').value,
                note: document.getElementById('dmNote').value
            }, '#dmOrderAlert', '#dmOrderSubmit');
        };
        openModal('dmOrderModal');
    }

    function openCart() {
        var c = readCart();
        var body = document.getElementById('dmCartBody');
        if (!c.length) {
            body.innerHTML = '<div class="dm-empty"><h3>' + esc(t('site_shop.cart_empty')) + '</h3></div>';
            document.getElementById('dmCartCheckout').style.display = 'none';
            openModal('dmCartModal');
            return;
        }
        document.getElementById('dmCartCheckout').style.display = '';
        var html = '';
        c.forEach(function (x, i) {
            html += '<div class="cart-item"><div class="info"><div class="dm-wrap-anywhere" style="font-weight:600">' + esc(x.name) + '</div>' +
                '<div class="dm-muted" style="font-size:12.5px">' + esc(x.business_name || '') + '</div>' +
                '<div class="qty-row" style="margin-top:6px"><button type="button" data-dec="' + i + '">-</button><span style="min-width:30px;text-align:center">' + x.quantity + '</span><button type="button" data-inc="' + i + '">+</button>' +
                '<button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" data-del="' + i + '">' + esc(t('site_common.remove')) + '</button></div></div>' +
                '<div style="font-weight:700">' + money(x.selling_price * x.quantity) + '</div></div>';
        });
        html += '<div class="dm-field" style="margin-top:16px"><label class="dm-label" data-i18n="site_shop.order_phone">Phone number</label><input class="dm-input" id="dmCartPhone" inputmode="tel" placeholder="07XXXXXXXX"></div>' +
            '<div class="dm-field"><label class="dm-label" data-i18n="site_shop.order_name">Name</label><input class="dm-input" id="dmCartName"></div>' +
            '<div class="dm-field"><label class="dm-label" data-i18n="site_shop.order_note">Note</label><textarea class="dm-textarea" id="dmCartNote"></textarea></div>' +
            '<div class="summary-row"><span>' + esc(t('site_common.subtotal')) + '</span><span>' + money(cartTotal()) + '</span></div>' +
            '<div class="summary-row total"><span>' + esc(t('site_common.total')) + '</span><span>' + money(cartTotal()) + '</span></div>' +
            '<div id="dmCartAlert"></div>';
        body.innerHTML = html;
        if (window.DM) Array.prototype.forEach.call(body.querySelectorAll('[data-i18n]'), function (el) { el.textContent = DM.t(el.getAttribute('data-i18n')); });
        body.querySelectorAll('[data-inc]').forEach(function (b) { b.onclick = function () { var c2 = readCart(); c2[+b.getAttribute('data-inc')].quantity++; writeCart(c2); openCart(); }; });
        body.querySelectorAll('[data-dec]').forEach(function (b) { b.onclick = function () { var c2 = readCart(); var i = +b.getAttribute('data-dec'); c2[i].quantity = Math.max(1, c2[i].quantity - 1); writeCart(c2); openCart(); }; });
        body.querySelectorAll('[data-del]').forEach(function (b) { b.onclick = function () { var c2 = readCart(); c2.splice(+b.getAttribute('data-del'), 1); writeCart(c2); openCart(); }; });
        document.getElementById('dmCartCheckout').onclick = function () {
            var items = readCart().map(function (x) { return { product_id: x.product_id, quantity: x.quantity }; });
            submitOrder(items, {
                phone: document.getElementById('dmCartPhone').value,
                name: document.getElementById('dmCartName').value,
                note: document.getElementById('dmCartNote').value
            }, '#dmCartAlert', '#dmCartCheckout');
        };
        openModal('dmCartModal');
    }

    function submitOrder(items, fields, alertSel, btnSel) {
        var alertBox = document.querySelector(alertSel);
        var btn = document.querySelector(btnSel);
        function show(cls, msg) { if (alertBox) alertBox.innerHTML = '<div class="dm-alert dm-alert--' + cls + '">' + esc(msg) + '</div>'; }
        if (!fields.phone || fields.phone.replace(/\D/g, '').length < 9) { show('err', t('site_track.error_phone')); return; }
        if (btn) btn.disabled = true;
        fetch(API + '/orders', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                items: items,
                customer_phone: fields.phone,
                customer_name: fields.name,
                customer_note: fields.note,
                client_order_key: 'web-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8),
                source: 'website'
            })
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); }).then(function (res) {
            if (btn) btn.disabled = false;
            if (!res.ok) { show('err', (res.d && res.d.error) || t('site_common.error_generic')); return; }
            writeCart([]);
            var refs = (res.d.orders || []).map(function (o) { return o.order_reference; }).join(', ');
            show('ok', t('site_shop.order_success', { reference: refs }));
            setTimeout(function () { location.href = '/track-orders'; }, 1400);
        }).catch(function () { if (btn) btn.disabled = false; show('err', t('site_common.error_generic')); });
    }

    /* ---------------- modal helpers ---------------- */
    function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('open'); }
    function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('open'); }
    document.addEventListener('click', function (e) {
        var t1 = e.target.closest('[data-dm-close]');
        if (t1) closeModal(t1.getAttribute('data-dm-close'));
        if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open');
    });

    /* ---------------- AI shopping assistant (capability #11) ---------------- */
    var aiState = { query: '', refine: {}, busy: false, answered: false };

    function aiLocale() {
        if (window.DM && DM.locale) return DM.locale();
        return document.documentElement.getAttribute('data-dm-locale') || 'sw';
    }

    function aiSortValue() {
        var el = document.getElementById('dmAiSort');
        var v = el ? el.value : 'relevance';
        return v === 'price_asc' || v === 'price_desc' ? v : 'relevance';
    }

    function aiPost(path, body) {
        return fetch(API + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); });
    }

    /* refine = {category} or {business_id} when a chip was tapped; a fresh
       typed question always starts from an empty refinement. */
    function askAi(query, refine) {
        query = String(query == null ? '' : query).trim();
        if (!query || aiState.busy) return;
        aiState.busy = true;
        aiState.query = query;
        aiState.refine = refine || {};

        var status = document.getElementById('dmAiStatus');
        var btn = document.getElementById('dmAiAsk');
        if (status) status.textContent = t('site_shop.ai_loading');
        if (btn) btn.disabled = true;

        var body = { query: query, locale: aiLocale(), sort: aiSortValue() };
        Object.keys(aiState.refine).forEach(function (k) {
            if (aiState.refine[k] != null && aiState.refine[k] !== '') body[k] = aiState.refine[k];
        });

        aiPost('/ai/shopping/ask', body).then(function (res) {
            aiState.busy = false;
            if (btn) btn.disabled = false;
            if (!res.ok || !res.d || !res.d.success) {
                if (status) status.textContent = (res.d && res.d.error) || t('site_shop.ai_error');
                var bodyBox = document.getElementById('dmAiBody');
                if (bodyBox) bodyBox.innerHTML = '';
                return;
            }
            aiState.answered = true;
            if (status) status.textContent = '';
            renderAi(res.d);
        }).catch(function () {
            aiState.busy = false;
            if (btn) btn.disabled = false;
            if (status) status.textContent = t('site_shop.ai_error');
        });
    }

    function aiFilterChips(d) {
        var it = d.interpreted || {};
        var chips = [];
        if (it.price_min != null && it.price_max != null) {
            chips.push(t('site_shop.ai_price_between', { min: money(it.price_min), max: money(it.price_max) }));
        } else if (it.price_max != null) {
            chips.push(t('site_shop.ai_price_max_only', { max: money(it.price_max) }));
        } else if (it.price_min != null) {
            chips.push(t('site_shop.ai_price_min_only', { min: money(it.price_min) }));
        }
        if (it.in_stock) chips.push(t('site_shop.ai_stock_only'));
        if (it.location) chips.push(it.location);
        return chips;
    }

    function renderAi(d) {
        var box = document.getElementById('dmAiBody');
        if (!box) return;
        var html = '';

        var kindLabels = {
            results: 'site_shop.ai_exact',
            relaxed: 'site_shop.ai_closest',
            businesses: 'site_shop.ai_businesses_label',
            clarify: 'site_shop.ai_clarify_label',
            none: 'site_shop.ai_results_label'
        };
        var it = d.interpreted || {};
        var terms = (it.terms || []).slice();
        if (it.phrase) terms.push('"' + it.phrase + '"');
        if (it.category) terms.push(it.category);
        var filters = aiFilterChips(d);
        if (terms.length || filters.length) {
            html += '<div class="shop-ai-filters"><span class="lbl">' + esc(t('site_shop.ai_interpreted')) + '</span>';
            terms.forEach(function (x) { html += '<span class="shop-ai-filter">' + esc(x) + '</span>'; });
            filters.forEach(function (x) { html += '<span class="shop-ai-filter">' + esc(x) + '</span>'; });
            html += '</div>';
        }

        if (d.answer && d.answer.text) {
            html += '<div class="shop-ai-answer"><span class="kind">' + esc(t(kindLabels[d.answer.kind] || 'site_shop.ai_results_label')) + '</span>' + esc(d.answer.text) + '</div>';
        }

        if (d.clarification && d.clarification.options && d.clarification.options.length) {
            html += '<div class="shop-ai-label">' + esc(t('site_shop.ai_clarify_label')) + '</div><div class="shop-ai-chips">';
            d.clarification.options.forEach(function (o, i) {
                html += '<button type="button" class="shop-ai-chip ask" data-clarify="' + i + '">' + esc(o.label) + (o.count ? ' (' + o.count + ')' : '') + '</button>';
            });
            html += '</div>';
        } else if (d.categories && d.categories.length) {
            html += '<div class="shop-ai-label">' + esc(t('site_shop.ai_refine_label')) + '</div><div class="shop-ai-chips">';
            d.categories.forEach(function (c, i) {
                html += '<button type="button" class="shop-ai-chip" data-cat="' + i + '">' + esc(c.category) + ' (' + c.count + ')</button>';
            });
            html += '</div>';
        }

        if (d.products && d.products.length) {
            html += '<div class="shop-ai-label">' + esc(t('site_shop.ai_results_label')) + ' (' + d.products.length + ')</div>';
            html += '<div class="shop-ai-grid">';
            d.products.forEach(function (p, i) {
                var biz = p.business || {};
                var media = p.image
                    ? '<img src="' + esc(p.image) + '" alt="' + esc(p.name) + '" loading="lazy">'
                    : '<div class="ph"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div>';
                var stock = p.available
                    ? '<span class="dm-badge dm-badge--ok">' + esc(t('site_shop.in_stock')) + '</span>'
                    : '<span class="dm-badge dm-badge--danger">' + esc(t('site_common.out_of_stock')) + '</span>';
                var match = p.match && p.match.type === 'relaxed'
                    ? '<span class="dm-badge">' + esc(t('site_shop.ai_closest')) + '</span>'
                    : '<span class="dm-badge dm-badge--ok">' + esc(t('site_shop.ai_exact')) + '</span>';
                var cert = biz.certified ? ' <span class="dm-badge dm-badge--ok">' + esc(t('site_shop.ai_certified')) + '</span>' : '';
                html += '<article class="dm-card product-card">' +
                    '<div class="product-media">' + media + '</div>' +
                    '<div class="product-body">' +
                        '<span class="product-biz dm-wrap-anywhere">' + esc(biz.name || '') + cert + '</span>' +
                        (biz.location ? '<span class="product-biz dm-wrap-anywhere">' + esc(biz.location) + '</span>' : '') +
                        '<h3 class="product-name dm-wrap-anywhere">' + esc(p.name) + '</h3>' +
                        '<div style="display:flex;gap:8px;flex-wrap:wrap">' + stock + match + '</div>' +
                        '<div class="product-price">' + money(p.selling_price) + '</div>' +
                        (p.available
                            ? '<button type="button" class="dm-btn dm-btn--primary dm-btn--sm dm-btn--block" data-aicart="' + i + '">' + esc(t('site_common.order_now')) + '</button>'
                            : '') +
                    '</div></article>';
            });
            html += '</div>';
        }

        if (d.businesses && d.businesses.length) {
            html += '<div class="shop-ai-label">' + esc(t('site_shop.ai_businesses_label')) + '</div>';
            d.businesses.forEach(function (b) {
                var avatar = b.avatar
                    ? '<span class="avatar"><img src="' + esc(b.avatar) + '" alt=""></span>'
                    : '<span class="avatar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/></svg></span>';
                var cert = b.certified ? ' <span class="dm-badge dm-badge--ok">' + esc(t('site_shop.ai_certified')) + '</span>' : '';
                var meta = [];
                if (b.location) meta.push(b.location);
                meta.push((b.product_count || 0) + ' ' + t('site_common.products'));
                html += '<div class="shop-ai-biz">' + avatar +
                    '<div class="who"><div class="dm-wrap-anywhere" style="font-weight:700">' + esc(b.name) + cert + '</div>' +
                    '<div class="dm-muted dm-wrap-anywhere" style="font-size:12.5px">' + esc(meta.join(' · ')) + '</div></div>' +
                    '<button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" data-aibiz="' + esc(b.id) + '">' + esc(t('site_shop.ai_view_business')) + '</button></div>';
            });
        }

        var notes = d.notes || [];
        var limits = d.limitations || [];
        if (notes.length || limits.length) {
            html += '<div class="shop-ai-notes">';
            notes.forEach(function (n) { html += '<div class="note">' + esc(n) + '</div>'; });
            if (limits.length) {
                html += '<b>' + esc(t('site_shop.ai_limitations_title')) + '</b>';
                limits.forEach(function (n) { html += '<div>' + esc(n) + '</div>'; });
            }
            html += '</div>';
        }

        html += '<div class="shop-ai-actions"><button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" id="dmAiClear">' + esc(t('site_shop.ai_clear')) + '</button></div>';

        box.innerHTML = html;

        Array.prototype.forEach.call(box.querySelectorAll('[data-clarify]'), function (btn) {
            btn.addEventListener('click', function () {
                var o = (d.clarification.options || [])[+btn.getAttribute('data-clarify')];
                if (!o) return;
                askAi(aiState.query, o.type === 'business' ? { business_id: o.business_id } : { category: o.category });
            });
        });
        Array.prototype.forEach.call(box.querySelectorAll('[data-cat]'), function (btn) {
            btn.addEventListener('click', function () {
                var c = (d.categories || [])[+btn.getAttribute('data-cat')];
                if (!c) return;
                askAi(aiState.query, { category: c.category });
            });
        });
        Array.prototype.forEach.call(box.querySelectorAll('[data-aicart]'), function (btn) {
            btn.addEventListener('click', function () {
                var p = (d.products || [])[+btn.getAttribute('data-aicart')];
                if (!p) return;
                var biz = p.business || {};
                addToCart({
                    id: p.id,
                    name: p.name,
                    business_id: biz.id,
                    business_name: biz.name,
                    selling_price: p.selling_price
                }, 1);
                openCart();
            });
        });
        Array.prototype.forEach.call(box.querySelectorAll('[data-aibiz]'), function (btn) {
            btn.addEventListener('click', function () {
                var target = document.querySelector('.biz-btn[data-biz="' + btn.getAttribute('data-aibiz') + '"]');
                if (target) {
                    target.click();
                    target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });
        var clearBtn = document.getElementById('dmAiClear');
        if (clearBtn) clearBtn.addEventListener('click', resetAi);
    }

    function resetAi() {
        aiState.query = '';
        aiState.refine = {};
        aiState.answered = false;
        var box = document.getElementById('dmAiBody');
        if (box) box.innerHTML = '';
        var status = document.getElementById('dmAiStatus');
        if (status) status.textContent = '';
        loadAiSamples();
    }

    function loadAiSamples() {
        var chips = document.getElementById('dmAiChips');
        if (!chips) return;
        fetch(API + '/ai/shopping/suggestions?q=', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var samples = (d && d.samples) || [];
                chips.innerHTML = samples.map(function (s) {
                    return '<button type="button" class="shop-ai-chip" data-sample="' + esc(s) + '">' + esc(s) + '</button>';
                }).join('');
                Array.prototype.forEach.call(chips.querySelectorAll('[data-sample]'), function (btn) {
                    btn.addEventListener('click', function () {
                        var input = document.getElementById('dmAiQuery');
                        if (input) input.value = btn.getAttribute('data-sample') || '';
                        askAi(btn.getAttribute('data-sample'), {});
                    });
                });
            })
            .catch(function () { /* samples are a nicety, never an error state */ });
    }

    /* ---------------- capability #14 - recommendations ---------------- */
    // Session-local mirror of this visitor's dismissals; the durable,
    // account-scoped list lives on the server (POST dismiss).
    var recoDismissed = {};

    function recoHeaders() {
        var token = '';
        try { token = localStorage.getItem('userToken') || ''; } catch (e) { token = ''; }
        var headers = { 'Accept': 'application/json' };
        if (token) headers['Authorization'] = 'Bearer ' + token;
        return headers;
    }

    function recoLocale() {
        if (window.DM && DM.locale) return DM.locale();
        return document.documentElement.getAttribute('data-dm-locale') || 'sw';
    }

    function loadRecommendations() {
        var box = document.getElementById('dmReco');
        if (!box) return;
        fetch(API + '/ai/recommendations?locale=' + recoLocale(), { headers: recoHeaders() })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (!res.ok || !res.d || res.d.success !== true) { box.hidden = true; return; }
                renderRecommendations(res.d);
            })
            .catch(function () { box.hidden = true; });
    }

    function renderRecommendations(d) {
        var box = document.getElementById('dmReco');
        var body = document.getElementById('dmRecoBody');
        if (!box || !body) return;

        var products = (d.products || []).filter(function (p) { return !recoDismissed[p.id]; });
        var businesses = (d.businesses || []).filter(function (b) { return !recoDismissed[b.id]; });
        var signedIn = !!recoHeaders()['Authorization'];

        if (!products.length && !businesses.length) { box.hidden = true; return; }

        var html = '';
        var meta = d.meta || {};
        if (meta.note) html += '<p class="dm-muted" style="margin:0 0 8px">' + esc(meta.note) + '</p>';

        if (products.length) {
            html += '<div class="shop-ai-label">' + esc(t('site_reco.products_title')) + '</div>';
            html += '<div class="dm-grid dm-grid--products">';
            products.forEach(function (p) {
                html += '<div class="dm-card" data-reco-card="' + esc(p.id) + '" style="position:relative">';
                if (signedIn) {
                    html += '<button type="button" data-reco-dismiss="product:' + esc(p.id) + '" title="' + esc(t('site_reco.dismiss')) + '" aria-label="' + esc(t('site_reco.dismiss')) + '" style="position:absolute;top:6px;right:6px;border:0;background:rgba(255,255,255,.85);border-radius:999px;width:24px;height:24px;cursor:pointer">&#10005;</button>';
                }
                if (p.image) html += '<img src="' + esc(p.image) + '" alt="" style="width:100%;height:96px;object-fit:cover;border-radius:8px;margin-bottom:8px">';
                html += '<b>' + esc(p.name || '') + '</b>';
                html += '<div class="dm-muted">' + esc(p.business_name || '') + '</div>';
                html += '<div>' + esc(money(p.selling_price)) + (p.unit ? ' / ' + esc(p.unit) : '') + '</div>';
                html += '<div class="shop-ai-chip" style="margin-top:6px;display:inline-block">' + esc((p.reason && p.reason.text) || '') + '</div>';
                if (signedIn) {
                    html += '<div style="margin-top:6px;display:flex;gap:6px">';
                    html += '<button type="button" data-reco-fb="helpful:' + esc(p.id) + '" title="' + esc(t('site_reco.helpful')) + '" style="border:0;background:none;cursor:pointer">&#128077;</button>';
                    html += '<button type="button" data-reco-fb="not_helpful:' + esc(p.id) + '" title="' + esc(t('site_reco.not_helpful')) + '" style="border:0;background:none;cursor:pointer">&#128078;</button>';
                    html += '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        if (businesses.length) {
            html += '<div class="shop-ai-label">' + esc(t('site_reco.businesses_title')) + '</div><div class="shop-ai-chips">';
            businesses.forEach(function (b) {
                html += '<span class="shop-ai-chip" data-reco-card="' + esc(b.id) + '">' + esc(b.business_name || '') + ' &middot; ' + esc((b.reason && b.reason.text) || '');
                if (signedIn) {
                    html += ' <button type="button" data-reco-dismiss="business:' + esc(b.id) + '" title="' + esc(t('site_reco.dismiss')) + '" aria-label="' + esc(t('site_reco.dismiss')) + '" style="border:0;background:none;cursor:pointer">&#10005;</button>';
                }
                html += '</span>';
            });
            html += '</div>';
        }

        if (meta.privacy) html += '<p class="dm-muted" style="margin:10px 0 0;font-size:12px">' + esc(meta.privacy) + '</p>';

        body.innerHTML = html;
        box.hidden = false;

        Array.prototype.forEach.call(body.querySelectorAll('[data-reco-dismiss]'), function (btn) {
            btn.addEventListener('click', function () {
                var parts = (btn.getAttribute('data-reco-dismiss') || '').split(':');
                dismissRecommendation(parts[0], parts[1]);
            });
        });
        Array.prototype.forEach.call(body.querySelectorAll('[data-reco-fb]'), function (btn) {
            btn.addEventListener('click', function () {
                var parts = (btn.getAttribute('data-reco-fb') || '').split(':');
                sendRecoFeedback(parts[0], parts[1]);
            });
        });
    }

    function dismissRecommendation(type, id) {
        if (!id) return;
        recoDismissed[id] = true;
        var el = document.querySelector('[data-reco-card="' + id + '"]');
        if (el && el.parentNode) el.parentNode.removeChild(el);
        fetch(API + '/ai/recommendations/dismiss', {
            method: 'POST',
            headers: (function () { var h = recoHeaders(); h['Content-Type'] = 'application/json'; return h; })(),
            body: JSON.stringify({ type: type, id: id })
        }).catch(function () { /* durable list is a nicety; the card stays hidden locally */ });
    }

    function sendRecoFeedback(rating, id) {
        var status = document.getElementById('dmRecoStatus');
        fetch(API + '/ai/recommendations/feedback', {
            method: 'POST',
            headers: (function () { var h = recoHeaders(); h['Content-Type'] = 'application/json'; return h; })(),
            body: JSON.stringify({ rating: rating, item_id: id || null })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (status) status.textContent = (d && d.message) || ''; })
            .catch(function () { /* feedback is best-effort */ });
    }

    /* ---------------- init ---------------- */
    document.addEventListener('DOMContentLoaded', function () {
        state.businessId = param('business_id');
        state.search = param('search');
        var sp = document.getElementById('dmShopSearch');
        if (sp) { sp.value = state.search; sp.addEventListener('input', debounce(function () { state.search = sp.value.trim(); loadProducts(true); }, 400)); }
        document.getElementById('dmCartBtn').addEventListener('click', openCart);
        document.getElementById('dmCartClear').addEventListener('click', function () { writeCart([]); openCart(); });
        paintCart();
        loadBusinesses();
        loadProducts(true);

        // Capability #11 - assistant wiring.
        loadAiSamples();
        var aiInput = document.getElementById('dmAiQuery');
        var aiAskBtn = document.getElementById('dmAiAsk');
        if (aiAskBtn) aiAskBtn.addEventListener('click', function () { askAi(aiInput ? aiInput.value : '', {}); });
        if (aiInput) aiInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); askAi(aiInput.value, {}); }
        });
        var aiSort = document.getElementById('dmAiSort');
        if (aiSort) aiSort.addEventListener('change', function () {
            if (aiState.answered && aiState.query) askAi(aiState.query, aiState.refine);
        });
        if (window.DM && DM.onChange) DM.onChange(function () { if (!aiState.answered) loadAiSamples(); });

        // Capability #14 - recommendations (public read; personalised when
        // this visitor has a token, cold-start otherwise).
        loadRecommendations();
        if (window.DM && DM.onChange) DM.onChange(function () { loadRecommendations(); });
    });
    function debounce(fn, ms) { var h; return function () { clearTimeout(h); h = setTimeout(fn, ms); }; }
})();
</script>
</body>
</html>
@endverbatim
