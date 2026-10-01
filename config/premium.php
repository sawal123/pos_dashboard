<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Premium product policy (PREM-D02A — FINAL)
    |--------------------------------------------------------------------------
    |
    | Product decisions that are already final. This section is policy only: it
    | never carries a price. Official prices are database-owned since PREM-D02C
    | (`subscription_plans` / `subscription_plan_prices`) and read by
    | App\Services\Subscription\PremiumPricing.
    |
    | - Canonical paid plan  : `cloud`
    | - Billing periods      : `monthly`, `yearly`
    | - Payment provider     : Midtrans (integration is PREM-D02B)
    | - Renewal              : manual — there is no auto-renewal
    | - Device limit         : 5 cloud devices per active Cloud business
    |
    | The server is the authoritative source of entitlement; see
    | App\Services\Subscription\PremiumPolicy.
    */
    'plan' => [
        'code' => 'cloud',
        'billing_periods' => ['monthly', 'yearly'],
        'payment_provider' => 'midtrans',
        'renewal_mode' => 'manual',
        'device_limit' => 5,

        /*
        | Descriptive product copy for the paid plan (presentation only). This is
        | not pricing: it never carries an amount, and it is surfaced by
        | PremiumPolicy::benefits(). Amounts live in the database (PREM-D02C).
        */
        'benefits' => [
            'Web dashboard',
            'Cloud sync',
            'Cloud devices',
        ],

        /*
        | A paid Cloud subscription activated through checkout must carry an
        | explicit `expires_at`. This is an activation invariant for PREM-D02B;
        | legacy rows with a null `expires_at` are deliberately NOT rewritten
        | (no destructive data migration in PREM-D02A).
        */
        'requires_expiry_on_activation' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Official Cloud capabilities
    |--------------------------------------------------------------------------
    |
    | All-or-nothing: every capability is denied unless the business holds an
    | active Cloud entitlement. Declaring a capability here grants nothing by
    | itself — PremiumPolicy::allows() still requires `Business::hasCloudAccess()`.
    |
    | `cloud_backup` and `cloud_restore` are declared product capabilities with no
    | backend implementation yet, so they stay denied for everyone until the
    | feature exists.
    */
    'capabilities' => [
        'web_dashboard',
        'cloud_backup',
        'cloud_restore',
        'cloud_sync',
        'cloud_devices',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing — database-owned since PREM-D02C
    |--------------------------------------------------------------------------
    |
    | There is deliberately no pricing key here. Official prices live in the
    | database (`subscription_plans` / `subscription_plan_prices`), are managed
    | by a Platform Admin, and are read only by
    | App\Services\Subscription\PremiumPricing. ENV is no longer a price source.
    |
    | While a plan has no active price the mobile catalog stays empty and
    | `checkout_available` stays false (fail closed).
    */

    /*
    |--------------------------------------------------------------------------
    | Midtrans checkout configuration
    |--------------------------------------------------------------------------
    |
    | Server key is backend-only. It must never be sent to mobile clients,
    | rendered in Blade, logged, or persisted to the database.
    */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],
];
