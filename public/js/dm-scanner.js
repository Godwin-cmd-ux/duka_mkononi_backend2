/**
 * Dukamkononi reusable QR scanner (web).
 *
 * ONE component for every scan workflow: administrator stock receiving, seller
 * sale cart, fridge/freezer in-out and the global order scanner. The caller
 * supplies the operation through `onScan`, which must call the matching API; the
 * component NEVER decides stock permissions and never changes a quantity on its
 * own. A quantity may only move after `onScan` resolves ok - see the pages,
 * which update their cart/badge from the backend response.
 *
 * Supports:
 *   - device camera (html5-qrcode, loaded on demand from a CDN, so pages stay
 *     light and an offline page simply falls back to manual input)
 *   - USB / Bluetooth scanners that behave as keyboards (they type into the
 *     focused input and press Enter)
 *   - pasted/typed codes as a last resort
 *
 * Concurrency/duplicate protection is server side. This layer only stops the
 * camera from firing the same code twenty times a second, because a detection
 * is not proof of success.
 *
 * Usage:
 *   var sc = DMScanner.open({
 *     title: 'Scanning Product Stock - Coca-Cola',
 *     hint: 'Skani kila kipande kisha bofya Maliza',
 *     finishLabel: 'Maliza',
 *     onScan: function (token) { return fetch(...).then(...); }, // -> {ok, message}
 *     onFinish: function () { ... },   // optional
 *     onClose: function () { ... }     // optional
 *   });
 */
(function () {
    'use strict';

    var CDN = 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js';
    var REPEAT_MS = 1500;
    var scriptPromise = null;

    function normalize(raw) {
        if (raw === null || raw === undefined) return '';
        var token = String(raw).trim();
        if (!token) return '';

        var lower = token.toLowerCase();
        ['dmk1:', 'dmk:', 'dukamkononi:'].forEach(function (prefix) {
            if (lower.indexOf(prefix) === 0) {
                token = token.slice(prefix.length).trim();
            }
        });

        if (/^https?:\/\//i.test(token)) {
            try {
                var parts = new URL(token);
                var seg = (parts.pathname || '').split('/').filter(Boolean).pop();
                if (seg) token = decodeURIComponent(seg);
            } catch (e) { /* keep the raw value; the server rejects what it cannot map */ }
        }

        return token.replace(/\s+/g, '');
    }

    function loadLibrary() {
        if (window.Html5Qrcode) return Promise.resolve(window.Html5Qrcode);
        if (scriptPromise) return scriptPromise;

        scriptPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = CDN;
            script.async = true;
            script.onload = function () { resolve(window.Html5Qrcode); };
            script.onerror = function () { reject(new Error('camera library unavailable')); };
            document.head.appendChild(script);
        });

        return scriptPromise;
    }

    function el(tag, style, text) {
        var node = document.createElement(tag);
        if (style) node.style.cssText = style;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function open(options) {
        options = options || {};
        var onScan = typeof options.onScan === 'function' ? options.onScan : function () {
            return Promise.resolve({ ok: false, message: 'Operation not configured' });
        };

        var successCount = 0;
        var rejectedCount = 0;
        var lastToken = '';
        var lastAt = 0;
        var busy = false;
        var closed = false;
        var camera = null;

        /* ---- overlay ---- */
        var overlay = el('div', [
            'position:fixed', 'inset:0', 'z-index:2147483100',
            'background:rgba(15,23,42,.72)', 'display:flex',
            'align-items:center', 'justify-content:center', 'padding:14px',
            'font-family:Inter,system-ui,-apple-system,sans-serif'
        ].join(';'));
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        var card = el('div', [
            'width:100%', 'max-width:440px', 'background:#fff', 'border-radius:18px',
            'box-shadow:0 24px 60px rgba(15,23,42,.45)', 'overflow:hidden',
            'display:flex', 'flex-direction:column', 'max-height:94vh'
        ].join(';'));

        var head = el('div', 'padding:16px 18px 12px;border-bottom:1px solid #eef2f5;');
        var title = el('div', 'font-size:15px;font-weight:800;color:#0f172a;line-height:1.35;', options.title || 'Skani QR');
        var subtitle = el('div', 'font-size:12px;color:#64748b;margin-top:5px;line-height:1.5;', options.hint || '');
        head.appendChild(title);
        head.appendChild(subtitle);

        var counters = el('div', 'display:flex;gap:8px;margin-top:12px;');
        var okBadge = el('div', 'flex:1;background:#ecfdf5;color:#047857;border-radius:10px;padding:8px 10px;font-size:12px;font-weight:700;', 'Zilizoskaniwa: 0');
        var badBadge = el('div', 'flex:1;background:#fef2f2;color:#b91c1c;border-radius:10px;padding:8px 10px;font-size:12px;font-weight:700;', 'Zilizokataliwa: 0');
        counters.appendChild(okBadge);
        counters.appendChild(badBadge);
        head.appendChild(counters);

        var body = el('div', 'padding:14px 18px;overflow:auto;');

        var stage = el('div', 'position:relative;background:#0f172a;border-radius:14px;overflow:hidden;min-height:150px;display:flex;align-items:center;justify-content:center;');
        var reader = el('div', 'width:100%;');
        reader.id = 'dmScannerReader';
        var stageMsg = el('div', 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:12px;text-align:center;padding:18px;line-height:1.6;', 'Inawasha kamera...');
        stage.appendChild(reader);
        stage.appendChild(stageMsg);
        body.appendChild(stage);

        var manualWrap = el('div', 'margin-top:12px;');
        var manualLabel = el('div', 'font-size:11px;font-weight:700;color:#475569;margin-bottom:6px;', 'Skana ya USB/Bluetooth au andika code');
        var manual = el('input', [
            'width:100%', 'box-sizing:border-box', 'padding:11px 12px', 'border-radius:10px',
            'border:1px solid #dbe3ea', 'font-size:13px', 'outline:none'
        ].join(';'));
        manual.type = 'text';
        manual.autocomplete = 'off';
        manual.spellcheck = false;
        manual.placeholder = 'Code ... kisha Enter';
        manualWrap.appendChild(manualLabel);
        manualWrap.appendChild(manual);
        body.appendChild(manualWrap);

        var status = el('div', 'margin-top:12px;font-size:12px;line-height:1.55;color:#475569;min-height:18px;', '');
        body.appendChild(status);

        var foot = el('div', 'padding:12px 18px 16px;border-top:1px solid #eef2f5;display:flex;gap:8px;');
        var finish = el('button', 'flex:1;padding:12px 14px;border-radius:11px;border:none;background:#1e3a8a;color:#fff;font-size:13px;font-weight:800;cursor:pointer;', options.finishLabel || 'Maliza');
        var cancel = el('button', 'flex:1;padding:12px 14px;border-radius:11px;border:1px solid #dbe3ea;background:#fff;color:#334155;font-size:13px;font-weight:700;cursor:pointer;', options.cancelLabel || 'Funga');
        foot.appendChild(finish);
        foot.appendChild(cancel);

        card.appendChild(head);
        card.appendChild(body);
        card.appendChild(foot);
        overlay.appendChild(card);
        document.body.appendChild(overlay);

        function paint(message, tone) {
            status.textContent = message || '';
            status.style.color = tone === 'error' ? '#b91c1c' : (tone === 'ok' ? '#047857' : '#475569');
            okBadge.textContent = 'Zilizoskaniwa: ' + successCount;
            badBadge.textContent = 'Zilizokataliwa: ' + rejectedCount;
        }

        function buzz(pattern) {
            try {
                if (navigator.vibrate) navigator.vibrate(pattern);
            } catch (e) { /* not supported */ }
        }

        function submit(raw) {
            var token = normalize(raw);
            if (!token) {
                paint('Code si sahihi. Jaribu tena.', 'error');
                return Promise.resolve();
            }
            if (busy) {
                paint('Inasubiri jibu la mfumo...', null);
                return Promise.resolve();
            }

            var now = Date.now();
            if (token === lastToken && now - lastAt < REPEAT_MS) {
                return Promise.resolve();
            }
            lastToken = token;
            lastAt = now;
            busy = true;
            paint('Inathibitisha ' + token + '...', null);

            return Promise.resolve()
                .then(function () { return onScan(token); })
                .then(function (result) {
                    result = result || {};
                    if (result.ok) {
                        successCount++;
                        paint(result.message || ('Imefanikiwa: ' + token), 'ok');
                        buzz(40);
                    } else {
                        rejectedCount++;
                        paint(result.message || 'Kipande hakikubaliwa.', 'error');
                        buzz([30, 60, 30]);
                    }
                })
                .catch(function (error) {
                    rejectedCount++;
                    paint((error && error.message) || 'Hitilafu ya mtandao. Jaribu tena.', 'error');
                })
                .then(function () {
                    busy = false;
                    manual.value = '';
                    manual.focus();
                });
        }

        manual.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            submit(manual.value);
        });

        /* ---- camera ---- */
        function startCamera() {
            if (closed) return;
            loadLibrary().then(function (Html5Qrcode) {
                if (closed) return;
                camera = new Html5Qrcode('dmScannerReader', { verbose: false });
                return camera.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 220, height: 220 } },
                    function (decoded) {
                        stageMsg.style.display = 'none';
                        submit(decoded);
                    },
                    function () { /* per-frame decode misses are normal */ }
                ).then(function () {
                    stageMsg.style.display = 'none';
                    paint('Kamera iko tayari. Skani kipande.', null);
                }).catch(function (error) {
                    stageMsg.style.display = 'flex';
                    stageMsg.textContent = 'Kamera haipatikani. Tumia skana ya USB/Bluetooth au andika code chini.';
                    paint('Kamera haipatikani: ' + ((error && error.message) || 'ruhusa imekataliwa'), 'error');
                });
            }).catch(function () {
                stageMsg.textContent = 'Maktaba ya kamera haijapakiwa (hakuna mtandao?). Tumia skana ya USB/Bluetooth au andika code chini.';
                paint('Kamera haipatikani kwa sasa; skana ya nje inafanya kazi.', 'error');
            });
        }

        function stopCamera() {
            if (!camera) return Promise.resolve();
            var instance = camera;
            camera = null;
            try {
                return instance.stop().then(function () { return instance.clear(); }).catch(function () {});
            } catch (e) {
                return Promise.resolve();
            }
        }

        function close(finished) {
            if (closed) return;
            closed = true;
            stopCamera().then(function () {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                if (finished && typeof options.onFinish === 'function') {
                    try { options.onFinish({ scanned: successCount, rejected: rejectedCount }); } catch (e) {}
                }
                if (typeof options.onClose === 'function') {
                    try { options.onClose({ scanned: successCount, rejected: rejectedCount }); } catch (e) {}
                }
            });
        }

        finish.addEventListener('click', function () { close(true); });
        cancel.addEventListener('click', function () { close(false); });
        overlay.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') close(false);
        });

        manual.focus();
        startCamera();

        return {
            close: function () { close(false); },
            counts: function () { return { scanned: successCount, rejected: rejectedCount }; }
        };
    }

    window.DMScanner = { open: open, normalize: normalize };
})();
