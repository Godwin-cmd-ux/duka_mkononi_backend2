@include('partials.dm-locale')
@include('partials.site-head', ['titleFallback' => 'DukaMkononi | Your online marketplace'])
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.site-navbar')
@verbatim
<style>
    .hero { background: radial-gradient(1100px 420px at 80% -10%, #dbeafe 0%, transparent 60%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border-bottom: 1px solid var(--dm-line); }
    .hero-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 40px; align-items: center; padding: 64px 0; }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 26px; }
    .hero-stats { display: flex; gap: 28px; margin-top: 34px; flex-wrap: wrap; }
    .hero-stat b { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 26px; color: var(--dm-primary); display: block; }
    .hero-stat span { font-size: 13px; color: var(--dm-muted); }
    .hero-visual { border-radius: 20px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, .16); border: 1px solid var(--dm-line); background: radial-gradient(circle at 50% 42%, #dbeafe 0%, #ffffff 72%); display: grid; place-items: center; }
    .hero-visual img { width: 100%; height: 100%; object-fit: contain; }
    .biz-card { display: flex; flex-direction: column; gap: 12px; padding: 18px; transition: transform .16s ease, box-shadow .16s ease; }
    .biz-card:hover { transform: translateY(-3px); box-shadow: var(--dm-shadow); }
    .biz-logo { width: 54px; height: 54px; border-radius: 14px; background: #dbeafe; color: var(--dm-primary); display: grid; place-items: center; font-weight: 800; overflow: hidden; }
    .biz-logo img { width: 100%; height: 100%; object-fit: cover; }
    .account-card { padding: 26px 22px; display: flex; flex-direction: column; gap: 10px; }
    .account-icon { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: #dbeafe; color: var(--dm-primary); }
    .story { background: #fff; border: 1px solid var(--dm-line); border-radius: var(--dm-radius); padding: 36px; box-shadow: var(--dm-shadow-sm); }
    .review-card { padding: 22px; display: flex; flex-direction: column; gap: 12px; }
    .stars { color: var(--dm-accent); letter-spacing: 2px; font-size: 15px; }
    .contact-grid { display: grid; grid-template-columns: 1fr 1.1fr; gap: 32px; align-items: start; }
    .contact-info li { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--dm-line); }
    .contact-info svg { width: 20px; height: 20px; color: var(--dm-primary); flex: 0 0 auto; }
    @media (max-width: 860px) {
        .hero-grid { grid-template-columns: 1fr; padding: 40px 0; }
        .hero-visual { order: -1; }
        .contact-grid { grid-template-columns: 1fr; }
    }
</style>

<main>
    <!-- Hero -->
    <section class="hero">
        <div class="dm-container hero-grid">
            <div>
                <div class="dm-eyebrow" data-i18n="site_home.hero_eyebrow">Duka Mkononi</div>
                <h1 class="dm-h1" data-i18n="site_home.hero_title">Shop from trusted businesses across Tanzania</h1>
                <p class="dm-lead" style="margin-top:16px" data-i18n="site_home.hero_lead">Duka Mkononi connects customers with businesses and the products they sell, so finding and ordering what you need is simple.</p>
                <div class="hero-actions">
                    <a class="dm-btn dm-btn--primary" href="/shop">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span data-i18n="site_home.cta_shop">Start Shopping</span>
                    </a>
                    <a class="dm-btn dm-btn--ghost" href="/businesses">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
                        <span data-i18n="site_home.cta_businesses">Explore Businesses</span>
                    </a>
                    <a class="dm-btn dm-btn--accent" href="https://expo.dev/artifacts/eas/9TKXD6b4FGjwYZDZos8OcAWmuyY4N4tvgO397xOsUu0.apk" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                        <span data-i18n="site_home.cta_download">Download the App</span>
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat"><b id="dmStatBiz">0</b><span data-i18n="site_home.stat_businesses">Businesses</span></div>
                    <div class="hero-stat"><b id="dmStatProd">0</b><span data-i18n="site_home.stat_products">Products</span></div>
                </div>
            </div>
            <div class="hero-visual">
                <img src="/logo.png" alt="Duka Mkononi" width="854" height="858" decoding="async">
            </div>
        </div>
    </section>

    <!-- Shop by business -->
    <section class="dm-section" id="businesses">
        <div class="dm-container">
            <div class="dm-section-head">
                <div class="dm-eyebrow" data-i18n="site_home.shop_by_business_eyebrow">Browse</div>
                <h2 class="dm-h2" data-i18n="site_home.shop_by_business">Shop by Business</h2>
                <p class="dm-lead" style="margin-top:10px" data-i18n="site_home.shop_by_business_sub">Pick a business to see only its products.</p>
            </div>
            <div class="dm-grid dm-grid--business" id="dmHomeBiz">
                <div class="dm-card dm-skeleton" style="height:150px"></div>
                <div class="dm-card dm-skeleton" style="height:150px"></div>
                <div class="dm-card dm-skeleton" style="height:150px"></div>
            </div>
        </div>
    </section>

    <!-- Our story -->
    <section class="dm-section dm-section--tight">
        <div class="dm-container">
            <div class="story">
                <h2 class="dm-h2" data-i18n="site_home.our_story_title">Our Story</h2>
                <p class="dm-lead" style="margin-top:14px" data-i18n="site_home.our_story_1">Duka Mkononi was built to connect customers with businesses and make product discovery easier. Instead of searching through scattered messages, customers can see what local businesses offer and order directly.</p>
                <p class="dm-lead" style="margin-top:12px" data-i18n="site_home.our_story_2">For businesses, it is a straightforward way to reach more customers and manage their products and orders in one place.</p>
            </div>
        </div>
    </section>

    <!-- Accounts -->
    <section class="dm-section dm-section--tight" id="accounts">
        <div class="dm-container">
            <div class="dm-section-head">
                <div class="dm-eyebrow" data-i18n="site_home.accounts_eyebrow">Accounts</div>
                <h2 class="dm-h2" data-i18n="site_home.accounts_title">Available Accounts</h2>
                <p class="dm-lead" style="margin-top:10px" data-i18n="site_home.accounts_sub">Duka Mkononi works with the roles the platform already supports.</p>
            </div>
            <div class="dm-grid dm-grid--accounts">
                <div class="dm-card account-card">
                    <div class="account-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6z"/></svg></div>
                    <h3 class="dm-h3" data-i18n="site_home.account_msimamizi_title">Msimamizi</h3>
                    <p class="dm-muted" data-i18n="site_home.account_msimamizi_desc">Administrative account for authorized business management.</p>
                </div>
                <div class="dm-card account-card">
                    <div class="account-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v4H3z"/><path d="M5 7v13h14V7"/><path d="M9 11h6"/></svg></div>
                    <h3 class="dm-h3" data-i18n="site_home.account_muuzaji_title">Muuzaji</h3>
                    <p class="dm-muted" data-i18n="site_home.account_muuzaji_desc">Seller account for managing the seller's authorized products and orders.</p>
                </div>
                <div class="dm-card account-card">
                    <div class="account-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
                    <h3 class="dm-h3" data-i18n="site_home.account_mteja_title">Mteja</h3>
                    <p class="dm-muted" data-i18n="site_home.account_mteja_desc">Customer experience for discovering products and shopping.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Reviews (rendered only when published reviews exist) -->
    <section class="dm-section dm-section--tight" id="reviews" style="display:none">
        <div class="dm-container">
            <div class="dm-section-head">
                <div class="dm-eyebrow" data-i18n="site_home.reviews_eyebrow">Reviews</div>
                <h2 class="dm-h2" data-i18n="site_home.reviews_title">Customer Reviews</h2>
            </div>
            <div class="dm-grid dm-grid--business" id="dmHomeReviews"></div>
        </div>
    </section>

    <!-- Contact -->
    <section class="dm-section" id="contact">
        <div class="dm-container">
            <div class="dm-section-head">
                <div class="dm-eyebrow" data-i18n="site_nav.contact">Contact</div>
                <h2 class="dm-h2" data-i18n="site_home.contact_title">Contact Us</h2>
                <p class="dm-lead" style="margin-top:10px" data-i18n="site_home.contact_sub">Send us a message and we will get back to you.</p>
            </div>
            <div class="contact-grid">
                <ul class="contact-info" style="list-style:none">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v14H4z"/><path d="m4 6 8 6 8-6"/></svg><a href="mailto:info@dukamkononi.com">info@dukamkononi.com</a></li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.6a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.5-1.2a2 2 0 0 1 2.1-.5c.8.3 1.7.6 2.6.7a2 2 0 0 1 1.7 2z"/></svg><a href="tel:0757071967">0757071967</a></li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.6L3 21l1.9-5.6A8.5 8.5 0 1 1 21 11.5z"/></svg><a href="https://wa.me/255757071967" target="_blank" rel="noopener" data-i18n="site_home.contact_whatsapp">Chat on WhatsApp</a></li>
                </ul>

                <form class="dm-card" style="padding:26px" id="dmContactForm" novalidate>
                    <div id="dmContactAlert"></div>
                    <div class="dm-field">
                        <label class="dm-label" for="dmcEmail" data-i18n="site_home.form_email">Email address</label>
                        <input class="dm-input" type="email" id="dmcEmail" required autocomplete="email">
                        <span class="dm-error" id="dmcEmailErr"></span>
                    </div>
                    <div class="dm-field">
                        <label class="dm-label" for="dmcPhone" data-i18n="site_home.form_phone">Phone number</label>
                        <input class="dm-input" type="tel" id="dmcPhone" required autocomplete="tel" placeholder="07XXXXXXXX">
                        <span class="dm-error" id="dmcPhoneErr"></span>
                    </div>
                    <div class="dm-field">
                        <label class="dm-label" for="dmcSubject" data-i18n="site_home.form_subject">Subject</label>
                        <input class="dm-input" type="text" id="dmcSubject" required maxlength="150">
                        <span class="dm-error" id="dmcSubjectErr"></span>
                    </div>
                    <div class="dm-field">
                        <label class="dm-label" for="dmcMessage" data-i18n="site_home.form_message">Message</label>
                        <textarea class="dm-textarea" id="dmcMessage" required maxlength="5000"></textarea>
                        <span class="dm-error" id="dmcMessageErr"></span>
                    </div>
                    <button class="dm-btn dm-btn--primary dm-btn--block" type="submit" id="dmcSubmit" data-i18n="site_home.form_send">Send Message</button>
                </form>
            </div>
        </div>
    </section>
</main>
@endverbatim
@include('partials.site-footer')
@verbatim
<script>
(function () {
    'use strict';
    var t = function (k, p) { return window.DM ? DM.t(k, p) : k; };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
    function api(path) { return fetch('/api' + path, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }); }

    function loadBusinesses() {
        api('/shop/businesses').then(function (list) {
            var box = document.getElementById('dmHomeBiz');
            list = Array.isArray(list) ? list : [];
            var totalProducts = list.reduce(function (a, b) { return a + (b.product_count || 0); }, 0);
            document.getElementById('dmStatBiz').textContent = list.length;
            document.getElementById('dmStatProd').textContent = totalProducts;
            if (!list.length) {
                box.innerHTML = '<div class="dm-empty" style="grid-column:1/-1"><h3>' + esc(t('site_home.empty_businesses')) + '</h3></div>';
                return;
            }
            list.slice(0, 8).forEach(function (b) {
                var card = document.createElement('a');
                card.className = 'dm-card biz-card';
                card.href = '/shop?business_id=' + encodeURIComponent(b.id);
                var logo = b.business_logo_url ? '<img src="' + esc(b.business_logo_url) + '" alt="">' : esc((b.business_name || 'D').trim().charAt(0).toUpperCase());
                var cert = b.is_certified ? '<span class="dm-badge dm-badge--ok">' + esc(t('site_businesses.certified_badge')) + '</span>' : '';
                card.innerHTML = '<div style="display:flex;align-items:center;gap:12px"><div class="biz-logo">' + logo + '</div><div style="flex:1"><div class="dm-wrap-anywhere" style="font-weight:700">' + esc(b.business_name || '') + '</div><div class="dm-muted" style="font-size:12.5px">' + esc(b.business_location || '') + '</div></div></div>' +
                    '<div style="display:flex;gap:8px;flex-wrap:wrap">' + cert + '<span class="dm-badge dm-badge--muted">' + (b.product_count || 0) + ' ' + esc(t('site_common.products')) + '</span></div>' +
                    '<span class="dm-btn dm-btn--ghost dm-btn--sm dm-btn--block">' + esc(t('site_home.cta_shop')) + '</span>';
                box.appendChild(card);
            });
        }).catch(function () {
            document.getElementById('dmHomeBiz').innerHTML = '<div class="dm-empty" style="grid-column:1/-1"><h3>' + esc(t('site_common.error_generic')) + '</h3></div>';
        });
    }

    function loadReviews() {
        api('/shop/reviews').then(function (list) {
            if (!Array.isArray(list) || !list.length) return; // section stays hidden
            var section = document.getElementById('reviews');
            var box = document.getElementById('dmHomeReviews');
            section.style.display = '';
            list.forEach(function (r) {
                var stars = '';
                if (r.rating) { for (var i = 0; i < r.rating; i++) stars += '\u2605'; }
                var card = document.createElement('div');
                card.className = 'dm-card review-card';
                card.innerHTML = (stars ? '<div class="stars">' + stars + '</div>' : '') +
                    (r.title ? '<h3 class="dm-h3 dm-wrap-anywhere">' + esc(r.title) + '</h3>' : '') +
                    '<p class="dm-muted dm-wrap-anywhere">' + esc(r.body) + '</p>' +
                    '<div style="font-weight:600;font-size:14px">' + esc(r.display_name || t('site_common.customer')) + '</div>';
                box.appendChild(card);
            });
        }).catch(function () {});
    }

    function contactForm() {
        var form = document.getElementById('dmContactForm');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var alertBox = document.getElementById('dmContactAlert');
            ['Email', 'Phone', 'Subject', 'Message'].forEach(function (f) { document.getElementById('dmc' + f + 'Err').textContent = ''; });
            var payload = {
                email: document.getElementById('dmcEmail').value.trim(),
                phone: document.getElementById('dmcPhone').value.trim(),
                subject: document.getElementById('dmcSubject').value.trim(),
                message: document.getElementById('dmcMessage').value.trim(),
                locale: (window.DM && DM.locale()) || 'sw',
                source: 'website'
            };
            var btn = document.getElementById('dmcSubmit'); btn.disabled = true;
            fetch('/api/inquiries', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload) })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (res) {
                    btn.disabled = false;
                    if (res.ok) {
                        alertBox.innerHTML = '<div class="dm-alert dm-alert--ok">' + esc(t('site_home.form_success')) + '</div>';
                        form.reset();
                        return;
                    }
                    var errs = (res.d && res.d.errors) || {};
                    Object.keys(errs).forEach(function (k) {
                        var el = document.getElementById('dmc' + k.charAt(0).toUpperCase() + k.slice(1) + 'Err');
                        if (el) el.textContent = errs[k];
                    });
                    alertBox.innerHTML = '<div class="dm-alert dm-alert--err">' + esc((res.d && res.d.error) || t('site_home.form_error')) + '</div>';
                })
                .catch(function () { btn.disabled = false; alertBox.innerHTML = '<div class="dm-alert dm-alert--err">' + esc(t('site_common.error_generic')) + '</div>'; });
        });
    }

    document.addEventListener('DOMContentLoaded', function () { loadBusinesses(); loadReviews(); contactForm(); });
    if (window.DM) DM.onChange(function () {});
})();
</script>
</body>
</html>
@endverbatim
