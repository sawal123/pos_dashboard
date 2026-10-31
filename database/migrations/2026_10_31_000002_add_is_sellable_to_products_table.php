<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Non-sellable items are tracked in inventory (raw materials,
            // supplies) but must never be sold from the POS.
            $table->boolean('is_sellable')->default(true)->after('kind');
            $table->index(['business_id', 'is_sellable']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'is_sellable']);
            $table->dropColumn('is_sellable');
        });
    }
};
