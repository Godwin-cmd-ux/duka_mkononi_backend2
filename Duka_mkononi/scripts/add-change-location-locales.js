#!/usr/bin/env node
/**
 * Adds the "Badili Eneo / confirm location" admin_dashboard + onboarding strings
 * to every locale file.
 * Run: node scripts/add-change-location-locales.js
 */
const fs = require('fs');
const path = require('path');

const LANGS = ['en', 'sw', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

// Keys added to BOTH the admin_dashboard and onboarding sections.
const KEYS = {
  en: {
    change_location: 'Change Location',
    location_confirm_title: 'Are you sure?',
    location_confirm_message: 'Are you sure you are at the business location? Detected area: {area}',
    location_confirm_yes: 'Yes',
  },
  sw: {
    change_location: 'Badili Eneo',
    location_confirm_title: 'Una uhakika?',
    location_confirm_message: 'Una uhakika uko katika eneo la biashara? Eneo linalotambuliwa: {area}',
    location_confirm_yes: 'Ndio',
  },
  fr: {
    change_location: "Changer d'emplacement",
    location_confirm_title: 'Êtes-vous sûr ?',
    location_confirm_message: "Êtes-vous sûr d'être à l'emplacement de l'entreprise ? Zone détectée : {area}",
    location_confirm_yes: 'Oui',
  },
  hi: {
    change_location: 'स्थान बदलें',
    location_confirm_title: 'क्या आपको यकीन है?',
    location_confirm_message: 'क्या आप सुनिश्चित हैं कि आप व्यवसाय स्थान पर हैं? पता लगाया गया क्षेत्र: {area}',
    location_confirm_yes: 'हाँ',
  },
  es: {
    change_location: 'Cambiar ubicación',
    location_confirm_title: '¿Estás seguro?',
    location_confirm_message: '¿Estás seguro de que estás en la ubicación del negocio? Área detectada: {area}',
    location_confirm_yes: 'Sí',
  },
  ur: {
    change_location: 'مقام تبدیل کریں',
    location_confirm_title: 'کیا آپ کو یقین ہے؟',
    location_confirm_message: 'کیا آپ کو یقین ہے کہ آپ کاروبار کے مقام پر ہیں؟ شناخت شدہ علاقہ: {area}',
    location_confirm_yes: 'جی ہاں',
  },
  de: {
    change_location: 'Standort ändern',
    location_confirm_title: 'Sind Sie sicher?',
    location_confirm_message: 'Sind Sie sicher, dass Sie sich am Geschäftsstandort befinden? Erkannte Gegend: {area}',
    location_confirm_yes: 'Ja',
  },
  zh: {
    change_location: '更改位置',
    location_confirm_title: '您确定吗？',
    location_confirm_message: '您确定您在商家位置吗？检测到的区域：{area}',
    location_confirm_yes: '是',
  },
};

// Insert keys just before the closing brace of a section object.
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
    text = insertIntoSection(text, 'onboarding', KEYS[lang]);
    try {
      JSON.parse(text);
    } catch (err) {
      console.error(`  ❌ ${lang}.json is INVALID after injection:`, err.message);
      process.exit(1);
    }
    fs.writeFileSync(filePath, text);
    console.log(`  ✅ ${lang}.json updated`);
  }
  console.log('🎉 Change-location strings added to all locales.');
}

main();
