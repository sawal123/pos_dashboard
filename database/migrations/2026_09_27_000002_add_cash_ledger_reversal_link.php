<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DASH-16 follow-up: deterministic link between a correction ledger row
     * and the exact row it corrects.
     *
     * A correction row (manual reversal or expense void refund) points at its
     * original through `reverses_ledger_id`. This makes correction chains
     * explicit and lets the app answer "has this row already been corrected?"
     * exactly, instead of guessing from `expense_id` or category alone.
     *
     * The column is nullable and additive: rows created by sync/device (sale
     * settlements) and pre-existing rows keep their current behaviour.
     */
    public function up(): void
    {
        Schema::table('cash_ledger', function (Blueprint $table) {
            $table->foreignId('reverses_ledger_id')
                ->nullable()
                ->after('expense_id')
                ->constrained('cash_ledger')
                ->nullOnDelete();
            $table->index(['business_id', 'reverses_ledger_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_ledger', function (Blueprint $table) {
            $table->dropIndex('cash_ledger_business_id_reverses_ledger_id_index');
            $table->dropConstrainedForeignId('reverses_ledger_id');
        });
    }
};
