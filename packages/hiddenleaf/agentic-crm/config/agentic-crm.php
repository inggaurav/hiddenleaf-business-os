<?php

return [
    'gideon' => [
        'base_url' => env('GIDEON_AGENT_URL', ''),
        'hmac_secret' => env('GIDEON_AGENT_HMAC_SECRET', ''),
        'timeout_seconds' => (int) env('GIDEON_AGENT_TIMEOUT', 45),
        'allow_insecure_http' => (bool) env('GIDEON_AGENT_ALLOW_HTTP', false),
    ],
    'worker' => [
        'lease_seconds' => (int) env('AGENT_TASK_LEASE_SECONDS', 120),
        'max_batch' => (int) env('AGENT_TASK_MAX_BATCH', 25),
    ],
];
