<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('notes');
            $table->unique(['business_id', 'idempotency_key']);
        });

        Schema::table('cash_ledger', function (Blueprint $table) {
            $table->foreignId('expense_id')
                ->nullable()
                ->after('sale_sync_id')
                ->constrained('expenses')
                ->restrictOnDelete();
            $table->string('idempotency_key')->nullable()->after('expense_id');
            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['business_id', 'expense_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_ledger', function (Blueprint $table) {
            $table->dropIndex('cash_ledger_business_id_expense_id_index');
            $table->dropUnique('cash_ledger_business_id_idempotency_key_unique');
            $table->dropConstrainedForeignId('expense_id');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_business_id_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
