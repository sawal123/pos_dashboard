<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('tax_enabled')->default(false)->after('business_type');
            $table->decimal('tax_rate', 5, 2)->default(11.00)->after('tax_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['tax_enabled', 'tax_rate']);
        });
    }
};

