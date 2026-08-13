<?php

return [
    'private_key' => env('LICENSING_PRIVATE_KEY'),
    'public_key' => env('LICENSING_PUBLIC_KEY'),
    'key_id' => env('LICENSING_KEY_ID', 'primary'),
    'grace_period_days' => (int) env('LICENSING_GRACE_PERIOD_DAYS', 14),
    'token_ttl_days' => (int) env('LICENSING_TOKEN_TTL_DAYS', 30),
];
