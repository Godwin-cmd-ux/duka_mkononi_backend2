<?php

return [
    // Cloudinary account used for browser-side unsigned uploads (profile
    // photos, matangazo media). These two values are NOT secrets — they ship
    // in page HTML by design of unsigned uploads — but they are configurable
    // per environment anyway. The API key/secret (media deletion) stay
    // server-side only and are read directly from env in AdvertisementController.
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET', 'react_native_uploads'),
];
