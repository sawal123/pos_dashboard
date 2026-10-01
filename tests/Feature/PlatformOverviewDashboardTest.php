<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformOverviewDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A. Platform Admin dapat melihat aggregate total bisnis dan status.
     */
    public function test_platform_admin_can_view_aggregated_business_metrics(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        // 3 active businesses (2 baru, 1 lama > 30 hari)
        Business::factory()->create(['status' => 'active', 'created_at' => now()]);
        Business::factory()->create(['status' => 'active', 'created_at' => now()->subDays(5)]);
        Business::factory()->create(['status' => 'active', 'created_at' => now()->subDays(40)]);

        // 2 inactive businesses
        Business::factory()->create(['status' => 'inactive', 'created_at' => now()]);
        Business::factory()->create(['status' => 'inactive', 'created_at' => now()]);

        $response = $this->actingAs($platformAdmin)->get('/platform');

        $response->assertOk();
        $response->assertSee('Platform Admin Overview');
        $response->assertSee('Total Bisnis');

        // Gunakan assertion terarah pada markup card bisnis
        $content = $response->getContent();
        $this->assertStringContainsString('data-testid="platform-total-businesses"', $content);
        $this->assertStringContainsString('data-testid="platform-active-businesses"', $content);
        $this->assertStringContainsString('data-testid="platform-inactive-businesses"', $content);
        $this->assertStringContainsString('data-testid="platform-recent-businesses"', $content);

        // Nilai metrik: 5 total, 3 aktif, 2 nonaktif, 4 dibuat dalam 30 hari terakhir
        $this->assertMatchesRegularExpression('/data-testid="platform-total-businesses">\s*5\s*<\/div>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-active-businesses">\s*3\s*<\/strong>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-inactive-businesses">\s*2\s*<\/strong>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-recent-businesses">\s*\+4\s*\(30h\)\s*<\/span>/', $content);
    }

    /**
     * B. Total user dihitung secara benar dengan pemisahan eksplisit: User Bisnis, Platform Admin, dan Belum Terhubung.
     */
    public function test_platform_admin_can_view_user_metrics_with_unambiguous_breakdown(): void
    {
        // 2 Platform Admin (1 acting admin + 1 extra admin)
        $adminA = User::factory()->platformAdmin()->create(['created_at' => now()]);
        User::factory()->platformAdmin()->create(['created_at' => now()]);

        // 3 user yang memiliki membership business
        $business = Business::factory()->create();
        $businessUser1 = User::factory()->create(['is_platform_admin' => false, 'created_at' => now()]);
        $businessUser2 = User::factory()->create(['is_platform_admin' => false, 'created_at' => now()]);
        $businessUser3 = User::factory()->create(['is_platform_admin' => false, 'created_at' => now()->subDays(45)]);

        $business->users()->attach($businessUser1->id, ['role' => Business::ROLE_OWNER]);
        $business->users()->attach($businessUser2->id, ['role' => Business::ROLE_CASHIER]);
        $business->users()->attach($businessUser3->id, ['role' => Business::ROLE_MEMBER]);

        // 1 regular user tanpa business
        User::factory()->create(['is_platform_admin' => false, 'created_at' => now()]);

        $response = $this->actingAs($adminA)->get('/platform');

        $response->assertOk();
        $content = $response->getContent();

        // Total Pengguna = 6, User Bisnis = 3, Platform Admin = 2, Belum Terhubung = 1
        $this->assertMatchesRegularExpression('/data-testid="platform-total-users">\s*6\s*<\/div>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-business-users">\s*3\s*<\/strong>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-admin-users">\s*2\s*<\/strong>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-unconnected-users">\s*1\s*<\/strong>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-recent-users">\s*\+5\s*\(30h\)\s*<\/span>/', $content);
    }

    /**
     * C. Ringkasan subscription akurat berdasarkan plan dan status existing.
     */
    public function test_platform_admin_can_view_subscription_summary_metrics(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        // 2 Free Active
        Subscription::factory()->free()->create();
        Subscription::factory()->free()->create();

        // 1 Cloud Active
        Subscription::factory()->cloud()->create();

        // 1 Cloud Expired
        Subscription::factory()->cloud()->expired()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform');

        $response->assertOk();
        $content = $response->getContent();

        // Total 4 subscription: 2 free, 2 cloud, 3 active, 1 expired
        $this->assertMatchesRegularExpression('/data-testid="platform-total-subscriptions">\s*4\s*<\/div>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-plan-free">\s*2\s*<\/strong>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-plan-cloud">\s*2\s*<\/strong>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-sub-active">\s*3\s*Aktif\s*<\/span>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-sub-expired">\s*1\s*<\/span>/', $content);
    }

    /**
     * D. Ringkasan total device, status aktif/nonaktif, dan keaktifan 30 hari.
     */
    public function test_platform_admin_can_view_device_summary_metrics(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Device 1: active, seen 5 days ago
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Kasir 1',
            'identifier' => 'DEV-01',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => now()->subDays(5),
        ]);

        // Device 2: active, seen 45 days ago
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Kasir 2',
            'identifier' => 'DEV-02',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => now()->subDays(45),
        ]);

        // Device 3: inactive, no last seen
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Backup',
            'identifier' => 'DEV-03',
            'status' => 'inactive',
            'registered_at' => now(),
            'last_seen_at' => null,
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform');

        $response->assertOk();
        $content = $response->getContent();

        // 3 devices total, 2 active, 1 inactive, 1 seen in recent 30d
        $this->assertMatchesRegularExpression('/data-testid="platform-total-devices">\s*3\s*<\/div>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-active-devices">\s*2\s*<\/strong>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-inactive-devices">\s*1\s*<\/strong>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-recent-devices">\s*1\s*Terlihat\s*\(30h\)\s*<\/span>/', $content);
    }

    /**
     * E. Ringkasan sinkronisasi global dari tabel sync_requests.
     */
    public function test_platform_admin_can_view_sync_summary_metrics(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $businessA = Business::factory()->create();
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);

        $deviceA = Device::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'name' => 'Device A',
            'identifier' => 'DEV-A',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $deviceB = Device::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'name' => 'Device B',
            'identifier' => 'DEV-B',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        // 2 sync push dari Device A (1 baru, 1 lama)
        SyncRequest::create([
            'business_id' => $businessA->id,
            'device_id' => $deviceA->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now()->subHour(),
        ]);
        SyncRequest::create([
            'business_id' => $businessA->id,
            'device_id' => $deviceA->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now()->subDays(40),
        ]);

        // 1 sync push dari Device B (baru)
        SyncRequest::create([
            'business_id' => $businessA->id,
            'device_id' => $deviceB->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform');

        $response->assertOk();
        $content = $response->getContent();

        // 3 sync requests total, 2 devices with sync push, 2 in recent 30d
        $this->assertMatchesRegularExpression('/data-testid="platform-total-sync-requests">\s*3\s*<\/span>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-synced-devices">\s*2\s*<\/span>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-recent-sync-requests">\s*2\s*<\/span>/', $content);
    }

    /**
     * F. Database kosong dari domain data: dashboard tetap 200 dan menampilkan metrik 0 / empty state.
     */
    public function test_dashboard_renders_cleanly_when_database_is_empty(): void
    {
        // Hanya satu user platform admin agar dapat login
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform');

        $response->assertOk();
        $content = $response->getContent();

        // Nilai total 0 untuk domain models
        $this->assertMatchesRegularExpression('/data-testid="platform-total-businesses">\s*0\s*<\/div>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-total-subscriptions">\s*0\s*<\/div>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-total-devices">\s*0\s*<\/div>/', $content);
        $this->assertMatchesRegularExpression('/data-testid="platform-total-sync-requests">\s*0\s*<\/span>/', $content);

        // Empty state list aktivitas terbaru
        $response->assertSee('Belum ada bisnis terdaftar di database.');
    }

    /**
     * G. Aktivitas terbaru menampilkan record bisnis dan pengguna nyata.
     */
    public function test_recent_activity_shows_latest_businesses_and_users(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $biz1 = Business::factory()->create(['name' => 'Kedai Kopi Senja', 'slug' => 'kedai-kopi-senja']);
        $biz2 = Business::factory()->create(['name' => 'Apotek Sehat Bersama', 'slug' => 'apotek-sehat-bersama']);

        $customerUser = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@merchant.com']);

        $response = $this->actingAs($platformAdmin)->get('/platform');

        $response->assertOk();
        $response->assertSee('Kedai Kopi Senja');
        $response->assertSee('kedai-kopi-senja');
        $response->assertSee('Apotek Sehat Bersama');
        $response->assertSee('Budi Santoso');
        $response->assertSee('budi@merchant.com');
    }

    /**
     * Aktivitas terbaru diurutkan berdasarkan created_at terbaru, bukan bergantung pada ID.
     */
    public function test_recent_activity_orders_records_by_created_at_descending_regardless_of_id(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        // Buat record: older dibuat lebih dahulu (created_at lama), newer dibuat belakangan (created_at baru)
        $olderBusiness = Business::factory()->create([
            'name' => 'Bisnis Lebih Lama',
            'slug' => 'bisnis-lama',
            'created_at' => now()->subDays(10),
        ]);

        $newerBusiness = Business::factory()->create([
            'name' => 'Bisnis Lebih Baru',
            'slug' => 'bisnis-baru',
            'created_at' => now(),
        ]);

        $olderUser = User::factory()->create([
            'name' => 'User Lebih Lama',
            'email' => 'lama@example.com',
            'created_at' => now()->subDays(15),
        ]);

        $newerUser = User::factory()->create([
            'name' => 'User Lebih Baru',
            'email' => 'baru@example.com',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform');
        $response->assertOk();
        $content = $response->getContent();

        // Pastikan record yang lebih baru muncul SEBELUM record yang lebih lama di HTML
        $posNewerBiz = strpos($content, 'Bisnis Lebih Baru');
        $posOlderBiz = strpos($content, 'Bisnis Lebih Lama');
        $this->assertNotFalse($posNewerBiz);
        $this->assertNotFalse($posOlderBiz);
        $this->assertLessThan($posOlderBiz, $posNewerBiz, 'Bisnis dengan created_at lebih baru harus muncul sebelum bisnis yang lebih lama.');

        $posNewerUser = strpos($content, 'User Lebih Baru');
        $posOlderUser = strpos($content, 'User Lebih Lama');
        $this->assertNotFalse($posNewerUser);
        $this->assertNotFalse($posOlderUser);
        $this->assertLessThan($posOlderUser, $posNewerUser, 'User dengan created_at lebih baru harus muncul sebelum user yang lebih lama.');
    }

    /**
     * H. Non-platform-admin (owner dan regular user) tetap ditolak dengan status 403.
     */
    public function test_non_platform_admins_are_forbidden_regression(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $regularUser = User::factory()->create();

        $this->actingAs($owner)->get('/platform')->assertForbidden();
        $this->actingAs($regularUser)->get('/platform')->assertForbidden();
    }

    /**
     * I. Platform Admin tidak membutuhkan active business session atau relasi business_user.
     */
    public function test_platform_admin_accesses_overview_without_business_context(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->assertSame(0, $platformAdmin->businesses()->count());

        $response = $this->actingAs($platformAdmin)->get('/platform');

        $response->assertOk();
        $response->assertSessionMissing('dashboard.current_business_id');
    }
}
