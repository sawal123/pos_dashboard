<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('shifts.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('shifts.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_without_business_gets_empty_shift_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('shifts.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();
        $response->assertSee('Shift');
        $response->assertSee('Belum Ada Shift');

        foreach ($queries as $query) {
            $this->assertStringNotContainsString('from "shifts"', $query['query']);
        }
    }

    public function test_current_business_shift_is_visible_and_foreign_shift_is_hidden(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();

        $this->createShift(['business_id' => $business->id, 'shift_number' => 'SHIFT-MINE-001']);
        $this->createShift(['business_id' => $foreign->id, 'shift_number' => 'SHIFT-FOREIGN-001']);

        $response = $this->actingAs($user)->get(route('shifts.index'));

        $response->assertOk();
        $response->assertSee('SHIFT-MINE-001');
        $response->assertDontSee('SHIFT-FOREIGN-001');
    }

    public function test_switching_business_changes_shift_dataset(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $businessA->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);
        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->createShift(['business_id' => $businessA->id, 'shift_number' => 'SHIFT-BIZ-A']);
        $this->createShift(['business_id' => $businessB->id, 'shift_number' => 'SHIFT-BIZ-B']);

        $responseA = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('shifts.index'));
        $responseA->assertSee('SHIFT-BIZ-A');
        $responseA->assertDontSee('SHIFT-BIZ-B');

        $responseB = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('shifts.index'));
        $responseB->assertSee('SHIFT-BIZ-B');
        $responseB->assertDontSee('SHIFT-BIZ-A');
    }

    public function test_summary_counts_total_open_and_closed_for_current_business_only(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();

        $this->createShift(['business_id' => $business->id, 'status' => 'open']);
        $this->createShift(['business_id' => $business->id, 'status' => 'closed']);
        $this->createShift(['business_id' => $business->id, 'status' => 'pending_review']);
        $this->createShift(['business_id' => $foreign->id, 'status' => 'open']);

        $response = $this->actingAs($user)->get(route('shifts.index'));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_shifts'] === 3
                && $summary['open_shifts'] === 1
                && $summary['closed_shifts'] === 1;
        });
    }

    public function test_filters_by_search_outlet_status_and_period(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet A']);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet B']);

        $this->createShift([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'shift_number' => 'SHIFT-TARGET-001',
            'status' => 'open',
            'opened_at' => '2026-05-15 08:00:00',
        ]);
        $this->createShift([
            'business_id' => $business->id,
            'outlet_id' => $outletB->id,
            'shift_number' => 'SHIFT-TARGET-002',
            'status' => 'closed',
            'opened_at' => '2026-05-15 08:00:00',
        ]);
        $this->createShift([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'shift_number' => 'SHIFT-OLD-001',
            'status' => 'open',
            'opened_at' => '2026-04-01 08:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('shifts.index', [
            'q' => 'TARGET',
            'outlet_id' => $outletA->id,
            'status' => 'open',
            'date' => 'custom',
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-20',
        ]));

        $response->assertOk();
        $response->assertSee('SHIFT-TARGET-001');
        $response->assertDontSee('SHIFT-TARGET-002');
        $response->assertDontSee('SHIFT-OLD-001');
    }

    public function test_pagination_is_twenty_five_items_and_preserves_query_string(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createShift([
                'business_id' => $business->id,
                'shift_number' => sprintf('SHIFT-PAGE-%02d', $i),
                'status' => 'open',
                'opened_at' => now()->subMinutes(40 - $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('shifts.index', ['status' => 'open', 'page' => 1]));

        $response->assertOk();
        $response->assertSee('status=open', false);
        $response->assertViewHas('shifts', function ($shifts): bool {
            return $shifts->count() === 25 && $shifts->total() === 30;
        });
    }

    public function test_shift_cash_aggregation_keeps_sales_cashledger_and_expense_separate(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $shift = $this->createShift([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'opening_cash' => 100000,
            'shift_number' => 'SHIFT-CASH-001',
        ]);

        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'total_amount' => 250000, 'status' => 'completed', 'payment_status' => 'paid']);
        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'total_amount' => 150000, 'status' => 'completed', 'payment_status' => 'unpaid']);
        $this->createCashLedger(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'type' => 'in', 'amount' => 250000, 'category' => 'sale']);
        $this->createCashLedger(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'type' => 'out', 'amount' => 40000]);
        $this->createExpense(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'amount' => 30000, 'status' => 'recorded']);

        $response = $this->actingAs($user)->get(route('shifts.index'));

        $response->assertOk();
        $response->assertViewHas('shifts', function ($shifts): bool {
            $first = $shifts->first();

            return $first['sales_count'] === 1
                && $first['sales_total'] === 250000
                && $first['cash_in'] === 250000
                && $first['cash_out'] === 40000
                && $first['expense_total'] === 30000
                && $first['estimated_cash'] === 310000;
        });
    }

    public function test_detail_endpoint_returns_shift_metrics_and_no_cashier_identity(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet Detail']);
        $shift = $this->createShift([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-DETAIL-001',
            'opening_cash' => 50000,
            'closing_cash' => 125000,
        ]);
        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'total_amount' => 80000]);
        $this->createCashLedger(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'type' => 'in', 'amount' => 80000]);
        $this->createCashLedger(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'type' => 'out', 'amount' => 5000]);
        $this->createExpense(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'shift_id' => $shift->id, 'amount' => 12000]);

        $response = $this->actingAs($user)->getJson(route('shifts.detail', $shift->id));

        $response->assertOk()
            ->assertJsonPath('shift_number', 'SHIFT-DETAIL-001')
            ->assertJsonPath('outlet_name', 'Outlet Detail')
            ->assertJsonPath('opening_cash', 50000)
            ->assertJsonPath('closing_cash', 125000)
            ->assertJsonPath('sales_count', 1)
            ->assertJsonPath('sales_total', 80000)
            ->assertJsonPath('cash_in', 80000)
            ->assertJsonPath('cash_out', 5000)
            ->assertJsonPath('expense_total', 12000)
            ->assertJsonPath('estimated_cash', 125000);

        $this->assertArrayNotHasKey('cashier', $response->json());
        $this->assertArrayNotHasKey('user_name', $response->json());
    }

    public function test_detail_endpoint_prevents_access_to_other_business_shift(): void
    {
        [$user] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();
        $foreignShift = $this->createShift(['business_id' => $foreign->id, 'shift_number' => 'SHIFT-FOREIGN-DETAIL']);

        $this->actingAs($user)->getJson(route('shifts.detail', $foreignShift->id))
            ->assertNotFound();
    }

    public function test_unknown_status_is_presented_neutrally_and_not_counted_as_closed(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createShift(['business_id' => $business->id, 'status' => 'pending_review', 'shift_number' => 'SHIFT-PENDING']);

        $response = $this->actingAs($user)->get(route('shifts.index'));

        $response->assertOk();
        $response->assertSee('Pending Review');
        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_shifts'] === 1
                && $summary['open_shifts'] === 0
                && $summary['closed_shifts'] === 0;
        });
    }

    public function test_empty_states_for_no_data_and_filtered_empty_results(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->actingAs($user)->get(route('shifts.index'))
            ->assertOk()
            ->assertSee('Belum Ada Shift');

        $this->createShift(['business_id' => $business->id, 'shift_number' => 'SHIFT-AVAILABLE']);

        $this->actingAs($user)->get(route('shifts.index', ['q' => 'NO-MATCH']))
            ->assertOk()
            ->assertSee('Shift Tidak Ditemukan');
    }

    public function test_invalid_query_parameters_do_not_cause_500_or_filter_leak(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createShift(['business_id' => $business->id, 'shift_number' => 'SHIFT-VALIDATION-001']);

        $response = $this->actingAs($user)->get(route('shifts.index', [
            'date' => 'not-valid',
            'start_date' => 'malformed',
            'outlet_id' => 'not-integer',
            'status' => str_repeat('x', 80),
            'page' => -2,
        ]));

        $response->assertOk();
        $response->assertSee('SHIFT-VALIDATION-001');
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
            'description' => 'Biaya Operasional Shift',
            'category' => 'Operasional',
            'amount' => 25000,
            'status' => 'recorded',
            'occurred_at' => now(),
            'notes' => null,
        ], $attributes));
    }
}
