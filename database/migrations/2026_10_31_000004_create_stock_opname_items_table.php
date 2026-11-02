<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('stock_opname_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('system_stock', 15, 3)->default(0);
            $table->decimal('counted_stock', 15, 3)->nullable();
            $table->decimal('variance', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'stock_opname_id', 'product_id']);
            $table->index(['business_id', 'stock_opname_id']);

            $table->foreign(['business_id', 'stock_opname_id'])
                ->references(['business_id', 'id'])
                ->on('stock_opnames')
                ->cascadeOnDelete();

            $table->foreign(['business_id', 'product_id'])
                ->references(['business_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
