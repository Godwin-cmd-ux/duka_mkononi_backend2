<?php

namespace App\Services\Ai;

/**
 * Capability #23 - Voice Input & Voice Answers.
 *
 * WHAT THIS IS
 * ------------
 * Voice is a CLIENT-side capability: speech recognition and speech synthesis
 * happen on the visitor's own device through the platform APIs (the browser's
 * Web Speech API + SpeechSynthesis, and the phone's built-in engine). This
 * service is the honest contract around that:
 *
 *   * it is the single authority on WHICH locales the implementation actually
 *     claims (config `ai.speech.*`), so no client can advertise a language the
 *     device may not understand;
 *   * it reports the privacy facts (no audio recorded, none sent to our
 *     server, no continuous listening) and the limits (single utterance,
 *     bounded seconds/characters);
 *   * it ships every UI/status string in all eight languages so the mic button,
 *     the errors and the "type instead" fallback are translated.
 *
 * WHY THERE IS NO PROVIDER KEY
 * ----------------------------
 * The catalogue says "keep API credentials server-side where applicable". For
 * this design none are applicable: the platform engines need no credential, so
 * there is no key to leak and nothing is proxied. If an operator later wants a
 * server-side engine, `provider` is the extension point and the key would live
 * here (server-side) - the clients never see it.
 *
 * HONEST COVERAGE
 * ---------------
 * `stt_verified_locales` / `tts_verified_locales` list what has been verified.
 * Swahili speech-to-text is required by the catalogue and is the first entry;
 * a locale outside the list is reported as `device_dependent`, never as
 * supported. This is the "do not claim a language is supported by the speech
 * engine until its actual capabilities have been verified" rule, enforced.
 */
class Speech
{
    public const CAPABILITY = 'voice';

    public const PROVIDER_PLATFORM = 'platform';

    public const SUPPORT_VERIFIED = 'verified';

    public const SUPPORT_DEVICE_DEPENDENT = 'device_dependent';

    /**
     * Every string the voice controls show, in all eight languages. Kept here
     * (like the other capabilities' messages) and mirrored into both locale
     * catalogues by scripts/add-voice-locales.js; a unit test keeps the two in
     * step so they cannot drift.
     */
    public const MESSAGES = [
        'sw' => [
            'title' => 'Sauti',
            'listen' => 'Zungumza',
            'listening' => 'Nasikiliza…',
            'stop' => 'Simama',
            'speak' => 'Sikiliza',
            'speaking' => 'Ninasoma kwa sauti…',
            'stop_speaking' => 'Acha kusoma',
            'unsupported' => 'Kuingiza kwa sauti hakutumiki kwenye kifaa hiki. Unaweza kuandika swali lako.',
            'denied' => 'Ruhusa ya maikrofoni imezuiwa. Iruhusu kwenye mipangilio ya kivinjari au kifaa, au andika badala yake.',
            'no_speech' => 'Sikusikia vizuri. Jaribu tena, au andika badala yake.',
            'error' => 'Kuingiza kwa sauti hakufanikiwa. Tafadhali andika swali lako.',
            'privacy' => 'Sauti yako hairekodiwi wala kutumwa kwa Duka Mkononi; maandishi yanayotambuliwa pekee ndiyo yanatumika.',
            'device_note' => 'Uwezo wa sauti unategemea kifaa chako na kivinjari. Lugha zingine huenda zisiweze kupatikana.',
        ],
        'en' => [
            'title' => 'Voice',
            'listen' => 'Speak',
            'listening' => 'Listening…',
            'stop' => 'Stop',
            'speak' => 'Listen',
            'speaking' => 'Reading aloud…',
            'stop_speaking' => 'Stop reading',
            'unsupported' => 'Voice input is not available on this device. You can type your question instead.',
            'denied' => 'Microphone access was blocked. Allow it in your browser or device settings, or type instead.',
            'no_speech' => 'I did not catch that. Try again, or type instead.',
            'error' => 'Voice input did not work. Please type your question instead.',
            'privacy' => 'Your voice is not recorded or sent to Duka Mkononi; only the recognised text is used.',
            'device_note' => 'Voice coverage depends on your device and browser. Some languages may not be available.',
        ],
        'fr' => [
            'title' => 'Voix',
            'listen' => 'Parler',
            'listening' => 'Écoute…',
            'stop' => 'Arrêter',
            'speak' => 'Écouter',
            'speaking' => 'Lecture en cours…',
            'stop_speaking' => 'Arrêter la lecture',
            'unsupported' => "La saisie vocale n'est pas disponible sur cet appareil. Vous pouvez saisir votre question.",
            'denied' => "L'accès au microphone a été bloqué. Autorisez-le dans les réglages, ou saisissez votre question.",
            'no_speech' => "Je n'ai pas compris. Réessayez, ou saisissez votre question.",
            'error' => "La saisie vocale n'a pas fonctionné. Veuillez saisir votre question.",
            'privacy' => "Votre voix n'est ni enregistrée ni envoyée à Duka Mkononi ; seul le texte reconnu est utilisé.",
            'device_note' => "La couverture vocale dépend de votre appareil et de votre navigateur. Certaines langues peuvent être indisponibles.",
        ],
        'hi' => [
            'title' => 'आवाज़',
            'listen' => 'बोलें',
            'listening' => 'सुन रहा हूँ…',
            'stop' => 'रोकें',
            'speak' => 'सुनें',
            'speaking' => 'पढ़ा जा रहा है…',
            'stop_speaking' => 'पढ़ना बंद करें',
            'unsupported' => 'इस डिवाइस पर आवाज़ से इनपुट उपलब्ध नहीं है। आप अपना सवाल टाइप कर सकते हैं।',
            'denied' => 'माइक्रोफ़ोन की अनुमति रोक दी गई है। सेटिंग्स में इसे अनुमति दें, या टाइप करें।',
            'no_speech' => 'मैं समझ नहीं पाया। फिर कोशिश करें, या टाइप करें।',
            'error' => 'आवाज़ से इनपुट काम नहीं किया। कृपया अपना सवाल टाइप करें।',
            'privacy' => 'आपकी आवाज़ रिकॉर्ड या Duka Mkononi को नहीं भेजी जाती; केवल पहचाना गया टेक्स्ट इस्तेमाल होता है।',
            'device_note' => 'आवाज़ की उपलब्धता आपके डिवाइस और ब्राउज़र पर निर्भर है। कुछ भाषाएँ उपलब्ध न हों।',
        ],
        'es' => [
            'title' => 'Voz',
            'listen' => 'Hablar',
            'listening' => 'Escuchando…',
            'stop' => 'Detener',
            'speak' => 'Escuchar',
            'speaking' => 'Leyendo en voz alta…',
            'stop_speaking' => 'Dejar de leer',
            'unsupported' => 'La entrada de voz no está disponible en este dispositivo. Puedes escribir tu pregunta.',
            'denied' => 'Se bloqueó el acceso al micrófono. Permítelo en los ajustes, o escribe tu pregunta.',
            'no_speech' => 'No te entendí. Inténtalo de nuevo, o escribe tu pregunta.',
            'error' => 'La entrada de voz no funcionó. Escribe tu pregunta, por favor.',
            'privacy' => 'Tu voz no se graba ni se envía a Duka Mkononi; solo se usa el texto reconocido.',
            'device_note' => 'La cobertura de voz depende de tu dispositivo y navegador. Puede que algunos idiomas no estén disponibles.',
        ],
        'ur' => [
            'title' => 'آواز',
            'listen' => 'بولیں',
            'listening' => 'سن رہا ہوں…',
            'stop' => 'رکیں',
            'speak' => 'سنیں',
            'speaking' => 'بلند آواز میں پڑھا جا رہا ہے…',
            'stop_speaking' => 'پڑھنا بند کریں',
            'unsupported' => 'اس ڈیوائس پر آواز سے ان پٹ دستیاب نہیں ہے۔ آپ اپنا سوال ٹائپ کر سکتے ہیں۔',
            'denied' => 'مائیکروفون کی اجازت روک دی گئی ہے۔ ترتیبات میں اجازت دیں، یا ٹائپ کریں۔',
            'no_speech' => 'میں سمجھ نہیں سکا۔ دوبارہ کوشش کریں، یا ٹائپ کریں۔',
            'error' => 'آواز سے ان پٹ کام نہیں کیا۔ براہ کرم اپنا سوال ٹائپ کریں۔',
            'privacy' => 'آپ کی آواز ریکارڈ یا Duka Mkononi کو نہیں بھیجی جاتی؛ صرف پہچانا گیا متن استعمال ہوتا ہے۔',
            'device_note' => 'آواز کی دستیابی آپ کے ڈیوائس اور براؤزر پر منحصر ہے۔ کچھ زبانیں دستیاب ن ہوں۔',
        ],
        'de' => [
            'title' => 'Stimme',
            'listen' => 'Sprechen',
            'listening' => 'Ich höre zu…',
            'stop' => 'Stoppen',
            'speak' => 'Anhören',
            'speaking' => 'Wird vorgelesen…',
            'stop_speaking' => 'Vorlesen beenden',
            'unsupported' => 'Spracheingabe ist auf diesem Gerät nicht verfügbar. Du kannst deine Frage stattdessen tippen.',
            'denied' => 'Der Mikrofonzugriff wurde blockiert. Erlaube ihn in den Einstellungen, oder tippe stattdessen.',
            'no_speech' => 'Das habe ich nicht verstanden. Versuche es erneut, oder tippe stattdessen.',
            'error' => 'Die Spracheingabe hat nicht funktioniert. Bitte tippe deine Frage.',
            'privacy' => 'Deine Stimme wird nicht aufgezeichnet und nicht an Duka Mkononi gesendet; nur der erkannte Text wird verwendet.',
            'device_note' => 'Die Sprachabdeckung hängt von deinem Gerät und Browser ab. Manche Sprachen sind eventuell nicht verfügbar.',
        ],
        'zh' => [
            'title' => '语音',
            'listen' => '说话',
            'listening' => '正在聆听…',
            'stop' => '停止',
            'speak' => '朗读',
            'speaking' => '正在朗读…',
            'stop_speaking' => '停止朗读',
            'unsupported' => '此设备不支持语音输入。你可以改为输入问题。',
            'denied' => '麦克风权限已被阻止。请在设置中允许，或改为输入。',
            'no_speech' => '我没有听清。请重试，或改为输入。',
            'error' => '语音输入失败。请输入你的问题。',
            'privacy' => '你的语音不会被录音，也不会发送给 Duka Mkononi；仅使用识别出的文字。',
            'device_note' => '语音支持取决于你的设备和浏览器，某些语言可能不可用。',
        ],
    ];

    public static function isConfigured(): bool
    {
        return (bool) config('ai.speech.enabled', true);
    }

    /** The capability flag AND the speech switch must both allow it. */
    public static function isEnabled(): bool
    {
        return (bool) config('ai.features.voice', true) && self::isConfigured();
    }

    public static function provider(): string
    {
        $provider = strtolower(trim((string) config('ai.speech.provider', self::PROVIDER_PLATFORM)));

        return $provider !== '' ? $provider : self::PROVIDER_PLATFORM;
    }

    /** @return array<int, string> */
    public static function sttVerifiedLocales(): array
    {
        return self::cleanLocales((array) config('ai.speech.stt_verified_locales', ['sw', 'en']));
    }

    /** @return array<int, string> */
    public static function ttsVerifiedLocales(): array
    {
        return self::cleanLocales((array) config('ai.speech.tts_verified_locales', Locale::supported()));
    }

    /**
     * Capability #22 integration: a locale is only ever "verified" if it is in
     * the shared allow-list AND in the speech verified list.
     */
    public static function sttSupport(string $locale): string
    {
        $code = Locale::normalize($locale);
        if ($code === null) {
            return self::SUPPORT_DEVICE_DEPENDENT;
        }

        return in_array($code, self::sttVerifiedLocales(), true)
            ? self::SUPPORT_VERIFIED
            : self::SUPPORT_DEVICE_DEPENDENT;
    }

    public static function ttsSupport(string $locale): string
    {
        $code = Locale::normalize($locale);
        if ($code === null) {
            return self::SUPPORT_DEVICE_DEPENDENT;
        }

        return in_array($code, self::ttsVerifiedLocales(), true)
            ? self::SUPPORT_VERIFIED
            : self::SUPPORT_DEVICE_DEPENDENT;
    }

    public static function maxSeconds(): int
    {
        return max(1, (int) config('ai.speech.max_seconds', 30));
    }

    public static function maxChars(): int
    {
        return max(1, (int) config('ai.speech.max_chars', 600));
    }

    /** Documented invariant: we never record, keep or upload audio. */
    public static function retainsAudio(): bool
    {
        return (bool) config('ai.speech.retain_audio', false);
    }

    /** BCP-47 tag the device engines should use for this locale. */
    public static function tag(string $locale): string
    {
        return Locale::tag($locale);
    }

    /**
     * The full capability contract a client needs before showing a mic button.
     * It is deliberately explicit about what is NOT guaranteed, so no UI can
     * silently over-claim.
     */
    public static function capabilities(string $locale): array
    {
        $code = Locale::resolve(null, $locale);
        $enabled = self::isEnabled();

        return [
            'success' => true,
            'capability' => self::CAPABILITY,
            'enabled' => $enabled,
            'provider' => self::provider(),
            // No credential is used for the platform provider; when a
            // server-side engine is configured, the key stays on the server.
            'credentials' => self::provider() === self::PROVIDER_PLATFORM ? 'none' : 'server_side',
            'locale' => $code,
            'direction' => Locale::direction($code),
            'tag' => self::tag($code),
            'stt' => [
                'available' => $enabled,
                'support' => self::sttSupport($code),
                'verified_locales' => self::sttVerifiedLocales(),
                'mode' => 'single_utterance',
                'max_seconds' => self::maxSeconds(),
            ],
            'tts' => [
                'available' => $enabled,
                'support' => self::ttsSupport($code),
                'verified_locales' => self::ttsVerifiedLocales(),
                'max_chars' => self::maxChars(),
            ],
            'privacy' => [
                'audio_sent_to_server' => false,
                'audio_retained' => self::retainsAudio(),
                'continuous_listening' => false,
                'text_alternative' => true,
            ],
            'limits' => [
                'max_seconds' => self::maxSeconds(),
                'max_chars' => self::maxChars(),
            ],
            'messages' => self::allMessages($code),
        ];
    }

    /** @return array<string, string> */
    public static function allMessages(string $locale): array
    {
        $table = self::MESSAGES[$locale] ?? self::MESSAGES['en'];

        return $table;
    }

    public static function msg(string $key, string $locale, array $params = []): string
    {
        $table = self::MESSAGES[$locale] ?? self::MESSAGES['en'];
        $text = $table[$key] ?? (self::MESSAGES['en'][$key] ?? $key);

        foreach ($params as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /** Every locale carries the same keys, all non-empty. */
    public static function messagesComplete(): bool
    {
        $reference = array_keys(self::MESSAGES['en']);
        sort($reference);

        foreach (self::MESSAGES as $table) {
            $keys = array_keys($table);
            sort($keys);
            if ($keys !== $reference) {
                return false;
            }
            foreach ($table as $value) {
                if (! is_string($value) || trim($value) === '') {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<int, mixed> $values @return array<int, string> */
    private static function cleanLocales(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $code = Locale::normalize(is_string($value) ? $value : null);
            if ($code !== null && ! in_array($code, $out, true)) {
                $out[] = $code;
            }
        }

        return $out;
    }
}
