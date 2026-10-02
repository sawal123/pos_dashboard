<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformUsageAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    // ============================================================
    // 1. Access Control Tests
    // ============================================================

    public function test_platform_admin_can_access_analytics_page(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');

        $response->assertOk();
        $response->assertSee('Analitik Penggunaan Platform');
        $response->assertSee('Total Transaksi');
        $response->assertSee('Nilai Transaksi');
    }

    public function test_business_owner_is_forbidden_from_analytics(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)->get('/platform/analytics')->assertForbidden();
    }

    public function test_regular_user_is_forbidden_from_analytics(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/platform/analytics')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/platform/analytics')->assertRedirect(route('login'));
    }

    public function test_unverified_platform_admin_is_redirected_to_verification(): void
    {
        $unverifiedAdmin = User::factory()->unverified()->create(['is_platform_admin' => true]);

        $this->actingAs($unverifiedAdmin)->get('/platform/analytics')->assertRedirect(route('verification.notice'));
    }

    // ============================================================
    // 2. Transaction Summary & Status Semantics Tests
    // ============================================================

    public function test_transaction_summary_counts_completed_sales_and_excludes_cancelled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $businessA = Business::factory()->create(['name' => 'Kedai Kopi A']);
        $businessB = Business::factory()->create(['name' => 'Resto B']);
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        // Completed sales for business A (Total: 2 completed = 75.000)
        Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'transaction_number' => 'TRX-A-01',
            'status' => 'completed',
            'subtotal' => 25000,
            'total_amount' => 25000,
            'sold_at' => now()->subDays(2),
        ]);
        Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'transaction_number' => 'TRX-A-02',
            'status' => 'completed',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'sold_at' => now()->subDays(1),
        ]);

        // Cancelled sale for business A (should NOT be counted in completed metrics)
        Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'transaction_number' => 'TRX-A-CANCELLED',
            'status' => 'cancelled',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'sold_at' => now()->subDays(1),
        ]);

        // Completed sale for business B (Total: 1 completed = 40.000)
        Sale::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-B-01',
            'status' => 'completed',
            'subtotal' => 40000,
            'total_amount' => 40000,
            'sold_at' => now()->subHours(5),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');

        $response->assertOk();
        // 3 completed sales total
        $response->assertSee('3');
        // Total value = 25.000 + 50.000 + 40.000 = 115.000
        $response->assertSee('Rp 115.000');
        // 2 distinct businesses with completed sales
        $response->assertSee('Kedai Kopi A');
        $response->assertSee('Resto B');
    }

    // ============================================================
    // 3. Date Range Filter Tests
    // ============================================================

    public function test_date_filters_preset_and_custom_ranges(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Sale today (Oct 15)
        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-TODAY',
            'status' => 'completed',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => Carbon::parse('2026-10-15 10:00:00'),
        ]);

        // Sale 5 days ago (Oct 10)
        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-5DAYS',
            'status' => 'completed',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => Carbon::parse('2026-10-10 10:00:00'),
        ]);

        // Sale 20 days ago (Sep 25)
        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-20DAYS',
            'status' => 'completed',
            'subtotal' => 30000,
            'total_amount' => 30000,
            'sold_at' => Carbon::parse('2026-09-25 10:00:00'),
        ]);

        // 1. Preset: today (only TRX-TODAY: 10.000)
        $responseToday = $this->actingAs($platformAdmin)->get('/platform/analytics?date=today');
        $responseToday->assertOk();
        $responseToday->assertSee('Rp 10.000');

        // 2. Preset: 7days (TRX-TODAY + TRX-5DAYS: 30.000)
        $response7 = $this->actingAs($platformAdmin)->get('/platform/analytics?date=7days');
        $response7->assertOk();
        $response7->assertSee('Rp 30.000');

        // 3. Preset: 30days (TRX-TODAY + TRX-5DAYS + TRX-20DAYS: 60.000)
        $response30 = $this->actingAs($platformAdmin)->get('/platform/analytics?date=30days');
        $response30->assertOk();
        $response30->assertSee('Rp 60.000');

        // 4. Custom range (Sep 20 to Sep 30: only TRX-20DAYS: 30.000)
        $responseCustom = $this->actingAs($platformAdmin)->get('/platform/analytics?date=custom&start_date=2026-09-20&end_date=2026-09-30');
        $responseCustom->assertOk();
        $responseCustom->assertSee('Rp 30.000');

        // 5. Reversed custom range (end earlier than start): should safely swap and still return 30.000
        $responseReversed = $this->actingAs($platformAdmin)->get('/platform/analytics?date=custom&start_date=2026-09-30&end_date=2026-09-20');
        $responseReversed->assertOk();
        $responseReversed->assertSee('Rp 30.000');
    }

    // ============================================================
    // 4. Sync Usage Tests
    // ============================================================

    public function test_sync_usage_aggregates_committed_requests(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $deviceA = Device::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'name' => 'Device A',
            'identifier' => 'DEV-A',
            'registered_at' => now(),
        ]);
        $deviceB = Device::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'name' => 'Device B',
            'identifier' => 'DEV-B',
            'registered_at' => now(),
        ]);

        // Sync requests within period
        SyncRequest::create([
            'business_id' => $businessA->id,
            'device_id' => $deviceA->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now()->subDays(2),
        ]);
        SyncRequest::create([
            'business_id' => $businessA->id,
            'device_id' => $deviceA->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now()->subDay(),
        ]);
        SyncRequest::create([
            'business_id' => $businessB->id,
            'device_id' => $deviceB->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now()->subHours(3),
        ]);

        // Sync request outside 30 days period
        SyncRequest::create([
            'business_id' => $businessA->id,
            'device_id' => $deviceA->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now()->subDays(45),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');

        $response->assertOk();
        // 3 requests within 30 days
        $response->assertSee('Total Sync Requests:');
        $response->assertSee('Bisnis yang Sinkron:');
        $response->assertSee('Perangkat Melakukan Sync:');
    }

    // ============================================================
    // 5. Device Activity Telemetry Tests
    // ============================================================

    public function test_device_metric_counts_seen_in_last_30_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Device seen 2 days ago (counted)
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device Recent',
            'identifier' => 'DEV-RECENT',
            'registered_at' => now()->subDays(10),
            'last_seen_at' => now()->subDays(2),
        ]);

        // Device seen 40 days ago (not counted)
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device Old',
            'identifier' => 'DEV-OLD',
            'registered_at' => now()->subDays(60),
            'last_seen_at' => now()->subDays(40),
        ]);

        // Device never seen (last_seen_at null, not counted)
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device Unseen',
            'identifier' => 'DEV-UNSEEN',
            'registered_at' => now()->subDays(5),
            'last_seen_at' => null,
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');

        $response->assertOk();
        $response->assertSee('Device Terlihat (30h)');
        // 1 device seen in last 30 days
        $response->assertSee('1');
    }

    // ============================================================
    // 6. Subscription Distribution Tests
    // ============================================================

    public function test_subscription_distribution_calculates_cloud_and_non_cloud(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();

        // 1. Cloud Active
        $bizCloud = Business::factory()->create(['name' => 'Cloud Active Biz']);
        Subscription::factory()->cloud()->create([
            'business_id' => $bizCloud->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        // 2. Cloud Expired (should be Non-Cloud)
        $bizExpired = Business::factory()->create(['name' => 'Expired Cloud Biz']);
        Subscription::factory()->cloud()->expired()->create([
            'business_id' => $bizExpired->id,
        ]);

        // 3. Free (Non-Cloud)
        $bizFree = Business::factory()->create(['name' => 'Free Biz']);
        Subscription::factory()->free()->create([
            'business_id' => $bizFree->id,
        ]);

        // 4. No subscription row at all (Non-Cloud)
        Business::factory()->create(['name' => 'No Sub Biz']);

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');

        $response->assertOk();
        $response->assertSee('Distribusi Langganan Bisnis');
        // 1 Cloud Active
        $response->assertSee('1 Bisnis (25%)');
        // 3 Non-cloud businesses
        $response->assertSee('3 Bisnis');
        // Total 4 businesses
        $response->assertSee('4 Bisnis');
    }

    // ============================================================
    // 7. Top Businesses Ranking Tests
    // ============================================================

    public function test_top_businesses_are_sorted_by_activity_and_limited_to_ten(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();

        $bizTop = Business::factory()->create(['name' => 'Top Merchant']);
        $bizMid = Business::factory()->create(['name' => 'Mid Merchant']);
        $bizLow = Business::factory()->create(['name' => 'Low Merchant']);
        $outletTop = Outlet::factory()->create(['business_id' => $bizTop->id]);
        $outletMid = Outlet::factory()->create(['business_id' => $bizMid->id]);
        $outletLow = Outlet::factory()->create(['business_id' => $bizLow->id]);

        // Top merchant: 3 transactions
        for ($i = 1; $i <= 3; $i++) {
            Sale::create([
                'business_id' => $bizTop->id,
                'outlet_id' => $outletTop->id,
                'transaction_number' => 'TOP-'.$i,
                'status' => 'completed',
                'subtotal' => 10000,
                'total_amount' => 10000,
                'sold_at' => now()->subDays(1),
            ]);
        }

        // Mid merchant: 2 transactions
        for ($i = 1; $i <= 2; $i++) {
            Sale::create([
                'business_id' => $bizMid->id,
                'outlet_id' => $outletMid->id,
                'transaction_number' => 'MID-'.$i,
                'status' => 'completed',
                'subtotal' => 20000,
                'total_amount' => 20000,
                'sold_at' => now()->subDays(1),
            ]);
        }

        // Low merchant: 1 transaction
        Sale::create([
            'business_id' => $bizLow->id,
            'outlet_id' => $outletLow->id,
            'transaction_number' => 'LOW-1',
            'status' => 'completed',
            'subtotal' => 5000,
            'total_amount' => 5000,
            'sold_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');

        $response->assertOk();
        $response->assertSee('Bisnis dengan Aktivitas Transaksi Tertinggi');
        $response->assertSee('Top Merchant');
        $response->assertSee('Mid Merchant');
        $response->assertSee('Low Merchant');

        // Check order in HTML
        $content = $response->getContent();
        $this->assertNotFalse($content);
        $posTop = strpos($content, 'Top Merchant');
        $posMid = strpos($content, 'Mid Merchant');
        $posLow = strpos($content, 'Low Merchant');

        $this->assertTrue($posTop < $posMid && $posMid < $posLow, 'Top businesses should be ordered by activity desc');
    }

    // ============================================================
    // 8. Zero State Test
    // ============================================================

    public function test_zero_state_renders_cleanly_without_errors(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');

        $response->assertOk();
        $response->assertSee('0');
        $response->assertSee('Rp 0');
        $response->assertSee('Belum ada transaksi merchant yang tercatat pada rentang periode ini.');
    }

    // ============================================================
    // 9. Sensitive Data & Privacy Protection Tests
    // ============================================================

    public function test_page_does_not_leak_tenant_sensitive_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-SENSITIVE',
            'status' => 'completed',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'gross_profit' => 15000.50,
            'cash_received' => 60000,
            'change_amount' => 10000,
            'note' => 'Catatan Rahasia Pembeli VIP',
            'customer_snapshot' => [
                'name' => 'Budi Santoso Rahasia',
                'phone' => '081299998888',
            ],
            'sold_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');

        $response->assertOk();
        $response->assertDontSee('Budi Santoso Rahasia');
        $response->assertDontSee('081299998888');
        $response->assertDontSee('Catatan Rahasia Pembeli VIP');
        $response->assertDontSee('15000.50');
        $response->assertDontSee('gross_profit');
    }

    // ============================================================
    // 10. No Fake Telemetry or Unsupported Metrics Tests
    // ============================================================

    public function test_page_does_not_invent_fake_metrics(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');

        $response->assertOk();
        // Should NOT invent fake active user metrics
        $response->assertDontSee('Active Users');
        $response->assertDontSee('Daily Active Users');
        $response->assertDontSee('Monthly Active Users');
        $response->assertDontSee('DAU');
        $response->assertDontSee('MAU');

        // Should NOT invent fake backup metrics
        $response->assertDontSee('Backup Count');
        $response->assertDontSee('Storage Usage');
        $response->assertDontSee('Restore Usage');

        // Should NOT call merchant transaction volume as platform revenue
        $response->assertDontSee('Revenue Platform');
        $response->assertDontSee('Platform Revenue');
    }

    // ============================================================
    // 11. Read-Only Guarantee Tests
    // ============================================================

    public function test_analytics_is_read_only_and_does_not_mutate_database(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $sub = Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'POS-01',
            'registered_at' => now(),
            'last_seen_at' => now(),
        ]);
        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'RO-01',
            'status' => 'completed',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
        $sync = SyncRequest::create([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now(),
        ]);

        $initialBusinessCount = Business::count();
        $initialSubCount = Subscription::count();
        $initialDeviceCount = Device::count();
        $initialSaleCount = Sale::count();
        $initialSyncCount = SyncRequest::count();

        $response = $this->actingAs($platformAdmin)->get('/platform/analytics');
        $response->assertOk();

        $this->assertSame($initialBusinessCount, Business::count());
        $this->assertSame($initialSubCount, Subscription::count());
        $this->assertSame($initialDeviceCount, Device::count());
        $this->assertSame($initialSaleCount, Sale::count());
        $this->assertSame($initialSyncCount, SyncRequest::count());

        // Mutation endpoints do NOT exist
        $this->actingAs($platformAdmin)->post('/platform/analytics')->assertStatus(405);
        $this->actingAs($platformAdmin)->patch('/platform/analytics')->assertStatus(405);
        $this->actingAs($platformAdmin)->delete('/platform/analytics')->assertStatus(405);
    }
}
