<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->unsignedBigInteger('price');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);

            $table->foreign(['business_id', 'category_id'])
                ->references(['business_id', 'id'])
                ->on('categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
