<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 40)->nullable();
            $table->string('discount_type', 20);
            $table->unsignedInteger('discount_value');
            $table->string('scope', 20)->default('all');
            $table->unsignedBigInteger('min_purchase')->default(0);
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->json('weekdays')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('start_time', 5)->nullable();
            $table->string('end_time', 5)->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_stackable')->default(false);
            $table->string('status', 20)->default('draft');
            $table->uuid('sync_id')->nullable();
            $table->unsignedBigInteger('sync_version')->default(1);
            $table->unsignedBigInteger('sync_sequence')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->unique(['business_id', 'id']);
            $table->unique(['business_id', 'sync_id']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'starts_at', 'ends_at']);
            $table->index(['business_id', 'sync_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
