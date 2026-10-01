<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->string('provider_order_id')->unique();
            $table->string('idempotency_key')->nullable();
            $table->string('plan');
            $table->string('billing_period');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending');
            $table->text('snap_token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('provider_payment_type')->nullable();
            $table->string('provider_transaction_status')->nullable();
            $table->string('provider_fraud_status')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_id', 'idempotency_key']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
