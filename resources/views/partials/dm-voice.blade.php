{{--
    Capability #23 - Voice Input & Voice Answers (website runtime).

    Include this AFTER partials.dm-lang-widget. It adds `DM.voice` to the page
    runtime so any AI input can offer a microphone button and any AI answer can
    be read aloud.

    HOW IT WORKS / PRIVACY
    ----------------------
    Speech runs entirely on the visitor's device using the platform engines:
      * recognition  -> the browser's Web Speech API (SpeechRecognition), a
                        SINGLE utterance that ends by itself (never continuous);
      * speech       -> the browser's SpeechSynthesis.
    We never record audio, never send audio to Duka Mkononi, and there is no
    speech API key anywhere. Only the recognised TEXT is used, and it lands in
    the existing (editable) input box before anything is submitted.

    WHAT IT WILL NOT DO
    -------------------
    It will not claim a language the platform may not understand. It reads
    GET /api/ai/speech/capabilities first, uses the returned BCP-47 tag and the
    single-utterance limits, and reports `device_dependent` coverage honestly.
    If recognition is missing, disabled by config, or blocked by the user, the
    caller gets a localized error and the page keeps its keyboard path.

    API (all safe to call even when voice is unavailable):
      DM.voice.sttAvailable()          -> boolean (engine present)
      DM.voice.ttsAvailable()          -> boolean
      DM.voice.isListening()           -> boolean
      DM.voice.isSpeaking()            -> boolean
      DM.voice.capabilities()          -> Promise<capabilities|null>
      DM.voice.listen({onText,onState,onError})   -> Promise<boolean>
      DM.voice.stopListening()
      DM.voice.speak(text, {onStart,onEnd,onError}) -> Promise<boolean>
      DM.voice.stopSpeaking()
      DM.voice.stop()                  -> stops both
--}}
@verbatim
<script>
(function (global) {
    'use strict';

    var CAPS_URL = '/api/ai/speech/capabilities';
    var Recognition = global.SpeechRecognition || global.webkitSpeechRecognition || null;
    var synth = global.speechSynthesis || null;

    var caps = null;
    var pendingCaps = null;
    var recognition = null;
    var listening = false;
    var listenTimer = null;
    var uttering = false;

    function dm() { return global.DM || null; }
    function locale() {
        var api = dm();
        return (api && api.locale && api.locale()) || 'sw';
    }
    function label(key) {
        var api = dm();
        var text = api && api.t ? api.t('voice.' + key) : key;
        return text === 'voice.' + key ? key : text;
    }

    function loadCapabilities() {
        if (caps) { return Promise.resolve(caps); }
        if (pendingCaps) { return pendingCaps; }
        pendingCaps = fetch(CAPS_URL + '?locale=' + encodeURIComponent(locale()), { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) { caps = data || null; pendingCaps = null; return caps; })
            .catch(function () { pendingCaps = null; return null; });
        return pendingCaps;
    }

    function mapError(code) {
        if (code === 'not-allowed' || code === 'service-not-allowed') { return 'denied'; }
        if (code === 'no-speech') { return 'no_speech'; }
        if (code === 'aborted') { return 'aborted'; }
        return 'error';
    }

    function clearTimer() {
        if (listenTimer) { global.clearTimeout(listenTimer); listenTimer = null; }
    }

    function finishListening() {
        clearTimer();
        listening = false;
        recognition = null;
    }

    /**
     * One utterance. Resolves true when listening started, false otherwise
     * (the caller is told why through onError).
     */
    function listen(options) {
        var opts = options || {};
        var onText = typeof opts.onText === 'function' ? opts.onText : function () {};
        var onState = typeof opts.onState === 'function' ? opts.onState : function () {};
        var onError = typeof opts.onError === 'function' ? opts.onError : function () {};

        if (!Recognition) {
            onError('unsupported', label('unsupported'));
            return Promise.resolve(false);
        }
        if (listening) { stopListening(); return Promise.resolve(true); }

        return loadCapabilities().then(function (data) {
            if (data && data.enabled === false) {
                onError('unsupported', label('unsupported'));
                return false;
            }

            var engine = null;
            try { engine = new Recognition(); } catch (e) { engine = null; }
            if (!engine) {
                onError('unsupported', label('unsupported'));
                return false;
            }

            engine.lang = (data && data.tag) || locale();
            engine.continuous = false;          // never a continuous recording
            engine.interimResults = true;
            engine.maxAlternatives = 1;

            engine.onresult = function (event) {
                var finalText = '';
                var interim = '';
                for (var i = event.resultIndex; i < event.results.length; i++) {
                    var result = event.results[i];
                    if (result.isFinal) { finalText += result[0].transcript; }
                    else { interim += result[0].transcript; }
                }
                if (finalText || interim) { onText(finalText || interim, !!finalText); }
            };
            engine.onerror = function (event) {
                var code = mapError(event && event.error ? event.error : 'error');
                finishListening();
                onState('idle');
                if (code !== 'aborted') { onError(code, label(code)); }
            };
            engine.onend = function () {
                var wasListening = listening;
                finishListening();
                if (wasListening) { onState('idle'); }
            };

            recognition = engine;
            try {
                engine.start();
            } catch (e) {
                finishListening();
                onError('error', label('error'));
                return false;
            }

            listening = true;
            onState('listening');
            var maxMs = ((data && data.limits && data.limits.max_seconds) || 30) * 1000;
            listenTimer = global.setTimeout(function () { if (listening) { stopListening(); } }, maxMs);
            return true;
        });
    }

    function stopListening() {
        clearTimer();
        if (recognition) {
            try { recognition.abort(); } catch (e) { /* already gone */ }
        }
        listening = false;
        recognition = null;
    }

    /** Read text aloud with the device engine. */
    function speak(text, options) {
        var opts = options || {};
        var onStart = typeof opts.onStart === 'function' ? opts.onStart : function () {};
        var onEnd = typeof opts.onEnd === 'function' ? opts.onEnd : function () {};
        var onError = typeof opts.onError === 'function' ? opts.onError : function () {};
        var value = String(text === null || text === undefined ? '' : text).trim();

        if (!value) { return Promise.resolve(false); }
        if (!synth || typeof global.SpeechSynthesisUtterance !== 'function') {
            onError('unsupported', label('unsupported'));
            return Promise.resolve(false);
        }

        return loadCapabilities().then(function (data) {
            if (data && data.enabled === false) {
                onError('unsupported', label('unsupported'));
                return false;
            }
            stopSpeaking();
            var maxChars = (data && data.tts && data.tts.max_chars) || 600;
            var utterance = new global.SpeechSynthesisUtterance(value.slice(0, maxChars));
            utterance.lang = (data && data.tag) || locale();
            utterance.onstart = function () { uttering = true; onStart(); };
            utterance.onend = function () { uttering = false; onEnd(); };
            utterance.onerror = function () { uttering = false; onEnd(); };
            try {
                synth.speak(utterance);
            } catch (e) {
                uttering = false;
                onError('error', label('error'));
                return false;
            }
            return true;
        });
    }

    function stopSpeaking() {
        if (synth) { try { synth.cancel(); } catch (e) { /* nothing to cancel */ } }
        uttering = false;
    }

    var api = dm();
    if (!api) { api = global.DM = {}; }
    if (api.voice && api.voice.__booted) { return; }

    api.voice = {
        __booted: true,
        sttAvailable: function () { return !!Recognition; },
        ttsAvailable: function () { return !!synth; },
        isListening: function () { return listening; },
        isSpeaking: function () { return uttering; },
        capabilities: loadCapabilities,
        listen: listen,
        stopListening: stopListening,
        speak: speak,
        stopSpeaking: stopSpeaking,
        stop: function () { stopListening(); stopSpeaking(); }
    };

    // Capabilities are locale-specific (tag, verified locales). Drop the cache
    // on a language switch so the next call refetches in the new language.
    if (api.onChange) {
        api.onChange(function () {
            stopListening();
            stopSpeaking();
            caps = null;
        });
    }
})(window);
</script>
@endverbatim
