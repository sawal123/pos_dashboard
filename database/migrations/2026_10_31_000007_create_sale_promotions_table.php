<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('promo_id');
            $table->uuid('promo_sync_id');
            $table->unsignedBigInteger('discount_amount');
            $table->timestamp('created_at')->nullable();

            $table->unique(['business_id', 'sale_id', 'promo_id']);
            $table->index(['business_id', 'promo_id']);

            $table->foreign(['business_id', 'sale_id'])
                ->references(['business_id', 'id'])
                ->on('sales')
                ->cascadeOnDelete();

            $table->foreign(['business_id', 'promo_id'])
                ->references(['business_id', 'id'])
                ->on('promos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_promotions');
    }
};
