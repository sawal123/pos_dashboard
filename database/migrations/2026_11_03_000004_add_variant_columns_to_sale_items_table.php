<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('variant_summary', 255)->nullable()->after('product_name');
            $table->json('variants_snapshot')->nullable()->after('variant_summary');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['variant_summary', 'variants_snapshot']);
        });
    }
};

