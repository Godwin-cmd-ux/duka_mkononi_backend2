<?php

namespace App\Services\Ai;

/**
 * Capability #19 (first half) - business certification review.
 *
 * WHAT THIS IS
 * ------------
 * A deterministic, evidence-based REVIEW ASSISTANT for the Super Admin
 * certification queue. It answers three questions about one business:
 *
 *   1. which required details are actually recorded (and which are missing),
 *   2. which items a person must check because the records cannot settle them,
 *   3. what the recorded evidence is for every line above.
 *
 * WHAT THIS IS NOT
 * ----------------
 * It never certifies, un-certifies or "approves" anything. The final decision
 * stays with the existing, authorized human workflow
 * (`PUT /api/system-admin/businesses/{id}/certify`), which this class does not
 * call, write to or influence. `decision` is always `human_only`, and the
 * response says so in every language.
 *
 * GROUNDING RULES
 * ---------------
 *  - Every requirement reads a REAL column or record that exists in this
 *    platform (see REQUIREMENTS: `businesses.*`, the business's own member rows
 *    on `users`, and its products through `products.seller_id`). Nothing is
 *    inferred from a name, a guess or the model.
 *  - A document requirement is NEVER reported as met: this platform has no
 *    document store, so a submitted document cannot be verified here. It is
 *    reported as `human_required` together with the reason, instead of being
 *    silently treated as present ("never invent missing evidence").
 *  - Backfilled rows carry provenance in `businesses.merged_from`. When the
 *    recorded name or location is only one of several raw spellings/variants,
 *    the row is flagged for a human to confirm the official one.
 *  - All methods are pure (arrays in, arrays out, locale passed in) so the whole
 *    contract is unit-testable without HTTP, a database or Laravel helpers.
 */
class CertificationReview
{
    public const CAPABILITY = 'certification_review';

    /** Requirement severities. */
    public const SEVERITIES = ['required', 'recommended', 'human_check'];

    /** How a requirement is judged. */
    public const STATUS_MET = 'met';
    public const STATUS_MISSING = 'missing';
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_HUMAN = 'human_required';

    /** The minimum length a business description needs to count as written. */
    public const MIN_DESCRIPTION_CHARS = 30;

    /**
     * The documented certification requirement set.
     *
     * `rule` is the only thing that decides a status, and every rule reads a
     * named source. `severity` decides how the item is reported:
     *   required     -> must be recorded before a human certifies
     *   recommended  -> good practice, never blocking
     *   human_check  -> a person must verify it; it can never be "met" by code
     *
     * The operator may replace this list through
     * `config('ai.certification.requirements')` - the reviewer then reports
     * exactly the operator's requirements, still without deciding anything.
     *
     * @var array<int, array{id: string, rule: string, severity: string, source: string, min_chars?: int}>
     */
    public const REQUIREMENTS = [
        ['id' => 'name', 'rule' => 'text', 'severity' => 'required', 'source' => 'businesses.business_name'],
        ['id' => 'type', 'rule' => 'text', 'severity' => 'required', 'source' => 'businesses.business_type'],
        ['id' => 'location', 'rule' => 'text', 'severity' => 'required', 'source' => 'businesses.business_location'],
        ['id' => 'description', 'rule' => 'text', 'severity' => 'required', 'source' => 'businesses.business_description', 'min_chars' => self::MIN_DESCRIPTION_CHARS],
        ['id' => 'logo', 'rule' => 'text', 'severity' => 'required', 'source' => 'businesses.business_logo_url'],
        ['id' => 'pinned', 'rule' => 'pair', 'severity' => 'required', 'source' => 'businesses.business_latitude + businesses.business_longitude'],
        ['id' => 'public', 'rule' => 'flag', 'severity' => 'required', 'source' => 'businesses.is_public'],
        ['id' => 'staff', 'rule' => 'staff', 'severity' => 'required', 'source' => 'users (approved admin/seller of this business, with a name and a phone)'],
        ['id' => 'products', 'rule' => 'products', 'severity' => 'recommended', 'source' => 'products (rows whose seller_id belongs to this business)'],
        ['id' => 'document', 'rule' => 'document', 'severity' => 'human_check', 'source' => 'no document store exists in the platform'],
        ['id' => 'name_ambiguous', 'rule' => 'ambiguity_name', 'severity' => 'human_check', 'source' => 'businesses.merged_from.raw_spellings'],
        ['id' => 'location_ambiguous', 'rule' => 'ambiguity_location', 'severity' => 'human_check', 'source' => 'businesses.merged_from.location_variants'],
        ['id' => 'migration', 'rule' => 'migration', 'severity' => 'human_check', 'source' => 'businesses.migration_status'],
    ];

    /**
     * The localized message table (8 locales, identical key sets - pinned by
     * CertificationReviewTest).
     *
     * @var array<string, array<string, string>>
     */
    public const MESSAGES = [
        'sw' => [
            'req_name' => 'Jina la biashara limeandikwa',
            'req_type' => 'Aina ya biashara imewekwa',
            'req_location' => 'Mahali pa biashara pameandikwa',
            'req_description' => 'Maelezo ya biashara yameandikwa ({min} herufi au zaidi)',
            'req_logo' => 'Nembo (logo) ya biashara imepakiwa',
            'req_pinned' => 'Mahali pa biashara pamewekwa kwenye ramani',
            'req_public' => 'Biashara inaonekana kwenye soko la umma',
            'req_staff' => 'Kuna msimamizi au muuzaji aliyethibitishwa mwenye jina na namba ya simu',
            'req_products' => 'Biashara ina bidhaa angalau moja kwenye orodha',
            'req_document' => 'Hati ya uthibitisho iliyowasilishwa',
            'req_name_ambiguous' => 'Jina la biashara linahitaji kuthibitishwa (majina mengi yameandikwa)',
            'req_location_ambiguous' => 'Mahali pa biashara panahitaji kuthibitishwa (mahali pengi pameandikwa)',
            'req_migration' => 'Safu ya biashara inahitaji kuangaliwa (haijasafishwa kikamilifu)',
            'status_met' => 'Imekamilika',
            'status_missing' => 'Haipo',
            'status_unknown' => 'Haijasomwa',
            'status_human' => 'Mtu lazima aangalie',
            'sev_required' => 'Lazima',
            'sev_recommended' => 'Inashauriwa',
            'sev_human_check' => 'Ukaguzi wa mtu',
            'ev_absent' => 'Hakuna thamani iliyorekodiwa ({source})',
            'ev_unknown' => 'Rekodi haikusomwa ({source})',
            'ev_present' => 'Iliyorekodiwa: {value}',
            'why_document' => 'Mfumo hauna sehemu ya kutunza hati, hivyo hati iliyowasilishwa haiwezi kuthibitishwa hapa.',
            'why_name_ambiguous' => 'Jina lilijazwa kutoka kwa tahajia zaidi ya moja; mtu lazima athibitishe jina rasmi.',
            'why_location_ambiguous' => 'Mahali palirekodiwa kwa namna zaidi ya moja; mtu lazima athibitishe mahali rasmi.',
            'why_migration' => 'Safu hii haikuja kwa uhamishaji wa kawaida; mtu lazima aiangalie.',
            'sum_ready' => 'Taarifa zote zinazohitajika zimerekodiwa. Uamuzi ni wako.',
            'sum_missing' => 'Taarifa {count} zinazohitajika hazipo.',
            'sum_human' => 'Vitu {count} vinahitaji kuangaliwa na mtu.',
            'lim_documents' => 'Hati zilizowasilishwa hazitunzwi kwenye mfumo; mtu lazima azikague nje ya mfumo.',
            'lim_no_decision' => 'Ukaguzi huu hauthibitishi wala hauondoi uthibitisho; msimamizi aliyeidhinishwa peke yake anaweza.',
            'lim_evidence' => 'Kila kitu hapo juu kinatoka kwenye rekodi halisi za biashara na akaunti; hakuna kinachobuniwa.',
            'lim_read_capped' => 'Rekodi zote hazikusomwa kwa mkupuo mmoja, hivyo vitu vilivyoathirika vinaonyeshwa kama "haijasomwa" badala ya kukisiwa.',
            'decision_human_only' => 'uamuzi ni wa mtu',
        ],
        'en' => [
            'req_name' => 'Business name is recorded',
            'req_type' => 'Business type is set',
            'req_location' => 'Business location is recorded',
            'req_description' => 'Business description is written ({min} characters or more)',
            'req_logo' => 'Business logo is uploaded',
            'req_pinned' => 'Business location is pinned on the map',
            'req_public' => 'Business is visible in the public marketplace',
            'req_staff' => 'An approved admin or seller with a name and a phone number exists',
            'req_products' => 'Business has at least one product listed',
            'req_document' => 'Submitted verification document',
            'req_name_ambiguous' => 'Business name needs confirmation (several spellings recorded)',
            'req_location_ambiguous' => 'Business location needs confirmation (several variants recorded)',
            'req_migration' => 'Business row needs a look (not a plain migrated row)',
            'status_met' => 'Recorded',
            'status_missing' => 'Missing',
            'status_unknown' => 'Not read',
            'status_human' => 'A person must check',
            'sev_required' => 'Required',
            'sev_recommended' => 'Recommended',
            'sev_human_check' => 'Human check',
            'ev_absent' => 'No value recorded ({source})',
            'ev_unknown' => 'The record was not read ({source})',
            'ev_present' => 'Recorded: {value}',
            'why_document' => 'The platform has no document store, so a submitted document cannot be verified here.',
            'why_name_ambiguous' => 'The name was backfilled from more than one spelling; a person must confirm the official name.',
            'why_location_ambiguous' => 'The location was recorded in more than one variant; a person must confirm the official location.',
            'why_migration' => 'This row did not come from a plain migration; a person must look at it.',
            'sum_ready' => 'Every required detail is recorded. The decision is still yours.',
            'sum_missing' => '{count} required detail(s) are missing.',
            'sum_human' => '{count} item(s) must be checked by a person.',
            'lim_documents' => 'Submitted documents are not stored in the platform; a person must review them outside it.',
            'lim_no_decision' => 'This review never certifies or un-certifies - only an authorized administrator can.',
            'lim_evidence' => 'Every line above comes from the recorded business and account rows; nothing is inferred or invented.',
            'lim_read_capped' => 'Not every record could be read in one pass, so the affected items are reported as "not read" instead of guessed.',
            'decision_human_only' => 'human decision only',
        ],
        'fr' => [
            'req_name' => "Le nom de l'entreprise est enregistré",
            'req_type' => "Le type d'entreprise est défini",
            'req_location' => "L'emplacement de l'entreprise est enregistré",
            'req_description' => "La description de l'entreprise est rédigée ({min} caractères ou plus)",
            'req_logo' => "Le logo de l'entreprise est téléversé",
            'req_pinned' => "L'emplacement de l'entreprise est épinglé sur la carte",
            'req_public' => "L'entreprise est visible dans la place de marché publique",
            'req_staff' => 'Un administrateur ou vendeur approuvé avec un nom et un téléphone existe',
            'req_products' => "L'entreprise a au moins un produit listé",
            'req_document' => 'Document de vérification soumis',
            'req_name_ambiguous' => "Le nom de l'entreprise doit être confirmé (plusieurs orthographes enregistrées)",
            'req_location_ambiguous' => "L'emplacement doit être confirmé (plusieurs variantes enregistrées)",
            'req_migration' => "La ligne de l'entreprise doit être examinée (ligne non migrée simplement)",
            'status_met' => 'Enregistré',
            'status_missing' => 'Manquant',
            'status_unknown' => 'Non lu',
            'status_human' => 'Une personne doit vérifier',
            'sev_required' => 'Obligatoire',
            'sev_recommended' => 'Recommandé',
            'sev_human_check' => 'Vérification humaine',
            'ev_absent' => 'Aucune valeur enregistrée ({source})',
            'ev_unknown' => "L'enregistrement n'a pas été lu ({source})",
            'ev_present' => 'Enregistré : {value}',
            'why_document' => "La plateforme ne stocke aucun document : un document soumis ne peut pas être vérifié ici.",
            'why_name_ambiguous' => "Le nom provient de plusieurs orthographes ; une personne doit confirmer le nom officiel.",
            'why_location_ambiguous' => "L'emplacement a plusieurs variantes ; une personne doit confirmer l'emplacement officiel.",
            'why_migration' => "Cette ligne ne vient pas d'une migration simple ; une personne doit l'examiner.",
            'sum_ready' => 'Tous les éléments obligatoires sont enregistrés. La décision reste la vôtre.',
            'sum_missing' => '{count} élément(s) obligatoire(s) manquent.',
            'sum_human' => '{count} élément(s) doivent être vérifiés par une personne.',
            'lim_documents' => 'Les documents soumis ne sont pas stockés sur la plateforme ; une personne doit les examiner en dehors.',
            'lim_no_decision' => "Cette revue ne certifie ni ne décertifie rien - seul un administrateur autorisé peut le faire.",
            'lim_evidence' => "Chaque ligne provient des enregistrements réels de l'entreprise et des comptes ; rien n'est inventé.",
            'lim_read_capped' => "Tous les enregistrements n'ont pas pu être lus en une passe : les éléments concernés sont signalés comme « non lus » plutôt que devinés.",
            'decision_human_only' => 'décision humaine uniquement',
        ],
        'hi' => [
            'req_name' => 'व्यवसाय का नाम दर्ज है',
            'req_type' => 'व्यवसाय का प्रकार सेट है',
            'req_location' => 'व्यवसाय का स्थान दर्ज है',
            'req_description' => 'व्यवसाय का विवरण लिखा गया है ({min} या अधिक अक्षर)',
            'req_logo' => 'व्यवसाय का लोगो अपलोड है',
            'req_pinned' => 'व्यवसाय का स्थान नक्शे पर पिन है',
            'req_public' => 'व्यवसाय सार्वजनिक बाज़ार में दिखाई देता है',
            'req_staff' => 'नाम और फ़ोन वाला स्वीकृत एडमिन या विक्रेता मौजूद है',
            'req_products' => 'व्यवसाय में कम से कम एक उत्पाद सूचीबद्ध है',
            'req_document' => 'जमा किया गया सत्यापन दस्तावेज़',
            'req_name_ambiguous' => 'व्यवसाय का नाम पुष्टि चाहता है (कई वर्तनियाँ दर्ज)',
            'req_location_ambiguous' => 'व्यवसाय का स्थान पुष्टि चाहता है (कई रूप दर्ज)',
            'req_migration' => 'व्यवसाय की पंक्ति को देखना आवश्यक है (सामान्य माइग्रेशन नहीं)',
            'status_met' => 'दर्ज',
            'status_missing' => 'अनुपस्थित',
            'status_unknown' => 'नहीं पढ़ा गया',
            'status_human' => 'व्यक्ति को जाँचना है',
            'sev_required' => 'आवश्यक',
            'sev_recommended' => 'अनुशंसित',
            'sev_human_check' => 'मानव जाँच',
            'ev_absent' => 'कोई मान दर्ज नहीं ({source})',
            'ev_unknown' => 'रिकॉर्ड नहीं पढ़ा गया ({source})',
            'ev_present' => 'दर्ज: {value}',
            'why_document' => 'प्लेटफ़ॉर्म में दस्तावेज़ संग्रह नहीं है, इसलिए जमा दस्तावेज़ यहाँ सत्यापित नहीं हो सकता।',
            'why_name_ambiguous' => 'नाम कई वर्तनियों से भरा गया; व्यक्ति को आधिकारिक नाम की पुष्टि करनी है।',
            'why_location_ambiguous' => 'स्थान कई रूपों में दर्ज है; व्यक्ति को आधिकारिक स्थान की पुष्टि करनी है।',
            'why_migration' => 'यह पंक्ति सामान्य माइग्रेशन से नहीं आई; व्यक्ति को इसे देखना है।',
            'sum_ready' => 'सभी आवश्यक विवरण दर्ज हैं। निर्णय आपका है।',
            'sum_missing' => '{count} आवश्यक विवरण अनुपस्थित हैं।',
            'sum_human' => '{count} चीज़ें व्यक्ति द्वारा जाँची जानी चाहिए।',
            'lim_documents' => 'जमा किए गए दस्तावेज़ प्लेटफ़ॉर्म में संग्रहित नहीं होते; व्यक्ति को बाहर जाँच करनी है।',
            'lim_no_decision' => 'यह समीक्षा किसी को प्रमाणित या अप्रमाणित नहीं करती - केवल अधिकृत एडमिन कर सकता है।',
            'lim_evidence' => 'ऊपर की हर पंक्ति वास्तविक व्यवसाय और खाता रिकॉर्ड से है; कुछ अनुमानित नहीं है।',
            'lim_read_capped' => 'सभी रिकॉर्ड एक बार में नहीं पढ़े जा सके, इसलिए प्रभावित मदें "नहीं पढ़ी गई" के रूप में दिखाई जाती हैं।',
            'decision_human_only' => 'केवल मानव निर्णय',
        ],
        'es' => [
            'req_name' => 'El nombre del negocio está registrado',
            'req_type' => 'El tipo de negocio está definido',
            'req_location' => 'La ubicación del negocio está registrada',
            'req_description' => 'La descripción del negocio está escrita ({min} caracteres o más)',
            'req_logo' => 'El logotipo del negocio está cargado',
            'req_pinned' => 'La ubicación del negocio está fijada en el mapa',
            'req_public' => 'El negocio es visible en el mercado público',
            'req_staff' => 'Existe un administrador o vendedor aprobado con nombre y teléfono',
            'req_products' => 'El negocio tiene al menos un producto listado',
            'req_document' => 'Documento de verificación presentado',
            'req_name_ambiguous' => 'El nombre del negocio necesita confirmación (varias grafías registradas)',
            'req_location_ambiguous' => 'La ubicación necesita confirmación (varias variantes registradas)',
            'req_migration' => 'La fila del negocio necesita revisión (no es una fila migrada simple)',
            'status_met' => 'Registrado',
            'status_missing' => 'Falta',
            'status_unknown' => 'No leído',
            'status_human' => 'Una persona debe revisar',
            'sev_required' => 'Obligatorio',
            'sev_recommended' => 'Recomendado',
            'sev_human_check' => 'Revisión humana',
            'ev_absent' => 'Sin valor registrado ({source})',
            'ev_unknown' => 'El registro no se leyó ({source})',
            'ev_present' => 'Registrado: {value}',
            'why_document' => 'La plataforma no almacena documentos, así que un documento presentado no puede verificarse aquí.',
            'why_name_ambiguous' => 'El nombre proviene de varias grafías; una persona debe confirmar el nombre oficial.',
            'why_location_ambiguous' => 'La ubicación tiene varias variantes; una persona debe confirmar la ubicación oficial.',
            'why_migration' => 'Esta fila no viene de una migración simple; una persona debe revisarla.',
            'sum_ready' => 'Todos los datos obligatorios están registrados. La decisión sigue siendo suya.',
            'sum_missing' => 'Faltan {count} dato(s) obligatorio(s).',
            'sum_human' => '{count} elemento(s) deben ser revisados por una persona.',
            'lim_documents' => 'Los documentos presentados no se guardan en la plataforma; una persona debe revisarlos fuera.',
            'lim_no_decision' => 'Esta revisión nunca certifica ni descertifica: solo un administrador autorizado puede.',
            'lim_evidence' => 'Cada línea proviene de los registros reales del negocio y las cuentas; nada se inventa.',
            'lim_read_capped' => 'No se pudieron leer todos los registros en una pasada, así que los elementos afectados se muestran como "no leído" en lugar de suponerse.',
            'decision_human_only' => 'solo decisión humana',
        ],
        'ur' => [
            'req_name' => 'کاروبار کا نام درج ہے',
            'req_type' => 'کاروبار کی قسم مقرر ہے',
            'req_location' => 'کاروبار کی جگہ درج ہے',
            'req_description' => 'کاروبار کی تفصیل لکھی گئی ہے ({min} یا زیادہ حروف)',
            'req_logo' => 'کاروبار کا لوگو اپلوڈ ہے',
            'req_pinned' => 'کاروبار کی جگہ نقشے پر پن ہے',
            'req_public' => 'کاروبار عوامی مارکیٹ میں نظر آتا ہے',
            'req_staff' => 'نام اور فون کے ساتھ منظور شدہ ایڈمن یا سیلر موجود ہے',
            'req_products' => 'کاروبار میں کم از کم ایک پروڈکٹ درج ہے',
            'req_document' => 'جمع کرایا گیا تصدیقی دستاویز',
            'req_name_ambiguous' => 'کاروبار کے نام کی تصدیق درکار ہے (کئی ہجے درج)',
            'req_location_ambiguous' => 'کاروبار کی جگہ کی تصدیق درکار ہے (کئی شکلیں درج)',
            'req_migration' => 'کاروبار کی قطار کا جائزہ درکار ہے (سادہ مائیگریشن نہیں)',
            'status_met' => 'درج',
            'status_missing' => 'غیر موجود',
            'status_unknown' => 'نہیں پڑھا گیا',
            'status_human' => 'کسی شخص کو جانچنا ہے',
            'sev_required' => 'لازمی',
            'sev_recommended' => 'تجویز کردہ',
            'sev_human_check' => 'انسانی جانچ',
            'ev_absent' => 'کوئی قدر درج نہیں ({source})',
            'ev_unknown' => 'ریکارڈ نہیں پڑھا گیا ({source})',
            'ev_present' => 'درج: {value}',
            'why_document' => 'پلیٹ فارم میں دستاویزات کا ذخیرہ نہیں، اس لیے جمع دستاویز یہاں تصدیق نہیں ہو سکتی۔',
            'why_name_ambiguous' => 'نام کئی ہجوں سے بھرا گیا؛ کسی شخص کو سرکاری نام کی تصدیق کرنی ہے۔',
            'why_location_ambiguous' => 'جگہ کئی شکلوں میں درج ہے؛ کسی شخص کو سرکاری جگہ کی تصدیق کرنی ہے۔',
            'why_migration' => 'یہ قطار سادہ مائیگریشن سے نہیں آئی؛ کسی شخص کو دیکھنا ہے۔',
            'sum_ready' => 'تمام لازمی تفصیلات درج ہیں۔ فیصلہ آپ کا ہے۔',
            'sum_missing' => '{count} لازمی تفصیلات غیر موجود ہیں۔',
            'sum_human' => '{count} چیزیں کسی شخص کی جانچ چاہتی ہیں۔',
            'lim_documents' => 'جمع دستاویزات پلیٹ فارم میں محفوظ نہیں ہوتے؛ کسی شخص کو باہر جانچنا ہے۔',
            'lim_no_decision' => 'یہ جائزہ کسی کو تصدیق یا غیر تصدیق نہیں کرتا - صرف مجاز ایڈمن کر سکتا ہے۔',
            'lim_evidence' => 'اوپر کی ہر سطر اصل کاروبار اور اکاؤنٹ ریکارڈ سے ہے؛ کچھ بھی بناوٹی نہیں۔',
            'lim_read_capped' => 'تمام ریکارڈ ایک بار میں نہیں پڑھے جا سکے، اس لیے متاثرہ چیزیں "نہیں پڑھا گیا" کے طور پر دکھائی جاتی ہیں۔',
            'decision_human_only' => 'فیصلہ صرف انسان کا',
        ],
        'de' => [
            'req_name' => 'Firmenname ist erfasst',
            'req_type' => 'Firmentyp ist gesetzt',
            'req_location' => 'Firmenstandort ist erfasst',
            'req_description' => 'Firmenbeschreibung ist geschrieben ({min} Zeichen oder mehr)',
            'req_logo' => 'Firmenlogo ist hochgeladen',
            'req_pinned' => 'Firmenstandort ist auf der Karte markiert',
            'req_public' => 'Das Unternehmen ist im öffentlichen Marktplatz sichtbar',
            'req_staff' => 'Ein freigegebener Admin oder Verkäufer mit Name und Telefon existiert',
            'req_products' => 'Das Unternehmen hat mindestens ein gelistetes Produkt',
            'req_document' => 'Eingereichtes Prüfdokument',
            'req_name_ambiguous' => 'Der Firmenname muss bestätigt werden (mehrere Schreibweisen erfasst)',
            'req_location_ambiguous' => 'Der Standort muss bestätigt werden (mehrere Varianten erfasst)',
            'req_migration' => 'Die Firmenzeile muss geprüft werden (keine einfache migrierte Zeile)',
            'status_met' => 'Erfasst',
            'status_missing' => 'Fehlt',
            'status_unknown' => 'Nicht gelesen',
            'status_human' => 'Eine Person muss prüfen',
            'sev_required' => 'Erforderlich',
            'sev_recommended' => 'Empfohlen',
            'sev_human_check' => 'Menschliche Prüfung',
            'ev_absent' => 'Kein Wert erfasst ({source})',
            'ev_unknown' => 'Der Datensatz wurde nicht gelesen ({source})',
            'ev_present' => 'Erfasst: {value}',
            'why_document' => 'Die Plattform speichert keine Dokumente, daher kann ein eingereichtes Dokument hier nicht geprüft werden.',
            'why_name_ambiguous' => 'Der Name stammt aus mehreren Schreibweisen; eine Person muss den offiziellen Namen bestätigen.',
            'why_location_ambiguous' => 'Der Standort hat mehrere Varianten; eine Person muss den offiziellen Standort bestätigen.',
            'why_migration' => 'Diese Zeile stammt nicht aus einer einfachen Migration; eine Person muss sie prüfen.',
            'sum_ready' => 'Alle erforderlichen Angaben sind erfasst. Die Entscheidung bleibt bei Ihnen.',
            'sum_missing' => '{count} erforderliche Angabe(n) fehlen.',
            'sum_human' => '{count} Punkt(e) müssen von einer Person geprüft werden.',
            'lim_documents' => 'Eingereichte Dokumente werden nicht auf der Plattform gespeichert; eine Person muss sie außerhalb prüfen.',
            'lim_no_decision' => 'Diese Prüfung zertifiziert oder entzertifiziert nichts – nur ein autorisierter Admin kann das.',
            'lim_evidence' => 'Jede Zeile stammt aus den erfassten Firmen- und Kontodatensätzen; nichts wird erfunden.',
            'lim_read_capped' => 'Nicht alle Datensätze konnten in einem Durchgang gelesen werden; betroffene Punkte werden als „nicht gelesen“ gemeldet statt geraten.',
            'decision_human_only' => 'nur menschliche Entscheidung',
        ],
        'zh' => [
            'req_name' => '已记录企业名称',
            'req_type' => '已设置企业类型',
            'req_location' => '已记录企业地点',
            'req_description' => '已撰写企业简介（{min} 个字符以上）',
            'req_logo' => '已上传企业标志',
            'req_pinned' => '企业地点已在地图上标注',
            'req_public' => '企业在公开市场中可见',
            'req_staff' => '存在有姓名和电话的已批准管理员或卖家',
            'req_products' => '企业至少列出一个商品',
            'req_document' => '已提交的验证文件',
            'req_name_ambiguous' => '企业名称需确认（记录了多个拼写）',
            'req_location_ambiguous' => '企业地点需确认（记录了多个版本）',
            'req_migration' => '企业记录需人工查看（非普通迁移记录）',
            'status_met' => '已记录',
            'status_missing' => '缺失',
            'status_unknown' => '未读取',
            'status_human' => '需人工核查',
            'sev_required' => '必需',
            'sev_recommended' => '建议',
            'sev_human_check' => '人工核查',
            'ev_absent' => '未记录任何值（{source}）',
            'ev_unknown' => '记录未被读取（{source}）',
            'ev_present' => '已记录：{value}',
            'why_document' => '平台没有文件存储，因此无法在此核实已提交的文件。',
            'why_name_ambiguous' => '名称来自多个拼写；需人工确认正式名称。',
            'why_location_ambiguous' => '地点记录了多个版本；需人工确认正式地点。',
            'why_migration' => '该记录并非来自普通迁移；需人工查看。',
            'sum_ready' => '所有必需信息均已记录。决定权仍在您手中。',
            'sum_missing' => '缺少 {count} 项必需信息。',
            'sum_human' => '{count} 项需人工核查。',
            'lim_documents' => '已提交的文件不存储在平台上；需人工在平台外核查。',
            'lim_no_decision' => '本审查不会认证或取消认证——只有获授权的管理员可以。',
            'lim_evidence' => '以上每一行都来自真实的企业和账户记录，绝无编造。',
            'lim_read_capped' => '未能一次读取全部记录，因此受影响的条目显示为“未读取”，而非猜测。',
            'decision_human_only' => '仅由人工决定',
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

    /** Every locale carries exactly the same keys (used by the unit test). */
    public static function messagesComplete(): bool
    {
        $english = array_keys(self::MESSAGES['en']);

        foreach (self::MESSAGES as $table) {
            $keys = array_keys($table);
            sort($keys);
            $expected = $english;
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

    /** The requirement ids this reviewer can report. */
    public static function requirementIds(): array
    {
        return array_column(self::REQUIREMENTS, 'id');
    }

    /** The placeholders used by one message key. */
    public static function placeholders(string $key, string $locale): array
    {
        preg_match_all('/\{([a-z_]+)\}/', self::msg($key, $locale), $matches);

        return $matches[1] ?? [];
    }

    /**
     * Review one business against the documented requirements.
     *
     * @param  array{
     *     locale: string,
     *     business: array<string, mixed>|null,
     *     members?: array<int, array<string, mixed>>,
     *     product_count?: int|null,
     *     requirements?: array<int, array<string, mixed>>|null
     * }  $ctx
     * @return array<string, mixed>
     */
    public static function review(array $ctx): array
    {
        $locale = (string) ($ctx['locale'] ?? 'en');
        $business = is_array($ctx['business'] ?? null) ? $ctx['business'] : null;
        $members = array_values(array_filter((array) ($ctx['members'] ?? []), 'is_array'));
        // When the member read was capped (or failed), the staff requirement is
        // "not read" - it must never be reported as missing.
        $membersRead = ! array_key_exists('members_read', $ctx) || $ctx['members_read'] !== false;
        $readsCapped = (bool) ($ctx['reads_capped'] ?? false);
        $productCount = array_key_exists('product_count', $ctx) && $ctx['product_count'] !== null
            ? max(0, (int) $ctx['product_count'])
            : null;

        $requirements = self::normaliseRequirements($ctx['requirements'] ?? null);

        $lines = [];
        $missing = [];
        $humanChecks = [];
        $requiredTotal = 0;
        $requiredMet = 0;
        $recommendedTotal = 0;
        $recommendedMet = 0;

        foreach ($requirements as $requirement) {
            $line = self::judge($requirement, $business, $members, $productCount, $locale, $membersRead);
            $line['severity_label'] = self::msg('sev_'.$line['severity'], $locale);
            $line['status_label'] = self::msg('status_'.$line['status'], $locale);
            $lines[] = $line;

            if ($line['severity'] === 'required') {
                $requiredTotal++;
                if ($line['status'] === self::STATUS_MET) {
                    $requiredMet++;
                } elseif ($line['status'] === self::STATUS_MISSING) {
                    $missing[] = $line['id'];
                }
            } elseif ($line['severity'] === 'recommended') {
                $recommendedTotal++;
                if ($line['status'] === self::STATUS_MET) {
                    $recommendedMet++;
                }
            }

            if ($line['status'] === self::STATUS_HUMAN) {
                $humanChecks[] = ['id' => $line['id'], 'label' => $line['label'], 'why' => $line['why']];
            } elseif ($line['status'] === self::STATUS_UNKNOWN && $line['severity'] === 'required') {
                // A record that could not be read is never reported as met; it
                // also cannot be called missing, so it is listed for a human.
                $humanChecks[] = ['id' => $line['id'], 'label' => $line['label'], 'why' => $line['why']];
            }
        }

        $summary = [];
        if ($requiredTotal > 0 && $requiredMet === $requiredTotal) {
            $summary[] = self::msg('sum_ready', $locale);
        } elseif ($missing !== []) {
            $summary[] = self::msg('sum_missing', $locale, ['count' => count($missing)]);
        }
        if ($humanChecks !== []) {
            $summary[] = self::msg('sum_human', $locale, ['count' => count($humanChecks)]);
        }

        $limitations = [
            self::msg('lim_documents', $locale),
            self::msg('lim_no_decision', $locale),
            self::msg('lim_evidence', $locale),
        ];
        if ($readsCapped) {
            array_unshift($limitations, self::msg('lim_read_capped', $locale));
        }

        return [
            'requirements' => $lines,
            'missing' => $missing,
            'human_checks' => $humanChecks,
            'counts' => [
                'required_total' => $requiredTotal,
                'required_met' => $requiredMet,
                'recommended_total' => $recommendedTotal,
                'recommended_met' => $recommendedMet,
                'missing' => count($missing),
                'human_checks' => count($humanChecks),
            ],
            'completeness' => $requiredTotal > 0 ? (int) round(($requiredMet / $requiredTotal) * 100) : 0,
            'summary' => $summary,
            // Never an approval: the reviewer only reports what is recorded.
            'decision' => 'human_only',
            'decision_label' => self::msg('decision_human_only', $locale),
            'sources' => self::sources($lines),
            'limitations' => $limitations,
        ];
    }

    /**
     * One requirement's status, evidence and (for human items) the reason.
     *
     * @param  array<string, mixed>  $requirement
     * @param  array<string, mixed>|null  $business
     * @param  array<int, array<string, mixed>>  $members
     * @return array{id: string, label: string, severity: string, status: string, rule: string, source: string, evidence: string, why: ?string}
     */
    private static function judge(array $requirement, ?array $business, array $members, ?int $productCount, string $locale, bool $membersRead = true): array
    {
        $id = (string) $requirement['id'];
        $rule = (string) $requirement['rule'];
        $severity = (string) $requirement['severity'];
        $source = (string) $requirement['source'];

        $status = self::STATUS_UNKNOWN;
        $evidence = self::msg('ev_unknown', $locale, ['source' => $source]);
        $why = null;

        if ($rule === 'document') {
            // There is no document store: a document can never be confirmed here,
            // whatever the client claims.
            $status = self::STATUS_HUMAN;
            $why = self::msg('why_document', $locale);
            $evidence = $source;
        } elseif ($business === null) {
            $status = self::STATUS_UNKNOWN;
            $evidence = self::msg('ev_unknown', $locale, ['source' => $source]);
        } else {
            switch ($rule) {
                case 'text':
                    $value = trim((string) ($business[self::column($id)] ?? ''));
                    $min = (int) ($requirement['min_chars'] ?? 0);
                    if ($value !== '' && mb_strlen($value) >= $min) {
                        $status = self::STATUS_MET;
                        $evidence = self::msg('ev_present', $locale, ['value' => self::clip($value)]);
                    } else {
                        $status = self::STATUS_MISSING;
                        $evidence = self::msg('ev_absent', $locale, ['source' => $source]);
                    }
                    break;

                case 'pair':
                    $lat = $business['business_latitude'] ?? null;
                    $lng = $business['business_longitude'] ?? null;
                    $pinned = $lat !== null && $lat !== '' && $lng !== null && $lng !== '';
                    $status = $pinned ? self::STATUS_MET : self::STATUS_MISSING;
                    $evidence = $pinned
                        ? self::msg('ev_present', $locale, ['value' => self::clip($lat.', '.$lng)])
                        : self::msg('ev_absent', $locale, ['source' => $source]);
                    break;

                case 'flag':
                    $value = $business['is_public'] ?? null;
                    if ($value === null || $value === '') {
                        $status = self::STATUS_UNKNOWN;
                        $evidence = self::msg('ev_unknown', $locale, ['source' => $source]);
                    } else {
                        $isPublic = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        $status = $isPublic === true ? self::STATUS_MET : self::STATUS_MISSING;
                        $evidence = self::msg('ev_present', $locale, ['value' => $isPublic === true ? 'true' : 'false']);
                    }
                    break;

                case 'staff':
                    if (! $membersRead) {
                        // The member read was capped: unknown, never "missing".
                        $status = self::STATUS_UNKNOWN;
                        $evidence = self::msg('ev_unknown', $locale, ['source' => $source]);
                        break;
                    }

                    $approved = [];
                    foreach ($members as $member) {
                        $role = (string) ($member['role'] ?? '');
                        $memberStatus = (string) ($member['status'] ?? '');
                        $hasName = trim((string) ($member['full_name'] ?? '')) !== '';
                        $hasPhone = trim((string) ($member['phone'] ?? '')) !== '';
                        if (in_array($role, ['admin', 'seller'], true)
                            && $memberStatus === 'approved'
                            && $hasName
                            && $hasPhone) {
                            $approved[] = $member;
                        }
                    }
                    $status = $approved !== [] ? self::STATUS_MET : self::STATUS_MISSING;
                    $evidence = $approved !== []
                        ? self::msg('ev_present', $locale, ['value' => self::clip(count($approved).' x '.(string) ($approved[0]['role'] ?? ''))])
                        : self::msg('ev_absent', $locale, ['source' => $source]);
                    break;

                case 'products':
                    if ($productCount === null) {
                        $status = self::STATUS_UNKNOWN;
                        $evidence = self::msg('ev_unknown', $locale, ['source' => $source]);
                    } else {
                        $status = $productCount > 0 ? self::STATUS_MET : self::STATUS_MISSING;
                        $evidence = self::msg('ev_present', $locale, ['value' => (string) $productCount]);
                    }
                    break;

                case 'ambiguity_name':
                    $spellings = self::spellings($business);
                    if (count($spellings) > 1) {
                        $status = self::STATUS_HUMAN;
                        $why = self::msg('why_name_ambiguous', $locale);
                        $evidence = self::msg('ev_present', $locale, ['value' => self::clip(implode(' | ', $spellings))]);
                    } else {
                        $status = self::STATUS_MET;
                        $evidence = self::msg('ev_present', $locale, ['value' => '1']);
                    }
                    break;

                case 'ambiguity_location':
                    $variants = self::locationVariants($business);
                    if (count($variants) > 1) {
                        $status = self::STATUS_HUMAN;
                        $why = self::msg('why_location_ambiguous', $locale);
                        $evidence = self::msg('ev_present', $locale, ['value' => self::clip(implode(' | ', $variants))]);
                    } else {
                        $status = self::STATUS_MET;
                        $evidence = self::msg('ev_present', $locale, ['value' => '1']);
                    }
                    break;

                case 'migration':
                    $migration = strtolower(trim((string) ($business['migration_status'] ?? '')));
                    if ($migration === 'active') {
                        $status = self::STATUS_MET;
                        $evidence = self::msg('ev_present', $locale, ['value' => 'active']);
                    } else {
                        $status = self::STATUS_HUMAN;
                        $why = self::msg('why_migration', $locale);
                        $evidence = $migration !== ''
                            ? self::msg('ev_present', $locale, ['value' => $migration])
                            : self::msg('ev_absent', $locale, ['source' => $source]);
                    }
                    break;

                default:
                    $status = self::STATUS_UNKNOWN;
                    $evidence = self::msg('ev_unknown', $locale, ['source' => $source]);
            }
        }

        return [
            'id' => $id,
            // min_chars is filled in when the label needs it; harmless otherwise.
            'label' => self::msg('req_'.$id, $locale, ['min' => (int) ($requirement['min_chars'] ?? self::MIN_DESCRIPTION_CHARS)]),
            'severity' => $severity,
            'status' => $status,
            'rule' => $rule,
            'source' => $source,
            'evidence' => $evidence,
            'why' => $why,
        ];
    }

    /**
     * The operator's requirement list if they supplied one, otherwise the
     * documented built-in set. Unknown rules/severities are dropped rather than
     * guessed, so a mis-configured list can never fake a status.
     *
     * @param  mixed  $override
     * @return array<int, array<string, mixed>>
     */
    private static function normaliseRequirements($override): array
    {
        if (! is_array($override) || $override === []) {
            return self::REQUIREMENTS;
        }

        $clean = [];
        foreach ($override as $requirement) {
            if (! is_array($requirement)) {
                continue;
            }
            $id = (string) ($requirement['id'] ?? '');
            $rule = (string) ($requirement['rule'] ?? '');
            $severity = (string) ($requirement['severity'] ?? '');
            if ($id === '' || ! in_array($severity, self::SEVERITIES, true)) {
                continue;
            }
            if (! in_array($rule, ['text', 'pair', 'flag', 'staff', 'products', 'document', 'ambiguity_name', 'ambiguity_location', 'migration'], true)) {
                continue;
            }
            $requirement['id'] = $id;
            $requirement['rule'] = $rule;
            $requirement['severity'] = $severity;
            $requirement['source'] = (string) ($requirement['source'] ?? '');
            $clean[] = $requirement;
        }

        return $clean === [] ? self::REQUIREMENTS : $clean;
    }

    /** The rule-key -> column map for the simple text requirements. */
    private static function column(string $id): string
    {
        $map = [
            'name' => 'business_name',
            'type' => 'business_type',
            'location' => 'business_location',
            'description' => 'business_description',
            'logo' => 'business_logo_url',
        ];

        return $map[$id] ?? $id;
    }

    /**
     * The backfill provenance stored on the row (`merged_from`), decoded whether
     * the backend returned it as an array or as a JSON string.
     *
     * @param  array<string, mixed>  $business
     * @return array<string, mixed>
     */
    private static function provenance(array $business): array
    {
        $raw = $business['merged_from'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * The recorded raw spellings of the business name. Empty when the row has no
     * provenance, or when it has exactly one spelling.
     *
     * @param  array<string, mixed>  $business
     * @return array<int, string>
     */
    public static function spellings(array $business): array
    {
        $raw = self::provenance($business)['raw_spellings'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($s): string => trim((string) $s),
            $raw
        ), static fn (string $s): bool => $s !== '')));
    }

    /**
     * The recorded location variants of the business (provenance only).
     *
     * @param  array<string, mixed>  $business
     * @return array<int, string>
     */
    public static function locationVariants(array $business): array
    {
        $raw = self::provenance($business)['location_variants'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($s): string => trim((string) $s),
            $raw
        ), static fn (string $s): bool => $s !== '')));
    }

    /** Every distinct source named by the reviewed lines. */
    private static function sources(array $lines): array
    {
        $sources = [];
        foreach ($lines as $line) {
            $source = trim((string) ($line['source'] ?? ''));
            if ($source !== '' && ! in_array($source, $sources, true)) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    /** Trim an evidence value to something readable in a checklist. */
    private static function clip(string $value, int $limit = 160): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).'…' : $value;
    }
}
