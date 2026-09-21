<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('cash.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_access_cash_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_authenticated_users_can_access_cash_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertOk();
    }

    public function test_cash_route_exists_and_renders_header(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('Kas & Pengeluaran');
        $response->assertSee('Pantau pergerakan kas dan pengeluaran operasional bisnis.');
    }

    public function test_sidebar_cash_uses_cash_index_route(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee(route('cash.index'));
        $response->assertSee('aria-current="page"', false);
    }

    public function test_cash_page_renders_accessible_tabs_for_ledgers_and_expenses(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('role="tablist"', false);
        $response->assertSee('id="tabLedgers"', false);
        $response->assertSee('id="tabExpenses"', false);
        $response->assertSee('aria-controls="panelLedgers"', false);
        $response->assertSee('aria-controls="panelExpenses"', false);
        $response->assertSee('tabindex="0"', false);
        $response->assertSee('tabindex="-1"', false);
    }

    public function test_cash_ledger_fixtures_use_positive_amounts_and_direction_from_type(): void
    {
        $loadFixtures = require resource_path('views/cash/fixtures.php');
        $data = $loadFixtures();

        $this->assertNotEmpty($data['ledgers']);

        foreach ($data['ledgers'] as $ledger) {
            $this->assertGreaterThan(0, $ledger['amount']);
            $this->assertContains($ledger['type'], ['in', 'out']);
        }

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        // Positive sign for in, negative sign for out
        $response->assertSee('+ Rp 125.000');
        $response->assertSee('- Rp 75.000');
    }

    public function test_nullable_reference_note_and_shift_render_safely(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        // Table should safely show '-' or handle null values without crashing
        $response->assertSee('TRX-260921-001');
        $response->assertSee('Tanpa Shift');
    }

    public function test_production_environment_does_not_display_development_fixtures(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        $response->assertDontSee('TRX-260921-001');
        $response->assertDontSee('Pembelian Es Batu Kristal');
        $response->assertDontSee('+ Rp 125.000');
    }

    public function test_production_cash_and_expense_empty_states_are_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        // Both empty states should exist in their respective tab panels
        $response->assertSee('Belum Ada Pergerakan Kas');
        $response->assertSee('Pergerakan kas yang telah tersinkron ke Cloud akan muncul di sini.');
        $response->assertSee('Belum Ada Pengeluaran');
        $response->assertSee('Pengeluaran yang telah tersinkron ke Cloud akan muncul di sini.');
    }

    public function test_cash_filter_does_not_contain_hardcoded_reference_date(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        $response->assertDontSee('2026/09/21', false);
    }

    public function test_default_fixtures_do_not_contain_laundry_data(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        $response->assertDontSee('Laundry Kiloan');
        $response->assertDontSee('Cuci Bedcover');
        $response->assertDontSee('Layanan Laundry');
    }

    public function test_machine_readable_occurred_at_raw_is_provided(): void
    {
        $loadFixtures = require resource_path('views/cash/fixtures.php');
        $data = $loadFixtures();

        $this->assertNotEmpty($data['ledgers']);
        foreach ($data['ledgers'] as $ledger) {
            $this->assertArrayHasKey('occurred_at_raw', $ledger);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $ledger['occurred_at_raw']);
        }

        foreach ($data['expenses'] as $expense) {
            $this->assertArrayHasKey('occurred_at_raw', $expense);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expense['occurred_at_raw']);
        }
    }
}
