@include('partials.dm-locale')
@include('partials.site-head', ['titleKey' => 'site_businesses.page_title', 'metaKey' => 'site_businesses.meta_description', 'titleFallback' => 'Businesses - DukaMkononi'])
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.dm-voice')
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
    /* Capability #12 - trust summary panel */
    .trust-btn { margin-top: 2px; }
    .trust-panel { display: none; border-top: 1px solid var(--dm-line); margin-top: 12px; padding-top: 12px; }
    .trust-answer { background: #f8fafc; border: 1px solid var(--dm-line); border-radius: 12px; padding: 11px 13px; font-size: 14px; line-height: 1.55; color: var(--dm-ink); }
    .trust-block { margin-top: 10px; }
    .trust-lbl { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--dm-muted); margin-bottom: 5px; }
    .trust-line { font-size: 13px; color: var(--dm-ink); line-height: 1.65; }
    .trust-review { border: 1px solid var(--dm-line); border-radius: 10px; padding: 9px 11px; margin-bottom: 7px; font-size: 13px; color: var(--dm-ink); }
    .trust-review-head { display: flex; gap: 8px; align-items: baseline; flex-wrap: wrap; margin-bottom: 3px; }
    .trust-stars { color: #f59e0b; letter-spacing: 1px; font-size: 12px; }
    .trust-link { display: inline-block; margin-top: 6px; font-size: 12.5px; font-weight: 700; color: var(--dm-primary); }
    .trust-limits { margin-top: 10px; font-size: 11.5px; color: var(--dm-muted); line-height: 1.6; }
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
@include('partials.public-concierge')
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
            '<button type="button" class="dm-btn dm-btn--ghost dm-btn--sm dm-btn--block trust-btn" data-trust="' + esc(b.id) + '">✨ ' + esc(t('site_businesses.trust_btn')) + '</button>' +
            '<div class="trust-panel" data-trust-panel="' + esc(b.id) + '"></div>' +
        '</article>';
    }

    /* ---------------- capability #12 - trust summary ---------------- */
    var trustCache = {}; // business id -> payload
    var trustLocale = null;

    function dmLocale() {
        if (window.DM && DM.locale) return DM.locale();
        return document.documentElement.getAttribute('data-dm-locale') || 'sw';
    }

    function trustPanelEl(id) { return document.querySelector('[data-trust-panel="' + id + '"]'); }

    function toggleTrust(id, btn) {
        var panel = trustPanelEl(id);
        if (!panel) return;
        if (panel.style.display === 'block') { panel.style.display = 'none'; return; }

        panel.style.display = 'block';
        if (trustCache[id] && trustLocale === dmLocale()) { renderTrust(panel, trustCache[id]); return; }

        btn.disabled = true;
        panel.innerHTML = '<p class="dm-muted">' + esc(t('site_businesses.trust_loading')) + '</p>';
        fetch('/api/ai/trust/summary?business_id=' + encodeURIComponent(id) + '&locale=' + dmLocale(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                btn.disabled = false;
                if (!res.ok || !res.d || res.d.success !== true) {
                    panel.innerHTML = '<p class="dm-muted">' + esc((res.d && res.d.error) || t('site_common.error_generic')) + '</p>';
                    return;
                }
                trustCache[id] = res.d;
                trustLocale = dmLocale();
                renderTrust(panel, res.d);
            })
            .catch(function () {
                btn.disabled = false;
                panel.innerHTML = '<p class="dm-muted">' + esc(t('site_common.error_generic')) + '</p>';
            });
    }

    function trustStars(rating) {
        var s = '';
        var n = parseInt(rating, 10);
        for (var i = 0; i < n && i < 5; i++) s += '\u2605';
        return s;
    }

    function renderTrust(panel, d) {
        var rev = d.reviews || {};
        var html = '<div class="trust-answer">' + esc((d.summary && d.summary.text) || '') + '</div>';

        if ((d.facts || []).length) {
            html += '<div class="trust-block"><span class="trust-lbl">' + esc(t('site_businesses.trust_facts')) + '</span>';
            d.facts.forEach(function (line) { html += '<div class="trust-line">' + esc(line) + '</div>'; });
            html += '</div>';
        }

        if ((d.opinions || []).length) {
            html += '<div class="trust-block"><span class="trust-lbl">' + esc(t('site_businesses.trust_opinions')) + '</span>';
            d.opinions.forEach(function (line) { html += '<div class="trust-line">' + esc(line) + '</div>'; });
            html += '</div>';
        }

        if ((rev.items || []).length) {
            html += '<div class="trust-block"><span class="trust-lbl">' + esc(t('site_businesses.trust_reviews_label')) + '</span>';
            rev.items.forEach(function (r) {
                html += '<div class="trust-review">' +
                    '<div class="trust-review-head"><b>' + esc(r.display_name || t('site_businesses.trust_anonymous')) + '</b>' +
                    '<span class="trust-stars">' + esc(trustStars(r.rating)) + '</span>' +
                    '<span class="dm-muted">' + esc(String(r.published_at || '').slice(0, 10)) + '</span></div>' +
                    '<div class="dm-wrap-anywhere">' + esc(r.body || '') + '</div></div>';
            });
            html += '<a class="trust-link" href="/#reviews">' + esc(t('site_businesses.trust_view_all')) + '</a></div>';
        }

        if ((d.limitations || []).length) {
            html += '<div class="trust-limits">';
            d.limitations.forEach(function (line) { html += '<div>' + esc(line) + '</div>'; });
            html += '</div>';
        }

        panel.innerHTML = html;
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
        Array.prototype.forEach.call(bEl.querySelectorAll('.trust-btn'), function (btn) {
            btn.addEventListener('click', function () { toggleTrust(btn.getAttribute('data-trust') || '', btn); });
        });
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
