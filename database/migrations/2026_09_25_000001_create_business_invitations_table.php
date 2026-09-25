<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role')->default('member');
            $table->string('token_hash', 64)->unique();
            $table->string('status', 20)->default('pending');
            // Deterministic key that is only set while an invitation is pending,
            // so the database itself guarantees one active invitation per
            // business + email (multiple NULLs are allowed by MySQL and SQLite).
            $table->string('active_key', 64)->nullable()->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_invitations');
    }
};
