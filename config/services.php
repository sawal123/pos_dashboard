<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'android_client_ids' => array_values(array_filter(array_map(
            static fn (string $clientId): string => trim($clientId),
            explode(',', (string) env('GOOGLE_ANDROID_CLIENT_IDS', '')),
        ))),
        'jwks_url' => 'https://www.googleapis.com/oauth2/v3/certs',
        'issuer' => 'https://accounts.google.com',
        'clock_skew_seconds' => (int) env('GOOGLE_CLOCK_SKEW_SECONDS', 60),
        'max_token_lifetime_seconds' => (int) env('GOOGLE_MAX_TOKEN_LIFETIME_SECONDS', 3600),
        'replay_ttl_seconds' => 3600,
    ],

];
