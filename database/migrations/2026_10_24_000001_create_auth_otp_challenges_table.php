<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_otp_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('subject_reference', 255);
            $table->string('purpose', 64);
            $table->string('email', 320);
            $table->char('otp_digest', 64);
            $table->char('challenge_nonce', 64);

            // Nullable unique values allow unlimited historical rows while
            // guaranteeing one active challenge per subject/purpose pair.
            $table->string('active_key', 320)->nullable()->unique();

            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('resend_available_at');
            $table->timestamp('last_sent_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subject_reference', 'purpose']);
            $table->index(['email', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_otp_challenges');
    }
};
