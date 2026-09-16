<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class P37E2ESeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'E2E Owner',
            'email' => 'e2e-owner@example.com',
        ]);

        $business = Business::create([
            'name' => 'E2E Business',
            'slug' => 'e2e-business-'.time(),
            'status' => 'active',
        ]);

        $user->businesses()->attach($business, ['role' => 'owner']);

        Subscription::create([
            'business_id' => $business->id,
            'plan' => 'cloud',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'name' => 'Outlet Pusat',
            'code' => 'OUT-1',
            'status' => 'active',
        ]);

        echo 'SEED business='.$business->id.' outlet='.$outlet->id.' email='.$user->email.PHP_EOL;
    }
}
