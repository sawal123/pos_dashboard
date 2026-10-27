<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_change_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')
                ->constrained('personal_access_tokens')
                ->cascadeOnDelete();
            $table->string('original_email', 320);
            $table->timestamp('original_email_verified_at')->nullable();
            $table->string('new_email', 320);
            $table->string('status', 32)->default('pending_verification');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'email_change_requests_user_status_idx');
            $table->index(['new_email', 'status'], 'email_change_requests_email_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_requests');
    }
};
