<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('business_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('unit_cost', 15, 2)
                ->nullable()
                ->after('note');

            $table->string('idempotency_key', 64)
                ->nullable()
                ->after('sale_sync_id');

            $table->index(['business_id', 'occurred_at']);
            $table->index(['business_id', 'product_id', 'occurred_at']);
            $table->unique(['business_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'idempotency_key']);
            $table->dropIndex(['business_id', 'product_id', 'occurred_at']);
            $table->dropIndex(['business_id', 'occurred_at']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['unit_cost', 'idempotency_key']);
        });
    }
};
