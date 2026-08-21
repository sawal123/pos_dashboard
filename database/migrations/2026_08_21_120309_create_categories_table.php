<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->unique(['business_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
