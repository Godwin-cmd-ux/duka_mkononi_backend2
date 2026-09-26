const fs = require('fs');
const path = require('path');

// Base Swahili locale data - covering ALL screens in the app
const sw = {
  app: {
    title: 'DUKANI APP',
    version: 'Toleo 1.0.0',
    badili_lugha: 'Badili Lugha',
    tuma: 'Tuma',
    funga: 'Funga',
    loading: 'Inapakia...',
    error: 'Hitilafu',
    success: 'Mafanikio',
    cancel: 'Ghairi',
    confirm: 'Thibitisha',
    save: 'Hifadhi',
    delete: 'Futa',
    edit: 'Hariri',
    search: 'Tafuta',
    back: 'Nyuma',
    next: 'Endelea',
    submit: 'Wasilisha',
    close: 'Funga',
    ok: 'Sawa',
    no_data: 'Hakuna data',
    refresh: 'Fresha',
    retry: 'Jaribu tena'
  },
  navigation: {
    home: 'Nyumbani',
    about: 'Kuhusu',
    contact: 'Mawasiliano',
    settings: 'Mipangilio',
    profile: 'Wasifu',
    logout: 'Toka',
    dashboard: 'Dashbodi',
    products: 'Bidhaa',
    sales: 'Mauzo',
    reports: 'Ripoti',
    notifications: 'Arifa',
    payments: 'Malipo',
    customers: 'Wateja',
    adverts: 'Matangazo'
  },
  home: {
    welcome: 'KARIBU DUKANI',
    subtitle: 'Chagua nafasi yako kuanza',
    get_started: 'Anza Sasa',
    learn_more: 'Jifunze Zaidi'
  },
  buttons: {
    mteja: 'MTEJA',
    muuzaji: 'MUUZAJI',
    msimamizi: 'MSIMAMIZI',
    washa: 'WASHA',
    mteja_description: 'Ninaomba huduma au bidhaa',
    muuzaji_description: 'Ninauzia bidhaa na huduma',
    msimamizi_description: 'Ninafanya usimamizi wa duka',
    washa_description: 'Ninafanya malipo ya haraka'
  },
  user_roles: {
    mteja: 'Mteja',
    muuzaji: 'Muuzaji',
    msimamizi: 'Msimamizi',
    washa: 'Washa',
    admin: 'Msimamizi',
    seller: 'Muuzaji',
    customer: 'Mteja',
    system_admin: 'Msimamizi Mkuu'
  },
  instructions: {
    title: 'Jinsi ya Kuanza',
    step1: '1. Chagua nafasi yako kutoka hapo juu',
    step2: '2. Jisajili au ingia kwenye akaunti yako',
    step3: '3. Anza kutumia huduma zetu'
  },
  security: {
    title: 'ANGALIZO LA USALAMA',
    warning: 'Tunakusihi kuhakiki biashara kabla hujafanya miamala ili kuepuka matapeli mtandaoni.',
    point1: 'Hakikisha biashara imesajiliwa kikamilifu',
    point2: 'Thibitisha anwani na mawasiliano ya biashara',
    point3: 'Zingatia maoni ya wateja waliopita',
    point4: 'Epuka kutoa malipo bila kuhakiki'
  },
  login: {
    title: 'INGIA',
    welcome_back: 'Karibu Tena!',
    email_label: 'Barua Pepe',
    email_placeholder: 'example@email.com',
    password_label: 'Nenosiri',
    password_placeholder: '......',
    remember_me: 'Nikumbuke',
    forgot_password: 'Umesahau nenosiri?',
    no_account: 'Huna akaunti?',
    signup_as_client: 'Jisajili kama Mteja',
    signup_as_seller: 'Jisajili kama Muuzaji',
    signup_as_admin: 'Jisajili kama Msimamizi',
    signup_here: 'Jisajili hapa',
    security_note: 'Taarifa zako zinalindwa kwa usalama wa juu',
    success_title: 'Mafanikio!',
    success_message: 'Karibu {name}! Umefanikiwa kuingia.',
    continue: 'Endelea',
    error_title: 'Hitilafu',
    error_general: 'Hitilafu imetokea',
    error_server: 'Hitilafu ya server: {status}',
    error_response: 'Jibu lisilo sahihi kutoka kwa server',
    error_email_required: 'Barua pepe inahitajika',
    error_email_invalid: 'Andika barua pepe sahihi',
    error_password_required: 'Nenosiri linahitajika',
    error_password_length: 'Nenosiri lazima liwe na herufi 6 au zaidi',
    error_login: 'Hitilafu imetokea wakati wa kuingia',
    error_invalid_credentials: 'Barua pepe au nenosiri si sahihi.',
    error_network: 'Hitilafu ya mtandao. Hakikisha umeunganishwa kwenye internet.',
    error_try_again: 'Hitilafu imetokea. Tafadhali jaribu tena.',
    account_pending: 'Akaunti yako bado haijaidhinishwa. Subiri msimamizi akuidhinishe.',
    account_rejected: 'Akaunti yako imekataliwa. Tafadhali wasiliana na msimamizi.',
    account_not_approved: 'Akaunti yako haijaidhinishwa.',
    account_not_approved_title: 'Akaunti Haijaidhinishwa'
  },
  client_signup: {
    title: 'JISAJILI KAMA MTEJA',
    subtitle: 'Unda akaunti yako ya Mteja',
    full_name: 'Jina Kamili *',
    full_name_placeholder: 'Andika jina lako kamili',
    email_label: 'Barua Pepe *',
    email_placeholder: 'example@email.com',
    phone_label: 'Namba ya Simu (Si lazima)',
    phone_placeholder: '07XXXXXXXX',
    password_label: 'Nenosiri *',
    password_placeholder: 'Andika nenosiri lako (herufi 6 au zaidi)',
    confirm_password_label: 'Rudia Nenosiri *',
    confirm_password_placeholder: 'Andika nenosiri tena',
    signup_button: 'JISAJILI SASA',
    terms: 'Kwa kubonyeza Jisajili Sasa, unakubali Sheria na Masharti yetu',
    back_to_login: 'Rudi kwenye Ukurasa wa Kuingia',
    footer_title: 'Akaunti ya Mteja inakuruhusu:',
    footer_point1: 'Kuangalia matangazo ya bidhaa',
    footer_point2: 'Kuwasiliana na wauzaji',
    footer_point3: 'Kupata huduma kwa urahisi',
    error_required_fields: 'Tafadhali jaza sehemu zote zinazohitajika',
    error_password_mismatch: 'Nenosiri hazifanani',
    error_password_length: 'Nenosiri lazima liwe na herufi 6 au zaidi',
    error_invalid_email: 'Tafadhali ingiza barua pepe sahihi',
    error_general: 'Hitilafu imetokea wakati wa kujisajili',
    error_account_exists: 'Akaunti na barua pepe hii tayari ipo.',
    success_title: 'Mafanikio!',
    success_message: 'Akaunti ya Mteja imeundwa kikamilifu! Sasa unaweza kuingia.',
    success_button: 'Ingia Sasa'
  },
  seller_signup: {
    title: 'JISAJILI KAMA MUUZAJI',
    subtitle: 'Inamaanisha sehemu inayohitajika',
    full_name_placeholder: 'Jina Kamili *',
    business_name_placeholder: 'Jina la Biashara *',
    business_location_placeholder: 'Mahali pa Biashara *',
    email_placeholder: 'Barua Pepe *',
    phone_placeholder: 'Namba ya Simu',
    password_placeholder: 'Nenosiri *',
    confirm_password_placeholder: 'Rudia Nenosiri *',
    signup_button: 'JISAJILI SASA',
    back_button: 'RUDI NYUMA',
    info_title: 'Maelezo muhimu kwa Muuzaji:',
    info_point1: 'Jina la biashara lazima liwe tayari kwenye mfumo',
    info_point2: 'Biashara lazima iwe na msimamizi aliyethibitishwa',
    info_point3: 'Akaunti yako itahitaji uthibitisho wa msimamizi',
    info_point4: 'Utapokea taarifa ukiapidhinishwa',
    error_required: 'Tafadhali jaza sehemu zote required',
    error_password_mismatch: 'Nenosiri hazifanani',
    error_invalid_email: 'Tafadhali ingiza barua pepe sahihi',
    error_business_check: 'Hitilafu katika ukaguzi wa biashara',
    error_business_not_found: 'Biashara {name} haipo kwenye mfumo.',
    error_business_no_admin: 'Biashara {name} haina msimamizi.',
    error_general: 'Hitilafu imetokea wakati wa kujisajili',
    error_email_taken: 'Barua pepe hii tayari imetumika.',
    error_network: 'Hakuna muunganisho wa mtandao.',
    success_title: 'Mafanikio',
    success_message: 'Akaunti ya Muuzaji imeundwa kikamilifu!',
    success_button: 'Sawa',
    business_not_found_title: 'Biashara Haipo',
    business_no_admin_title: 'Biashara Haina Msimamizi'
  },
  admin_signup: {
    title: 'JISAJILI KAMA MSIMAMIZI',
    subtitle: 'Inamaanisha sehemu inayohitajika',
    full_name_placeholder: 'Jina Kamili *',
    business_name_placeholder: 'Jina la Biashara *',
    business_location_placeholder: 'Mahali pa Biashara *',
    email_placeholder: 'Barua Pepe *',
    phone_placeholder: 'Namba ya Simu',
    admin_code_placeholder: 'Admin Code *',
    password_placeholder: 'Nenosiri *',
    confirm_password_placeholder: 'Rudia Nenosiri *',
    signup_button: 'JISAJILI SASA',
    back_button: 'RUDI NYUMA',
    info_title: 'Maelezo muhimu kwa Msimamizi:',
    info_point1: 'Jina la biashara lazima liwe la kipekee',
    info_point2: 'Utakuwa na mamlaka kamili ya kuidhinisha',
    info_point3: 'Unaweza kuanza kutumia mfumo mara moja',
    info_point4: 'Admin Code: ADMIN2024',
    error_required: 'Tafadhali jaza sehemu zote required',
    error_password_mismatch: 'Nenosiri hazifanani',
    error_invalid_email: 'Tafadhali ingiza barua pepe sahihi',
    error_wrong_admin_code: 'Admin code si sahihi',
    error_network: 'Hakuna muunganisho wa mtandao.',
    success_title: 'Mafanikio',
    success_message: 'Akaunti ya Msimamizi imeundwa kikamilifu!',
    success_button: 'Ingia Sasa'
  },
  forgot_password: {
    title: 'Badilisha Nenosiri',
    email_label: 'Barua Pepe',
    email_placeholder: 'example@email.com',
    send_code: 'Tuma Msimbo',
    code_label: 'Msimbo (6 tarakimu)',
    code_placeholder: 'Weka msimbo uliopokea',
    verify_code: 'Thibitisha Msimbo',
    new_password_label: 'Nenosiri Jipya',
    new_password_placeholder: 'Weka nenosiri jipya',
    confirm_password_label: 'Rudia Nenosiri',
    confirm_password_placeholder: 'Andika nenosiri tena',
    reset_button: 'Badilisha Nenosiri',
    resend_code: 'Tuma tena msimbo',
    success_message: 'Nenosiri limebadilishwa kikamilifu!',
    success_button: 'Ingia Sasa',
    error_required: 'Tafadhali jaza barua pepe yako',
    error_invalid_email: 'Tafadhali ingiza barua pepe sahihi',
    error_code_required: 'Tafadhali weka msimbo',
    error_code_invalid: 'Msimbo si sahihi',
    error_password_required: 'Tafadhali weka nenosiri jipya',
    error_password_mismatch: 'Nenosiri hazifanani',
    error_password_length: 'Nenosiri lazima liwe na herufi 6 au zaidi',
    error_network: 'Hitilafu ya mtandao.'
  },
  payment: {
    title: 'Malipo',
    header: 'Malipo',
    amount: 'Kiasi cha Kulipa',
    currency: 'TZS',
    amount_placeholder: 'Weka kiasi',
    phone_number: 'Namba ya Simu',
    country_code: '+255',
    phone_placeholder: 'Weka namba ya simu',
    choose_method: 'Chagua Njia ya Malipo',
    payment_info: 'Taarifa za Malipo',
    continue_payment: 'Endelea na Malipo',
    security_notice: 'Malipo yako yanashughulikiwa kwa usalama.',
    error_fill_fields: 'Tafadhali jaza sehemu zote',
    confirm_title: 'Thibitisha Malipo',
    confirm_message: 'Thibitisha malipo ya {amount} TZS kwa {phone}?',
    success_title: 'Mafanikio',
    success_message: 'Malipo yamefanikiwa!'
  },
  payment_methods: {
    mpesa: 'M-Pesa',
    tigo_pesa: 'Tigo Pesa',
    airtel_money: 'Airtel Money',
    halotel_pesa: 'Halotel Pesa',
    bank_card: 'Kadi ya Benki',
    ezy_pesa: 'Ezy Pesa'
  },
  language: {
    sw: 'Kiswahili',
    en: 'English',
    fr: 'Francais',
    hi: 'Hindi',
    es: 'Espanol',
    ur: 'Urdu'
  },
  footer: {
    security_note: 'Tunaendesha usalama wa juu kwa miamala yako.'
  },
  admin_dashboard: {
    title: 'Dashbodi ya Msimamizi',
    total_products: 'Jumla ya Bidhaa',
    total_sales: 'Jumla ya Mauzo',
    total_customers: 'Jumla ya Wateja',
    total_revenue: 'Jumla ya Mapato',
    today_sales: 'Mauzo ya Leo',
    recent_activities: 'Shughuli za Hivi Karibuni',
    view_all: 'Angalia Zote',
    no_activities: 'Hakuna shughuli'
  },
  products: {
    title: 'Bidhaa',
    new_product: 'Bidhaa Mpya',
    edit_product: 'Hariri Bidhaa',
    product_name: 'Jina la Bidhaa',
    product_name_placeholder: 'Weka jina la bidhaa',
    product_description: 'Maelezo ya Bidhaa',
    product_description_placeholder: 'Elezea bidhaa kwa kina...',
    product_price: 'Bei ya Bidhaa',
    current_price: 'Bei ya sasa',
    desired_price: 'Bei unayotaka',
    product_category: 'Aina ya Bidhaa',
    product_category_placeholder: 'Chagua aina ya bidhaa',
    product_image: 'Picha ya Bidhaa',
    choose_image: 'Chagua Picha au Video',
    save_product: 'Hifadhi Bidhaa',
    delete_product: 'Futa Bidhaa',
    delete_confirm: 'Una hakika unataka kufuta bidhaa hii?',
    no_products: 'Hakuna bidhaa bado',
    search_placeholder: 'Tafuta bidhaa...',
    category_electronics: 'Umeme na vifaa',
    category_clothing: 'Mavazi',
    category_food: 'Chakula',
    category_furniture: 'Samani',
    category_services: 'Huduma',
    category_other: 'Nyinginezo'
  },
  sales: {
    title: 'Mauzo',
    today_sales: 'Mauzo ya Leo',
    close_sales: 'FUNGA MAUZO YA LEO',
    close_confirm: 'Una hakika unataka kufunga mauzo ya leo?',
    invoice_number: 'Namba ya Ankra',
    product: 'Bidhaa',
    quantity: 'Idadi',
    price: 'Bei',
    total: 'Jumla',
    payment_method: 'Njia ya Malipo',
    customer_name: 'Jina la Mteja',
    date: 'Tarehe',
    status: 'Hali',
    no_sales: 'Hakuna mauzo bado'
  },
  reports: {
    title: 'Ripoti',
    sales_report: 'Ripoti ya Mauzo',
    daily_report: 'Ripoti ya Leo',
    monthly_report: 'Ripoti ya Mwezi',
    print: 'Chapisha',
    no_data: 'Hakuna data ya ripoti'
  },
  notifications_section: {
    title: 'Arifa',
    send: 'Tuma',
    title_label: 'Kichwa cha Arifa',
    title_placeholder: 'Andika kichwa hapa...',
    message_label: 'Ujumbe wa Arifa',
    message_placeholder: 'Andika ujumbe hapa...',
    recipient_all: 'Wote',
    recipient_admins: 'Wasimamizi',
    recipient_sellers: 'Wauzaji',
    recipient_clients: 'Wateja',
    recipient_specific: 'Maalum',
    select_recipients: 'Chagua wapokeaji',
    search_users: 'Tafuta watumiaji...',
    select_all: 'Teua Wote',
    done: 'Imekamilika',
    sent_success: 'Arifa imetumwa kikamilifu!',
    history: 'Historia ya Arifa',
    no_history: 'Hakuna arifa zilizotumwa bado',
    stats_sent: 'Zimetumwa',
    stats_today: 'Leo',
    stats_recipients: 'Wapokeaji',
    stats_success: 'Mafanikio'
  },
  customer_dashboard: {
    title: 'Mteja Dashbodi',
    browse_businesses: 'Vinjari Biashara',
    view_adverts: 'Angalia Matangazo',
    my_profile: 'Wasifu Wangu',
    no_businesses: 'Hakuna biashara zilizopatikana',
    no_adverts: 'Hakuna matangazo bado'
  },
  seller_dashboard: {
    title: 'Muuzaji Dashbodi',
    my_products: 'Bidhaa Zangu',
    my_sales: 'Mauzo Yangu',
    add_product: 'Ongeza Bidhaa',
    today_sales: 'Mauzo ya Leo',
    total_earnings: 'Jumla ya Mapato',
    expenses: 'Matumizi',
    edit_profile: 'Hariri Wasifu'
  },
  profile: {
    title: 'Wasifu',
    edit_title: 'Hariri Wasifu',
    full_name: 'Jina Kamili',
    phone: 'Namba ya Simu',
    email: 'Barua Pepe',
    business_name: 'Jina la Biashara',
    business_location: 'Mahali pa Biashara',
    password: 'Nenosiri',
    save: 'Hifadhi Mabadiliko',
    logout: 'Toka',
    logout_confirm: 'Una hakika unataka kutoka?',
    cancel: 'Ghairi',
    success_update: 'Wasifu umesasishwa kikamilifu!',
    error_update: 'Imeshindikana kusasisha wasifu.'
  },
  system_admin: {
    title: 'Msimamizi Mkuu',
    dashboard: 'Dashbodi',
    notifications: 'Tuma Arifa',
    total_users: 'Jumla ya Watumiaji',
    active_users: 'Watumiaji Hai',
    pending_approvals: 'Wanasubiri Uidhinishaji',
    approved_users: 'Wameidhinishwa',
    rejected_users: 'Wamekataliwa',
    welcome_message: 'Karibu kwenye Mfumo wa Usimamizi',
    select_action: 'Chagua kitendo kutoka kwenye menyu'
  },
  otp: {
    title: 'Thibitisha Barua Pepe',
    subtitle: 'Ingiza msimbo wa tarakimu 6 tuliokutumia kwa {email}',
    code_placeholder: 'Msimbo wa tarakimu 6',
    verify_button: 'THIBITISHA & KAMILISHA',
    verifying: 'Inathibitisha...',
    sending: 'Inatuma msimbo kwenye barua pepe...',
    resend: 'Tuma msimbo tena',
    resend_in: 'Tuma tena baada ya {seconds}s',
    change_email: 'Badilisha barua pepe',
    didnt_receive: 'Hukupokea msimbo?',
    error_required: 'Tafadhali ingiza msimbo wa tarakimu 6',
    error_invalid: 'Msimbo si sahihi au umeisha muda wake',
    error_general: 'Imeshindikana kuthibitisha. Tafadhali jaribu tena.',
    error_network: 'Hitilafu ya mtandao. Hakikisha umeunganishwa.',
    sent_title: 'Msimbo Umetumwa!',
    sent_message: 'Msimbo mpya umetumwa kwa {email}',
    test_hint: 'Msimbo wako: {code}',
    email_failed: 'Msimbo haukuweza kutumwa kwenye barua pepe yako. Bonyeza "Tuma msimbo tena" kujaribu tena.'
  }
};

// 🖼️ Business Profile + Twende Dukani campaign keys (onboarding slideshow)
sw.onboarding = {
  photo_title: 'Ongeza Picha ya Wasifu wa Biashara Yako',
  photo_subtitle: 'Hii husaidia wateja kukutambua. Unaweza kuibadilisha wakati wowote kutoka kwenye wasifu wako.',
  photo_upload: 'Pakia Picha',
  photo_change: 'Badilisha Picha',
  photo_uploading: 'Inapakia...',
  photo_choose_title: 'Ongeza Picha ya Wasifu',
  photo_choose_message: 'Chagua chanzo cha picha',
  photo_camera: 'Piga Picha',
  photo_gallery: 'Chagua Kutoka kwenye Ghala',
  photo_permission_denied: 'Ruhusa ya kamera/picha imekataliwa. Tafadhali iwaze katika Mipangilio.',
  photo_upload_error: 'Upakiaji wa picha umeshindikana. Tafadhali jaribu tena.',
  photo_save_error: 'Haikuwezekana kuhifadhi picha. Tafadhali jaribu tena.',
  photo_success_title: 'Picha Imehifadhiwa!',
  photo_success_message: 'Picha yako ya wasifu imehifadhiwa.',
  skip: 'Ruka',
  swipe_hint: '← Telezesha kuendelea →',
  location_title: 'Weka Eneo la Biashara Yako',
  location_subtitle: 'Bonyeza kitufe ukiwa dukani ili wateja wakupate kwa urahisi kupitia "Twende Dukani".',
  location_button: 'Eneo la Google Map',
  location_saving: 'Inatafuta eneo lako...',
  location_saved: 'Eneo Limehifadhiwa',
  location_placeholder: 'Eneo la duka lako litaonekana hapa',
  location_note: 'Unaweza pia kuweka hili baadaye kutoka kwenye wasifu wako.',
  location_permission_denied_title: 'Ruhusa ya Eneo Inahitajika',
  location_permission_denied: 'Ruhusa ya eneo imekataliwa. Tafadhali iwaze katika Mipangilio ili utumie huduma hii.',
  location_save_error: 'Haikuwezekana kuhifadhi eneo lako. Tafadhali jaribu tena.',
  location_success_title: 'Eneo Limehifadhiwa!',
  location_success_message: 'Wateja sasa wanaweza kufika dukani lako kwa kutumia Twende Dukani.',
  location_error: 'Haikuwezekana kupata eneo lako. Tafadhali jaribu tena.',
  location_confirm_title: 'Una uhakika?',
  location_confirm_message: 'Una uhakika uko katika eneo la biashara? Eneo linalotambuliwa: {area}',
  location_confirm_yes: 'Ndio'
};
Object.assign(sw.profile, {
  photo_permission_denied: 'Ruhusa ya picha imekataliwa. Tafadhali iwaze katika Mipangilio.',
  photo_success_title: 'Picha Imesasishwa!',
  photo_success: 'Picha yako ya wasifu imesasishwa.',
  photo_error: 'Haikuwezekana kusasisha picha yako. Tafadhali jaribu tena.'
});
Object.assign(sw.customer_dashboard, {
  twende_dukani: 'Twende Dukani',
  twende_finding_title: 'Inatafuta eneo lako...',
  twende_finding_message: 'Tafadhali subiri tukupate...',
  twende_location_permission: 'Ruhusa ya eneo inahitajika kwa urambazaji. Tafadhali iwaze.',
  twende_no_location: 'Biashara hii bado haijaweka eneo lake.',
  twende_location_failed: 'Haikuwezekana kupata eneo lako. Tafadhali angalia GPS yako na ujaribu tena.',
  twende_open_error: 'Haikuwezekana kufungua Google Maps kwenye kifaa hiki.'
});
Object.assign(sw.admin_dashboard, {
  logo_permission_denied: 'Ruhusa ya picha imekataliwa. Tafadhali iwaze katika Mipangilio.',
  logo_success_title: 'Nembo Imesasishwa!',
  logo_success: 'Nembo ya biashara yako imesasishwa.',
  logo_error: 'Haikuwezekana kusasisha nembo. Tafadhali jaribu tena.',
  track_location: 'Fuatilia Eneo Langu',
  track_location_loading: 'Inafuatilia eneo lako...',
  track_location_permission: 'Ruhusa ya eneo inahitajika kufuatilia eneo la biashara yako. Tafadhali iwaze.',
  track_location_success_title: 'Eneo Limefuatiliwa!',
  track_location_success: 'Eneo la biashara yako limehifadhiwa. Wateja sasa wanaweza kutumia Twende Dukani kukufikia.',
  track_location_error: 'Haikuwezekana kufuatilia eneo lako. Tafadhali jaribu tena.',
  location_tracked: 'Eneo limefuatiliwa',
  change_location: 'Badili Eneo',
  location_confirm_title: 'Una uhakika?',
  location_confirm_message: 'Una uhakika uko katika eneo la biashara? Eneo linalotambuliwa: {area}',
  location_confirm_yes: 'Ndio'
});

// Write sw.json
const localesDir = path.join(__dirname, '..', 'locales');
if (!fs.existsSync(localesDir)) fs.mkdirSync(localesDir, { recursive: true });
fs.writeFileSync(path.join(localesDir, 'sw.json'), JSON.stringify(sw, null, 2));
console.log('sw.json written successfully - ' + countKeys(sw) + ' keys');

// Generate English version
const en = JSON.parse(JSON.stringify(sw));
// English translations
Object.assign(en.app, {
  title: 'DUKANI APP', version: 'Version 1.0.0', badili_lugha: 'Change Language',
  tuma: 'Apply', funga: 'Close', loading: 'Loading...', error: 'Error',
  success: 'Success', cancel: 'Cancel', confirm: 'Confirm', save: 'Save',
  delete: 'Delete', edit: 'Edit', search: 'Search', back: 'Back', next: 'Next',
  submit: 'Submit', close: 'Close', ok: 'OK', no_data: 'No data',
  refresh: 'Refresh', retry: 'Try again'
});
Object.assign(en.navigation, {
  home: 'Home', about: 'About', contact: 'Contact', settings: 'Settings',
  profile: 'Profile', logout: 'Logout', dashboard: 'Dashboard',
  products: 'Products', sales: 'Sales', reports: 'Reports',
  notifications: 'Notifications', payments: 'Payments', customers: 'Customers',
  adverts: 'Adverts'
});
Object.assign(en.home, {
  welcome: 'WELCOME TO DUKANI', subtitle: 'Select your role to start',
  get_started: 'Get Started', learn_more: 'Learn More'
});
Object.assign(en.buttons, {
  mteja: 'CLIENT', muuzaji: 'SELLER', msimamizi: 'MANAGER', washa: 'QUICK PAY',
  mteja_description: 'I am requesting services or products',
  muuzaji_description: 'I sell products and services',
  msimamizi_description: 'I manage the shop',
  washa_description: 'I make quick payments'
});
Object.assign(en.login, {
  title: 'LOGIN', welcome_back: 'Welcome Back!', email_label: 'Email',
  email_placeholder: 'example@email.com', password_label: 'Password',
  password_placeholder: '......', remember_me: 'Remember Me',
  forgot_password: 'Forgot password?', no_account: 'No account?',
  signup_as_client: 'Register as Client',
  signup_as_seller: 'Register as Seller',
  signup_as_admin: 'Register as Manager',
  signup_here: 'Register here',
  security_note: 'Your information is protected with high security',
  success_title: 'Success!',
  success_message: 'Welcome {name}! You have logged in successfully.',
  continue: 'Continue', error_title: 'Error',
  error_email_required: 'Email is required',
  error_email_invalid: 'Enter a valid email',
  error_password_required: 'Password is required',
  error_password_length: 'Password must be 6 or more characters',
  error_network: 'Network error. Make sure you are connected to the internet.',
  account_pending: 'Your account is not yet approved.',
  account_not_approved_title: 'Account Not Approved',
  account_rejected: 'Your account has been rejected.',
  account_not_approved: 'Your account is not approved.'
});
Object.assign(en.client_signup, {
  title: 'REGISTER AS CLIENT', subtitle: 'Create your Client account',
  full_name: 'Full Name *',
  full_name_placeholder: 'Enter your full name',
  email_label: 'Email *', email_placeholder: 'example@email.com',
  phone_label: 'Phone Number (Optional)', phone_placeholder: '07XXXXXXXX',
  password_label: 'Password *',
  password_placeholder: 'Enter your password (6 or more characters)',
  confirm_password_label: 'Confirm Password *',
  confirm_password_placeholder: 'Re-enter your password',
  signup_button: 'REGISTER NOW',
  terms: 'By clicking Register Now, you agree to our Terms and Conditions',
  back_to_login: 'Back to Login Page',
  footer_title: 'Client account allows you:',
  footer_point1: 'View product adverts',
  footer_point2: 'Contact sellers',
  footer_point3: 'Access services easily',
  error_required_fields: 'Please fill all required fields',
  error_password_mismatch: 'Passwords do not match',
  error_invalid_email: 'Please enter a valid email',
  error_general: 'An error occurred during registration',
  error_account_exists: 'This email already exists.',
  success_title: 'Success!',
  success_message: 'Client account created successfully! You can now login.',
  success_button: 'Login Now'
});
Object.assign(en.seller_signup, {
  title: 'REGISTER AS SELLER', subtitle: 'Indicates required field',
  signup_button: 'REGISTER NOW', back_button: 'GO BACK',
  info_title: 'Important information for Sellers:',
  success_title: 'Success', success_button: 'OK',
  business_not_found_title: 'Business Not Found',
  business_no_admin_title: 'Business Has No Admin'
});
Object.assign(en.admin_signup, {
  title: 'REGISTER AS MANAGER', subtitle: 'Indicates required field',
  signup_button: 'REGISTER NOW', back_button: 'GO BACK',
  info_title: 'Important information for Managers:',
  error_wrong_admin_code: 'Admin code is incorrect',
  success_title: 'Success', success_button: 'Login Now'
});
Object.assign(en.forgot_password, {
  title: 'Reset Password', send_code: 'Send Code',
  verify_code: 'Verify Code', reset_button: 'Reset Password',
  resend_code: 'Resend code', success_button: 'Login Now',
  success_message: 'Password has been reset successfully!'
});
Object.assign(en.payment, {
  title: 'Payment', header: 'Payment', amount: 'Amount to Pay',
  currency: 'TZS', amount_placeholder: 'Enter amount',
  phone_number: 'Phone Number', country_code: '+255',
  phone_placeholder: 'Enter phone number',
  choose_method: 'Choose Payment Method', payment_info: 'Payment Information',
  continue_payment: 'Continue to Payment',
  security_notice: 'Your payment is processed securely.',
  error_fill_fields: 'Please fill all fields',
  confirm_title: 'Confirm Payment',
  confirm_message: 'Confirm payment of {amount} TZS to {phone}?',
  success_title: 'Success', success_message: 'Payment successful!'
});
Object.assign(en.language, {
  sw: 'Kiswahili', en: 'English', fr: 'French', hi: 'Hindi',
  es: 'Spanish', ur: 'Urdu'
});
Object.assign(en.admin_dashboard, {
  title: 'Admin Dashboard', total_products: 'Total Products',
  total_sales: 'Total Sales', total_customers: 'Total Customers',
  total_revenue: 'Total Revenue', today_sales: 'Today Sales',
  recent_activities: 'Recent Activities', view_all: 'View All',
  no_activities: 'No activities'
});
Object.assign(en.products, {
  title: 'Products', new_product: 'New Product',
  edit_product: 'Edit Product', product_name: 'Product Name',
  save_product: 'Save Product', no_products: 'No products yet',
  category_electronics: 'Electronics', category_clothing: 'Clothing',
  category_food: 'Food', category_furniture: 'Furniture',
  category_services: 'Services', category_other: 'Other'
});
Object.assign(en.sales, {
  title: 'Sales', today_sales: 'Today Sales',
  close_sales: 'CLOSE TODAY SALES', invoice_number: 'Invoice Number',
  product: 'Product', quantity: 'Quantity', price: 'Price', total: 'Total',
  payment_method: 'Payment Method', customer_name: 'Customer Name',
  date: 'Date', status: 'Status', no_sales: 'No sales yet'
});
Object.assign(en.reports, {
  title: 'Reports', sales_report: 'Sales Report',
  daily_report: 'Daily Report', monthly_report: 'Monthly Report',
  print: 'Print', no_data: 'No report data'
});
Object.assign(en.notifications_section, {
  title: 'Notifications', send: 'Send',
  recipient_all: 'All', recipient_admins: 'Admins',
  recipient_sellers: 'Sellers', recipient_clients: 'Clients',
  recipient_specific: 'Specific', select_recipients: 'Select recipients',
  search_users: 'Search users...', select_all: 'Select All',
  done: 'Done', sent_success: 'Notification sent successfully!',
  history: 'Notification History',
  no_history: 'No notifications sent yet',
  stats_sent: 'Sent', stats_today: 'Today',
  stats_recipients: 'Recipients', stats_success: 'Success'
});
Object.assign(en.customer_dashboard, {
  title: 'Customer Dashboard', browse_businesses: 'Browse Businesses',
  view_adverts: 'View Adverts', my_profile: 'My Profile',
  no_businesses: 'No businesses found', no_adverts: 'No adverts yet'
});
Object.assign(en.seller_dashboard, {
  title: 'Seller Dashboard', my_products: 'My Products',
  my_sales: 'My Sales', add_product: 'Add Product',
  today_sales: 'Today Sales', total_earnings: 'Total Earnings',
  expenses: 'Expenses', edit_profile: 'Edit Profile'
});
Object.assign(en.profile, {
  title: 'Profile', edit_title: 'Edit Profile', full_name: 'Full Name',
  phone: 'Phone Number', email: 'Email', business_name: 'Business Name',
  business_location: 'Business Location', password: 'Password',
  save: 'Save Changes', logout: 'Logout',
  logout_confirm: 'Are you sure you want to logout?',
  cancel: 'Cancel', success_update: 'Profile updated successfully!',
  error_update: 'Failed to update profile.'
});
Object.assign(en.system_admin, {
  title: 'System Admin', dashboard: 'Dashboard',
  notifications: 'Send Notifications', total_users: 'Total Users',
  active_users: 'Active Users',
  pending_approvals: 'Pending Approvals',
  approved_users: 'Approved', rejected_users: 'Rejected',
  welcome_message: 'Welcome to the Administration System',
  select_action: 'Select an action from the menu'
});
Object.assign(en.otp, {
  title: 'Verify Your Email',
  subtitle: 'Enter the 6-digit code we sent to {email}',
  code_placeholder: '6-digit code',
  verify_button: 'VERIFY & COMPLETE',
  verifying: 'Verifying...',
  sending: 'Sending code to your email...',
  resend: 'Resend code',
  resend_in: 'Resend in {seconds}s',
  change_email: 'Change email',
  didnt_receive: "Didn't receive the code?",
  error_required: 'Please enter the 6-digit code',
  error_invalid: 'The code is incorrect or has expired',
  error_general: 'Verification failed. Please try again.',
  error_network: 'Network error. Make sure you are connected.',
  sent_title: 'Code Sent!',
  sent_message: 'A new code has been sent to {email}',
  test_hint: 'Your code: {code}',
  email_failed: 'The code could not be sent to your email. Tap "Resend code" to try again.'
});
Object.assign(en.onboarding, {
  photo_title: 'Add Your Business Profile Photo',
  photo_subtitle: 'This helps customers recognize your business. You can change it anytime from your profile.',
  photo_upload: 'Upload Photo',
  photo_change: 'Change Photo',
  photo_uploading: 'Uploading...',
  photo_choose_title: 'Add Profile Photo',
  photo_choose_message: 'Choose a photo source',
  photo_camera: 'Take Photo',
  photo_gallery: 'Choose from Gallery',
  photo_permission_denied: 'Camera/photo permission was denied. Please allow it in Settings.',
  photo_upload_error: 'Photo upload failed. Please try again.',
  photo_save_error: 'Could not save the photo. Please try again.',
  photo_success_title: 'Photo Saved!',
  photo_success_message: 'Your profile photo has been saved.',
  skip: 'Skip',
  swipe_hint: '← Swipe to continue →',
  location_title: 'Set Your Business Location',
  location_subtitle: 'Tap the button when you are at your shop so customers can find you easily with "Twende Dukani".',
  location_button: 'Google Map Location',
  location_saving: 'Getting your location...',
  location_saved: 'Location Saved',
  location_placeholder: 'Your shop location will appear here',
  location_note: 'You can also set this later from your profile.',
  location_permission_denied_title: 'Location Permission Needed',
  location_permission_denied: 'Location permission was denied. Please allow it in Settings to use this feature.',
  location_save_error: 'Could not save your location. Please try again.',
  location_success_title: 'Location Saved!',
  location_success_message: 'Customers can now navigate to your shop with Twende Dukani.',
  location_error: 'Could not get your location. Please try again.',
  location_confirm_title: 'Are you sure?',
  location_confirm_message: 'Are you sure you are at the business location? Detected area: {area}',
  location_confirm_yes: 'Yes'
});
Object.assign(en.profile, {
  photo_permission_denied: 'Photo permission was denied. Please allow it in Settings.',
  photo_success_title: 'Photo Updated!',
  photo_success: 'Your profile photo has been updated.',
  photo_error: 'Could not update your photo. Please try again.'
});
Object.assign(en.customer_dashboard, {
  twende_dukani: 'Twende Dukani',
  twende_finding_title: 'Finding your location...',
  twende_finding_message: 'Please wait while we locate you...',
  twende_location_permission: 'Location permission is needed to navigate. Please allow it.',
  twende_no_location: 'This business has not set a location yet.',
  twende_location_failed: 'Could not get your location. Please check your GPS and try again.',
  twende_open_error: 'Could not open Google Maps on this device.'
});
Object.assign(en.admin_dashboard, {
  logo_permission_denied: 'Photo permission was denied. Please allow it in Settings.',
  logo_success_title: 'Logo Updated!',
  logo_success: 'Your business logo has been updated.',
  logo_error: 'Could not update the logo. Please try again.',
  track_location: 'Track My Location',
  track_location_loading: 'Tracking your location...',
  track_location_permission: 'Location permission is needed to track your business location. Please allow it.',
  track_location_success_title: 'Location Tracked!',
  track_location_success: 'Your business location has been saved. Customers can now use Twende Dukani to reach you.',
  track_location_error: 'Could not track your location. Please try again.',
  location_tracked: 'Location tracked',
  change_location: 'Change Location',
  location_confirm_title: 'Are you sure?',
  location_confirm_message: 'Are you sure you are at the business location? Detected area: {area}',
  location_confirm_yes: 'Yes'
});

fs.writeFileSync(path.join(localesDir, 'en.json'), JSON.stringify(en, null, 2));
console.log('en.json written successfully - ' + countKeys(en) + ' keys');

// Copy to other languages with English fallback
const otherLocales = ['fr.json', 'hi.json', 'es.json', 'ur.json'];
const localeNames = {
  'fr.json': 'French', 'hi.json': 'Hindi', 'es.json': 'Spanish', 'ur.json': 'Urdu'
};

for (const loc of otherLocales) {
  const data = JSON.parse(JSON.stringify(en));
  data.language = {
    sw: 'Kiswahili', en: 'English', fr: 'Francais',
    hi: 'Hindi', es: 'Espanol', ur: 'Urdu'
  };
  if (loc === 'fr.json') {
    data.language.fr = 'Francais';
    data.app.badili_lugha = 'Changer de Langue';
    data.app.tuma = 'Appliquer';
    data.app.loading = 'Chargement...';
    data.login.title = 'CONNEXION';
    data.login.welcome_back = 'Bon retour!';
    data.client_signup.title = 'INSCRIPTION CLIENT';
    data.client_signup.signup_button = "S'INSCRIRE";
    data.home.welcome = 'BIENVENUE A DUKANI';
  }
  if (loc === 'es.json') {
    data.language.es = 'Espanol';
    data.app.badili_lugha = 'Cambiar Idioma';
    data.app.loading = 'Cargando...';
    data.login.title = 'INICIAR SESION';
    data.login.welcome_back = 'Bienvenido de nuevo!';
    data.client_signup.title = 'REGISTRARSE COMO CLIENTE';
    data.home.welcome = 'BIENVENIDO A DUKANI';
  }
  if (loc === 'hi.json') {
    data.language.hi = 'Hindi';
    data.app.loading = 'Loading...';
  }
  if (loc === 'ur.json') {
    data.language.ur = 'Urdu';
    data.app.loading = 'Loading...';
  }
  fs.writeFileSync(path.join(localesDir, loc), JSON.stringify(data, null, 2));
  console.log(loc + ' written successfully');
}

function countKeys(obj) {
  let count = 0;
  for (const v of Object.values(obj)) {
    if (typeof v === 'object' && v !== null) count += countKeys(v);
    else count++;
  }
  return count;
}

console.log('\\nAll locale files generated successfully!');
