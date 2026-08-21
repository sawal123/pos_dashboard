<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->unique(['business_id', 'id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['business_id', 'id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['business_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'id']);
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'id']);
        });
    }
};
