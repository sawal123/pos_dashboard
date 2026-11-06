<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionsPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Scope Integrity (No HasFactory on Sales/Shift models)
    // ============================================================

    public function test_sale_models_do_not_rely_on_has_factory(): void
    {
        $traits = class_uses_recursive(Sale::class);
        $this->assertNotContains(HasFactory::class, $traits);

        $itemTraits = class_uses_recursive(SaleItem::class);
        $this->assertNotContains(HasFactory::class, $itemTraits);

        $shiftTraits = class_uses_recursive(Shift::class);
        $this->assertNotContains(HasFactory::class, $shiftTraits);
    }

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
        $response->assertSee('Belum Ada Transaksi');
    }

    public function test_only_transactions_of_the_current_business_are_shown(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $otherBusiness = Business::factory()->create();

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $otherOutlet = Outlet::factory()->create(['business_id' => $otherBusiness->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'OWN-TRX-001',
        ]);

        $this->createSale([
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

        $this->createSale([
            'business_id' => $otherBusiness->id,
            'outlet_id' => $otherOutlet->id,
            'transaction_number' => 'LEAKED-TRX',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertDontSee('LEAKED-TRX');
    }

    public function test_search_tenant_a_does_not_find_sale_of_tenant_b(): void
    {
        [$userA, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->createSale([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'transaction_number' => 'TRX-TARGET-AAA',
        ]);
        $this->createSale([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-TARGET-BBB',
        ]);

        $this->actingAs($userA);
        $response = $this->get(route('transactions.index', ['q' => 'TARGET']));
        $response->assertOk();
        $response->assertSee('TRX-TARGET-AAA');
        $response->assertDontSee('TRX-TARGET-BBB');
    }

    public function test_filtering_by_outlet_id_of_tenant_b_does_not_leak_sales(): void
    {
        [$userA, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->createSale([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-SECRET-B',
        ]);

        $this->actingAs($userA);
        $response = $this->get(route('transactions.index', ['outlet_id' => $outletB->id]));
        $response->assertOk();
        $response->assertDontSee('TRX-SECRET-B');
    }

    public function test_switching_current_business_changes_dataset(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        Subscription::factory()->cloud()->create(['business_id' => $businessA->id]);
        Subscription::factory()->cloud()->create(['business_id' => $businessB->id]);
        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->createSale([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'transaction_number' => 'TRX-BUSINESS-A',
        ]);
        $this->createSale([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-BUSINESS-B',
        ]);

        $this->actingAs($user);

        // View Business A
        $this->withSession(['dashboard.current_business_id' => $businessA->id]);
        $responseA = $this->get(route('transactions.index'));
        $responseA->assertOk();
        $responseA->assertSee('TRX-BUSINESS-A');
        $responseA->assertDontSee('TRX-BUSINESS-B');

        // Switch to Business B
        $this->withSession(['dashboard.current_business_id' => $businessB->id]);
        $responseB = $this->get(route('transactions.index'));
        $responseB->assertOk();
        $responseB->assertSee('TRX-BUSINESS-B');
        $responseB->assertDontSee('TRX-BUSINESS-A');
    }

    // ============================================================
    // Real Data Rendering
    // ============================================================

    public function test_real_sale_transaction_number_appears_in_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale([
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

        $this->createSale([
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

        $this->createSale([
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

        $this->createSale([
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

        $this->createSale([
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

        $this->createSale([
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

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Belum Lunas');
    }

    public function test_unknown_payment_status_is_rendered_neutral_not_belum_lunas(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-UNKNOWN-PAY',
            'payment_status' => 'pending_review',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Pending Review');
        $response->assertSee('<span>Pending Review</span>', false);
        $response->assertDontSee('<span>Belum Lunas</span>', false);
    }

    public function test_payment_method_cash_is_presented_as_tunai(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale([
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

        $this->createSale([
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

    public function test_payment_method_filter_uses_raw_value_and_presentation_label(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'payment_method' => 'cash',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('value="cash"', false);
        $response->assertSee('Tunai');

        // Filtering by raw cash works
        $filterResponse = $this->get(route('transactions.index', ['payment_method' => 'cash']));
        $filterResponse->assertOk();
    }

    // ============================================================
    // Decimal Quantity, Pricing Unit & Gross Profit
    // ============================================================

    public function test_sale_item_decimal_quantity_is_preserved_in_data_raw(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
        ]);

        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DEC-001',
        ]);

        $this->createSaleItem([
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

        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
        ]);

        $this->createSaleItem([
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

    public function test_gross_profit_maintains_fractional_value_in_data(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-FRACTION',
            'gross_profit' => 123456.50,
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('123456.5');
    }

    // ============================================================
    // Metrics
    // ============================================================

    public function test_metrics_total_transactions_matches_db_count(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        for ($i = 0; $i < 3; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
            ]);
        }

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

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-EARLIER',
            'sold_at' => now()->subDays(2),
        ]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-LATEST',
            'sold_at' => now(),
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index', ['date' => 'all']));
        $response->assertOk();

        $content = $response->getContent();
        $latestPos = strpos((string) $content, 'TRX-LATEST');
        $earlierPos = strpos((string) $content, 'TRX-EARLIER');
        $this->assertNotFalse($latestPos);
        $this->assertNotFalse($earlierPos);
        $this->assertLessThan($earlierPos, $latestPos, 'Latest transaction should appear first');
    }

    public function test_transactions_page_defaults_to_today_filter(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-TODAY-ONLY',
            'subtotal' => 120000,
            'total_amount' => 120000,
            'sold_at' => now(),
        ]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-3DAYS-AGO',
            'subtotal' => 80000,
            'total_amount' => 80000,
            'sold_at' => now()->subDays(3),
        ]);

        $this->actingAs($user);

        // Default visit without query params must filter to Today ('today')
        $defaultResponse = $this->get(route('transactions.index'));
        $defaultResponse->assertOk();
        $defaultResponse->assertSee('TRX-TODAY-ONLY');
        $defaultResponse->assertDontSee('TRX-3DAYS-AGO');
        $defaultResponse->assertSee('Rp 120.000');
        $defaultResponse->assertSee('id="flatpickrDateRange"', false);
        $defaultResponse->assertSee('<option value="today" selected>', false);

        // Explicit date=all shows all historical transactions
        $allResponse = $this->get(route('transactions.index', ['date' => 'all']));
        $allResponse->assertOk();
        $allResponse->assertSee('TRX-TODAY-ONLY');
        $allResponse->assertSee('TRX-3DAYS-AGO');
        $allResponse->assertSee('Rp 200.000');
    }

    public function test_custom_date_range_and_single_day_flatpickr_filter_accurately(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = now($tz);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DAY-0',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'sold_at' => $now->copy()->setTime(14, 0),
        ]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DAY-2',
            'subtotal' => 75000,
            'total_amount' => 75000,
            'sold_at' => $now->copy()->subDays(2)->setTime(11, 30),
        ]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DAY-5',
            'subtotal' => 90000,
            'total_amount' => 90000,
            'sold_at' => $now->copy()->subDays(5)->setTime(16, 15),
        ]);

        $this->actingAs($user);

        // Single day selection via Flatpickr (start_date === end_date)
        $day2Ymd = $now->copy()->subDays(2)->format('Y-m-d');
        $singleDayResponse = $this->get(route('transactions.index', [
            'date' => 'custom',
            'start_date' => $day2Ymd,
            'end_date' => $day2Ymd,
        ]));
        $singleDayResponse->assertOk();
        $singleDayResponse->assertSee('TRX-DAY-2');
        $singleDayResponse->assertDontSee('TRX-DAY-0');
        $singleDayResponse->assertDontSee('TRX-DAY-5');
        $singleDayResponse->assertSee('Rp 75.000');

        // Multi-day range selection via Flatpickr
        $rangeResponse = $this->get(route('transactions.index', [
            'date' => 'custom',
            'start_date' => $now->copy()->subDays(5)->format('Y-m-d'),
            'end_date' => $day2Ymd,
        ]));
        $rangeResponse->assertOk();
        $rangeResponse->assertSee('TRX-DAY-2');
        $rangeResponse->assertSee('TRX-DAY-5');
        $rangeResponse->assertDontSee('TRX-DAY-0');
        $rangeResponse->assertSee('Rp 165.000');
    }

    // ============================================================
    // Pagination
    // ============================================================

    public function test_pagination_renders_nav_with_aria_current_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        for ($i = 0; $i < 5; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
            ]);
        }

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

        for ($i = 0; $i < 3; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
            ]);
        }

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
        for ($i = 0; $i < 26; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'status' => 'completed',
            ]);
        }

        $this->actingAs($user);
        // Filter by status=completed — all 26 records match, triggering pagination
        $response = $this->get(route('transactions.index', ['status' => 'completed']));
        $response->assertOk();
        // Pagination links should contain the status parameter
        $response->assertSee('status=completed', false);
    }

    public function test_page_two_summary_metrics_represent_entire_dataset_not_active_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Create 26 transactions: 20 cash, 6 qris. Each total_amount = 100,000.
        // Total = 26 * 100,000 = 2,600,000. Average = 100,000. Top payment = Tunai.
        for ($i = 0; $i < 26; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'total_amount' => 100000,
                'subtotal' => 100000,
                'payment_method' => $i < 20 ? 'cash' : 'qris',
                'sold_at' => now()->subMinutes(26 - $i),
                'transaction_number' => sprintf('TRX-PAGE-%03d', $i + 1),
            ]);
        }

        $this->actingAs($user);

        // Page 1
        $responsePage1 = $this->get(route('transactions.index', ['page' => 1]));
        $responsePage1->assertOk();

        // Page 2
        $responsePage2 = $this->get(route('transactions.index', ['page' => 2]));
        $responsePage2->assertOk();

        // Page 2 has only 1 item (the 1st created record, oldest by sold_at DESC)
        $responsePage2->assertSee('TRX-PAGE-001');
        $responsePage2->assertDontSee('TRX-PAGE-026'); // on page 1

        // Summary metrics on page 2 must match page 1 and reflect all 26 records
        $responsePage2->assertSee('26'); // total transactions
        $responsePage2->assertSee('Rp 2.600.000'); // total sales
        $responsePage2->assertSee('Rp 100.000'); // average sales
        $responsePage2->assertSee('Tunai'); // top payment method across all 26
    }

    public function test_filtered_page_two_metrics_represent_entire_filtered_dataset(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Create 30 completed transactions with total_amount = 50,000
        // Total filtered = 30 * 50,000 = 1,500,000. Average = 50,000.
        // And create 5 cancelled transactions with total_amount = 999,000
        for ($i = 0; $i < 30; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'status' => 'completed',
                'total_amount' => 50000,
                'subtotal' => 50000,
                'payment_method' => 'transfer',
                'sold_at' => now()->subMinutes(30 - $i),
            ]);
        }

        for ($i = 0; $i < 5; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'status' => 'cancelled',
                'total_amount' => 999000,
                'subtotal' => 999000,
            ]);
        }

        $this->actingAs($user);

        // Filter status=completed on page 2 (contains 5 records: 30 - 25 = 5)
        $response = $this->get(route('transactions.index', ['status' => 'completed', 'page' => 2]));
        $response->assertOk();

        // Summary metrics must reflect ALL 30 completed records (not just the 5 on page 2, and excluding cancelled)
        $response->assertSee('30'); // total transactions
        $response->assertSee('Rp 1.500.000'); // total sales (30 * 50,000)
        $response->assertSee('Rp 50.000'); // average transaction
        $response->assertSee('Transfer'); // top payment method
        $response->assertDontSee('Rp 999.000'); // cancelled transaction amount excluded
    }

    // ============================================================
    // Filter Persistence & Safe Validation
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

    public function test_invalid_custom_date_does_not_cause_500(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->actingAs($user);
        $response = $this->get(route('transactions.index', [
            'date' => 'custom',
            'start_date' => 'not-a-date',
            'end_date' => 'invalid-end-date',
        ]));
        $response->assertOk();
    }

    // ============================================================
    // Empty State (Truly empty vs Filtered zero results)
    // ============================================================

    public function test_truly_empty_business_renders_belum_ada_transaksi(): void
    {
        [$user] = $this->makeUserWithBusiness();
        $this->actingAs($user);

        $response = $this->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Transaksi');
        $response->assertSee('Transaksi yang telah tersinkron ke Cloud akan muncul di sini.');
        $response->assertDontSee('Transaksi Tidak Ditemukan');
    }

    public function test_filtered_zero_result_renders_transaksi_tidak_ditemukan(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-EXISTS',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index', ['q' => 'NON_EXISTENT_SEARCH_STRING']));
        $response->assertOk();
        $response->assertSee('Transaksi Tidak Ditemukan');
        $response->assertSee('Coba ubah pencarian atau filter yang digunakan.');
        $response->assertDontSee('Belum Ada Transaksi');
    }

    public function test_reset_filtered_empty_state_links_to_transactions_index(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-EXISTS',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('transactions.index', ['q' => 'NON_EXISTENT_SEARCH_STRING']));
        $response->assertOk();
        $response->assertSee(route('transactions.index'));
        $response->assertSee('Reset Filter');
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

        $this->createSale([
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
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        // Set session so middleware resolves this business
        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$user, $business];
    }

    /**
     * Local test helper to create a Sale without model factory.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createSale(array $attributes = []): Sale
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        $subtotal = $attributes['subtotal'] ?? 100000;
        $discount = $attributes['discount_amount'] ?? 0;
        $tax = $attributes['tax_amount'] ?? 0;
        $total = $attributes['total_amount'] ?? ($subtotal - $discount + $tax);

        return Sale::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'customer_id' => null,
            'shift_id' => null,
            'transaction_number' => 'TRX-'.strtoupper(uniqid()),
            'status' => 'completed',
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'gross_profit' => 40000.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => null,
            'change_amount' => null,
            'order_status' => null,
            'estimated_completed_at' => null,
            'note' => null,
            'customer_snapshot' => null,
            'business_snapshot' => null,
            'sold_at' => now(),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }

    /**
     * Local test helper to create a SaleItem without model factory.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createSaleItem(array $attributes = []): SaleItem
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $saleId = $attributes['sale_id'] ?? $this->createSale(['business_id' => $businessId])->id;
        $unitPrice = $attributes['unit_price'] ?? 25000;
        $quantity = $attributes['quantity'] ?? 1;

        return SaleItem::create(array_merge([
            'business_id' => $businessId,
            'sale_id' => $saleId,
            'product_id' => null,
            'product_name' => 'Item Sample',
            'product_sku' => 'SKU-SAMPLE',
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'line_total' => $attributes['line_total'] ?? (int) ($unitPrice * $quantity),
            'cost_snapshot' => $attributes['cost_snapshot'] ?? 15000,
            'unit' => 'pcs',
            'kind' => 'product',
            'pricing_unit' => null,
            'line_cost' => 0,
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }

    // ============================================================
    // Owner Transaction Editing (DASH-TX-EDIT)
    // ============================================================

    public function test_owner_sees_edit_button_and_edit_modal_in_transactions_page(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $owner->businesses()->attach($business->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-EDIT-TEST',
            'payment_method' => 'Tunai',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($owner)->get(route('transactions.index'));
        $response->assertOk();
        $response->assertSee('title="Edit Transaksi"', false);
        $response->assertSee('id="editTransactionForm"', false);
        $response->assertSee('id="drawerEditBtn"', false);
        $response->assertSee('Edit Transaksi');
    }

    public function test_member_cannot_see_edit_button_and_cannot_update_transaction(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $member->businesses()->attach($business->id, ['role' => 'member']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-MEMBER-TEST',
            'payment_method' => 'Tunai',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($member)->get(route('transactions.index'));
        $response->assertOk();
        $response->assertDontSee('title="Edit Transaksi"', false);
        $response->assertDontSee('id="editTransactionForm"', false);
        $response->assertDontSee('id="drawerEditBtn"', false);

        // Attempt direct patch
        $this->actingAs($member)
            ->patch(route('transactions.update', $sale->id), [
                'payment_method' => 'QRIS',
                'payment_status' => 'unpaid',
                'status' => 'cancelled',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_update_transaction_payment_and_status(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $owner->businesses()->attach($business->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-UPDATE-OK',
            'payment_method' => 'Tunai',
            'payment_status' => 'unpaid',
            'status' => 'completed',
            'total_amount' => 50000,
            'paid_at' => null,
        ]);

        $response = $this->actingAs($owner)
            ->patch(route('transactions.update', $sale->id), [
                'payment_method' => 'QRIS',
                'payment_status' => 'paid',
                'status' => 'completed',
                'note' => 'Diubah ke QRIS setelah konfirmasi pelanggan',
            ]);

        $response->assertRedirect(route('transactions.index'));
        $response->assertSessionHas('status');

        $sale->refresh();
        $this->assertEquals('QRIS', $sale->payment_method);
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('completed', $sale->status);
        $this->assertNotNull($sale->paid_at);
        $this->assertEquals('Diubah ke QRIS setelah konfirmasi pelanggan', $sale->note);
    }

    public function test_owner_can_cancel_transaction_and_update_cash(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $owner->businesses()->attach($business->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-CANCEL-OK',
            'payment_method' => 'Tunai',
            'payment_status' => 'paid',
            'status' => 'completed',
            'total_amount' => 50000,
        ]);

        $response = $this->actingAs($owner)
            ->patch(route('transactions.update', $sale->id), [
                'payment_method' => 'Tunai',
                'payment_status' => 'unpaid',
                'status' => 'cancelled',
                'cash_received' => 100000,
                'note' => 'Dibatalkan atas permintaan pelanggan',
            ]);

        $response->assertRedirect(route('transactions.index'));

        $sale->refresh();
        $this->assertEquals('Tunai', $sale->payment_method);
        $this->assertEquals('unpaid', $sale->payment_status);
        $this->assertEquals('cancelled', $sale->status);
        $this->assertEquals(100000, $sale->cash_received);
        $this->assertEquals(50000, $sale->change_amount);
        $this->assertNull($sale->paid_at);
        $this->assertEquals('Dibatalkan atas permintaan pelanggan', $sale->note);
    }

    public function test_owner_cannot_update_transaction_of_different_business(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        $owner->businesses()->attach($businessA->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $businessA->id]);

        $saleB = $this->createSale([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-TENANT-ISOLATION',
        ]);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->patch(route('transactions.update', $saleB->id), [
                'payment_method' => 'QRIS',
                'payment_status' => 'paid',
                'status' => 'completed',
            ])
            ->assertNotFound();
    }

    /**
     * Local test helper to create a Shift without model factory.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createShift(array $attributes = []): Shift
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        return Shift::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'shift_number' => 'SHIFT-'.uniqid(),
            'status' => 'open',
            'opening_cash' => 100000,
            'closing_cash' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => null,
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }
}
