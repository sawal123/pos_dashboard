<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Assign any existing cashier without outlet_id to the primary outlet of their business
        $primaryOutlets = DB::table('outlets')
            ->where('is_primary', true)
            ->pluck('id', 'business_id');

        $cashiers = DB::table('business_user')
            ->where('role', 'cashier')
            ->whereNull('outlet_id')
            ->get();

        foreach ($cashiers as $cashier) {
            $outletId = $primaryOutlets->get($cashier->business_id)
                ?? DB::table('outlets')->where('business_id', $cashier->business_id)->value('id');

            if ($outletId) {
                DB::table('business_user')
                    ->where('id', $cashier->id)
                    ->update(['outlet_id' => $outletId]);
            }
        }
    }

    public function down(): void
    {
        // No-op rollback: preserving data association
    }
};
