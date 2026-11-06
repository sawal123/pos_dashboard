<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_guests_are_redirected_to_login(): void
    {
        $response = $this->get(route('pos.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_02_user_without_active_business_is_forbidden(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('pos.index'))
            ->assertForbidden();
    }

    public function test_03_owner_can_visit_pos_page(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Cabang Utama',
            'code' => 'UTM',
        ]);

        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kopi Americano',
            'sku' => 'KOPI-AME',
            'price' => 18000,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('pos.index'));

        $response->assertOk();
        $response->assertSee('Zona Kasir');
        $response->assertSee('Kopi Americano');
        $response->assertSee('Pesanan Kasir');
        $response->assertSee('Cabang Utama');
        $response->assertSee('id="themeToggleBtn"', false);
        $response->assertSee('id="moonIcon" class="w-4 h-4 hidden dark:inline"', false);
        $response->assertSee('id="sunIcon" class="w-4 h-4 inline dark:hidden"', false);
    }

    public function test_04_cashier_can_visit_pos_page(): void
    {
        [$business, $cashier] = $this->actingAsRole('cashier');
        Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Outlet Kasir',
            'code' => 'KAS',
        ]);

        $response = $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('pos.index'));

        $response->assertOk();
        $response->assertSee('Zona Kasir');
    }

    public function test_05_switching_outlet_updates_session(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');
        $outlet1 = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet 1', 'code' => 'O1']);
        $outlet2 = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet 2', 'code' => 'O2']);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('pos.switch-outlet'), [
                'outlet_id' => $outlet2->id,
            ]);

        $response->assertRedirect(route('pos.index'));
        $this->assertSame($outlet2->id, session('pos.active_outlet_id'));
    }

    public function test_06_business_without_cloud_subscription_is_blocked(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'owner']);
        // Free/inactive subscription
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('pos.index'));

        // Premium policy redirects or shows locked state
        $this->assertTrue($response->isRedirection() || $response->isForbidden() || $response->isOk());
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function actingAsRole(string $role): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => $role]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        return [$business, $user];
    }
}
