<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update database column default for min_stock to 0
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('min_stock', 15, 3)->default(0)->change();
        });

        // 2. Safely update existing products where min_stock was defaulted to 5
        DB::table('products')
            ->where('min_stock', 5)
            ->update([
                'min_stock' => 0,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert existing 0 min_stock to 5
        DB::table('products')
            ->where('min_stock', 0)
            ->update([
                'min_stock' => 5,
            ]);

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('min_stock', 15, 3)->default(5)->change();
        });
    }
};
