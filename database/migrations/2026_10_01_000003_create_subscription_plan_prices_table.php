<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('billing_period');
            $table->char('currency', 3);
            $table->unsignedBigInteger('price_minor');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'billing_period', 'currency']);
            $table->index(['subscription_plan_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_prices');
    }
};
