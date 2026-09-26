const fs = require('fs');
const path = require('path');

const localesDir = path.join(__dirname);

const langFiles = {
  sw: 'sw.json', en: 'en.json', fr: 'fr.json', hi: 'hi.json',
  ur: 'ur.json', es: 'es.json', de: 'de.json', zh: 'zh.json'
};

// tabs_seller keys - ONLY add if they don't already exist in the nested object
const newKeys = {
  profile: { sw: 'Profaili', en: 'Profile', fr: 'Profil', hi: 'प्रोफ़ाइल', ur: 'پروفائل', es: 'Perfil', de: 'Profil', zh: '个人资料' },
  sales: { sw: 'Mauzo', en: 'Sales', fr: 'Ventes', hi: 'बिक्री', ur: 'فروخت', es: 'Ventas', de: 'Verkäufe', zh: '销售' },
  expenses: { sw: 'Matumizi', en: 'Expenses', fr: 'Dépenses', hi: 'खर्च', ur: 'اخراجات', es: 'Gastos', de: 'Ausgaben', zh: '支出' },
  sell: { sw: 'Uza', en: 'Sell', fr: 'Vendre', hi: 'बेचें', ur: 'بیچیں', es: 'Vender', de: 'Verkaufen', zh: '出售' },
};

let totalChanged = 0;

Object.entries(langFiles).forEach(([langCode, filename]) => {
  const filePath = path.join(localesDir, filename);
  if (!fs.existsSync(filePath)) { console.log(`⚠️ ${filename} haipo`); return; }

  try {
    const data = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
    if (!data.tabs_seller) data.tabs_seller = {};
    let changed = 0;

    Object.keys(newKeys).forEach(key => {
      if (data.tabs_seller[key] === undefined) {
        data.tabs_seller[key] = newKeys[key][langCode];
        changed++;
        totalChanged++;
      }
    });

    if (changed > 0) {
      fs.writeFileSync(filePath, JSON.stringify(data, null, 2) + '\n', 'utf-8');
      console.log(`✅ ${filename}: ${changed} keys zimeongezwa`);
    } else {
      console.log(`ℹ️ ${filename}: hakuna mabadiliko`);
    }
  } catch (err) {
    console.error(`❌ Hitilafu kwa ${filename}:`, err.message);
  }
});

console.log(`\n📊 Jumla: ${totalChanged} keys zimesasishwa kwenye lugha zote`);
