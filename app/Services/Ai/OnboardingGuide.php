<?php

namespace App\Services\Ai;

/**
 * Capability #19 (second half) - new-user onboarding guide.
 *
 * WHAT THIS IS
 * ------------
 * A grounded guide for signup and setup. It answers two things for the signed-in
 * user, in their own language:
 *
 *   1. WHAT IS LEFT TO DO: a checklist whose every step is derived from the
 *      user's own recorded row (and their business row), so a step is "done"
 *      only because the platform actually holds the value - never because the
 *      user said so;
 *   2. WHAT THE RULES ARE: answers composed from the APPROVED onboarding
 *      content below, which is written from the application's real behaviour
 *      (the signup pages, the emailed verification code with its real 10-minute
 *      lifetime, the seller approval rule, the onboarding photo/location step,
 *      product creation, the public listing rules, password reset).
 *
 * The optional model step may only choose WHICH approved topics to include and
 * write ONE short acknowledgement sentence that is checked for digits, links,
 * addresses, phone numbers and unapproved topic ids. It can never add a rule,
 * a link, a phone number, an address or a claim of its own.
 *
 * GROUNDING / PRIVACY RULES
 * -------------------------
 *  - Steps read `users.*` and `businesses.*` columns that exist in this platform
 *    (see STEPS). Nothing is inferred from a name or guessed by the model.
 *  - A step whose record could not be read is reported as "not read" instead of
 *    "done" or "missing".
 *  - Nothing internal is ever surfaced by this service: no passwords, no
 *    verification codes, no other account's data, no table names in the answer
 *    text. The only account data returned is the caller's own.
 *  - Certification is never promised: the guide states that an administrator
 *    decides it.
 *  - All methods are pure (arrays in, arrays out, locale passed in) so the whole
 *    contract is unit-testable without HTTP, a database or Laravel helpers.
 */
class OnboardingGuide
{
    public const CAPABILITY = 'onboarding_guide';

    /** The roles this guide knows how to walk through setup. */
    public const ROLES = ['admin', 'seller', 'customer'];

    /** At most this many approved topics are used for one answer. */
    public const MAX_TOPICS = 2;

    /** A business description counts as written at this length (matches #19's review). */
    public const MIN_DESCRIPTION_CHARS = CertificationReview::MIN_DESCRIPTION_CHARS;

    /**
     * The documented setup steps.
     *
     * `rule` decides the status from REAL records only:
     *   user_text     -> users.<field> is non-empty
     *   business_text -> businesses.<field> is non-empty
     *   either_text   -> the user row OR the business row holds the value
     *   either_pair   -> latitude AND longitude on the user row OR the business row
     *   approval      -> users.status for this role (seller accounts wait)
     *   products      -> the business has at least one product row
     *   public_flag   -> businesses.is_public
     *   certified     -> businesses.is_certified (informational only)
     *
     * `required` = false marks a step that is good practice but never blocks
     * going live, so it is never returned as the next thing to do.
     *
     * @var array<int, array{id: string, rule: string, roles: array<int, string>, required: bool, field?: string, min_chars?: int}>
     */
    public const STEPS = [
        ['id' => 'profile_name', 'rule' => 'user_text', 'field' => 'full_name', 'roles' => ['admin', 'seller', 'customer'], 'required' => true],
        ['id' => 'phone', 'rule' => 'user_text', 'field' => 'phone', 'roles' => ['admin', 'seller', 'customer'], 'required' => true],
        ['id' => 'language', 'rule' => 'user_text', 'field' => 'language', 'roles' => ['admin', 'seller', 'customer'], 'required' => false],
        ['id' => 'business_name', 'rule' => 'either_text', 'field' => 'business_name', 'roles' => ['admin', 'seller'], 'required' => true],
        ['id' => 'business_type', 'rule' => 'business_text', 'field' => 'business_type', 'roles' => ['admin', 'seller'], 'required' => true],
        ['id' => 'business_location', 'rule' => 'either_text', 'field' => 'business_location', 'roles' => ['admin', 'seller'], 'required' => true],
        ['id' => 'business_description', 'rule' => 'business_text', 'field' => 'business_description', 'min_chars' => self::MIN_DESCRIPTION_CHARS, 'roles' => ['admin', 'seller'], 'required' => false],
        ['id' => 'logo', 'rule' => 'either_text', 'field' => 'business_logo_url', 'roles' => ['admin', 'seller'], 'required' => true],
        ['id' => 'location_pinned', 'rule' => 'either_pair', 'roles' => ['admin', 'seller'], 'required' => true],
        ['id' => 'approval', 'rule' => 'approval', 'roles' => ['admin', 'seller'], 'required' => true],
        ['id' => 'first_product', 'rule' => 'products', 'roles' => ['admin', 'seller'], 'required' => true],
        ['id' => 'public_listing', 'rule' => 'public_flag', 'roles' => ['admin', 'seller'], 'required' => false],
        ['id' => 'certification', 'rule' => 'certified', 'roles' => ['admin', 'seller'], 'required' => false],
    ];

    /**
     * The signup facts of each role, as the application actually implements them
     * (`POST /api/register/initiate` -> emailed code -> `POST /api/register`;
     * sellers are created `pending` and need an approved admin of that business).
     *
     * @var array<string, array<string, mixed>>
     */
    public const SIGNUP = [
        'admin' => ['web' => '/admin-signup', 'app' => 'admin_signup', 'needs_business' => true, 'needs_approval' => false],
        'seller' => ['web' => '/cashier-signup', 'app' => 'cashier_signup', 'needs_business' => true, 'needs_approval' => true],
        'customer' => ['web' => '/client-signup', 'app' => 'client_signup', 'needs_business' => false, 'needs_approval' => false],
    ];

    /**
     * The approved onboarding knowledge base: one entry per topic id, per
     * language. Every sentence describes behaviour that exists in this
     * application (signup pages, the 10-minute emailed code, the 6-character
     * password rule, the seller approval rule, the onboarding photo/location
     * step, product creation with its buying vs selling price, the public
     * listing rules, certification by an administrator, the language switch and
     * password reset). An operator may replace it through
     * `config('ai.onboarding.kb')` without touching this class.
     *
     * @var array<string, array<string, string>>
     */
    public const KB = [
        'sw' => [
            'kb_signup_roles' => 'Akaunti mpya zinaanzishwa kwa ukurasa wako wa kujisajili: msimamizi wa biashara (/admin-signup), muuzaji au mhudumu (/cashier-signup), na mteja (/client-signup). Kwenye programu, chagua "Jisajili" kisha chagua aina ya akaunti.',
            'kb_verify_email' => 'Baada ya kujaza fomu, tunatuma msimbo kwenye barua pepe yako. Msimbo huo unaisha muda wake baada ya dakika 10 na unaweza kujaribiwa mara 5. Ukiisha, unaweza kutuma msimbo mpya baada ya sekunde 30.',
            'kb_signup_fields' => 'Unahitaji jina lako kamili, barua pepe, namba ya simu na nenosiri la herufi 6 au zaidi. Msimamizi na muuzaji pia wanahitaji jina la biashara, mahali pa biashara na aina ya biashara.',
            'kb_seller_approval' => 'Akaunti ya muuzaji inaanza kama "inasubiri". Msimamizi wa biashara hiyo (aliyethibitishwa) lazima aikubali kabla hujaanza kuingia. Msimamizi mwenyewe anaingia mara moja baada ya kujisajili.',
            'kb_first_login' => 'Ukiingia mara ya kwanza, programu inakuomba kupakia picha ya biashara yako na kuweka mahali pako kwenye ramani. Vitu hivi vinaonekana kwa wateja, hivyo chagua picha iliyo wazi.',
            'kb_first_product' => 'Ongeza bidhaa yako ya kwanza kwenye sehemu ya muuzaji: jina, picha, bei ya kununua na bei ya kuuza. Kiasi cha bidhaa na kiwango cha chini cha hisa husaidia tahadhari za hisa.',
            'kb_public_listing' => 'Biashara inaonekana kwenye soko la umma ikiwa imewekwa kama ya umma. Ukurasa wa biashara zilizothibitishwa unaonyesha tu biashara zilizothibitishwa.',
            'kb_certification' => 'Uthibitisho wa biashara unakaguliwa na msimamizi wa jukwaa; hauji kiotomatiki. Ukiwa umethibitishwa, biashara yako inaonekana kwenye orodha ya biashara zilizothibitishwa.',
            'kb_language' => 'Unaweza kubadilisha lugha wakati wowote. Skrini zote, ikiwa ni pamoja na ujumbe wa barua pepe, zinafuata lugha uliyochagua.',
            'kb_password' => 'Umesahau nenosiri? Tumia "Umesahau nenosiri" kwenye ukurasa wa kuingia: tutatuma msimbo, utauthibitishe, kisha utaweka nenosiri jipya.',
            'kb_pending_login' => 'Kama huwezi kuingia bado, angalia hali ya akaunti yako: akaunti za wauzaji zinasubiri idhini ya msimamizi wa biashara. Msimamizi wako anaweza kuthibitisha akaunti yako.',
        ],
        'en' => [
            'kb_signup_roles' => 'New accounts start on the signup page for their role: a business administrator (/admin-signup), a seller or cashier (/cashier-signup) and a customer (/client-signup). In the app, choose "Sign up" and then the account type.',
            'kb_verify_email' => 'After the form we email you a code. The code expires after 10 minutes and can be tried 5 times; you can ask for a new code 30 seconds after the last one.',
            'kb_signup_fields' => 'You need your full name, email, phone number and a password of at least 6 characters. Administrators and sellers also need the business name, the business location and the business type.',
            'kb_seller_approval' => 'A seller account starts as "pending". The approved administrator of that business has to accept it before you can sign in. An administrator can sign in straight after signing up.',
            'kb_first_login' => 'On your first sign-in the app asks you to upload your business photo and to put your location on the map. These are shown to customers, so pick a clear photo.',
            'kb_first_product' => 'Add your first product in the seller area: name, photo, buying price and selling price. Your quantity and minimum stock level feed the stock warnings.',
            'kb_public_listing' => 'A business appears in the public marketplace when it is set to public. The certified businesses page lists only certified businesses.',
            'kb_certification' => 'Business certification is reviewed by a platform administrator; it is not automatic. Once certified, your business appears on the certified businesses page.',
            'kb_language' => 'You can change the language at any time. Every screen, including the emails we send, follows the language you choose.',
            'kb_password' => 'Forgot your password? Use "Forgot password" on the sign-in page: we send a code, you confirm it, then you set a new password.',
            'kb_pending_login' => 'If you cannot sign in yet, check your account status: seller accounts wait for the administrator of the business to approve them. Your administrator can approve your account.',
        ],
        'fr' => [
            'kb_signup_roles' => "Les nouveaux comptes commencent sur la page d'inscription de leur rôle : un administrateur (/admin-signup), un vendeur ou caissier (/cashier-signup) et un client (/client-signup). Dans l'application, choisissez « S'inscrire » puis le type de compte.",
            'kb_verify_email' => "Après le formulaire, nous vous envoyons un code par e-mail. Il expire après 10 minutes et peut être essayé 5 fois ; vous pouvez demander un nouveau code 30 secondes après le précédent.",
            'kb_signup_fields' => "Il faut votre nom complet, votre e-mail, votre numéro de téléphone et un mot de passe d'au moins 6 caractères. Les administrateurs et vendeurs doivent aussi indiquer le nom, l'emplacement et le type de l'entreprise.",
            'kb_seller_approval' => "Un compte vendeur commence en « en attente ». L'administrateur approuvé de cette entreprise doit l'accepter avant que vous puissiez vous connecter. Un administrateur peut se connecter juste après son inscription.",
            'kb_first_login' => "À la première connexion, l'application vous demande de téléverser la photo de votre entreprise et de placer votre emplacement sur la carte. Ils sont visibles par les clients : choisissez une photo nette.",
            'kb_first_product' => "Ajoutez votre premier produit dans l'espace vendeur : nom, photo, prix d'achat et prix de vente. La quantité et le seuil minimum alimentent les alertes de stock.",
            'kb_public_listing' => "Une entreprise apparaît dans la place de marché publique lorsqu'elle est définie comme publique. La page des entreprises certifiées ne liste que les entreprises certifiées.",
            'kb_certification' => "La certification est examinée par un administrateur de la plateforme ; elle n'est pas automatique. Une fois certifiée, votre entreprise apparaît sur la page des entreprises certifiées.",
            'kb_language' => "Vous pouvez changer la langue à tout moment. Tous les écrans, y compris nos e-mails, suivent la langue choisie.",
            'kb_password' => "Mot de passe oublié ? Utilisez « Mot de passe oublié » sur la page de connexion : nous envoyons un code, vous le confirmez, puis vous définissez un nouveau mot de passe.",
            'kb_pending_login' => "Si vous ne pouvez pas encore vous connecter, vérifiez le statut de votre compte : les comptes vendeurs attendent l'approbation de l'administrateur de l'entreprise.",
        ],
        'hi' => [
            'kb_signup_roles' => 'नए खाते अपनी भूमिका के साइनअप पेज से शुरू होते हैं: व्यवसाय एडमिन (/admin-signup), विक्रेता या कैशियर (/cashier-signup) और ग्राहक (/client-signup)। ऐप में "साइन अप" चुनें और फिर खाते का प्रकार चुनें।',
            'kb_verify_email' => 'फ़ॉर्म के बाद हम आपके ईमेल पर एक कोड भेजते हैं। कोड 10 मिनट में समाप्त हो जाता है और 5 बार आज़माया जा सकता है; पिछले कोड के 30 सेकंड बाद नया कोड माँग सकते हैं।',
            'kb_signup_fields' => 'आपको अपना पूरा नाम, ईमेल, फ़ोन नंबर और कम से कम 6 अक्षरों का पासवर्ड चाहिए। एडमिन और विक्रेता को व्यवसाय का नाम, स्थान और प्रकार भी देना होता है।',
            'kb_seller_approval' => 'विक्रेता खाता "प्रतीक्षारत" के रूप में शुरू होता है। उस व्यवसाय के स्वीकृत एडमिन की मंज़ूरी के बाद ही आप साइन इन कर सकते हैं। एडमिन साइनअप के तुरंत बाद साइन इन कर सकता है।',
            'kb_first_login' => 'पहली बार साइन इन करने पर ऐप आपसे व्यवसाय की फ़ोटो और नक्शे पर स्थान रखने के लिए कहता है। ये ग्राहकों को दिखते हैं, इसलिए साफ़ फ़ोटो चुनें।',
            'kb_first_product' => 'विक्रेता क्षेत्र में अपना पहला उत्पाद जोड़ें: नाम, फ़ोटो, खरीद मूल्य और बिक्री मूल्य। मात्रा और न्यूनतम स्टॉक स्तर स्टॉक चेतावनियों में मदद करते हैं।',
            'kb_public_listing' => 'व्यवसाय सार्वजनिक बाज़ार में तब दिखता है जब वह सार्वजनिक सेट हो। प्रमाणित व्यवसाय पेज पर केवल प्रमाणित व्यवसाय होते हैं।',
            'kb_certification' => 'व्यवसाय प्रमाणन की समीक्षा प्लेटफ़ॉर्म एडमिन करता है; यह स्वतः नहीं होता। प्रमाणित होने पर आपका व्यवसाय प्रमाणित व्यवसाय पेज पर दिखता है।',
            'kb_language' => 'आप कभी भी भाषा बदल सकते हैं। सभी स्क्रीन, ईमेल सहित, चुनी हुई भाषा का पालन करती हैं।',
            'kb_password' => 'पासवर्ड भूल गए? साइन-इन पेज पर "पासवर्ड भूल गए" का उपयोग करें: हम कोड भेजते हैं, आप पुष्टि करते हैं, फिर नया पासवर्ड सेट करते हैं।',
            'kb_pending_login' => 'यदि आप अभी साइन इन नहीं कर पा रहे हैं, तो खाते की स्थिति देखें: विक्रेता खाते व्यवसाय एडमिन की मंज़ूरी की प्रतीक्षा करते हैं।',
        ],
        'es' => [
            'kb_signup_roles' => 'Las cuentas nuevas empiezan en la página de registro de su rol: administrador (/admin-signup), vendedor o cajero (/cashier-signup) y cliente (/client-signup). En la app, elija "Registrarse" y luego el tipo de cuenta.',
            'kb_verify_email' => 'Después del formulario le enviamos un código por correo. El código caduca en 10 minutos y admite 5 intentos; puede pedir uno nuevo 30 segundos después del anterior.',
            'kb_signup_fields' => 'Necesita su nombre completo, correo, número de teléfono y una contraseña de al menos 6 caracteres. Los administradores y vendedores también indican el nombre, la ubicación y el tipo del negocio.',
            'kb_seller_approval' => 'Una cuenta de vendedor empieza como "pendiente". El administrador aprobado de ese negocio debe aceptarla antes de que pueda entrar. Un administrador entra justo después de registrarse.',
            'kb_first_login' => 'En el primer inicio de sesión la app le pide subir la foto de su negocio y fijar su ubicación en el mapa. Se muestran a los clientes, así que elija una foto clara.',
            'kb_first_product' => 'Añada su primer producto en el área de vendedor: nombre, foto, precio de compra y precio de venta. La cantidad y el stock mínimo alimentan los avisos de stock.',
            'kb_public_listing' => 'Un negocio aparece en el mercado público cuando está marcado como público. La página de negocios certificados solo lista negocios certificados.',
            'kb_certification' => 'La certificación la revisa un administrador de la plataforma; no es automática. Una vez certificado, su negocio aparece en la página de negocios certificados.',
            'kb_language' => 'Puede cambiar el idioma en cualquier momento. Todas las pantallas, incluidos los correos, siguen el idioma elegido.',
            'kb_password' => '¿Olvidó su contraseña? Use "Olvidé mi contraseña" en la página de acceso: enviamos un código, lo confirma y luego define una nueva contraseña.',
            'kb_pending_login' => 'Si aún no puede entrar, revise el estado de su cuenta: las cuentas de vendedor esperan la aprobación del administrador del negocio.',
        ],
        'ur' => [
            'kb_signup_roles' => 'نئے اکاؤنٹس اپنے کردار کے سائن اپ صفحے سے شروع ہوتے ہیں: کاروباری ایڈمن (/admin-signup)، سیلر یا کیشیئر (/cashier-signup) اور کسٹمر (/client-signup)۔ ایپ میں "سائن اپ" چنیں پھر اکاؤنٹ کی قسم چنیں۔',
            'kb_verify_email' => 'فارم کے بعد ہم آپ کے ای میل پر کوڈ بھیجتے ہیں۔ کوڈ 10 منٹ میں ختم ہو جاتا ہے اور 5 بار آزمایا جا سکتا ہے؛ پچھلے کوڈ کے 30 سیکنڈ بعد نیا کوڈ مانگ سکتے ہیں۔',
            'kb_signup_fields' => 'آپ کو اپنا پورا نام، ای میل، فون نمبر اور کم از کم 6 حروف کا پاس ورڈ درکار ہے۔ ایڈمن اور سیلر کو کاروبار کا نام، جگہ اور قسم بھی دینی ہوتی ہے۔',
            'kb_seller_approval' => 'سیلر اکاؤنٹ "زیر التواء" شروع ہوتا ہے۔ اس کاروبار کے منظور شدہ ایڈمن کی منظوری کے بعد ہی آپ سائن ان کر سکتے ہیں۔ ایڈمن سائن اپ کے فوراً بعد سائن ان کر سکتا ہے۔',
            'kb_first_login' => 'پہلی بار سائن ان پر ایپ آپ سے کاروبار کی تصویر اور نقشے پر جگہ رکھنے کو کہتی ہے۔ یہ گاہکوں کو نظر آتے ہیں، اس لیے واضح تصویر چنیں۔',
            'kb_first_product' => 'سیلر حصے میں اپنی پہلی پروڈکٹ شامل کریں: نام، تصویر، خرید قیمت اور فروخت قیمت۔ مقدار اور کم از کم اسٹاک اسٹاک انتباہات میں مدد دیتے ہیں۔',
            'kb_public_listing' => 'کاروبار عوامی مارکیٹ میں تب نظر آتا ہے جب وہ عوامی مقرر ہو۔ تصدیق شدہ کاروبار کے صفحے پر صرف تصدیق شدہ کاروبار ہوتے ہیں۔',
            'kb_certification' => 'کاروبار کی تصدیق پلیٹ فارم ایڈمن کرتا ہے؛ یہ خودکار نہیں۔ تصدیق کے بعد آپ کا کاروبار تصدیق شدہ کاروبار کے صفحے پر آتا ہے۔',
            'kb_language' => 'آپ کسی بھی وقت زبان بدل سکتے ہیں۔ تمام اسکرینیں، ای میلز سمیت، منتخب زبان کی پیروی کرتی ہیں۔',
            'kb_password' => 'پاس ورڈ بھول گئے؟ سائن ان صفحے پر "پاس ورڈ بھول گئے" استعمال کریں: ہم کوڈ بھیجتے ہیں، آپ تصدیق کرتے ہیں، پھر نیا پاس ورڈ رکھتے ہیں۔',
            'kb_pending_login' => 'اگر آپ ابھی سائن ان نہیں کر سکتے تو اکاؤنٹ کی حیثیت دیکھیں: سیلر اکاؤنٹس کاروبار کے ایڈمن کی منظوری کے منتظر ہوتے ہیں۔',
        ],
        'de' => [
            'kb_signup_roles' => 'Neue Konten beginnen auf der Registrierungsseite ihrer Rolle: Administrator (/admin-signup), Verkäufer oder Kassierer (/cashier-signup) und Kunde (/client-signup). In der App „Registrieren“ wählen und dann den Kontotyp.',
            'kb_verify_email' => 'Nach dem Formular senden wir Ihnen einen Code per E-Mail. Der Code läuft nach 10 Minuten ab und kann 5-mal versucht werden; einen neuen Code können Sie 30 Sekunden nach dem letzten anfordern.',
            'kb_signup_fields' => 'Sie brauchen Ihren vollständigen Namen, E-Mail, Telefonnummer und ein Passwort mit mindestens 6 Zeichen. Administratoren und Verkäufer geben zusätzlich Firmenname, Standort und Firmentyp an.',
            'kb_seller_approval' => 'Ein Verkäuferkonto startet als „ausstehend“. Der freigegebene Administrator dieses Unternehmens muss es annehmen, bevor Sie sich anmelden können. Ein Administrator kann sich direkt nach der Registrierung anmelden.',
            'kb_first_login' => 'Beim ersten Anmelden bittet die App um Ihr Firmenfoto und um Ihren Standort auf der Karte. Beides sehen Kunden - wählen Sie ein klares Foto.',
            'kb_first_product' => 'Fügen Sie im Verkäuferbereich Ihr erstes Produkt hinzu: Name, Foto, Einkaufspreis und Verkaufspreis. Menge und Mindestbestand speisen die Bestandswarnungen.',
            'kb_public_listing' => 'Ein Unternehmen erscheint im öffentlichen Marktplatz, wenn es als öffentlich gesetzt ist. Die Seite der zertifizierten Unternehmen listet nur zertifizierte Unternehmen.',
            'kb_certification' => 'Die Zertifizierung prüft ein Plattform-Administrator; sie ist nicht automatisch. Nach der Zertifizierung erscheint Ihr Unternehmen auf der Seite der zertifizierten Unternehmen.',
            'kb_language' => 'Sie können die Sprache jederzeit ändern. Alle Bildschirme, auch unsere E-Mails, folgen der gewählten Sprache.',
            'kb_password' => 'Passwort vergessen? Nutzen Sie „Passwort vergessen“ auf der Anmeldeseite: Wir senden einen Code, Sie bestätigen ihn und setzen ein neues Passwort.',
            'kb_pending_login' => 'Wenn Sie sich noch nicht anmelden können, prüfen Sie den Kontostatus: Verkäuferkonten warten auf die Freigabe durch den Administrator des Unternehmens.',
        ],
        'zh' => [
            'kb_signup_roles' => '新账户从对应角色的注册页开始：企业管理员（/admin-signup）、卖家或收银员（/cashier-signup）、客户（/client-signup）。在应用中点击“注册”，再选择账户类型。',
            'kb_verify_email' => '提交表单后，我们会向您的邮箱发送验证码。验证码 10 分钟后失效，最多尝试 5 次；上次发送 30 秒后可重新获取。',
            'kb_signup_fields' => '您需要填写全名、邮箱、电话号码以及至少 6 位的密码。管理员和卖家还需填写企业名称、企业地点和企业类型。',
            'kb_seller_approval' => '卖家账户初始为“待批准”。该企业的已批准管理员同意后您才能登录。管理员注册后可直接登录。',
            'kb_first_login' => '首次登录时，应用会请您上传企业照片并在地图上标注地点。这些内容客户可见，请选择清晰的照片。',
            'kb_first_product' => '在卖家区域添加第一个商品：名称、照片、进货价和销售价。数量与最低库存会用于库存提醒。',
            'kb_public_listing' => '当企业设置为公开时，会出现在公开市场中。已认证企业页面只列出已认证企业。',
            'kb_certification' => '企业认证由平台管理员审核，不会自动完成。认证后，您的企业会出现在已认证企业页面。',
            'kb_language' => '您可以随时切换语言。所有界面（包括我们发送的邮件）都会使用您选择的语言。',
            'kb_password' => '忘记密码？在登录页使用“忘记密码”：我们发送验证码，您确认后即可设置新密码。',
            'kb_pending_login' => '如果暂时无法登录，请查看账户状态：卖家账户需要企业管理员批准。',
        ],
    ];

    /**
     * The keyword map for selecting approved topics from a free-text question.
     * Keywords are matched against the normalized question in any language, so a
     * user can ask in their own words.
     *
     * @var array<string, array<int, string>>
     */
    public const TOPIC_KEYWORDS = [
        'kb_signup_roles' => ['sign up', 'signup', 'register', 'registration', 'create account', 'create an account', 'which account', 'kujisajili', 'jisajili', 'fungua akaunti', 'akaunti mpya', 'inscription', 'creer un compte', 'registrarse', 'registro', 'registrarme', 'crear cuenta', 'cuenta nueva', 'registrieren', 'konto erstellen', '注册', '创建账号', 'साइन अप', 'खाता बनाएं', 'رجسٹر', 'اکاؤنٹ بنائیں'],
        'kb_verify_email' => ['code', 'otp', 'verification', 'verify', 'expired', 'did not arrive', 'no email', 'resend', 'msimbo', 'msimbo wa uthibitisho', 'uthibitisho', 'barua pepe', 'code non reçu', 'code de verification', 'codigo', 'codigo de verificacion', 'bestatigungscode', 'verifizierungscode', '验证码', 'कोड', 'کوڈ'],
        'kb_signup_fields' => ['required', 'which fields', 'form', 'password length', 'how many characters', 'details needed', 'jina', 'nenosiri', 'herufi', 'champs', 'campos', 'felder', '字段', 'फ़ील्ड', 'خانے'],
        'kb_seller_approval' => ['approval', 'approve', 'waiting', 'pending', 'not approved', 'subiri', 'inasubiri', 'idhini', 'kupitishwa', 'attente', 'approuve', 'aprobacion', 'aprobado', 'freigabe', 'genehmigt', '批准', '审核', 'मंज़ूरी', 'منظوری'],
        'kb_first_login' => ['first login', 'first sign in', 'photo', 'picture', 'logo', 'location permission', 'kuingia mara ya kwanza', 'picha', 'mahali', 'premiere connexion', 'primer inicio', 'erste anmeldung', '首次登录', 'पहली बार', 'پہلی بار'],
        'kb_first_product' => ['add product', 'first product', 'add item', 'stock', 'inventory', 'selling price', 'bidhaa', 'ongeza', 'bei ya kuuza', 'hisa', 'ajouter un produit', 'producto', 'produkt', '添加商品', 'उत्पाद', 'پروڈکٹ'],
        'kb_public_listing' => ['public', 'marketplace', 'visible', 'appear', 'customer see', 'onekana', 'soko la umma', 'biashara yangu ionekane', 'public', 'mercado', 'offentlich', '公开', 'सार्वजनिक', 'عوامی'],
        'kb_certification' => ['certified', 'certification', 'verified badge', 'trust badge', 'uthibitisho wa biashara', 'kuthibitishwa', 'certifie', 'certificado', 'zertifiziert', '认证', 'प्रमाणित', 'تصدیق'],
        'kb_language' => ['language', 'translate', 'change language', 'lugha', 'kiswahili', 'english', 'langue', 'idioma', 'sprache', '语言', 'भाषा', 'زبان'],
        'kb_password' => ['forgot password', 'reset password', 'cannot remember password', 'new password', 'nimesahau neno la siri', 'neno la siri', 'mot de passe', 'mot de passe oublie', 'contrasena', 'contrasena olvidada', 'passwort', 'passwort vergessen', '密码', '忘记密码', 'पासवर्ड', 'पासवर्ड भूल', 'پاس ورڈ'],
        'kb_pending_login' => ['cannot log in', 'cannot sign in', 'login blocked', 'siwezi kuingia', 'siwezi kuingia bado', 'impossible de me connecter', 'me connecter', 'se connecter', 'connexion impossible', 'no puedo entrar', 'no puedo iniciar sesion', 'kann mich nicht anmelden', 'anmeldung nicht moglich', '无法登录', '登录不了', 'साइन इन नहीं', 'साइन इन नहीं हो', 'سائن ان نہیں'],
    ];

    /**
     * The localized step labels, step instructions and response strings
     * (8 locales, identical key sets - pinned by OnboardingGuideTest).
     *
     * @var array<string, array<string, string>>
     */
    public const MESSAGES = [
        'sw' => [
            'label_profile_name' => 'Jina lako kamili',
            'label_phone' => 'Namba ya simu',
            'label_language' => 'Lugha yako',
            'label_business_name' => 'Jina la biashara',
            'label_business_type' => 'Aina ya biashara',
            'label_business_location' => 'Mahali pa biashara',
            'label_business_description' => 'Maelezo ya biashara',
            'label_logo' => 'Picha au nembo ya biashara',
            'label_location_pinned' => 'Mahali pako kwenye ramani',
            'label_approval' => 'Idhini ya akaunti yako',
            'label_first_product' => 'Bidhaa ya kwanza',
            'label_public_listing' => 'Kuonekana kwenye soko la umma',
            'label_certification' => 'Uthibitisho wa biashara',
            'how_profile_name' => 'Weka jina lako kamili kwenye profaili yako.',
            'how_phone' => 'Ongeza namba ya simu ili timu na wateja wakupe habari.',
            'how_language' => 'Chagua lugha yako kwenye kitufe cha lugha.',
            'how_business_name' => 'Jina la biashara hili liliwekwa wakati wa kujisajili; msimamizi anaweza kulirekebisha.',
            'how_business_type' => 'Aina ya biashara huchaguliwa wakati wa kusajili akaunti ya msimamizi.',
            'how_business_location' => 'Weka mahali pa biashara yako (mji au eneo).',
            'how_business_description' => 'Andika maelezo mafupi ya biashara ({min} herufi au zaidi).',
            'how_logo' => 'Pakia picha ya biashara yako kwenye skrini ya kuanza.',
            'how_location_pinned' => 'Ruhusu programu kupata mahali pako na uthibitishe alama kwenye ramani.',
            'how_approval' => 'Subiri msimamizi wa biashara yako aikubali akaunti yako.',
            'how_first_product' => 'Ongeza bidhaa yako ya kwanza: jina, picha, bei ya kununua na bei ya kuuza.',
            'how_public_listing' => 'Biashara yako inaonekana kwenye soko la umma ikiwa imewekwa kama ya umma.',
            'how_certification' => 'Uthibitisho hukaguliwa na msimamizi wa jukwaa; hauji kiotomatiki.',
            'status_done' => 'Imekamilika',
            'status_todo' => 'Inahitajika',
            'status_recommended' => 'Inashauriwa',
            'status_unknown' => 'Haijasomwa',
            'status_waiting' => 'Inasubiri idhini',
            'status_rejected' => 'Imekataliwa',
            'role_admin' => 'Msimamizi wa biashara',
            'role_seller' => 'Muuzaji / mhudumu',
            'role_customer' => 'Mteja',
            'signup_web' => 'Ukurasa wa kujisajili',
            'signup_app' => 'Skrini ya programu',
            'signup_needs_approval' => 'Akaunti hii inasubiri idhini ya msimamizi wa biashara.',
            'signup_direct' => 'Akaunti hii inaingia mara moja baada ya kuthibitisha barua pepe.',
            'sum_all_done' => 'Umekamilisha hatua zote muhimu. Karibu!',
            'sum_remaining' => 'Zimebaki hatua {count} muhimu.',
            'sum_no_next' => 'Hakuna hatua inayofuata kwa sasa.',
            'lim_rules' => 'Majibu yote yanatoka kwenye mwongozo ulioidhinishwa wa jukwaa; hakuna hatua inayobuniwa.',
            'lim_own_data' => 'Orodha hii inaonyesha rekodi zako tu; hatuonyeshi nywila, misimbo au taarifa za akaunti nyingine.',
            'lim_secure' => 'Hatutaki nywila au msimbo wa uthibitisho; usimpe mtu yeyote taarifa hizo.',
            'lim_no_approval' => 'Mwongozo huu haukubali wala hauthibitishi akaunti; msimamizi peke yake anaweza.',
            'topic_prefix' => 'Kutoka mwongozo ulioidhinishwa',
            'notice_no_match' => 'Swali lako halijalingana na mwongozo ulioidhinishwa. Orodha ya hatua zako ipo hapa chini.',
        ],
        'en' => [
            'label_profile_name' => 'Your full name',
            'label_phone' => 'Phone number',
            'label_language' => 'Your language',
            'label_business_name' => 'Business name',
            'label_business_type' => 'Business type',
            'label_business_location' => 'Business location',
            'label_business_description' => 'Business description',
            'label_logo' => 'Business photo or logo',
            'label_location_pinned' => 'Your location on the map',
            'label_approval' => 'Approval of your account',
            'label_first_product' => 'First product',
            'label_public_listing' => 'Visible in the public marketplace',
            'label_certification' => 'Business certification',
            'how_profile_name' => 'Write your full name in your profile.',
            'how_phone' => 'Add a phone number so the team and customers can reach you.',
            'how_language' => 'Choose your language with the language button.',
            'how_business_name' => 'The business name was set when you signed up; an administrator can correct it.',
            'how_business_type' => 'The business type is chosen when the administrator account is created.',
            'how_business_location' => 'Record where your business is (town or area).',
            'how_business_description' => 'Write a short description of the business ({min} characters or more).',
            'how_logo' => 'Upload a photo of your business on the setup screen.',
            'how_location_pinned' => 'Allow location access and confirm the pin on the map.',
            'how_approval' => 'Wait for the administrator of your business to accept your account.',
            'how_first_product' => 'Add your first product: name, photo, buying price and selling price.',
            'how_public_listing' => 'Your business appears in the public marketplace when it is set to public.',
            'how_certification' => 'Certification is reviewed by a platform administrator; it is not automatic.',
            'status_done' => 'Done',
            'status_todo' => 'To do',
            'status_recommended' => 'Recommended',
            'status_unknown' => 'Not read',
            'status_waiting' => 'Waiting for approval',
            'status_rejected' => 'Rejected',
            'role_admin' => 'Business administrator',
            'role_seller' => 'Seller / cashier',
            'role_customer' => 'Customer',
            'signup_web' => 'Signup page',
            'signup_app' => 'App screen',
            'signup_needs_approval' => 'This account waits for the business administrator to approve it.',
            'signup_direct' => 'This account can sign in once the email is verified.',
            'sum_all_done' => 'You have completed every required step. Welcome!',
            'sum_remaining' => '{count} required step(s) left.',
            'sum_no_next' => 'There is no next step right now.',
            'lim_rules' => 'Every answer comes from the platform\'s approved onboarding content; no step is invented.',
            'lim_own_data' => 'This list shows only your own records; we never show passwords, codes or another account\'s data.',
            'lim_secure' => 'We never ask for your password or verification code; never share them with anyone.',
            'lim_no_approval' => 'This guide cannot approve or certify an account; only an administrator can.',
            'topic_prefix' => 'From the approved guide',
            'notice_no_match' => 'Your question did not match the approved guide. Your setup checklist is below.',
        ],
        'fr' => [
            'label_profile_name' => 'Votre nom complet',
            'label_phone' => 'Numéro de téléphone',
            'label_language' => 'Votre langue',
            'label_business_name' => "Nom de l'entreprise",
            'label_business_type' => "Type d'entreprise",
            'label_business_location' => "Emplacement de l'entreprise",
            'label_business_description' => "Description de l'entreprise",
            'label_logo' => "Photo ou logo de l'entreprise",
            'label_location_pinned' => 'Votre position sur la carte',
            'label_approval' => 'Approbation de votre compte',
            'label_first_product' => 'Premier produit',
            'label_public_listing' => 'Visible dans la place de marché publique',
            'label_certification' => "Certification de l'entreprise",
            'how_profile_name' => 'Écrivez votre nom complet dans votre profil.',
            'how_phone' => "Ajoutez un téléphone pour que l'équipe et les clients puissent vous joindre.",
            'how_language' => 'Choisissez votre langue avec le bouton de langue.',
            'how_business_name' => "Le nom a été défini à l'inscription ; un administrateur peut le corriger.",
            'how_business_type' => "Le type est choisi lors de la création du compte administrateur.",
            'how_business_location' => "Indiquez où se trouve votre entreprise (ville ou zone).",
            'how_business_description' => "Rédigez une courte description ({min} caractères ou plus).",
            'how_logo' => "Téléversez une photo de votre entreprise sur l'écran de configuration.",
            'how_location_pinned' => "Autorisez la localisation et confirmez le repère sur la carte.",
            'how_approval' => "Attendez que l'administrateur de votre entreprise accepte votre compte.",
            'how_first_product' => "Ajoutez votre premier produit : nom, photo, prix d'achat et prix de vente.",
            'how_public_listing' => "Votre entreprise apparaît dans la place de marché publique lorsqu'elle est publique.",
            'how_certification' => "La certification est examinée par un administrateur de la plateforme ; elle n'est pas automatique.",
            'status_done' => 'Terminé',
            'status_todo' => 'À faire',
            'status_recommended' => 'Recommandé',
            'status_unknown' => 'Non lu',
            'status_waiting' => "En attente d'approbation",
            'status_rejected' => 'Refusé',
            'role_admin' => "Administrateur de l'entreprise",
            'role_seller' => 'Vendeur / caissier',
            'role_customer' => 'Client',
            'signup_web' => "Page d'inscription",
            'signup_app' => "Écran de l'application",
            'signup_needs_approval' => "Ce compte attend l'approbation de l'administrateur de l'entreprise.",
            'signup_direct' => "Ce compte peut se connecter dès que l'e-mail est vérifié.",
            'sum_all_done' => 'Vous avez terminé toutes les étapes obligatoires. Bienvenue !',
            'sum_remaining' => '{count} étape(s) obligatoire(s) restante(s).',
            'sum_no_next' => "Il n'y a pas d'étape suivante pour le moment.",
            'lim_rules' => "Chaque réponse vient du contenu d'accueil approuvé de la plateforme ; aucune étape n'est inventée.",
            'lim_own_data' => "Cette liste ne montre que vos propres données ; jamais de mots de passe, de codes ni de données d'un autre compte.",
            'lim_secure' => "Nous ne demandons jamais votre mot de passe ni votre code ; ne les partagez avec personne.",
            'lim_no_approval' => "Ce guide ne peut approuver ni certifier un compte ; seul un administrateur peut le faire.",
            'topic_prefix' => 'D\'après le guide approuvé',
            'notice_no_match' => "Votre question ne correspond pas au guide approuvé. Votre liste d'étapes est ci-dessous.",
        ],
        'hi' => [
            'label_profile_name' => 'आपका पूरा नाम',
            'label_phone' => 'फ़ोन नंबर',
            'label_language' => 'आपकी भाषा',
            'label_business_name' => 'व्यवसाय का नाम',
            'label_business_type' => 'व्यवसाय का प्रकार',
            'label_business_location' => 'व्यवसाय का स्थान',
            'label_business_description' => 'व्यवसाय का विवरण',
            'label_logo' => 'व्यवसाय की फ़ोटो या लोगो',
            'label_location_pinned' => 'नक्शे पर आपका स्थान',
            'label_approval' => 'आपके खाते की मंज़ूरी',
            'label_first_product' => 'पहला उत्पाद',
            'label_public_listing' => 'सार्वजनिक बाज़ार में दिखना',
            'label_certification' => 'व्यवसाय प्रमाणन',
            'how_profile_name' => 'अपने प्रोफ़ाइल में पूरा नाम लिखें।',
            'how_phone' => 'फ़ोन नंबर जोड़ें ताकि टीम और ग्राहक आपसे संपर्क कर सकें।',
            'how_language' => 'भाषा बटन से अपनी भाषा चुनें।',
            'how_business_name' => 'नाम साइनअप के समय सेट हुआ; एडमिन इसे ठीक कर सकता है।',
            'how_business_type' => 'प्रकार एडमिन खाता बनाते समय चुना जाता है।',
            'how_business_location' => 'अपने व्यवसाय का स्थान (शहर या क्षेत्र) दर्ज करें।',
            'how_business_description' => 'व्यवसाय का छोटा विवरण लिखें ({min} या अधिक अक्षर)।',
            'how_logo' => 'सेटअप स्क्रीन पर अपने व्यवसाय की फ़ोटो अपलोड करें।',
            'how_location_pinned' => 'स्थान की अनुमति दें और नक्शे पर पिन की पुष्टि करें।',
            'how_approval' => 'अपने व्यवसाय के एडमिन द्वारा खाता स्वीकृत होने की प्रतीक्षा करें।',
            'how_first_product' => 'पहला उत्पाद जोड़ें: नाम, फ़ोटो, खरीद मूल्य और बिक्री मूल्य।',
            'how_public_listing' => 'सार्वजनिक सेट होने पर आपका व्यवसाय सार्वजनिक बाज़ार में दिखता है।',
            'how_certification' => 'प्रमाणन की समीक्षा प्लेटफ़ॉर्म एडमिन करता है; यह स्वतः नहीं होता।',
            'status_done' => 'पूरा',
            'status_todo' => 'करना है',
            'status_recommended' => 'अनुशंसित',
            'status_unknown' => 'नहीं पढ़ा गया',
            'status_waiting' => 'मंज़ूरी की प्रतीक्षा',
            'status_rejected' => 'अस्वीकृत',
            'role_admin' => 'व्यवसाय एडमिन',
            'role_seller' => 'विक्रेता / कैशियर',
            'role_customer' => 'ग्राहक',
            'signup_web' => 'साइनअप पेज',
            'signup_app' => 'ऐप स्क्रीन',
            'signup_needs_approval' => 'यह खाता व्यवसाय एडमिन की मंज़ूरी की प्रतीक्षा करता है।',
            'signup_direct' => 'ईमेल सत्यापित होते ही यह खाता साइन इन कर सकता है।',
            'sum_all_done' => 'आपने सभी आवश्यक चरण पूरे कर लिए। स्वागत है!',
            'sum_remaining' => '{count} आवश्यक चरण शेष हैं।',
            'sum_no_next' => 'अभी कोई अगला चरण नहीं है।',
            'lim_rules' => 'प्रत्येक उत्तर प्लेटफ़ॉर्म की स्वीकृत मार्गदर्शिका से है; कोई चरण बनाया नहीं जाता।',
            'lim_own_data' => 'यह सूची केवल आपके रिकॉर्ड दिखाती है; पासवर्ड, कोड या दूसरे खाते की जानकारी कभी नहीं।',
            'lim_secure' => 'हम कभी पासवर्ड या सत्यापन कोड नहीं माँगते; किसी से साझा न करें।',
            'lim_no_approval' => 'यह मार्गदर्शिका खाते को स्वीकृत या प्रमाणित नहीं कर सकती; केवल एडमिन कर सकता है।',
            'topic_prefix' => 'स्वीकृत मार्गदर्शिका से',
            'notice_no_match' => 'आपका प्रश्न स्वीकृत मार्गदर्शिका से मेल नहीं खाया। आपके चरण नीचे हैं।',
        ],
        'es' => [
            'label_profile_name' => 'Su nombre completo',
            'label_phone' => 'Número de teléfono',
            'label_language' => 'Su idioma',
            'label_business_name' => 'Nombre del negocio',
            'label_business_type' => 'Tipo de negocio',
            'label_business_location' => 'Ubicación del negocio',
            'label_business_description' => 'Descripción del negocio',
            'label_logo' => 'Foto o logotipo del negocio',
            'label_location_pinned' => 'Su ubicación en el mapa',
            'label_approval' => 'Aprobación de su cuenta',
            'label_first_product' => 'Primer producto',
            'label_public_listing' => 'Visible en el mercado público',
            'label_certification' => 'Certificación del negocio',
            'how_profile_name' => 'Escriba su nombre completo en su perfil.',
            'how_phone' => 'Añada un teléfono para que el equipo y los clientes puedan contactarle.',
            'how_language' => 'Elija su idioma con el botón de idioma.',
            'how_business_name' => 'El nombre se definió al registrarse; un administrador puede corregirlo.',
            'how_business_type' => 'El tipo se elige al crear la cuenta de administrador.',
            'how_business_location' => 'Registre dónde está su negocio (ciudad o zona).',
            'how_business_description' => 'Escriba una descripción corta del negocio ({min} caracteres o más).',
            'how_logo' => 'Suba una foto de su negocio en la pantalla de configuración.',
            'how_location_pinned' => 'Permita el acceso a la ubicación y confirme el punto en el mapa.',
            'how_approval' => 'Espere a que el administrador de su negocio acepte su cuenta.',
            'how_first_product' => 'Añada su primer producto: nombre, foto, precio de compra y precio de venta.',
            'how_public_listing' => 'Su negocio aparece en el mercado público cuando está marcado como público.',
            'how_certification' => 'La certificación la revisa un administrador de la plataforma; no es automática.',
            'status_done' => 'Hecho',
            'status_todo' => 'Pendiente',
            'status_recommended' => 'Recomendado',
            'status_unknown' => 'No leído',
            'status_waiting' => 'Esperando aprobación',
            'status_rejected' => 'Rechazado',
            'role_admin' => 'Administrador del negocio',
            'role_seller' => 'Vendedor / cajero',
            'role_customer' => 'Cliente',
            'signup_web' => 'Página de registro',
            'signup_app' => 'Pantalla de la app',
            'signup_needs_approval' => 'Esta cuenta espera la aprobación del administrador del negocio.',
            'signup_direct' => 'Esta cuenta puede entrar cuando el correo esté verificado.',
            'sum_all_done' => 'Ha completado todos los pasos obligatorios. ¡Bienvenido!',
            'sum_remaining' => 'Quedan {count} paso(s) obligatorio(s).',
            'sum_no_next' => 'No hay un paso siguiente por ahora.',
            'lim_rules' => 'Cada respuesta proviene del contenido de bienvenida aprobado; ningún paso se inventa.',
            'lim_own_data' => 'Esta lista muestra solo sus registros; nunca contraseñas, códigos ni datos de otra cuenta.',
            'lim_secure' => 'Nunca pedimos su contraseña ni su código de verificación; no los comparta.',
            'lim_no_approval' => 'Esta guía no puede aprobar ni certificar una cuenta; solo un administrador puede.',
            'topic_prefix' => 'De la guía aprobada',
            'notice_no_match' => 'Su pregunta no coincide con la guía aprobada. Su lista de pasos está abajo.',
        ],
        'ur' => [
            'label_profile_name' => 'آپ کا پورا نام',
            'label_phone' => 'فون نمبر',
            'label_language' => 'آپ کی زبان',
            'label_business_name' => 'کاروبار کا نام',
            'label_business_type' => 'کاروبار کی قسم',
            'label_business_location' => 'کاروبار کی جگہ',
            'label_business_description' => 'کاروبار کی تفصیل',
            'label_logo' => 'کاروبار کی تصویر یا لوگو',
            'label_location_pinned' => 'نقشے پر آپ کی جگہ',
            'label_approval' => 'آپ کے اکاؤنٹ کی منظوری',
            'label_first_product' => 'پہلی پروڈکٹ',
            'label_public_listing' => 'عوامی مارکیٹ میں نظر آنا',
            'label_certification' => 'کاروبار کی تصدیق',
            'how_profile_name' => 'اپنے پروفائل میں پورا نام لکھیں۔',
            'how_phone' => 'فون نمبر شامل کریں تاکہ ٹیم اور گاہک رابطہ کر سکیں۔',
            'how_language' => 'زبان کے بٹن سے اپنی زبان چنیں۔',
            'how_business_name' => 'نام سائن اپ کے وقت مقرر ہوا؛ ایڈمن اسے درست کر سکتا ہے۔',
            'how_business_type' => 'قسم ایڈمن اکاؤنٹ بناتے وقت چنی جاتی ہے۔',
            'how_business_location' => 'اپنے کاروبار کی جگہ (شہر یا علاقہ) درج کریں۔',
            'how_business_description' => 'کاروبار کی مختصر تفصیل لکھیں ({min} یا زیادہ حروف)۔',
            'how_logo' => 'سیٹ اپ اسکرین پر اپنے کاروبار کی تصویر اپلوڈ کریں۔',
            'how_location_pinned' => 'مقام کی اجازت دیں اور نقشے پر پن کی تصدیق کریں۔',
            'how_approval' => 'اپنے کاروبار کے ایڈمن کی منظوری کا انتظار کریں۔',
            'how_first_product' => 'پہلی پروڈکٹ شامل کریں: نام، تصویر، خرید قیمت اور فروخت قیمت۔',
            'how_public_listing' => 'عوامی مقرر ہونے پر آپ کا کاروبار عوامی مارکیٹ میں نظر آتا ہے۔',
            'how_certification' => 'تصدیق پلیٹ فارم ایڈمن کرتا ہے؛ یہ خودکار نہیں۔',
            'status_done' => 'مکمل',
            'status_todo' => 'باقی',
            'status_recommended' => 'تجویز کردہ',
            'status_unknown' => 'نہیں پڑھا گیا',
            'status_waiting' => 'منظوری کا انتظار',
            'status_rejected' => 'مسترد',
            'role_admin' => 'کاروباری ایڈمن',
            'role_seller' => 'سیلر / کیشیئر',
            'role_customer' => 'کسٹمر',
            'signup_web' => 'سائن اپ صفحہ',
            'signup_app' => 'ایپ اسکرین',
            'signup_needs_approval' => 'یہ اکاؤنٹ کاروبار کے ایڈمن کی منظوری کا منتظر ہے۔',
            'signup_direct' => 'ای میل کی تصدیق کے بعد یہ اکاؤنٹ سائن ان کر سکتا ہے۔',
            'sum_all_done' => 'آپ نے تمام لازمی مراحل مکمل کر لیے۔ خوش آمدید!',
            'sum_remaining' => '{count} لازمی مرحلے باقی ہیں۔',
            'sum_no_next' => 'ابھی کوئی اگلا مرحلہ نہیں۔',
            'lim_rules' => 'ہر جواب پلیٹ فارم کی منظور شدہ گائیڈ سے ہے؛ کوئی مرحلہ بناوٹی نہیں۔',
            'lim_own_data' => 'یہ فہرست صرف آپ کے ریکارڈ دکھاتی ہے؛ پاس ورڈ، کوڈ یا دوسرے اکاؤنٹ کی معلومات کبھی نہیں۔',
            'lim_secure' => 'ہم کبھی پاس ورڈ یا تصدیقی کوڈ نہیں مانگتے؛ کسی کو نہ بتائیں۔',
            'lim_no_approval' => 'یہ گائیڈ اکاؤنٹ منظور یا تصدیق نہیں کر سکتی؛ صرف ایڈمن کر سکتا ہے۔',
            'topic_prefix' => 'منظور شدہ گائیڈ سے',
            'notice_no_match' => 'آپ کا سوال منظور شدہ گائیڈ سے مماثل نہیں۔ آپ کے مراحل نیچے ہیں۔',
        ],
        'de' => [
            'label_profile_name' => 'Ihr vollständiger Name',
            'label_phone' => 'Telefonnummer',
            'label_language' => 'Ihre Sprache',
            'label_business_name' => 'Firmenname',
            'label_business_type' => 'Firmentyp',
            'label_business_location' => 'Firmenstandort',
            'label_business_description' => 'Firmenbeschreibung',
            'label_logo' => 'Firmenfoto oder Logo',
            'label_location_pinned' => 'Ihr Standort auf der Karte',
            'label_approval' => 'Freigabe Ihres Kontos',
            'label_first_product' => 'Erstes Produkt',
            'label_public_listing' => 'Im öffentlichen Marktplatz sichtbar',
            'label_certification' => 'Firmenzertifizierung',
            'how_profile_name' => 'Schreiben Sie Ihren vollständigen Namen in Ihr Profil.',
            'how_phone' => 'Fügen Sie eine Telefonnummer hinzu, damit Team und Kunden Sie erreichen.',
            'how_language' => 'Wählen Sie Ihre Sprache über die Sprachschaltfläche.',
            'how_business_name' => 'Der Name wurde bei der Registrierung gesetzt; ein Administrator kann ihn korrigieren.',
            'how_business_type' => 'Der Typ wird beim Anlegen des Administratorkontos gewählt.',
            'how_business_location' => 'Erfassen Sie, wo Ihr Unternehmen ist (Stadt oder Gebiet).',
            'how_business_description' => 'Schreiben Sie eine kurze Beschreibung ({min} Zeichen oder mehr).',
            'how_logo' => 'Laden Sie im Einrichtungsbildschirm ein Foto Ihres Unternehmens hoch.',
            'how_location_pinned' => 'Erlauben Sie den Standortzugriff und bestätigen Sie die Markierung auf der Karte.',
            'how_approval' => 'Warten Sie, bis der Administrator Ihres Unternehmens Ihr Konto annimmt.',
            'how_first_product' => 'Fügen Sie Ihr erstes Produkt hinzu: Name, Foto, Einkaufspreis und Verkaufspreis.',
            'how_public_listing' => 'Ihr Unternehmen erscheint im öffentlichen Marktplatz, wenn es öffentlich gesetzt ist.',
            'how_certification' => 'Die Zertifizierung prüft ein Plattform-Administrator; sie ist nicht automatisch.',
            'status_done' => 'Erledigt',
            'status_todo' => 'Zu tun',
            'status_recommended' => 'Empfohlen',
            'status_unknown' => 'Nicht gelesen',
            'status_waiting' => 'Wartet auf Freigabe',
            'status_rejected' => 'Abgelehnt',
            'role_admin' => 'Firmen-Administrator',
            'role_seller' => 'Verkäufer / Kassierer',
            'role_customer' => 'Kunde',
            'signup_web' => 'Registrierungsseite',
            'signup_app' => 'App-Bildschirm',
            'signup_needs_approval' => 'Dieses Konto wartet auf die Freigabe durch den Firmen-Administrator.',
            'signup_direct' => 'Dieses Konto kann sich anmelden, sobald die E-Mail bestätigt ist.',
            'sum_all_done' => 'Sie haben alle erforderlichen Schritte erledigt. Willkommen!',
            'sum_remaining' => '{count} erforderliche(r) Schritt(e) offen.',
            'sum_no_next' => 'Derzeit gibt es keinen nächsten Schritt.',
            'lim_rules' => 'Jede Antwort stammt aus den freigegebenen Einrichtungsinhalten; kein Schritt wird erfunden.',
            'lim_own_data' => 'Diese Liste zeigt nur Ihre Daten; niemals Passwörter, Codes oder Daten anderer Konten.',
            'lim_secure' => 'Wir fragen niemals nach Passwort oder Bestätigungscode; geben Sie sie niemandem.',
            'lim_no_approval' => 'Diese Anleitung kann kein Konto freigeben oder zertifizieren; das kann nur ein Administrator.',
            'topic_prefix' => 'Aus der freigegebenen Anleitung',
            'notice_no_match' => 'Ihre Frage passt nicht zur freigegebenen Anleitung. Ihre Schritte stehen unten.',
        ],
        'zh' => [
            'label_profile_name' => '您的全名',
            'label_phone' => '电话号码',
            'label_language' => '您的语言',
            'label_business_name' => '企业名称',
            'label_business_type' => '企业类型',
            'label_business_location' => '企业地点',
            'label_business_description' => '企业简介',
            'label_logo' => '企业照片或标志',
            'label_location_pinned' => '您在地图上的位置',
            'label_approval' => '账户批准',
            'label_first_product' => '第一个商品',
            'label_public_listing' => '在公开市场中可见',
            'label_certification' => '企业认证',
            'how_profile_name' => '在个人资料中填写您的全名。',
            'how_phone' => '添加电话号码，方便团队和客户联系您。',
            'how_language' => '使用语言按钮选择您的语言。',
            'how_business_name' => '名称在注册时确定；管理员可以更正。',
            'how_business_type' => '类型在创建管理员账户时选择。',
            'how_business_location' => '记录您企业所在的地点（城市或区域）。',
            'how_business_description' => '撰写简短的企业简介（{min} 个字符以上）。',
            'how_logo' => '在设置界面上传企业照片。',
            'how_location_pinned' => '允许定位并在地图上确认标记。',
            'how_approval' => '等待您企业的管理员批准您的账户。',
            'how_first_product' => '添加第一个商品：名称、照片、进货价和销售价。',
            'how_public_listing' => '企业设置为公开后即出现在公开市场。',
            'how_certification' => '认证由平台管理员审核，不会自动完成。',
            'status_done' => '已完成',
            'status_todo' => '待完成',
            'status_recommended' => '建议',
            'status_unknown' => '未读取',
            'status_waiting' => '等待批准',
            'status_rejected' => '已拒绝',
            'role_admin' => '企业管理员',
            'role_seller' => '卖家 / 收银员',
            'role_customer' => '客户',
            'signup_web' => '注册页面',
            'signup_app' => '应用界面',
            'signup_needs_approval' => '该账户等待企业管理员批准。',
            'signup_direct' => '邮箱验证后该账户即可登录。',
            'sum_all_done' => '您已完成所有必需步骤。欢迎！',
            'sum_remaining' => '还剩 {count} 个必需步骤。',
            'sum_no_next' => '目前没有下一步。',
            'lim_rules' => '每个回答都来自平台已批准的入门内容；不会编造步骤。',
            'lim_own_data' => '此列表只显示您自己的记录；绝不显示密码、验证码或他人账户数据。',
            'lim_secure' => '我们绝不索要密码或验证码；请勿向任何人透露。',
            'lim_no_approval' => '本指南不能批准或认证账户；只有管理员可以。',
            'topic_prefix' => '来自已批准的指南',
            'notice_no_match' => '您的问题与已批准的指南不匹配。您的步骤见下方。',
        ],
    ];

    /** The localized string for one key, with {placeholders} filled in. */
    public static function msg(string $key, string $locale, array $params = []): string
    {
        $table = self::MESSAGES[$locale] ?? self::MESSAGES['en'];
        $text = $table[$key] ?? (self::MESSAGES['en'][$key] ?? $key);

        foreach ($params as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /** The approved onboarding text for one topic, falling back to English. */
    public static function kbText(string $topicId, string $locale, ?array $override = null): string
    {
        $kb = self::knowledgeBase($override);
        $table = $kb[$locale] ?? $kb['en'] ?? [];

        return (string) ($table[$topicId] ?? ($kb['en'][$topicId] ?? ''));
    }

    /** The topic ids this guide can answer. */
    public static function topicIds(): array
    {
        return array_keys(self::TOPIC_KEYWORDS);
    }

    /** Every locale carries the same message keys and the same topic ids. */
    public static function messagesComplete(): bool
    {
        $english = array_keys(self::MESSAGES['en']);
        sort($english);

        foreach (self::MESSAGES as $table) {
            $keys = array_keys($table);
            sort($keys);
            if ($keys !== $english) {
                return false;
            }
            foreach ($table as $value) {
                if (trim((string) $value) === '') {
                    return false;
                }
            }
        }

        foreach (self::KB as $table) {
            $keys = array_keys($table);
            sort($keys);
            $expected = self::topicIds();
            sort($expected);
            if ($keys !== $expected) {
                return false;
            }
            foreach ($table as $value) {
                if (trim((string) $value) === '') {
                    return false;
                }
            }
        }

        return true;
    }

    /** The placeholders used by one message key. */
    public static function placeholders(string $key, string $locale): array
    {
        preg_match_all('/\{([a-z_]+)\}/', self::msg($key, $locale), $matches);

        return $matches[1] ?? [];
    }

    /**
     * The setup checklist for one account.
     *
     * @param  array{
     *     locale: string,
     *     role: string,
     *     user: array<string, mixed>|null,
     *     business?: array<string, mixed>|null,
     *     product_count?: int|null,
     *     steps?: array<int, array<string, mixed>>|null
     * }  $ctx
     * @return array<string, mixed>
     */
    public static function steps(array $ctx): array
    {
        $locale = (string) ($ctx['locale'] ?? 'en');
        $role = self::role((string) ($ctx['role'] ?? ''));
        $user = is_array($ctx['user'] ?? null) ? $ctx['user'] : null;
        $business = is_array($ctx['business'] ?? null) ? $ctx['business'] : null;
        $productCount = array_key_exists('product_count', $ctx) && $ctx['product_count'] !== null
            ? max(0, (int) $ctx['product_count'])
            : null;

        $table = self::normaliseSteps($ctx['steps'] ?? null);

        $steps = [];
        foreach ($table as $step) {
            if (! in_array($role, (array) ($step['roles'] ?? []), true)) {
                continue;
            }
            $steps[] = self::judgeStep($step, $role, $user, $business, $productCount, $locale);
        }

        $requiredSteps = array_values(array_filter($steps, static fn (array $s): bool => $s['required']));
        $doneRequired = array_values(array_filter($requiredSteps, static fn (array $s): bool => $s['done']));
        $next = null;
        foreach ($requiredSteps as $step) {
            if (! $step['done']) {
                $next = ['id' => $step['id'], 'label' => $step['label'], 'how' => $step['how']];
                break;
            }
        }

        $summary = $next === null
            ? [self::msg('sum_all_done', $locale)]
            : [self::msg('sum_remaining', $locale, ['count' => count($requiredSteps) - count($doneRequired)])];

        return [
            'role' => $role,
            'role_label' => self::msg('role_'.$role, $locale),
            'steps' => $steps,
            'next_step' => $next,
            'counts' => [
                'total' => count($steps),
                'required' => count($requiredSteps),
                'done' => count($doneRequired),
                'remaining' => count($requiredSteps) - count($doneRequired),
            ],
            'progress' => $requiredSteps !== [] ? (int) round((count($doneRequired) / count($requiredSteps)) * 100) : 100,
            'summary' => $summary,
            'signup' => self::signupFacts($role, $locale),
            'sources' => self::sources($steps),
            'limitations' => [
                self::msg('lim_rules', $locale),
                self::msg('lim_own_data', $locale),
                self::msg('lim_secure', $locale),
                self::msg('lim_no_approval', $locale),
            ],
        ];
    }

    /**
     * The grounded answer to an onboarding question: the approved topics that
     * match, their approved text in the user's language, plus the setup
     * checklist so the answer is actionable.
     *
     * @param  array{
     *     locale: string,
     *     role: string,
     *     question: string,
     *     user: array<string, mixed>|null,
     *     business?: array<string, mixed>|null,
     *     product_count?: int|null,
     *     kb?: array<string, array<string, string>>|null,
     *     acknowledgement?: ?string
     * }  $ctx
     * @return array<string, mixed>
     */
    public static function answer(array $ctx): array
    {
        $locale = (string) ($ctx['locale'] ?? 'en');
        $question = (string) ($ctx['question'] ?? '');
        $kbOverride = is_array($ctx['kb'] ?? null) ? $ctx['kb'] : null;

        $matches = self::matchTopics($question, self::MAX_TOPICS);

        $ack = isset($ctx['acknowledgement']) ? self::sanitizeAckString((string) $ctx['acknowledgement']) : null;

        $topics = [];
        foreach ($matches as $match) {
            $text = self::kbText($match['id'], $locale, $kbOverride);
            if ($text === '') {
                continue;
            }
            $topics[] = [
                'id' => $match['id'],
                'label' => self::msg('topic_prefix', $locale),
                'evidence' => $match['evidence'],
                'answer' => $text,
            ];
        }

        $guide = self::steps($ctx);

        return [
            'question' => $question,
            'matched' => $topics !== [],
            'acknowledgement' => $ack,
            'topics' => $topics,
            'answers' => array_column($topics, 'answer'),
            'guide' => $guide,
            'notice' => $guide['summary'][0] ?? '',
            'sources' => array_values(array_unique(array_merge(
                array_column($topics, 'label'),
                $guide['sources']
            ))),
            'limitations' => $guide['limitations'],
        ];
    }

    /**
     * Which approved topics a question is about. Deterministic keyword matching
     * over the normalized question; the model may only NARROW this list.
     *
     * @return array<int, array{id: string, evidence: array<int, string>}>
     */
    public static function matchTopics(string $question, int $limit = self::MAX_TOPICS): array
    {
        $haystack = self::normalize($question);
        if ($haystack === '') {
            return [];
        }

        $matches = [];
        foreach (self::TOPIC_KEYWORDS as $topicId => $keywords) {
            $hits = [];
            foreach ($keywords as $keyword) {
                $needle = self::normalize($keyword);
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    $hits[] = $keyword;
                }
            }
            if ($hits !== []) {
                $matches[] = ['id' => $topicId, 'evidence' => array_values(array_unique($hits)), 'score' => count($hits)];
            }
        }

        usort($matches, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        return array_map(static function (array $match): array {
            unset($match['score']);

            return ['id' => $match['id'], 'evidence' => $match['evidence']];
        }, array_slice($matches, 0, max(1, $limit)));
    }

    /**
     * Keep only an acknowledgement sentence that carries no digits, no links, no
     * email addresses, no phone numbers and no question marks (it must not turn
     * into a promise or a second answer), and only topic ids from the allow-list.
     *
     * @param  mixed  $raw
     * @return array{ack: ?string, topics: array<int, string>}
     */
    public static function sanitizeNarration($raw): array
    {
        $out = ['ack' => null, 'topics' => []];

        if (! is_array($raw)) {
            return $out;
        }

        if (isset($raw['ack']) && is_string($raw['ack'])) {
            $out['ack'] = self::sanitizeAckString($raw['ack']);
        }

        if (isset($raw['topics']) && is_array($raw['topics'])) {
            $allowed = self::topicIds();
            foreach ($raw['topics'] as $topic) {
                if (is_string($topic) && in_array($topic, $allowed, true) && ! in_array($topic, $out['topics'], true)) {
                    $out['topics'][] = $topic;
                }
            }
        }

        return $out;
    }

    /** One safe acknowledgement sentence, or null. */
    public static function sanitizeAckString(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '' || mb_strlen($text) > 200) {
            return null;
        }
        // Digits (prices, codes, phone parts), links, emails, handles, phones.
        if (preg_match('/\d/u', $text) === 1
            || preg_match('/[\/\\\\@#*_]/u', $text) === 1
            || preg_match('/https?:|www\./iu', $text) === 1
            || preg_match('/\b\d[\d\s\-()]{5,}\b/u', $text) === 1
            || str_contains($text, '?')) {
            return null;
        }

        return $text;
    }

    /** The role, narrowed to the roles this guide documents. */
    public static function role(string $role): string
    {
        $role = strtolower(trim($role));
        if (in_array($role, ['mteja', 'customer', 'client'], true)) {
            return 'customer';
        }
        if (in_array($role, ['muuzaji', 'seller', 'cashier', 'mhudumu'], true)) {
            return 'seller';
        }
        if (in_array($role, ['msimamizi', 'admin', 'administrator'], true)) {
            return 'admin';
        }

        return 'customer';
    }

    /** Lowercase, accent-stripped, punctuation-free text for keyword matching. */
    public static function normalize(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text), 'UTF-8');

        $text = str_replace(
            ['á', 'à', 'â', 'ä', 'ã', 'å', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'ö', 'õ', 'ú', 'ù', 'û', 'ü', 'ñ', 'ç', 'ß'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'n', 'c', 'ss'],
            $text
        );

        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * The status of one step, decided from the real records only.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>|null  $user
     * @param  array<string, mixed>|null  $business
     * @return array{id: string, label: string, how: string, done: bool, required: bool, status: string, status_label: string, source: string, evidence: string}
     */
    private static function judgeStep(array $step, string $role, ?array $user, ?array $business, ?int $productCount, string $locale): array
    {
        $id = (string) $step['id'];
        $rule = (string) $step['rule'];
        $required = (bool) ($step['required'] ?? true);
        $field = (string) ($step['field'] ?? '');
        $minChars = (int) ($step['min_chars'] ?? 0);

        $status = 'unknown';
        $evidence = '';

        if ($rule === 'user_text') {
            $value = self::text($user, $field);
            $status = $value !== '' ? 'done' : 'todo';
            $evidence = $value;
        } elseif ($rule === 'business_text') {
            $value = self::text($business, $field);
            $status = $value !== '' ? 'done' : 'todo';
            $evidence = $value;
        } elseif ($rule === 'either_text') {
            $fromBusiness = self::text($business, $field);
            $value = $fromBusiness !== '' ? $fromBusiness : self::text($user, $field);
            $status = $value !== '' ? 'done' : 'todo';
            $evidence = $value;
        } elseif ($rule === 'either_pair') {
            $pairs = [
                [$business['business_latitude'] ?? null, $business['business_longitude'] ?? null],
                [$user['business_latitude'] ?? null, $user['business_longitude'] ?? null],
            ];
            $done = false;
            foreach ($pairs as $pair) {
                if (($pair[0] ?? null) !== null && $pair[0] !== '' && ($pair[1] ?? null) !== null && $pair[1] !== '') {
                    $done = true;
                    $evidence = trim((string) $pair[0]).', '.trim((string) $pair[1]);
                    break;
                }
            }
            $status = $done ? 'done' : 'todo';
        } elseif ($rule === 'approval') {
            $accountStatus = strtolower(self::text($user, 'status'));
            if ($user === null) {
                $status = 'unknown';
            } elseif ($accountStatus === 'approved') {
                $status = 'done';
            } elseif ($accountStatus === 'pending' || $accountStatus === '') {
                $status = 'waiting';
            } else {
                $status = 'rejected';
            }
            $evidence = $accountStatus;
        } elseif ($rule === 'products') {
            if ($productCount === null) {
                $status = 'unknown';
            } else {
                $status = $productCount > 0 ? 'done' : 'todo';
                $evidence = (string) $productCount;
            }
        } elseif ($rule === 'public_flag') {
            $value = $business['is_public'] ?? null;
            if ($value === null || $value === '') {
                $status = 'unknown';
            } else {
                $status = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'done' : 'todo';
                $evidence = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            }
        } elseif ($rule === 'certified') {
            $value = $business['is_certified'] ?? null;
            if ($value === null || $value === '') {
                $status = 'unknown';
            } else {
                $status = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'done' : 'todo';
                $evidence = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            }
        }

        // A written value may still be too short (the description).
        if ($minChars > 0 && $status === 'done' && mb_strlen($evidence) < $minChars) {
            $status = 'todo';
        }

        return [
            'id' => $id,
            'label' => self::msg('label_'.$id, $locale),
            'how' => self::msg('how_'.$id, $locale, ['min' => $minChars > 0 ? $minChars : self::MIN_DESCRIPTION_CHARS]),
            'done' => $status === 'done',
            'required' => $required,
            'status' => $status,
            'status_label' => $required || $status === 'done'
                ? self::msg('status_'.$status, $locale)
                : self::msg('status_recommended', $locale),
            'source' => (string) ($step['source'] ?? ($field === '' ? $rule : 'users.'.$field)),
            'evidence' => mb_substr($evidence, 0, 120),
        ];
    }

    /**
     * The signup facts of one role (real routes and the real approval rule).
     *
     * @return array<string, mixed>
     */
    private static function signupFacts(string $role, string $locale): array
    {
        $facts = self::SIGNUP[$role] ?? self::SIGNUP['customer'];

        return [
            'role' => $role,
            'web' => (string) $facts['web'],
            'app' => (string) $facts['app'],
            'needs_approval' => (bool) $facts['needs_approval'],
            'note' => self::msg($facts['needs_approval'] ? 'signup_needs_approval' : 'signup_direct', $locale),
        ];
    }

    /**
     * The operator's step list if supplied, otherwise the documented built-in
     * set. Unknown rules are DROPPED rather than guessed, so a mis-configured
     * list can never mark a step done.
     *
     * @param  mixed  $override
     * @return array<int, array<string, mixed>>
     */
    private static function normaliseSteps($override): array
    {
        if (! is_array($override) || $override === []) {
            return self::STEPS;
        }

        $allowedRules = ['user_text', 'business_text', 'either_text', 'either_pair', 'approval', 'products', 'public_flag', 'certified'];
        $clean = [];
        foreach ($override as $step) {
            if (! is_array($step)) {
                continue;
            }
            $id = (string) ($step['id'] ?? '');
            $rule = (string) ($step['rule'] ?? '');
            if ($id === '' || ! in_array($rule, $allowedRules, true)) {
                continue;
            }
            $roles = array_values(array_filter((array) ($step['roles'] ?? self::ROLES), static fn ($r): bool => is_string($r)));
            $step['id'] = $id;
            $step['rule'] = $rule;
            $step['roles'] = $roles === [] ? self::ROLES : $roles;
            $step['required'] = (bool) ($step['required'] ?? true);
            $clean[] = $step;
        }

        return $clean === [] ? self::STEPS : $clean;
    }

    /**
     * The approved knowledge base, with the operator's override merged in per
     * locale + topic (so an operator can correct one sentence without losing the
     * rest).
     *
     * @param  array<string, array<string, string>>|null  $override
     * @return array<string, array<string, string>>
     */
    private static function knowledgeBase(?array $override): array
    {
        if ($override === null || $override === []) {
            return self::KB;
        }

        $kb = self::KB;
        foreach ($override as $locale => $topics) {
            if (! is_string($locale) || ! is_array($topics)) {
                continue;
            }
            foreach ($topics as $topicId => $text) {
                if (! is_string($topicId) || ! is_string($text) || trim($text) === '') {
                    continue;
                }
                if (! in_array($topicId, self::topicIds(), true)) {
                    continue;
                }
                $kb[$locale][$topicId] = $text;
            }
        }

        return $kb;
    }

    /** A trimmed string value from a row that may not be loaded at all. */
    private static function text(?array $row, string $field): string
    {
        return $row === null ? '' : trim((string) ($row[$field] ?? ''));
    }

    /** Every distinct source named by the checklist steps. */
    private static function sources(array $steps): array
    {
        $sources = [];
        foreach ($steps as $step) {
            $source = trim((string) ($step['source'] ?? ''));
            if ($source !== '' && ! in_array($source, $sources, true)) {
                $sources[] = $source;
            }
        }

        return $sources;
    }
}
