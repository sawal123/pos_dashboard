<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('promo_sync_id')->nullable()->after('discount_amount');
            $table->index(['business_id', 'promo_sync_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'promo_sync_id']);
            $table->dropColumn('promo_sync_id');
        });
    }
};
