<?php

namespace App\Services\Ai;

/**
 * Capability #5 - composes the localized text of a weekly health digest.
 *
 * Every sentence is built from already-computed figures and a per-locale
 * template table, so a digest is always truthful and always in the seller's
 * language. The model only ever adds a short optional narrative; the score,
 * components, changes and actions are stated here (grounding rule 2).
 */
class HealthNarrator
{
    private const TEMPLATES = [
        'sw' => [
            'band' => [
                'strong' => 'Nzuri sana',
                'steady' => 'Imara',
                'watch' => 'Angalia',
                'attention' => 'Inahitaji hatua',
                'insufficient' => 'Data haitoshi',
            ],
            'interpretation' => [
                'strong' => 'Vipimo vingi vya biashara yako viko katika hali nzuri wiki hii.',
                'steady' => 'Biashara yako inaendelea vizuri; kuna nafasi za kuboresha.',
                'watch' => 'Vipimo vya biashara vinaonyesha ishara za kufuatilia wiki hii.',
                'attention' => 'Vipimo kadhaa vinahitaji hatua yako wiki hii.',
                'insufficient' => 'Hatukuweza kuhesabu alama ya afya kwa sababu data haitoshi.',
            ],
            'component' => [
                'margin' => 'Faida (margin)',
                'stock_cover' => 'Siku za stock',
                'sales_trend' => 'Mwenendo wa mauzo',
                'expense_ratio' => 'Uwiano wa matumizi',
            ],
            'status' => [
                'strong' => 'Nzuri',
                'steady' => 'Wastani',
                'watch' => 'Angalia',
                'attention' => 'Hatari',
                'missing' => 'Hakuna data',
            ],
            'evidence' => [
                'margin' => 'Faida ghafi {currency} {profit} kwa mauzo {currency} {revenue}; margin {margin}%.',
                'stock_cover' => 'Wastani wa siku za stock {cover} (lengo {target}); bidhaa {out} zimeisha na {over} zimezidi.',
                'sales_trend' => 'Mauzo {change}% ikilinganishwa na kipindi kilichopita ({currency} {previous} → {currency} {current}).',
                'expense_ratio' => 'Matumizi {currency} {expenses}, sawa na {ratio}% ya mauzo.',
            ],
            'action' => [
                'margin' => ['title' => 'Boresha faida ya mauzo', 'body' => 'Pitia bei na gharama za bidhaa zenye margin ndogo; margin ya sasa {margin}%.'],
                'restock' => ['title' => 'Agiza bidhaa zinazoisha', 'body' => 'Bidhaa {out} zimeisha stock; agiza haraka kuepuka kupoteza mauzo.'],
                'overstock' => ['title' => 'Punguza stock iliyozidi', 'body' => 'Bidhaa {over} zimezidi siku {target}; fanya promo au punguza manunuzi.'],
                'expenses' => ['title' => 'Punguza matumizi', 'body' => 'Matumizi ni {ratio}% ya mauzo; angalia {category} ({currency} {amount}).'],
                'sales_decline' => ['title' => 'Chunguza kushuka kwa mauzo', 'body' => 'Mauzo yalipungua {change}%; angalia bidhaa zinazouza chini.'],
                'sales_growth' => ['title' => 'Endeleza ukuaji', 'body' => 'Mauzo yameongezeka {change}%; ongeza stock ya bidhaa zinazoongoza.'],
                'generic' => ['title' => 'Fuatilia biashara yako', 'body' => 'Endelea kurekodi mauzo, matumizi na stock ili ushauri uwe sahihi.'],
            ],
            'change' => [
                'revenue' => 'Mauzo',
                'margin' => 'Faida',
                'expenses' => 'Matumizi',
                'stock_out' => 'Bidhaa zilizoisha',
            ],
            'misc' => [
                'headline' => 'Afya ya biashara: {band}',
                'summary' => '{band} — alama {score}/100. {interpretation}',
                'changes_title' => 'Mabadiliko muhimu',
                'actions_title' => 'Vitendo vinavyopendekezwa',
                'components_title' => 'Vipimo',
                'change_line' => '{label}: {previous} → {current} ({change}%)',
                'lim_insufficient' => 'Data haitoshi kuhesabu alama ya afya wiki hii. Rekodi mauzo, matumizi na stock.',
                'lim_missing_component' => '{component}: haikuweza kuhesabiwa (data haitoshi).',
                'lim_no_previous' => 'Hakuna kipindi cha awali cha kulinganisha, hivyo mwenendo wa mauzo haujahesabiwa.',
                'lim_ai' => 'Maelezo ya AI hayapatikani; takwimu zilizohesabiwa zinaonyeshwa.',
            ],
        ],
        'en' => [
            'band' => [
                'strong' => 'Strong',
                'steady' => 'Steady',
                'watch' => 'Watch',
                'attention' => 'Needs attention',
                'insufficient' => 'Not enough evidence',
            ],
            'interpretation' => [
                'strong' => 'Most of your business indicators are in good shape this week.',
                'steady' => 'Your business is holding steady, with room to improve.',
                'watch' => 'Some indicators need watching this week.',
                'attention' => 'Several indicators need your attention this week.',
                'insufficient' => 'We could not compute a health score because the evidence was insufficient.',
            ],
            'component' => [
                'margin' => 'Margin',
                'stock_cover' => 'Stock cover',
                'sales_trend' => 'Sales trend',
                'expense_ratio' => 'Expense ratio',
            ],
            'status' => [
                'strong' => 'Strong',
                'steady' => 'Steady',
                'watch' => 'Watch',
                'attention' => 'Weak',
                'missing' => 'No data',
            ],
            'evidence' => [
                'margin' => 'Gross profit {currency} {profit} on {currency} {revenue} of sales; margin {margin}%.',
                'stock_cover' => 'Average stock cover {cover} days (target {target}); {out} out of stock and {over} overstocked.',
                'sales_trend' => 'Sales {change}% versus the previous period ({currency} {previous} → {currency} {current}).',
                'expense_ratio' => 'Expenses {currency} {expenses}, equal to {ratio}% of sales.',
            ],
            'action' => [
                'margin' => ['title' => 'Improve sales margin', 'body' => 'Review prices and costs of low-margin products; the current margin is {margin}%.'],
                'restock' => ['title' => 'Restock low items', 'body' => '{out} products are out of stock; order quickly to avoid lost sales.'],
                'overstock' => ['title' => 'Reduce overstock', 'body' => '{over} products exceed {target} days of cover; run a promotion or buy less.'],
                'expenses' => ['title' => 'Cut expenses', 'body' => 'Expenses are {ratio}% of sales; review {category} ({currency} {amount}).'],
                'sales_decline' => ['title' => 'Investigate the sales drop', 'body' => 'Sales fell {change}%; check the products and customers that slowed.'],
                'sales_growth' => ['title' => 'Build on the growth', 'body' => 'Sales grew {change}%; restock your top products.'],
                'generic' => ['title' => 'Keep tracking your business', 'body' => 'Keep recording sales, expenses and stock so the advice stays accurate.'],
            ],
            'change' => [
                'revenue' => 'Sales',
                'margin' => 'Profit',
                'expenses' => 'Expenses',
                'stock_out' => 'Out of stock',
            ],
            'misc' => [
                'headline' => 'Business health: {band}',
                'summary' => '{band} — score {score}/100. {interpretation}',
                'changes_title' => 'Meaningful changes',
                'actions_title' => 'Suggested actions',
                'components_title' => 'Components',
                'change_line' => '{label}: {previous} → {current} ({change}%)',
                'lim_insufficient' => 'Not enough data to compute a health score this week. Record sales, expenses and stock.',
                'lim_missing_component' => '{component}: could not be computed (insufficient data).',
                'lim_no_previous' => 'No previous period to compare, so sales trend was not computed.',
                'lim_ai' => 'AI narrative unavailable; the computed figures are shown.',
            ],
        ],
        'fr' => [
            'band' => [
                'strong' => 'Solide',
                'steady' => 'Stable',
                'watch' => 'À surveiller',
                'attention' => 'Nécessite une action',
                'insufficient' => 'Preuves insuffisantes',
            ],
            'interpretation' => [
                'strong' => 'La plupart des indicateurs de votre entreprise sont bons cette semaine.',
                'steady' => 'Votre entreprise reste stable, avec des marges de progression.',
                'watch' => 'Certains indicateurs sont à surveiller cette semaine.',
                'attention' => 'Plusieurs indicateurs nécessitent votre attention cette semaine.',
                'insufficient' => 'Nous n’avons pas pu calculer de score, faute de preuves suffisantes.',
            ],
            'component' => [
                'margin' => 'Marge',
                'stock_cover' => 'Couverture de stock',
                'sales_trend' => 'Tendance des ventes',
                'expense_ratio' => 'Ratio de dépenses',
            ],
            'status' => [
                'strong' => 'Bon',
                'steady' => 'Moyen',
                'watch' => 'À surveiller',
                'attention' => 'Faible',
                'missing' => 'Aucune donnée',
            ],
            'evidence' => [
                'margin' => 'Marge brute {currency} {profit} sur {currency} {revenue} de ventes ; marge {margin}%.',
                'stock_cover' => 'Couverture de stock moyenne {cover} jours (objectif {target}) ; {out} en rupture et {over} en surstock.',
                'sales_trend' => 'Ventes {change}% par rapport à la période précédente ({currency} {previous} → {currency} {current}).',
                'expense_ratio' => 'Dépenses {currency} {expenses}, soit {ratio}% des ventes.',
            ],
            'action' => [
                'margin' => ['title' => 'Améliorer la marge', 'body' => 'Revoyez les prix et coûts des produits à faible marge ; la marge actuelle est de {margin}%.'],
                'restock' => ['title' => 'Réapprovisionner les articles', 'body' => '{out} produits sont en rupture ; commandez rapidement pour éviter des ventes perdues.'],
                'overstock' => ['title' => 'Réduire le surstock', 'body' => '{over} produits dépassent {target} jours de couverture ; faites une promotion ou achetez moins.'],
                'expenses' => ['title' => 'Réduire les dépenses', 'body' => 'Les dépenses représentent {ratio}% des ventes ; examinez {category} ({currency} {amount}).'],
                'sales_decline' => ['title' => 'Analyser la baisse des ventes', 'body' => 'Les ventes ont baissé de {change}% ; vérifiez les produits et clients ralentis.'],
                'sales_growth' => ['title' => 'Capitaliser sur la croissance', 'body' => 'Les ventes ont augmenté de {change}% ; réapprovisionnez vos meilleurs produits.'],
                'generic' => ['title' => 'Continuez à suivre votre activité', 'body' => 'Continuez à enregistrer ventes, dépenses et stock pour des conseils fiables.'],
            ],
            'change' => [
                'revenue' => 'Ventes',
                'margin' => 'Profit',
                'expenses' => 'Dépenses',
                'stock_out' => 'En rupture',
            ],
            'misc' => [
                'headline' => 'Santé de l’entreprise : {band}',
                'summary' => '{band} — score {score}/100. {interpretation}',
                'changes_title' => 'Changements notables',
                'actions_title' => 'Actions suggérées',
                'components_title' => 'Composantes',
                'change_line' => '{label} : {previous} → {current} ({change}%)',
                'lim_insufficient' => 'Données insuffisantes pour calculer un score cette semaine. Enregistrez ventes, dépenses et stock.',
                'lim_missing_component' => '{component} : non calculable (données insuffisantes).',
                'lim_no_previous' => 'Aucune période précédente à comparer ; la tendance des ventes n’a pas été calculée.',
                'lim_ai' => 'Narration IA indisponible ; les chiffres calculés sont affichés.',
            ],
        ],
        'hi' => [
            'band' => [
                'strong' => 'मजबूत',
                'steady' => 'स्थिर',
                'watch' => 'निगरानी',
                'attention' => 'ध्यान चाहिए',
                'insufficient' => 'पर्याप्त प्रमाण नहीं',
            ],
            'interpretation' => [
                'strong' => 'इस सप्ताह आपके अधिकांश व्यावसायिक संकेतक अच्छी स्थिति में हैं।',
                'steady' => 'आपका व्यवसाय स्थिर है, सुधार की गुंजाइश है।',
                'watch' => 'इस सप्ताह कुछ संकेतकों पर नजर रखनी है।',
                'attention' => 'इस सप्ताह कई संकेतकों पर आपका ध्यान चाहिए।',
                'insufficient' => 'पर्याप्त प्रमाण न होने के कारण हम स्वास्थ्य स्कोर की गणना नहीं कर सके।',
            ],
            'component' => [
                'margin' => 'मार्जिन',
                'stock_cover' => 'स्टॉक कवर',
                'sales_trend' => 'बिक्री रुझान',
                'expense_ratio' => 'खर्च अनुपात',
            ],
            'status' => [
                'strong' => 'मजबूत',
                'steady' => 'स्थिर',
                'watch' => 'निगरानी',
                'attention' => 'कमजोर',
                'missing' => 'कोई डेटा नहीं',
            ],
            'evidence' => [
                'margin' => '{currency} {revenue} की बिक्री पर सकल लाभ {currency} {profit}; मार्जिन {margin}%।',
                'stock_cover' => 'औसत स्टॉक कवर {cover} दिन (लक्ष्य {target}); {out} स्टॉक ख़त्म और {over} अधिक।',
                'sales_trend' => 'पिछली अवधि की तुलना में बिक्री {change}% ({currency} {previous} → {currency} {current})।',
                'expense_ratio' => 'खर्च {currency} {expenses}, जो बिक्री का {ratio}% है।',
            ],
            'action' => [
                'margin' => ['title' => 'मार्जिन सुधारें', 'body' => 'कम मार्जिन वाले उत्पादों की कीमत और लागत देखें; वर्तमान मार्जिन {margin}% है।'],
                'restock' => ['title' => 'कम स्टॉक फिर भरें', 'body' => '{out} उत्पाद स्टॉक से बाहर हैं; बिक्री न खोने के लिए जल्दी ऑर्डर करें।'],
                'overstock' => ['title' => 'अधिक स्टॉक घटाएँ', 'body' => '{over} उत्पाद {target} दिन से अधिक हैं; प्रोमो चलाएँ या कम खरीदें।'],
                'expenses' => ['title' => 'खर्च घटाएँ', 'body' => 'खर्च बिक्री का {ratio}% है; {category} ({currency} {amount}) देखें।'],
                'sales_decline' => ['title' => 'बिक्री गिरावट जाँचें', 'body' => 'बिक्री {change}% गिरी; धीमे उत्पादों और ग्राहकों को देखें।'],
                'sales_growth' => ['title' => 'वृद्धि जारी रखें', 'body' => 'बिक्री {change}% बढ़ी; अपने शीर्ष उत्पादों का स्टॉक बढ़ाएँ।'],
                'generic' => ['title' => 'व्यवसाय पर नजर रखें', 'body' => 'सटीक सलाह के लिए बिक्री, खर्च और स्टॉक दर्ज करते रहें।'],
            ],
            'change' => [
                'revenue' => 'बिक्री',
                'margin' => 'लाभ',
                'expenses' => 'खर्च',
                'stock_out' => 'स्टॉक ख़त्म',
            ],
            'misc' => [
                'headline' => 'व्यवसाय स्वास्थ्य: {band}',
                'summary' => '{band} — स्कोर {score}/100। {interpretation}',
                'changes_title' => 'महत्वपूर्ण बदलाव',
                'actions_title' => 'सुझाए गए कदम',
                'components_title' => 'घटक',
                'change_line' => '{label}: {previous} → {current} ({change}%)',
                'lim_insufficient' => 'इस सप्ताह स्कोर की गणना के लिए पर्याप्त डेटा नहीं। बिक्री, खर्च और स्टॉक दर्ज करें।',
                'lim_missing_component' => '{component}: गणना नहीं हो सकी (डेटा अपर्याप्त)।',
                'lim_no_previous' => 'तुलना के लिए पिछली अवधि नहीं; बिक्री रुझान की गणना नहीं हुई।',
                'lim_ai' => 'AI विवरण उपलब्ध नहीं; गणना किए गए आंकड़े दिखाए गए हैं।',
            ],
        ],
        'es' => [
            'band' => [
                'strong' => 'Sólida',
                'steady' => 'Estable',
                'watch' => 'A vigilar',
                'attention' => 'Requiere acción',
                'insufficient' => 'Evidencia insuficiente',
            ],
            'interpretation' => [
                'strong' => 'La mayoría de los indicadores de tu negocio están bien esta semana.',
                'steady' => 'Tu negocio se mantiene estable, con margen de mejora.',
                'watch' => 'Algunos indicadores deben vigilarse esta semana.',
                'attention' => 'Varios indicadores necesitan tu atención esta semana.',
                'insufficient' => 'No pudimos calcular una puntuación por falta de evidencia suficiente.',
            ],
            'component' => [
                'margin' => 'Margen',
                'stock_cover' => 'Cobertura de stock',
                'sales_trend' => 'Tendencia de ventas',
                'expense_ratio' => 'Ratio de gastos',
            ],
            'status' => [
                'strong' => 'Buena',
                'steady' => 'Media',
                'watch' => 'A vigilar',
                'attention' => 'Débil',
                'missing' => 'Sin datos',
            ],
            'evidence' => [
                'margin' => 'Beneficio bruto {currency} {profit} sobre {currency} {revenue} de ventas; margen {margin}%.',
                'stock_cover' => 'Cobertura media {cover} días (objetivo {target}); {out} agotados y {over} con exceso.',
                'sales_trend' => 'Ventas {change}% frente al periodo anterior ({currency} {previous} → {currency} {current}).',
                'expense_ratio' => 'Gastos {currency} {expenses}, equivalente al {ratio}% de las ventas.',
            ],
            'action' => [
                'margin' => ['title' => 'Mejorar el margen', 'body' => 'Revisa precios y costes de productos de bajo margen; el margen actual es {margin}%.'],
                'restock' => ['title' => 'Reponer artículos', 'body' => '{out} productos están agotados; pide rápido para no perder ventas.'],
                'overstock' => ['title' => 'Reducir el exceso de stock', 'body' => '{over} productos superan {target} días; haz una promoción o compra menos.'],
                'expenses' => ['title' => 'Reducir gastos', 'body' => 'Los gastos son el {ratio}% de las ventas; revisa {category} ({currency} {amount}).'],
                'sales_decline' => ['title' => 'Investigar la caída de ventas', 'body' => 'Las ventas cayeron {change}%; revisa productos y clientes más lentos.'],
                'sales_growth' => ['title' => 'Aprovechar el crecimiento', 'body' => 'Las ventas crecieron {change}%; repón tus productos principales.'],
                'generic' => ['title' => 'Sigue midiendo tu negocio', 'body' => 'Sigue registrando ventas, gastos y stock para que el consejo sea fiable.'],
            ],
            'change' => [
                'revenue' => 'Ventas',
                'margin' => 'Beneficio',
                'expenses' => 'Gastos',
                'stock_out' => 'Agotados',
            ],
            'misc' => [
                'headline' => 'Salud del negocio: {band}',
                'summary' => '{band} — puntuación {score}/100. {interpretation}',
                'changes_title' => 'Cambios importantes',
                'actions_title' => 'Acciones sugeridas',
                'components_title' => 'Componentes',
                'change_line' => '{label}: {previous} → {current} ({change}%)',
                'lim_insufficient' => 'Datos insuficientes para calcular una puntuación esta semana. Registra ventas, gastos y stock.',
                'lim_missing_component' => '{component}: no se pudo calcular (datos insuficientes).',
                'lim_no_previous' => 'No hay periodo anterior para comparar; no se calculó la tendencia de ventas.',
                'lim_ai' => 'Narración de IA no disponible; se muestran las cifras calculadas.',
            ],
        ],
        'ur' => [
            'band' => [
                'strong' => 'مضبوط',
                'steady' => 'مستحکم',
                'watch' => 'نگاہ رکھیں',
                'attention' => 'توجہ درکار',
                'insufficient' => 'ناکافی ثبوت',
            ],
            'interpretation' => [
                'strong' => 'اس ہفتے آپ کے زیادہ تر کاروباری اشارے اچھی حالت میں ہیں۔',
                'steady' => 'آپ کا کاروبار مستحکم ہے، بہتری کی گنجائش موجود ہے۔',
                'watch' => 'اس ہفتے کچھ اشاروں پر نگاہ رکھنی ہے۔',
                'attention' => 'اس ہفتے کئی اشاروں پر آپ کی توجہ درکار ہے۔',
                'insufficient' => 'کافی ثبوت نہ ہونے کے باعث ہم صحت کا اسکور نہیں نکال سکے۔',
            ],
            'component' => [
                'margin' => 'مارجن',
                'stock_cover' => 'اسٹاک کوریج',
                'sales_trend' => 'فروخت کا رجحان',
                'expense_ratio' => 'اخراجات کا تناسب',
            ],
            'status' => [
                'strong' => 'اچھا',
                'steady' => 'درمیانہ',
                'watch' => 'نگاہ رکھیں',
                'attention' => 'کمزور',
                'missing' => 'ڈیٹا نہیں',
            ],
            'evidence' => [
                'margin' => '{currency} {revenue} کی فروخت پر مجموعی منافع {currency} {profit}؛ مارجن {margin}%۔',
                'stock_cover' => 'اوسط اسٹاک کوریج {cover} دن (ہدف {target})؛ {out} ختم اور {over} زائد۔',
                'sales_trend' => 'پچھلی مدت کے مقابلے فروخت {change}% ({currency} {previous} → {currency} {current})۔',
                'expense_ratio' => 'اخراجات {currency} {expenses}، جو فروخت کا {ratio}% ہیں۔',
            ],
            'action' => [
                'margin' => ['title' => 'مارجن بہتر کریں', 'body' => 'کم مارجن والی مصنوعات کی قیمت اور لاگت دیکھیں؛ موجودہ مارجن {margin}% ہے۔'],
                'restock' => ['title' => 'کم اسٹاک بھریں', 'body' => '{out} مصنوعات اسٹاک سے ختم ہیں؛ فروخت نہ گنوانے کے لیے جلدی آرڈر کریں۔'],
                'overstock' => ['title' => 'زائد اسٹاک کم کریں', 'body' => '{over} مصنوعات {target} دن سے زائد ہیں؛ پروموشن چلائیں یا کم خریدیں۔'],
                'expenses' => ['title' => 'اخراجات گھٹائیں', 'body' => 'اخراجات فروخت کا {ratio}% ہیں؛ {category} ({currency} {amount}) دیکھیں۔'],
                'sales_decline' => ['title' => 'فروخت میں کمی جانچیں', 'body' => 'فروخت {change}% کم ہوئی؛ سست مصنوعات اور گاہکوں کو دیکھیں۔'],
                'sales_growth' => ['title' => 'ترقی جاری رکھیں', 'body' => 'فروخت {change}% بڑھی؛ اپنی سرفہرست مصنوعات کا اسٹاک بڑھائیں۔'],
                'generic' => ['title' => 'کاروبار پر نظر رکھیں', 'body' => 'درست مشورے کے لیے فروخت، اخراجات اور اسٹاک درج کرتے رہیں۔'],
            ],
            'change' => [
                'revenue' => 'فروخت',
                'margin' => 'منافع',
                'expenses' => 'اخراجات',
                'stock_out' => 'اسٹاک ختم',
            ],
            'misc' => [
                'headline' => 'کاروباری صحت: {band}',
                'summary' => '{band} — اسکور {score}/100۔ {interpretation}',
                'changes_title' => 'اہم تبدیلیاں',
                'actions_title' => 'تجویز کردہ اقدامات',
                'components_title' => 'اجزاء',
                'change_line' => '{label}: {previous} → {current} ({change}%)',
                'lim_insufficient' => 'اس ہفتے اسکور کے لیے ناکافی ڈیٹا۔ فروخت، اخراجات اور اسٹاک درج کریں۔',
                'lim_missing_component' => '{component}: حساب نہیں ہو سکا (ڈیٹا ناکافی)۔',
                'lim_no_previous' => 'موازنے کے لیے پچھلی مدت نہیں؛ فروخت کا رجحان شمار نہیں ہوا۔',
                'lim_ai' => 'AI بیان دستیاب نہیں؛ شمار شدہ اعداد دکھائے گئے ہیں۔',
            ],
        ],
        'de' => [
            'band' => [
                'strong' => 'Stark',
                'steady' => 'Stabil',
                'watch' => 'Beobachten',
                'attention' => 'Handlungsbedarf',
                'insufficient' => 'Unzureichende Belege',
            ],
            'interpretation' => [
                'strong' => 'Die meisten Kennzahlen deines Betriebs sind diese Woche gut.',
                'steady' => 'Dein Betrieb bleibt stabil, mit Luft nach oben.',
                'watch' => 'Einige Kennzahlen sollten diese Woche beobachtet werden.',
                'attention' => 'Mehrere Kennzahlen brauchen diese Woche deine Aufmerksamkeit.',
                'insufficient' => 'Wir konnten keinen Score berechnen, da die Belege nicht ausreichen.',
            ],
            'component' => [
                'margin' => 'Marge',
                'stock_cover' => 'Bestandsreichweite',
                'sales_trend' => 'Umsatztrend',
                'expense_ratio' => 'Ausgabenquote',
            ],
            'status' => [
                'strong' => 'Gut',
                'steady' => 'Mittel',
                'watch' => 'Beobachten',
                'attention' => 'Schwach',
                'missing' => 'Keine Daten',
            ],
            'evidence' => [
                'margin' => 'Bruttogewinn {currency} {profit} bei {currency} {revenue} Umsatz; Marge {margin}%.',
                'stock_cover' => 'Durchschnittliche Reichweite {cover} Tage (Ziel {target}); {out} nicht vorrätig und {over} überbestand.',
                'sales_trend' => 'Umsatz {change}% gegenüber der Vorperiode ({currency} {previous} → {currency} {current}).',
                'expense_ratio' => 'Ausgaben {currency} {expenses}, das sind {ratio}% des Umsatzes.',
            ],
            'action' => [
                'margin' => ['title' => 'Marge verbessern', 'body' => 'Prüfe Preise und Kosten margenschwacher Produkte; die aktuelle Marge ist {margin}%.'],
                'restock' => ['title' => 'Bestand auffüllen', 'body' => '{out} Produkte sind ausverkauft; bestelle schnell, um verlorene Verkäufe zu vermeiden.'],
                'overstock' => ['title' => 'Überbestand abbauen', 'body' => '{over} Produkte überschreiten {target} Tage; mach eine Aktion oder kaufe weniger.'],
                'expenses' => ['title' => 'Ausgaben senken', 'body' => 'Ausgaben sind {ratio}% des Umsatzes; prüfe {category} ({currency} {amount}).'],
                'sales_decline' => ['title' => 'Umsatzrückgang prüfen', 'body' => 'Der Umsatz fiel um {change}%; prüfe langsamer verkaufte Produkte und Kunden.'],
                'sales_growth' => ['title' => 'Wachstum nutzen', 'body' => 'Der Umsatz stieg um {change}%; fülle deine Top-Produkte auf.'],
                'generic' => ['title' => 'Betrieb weiter verfolgen', 'body' => 'Erfasse weiter Umsatz, Ausgaben und Bestand, damit die Tipps stimmen.'],
            ],
            'change' => [
                'revenue' => 'Umsatz',
                'margin' => 'Gewinn',
                'expenses' => 'Ausgaben',
                'stock_out' => 'Ausverkauft',
            ],
            'misc' => [
                'headline' => 'Betriebsgesundheit: {band}',
                'summary' => '{band} — Score {score}/100. {interpretation}',
                'changes_title' => 'Wichtige Veränderungen',
                'actions_title' => 'Empfohlene Maßnahmen',
                'components_title' => 'Komponenten',
                'change_line' => '{label}: {previous} → {current} ({change}%)',
                'lim_insufficient' => 'Zu wenig Daten für einen Score diese Woche. Erfasse Umsatz, Ausgaben und Bestand.',
                'lim_missing_component' => '{component}: nicht berechenbar (Daten unzureichend).',
                'lim_no_previous' => 'Keine Vorperiode zum Vergleich; der Umsatztrend wurde nicht berechnet.',
                'lim_ai' => 'KI-Erzählung nicht verfügbar; die berechneten Zahlen werden gezeigt.',
            ],
        ],
        'zh' => [
            'band' => [
                'strong' => '强劲',
                'steady' => '稳定',
                'watch' => '关注',
                'attention' => '需处理',
                'insufficient' => '证据不足',
            ],
            'interpretation' => [
                'strong' => '本周您的大部分经营指标状况良好。',
                'steady' => '您的经营保持稳定，仍有提升空间。',
                'watch' => '本周有部分指标需要关注。',
                'attention' => '本周有多项指标需要您处理。',
                'insufficient' => '由于证据不足，我们无法计算健康评分。',
            ],
            'component' => [
                'margin' => '利润率',
                'stock_cover' => '库存覆盖天数',
                'sales_trend' => '销售趋势',
                'expense_ratio' => '支出比率',
            ],
            'status' => [
                'strong' => '良好',
                'steady' => '一般',
                'watch' => '关注',
                'attention' => '偏弱',
                'missing' => '无数据',
            ],
            'evidence' => [
                'margin' => '在 {currency} {revenue} 销售额上实现毛利 {currency} {profit}；利润率 {margin}%。',
                'stock_cover' => '平均库存覆盖 {cover} 天（目标 {target} 天）；{out} 项缺货，{over} 项积压。',
                'sales_trend' => '销售额较上一期间变化 {change}%（{currency} {previous} → {currency} {current}）。',
                'expense_ratio' => '支出 {currency} {expenses}，相当于销售额的 {ratio}%。',
            ],
            'action' => [
                'margin' => ['title' => '提高销售利润率', 'body' => '检查低利润率商品的价格与成本；当前利润率为 {margin}%。'],
                'restock' => ['title' => '补货紧俏商品', 'body' => '{out} 项商品已缺货；请尽快下单以免损失销售。'],
                'overstock' => ['title' => '减少积压库存', 'body' => '{over} 项商品超过 {target} 天覆盖；可促销或减少进货。'],
                'expenses' => ['title' => '削减开支', 'body' => '支出占销售额的 {ratio}%；请检查 {category}（{currency} {amount}）。'],
                'sales_decline' => ['title' => '排查销售下滑', 'body' => '销售额下降 {change}%；检查放缓的商品与客户。'],
                'sales_growth' => ['title' => '延续增长势头', 'body' => '销售额增长 {change}%；为热销商品补货。'],
                'generic' => ['title' => '持续跟踪经营', 'body' => '继续记录销售、支出和库存，让建议保持准确。'],
            ],
            'change' => [
                'revenue' => '销售额',
                'margin' => '利润',
                'expenses' => '支出',
                'stock_out' => '缺货',
            ],
            'misc' => [
                'headline' => '经营健康状况：{band}',
                'summary' => '{band} — 评分 {score}/100。{interpretation}',
                'changes_title' => '重要变化',
                'actions_title' => '建议行动',
                'components_title' => '组成部分',
                'change_line' => '{label}：{previous} → {current}（{change}%）',
                'lim_insufficient' => '本周数据不足以计算评分。请记录销售、支出和库存。',
                'lim_missing_component' => '{component}：无法计算（数据不足）。',
                'lim_no_previous' => '无上一期间可对比；未计算销售趋势。',
                'lim_ai' => 'AI 说明不可用；已展示计算出的数据。',
            ],
        ],
    ];

    public static function supportedLocales(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public static function bandLabel(string $band, string $locale): string
    {
        return self::value(['band', $band], $locale) ?? $band;
    }

    public static function interpretation(string $band, string $locale): string
    {
        return self::value(['interpretation', $band], $locale) ?? '';
    }

    public static function componentLabel(string $key, string $locale): string
    {
        return self::value(['component', $key], $locale) ?? $key;
    }

    public static function statusLabel(string $status, string $locale): string
    {
        return self::value(['status', $status], $locale) ?? $status;
    }

    public static function changeLabel(string $key, string $locale): string
    {
        return self::value(['change', $key], $locale) ?? $key;
    }

    public static function title(string $section, string $key, string $locale, array $params = []): string
    {
        $template = self::value(['misc', $key], $locale);
        if ($template === null) {
            return '';
        }

        return self::interpolate($template, $params);
    }

    /**
     * A localized sentence explaining the figures behind one component.
     *
     * @param  array<string, mixed>  $data
     */
    public static function evidence(string $component, array $data, string $locale, string $currency): string
    {
        $template = self::value(['evidence', $component], $locale);
        if ($template === null) {
            return '';
        }

        return self::interpolate($template, [
            'currency' => $currency,
            'revenue' => self::money($data['revenue'] ?? null),
            'profit' => self::money($data['gross_profit'] ?? null),
            'margin' => self::pct($data['margin_pct'] ?? null),
            'cover' => self::num($data['average_days_cover'] ?? null, 0),
            'target' => self::num($data['target_cover_days'] ?? null, 0),
            'out' => (string) ($data['out_of_stock_count'] ?? 0),
            'over' => (string) ($data['overstock_count'] ?? 0),
            'change' => self::num($data['change_pct'] ?? null, 0),
            'previous' => self::money($data['previous_revenue'] ?? null),
            'current' => self::money($data['revenue'] ?? null),
            'expenses' => self::money($data['expenses_total'] ?? null),
            'ratio' => self::pct($data['expense_ratio'] ?? null),
        ]);
    }

    /**
     * A localized action (title + body) for one suggested action.
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, body: string}
     */
    public static function action(string $key, array $data, string $locale, string $currency): array
    {
        $templates = self::localeTable($locale)['action'][$key] ?? self::TEMPLATES['en']['action'][$key] ?? null;
        if ($templates === null) {
            return ['title' => '', 'body' => ''];
        }

        $params = [
            'currency' => $currency,
            'margin' => self::pct($data['margin_pct'] ?? null),
            'out' => (string) ($data['out_of_stock_count'] ?? 0),
            'over' => (string) ($data['overstock_count'] ?? 0),
            'target' => self::num($data['target_cover_days'] ?? null, 0),
            'ratio' => self::pct($data['expense_ratio'] ?? null),
            'category' => (string) ($data['top_expense_category'] ?? ''),
            'amount' => self::money($data['top_expense_amount'] ?? null),
            'change' => self::num($data['change_pct'] ?? null, 0),
        ];

        return [
            'title' => self::interpolate((string) ($templates['title'] ?? ''), $params),
            'body' => self::interpolate((string) ($templates['body'] ?? ''), $params),
        ];
    }

    /**
     * A localized "label: previous → current (x%)" line.
     */
    public static function changeLine(string $key, $previous, $current, float $changePct, string $locale, string $kind = 'number'): string
    {
        $template = self::value(['misc', 'change_line'], $locale) ?? '{label}: {previous} → {current} ({change}%)';

        $format = fn ($value) => $kind === 'money' ? self::money($value) : self::num($value, 0);

        return self::interpolate($template, [
            'label' => self::changeLabel($key, $locale),
            'previous' => $format($previous),
            'current' => $format($current),
            'change' => self::signed($changePct),
        ]);
    }

    private static function value(array $path, string $locale): ?string
    {
        $table = self::localeTable($locale);
        $node = $table;
        foreach ($path as $key) {
            if (! is_array($node) || ! isset($node[$key])) {
                $node = null;
                break;
            }
            $node = $node[$key];
        }

        if (is_string($node)) {
            return $node;
        }

        // Fall back to English, then Swahili.
        foreach (['en', 'sw'] as $fallback) {
            $node = self::TEMPLATES[$fallback];
            foreach ($path as $key) {
                if (! is_array($node) || ! isset($node[$key])) {
                    $node = null;
                    break;
                }
                $node = $node[$key];
            }
            if (is_string($node)) {
                return $node;
            }
        }

        return null;
    }

    private static function localeTable(string $locale): array
    {
        return self::TEMPLATES[$locale] ?? self::TEMPLATES['en'];
    }

    private static function interpolate(string $template, array $params): string
    {
        $replace = [];
        foreach ($params as $key => $value) {
            $replace['{'.$key.'}'] = (string) $value;
        }

        return strtr($template, $replace);
    }

    private static function money($value): string
    {
        if ($value === null || ! is_numeric($value)) {
            return '-';
        }

        return number_format((float) $value, 0);
    }

    private static function pct($value): string
    {
        return self::num($value, 1);
    }

    private static function num($value, int $decimals): string
    {
        if ($value === null || ! is_numeric($value)) {
            return '-';
        }

        return number_format((float) $value, $decimals);
    }

    private static function signed(float $value): string
    {
        return ($value > 0 ? '+' : '').number_format($value, 1);
    }
}
