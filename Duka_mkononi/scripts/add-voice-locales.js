#!/usr/bin/env node
/**
 * Capability #23 - Voice Input & Voice Answers.
 *
 * Adds the "voice" string namespace to every locale file
 * (Duka_mkononi/locales/*.json) AND to the website's single translation source
 * (locales.json -> translations.<lang>), so the microphone button, the
 * listening/reading status, the permission and failure messages and the
 * "type instead" fallbacks are translated in all 8 languages.
 *
 * These strings are the client-side chrome. The speech ITSELF is produced by
 * the device (browser Web Speech API / native engine); the capability facts
 * (which locales have verified coverage, the limits, the privacy promise) come
 * from GET /api/ai/speech/capabilities.
 *
 * The same 13 keys are mirrored in App\Services\Ai\Speech::MESSAGES (served by
 * that endpoint), and tests/Unit/SpeechTest.php asserts the three sources agree
 * so they cannot drift.
 *
 * Run from Duka_mkononi:  node scripts/add-voice-locales.js
 *
 * Safe: parses each file, validates the result, then writes it back with the
 * same 2-space indent (+ trailing newline when the file had one). An existing
 * "voice" section is replaced, never duplicated. The brand "Duka Mkononi" is a
 * literal and is never translated.
 */
const fs = require('fs');
const path = require('path');

const LANGS = ['en', 'sw', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

const STRINGS = {
  en: {
    title: 'Voice',
    listen: 'Speak',
    listening: 'Listening…',
    stop: 'Stop',
    speak: 'Listen',
    speaking: 'Reading aloud…',
    stop_speaking: 'Stop reading',
    unsupported: 'Voice input is not available on this device. You can type your question instead.',
    denied: 'Microphone access was blocked. Allow it in your browser or device settings, or type instead.',
    no_speech: 'I did not catch that. Try again, or type instead.',
    error: 'Voice input did not work. Please type your question instead.',
    privacy: 'Your voice is not recorded or sent to Duka Mkononi; only the recognised text is used.',
    device_note: 'Voice coverage depends on your device and browser. Some languages may not be available.',
  },
  sw: {
    title: 'Sauti',
    listen: 'Zungumza',
    listening: 'Nasikiliza…',
    stop: 'Simama',
    speak: 'Sikiliza',
    speaking: 'Ninasoma kwa sauti…',
    stop_speaking: 'Acha kusoma',
    unsupported: 'Kuingiza kwa sauti hakutumiki kwenye kifaa hiki. Unaweza kuandika swali lako.',
    denied: 'Ruhusa ya maikrofoni imezuiwa. Iruhusu kwenye mipangilio ya kivinjari au kifaa, au andika badala yake.',
    no_speech: 'Sikusikia vizuri. Jaribu tena, au andika badala yake.',
    error: 'Kuingiza kwa sauti hakufanikiwa. Tafadhali andika swali lako.',
    privacy: 'Sauti yako hairekodiwi wala kutumwa kwa Duka Mkononi; maandishi yanayotambuliwa pekee ndiyo yanatumika.',
    device_note: 'Uwezo wa sauti unategemea kifaa chako na kivinjari. Lugha zingine huenda zisiweze kupatikana.',
  },
  fr: {
    title: 'Voix',
    listen: 'Parler',
    listening: 'Écoute…',
    stop: 'Arrêter',
    speak: 'Écouter',
    speaking: 'Lecture en cours…',
    stop_speaking: 'Arrêter la lecture',
    unsupported: "La saisie vocale n'est pas disponible sur cet appareil. Vous pouvez saisir votre question.",
    denied: "L'accès au microphone a été bloqué. Autorisez-le dans les réglages, ou saisissez votre question.",
    no_speech: "Je n'ai pas compris. Réessayez, ou saisissez votre question.",
    error: "La saisie vocale n'a pas fonctionné. Veuillez saisir votre question.",
    privacy: "Votre voix n'est ni enregistrée ni envoyée à Duka Mkononi ; seul le texte reconnu est utilisé.",
    device_note: 'La couverture vocale dépend de votre appareil et de votre navigateur. Certaines langues peuvent être indisponibles.',
  },
  hi: {
    title: 'आवाज़',
    listen: 'बोलें',
    listening: 'सुन रहा हूँ…',
    stop: 'रोकें',
    speak: 'सुनें',
    speaking: 'पढ़ा जा रहा है…',
    stop_speaking: 'पढ़ना बंद करें',
    unsupported: 'इस डिवाइस पर आवाज़ से इनपुट उपलब्ध नहीं है। आप अपना सवाल टाइप कर सकते हैं।',
    denied: 'माइक्रोफ़ोन की अनुमति रोक दी गई है। सेटिंग्स में इसे अनुमति दें, या टाइप करें।',
    no_speech: 'मैं समझ नहीं पाया। फिर कोशिश करें, या टाइप करें।',
    error: 'आवाज़ से इनपुट काम नहीं किया। कृपया अपना सवाल टाइप करें।',
    privacy: 'आपकी आवाज़ रिकॉर्ड या Duka Mkononi को नहीं भेजी जाती; केवल पहचाना गया टेक्स्ट इस्तेमाल होता है।',
    device_note: 'आवाज़ की उपलब्धता आपके डिवाइस और ब्राउज़र पर निर्भर है। कुछ भाषाएँ उपलब्ध न हों।',
  },
  es: {
    title: 'Voz',
    listen: 'Hablar',
    listening: 'Escuchando…',
    stop: 'Detener',
    speak: 'Escuchar',
    speaking: 'Leyendo en voz alta…',
    stop_speaking: 'Dejar de leer',
    unsupported: 'La entrada de voz no está disponible en este dispositivo. Puedes escribir tu pregunta.',
    denied: 'Se bloqueó el acceso al micrófono. Permítelo en los ajustes, o escribe tu pregunta.',
    no_speech: 'No te entendí. Inténtalo de nuevo, o escribe tu pregunta.',
    error: 'La entrada de voz no funcionó. Escribe tu pregunta, por favor.',
    privacy: 'Tu voz no se graba ni se envía a Duka Mkononi; solo se usa el texto reconocido.',
    device_note: 'La cobertura de voz depende de tu dispositivo y navegador. Puede que algunos idiomas no estén disponibles.',
  },
  ur: {
    title: 'آواز',
    listen: 'بولیں',
    listening: 'سن رہا ہوں…',
    stop: 'رکیں',
    speak: 'سنیں',
    speaking: 'بلند آواز میں پڑھا جا رہا ہے…',
    stop_speaking: 'پڑھنا بند کریں',
    unsupported: 'اس ڈیوائس پر آواز سے ان پٹ دستیاب نہیں ہے۔ آپ اپنا سوال ٹائپ کر سکتے ہیں۔',
    denied: 'مائیکروفون کی اجازت روک دی گئی ہے۔ ترتیبات میں اجازت دیں، یا ٹائپ کریں۔',
    no_speech: 'میں سمجھ نہیں سکا۔ دوبارہ کوشش کریں، یا ٹائپ کریں۔',
    error: 'آواز سے ان پٹ کام نہیں کیا۔ براہ کرم اپنا سوال ٹائپ کریں۔',
    privacy: 'آپ کی آواز ریکارڈ یا Duka Mkononi کو نہیں بھیجی جاتی؛ صرف پہچانا گیا متن استعمال ہوتا ہے۔',
    device_note: 'آواز کی دستیابی آپ کے ڈیوائس اور براؤزر پر منحصر ہے۔ کچھ زبانیں دستیاب ن ہوں۔',
  },
  de: {
    title: 'Stimme',
    listen: 'Sprechen',
    listening: 'Ich höre zu…',
    stop: 'Stoppen',
    speak: 'Anhören',
    speaking: 'Wird vorgelesen…',
    stop_speaking: 'Vorlesen beenden',
    unsupported: 'Spracheingabe ist auf diesem Gerät nicht verfügbar. Du kannst deine Frage stattdessen tippen.',
    denied: 'Der Mikrofonzugriff wurde blockiert. Erlaube ihn in den Einstellungen, oder tippe stattdessen.',
    no_speech: 'Das habe ich nicht verstanden. Versuche es erneut, oder tippe stattdessen.',
    error: 'Die Spracheingabe hat nicht funktioniert. Bitte tippe deine Frage.',
    privacy: 'Deine Stimme wird nicht aufgezeichnet und nicht an Duka Mkononi gesendet; nur der erkannte Text wird verwendet.',
    device_note: 'Die Sprachabdeckung hängt von deinem Gerät und Browser ab. Manche Sprachen sind eventuell nicht verfügbar.',
  },
  zh: {
    title: '语音',
    listen: '说话',
    listening: '正在聆听…',
    stop: '停止',
    speak: '朗读',
    speaking: '正在朗读…',
    stop_speaking: '停止朗读',
    unsupported: '此设备不支持语音输入。你可以改为输入问题。',
    denied: '麦克风权限已被阻止。请在设置中允许，或改为输入。',
    no_speech: '我没有听清。请重试，或改为输入。',
    error: '语音输入失败。请输入你的问题。',
    privacy: '你的语音不会被录音，也不会发送给 Duka Mkononi；仅使用识别出的文字。',
    device_note: '语音支持取决于你的设备和浏览器，某些语言可能不可用。',
  },
};

// Every locale must carry the exact same key set, or the UI would show a key.
const KEYS = Object.keys(STRINGS.en).sort();

function assertComplete(lang, section) {
  const keys = Object.keys(section).sort();
  if (JSON.stringify(keys) !== JSON.stringify(KEYS)) {
    throw new Error(`voice/${lang} key mismatch: ${JSON.stringify(keys)}`);
  }
  for (const key of KEYS) {
    if (typeof section[key] !== 'string' || section[key].trim() === '') {
      throw new Error(`voice/${lang}.${key} must be a non-empty string`);
    }
  }
}

function writeJson(file, data, hadTrailingNewline) {
  const text = JSON.stringify(data, null, 2) + (hadTrailingNewline ? '\n' : '');
  JSON.parse(text);
  fs.writeFileSync(file, text);
}

function main() {
  const mobileDir = path.join(__dirname, '..', 'locales');
  const rootFile = path.join(__dirname, '..', '..', 'locales.json');

  for (const lang of LANGS) {
    assertComplete(lang, STRINGS[lang]);
  }

  // 1. Mobile app locale files.
  for (const lang of LANGS) {
    const file = path.join(mobileDir, `${lang}.json`);
    const original = fs.readFileSync(file, 'utf8');
    const data = JSON.parse(original);
    data.voice = STRINGS[lang];
    writeJson(file, data, original.endsWith('\n'));
    console.log(`  ✅ locales/${lang}.json updated`);
  }

  // 2. Website translation source (translations.<lang>.voice).
  const rootOriginal = fs.readFileSync(rootFile, 'utf8');
  const root = JSON.parse(rootOriginal);
  let touched = 0;
  for (const lang of LANGS) {
    if (!root.translations || !root.translations[lang]) {
      console.warn(`  ⚠️ translations.${lang} missing in locales.json - skipped`);
      continue;
    }
    root.translations[lang].voice = STRINGS[lang];
    touched += 1;
  }
  writeJson(rootFile, root, rootOriginal.endsWith('\n'));
  console.log(`  ✅ locales.json updated (${touched}/${LANGS.length} languages)`);
  console.log(`🎉 voice strings (${KEYS.length} keys) are in every locale.`);
}

main();
