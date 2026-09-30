<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile premium catalog
    |--------------------------------------------------------------------------
    |
    | Pricing, billing periods, renewal rules and checkout are intentionally
    | undecided in the backend today. Keep the public mobile catalog empty until
    | Product/Billing records an official source of truth here or in database.
    |
    | Expected plan shape when official data exists:
    | Each configured plan must provide canonical `code`, display `name`, and
    | official backend-owned billing periods with `period`, `currency`, and
    | integer `price_minor` values.
    */
    'mobile_plans' => [],
];
