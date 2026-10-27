<?php

return [
    'token_ttl_days' => (int) env('SANCTUM_MOBILE_TOKEN_TTL_DAYS', 30),
    'challenge_ttl_minutes' => min((int) env('MOBILE_AUTH_CHALLENGE_TTL_MINUTES', 5), 5),
    'challenge_max_attempts' => 5,
];
