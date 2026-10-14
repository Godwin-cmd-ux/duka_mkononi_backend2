#!/usr/bin/env node
/**
 * Capability #21 - Public AI Concierge.
 *
 * Adds the "public_concierge" string namespace to every locale file
 * (Duka_mkononi/locales/*.json) AND to the website's single translation source
 * (locales.json -> translations.<lang>), so the concierge widget on the public
 * website and the concierge entry point in the mobile app are fully translated
 * in all 8 supported languages.
 *
 * Only the UI chrome lives here (labels, hints, loading and error text). Every
 * factual string the visitor reads - the topics, the example questions, the
 * answers, the notices and the link labels - is served by the concierge API in
 * the visitor's language, so there is exactly one source for it.
 *
 * Run from Duka_mkononi:  node scripts/add-public-concierge-locales.js
 *
 * Safe: parses each file, validates the result, then writes it back with the
 * same 2-space indent + trailing newline the files already use. An existing
 * "public_concierge" section is replaced, never duplicated.
 */
const fs = require('fs');
const path = require('path');

const LANGS = ['en', 'sw', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

const STRINGS = {
  en: {
    open: 'Ask us',
    title: 'Duka Mkononi assistant',
    subtitle: 'How the marketplace works, how to get started, and where to find the app',
    ask_placeholder: 'e.g. How do I buy a product?',
    ask_button: 'Ask',
    loading: 'Looking for an answer...',
    error: 'Could not answer that right now. Please try again.',
    topics_title: 'Topics I can explain',
    examples_title: 'Example questions',
    links_title: 'Useful pages',
    results_title: 'Products found',
    businesses_title: 'Businesses found',
    unknown: 'I can only answer from public marketplace information - try one of the examples above.',
    help_note: 'Public information only: what Duka Mkononi is, how buying, selling, delivery and payment work, and how to get the app.',
    empty_state: 'Ask a question above, or pick one of the examples.',
  },
  sw: {
    open: 'Uliza',
    title: 'Msaidizi wa Duka Mkononi',
    subtitle: 'Jinsi soko linavyofanya kazi, jinsi ya kuanza, na wapi kupata programu',
    ask_placeholder: 'mf. Nitaje bidhaa vipi?',
    ask_button: 'Uliza',
    loading: 'Inatafuta jibu...',
    error: 'Imeshindikana kujibu kwa sasa. Tafadhali jaribu tena.',
    topics_title: 'Mada ninazoweza kueleza',
    examples_title: 'Mifano ya maswali',
    links_title: 'Kurasa muhimu',
    results_title: 'Bidhaa zilizopatikana',
    businesses_title: 'Biashara zilizopatikana',
    unknown: 'Naweza kujibu kwa taarifa za umma za soko tu - jaribu mojawapo ya mifano hapo juu.',
    help_note: 'Taarifa za umma tu: Duka Mkononi ni nini, ununuzi, uuzaji, usafirishaji na malipo hufanyaje, na wapi kupata programu.',
    empty_state: 'Uliza swali hapo juu, au chagua mojawapo ya mifano.',
  },
  fr: {
    open: 'Nous demander',
    title: 'Assistant Duka Mkononi',
    subtitle: "Comment fonctionne la place de marche, comment demarrer et ou trouver l'application",
    ask_placeholder: 'ex. Comment acheter un produit ?',
    ask_button: 'Demander',
    loading: "Recherche d'une reponse...",
    error: 'Impossible de repondre pour le moment. Veuillez reessayer.',
    topics_title: 'Sujets que je peux expliquer',
    examples_title: 'Exemples de questions',
    links_title: 'Pages utiles',
    results_title: 'Produits trouves',
    businesses_title: 'Entreprises trouvees',
    unknown: "Je ne peux repondre qu'avec les informations publiques de la place de marche - essayez un des exemples ci-dessus.",
    help_note: "Informations publiques uniquement : ce qu'est Duka Mkononi, le fonctionnement des achats, de la vente, de la livraison et du paiement, et ou trouver l'application.",
    empty_state: 'Posez une question ci-dessus ou choisissez un exemple.',
  },
  hi: {
    open: 'हमसे पूछें',
    title: 'Duka Mkononi सहायक',
    subtitle: 'बाज़ार कैसे काम करता है, शुरुआत कैसे करें और ऐप कहाँ से लें',
    ask_placeholder: 'उदा. मैं उत्पाद कैसे खरीदूँ?',
    ask_button: 'पूछें',
    loading: 'उत्तर खोजा जा रहा है...',
    error: 'अभी उत्तर नहीं दिया जा सका। कृपया पुनः प्रयास करें।',
    topics_title: 'विषय जिन्हें मैं समझा सकता हूँ',
    examples_title: 'उदाहरण प्रश्न',
    links_title: 'उपयोगी पृष्ठ',
    results_title: 'मिले उत्पाद',
    businesses_title: 'मिले व्यवसाय',
    unknown: 'मैं केवल बाज़ार की सार्वजनिक जानकारी से उत्तर दे सकता हूँ - ऊपर दिया कोई उदाहरण आज़माएँ।',
    help_note: 'केवल सार्वजनिक जानकारी: Duka Mkononi क्या है, खरीद, बिक्री, डिलीवरी और भुगतान कैसे होते हैं, और ऐप कहाँ मिलेगा।',
    empty_state: 'ऊपर प्रश्न पूछें, या कोई उदाहरण चुनें।',
  },
  es: {
    open: 'Preguntanos',
    title: 'Asistente de Duka Mkononi',
    subtitle: 'Como funciona el mercado, como empezar y donde conseguir la aplicacion',
    ask_placeholder: 'p. ej. Como compro un producto?',
    ask_button: 'Preguntar',
    loading: 'Buscando una respuesta...',
    error: 'No se pudo responder ahora mismo. Intentalo de nuevo.',
    topics_title: 'Temas que puedo explicar',
    examples_title: 'Preguntas de ejemplo',
    links_title: 'Paginas utiles',
    results_title: 'Productos encontrados',
    businesses_title: 'Empresas encontradas',
    unknown: 'Solo puedo responder con informacion publica del mercado: prueba uno de los ejemplos de arriba.',
    help_note: 'Solo informacion publica: que es Duka Mkononi, como funcionan las compras, las ventas, la entrega y el pago, y donde conseguir la aplicacion.',
    empty_state: 'Haz una pregunta arriba o elige un ejemplo.',
  },
  ur: {
    open: 'ہم سے پوچھیں',
    title: 'Duka Mkononi اسسٹنٹ',
    subtitle: 'بازار کیسے کام کرتا ہے، شروعات کیسے کریں اور ایپ کہاں سے ملے گی',
    ask_placeholder: 'مثلاً میں پروڈکٹ کیسے خریدوں؟',
    ask_button: 'پوچھیں',
    loading: 'جواب تلاش کیا جا رہا ہے...',
    error: 'ابھی جواب نہیں دیا جا سکا۔ براہ کرم دوبارہ کوشش کریں۔',
    topics_title: 'وہ موضوعات جو میں سمجھا سکتا ہوں',
    examples_title: 'مثال کے سوالات',
    links_title: 'مفید صفحات',
    results_title: 'ملنے والی مصنوعات',
    businesses_title: 'ملنے والے کاروبار',
    unknown: 'میں صرف بازار کی عوامی معلومات سے جواب دے سکتا ہوں - اوپر دی گئی کوئی مثال آزمائیں۔',
    help_note: 'صرف عوامی معلومات: Duka Mkononi کیا ہے، خریداری، فروخت، ڈیلیوری اور ادائیگی کیسے ہوتی ہے، اور ایپ کہاں سے ملے گی۔',
    empty_state: 'اوپر سوال پوچھیں، یا کوئی مثال منتخب کریں۔',
  },
  de: {
    open: 'Frag uns',
    title: 'Duka Mkononi Assistent',
    subtitle: 'Wie der Marktplatz funktioniert, wie man startet und wo es die App gibt',
    ask_placeholder: 'z. B. Wie kaufe ich ein Produkt?',
    ask_button: 'Fragen',
    loading: 'Antwort wird gesucht...',
    error: 'Das konnte gerade nicht beantwortet werden. Bitte erneut versuchen.',
    topics_title: 'Themen, die ich erklaren kann',
    examples_title: 'Beispielfragen',
    links_title: 'Nutzliche Seiten',
    results_title: 'Gefundene Produkte',
    businesses_title: 'Gefundene Unternehmen',
    unknown: 'Ich kann nur mit offentlichen Marktplatz-Informationen antworten - probieren Sie eines der Beispiele oben.',
    help_note: 'Nur offentliche Informationen: was Duka Mkononi ist, wie Kauf, Verkauf, Lieferung und Zahlung ablaufen und wo es die App gibt.',
    empty_state: 'Stellen Sie oben eine Frage oder wahlen Sie ein Beispiel.',
  },
  zh: {
    open: '向我们来提问',
    title: 'Duka Mkononi 助手',
    subtitle: '商城如何运作、如何开始使用，以及在哪里获取应用',
    ask_placeholder: '例如：我如何购买商品？',
    ask_button: '询问',
    loading: '正在查找答案...',
    error: '暂时无法回答。请重试。',
    topics_title: '我可以解释的主题',
    examples_title: '示例问题',
    links_title: '有用页面',
    results_title: '找到的商品',
    businesses_title: '找到的商家',
    unknown: '我只能依据商城的公开信息作答——请试用上面的示例。',
    help_note: '仅限公开信息：Duka Mkononi 是什么、购买、销售、配送和支付如何运作，以及在哪里获取应用。',
    empty_state: '请在上方提问，或选择一个示例。',
  },
};

// Every locale must carry the exact same key set, or the UI would show a key.
const KEYS = Object.keys(STRINGS.en).sort();

function assertComplete(lang, section) {
  const keys = Object.keys(section).sort();
  if (JSON.stringify(keys) !== JSON.stringify(KEYS)) {
    throw new Error(`public_concierge/${lang} key mismatch: ${JSON.stringify(keys)}`);
  }
  for (const key of KEYS) {
    if (typeof section[key] !== 'string' || section[key].trim() === '') {
      throw new Error(`public_concierge/${lang}.${key} must be a non-empty string`);
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
    data.public_concierge = STRINGS[lang];
    writeJson(file, data, original.endsWith('\n'));
    console.log(`  ✅ locales/${lang}.json updated`);
  }

  // 2. Website translation source (translations.<lang>.public_concierge).
  const rootOriginal = fs.readFileSync(rootFile, 'utf8');
  const root = JSON.parse(rootOriginal);
  let touched = 0;
  for (const lang of LANGS) {
    if (!root.translations || !root.translations[lang]) {
      console.warn(`  ⚠️ translations.${lang} missing in locales.json - skipped`);
      continue;
    }
    root.translations[lang].public_concierge = STRINGS[lang];
    touched += 1;
  }
  writeJson(rootFile, root, rootOriginal.endsWith('\n'));
  console.log(`  ✅ locales.json updated (${touched}/${LANGS.length} languages)`);
  console.log(`🎉 public_concierge strings (${KEYS.length} keys) are in every locale.`);
}

main();
