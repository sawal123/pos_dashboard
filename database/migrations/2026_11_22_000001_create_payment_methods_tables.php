<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('code', 50);
                $table->string('category', 30)->default('other'); // cash, qris, transfer, card, ewallet, online_food, other
                $table->boolean('is_cash')->default(false);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('description', 255)->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'code']);
                $table->index(['business_id', 'is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('outlet_payment_method')) {
            Schema::create('outlet_payment_method', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->unique(['outlet_id', 'payment_method_id']);
                $table->index(['business_id', 'outlet_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_payment_method');
        Schema::dropIfExists('payment_methods');
    }
};
