<?php

// EMAIL_FROM is a legacy variable that sometimes holds a full mailbox string
// ("Name <address>"). Symfony rejects that as an addr-spec, which used to make
// EVERY OTP/password-reset email fail before it even reached the transport.
// Split it into a bare address plus an optional display name here so both the
// legacy and the current variables always produce a valid From header.
$fromAddress = env('MAIL_FROM_ADDRESS') ?: env('EMAIL_FROM') ?: 'no-reply@dukamkononi.com';
$fromName = env('MAIL_FROM_NAME') ?: 'DukaMkononi';

if (is_string($fromAddress) && preg_match('/^\s*(.*?)\s*<([^>]+)>\s*$/', $fromAddress, $matches)) {
    $fromAddress = trim($matches[2]);
    if (trim($matches[1]) !== '') {
        $fromName = trim($matches[1], " \"'");
    }
}

$fromAddress = $fromAddress ?: 'no-reply@dukamkononi.com';

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
        'address' => $fromAddress,
        'name' => $fromName,
    ],

    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];