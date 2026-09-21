<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1-3. Auth & Business Context
    // ============================================================

    public function test_1_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('cash.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_2_unverified_users_cannot_access_cash_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_3_verified_user_without_business_gets_200_and_empty_state(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('Kas & Pengeluaran');
        $response->assertSee('Belum Ada Pergerakan Kas');
    }

    // ============================================================
    // 4-8. Tenant Scoping & Switch Business
    // ============================================================

    public function test_4_current_business_cash_ledger_is_visible(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $ledger = $this->createCashLedger([
            'business_id' => $business->id,
            'reference_id' => 'REF-TENANT-A-001',
            'amount' => 150000,
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('REF-TENANT-A-001');
    }

    public function test_5_foreign_tenant_ledger_is_not_visible(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        $this->createCashLedger([
            'business_id' => $businessA->id,
            'reference_id' => 'REF-MINE-001',
        ]);
        $this->createCashLedger([
            'business_id' => $businessB->id,
            'reference_id' => 'REF-FOREIGN-002',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('REF-MINE-001');
        $response->assertDontSee('REF-FOREIGN-002');
    }

    public function test_6_current_business_expense_is_visible(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'description' => 'Beli Kertas Struk Thermal',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses']));
        $response->assertOk();
        $response->assertSee('Beli Kertas Struk Thermal');
    }

    public function test_7_foreign_expense_is_not_visible(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        $this->createExpense([
            'business_id' => $businessA->id,
            'description' => 'Pengeluaran Tenant A',
        ]);
        $this->createExpense([
            'business_id' => $businessB->id,
            'description' => 'Pengeluaran Tenant B',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses']));
        $response->assertOk();
        $response->assertSee('Pengeluaran Tenant A');
        $response->assertDontSee('Pengeluaran Tenant B');
    }

    public function test_8_switching_business_changes_dataset(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        Subscription::factory()->create(['business_id' => $businessA->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);

        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->createCashLedger(['business_id' => $businessA->id, 'reference_id' => 'REF-BIZ-A']);
        $this->createCashLedger(['business_id' => $businessB->id, 'reference_id' => 'REF-BIZ-B']);

        // Active business A
        $resA = $this->actingAs($user)->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('cash.index'));
        $resA->assertSee('REF-BIZ-A');
        $resA->assertDontSee('REF-BIZ-B');

        // Switch to business B
        $resB = $this->actingAs($user)->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('cash.index'));
        $resB->assertSee('REF-BIZ-B');
        $resB->assertDontSee('REF-BIZ-A');
    }

    // ============================================================
    // 9-15. Ledger Presentation & Nullable Safety
    // ============================================================

    public function test_9_ledger_amount_remains_positive_in_presentation(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger([
            'business_id' => $business->id,
            'type' => 'out',
            'amount' => 75000,
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertViewHas('ledgers', function ($ledgers) {
            $first = $ledgers->first();

            return $first['amount'] === 75000;
        });
    }

    public function test_10_type_in_displays_plus_and_kas_masuk(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger([
            'business_id' => $business->id,
            'type' => 'in',
            'amount' => 125000,
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('Kas Masuk');
        $response->assertSee('+ Rp 125.000');
    }

    public function test_11_type_out_displays_minus_and_kas_keluar(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger([
            'business_id' => $business->id,
            'type' => 'out',
            'amount' => 80000,
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('Kas Keluar');
        $response->assertSee('- Rp 80.000');
    }

    public function test_12_unknown_ledger_type_does_not_default_to_kas_keluar(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger([
            'business_id' => $business->id,
            'type' => 'adjustment',
            'amount' => 50000,
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertViewHas('ledgers', function ($ledgers) {
            $first = $ledgers->first();

            return $first['type'] === 'Adjustment' && $first['type'] !== 'Kas Keluar';
        });
        $response->assertSee('Adjustment');
    }

    public function test_13_nullable_reference_id_renders_safely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger([
            'business_id' => $business->id,
            'reference_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertViewHas('ledgers', function ($ledgers) {
            return $ledgers->first()['reference_id'] === null;
        });
    }

    public function test_14_nullable_note_renders_safely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger([
            'business_id' => $business->id,
            'note' => null,
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertViewHas('ledgers', function ($ledgers) {
            return $ledgers->first()['note'] === null;
        });
    }

    public function test_15_nullable_shift_renders_tanpa_shift(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger([
            'business_id' => $business->id,
            'shift_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('Tanpa Shift');
    }

    // ============================================================
    // 16-18. Expense Presentation & Void Status
    // ============================================================

    public function test_16_expense_recorded_presents_tercatat(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'status' => 'recorded',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses']));
        $response->assertOk();
        $response->assertSee('Tercatat');
    }

    public function test_17_expense_unknown_non_void_status_is_neutral(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses']));
        $response->assertOk();
        $response->assertViewHas('expenses', function ($expenses) {
            $first = $expenses->first();

            return $first['status'] === 'Pending Review' && $first['status'] !== 'Tercatat';
        });
        $response->assertSee('Pending Review');
    }

    public function test_18_expense_void_is_excluded_from_expenses_tab(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'description' => 'Biaya Aktif',
            'status' => 'recorded',
        ]);
        $this->createExpense([
            'business_id' => $business->id,
            'description' => 'Biaya Batal Void',
            'status' => 'void',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses']));
        $response->assertOk();
        $response->assertSee('Biaya Aktif');
        $response->assertDontSee('Biaya Batal Void');
    }

    // ============================================================
    // 19-23. Summary Calculation & Non-conflation
    // ============================================================

    public function test_19_summary_cash_in_is_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'in', 'amount' => 100000]);
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'in', 'amount' => 50000]);
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'out', 'amount' => 30000]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['cash_in'] === 150000;
        });
    }

    public function test_20_summary_cash_out_is_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'out', 'amount' => 40000]);
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'out', 'amount' => 60000]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['cash_out'] === 100000;
        });
    }

    public function test_21_summary_net_cash_flow_is_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'in', 'amount' => 200000]);
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'out', 'amount' => 75000]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['net_cash_flow'] === 125000;
        });
    }

    public function test_22_total_expense_only_includes_recorded_status(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense(['business_id' => $business->id, 'amount' => 50000, 'status' => 'recorded']);
        $this->createExpense(['business_id' => $business->id, 'amount' => 20000, 'status' => 'pending_review']);
        $this->createExpense(['business_id' => $business->id, 'amount' => 30000, 'status' => 'void']);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_expense'] === 50000;
        });
    }

    public function test_23_cash_ledger_out_is_not_added_to_total_expense(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'out', 'amount' => 100000]);
        $this->createExpense(['business_id' => $business->id, 'amount' => 45000, 'status' => 'recorded']);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['total_expense'] === 45000 && $summary['cash_out'] === 100000;
        });
    }

    // ============================================================
    // 24-29. Date Filtering Semantics
    // ============================================================

    public function test_24_filter_date_today(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-TODAY', 'occurred_at' => now()]);
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-OLD', 'occurred_at' => now()->subDays(3)]);

        $response = $this->actingAs($user)->get(route('cash.index', ['date' => 'today']));
        $response->assertOk();
        $response->assertSee('REF-TODAY');
        $response->assertDontSee('REF-OLD');
    }

    public function test_25_filter_date_7d(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-IN-7D', 'occurred_at' => now()->subDays(2)]);
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-OUT-7D', 'occurred_at' => now()->subDays(10)]);

        $response = $this->actingAs($user)->get(route('cash.index', ['date' => '7d']));
        $response->assertOk();
        $response->assertSee('REF-IN-7D');
        $response->assertDontSee('REF-OUT-7D');
    }

    public function test_26_filter_date_30d(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-IN-30D', 'occurred_at' => now()->subDays(15)]);
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-OUT-30D', 'occurred_at' => now()->subDays(40)]);

        $response = $this->actingAs($user)->get(route('cash.index', ['date' => '30d']));
        $response->assertOk();
        $response->assertSee('REF-IN-30D');
        $response->assertDontSee('REF-OUT-30D');
    }

    public function test_27_filter_date_custom(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-CUSTOM-MATCH', 'occurred_at' => '2026-05-15 10:00:00']);
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-CUSTOM-OUT', 'occurred_at' => '2026-05-25 10:00:00']);

        $response = $this->actingAs($user)->get(route('cash.index', [
            'date' => 'custom',
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-20',
        ]));
        $response->assertOk();
        $response->assertSee('REF-CUSTOM-MATCH');
        $response->assertDontSee('REF-CUSTOM-OUT');
    }

    public function test_28_reversed_custom_dates_swap_safely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-SWAP-MATCH', 'occurred_at' => '2026-05-15 10:00:00']);

        $response = $this->actingAs($user)->get(route('cash.index', [
            'date' => 'custom',
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-10',
        ]));
        $response->assertOk();
        $response->assertSee('REF-SWAP-MATCH');
    }

    public function test_29_invalid_date_does_not_cause_500(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('cash.index', [
            'date' => 'not-a-valid-date-enum',
            'start_date' => 'malformed',
        ]));
        $response->assertOk();
    }

    // ============================================================
    // 30-31. Outlet Filtering
    // ============================================================

    public function test_30_outlet_filter_uses_numeric_outlet_id(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet Cabang 1']);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet Cabang 2']);

        $this->createCashLedger(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'reference_id' => 'REF-OUTLET-A']);
        $this->createCashLedger(['business_id' => $business->id, 'outlet_id' => $outletB->id, 'reference_id' => 'REF-OUTLET-B']);

        $response = $this->actingAs($user)->get(route('cash.index', ['outlet_id' => $outletA->id]));
        $response->assertOk();
        $response->assertSee('REF-OUTLET-A');
        $response->assertDontSee('REF-OUTLET-B');
    }

    public function test_31_foreign_outlet_id_does_not_leak(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $foreignOutlet = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->createCashLedger(['business_id' => $businessB->id, 'outlet_id' => $foreignOutlet->id, 'reference_id' => 'REF-LEAK']);

        $response = $this->actingAs($user)->get(route('cash.index', ['outlet_id' => $foreignOutlet->id]));
        $response->assertOk();
        $response->assertDontSee('REF-LEAK');
    }

    // ============================================================
    // 32-35. Search & Tab Specific Filters
    // ============================================================

    public function test_32_q_search_filters_ledger_server_side(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-SPECIAL-KEY', 'note' => 'Catatan biasa']);
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-OTHER', 'note' => 'Tanpa kecocokan']);

        $response = $this->actingAs($user)->get(route('cash.index', ['q' => 'SPECIAL']));
        $response->assertOk();
        $response->assertSee('REF-SPECIAL-KEY');
        $response->assertDontSee('REF-OTHER');
    }

    public function test_33_q_search_filters_expense_server_side(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense(['business_id' => $business->id, 'description' => 'Servis AC Kantor']);
        $this->createExpense(['business_id' => $business->id, 'description' => 'Beli Alat Tulis']);

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses', 'q' => 'Servis AC']));
        $response->assertOk();
        $response->assertSee('Servis AC Kantor');
        $response->assertDontSee('Beli Alat Tulis');
    }

    public function test_34_type_filter_applies_to_ledgers(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'in', 'reference_id' => 'REF-IN-ONLY']);
        $this->createCashLedger(['business_id' => $business->id, 'type' => 'out', 'reference_id' => 'REF-OUT-ONLY']);

        $response = $this->actingAs($user)->get(route('cash.index', ['type' => 'in']));
        $response->assertOk();
        $response->assertSee('REF-IN-ONLY');
        $response->assertDontSee('REF-OUT-ONLY');
    }

    public function test_35_category_filter_applies_to_expenses_raw_exact(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense(['business_id' => $business->id, 'category' => 'Logistik', 'description' => 'Bensin']);
        $this->createExpense(['business_id' => $business->id, 'category' => 'Dapur', 'description' => 'Gula Pasir']);

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses', 'category' => 'Logistik']));
        $response->assertOk();
        $response->assertSee('Bensin');
        $response->assertDontSee('Gula Pasir');
    }

    // ============================================================
    // 36-39. Pagination & Query String & Summary Invariance
    // ============================================================

    public function test_36_pagination_ledger_is_25_per_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        for ($i = 1; $i <= 30; $i++) {
            $this->createCashLedger(['business_id' => $business->id, 'reference_id' => sprintf('REF-PAGE-%02d', $i)]);
        }

        $response = $this->actingAs($user)->get(route('cash.index', ['page' => 1]));
        $response->assertOk();
        $response->assertViewHas('ledgers', function ($ledgers) {
            return $ledgers->count() === 25 && $ledgers->total() === 30;
        });
    }

    public function test_37_pagination_expense_is_25_per_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        for ($i = 1; $i <= 30; $i++) {
            $this->createExpense(['business_id' => $business->id, 'description' => sprintf('Exp item %02d', $i)]);
        }

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses', 'page' => 1]));
        $response->assertOk();
        $response->assertViewHas('expenses', function ($expenses) {
            return $expenses->count() === 25 && $expenses->total() === 30;
        });
    }

    public function test_38_query_string_preserved_in_pagination(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        for ($i = 1; $i <= 30; $i++) {
            $this->createCashLedger(['business_id' => $business->id]);
        }

        $response = $this->actingAs($user)->get(route('cash.index', ['type' => 'in']));
        $response->assertOk();
        $response->assertSee('type=in');
    }

    public function test_39_summary_is_identical_on_page_1_and_page_2(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        for ($i = 1; $i <= 30; $i++) {
            $this->createCashLedger(['business_id' => $business->id, 'type' => 'in', 'amount' => 10000]);
        }

        $resPage1 = $this->actingAs($user)->get(route('cash.index', ['page' => 1]));
        $resPage2 = $this->actingAs($user)->get(route('cash.index', ['page' => 2]));

        $summary1 = $resPage1->viewData('summary');
        $summary2 = $resPage2->viewData('summary');

        $this->assertEquals($summary1['cash_in'], $summary2['cash_in']);
        $this->assertEquals(300000, $summary1['cash_in']);
    }

    // ============================================================
    // 40-43. Empty States
    // ============================================================

    public function test_40_ledger_filtered_empty_state_shown(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger(['business_id' => $business->id, 'reference_id' => 'REF-EXISTS']);

        $response = $this->actingAs($user)->get(route('cash.index', ['q' => 'NONEXISTENT']));
        $response->assertOk();
        $response->assertSee('Pergerakan Kas Tidak Ditemukan');
    }

    public function test_41_expense_filtered_empty_state_shown(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createExpense(['business_id' => $business->id, 'description' => 'Biaya Ada']);

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses', 'q' => 'NONEXISTENT']));
        $response->assertOk();
        $response->assertSee('Pengeluaran Tidak Ditemukan');
    }

    public function test_42_truly_empty_ledger_state_shown(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Pergerakan Kas');
    }

    public function test_43_truly_empty_expense_state_shown(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses']));
        $response->assertOk();
        $response->assertSee('Belum Ada Pengeluaran');
    }

    // ============================================================
    // 44-50. Fixture Removal & Read-Only Constraints
    // ============================================================

    public function test_44_fixture_trx_260921_001_does_not_appear_when_db_is_empty(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertDontSee('TRX-260921-001');
    }

    public function test_45_fixture_pembelian_es_batu_does_not_appear_when_db_is_empty(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('cash.index', ['tab' => 'expenses']));
        $response->assertOk();
        $response->assertDontSee('Pembelian Es Batu Kristal');
    }

    public function test_46_cash_index_blade_does_not_require_fixtures_php(): void
    {
        $content = file_get_contents(resource_path('views/cash/index.blade.php'));
        $this->assertStringNotContainsString('fixtures.php', $content);
    }

    public function test_47_response_does_not_expose_sale_sync_id(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createCashLedger([
            'business_id' => $business->id,
            'sale_sync_id' => 'SECRET-SALE-SYNC-123',
        ]);

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertDontSee('SECRET-SALE-SYNC-123');
        $response->assertDontSee('ID Sinkronisasi Penjualan');
    }

    public function test_48_no_business_does_not_query_cash_or_expense(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('cash.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('cash_ledger', $query['query']);
            $this->assertStringNotContainsString('expenses', $query['query']);
        }
    }

    public function test_49_record_cash_is_readonly_placeholder(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('Catat Kas');
        $response->assertSee('Catat kas dari dashboard belum tersedia.');
    }

    public function test_50_add_expense_is_readonly_placeholder(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('cash.index'));
        $response->assertOk();
        $response->assertSee('Tambah Pengeluaran');
        $response->assertSee('Tambah pengeluaran dari dashboard belum tersedia.');
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
}
