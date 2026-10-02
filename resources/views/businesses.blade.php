@include('partials.dm-locale')
@include('partials.site-head', ['titleKey' => 'site_businesses.page_title', 'metaKey' => 'site_businesses.meta_description', 'titleFallback' => 'Certified Businesses - DukaMkononi'])
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.site-navbar')
@verbatim
<style>
    .bl-card { display: flex; flex-direction: column; gap: 12px; padding: 18px; }
    .bl-logo { width: 56px; height: 56px; border-radius: 14px; background: #dbeafe; color: var(--dm-primary); display: grid; place-items: center; font-weight: 800; font-size: 20px; overflow: hidden; }
    .bl-logo img { width: 100%; height: 100%; object-fit: cover; }
</style>
<main class="dm-section">
    <div class="dm-container">
        <div class="dm-section-head">
            <div class="dm-eyebrow" data-i18n="site_nav.businesses">Businesses</div>
            <h1 class="dm-h2" data-i18n="site_businesses.title">Certified Businesses</h1>
            <p class="dm-lead" style="margin-top:10px" data-i18n="site_businesses.sub">Businesses verified on Duka Mkononi.</p>
        </div>
        <div class="dm-grid dm-grid--business" id="dmBizCards">
            <div class="dm-card dm-skeleton" style="height:190px"></div>
            <div class="dm-card dm-skeleton" style="height:190px"></div>
            <div class="dm-card dm-skeleton" style="height:190px"></div>
        </div>
    </div>
</main>
@endverbatim
@include('partials.site-footer')
@verbatim
<script>
(function () {
    'use strict';
    var t = function (k, p) { return window.DM ? DM.t(k, p) : k; };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
    document.addEventListener('DOMContentLoaded', function () {
        fetch('/api/shop/businesses/certified', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (list) {
                var box = document.getElementById('dmBizCards');
                if (!Array.isArray(list) || !list.length) {
                    box.innerHTML = '<div class="dm-empty" style="grid-column:1/-1">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>' +
                        '<h3>' + esc(t('site_businesses.empty')) + '</h3><p>' + esc(t('site_businesses.empty_hint')) + '</p></div>';
                    return;
                }
                box.innerHTML = '';
                list.forEach(function (b) {
                    var logo = b.business_logo_url ? '<img src="' + esc(b.business_logo_url) + '" alt="">' : esc((b.business_name || 'D').trim().charAt(0).toUpperCase());
                    var card = document.createElement('article');
                    card.className = 'dm-card bl-card';
                    card.innerHTML =
                        '<div style="display:flex;align-items:center;gap:12px"><div class="bl-logo">' + logo + '</div><div style="flex:1"><div class="dm-wrap-anywhere" style="font-weight:700">' + esc(b.business_name || '') + '</div>' +
                        (b.business_location ? '<div class="dm-muted" style="font-size:12.5px">' + esc(b.business_location) + '</div>' : '') + '</div></div>' +
                        '<div style="display:flex;gap:8px;flex-wrap:wrap"><span class="dm-badge dm-badge--ok">' + esc(t('site_businesses.certified_badge')) + '</span><span class="dm-badge dm-badge--muted">' + (b.product_count || 0) + ' ' + esc(t('site_common.products')) + '</span></div>' +
                        (b.business_description ? '<p class="dm-muted dm-wrap-anywhere" style="font-size:13.5px">' + esc(b.business_description) + '</p>' : '') +
                        '<a class="dm-btn dm-btn--primary dm-btn--sm dm-btn--block" href="/shop?business_id=' + encodeURIComponent(b.id) + '">' + esc(t('site_businesses.view_products')) + '</a>';
                    box.appendChild(card);
                });
            })
            .catch(function () {
                document.getElementById('dmBizCards').innerHTML = '<div class="dm-empty" style="grid-column:1/-1"><h3>' + esc(t('site_common.error_generic')) + '</h3></div>';
            });
    });
})();
</script>
</body>
</html>
@endverbatim
