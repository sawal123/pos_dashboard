<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('transactions.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_access_transactions_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_authenticated_users_can_access_transactions_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
    }

    public function test_transactions_page_renders_header_and_title(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Transaksi');
        $response->assertSee('Pantau seluruh transaksi penjualan dari setiap outlet.');
        $response->assertDontSee('Transaksi Baru');
    }

    public function test_sidebar_transaksi_uses_transactions_index_route(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee(route('transactions.index'));
        $response->assertSee('aria-current="page"', false);
    }

    public function test_breadcrumb_renders_dashboard_and_transaksi(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Transaksi');
    }

    public function test_production_environment_does_not_display_development_fixtures(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertDontSee('TRX-260921-001');
        $response->assertDontSee('Kopi Susu Gula Aren');
        $response->assertDontSee('Croissant Almond');
    }

    public function test_empty_state_structure_is_rendered_in_production(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Transaksi');
        $response->assertSee('Transaksi yang telah tersinkron ke Cloud akan muncul di sini.');
    }
}
