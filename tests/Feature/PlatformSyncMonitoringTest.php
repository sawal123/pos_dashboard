<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\SyncCounter;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSyncMonitoringTest extends TestCase
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

    public function test_platform_admin_can_access_sync_index(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/sync');

        $response->assertOk();
        $response->assertSee('Monitoring Sinkronisasi');
        $response->assertSee('Total Committed');
        $response->assertSee('Bisnis Terhubung');
        $response->assertSee('Perangkat Terhubung');
        $response->assertSee('Terakhir Diproses');
    }

    public function test_platform_admin_can_access_sync_show(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $req = $this->createSyncRequest();

        $response = $this->actingAs($platformAdmin)->get('/platform/sync/'.$req->id);

        $response->assertOk();
        $response->assertSee('Detail Sync Request');
        $response->assertSee($req->request_id);
        $response->assertSee('Committed');
    }

    public function test_business_owner_is_forbidden_from_sync_index_and_show(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $req = $this->createSyncRequest(['business_id' => $business->id]);

        $this->actingAs($owner)->get('/platform/sync')->assertForbidden();
        $this->actingAs($owner)->get('/platform/sync/'.$req->id)->assertForbidden();
    }

    public function test_regular_user_is_forbidden_from_sync_index_and_show(): void
    {
        $user = User::factory()->create();
        $req = $this->createSyncRequest();

        $this->actingAs($user)->get('/platform/sync')->assertForbidden();
        $this->actingAs($user)->get('/platform/sync/'.$req->id)->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_sync_index_and_show(): void
    {
        $req = $this->createSyncRequest();

        $this->get('/platform/sync')->assertRedirect(route('login'));
        $this->get('/platform/sync/'.$req->id)->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification(): void
    {
        $unverified = User::factory()->unverified()->create(['is_platform_admin' => true]);

        $this->actingAs($unverified)->get('/platform/sync')->assertRedirect(route('verification.notice'));
    }

    // ============================================================
    // 2. Global Cross-Business Observability
    // ============================================================

    public function test_platform_admin_sees_sync_requests_across_multiple_businesses(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $businessA = Business::factory()->create(['name' => 'Kopi Nusantara']);
        $businessB = Business::factory()->create(['name' => 'Laundry Berkah']);

        $reqA = $this->createSyncRequest([
            'business_id' => $businessA->id,
            'request_id' => '11111111-1111-1111-1111-111111111111',
        ]);
        $reqB = $this->createSyncRequest([
            'business_id' => $businessB->id,
            'request_id' => '22222222-2222-2222-2222-222222222222',
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync');

        $response->assertOk();
        $response->assertSee('Kopi Nusantara');
        $response->assertSee('11111111-1111-1111-1111-111111111111');
        $response->assertSee('Laundry Berkah');
        $response->assertSee('22222222-2222-2222-2222-222222222222');
    }

    // ============================================================
    // 3. Search Functionality
    // ============================================================

    public function test_search_by_request_id(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $req1 = $this->createSyncRequest(['request_id' => 'aaaaaaaa-0000-0000-0000-000000000001']);
        $req2 = $this->createSyncRequest(['request_id' => 'bbbbbbbb-0000-0000-0000-000000000002']);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync?q=aaaaaaaa');

        $response->assertOk();
        $response->assertSee('aaaaaaaa-0000-0000-0000-000000000001');
        $response->assertDontSee('bbbbbbbb-0000-0000-0000-000000000002');
    }

    public function test_search_by_device_name(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $device1 = $this->createDevice(['business_id' => $business->id, 'name' => 'Tablet Utama Kasir']);
        $device2 = $this->createDevice(['business_id' => $business->id, 'name' => 'Smartphone Dapur']);

        $req1 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device1->id]);
        $req2 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device2->id]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync?q=Tablet+Utama');

        $response->assertOk();
        $response->assertSee($req1->request_id);
        $response->assertDontSee($req2->request_id);
    }

    public function test_search_by_device_identifier(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $device1 = $this->createDevice(['business_id' => $business->id, 'identifier' => 'POS-DEV-ALPHA-999']);
        $device2 = $this->createDevice(['business_id' => $business->id, 'identifier' => 'POS-DEV-BETA-888']);

        $req1 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device1->id]);
        $req2 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device2->id]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync?q=ALPHA-999');

        $response->assertOk();
        $response->assertSee('POS-DEV-ALPHA-999');
        $response->assertSee($req1->request_id);
        $response->assertDontSee($req2->request_id);
    }

    public function test_search_by_business_name_and_slug(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $businessA = Business::factory()->create(['name' => 'Resto Sedap Rasa', 'slug' => 'resto-sedap-rasa']);
        $businessB = Business::factory()->create(['name' => 'Toko Buku Maju', 'slug' => 'toko-buku-maju']);

        $reqA = $this->createSyncRequest(['business_id' => $businessA->id]);
        $reqB = $this->createSyncRequest(['business_id' => $businessB->id]);

        // Search by business name
        $response1 = $this->actingAs($platformAdmin)->get('/platform/sync?q=Sedap+Rasa');
        $response1->assertOk();
        $response1->assertSee($reqA->request_id);
        $response1->assertDontSee($reqB->request_id);

        // Search by slug
        $response2 = $this->actingAs($platformAdmin)->get('/platform/sync?q=toko-buku');
        $response2->assertOk();
        $response2->assertSee($reqB->request_id);
        $response2->assertDontSee($reqA->request_id);
    }

    public function test_search_by_outlet_name(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $outlet1 = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Cabang Sudirman']);
        $outlet2 = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Cabang Thamrin']);

        $device1 = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outlet1->id]);
        $device2 = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outlet2->id]);

        $req1 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device1->id]);
        $req2 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device2->id]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync?q=Sudirman');

        $response->assertOk();
        $response->assertSee('Cabang Sudirman');
        $response->assertSee($req1->request_id);
        $response->assertDontSee($req2->request_id);
    }

    // ============================================================
    // 4. Filtering (Business, Device, Outlet, Date)
    // ============================================================

    public function test_filter_by_business_id(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $businessA = Business::factory()->create(['name' => 'Bisnis Satu']);
        $businessB = Business::factory()->create(['name' => 'Bisnis Dua']);

        $reqA = $this->createSyncRequest(['business_id' => $businessA->id]);
        $reqB = $this->createSyncRequest(['business_id' => $businessB->id]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync?business_id='.$businessA->id);

        $response->assertOk();
        $response->assertSee($reqA->request_id);
        $response->assertDontSee($reqB->request_id);
    }

    public function test_filter_by_device_id(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $device1 = $this->createDevice(['business_id' => $business->id]);
        $device2 = $this->createDevice(['business_id' => $business->id]);

        $req1 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device1->id]);
        $req2 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device2->id]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync?device_id='.$device1->id);

        $response->assertOk();
        $response->assertSee($req1->request_id);
        $response->assertDontSee($req2->request_id);
    }

    public function test_filter_by_outlet_id(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $outlet1 = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet Satu']);
        $outlet2 = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet Dua']);

        $device1 = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outlet1->id]);
        $device2 = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outlet2->id]);

        $req1 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device1->id]);
        $req2 = $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $device2->id]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync?outlet_id='.$outlet1->id);

        $response->assertOk();
        $response->assertSee($req1->request_id);
        $response->assertDontSee($req2->request_id);
    }

    public function test_filter_by_date_presets(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        $platformAdmin = User::factory()->platformAdmin()->create();

        $reqToday = $this->createSyncRequest(['processed_at' => Carbon::parse('2026-10-02 10:00:00')]);
        $req5DaysAgo = $this->createSyncRequest(['processed_at' => Carbon::parse('2026-09-27 10:00:00')]);
        $req20DaysAgo = $this->createSyncRequest(['processed_at' => Carbon::parse('2026-09-12 10:00:00')]);
        $req45DaysAgo = $this->createSyncRequest(['processed_at' => Carbon::parse('2026-08-18 10:00:00')]);

        // Today: only reqToday
        $resToday = $this->actingAs($platformAdmin)->get('/platform/sync?date=today');
        $resToday->assertOk();
        $resToday->assertSee($reqToday->request_id);
        $resToday->assertDontSee($req5DaysAgo->request_id);
        $resToday->assertDontSee($req20DaysAgo->request_id);
        $resToday->assertDontSee($req45DaysAgo->request_id);

        // 7 days: reqToday + req5DaysAgo
        $res7Days = $this->actingAs($platformAdmin)->get('/platform/sync?date=7days');
        $res7Days->assertOk();
        $res7Days->assertSee($reqToday->request_id);
        $res7Days->assertSee($req5DaysAgo->request_id);
        $res7Days->assertDontSee($req20DaysAgo->request_id);
        $res7Days->assertDontSee($req45DaysAgo->request_id);

        // 30 days: reqToday + req5DaysAgo + req20DaysAgo
        $res30Days = $this->actingAs($platformAdmin)->get('/platform/sync?date=30days');
        $res30Days->assertOk();
        $res30Days->assertSee($reqToday->request_id);
        $res30Days->assertSee($req5DaysAgo->request_id);
        $res30Days->assertSee($req20DaysAgo->request_id);
        $res30Days->assertDontSee($req45DaysAgo->request_id);
    }

    public function test_custom_date_filter_handles_reversed_start_and_end(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $reqInRange = $this->createSyncRequest(['processed_at' => Carbon::parse('2026-09-15 12:00:00')]);
        $reqOutRange = $this->createSyncRequest(['processed_at' => Carbon::parse('2026-09-25 12:00:00')]);

        // Pass start_date > end_date (2026-09-20 > 2026-09-10) -> service swaps them
        $response = $this->actingAs($platformAdmin)->get('/platform/sync?date=custom&start_date=2026-09-20&end_date=2026-09-10');

        $response->assertOk();
        $response->assertSee($reqInRange->request_id);
        $response->assertDontSee($reqOutRange->request_id);
    }

    // ============================================================
    // 5. Pagination
    // ============================================================

    public function test_pagination_and_query_string_preservation(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();

        for ($i = 1; $i <= 28; $i++) {
            $this->createSyncRequest([
                'business_id' => $business->id,
                'request_id' => sprintf('00000000-0000-0000-0000-%012d', $i),
                'processed_at' => Carbon::parse('2026-10-01 00:00:00')->addMinutes($i),
            ]);
        }

        // Page 1 should contain 25 records (sorted desc)
        $resPage1 = $this->actingAs($platformAdmin)->get('/platform/sync?business_id='.$business->id);
        $resPage1->assertOk();
        $resPage1->assertSee(sprintf('00000000-0000-0000-0000-%012d', 28));
        $resPage1->assertDontSee(sprintf('00000000-0000-0000-0000-%012d', 1));

        // Page 2 should contain the remaining 3 records
        $resPage2 = $this->actingAs($platformAdmin)->get('/platform/sync?business_id='.$business->id.'&page=2');
        $resPage2->assertOk();
        $resPage2->assertSee(sprintf('00000000-0000-0000-0000-%012d', 1));
        $resPage2->assertSee('business_id='.$business->id);
    }

    // ============================================================
    // 6. Detail View
    // ============================================================

    public function test_detail_displays_full_context_including_server_sequence(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create([
            'name' => 'Kopi Mantap Jiwa',
            'slug' => 'kopi-mantap-jiwa',
        ]);
        SyncCounter::where('business_id', $business->id)->update([
            'current_sequence' => 142,
        ]);
        $outlet = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Outlet Pusat',
        ]);
        $device = $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Kasir Utama',
            'identifier' => 'DEV-KMJ-001',
            'platform' => 'android',
            'status' => 'active',
            'last_seen_at' => Carbon::parse('2026-10-02 08:30:00'),
        ]);

        $syncRequest = $this->createSyncRequest([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'request_id' => '33333333-3333-3333-3333-333333333333',
            'processed_at' => Carbon::parse('2026-10-02 08:31:00'),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync/'.$syncRequest->id);

        $response->assertOk();
        $response->assertSee('33333333-3333-3333-3333-333333333333');
        $response->assertSee('Kopi Mantap Jiwa');
        $response->assertSee('kopi-mantap-jiwa');
        $response->assertSee('Kasir Utama');
        $response->assertSee('DEV-KMJ-001');
        $response->assertSee('Outlet Pusat');
        $response->assertSee('142'); // Server sequence
        $response->assertSee('Committed');
        $response->assertSee('YA (Tersimpan pada tabel sync_requests)');
    }

    // ============================================================
    // 7. Historical vs Current Separation
    // ============================================================

    public function test_historical_sync_separated_from_current_device_and_subscription_status(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();

        // Expired subscription
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDays(5),
        ]);

        // Device is currently inactive
        $device = $this->createDevice([
            'business_id' => $business->id,
            'status' => Device::STATUS_INACTIVE,
            'last_seen_at' => now()->subDays(10),
        ]);

        // Historical sync request committed yesterday
        $syncRequest = $this->createSyncRequest([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'request_id' => '44444444-4444-4444-4444-444444444444',
            'processed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync/'.$syncRequest->id);

        $response->assertOk();
        // Historical request remains committed
        $response->assertSee('Committed');
        // Current device is inactive
        $response->assertSee('Nonaktif');
        // Current cloud entitlement is denied
        $response->assertSee('Denied (Nonaktif / Expired)');
        // Must NOT label historical request as broken/failed/invalid
        $response->assertDontSee('Failed Sync');
        $response->assertDontSee('Sync Failed');
    }

    // ============================================================
    // 8. No Fake Telemetry / Observability Transparency
    // ============================================================

    public function test_ui_does_not_display_fake_failure_or_queue_telemetry(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $this->createSyncRequest();

        $response = $this->actingAs($platformAdmin)->get('/platform/sync');

        $response->assertOk();
        // Assert absence of fabricated metrics
        $response->assertDontSee('Failed Sync:');
        $response->assertDontSee('Pending Queue:');
        $response->assertDontSee('Conflict:');
        $response->assertDontSee('Success Rate:');

        // Assert presence of honest observability notices
        $response->assertSee('Observabilitas Sinkronisasi');
        $response->assertSee('Server mencatat sync push yang berhasil committed');
    }

    // ============================================================
    // 9. Read-Only Guarantee
    // ============================================================

    public function test_read_only_guarantee_no_state_mutation(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        SyncCounter::where('business_id', $business->id)->update(['current_sequence' => 50]);
        $counter = SyncCounter::where('business_id', $business->id)->firstOrFail();
        $device = $this->createDevice(['business_id' => $business->id]);
        $req = $this->createSyncRequest([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'processed_at' => Carbon::parse('2026-10-01 10:00:00'),
        ]);

        $initialReqCount = SyncRequest::count();
        $initialCounterSeq = $counter->fresh()->current_sequence;
        $initialDeviceStatus = $device->fresh()->status;

        $this->actingAs($platformAdmin)->get('/platform/sync');
        $this->actingAs($platformAdmin)->get('/platform/sync/'.$req->id);

        $this->assertSame($initialReqCount, SyncRequest::count());
        $this->assertSame($initialCounterSeq, $counter->fresh()->current_sequence);
        $this->assertSame($initialDeviceStatus, $device->fresh()->status);
        $this->assertSame('2026-10-01 10:00:00', $req->fresh()->processed_at->format('Y-m-d H:i:s'));
    }

    // ============================================================
    // 10. Zero State & Edge Cases
    // ============================================================

    public function test_zero_state_renders_cleanly(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/sync');

        $response->assertOk();
        $response->assertSee('Belum Ada Riwayat Sinkronisasi');
        $response->assertSee('Belum Ada'); // Last processed metric
    }

    public function test_detail_handles_missing_counter_and_null_last_seen(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        // Remove SyncCounter to test fallback
        SyncCounter::where('business_id', $business->id)->delete();

        $device = $this->createDevice([
            'business_id' => $business->id,
            'last_seen_at' => null,
        ]);
        $req = $this->createSyncRequest([
            'business_id' => $business->id,
            'device_id' => $device->id,
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/sync/'.$req->id);

        $response->assertOk();
        $response->assertSee('Business Server Sequence');
        // Sequence falls back to 0
        $response->assertSee('0');
        $response->assertSee('Belum Ada');
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDevice(array $attributes = []): Device
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = array_key_exists('outlet_id', $attributes)
            ? $attributes['outlet_id']
            : Outlet::factory()->create(['business_id' => $businessId])->id;

        return Device::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'name' => 'Device '.Str::random(6),
            'identifier' => 'device-'.Str::random(10),
            'platform' => 'android',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => now(),
            'notes' => null,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSyncRequest(array $attributes = []): SyncRequest
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $deviceId = $attributes['device_id'] ?? $this->createDevice(['business_id' => $businessId])->id;

        return SyncRequest::create(array_merge([
            'business_id' => $businessId,
            'device_id' => $deviceId,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now(),
        ], $attributes));
    }
}
