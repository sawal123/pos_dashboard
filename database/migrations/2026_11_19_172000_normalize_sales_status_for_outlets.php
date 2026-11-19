<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize any sales pushed with status = 'paid' to standard 'completed'
        DB::table('sales')
            ->where('status', 'paid')
            ->update([
                'status' => 'completed',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive; no-op
    }
};
