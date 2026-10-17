{{--
    Dukamkononi website theme selector (public + authenticated portal pages).

    Provides the floating paintbrush button and its two-card panel:

      White Professional       - the existing Duka Mkononi design (default)
      Royal Champagne Cream    - warm cream / ivory / royal gold)

    Placement variants:
      default                  fixed, directly under the floating language
                               button (public site pages)
      ['dmThemePlacement' => 'home']
                               in-flow under home's own top-right language
                               switcher, mirroring that layout instead of the
                               floating rail
      ['dmThemePlacement' => 'bottom']
                               fixed bottom-right corner on the authenticated
                               portal pages (mteja / muuzaji / msimamizi /
                               system_admin), whose left edge is owned by the
                               fixed sidebar and which hide the floating
                               language rail; the panel opens upward

    Activation: window.DMTheme (public/js/dm-theme.js) validates, applies
    <html data-theme>, persists to localStorage["dukamkononi_theme"] and — when
    a session token exists — best-effort syncs to PUT /api/user/theme.

    Labels are translated through the existing DM runtime
    (data-i18n keys under `ui.*` in locales.json); display names are brand
    literals and are never translated. No emojis: icons are inline SVG, same
    convention as the language widget.
--}}
@php
    $dmThemePlacement = $dmThemePlacement ?? 'float';
@endphp
@verbatim
<style>
    /* ===== Floating theme selector (Dukamkononi website theme system) ===== */
    .dm-theme-widget {
        position: fixed;
        left: 18px;
        /* The language button is 58px tall centred at 50%; place this one
           directly below it with a 12px gap: (50% - 29px) + 58px + 12px. */
        top: calc(50% + 41px);
        z-index: 9999;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    .dm-theme-main {
        position: relative;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1px;
        background: linear-gradient(135deg, #8b6f23, #6e591b);
        color: #fff;
        box-shadow: 0 10px 24px rgba(139, 111, 35, .32), 0 0 0 4px rgba(255, 255, 255, .7);
        transition: box-shadow .3s ease, transform .16s ease;
    }

    .dm-theme-main:hover {
        box-shadow: 0 14px 30px rgba(139, 111, 35, .42), 0 0 0 4px rgba(255, 255, 255, .95);
        transform: translateY(-1px);
    }

    .dm-theme-main:focus-visible {
        outline: 3px solid var(--dm-focus, rgba(30, 58, 138, .45));
        outline-offset: 3px;
    }

    .dm-theme-main svg { width: 26px; height: 26px; }

    /* Panel opens beside the control rail, like the language menu, sized so it
       always fits the viewport (scrolls when short). */
    .dm-theme-panel {
        position: fixed;
        left: 88px;
        top: 50%;
        transform: translateY(-50%) translateX(-10px);
        width: min(340px, calc(100vw - 108px));
        max-height: 82vh;
        overflow-y: auto;
        background: var(--dm-card, #fff);
        color: var(--dm-ink, #1e293b);
        border: 1px solid var(--dm-line, #e2e8f0);
        border-radius: 16px;
        box-shadow: 0 18px 50px rgba(15, 23, 42, .18);
        padding: 14px;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .22s ease, visibility .22s ease, transform .22s ease;
    }

    .dm-theme-widget.open .dm-theme-panel {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: translateY(-50%) translateX(0);
    }

    .dm-theme-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
        padding: 0 2px;
    }

    .dm-theme-title {
        font-size: 14px;
        font-weight: 700;
        letter-spacing: .01em;
        color: var(--dm-ink, #1e293b);
    }

    .dm-theme-cards { display: grid; gap: 10px; }

    .dm-theme-card {
        position: relative;
        display: grid;
        gap: 8px;
        text-align: left;
        padding: 12px;
        border-radius: 12px;
        border: 2px solid var(--dm-line, #e2e8f0);
        background: var(--dm-bg, #f8fafc);
        color: inherit;
        font-family: inherit;
        cursor: pointer;
        transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease;
    }

    .dm-theme-card:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15, 23, 42, .1); }

    .dm-theme-card:focus-visible {
        outline: 3px solid var(--dm-focus, rgba(30, 58, 138, .45));
        outline-offset: 2px;
    }

    .dm-theme-card[aria-checked="true"] {
        border-color: var(--dm-primary, #1e3a8a);
        box-shadow: 0 0 0 3px var(--dm-focus-soft, rgba(30, 58, 138, .14));
        background: var(--dm-card, #fff);
    }

    /* Selection never relies on color alone: a check badge AND a text label. */
    .dm-theme-check {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        background: var(--dm-primary, #1e3a8a);
        color: #fff;
        box-shadow: 0 2px 6px rgba(15, 23, 42, .25);
    }

    .dm-theme-check svg { width: 14px; height: 14px; }
    .dm-theme-card[aria-checked="true"] .dm-theme-check { display: flex; }

    .dm-theme-name {
        font-size: 14.5px;
        font-weight: 700;
        color: var(--dm-ink, #1e293b);
        padding-right: 30px;
    }

    .dm-theme-desc {
        font-size: 12.5px;
        line-height: 1.5;
        color: var(--dm-muted, #64748b);
        overflow-wrap: anywhere;
    }

    .dm-theme-status {
        display: none;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--dm-primary, #1e3a8a);
    }

    .dm-theme-card[aria-checked="true"] .dm-theme-status { display: inline-flex; }

    /* ---- Visual preview: a mini page mock built from each theme's REAL
       colors (not live variables, so the card always shows the OTHER
       theme's palette no matter which theme is active). ---- */
    .dm-preview { display: grid; gap: 6px; border-radius: 10px; padding: 10px; border: 1px solid transparent; }

    .dm-preview--white { background: #f8fafc; border-color: #e2e8f0; }
    .dm-preview--royal { background: #f4e8d0; border-color: #d9d8cf; }

    .dm-preview-row { display: flex; gap: 6px; align-items: stretch; }

    .dm-preview-card {
        flex: 1;
        display: grid;
        gap: 5px;
        padding: 8px;
        border-radius: 8px;
        border: 1px solid transparent;
    }

    .dm-preview--white .dm-preview-card { background: #ffffff; border-color: #e2e8f0; }
    .dm-preview--royal .dm-preview-card { background: #fcfbf7; border-color: #d9d8cf; }

    .dm-preview-line { height: 6px; border-radius: 4px; }
    .dm-preview-line--title { width: 70%; }
    .dm-preview--white .dm-preview-line { background: #1e293b; }
    .dm-preview--white .dm-preview-line--sub { background: #64748b; width: 90%; }
    .dm-preview--royal .dm-preview-line { background: #171717; }
    .dm-preview--royal .dm-preview-line--sub { background: #5f636e; width: 90%; }

    .dm-preview-btn {
        margin-top: 3px;
        height: 14px;
        width: 58%;
        border-radius: 999px;
    }

    .dm-preview--white .dm-preview-btn { background: #1e3a8a; }
    .dm-preview--royal .dm-preview-btn { background: #8b6f23; }

    .dm-preview-dots { display: flex; gap: 5px; }

    .dm-preview-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 1px solid rgba(15, 23, 42, .14);
    }

    /* Mobile: mirror the language widget's tightened rail. */
    @media (max-width: 600px) {
        .dm-theme-widget { left: 10px; top: calc(50% + 35px); }
        .dm-theme-main { width: 50px; height: 50px; }
        .dm-theme-main svg { width: 22px; height: 22px; }
        .dm-theme-panel { left: 70px; width: min(340px, calc(100vw - 80px)); padding: 12px; }
    }

    /* RTL (Urdu): mirror the rail exactly like the language widget. */
    [dir="rtl"] .dm-theme-widget { left: auto; right: 18px; }
    [dir="rtl"] .dm-theme-panel { left: auto; right: 88px; transform: translateY(-50%) translateX(10px); }
    [dir="rtl"] .dm-theme-widget.open .dm-theme-panel { transform: translateY(-50%) translateX(0); }
    @media (max-width: 600px) {
        [dir="rtl"] .dm-theme-widget { right: 10px; }
        [dir="rtl"] .dm-theme-panel { right: 70px; }
    }

    /* home variant: in-flow under that page's own top-right language pill,
       stacked below it (this page hides the floating rail on purpose). */
    .dm-theme-widget--home {
        position: relative;
        display: flex;
        justify-content: flex-end;
        margin-top: 10px;
    }

    .dm-theme-widget--home .dm-theme-main {
        width: auto;
        height: 40px;
        border-radius: 999px;
        padding: 0 16px;
        flex-direction: row;
        gap: 8px;
        background: transparent;
        color: var(--pg-text, #2c3e50);
        border: 1px solid var(--pg-line, #e0e0e0);
        box-shadow: none;
    }

    .dm-theme-widget--home .dm-theme-main:hover {
        background: var(--pg-soft, #f8f9fa);
        box-shadow: none;
        transform: none;
    }

    .dm-theme-widget--home .dm-theme-main svg { width: 18px; height: 18px; }

    .dm-theme-widget--home .dm-theme-main .dm-theme-home-label {
        font-size: 13.5px;
        font-weight: 600;
    }

    .dm-theme-widget--home .dm-theme-panel {
        position: absolute;
        right: 0;
        left: auto;
        top: calc(100% + 10px);
        bottom: auto;
        transform: translateY(6px);
        width: min(340px, calc(100vw - 40px));
        max-height: calc(100vh - 150px);
    }

    .dm-theme-widget--home.open .dm-theme-panel { transform: translateY(0); }

    @media (max-width: 600px) {
        .dm-theme-widget--home .dm-theme-panel { right: 0; width: min(340px, calc(100vw - 32px)); }
    }

    /* bottom variant: fixed in the bottom-right corner on authenticated
       portal pages (mteja / muuzaji / msimamizi / system_admin). Their left
       edge is occupied by a fixed sidebar and they hide the floating
       language rail, so the rail-hugging placement cannot be used here; the
       panel opens upward above the trigger. */
    .dm-theme-widget--bottom {
        left: auto;
        right: 16px;
        top: auto;
        bottom: 16px;
    }

    .dm-theme-widget--bottom .dm-theme-panel {
        left: auto;
        right: 16px;
        top: auto;
        bottom: 84px;
        width: min(340px, calc(100vw - 32px));
        transform: translateY(8px);
    }

    .dm-theme-widget--bottom.open .dm-theme-panel {
        transform: translateY(0);
    }

    @media (max-width: 600px) {
        .dm-theme-widget--bottom { right: 12px; bottom: 12px; }
        .dm-theme-widget--bottom .dm-theme-panel {
            right: 12px;
            bottom: 76px;
            width: min(340px, calc(100vw - 24px));
        }
    }

    /* RTL (Urdu): mirror the corner exactly like the public rail. */
    [dir="rtl"] .dm-theme-widget--bottom { right: auto; left: 16px; }
    [dir="rtl"] .dm-theme-widget--bottom .dm-theme-panel { right: auto; left: 16px; }
    /* The generic RTL open-state rule (translateY(-50%)) is more specific
       than the bottom open-state rule; re-state it here so the upward-
       opening panel is not shifted by half its height in RTL. */
    [dir="rtl"] .dm-theme-widget--bottom.open .dm-theme-panel { transform: translateY(0); }
    @media (max-width: 600px) {
        [dir="rtl"] .dm-theme-widget--bottom { left: 12px; }
        [dir="rtl"] .dm-theme-widget--bottom .dm-theme-panel { left: 12px; }
    }

    /* Respect reduced motion: state changes without animation. */
    @media (prefers-reduced-motion: reduce) {
        .dm-theme-panel,
        .dm-theme-card,
        .dm-theme-main { transition: none !important; transform: none !important; }
        .dm-theme-widget.open .dm-theme-panel { transform: none !important; }
        [dir="rtl"] .dm-theme-widget.open .dm-theme-panel { transform: none !important; }
        .dm-theme-widget--home .dm-theme-panel,
        .dm-theme-widget--home.open .dm-theme-panel { transform: none !important; }
        .dm-theme-widget--bottom .dm-theme-panel,
        .dm-theme-widget--bottom.open .dm-theme-panel { transform: none !important; }
    }
</style>
@endverbatim

<div class="dm-theme-widget dm-theme-widget--{{ $dmThemePlacement }}" id="dmThemeWidget">
    <button type="button" class="dm-theme-main" id="dmThemeToggle" aria-haspopup="true" aria-expanded="false"
            aria-label="Badilisha mandhari" data-i18n="ui.change_theme" data-i18n-attr="aria-label">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"></path>
            <path d="M6 16.5c-1.5 0-3 1-3 2.6 0 1.3-1 2.4-3 2.9 1.4 1.4 3.4 2.1 5.4 2.1 3 0 5.6-2 5.6-4.6 0-1.6-1.3-3-3-3Z" transform="translate(1.5 -4.4) scale(.92)"></path>
        </svg>
        @if ($dmThemePlacement === 'home')
            <span class="dm-theme-home-label" data-i18n="ui.change_theme">Badilisha mandhari</span>
        @endif
    </button>

    <div class="dm-theme-panel" id="dmThemePanel" role="dialog" aria-label="Theme" data-i18n="ui.theme_panel_title"
         data-i18n-attr="aria-label">
        <div class="dm-theme-head">
            <span class="dm-theme-title" id="dmThemePanelTitle" data-i18n="ui.theme_panel_title">Choose your theme</span>
        </div>

        <div class="dm-theme-cards" role="radiogroup" aria-labelledby="dmThemePanelTitle">

            <button type="button" class="dm-theme-card" role="radio" data-theme-id="white-professional" aria-checked="true">
                <span class="dm-theme-check" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <span class="dm-preview dm-preview--white" aria-hidden="true">
                    <span class="dm-preview-row">
                        <span class="dm-preview-card">
                            <span class="dm-preview-line dm-preview-line--title"></span>
                            <span class="dm-preview-line dm-preview-line--sub"></span>
                            <span class="dm-preview-btn"></span>
                        </span>
                    </span>
                    <span class="dm-preview-dots">
                        <span class="dm-preview-dot" style="background:#f8fafc"></span>
                        <span class="dm-preview-dot" style="background:#ffffff"></span>
                        <span class="dm-preview-dot" style="background:#1e3a8a"></span>
                        <span class="dm-preview-dot" style="background:#1e293b"></span>
                        <span class="dm-preview-dot" style="background:#e2e8f0"></span>
                    </span>
                </span>
                <span class="dm-theme-name">White Professional</span>
                <span class="dm-theme-desc" data-i18n="ui.theme_white_desc">Clean, bright, and familiar. Retains the original Duka Mkononi appearance with professional layouts, neutral surfaces, and existing brand styling.</span>
                <span class="dm-theme-status" data-i18n="ui.theme_selected">Selected</span>
            </button>

            <button type="button" class="dm-theme-card" role="radio" data-theme-id="royal-champagne-cream" aria-checked="false">
                <span class="dm-theme-check" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <span class="dm-preview dm-preview--royal" aria-hidden="true">
                    <span class="dm-preview-row">
                        <span class="dm-preview-card">
                            <span class="dm-preview-line dm-preview-line--title"></span>
                            <span class="dm-preview-line dm-preview-line--sub"></span>
                            <span class="dm-preview-btn"></span>
                        </span>
                    </span>
                    <span class="dm-preview-dots">
                        <span class="dm-preview-dot" style="background:#f4e8d0"></span>
                        <span class="dm-preview-dot" style="background:#fcfbf7"></span>
                        <span class="dm-preview-dot" style="background:#8b6f23"></span>
                        <span class="dm-preview-dot" style="background:#171717"></span>
                        <span class="dm-preview-dot" style="background:#d9d8cf"></span>
                    </span>
                </span>
                <span class="dm-theme-name">Royal Champagne Cream</span>
                <span class="dm-theme-desc" data-i18n="ui.theme_royal_desc">Warm, elegant, and luxurious. Combines soft cream backgrounds, ivory cards, royal gold accents, and refined neutral text for a premium shopping experience.</span>
                <span class="dm-theme-status" data-i18n="ui.theme_selected">Selected</span>
            </button>

        </div>
    </div>
</div>

<script src="/js/dm-theme.js"></script>
@verbatim
<script>
(function (global) {
    'use strict';

    var widget = document.getElementById('dmThemeWidget');
    var toggle = document.getElementById('dmThemeToggle');
    var panel = document.getElementById('dmThemePanel');
    if (!widget || !toggle || !panel || !global.DMTheme) return;

    var cards = Array.prototype.slice.call(panel.querySelectorAll('.dm-theme-card'));

    function paint() {
        var active = DMTheme.current();
        cards.forEach(function (card) {
            card.setAttribute('aria-checked', card.getAttribute('data-theme-id') === active ? 'true' : 'false');
        });
    }

    function open() {
        widget.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        paint();
    }

    function close(focusToggle) {
        widget.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        if (focusToggle) toggle.focus();
    }

    function isOpen() {
        return widget.classList.contains('open');
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        if (isOpen()) close(false);
        else open();
    });

    cards.forEach(function (card, index) {
        card.addEventListener('click', function (e) {
            e.stopPropagation();
            DMTheme.set(card.getAttribute('data-theme-id'));
            paint();
            close(true);
        });

        card.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                e.preventDefault();
                cards[(index + 1) % cards.length].focus();
            } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                e.preventDefault();
                cards[(index - 1 + cards.length) % cards.length].focus();
            }
        });
    });

    /* Close on outside pointer (touch included — click events bubble). */
    document.addEventListener('click', function (e) {
        if (isOpen() && !widget.contains(e.target)) close(false);
    });

    /* Escape closes and returns focus to the trigger. */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) close(true);
    });

    /* Keyboard users tabbing out of the panel dismiss it. */
    widget.addEventListener('focusout', function (e) {
        if (isOpen() && !widget.contains(e.relatedTarget)) close(false);
    });

    /* Keep the indicator in step if the theme changes elsewhere on the page. */
    if (DMTheme.onChange) DMTheme.onChange(paint);

    paint();
})(window);
</script>
@endverbatim