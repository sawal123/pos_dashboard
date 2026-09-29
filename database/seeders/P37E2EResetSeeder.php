<?php

namespace Database\Seeders;

use App\Support\QaDatabaseGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class P37E2EResetSeeder extends Seeder
{
    public function run(): void
    {
        // Fail closed before deleting anything: this reset wipes users,
        // transactions and tokens, so it must never run outside an isolated,
        // explicitly allow-listed QA database.
        QaDatabaseGuard::assertIsolated();

        DB::table('personal_access_tokens')->delete();

        foreach (['devices', 'stock_movements', 'cash_ledger', 'sale_items', 'sales', 'expenses', 'shifts', 'products', 'customers', 'categories', 'outlets', 'business_user', 'subscriptions', 'businesses', 'users'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('sync_requests')->delete();
        DB::table('sync_counters')->delete();
        echo 'RESET_OK'.PHP_EOL;
    }
}
