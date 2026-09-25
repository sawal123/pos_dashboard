<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LaundryOrdersPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Access
    // ============================================================

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('laundry-orders.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        [$user] = $this->makeUserWithBusiness(verified: false);

        $this->actingAs($user)->get(route('laundry-orders.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_user_without_business_gets_empty_state(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index'));

        $response->assertOk();
        $response->assertSee('Belum Ada Pesanan Laundry');
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['total_orders'] === 0
            && $summary['masuk'] === 0
            && $summary['overdue'] === 0);
        $response->assertViewHas('hasAnyOrders', false);
    }

    // ============================================================
    // Listing & tenant isolation
    // ============================================================

    public function test_active_business_laundry_orders_are_listed(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $sale = $this->createSale($business, [
            'transaction_number' => 'LDR-OUR-0001',
            'order_status' => 'Masuk',
        ]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index'));

        $response->assertOk();
        $response->assertSee('LDR-OUR-0001');
        $response->assertSee(route('laundry-orders.index'), false);
        $this->assertSame($sale->id, $this->orderRow($response, 'LDR-OUR-0001')['id']);
    }

    public function test_orders_of_other_businesses_are_not_listed(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, ['transaction_number' => 'LDR-OUR-0002', 'order_status' => 'Masuk']);

        $foreign = Business::factory()->create();
        $this->createSale($foreign, [
            'transaction_number' => 'LDR-FOREIGN-0001',
            'order_status' => 'Masuk',
            'customer_snapshot' => ['name' => 'Rahasia Bisnis Lain', 'phone' => '089900001111'],
        ]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index'));

        $response->assertOk();
        $response->assertSee('LDR-OUR-0002');
        $response->assertDontSee('LDR-FOREIGN-0001');
        $response->assertDontSee('Rahasia Bisnis Lain');
        $response->assertDontSee('089900001111');
    }

    public function test_detail_of_foreign_business_returns_404(): void
    {
        [$user] = $this->makeUserWithBusiness();

        $foreign = Business::factory()->create();
        $foreignSale = $this->createSale($foreign, ['order_status' => 'Masuk']);

        $this->actingAs($user)->getJson(route('laundry-orders.detail', $foreignSale->id))
            ->assertNotFound();
    }

    public function test_non_laundry_transactions_are_not_listed(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, [
            'transaction_number' => 'CAFE-NOT-LAUNDRY',
            'order_status' => null,
        ]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index'));

        $response->assertOk();
        $response->assertSee('Belum Ada Pesanan Laundry');
        $response->assertDontSee('CAFE-NOT-LAUNDRY');
    }

    public function test_detail_of_non_laundry_transaction_returns_404(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $regularSale = $this->createSale($business, ['order_status' => null]);

        $this->actingAs($user)->getJson(route('laundry-orders.detail', $regularSale->id))
            ->assertNotFound();
    }

    public function test_switching_business_changes_dataset(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->createSale($businessA, ['transaction_number' => 'LDR-BIZ-A', 'order_status' => 'Masuk']);
        $this->createSale($businessB, ['transaction_number' => 'LDR-BIZ-B', 'order_status' => 'Masuk']);

        $responseA = $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $businessA->id])
            ->get(route('laundry-orders.index'));
        $responseA->assertSee('LDR-BIZ-A');
        $responseA->assertDontSee('LDR-BIZ-B');

        $responseB = $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $businessB->id])
            ->get(route('laundry-orders.index'));
        $responseB->assertSee('LDR-BIZ-B');
        $responseB->assertDontSee('LDR-BIZ-A');
    }

    // ============================================================
    // Summary & overdue
    // ============================================================

    public function test_summary_counts_each_order_status(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createSale($business, ['order_status' => 'Masuk']);
        $this->createSale($business, ['order_status' => 'Masuk']);
        $this->createSale($business, ['order_status' => 'Diproses']);
        $this->createSale($business, ['order_status' => 'Siap Diambil']);
        $this->createSale($business, ['order_status' => 'Selesai']);

        // A non-laundry sale must not be counted.
        $this->createSale($business, ['order_status' => null]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['total_orders'] === 5
            && $summary['masuk'] === 2
            && $summary['diproses'] === 1
            && $summary['siap_diambil'] === 1
            && $summary['selesai'] === 1);
    }

    public function test_order_without_estimate_is_not_overdue(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, [
            'transaction_number' => 'LDR-NO-ESTIMATE',
            'order_status' => 'Masuk',
            'estimated_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['overdue'] === 0);
        $this->assertFalse($this->orderRow($response, 'LDR-NO-ESTIMATE')['is_overdue']);
    }

    public function test_completed_order_past_estimate_is_not_overdue(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, [
            'transaction_number' => 'LDR-DONE-LATE',
            'order_status' => 'Selesai',
            'estimated_completed_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['overdue'] === 0);
        $this->assertFalse($this->orderRow($response, 'LDR-DONE-LATE')['is_overdue']);
    }

    public function test_late_order_not_completed_is_overdue(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, [
            'transaction_number' => 'LDR-OVERDUE',
            'order_status' => 'Diproses',
            'estimated_completed_at' => now()->subHours(3),
        ]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['overdue'] === 1);
        $this->assertTrue($this->orderRow($response, 'LDR-OVERDUE')['is_overdue']);

        $filtered = $this->actingAs($user)->get(route('laundry-orders.index', ['overdue' => 'overdue']));
        $filtered->assertOk();
        $filtered->assertSee('LDR-OVERDUE');
    }

    // ============================================================
    // Filters
    // ============================================================

    public function test_filters_by_order_status(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, ['transaction_number' => 'LDR-IN', 'order_status' => 'Masuk']);
        $this->createSale($business, ['transaction_number' => 'LDR-PROC', 'order_status' => 'Diproses']);

        $response = $this->actingAs($user)->get(route('laundry-orders.index', ['order_status' => 'Diproses']));

        $response->assertOk();
        $response->assertSee('LDR-PROC');
        $response->assertDontSee('LDR-IN');
    }

    public function test_filters_by_payment_status(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, [
            'transaction_number' => 'LDR-PAID',
            'order_status' => 'Masuk',
            'payment_status' => 'paid',
        ]);
        $this->createSale($business, [
            'transaction_number' => 'LDR-UNPAID',
            'order_status' => 'Masuk',
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index', ['payment_status' => 'unpaid']));

        $response->assertOk();
        $response->assertSee('LDR-UNPAID');
        $response->assertDontSee('LDR-PAID');
    }

    public function test_filters_by_outlet_and_rejects_foreign_outlet(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outletA = Outlet::factory()->create(['business_id' => $business->id]);
        $outletB = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale($business, [
            'transaction_number' => 'LDR-OUT-A',
            'outlet_id' => $outletA->id,
            'order_status' => 'Masuk',
        ]);
        $this->createSale($business, [
            'transaction_number' => 'LDR-OUT-B',
            'outlet_id' => $outletB->id,
            'order_status' => 'Masuk',
        ]);

        $byOutlet = $this->actingAs($user)->get(route('laundry-orders.index', ['outlet_id' => $outletA->id]));
        $byOutlet->assertOk();
        $byOutlet->assertSee('LDR-OUT-A');
        $byOutlet->assertDontSee('LDR-OUT-B');

        // A foreign outlet id is ignored (not applied, no 500, no leak).
        $foreignOutlet = Outlet::factory()->create();
        $foreign = $this->actingAs($user)->get(route('laundry-orders.index', ['outlet_id' => $foreignOutlet->id]));
        $foreign->assertOk();
        $foreign->assertSee('LDR-OUT-A');
        $foreign->assertSee('LDR-OUT-B');
    }

    public function test_searches_transaction_number_customer_name_and_phone(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, [
            'transaction_number' => 'LDR-SEARCH-01',
            'order_status' => 'Masuk',
            'customer_snapshot' => ['name' => 'Andini Wijaya', 'phone' => '081200000001'],
        ]);
        $this->createSale($business, [
            'transaction_number' => 'LDR-SEARCH-02',
            'order_status' => 'Masuk',
            'customer_snapshot' => ['name' => 'Bagas Prakoso', 'phone' => '085700000002'],
        ]);

        $byNumber = $this->actingAs($user)->get(route('laundry-orders.index', ['q' => 'SEARCH-01']));
        $byNumber->assertSee('LDR-SEARCH-01');
        $byNumber->assertDontSee('LDR-SEARCH-02');

        $byName = $this->actingAs($user)->get(route('laundry-orders.index', ['q' => 'Andini']));
        $byName->assertSee('LDR-SEARCH-01');
        $byName->assertDontSee('LDR-SEARCH-02');

        $byPhone = $this->actingAs($user)->get(route('laundry-orders.index', ['q' => '085700000002']));
        $byPhone->assertSee('LDR-SEARCH-02');
        $byPhone->assertDontSee('LDR-SEARCH-01');
    }

    public function test_filters_by_date_range(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, [
            'transaction_number' => 'LDR-TODAY',
            'order_status' => 'Masuk',
            'sold_at' => now(),
        ]);
        $this->createSale($business, [
            'transaction_number' => 'LDR-OLD',
            'order_status' => 'Masuk',
            'sold_at' => now()->subDays(20),
        ]);

        $today = $this->actingAs($user)->get(route('laundry-orders.index', ['date' => 'today']));
        $today->assertOk();
        $today->assertSee('LDR-TODAY');
        $today->assertDontSee('LDR-OLD');

        $custom = $this->actingAs($user)->get(route('laundry-orders.index', [
            'date' => 'custom',
            'start_date' => now()->subDays(25)->format('Y-m-d'),
            'end_date' => now()->subDays(15)->format('Y-m-d'),
        ]));
        $custom->assertOk();
        $custom->assertSee('LDR-OLD');
        $custom->assertDontSee('LDR-TODAY');
    }

    public function test_pagination_is_twenty_five_items_and_preserves_query_string(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createSale($business, [
                'transaction_number' => sprintf('LDR-PAGE-%02d', $i),
                'order_status' => 'Masuk',
            ]);
        }

        $response = $this->actingAs($user)->get(route('laundry-orders.index', ['order_status' => 'Masuk', 'page' => 1]));

        $response->assertOk();
        $response->assertSee('order_status=Masuk', false);
        $response->assertViewHas('orders', fn ($orders): bool => $orders->count() === 25 && $orders->total() === 30);
    }

    // ============================================================
    // Detail: items, decimal quantity, snapshot price
    // ============================================================

    public function test_decimal_quantity_is_preserved(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $sale = $this->createSale($business, ['transaction_number' => 'LDR-DECIMAL', 'order_status' => 'Masuk']);
        $this->createSaleItem($sale, ['quantity' => 2.5, 'unit_price' => 10000, 'line_total' => 25000]);

        $response = $this->actingAs($user)->getJson(route('laundry-orders.detail', $sale->id));

        $response->assertOk();
        $item = $response->json('items.0');
        $this->assertEquals(2.5, $item['quantity']);
        $this->assertSame('2.500', $item['quantity_raw']);
        $this->assertSame('2,5', $item['quantity_display']);
        $this->assertSame('kg', $item['unit']);
    }

    public function test_item_price_comes_from_sale_item_snapshot(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'price' => 10000]);
        $sale = $this->createSale($business, ['transaction_number' => 'LDR-SNAPSHOT', 'order_status' => 'Masuk']);
        $this->createSaleItem($sale, ['unit_price' => 10000, 'quantity' => 2, 'line_total' => 20000], $product);

        // The product price changes after the transaction.
        $product->forceFill(['price' => 99999])->save();

        $response = $this->actingAs($user)->getJson(route('laundry-orders.detail', $sale->id));

        $response->assertOk();
        $this->assertSame(10000, $response->json('items.0.unit_price'));
        $this->assertSame(20000, $response->json('items.0.line_total'));
    }

    public function test_customer_identity_falls_back_to_relation_then_generic_label(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
            'name' => 'Pelanggan Relasi',
            'phone' => '081111111111',
        ]);

        $withRelation = $this->createSale($business, [
            'transaction_number' => 'LDR-RELATION',
            'order_status' => 'Masuk',
            'customer_id' => $customer->id,
        ]);
        $walkIn = $this->createSale($business, [
            'transaction_number' => 'LDR-WALKIN',
            'order_status' => 'Masuk',
        ]);

        $relation = $this->actingAs($user)->getJson(route('laundry-orders.detail', $withRelation->id));
        $relation->assertOk();
        $this->assertSame('Pelanggan Relasi', $relation->json('customer_name'));
        $this->assertSame('081111111111', $relation->json('customer_phone'));

        $generic = $this->actingAs($user)->getJson(route('laundry-orders.detail', $walkIn->id));
        $generic->assertOk();
        $this->assertSame('Pelanggan Umum', $generic->json('customer_name'));
    }

    // ============================================================
    // Revenue separation & status independence
    // ============================================================

    public function test_canceled_or_unpaid_orders_are_not_counted_as_revenue(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        // Realised: completed + paid.
        $this->createSale($business, [
            'order_status' => 'Selesai',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 25000,
        ]);
        // Canceled but "paid" — must be excluded.
        $this->createSale($business, [
            'order_status' => 'Selesai',
            'status' => 'cancelled',
            'payment_status' => 'paid',
            'total_amount' => 50000,
        ]);
        // Completed but unpaid — must be excluded.
        $this->createSale($business, [
            'order_status' => 'Selesai',
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'total_amount' => 100000,
        ]);

        $response = $this->actingAs($user)->get(route('laundry-orders.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['paid_revenue'] === 25000);
    }

    public function test_payment_status_is_never_mixed_with_order_status(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $sale = $this->createSale($business, [
            'transaction_number' => 'LDR-DONE-UNPAID',
            'order_status' => 'Selesai',
            'status' => 'unpaid',
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->getJson(route('laundry-orders.detail', $sale->id));

        $response->assertOk();
        $this->assertSame('Selesai', $response->json('order_status'));
        $this->assertSame('done', $response->json('order_status_category'));
        $this->assertSame('Belum Lunas', $response->json('payment_status'));
        $this->assertSame('unpaid', $response->json('payment_status_raw'));
    }

    // ============================================================
    // Robustness & isolation
    // ============================================================

    public function test_invalid_filter_parameters_do_not_cause_500(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, ['transaction_number' => 'LDR-VALID', 'order_status' => 'Masuk']);

        $response = $this->actingAs($user)->get(route('laundry-orders.index', [
            'q' => ['array-instead-of-string'],
            'order_status' => ['Masuk'],
            'payment_status' => 'bogus',
            'outlet_id' => 'not-an-int',
            'date' => 'yesterday',
            'overdue' => 'maybe',
            'start_date' => 'not-a-date',
            'end_date' => '2026-13-45',
            'page' => -3,
        ]));

        $response->assertOk();
        $response->assertSee('LDR-VALID');
    }

    public function test_customer_and_item_data_from_other_business_does_not_leak(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale($business, ['transaction_number' => 'LDR-MINE', 'order_status' => 'Masuk']);

        $foreign = Business::factory()->create();
        $foreignSale = $this->createSale($foreign, [
            'transaction_number' => 'LDR-FOREIGN-SECRET',
            'order_status' => 'Masuk',
            'customer_snapshot' => ['name' => 'Nama Rahasia Asing', 'phone' => '089977776666'],
        ]);
        $this->createSaleItem($foreignSale, [
            'product_name' => 'Layanan Rahasia Asing',
            'product_sku' => 'SECRET-SKU',
        ]);

        $list = $this->actingAs($user)->get(route('laundry-orders.index'));
        $list->assertOk();
        $list->assertDontSee('LDR-FOREIGN-SECRET');
        $list->assertDontSee('Nama Rahasia Asing');
        $list->assertDontSee('089977776666');
        $list->assertDontSee('Layanan Rahasia Asing');
        $list->assertDontSee('SECRET-SKU');

        $this->actingAs($user)->getJson(route('laundry-orders.detail', $foreignSale->id))
            ->assertNotFound();
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeUserWithBusiness(bool $verified = true): array
    {
        $user = User::factory()->create([
            'email_verified_at' => $verified ? now() : null,
        ]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        if ($verified) {
            $this->withSession([DashboardBusinessContext::SESSION_KEY => $business->id]);
        }

        return [$user, $business];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSale(Business $business, array $attributes = []): Sale
    {
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $business->id])->id;

        return Sale::create(array_merge([
            'business_id' => $business->id,
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSaleItem(Sale $sale, array $attributes = [], ?Product $product = null): SaleItem
    {
        $product ??= Product::factory()->create(['business_id' => $sale->business_id]);

        return SaleItem::create(array_merge([
            'business_id' => $sale->business_id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => 'Cuci Kering',
            'product_sku' => 'CK-01',
            'unit_price' => 10000,
            'quantity' => 2.5,
            'line_total' => 25000,
            'cost_snapshot' => 0,
            'unit' => 'kg',
            'kind' => 'service',
            'pricing_unit' => 'kg',
            'line_cost' => 0,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function orderRow(TestResponse $response, string $transactionNumber): ?array
    {
        $orders = $response->viewData('orders');

        foreach ($orders->items() as $row) {
            if ($row['transaction_number'] === $transactionNumber) {
                return $row;
            }
        }

        return null;
    }
}
