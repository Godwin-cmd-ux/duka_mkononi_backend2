<?php

namespace App\Services\Ai;

/**
 * Capability #18 - Inquiry Triage & Auto-Draft Replies (deterministic core).
 *
 * The first stage of inquiry management, computed in trusted PHP:
 *  - CLASSIFY every inquiry into an allow-listed topic family (account,
 *    payments, technical, certification, general) with the matched keywords as
 *    evidence, so the same message always lands in the same bucket;
 *  - URGENCY from documented criteria only (see URGENCY rules below): a
 *    critical/high inquiry is flagged for a human instead of being answered;
 *  - ROUTE the inquiry to a team using the documented category -> team map,
 *    with an escalation mark for critical cases;
 *  - DRAFT a reply that is assembled ONLY from the approved help content
 *    (`HELP`) plus a fixed localized frame. No internal record, no other
 *    customer's data and no unapproved claim can reach a draft, because the
 *    draft is not generated - it is composed. The optional model step may at
 *    most choose which approved topics to use and write one short
 *    acknowledgement line, both gated (topic ids must come from the
 *    allow-list; the line must be claim-, figure-, link- and address-free);
 *  - FLAG cases needing human support (urgency, disputes, no matching help
 *    content, an account question with no matching account);
 *  - decide whether a NARROW automation policy may auto-send: only when the
 *    operator explicitly enables it AND the inquiry is general, low-urgency and
 *    needs no human - otherwise a human must approve and send.
 *
 * All public methods are pure (arrays in, arrays out, locale passed in), so the
 * whole contract is unit-testable without HTTP, a database or Laravel helpers.
 */
class InquiryTriage
{
    public const CAPABILITY = 'inquiry_triage';

    /** Allow-listed topic families. */
    public const CATEGORIES = ['account', 'payments', 'technical', 'certification', 'general'];

    /** Documented urgency levels, most severe first. */
    public const URGENCIES = ['critical', 'high', 'normal', 'low'];

    /** Allow-listed teams an inquiry may be routed to. */
    public const ROUTES = ['support', 'billing', 'certification', 'general'];

    /** At most this many approved help topics are used in one draft. */
    public const MAX_TOPICS = 2;

    /**
     * The documented routing map (category -> team). Certification has its own
     * team because it is a review queue, payments go to billing, account and
     * technical questions are front-line support, everything else is general.
     */
    private const ROUTE_BY_CATEGORY = [
        'account' => 'support',
        'technical' => 'support',
        'payments' => 'billing',
        'certification' => 'certification',
        'general' => 'general',
    ];

    /**
     * Approval policy: reputation/abuse-sensitive terminology forces a human.
     * Hitting any of these is NEVER answered automatically.
     */
    public const DISPUTE_TERMS = [
        'refund', 'refunds', 'chargeback', 'dispute', 'lawyer', 'legal action',
        'court', 'compensation', 'claim against', 'marejesho', 'fidia',
        'malipo yaliyokwenda vibaya', 'nimedhulumiwa', 'remboursement', 'litige',
        'reembolso', 'disputa', 'ruckerstattung', 'streitfall', '退款', 'वापसी',
        'رقم کی واپسی', 'تنازع',
    ];

    /**
     * Documented urgency signals. CRITICAL means money or account security is
     * reported as already lost; HIGH means the customer is blocked or has paid
     * twice; LOW is explicitly non-urgent feedback. Anything else is NORMAL.
     */
    public const CRITICAL_TERMS = [
        'hacked', 'hack', 'unauthorized', 'unauthorised', 'fraud', 'scam',
        'stolen', 'stole', 'phishing', 'imehackiwa', 'wizi', 'imeibiwa',
        'utapeli', 'nimeliwa', 'nimedhulumiwa', 'pesa zimepotea', 'piratage',
        'fraude', 'estafa', 'betrug', 'betrugsfall', '诈骗', '被盗', 'धोखा', 'ठगी',
        'دھوکہ', 'چوری',
    ];

    public const HIGH_TERMS = [
        'urgent', 'urgently', 'asap', 'immediately', 'as soon as possible',
        'haraka', 'upesi', 'urgente', 'dringend', 'cannot log in', "can't log in",
        'cant log in', 'cannot sign in', "can't sign in", 'unable to log in',
        'blocked', 'locked out', 'siwezi kuingia', 'sijui kuingia', 'imefungwa',
        'charged twice', 'double charged', 'mara mbili', 'refund', 'refunds',
        'money back', 'marejesho ya pesa', 'reembolso', 'remboursement',
        'erstattung', '退款', 'वापसी', 'رقم کی واپسی',
    ];

    public const LOW_TERMS = [
        'suggestion', 'suggestions', 'feedback', 'idea', 'pendekezo', 'maoni',
        'no rush', 'not urgent', 'whenever', 'when you have time', 'si haraka',
        'suggestion pour', 'idea para', 'vorschlag', '建议', 'सुझाव', 'تجویز',
    ];

    /**
     * The LOW terms that CONTAIN a higher-severity term as a substring: "not
     * urgent" contains "urgent" and "si haraka" (Kiswahili: it is not urgent)
     * contains "haraka". They are removed from the text BEFORE the HIGH check,
     * otherwise a customer who explicitly says the matter can wait would be
     * escalated to HIGH by their own polite wording. Only the negation phrase is
     * removed - any OTHER blocking wording in the same message ("si haraka,
     * lakini siwezi kuingia") still hits HIGH.
     */
    public const NON_URGENT_PHRASES = [
        'no rush', 'not urgent', 'whenever', 'when you have time', 'si haraka',
    ];

    /**
     * The approved help knowledge base. Every draft answer comes from here, so
     * an operator can review exactly what customers may be told. Content is
     * short and limited to behaviour that exists in the product (password reset
     * codes, per-role login pages, the payment partner flow, certification
     * review, the published app download, business registration).
     *
     * @var array<int, array{id: string, category: string, keywords: array<int, string>}>
     */
    public const TOPICS = [
        [
            'id' => 'help_reset',
            'category' => 'account',
            'keywords' => [
                'forgot password', 'forget password', 'reset password', 'reset my password',
                'password reset', 'change password', 'cannot remember password', 'password',
                'nimesahau neno la siri', 'neno la siri', 'kubadilisha neno la siri',
                'mot de passe', 'contrasena', 'passwort', 'kennwort', '密码', '忘记密码',
                'पासवर्ड', 'पासवर्ड भूल', 'پاس ورڈ',
            ],
        ],
        [
            'id' => 'help_signin',
            'category' => 'account',
            'keywords' => [
                'sign in', 'signin', 'log in', 'login', 'logged out', 'my account',
                'cannot access my account', 'account access', 'wrong page',
                'kuingia', 'akaunti', 'sehemu ya kuingia', 'kwenye akaunti yangu',
                'connexion', 'compte', 'iniciar sesion', 'cuenta', 'anmelden', 'konto',
                '登录', '账户', 'साइन इन', 'खाता', 'لاگ ان', 'اکاؤنٹ',
            ],
        ],
        [
            'id' => 'help_payment',
            'category' => 'payments',
            'keywords' => [
                'payment', 'payments', 'paid', 'i have paid', 'invoice', 'receipt',
                'transaction', 'pesapal', 'm-pesa', 'mpesa', 'tigo pesa', 'airtel money',
                'payment status', 'payment pending', 'malipo', 'nimelipa', 'risiti',
                'muamala', 'paiement', 'paye', 'pago', 'pague', 'zahlung', 'bezahlt',
                '付款', '支付', 'भुगतान', 'पेमेंट', 'ادائیگی',
            ],
        ],
        [
            'id' => 'help_certification',
            'category' => 'certification',
            'keywords' => [
                'certification', 'certificate', 'certified', 'verified badge',
                'verification of business', 'business verification', 'trust badge',
                'uthibitisho', 'cheti', 'kuthibitishwa', 'beji ya uaminifu',
                'certification', 'certificacion', 'zertifizierung', 'zertifikat',
                '认证', 'प्रमाणन', 'प्रमाण पत्र', 'تصدیق', 'سرٹیفکیٹ',
            ],
        ],
        [
            'id' => 'help_technical',
            'category' => 'technical',
            'keywords' => [
                'not working', 'does not work', 'doesnt work', "doesn't work", 'error',
                'bug', 'crash', 'crashes', 'freeze', 'frozen', 'website', 'the app',
                'app not', 'slow', 'cannot upload', 'cannot add product', 'haifanyi kazi',
                'tatizo la kiufundi', 'programu', 'tovuti', 'imekwama', 'hitilafu',
                'ne fonctionne pas', 'erreur', 'no funciona', 'error', 'funktioniert nicht',
                'fehler', '无法', '错误', '不工作', 'काम नहीं', 'त्रुटि', 'خرابی', 'کام نہیں',
            ],
        ],
        [
            'id' => 'help_welcome',
            'category' => 'general',
            'keywords' => [
                'how do i start', 'how to start', 'get started', 'register', 'sign up',
                'signup', 'open a shop', 'create account', 'what is duka mkononi',
                'ninaanzaje', 'kujiunga', 'kujisajili', 'kuanzisha duka', 'duka mkononi ni nini',
                'comment commencer', 's inscrire', 'como empezar', 'registrarse',
                'wie fange ich an', 'registrieren', '如何开始', '注册', 'कैसे शुरू', 'पंजीकरण',
                'کیسے شروع', 'رجسٹر',
            ],
        ],
    ];

    /**
     * Approved help answers per locale. A draft may only contain these (plus the
     * localized frame below), so nothing unapproved can be sent to a customer.
     */
    public const HELP = [
        'sw' => [
            'help_reset' => 'Ili kubadilisha neno la siri, fungua ukurasa wa kuingia, chagua "Nimesahau neno la siri" na uweke barua pepe uliyosajili nayo. Tutatuma msimbo wa mara moja kwenye barua pepe hiyo; uweke msimbo huo ili kuweka neno la siri jipya.',
            'help_signin' => 'Kila aina ya akaunti ina ukurasa wake wa kuingia. Wauzaji, wasimamizi na makasisi hutumia kuingia kwa biashara, na wateja hutumia kuingia kwa mteja. Hakikisha uko kwenye ukurasa unaofaa akaunti yako.',
            'help_payment' => 'Malipo hufanywa na mshirika wetu wa malipo. Malipo yanapokamilika, hali yake inaonekana kwenye ukurasa wako wa malipo. Kama bado inaonyesha "inasubiri", subiri dakika chache na ufrishe ukurasa kabla ya kujaribu tena.',
            'help_certification' => 'Uthibitisho wa biashara huhakikiwa na timu yetu. Tuma jina la biashara yako, eneo lake na taarifa zinazohitajika kwenye profaili yako; mhakiki huziangalia na beji ya uthibitisho inaonekana baada ya kupitishwa.',
            'help_technical' => 'Kama programu au tovuti haifanyi kazi, ifunge na kuifungua tena, kisha hakikisha unatumia toleo la hivi karibuni. Unaweza kupakua toleo la sasa la programu kwa kiungo cha "Pakua Programu" kwenye tovuti yetu.',
            'help_welcome' => 'Duka Mkononi ni jukwaa la biashara ambapo maduka huuza kwa wateja mtandaoni. Ili kuanza, fungua akaunti ya biashara yako, ongeza bidhaa zako na ushirikishe duka lako kwa wateja.',
        ],
        'en' => [
            'help_reset' => 'To change your password, open the login screen, choose "Forgot password" and enter the email address you registered with. We will send a one-time code to that email; enter the code to set a new password.',
            'help_signin' => 'Each account type signs in on its own page. Sellers, managers and cashiers use the business login, and customers use the customer login. Please make sure you are on the page that matches your account.',
            'help_payment' => 'Payments are processed by our payment partner. When a payment completes, its status appears on your payments page. If it still shows as pending, wait a few minutes and refresh the page before trying again.',
            'help_certification' => 'Business certification is reviewed by our team. Submit your business name, its location and the details requested in your profile; a reviewer checks them and the verified badge appears once your business is approved.',
            'help_technical' => 'If the app or the website is not working, close it and open it again, then make sure you are using the latest version. You can download the current app build from the "Download App" link on our website.',
            'help_welcome' => 'Duka Mkononi is a marketplace where shops sell to customers online. To start, create your business account, add your products and share your shop with customers.',
        ],
        'fr' => [
            'help_reset' => 'Pour changer votre mot de passe, ouvrez la page de connexion, choisissez « Mot de passe oublié » et saisissez l\'adresse e-mail avec laquelle vous vous êtes inscrit. Nous vous enverrons un code à usage unique ; saisissez-le pour définir un nouveau mot de passe.',
            'help_signin' => 'Chaque type de compte se connecte sur sa propre page. Les vendeurs, gestionnaires et caissiers utilisent la connexion entreprise, et les clients utilisent la connexion client. Vérifiez que vous êtes bien sur la page correspondant à votre compte.',
            'help_payment' => 'Les paiements sont traités par notre partenaire de paiement. Une fois le paiement effectué, son statut apparaît sur votre page de paiements. S\'il est encore indiqué « en attente », patientez quelques minutes et actualisez la page avant de réessayer.',
            'help_certification' => 'La certification d\'entreprise est examinée par notre équipe. Envoyez le nom de votre entreprise, son emplacement et les informations demandées dans votre profil ; un réviseur les vérifie et le badge vérifié apparaît après approbation.',
            'help_technical' => 'Si l\'application ou le site ne fonctionne pas, fermez-la et rouvrez-la, puis vérifiez que vous utilisez la dernière version. Vous pouvez télécharger la version actuelle depuis le lien « Télécharger l\'application » sur notre site.',
            'help_welcome' => 'Duka Mkononi est une place de marché où les boutiques vendent en ligne à leurs clients. Pour commencer, créez le compte de votre entreprise, ajoutez vos produits et partagez votre boutique avec vos clients.',
        ],
        'hi' => [
            'help_reset' => 'पासवर्ड बदलने के लिए लॉगिन स्क्रीन खोलें, "पासवर्ड भूल गए" चुनें और वह ईमेल पता डालें जिससे आपने पंजीकरण किया था। हम उस ईमेल पर एक बार इस्तेमाल होने वाला कोड भेजेंगे; नया पासवर्ड बनाने के लिए वह कोड डालें।',
            'help_signin' => 'हर खाता प्रकार अपने अलग पेज से साइन इन करता है। विक्रेता, प्रबंधक और कैशियर व्यवसाय लॉगिन का उपयोग करते हैं, और ग्राहक ग्राहक लॉगिन का। कृपया सुनिश्चित करें कि आप अपने खाते के सही पेज पर हैं।',
            'help_payment' => 'भुगतान हमारे भुगतान साझेदार द्वारा संसाधित किए जाते हैं। भुगतान पूरा होने पर उसकी स्थिति आपके भुगतान पेज पर दिखाई देती है। यदि वह अभी भी लंबित दिखे, तो कुछ मिनट प्रतीक्षा करें और दोबारा प्रयास करने से पहले पेज रीफ्रेश करें।',
            'help_certification' => 'व्यवसाय प्रमाणन की समीक्षा हमारी टीम करती है। अपने व्यवसाय का नाम, स्थान और प्रोफ़ाइल में मांगी गई जानकारी भेजें; समीक्षक उन्हें जांचते हैं और स्वीकृति के बाद सत्यापित बैज दिखाई देता है।',
            'help_technical' => 'यदि ऐप या वेबसाइट काम नहीं कर रही है, तो उसे बंद करके दोबारा खोलें और सुनिश्चित करें कि आप नवीनतम संस्करण पर हैं। आप हमारी वेबसाइट पर "ऐप डाउनलोड करें" लिंक से मौजूदा संस्करण डाउनलोड कर सकते हैं।',
            'help_welcome' => 'Duka Mkononi एक बाज़ार है जहाँ दुकानें ऑनलाइन ग्राहकों को बेचती हैं। शुरू करने के लिए अपना व्यवसाय खाता बनाएँ, उत्पाद जोड़ें और अपनी दुकान ग्राहकों के साथ साझा करें।',
        ],
        'es' => [
            'help_reset' => 'Para cambiar su contraseña, abra la pantalla de inicio de sesión, elija "Olvidé mi contraseña" e introduzca el correo con el que se registró. Le enviaremos un código de un solo uso; introdúzcalo para establecer una nueva contraseña.',
            'help_signin' => 'Cada tipo de cuenta inicia sesión en su propia página. Los vendedores, gerentes y cajeros usan el inicio de sesión de empresa, y los clientes usan el de cliente. Verifique que está en la página que corresponde a su cuenta.',
            'help_payment' => 'Los pagos los procesa nuestro socio de pagos. Cuando un pago se completa, su estado aparece en su página de pagos. Si aún figura como pendiente, espere unos minutos y actualice la página antes de intentarlo de nuevo.',
            'help_certification' => 'Nuestro equipo revisa la certificación de empresas. Envíe el nombre de su empresa, su ubicación y los datos solicitados en su perfil; un revisor los comprueba y la insignia verificada aparece tras la aprobación.',
            'help_technical' => 'Si la aplicación o el sitio web no funciona, ciérrelo y vuelva a abrirlo, y compruebe que usa la última versión. Puede descargar la versión actual desde el enlace "Descargar la aplicación" de nuestro sitio.',
            'help_welcome' => 'Duka Mkononi es un mercado donde las tiendas venden en línea a sus clientes. Para empezar, cree la cuenta de su empresa, añada sus productos y comparta su tienda con los clientes.',
        ],
        'ur' => [
            'help_reset' => 'پاس ورڈ تبدیل کرنے کے لیے لاگ اِن اسکرین کھولیں، "پاس ورڈ بھول گئے" منتخب کریں اور وہ ای میل ڈالیں جس سے آپ نے رجسٹر کیا تھا۔ ہم اُس ای میل پر ایک بار استعمال ہونے والا کوڈ بھیجیں گے؛ نیا پاس ورڈ بنانے کے لیے وہ کوڈ درج کریں۔',
            'help_signin' => 'ہر اکاؤنٹ کی قسم اپنے الگ صفحے سے سائن اِن کرتی ہے۔ بیچنے والے، مینیجر اور کیشیئر کاروباری لاگ اِن استعمال کرتے ہیں، اور گاہک گاہک لاگ اِن۔ براہ کرم یقینی بنائیں کہ آپ اپنے اکاؤنٹ کے درست صفحے پر ہیں۔',
            'help_payment' => 'ادائیگیاں ہمارے ادائیگی پارٹنر کے ذریعے عمل میں آتی ہیں۔ ادائیگی مکمل ہونے پر اُس کی حیثیت آپ کے ادائیگی صفحے پر نظر آتی ہے۔ اگر وہ اب بھی زیرِ التوا دکھے تو چند منٹ انتظار کریں اور دوبارہ کوشش سے پہلے صفحہ ریفریش کریں۔',
            'help_certification' => 'کاروباری تصدیق ہماری ٹیم جائزہ لیتی ہے۔ اپنے کاروبار کا نام، مقام اور پروفائل میں مانگی گئی تفصیلات بھیجیں؛ جائزہ لینے والا اُن کی جانچ کرتا ہے اور منظوری کے بعد تصدیق شدہ بیج ظاہر ہوتا ہے۔',
            'help_technical' => 'اگر ایپ یا ویب سائٹ کام نہیں کر رہی تو اُسے بند کر کے دوبارہ کھولیں اور یقینی بنائیں کہ آپ تازہ ترین ورژن استعمال کر رہے ہیں۔ آپ ہماری ویب سائٹ پر "ایپ ڈاؤن لوڈ کریں" لنک سے موجودہ ورژن ڈاؤن لوڈ کر سکتے ہیں۔',
            'help_welcome' => 'Duka Mkononi ایک مارکیٹ پلیس ہے جہاں دکانیں آن لائن گاہکوں کو فروخت کرتی ہیں۔ شروع کرنے کے لیے اپنا کاروباری اکاؤنٹ بنائیں، مصنوعات شامل کریں اور اپنی دکان گاہکوں کے ساتھ شیئر کریں۔',
        ],
        'de' => [
            'help_reset' => 'Um Ihr Passwort zu ändern, öffnen Sie die Anmeldeseite, wählen Sie "Passwort vergessen" und geben Sie die E-Mail-Adresse ein, mit der Sie sich registriert haben. Wir senden einen Einmalcode an diese Adresse; geben Sie ihn ein, um ein neues Passwort zu setzen.',
            'help_signin' => 'Jeder Kontotyp meldet sich auf einer eigenen Seite an. Verkäufer, Manager und Kassierer nutzen die Geschäftsanmeldung, Kunden die Kundenanmeldung. Bitte prüfen Sie, dass Sie auf der zu Ihrem Konto passenden Seite sind.',
            'help_payment' => 'Zahlungen werden von unserem Zahlungspartner abgewickelt. Sobald eine Zahlung abgeschlossen ist, erscheint ihr Status auf Ihrer Zahlungsseite. Steht dort noch "ausstehend", warten Sie einige Minuten und aktualisieren Sie die Seite, bevor Sie es erneut versuchen.',
            'help_certification' => 'Die Zertifizierung von Betrieben prüft unser Team. Senden Sie den Namen Ihres Betriebs, den Standort und die in Ihrem Profil verlangten Angaben; eine Prüferin kontrolliert sie und das verifizierte Abzeichen erscheint nach der Freigabe.',
            'help_technical' => 'Wenn die App oder die Website nicht funktioniert, schließen Sie sie und öffnen Sie sie erneut, und prüfen Sie, ob Sie die neueste Version verwenden. Die aktuelle Version können Sie über den Link "App herunterladen" auf unserer Website laden.',
            'help_welcome' => 'Duka Mkononi ist ein Marktplatz, auf dem Geschäfte online an Kunden verkaufen. Erstellen Sie zunächst Ihr Geschäftskonto, fügen Sie Ihre Produkte hinzu und teilen Sie Ihren Shop mit Kunden.',
        ],
        'zh' => [
            'help_reset' => '如需修改密码，请打开登录页面，选择"忘记密码"，并输入您注册时使用的电子邮箱。我们会向该邮箱发送一次性验证码；输入验证码即可设置新密码。',
            'help_signin' => '每种账户类型在各自的页面登录。卖家、管理者和收银员使用商家登录，客户使用客户登录。请确认您所在的页面与账户类型一致。',
            'help_payment' => '付款由我们的支付合作伙伴处理。付款完成后，其状态会显示在您的付款页面上。如果仍显示为待处理，请等待几分钟并刷新页面后再试。',
            'help_certification' => '商户认证由我们的团队审核。请提交您的商户名称、所在地以及个人资料中要求的信息；审核人员会进行核查，通过后会显示认证标识。',
            'help_technical' => '如果应用或网站无法使用，请关闭后重新打开，并确认您使用的是最新版本。您可以通过我们网站上的"下载应用"链接下载当前版本。',
            'help_welcome' => 'Duka Mkononi 是一个让商家在线向客户销售的市场平台。开始使用前，请创建商户账户、添加商品，并把您的店铺分享给客户。',
        ],
    ];

    /**
     * Every message must exist for all eight locales (unit-tested), and every
     * locale must carry exactly the same placeholders for a key.
     */
    public const MESSAGES = [
        'sw' => [
            'cat_account' => 'Akaunti na kuingia',
            'cat_payments' => 'Malipo',
            'cat_technical' => 'Msaada wa kiufundi',
            'cat_certification' => 'Uthibitisho wa biashara',
            'cat_general' => 'Maswali ya jumla',
            'urg_critical' => 'Dharura kubwa',
            'urg_high' => 'Dharura',
            'urg_normal' => 'Kawaida',
            'urg_low' => 'Si dharura',
            'urg_reason_critical' => 'Maneno ya hatari yameonekana: {term}',
            'urg_reason_high' => 'Maneno ya haraka yameonekana: {term}',
            'urg_reason_low' => 'Swali limeelezwa kama si la haraka: {term}',
            'route_support' => 'Timu ya msaada wa wateja',
            'route_billing' => 'Timu ya malipo',
            'route_certification' => 'Timu ya uthibitisho',
            'route_general' => 'Timu ya jumla',
            'route_escalate' => 'Pandisha kwa kiongozi wa timu (dharura kubwa).',
            'why_urgency' => 'Kwa sababu ya dharura: {level}',
            'why_dispute' => 'Swali lina mgogoro wa malipo au madai ya kisheria; mwanadamu anahitajika.',
            'why_no_help' => 'Hakuna msaada ulioidhinishwa unaolingana na swali hili; mwanadamu anahitajika.',
            'why_account' => 'Hakuna akaunti inayolingana na barua pepe hii; mwanadamu anahitajika kuthibitisha.',
            'why_manual' => 'Mwanadamu anahitajika kuhakiki jibu kabla ya kutuma.',
            'draft_greeting' => 'Habari,',
            'detail_intro' => 'Ili kukusaidia haraka, tafadhali tuambie:',
            'draft_closing' => 'Kama unahitaji msaada zaidi, jibu barua pepe hii na tutakusaidia.',
            'draft_signature' => 'Timu ya Duka Mkononi',
            'draft_subject' => 'Re: {original}',
            'detail_payment_ref' => 'Namba ya muamala au tarehe ya malipo',
            'detail_device' => 'Aina ya simu au kompyuta unayotumia',
            'detail_business_name' => 'Jina la biashara yako kama lilivyosajiliwa',
            'notice_auto_sent' => 'Jibu hili lilitumwa kiotomatiki chini ya sera nyembamba iliyoruhusiwa (maswali ya jumla, si dharura).',
            'notice_approved' => 'Jibu limetumwa baada ya idhini ya mwanadamu.',
            'notice_human' => 'Swali hili linahitaji mwanadamu; AI haitajibu moja kwa moja.',
            'lim_help_only' => 'Jibu limeundwa kutoka msaada ulioidhinishwa pekee - hakuna taarifa ya ndani wala ya mteja mwingine.',
            'lim_no_resolution' => 'Kutuma jibu hakumaanishi kuwa tatizo limetatuliwa; ufuatiliaji bado unahitajika.',
            'lim_narration' => 'AI inaweza tu kuchagua mada zilizoidhinishwa na kuandika sentensi moja ya utambulisho.',
            'lim_no_send_in_triage' => 'Kipimo hiki hakikutuma barua pepe yoyote.',
            'src_help_content' => 'Msaada ulioidhinishwa wa Duka Mkononi.',
            'src_your_message' => 'Ujumbe wako mwenyewe.',
            'err_inquiry_missing' => 'Swali halipatikani.',
            'err_approval_required' => 'Thibitisha kuwa umesoma jibu kabla ya kutuma.',
            'err_reply_empty' => 'Jibu haliwezi kuwa tupu.',
            'err_send_failed' => 'Barua pepe haikutumwa.',
            'err_send_failed_hint' => 'Angalia mipangilio ya barua pepe au wasiliana na mteja kwa njia nyingine.',
        ],
        'en' => [
            'cat_account' => 'Account and sign-in',
            'cat_payments' => 'Payments',
            'cat_technical' => 'Technical support',
            'cat_certification' => 'Business certification',
            'cat_general' => 'General questions',
            'urg_critical' => 'Critical',
            'urg_high' => 'Urgent',
            'urg_normal' => 'Normal',
            'urg_low' => 'Not urgent',
            'urg_reason_critical' => 'Risk wording detected: {term}',
            'urg_reason_high' => 'Urgency wording detected: {term}',
            'urg_reason_low' => 'Marked as not urgent: {term}',
            'route_support' => 'Customer support team',
            'route_billing' => 'Billing team',
            'route_certification' => 'Certification team',
            'route_general' => 'General team',
            'route_escalate' => 'Escalate to the team lead (critical urgency).',
            'why_urgency' => 'Urgency is {level}',
            'why_dispute' => 'The inquiry involves a payment dispute or legal claim; a human is required.',
            'why_no_help' => 'No approved help content matches this inquiry; a human is required.',
            'why_account' => 'No account matches this email address; a human must verify it.',
            'why_manual' => 'A human must review the reply before it is sent.',
            'draft_greeting' => 'Hello,',
            'detail_intro' => 'To help you faster, please tell us:',
            'draft_closing' => 'If you need more help, reply to this email and we will assist you.',
            'draft_signature' => 'The Duka Mkononi Team',
            'draft_subject' => 'Re: {original}',
            'detail_payment_ref' => 'the transaction reference or the payment date',
            'detail_device' => 'the phone or computer you are using',
            'detail_business_name' => 'your registered business name',
            'notice_auto_sent' => 'This reply was sent automatically under the narrow approved policy (general, non-urgent questions).',
            'notice_approved' => 'The reply was sent after a human approval.',
            'notice_human' => 'This inquiry needs a human; the AI will not answer it directly.',
            'lim_help_only' => 'The reply is composed from approved help content only - no internal records and no other customer\'s data.',
            'lim_no_resolution' => 'Sending a reply does not mean the issue is resolved; follow-up is still required.',
            'lim_narration' => 'The AI may only pick approved help topics and write one acknowledgement sentence.',
            'lim_no_send_in_triage' => 'This step sent no email.',
            'src_help_content' => 'Approved Duka Mkononi help content.',
            'src_your_message' => 'Your own message.',
            'err_inquiry_missing' => 'That inquiry was not found.',
            'err_approval_required' => 'Confirm you have read the reply before sending it.',
            'err_reply_empty' => 'The reply cannot be empty.',
            'err_send_failed' => 'The email was not sent.',
            'err_send_failed_hint' => 'Check the mail configuration or contact the customer another way.',
        ],
        'fr' => [
            'cat_account' => 'Compte et connexion',
            'cat_payments' => 'Paiements',
            'cat_technical' => 'Support technique',
            'cat_certification' => 'Certification d\'entreprise',
            'cat_general' => 'Questions générales',
            'urg_critical' => 'Critique',
            'urg_high' => 'Urgent',
            'urg_normal' => 'Normal',
            'urg_low' => 'Non urgent',
            'urg_reason_critical' => 'Termes à risque détectés : {term}',
            'urg_reason_high' => 'Termes d\'urgence détectés : {term}',
            'urg_reason_low' => 'Signalé comme non urgent : {term}',
            'route_support' => 'Équipe du support client',
            'route_billing' => 'Équipe de facturation',
            'route_certification' => 'Équipe de certification',
            'route_general' => 'Équipe générale',
            'route_escalate' => 'Transmettre au responsable d\'équipe (urgence critique).',
            'why_urgency' => 'L\'urgence est {level}',
            'why_dispute' => 'La demande implique un litige de paiement ou une action juridique ; un humain est requis.',
            'why_no_help' => 'Aucun contenu d\'aide approuvé ne correspond à cette demande ; un humain est requis.',
            'why_account' => 'Aucun compte ne correspond à cette adresse e-mail ; un humain doit le vérifier.',
            'why_manual' => 'Un humain doit relire la réponse avant son envoi.',
            'draft_greeting' => 'Bonjour,',
            'detail_intro' => 'Pour vous aider plus vite, merci de nous indiquer :',
            'draft_closing' => 'Si vous avez besoin d\'aide supplémentaire, répondez à cet e-mail et nous vous aiderons.',
            'draft_signature' => 'L\'équipe Duka Mkononi',
            'draft_subject' => 'Re : {original}',
            'detail_payment_ref' => 'la référence de la transaction ou la date du paiement',
            'detail_device' => 'le téléphone ou l\'ordinateur que vous utilisez',
            'detail_business_name' => 'le nom enregistré de votre entreprise',
            'notice_auto_sent' => 'Cette réponse a été envoyée automatiquement selon la politique étroite approuvée (questions générales et non urgentes).',
            'notice_approved' => 'La réponse a été envoyée après approbation par un humain.',
            'notice_human' => 'Cette demande nécessite un humain ; l\'IA n\'y répondra pas directement.',
            'lim_help_only' => 'La réponse est composée uniquement de contenu d\'aide approuvé - aucun dossier interne ni donnée d\'un autre client.',
            'lim_no_resolution' => 'L\'envoi d\'une réponse ne signifie pas que le problème est résolu ; un suivi reste nécessaire.',
            'lim_narration' => 'L\'IA peut seulement choisir des sujets d\'aide approuvés et rédiger une phrase d\'accusé de réception.',
            'lim_no_send_in_triage' => 'Cette étape n\'a envoyé aucun e-mail.',
            'src_help_content' => 'Contenu d\'aide approuvé de Duka Mkononi.',
            'src_your_message' => 'Votre propre message.',
            'err_inquiry_missing' => 'Cette demande est introuvable.',
            'err_approval_required' => 'Confirmez avoir lu la réponse avant de l\'envoyer.',
            'err_reply_empty' => 'La réponse ne peut pas être vide.',
            'err_send_failed' => 'L\'e-mail n\'a pas été envoyé.',
            'err_send_failed_hint' => 'Vérifiez la configuration e-mail ou contactez le client autrement.',
        ],
        'hi' => [
            'cat_account' => 'खाता और साइन-इन',
            'cat_payments' => 'भुगतान',
            'cat_technical' => 'तकनीकी सहायता',
            'cat_certification' => 'व्यवसाय प्रमाणन',
            'cat_general' => 'सामान्य प्रश्न',
            'urg_critical' => 'अति गंभीर',
            'urg_high' => 'अत्यावश्यक',
            'urg_normal' => 'सामान्य',
            'urg_low' => 'गैर-अत्यावश्यक',
            'urg_reason_critical' => 'जोखिम के शब्द मिले: {term}',
            'urg_reason_high' => 'अत्यावश्यकता के शब्द मिले: {term}',
            'urg_reason_low' => 'गैर-अत्यावश्यक बताया गया: {term}',
            'route_support' => 'ग्राहक सहायता टीम',
            'route_billing' => 'भुगतान टीम',
            'route_certification' => 'प्रमाणन टीम',
            'route_general' => 'सामान्य टीम',
            'route_escalate' => 'टीम लीड तक बढ़ाएँ (अति गंभीर)।',
            'why_urgency' => 'अत्यावश्यकता {level} है',
            'why_dispute' => 'इसमें भुगतान विवाद या कानूनी दावा शामिल है; मानव आवश्यक है।',
            'why_no_help' => 'इस प्रश्न से मेल खाता कोई स्वीकृत सहायता सामग्री नहीं है; मानव आवश्यक है।',
            'why_account' => 'इस ईमेल से कोई खाता मेल नहीं खाता; मानव को सत्यापन करना होगा।',
            'why_manual' => 'भेजने से पहले मानव को जवाब की समीक्षा करनी होगी।',
            'draft_greeting' => 'नमस्ते,',
            'detail_intro' => 'आपकी तेज़ सहायता के लिए कृपया बताएँ:',
            'draft_closing' => 'यदि और सहायता चाहिए तो इस ईमेल का जवाब दें, हम मदद करेंगे।',
            'draft_signature' => 'Duka Mkononi टीम',
            'draft_subject' => 'Re: {original}',
            'detail_payment_ref' => 'लेन-देन संदर्भ या भुगतान की तारीख',
            'detail_device' => 'आप जिस फ़ोन या कंप्यूटर का उपयोग कर रहे हैं',
            'detail_business_name' => 'आपके पंजीकृत व्यवसाय का नाम',
            'notice_auto_sent' => 'यह जवाब स्वीकृत संकीर्ण नीति के अंतर्गत स्वतः भेजा गया (सामान्य, गैर-अत्यावश्यक प्रश्न)।',
            'notice_approved' => 'जवाब मानव स्वीकृति के बाद भेजा गया।',
            'notice_human' => 'इस प्रश्न के लिए मानव आवश्यक है; AI सीधे जवाब नहीं देगा।',
            'lim_help_only' => 'जवाब केवल स्वीकृत सहायता सामग्री से बनाया गया है - कोई आंतरिक रिकॉर्ड या अन्य ग्राहक का डेटा नहीं।',
            'lim_no_resolution' => 'जवाब भेजने का अर्थ समस्या हल होना नहीं है; अनुवर्ती कार्रवाई अभी आवश्यक है।',
            'lim_narration' => 'AI केवल स्वीकृत सहायता विषय चुन सकता है और एक पावती वाक्य लिख सकता है।',
            'lim_no_send_in_triage' => 'इस चरण में कोई ईमेल नहीं भेजा गया।',
            'src_help_content' => 'Duka Mkononi की स्वीकृत सहायता सामग्री।',
            'src_your_message' => 'आपका अपना संदेश।',
            'err_inquiry_missing' => 'वह प्रश्न नहीं मिला।',
            'err_approval_required' => 'भेजने से पहले पुष्टि करें कि आपने जवाब पढ़ लिया है।',
            'err_reply_empty' => 'जवाब ख़ाली नहीं हो सकता।',
            'err_send_failed' => 'ईमेल नहीं भेजा गया।',
            'err_send_failed_hint' => 'मेल सेटिंग जाँचें या ग्राहक से किसी अन्य तरीके से संपर्क करें।',
        ],
        'es' => [
            'cat_account' => 'Cuenta e inicio de sesión',
            'cat_payments' => 'Pagos',
            'cat_technical' => 'Soporte técnico',
            'cat_certification' => 'Certificación de empresas',
            'cat_general' => 'Preguntas generales',
            'urg_critical' => 'Crítica',
            'urg_high' => 'Urgente',
            'urg_normal' => 'Normal',
            'urg_low' => 'No urgente',
            'urg_reason_critical' => 'Se detectaron términos de riesgo: {term}',
            'urg_reason_high' => 'Se detectaron términos de urgencia: {term}',
            'urg_reason_low' => 'Marcado como no urgente: {term}',
            'route_support' => 'Equipo de soporte al cliente',
            'route_billing' => 'Equipo de facturación',
            'route_certification' => 'Equipo de certificación',
            'route_general' => 'Equipo general',
            'route_escalate' => 'Escalar al responsable del equipo (urgencia crítica).',
            'why_urgency' => 'La urgencia es {level}',
            'why_dispute' => 'La consulta implica una disputa de pago o un reclamo legal; se requiere una persona.',
            'why_no_help' => 'Ningún contenido de ayuda aprobado coincide con esta consulta; se requiere una persona.',
            'why_account' => 'Ninguna cuenta coincide con este correo; una persona debe verificarlo.',
            'why_manual' => 'Una persona debe revisar la respuesta antes de enviarla.',
            'draft_greeting' => 'Hola,',
            'detail_intro' => 'Para ayudarle más rápido, indíquenos:',
            'draft_closing' => 'Si necesita más ayuda, responda a este correo y le ayudaremos.',
            'draft_signature' => 'El equipo de Duka Mkononi',
            'draft_subject' => 'Re: {original}',
            'detail_payment_ref' => 'la referencia de la transacción o la fecha del pago',
            'detail_device' => 'el teléfono o el ordenador que está usando',
            'detail_business_name' => 'el nombre registrado de su empresa',
            'notice_auto_sent' => 'Esta respuesta se envió automáticamente bajo la política estrecha aprobada (preguntas generales y no urgentes).',
            'notice_approved' => 'La respuesta se envió tras la aprobación de una persona.',
            'notice_human' => 'Esta consulta necesita una persona; la IA no responderá directamente.',
            'lim_help_only' => 'La respuesta se compone solo de contenido de ayuda aprobado - sin registros internos ni datos de otros clientes.',
            'lim_no_resolution' => 'Enviar una respuesta no significa que el problema esté resuelto; aún se requiere seguimiento.',
            'lim_narration' => 'La IA solo puede elegir temas de ayuda aprobados y escribir una frase de acuse.',
            'lim_no_send_in_triage' => 'Este paso no envió ningún correo.',
            'src_help_content' => 'Contenido de ayuda aprobado de Duka Mkononi.',
            'src_your_message' => 'Su propio mensaje.',
            'err_inquiry_missing' => 'No se encontró esa consulta.',
            'err_approval_required' => 'Confirme que ha leído la respuesta antes de enviarla.',
            'err_reply_empty' => 'La respuesta no puede estar vacía.',
            'err_send_failed' => 'El correo no se envió.',
            'err_send_failed_hint' => 'Revise la configuración de correo o contacte al cliente de otra forma.',
        ],
        'ur' => [
            'cat_account' => 'اکاؤنٹ اور سائن اِن',
            'cat_payments' => 'ادائیگیاں',
            'cat_technical' => 'تکنیکی معاونت',
            'cat_certification' => 'کاروباری تصدیق',
            'cat_general' => 'عمومی سوالات',
            'urg_critical' => 'انتہائی نازک',
            'urg_high' => 'فوری',
            'urg_normal' => 'عام',
            'urg_low' => 'غیر فوری',
            'urg_reason_critical' => 'خطرے کے الفاظ ملے: {term}',
            'urg_reason_high' => 'فوری الفاظ ملے: {term}',
            'urg_reason_low' => 'غیر فوری بتایا گیا: {term}',
            'route_support' => 'کسٹمر سپورٹ ٹیم',
            'route_billing' => 'بلنگ ٹیم',
            'route_certification' => 'تصدیق ٹیم',
            'route_general' => 'عمومی ٹیم',
            'route_escalate' => 'ٹیم لیڈ تک بڑھائیں (انتہائی نازک)۔',
            'why_urgency' => 'فوریت {level} ہے',
            'why_dispute' => 'اس میں ادائیگی کا تنازع یا قانونی دعویٰ شامل ہے؛ انسان درکار ہے۔',
            'why_no_help' => 'اس سوال سے مماثل کوئی منظور شدہ معاون مواد نہیں؛ انسان درکار ہے۔',
            'why_account' => 'اس ای میل سے کوئی اکاؤنٹ مماثل نہیں؛ انسان کو تصدیق کرنی ہوگی۔',
            'why_manual' => 'بھیجنے سے پہلے انسان کو جواب کا جائزہ لینا ہوگا۔',
            'draft_greeting' => 'سلام،',
            'detail_intro' => 'آپ کی فوری مدد کے لیے براہ کرم بتائیں:',
            'draft_closing' => 'اگر مزید مدد چاہیے تو اسی ای میل کا جواب دیں، ہم معاونت کریں گے۔',
            'draft_signature' => 'Duka Mkononi ٹیم',
            'draft_subject' => 'Re: {original}',
            'detail_payment_ref' => 'لین دین کا حوالہ یا ادائیگی کی تاریخ',
            'detail_device' => 'وہ فون یا کمپیوٹر جو آپ استعمال کر رہے ہیں',
            'detail_business_name' => 'آپ کے رجسٹرڈ کاروبار کا نام',
            'notice_auto_sent' => 'یہ جواب منظور شدہ محدود پالیسی کے تحت خودکار بھیجا گیا (عمومی، غیر فوری سوالات)۔',
            'notice_approved' => 'جواب انسانی منظوری کے بعد بھیجا گیا۔',
            'notice_human' => 'اس سوال کے لیے انسان درکار ہے؛ AI براہِ راست جواب نہیں دے گا۔',
            'lim_help_only' => 'جواب صرف منظور شدہ معاون مواد سے بنایا گیا - کوئی داخلی ریکارڈ یا دوسرے گاہک کا ڈیٹا نہیں۔',
            'lim_no_resolution' => 'جواب بھیجنے کا مطلب مسئلہ حل ہونا نہیں؛ پیروی اب بھی درکار ہے۔',
            'lim_narration' => 'AI صرف منظور شدہ معاون موضوعات چن سکتا ہے اور ایک تعریفی جملہ لکھ سکتا ہے۔',
            'lim_no_send_in_triage' => 'اس مرحلے میں کوئی ای میل نہیں بھیجی گئی۔',
            'src_help_content' => 'Duka Mkononi کا منظور شدہ معاون مواد۔',
            'src_your_message' => 'آپ کا اپنا پیغام۔',
            'err_inquiry_missing' => 'وہ سوال نہیں ملا۔',
            'err_approval_required' => 'بھیجنے سے پہلے تصدیق کریں کہ آپ نے جواب پڑھ لیا ہے۔',
            'err_reply_empty' => 'جواب خالی نہیں ہو سکتا۔',
            'err_send_failed' => 'ای میل نہیں بھیجی گئی۔',
            'err_send_failed_hint' => 'میل ترتیبات چیک کریں یا گاہک سے کسی اور طریقے سے رابطہ کریں۔',
        ],
        'de' => [
            'cat_account' => 'Konto und Anmeldung',
            'cat_payments' => 'Zahlungen',
            'cat_technical' => 'Technischer Support',
            'cat_certification' => 'Betriebszertifizierung',
            'cat_general' => 'Allgemeine Fragen',
            'urg_critical' => 'Kritisch',
            'urg_high' => 'Dringend',
            'urg_normal' => 'Normal',
            'urg_low' => 'Nicht dringend',
            'urg_reason_critical' => 'Risikobegriffe erkannt: {term}',
            'urg_reason_high' => 'Dringlichkeitsbegriffe erkannt: {term}',
            'urg_reason_low' => 'Als nicht dringend gekennzeichnet: {term}',
            'route_support' => 'Kundensupport-Team',
            'route_billing' => 'Abrechnungsteam',
            'route_certification' => 'Zertifizierungsteam',
            'route_general' => 'Allgemeines Team',
            'route_escalate' => 'An die Teamleitung eskalieren (kritische Dringlichkeit).',
            'why_urgency' => 'Die Dringlichkeit ist {level}',
            'why_dispute' => 'Die Anfrage betrifft einen Zahlungsstreit oder eine rechtliche Forderung; ein Mensch ist erforderlich.',
            'why_no_help' => 'Kein freigegebener Hilfeinhalt passt zu dieser Anfrage; ein Mensch ist erforderlich.',
            'why_account' => 'Zu dieser E-Mail-Adresse gibt es kein Konto; ein Mensch muss dies prüfen.',
            'why_manual' => 'Ein Mensch muss die Antwort vor dem Versand prüfen.',
            'draft_greeting' => 'Guten Tag,',
            'detail_intro' => 'Damit wir schneller helfen können, nennen Sie uns bitte:',
            'draft_closing' => 'Wenn Sie weitere Hilfe brauchen, antworten Sie auf diese E-Mail und wir unterstützen Sie.',
            'draft_signature' => 'Ihr Duka Mkononi Team',
            'draft_subject' => 'Re: {original}',
            'detail_payment_ref' => 'die Transaktionsreferenz oder das Zahlungsdatum',
            'detail_device' => 'das Telefon oder den Computer, den Sie verwenden',
            'detail_business_name' => 'Ihren registrierten Betriebsnamen',
            'notice_auto_sent' => 'Diese Antwort wurde automatisch nach der engen freigegebenen Richtlinie gesendet (allgemeine, nicht dringende Fragen).',
            'notice_approved' => 'Die Antwort wurde nach menschlicher Freigabe gesendet.',
            'notice_human' => 'Diese Anfrage braucht einen Menschen; die KI antwortet nicht direkt.',
            'lim_help_only' => 'Die Antwort besteht ausschließlich aus freigegebenem Hilfeinhalt - keine internen Datensätze und keine Daten anderer Kunden.',
            'lim_no_resolution' => 'Der Versand einer Antwort bedeutet nicht, dass das Problem gelöst ist; eine Nachverfolgung bleibt nötig.',
            'lim_narration' => 'Die KI darf nur freigegebene Hilfethemen wählen und einen Bestätigungssatz schreiben.',
            'lim_no_send_in_triage' => 'Dieser Schritt hat keine E-Mail gesendet.',
            'src_help_content' => 'Freigegebener Duka Mkononi Hilfeinhalt.',
            'src_your_message' => 'Ihre eigene Nachricht.',
            'err_inquiry_missing' => 'Diese Anfrage wurde nicht gefunden.',
            'err_approval_required' => 'Bestätigen Sie, dass Sie die Antwort vor dem Versand gelesen haben.',
            'err_reply_empty' => 'Die Antwort darf nicht leer sein.',
            'err_send_failed' => 'Die E-Mail wurde nicht gesendet.',
            'err_send_failed_hint' => 'Prüfen Sie die Mail-Konfiguration oder kontaktieren Sie den Kunden anders.',
        ],
        'zh' => [
            'cat_account' => '账户与登录',
            'cat_payments' => '付款',
            'cat_technical' => '技术支持',
            'cat_certification' => '商户认证',
            'cat_general' => '常规问题',
            'urg_critical' => '紧急（严重）',
            'urg_high' => '紧急',
            'urg_normal' => '普通',
            'urg_low' => '非紧急',
            'urg_reason_critical' => '检测到风险用语：{term}',
            'urg_reason_high' => '检测到紧急用语：{term}',
            'urg_reason_low' => '标记为非紧急：{term}',
            'route_support' => '客户支持团队',
            'route_billing' => '账单团队',
            'route_certification' => '认证团队',
            'route_general' => '综合团队',
            'route_escalate' => '上报团队负责人（严重紧急）。',
            'why_urgency' => '紧急程度为 {level}',
            'why_dispute' => '该咨询涉及付款争议或法律主张；需要人工处理。',
            'why_no_help' => '没有与该咨询匹配的已批准帮助内容；需要人工处理。',
            'why_account' => '没有与该邮箱匹配的账户；需要人工核实。',
            'why_manual' => '发送前需要人工审核该回复。',
            'draft_greeting' => '您好，',
            'detail_intro' => '为更快帮到您，请提供：',
            'draft_closing' => '如需更多帮助，请回复此邮件，我们会协助您。',
            'draft_signature' => 'Duka Mkononi 团队',
            'draft_subject' => 'Re: {original}',
            'detail_payment_ref' => '交易单号或付款日期',
            'detail_device' => '您使用的手机或电脑',
            'detail_business_name' => '您注册的商户名称',
            'notice_auto_sent' => '该回复依据已批准的窄范围策略自动发送（常规、非紧急问题）。',
            'notice_approved' => '该回复已由人工批准后发送。',
            'notice_human' => '该咨询需要人工处理；AI 不会直接回复。',
            'lim_help_only' => '回复仅由已批准的帮助内容组成 - 不含内部记录或其他客户的数据。',
            'lim_no_resolution' => '发送回复不代表问题已解决；仍需跟进。',
            'lim_narration' => 'AI 只能选择已批准的帮助主题并撰写一句确认语。',
            'lim_no_send_in_triage' => '此步骤未发送任何邮件。',
            'src_help_content' => '已批准的 Duka Mkononi 帮助内容。',
            'src_your_message' => '您自己的消息。',
            'err_inquiry_missing' => '未找到该咨询。',
            'err_approval_required' => '发送前请确认您已阅读该回复。',
            'err_reply_empty' => '回复不能为空。',
            'err_send_failed' => '邮件未发送。',
            'err_send_failed_hint' => '请检查邮件配置，或通过其他方式联系客户。',
        ],
    ];

    /** Localized message. Falls back to English and then to the key itself. */
    public static function msg(string $key, string $locale, array $params = []): string
    {
        $table = self::MESSAGES[$locale] ?? self::MESSAGES['en'];
        $text = $table[$key] ?? self::MESSAGES['en'][$key] ?? $key;

        foreach ($params as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /** True when every locale carries every message key (unit-tested). */
    public static function messagesComplete(): bool
    {
        $keys = array_keys(self::MESSAGES['en']);
        sort($keys);
        foreach (self::MESSAGES as $table) {
            $other = array_keys($table);
            sort($other);
            if ($keys !== $other) {
                return false;
            }
        }

        return true;
    }

    /** Placeholders used by a message in a locale, sorted. */
    public static function placeholders(string $key, string $locale): array
    {
        $text = self::MESSAGES[$locale][$key] ?? '';
        preg_match_all('/\{([a-z_]+)\}/', $text, $matches);
        $found = array_values(array_unique($matches[1] ?? []));
        sort($found);

        return $found;
    }

    /** Every approved help topic id (the allow-list for model topic picks). */
    public static function topicIds(): array
    {
        return array_map(static fn (array $topic): string => (string) $topic['id'], self::TOPICS);
    }

    /** True when every locale carries an approved answer for every topic. */
    public static function helpComplete(): bool
    {
        $ids = self::topicIds();
        sort($ids);

        foreach (self::MESSAGES as $locale => $unused) {
            if (! isset(self::HELP[$locale])) {
                return false;
            }
            $have = array_keys(self::HELP[$locale]);
            sort($have);
            if ($have !== $ids) {
                return false;
            }
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Text handling                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Normalise free text for keyword matching: lowercase, latin accents and
     * punctuation folded, whitespace collapsed. Deliberately NOT a stemmer -
     * predictable substring matching is what makes the triage auditable.
     */
    public static function normalize(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text), 'UTF-8');

        $text = str_replace(
            ['á', 'à', 'â', 'ä', 'ã', 'å', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'ö', 'õ', 'ú', 'ù', 'û', 'ü', 'ñ', 'ç', 'ß'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'n', 'c', 'ss'],
            $text
        );

        // Keep letters, digits and the word characters of any script; anything
        // else (punctuation, quotes, emoji) becomes a space.
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /** How many of the topic's keywords appear in the normalised haystack. */
    /** Remove the given phrases (and their surrounding spacing) from a haystack. */
    private static function removePhrases(string $haystack, array $phrases): string
    {
        foreach ($phrases as $phrase) {
            $needle = self::normalize($phrase);
            if ($needle === '') {
                continue;
            }
            $haystack = str_replace($needle, ' ', $haystack);
        }

        return trim(preg_replace('/\s+/u', ' ', $haystack) ?? '');
    }

    private static function keywordHits(array $keywords, string $haystack): array
    {
        $hits = [];
        foreach ($keywords as $keyword) {
            $needle = self::normalize($keyword);
            if ($needle === '') {
                continue;
            }
            if (str_contains($haystack, $needle)) {
                $hits[] = $keyword;
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * Match the approved help topics against the inquiry text.
     *
     * @return array<int, array{id: string, category: string, score: int, evidence: array<int, string>}>
     */
    public static function matchTopics(string $subject, string $message, int $limit = self::MAX_TOPICS): array
    {
        $haystack = self::normalize($subject.' '.$message);

        $matches = [];
        foreach (self::TOPICS as $topic) {
            $hits = self::keywordHits((array) $topic['keywords'], $haystack);
            if ($hits === []) {
                continue;
            }
            $matches[] = [
                'id' => (string) $topic['id'],
                'category' => (string) $topic['category'],
                'score' => count($hits),
                'evidence' => $hits,
            ];
        }

        usort($matches, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'] ?: strcmp($a['id'], $b['id']);
        });

        return array_slice($matches, 0, max(1, $limit));
    }

    /**
     * The topic family. The highest scoring topic decides; a specific family
     * beats `general`, and an inquiry nothing matches is `general`.
     */
    public static function classify(string $subject, string $message): array
    {
        $matches = self::matchTopics($subject, $message, count(self::TOPICS));

        $category = 'general';
        foreach ($matches as $match) {
            if ($match['category'] !== 'general') {
                $category = (string) $match['category'];
                break;
            }
        }

        $scores = array_fill_keys(self::CATEGORIES, 0);
        $evidence = array_fill_keys(self::CATEGORIES, []);
        foreach ($matches as $match) {
            $scores[$match['category']] += $match['score'];
            $evidence[$match['category']] = array_merge($evidence[$match['category']], $match['evidence']);
        }
        foreach ($evidence as $key => $terms) {
            $evidence[$key] = array_values(array_unique($terms));
        }

        return [
            'category' => $category,
            'scores' => $scores,
            'evidence' => $evidence,
            'matched_topics' => $matches,
        ];
    }

    /**
     * Documented urgency criteria (also printed in the response):
     *  - CRITICAL when risk wording is present (fraud, scam, stolen, unauthorised);
     *  - HIGH when the customer is blocked or has paid twice (urgent, cannot log
     *    in, blocked, charged twice, refund wording);
     *  - LOW when the customer explicitly marks it as not urgent (feedback,
     *    suggestion, no rush);
     *  - NORMAL otherwise.
     * The matched terms are returned as evidence so a human can see WHY.
     *
     * An explicit non-urgent phrase is removed before the HIGH check (it is a
     * LOW phrase that would otherwise match a HIGH term inside itself), so the
     * customer's own "not urgent" / "si haraka" never escalates the inquiry -
     * while any other blocking wording in the same message still does.
     */
    public static function urgency(string $subject, string $message, string $locale = 'en'): array
    {
        $haystack = self::normalize($subject.' '.$message);

        $critical = self::keywordHits(self::CRITICAL_TERMS, $haystack);
        if ($critical !== []) {
            return [
                'urgency' => 'critical',
                'level_label' => self::msg('urg_critical', $locale),
                'reasons' => [self::msg('urg_reason_critical', $locale, ['term' => $critical[0]])],
                'evidence' => $critical,
            ];
        }

        $high = self::keywordHits(self::HIGH_TERMS, self::removePhrases($haystack, self::NON_URGENT_PHRASES));
        if ($high !== []) {
            return [
                'urgency' => 'high',
                'level_label' => self::msg('urg_high', $locale),
                'reasons' => [self::msg('urg_reason_high', $locale, ['term' => $high[0]])],
                'evidence' => $high,
            ];
        }

        $low = self::keywordHits(self::LOW_TERMS, $haystack);
        if ($low !== []) {
            return [
                'urgency' => 'low',
                'level_label' => self::msg('urg_low', $locale),
                'reasons' => [self::msg('urg_reason_low', $locale, ['term' => $low[0]])],
                'evidence' => $low,
            ];
        }

        return [
            'urgency' => 'normal',
            'level_label' => self::msg('urg_normal', $locale),
            'reasons' => [],
            'evidence' => [],
        ];
    }

    /**
     * The documented category -> team routing map, plus an escalation mark for
     * critical inquiries.
     */
    public static function route(string $category, string $urgency, string $locale = 'en'): array
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            $category = 'general';
        }

        $team = self::ROUTE_BY_CATEGORY[$category] ?? 'general';
        $escalate = $urgency === 'critical';

        $reasons = [];
        if ($escalate) {
            $reasons[] = self::msg('route_escalate', $locale);
        }

        return [
            'team' => $team,
            'team_label' => self::msg('route_'.$team, $locale),
            'escalate' => $escalate,
            'reasons' => $reasons,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* The triage                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * The full deterministic triage for one inquiry.
     *
     * @param  array{
     *     locale: string,
     *     subject: string,
     *     message: string,
     *     account_found?: bool|null,
     *     help?: array<string, array<string, string>>|null,
     *     topics?: array<int, array{id: string, category: string}>|null
     * }  $ctx
     */
    public static function triage(array $ctx): array
    {
        $locale = (string) ($ctx['locale'] ?? 'en');
        $subject = (string) ($ctx['subject'] ?? '');
        $message = (string) ($ctx['message'] ?? '');
        $accountFound = $ctx['account_found'] ?? null;

        $classification = self::classify($subject, $message);
        $urgency = self::urgency($subject, $message, $locale);
        $routing = self::route($classification['category'], $urgency['urgency'], $locale);

        $disputeHits = self::keywordHits(self::DISPUTE_TERMS, self::normalize($subject.' '.$message));

        /* Human-support criteria, in a fixed order so the explanation is stable. */
        $needsHuman = [];
        if (in_array($urgency['urgency'], ['critical', 'high'], true)) {
            $needsHuman[] = self::msg('why_urgency', $locale, ['level' => $urgency['level_label']]);
        }
        if ($disputeHits !== []) {
            $needsHuman[] = self::msg('why_dispute', $locale);
        }
        if ($classification['matched_topics'] === []) {
            $needsHuman[] = self::msg('why_no_help', $locale);
        }
        if ($classification['category'] === 'account' && $accountFound === false) {
            $needsHuman[] = self::msg('why_account', $locale);
        }
        if ($needsHuman !== []) {
            $needsHuman[] = self::msg('why_manual', $locale);
        }

        return [
            'category' => $classification['category'],
            'category_label' => self::msg('cat_'.$classification['category'], $locale),
            'category_scores' => $classification['scores'],
            'category_evidence' => $classification['evidence'],
            'topics' => array_slice($classification['matched_topics'], 0, self::MAX_TOPICS),
            'urgency' => $urgency['urgency'],
            'urgency_label' => $urgency['level_label'],
            'urgency_reasons' => $urgency['reasons'],
            'urgency_evidence' => $urgency['evidence'],
            'route' => $routing['team'],
            'route_label' => $routing['team_label'],
            'escalate' => $routing['escalate'],
            'route_reasons' => $routing['reasons'],
            'needs_human' => $needsHuman !== [],
            'needs_human_reasons' => $needsHuman,
            'dispute_evidence' => $disputeHits,
            'account_found' => $accountFound,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* The draft                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Compose the reply draft. The body is built from the localized frame and
     * the APPROVED help answers for the matched topics only; when nothing
     * matches, the draft says a human will follow up instead of inventing an
     * answer.
     *
     * @param  array<string, mixed>  $triage  Result of self::triage().
     * @param  array{subject?: string, id?: string}  $inquiry
     * @return array{subject: string, body: string, topic_ids: array<int, string>, asked_details: array<int, string>, sources: array<int, string>, uses_approved_help: bool}
     */
    public static function draft(array $triage, array $inquiry = [], string $locale = 'en', ?string $acknowledgement = null, ?array $help = null): array
    {
        $help = $help ?? (self::HELP[$locale] ?? self::HELP['en']);

        $topicIds = [];
        $answers = [];
        foreach ((array) ($triage['topics'] ?? []) as $topic) {
            $id = (string) ($topic['id'] ?? '');
            if ($id === '' || ! isset($help[$id])) {
                continue;
            }
            $topicIds[] = $id;
            $answers[] = (string) $help[$id];
        }

        $category = (string) ($triage['category'] ?? 'general');
        $message = (string) ($inquiry['message'] ?? '');
        $asked = self::requestedDetails($category, $message, $locale);

        $blocks = [self::msg('draft_greeting', $locale)];

        $ack = $acknowledgement !== null ? self::sanitizeAckString($acknowledgement) : null;
        if ($ack !== null) {
            $blocks[] = $ack;
        }

        if ($answers !== []) {
            $blocks[] = implode("\n\n", $answers);
        } else {
            $blocks[] = self::msg('why_no_help', $locale);
        }

        if ($asked !== []) {
            $bullets = array_map(static fn (array $row): string => '- '.$row['text'], $asked);
            $blocks[] = self::msg('detail_intro', $locale)."\n".implode("\n", $bullets);
        }

        $blocks[] = self::msg('draft_closing', $locale);
        $blocks[] = self::msg('draft_signature', $locale);

        $original = trim((string) ($inquiry['subject'] ?? ''));
        if ($original === '') {
            $original = self::msg('cat_'.$category, $locale);
        }

        return [
            'subject' => mb_substr(self::msg('draft_subject', $locale, ['original' => $original]), 0, 160),
            'body' => implode("\n\n", array_filter($blocks, static fn ($b) => trim((string) $b) !== '')),
            'topic_ids' => array_values(array_unique($topicIds)),
            'asked_details' => array_map(static fn (array $row): string => $row['key'], $asked),
            'sources' => [
                self::msg('src_help_content', $locale),
                self::msg('src_your_message', $locale),
            ],
            'uses_approved_help' => $topicIds !== [],
        ];
    }

    /**
     * Which extra details the reply should ask for, per category, minus the ones
     * the message already contains (a reference number, a device, a business
     * name). Asking is deliberate: the approved answer never invents the detail.
     *
     * @return array<int, array{key: string, text: string}>
     */
    public static function requestedDetails(string $category, string $message, string $locale = 'en'): array
    {
        $haystack = self::normalize($message);
        $details = [];

        if ($category === 'payments') {
            $hasReference = preg_match('/\d{4,}/', $message) === 1;
            if (! $hasReference) {
                $details[] = ['key' => 'detail_payment_ref', 'text' => self::msg('detail_payment_ref', $locale)];
            }
        }

        if ($category === 'technical') {
            $deviceWords = ['android', 'ios', 'iphone', 'samsung', 'tecno', 'infinix', 'huawei', 'laptop', 'computer', 'desktop', 'chrome', 'firefox', 'safari', 'simu', 'kompyuta'];
            $hasDevice = self::keywordHits($deviceWords, $haystack) !== [];
            if (! $hasDevice) {
                $details[] = ['key' => 'detail_device', 'text' => self::msg('detail_device', $locale)];
            }
        }

        if ($category === 'certification') {
            $hasName = str_contains($haystack, self::normalize('business name')) || str_contains($haystack, self::normalize('jina la biashara'));
            if (! $hasName) {
                $details[] = ['key' => 'detail_business_name', 'text' => self::msg('detail_business_name', $locale)];
            }
        }

        return $details;
    }

    /* ------------------------------------------------------------------ */
    /* The optional model step (gated)                                     */
    /* ------------------------------------------------------------------ */

    /**
     * A deliberately tiny gate for the advisory acknowledgement line: no
     * figures, links, addresses or claim wording, bounded length, one sentence.
     * Anything else is discarded and the deterministic draft is used as-is.
     */
    public static function sanitizeAckString(?string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');
        if ($text === '' || mb_strlen($text) < 10 || mb_strlen($text) > 200) {
            return null;
        }
        if (preg_match('/\d/u', $text) === 1) {
            return null;
        }
        if (preg_match('/(https?:|www\.|@|\+\d)/iu', $text) === 1) {
            return null;
        }

        $banned = ['refund', 'refunds', 'guarantee', 'guaranteed', 'free of charge', 'discount', 'refunded', 'promise', 'marejesho', 'dhamana', 'bure', 'garantie', 'garantia', '退款', '保证', '免费', 'वापसी', 'गारंटी', 'واپسی', 'ضمانت'];
        $normalized = self::normalize($text);
        foreach ($banned as $word) {
            if (str_contains($normalized, self::normalize($word))) {
                return null;
            }
        }

        return $text;
    }

    /**
     * The model may return a JSON object {"ack": string} plus an optional
     * {"topics": [ids]}. Topic ids must exist in the allow-list and the
     * acknowledgement must pass the gate; anything else returns null and the
     * deterministic draft is used.
     *
     * @param  mixed  $raw
     * @return array{ack: ?string, topics: array<int, string>}|null
     */
    public static function sanitizeNarration($raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $allowed = self::topicIds();
        $topics = [];
        foreach ((array) ($raw['topics'] ?? []) as $id) {
            $id = is_string($id) ? trim($id) : '';
            if ($id !== '' && in_array($id, $allowed, true) && ! in_array($id, $topics, true)) {
                $topics[] = $id;
            }
        }

        return [
            'ack' => self::sanitizeAckString($raw['ack'] ?? null),
            'topics' => array_slice($topics, 0, self::MAX_TOPICS),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Approval policy                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * The narrow automation policy. Auto-sending is allowed ONLY when the
     * operator enables it AND the inquiry is general, low urgency and needs no
     * human review. Everything else always requires an explicit human approval.
     */
    public static function mayAutoSend(array $triage, bool $policyEnabled): bool
    {
        if (! $policyEnabled) {
            return false;
        }
        if ((bool) ($triage['needs_human'] ?? true)) {
            return false;
        }
        if ((string) ($triage['category'] ?? '') !== 'general') {
            return false;
        }

        return (string) ($triage['urgency'] ?? '') === 'low';
    }
}
