<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('recorded');
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['business_id', 'outlet_id'])
                ->references(['business_id', 'id'])
                ->on('outlets')
                ->restrictOnDelete();

            $table->foreign(['business_id', 'outlet_id', 'shift_id'])
                ->references(['business_id', 'outlet_id', 'id'])
                ->on('shifts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
