<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('features')->nullable()->after('description');
        });

        // Set default features for canonical Cloud plan if present
        DB::table('subscription_plans')->where('code', 'cloud')->update([
            'features' => json_encode([
                'Semua fitur Paket Free',
                'Sinkronisasi Cloud otomatis & real-time',
                'Web Dashboard analitik laba rugi',
                'Varian Menu (Cup, Sugar, Level)',
                'Hingga 5 perangkat kasir terhubung',
                'Manajemen Promo & Diskon kasir',
                'Backup & restore data cloud aman',
            ]),
        ]);

        // Set default features for Free plan if present
        DB::table('subscription_plans')->where('code', 'free')->update([
            'features' => json_encode([
                'Aplikasi POS kasir offline cepat',
                'Transaksi & cetak struk tanpa internet',
                'Katalog produk & kategori lokal',
                'Hitung HPP & pantau stok bahan baku',
                '1 perangkat kasir lokal',
            ]),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
