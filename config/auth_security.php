<?php

return [
    'audit_channel' => env('AUTH_AUDIT_LOG_CHANNEL', 'stack'),
    'schema_connection' => env('AUTH_PREFLIGHT_SCHEMA_CONNECTION'),

    'login_rate_limit' => [
        'max_attempts' => 10,
        'decay_seconds' => 60,
    ],
];
