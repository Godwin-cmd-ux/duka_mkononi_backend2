<?php

return [
    // Cloudinary account used for browser-side unsigned uploads (profile
    // photos, matangazo media). These two values are NOT secrets — they ship
    // in page HTML by design of unsigned uploads — but they are configurable
    // per environment anyway. The API key/secret (media deletion) stay
    // server-side only and are read directly from env in AdvertisementController.
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET', 'react_native_uploads'),

    // Server-side credentials: db backups upload/delete raw SQL files. They are
    // secrets and never reach the browser (unsigned uploads use the public
    // cloud_name + upload_preset above instead).
    'api_key' => env('CLOUDINARY_API_KEY', ''),
    'api_secret' => env('CLOUDINARY_API_SECRET', ''),

    // Root folder holding every schema+seed backup pair, per backup folder
    // beneath it. Override per environment if the history should live elsewhere.
    'backup_folder' => env('CLOUDINARY_BACKUP_FOLDER', 'dukamkononi/backups'),

    // Seconds a backup upload may take. A seed is the whole database; a slow
    // uplink is normal and timing out mid-seed leaves nothing usable behind.
    'timeout' => (int) env('CLOUDINARY_TIMEOUT', 900),
];
