<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_auth_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('challenge_digest', 64)->unique('mobile_auth_challenges_digest_unique');
            $table->string('login_method', 32);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at'], 'mobile_auth_challenges_user_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_auth_challenges');
    }
};
