<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public SEO defaults
    |--------------------------------------------------------------------------
    |
    | The application has one public marketing surface today (the homepage).
    | Authenticated and operational screens opt out of indexing explicitly in
    | the shared layout.
    */
    'site_name' => env('SEO_SITE_NAME', 'NexaPOS'),
    'title' => env('SEO_TITLE', 'NexaPOS - Sistem POS untuk Operasional Bisnis'),
    'description' => env(
        'SEO_DESCRIPTION',
        'NexaPOS membantu bisnis mengelola penjualan, produk, stok, laporan, outlet, dan sinkronisasi Cloud dalam satu platform POS.',
    ),
    'keywords' => env(
        'SEO_KEYWORDS',
        'sistem POS, aplikasi kasir, software kasir, manajemen stok, laporan penjualan, POS Indonesia',
    ),
    'locale' => env('SEO_LOCALE', 'id_ID'),
    'indexable' => filter_var(env('SEO_INDEXABLE', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOL),
    'image' => env('SEO_IMAGE'),
];
