<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MULTI-OUTLET — one subscription per outlet.
 *
 * `outlet_id = NULL` / `scope_key = primary` is the legacy business-level row
 * and covers the primary outlet. Every additional branch gets its own row with
 * `scope_key = outlet:{id}`. The composite unique keeps "one subscription per
 * scope" enforced by the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('outlet_id')->nullable()->after('business_id');
            $table->string('scope_key', 40)->default('primary')->after('outlet_id');
        });

        // Add the composite unique first so MySQL keeps an index usable by the
        // business_id foreign key, then drop the old single-column unique.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique(['business_id', 'scope_key']);
            $table->index('outlet_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['business_id']);
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('outlet_id')->nullable()->after('business_id');
            $table->index(['business_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'outlet_id']);
            $table->dropColumn('outlet_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique(['business_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'scope_key']);
            $table->dropIndex(['outlet_id']);
            $table->dropColumn(['outlet_id', 'scope_key']);
        });
    }
};
