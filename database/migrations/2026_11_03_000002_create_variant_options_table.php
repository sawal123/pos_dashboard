<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('variant_group_id')->constrained('variant_groups')->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('price_adjustment')->default(0);
            $table->integer('cost_adjustment')->default(0);
            $table->foreignId('ingredient_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('ingredient_quantity', 12, 4)->default(0.0000);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->uuid('sync_id')->nullable();
            $table->unsignedBigInteger('sync_version')->default(1);
            $table->unsignedBigInteger('sync_sequence')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'sync_id']);
            $table->index(['business_id', 'variant_group_id', 'status']);
            $table->index(['business_id', 'ingredient_product_id']);
            $table->index(['business_id', 'sync_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_options');
    }
};
