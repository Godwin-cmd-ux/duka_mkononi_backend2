#!/usr/bin/env node
/**
 * Adds the "Business Profile" + "Twende Dukani" campaign strings to every
 * locale file (locales/*.json). Run: node scripts/add-onboarding-locales.js
 *
 * Safe: parses each file, validates, then writes back with the same 2-space
 * indent + trailing newline the files already use.
 */
const fs = require('fs');
const path = require('path');

const LANGS = ['en', 'sw', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

// ── New top-level "onboarding" section (post-registration slideshow) ──
const ONBOARDING = {
  en: {
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
  },
  sw: {
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
  },
  fr: {
    photo_title: 'Ajoutez la photo de profil de votre entreprise',
    photo_subtitle: 'Cela aide les clients à reconnaître votre entreprise. Vous pouvez la modifier à tout moment depuis votre profil.',
    photo_upload: 'Téléverser la photo',
    photo_change: 'Changer la photo',
    photo_uploading: 'Téléversement...',
    photo_choose_title: 'Ajouter une photo de profil',
    photo_choose_message: 'Choisissez une source de photo',
    photo_camera: 'Prendre une photo',
    photo_gallery: 'Choisir dans la galerie',
    photo_permission_denied: 'Autorisation caméra/photos refusée. Veuillez l’autoriser dans les Réglages.',
    photo_upload_error: 'Échec du téléversement de la photo. Veuillez réessayer.',
    photo_save_error: 'Impossible d’enregistrer la photo. Veuillez réessayer.',
    photo_success_title: 'Photo enregistrée !',
    photo_success_message: 'Votre photo de profil a été enregistrée.',
    skip: 'Passer',
    swipe_hint: '← Balayez pour continuer →',
    location_title: 'Définissez la localisation de votre entreprise',
    location_subtitle: 'Appuyez sur le bouton lorsque vous êtes dans votre magasin pour que les clients vous trouvent facilement avec « Twende Dukani ».',
    location_button: 'Localisation Google Map',
    location_saving: 'Obtention de votre position...',
    location_saved: 'Localisation enregistrée',
    location_placeholder: 'La localisation de votre magasin apparaîtra ici',
    location_note: 'Vous pouvez aussi la définir plus tard depuis votre profil.',
    location_permission_denied_title: 'Autorisation de localisation requise',
    location_permission_denied: 'Autorisation de localisation refusée. Veuillez l’autoriser dans les Réglages pour utiliser cette fonctionnalité.',
    location_save_error: 'Impossible d’enregistrer votre position. Veuillez réessayer.',
    location_success_title: 'Localisation enregistrée !',
    location_success_message: 'Les clients peuvent maintenant naviguer vers votre magasin avec Twende Dukani.',
    location_error: 'Impossible d’obtenir votre position. Veuillez réessayer.',
  },
  hi: {
    photo_title: 'अपनी व्यवसाय प्रोफ़ाइल फोटो जोड़ें',
    photo_subtitle: 'इससे ग्राहकों को आपके व्यवसाय को पहचानने में मदद मिलती है। आप इसे अपनी प्रोफ़ाइल से कभी भी बदल सकते हैं।',
    photo_upload: 'फोटो अपलोड करें',
    photo_change: 'फोटो बदलें',
    photo_uploading: 'अपलोड हो रहा है...',
    photo_choose_title: 'प्रोफ़ाइल फोटो जोड़ें',
    photo_choose_message: 'फोटो स्रोत चुनें',
    photo_camera: 'फोटो लें',
    photo_gallery: 'गैलरी से चुनें',
    photo_permission_denied: 'कैमरा/फोटो अनुमति अस्वीकृत हुई। कृपया सेटिंग्स में इसे अनुमति दें।',
    photo_upload_error: 'फोटो अपलोड विफल हुआ। कृपया पुनः प्रयास करें।',
    photo_save_error: 'फोटो सहेजा नहीं जा सका। कृपया पुनः प्रयास करें।',
    photo_success_title: 'फोटो सहेजी गई!',
    photo_success_message: 'आपकी प्रोफ़ाइल फोटो सहेज ली गई है।',
    skip: 'छोड़ें',
    swipe_hint: '← जारी रखने के लिए स्वाइप करें →',
    location_title: 'अपना व्यवसाय स्थान निर्धारित करें',
    location_subtitle: 'जब आप अपनी दुकान पर हों तो बटन दबाएँ ताकि ग्राहक "ट्वेंडे दुकानी" से आपको आसानी से ढूँढ सकें।',
    location_button: 'गूगल मैप स्थान',
    location_saving: 'आपका स्थान प्राप्त हो रहा है...',
    location_saved: 'स्थान सहेजा गया',
    location_placeholder: 'आपकी दुकान का स्थान यहाँ दिखाई देगा',
    location_note: 'आप इसे बाद में अपनी प्रोफ़ाइल से भी सेट कर सकते हैं।',
    location_permission_denied_title: 'स्थान अनुमति आवश्यक',
    location_permission_denied: 'स्थान अनुमति अस्वीकृत हुई। इस सुविधा का उपयोग करने के लिए कृपया सेटिंग्स में अनुमति दें।',
    location_save_error: 'आपका स्थान सहेजा नहीं जा सका। कृपया पुनः प्रयास करें।',
    location_success_title: 'स्थान सहेजा गया!',
    location_success_message: 'ग्राहक अब ट्वेंडे दुकानी से आपकी दुकान तक पहुँच सकते हैं।',
    location_error: 'आपका स्थान प्राप्त नहीं हो सका। कृपया पुनः प्रयास करें।',
  },
  es: {
    photo_title: 'Añade la foto de perfil de tu negocio',
    photo_subtitle: 'Esto ayuda a los clientes a reconocer tu negocio. Puedes cambiarla en cualquier momento desde tu perfil.',
    photo_upload: 'Subir foto',
    photo_change: 'Cambiar foto',
    photo_uploading: 'Subiendo...',
    photo_choose_title: 'Añadir foto de perfil',
    photo_choose_message: 'Elige una fuente de foto',
    photo_camera: 'Tomar foto',
    photo_gallery: 'Elegir de la galería',
    photo_permission_denied: 'Permiso de cámara/fotos denegado. Actívalo en Ajustes.',
    photo_upload_error: 'Error al subir la foto. Inténtalo de nuevo.',
    photo_save_error: 'No se pudo guardar la foto. Inténtalo de nuevo.',
    photo_success_title: '¡Foto guardada!',
    photo_success_message: 'Tu foto de perfil se ha guardado.',
    skip: 'Omitir',
    swipe_hint: '← Desliza para continuar →',
    location_title: 'Configura la ubicación de tu negocio',
    location_subtitle: 'Pulsa el botón cuando estés en tu tienda para que los clientes te encuentren fácilmente con "Twende Dukani".',
    location_button: 'Ubicación de Google Map',
    location_saving: 'Obteniendo tu ubicación...',
    location_saved: 'Ubicación guardada',
    location_placeholder: 'La ubicación de tu tienda aparecerá aquí',
    location_note: 'También puedes configurarla más tarde desde tu perfil.',
    location_permission_denied_title: 'Permiso de ubicación necesario',
    location_permission_denied: 'Permiso de ubicación denegado. Actívalo en Ajustes para usar esta función.',
    location_save_error: 'No se pudo guardar tu ubicación. Inténtalo de nuevo.',
    location_success_title: '¡Ubicación guardada!',
    location_success_message: 'Los clientes ya pueden navegar hasta tu tienda con Twende Dukani.',
    location_error: 'No se pudo obtener tu ubicación. Inténtalo de nuevo.',
  },
  ur: {
    photo_title: 'اپنے کاروبار کی پروفائل تصویر شامل کریں',
    photo_subtitle: 'اس سے گاہکوں کو آپ کے کاروبار کو پہچاننے میں مدد ملتی ہے۔ آپ اسے اپنی پروفائل سے کسی بھی وقت تبدیل کر سکتے ہیں۔',
    photo_upload: 'تصویر اپ لوڈ کریں',
    photo_change: 'تصویر تبدیل کریں',
    photo_uploading: 'اپ لوڈ ہو رہا ہے...',
    photo_choose_title: 'پروفائل تصویر شامل کریں',
    photo_choose_message: 'تصویر کا ذریعہ منتخب کریں',
    photo_camera: 'تصویر لیں',
    photo_gallery: 'گیلری سے منتخب کریں',
    photo_permission_denied: 'کیمرہ/تصویر کی اجازت مسترد کر دی گئی۔ براہ کرم ترتیبات میں اجازت دیں۔',
    photo_upload_error: 'تصویر اپ لوڈ ناکام ہوئی۔ براہ کرم دوبارہ کوشش کریں۔',
    photo_save_error: 'تصویر محفوظ نہیں ہو سکی۔ براہ کرم دوبارہ کوشش کریں۔',
    photo_success_title: 'تصویر محفوظ ہو گئی!',
    photo_success_message: 'آپ کی پروفائل تصویر محفوظ ہو گئی ہے۔',
    skip: 'چھوڑیں',
    swipe_hint: '← جاری رکھنے کے لیے سوائپ کریں →',
    location_title: 'اپنے کاروبار کا مقام مقرر کریں',
    location_subtitle: 'جب آپ اپنی دکان پر ہوں تو بٹن دبائیں تاکہ گاہک "ٹوینڈے دوکانی" سے آپ کو آسانی سے تلاش کر سکیں۔',
    location_button: 'گوگل میپ کا مقام',
    location_saving: 'آپ کا مقام حاصل ہو رہا ہے...',
    location_saved: 'مقام محفوظ ہو گیا',
    location_placeholder: 'آپ کی دکان کا مقام یہاں ظاہر ہوگا',
    location_note: 'آپ اسے بعد میں اپنی پروفائل سے بھی مقرر کر سکتے ہیں۔',
    location_permission_denied_title: 'مقام کی اجازت درکار ہے',
    location_permission_denied: 'مقام کی اجازت مسترد کر دی گئی۔ اس خصوصیت کے لیے براہ کرم ترتیبات میں اجازت دیں۔',
    location_save_error: 'آپ کا مقام محفوظ نہیں ہو سکا۔ براہ کرم دوبارہ کوشش کریں۔',
    location_success_title: 'مقام محفوظ ہو گیا!',
    location_success_message: 'گاہک اب ٹوینڈے دوکانی کے ذریعے آپ کی دکان تک پہنچ سکتے ہیں۔',
    location_error: 'آپ کا مقام حاصل نہیں ہو سکا۔ براہ کرم دوبارہ کوشش کریں۔',
  },
  de: {
    photo_title: 'Fügen Sie Ihr Geschäftsprofilfoto hinzu',
    photo_subtitle: 'So erkennen Kunden Ihr Geschäft leichter. Sie können es jederzeit in Ihrem Profil ändern.',
    photo_upload: 'Foto hochladen',
    photo_change: 'Foto ändern',
    photo_uploading: 'Wird hochgeladen...',
    photo_choose_title: 'Profilfoto hinzufügen',
    photo_choose_message: 'Fotoquelle wählen',
    photo_camera: 'Foto aufnehmen',
    photo_gallery: 'Aus Galerie wählen',
    photo_permission_denied: 'Kamera/Foto-Berechtigung verweigert. Bitte in den Einstellungen erlauben.',
    photo_upload_error: 'Foto-Upload fehlgeschlagen. Bitte erneut versuchen.',
    photo_save_error: 'Foto konnte nicht gespeichert werden. Bitte erneut versuchen.',
    photo_success_title: 'Foto gespeichert!',
    photo_success_message: 'Ihr Profilfoto wurde gespeichert.',
    skip: 'Überspringen',
    swipe_hint: '← Zum Fortfahren wischen →',
    location_title: 'Geschäftsstandort festlegen',
    location_subtitle: 'Tippen Sie auf die Schaltfläche, wenn Sie in Ihrem Geschäft sind, damit Kunden Sie mit „Twende Dukani“ leicht finden.',
    location_button: 'Google-Map-Standort',
    location_saving: 'Standort wird ermittelt...',
    location_saved: 'Standort gespeichert',
    location_placeholder: 'Ihr Geschäftsstandort erscheint hier',
    location_note: 'Sie können dies auch später in Ihrem Profil festlegen.',
    location_permission_denied_title: 'Standortberechtigung erforderlich',
    location_permission_denied: 'Standortberechtigung verweigert. Bitte in den Einstellungen erlauben, um diese Funktion zu nutzen.',
    location_save_error: 'Ihr Standort konnte nicht gespeichert werden. Bitte erneut versuchen.',
    location_success_title: 'Standort gespeichert!',
    location_success_message: 'Kunden können nun mit Twende Dukani zu Ihrem Geschäft navigieren.',
    location_error: 'Ihr Standort konnte nicht ermittelt werden. Bitte erneut versuchen.',
  },
  zh: {
    photo_title: '添加您的企业资料照片',
    photo_subtitle: '这有助于客户识别您的企业。您可以随时在个人资料中更改。',
    photo_upload: '上传照片',
    photo_change: '更换照片',
    photo_uploading: '上传中...',
    photo_choose_title: '添加资料照片',
    photo_choose_message: '选择照片来源',
    photo_camera: '拍照',
    photo_gallery: '从相册选择',
    photo_permission_denied: '相机/照片权限被拒绝。请在设置中允许。',
    photo_upload_error: '照片上传失败。请重试。',
    photo_save_error: '无法保存照片。请重试。',
    photo_success_title: '照片已保存！',
    photo_success_message: '您的资料照片已保存。',
    skip: '跳过',
    swipe_hint: '← 滑动继续 →',
    location_title: '设置您的企业位置',
    location_subtitle: '当您在店铺时点击按钮，客户即可通过“Twende Dukani”轻松找到您。',
    location_button: '谷歌地图位置',
    location_saving: '正在获取您的位置...',
    location_saved: '位置已保存',
    location_placeholder: '您的店铺位置将显示在这里',
    location_note: '您也可以稍后在个人资料中设置。',
    location_permission_denied_title: '需要位置权限',
    location_permission_denied: '位置权限被拒绝。请在设置中允许以使用此功能。',
    location_save_error: '无法保存您的位置。请重试。',
    location_success_title: '位置已保存！',
    location_success_message: '客户现在可以通过 Twende Dukani 导航到您的店铺。',
    location_error: '无法获取您的位置。请重试。',
  },
};

// ── Keys merged into existing sections ──
const EXTRA = {
  en: {
    profile: {
      photo_permission_denied: 'Photo permission was denied. Please allow it in Settings.',
      photo_success_title: 'Photo Updated!',
      photo_success: 'Your profile photo has been updated.',
      photo_error: 'Could not update your photo. Please try again.',
    },
    customer_dashboard: {
      twende_dukani: 'Twende Dukani',
      twende_finding_title: 'Finding your location...',
      twende_finding_message: 'Please wait while we locate you...',
      twende_location_permission: 'Location permission is needed to navigate. Please allow it.',
      twende_no_location: 'This business has not set a location yet.',
      twende_location_failed: 'Could not get your location. Please check your GPS and try again.',
      twende_open_error: 'Could not open Google Maps on this device.',
    },
    admin_dashboard: {
      logo_permission_denied: 'Photo permission was denied. Please allow it in Settings.',
      logo_success_title: 'Logo Updated!',
      logo_success: 'Your business logo has been updated.',
      logo_error: 'Could not update the logo. Please try again.',
    },
  },
  sw: {
    profile: {
      photo_permission_denied: 'Ruhusa ya picha imekataliwa. Tafadhali iwaze katika Mipangilio.',
      photo_success_title: 'Picha Imesasishwa!',
      photo_success: 'Picha yako ya wasifu imesasishwa.',
      photo_error: 'Haikuwezekana kusasisha picha yako. Tafadhali jaribu tena.',
    },
    customer_dashboard: {
      twende_dukani: 'Twende Dukani',
      twende_finding_title: 'Inatafuta eneo lako...',
      twende_finding_message: 'Tafadhali subiri tukupate...',
      twende_location_permission: 'Ruhusa ya eneo inahitajika kwa urambazaji. Tafadhali iwaze.',
      twende_no_location: 'Biashara hii bado haijaweka eneo lake.',
      twende_location_failed: 'Haikuwezekana kupata eneo lako. Tafadhali angalia GPS yako na ujaribu tena.',
      twende_open_error: 'Haikuwezekana kufungua Google Maps kwenye kifaa hiki.',
    },
    admin_dashboard: {
      logo_permission_denied: 'Ruhusa ya picha imekataliwa. Tafadhali iwaze katika Mipangilio.',
      logo_success_title: 'Nembo Imesasishwa!',
      logo_success: 'Nembo ya biashara yako imesasishwa.',
      logo_error: 'Haikuwezekana kusasisha nembo. Tafadhali jaribu tena.',
    },
  },
  fr: {
    profile: {
      photo_permission_denied: 'Autorisation photos refusée. Veuillez l’autoriser dans les Réglages.',
      photo_success_title: 'Photo mise à jour !',
      photo_success: 'Votre photo de profil a été mise à jour.',
      photo_error: 'Impossible de mettre à jour votre photo. Veuillez réessayer.',
    },
    customer_dashboard: {
      twende_dukani: 'Twende Dukani',
      twende_finding_title: 'Recherche de votre position...',
      twende_finding_message: 'Veuillez patienter pendant que nous vous localisons...',
      twende_location_permission: 'L’autorisation de localisation est requise pour naviguer. Veuillez l’autoriser.',
      twende_no_location: 'Cette entreprise n’a pas encore défini de localisation.',
      twende_location_failed: 'Impossible d’obtenir votre position. Vérifiez votre GPS et réessayez.',
      twende_open_error: 'Impossible d’ouvrir Google Maps sur cet appareil.',
    },
    admin_dashboard: {
      logo_permission_denied: 'Autorisation photos refusée. Veuillez l’autoriser dans les Réglages.',
      logo_success_title: 'Logo mis à jour !',
      logo_success: 'Le logo de votre entreprise a été mis à jour.',
      logo_error: 'Impossible de mettre à jour le logo. Veuillez réessayer.',
    },
  },
  hi: {
    profile: {
      photo_permission_denied: 'फोटो अनुमति अस्वीकृत हुई। कृपया सेटिंग्स में अनुमति दें।',
      photo_success_title: 'फोटो अपडेट हुई!',
      photo_success: 'आपकी प्रोफ़ाइल फोटो अपडेट हो गई है।',
      photo_error: 'आपकी फोटो अपडेट नहीं हो सकी। कृपया पुनः प्रयास करें।',
    },
    customer_dashboard: {
      twende_dukani: 'ट्वेंडे दुकानी',
      twende_finding_title: 'आपका स्थान खोजा जा रहा है...',
      twende_finding_message: 'कृपया प्रतीक्षा करें, हम आपको ढूँढ रहे हैं...',
      twende_location_permission: 'नेविगेट करने के लिए स्थान अनुमति आवश्यक है। कृपया इसे अनुमति दें।',
      twende_no_location: 'इस व्यवसाय ने अभी तक स्थान निर्धारित नहीं किया है।',
      twende_location_failed: 'आपका स्थान प्राप्त नहीं हो सका। कृपया अपना GPS जाँचें और पुनः प्रयास करें।',
      twende_open_error: 'इस डिवाइस पर गूगल मैप्स नहीं खोला जा सका।',
    },
    admin_dashboard: {
      logo_permission_denied: 'फोटो अनुमति अस्वीकृत हुई। कृपया सेटिंग्स में अनुमति दें।',
      logo_success_title: 'लोगो अपडेट हुआ!',
      logo_success: 'आपका व्यवसाय लोगो अपडेट हो गया है।',
      logo_error: 'लोगो अपडेट नहीं हो सका। कृपया पुनः प्रयास करें।',
    },
  },
  es: {
    profile: {
      photo_permission_denied: 'Permiso de fotos denegado. Actívalo en Ajustes.',
      photo_success_title: '¡Foto actualizada!',
      photo_success: 'Tu foto de perfil se ha actualizado.',
      photo_error: 'No se pudo actualizar tu foto. Inténtalo de nuevo.',
    },
    customer_dashboard: {
      twende_dukani: 'Twende Dukani',
      twende_finding_title: 'Buscando tu ubicación...',
      twende_finding_message: 'Espera mientras te localizamos...',
      twende_location_permission: 'Se necesita permiso de ubicación para navegar. Actívalo.',
      twende_no_location: 'Este negocio aún no ha establecido una ubicación.',
      twende_location_failed: 'No se pudo obtener tu ubicación. Revisa tu GPS e inténtalo de nuevo.',
      twende_open_error: 'No se pudo abrir Google Maps en este dispositivo.',
    },
    admin_dashboard: {
      logo_permission_denied: 'Permiso de fotos denegado. Actívalo en Ajustes.',
      logo_success_title: '¡Logotipo actualizado!',
      logo_success: 'El logotipo de tu negocio se ha actualizado.',
      logo_error: 'No se pudo actualizar el logotipo. Inténtalo de nuevo.',
    },
  },
  ur: {
    profile: {
      photo_permission_denied: 'تصویر کی اجازت مسترد کر دی گئی۔ براہ کرم ترتیبات میں اجازت دیں۔',
      photo_success_title: 'تصویر اپڈیٹ ہو گئی!',
      photo_success: 'آپ کی پروفائل تصویر اپڈیٹ ہو گئی ہے۔',
      photo_error: 'آپ کی تصویر اپڈیٹ نہیں ہو سکی۔ براہ کرم دوبارہ کوشش کریں۔',
    },
    customer_dashboard: {
      twende_dukani: 'ٹوینڈے دوکانی',
      twende_finding_title: 'آپ کا مقام تلاش کیا جا رہا ہے...',
      twende_finding_message: 'براہ کرم انتظار کریں، ہم آپ کو تلاش کر رہے ہیں...',
      twende_location_permission: 'نیویگیشن کے لیے مقام کی اجازت درکار ہے۔ براہ کرم اجازت دیں۔',
      twende_no_location: 'اس کاروبار نے ابھی مقام مقرر نہیں کیا۔',
      twende_location_failed: 'آپ کا مقام حاصل نہیں ہو سکا۔ براہ کرم اپنا GPS چیک کریں اور دوبارہ کوشش کریں۔',
      twende_open_error: 'اس ڈیوائس پر گوگل میپس نہیں کھولا جا سکا۔',
    },
    admin_dashboard: {
      logo_permission_denied: 'تصویر کی اجازت مسترد کر دی گئی۔ براہ کرم ترتیبات میں اجازت دیں۔',
      logo_success_title: 'لوگو اپڈیٹ ہو گیا!',
      logo_success: 'آپ کے کاروبار کا لوگو اپڈیٹ ہو گیا ہے۔',
      logo_error: 'لوگو اپڈیٹ نہیں ہو سکا۔ براہ کرم دوبارہ کوشش کریں۔',
    },
  },
  de: {
    profile: {
      photo_permission_denied: 'Foto-Berechtigung verweigert. Bitte in den Einstellungen erlauben.',
      photo_success_title: 'Foto aktualisiert!',
      photo_success: 'Ihr Profilfoto wurde aktualisiert.',
      photo_error: 'Ihr Foto konnte nicht aktualisiert werden. Bitte erneut versuchen.',
    },
    customer_dashboard: {
      twende_dukani: 'Twende Dukani',
      twende_finding_title: 'Standort wird gesucht...',
      twende_finding_message: 'Bitte warten, wir orten Sie...',
      twende_location_permission: 'Für die Navigation ist eine Standortberechtigung erforderlich. Bitte erlauben.',
      twende_no_location: 'Dieses Geschäft hat noch keinen Standort festgelegt.',
      twende_location_failed: 'Ihr Standort konnte nicht ermittelt werden. Bitte GPS prüfen und erneut versuchen.',
      twende_open_error: 'Google Maps konnte auf diesem Gerät nicht geöffnet werden.',
    },
    admin_dashboard: {
      logo_permission_denied: 'Foto-Berechtigung verweigert. Bitte in den Einstellungen erlauben.',
      logo_success_title: 'Logo aktualisiert!',
      logo_success: 'Ihr Geschäftslogo wurde aktualisiert.',
      logo_error: 'Das Logo konnte nicht aktualisiert werden. Bitte erneut versuchen.',
    },
  },
  zh: {
    profile: {
      photo_permission_denied: '照片权限被拒绝。请在设置中允许。',
      photo_success_title: '照片已更新！',
      photo_success: '您的资料照片已更新。',
      photo_error: '无法更新您的照片。请重试。',
    },
    customer_dashboard: {
      twende_dukani: 'Twende Dukani',
      twende_finding_title: '正在查找您的位置...',
      twende_finding_message: '请稍候，我们正在定位您...',
      twende_location_permission: '导航需要位置权限。请允许。',
      twende_no_location: '该企业尚未设置位置。',
      twende_location_failed: '无法获取您的位置。请检查 GPS 并重试。',
      twende_open_error: '无法在此设备上打开谷歌地图。',
    },
    admin_dashboard: {
      logo_permission_denied: '照片权限被拒绝。请在设置中允许。',
      logo_success_title: '标志已更新！',
      logo_success: '您的企业标志已更新。',
      logo_error: '无法更新标志。请重试。',
    },
  },
};

// Insert keys just before the closing brace of a section object.
// The closing brace is identified by indentation (2 spaces = top-level section
// end), so strings containing { } placeholders can never confuse the matcher.
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

  // The section's last key may lack a trailing comma — add one so the injected
  // keys below it parse correctly.
  if (endIdx > 0 && !/,\s*$/.test(lines[endIdx - 1])) {
    lines[endIdx - 1] = lines[endIdx - 1] + ',';
  }

  const indent = '    ';
  const newLines = Object.entries(keys).map(([k, v]) => {
    const escaped = String(v).replace(/"/g, '\\"');
    return `${indent}"${k}": "${escaped}",`;
  });
  // Strict JSON forbids trailing commas — the LAST inserted key must not end
  // with one (it is followed by the section's closing brace).
  newLines[newLines.length - 1] = newLines[newLines.length - 1].replace(/,$/, '');
  lines.splice(endIdx, 0, ...newLines);
  return lines.join('\n');
}

function main() {
  for (const lang of LANGS) {
    const filePath = path.join(__dirname, '..', 'locales', `${lang}.json`);
    let text = fs.readFileSync(filePath, 'utf8');

    // 1. Merge keys into existing sections
    const extra = EXTRA[lang];
    for (const section of ['profile', 'customer_dashboard', 'admin_dashboard']) {
      if (extra[section]) {
        text = insertIntoSection(text, section, extra[section]);
      }
    }

    // 2. Insert the new top-level "onboarding" section before "otp"
    const onboarding = ONBOARDING[lang];
    const onboardingLines = Object.entries(onboarding).map(([k, v]) => {
      const escaped = String(v).replace(/"/g, '\\"');
      return `    "${k}": "${escaped}",`;
    });
    // No trailing comma on the last key (followed by the section's closing brace)
    onboardingLines[onboardingLines.length - 1] = onboardingLines[onboardingLines.length - 1].replace(/,$/, '');
    const onboardingBlock = `  "onboarding": {\n${onboardingLines.join('\n')}\n  },\n`;
    const otpIdx = text.indexOf('  "otp": {');
    if (otpIdx === -1) {
      console.warn(`  ⚠️ "otp" section not found in ${lang}.json — onboarding NOT inserted`);
    } else {
      text = text.slice(0, otpIdx) + onboardingBlock + text.slice(otpIdx);
    }

    // 3. Validate + write
    try {
      JSON.parse(text);
    } catch (err) {
      console.error(`  ❌ ${lang}.json is INVALID after injection:`, err.message);
      const m = String(err.message).match(/position (\d+)/);
      const pos = m ? Number(m[1]) : 0;
      const before = text.slice(0, pos);
      const line = (before.match(/\n/g) || []).length + 1;
      const allLines = text.split('\n');
      for (let i = Math.max(0, line - 8); i < Math.min(allLines.length, line + 4); i++) {
        console.error(`    ${i + 1}: ${JSON.stringify(allLines[i])}`);
      }
      process.exit(1);
    }
    fs.writeFileSync(filePath, text);
    console.log(`  ✅ ${lang}.json updated`);
  }
  console.log('🎉 All locale files updated.');
}

main();
