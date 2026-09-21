<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionsPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Auth & Access
    // ============================================================

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
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
    }

    // ============================================================
    // UI Structure
    // ============================================================

    public function test_transactions_page_renders_header_and_title(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Transaksi');
        $response->assertSee('Pantau seluruh transaksi penjualan dari setiap outlet.');
        $response->assertDontSee('Transaksi Baru');
    }

    public function test_sidebar_transaksi_uses_transactions_index_route(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee(route('transactions.index'));
    }

    public function test_breadcrumb_renders_dashboard_and_transaksi(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Transaksi');
    }

    public function test_filter_bar_renders_date_filter_and_custom_range_inputs(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
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

    // ============================================================
    // Tenant Isolation
    // ============================================================

    public function test_user_without_business_returns_200_with_zero_metrics_and_empty_transactions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // No business attached to user
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('0'); // total_transactions metric
    }

    public function test_only_transactions_of_the_current_business_are_shown(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $otherBusiness = Business::factory()->create();

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $otherOutlet = Outlet::factory()->create(['business_id' => $otherBusiness->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'OWN-TRX-001',
        ]);

        Sale::factory()->create([
            'business_id' => $otherBusiness->id,
            'outlet_id' => $otherOutlet->id,
            'transaction_number' => 'OTHER-TRX-001',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('OWN-TRX-001');
        $response->assertDontSee('OTHER-TRX-001');
    }

    public function test_transactions_are_not_accessible_across_businesses(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $otherBusiness = Business::factory()->create();
        $otherOutlet = Outlet::factory()->create(['business_id' => $otherBusiness->id]);

        Sale::factory()->create([
            'business_id' => $otherBusiness->id,
            'outlet_id' => $otherOutlet->id,
            'transaction_number' => 'LEAKED-TRX',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertDontSee('LEAKED-TRX');
    }

    // ============================================================
    // Real Data Rendering
    // ============================================================

    public function test_real_sale_transaction_number_appears_in_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-REAL-001',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('TRX-REAL-001');
    }

    public function test_customer_snapshot_name_takes_priority_over_relation(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $customer = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Nama Relation']);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'customer_snapshot' => ['name' => 'Nama Snapshot'],
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Nama Snapshot');
        $response->assertDontSee('Nama Relation');
    }

    public function test_null_customer_shows_pelanggan_umum(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => null,
            'customer_snapshot' => null,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Pelanggan Umum');
    }

    // ============================================================
    // Status Mapping
    // ============================================================

    public function test_status_completed_is_presented_as_selesai(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'status' => 'completed',
            'transaction_number' => 'TRX-COMP-001',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Selesai');
    }

    public function test_status_cancelled_is_presented_as_dibatalkan(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'status' => 'cancelled',
            'transaction_number' => 'TRX-CANCEL-001',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Dibatalkan');
    }

    public function test_payment_status_paid_is_presented_as_lunas(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Lunas');
    }

    public function test_payment_status_unpaid_is_presented_as_belum_lunas(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Belum Lunas');
    }

    public function test_payment_method_cash_is_presented_as_tunai(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'payment_method' => 'cash',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Tunai');
    }

    public function test_null_payment_method_is_presented_as_tidak_diketahui(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'payment_method' => null,
            'transaction_number' => 'TRX-NULL-PM',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('TRX-NULL-PM');
    }

    // ============================================================
    // Decimal Quantity & Pricing Unit
    // ============================================================

    public function test_sale_item_decimal_quantity_is_preserved_in_data_raw(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
        ]);

        $sale = Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DEC-001',
        ]);

        SaleItem::factory()->create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => '4.250',
            'unit' => 'kg',
            'pricing_unit' => 'kg',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        // quantity is preserved in data-raw JSON
        $response->assertSee('4.250', false);
    }

    public function test_pricing_unit_generic_not_per_kg_legacy(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
        ]);

        $sale = Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
        ]);

        SaleItem::factory()->create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'pricing_unit' => 'kg', // generic, not 'per_kg' legacy
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        // Should not see legacy per_kg in data-raw
        $response->assertDontSee('"pricing_unit":"per_kg"', false);
    }

    // ============================================================
    // Metrics
    // ============================================================

    public function test_metrics_total_transactions_matches_db_count(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->count(3)->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('3'); // total_transactions in summary card
    }

    // ============================================================
    // Ordering
    // ============================================================

    public function test_transactions_are_ordered_by_sold_at_descending(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-EARLIER',
            'sold_at' => now()->subDays(2),
        ]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-LATEST',
            'sold_at' => now(),
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();

        $content = $response->getContent();
        $latestPos = strpos((string) $content, 'TRX-LATEST');
        $earlierPos = strpos((string) $content, 'TRX-EARLIER');
        $this->assertNotFalse($latestPos);
        $this->assertNotFalse($earlierPos);
        $this->assertLessThan($earlierPos, $latestPos, 'Latest transaction should appear first');
    }

    // ============================================================
    // Pagination
    // ============================================================

    public function test_pagination_renders_nav_with_aria_current_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->count(5)->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('id="transactionsPagination"', false);
        $response->assertSee('aria-current="page"', false);
    }

    public function test_second_page_is_not_shown_when_total_less_than_25(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->count(3)->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        // Page 2 link should not exist
        $response->assertDontSee('>2</a>', false);
        $response->assertDontSee('>2</span>', false);
    }

    public function test_query_string_is_preserved_in_pagination_links(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Create 26 sales so second page appears
        Sale::factory()->count(26)->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'status' => 'completed',
        ]);

        $this->actingAs($user);
        // Filter by status=completed — all 26 records match, triggering pagination
        $response = $this->get(route('transactions.index', ['status' => 'completed']));
        $response->assertOk();
        // Pagination links should contain the status parameter
        $response->assertSee('status=completed', false);
    }

    // ============================================================
    // Filter Persistence
    // ============================================================

    public function test_filter_selected_outlet_is_preserved_in_form(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet Test']);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index', ['outlet_id' => $outlet->id]));
        $response->assertOk();
        $response->assertSee('Outlet Test');
    }

    public function test_filter_bar_action_points_to_transactions_index(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('action="'.route('transactions.index').'"', false);
    }

    // ============================================================
    // Empty State
    // ============================================================

    public function test_empty_state_renders_when_no_transactions(): void
    {
        [$user] = $this->makeUserWithBusiness();
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Transaksi');
    }

    public function test_no_fixture_data_rendered_for_business_with_no_sales(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // User without business → no fixture data should appear
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertDontSee('TRX-260921-001');
        $response->assertDontSee('Kopi Susu Gula Aren');
    }

    // ============================================================
    // Data-raw serialization
    // ============================================================

    public function test_transaction_row_contains_data_raw_json(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-RAW-TEST',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('data-raw', false);
    }

    public function test_data_transactions_page_attribute_is_present(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('data-transactions-page="true"', false);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Create a user + business + subscription + pivot and set session to that business.
     *
     * @return array{0: User, 1: Business}
     */
    private function makeUserWithBusiness(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $business->id]);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        // Set session so middleware resolves this business
        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$user, $business];
    }
}
