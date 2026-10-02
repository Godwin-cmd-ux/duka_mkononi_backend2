{{--
    Public website top navigation. Website-only: it is never used by the mobile
    application. Eight destinations, all reachable on every screen size.
--}}
<style>
    .dm-nav { position: sticky; top: 0; z-index: 500; background: rgba(255, 255, 255, .92); backdrop-filter: blur(10px); border-bottom: 1px solid var(--dm-line); }
    .dm-nav-inner { display: flex; align-items: center; gap: 16px; min-height: 66px; }
    .dm-brand { display: flex; align-items: center; gap: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--dm-navy); font-size: 18px; letter-spacing: -.01em; }
    .dm-brand-mark {
        width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; color: #fff;
        background: linear-gradient(135deg, var(--dm-primary), #0b4f4a); font-weight: 800; font-size: 15px; box-shadow: 0 6px 14px rgba(15, 118, 110, .28);
    }
    .dm-nav-links { display: flex; align-items: center; gap: 4px; margin-left: auto; }
    .dm-nav-link { padding: 9px 12px; border-radius: 9px; font-size: 14.5px; font-weight: 500; color: var(--dm-ink); }
    .dm-nav-link:hover { background: #f1f5f9; color: var(--dm-primary); }
    .dm-nav-cta { display: flex; align-items: center; gap: 8px; }
    .dm-nav-toggle { display: none; margin-left: auto; width: 44px; height: 44px; border-radius: 10px; border: 1px solid var(--dm-line); background: #fff; cursor: pointer; align-items: center; justify-content: center; }
    .dm-nav-toggle svg { width: 22px; height: 22px; color: var(--dm-ink); }
    @media (max-width: 960px) {
        .dm-nav-links, .dm-nav-cta.desktop { display: none; }
        .dm-nav-toggle { display: inline-flex; }
        .dm-nav-drawer { display: block; position: fixed; inset: 66px 0 0 0; background: #fff; padding: 18px 20px 40px; overflow-y: auto; transform: translateY(-8px); opacity: 0; visibility: hidden; transition: opacity .2s ease, transform .2s ease, visibility .2s; z-index: 490; }
        .dm-nav-drawer.open { opacity: 1; visibility: visible; transform: translateY(0); }
        .dm-nav-drawer a { display: flex; align-items: center; gap: 12px; padding: 14px 12px; border-radius: 12px; font-size: 16px; font-weight: 600; border-bottom: 1px solid var(--dm-line); }
        .dm-nav-drawer a:last-child { border-bottom: none; margin-top: 8px; }
        .dm-nav-drawer svg { width: 20px; height: 20px; color: var(--dm-primary); }
        body.dm-nav-open { overflow: hidden; }
    }
    @media (min-width: 961px) { .dm-nav-drawer { display: none; } }
</style>
<header class="dm-nav">
    <div class="dm-container dm-nav-inner">
        <a class="dm-brand" href="/" aria-label="DukaMkononi">
            <span class="dm-brand-mark" aria-hidden="true">DM</span>
            <span>Duka<span style="color:var(--dm-primary)">Mkononi</span></span>
        </a>

        <nav class="dm-nav-links" aria-label="Main">
            <a class="dm-nav-link" href="/" data-i18n="site_nav.home">Home</a>
            <a class="dm-nav-link" href="/shop" data-i18n="site_nav.shop">Shop</a>
            <a class="dm-nav-link" href="/track-orders" data-i18n="site_nav.track_orders">Track Orders</a>
            <a class="dm-nav-link" href="/advertisements" data-i18n="site_nav.advertisements">Advertisements</a>
            <a class="dm-nav-link" href="/businesses" data-i18n="site_nav.businesses">Businesses</a>
            <a class="dm-nav-link" href="/#contact" data-i18n="site_nav.contact">Contact</a>
        </nav>

        <div class="dm-nav-cta desktop">
            <a class="dm-btn dm-btn--ghost dm-btn--sm" href="/login" data-i18n="site_nav.login">Login</a>
            <a class="dm-btn dm-btn--primary dm-btn--sm" href="https://expo.dev/artifacts/eas/9TKXD6b4FGjwYZDZos8OcAWmuyY4N4tvgO397xOsUu0.apk" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                <span data-i18n="site_nav.download_app">Download App</span>
            </a>
        </div>

        <button class="dm-nav-toggle" id="dmNavToggle" aria-label="Menu" aria-expanded="false" aria-controls="dmNavDrawer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
    </div>

    <div class="dm-nav-drawer" id="dmNavDrawer">
        <a href="/"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg><span data-i18n="site_nav.home">Home</span></a>
        <a href="/shop"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg><span data-i18n="site_nav.shop">Shop</span></a>
        <a href="/track-orders"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span data-i18n="site_nav.track_orders">Track Orders</span></a>
        <a href="/advertisements"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 20h8"/></svg><span data-i18n="site_nav.advertisements">Advertisements</span></a>
        <a href="/businesses"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg><span data-i18n="site_nav.businesses">Businesses</span></a>
        <a href="/#contact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v14H4z"/><path d="m4 6 8 6 8-6"/></svg><span data-i18n="site_nav.contact">Contact</span></a>
        <a href="/login"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg><span data-i18n="site_nav.login">Login</span></a>
        <a href="https://expo.dev/artifacts/eas/9TKXD6b4FGjwYZDZos8OcAWmuyY4N4tvgO397xOsUu0.apk" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg><span data-i18n="site_nav.download_app">Download App</span></a>
    </div>
</header>
<script>
(function () {
    var toggle = document.getElementById('dmNavToggle');
    var drawer = document.getElementById('dmNavDrawer');
    if (!toggle || !drawer) return;
    function setOpen(open) {
        drawer.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('dm-nav-open', open);
    }
    toggle.addEventListener('click', function () { setOpen(!drawer.classList.contains('open')); });
    drawer.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 960) setOpen(false); });
})();
</script>
