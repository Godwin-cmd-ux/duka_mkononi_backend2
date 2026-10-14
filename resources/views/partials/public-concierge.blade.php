{{--
    Capability #21 - Public AI Concierge (website widget).

    Include this partial from any PUBLIC page (homepage, shop, businesses) to
    give a visitor - signed in or not - an ask box that answers from the
    approved public knowledge base and the public catalogue, in the language
    they selected with the floating language button.

    Everything factual comes from GET/POST /api/ai/concierge/*: this file only
    renders it. It never builds a URL of its own (the links come from the API,
    which is limited to the URLs this website already publishes) and it never
    sends anything but the question text and the active locale.

    The widget is deliberately self-contained (its own `dmc-` CSS/JS, no
    framework, no globals beyond `window.DM` when the language widget loaded)
    so including it cannot disturb the page around it.
--}}
<div class="dmc-root" id="dmConcierge">
    <button class="dmc-launcher" id="dmcLauncher" type="button" aria-expanded="false">
        <span class="dmc-launcher-dot" aria-hidden="true"></span>
        <span data-i18n="public_concierge.open">Ask us</span>
    </button>

    <section class="dmc-panel" id="dmcPanel" hidden aria-live="polite">
        <header class="dmc-head">
            <div>
                <h3 data-i18n="public_concierge.title">Duka Mkononi assistant</h3>
                <p data-i18n="public_concierge.subtitle">Ask about our marketplace, how it works, or where to download the app</p>
            </div>
            <button class="dmc-close" id="dmcClose" type="button" aria-label="Close">&times;</button>
        </header>

        <div class="dmc-body" id="dmcBody">
            <p class="dmc-note" id="dmcNotice"></p>

            <div class="dmc-ask">
                <input id="dmcInput" type="text" autocomplete="off"
                       data-i18n="public_concierge.ask_placeholder" data-i18n-attr="placeholder"
                       placeholder="e.g. How do I buy a product?">
                {{-- Capability #23: revealed only when the device has a speech
                     engine AND the capability endpoint allows it. The typed
                     input is always the primary path. --}}
                <button class="dmc-mic" id="dmcMic" type="button" hidden
                        data-i18n="voice.listen" data-i18n-attr="aria-label" aria-label="Speak">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0V6a3 3 0 0 1 3-3Z"></path>
                        <path d="M5 11a7 7 0 0 0 14 0"></path>
                        <path d="M12 18v3"></path>
                    </svg>
                </button>
                <button class="dmc-send" id="dmcSend" type="button"
                        data-i18n="public_concierge.ask_button">Ask</button>
            </div>
            <p class="dmc-voice" id="dmcVoice" role="status" aria-live="polite" hidden></p>

            <p class="dmc-section" id="dmcTopicsTitle" data-i18n="public_concierge.topics_title">Topics I can explain</p>
            <div class="dmc-chips" id="dmcTopics"></div>

            <p class="dmc-section" id="dmcExamplesTitle" data-i18n="public_concierge.examples_title">Example questions</p>
            <div class="dmc-chips" id="dmcExamples"></div>

            <div id="dmcAnswer"></div>
        </div>
    </section>
</div>

@verbatim
<style>
    /* Logical inset properties only: the launcher and panel stay on the
       reading-end side of the page, so Urdu (RTL) mirrors without extra CSS. */
    .dmc-root { font-family: 'Inter', system-ui, sans-serif; }
    .dmc-launcher {
        position: fixed; inset-inline-end: 18px; bottom: 18px; z-index: 900;
        display: inline-flex; align-items: center; gap: 9px;
        padding: 13px 18px; border: 0; border-radius: 999px; cursor: pointer;
        background: #4f46e5; color: #fff; font-weight: 700; font-size: 14.5px;
        box-shadow: 0 10px 26px rgba(79, 70, 229, .32);
    }
    .dmc-launcher-dot { width: 9px; height: 9px; border-radius: 50%; background: #a5f3fc; }
    .dmc-panel {
        position: fixed; inset-inline-end: 18px; bottom: 82px; z-index: 901;
        width: min(420px, calc(100vw - 24px)); max-height: min(80vh, 660px);
        display: flex; flex-direction: column; overflow: hidden;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 18px;
        box-shadow: 0 22px 60px rgba(15, 23, 42, .22);
    }
    .dmc-head { display: flex; align-items: flex-start; gap: 10px; padding: 15px 16px; border-bottom: 1px solid #eef2f7; }
    .dmc-head h3 { margin: 0; font-size: 15.5px; font-weight: 800; color: #0f172a; }
    .dmc-head p { margin: 3px 0 0; font-size: 12.5px; color: #64748b; }
    .dmc-close { margin-left: auto; border: 0; background: transparent; font-size: 22px; line-height: 1; cursor: pointer; color: #64748b; }
    .dmc-body { padding: 14px 16px 18px; overflow-y: auto; }
    .dmc-note { margin: 0 0 12px; padding: 9px 11px; border-radius: 10px; background: #eff6ff; color: #1e40af; font-size: 12px; }
    .dmc-ask { display: flex; gap: 8px; }
    .dmc-ask input { flex: 1; min-width: 0; padding: 11px 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-family: inherit; }
    .dmc-send { padding: 11px 15px; border: 0; border-radius: 10px; background: #4f46e5; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
    .dmc-send:disabled { opacity: .6; cursor: progress; }
    .dmc-section { margin: 14px 0 7px; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #334155; }
    .dmc-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .dmc-chip { border: 1px solid #e0e7ff; background: #eef2ff; color: #3730a3; border-radius: 999px; padding: 7px 11px; font-size: 12.5px; cursor: pointer; font-family: inherit; text-align: left; }
    .dmc-chip:hover { background: #e0e7ff; }
    .dmc-answer { margin-top: 14px; padding: 13px; border: 1px solid #eef2f7; border-radius: 14px; background: #fcfdff; }
    .dmc-answer h4 { margin: 0 0 6px; font-size: 14.5px; font-weight: 800; color: #0f172a; }
    .dmc-answer p { margin: 0 0 8px; font-size: 13.5px; color: #334155; line-height: 1.5; }
    .dmc-answer ul { margin: 0 0 10px 18px; font-size: 13px; color: #475569; }
    .dmc-ack { margin: 8px 0 0; font-size: 12.5px; color: #1e40af; background: #eff6ff; border-radius: 9px; padding: 8px 10px; }
    .dmc-links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .dmc-link { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 10px; background: #fff; border: 1px solid #e2e8f0; color: #4f46e5; font-size: 13px; font-weight: 600; text-decoration: none; }
    .dmc-item { display: flex; gap: 9px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #0f172a; }
    .dmc-item b { font-weight: 700; }
    .dmc-item span { color: #64748b; }
    .dmc-mic { display: inline-flex; align-items: center; justify-content: center; width: 42px; padding: 0; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; color: #4f46e5; cursor: pointer; }
    .dmc-mic:hover { background: #eef2ff; }
    .dmc-mic.listening { background: #fee2e2; border-color: #fecaca; color: #b91c1c; animation: dmcPulse 1.1s ease-in-out infinite; }
    @keyframes dmcPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(185, 28, 28, .28); } 50% { box-shadow: 0 0 0 7px rgba(185, 28, 28, 0); } }
    .dmc-voice { margin: 8px 0 0; padding: 8px 10px; border-radius: 10px; background: #f8fafc; color: #475569; font-size: 12.5px; line-height: 1.45; }
    .dmc-speak { margin-top: 8px; padding: 7px 12px; border: 1px solid #e0e7ff; border-radius: 10px; background: #eef2ff; color: #3730a3; font-size: 12.5px; font-weight: 600; cursor: pointer; font-family: inherit; }
    .dmc-speak.speaking { background: #e0e7ff; }
    .dmc-err { margin-top: 12px; padding: 10px 12px; border-radius: 10px; background: #fef2f2; color: #991b1b; font-size: 13px; }
    .dmc-loading { margin-top: 12px; font-size: 13px; color: #64748b; }
    @media (max-width: 520px) {
        .dmc-launcher { inset-inline-end: 12px; bottom: 12px; padding: 11px 14px; font-size: 13.5px; }
        .dmc-panel { inset-inline: 12px; width: auto; bottom: 70px; }
    }
</style>
@endverbatim

@verbatim
<script>
(function () {
    'use strict';
    var root = document.getElementById('dmConcierge');
    if (!root || root.dataset.dmcReady === '1') { return; }
    root.dataset.dmcReady = '1';

    var ASK_URL = '/api/ai/concierge/ask';
    var HELP_URL = '/api/ai/concierge/help';
    var t = function (key, params) { return window.DM && DM.t ? DM.t(key, params) : key; };
    var locale = function () { return (window.DM && DM.locale && DM.locale()) || 'sw'; };
    var panel = document.getElementById('dmcPanel');
    var body = document.getElementById('dmcBody');
    var answerBox = document.getElementById('dmcAnswer');
    var input = document.getElementById('dmcInput');
    var send = document.getElementById('dmcSend');
    var launcher = document.getElementById('dmcLauncher');
    var mic = document.getElementById('dmcMic');
    var voiceNote = document.getElementById('dmcVoice');
    var help = null;
    var lastSpeech = '';

    function esc(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function label(key, fallback) {
        var text = t(key);
        return text === key ? fallback : text;
    }

    function linkHtml(links) {
        if (!links || !links.length) { return ''; }
        return '<p class="dmc-section">' + esc(label('public_concierge.links_title', 'Related pages')) + '</p>' +
            '<div class="dmc-links">' + links.map(function (l) {
                return '<a class="dmc-link" href="' + esc(l.url) + '" rel="noopener">' + esc(l.label) + '</a>';
            }).join('') + '</div>';
    }

    function chips(container, items, keyOf, labelOf) {
        container.innerHTML = '';
        (items || []).forEach(function (item) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'dmc-chip';
            button.textContent = labelOf(item);
            button.setAttribute(keyOf.name, keyOf.value(item));
            container.appendChild(button);
        });
    }

    function renderHelp(data) {
        help = data;
        document.getElementById('dmcNotice').textContent = data.notice_public || label('public_concierge.help_note', '');

        chips(
            document.getElementById('dmcTopics'),
            data.topics,
            { name: 'data-topic', value: function (i) { return i.id; } },
            function (i) { return i.label; }
        );
        chips(
            document.getElementById('dmcExamples'),
            data.examples,
            { name: 'data-example', value: function (q) { return q; } },
            function (q) { return q; }
        );
    }

    function renderAnswer(data) {
        // Capability #23: the spoken text is the same localized answer the
        // visitor reads - never a second, generated one.
        lastSpeech = data.answer
            ? [data.answer.title, data.answer.body].concat(data.answer.bullets || []).filter(Boolean).join('. ')
            : '';

        if (data.intent === 'refused' || data.intent === 'unknown' || (!data.answer && !data.results)) {
            var note = data.notice || label('public_concierge.unknown', '');
            answerBox.innerHTML = '<div class="dmc-answer"><p>' + esc(note) + '</p>' +
                linkHtml(data.fallback_links) + '</div>';
            return;
        }

        var html = '<div class="dmc-answer">';

        if (data.answer) {
            html += '<h4>' + esc(data.answer.title) + '</h4>';
            html += '<p>' + esc(data.answer.body) + '</p>';
            if (data.answer.bullets && data.answer.bullets.length) {
                html += '<ul>' + data.answer.bullets.map(function (b) { return '<li>' + esc(b) + '</li>'; }).join('') + '</ul>';
            }
            if (data.acknowledgement) { html += '<p class="dmc-ack">' + esc(data.acknowledgement) + '</p>'; }
            html += linkHtml(data.answer.links);
        }

        var products = (data.results && data.results.products) || [];
        var businesses = (data.results && data.results.businesses) || [];

        if (products.length) {
            html += '<p class="dmc-section">' + esc(label('public_concierge.results_title', 'Products found')) + '</p>';
            html += products.map(function (p) {
                return '<div class="dmc-item"><b>' + esc(p.name) + '</b><span>' + esc(p.business_name || '') +
                    ' - ' + esc(p.selling_price) + ' ' + esc(p.currency || '') + '</span></div>';
            }).join('');
        }
        if (businesses.length) {
            html += '<p class="dmc-section">' + esc(label('public_concierge.businesses_title', 'Businesses found')) + '</p>';
            html += businesses.map(function (b) {
                return '<div class="dmc-item"><b>' + esc(b.business_name) + '</b><span>' + esc(b.business_location || '') + '</span></div>';
            }).join('');
        }
        if (data.notice && !data.answer) {
            html += '<p>' + esc(data.notice) + '</p>';
        }

        html += linkHtml(data.fallback_links);
        if (data.answer) {
            html += '<button class="dmc-speak" type="button" data-speak="1">' + esc(voiceLabel('speak')) + '</button>';
        }
        html += '</div>';
        answerBox.innerHTML = html;
    }

    function ask(question) {
        var text = String(question || '').trim();
        if (text.length < 3) { return; }
        input.value = text;
        send.disabled = true;
        answerBox.innerHTML = '<p class="dmc-loading">' + esc(label('public_concierge.loading', 'Looking for an answer...')) + '</p>';

        fetch(ASK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ locale: locale(), question: text })
        }).then(function (response) {
            return response.json().then(function (data) { return { ok: response.ok, data: data }; });
        }).then(function (result) {
            if (!result.ok) {
                answerBox.innerHTML = '<div class="dmc-err">' + esc(result.data.error || label('public_concierge.error', '')) + '</div>' +
                    linkHtml(result.data.fallback_links);
                return;
            }
            renderAnswer(result.data);
        }).catch(function () {
            answerBox.innerHTML = '<div class="dmc-err">' + esc(label('public_concierge.error', '')) + '</div>';
        }).then(function () {
            send.disabled = false;
        });
    }

    function loadHelp() {
        fetch(HELP_URL + '?locale=' + encodeURIComponent(locale()), { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) { if (data) { renderHelp(data); } })
            .catch(function () { /* the ask box still works without the help list */ });
    }

    function openPanel() {
        panel.hidden = false;
        launcher.setAttribute('aria-expanded', 'true');
        if (!help) { loadHelp(); }
    }
    function closePanel() {
        panel.hidden = true;
        launcher.setAttribute('aria-expanded', 'false');
    }

    launcher.addEventListener('click', function () {
        if (panel.hidden) { openPanel(); } else { closePanel(); }
    });
    document.getElementById('dmcClose').addEventListener('click', closePanel);
    send.addEventListener('click', function () { ask(input.value); });
    input.addEventListener('keydown', function (event) { if (event.key === 'Enter') { ask(input.value); } });
    body.addEventListener('click', function (event) {
        var example = event.target.closest('[data-example]');
        if (example) { ask(example.getAttribute('data-example')); return; }
        var topic = event.target.closest('[data-topic]');
        if (topic) { ask(help && help.topics ? topicLabel(topic.getAttribute('data-topic')) : topic.getAttribute('data-topic')); }
    });

    function topicLabel(id) {
        var found = (help.topics || []).filter(function (t) { return t.id === id; })[0];
        return found ? found.label : id;
    }

    /* ----- Capability #23: voice input & spoken answers -----
       The mic only appears when the DEVICE has a recognition engine and the
       capability endpoint agrees; recognised text lands in the editable input
       and is never submitted by itself. "Listen" reads the same answer aloud.
       Everything degrades to the keyboard path with a localized message. */
    function voiceLabel(key) {
        var text = t('voice.' + key);
        return text === 'voice.' + key ? key : text;
    }

    function showVoiceNote(text) {
        if (!voiceNote) { return; }
        voiceNote.textContent = text || '';
        voiceNote.hidden = !text;
    }

    function setupVoice() {
        if (!mic || !(window.DM && DM.voice)) { return; }
        DM.voice.capabilities().then(function (caps) {
            if (!caps || caps.enabled === false || !caps.stt || !caps.stt.available) { return; }
            if (!DM.voice.sttAvailable()) { showVoiceNote(voiceLabel('unsupported')); return; }
            mic.hidden = false;
        });
    }

    if (mic) {
        mic.addEventListener('click', function () {
            if (!(window.DM && DM.voice)) { return; }
            if (DM.voice.isListening()) {
                DM.voice.stopListening();
                mic.classList.remove('listening');
                return;
            }
            mic.classList.add('listening');
            showVoiceNote(voiceLabel('listening'));
            input.focus();
            DM.voice.listen({
                onText: function (text, isFinal) {
                    input.value = text;   // stays editable until the visitor sends it
                    if (isFinal) { mic.classList.remove('listening'); showVoiceNote(''); }
                },
                onState: function (state) { if (state === 'idle') { mic.classList.remove('listening'); } },
                onError: function (code, message) {
                    mic.classList.remove('listening');
                    showVoiceNote(message);
                }
            });
        });
    }

    answerBox.addEventListener('click', function (event) {
        var button = event.target.closest('[data-speak]');
        if (!button || !(window.DM && DM.voice)) { return; }
        if (DM.voice.isSpeaking()) {
            DM.voice.stopSpeaking();
            button.classList.remove('speaking');
            button.textContent = voiceLabel('speak');
            return;
        }
        button.classList.add('speaking');
        button.textContent = voiceLabel('speaking');
        DM.voice.speak(lastSpeech, {
            onEnd: function () { button.classList.remove('speaking'); button.textContent = voiceLabel('speak'); },
            onError: function (code, message) {
                button.classList.remove('speaking');
                button.textContent = voiceLabel('speak');
                showVoiceNote(message);
            }
        });
    });

    setupVoice();

    /* The chrome labels (open, title, loading, error...) are data-i18n text in
       the page's own catalogue, so the site translator repaints them on switch.
       The server-supplied content (topics, examples, notices) does not go
       through data-i18n, so it is refetched here in the new language. */
    if (window.DM && DM.onChange) {
        DM.onChange(function () {
            help = null;
            if (!panel.hidden) { loadHelp(); }
            if (window.DM && DM.voice) { DM.voice.stop(); }
            showVoiceNote('');
        });
    }
})();
</script>
@endverbatim
