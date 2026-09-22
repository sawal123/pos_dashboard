<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1-3. Auth & Context
    // ============================================================

    public function test_1_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('reports.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_2_unverified_users_cannot_access_reports_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_3_verified_user_without_business_gets_200_and_empty_report(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee('Laporan');
        $response->assertSee('Belum Ada Data Laporan');
    }

    // ============================================================
    // 4-9. Sales Accounting Scope & Tenant Isolation
    // ============================================================

    public function test_4_completed_and_paid_sale_current_tenant_enters_report(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 125000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_sales'] === 125000 && $summary['total_transactions'] === 1;
        });
    }

    public function test_5_completed_and_unpaid_sale_is_excluded(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'total_amount' => 125000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_sales'] === 0 && $summary['total_transactions'] === 0;
        });
    }

    public function test_6_cancelled_and_paid_sale_is_excluded(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'cancelled',
            'payment_status' => 'paid',
            'total_amount' => 125000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_sales'] === 0;
        });
    }

    public function test_7_canceled_alternative_spelling_and_paid_sale_is_excluded(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'canceled',
            'payment_status' => 'paid',
            'total_amount' => 125000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_sales'] === 0;
        });
    }

    public function test_8_unknown_sale_status_is_excluded(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'pending_review',
            'payment_status' => 'paid',
            'total_amount' => 80000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_sales'] === 0;
        });
    }

    public function test_9_foreign_sale_is_excluded_from_report(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        $this->createSale([
            'business_id' => $businessB->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 500000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_sales'] === 0;
        });
    }

    // ============================================================
    // 10-13. Expense Accounting Scope
    // ============================================================

    public function test_10_expense_recorded_enters_report(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'amount' => 60000,
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_expenses'] === 60000;
        });
    }

    public function test_11_expense_void_is_excluded_from_report(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'amount' => 60000,
            'status' => 'void',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_expenses'] === 0;
        });
    }

    public function test_12_expense_unknown_status_does_not_automatically_enter_accounting_report(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'amount' => 60000,
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_expenses'] === 0;
        });
    }

    public function test_13_foreign_expense_is_excluded_from_report(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        $this->createExpense([
            'business_id' => $businessB->id,
            'amount' => 90000,
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_expenses'] === 0;
        });
    }

    // ============================================================
    // 14. Switch Business
    // ============================================================

    public function test_14_switching_business_changes_report(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        Subscription::factory()->create(['business_id' => $businessA->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);

        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->createSale([
            'business_id' => $businessA->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100000,
        ]);
        $this->createSale([
            'business_id' => $businessB->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 250000,
        ]);

        $resA = $this->actingAs($user)->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('reports.index'));
        $this->assertEquals(100000, $resA->viewData('summary')['total_sales']);

        $resB = $this->actingAs($user)->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('reports.index'));
        $this->assertEquals(250000, $resB->viewData('summary')['total_sales']);
    }

    // ============================================================
    // 15-22. Summary Metrics & Integrity
    // ============================================================

    public function test_15_total_sales_is_calculated_accurately(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 100000]);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 150000]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $this->assertEquals(250000, $response->viewData('summary')['total_sales']);
    }

    public function test_16_total_transactions_is_calculated_accurately(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid']);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid']);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $this->assertEquals(3, $response->viewData('summary')['total_transactions']);
    }

    public function test_17_estimated_gross_profit_uses_sale_gross_profit(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'gross_profit' => '45000.50',
        ]);
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'gross_profit' => '25000.25',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $this->assertEquals('70000.75', $response->viewData('summary')['estimated_gross_profit']);
    }

    public function test_18_gross_profit_maintains_decimal_2(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'gross_profit' => '10000.00',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $this->assertEquals('10000.00', $response->viewData('summary')['estimated_gross_profit']);
    }

    public function test_19_gross_profit_is_not_recalculated_from_product_cogs(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        // Sale with gross_profit stored directly
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100000,
            'gross_profit' => '99999.00',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $this->assertEquals('99999.00', $response->viewData('summary')['estimated_gross_profit']);
    }

    public function test_20_total_expenses_is_calculated_accurately(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense(['business_id' => $business->id, 'amount' => 35000, 'status' => 'recorded']);
        $this->createExpense(['business_id' => $business->id, 'amount' => 45000, 'status' => 'recorded']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $this->assertEquals(80000, $response->viewData('summary')['total_expenses']);
    }

    public function test_21_cash_ledger_does_not_affect_total_expenses(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'out', 'amount' => 500000]);
        $this->createExpense(['business_id' => $business->id, 'amount' => 20000, 'status' => 'recorded']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $this->assertEquals(20000, $response->viewData('summary')['total_expenses']);
    }

    public function test_22_cash_ledger_does_not_affect_total_sales(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'in', 'amount' => 1000000]);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 50000]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $this->assertEquals(50000, $response->viewData('summary')['total_sales']);
    }

    // ============================================================
    // 23-28. Date Filters
    // ============================================================

    public function test_23_date_filter_today(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 10000, 'sold_at' => now()]);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 20000, 'sold_at' => now()->subDays(2)]);

        $response = $this->actingAs($user)->get(route('reports.index', ['date' => 'today']));
        $response->assertOk();
        $this->assertEquals(10000, $response->viewData('summary')['total_sales']);
    }

    public function test_24_date_filter_7d(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 10000, 'sold_at' => now()->subDays(3)]);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 20000, 'sold_at' => now()->subDays(10)]);

        $response = $this->actingAs($user)->get(route('reports.index', ['date' => '7d']));
        $response->assertOk();
        $this->assertEquals(10000, $response->viewData('summary')['total_sales']);
    }

    public function test_25_date_filter_30d(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 10000, 'sold_at' => now()->subDays(15)]);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 20000, 'sold_at' => now()->subDays(45)]);

        $response = $this->actingAs($user)->get(route('reports.index', ['date' => '30d']));
        $response->assertOk();
        $this->assertEquals(10000, $response->viewData('summary')['total_sales']);
    }

    public function test_26_date_filter_custom(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 10000, 'sold_at' => '2026-06-15 12:00:00']);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 20000, 'sold_at' => '2026-06-25 12:00:00']);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'date' => 'custom',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-20',
        ]));
        $response->assertOk();
        $this->assertEquals(10000, $response->viewData('summary')['total_sales']);
    }

    public function test_27_date_filter_custom_reversed_swaps_safely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 10000, 'sold_at' => '2026-06-15 12:00:00']);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'date' => 'custom',
            'start_date' => '2026-06-20',
            'end_date' => '2026-06-10',
        ]));
        $response->assertOk();
        $this->assertEquals(10000, $response->viewData('summary')['total_sales']);
    }

    public function test_28_invalid_date_input_is_handled_safely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('reports.index', [
            'date' => 'invalid-enum',
            'start_date' => 'not-a-date',
        ]));
        $response->assertOk();
    }

    // ============================================================
    // 29-30. Outlet Filter
    // ============================================================

    public function test_29_outlet_filter_uses_stable_numeric_id(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outletA = Outlet::factory()->create(['business_id' => $business->id]);
        $outletB = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 40000]);
        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outletB->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 60000]);

        $response = $this->actingAs($user)->get(route('reports.index', ['outlet_id' => $outletA->id]));
        $response->assertOk();
        $this->assertEquals(40000, $response->viewData('summary')['total_sales']);
    }

    public function test_30_foreign_outlet_id_does_not_leak_data(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $foreignOutlet = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->createSale(['business_id' => $businessB->id, 'outlet_id' => $foreignOutlet->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 80000]);

        $response = $this->actingAs($user)->get(route('reports.index', ['outlet_id' => $foreignOutlet->id]));
        $response->assertOk();
        $this->assertEquals(0, $response->viewData('summary')['total_sales']);
    }

    // ============================================================
    // 31-33. Sales Trend
    // ============================================================

    public function test_31_sales_trend_is_grouped_by_calendar_date(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 50000, 'sold_at' => '2026-07-01 10:00:00']);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 70000, 'sold_at' => '2026-07-01 14:00:00']);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 40000, 'sold_at' => '2026-07-02 09:00:00']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();

        $trend = $response->viewData('salesTrend');
        $this->assertCount(2, $trend);

        $day1 = collect($trend)->firstWhere('date_raw', '2026-07-01');
        $this->assertNotNull($day1);
        $this->assertEquals(120000, $day1['total_sales']);
        $this->assertEquals(2, $day1['transaction_count']);
    }

    public function test_32_sales_trend_is_tenant_scoped(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        $this->createSale(['business_id' => $businessA->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 30000, 'sold_at' => '2026-07-01 10:00:00']);
        $this->createSale(['business_id' => $businessB->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 90000, 'sold_at' => '2026-07-01 11:00:00']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $trend = $response->viewData('salesTrend');

        $day = collect($trend)->firstWhere('date_raw', '2026-07-01');
        $this->assertEquals(30000, $day['total_sales']);
    }

    public function test_33_sales_trend_excludes_unpaid_or_cancelled_sales(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'total_amount' => 25000, 'sold_at' => '2026-07-01 10:00:00']);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'unpaid', 'total_amount' => 50000, 'sold_at' => '2026-07-01 11:00:00']);
        $this->createSale(['business_id' => $business->id, 'status' => 'cancelled', 'payment_status' => 'paid', 'total_amount' => 50000, 'sold_at' => '2026-07-01 12:00:00']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $trend = $response->viewData('salesTrend');

        $day = collect($trend)->firstWhere('date_raw', '2026-07-01');
        $this->assertEquals(25000, $day['total_sales']);
    }

    // ============================================================
    // 34-37. Payment Breakdown
    // ============================================================

    public function test_34_payment_breakdown_is_grouped_correctly(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cash', 'total_amount' => 30000]);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'qris', 'total_amount' => 70000]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $breakdown = $response->viewData('paymentBreakdown');

        $this->assertCount(2, $breakdown);
    }

    public function test_35_payment_percentage_is_based_on_transaction_count(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        // 3 cash (total 30k), 1 qris (total 70k) -> cash = 75%, qris = 25%
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cash', 'total_amount' => 10000]);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cash', 'total_amount' => 10000]);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cash', 'total_amount' => 10000]);
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'qris', 'total_amount' => 70000]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $breakdown = $response->viewData('paymentBreakdown');

        $cash = collect($breakdown)->firstWhere('payment_method', 'Tunai');
        $qris = collect($breakdown)->firstWhere('payment_method', 'QRIS');

        $this->assertEquals(75, $cash['percentage']);
        $this->assertEquals(25, $qris['percentage']);
    }

    public function test_36_payment_raw_cash_maps_to_tunai_presentation(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cash']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $breakdown = $response->viewData('paymentBreakdown');

        $this->assertEquals('Tunai', $breakdown[0]['payment_method']);
    }

    public function test_37_null_payment_method_maps_to_tidak_diketahui(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => null]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $breakdown = $response->viewData('paymentBreakdown');

        $this->assertEquals('Tidak Diketahui', $breakdown[0]['payment_method']);
    }

    // ============================================================
    // 38-40. Expense Category Breakdown
    // ============================================================

    public function test_38_expense_breakdown_amount_is_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense(['business_id' => $business->id, 'category' => 'Operasional', 'amount' => 40000, 'status' => 'recorded']);
        $this->createExpense(['business_id' => $business->id, 'category' => 'Operasional', 'amount' => 60000, 'status' => 'recorded']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $breakdown = $response->viewData('expenseBreakdown');

        $op = collect($breakdown)->firstWhere('category', 'Operasional');
        $this->assertEquals(100000, $op['total_amount']);
    }

    public function test_39_expense_category_null_presents_tanpa_kategori(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense(['business_id' => $business->id, 'category' => null, 'amount' => 15000, 'status' => 'recorded']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $breakdown = $response->viewData('expenseBreakdown');

        $this->assertEquals('Tanpa Kategori', $breakdown[0]['category']);
    }

    public function test_40_expense_percentage_is_based_on_total_expenses_amount(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense(['business_id' => $business->id, 'category' => 'Bahan', 'amount' => 75000, 'status' => 'recorded']);
        $this->createExpense(['business_id' => $business->id, 'category' => 'Listrik', 'amount' => 25000, 'status' => 'recorded']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $breakdown = $response->viewData('expenseBreakdown');

        $bahan = collect($breakdown)->firstWhere('category', 'Bahan');
        $listrik = collect($breakdown)->firstWhere('category', 'Listrik');

        $this->assertEquals(75, $bahan['percentage']);
        $this->assertEquals(25, $listrik['percentage']);
    }

    // ============================================================
    // 41-45. Empty States & Fixture Removal
    // ============================================================

    public function test_41_report_has_any_data_false_renders_no_data_empty_state(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Data Laporan');
    }

    public function test_42_existing_data_with_zero_filter_results_renders_no_results_state(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid', 'sold_at' => now()->subDays(60)]);

        $response = $this->actingAs($user)->get(route('reports.index', ['date' => 'today']));
        $response->assertOk();
        $response->assertSee('Data Laporan Tidak Ditemukan');
    }

    public function test_43_reports_index_blade_does_not_require_fixtures_php(): void
    {
        $content = file_get_contents(resource_path('views/reports/index.blade.php'));
        $this->assertStringNotContainsString('fixtures.php', $content);
    }

    public function test_44_raw_fixture_trx_260921_001_does_not_appear_when_db_is_empty(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('TRX-260921-001');
    }

    public function test_45_raw_fixture_pembelian_es_batu_does_not_appear_when_db_is_empty(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('Pembelian Es Batu Kristal');
    }

    // ============================================================
    // 46-52. Accounting & Module Boundaries
    // ============================================================

    public function test_46_there_is_no_laba_bersih_label(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('Laba Bersih', false);
    }

    public function test_47_there_is_no_net_profit_label(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('Net Profit', false);
    }

    public function test_48_there_is_no_fake_growth_percentage(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSale(['business_id' => $business->id, 'status' => 'completed', 'payment_status' => 'paid']);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('Pertumbuhan Penjualan', false);
        $response->assertDontSee('Revenue Growth', false);
    }

    public function test_49_there_is_no_all_sales_json_in_browser(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('const allSales', false);
    }

    public function test_50_there_is_no_all_expenses_json_in_browser(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertDontSee('const allExpenses', false);
    }

    public function test_51_export_button_is_a_placeholder(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee('Ekspor Laporan');
        $response->assertSee('Ekspor laporan dari dashboard belum tersedia.');
    }

    public function test_52_report_queries_are_tenant_scoped(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('reports.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();
        foreach ($queries as $q) {
            $sql = strtolower($q['query']);
            if (str_contains($sql, 'sales') || str_contains($sql, 'expenses') || str_contains($sql, 'outlets')) {
                $this->assertStringContainsString('business_id', $sql);
            }
        }
    }

    public function test_53_expense_recorded_with_amount_zero_is_still_considered_data(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'category' => 'Operasional',
            'amount' => 0,
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));
        $response->assertOk();

        // 1. hasFilteredReportData is true
        $this->assertTrue($response->viewData('hasFilteredReportData'));

        // 2. Summary total_expenses remains Rp0
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_expenses'] === 0;
        });
        $response->assertSee('Rp 0');

        // 3. Expense breakdown still displays that record
        $breakdown = $response->viewData('expenseBreakdown');
        $this->assertNotEmpty($breakdown);
        $op = collect($breakdown)->firstWhere('category', 'Operasional');
        $this->assertNotNull($op);
        $this->assertEquals(1, $op['expense_count']);
        $this->assertEquals(0, $op['total_amount']);

        // 4. Does not show empty state
        $response->assertDontSee('Data Laporan Tidak Ditemukan');
    }

    public function test_54_filter_with_truly_no_records_displays_data_laporan_tidak_ditemukan(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'category' => 'Operasional',
            'amount' => 0,
            'status' => 'recorded',
            'occurred_at' => '2026-06-01 10:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'date' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]));
        $response->assertOk();

        $this->assertFalse($response->viewData('hasFilteredReportData'));
        $response->assertSee('Data Laporan Tidak Ditemukan');
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
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => $total,
            'change_amount' => 0,
            'gross_profit' => 30000.00,
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
            'description' => 'Biaya Operasional Sample',
            'category' => 'Operasional',
            'amount' => 25000,
            'status' => 'recorded',
            'occurred_at' => now(),
            'notes' => null,
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
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
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }
}
