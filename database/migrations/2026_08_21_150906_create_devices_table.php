<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('outlet_id');
            $table->string('name');
            $table->string('identifier');
            $table->string('platform')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('registered_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'identifier']);
            $table->unique(['business_id', 'id']);

            $table->foreign(['business_id', 'outlet_id'])
                ->references(['business_id', 'id'])
                ->on('outlets')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
