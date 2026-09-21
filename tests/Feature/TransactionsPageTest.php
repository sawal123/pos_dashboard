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

    public function test_filter_bar_renders_date_filter_and_custom_range_inputs(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('id="filterDate"', false);
        $response->assertSee('value="all"', false);
        $response->assertSee('value="today"', false);
        $response->assertSee('value="7days"', false);
        $response->assertSee('value="30days"', false);
        $response->assertSee('value="custom"', false);
        $response->assertSee('id="customDateRangeContainer"', false);
        $response->assertSee('id="filterStartDate"', false);
        $response->assertSee('id="filterEndDate"', false);
    }

    public function test_pagination_renders_only_active_first_page_without_fake_pages(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('id="transactionsPagination"', false);
        $response->assertSee('aria-current="page"', false);
        // Pastikan tidak ada tombol page 2 palsu di navigasi
        $response->assertDontSee('>2</button>', false);
    }

    public function test_transaction_items_render_sold_at_metadata(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('data-sold-at="2026-09-21 09:42"', false);
        $response->assertSee('data-transactions-page="true"', false);
    }
}
