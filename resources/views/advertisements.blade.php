@include('partials.dm-locale')
@include('partials.site-head', ['titleKey' => 'site_ads.page_title', 'metaKey' => 'site_ads.meta_description', 'titleFallback' => 'Advertisements - DukaMkononi'])
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.site-navbar')
@verbatim
<style>
    .ad-card { overflow: hidden; display: flex; flex-direction: column; }
    .ad-media { aspect-ratio: 16 / 10; background: #f1f5f9; }
    .ad-media img, .ad-media video { width: 100%; height: 100%; object-fit: cover; }
    .ad-body { padding: 16px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
</style>
<main class="dm-section">
    <div class="dm-container">
        <div class="dm-section-head">
            <div class="dm-eyebrow" data-i18n="site_nav.advertisements">Advertisements</div>
            <h1 class="dm-h2" data-i18n="site_ads.title">Advertisements</h1>
            <p class="dm-lead" style="margin-top:10px" data-i18n="site_ads.sub">Offers and adverts posted on Duka Mkononi.</p>
        </div>
        <div class="dm-grid dm-grid--business" id="dmAds">
            <div class="dm-card dm-skeleton" style="height:260px"></div>
            <div class="dm-card dm-skeleton" style="height:260px"></div>
            <div class="dm-card dm-skeleton" style="height:260px"></div>
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
        fetch('/api/shop/advertisements', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (list) {
                var box = document.getElementById('dmAds');
                if (!Array.isArray(list) || !list.length) {
                    box.innerHTML = '<div class="dm-empty" style="grid-column:1/-1">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 20h8"/></svg>' +
                        '<h3>' + esc(t('site_ads.empty')) + '</h3><p>' + esc(t('site_ads.empty_hint')) + '</p></div>';
                    return;
                }
                box.innerHTML = '';
                list.forEach(function (a) {
                    var media = '';
                    if (a.media_url) {
                        media = (a.media_type === 'video')
                            ? '<video src="' + esc(a.media_url) + '" controls preload="metadata"></video>'
                            : '<img src="' + esc(a.thumbnail_url || a.media_url) + '" alt="' + esc(a.title || '') + '" loading="lazy">';
                    }
                    var card = document.createElement('article');
                    card.className = 'dm-card ad-card';
                    card.innerHTML = '<div class="ad-media">' + media + '</div>' +
                        '<div class="ad-body">' +
                        (a.title ? '<h3 class="dm-h3 dm-wrap-anywhere">' + esc(a.title) + '</h3>' : '') +
                        (a.description ? '<p class="dm-muted dm-wrap-anywhere">' + esc(a.description) + '</p>' : '') +
                        '</div>';
                    box.appendChild(card);
                });
            })
            .catch(function () {
                document.getElementById('dmAds').innerHTML = '<div class="dm-empty" style="grid-column:1/-1"><h3>' + esc(t('site_common.error_generic')) + '</h3></div>';
            });
    });
})();
</script>
</body>
</html>
@endverbatim
