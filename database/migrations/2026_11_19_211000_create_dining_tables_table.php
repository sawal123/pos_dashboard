<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dining_tables')) {
            Schema::create('dining_tables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('outlet_id')->nullable();
                $table->string('name', 100);
                $table->unsignedInteger('capacity')->nullable()->default(4);
                $table->string('status', 50)->default('available');
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['business_id', 'outlet_id']);
                $table->foreign('outlet_id')->references('id')->on('outlets')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
