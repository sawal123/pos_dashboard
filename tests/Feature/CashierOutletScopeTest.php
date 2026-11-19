<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashierOutletScopeTest extends TestCase
{
    use RefreshDatabase;

    private function createBusinessWithTwoOutlets(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outletA = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Toko A',
            'code' => 'OUT-A',
            'address' => 'Jl. Toko A No. 1',
            'phone' => '0811111111',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $outletB = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Toko B',
            'code' => 'OUT-B',
            'address' => 'Jl. Toko B No. 2',
            'phone' => '0822222222',
            'status' => 'active',
            'is_primary' => false,
        ]);
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'outlet_id' => $outletB->id,
        ]);

        return [$owner, $business, $outletA, $outletB];
    }

    public function test_owner_can_register_cashier_assigned_to_specific_outlet(): void
    {
        [$owner, $business, $outletA, $outletB] = $this->createBusinessWithTwoOutlets();

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('users.invitations.store'), [
                'mode' => 'direct',
                'name' => 'Kasir Toko A',
                'email' => 'kasirA@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'cashier',
                'outlet_id' => $outletA->id,
            ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'role' => 'cashier',
            'outlet_id' => $outletA->id,
        ]);
    }

    public function test_owner_can_change_member_role_and_assign_outlet(): void
    {
        [$owner, $business, $outletA, $outletB] = $this->createBusinessWithTwoOutlets();

        $member = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('users.members.role.update', $member->id), [
                'role' => 'cashier',
                'outlet_id' => $outletB->id,
            ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('business_user', [
            'business_id' => $business->id,
            'user_id' => $member->id,
            'role' => 'cashier',
            'outlet_id' => $outletB->id,
        ]);
    }

    public function test_cashier_is_locked_to_assigned_outlet_in_dashboard_and_pos(): void
    {
        [$owner, $business, $outletA, $outletB] = $this->createBusinessWithTwoOutlets();

        $cashier = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($cashier->id, [
            'role' => 'cashier',
            'outlet_id' => $outletA->id,
        ]);

        // Access dashboard
        $response = $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame($outletA->id, session('dashboard.active_outlet_id'));
        $this->assertSame($outletA->id, session('pos.active_outlet_id'));

        // Attempting to switch dashboard active outlet to outlet B is rejected with 403
        $switchResponse = $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('dashboard.outlet-context.update'), [
                'outlet_id' => $outletB->id,
            ]);

        $switchResponse->assertForbidden();

        // Access Web POS
        $posResponse = $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('pos.index'));

        $posResponse->assertOk();
        $this->assertSame($outletA->id, session('pos.active_outlet_id'));

        // Attempting to switch POS active outlet to outlet B is rejected with 403
        $posSwitchResponse = $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('pos.switch-outlet'), [
                'outlet_id' => $outletB->id,
            ]);

        $posSwitchResponse->assertForbidden();
    }

    public function test_mobile_context_returns_only_assigned_outlet_for_cashier(): void
    {
        [$owner, $business, $outletA, $outletB] = $this->createBusinessWithTwoOutlets();

        $cashier = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($cashier->id, [
            'role' => 'cashier',
            'outlet_id' => $outletA->id,
        ]);

        $token = $cashier->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/context');

        $response->assertOk();
        $outlets = $response->json('data.businesses.0.outlets');

        $this->assertCount(1, $outlets);
        $this->assertSame($outletA->id, $outlets[0]['id']);
        $this->assertSame('Toko A', $outlets[0]['name']);
    }

    public function test_sync_blocks_cashier_attempting_to_sync_device_from_another_branch(): void
    {
        [$owner, $business, $outletA, $outletB] = $this->createBusinessWithTwoOutlets();

        $cashierA = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($cashierA->id, [
            'role' => 'cashier',
            'outlet_id' => $outletA->id,
        ]);

        // Device registered at Toko B
        $deviceB = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outletB->id,
            'name' => 'POS Terminal B',
            'identifier' => 'DEV-B-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $tokenA = $cashierA->createToken('mobile-api', ['mobile'])->plainTextToken;

        // Cashier A attempts to sync on device B
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson("/api/sync/pull?business_id={$business->id}&device_identifier=DEV-B-01&after=0");

        $response->assertForbidden();
        $this->assertSame('OUTLET_ACCESS_DENIED', $response->json('code'));
    }

    public function test_transactions_page_defaults_to_active_branch_in_filter(): void
    {
        [$owner, $business, $outletA, $outletB] = $this->createBusinessWithTwoOutlets();

        // Set active outlet in session to outlet B
        $response = $this->actingAs($owner)
            ->withSession([
                'dashboard.current_business_id' => $business->id,
                'dashboard.active_outlet_id' => $outletB->id,
            ])
            ->get(route('transactions.index'));

        $response->assertOk();
        // View data has currentFilters['outlet_id'] set to outlet B
        $currentFilters = $response->viewData('currentFilters');
        $this->assertSame((string) $outletB->id, (string) $currentFilters['outlet_id']);
    }

    public function test_receipt_prints_outlet_address_and_phone(): void
    {
        [$owner, $business, $outletA, $outletB] = $this->createBusinessWithTwoOutlets();

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'customer_id' => null,
            'shift_id' => null,
            'transaction_number' => 'TRX-TEST-001',
            'status' => 'completed',
            'subtotal' => 50000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 50000,
            'gross_profit' => 20000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'sold_at' => now(),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('pos.receipt', $sale->id));

        $response->assertOk();
        $response->assertSee('Toko A');
        $response->assertSee('Jl. Toko A No. 1');
        $response->assertSee('0811111111');
    }
}
