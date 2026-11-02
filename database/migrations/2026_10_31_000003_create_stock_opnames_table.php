<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('status', 20)->default('draft');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->uuid('sync_id')->nullable();
            $table->unsignedBigInteger('sync_version')->default(1);
            $table->unsignedBigInteger('sync_sequence')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->unique(['business_id', 'id']);
            $table->unique(['business_id', 'sync_id']);
            $table->index(['business_id', 'status', 'created_at']);
            $table->index(['business_id', 'sync_sequence']);

            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
