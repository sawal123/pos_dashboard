<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only membership audit log: no updated_at, rows are never
        // modified or deleted by the application once written.
        Schema::create('membership_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_email')->nullable();
            $table->string('action', 50);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_audit_logs');
    }
};
