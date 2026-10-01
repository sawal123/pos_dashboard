<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Premium product policy (PREM-D02A — FINAL)
    |--------------------------------------------------------------------------
    |
    | Product decisions that are already final. This section is policy only: it
    | never carries a price. Official prices live in the `pricing` section below
    | and remain undecided (BLOCKER).
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
    | Pricing configuration (PREM-D02A — UNDECIDED, BLOCKER)
    |--------------------------------------------------------------------------
    |
    | No official price exists. Do not invent one, and never copy numbers from a
    | mockup. While pricing is unconfigured the mobile plan catalog stays empty
    | and `checkout_available` stays false.
    |
    | Expected plan shape once official data exists: canonical `code`, display
    | `name`, and official periods with `period` (`monthly`/`yearly`), `currency`
    | and integer `price_minor`.
    |
    | `pricing.mobile_plans` is the canonical location. The legacy top-level
    | `mobile_plans` key is still honoured as a fallback so the PREM-D01 contract
    | keeps working (no breaking change).
    */
    'pricing' => [
        'configured' => env('PREMIUM_PRICING_CONFIGURED', false),
        'mobile_plans' => [
            [
                'code' => 'cloud',
                'name' => 'Cloud',
                'billing_periods' => array_values(array_filter([
                    blank(env('PREMIUM_CLOUD_MONTHLY_PRICE_MINOR')) ? null : [
                        'period' => 'monthly',
                        'currency' => 'IDR',
                        'price_minor' => (int) env('PREMIUM_CLOUD_MONTHLY_PRICE_MINOR'),
                    ],
                    blank(env('PREMIUM_CLOUD_YEARLY_PRICE_MINOR')) ? null : [
                        'period' => 'yearly',
                        'currency' => 'IDR',
                        'price_minor' => (int) env('PREMIUM_CLOUD_YEARLY_PRICE_MINOR'),
                    ],
                ])),
                'benefits' => [
                    'Web dashboard',
                    'Cloud sync',
                    'Cloud devices',
                ],
                'available' => true,
            ],
        ],
    ],

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
