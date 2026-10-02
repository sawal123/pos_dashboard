<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Subscription;
use App\Models\SyncCounter;
use App\Models\SyncRequest;
use App\Models\User;
use App\Services\Platform\PlatformCloudBackupMonitoringData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformCloudBackupMonitoringTest extends TestCase
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

    public function test_platform_admin_can_access_backup_monitoring_page(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/backups');

        $response->assertOk();
        $response->assertSee('Monitoring Backup Cloud');
        $response->assertSee('Status Backend:');
        $response->assertSee('Belum Tersedia');
    }

    public function test_business_owner_is_forbidden_from_backup_monitoring(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)->get('/platform/backups')->assertForbidden();
    }

    public function test_regular_user_is_forbidden_from_backup_monitoring(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/platform/backups')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_backup_monitoring(): void
    {
        $this->get('/platform/backups')->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification(): void
    {
        $unverified = User::factory()->unverified()->create(['is_platform_admin' => true]);

        $this->actingAs($unverified)->get('/platform/backups')->assertRedirect(route('verification.notice'));
    }

    // ============================================================
    // 2. Capability Declaration & Backend Status Tests
    // ============================================================

    public function test_page_displays_declared_and_availability_readiness_status(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/backups');

        $response->assertOk();
        $response->assertSee('Cloud Backup');
        $response->assertSee('cloud_backup');
        $response->assertSee('Cloud Restore');
        $response->assertSee('cloud_restore');
        $response->assertSee('Terdaftar (Declared)');

        // Requirements: Declared: YA and Available: TIDAK
        $response->assertSee('Declared:');
        $response->assertSee('Available:');
        $response->assertSee('YA');
        $response->assertSee('TIDAK');

        // Test service/policy canonical values directly
        $monitoringData = app(PlatformCloudBackupMonitoringData::class)->get();
        $this->assertTrue($monitoringData['capabilities']['cloud_backup']['declared']);
        $this->assertFalse($monitoringData['capabilities']['cloud_backup']['backend_available']);
        $this->assertTrue($monitoringData['capabilities']['cloud_restore']['declared']);
        $this->assertFalse($monitoringData['capabilities']['cloud_restore']['backend_available']);
    }

    public function test_page_explicitly_discloses_backend_is_not_implemented(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/backups');

        $response->assertOk();
        $response->assertSee('Status Backend:');
        $response->assertSee('Belum Tersedia');
        $response->assertSee('Belum Diimplementasikan');
        $response->assertSee('backend engine belum diimplementasikan');
        $response->assertSee('Tidak ada tabel database penyimpanan');
        $response->assertSee('Belum ada integrasi object storage');
    }

    // ============================================================
    // 3. No Fake Telemetry Tests
    // ============================================================

    public function test_page_does_not_display_fake_telemetry_metrics(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/backups');

        $response->assertOk();
        // Zero or fake numbers must not be displayed as telemetry
        $response->assertDontSee('Total Backup: 0');
        $response->assertDontSee('Total Backup:');
        $response->assertDontSee('Backup Berhasil: 0');
        $response->assertDontSee('Backup Gagal: 0');
        $response->assertDontSee('Storage: 0 MB');
        $response->assertDontSee('0 MB');
        $response->assertDontSee('Restore: 0');
        $response->assertDontSee('Last Backup: -');

        // Explicit unavailable status is shown instead
        $response->assertSee('Tidak Tersedia');
    }

    public function test_page_displays_domain_boundaries_disclaimers(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/backups');

        $response->assertOk();
        $response->assertSee('Sync Bukan Backup');
        $response->assertSee('Backup Lokal POS Mobile Bukan Cloud');
        $response->assertSee('Bebas Surrogate Metric');
    }

    public function test_page_displays_softened_future_observability_requirements(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/backups');

        $response->assertOk();
        $response->assertSee('Kebutuhan Observabilitas Saat Backend Dibangun');
        $response->assertSee('backup identifier');
        $response->assertSee('business reference');
        $response->assertSee('source device reference');
        $response->assertSee('execution status');
        $response->assertSee('started timestamp');
        $response->assertSee('completed timestamp');
        $response->assertSee('size metadata');
        $response->assertSee('storage reference (jika applicable)');
        $response->assertSee('integrity metadata (jika applicable)');
        $response->assertSee('restore event reference');

        // Ensure rigid premature backend assumptions are removed
        $response->assertDontSee('SHA-256');
        $response->assertDontSee('terkompresi');
    }

    // ============================================================
    // 4. Read-Only & No Mutation Routes Tests
    // ============================================================

    public function test_no_mutation_routes_exist_for_platform_backups(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        // Mutating endpoints do not exist
        $this->actingAs($platformAdmin)->post('/platform/backups')->assertStatus(405);
        $this->actingAs($platformAdmin)->patch('/platform/backups/1')->assertStatus(404);
        $this->actingAs($platformAdmin)->delete('/platform/backups/1')->assertStatus(404);
    }

    public function test_read_only_guarantee_does_not_mutate_database(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $sub = Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $initialBusinessCount = Business::count();
        $initialSubCount = Subscription::count();
        $initialDeviceCount = Device::count();
        $initialSyncReqCount = SyncRequest::count();
        $initialCounterSeq = SyncCounter::where('business_id', $business->id)->value('current_sequence');

        $response = $this->actingAs($platformAdmin)->get('/platform/backups');
        $response->assertOk();

        $this->assertSame($initialBusinessCount, Business::count());
        $this->assertSame($initialSubCount, Subscription::count());
        $this->assertSame($initialDeviceCount, Device::count());
        $this->assertSame($initialSyncReqCount, SyncRequest::count());
        $this->assertSame($initialCounterSeq, SyncCounter::where('business_id', $business->id)->value('current_sequence'));
    }

    // ============================================================
    // 5. Security & Secret Protection Tests
    // ============================================================

    public function test_page_does_not_leak_credentials_or_secret_keys(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/backups');

        $response->assertOk();
        $response->assertDontSee('MIDTRANS_SERVER_KEY');
        $response->assertDontSee('AWS_SECRET_ACCESS_KEY');
        $response->assertDontSee('APP_KEY');
        $response->assertDontSee('DB_PASSWORD');
    }
}
