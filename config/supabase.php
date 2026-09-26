<?php

return [
    'url' => rtrim((string) env('SUPABASE_URL', ''), '/'),
    'anon_key' => (string) env('SUPABASE_ANON_KEY', ''),
    'service_role_key' => (string) env('SUPABASE_SERVICE_ROLE_KEY', ''),
    'timeout' => (int) env('SUPABASE_TIMEOUT', 20),
];