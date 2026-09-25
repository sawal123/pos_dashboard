<?php

namespace Tests\Feature;

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
        $response->assertSee('Operasional');
        $response->assertSee('Produk &amp; Layanan', false);
        $response->assertSee('Paket Cloud');
        $response->assertSee('Status Cloud');
        $response->assertSee('Belum Ada Notifikasi');
        $response->assertDontSee('Subscriber Aktif');
        // DASH-11: the laundry menu is always reachable — there is no persisted
        // business type to gate on (see docs/dashboard/DASH11_LAUNDRY_MONITORING.md).
        $response->assertSee('Pesanan Laundry');
        $response->assertSee(route('laundry-orders.index'), false);
        $response->assertDontSee('sidebar-item-label truncate flex-1 tracking-tight">Categories', false);
    }

    public function test_unverified_users_cannot_visit_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('verification.notice'));
    }
}
