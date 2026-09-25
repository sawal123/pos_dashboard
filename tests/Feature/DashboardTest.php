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

    public function test_owner_without_a_business_type_does_not_get_a_guessed_laundry_menu(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        // No business_type: NULL/"unknown" must never be presented as Cafe or as
        // a laundry tenant (DASH-14). The laundry menu requires BOTH the
        // LAUNDRY_VIEW permission and business_type = laundry.
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);

        $response = $this->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Operasional');
        $response->assertSee('Produk &amp; Layanan', false);
        $response->assertDontSee('Pesanan Laundry');
        $response->assertDontSee(route('laundry-orders.index'), false);
        // The owner is still guided to choose a type instead of being locked out.
        $response->assertSee('Tipe bisnis belum ditentukan');
    }

    public function test_unverified_users_cannot_visit_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('verification.notice'));
    }
}
