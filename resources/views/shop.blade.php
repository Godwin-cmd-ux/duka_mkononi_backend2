@include('partials.dm-locale')
@include('partials.site-head', ['titleKey' => 'site_shop.page_title', 'metaKey' => 'site_shop.meta_description', 'titleFallback' => 'Shop - DukaMkononi'])
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.site-navbar')
@verbatim
<style>
    .shop-layout { display: grid; grid-template-columns: 270px 1fr; gap: 26px; align-items: start; }
    .shop-sidebar { position: sticky; top: 84px; background: #fff; border: 1px solid var(--dm-line); border-radius: var(--dm-radius); padding: 18px; box-shadow: var(--dm-shadow-sm); }
    .shop-sidebar h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--dm-muted); margin-bottom: 12px; }
    .biz-list { display: flex; flex-direction: column; gap: 4px; max-height: 62vh; overflow-y: auto; }
    .biz-btn { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; text-align: left; padding: 10px 12px; border-radius: 10px; border: 1px solid transparent; background: transparent; cursor: pointer; font-size: 14px; color: var(--dm-ink); }
    .biz-btn:hover { background: #f1f5f9; }
    .biz-btn.active { background: #ecfdf5; border-color: #a7f3d0; color: #047857; font-weight: 600; }
    .biz-btn .cnt { font-size: 12px; color: var(--dm-muted); }
    .shop-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 18px; }
    .shop-toolbar .dm-input { flex: 1 1 220px; }
    .product-card { display: flex; flex-direction: column; overflow: hidden; }
    .product-media { aspect-ratio: 4 / 3; background: #f1f5f9; overflow: hidden; }
    .product-media img { width: 100%; height: 100%; object-fit: cover; }
    .product-media .ph { width: 100%; height: 100%; display: grid; place-items: center; color: #cbd5e1; }
    .product-body { padding: 14px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .product-name { font-weight: 600; color: var(--dm-navy); font-size: 15px; line-height: 1.35; }
    .product-biz { font-size: 12.5px; color: var(--dm-muted); }
    .product-price { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--dm-primary); font-size: 17px; margin-top: auto; }
    .cart-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #fff; border: 1px solid var(--dm-line); border-radius: var(--dm-radius); margin-bottom: 18px; box-shadow: var(--dm-shadow-sm); }
    .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: none; align-items: center; justify-content: center; padding: 16px; z-index: 800; }
    .modal-overlay.open { display: flex; }
    .modal { background: #fff; border-radius: var(--dm-radius); width: 100%; max-width: 560px; max-height: 92vh; overflow-y: auto; box-shadow: 0 24px 60px rgba(15, 23, 42, .3); }
    .modal-head { padding: 18px 20px; border-bottom: 1px solid var(--dm-line); display: flex; align-items: center; justify-content: space-between; gap: 12px; position: sticky; top: 0; background: #fff; }
    .modal-body { padding: 18px 20px; }
    .modal-foot { padding: 16px 20px; border-top: 1px solid var(--dm-line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; position: sticky; bottom: 0; background: #fff; }
    .qty-row { display: flex; align-items: center; gap: 10px; }
    .qty-row button { width: 42px; height: 42px; border-radius: 10px; border: 1px solid var(--dm-line); background: #fff; font-size: 20px; cursor: pointer; }
    .cart-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--dm-line); }
    .cart-item .info { flex: 1; }
    .summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14.5px; }
    .summary-row.total { font-weight: 800; font-size: 17px; color: var(--dm-navy); border-top: 1px solid var(--dm-line); margin-top: 6px; padding-top: 12px; }
    .shop-title-row { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
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
                    <div class="dm-skeleton" style="height:44px"></div>
                    <div class="dm-skeleton" style="height:44px"></div>
                </div>
            </aside>

            <section>
                <div class="shop-toolbar">
                    <input class="dm-input" type="search" id="dmShopSearch" data-i18n="site_shop.search_placeholder" data-i18n-attr="placeholder" placeholder="Search products...">
                    <span class="dm-muted" id="dmResultCount"></span>
                </div>
                <div class="dm-grid dm-grid--products" id="dmProducts">
                    <div class="dm-card dm-skeleton" style="height:280px"></div>
                    <div class="dm-card dm-skeleton" style="height:280px"></div>
                    <div class="dm-card dm-skeleton" style="height:280px"></div>
                    <div class="dm-card dm-skeleton" style="height:280px"></div>
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
                    '<div style="width:96px;height:96px;border-radius:12px;overflow:hidden;background:#f1f5f9;flex:0 0 auto">' +
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
    });
    function debounce(fn, ms) { var h; return function () { clearTimeout(h); h = setTimeout(fn, ms); }; }
})();
</script>
</body>
</html>
@endverbatim
