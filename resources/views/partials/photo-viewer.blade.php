@verbatim
<!-- =====================================================================
     Photo viewer (WhatsApp-style lightbox) — shared across all portals.
     Any element with class "js-avatar-view" and data-full="<url>" becomes
     a clickable photo that opens large. Event delegation on document, so
     it survives the SPA-style innerHTML re-renders these pages do.

     Screenshot deterrence (web equivalent of WhatsApp's protections —
     browsers cannot block OS-level screenshots, and neither can WhatsApp
     Web): the photo is painted as a CSS background (never an <img>), a
     transparent shield intercepts taps/right-clicks, context menu is
     disabled, dragging/selection/long-press-callout are disabled, and the
     viewer's name + capture time are watermarked across the image.
     ===================================================================== -->
<style>
    #pvOverlay {
        position: fixed; inset: 0; background: rgba(0, 0, 0, 0.92);
        display: none; align-items: center; justify-content: center; z-index: 4000;
        user-select: none; -webkit-user-select: none; -webkit-touch-callout: none;
    }
    #pvOverlay.show { display: flex; }
    #pvStage {
        position: relative; width: min(92vw, 900px); height: min(80vh, 900px);
        border-radius: 12px; overflow: hidden; background: #111;
        box-shadow: 0 12px 60px rgba(0, 0, 0, 0.6);
    }
    #pvImage {
        position: absolute; inset: 0; z-index: 1;
        background-position: center; background-repeat: no-repeat; background-size: contain;
    }
    #pvWatermark {
        position: absolute; inset: 0; z-index: 2; pointer-events: none; opacity: 0.14;
        display: flex; flex-wrap: wrap; align-content: center; justify-content: center;
        gap: 28px 34px; padding: 24px; overflow: hidden;
    }
    #pvWatermark span {
        transform: rotate(-24deg); color: #fff; font-weight: 700;
        font-size: 15px; white-space: nowrap;
    }
    #pvShield { position: absolute; inset: 0; z-index: 3; cursor: zoom-out; }
    #pvTop {
        position: absolute; top: 0; left: 0; right: 0; z-index: 4;
        display: flex; justify-content: space-between; align-items: center;
        padding: 14px 18px; background: linear-gradient(rgba(0, 0, 0, 0.65), transparent);
    }
    #pvName {
        color: #fff; font-weight: 700; font-size: 15px;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80%;
    }
    #pvClose {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
        background: rgba(255, 255, 255, 0.15); border: none; color: #fff;
        cursor: pointer; font-size: 16px; line-height: 1;
    }
    #pvClose:hover { background: rgba(255, 255, 255, 0.28); }
    .js-avatar-view { cursor: zoom-in; }
</style>
<script>
(function () {
    if (window.__photoViewerReady) return;
    window.__photoViewerReady = true;

    let overlay = null, imgBox = null, wm = null, nameEl = null;

    function ensureOverlay() {
        if (overlay) return;
        overlay = document.createElement('div');
        overlay.id = 'pvOverlay';
        overlay.innerHTML =
            '<div id="pvStage">' +
                '<div id="pvImage"></div>' +
                '<div id="pvWatermark"></div>' +
                '<div id="pvShield"></div>' +
                '<div id="pvTop"><div id="pvName"></div><button id="pvClose" aria-label="Funga">\u2715</button></div>' +
            '</div>';
        document.body.appendChild(overlay);
        imgBox = overlay.querySelector('#pvImage');
        wm = overlay.querySelector('#pvWatermark');
        nameEl = overlay.querySelector('#pvName');

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeViewer();
        });
        overlay.querySelector('#pvClose').addEventListener('click', closeViewer);
        overlay.querySelector('#pvShield').addEventListener('click', closeViewer);
        // Right-click / long-press menu disabled inside the viewer.
        overlay.addEventListener('contextmenu', function (e) { e.preventDefault(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('show')) closeViewer();
        });
    }

    function closeViewer() {
        if (!overlay) return;
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        imgBox.style.backgroundImage = '';
        wm.innerHTML = '';
    }

    function openViewer(url, name) {
        if (!url) return;
        ensureOverlay();
        const safeName = String(name || '').slice(0, 60);
        nameEl.textContent = safeName || 'Picha';
        imgBox.style.backgroundImage = 'url("' + String(url).replace(/"/g, '%22') + '")';

        // Watermark tiles: owner name + app + capture time, so any capture
        // carries the viewer's identity (WhatsApp-style attribution).
        const stamp = new Date().toLocaleString('sw-TZ');
        const tileText = (safeName || 'Picha') + ' \u2022 DukaMkononi \u2022 ' + stamp;
        wm.innerHTML = '';
        for (let i = 0; i < 16; i++) {
            const s = document.createElement('span');
            s.textContent = tileText;
            wm.appendChild(s);
        }

        document.body.style.overflow = 'hidden';
        overlay.classList.add('show');
    }

    // Delegated binding: works for dynamically re-rendered avatars.
    document.addEventListener('click', function (e) {
        const el = e.target.closest('.js-avatar-view');
        if (!el) return;
        const full = el.getAttribute('data-full');
        if (!full) return; // letter placeholders have nothing to view
        e.preventDefault();
        openViewer(full, el.getAttribute('data-name') || '');
    });

    // Exposed for programmatic use (e.g. modal avatars).
    window.PhotoViewer = { show: openViewer, close: closeViewer };
})();
</script>
@endverbatim
