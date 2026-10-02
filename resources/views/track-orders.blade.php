@include('partials.dm-locale')
@include('partials.site-head', ['titleKey' => 'site_track.page_title', 'metaKey' => 'site_track.meta_description', 'titleFallback' => 'Track Orders - DukaMkononi'])
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.site-navbar')
@verbatim
<style>
    .track-form { max-width: 560px; margin: 0 auto; padding: 26px; }
    .track-order { padding: 20px; margin-bottom: 16px; }
    .track-head { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
    .track-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 14px 0; }
    .track-meta div span { display: block; font-size: 12px; color: var(--dm-muted); }
    .track-meta div b { font-size: 15px; }
    .track-items { border-top: 1px solid var(--dm-line); margin-top: 12px; padding-top: 12px; }
    .track-item { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px dashed var(--dm-line); flex-wrap: wrap; }
    .timeline { list-style: none; margin-top: 14px; border-left: 2px solid var(--dm-line); padding-left: 16px; }
    .timeline li { position: relative; padding-bottom: 12px; font-size: 13.5px; }
    .timeline li::before { content: ''; position: absolute; left: -22px; top: 5px; width: 9px; height: 9px; border-radius: 50%; background: var(--dm-primary); }
    .timeline time { display: block; color: var(--dm-muted); font-size: 12px; }
</style>

<main class="dm-section">
    <div class="dm-container">
        <div class="dm-section-head">
            <div class="dm-eyebrow" data-i18n="site_nav.track_orders">Track Orders</div>
            <h1 class="dm-h2" data-i18n="site_track.title">Track your orders</h1>
            <p class="dm-lead" style="margin-top:10px" data-i18n="site_track.sub">Enter the phone number you used when ordering.</p>
        </div>

        <form class="dm-card track-form" id="dmTrackForm" novalidate>
            <div id="dmTrackAlert"></div>
            <div class="dm-field">
                <label class="dm-label" for="dmTrackPhone" data-i18n="site_track.phone_label">Phone number</label>
                <input class="dm-input" type="tel" id="dmTrackPhone" required placeholder="07XXXXXXXX" autocomplete="tel">
                <span class="dm-error" id="dmTrackPhoneErr"></span>
            </div>
            <div class="dm-field">
                <label class="dm-label" for="dmTrackRef" data-i18n="site_track.reference_label">Order reference (optional)</label>
                <input class="dm-input" type="text" id="dmTrackRef" data-i18n="site_track.reference_placeholder" data-i18n-attr="placeholder" placeholder="DM-YYYYMMDD-XXXXXX">
                <span class="dm-hint" data-i18n="site_track.reference_optional">Adding the reference narrows down which order is shown.</span>
            </div>
            <button class="dm-btn dm-btn--primary dm-btn--block" type="submit" id="dmTrackSubmit" data-i18n="site_track.submit">Track my order</button>
        </form>

        <div id="dmTrackResults" style="max-width:760px;margin:30px auto 0"></div>
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
    function money(n) { try { return 'TZS ' + Number(n).toLocaleString('en-US'); } catch (e) { return 'TZS ' + n; } }
    function statusBadge(s) { var map = { pending: 'warn', confirmed: 'info', processing: 'info', ready: 'info', out_for_delivery: 'info', delivered: 'ok', completed: 'ok', cancelled: 'danger' }; return '<span class="dm-badge dm-badge--' + (map[s] || 'muted') + '">' + esc(statusLabel(s)) + '</span>'; }
    function statusLabel(s) { var key = 'muuzaji_orders.status_' + s; var v = t(key); return v === key ? s : v; }

    function submit(e) {
        e.preventDefault();
        var phone = document.getElementById('dmTrackPhone').value.trim();
        var ref = document.getElementById('dmTrackRef').value.trim();
        var alertBox = document.getElementById('dmTrackAlert');
        var results = document.getElementById('dmTrackResults');
        document.getElementById('dmTrackPhoneErr').textContent = '';
        if (phone.replace(/\D/g, '').length < 9) { document.getElementById('dmTrackPhoneErr').textContent = t('site_track.error_phone'); return; }
        var btn = document.getElementById('dmTrackSubmit'); btn.disabled = true;
        results.innerHTML = '<div class="dm-card dm-skeleton" style="height:120px"></div>';
        var q = '/api/orders/track?phone=' + encodeURIComponent(phone) + (ref ? '&reference=' + encodeURIComponent(ref) : '');
        fetch(q, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                btn.disabled = false;
                if (!res.ok) { results.innerHTML = ''; alertBox.innerHTML = '<div class="dm-alert dm-alert--err">' + esc((res.d && res.d.error) || t('site_common.error_generic')) + '</div>'; return; }
                alertBox.innerHTML = '';
                var orders = (res.d && res.d.orders) || [];
                if (!orders.length) {
                    results.innerHTML = '<div class="dm-empty"><h3>' + esc(t('site_track.empty')) + '</h3><p>' + esc(t('site_track.empty_hint')) + '</p></div>';
                    return;
                }
                results.innerHTML = '<h2 class="dm-h3" style="margin-bottom:14px">' + esc(t('site_track.found_title')) + '</h2>' + orders.map(renderOrder).join('');
            })
            .catch(function () { btn.disabled = false; results.innerHTML = ''; alertBox.innerHTML = '<div class="dm-alert dm-alert--err">' + esc(t('site_common.error_generic')) + '</div>'; });
    }

    function renderOrder(o) {
        var items = (o.items || []).map(function (it) {
            return '<div class="track-item"><span class="dm-wrap-anywhere">' + esc(it.product_name) + ' &times; ' + it.quantity + '</span><b>' + money(it.line_total) + '</b></div>';
        }).join('');
        var timeline = (o.timeline || []).map(function (e) {
            return '<li><b>' + esc(statusLabel(e.status)) + '</b>' + (e.note ? '<div class="dm-muted">' + esc(e.note) + '</div>' : '') + '<time>' + esc(fmtDate(e.at)) + '</time></li>';
        }).join('');
        return '<article class="dm-card track-order">' +
            '<div class="track-head"><div><b class="dm-wrap-anywhere">' + esc(o.order_reference) + '</b>' + (o.business_name ? '<div class="dm-muted" style="font-size:13px">' + esc(o.business_name) + '</div>' : '') + '</div>' + statusBadge(o.status) + '</div>' +
            '<div class="track-meta">' +
                '<div><span>' + esc(t('site_track.order_date')) + '</span><b>' + esc(fmtDate(o.order_date)) + '</b></div>' +
                '<div><span>' + esc(t('site_common.total')) + '</span><b>' + money(o.total_amount) + '</b></div>' +
            '</div>' +
            (o.latest_note ? '<div class="dm-alert dm-alert--info">' + esc(t('site_track.latest_note')) + ': ' + esc(o.latest_note) + '</div>' : '') +
            '<div class="track-items">' + items + '</div>' +
            (timeline ? '<ul class="timeline">' + timeline + '</ul>' : '') +
        '</article>';
    }
    function fmtDate(s) { if (!s) return '-'; var d = new Date(s); return isNaN(d) ? String(s) : d.toLocaleString((window.DM && DM.locale()) || 'sw'); }

    document.addEventListener('DOMContentLoaded', function () { document.getElementById('dmTrackForm').addEventListener('submit', submit); });
})();
</script>
</body>
</html>
@endverbatim
