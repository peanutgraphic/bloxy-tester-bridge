<?php

return [
    'mode' => env('TESTER_MODE', false),
    'shared_secret' => env('TESTER_SHARED_SECRET'),
    'allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('TESTER_ALLOWED_IPS', '')))),
    'token_ttl_seconds' => 300,
    'clock_skew_seconds' => 60,
    'scenarios_path' => env('TESTER_SCENARIOS_PATH'),
    'scenarios_cache_seconds' => 60,
];
