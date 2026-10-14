#!/usr/bin/env node
/**
 * Capability #25 - Schema-Free Natural-Language Data Entry.
 *
 * Adds the "nl_entry" string namespace to every locale file
 * (Duka_mkononi/locales/*.json) AND to the website's single translation source
 * (locales.json -> translations.<lang>), so the write-it-here panel (heading,
 * subtitle, placeholder, preview/confirm buttons, the match states and the
 * "this is not one of the four record types" explanation) is translated in all
 * 8 languages.
 *
 * These strings are the CLIENT CHROME. The capability itself - which record
 * types exist, what was extracted, what is missing, whether a product matched -
 * comes from POST /api/ai/entry/preview, and the flag sentences come from
 * App\Services\Ai\NlEntry::FLAG_MESSAGES.
 *
 * The same 36 keys are mirrored in App\Services\Ai\NlEntry::MESSAGES, and
 * tests/Unit/NlEntryTest.php asserts the service, the eight app locale files and
 * locales.json all agree - so they cannot drift.
 *
 * Run from Duka_mkononi:  node scripts/add-nl-entry-locales.js
 *
 * Safe: parses each file, validates the result, then writes it back with the
 * same 2-space indent (+ trailing newline when the file had one). An existing
 * "nl_entry" section is replaced, never duplicated. The brand "Duka Mkononi" is
 * a literal and is never translated.
 */
const fs = require('fs');
const path = require('path');

const LANGS = ['en', 'sw', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

const STRINGS = {
  en: {
    title: 'Write it, we record it',
    subtitle: 'Describe one thing that happened and check the preview before saving.',
    placeholder: 'e.g. Add 20 bottles of blue perfume to stock',
    analyze: 'Prepare preview',
    preview_title: 'Check before saving',
    confirm: 'Confirm and save',
    cancel: 'Cancel',
    retry: 'Try again',
    saved: 'Saved through the normal workflow.',
    unsupported: 'I could not tell which record this is. Write it as a product, a sale, an expense or a customer.',
    type_product: 'Product',
    type_sale: 'Sale',
    type_expense: 'Expense',
    type_customer: 'Customer',
    matched: 'Found in your catalogue',
    ambiguous: 'More than one product fits - choose one',
    not_found: 'Not in your catalogue',
    new_record: 'Will be created as a new record',
    characters_left: '{count} characters left',
    name: 'Name',
    category: 'Category',
    unit: 'Unit',
    stock: 'Stock',
    price: 'Price',
    selling_price: 'Selling price',
    description: 'Description',
    amount: 'Amount',
    date: 'Date',
    phone: 'Phone',
    email: 'Email',
    quantity: 'Quantity',
    unit_price: 'Unit price',
    customer: 'Customer',
    payment: 'Payment method',
    sign_in: 'Please sign in again.',
    check_missing: 'Fill in the fields marked as missing.',
  },
  sw: {
    title: 'Andika, sisi tunarekodi',
    subtitle: 'Eleza jambo moja lililotokea na uangalie muhtasari kabla ya kuhifadhi.',
    placeholder: 'mfano: Ongeza chupa 20 za manukato ya bluu kwenye stock',
    analyze: 'Tengeneza muhtasari',
    preview_title: 'Angalia kabla ya kuhifadhi',
    confirm: 'Thibitisha na hifadhi',
    cancel: 'Ghairi',
    retry: 'Jaribu tena',
    saved: 'Imehifadhiwa kwa mtiririko wa kawaida.',
    unsupported: 'Sikuweza kutambua rekodi hii. Andika kama bidhaa, mauzo, matumizi au mteja.',
    type_product: 'Bidhaa',
    type_sale: 'Mauzo',
    type_expense: 'Matumizi',
    type_customer: 'Mteja',
    matched: 'Imepatikana kwenye katalogi yako',
    ambiguous: 'Bidhaa zaidi ya moja zinafanana - chagua moja',
    not_found: 'Haipo kwenye katalogi yako',
    new_record: 'Itaundwa kama rekodi mpya',
    characters_left: 'Herufi {count} zimebaki',
    name: 'Jina',
    category: 'Aina',
    unit: 'Kipimo',
    stock: 'Stock',
    price: 'Bei',
    selling_price: 'Bei ya kuuza',
    description: 'Maelezo',
    amount: 'Kiasi',
    date: 'Tarehe',
    phone: 'Simu',
    email: 'Barua pepe',
    quantity: 'Idadi',
    unit_price: 'Bei ya kimoja',
    customer: 'Mteja',
    payment: 'Njia ya malipo',
    sign_in: 'Tafadhali ingia tena.',
    check_missing: 'Jaza sehemu zilizoonyeshwa kama zinazokosekana.',
  },
  fr: {
    title: 'Écrivez, nous enregistrons',
    subtitle: "Décrivez une seule chose qui s'est passée et vérifiez l'aperçu avant d'enregistrer.",
    placeholder: 'ex. Ajouter 20 bouteilles de parfum bleu au stock',
    analyze: "Préparer l'aperçu",
    preview_title: "Vérifier avant d'enregistrer",
    confirm: 'Confirmer et enregistrer',
    cancel: 'Annuler',
    retry: 'Réessayer',
    saved: 'Enregistré via le flux habituel.',
    unsupported: "Je n'ai pas pu déterminer de quel enregistrement il s'agit. Écrivez-le comme un produit, une vente, une dépense ou un client.",
    type_product: 'Produit',
    type_sale: 'Vente',
    type_expense: 'Dépense',
    type_customer: 'Client',
    matched: 'Trouvé dans votre catalogue',
    ambiguous: 'Plusieurs produits correspondent - choisissez-en un',
    not_found: 'Absent de votre catalogue',
    new_record: 'Sera créé comme nouvel enregistrement',
    characters_left: '{count} caractères restants',
    name: 'Nom',
    category: 'Catégorie',
    unit: 'Unité',
    stock: 'Stock',
    price: 'Prix',
    selling_price: 'Prix de vente',
    description: 'Description',
    amount: 'Montant',
    date: 'Date',
    phone: 'Téléphone',
    email: 'E-mail',
    quantity: 'Quantité',
    unit_price: 'Prix unitaire',
    customer: 'Client',
    payment: 'Mode de paiement',
    sign_in: 'Veuillez vous reconnecter.',
    check_missing: 'Remplissez les champs signalés comme manquants.',
  },
  hi: {
    title: 'लिखें, हम दर्ज करेंगे',
    subtitle: 'जो हुआ उसे एक वाक्य में लिखें और सहेजने से पहले पूर्वावलोकन देखें।',
    placeholder: 'जैसे: स्टॉक में 20 बोतल नीली परफ्यूम जोड़ें',
    analyze: 'पूर्वावलोकन बनाएँ',
    preview_title: 'सहेजने से पहले जाँचें',
    confirm: 'पुष्टि करें और सहेजें',
    cancel: 'रद्द करें',
    retry: 'फिर कोशिश करें',
    saved: 'सामान्य प्रक्रिया से सहेजा गया।',
    unsupported: 'मैं यह नहीं पहचान सका कि यह कौन-सी प्रविष्टि है। इसे उत्पाद, बिक्री, खर्च या ग्राहक के रूप में लिखें।',
    type_product: 'उत्पाद',
    type_sale: 'बिक्री',
    type_expense: 'खर्च',
    type_customer: 'ग्राहक',
    matched: 'आपकी सूची में मिला',
    ambiguous: 'एक से अधिक उत्पाद मेल खाते हैं - एक चुनें',
    not_found: 'आपकी सूची में नहीं है',
    new_record: 'नई प्रविष्टि के रूप में बनेगा',
    characters_left: '{count} अक्षर शेष',
    name: 'नाम',
    category: 'श्रेणी',
    unit: 'इकाई',
    stock: 'स्टॉक',
    price: 'मूल्य',
    selling_price: 'बिक्री मूल्य',
    description: 'विवरण',
    amount: 'राशि',
    date: 'तारीख',
    phone: 'फ़ोन',
    email: 'ईमेल',
    quantity: 'मात्रा',
    unit_price: 'इकाई मूल्य',
    customer: 'ग्राहक',
    payment: 'भुगतान का तरीका',
    sign_in: 'कृपया दोबारा साइन इन करें।',
    check_missing: 'छूटे हुए बताए गए फ़ील्ड भरें।',
  },
  es: {
    title: 'Escríbelo, lo registramos',
    subtitle: 'Describe una sola cosa que pasó y revisa la vista previa antes de guardar.',
    placeholder: 'p. ej. Añadir 20 botellas de perfume azul al stock',
    analyze: 'Preparar vista previa',
    preview_title: 'Revisa antes de guardar',
    confirm: 'Confirmar y guardar',
    cancel: 'Cancelar',
    retry: 'Intentar de nuevo',
    saved: 'Guardado mediante el flujo habitual.',
    unsupported: 'No pude identificar qué registro es. Escríbelo como producto, venta, gasto o cliente.',
    type_product: 'Producto',
    type_sale: 'Venta',
    type_expense: 'Gasto',
    type_customer: 'Cliente',
    matched: 'Encontrado en tu catálogo',
    ambiguous: 'Más de un producto coincide: elige uno',
    not_found: 'No está en tu catálogo',
    new_record: 'Se creará como registro nuevo',
    characters_left: 'Quedan {count} caracteres',
    name: 'Nombre',
    category: 'Categoría',
    unit: 'Unidad',
    stock: 'Stock',
    price: 'Precio',
    selling_price: 'Precio de venta',
    description: 'Descripción',
    amount: 'Importe',
    date: 'Fecha',
    phone: 'Teléfono',
    email: 'Correo',
    quantity: 'Cantidad',
    unit_price: 'Precio unitario',
    customer: 'Cliente',
    payment: 'Método de pago',
    sign_in: 'Vuelve a iniciar sesión.',
    check_missing: 'Completa los campos marcados como faltantes.',
  },
  ur: {
    title: 'لکھیں، ہم درج کریں گے',
    subtitle: 'جو ہوا اسے ایک جملے میں لکھیں اور محفوظ کرنے سے پہلے جھلک دیکھیں۔',
    placeholder: 'مثلاً: اسٹاک میں 20 بوتلیں نیلی خوشبو شامل کریں',
    analyze: 'جھلک تیار کریں',
    preview_title: 'محفوظ کرنے سے پہلے دیکھیں',
    confirm: 'تصدیق کریں اور محفوظ کریں',
    cancel: 'منسوخ کریں',
    retry: 'دوبارہ کوشش کریں',
    saved: 'عام طریقہ کار سے محفوظ ہو گیا۔',
    unsupported: 'میں یہ نہیں پہچان سکا کہ یہ کون سا ریکارڈ ہے۔ اسے پروڈکٹ، فروخت، خرچ یا گاہک کے طور پر لکھیں۔',
    type_product: 'پروڈکٹ',
    type_sale: 'فروخت',
    type_expense: 'خرچ',
    type_customer: 'گاہک',
    matched: 'آپ کی فہرست میں ملا',
    ambiguous: 'ایک سے زیادہ پروڈکٹ مماثل ہیں - ایک منتخب کریں',
    not_found: 'آپ کی فہرست میں نہیں',
    new_record: 'نئے ریکارڈ کے طور پر بنے گا',
    characters_left: '{count} حروف باقی',
    name: 'نام',
    category: 'قسم',
    unit: 'یونٹ',
    stock: 'اسٹاک',
    price: 'قیمت',
    selling_price: 'فروخت قیمت',
    description: 'تفصیل',
    amount: 'رقم',
    date: 'تاریخ',
    phone: 'فون',
    email: 'ای میل',
    quantity: 'مقدار',
    unit_price: 'فی یونٹ قیمت',
    customer: 'گاہک',
    payment: 'ادائیگی کا طریقہ',
    sign_in: 'براہ کرم دوبارہ سائن ان کریں۔',
    check_missing: 'جو فیلڈز غائب بتائی گئی ہیں انہیں بھریں۔',
  },
  de: {
    title: 'Schreib es, wir erfassen es',
    subtitle: 'Beschreibe eine Sache, die passiert ist, und prüfe die Vorschau vor dem Speichern.',
    placeholder: 'z. B. 20 Flaschen blaues Parfüm zum Bestand hinzufügen',
    analyze: 'Vorschau erstellen',
    preview_title: 'Vor dem Speichern prüfen',
    confirm: 'Bestätigen und speichern',
    cancel: 'Abbrechen',
    retry: 'Erneut versuchen',
    saved: 'Über den normalen Ablauf gespeichert.',
    unsupported: 'Ich konnte nicht erkennen, um welchen Datensatz es geht. Schreibe es als Produkt, Verkauf, Ausgabe oder Kunde.',
    type_product: 'Produkt',
    type_sale: 'Verkauf',
    type_expense: 'Ausgabe',
    type_customer: 'Kunde',
    matched: 'In deinem Katalog gefunden',
    ambiguous: 'Mehrere Produkte passen - wähle eines',
    not_found: 'Nicht in deinem Katalog',
    new_record: 'Wird als neuer Datensatz angelegt',
    characters_left: '{count} Zeichen übrig',
    name: 'Name',
    category: 'Kategorie',
    unit: 'Einheit',
    stock: 'Bestand',
    price: 'Preis',
    selling_price: 'Verkaufspreis',
    description: 'Beschreibung',
    amount: 'Betrag',
    date: 'Datum',
    phone: 'Telefon',
    email: 'E-Mail',
    quantity: 'Menge',
    unit_price: 'Stückpreis',
    customer: 'Kunde',
    payment: 'Zahlungsart',
    sign_in: 'Bitte melde dich erneut an.',
    check_missing: 'Fülle die als fehlend markierten Felder aus.',
  },
  zh: {
    title: '写下来，我们来记录',
    subtitle: '用一句话描述发生的事，保存前先检查预览。',
    placeholder: '例如：把 20 瓶蓝色香水加入库存',
    analyze: '生成预览',
    preview_title: '保存前请核对',
    confirm: '确认并保存',
    cancel: '取消',
    retry: '重试',
    saved: '已通过常规流程保存。',
    unsupported: '我无法判断这是哪类记录。请按产品、销售、支出或客户来写。',
    type_product: '产品',
    type_sale: '销售',
    type_expense: '支出',
    type_customer: '客户',
    matched: '在你的目录中找到',
    ambiguous: '有多个产品匹配——请选择其一',
    not_found: '不在你的目录中',
    new_record: '将创建为新记录',
    characters_left: '剩余 {count} 个字符',
    name: '名称',
    category: '类别',
    unit: '单位',
    stock: '库存',
    price: '价格',
    selling_price: '销售价',
    description: '说明',
    amount: '金额',
    date: '日期',
    phone: '电话',
    email: '邮箱',
    quantity: '数量',
    unit_price: '单价',
    customer: '客户',
    payment: '支付方式',
    sign_in: '请重新登录。',
    check_missing: '请填写标记为缺失的字段。',
  },
};

// Every locale must carry the exact same key set, or the UI would show a key.
const KEYS = Object.keys(STRINGS.en).sort();

function assertComplete(lang, section) {
  const keys = Object.keys(section).sort();
  if (JSON.stringify(keys) !== JSON.stringify(KEYS)) {
    throw new Error(`nl_entry/${lang} key mismatch: ${JSON.stringify(keys)}`);
  }
  for (const key of KEYS) {
    if (typeof section[key] !== 'string' || section[key].trim() === '') {
      throw new Error(`nl_entry/${lang}.${key} must be a non-empty string`);
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
    data.nl_entry = STRINGS[lang];
    writeJson(file, data, original.endsWith('\n'));
    console.log(`  ✅ locales/${lang}.json updated`);
  }

  // 2. Website translation source (translations.<lang>.nl_entry).
  const rootOriginal = fs.readFileSync(rootFile, 'utf8');
  const root = JSON.parse(rootOriginal);
  let touched = 0;
  for (const lang of LANGS) {
    if (!root.translations || !root.translations[lang]) {
      console.warn(`  ⚠️ translations.${lang} missing in locales.json - skipped`);
      continue;
    }
    root.translations[lang].nl_entry = STRINGS[lang];
    touched += 1;
  }
  writeJson(rootFile, root, rootOriginal.endsWith('\n'));
  console.log(`  ✅ locales.json updated (${touched}/${LANGS.length} languages)`);
  console.log(`🎉 nl_entry strings (${KEYS.length} keys) are in every locale.`);
}

main();
