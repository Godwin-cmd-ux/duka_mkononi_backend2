<?php

return [
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    'api_key' => env('GEMINI_API_KEY', ''),
];