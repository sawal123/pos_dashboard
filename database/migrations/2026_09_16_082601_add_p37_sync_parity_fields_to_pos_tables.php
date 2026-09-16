<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P37 additive parity columns for POS cloud contract.
     *
     * All columns are nullable or defaulted: existing rows are untouched and
     * no table, column, or data is dropped, renamed, or truncated.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('kind')->default('product')->after('name');
            $table->decimal('cost', 15, 2)->default(0)->after('price');
            $table->decimal('stock', 15, 3)->default(0)->after('cost');
            $table->string('unit')->default('pcs')->after('stock');
            $table->decimal('min_stock', 15, 3)->default(0)->after('unit');
            $table->string('pricing_unit')->default('pcs')->after('min_stock');
            $table->decimal('min_quantity', 15, 3)->default(0)->after('pricing_unit');
            $table->string('estimated_duration')->nullable()->after('min_quantity');
        });

        Schema::create('cash_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->string('type');
            $table->unsignedBigInteger('amount');
            $table->string('category')->nullable();
            $table->text('note')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('sale_sync_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->uuid('sync_id')->nullable();
            $table->unsignedBigInteger('sync_version')->default(1);
            $table->unsignedBigInteger('sync_sequence')->default(0);

            $table->unique(['business_id', 'sync_id']);
            $table->unique(['business_id', 'reference_id']);
            $table->index(['business_id', 'sync_sequence']);

            $table->foreign(['business_id', 'outlet_id'])
                ->references(['business_id', 'id'])
                ->on('outlets')
                ->restrictOnDelete();

            $table->foreign(['business_id', 'outlet_id', 'shift_id'])
                ->references(['business_id', 'outlet_id', 'id'])
                ->on('shifts')
                ->restrictOnDelete();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('movement_type');
            $table->decimal('quantity_change', 15, 3);
            $table->decimal('stock_before', 15, 3)->default(0);
            $table->decimal('stock_after', 15, 3)->default(0);
            $table->string('reference_id')->nullable();
            $table->string('category')->nullable();
            $table->text('note')->nullable();
            $table->string('sale_sync_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->uuid('sync_id')->nullable();
            $table->unsignedBigInteger('sync_version')->default(1);
            $table->unsignedBigInteger('sync_sequence')->default(0);

            $table->unique(['business_id', 'sync_id']);
            $table->unique(['business_id', 'reference_id']);
            $table->index(['business_id', 'sync_sequence']);

            $table->foreign(['business_id', 'product_id'])
                ->references(['business_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('total_amount');
            $table->string('payment_status')->default('paid')->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->unsignedBigInteger('cash_received')->nullable()->after('paid_at');
            $table->unsignedBigInteger('change_amount')->nullable()->after('cash_received');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_status', 'paid_at', 'cash_received', 'change_amount']);
        });

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('cash_ledger');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'kind',
                'cost',
                'stock',
                'unit',
                'min_stock',
                'pricing_unit',
                'min_quantity',
                'estimated_duration',
            ]);
        });
    }
};
