<?php

/*
 * QA-only configuration.
 *
 * `allowed_databases` is the explicit allow-list of isolated databases that the
 * QA seeders (QaB2FixtureSeeder, P37E2EResetSeeder) may touch. Anything else is
 * refused by App\Support\QaDatabaseGuard before a single row is changed.
 */
return [
    'allowed_databases' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('QA_ALLOWED_DATABASES', 'pos_qa_b2')),
    ))),
];
