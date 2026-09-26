<?php

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'host' => env('EMAIL_HOST', env('MAIL_HOST', 'smtp.mailtrap.io')),
            'port' => env('EMAIL_PORT', env('MAIL_PORT', 587)),
            'encryption' => env('EMAIL_SECURE') ? 'ssl' : (env('MAIL_ENCRYPTION', 'tls')),
            'username' => env('EMAIL_USER', env('MAIL_USERNAME')),
            'password' => env('EMAIL_PASSWORD', env('MAIL_PASSWORD')),
            'timeout' => 30,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
            'verify_peer' => false,
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    'from' => [
        'address' => env('EMAIL_FROM', env('MAIL_FROM_ADDRESS', 'no-reply@dukamkononi.com')),
        'name' => env('MAIL_FROM_NAME', 'DukaMkononi'),
    ],

    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];