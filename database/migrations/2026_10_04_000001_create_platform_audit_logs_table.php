<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ADMIN-13: Permanent, append-only Platform Admin audit log.
     * Rows are created on successful administrative mutations and never updated or deleted.
     */
    public function up(): void
    {
        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();

            // Actor references (nullable FK + immutable snapshot)
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_email');

            // Action machine identifier
            $table->string('action', 60);

            // Generic target references (no cascade delete to preserve audit history)
            $table->string('target_type', 40);
            $table->string('target_id')->nullable();
            $table->string('target_label')->nullable();

            // Optional business correlation context
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();

            // JSON state snapshots & safe metadata
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();

            // Append-only timestamp
            $table->timestamp('created_at')->nullable();

            // Targeted indexes for audit search, filtering, and correlation
            $table->index('actor_user_id');
            $table->index('action');
            $table->index('target_type');
            $table->index('business_id');
            $table->index('created_at');
            $table->index(['target_type', 'target_id']);
            $table->index(['business_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
    }
};
