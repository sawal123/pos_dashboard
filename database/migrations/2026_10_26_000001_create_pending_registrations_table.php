<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('normalized_email', 320)->unique();
            $table->char('idempotency_key_hash', 64)->unique();
            $table->char('request_fingerprint', 64);
            $table->string('business_name_intent', 255);
            $table->string('status', 32)->default('pending_email');
            $table->string('provisioning_status', 32)->default('pending');
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'provisioning_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
