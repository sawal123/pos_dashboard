<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('stock.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_access_stock_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_authenticated_users_can_access_stock_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertOk();
    }

    public function test_stock_page_renders_header_and_title(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Stok');
        $response->assertSee('Pantau jumlah stok dan pergerakan inventori bisnis.');
    }

    public function test_sidebar_stock_uses_stock_index_route(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee(route('stock.index'));
        $response->assertSee('aria-current="page"', false);
    }

    public function test_stock_status_structure_is_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Stok Aman');
        $response->assertSee('Stok Menipis');
        $response->assertSee('Habis / Minus');
    }

    public function test_negative_stock_fixture_renders_properly_without_error(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Cup 16oz');
        $response->assertSee('-12.000');
        $response->assertSee('Minus');
    }

    public function test_production_environment_does_not_display_development_fixtures(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('stock.index'));
        $response->assertOk();
        $response->assertDontSee('Biji Kopi Arabika');
        $response->assertDontSee('Cup 16oz');
        $response->assertDontSee('RAW-001');
    }

    public function test_deterministic_stock_status_attributes_rendered_in_table_and_cards(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertOk();
        // Check data-stock-status attribute presence
        $response->assertSee('data-stock-status="negative"', false);
        $response->assertSee('data-stock-status="low"', false);
        $response->assertSee('data-stock-status="safe"', false);
    }

    public function test_stock_movement_drawer_uses_numeric_sign_rendering(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertOk();
        // Ensure numeric check is used rather than startsWith('+')
        $response->assertDontSee("String(m.quantity_change).startsWith('+')", false);
        $response->assertSee('Number(m.quantity_change)', false);
    }

    public function test_empty_state_structure_is_rendered_in_production(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Data Stok');
        $response->assertSee('Data inventori yang telah tersinkron ke Cloud akan muncul di sini.');
    }
}
