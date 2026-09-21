<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('products.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_access_products_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('products.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_authenticated_users_can_access_products_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('products.index'));
        $response->assertOk();
    }

    public function test_products_page_renders_header_and_title(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Produk & Layanan');
        $response->assertSee('Kelola katalog produk, layanan, harga, dan kategori bisnis.');
    }

    public function test_sidebar_products_uses_products_index_route(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('products.index'));
        $response->assertOk();
        $response->assertSee(route('products.index'));
        $response->assertSee('aria-current="page"', false);
    }

    public function test_products_page_renders_tabs_for_products_services_and_categories(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('role="tablist"', false);
        $response->assertSee('id="tabProducts"', false);
        $response->assertSee('id="tabServices"', false);
        $response->assertSee('id="tabCategories"', false);
    }

    public function test_production_environment_does_not_display_development_fixtures(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('products.index'));
        $response->assertOk();
        $response->assertDontSee('KOPI-001');
        $response->assertDontSee('Kopi Susu Gula Aren');
        $response->assertDontSee('Laundry Kiloan');
    }

    public function test_empty_state_structure_is_rendered_in_production(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Produk');
        $response->assertSee('Produk yang telah tersinkron ke Cloud akan muncul di sini.');
    }

    public function test_local_fixtures_support_decimal_quantity_and_pricing_unit(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('products.index'));
        $response->assertOk();
        // Decimal stock on product
        $response->assertSee('4.250 kg');
        // Negative stock on product
        $response->assertSee('-2.000 pcs');
        // Service pricing unit
        $response->assertSee('Per Kilogram');
    }
}
