<?php

return [
    // No fallback: if JWT_SECRET is missing the app must fail loudly rather
    // than silently sign tokens with a publicly-known secret.
    'secret' => env('JWT_SECRET', ''),
    'algo' => 'HS256',
];