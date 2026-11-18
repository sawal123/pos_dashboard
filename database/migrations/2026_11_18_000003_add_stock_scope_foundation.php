<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MULTI-OUTLET — stock foundation.
 *
 * Stock stays shared across outlets (`stock_scope = shared`), but every stock
 * movement and opname now records the outlet it happened at so a future
 * `per_outlet` mode can rebuild per-branch balances from history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('stock_scope', 20)->default('shared');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('outlet_id')->nullable()->after('business_id');
            $table->index(['business_id', 'outlet_id']);
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->unsignedBigInteger('outlet_id')->nullable()->after('business_id');
            $table->index(['business_id', 'outlet_id']);
        });

        $primary = DB::table('outlets')->where('is_primary', true)->pluck('id', 'business_id');

        foreach ($primary as $businessId => $outletId) {
            DB::table('stock_movements')->where('business_id', $businessId)->whereNull('outlet_id')->update(['outlet_id' => $outletId]);
            DB::table('stock_opnames')->where('business_id', $businessId)->whereNull('outlet_id')->update(['outlet_id' => $outletId]);
        }
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'outlet_id']);
            $table->dropColumn('outlet_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'outlet_id']);
            $table->dropColumn('outlet_id');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('stock_scope');
        });
    }
};
