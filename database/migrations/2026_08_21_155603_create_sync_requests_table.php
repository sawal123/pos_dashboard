<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('device_id');
            $table->uuid('request_id');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['business_id', 'device_id', 'request_id']);

            $table->foreign(['business_id', 'device_id'])
                ->references(['business_id', 'id'])
                ->on('devices')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_requests');
    }
};
