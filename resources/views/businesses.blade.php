@include('partials.dm-locale')
@include('partials.site-head', ['titleKey' => 'site_businesses.page_title', 'metaKey' => 'site_businesses.meta_description', 'titleFallback' => 'Businesses - DukaMkononi'])
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.site-navbar')
@verbatim
<style>
    .bl-toolbar { position: relative; max-width: 640px; margin: 0 auto 14px; }
    .bl-toolbar svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--dm-muted); pointer-events: none; }
    .bl-toolbar .dm-input { padding-left: 42px; }
    .bl-count { text-align: center; font-weight: 700; color: var(--dm-navy); font-size: 15px; margin-bottom: 22px; }
    .bl-card { display: flex; flex-direction: column; gap: 14px; padding: 18px; }
    .bl-logo { width: 56px; height: 56px; border-radius: 14px; background: #dbeafe; color: var(--dm-primary); display: grid; place-items: center; font-weight: 800; font-size: 20px; overflow: hidden; flex: 0 0 auto; }
    .bl-logo img { width: 100%; height: 100%; object-fit: cover; }
    .bl-rows { display: flex; flex-direction: column; }
    .bl-row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--dm-line); font-size: 13.5px; }
    .bl-row:last-child { border-bottom: none; }
    .bl-row .lbl { font-weight: 600; color: var(--dm-ink); }
    .bl-row .val { color: var(--dm-muted); text-align: right; overflow-wrap: anywhere; }
    .bl-row .val a { color: var(--dm-primary); font-weight: 600; }
</style>
<main class="dm-section">
    <div class="dm-container">
        <div class="dm-section-head">
            <div class="dm-eyebrow" data-i18n="site_nav.businesses">Businesses</div>
            <h1 class="dm-h2" data-i18n="site_nav.businesses">Businesses</h1>
            <p class="dm-lead" style="margin-top:10px" data-i18n="site_businesses.sub">Registered businesses on Duka Mkononi.</p>
        </div>

        <div class="bl-toolbar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input class="dm-input" type="search" id="dmBizSearch" placeholder="Search businesses..." data-i18n="mteja_biashara.search_placeholder" data-i18n-attr="placeholder">
        </div>
        <div class="bl-count" id="dmBizCount"></div>

        <div class="dm-grid dm-grid--business" id="dmBizCards">
            <div class="dm-card dm-skeleton" style="height:230px"></div>
            <div class="dm-card dm-skeleton" style="height:230px"></div>
            <div class="dm-card dm-skeleton" style="height:230px"></div>
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
    // locales.json stores line breaks as the literal two characters backslash + n.
    var BS = String.fromCharCode(92);
    function br(s) { return esc(s).split(BS + 'n').join('<br>'); }
    function nf(v) { return (v === null || v === undefined || v === '') ? t('not_filled') : v; }

    var businesses = [];
    var query = '';

    function filtered() {
        if (!query.trim()) return businesses;
        var q = query.toLowerCase();
        return businesses.filter(function (b) {
            return (b.business_name && String(b.business_name).toLowerCase().indexOf(q) > -1) ||
                   (b.business_location && String(b.business_location).toLowerCase().indexOf(q) > -1) ||
                   (b.full_name && String(b.full_name).toLowerCase().indexOf(q) > -1) ||
                   (b.phone && String(b.phone).indexOf(q) > -1) ||
                   (b.email && String(b.email).toLowerCase().indexOf(q) > -1);
        });
    }

    function row(label, value) {
        return '<div class="bl-row"><span class="lbl">' + esc(label) + '</span><span class="val">' + esc(nf(value)) + '</span></div>';
    }

    function card(b) {
        var logo = b.business_logo_url ? '<img src="' + esc(b.business_logo_url) + '" alt="">' : esc((b.business_name || 'D').trim().charAt(0).toUpperCase());
        var phoneCell = b.phone ? '<a href="tel:' + esc(String(b.phone).replace(/[^0-9+]/g, '')) + '">' + esc(b.phone) + '</a>' : esc(t('not_filled'));
        var emailCell = b.email ? '<a href="mailto:' + esc(b.email) + '">' + esc(b.email) + '</a>' : esc(t('not_filled'));
        return '<article class="dm-card bl-card">' +
            '<div style="display:flex;align-items:center;gap:12px">' +
                '<div class="bl-logo">' + logo + '</div>' +
                '<div style="flex:1;min-width:0">' +
                    '<div class="dm-wrap-anywhere" style="font-weight:700;font-size:16px">' + esc(b.business_name || t('business_unnamed')) + '</div>' +
                    '<span class="dm-badge dm-badge--ok" style="margin-top:6px">' + esc(t('status_verified')) + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="bl-rows">' +
                row(t('label_admin_name'), b.full_name) +
                row(t('label_location'), b.business_location) +
                '<div class="bl-row"><span class="lbl">' + esc(t('label_phone')) + '</span><span class="val">' + phoneCell + '</span></div>' +
                '<div class="bl-row"><span class="lbl">' + esc(t('label_email')) + '</span><span class="val">' + emailCell + '</span></div>' +
            '</div>' +
            '<a class="dm-btn dm-btn--primary dm-btn--sm dm-btn--block" href="/shop?business_id=' + encodeURIComponent(b.id) + '">' + esc(t('site_businesses.view_products')) + '</a>' +
        '</article>';
    }

    function emptyHtml() {
        return '<div class="dm-empty" style="grid-column:1/-1">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>' +
            '<h3>' + esc(t('empty_title')) + '</h3>' +
            '<p>' + br(t('empty_text')) + '</p>' +
            '<button class="dm-btn dm-btn--primary dm-btn--sm" type="button" id="dmBizRefresh" style="margin-top:14px">' + esc(t('btn_refresh')) + '</button>' +
        '</div>';
    }

    function noMatchHtml() {
        return '<div class="dm-empty" style="grid-column:1/-1">' +
            '<h3>' + esc(t('no_match_title')) + '</h3>' +
            '<p>' + br(t('no_match_text', { query: query })) + '</p>' +
        '</div>';
    }

    function render() {
        var list = filtered();
        var cEl = document.getElementById('dmBizCount');
        if (cEl) cEl.textContent = t('businesses_registered', { count: list.length });
        var bEl = document.getElementById('dmBizCards');
        if (!bEl) return;
        if (!businesses.length) {
            bEl.innerHTML = emptyHtml();
            var rb = document.getElementById('dmBizRefresh');
            if (rb) rb.addEventListener('click', load);
            return;
        }
        if (!list.length) { bEl.innerHTML = noMatchHtml(); return; }
        bEl.innerHTML = list.map(card).join('');
    }

    function load() {
        fetch('/api/businesses', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (list) {
                businesses = Array.isArray(list) ? list : [];
                render();
            })
            .catch(function () {
                var bEl = document.getElementById('dmBizCards');
                if (bEl) bEl.innerHTML = '<div class="dm-empty" style="grid-column:1/-1"><h3>' + esc(t('site_common.error_generic')) + '</h3></div>';
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var search = document.getElementById('dmBizSearch');
        if (search) search.addEventListener('input', function (e) { query = e.target.value; render(); });
        load();
    });
    if (window.DM) DM.onChange(function () { render(); });
})();
</script>
</body>
</html>
@endverbatim
