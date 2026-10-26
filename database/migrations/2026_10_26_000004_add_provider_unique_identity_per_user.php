<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_identities', function (Blueprint $table): void {
            $table->unique(['user_id', 'provider'], 'auth_identities_user_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::table('auth_identities', function (Blueprint $table): void {
            $table->dropUnique('auth_identities_user_provider_unique');
        });
    }
};
