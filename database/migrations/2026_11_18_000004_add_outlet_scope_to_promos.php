<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->string('outlet_scope', 20)->default('all')->after('scope');
        });

        Schema::create('promo_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promo_id', 'outlet_id']);
            $table->index(['business_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_outlets');

        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn('outlet_scope');
        });
    }
};
