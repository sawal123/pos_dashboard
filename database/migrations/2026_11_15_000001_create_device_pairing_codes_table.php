<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kasir device pairing (kode verifikasi 4 digit, berlaku 30 menit).
 *
 * The owner generates a short-lived code in the dashboard and hands it to the
 * cashier, who redeems it from the POS app. Only the hash of the code is stored,
 * so a database leak cannot be replayed into a device registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('outlet_id');
            $table->string('code_hash', 64);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'code_hash']);
            $table->index(['business_id', 'outlet_id', 'used_at', 'expires_at'], 'device_pairing_codes_active_index');

            $table->foreign(['business_id', 'outlet_id'])
                ->references(['business_id', 'id'])
                ->on('outlets')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_pairing_codes');
    }
};
