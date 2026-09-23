<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\SyncCounter;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardOverviewPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1. Auth & Context
    // ============================================================

    public function test_guest_is_redirected_to_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_blocked_from_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_without_business_gets_200_and_no_tenant_queries(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        DB::enableQueryLog();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Verify no queries ran against any business domain models
        $tableList = ['sales', 'products', 'cash_ledgers', 'expenses', 'shifts', 'devices', 'sync_requests', 'sync_counters'];
        foreach ($queries as $q) {
            $sql = strtolower($q['query']);
            foreach ($tableList as $table) {
                $this->assertStringNotContainsString("from `{$table}`", $sql);
                $this->assertStringNotContainsString("from \"{$table}\"", $sql);
            }
        }

        $overview = $response->viewData('overview');
        $this->assertEquals(0, $overview['kpi']['today_sales']['raw']);
        $this->assertEquals(0, $overview['kpi']['today_transactions']['raw']);
        $this->assertEquals('0.00', $overview['kpi']['estimated_gross_profit']['raw']);
        $this->assertEquals(0, $overview['kpi']['stock_alerts']['raw']);
        $this->assertEmpty($overview['recent_transactions']);
        $this->assertEmpty($overview['stock_panel']['items']);
        $this->assertEquals(0, $overview['cash_summary']['cash_in_raw']);
        $this->assertEquals(0, $overview['cloud_panel']['server_sequence']);
    }

    // ============================================================
    // 2. Tenant Isolation
    // ============================================================

    public function test_current_business_dataset_is_correct_and_foreign_tenant_excluded(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        // Business A sale
        $this->createSale([
            'business_id' => $businessA->id,
            'total_amount' => 150000,
            'gross_profit' => 50000.00,
            'sold_at' => now(),
            'transaction_number' => 'TRX-TENANT-A',
        ]);

        // Business B sale
        $this->createSale([
            'business_id' => $businessB->id,
            'total_amount' => 999000,
            'gross_profit' => 300000.00,
            'sold_at' => now(),
            'transaction_number' => 'TRX-TENANT-B',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $overview = $response->viewData('overview');
        $this->assertEquals(150000, $overview['kpi']['today_sales']['raw']);
        $this->assertEquals(1, $overview['kpi']['today_transactions']['raw']);
        $this->assertEquals('50000.00', $overview['kpi']['estimated_gross_profit']['raw']);

        $response->assertSee('TRX-TENANT-A');
        $response->assertDontSee('TRX-TENANT-B');
    }

    public function test_switching_business_swaps_all_dashboard_metrics(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create(['name' => 'Bisnis Alpha']);
        $businessB = Business::factory()->create(['name' => 'Bisnis Beta']);
        Subscription::factory()->create(['business_id' => $businessA->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);

        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->createSale([
            'business_id' => $businessA->id,
            'total_amount' => 100000,
            'sold_at' => now(),
        ]);
        $this->createSale([
            'business_id' => $businessB->id,
            'total_amount' => 500000,
            'sold_at' => now(),
        ]);

        // Visit Business A
        $resA = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('dashboard'));
        $resA->assertOk();
        $this->assertEquals(100000, $resA->viewData('overview')['kpi']['today_sales']['raw']);
        $resA->assertSee('Bisnis Alpha');

        // Visit Business B
        $resB = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('dashboard'));
        $resB->assertOk();
        $this->assertEquals(500000, $resB->viewData('overview')['kpi']['today_sales']['raw']);
        $resB->assertSee('Bisnis Beta');
    }

    public function test_subscription_does_not_claim_network_connectivity(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        // Should say subscription claim like "Akses Cloud aktif", never "Online"
        $overview = $response->viewData('overview');
        $this->assertEquals('Akses Cloud aktif', $overview['cloud_panel']['cloud_access_label']);
        $response->assertDontSee('>Online<', false);
    }

    // ============================================================
    // 3. Sales KPI Accounting Scope & Today Filter
    // ============================================================

    public function test_kpi_includes_only_completed_and_paid_today(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $now = Carbon::now(config('app.timezone'));

        // 1. Valid: completed + paid today
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 200000,
            'gross_profit' => 80000.00,
            'sold_at' => $now->copy()->setTime(10, 0, 0),
        ]);

        // 2. Excluded: completed + unpaid today
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'total_amount' => 50000,
            'gross_profit' => 20000.00,
            'sold_at' => $now->copy()->setTime(11, 0, 0),
        ]);

        // 3. Excluded: cancelled + paid today
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'cancelled',
            'payment_status' => 'paid',
            'total_amount' => 70000,
            'gross_profit' => 30000.00,
            'sold_at' => $now->copy()->setTime(12, 0, 0),
        ]);

        // 4. Excluded: canceled (alternate spelling) + paid today
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'canceled',
            'payment_status' => 'paid',
            'total_amount' => 60000,
            'gross_profit' => 25000.00,
            'sold_at' => $now->copy()->setTime(13, 0, 0),
        ]);

        // 5. Excluded: unknown status + paid today
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'pending_review',
            'payment_status' => 'paid',
            'total_amount' => 40000,
            'gross_profit' => 15000.00,
            'sold_at' => $now->copy()->setTime(14, 0, 0),
        ]);

        // 6. Excluded: completed + paid yesterday
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 300000,
            'gross_profit' => 120000.00,
            'sold_at' => $now->copy()->subDay()->setTime(10, 0, 0),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $overview = $response->viewData('overview');
        $this->assertEquals(200000, $overview['kpi']['today_sales']['raw']);
        $this->assertEquals(1, $overview['kpi']['today_transactions']['raw']);
        $this->assertEquals('80000.00', $overview['kpi']['estimated_gross_profit']['raw']);
        $this->assertStringContainsString('Rp 200.000', $overview['kpi']['today_sales']['value']);
        $this->assertStringContainsString('1 Transaksi', $overview['kpi']['today_transactions']['value']);
        $this->assertStringContainsString('Rp 80.000', $overview['kpi']['estimated_gross_profit']['value']);
        $this->assertEquals('Rata-rata per transaksi: Rp 200.000', $overview['kpi']['today_transactions']['subtitle']);
    }

    public function test_kpi_zero_when_no_sales_today(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $overview = $response->viewData('overview');
        $this->assertEquals('Rp 0', $overview['kpi']['today_sales']['value']);
        $this->assertEquals('0 Transaksi', $overview['kpi']['today_transactions']['value']);
        $this->assertEquals('Rp 0', $overview['kpi']['estimated_gross_profit']['value']);
        $this->assertEquals('Rata-rata per transaksi: Rp 0', $overview['kpi']['today_transactions']['subtitle']);
    }

    // ============================================================
    // 4. Sales Chart Aggregation & Zero-Fill
    // ============================================================

    public function test_chart_7d_aggregates_and_zero_fills_intervals(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $now = Carbon::now(config('app.timezone'));

        // Sale 2 days ago
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 120000,
            'sold_at' => $now->copy()->subDays(2)->setTime(10, 0, 0),
            'payment_method' => 'qris',
        ]);

        // Sale today
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 80000,
            'sold_at' => $now->copy()->setTime(10, 0, 0),
            'payment_method' => 'qris',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => '7d']));
        $response->assertOk();

        $chart = $response->viewData('overview')['sales_chart'];
        $this->assertEquals('7d', $chart['period']);
        $this->assertCount(7, $chart['intervals']);
        $this->assertCount(7, $chart['labels']);
        $this->assertCount(7, $chart['data']);

        // Sum of all 7 days data must match 200000
        $this->assertEquals(200000, array_sum($chart['data']));

        // Peak omzet should be 2 days ago
        $this->assertStringContainsString('Rp 120.000', $chart['insight']['peak_sales']);

        // Average sales: 200000 / 7 = 28571
        $this->assertStringContainsString('Rp 28.571', $chart['insight']['average_sales']);
        $this->assertEquals('Rata-rata per Hari', $chart['insight']['average_label']);

        // Top payment method: QRIS (100%)
        $this->assertEquals('QRIS (100%)', $chart['insight']['top_payment_method']);
    }

    public function test_chart_30d_zero_fills_30_intervals(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => '30d']));
        $response->assertOk();

        $chart = $response->viewData('overview')['sales_chart'];
        $this->assertEquals('30d', $chart['period']);
        $this->assertCount(30, $chart['intervals']);
        $this->assertEquals('Rata-rata per Hari', $chart['insight']['average_label']);
    }

    public function test_chart_3m_aggregates_3_calendar_months(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $now = Carbon::now(config('app.timezone'));

        // Sale in current month
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 500000,
            'sold_at' => $now->copy()->setTime(10, 0, 0),
        ]);

        // Sale in 1 month prior
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 300000,
            'sold_at' => $now->copy()->subMonthNoOverflow()->setTime(10, 0, 0),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => '3m']));
        $response->assertOk();

        $chart = $response->viewData('overview')['sales_chart'];
        $this->assertEquals('3m', $chart['period']);
        $this->assertCount(3, $chart['intervals']);
        $this->assertEquals('Rata-rata per Bulan', $chart['insight']['average_label']);
        $this->assertEquals(800000, array_sum($chart['data']));
    }

    public function test_chart_12m_aggregates_12_calendar_months(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => '12m']));
        $response->assertOk();

        $chart = $response->viewData('overview')['sales_chart'];
        $this->assertEquals('12m', $chart['period']);
        $this->assertCount(12, $chart['intervals']);
        $this->assertEquals('Rata-rata per Bulan', $chart['insight']['average_label']);
    }

    public function test_invalid_period_falls_back_to_7d(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => 'invalid_period']));
        $response->assertOk();

        $chart = $response->viewData('overview')['sales_chart'];
        $this->assertEquals('7d', $chart['period']);
        $this->assertCount(7, $chart['intervals']);
    }

    public function test_array_period_query_falls_back_to_7d_without_error(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('dashboard').'?period[]=7d');
        $response->assertOk();

        $chart = $response->viewData('overview')['sales_chart'];
        $this->assertEquals('7d', $chart['period']);
        $this->assertCount(7, $chart['intervals']);
    }

    public function test_chart_insight_empty_state_when_no_sales(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => '7d']));
        $response->assertOk();

        $insight = $response->viewData('overview')['sales_chart']['insight'];
        $this->assertEquals('-', $insight['peak_sales']);
        $this->assertEquals('Rp 0', $insight['average_sales']);
        $this->assertEquals('-', $insight['top_payment_method']);
    }

    public function test_payment_method_percentage_is_based_on_count_not_total_amount(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $now = Carbon::now(config('app.timezone'));

        // 3 cash sales of 10,000 each (Total 30,000, Count 3)
        for ($i = 0; $i < 3; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'total_amount' => 10000,
                'sold_at' => $now,
            ]);
        }

        // 1 QRIS sale of 100,000 (Total 100,000, Count 1)
        $this->createSale([
            'business_id' => $business->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'qris',
            'total_amount' => 100000,
            'sold_at' => $now,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => '7d']));
        $response->assertOk();

        $insight = $response->viewData('overview')['sales_chart']['insight'];
        // Tunai is 3 out of 4 transactions (75%) even though QRIS had more Rupiah amount
        $this->assertEquals('Tunai (75%)', $insight['top_payment_method']);
    }

    // ============================================================
    // 5. Stock KPI & Alert Panel
    // ============================================================

    public function test_stock_kpi_and_alert_panel_logic(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        // 1. Negative stock (-2.5) -> critical
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Bahan Minus',
            'kind' => 'product',
            'status' => 'active',
            'stock' => -2.5,
            'min_stock' => 5,
        ]);

        // 2. Empty stock (0) -> critical
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Bahan Habis',
            'kind' => 'product',
            'status' => 'active',
            'stock' => 0,
            'min_stock' => 5,
        ]);

        // 3. Low stock (3 <= 5) -> warning
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Bahan Menipis',
            'kind' => 'product',
            'status' => 'active',
            'stock' => 3,
            'min_stock' => 5,
        ]);

        // 4. Safe stock (10 > 5) -> safe (excluded from alert)
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Bahan Aman',
            'kind' => 'product',
            'status' => 'active',
            'stock' => 10,
            'min_stock' => 5,
        ]);

        // 5. Service kind -> excluded from stock alerts
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Jasa Cuci',
            'kind' => 'service',
            'status' => 'active',
            'stock' => 0,
            'min_stock' => 10,
        ]);

        // 6. Deleted product -> excluded from stock alerts
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Bahan Dihapus',
            'kind' => 'product',
            'status' => 'deleted',
            'stock' => 0,
            'min_stock' => 10,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $overview = $response->viewData('overview');
        // Total alert KPI: negative + empty + low = 3
        $this->assertEquals(3, $overview['kpi']['stock_alerts']['raw']);
        $this->assertEquals('3 Item', $overview['kpi']['stock_alerts']['value']);

        // Stock panel items: sorted by severity (Minus, Habis, Menipis)
        $panelItems = $overview['stock_panel']['items'];
        $this->assertCount(3, $panelItems);
        $this->assertEquals('Bahan Minus', $panelItems[0]['name']);
        $this->assertEquals('Minus', $panelItems[0]['status']);
        $this->assertEquals('Bahan Habis', $panelItems[1]['name']);
        $this->assertEquals('Habis', $panelItems[1]['status']);
        $this->assertEquals('Bahan Menipis', $panelItems[2]['name']);
        $this->assertEquals('Menipis', $panelItems[2]['status']);

        $response->assertDontSee('Jasa Cuci');
        $response->assertDontSee('Bahan Dihapus');
    }

    public function test_stock_uses_product_stock_column_not_movement_sum(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Produk Kolom Otoritatif',
            'kind' => 'product',
            'status' => 'active',
            'stock' => 2, // Authoritative: 2 <= 5 (Low)
            'min_stock' => 5,
        ]);

        // Fake movements that sum to 100
        StockMovement::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'movement_type' => 'adjustment',
            'quantity_change' => 100,
            'stock_before' => 0,
            'stock_after' => 100,
            'occurred_at' => now(),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        // Stock alert count should still be 1 because product.stock = 2 <= 5
        $this->assertEquals(1, $response->viewData('overview')['kpi']['stock_alerts']['raw']);
    }

    // ============================================================
    // 6. Cash Summary
    // ============================================================

    public function test_cash_summary_metrics_for_today(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $now = Carbon::now(config('app.timezone'));

        // Cash in today: 500,000
        $this->createCashLedger([
            'business_id' => $business->id,
            'type' => 'in',
            'amount' => 500000,
            'occurred_at' => $now,
        ]);

        // Cash out today: 150,000
        $this->createCashLedger([
            'business_id' => $business->id,
            'type' => 'out',
            'amount' => 150000,
            'occurred_at' => $now,
        ]);

        // Expense recorded today: 80,000
        $this->createExpense([
            'business_id' => $business->id,
            'status' => 'recorded',
            'amount' => 80000,
            'occurred_at' => $now,
        ]);

        // Expense void today (excluded): 50,000
        $this->createExpense([
            'business_id' => $business->id,
            'status' => 'void',
            'amount' => 50000,
            'occurred_at' => $now,
        ]);

        // Cash in yesterday (excluded): 200,000
        $this->createCashLedger([
            'business_id' => $business->id,
            'type' => 'in',
            'amount' => 200000,
            'occurred_at' => $now->copy()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $cash = $response->viewData('overview')['cash_summary'];
        $this->assertEquals(500000, $cash['cash_in_raw']);
        $this->assertEquals(150000, $cash['cash_out_raw']);
        $this->assertEquals(350000, $cash['net_movement_raw']);
        $this->assertEquals(80000, $cash['recorded_expense_raw']);

        $response->assertSee('Rp 500.000');
        $response->assertSee('Rp 150.000');
        $response->assertSee('Rp 350.000');
        $response->assertSee('Rp 80.000');
        $response->assertDontSee('Total di Laci');
        $response->assertDontSee('Kas Awal');
    }

    // ============================================================
    // 7. Cloud & Sync Monitoring
    // ============================================================

    public function test_cloud_monitoring_uses_real_sequence_and_requests(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        SyncCounter::updateOrCreate([
            'business_id' => $business->id,
        ], [
            'current_sequence' => 42,
        ]);

        $deviceA = $this->createDevice(['business_id' => $business->id]);
        $deviceB = $this->createDevice(['business_id' => $business->id]);

        SyncRequest::create([
            'business_id' => $business->id,
            'device_id' => $deviceA->id,
            'request_id' => 'REQ-1',
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 1,
            'status' => 'processed',
            'processed_at' => '2026-09-23 11:30:00',
        ]);

        SyncRequest::create([
            'business_id' => $business->id,
            'device_id' => $deviceB->id,
            'request_id' => 'REQ-2',
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 2,
            'status' => 'processed',
            'processed_at' => '2026-09-23 12:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $cloud = $response->viewData('overview')['cloud_panel'];
        $this->assertEquals(42, $cloud['server_sequence']);
        $this->assertEquals(2, $cloud['total_push_requests']);
        $this->assertEquals(2, $cloud['devices_with_push']);
        $this->assertEquals(2, $cloud['total_registered_devices']);
        $this->assertStringContainsString('23 Sep 2026', $cloud['last_processed_at']);

        $response->assertDontSee('0 konflik');
        $response->assertDontSee('Semua data tersinkron');
        $response->assertSee('Lihat Riwayat Sinkronisasi');
        $response->assertSee('Perangkat Pernah Push');
        $response->assertDontSee('Perangkat Aktif Push');
    }

    // ============================================================
    // 8. Recent Transactions (Preview 8)
    // ============================================================

    public function test_recent_transactions_displays_maximum_8_and_sorted_desc(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 10; $i++) {
            $this->createSale([
                'business_id' => $business->id,
                'transaction_number' => sprintf('TRX-%03d', $i),
                'sold_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $recent = $response->viewData('overview')['recent_transactions'];
        $this->assertCount(8, $recent);
        $this->assertEquals('TRX-010', $recent[0]['transaction_number']);
        $this->assertEquals('TRX-009', $recent[1]['transaction_number']);

        // TRX-001 and TRX-002 should not be in preview of 8
        $response->assertDontSee('TRX-001');
        $response->assertDontSee('TRX-002');
    }

    public function test_recent_transactions_prioritizes_snapshots(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createSale([
            'business_id' => $business->id,
            'customer_snapshot' => ['name' => 'Pelanggan VIP Snapshot'],
            'business_snapshot' => ['outlet' => 'Outlet Gatot Subroto'],
            'transaction_number' => 'TRX-SNAPSHOT',
            'sold_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $response->assertSee('Pelanggan VIP Snapshot');
        $response->assertSee('Outlet Gatot Subroto');
    }

    public function test_recent_transactions_renders_unknown_payment_status_as_neutral_badge(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createSale([
            'business_id' => $business->id,
            'payment_status' => 'pending_review',
            'transaction_number' => 'TRX-UNKNOWN-PAY',
            'sold_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        // Should see human-readable "Pending Review"
        $response->assertSee('Pending Review');
        // Should have neutral slate class for unknown payment status, not amber
        $response->assertSee('bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300');
    }

    // ============================================================
    // 9. Fixture Cleanup & Real Database Verification
    // ============================================================

    public function test_all_seven_legacy_fixture_files_are_deleted(): void
    {
        $fixtureFiles = [
            resource_path('views/transactions/fixtures.php'),
            resource_path('views/products/fixtures.php'),
            resource_path('views/stock/fixtures.php'),
            resource_path('views/cash/fixtures.php'),
            resource_path('views/reports/fixtures.php'),
            resource_path('views/devices/fixtures.php'),
            resource_path('views/sync/fixtures.php'),
        ];

        foreach ($fixtureFiles as $filePath) {
            $this->assertFileDoesNotExist($filePath, "Fixture file [{$filePath}] should have been removed.");
        }
    }

    public function test_dashboard_view_contains_no_mock_dataset_or_hardcoded_arrays(): void
    {
        $dashboardViewContent = file_get_contents(resource_path('views/dashboard.blade.php'));
        $this->assertNotFalse($dashboardViewContent);

        $this->assertStringNotContainsString('transactionDataset', $dashboardViewContent);
        $this->assertStringNotContainsString('INV-2026-001', $dashboardViewContent);
        $this->assertStringNotContainsString('getChartData', $dashboardViewContent);
        $this->assertStringNotContainsString('getChartLabels', $dashboardViewContent);
        $this->assertStringNotContainsString('+14.2%', $dashboardViewContent);
        $this->assertStringNotContainsString('+8.5%', $dashboardViewContent);
        $this->assertStringNotContainsString('+11.8%', $dashboardViewContent);
        $this->assertStringNotContainsString('Kopi Nusantara Cafe', $dashboardViewContent);
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
        $business = Business::factory()->create(['name' => 'Bisnis Utama']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
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
            'name' => 'Device '.Str::random(6),
            'identifier' => 'device-'.Str::random(10),
            'platform' => 'Android',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => now(),
            'notes' => null,
        ], $attributes));
    }
}
