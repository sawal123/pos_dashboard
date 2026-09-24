<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomersPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Auth & Access
    // ============================================================

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('customers.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_user_without_business_gets_empty_state(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('Pelanggan');
        $response->assertSee('Belum Ada Pelanggan');
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_customers'] === 0
                && $summary['active_customers'] === 0
                && $summary['customers_with_purchases'] === 0
                && $summary['customers_without_purchases'] === 0;
        });
    }

    // ============================================================
    // Tenant Isolation
    // ============================================================

    public function test_active_customer_of_current_business_is_visible_and_foreign_customer_is_hidden(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();

        $this->createCustomer(['business_id' => $business->id, 'name' => 'Pelanggan Milik Kita']);
        $this->createCustomer(['business_id' => $foreign->id, 'name' => 'Pelanggan Bisnis Lain']);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('Pelanggan Milik Kita');
        $response->assertDontSee('Pelanggan Bisnis Lain');
    }

    public function test_detail_endpoint_returns_404_for_customer_of_other_business(): void
    {
        [$user] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();
        $foreignCustomer = $this->createCustomer(['business_id' => $foreign->id]);

        $this->actingAs($user)->getJson(route('customers.detail', $foreignCustomer->id))
            ->assertNotFound();
    }

    public function test_switching_business_changes_customer_dataset(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $businessA->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);
        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->createCustomer(['business_id' => $businessA->id, 'name' => 'Pelanggan Biz A']);
        $this->createCustomer(['business_id' => $businessB->id, 'name' => 'Pelanggan Biz B']);

        $responseA = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('customers.index'));
        $responseA->assertOk();
        $responseA->assertSee('Pelanggan Biz A');
        $responseA->assertDontSee('Pelanggan Biz B');

        $responseB = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('customers.index'));
        $responseB->assertOk();
        $responseB->assertSee('Pelanggan Biz B');
        $responseB->assertDontSee('Pelanggan Biz A');
    }

    public function test_personal_data_is_not_leaked_across_tenants(): void
    {
        [$userA, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        $this->createCustomer([
            'business_id' => $businessB->id,
            'name' => 'Rahasia Bisnis B',
            'phone' => '089900001111',
            'email' => 'rahasia-b@example.com',
        ]);

        $response = $this->actingAs($userA)->get(route('customers.index'));

        $response->assertOk();
        $response->assertDontSee('Rahasia Bisnis B');
        $response->assertDontSee('089900001111');
        $response->assertDontSee('rahasia-b@example.com');
    }

    // ============================================================
    // Summary Metrics
    // ============================================================

    public function test_summary_counts_exclude_deleted_and_split_purchase_status(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $withPurchase = $this->createCustomer(['business_id' => $business->id, 'status' => 'active']);
        $withoutPurchase = $this->createCustomer(['business_id' => $business->id, 'status' => 'inactive']);
        $deleted = $this->createCustomer(['business_id' => $business->id, 'status' => 'deleted']);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $withPurchase->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 250000,
        ]);
        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $deleted->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 999000,
        ]);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_customers'] === 2
                && $summary['active_customers'] === 1
                && $summary['customers_with_purchases'] === 1
                && $summary['customers_without_purchases'] === 1;
        });
    }

    // ============================================================
    // Filters
    // ============================================================

    public function test_search_matches_name_phone_and_email(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createCustomer([
            'business_id' => $business->id,
            'name' => 'Andini Wijaya',
            'phone' => '081200000001',
            'email' => 'andini@example.com',
        ]);
        $this->createCustomer([
            'business_id' => $business->id,
            'name' => 'Bagas Prakoso',
            'phone' => '085700000002',
            'email' => 'bagas@example.com',
        ]);

        $byName = $this->actingAs($user)->get(route('customers.index', ['q' => 'Andini']));
        $byName->assertOk();
        $byName->assertSee('Andini Wijaya');
        $byName->assertDontSee('Bagas Prakoso');

        $byPhone = $this->actingAs($user)->get(route('customers.index', ['q' => '085700000002']));
        $byPhone->assertOk();
        $byPhone->assertSee('Bagas Prakoso');
        $byPhone->assertDontSee('Andini Wijaya');

        $byEmail = $this->actingAs($user)->get(route('customers.index', ['q' => 'andini@example.com']));
        $byEmail->assertOk();
        $byEmail->assertSee('Andini Wijaya');
        $byEmail->assertDontSee('Bagas Prakoso');
    }

    public function test_status_filter_including_deleted_customer(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createCustomer(['business_id' => $business->id, 'name' => 'Konsumen Alpha', 'status' => 'active']);
        $this->createCustomer(['business_id' => $business->id, 'name' => 'Konsumen Beta', 'status' => 'inactive']);
        $this->createCustomer(['business_id' => $business->id, 'name' => 'Konsumen Gamma', 'status' => 'deleted']);

        // Default list hides deleted customers.
        $default = $this->actingAs($user)->get(route('customers.index'));
        $default->assertOk();
        $default->assertSee('Konsumen Alpha');
        $default->assertSee('Konsumen Beta');
        $default->assertDontSee('Konsumen Gamma');

        // Explicit status filter can reveal a deleted customer.
        $deleted = $this->actingAs($user)->get(route('customers.index', ['status' => 'deleted']));
        $deleted->assertOk();
        $deleted->assertSee('Konsumen Gamma');
        $deleted->assertDontSee('Konsumen Alpha');

        // Active filter only returns active customers.
        $active = $this->actingAs($user)->get(route('customers.index', ['status' => 'active']));
        $active->assertOk();
        $active->assertSee('Konsumen Alpha');
        $active->assertDontSee('Konsumen Beta');
        $active->assertDontSee('Konsumen Gamma');
    }

    // ============================================================
    // Pagination
    // ============================================================

    public function test_pagination_is_twenty_five_items_and_preserves_query_string(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createCustomer([
                'business_id' => $business->id,
                'status' => 'active',
                'name' => sprintf('Pelanggan Halaman %02d', $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('customers.index', ['status' => 'active', 'page' => 1]));

        $response->assertOk();
        $response->assertSee('status=active', false);
        $response->assertViewHas('customers', function ($customers): bool {
            return $customers->count() === 25 && $customers->total() === 30;
        });
    }

    // ============================================================
    // Purchase Metrics
    // ============================================================

    public function test_completed_paid_transactions_count_and_total_are_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $customer = $this->createCustomer(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 120000,
        ]);
        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 80000,
        ]);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertViewHas('customers', function ($customers): bool {
            $row = $customers->first();

            return $row['transactions_count'] === 2 && $row['purchase_total'] === 200000;
        });
    }

    public function test_unpaid_and_canceled_transactions_do_not_inflate_purchase_total(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $customer = $this->createCustomer(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100000,
        ]);
        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'total_amount' => 500000,
        ]);
        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'status' => 'cancelled',
            'payment_status' => 'paid',
            'total_amount' => 700000,
        ]);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertViewHas('customers', function ($customers): bool {
            $row = $customers->first();

            return $row['transactions_count'] === 1 && $row['purchase_total'] === 100000;
        });
    }

    public function test_customer_without_transactions_shows_zero_metrics(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $customer = $this->createCustomer(['business_id' => $business->id, 'name' => 'Pelanggan Tanpa Transaksi']);

        $listResponse = $this->actingAs($user)->get(route('customers.index'));
        $listResponse->assertOk();
        $listResponse->assertViewHas('customers', function ($customers): bool {
            $row = $customers->first();

            return $row['transactions_count'] === 0
                && $row['purchase_total'] === 0
                && $row['last_purchase_at'] === null;
        });

        $detailResponse = $this->actingAs($user)->getJson(route('customers.detail', $customer->id));
        $detailResponse->assertOk()
            ->assertJsonPath('metrics.transactions_count', 0)
            ->assertJsonPath('metrics.purchase_total', 0)
            ->assertJsonPath('metrics.first_purchase_at', null)
            ->assertJsonPath('metrics.last_purchase_at', null)
            ->assertJsonPath('recent_transactions', []);
    }

    // ============================================================
    // Detail Endpoint
    // ============================================================

    public function test_detail_history_only_contains_transactions_of_the_customer_and_tenant(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $customer = $this->createCustomer(['business_id' => $business->id, 'name' => 'Pelanggan Utama']);
        $otherCustomer = $this->createCustomer(['business_id' => $business->id, 'name' => 'Pelanggan Lain']);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'transaction_number' => 'TRX-MILIK-SENDIRI',
            'total_amount' => 150000,
        ]);
        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $otherCustomer->id,
            'transaction_number' => 'TRX-MILIK-ORANG-LAIN',
            'total_amount' => 300000,
        ]);

        $response = $this->actingAs($user)->getJson(route('customers.detail', $customer->id));

        $response->assertOk();
        $numbers = array_column($response->json('recent_transactions'), 'transaction_number');

        $this->assertContains('TRX-MILIK-SENDIRI', $numbers);
        $this->assertNotContains('TRX-MILIK-ORANG-LAIN', $numbers);
        $response->assertJsonPath('metrics.transactions_count', 1);
        $response->assertJsonPath('metrics.purchase_total', 150000);
    }

    public function test_recent_transactions_are_ordered_newest_first_and_limited_to_ten(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $customer = $this->createCustomer(['business_id' => $business->id]);

        for ($i = 1; $i <= 12; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'customer_id' => $customer->id,
                'transaction_number' => sprintf('TRX-ORDER-%02d', $i),
                'total_amount' => 10000 * $i,
                'sold_at' => now()->subMinutes(20 - $i),
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('customers.detail', $customer->id));

        $response->assertOk();
        $transactions = $response->json('recent_transactions');

        $this->assertCount(10, $transactions);
        $this->assertSame('TRX-ORDER-12', $transactions[0]['transaction_number']);
        $this->assertSame('TRX-ORDER-03', $transactions[9]['transaction_number']);
    }

    public function test_detail_exposes_original_status_for_non_purchase_history(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $customer = $this->createCustomer(['business_id' => $business->id]);

        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'transaction_number' => 'TRX-CANCEL-HISTORY',
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'total_amount' => 400000,
        ]);

        $response = $this->actingAs($user)->getJson(route('customers.detail', $customer->id));

        $response->assertOk();
        $response->assertJsonPath('metrics.transactions_count', 0);
        $response->assertJsonPath('metrics.purchase_total', 0);
        $response->assertJsonPath('recent_transactions.0.status', 'Dibatalkan');
        $response->assertJsonPath('recent_transactions.0.payment_status', 'Belum Lunas');
    }

    // ============================================================
    // Validation & Safety
    // ============================================================

    public function test_invalid_query_parameters_do_not_cause_500(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCustomer(['business_id' => $business->id, 'name' => 'Pelanggan Valid']);

        $response = $this->actingAs($user)->get(route('customers.index', [
            'q' => ['not', 'a', 'string'],
            'status' => str_repeat('x', 80),
            'page' => -3,
        ]));

        $response->assertOk();
        $response->assertSee('Pelanggan Valid');
    }

    public function test_unknown_status_is_presented_neutrally(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCustomer(['business_id' => $business->id, 'name' => 'Pelanggan Unik', 'status' => 'pending_review']);

        $response = $this->actingAs($user)->get(route('customers.index', ['status' => 'pending_review']));

        $response->assertOk();
        $response->assertSee('Pending Review');
        $response->assertSee('Pelanggan Unik');
    }

    // ============================================================
    // UI Structure
    // ============================================================

    public function test_page_renders_expected_structure_and_sidebar_route(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('data-customers-page="true"', false);
        $response->assertSee('action="'.route('customers.index').'"', false);
        $response->assertSee(route('customers.index'));
        $response->assertSee('id="customerDrawerWrapper"', false);
        $response->assertSee('Pembelian Selesai &amp; Lunas', false);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeUserWithBusiness(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $business->id]);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$user, $business];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCustomer(array $attributes = []): Customer
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;

        return Customer::factory()->create(array_merge([
            'business_id' => $businessId,
            'name' => 'Pelanggan '.strtoupper(uniqid()),
            'phone' => null,
            'email' => null,
            'address' => null,
            'notes' => null,
            'status' => 'active',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSale(array $attributes = []): Sale
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        return Sale::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'customer_id' => null,
            'shift_id' => null,
            'transaction_number' => 'TRX-'.strtoupper(uniqid()),
            'status' => 'completed',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => null,
            'change_amount' => null,
            'gross_profit' => 0,
            'order_status' => null,
            'estimated_completed_at' => null,
            'note' => null,
            'customer_snapshot' => null,
            'business_snapshot' => null,
            'sold_at' => now(),
        ], $attributes));
    }
}
