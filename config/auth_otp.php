<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP policy
    |--------------------------------------------------------------------------
    |
    | These values are infrastructure defaults only. AUTH-D03 is responsible
    | for deciding when a registration is allowed to use this service.
    |
    */

    'purpose' => 'email_verification',
    'code_length' => 6,
    'ttl_minutes' => 10,
    'max_attempts' => 5,
    'resend_cooldown_seconds' => 60,

    /*
    | The configured key is preferred. When it is absent, the service derives
    | a domain-separated key from APP_KEY. Both paths fail closed if no key is
    | available. Never put the resulting value in logs or source control.
    */
    'key' => env('AUTH_OTP_KEY'),

    /*
    | When AUTH-D03 validates an invalid code inside its provisioning
    | transaction, it must use a separate connection to make failed-attempt
    | accounting durable. The connection must point at the same database and
    | use the same schema as the default connection.
    */
    'connection' => env('AUTH_OTP_DB_CONNECTION'),

    /*
    | `log`, `array`, `failover`, and `roundrobin` are intentionally rejected
    | for real delivery because they can expose or discard an OTP. Mail::fake()
    | is allowed by the service for automated tests.
    */
    'mailer' => env('AUTH_OTP_MAILER', env('MAIL_MAILER', 'log')),
    'allowed_mailers' => [
        'smtp',
        'ses',
        'postmark',
        'resend',
        'sendmail',
    ],

    /*
    | Rate limits are applied by the reusable service, not by an HTTP route.
    | Keys contain keyed digests rather than plaintext email addresses.
    */
    'rate_limits' => [
        'issue_subject' => ['max_attempts' => 5, 'decay_seconds' => 600],
        'issue_email' => ['max_attempts' => 5, 'decay_seconds' => 600],
        'issue_ip' => ['max_attempts' => 20, 'decay_seconds' => 600],
        'resend_subject' => ['max_attempts' => 10, 'decay_seconds' => 3600],
        'resend_email' => ['max_attempts' => 10, 'decay_seconds' => 3600],
        'resend_ip' => ['max_attempts' => 30, 'decay_seconds' => 3600],
        'verify_subject' => ['max_attempts' => 30, 'decay_seconds' => 600],
        'verify_ip' => ['max_attempts' => 60, 'decay_seconds' => 600],
    ],

];
