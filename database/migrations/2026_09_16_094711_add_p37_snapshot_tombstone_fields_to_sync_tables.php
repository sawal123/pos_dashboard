<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P37 patch: historical snapshot columns, laundry lifecycle, expense
     * category, customer/business snapshots, and tombstone status support.
     *
     * All additive and nullable/defaulted. Existing rows keep their values;
     * nothing is dropped, renamed, or truncated.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost_snapshot', 15, 2)->default(0)->after('unit_price');
            $table->string('unit')->default('pcs')->after('cost_snapshot');
            $table->string('kind')->default('product')->after('unit');
            $table->string('pricing_unit')->default('pcs')->after('kind');
            $table->decimal('line_cost', 15, 2)->default(0)->after('line_total');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('gross_profit', 15, 2)->default(0)->after('total_amount');
            $table->string('order_status')->nullable()->after('status');
            $table->timestamp('estimated_completed_at')->nullable()->after('paid_at');
            $table->text('note')->nullable()->after('estimated_completed_at');
            $table->json('customer_snapshot')->nullable()->after('customer_id');
            $table->json('business_snapshot')->nullable()->after('customer_snapshot');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('category')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'gross_profit',
                'order_status',
                'estimated_completed_at',
                'note',
                'customer_snapshot',
                'business_snapshot',
            ]);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['cost_snapshot', 'unit', 'kind', 'pricing_unit', 'line_cost']);
        });
    }
};
