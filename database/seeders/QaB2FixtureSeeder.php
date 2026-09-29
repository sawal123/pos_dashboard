<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\QaDatabaseGuard;
use Illuminate\Database\Seeder;

/**
 * QA-RELEASE B2 — synthetic, idempotent fixtures for live backend integration.
 *
 * Runs ONLY against the isolated `pos_qa_b2` database (see
 * docs/qa/QA_RELEASE_PHASE_B2.md). Never run against development or production.
 *
 * Creates:
 *  - Business A (cloud)  : owner-a / member-a / cashier-a, outlets A1/A2,
 *                          device owner-A1 (active) + pre-registered cashier-A1.
 *  - Business B (cloud)  : owner-b, outlet B1, device owner-B1.
 *  - E2E Business (cloud): e2e-owner@example.com + OUT-1 (automated P37/P38 specs).
 *  - A category + physical product in Business A.
 *
 * Every account uses the password `password` and a verified email.
 */
class QaB2FixtureSeeder extends Seeder
{
    public function run(): void
    {
        // Fail closed before any write: this seeder only runs on an isolated,
        // explicitly allow-listed QA database.
        QaDatabaseGuard::assertIsolated();

        $businessA = $this->business('QA Bisnis A', 'qa-biz-a', 'cafe');
        $businessB = $this->business('QA Bisnis B', 'qa-biz-b', 'grosir');
        $businessE2e = $this->business('E2E Business', 'e2e-business', 'cafe');

        $ownerA = $this->user('QA Owner A', 'owner-a@example.com');
        $memberA = $this->user('QA Member A', 'member-a@example.com');
        $cashierA = $this->user('QA Cashier A', 'cashier-a@example.com');
        $ownerB = $this->user('QA Owner B', 'owner-b@example.com');
        $e2eOwner = $this->user('E2E Owner', 'e2e-owner@example.com');

        $this->attach($ownerA, $businessA, 'owner');
        $this->attach($memberA, $businessA, 'member');
        $this->attach($cashierA, $businessA, 'cashier');
        $this->attach($ownerB, $businessB, 'owner');
        $this->attach($e2eOwner, $businessE2e, 'owner');

        $outletA1 = $this->outlet($businessA, 'Outlet A1', 'A1');
        $outletA2 = $this->outlet($businessA, 'Outlet A2', 'A2');
        $outletB1 = $this->outlet($businessB, 'Outlet B1', 'B1');
        $this->outlet($businessE2e, 'Outlet Pusat', 'OUT-1');

        $this->device($businessA, $outletA1, 'QA-OWNER-A-DEV-1', 'QA Owner A Device');
        // Cashier device is pre-registered by the owner (cashiers may not create devices).
        $this->device($businessA, $outletA1, 'QA-CASHIER-A-DEV-1', 'QA Cashier A Device');
        $this->device($businessB, $outletB1, 'QA-OWNER-B-DEV-1', 'QA Owner B Device');

        $category = Category::firstOrCreate(
            ['business_id' => $businessA->id, 'name' => 'QA Kategori'],
            ['status' => 'active'],
        );

        Product::firstOrCreate(
            ['business_id' => $businessA->id, 'sku' => 'QA-PROD-1'],
            [
                'category_id' => $category->id,
                'name' => 'QA Produk Fisik 1',
                'price' => 15000,
                'kind' => 'product',
                'cost' => 8000,
                'stock' => 100,
                'unit' => 'pcs',
                'status' => 'active',
            ],
        );

        $this->command->info(sprintf(
            'QA B2 fixtures ready: bizA=%d bizB=%d e2e=%d outletA1=%d',
            $businessA->id,
            $businessB->id,
            $businessE2e->id,
            $outletA1->id,
        ));
        $this->command->info(
            'Logins (password "password"): owner-a@example.com, member-a@example.com, cashier-a@example.com, owner-b@example.com, e2e-owner@example.com',
        );
    }

    private function user(string $name, string $email): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );
    }

    private function business(string $name, string $slug, string $type): Business
    {
        $business = Business::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'active', 'business_type' => $type],
        );

        Subscription::updateOrCreate(
            ['business_id' => $business->id],
            [
                'plan' => 'cloud',
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
            ],
        );

        return $business;
    }

    private function attach(User $user, Business $business, string $role): void
    {
        $user->businesses()->syncWithoutDetaching([
            $business->id => ['role' => $role],
        ]);
    }

    private function outlet(Business $business, string $name, string $code): Outlet
    {
        return Outlet::firstOrCreate(
            ['business_id' => $business->id, 'code' => $code],
            ['name' => $name, 'status' => 'active'],
        );
    }

    private function device(Business $business, Outlet $outlet, string $identifier, string $name): Device
    {
        return Device::firstOrCreate(
            ['business_id' => $business->id, 'identifier' => $identifier],
            [
                'outlet_id' => $outlet->id,
                'name' => $name,
                'platform' => 'android',
                'status' => 'active',
                'registered_at' => now(),
                'last_seen_at' => now(),
            ],
        );
    }
}
