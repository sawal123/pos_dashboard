<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class P37E2EResetSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('personal_access_tokens')->delete();

        foreach (['devices', 'stock_movements', 'cash_ledger', 'sale_items', 'sales', 'expenses', 'shifts', 'products', 'customers', 'categories', 'outlets', 'business_user', 'subscriptions', 'businesses', 'users'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('sync_requests')->delete();
        DB::table('sync_counters')->delete();
        echo 'RESET_OK'.PHP_EOL;
    }
}
