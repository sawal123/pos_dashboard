<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOutletContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_dashboard_shares_outlets_and_active_outlet_to_views(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet1 = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Cabang Medan',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $outlet2 = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Cabang Binjai',
            'status' => 'active',
            'is_primary' => false,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Cabang Aktif');
        $response->assertSee('Cabang Medan');
        $response->assertSee('Cabang Binjai');
        $this->assertSame($outlet1->id, session('dashboard.active_outlet_id'));
    }

    public function test_02_switching_active_outlet_updates_session(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet1 = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Cabang Medan',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $outlet2 = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Cabang Binjai',
            'status' => 'active',
            'is_primary' => false,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('dashboard.outlet-context.update'), [
                'outlet_id' => $outlet2->id,
            ]);

        $response->assertRedirect();
        $this->assertSame($outlet2->id, session('dashboard.active_outlet_id'));
        $this->assertSame($outlet2->id, session('pos.active_outlet_id'));
        $this->assertTrue(session('pos.outlet_confirmed'));
    }

    public function test_03_cannot_switch_to_outlet_of_another_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $otherBusiness = Business::factory()->create();
        $otherOutlet = Outlet::factory()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Cabang Lain',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('dashboard.outlet-context.update'), [
                'outlet_id' => $otherOutlet->id,
            ]);

        $response->assertSessionHasErrors('outlet_id');
    }

    public function test_04_switching_business_clears_outlet_context(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business1 = Business::factory()->create();
        $business1->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business1->id]);

        $business2 = Business::factory()->create();
        $business2->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business2->id]);

        $outletB1 = Outlet::factory()->create([
            'business_id' => $business1->id,
            'name' => 'B1 Outlet',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'dashboard.current_business_id' => $business1->id,
                'dashboard.active_outlet_id' => $outletB1->id,
                'pos.active_outlet_id' => $outletB1->id,
            ])
            ->post(route('dashboard.business-context.update'), [
                'business_id' => $business2->id,
            ]);

        $response->assertRedirect();
        $this->assertSame($business2->id, session('dashboard.current_business_id'));
        $this->assertNull(session('dashboard.active_outlet_id'));
        $this->assertNull(session('pos.active_outlet_id'));
    }
}
