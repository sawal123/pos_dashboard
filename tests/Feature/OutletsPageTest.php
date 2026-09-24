<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OutletsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('outlets.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('outlets.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_without_business_gets_empty_outlet_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('outlets.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();
        $response->assertSee('Outlet');
        $response->assertSee('Belum Ada Outlet');

        foreach ($queries as $query) {
            $this->assertStringNotContainsString('from "outlets"', $query['query']);
        }
    }

    public function test_current_business_outlet_is_visible_and_foreign_outlet_is_hidden(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();

        $this->createOutlet(['business_id' => $business->id, 'name' => 'Outlet Milik Saya', 'code' => 'OUT-MINE-001']);
        $this->createOutlet(['business_id' => $foreign->id, 'name' => 'Outlet Asing', 'code' => 'OUT-FOREIGN-001']);

        $response = $this->actingAs($user)->get(route('outlets.index'));

        $response->assertOk();
        $response->assertSee('Outlet Milik Saya');
        $response->assertSee('OUT-MINE-001');
        $response->assertDontSee('Outlet Asing');
        $response->assertDontSee('OUT-FOREIGN-001');
    }

    public function test_outlet_detail_of_other_business_returns_404(): void
    {
        [$user] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();
        $foreignOutlet = $this->createOutlet(['business_id' => $foreign->id, 'code' => 'OUT-FOREIGN-DETAIL']);

        $this->actingAs($user)->getJson(route('outlets.detail', $foreignOutlet->id))
            ->assertNotFound();
    }

    public function test_switching_business_changes_outlet_dataset(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $businessA->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);
        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->createOutlet(['business_id' => $businessA->id, 'name' => 'Outlet Biz A', 'code' => 'OUT-BIZ-A']);
        $this->createOutlet(['business_id' => $businessB->id, 'name' => 'Outlet Biz B', 'code' => 'OUT-BIZ-B']);

        $responseA = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('outlets.index'));
        $responseA->assertSee('Outlet Biz A');
        $responseA->assertDontSee('Outlet Biz B');

        $responseB = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('outlets.index'));
        $responseB->assertSee('Outlet Biz B');
        $responseB->assertDontSee('Outlet Biz A');
    }

    public function test_summary_counts_total_active_and_inactive_without_treating_other_status_as_active(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();

        $this->createOutlet(['business_id' => $business->id, 'status' => 'active']);
        $this->createOutlet(['business_id' => $business->id, 'status' => 'inactive']);
        $this->createOutlet(['business_id' => $business->id, 'status' => 'maintenance']);
        $this->createOutlet(['business_id' => $foreign->id, 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('outlets.index'));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_outlets'] === 3
                && $summary['active_outlets'] === 1
                && $summary['inactive_outlets'] === 1;
        });
        $response->assertSee('Maintenance');
    }

    public function test_summary_counts_distinct_outlets_with_open_shift(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outletA = $this->createOutlet(['business_id' => $business->id]);
        $outletB = $this->createOutlet(['business_id' => $business->id]);

        $this->createShift(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'status' => 'open']);
        $this->createShift(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'status' => 'closed']);
        $this->createShift(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'status' => 'open']);
        $this->createShift(['business_id' => $business->id, 'outlet_id' => $outletB->id, 'status' => 'closed']);

        $response = $this->actingAs($user)->get(route('outlets.index'));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['outlets_with_open_shift'] === 1;
        });
    }

    public function test_filters_by_search_name_or_code_and_status(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createOutlet(['business_id' => $business->id, 'name' => 'Outlet Target', 'code' => 'OUT-TARGET-01', 'status' => 'active']);
        $this->createOutlet(['business_id' => $business->id, 'name' => 'Outlet Lain', 'code' => 'OUT-OTHER-99', 'status' => 'active']);
        $this->createOutlet(['business_id' => $business->id, 'name' => 'Outlet Target Nonaktif', 'code' => 'OUT-TARGET-02', 'status' => 'inactive']);

        // Search by code fragment.
        $byCode = $this->actingAs($user)->get(route('outlets.index', ['q' => 'TARGET-01']));
        $byCode->assertOk();
        $byCode->assertSee('OUT-TARGET-01');
        $byCode->assertDontSee('OUT-OTHER-99');

        // Search by name plus status filter.
        $filtered = $this->actingAs($user)->get(route('outlets.index', ['q' => 'Target', 'status' => 'active']));
        $filtered->assertOk();
        $filtered->assertSee('OUT-TARGET-01');
        $filtered->assertDontSee('OUT-TARGET-02');
    }

    public function test_pagination_is_twenty_five_items_and_preserves_query_string(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createOutlet([
                'business_id' => $business->id,
                'name' => sprintf('Outlet Page %02d', $i),
                'code' => sprintf('OUT-PAGE-%02d', $i),
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($user)->get(route('outlets.index', ['status' => 'active', 'page' => 1]));

        $response->assertOk();
        $response->assertSee('status=active', false);
        $response->assertViewHas('outlets', function ($outlets): bool {
            return $outlets->count() === 25 && $outlets->total() === 30;
        });
    }

    public function test_revenue_counts_only_completed_and_paid_sales(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = $this->createOutlet(['business_id' => $business->id]);

        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'total_amount' => 100000, 'status' => 'completed', 'payment_status' => 'paid']);

        $response = $this->actingAs($user)->get(route('outlets.index'));

        $response->assertOk();
        $response->assertViewHas('outlets', function ($outlets): bool {
            $first = $outlets->first();

            return $first['sales_count'] === 1 && $first['sales_total'] === 100000;
        });
    }

    public function test_cancelled_and_unpaid_transactions_do_not_add_revenue(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = $this->createOutlet(['business_id' => $business->id]);

        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'total_amount' => 100000, 'status' => 'completed', 'payment_status' => 'paid']);
        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'total_amount' => 50000, 'status' => 'completed', 'payment_status' => 'unpaid']);
        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'total_amount' => 40000, 'status' => 'cancelled', 'payment_status' => 'paid']);
        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'total_amount' => 30000, 'status' => 'cancelled', 'payment_status' => 'unpaid']);

        $response = $this->actingAs($user)->get(route('outlets.index'));

        $response->assertOk();
        $response->assertViewHas('outlets', function ($outlets): bool {
            $first = $outlets->first();

            return $first['sales_count'] === 1 && $first['sales_total'] === 100000;
        });
    }

    public function test_device_and_open_shift_aggregation_is_per_outlet(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outletA = $this->createOutlet(['business_id' => $business->id, 'name' => 'Outlet A', 'code' => 'OUT-AGG-A']);
        $outletB = $this->createOutlet(['business_id' => $business->id, 'name' => 'Outlet B', 'code' => 'OUT-AGG-B']);

        $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outletA->id]);
        $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outletA->id]);
        $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outletB->id]);

        $this->createShift(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'status' => 'open']);
        $this->createShift(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'status' => 'closed']);
        $this->createShift(['business_id' => $business->id, 'outlet_id' => $outletB->id, 'status' => 'closed']);

        $response = $this->actingAs($user)->get(route('outlets.index'));

        $response->assertOk();
        $response->assertViewHas('outlets', function ($outlets) use ($outletA, $outletB): bool {
            $rows = collect($outlets->items())->keyBy('id');

            return $rows[$outletA->id]['device_count'] === 2
                && $rows[$outletA->id]['open_shift_count'] === 1
                && $rows[$outletB->id]['device_count'] === 1
                && $rows[$outletB->id]['open_shift_count'] === 0;
        });
    }

    public function test_detail_endpoint_keeps_cashledger_and_expense_separate_from_revenue(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = $this->createOutlet(['business_id' => $business->id, 'name' => 'Outlet Detail', 'code' => 'OUT-DETAIL-01', 'address' => 'Jl. Merdeka 10']);
        $shift = $this->createShift(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'status' => 'open']);

        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'total_amount' => 80000, 'status' => 'completed', 'payment_status' => 'paid']);
        $this->createCashLedger(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'type' => 'in', 'amount' => 100000]);
        $this->createCashLedger(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'type' => 'out', 'amount' => 30000]);
        $this->createExpense(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'amount' => 20000, 'status' => 'recorded']);

        $response = $this->actingAs($user)->getJson(route('outlets.detail', $outlet->id));

        $response->assertOk()
            ->assertJsonPath('name', 'Outlet Detail')
            ->assertJsonPath('code', 'OUT-DETAIL-01')
            ->assertJsonPath('address', 'Jl. Merdeka 10')
            ->assertJsonPath('status_raw', 'active')
            ->assertJsonPath('sales_count', 1)
            ->assertJsonPath('sales_total', 80000)
            ->assertJsonPath('cash_in', 100000)
            ->assertJsonPath('cash_out', 30000)
            ->assertJsonPath('expense_total', 20000)
            ->assertJsonPath('total_shifts', 1)
            ->assertJsonPath('open_shifts', 1);

        // Revenue, cash mutations and expenses are never collapsed into one figure.
        $this->assertSame(80000, $response->json('sales_total'));
        $this->assertSame(100000, $response->json('cash_in'));
        $this->assertSame(30000, $response->json('cash_out'));
        $this->assertSame(20000, $response->json('expense_total'));
    }

    public function test_detail_endpoint_without_any_transactions_is_safe(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = $this->createOutlet(['business_id' => $business->id, 'code' => 'OUT-EMPTY-01']);

        $response = $this->actingAs($user)->getJson(route('outlets.detail', $outlet->id));

        $response->assertOk()
            ->assertJsonPath('sales_count', 0)
            ->assertJsonPath('sales_total', 0)
            ->assertJsonPath('cash_in', 0)
            ->assertJsonPath('cash_out', 0)
            ->assertJsonPath('expense_total', 0)
            ->assertJsonPath('device_count', 0)
            ->assertJsonPath('last_transaction_at', null);
    }

    public function test_invalid_query_parameters_do_not_cause_500_or_filter_leak(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createOutlet(['business_id' => $business->id, 'code' => 'OUT-VALIDATION-01']);

        $response = $this->actingAs($user)->get(route('outlets.index', [
            'q' => ['array-instead-of-string'],
            'status' => str_repeat('x', 80),
            'page' => -2,
        ]));

        $response->assertOk();
        $response->assertSee('OUT-VALIDATION-01');
    }

    public function test_outlet_menu_is_wired_to_the_outlets_route(): void
    {
        [$user] = $this->makeUserWithBusiness();

        $this->actingAs($user)->get(route('outlets.index'))
            ->assertOk()
            ->assertSee(route('outlets.index'), false);
    }

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
    private function createOutlet(array $attributes = []): Outlet
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;

        return Outlet::create(array_merge([
            'business_id' => $businessId,
            'name' => 'Outlet '.strtoupper(uniqid()),
            'code' => 'OUT-'.strtoupper(uniqid()),
            'status' => 'active',
            'address' => null,
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
            'subtotal' => 50000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => 50000,
            'change_amount' => 0,
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
    private function createShift(array $attributes = []): Shift
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        return Shift::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'shift_number' => 'SHIFT-'.strtoupper(uniqid()),
            'status' => 'open',
            'opening_cash' => 0,
            'closing_cash' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => null,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDevice(array $attributes = []): Device
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        return Device::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'name' => 'POS '.strtoupper(uniqid()),
            'identifier' => 'POS-'.strtoupper(uniqid()),
            'platform' => 'android',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => null,
            'notes' => null,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCashLedger(array $attributes = []): CashLedger
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        return CashLedger::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'shift_id' => null,
            'type' => 'in',
            'amount' => 50000,
            'category' => 'sale',
            'note' => null,
            'reference_id' => 'REF-'.strtoupper(uniqid()),
            'sale_sync_id' => null,
            'occurred_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createExpense(array $attributes = []): Expense
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        return Expense::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'shift_id' => null,
            'description' => 'Biaya Operasional Outlet',
            'category' => 'Operasional',
            'amount' => 25000,
            'status' => 'recorded',
            'occurred_at' => now(),
            'notes' => null,
        ], $attributes));
    }
}
