<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('reports.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_access_reports_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_authenticated_users_can_access_reports_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertOk();
    }

    public function test_reports_route_exists_and_renders_header(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee('Laporan');
        $response->assertSee('Analisis ringkas penjualan dan pengeluaran bisnis.');
    }

    public function test_sidebar_reports_uses_reports_index_route_and_is_active(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee(route('reports.index'));
        $response->assertSee('aria-current="page"', false);
    }

    public function test_reports_summary_structure_is_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee('Total Penjualan');
        $response->assertSee('Total Transaksi');
        $response->assertSee('Estimasi Laba Kotor');
        $response->assertSee('Total Pengeluaran');
    }

    public function test_sections_are_rendered_properly(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee('Tren Penjualan');
        $response->assertSee('Metode Pembayaran');
        $response->assertSee('Pengeluaran per Kategori');
    }

    public function test_production_environment_does_not_display_development_fixtures(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('TRX-260921-001');
        $response->assertDontSee('Pembelian Es Batu Kristal');
    }

    public function test_production_reports_empty_state_is_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Data Laporan');
        $response->assertSee('Data penjualan dan pengeluaran yang telah tersinkron ke Cloud akan muncul di sini.');
    }

    public function test_default_fixtures_do_not_contain_laundry_data(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('Laundry Kiloan');
        $response->assertDontSee('Cuci Bedcover');
        $response->assertDontSee('Layanan Laundry');
    }

    public function test_machine_readable_dates_are_provided(): void
    {
        $loadFixtures = require resource_path('views/reports/fixtures.php');
        $data = $loadFixtures();

        $this->assertNotEmpty($data['sales']);
        foreach ($data['sales'] as $sale) {
            $this->assertArrayHasKey('sold_at_raw', $sale);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $sale['sold_at_raw']);
        }

        foreach ($data['expenses'] as $expense) {
            $this->assertArrayHasKey('occurred_at_raw', $expense);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expense['occurred_at_raw']);
        }
    }

    public function test_no_net_profit_or_fake_growth_percentage_labels(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        // Disallowed accounting terms without full contract
        $response->assertDontSee('Laba Bersih');
        $response->assertDontSee('Net Profit');
        $response->assertDontSee('Pertumbuhan Penjualan');
        $response->assertDontSee('Revenue Growth');
    }

    public function test_reports_filter_does_not_contain_hardcoded_reference_date(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('2026/09/21', false);
    }
}
