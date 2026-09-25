<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Ringkasan');
        $response->assertSee('Paket Cloud');
        $response->assertSee('Status Cloud');
        $response->assertSee('Belum Ada Notifikasi');
        $response->assertDontSee('Subscriber Aktif');
        // DASH-10B2 — a user with no active business holds no permissions, so no
        // business menu item is rendered (deny-by-default, not a global role).
        $response->assertDontSee('Operasional');
        $response->assertDontSee('Pesanan Laundry');
        $response->assertDontSee(route('laundry-orders.index'), false);
        $response->assertDontSee('sidebar-item-label truncate flex-1 tracking-tight">Categories', false);
    }

    public function test_owner_sees_the_operational_and_laundry_menu(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);

        // DASH-11 — the laundry menu is visible for every active business. It is
        // now driven by the DASH-10B2 permission matrix instead of business type.
        $response = $this->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Operasional');
        $response->assertSee('Produk &amp; Layanan', false);
        $response->assertSee('Pesanan Laundry');
        $response->assertSee(route('laundry-orders.index'), false);
    }

    public function test_unverified_users_cannot_visit_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('verification.notice'));
    }
}
