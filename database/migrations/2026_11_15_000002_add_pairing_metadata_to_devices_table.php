<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security metadata captured when a cashier device is paired with a code.
 *
 * Stored on the device row (not in `notes`) so the dashboard can audit which
 * cashier paired a terminal, from which IP/user agent, and with which app
 * version — without parsing free text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('paired_user_id')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->timestamp('paired_at')->nullable()->after('paired_user_id');
            $table->string('last_ip', 45)->nullable()->after('paired_at');
            $table->text('user_agent')->nullable()->after('last_ip');
            $table->string('app_version', 40)->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paired_user_id');
            $table->dropColumn(['paired_at', 'last_ip', 'user_agent', 'app_version']);
        });
    }
};
