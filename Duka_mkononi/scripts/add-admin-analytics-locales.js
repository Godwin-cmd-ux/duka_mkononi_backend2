#!/usr/bin/env node
/**
 * Capability #20 - Natural-Language Admin Analytics.
 *
 * Adds the "admin_analytics" string namespace to every locale file
 * (Duka_mkononi/locales/*.json) AND to the website's single translation source
 * (locales.json -> translations.<lang>), so the Super Admin analytics panel is
 * fully translated in all 8 supported languages on both clients.
 *
 * Run from Duka_mkononi:  node scripts/add-admin-analytics-locales.js
 *
 * Safe: parses each file, validates the result, then writes it back with the
 * same 2-space indent + trailing newline the files already use. An existing
 * "admin_analytics" section is replaced, never duplicated.
 */
const fs = require('fs');
const path = require('path');

const LANGS = ['en', 'sw', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

const STRINGS = {
  en: {
    nav_analytics: 'Analytics',
    title: 'Platform analytics',
    subtitle: 'Ask about platform operations in your own words - users, activity, certification and sales',
    ask_placeholder: 'e.g. How many sellers joined last month?',
    ask_button: 'Ask',
    clear: 'Clear',
    loading: 'Reading platform data...',
    error: 'Could not answer that question. Please try again.',
    retry: 'Try again',
    examples_title: 'Example questions',
    table_title: 'Details',
    chart_title: 'Chart',
    sources_title: 'Data sources',
    limitations_title: 'What this answer does not cover',
    empty_state: 'Ask a question to see the numbers, the table and the chart.',
    scope_badge: 'Platform-wide - Super Admin only',
    no_sql_note: 'No SQL is generated; your question only selects from the listed metrics.',
    privacy_note: 'Answers contain totals and business names only - no emails, phone numbers or personal data.',
    live_badge: 'Live snapshot',
    period_badge: 'Recorded period',
    results_title: 'Answer',
  },
  sw: {
    nav_analytics: 'Uchanganuzi',
    title: 'Uchanganuzi wa jukwaa',
    subtitle: 'Uliza kuhusu shughuli za jukwaa kwa maneno yako mwenyewe - watumiaji, shughuli, uthibitisho na mauzo',
    ask_placeholder: 'mf. Wauzaji wangapi walijiunga mwezi uliopita?',
    ask_button: 'Uliza',
    clear: 'Futa',
    loading: 'Inasoma data ya jukwaa...',
    error: 'Imeshindikana kujibu swali hilo. Tafadhali jaribu tena.',
    retry: 'Jaribu tena',
    examples_title: 'Mifano ya maswali',
    table_title: 'Maelezo',
    chart_title: 'Chati',
    sources_title: 'Vyanzo vya data',
    limitations_title: 'Jibu hili halijumuishi nini',
    empty_state: 'Uliza swali ili kuona namba, jedwali na chati.',
    scope_badge: 'Jukwaa lote - Msimamizi Mkuu tu',
    no_sql_note: 'Hakuna SQL inayotengenezwa; swali lako linachagua tu kati ya vipimo vilivyoorodheshwa.',
    privacy_note: 'Majibu yana jumla na majina ya biashara tu - hakuna barua pepe, namba za simu wala taarifa za mtu mmoja mmoja.',
    live_badge: 'Hali ya sasa',
    period_badge: 'Kipindi kilichorekodiwa',
    results_title: 'Jibu',
  },
  fr: {
    nav_analytics: 'Analyses',
    title: 'Analyses de la plateforme',
    subtitle: 'Posez vos questions sur les opérations de la plateforme dans vos propres mots - utilisateurs, activité, certification et ventes',
    ask_placeholder: 'ex. Combien de vendeurs se sont inscrits le mois dernier ?',
    ask_button: 'Demander',
    clear: 'Effacer',
    loading: 'Lecture des données de la plateforme...',
    error: 'Impossible de répondre à cette question. Veuillez réessayer.',
    retry: 'Réessayer',
    examples_title: 'Exemples de questions',
    table_title: 'Détails',
    chart_title: 'Graphique',
    sources_title: 'Sources de données',
    limitations_title: 'Ce que cette réponse ne couvre pas',
    empty_state: 'Posez une question pour voir les chiffres, le tableau et le graphique.',
    scope_badge: 'Toute la plateforme - Super Admin uniquement',
    no_sql_note: "Aucun SQL n'est généré ; votre question ne choisit que parmi les indicateurs listés.",
    privacy_note: "Les réponses ne contiennent que des totaux et des noms d'entreprises - aucun e-mail, numéro de téléphone ni donnée personnelle.",
    live_badge: 'Instantané en direct',
    period_badge: 'Période enregistrée',
    results_title: 'Réponse',
  },
  hi: {
    nav_analytics: 'विश्लेषण',
    title: 'प्लेटफ़ॉर्म विश्लेषण',
    subtitle: 'प्लेटफ़ॉर्म के संचालन के बारे में अपने शब्दों में पूछें - उपयोगकर्ता, गतिविधि, प्रमाणन और बिक्री',
    ask_placeholder: 'उदा. पिछले महीने कितने विक्रेता जुड़े?',
    ask_button: 'पूछें',
    clear: 'साफ़ करें',
    loading: 'प्लेटफ़ॉर्म डेटा पढ़ा जा रहा है...',
    error: 'इस प्रश्न का उत्तर नहीं दिया जा सका। कृपया पुनः प्रयास करें।',
    retry: 'पुनः प्रयास करें',
    examples_title: 'उदाहरण प्रश्न',
    table_title: 'विवरण',
    chart_title: 'चार्ट',
    sources_title: 'डेटा स्रोत',
    limitations_title: 'यह उत्तर क्या शामिल नहीं करता',
    empty_state: 'संख्याएँ, तालिका और चार्ट देखने के लिए प्रश्न पूछें।',
    scope_badge: 'पूरा प्लेटफ़ॉर्म - केवल सुपर एडमिन',
    no_sql_note: 'कोई SQL नहीं बनाया जाता; आपका प्रश्न केवल सूचीबद्ध मेट्रिक्स में से चुनता है।',
    privacy_note: 'उत्तरों में केवल कुल संख्याएँ और व्यवसाय के नाम होते हैं - कोई ईमेल, फ़ोन नंबर या व्यक्तिगत जानकारी नहीं।',
    live_badge: 'लाइव स्नैपशॉट',
    period_badge: 'दर्ज अवधि',
    results_title: 'उत्तर',
  },
  es: {
    nav_analytics: 'Analíticas',
    title: 'Analíticas de la plataforma',
    subtitle: 'Pregunta sobre las operaciones de la plataforma con tus propias palabras: usuarios, actividad, certificación y ventas',
    ask_placeholder: 'p. ej. ¿Cuántos vendedores se registraron el mes pasado?',
    ask_button: 'Preguntar',
    clear: 'Limpiar',
    loading: 'Leyendo los datos de la plataforma...',
    error: 'No se pudo responder esa pregunta. Inténtalo de nuevo.',
    retry: 'Reintentar',
    examples_title: 'Preguntas de ejemplo',
    table_title: 'Detalles',
    chart_title: 'Gráfico',
    sources_title: 'Fuentes de datos',
    limitations_title: 'Lo que esta respuesta no cubre',
    empty_state: 'Haz una pregunta para ver las cifras, la tabla y el gráfico.',
    scope_badge: 'Toda la plataforma - solo Super Admin',
    no_sql_note: 'No se genera SQL; tu pregunta solo elige entre las métricas listadas.',
    privacy_note: 'Las respuestas contienen solo totales y nombres de empresas: ningún correo, teléfono ni dato personal.',
    live_badge: 'Instantánea en vivo',
    period_badge: 'Período registrado',
    results_title: 'Respuesta',
  },
  ur: {
    nav_analytics: 'تجزیات',
    title: 'پلیٹ فارم تجزیات',
    subtitle: 'پلیٹ فارم کے امور کے بارے میں اپنے الفاظ میں پوچھیں - صارفین، سرگرمی، تصدیق اور فروخت',
    ask_placeholder: 'مثلاً پچھلے مہینے کتنے بیچنے والے شامل ہوئے؟',
    ask_button: 'پوچھیں',
    clear: 'صاف کریں',
    loading: 'پلیٹ فارم ڈیٹا پڑھا جا رہا ہے...',
    error: 'اس سوال کا جواب نہیں دیا جا سکا۔ براہ کرم دوبارہ کوشش کریں۔',
    retry: 'دوبارہ کوشش کریں',
    examples_title: 'مثال کے سوالات',
    table_title: 'تفصیلات',
    chart_title: 'چارٹ',
    sources_title: 'ڈیٹا کے ذرائع',
    limitations_title: 'اس جواب میں کیا شامل نہیں',
    empty_state: 'اعداد، جدول اور چارٹ دیکھنے کے لیے سوال پوچھیں۔',
    scope_badge: 'پورا پلیٹ فارم - صرف سپر ایڈمن',
    no_sql_note: 'کوئی SQL نہیں بنایا جاتا؛ آپ کا سوال صرف فہرست شدہ میٹرکس میں سے چنتا ہے۔',
    privacy_note: 'جوابات میں صرف مجموعی اعداد اور کاروبار کے نام ہوتے ہیں - کوئی ای میل، فون نمبر یا ذاتی معلومات نہیں۔',
    live_badge: 'لائیو اسنیپ شاٹ',
    period_badge: 'درج شدہ مدت',
    results_title: 'جواب',
  },
  de: {
    nav_analytics: 'Analysen',
    title: 'Plattform-Analysen',
    subtitle: 'Fragen Sie in eigenen Worten zu Plattformvorgängen - Nutzer, Aktivität, Zertifizierung und Umsatz',
    ask_placeholder: 'z. B. Wie viele Verkäufer sind letzten Monat beigetreten?',
    ask_button: 'Fragen',
    clear: 'Leeren',
    loading: 'Plattformdaten werden gelesen...',
    error: 'Diese Frage konnte nicht beantwortet werden. Bitte erneut versuchen.',
    retry: 'Erneut versuchen',
    examples_title: 'Beispielfragen',
    table_title: 'Details',
    chart_title: 'Diagramm',
    sources_title: 'Datenquellen',
    limitations_title: 'Was diese Antwort nicht abdeckt',
    empty_state: 'Stellen Sie eine Frage, um Zahlen, Tabelle und Diagramm zu sehen.',
    scope_badge: 'Plattformweit - nur Super-Admin',
    no_sql_note: 'Es wird kein SQL erzeugt; Ihre Frage wählt nur aus den gelisteten Kennzahlen.',
    privacy_note: 'Antworten enthalten nur Summen und Firmennamen - keine E-Mails, Telefonnummern oder persönlichen Daten.',
    live_badge: 'Live-Momentaufnahme',
    period_badge: 'Erfasster Zeitraum',
    results_title: 'Antwort',
  },
  zh: {
    nav_analytics: '分析',
    title: '平台分析',
    subtitle: '用您自己的话询问平台运营情况——用户、活动、认证和销售额',
    ask_placeholder: '例如：上个月新增了多少卖家？',
    ask_button: '询问',
    clear: '清除',
    loading: '正在读取平台数据...',
    error: '无法回答该问题，请重试。',
    retry: '重试',
    examples_title: '示例问题',
    table_title: '详情',
    chart_title: '图表',
    sources_title: '数据来源',
    limitations_title: '此回答未涵盖的内容',
    empty_state: '提出一个问题即可查看数字、表格和图表。',
    scope_badge: '全平台——仅限超级管理员',
    no_sql_note: '系统不会生成 SQL；您的问题只会在已列出的指标中选择。',
    privacy_note: '回答仅包含汇总数据和商家名称——不包含邮箱、电话或个人数据。',
    live_badge: '实时快照',
    period_badge: '记录期间',
    results_title: '回答',
  },
};

function writeJson(file, data, hadTrailingNewline) {
  const text = JSON.stringify(data, null, 2) + (hadTrailingNewline ? '\n' : '');
  JSON.parse(text);
  fs.writeFileSync(file, text);
}

function main() {
  const mobileDir = path.join(__dirname, '..', 'locales');
  const rootFile = path.join(__dirname, '..', '..', 'locales.json');

  // 1. Mobile app locale files.
  for (const lang of LANGS) {
    const file = path.join(mobileDir, `${lang}.json`);
    const original = fs.readFileSync(file, 'utf8');
    const data = JSON.parse(original);
    data.admin_analytics = STRINGS[lang];
    writeJson(file, data, original.endsWith('\n'));
    console.log(`  ✅ locales/${lang}.json updated`);
  }

  // 2. Website translation source (translations.<lang>.admin_analytics).
  const rootOriginal = fs.readFileSync(rootFile, 'utf8');
  const root = JSON.parse(rootOriginal);
  let touched = 0;
  for (const lang of LANGS) {
    if (!root.translations || !root.translations[lang]) {
      console.warn(`  ⚠️ translations.${lang} missing in locales.json - skipped`);
      continue;
    }
    root.translations[lang].admin_analytics = STRINGS[lang];
    touched += 1;
  }
  writeJson(rootFile, root, rootOriginal.endsWith('\n'));
  console.log(`  ✅ locales.json updated (${touched}/${LANGS.length} languages)`);
  console.log('🎉 admin_analytics strings are in every locale.');
}

main();
