<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MULTI-OUTLET — per-branch catalog overrides.
 *
 * A missing row (or NULL price) means "follow the master product". `stock` is a
 * foundation column for a future per-outlet stock mode and is not read while
 * `businesses.stock_scope = shared`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('promo_price')->nullable();
            $table->boolean('is_available')->default(true);
            $table->decimal('stock', 15, 3)->nullable();
            $table->decimal('min_stock', 15, 3)->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'product_id']);
            $table->index(['business_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_product');
    }
};
