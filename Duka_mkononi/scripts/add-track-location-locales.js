#!/usr/bin/env node
/**
 * Adds the "Track My Location" admin_dashboard strings to every locale file.
 * Run: node scripts/add-track-location-locales.js
 */
const fs = require('fs');
const path = require('path');

const LANGS = ['en', 'sw', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

const KEYS = {
  en: {
    track_location: 'Track My Location',
    track_location_loading: 'Tracking your location...',
    track_location_permission: 'Location permission is needed to track your business location. Please allow it.',
    track_location_success_title: 'Location Tracked!',
    track_location_success: 'Your business location has been saved. Customers can now use Twende Dukani to reach you.',
    track_location_error: 'Could not track your location. Please try again.',
    location_tracked: 'Location tracked',
  },
  sw: {
    track_location: 'Fuatilia Eneo Langu',
    track_location_loading: 'Inafuatilia eneo lako...',
    track_location_permission: 'Ruhusa ya eneo inahitajika kufuatilia eneo la biashara yako. Tafadhali iwaze.',
    track_location_success_title: 'Eneo Limefuatiliwa!',
    track_location_success: 'Eneo la biashara yako limehifadhiwa. Wateja sasa wanaweza kutumia Twende Dukani kukufikia.',
    track_location_error: 'Haikuwezekana kufuatilia eneo lako. Tafadhali jaribu tena.',
    location_tracked: 'Eneo limefuatiliwa',
  },
  fr: {
    track_location: 'Localiser mon entreprise',
    track_location_loading: 'Localisation en cours...',
    track_location_permission: 'L’autorisation de localisation est requise pour localiser votre entreprise. Veuillez l’autoriser.',
    track_location_success_title: 'Entreprise localisée !',
    track_location_success: 'La localisation de votre entreprise a été enregistrée. Les clients peuvent maintenant vous rejoindre avec Twende Dukani.',
    track_location_error: 'Impossible de localiser votre entreprise. Veuillez réessayer.',
    location_tracked: 'Entreprise localisée',
  },
  hi: {
    track_location: 'मेरा स्थान ट्रैक करें',
    track_location_loading: 'आपका स्थान ट्रैक किया जा रहा है...',
    track_location_permission: 'अपने व्यवसाय का स्थान ट्रैक करने के लिए स्थान अनुमति आवश्यक है। कृपया इसे अनुमति दें।',
    track_location_success_title: 'स्थान ट्रैक हो गया!',
    track_location_success: 'आपका व्यवसाय स्थान सहेज लिया गया है। ग्राहक अब ट्वेंडे दुकानी से आप तक पहुँच सकते हैं।',
    track_location_error: 'आपका स्थान ट्रैक नहीं हो सका। कृपया पुनः प्रयास करें।',
    location_tracked: 'स्थान ट्रैक किया गया',
  },
  es: {
    track_location: 'Rastrear mi ubicación',
    track_location_loading: 'Rastreando tu ubicación...',
    track_location_permission: 'Se necesita permiso de ubicación para rastrear tu negocio. Actívalo.',
    track_location_success_title: '¡Ubicación rastreada!',
    track_location_success: 'La ubicación de tu negocio se ha guardado. Los clientes ya pueden llegar a ti con Twende Dukani.',
    track_location_error: 'No se pudo rastrear tu ubicación. Inténtalo de nuevo.',
    location_tracked: 'Ubicación rastreada',
  },
  ur: {
    track_location: 'میرا مقام ٹریک کریں',
    track_location_loading: 'آپ کا مقام ٹریک کیا جا رہا ہے...',
    track_location_permission: 'اپنے کاروبار کا مقام ٹریک کرنے کے لیے مقام کی اجازت درکار ہے۔ براہ کرم اجازت دیں۔',
    track_location_success_title: 'مقام ٹریک ہو گیا!',
    track_location_success: 'آپ کے کاروبار کا مقام محفوظ ہو گیا ہے۔ گاہک اب ٹوینڈے دوکانی سے آپ تک پہنچ سکتے ہیں۔',
    track_location_error: 'آپ کا مقام ٹریک نہیں ہو سکا۔ براہ کرم دوبارہ کوشش کریں۔',
    location_tracked: 'مقام ٹریک ہو گیا',
  },
  de: {
    track_location: 'Meinen Standort erfassen',
    track_location_loading: 'Standort wird erfasst...',
    track_location_permission: 'Zur Erfassung Ihres Geschäftsstandorts ist eine Standortberechtigung erforderlich. Bitte erlauben.',
    track_location_success_title: 'Standort erfasst!',
    track_location_success: 'Ihr Geschäftsstandort wurde gespeichert. Kunden können Sie nun mit Twende Dukani erreichen.',
    track_location_error: 'Ihr Standort konnte nicht erfasst werden. Bitte erneut versuchen.',
    location_tracked: 'Standort erfasst',
  },
  zh: {
    track_location: '追踪我的位置',
    track_location_loading: '正在追踪您的位置...',
    track_location_permission: '追踪您的企业位置需要位置权限。请允许。',
    track_location_success_title: '位置已追踪！',
    track_location_success: '您的企业位置已保存。客户现在可以通过 Twende Dukani 找到您。',
    track_location_error: '无法追踪您的位置。请重试。',
    location_tracked: '位置已追踪',
  },
};

// Insert keys just before the closing brace of the admin_dashboard section.
function insertIntoSection(text, sectionName, keys) {
  const lines = text.split('\n');
  const openIdx = lines.findIndex((l) => l === `  "${sectionName}": {`);
  if (openIdx === -1) {
    console.warn(`  ⚠️ section "${sectionName}" not found — skipped`);
    return text;
  }

  let endIdx = -1;
  for (let i = openIdx + 1; i < lines.length; i++) {
    if (/^  },?$/.test(lines[i])) {
      endIdx = i;
      break;
    }
  }
  if (endIdx === -1) {
    console.warn(`  ⚠️ could not find closing brace of "${sectionName}" — skipped`);
    return text;
  }

  if (endIdx > 0 && !/,\s*$/.test(lines[endIdx - 1])) {
    lines[endIdx - 1] = lines[endIdx - 1] + ',';
  }

  const indent = '    ';
  const newLines = Object.entries(keys).map(([k, v]) => {
    const escaped = String(v).replace(/"/g, '\\"');
    return `${indent}"${k}": "${escaped}",`;
  });
  newLines[newLines.length - 1] = newLines[newLines.length - 1].replace(/,$/, '');
  lines.splice(endIdx, 0, ...newLines);
  return lines.join('\n');
}

function main() {
  for (const lang of LANGS) {
    const filePath = path.join(__dirname, '..', 'locales', `${lang}.json`);
    let text = fs.readFileSync(filePath, 'utf8');
    text = insertIntoSection(text, 'admin_dashboard', KEYS[lang]);
    try {
      JSON.parse(text);
    } catch (err) {
      console.error(`  ❌ ${lang}.json is INVALID after injection:`, err.message);
      process.exit(1);
    }
    fs.writeFileSync(filePath, text);
    console.log(`  ✅ ${lang}.json updated`);
  }
  console.log('🎉 Track My Location strings added to all locales.');
}

main();
