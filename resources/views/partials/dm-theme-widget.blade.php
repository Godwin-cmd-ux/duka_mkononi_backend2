{{--
    Dukamkononi website theme selector (public + authenticated portal pages).

    Provides the floating paintbrush button and its theme picker (a
    bottom sheet on home that copies the language picker's pattern; a
    panel beside/above the trigger elsewhere):

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

    .dm-theme-cards { display: grid; gap: 8px; }

    /* Cards mirror the home page's language pills (.dm-lang-item): the same
       compact horizontal pill geometry — theme name + radio, nothing else. */
    .dm-theme-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 9px;
        text-align: left;
        padding: 9px 15px;
        border-radius: 999px;
        border: 1px solid var(--dm-line, #e2e8f0);
        background: var(--dm-card, #fff);
        color: var(--dm-ink, #1e293b);
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
        box-shadow: 0 6px 16px rgba(44, 62, 80, .12);
        transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease;
    }

    .dm-theme-card:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(44, 62, 80, .2); }

    .dm-theme-card:focus-visible {
        outline: 3px solid var(--dm-focus, rgba(30, 58, 138, .45));
        outline-offset: 2px;
    }

    /* The radio mirrors the theme it offers: ring = that theme's border,
       fill = its background, inner dot = its accent. Its background and the
       card's text colors shift with the active theme via the shared vars. */
    .dm-theme-radio {
        position: relative;
        flex: 0 0 auto;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid var(--dm-radio-line, #cbd5e1);
        background: var(--dm-radio-bg, #fff);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: border-color .16s ease;
    }

    .dm-theme-radio::after {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--dm-radio-dot, #1e3a8a);
        transform: scale(0);
        transition: transform .16s ease;
    }

    .dm-theme-card[aria-checked="true"] .dm-theme-radio::after { transform: scale(1); }

    /* Selection never relies on color alone: the radio's inner dot is a
       structural change, plus the card ring and aria-checked for AT. */
    .dm-theme-card[aria-checked="true"] {
        border-color: var(--dm-radio-dot, #1e3a8a);
        box-shadow: 0 0 0 3px var(--dm-focus-soft, rgba(30, 58, 138, .14)), 0 6px 16px rgba(44, 62, 80, .12);
        background: var(--dm-card, #fff);
    }

    /* Real colors of each offered theme (never live vars: the card must show
       that theme's palette whichever theme is active). */
    .dm-theme-card[data-theme-id="white-professional"] {
        --dm-radio-bg: #ffffff;
        --dm-radio-line: #cbd5e1;
        --dm-radio-dot: #1e3a8a;
    }

    .dm-theme-card[data-theme-id="royal-champagne-cream"] {
        --dm-radio-bg: #f4e8d0;
        --dm-radio-line: #d9d8cf;
        --dm-radio-dot: #8b6f23;
    }

    .dm-theme-card[data-theme-id="midnight-royal-gold"] {
        --dm-radio-bg: #101010;
        --dm-radio-line: #444444;
        --dm-radio-dot: #ff9f1c;
    }

    .dm-theme-name {
        font-size: 14px;
        font-weight: 600;
        color: inherit;
        overflow: hidden;
        text-overflow: ellipsis;
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

    /* ---- Home opens the SAME way the home language picker opens: an
       overlay fade + bottom sheet sliding up, the header ("Badili
       Mandhari" + "Chagua Mandhari upendayo"), then the theme cards
       listed like the language rows. Geometry copied 1:1 from home's
       .lang-modal-* rules; widgets other than home keep the panel. ---- */
    .dm-theme-modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: flex-end;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }

    .dm-theme-modal.open { opacity: 1; visibility: visible; }

    .dm-theme-sheet {
        width: 100%;
        max-width: 550px;
        background: var(--pg-surface, #ffffff);
        border-top-left-radius: 28px;
        border-top-right-radius: 28px;
        padding: 12px 24px 34px;
        max-height: 75vh;
        display: flex;
        flex-direction: column;
        transform: translateY(100%);
        transition: transform 0.28s cubic-bezier(0.2, 0.9, 0.4, 1);
        box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.1);
    }

    .dm-theme-modal.open .dm-theme-sheet { transform: translateY(0); }

    .dm-theme-modal-handle {
        width: 40px;
        height: 4px;
        border-radius: 2px;
        background: var(--pg-line, #e0e0e0);
        margin: 0 auto 16px;
    }

    .dm-theme-modal-header { text-align: center; margin-bottom: 20px; }

    .dm-theme-modal-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--pg-blue-tint, #e8f4fd);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        color: var(--pg-cta-blue, #3498db);
    }

    .dm-theme-modal-title {
        font-size: 22px;
        font-weight: 700;
        color: var(--pg-text, #2c3e50);
        margin-bottom: 4px;
    }

    .dm-theme-modal-subtitle { font-size: 14px; color: var(--pg-muted, #7f8c8d); }

    .dm-theme-modal-list {
        overflow-y: auto;
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    /* Card rows are sized exactly like the language rows: 14x16 padding,
       12px radius, swatch + name left, radio right. */
    .dm-theme-modal-list .dm-theme-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        padding: 14px 16px;
        border-radius: 12px;
        background: var(--pg-soft, #f8f9fa);
        border: 1.5px solid transparent;
        box-shadow: none;
        font-size: 16px;
        text-align: left;
    }

    .dm-theme-modal-list .dm-theme-card:hover { transform: none; box-shadow: none; }

    .dm-theme-item-left { display: flex; align-items: center; min-width: 0; }

    /* The swatch stands in for the language rows' flag: each theme's own
       background with a corner of its accent. */
    .dm-theme-swatch {
        position: relative;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        margin-right: 14px;
        flex: 0 0 auto;
        border: 1px solid rgba(15, 23, 42, .14);
    }

    .dm-theme-swatch::after {
        content: "";
        position: absolute;
        right: 4px;
        bottom: 4px;
        width: 10px;
        height: 10px;
        border-radius: 3px;
    }

    .dm-theme-swatch--white { background: #f8fafc; }
    .dm-theme-swatch--white::after { background: #1e3a8a; }
    .dm-theme-swatch--royal { background: #f4e8d0; }
    .dm-theme-swatch--royal::after { background: #8b6f23; }
    .dm-theme-swatch--midnight { background: #101010; }
    .dm-theme-swatch--midnight::after { background: #ff9f1c; }

    .dm-theme-modal-list .dm-theme-name { font-size: 16px; font-weight: 600; }

    .dm-theme-modal-list .dm-theme-radio { width: 22px; height: 22px; border-color: var(--pg-line-6, #ccc); }
    .dm-theme-modal-list .dm-theme-radio::after { width: 12px; height: 12px; }
    .dm-theme-modal-list .dm-theme-card[aria-checked="true"] .dm-theme-radio { border-color: var(--dm-radio-dot); }

    /* Selected row: that theme's own tint + accent ring, in its palette (the
       mirror the panel cards already do). */
    .dm-theme-modal-list .dm-theme-card[data-theme-id="white-professional"][aria-checked="true"] {
        background: #e9f0fa;
        border-color: #1e3a8a;
        box-shadow: none;
    }

    .dm-theme-modal-list .dm-theme-card[data-theme-id="white-professional"][aria-checked="true"] .dm-theme-name { color: #1e3a8a; }

    .dm-theme-modal-list .dm-theme-card[data-theme-id="royal-champagne-cream"][aria-checked="true"] {
        background: #f7edd6;
        border-color: #8b6f23;
        box-shadow: none;
    }

    .dm-theme-modal-list .dm-theme-card[data-theme-id="royal-champagne-cream"][aria-checked="true"] .dm-theme-name { color: #8b6f23; }

    .dm-theme-modal-list .dm-theme-card[data-theme-id="midnight-royal-gold"][aria-checked="true"] {
        background: #282213;
        border-color: #d4af37;
        box-shadow: none;
    }

    .dm-theme-modal-list .dm-theme-card[data-theme-id="midnight-royal-gold"][aria-checked="true"] .dm-theme-name { color: #d4af37; }

    /* Actions: same geometry as the language sheet's Funga / Tuma buttons. */
    .dm-theme-modal-actions { display: flex; gap: 12px; }

    .dm-theme-modal-cancel {
        flex: 1;
        height: 50px;
        border: none;
        border-radius: 12px;
        background: var(--pg-pill, #f0f4f8);
        color: var(--pg-muted, #7f8c8d);
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
    }

    .dm-theme-modal-apply {
        flex: 1.5;
        height: 50px;
        border: none;
        border-radius: 12px;
        background: var(--pg-cta-blue, #3498db);
        color: #ffffff;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 8px var(--pg-cta-glow, rgba(52, 152, 219, 0.3));
    }

    .dm-theme-modal-apply:disabled {
        background: var(--pg-cta-disabled, #85c1e9);
        box-shadow: none;
        cursor: default;
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
        .dm-theme-modal,
        .dm-theme-sheet { transition: none !important; transform: none !important; }
        .dm-theme-widget--bottom .dm-theme-panel,
        .dm-theme-widget--bottom.open .dm-theme-panel { transform: none !important; }
    }
</style>
@endverbatim

<div class="dm-theme-widget dm-theme-widget--{{ $dmThemePlacement }}" id="dmThemeWidget">
    <button type="button" class="dm-theme-main" id="dmThemeToggle" aria-haspopup="{{ $dmThemePlacement === 'home' ? 'dialog' : 'true' }}" aria-expanded="false"
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

    @if ($dmThemePlacement === 'home')
        {{-- Home: bottom sheet, identical pattern to the language picker. --}}
        <div class="dm-theme-modal" id="dmThemeModal" aria-hidden="true">
            <div class="dm-theme-sheet" role="dialog" aria-modal="true" aria-labelledby="dmThemeModalTitle">
                <div class="dm-theme-modal-handle"></div>
                <div class="dm-theme-modal-header">
                    <div class="dm-theme-modal-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"></path>
                            <path d="M6 16.5c-1.5 0-3 1-3 2.6 0 1.3-1 2.4-3 2.9 1.4 1.4 3.4 2.1 5.4 2.1 3 0 5.6-2 5.6-4.6 0-1.6-1.3-3-3-3Z" transform="translate(1.5 -4.4) scale(.92)"></path>
                        </svg>
                    </div>
                    <h3 class="dm-theme-modal-title" id="dmThemeModalTitle" data-i18n="ui.theme_modal_title">Badili Mandhari</h3>
                    <p class="dm-theme-modal-subtitle" data-i18n="ui.theme_modal_subtitle">Chagua Mandhari upendayo</p>
                </div>

                <div class="dm-theme-modal-list" role="radiogroup" aria-labelledby="dmThemeModalTitle">

                    <button type="button" class="dm-theme-card" role="radio" data-theme-id="white-professional" aria-checked="true">
                        <span class="dm-theme-item-left">
                            <span class="dm-theme-swatch dm-theme-swatch--white" aria-hidden="true"></span>
                            <span class="dm-theme-name">White Professional</span>
                        </span>
                        <span class="dm-theme-radio" aria-hidden="true"></span>
                    </button>

                    <button type="button" class="dm-theme-card" role="radio" data-theme-id="royal-champagne-cream" aria-checked="false">
                        <span class="dm-theme-item-left">
                            <span class="dm-theme-swatch dm-theme-swatch--royal" aria-hidden="true"></span>
                            <span class="dm-theme-name">Royal Champagne Cream</span>
                        </span>
                        <span class="dm-theme-radio" aria-hidden="true"></span>
                    </button>

                    <button type="button" class="dm-theme-card" role="radio" data-theme-id="midnight-royal-gold" aria-checked="false">
                        <span class="dm-theme-item-left">
                            <span class="dm-theme-swatch dm-theme-swatch--midnight" aria-hidden="true"></span>
                            <span class="dm-theme-name">Midnight Royal Gold</span>
                        </span>
                        <span class="dm-theme-radio" aria-hidden="true"></span>
                    </button>

                </div>

                <div class="dm-theme-modal-actions">
                    <button type="button" class="dm-theme-modal-cancel" id="dmThemeCancel" data-i18n="ui.theme_close">Funga</button>
                    <button type="button" class="dm-theme-modal-apply" id="dmThemeApply">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="m8.5 12.5 2.5 2.5 4.5-5"></path>
                        </svg>
                        <span data-i18n="ui.theme_apply">Tuma</span>
                    </button>
                </div>
            </div>
        </div>
    @else
    <div class="dm-theme-panel" id="dmThemePanel" role="dialog" aria-label="Theme" data-i18n="ui.theme_panel_title"
         data-i18n-attr="aria-label">
        <div class="dm-theme-head">
            <span class="dm-theme-title" id="dmThemePanelTitle" data-i18n="ui.theme_panel_title">Choose your theme</span>
        </div>

        <div class="dm-theme-cards" role="radiogroup" aria-labelledby="dmThemePanelTitle">

            <button type="button" class="dm-theme-card" role="radio" data-theme-id="white-professional" aria-checked="true">
                <span class="dm-theme-radio" aria-hidden="true"></span>
                <span class="dm-theme-name">White Professional</span>
            </button>

            <button type="button" class="dm-theme-card" role="radio" data-theme-id="royal-champagne-cream" aria-checked="false">
                <span class="dm-theme-radio" aria-hidden="true"></span>
                <span class="dm-theme-name">Royal Champagne Cream</span>
            </button>

            <button type="button" class="dm-theme-card" role="radio" data-theme-id="midnight-royal-gold" aria-checked="false">
                <span class="dm-theme-radio" aria-hidden="true"></span>
                <span class="dm-theme-name">Midnight Royal Gold</span>
            </button>

        </div>
    </div>
    @endif
</div>

<script src="/js/dm-theme.js"></script>
@verbatim
<script>
(function (global) {
    'use strict';

    var widget = document.getElementById('dmThemeWidget');
    var toggle = document.getElementById('dmThemeToggle');
    /* home renders the bottom-sheet modal; the other placements render the
       dropdown panel under/above the trigger. */
    var modal = document.getElementById('dmThemeModal');
    var panel = document.getElementById('dmThemePanel');
    var cancel = document.getElementById('dmThemeCancel');
    var apply = document.getElementById('dmThemeApply');
    if (!widget || !toggle || (!modal && !panel) || !global.DMTheme) return;

    var cards = Array.prototype.slice.call(widget.querySelectorAll('.dm-theme-card'));
    var pending = null; /* sheet: the choice waiting for Tuma */

    function checkedId() {
        /* The open sheet shows the pending choice; the dropdown applies on
           tap, so it always shows the live theme. */
        if (modal && modal.classList.contains('open') && pending) return pending;
        return DMTheme.current();
    }

    function paint() {
        var active = checkedId();
        cards.forEach(function (card) {
            card.setAttribute('aria-checked', card.getAttribute('data-theme-id') === active ? 'true' : 'false');
        });
        if (apply) apply.disabled = !pending || pending === DMTheme.current();
    }

    function open() {
        if (modal) {
            pending = DMTheme.current();
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        } else {
            widget.classList.add('open');
        }
        toggle.setAttribute('aria-expanded', 'true');
        paint();
    }

    function close(focusToggle) {
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            pending = null;
        } else {
            widget.classList.remove('open');
        }
        toggle.setAttribute('aria-expanded', 'false');
        if (focusToggle) toggle.focus();
    }

    function isOpen() {
        return modal ? modal.classList.contains('open') : widget.classList.contains('open');
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        if (isOpen()) close(false);
        else open();
    });

    cards.forEach(function (card, index) {
        card.addEventListener('click', function (e) {
            e.stopPropagation();
            if (modal) {
                /* Sheet flow: highlight now, apply on Tuma — exactly how the
                   language modal's list behaves. */
                pending = card.getAttribute('data-theme-id');
                paint();
            } else {
                DMTheme.set(card.getAttribute('data-theme-id'));
                paint();
                close(true);
            }
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

    if (modal) {
        /* The sheet closes the same ways the language modal does: scrim tap
           (this listener covers it), Funga above, Esc below. */
        modal.addEventListener('click', function (e) {
            if (e.target === modal) close(false);
        });
        if (cancel) cancel.addEventListener('click', function () { close(true); });
        if (apply) {
            apply.addEventListener('click', function () {
                if (pending && pending !== DMTheme.current()) DMTheme.set(pending);
                close(true);
            });
        }
    } else {
        /* Close on outside pointer (touch included — click events bubble). */
        document.addEventListener('click', function (e) {
            if (isOpen() && !widget.contains(e.target)) close(false);
        });
    }

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