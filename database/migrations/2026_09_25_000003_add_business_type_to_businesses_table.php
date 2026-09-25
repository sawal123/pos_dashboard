<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DASH-14 — persisted business type.
 *
 * Additive and nullable with NO default: every existing business keeps a NULL
 * business type ("unknown") until an owner explicitly chooses one. No data is
 * changed, dropped or backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('business_type')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
