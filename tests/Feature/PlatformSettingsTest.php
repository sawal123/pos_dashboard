<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CloudBackup;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use App\Services\Platform\PlatformOperationalAlerts;
use App\Services\Platform\PlatformSettings;
use App\Services\Subscription\CloudDeviceLimit;
use App\Services\Subscription\PremiumPolicy;
use App\Support\PlatformAuditAction;
use App\Support\PlatformOperationalAlertType;
use App\Support\PlatformSettingDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure canonical cloud plan exists
        SubscriptionPlan::query()->firstOrCreate(
            ['code' => 'cloud'],
            [
                'name' => 'Nexa Cloud',
                'description' => 'Akses multi-device & cloud sync real-time',
                'is_active' => true,
            ]
        );
    }

    /**
     * 1. Access tests: Platform Admin can access, others denied.
     */
    public function test_platform_admin_can_access_settings_page(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('platform.settings.index'))
            ->assertOk()
            ->assertSee('Pengaturan Platform')
            ->assertSee('Batas Perangkat Cloud')
            ->assertSee('Ambang Peringatan Kedaluwarsa Langganan')
            ->assertSee('Web Dashboard (Cloud)')
            ->assertSee('Harga Langganan Cloud (ADMIN-06)')
            ->assertSee('Kredensial &amp; Secrets Deployment', false);
    }

    public function test_business_owner_is_forbidden_from_platform_settings(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('platform.settings.index'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('platform.settings.update', 'device-limit'), ['value' => 3])
            ->assertForbidden();
    }

    public function test_regular_user_is_forbidden_from_platform_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('platform.settings.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('platform.settings.update', 'device-limit'), ['value' => 3])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('platform.settings.index'))
            ->assertRedirect(route('login'));

        $this->patch(route('platform.settings.update', 'device-limit'), ['value' => 3])
            ->assertRedirect(route('login'));
    }

    public function test_unverified_platform_admin_is_redirected_to_verification(): void
    {
        $admin = User::factory()->platformAdmin()->unverified()->create();

        $this->actingAs($admin)
            ->get(route('platform.settings.index'))
            ->assertRedirect(route('verification.notice'));
    }

    /**
     * 2. Fallback semantics when DB rows are absent.
     */
    public function test_fallback_values_when_db_records_are_absent(): void
    {
        /** @var PlatformSettings $settings */
        $settings = app(PlatformSettings::class);

        $this->assertSame(5, $settings->deviceLimit());
        $this->assertSame(7, $settings->subscriptionExpiryDays());
        $this->assertSame(7, $settings->backupStaleDays());
        $this->assertTrue($settings->isFeatureRuntimeEnabled('web_dashboard'));
        $this->assertTrue($settings->isFeatureRuntimeEnabled('cloud_sync'));
        $this->assertTrue($settings->isFeatureRuntimeEnabled('cloud_devices'));
        $this->assertTrue($settings->isFeatureRuntimeEnabled('cloud_backup'));
        $this->assertTrue($settings->isFeatureRuntimeEnabled('cloud_restore'));

        // All manageable list should indicate unpersisted
        $all = $settings->allManageable();
        $this->assertFalse($all[PlatformSettingDefinition::KEY_DEVICE_LIMIT]['is_persisted']);
        $this->assertSame(5, $all[PlatformSettingDefinition::KEY_DEVICE_LIMIT]['effective_value']);
    }

    /**
     * 3. Invalid or corrupt database value fails safely to fallback.
     */
    public function test_corrupt_database_values_fall_back_safely(): void
    {
        PlatformSetting::query()->create([
            'key' => PlatformSettingDefinition::KEY_DEVICE_LIMIT,
            'value' => 'corrupt-string-value',
        ]);

        PlatformSetting::query()->create([
            'key' => PlatformSettingDefinition::KEY_ALERT_EXPIRY_DAYS,
            'value' => -99, // Out of min bounds
        ]);

        /** @var PlatformSettings $settings */
        $settings = app(PlatformSettings::class);

        $this->assertSame(5, $settings->deviceLimit());
        $this->assertSame(7, $settings->subscriptionExpiryDays());
    }

    /**
     * 4. Successful update creates DB record and exactly 1 audit log with before/after state.
     */
    public function test_admin_can_update_device_limit_and_persists_in_db_with_audit_log(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'device-limit'), [
                'value' => 4,
            ]);

        $response->assertRedirect(route('platform.settings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('platform_settings', [
            'key' => PlatformSettingDefinition::KEY_DEVICE_LIMIT,
            'value' => 4,
            'updated_by' => $admin->id,
        ]);

        /** @var PlatformSettings $settings */
        $settings = app(PlatformSettings::class);
        $this->assertSame(4, $settings->deviceLimit());

        // Assert exactly one audit log created
        $this->assertDatabaseCount('platform_audit_logs', 1);

        $log = PlatformAuditLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame(PlatformAuditAction::PLATFORM_SETTING_UPDATED, $log->action);
        $this->assertSame(PlatformAuditAction::TARGET_PLATFORM_SETTING, $log->target_type);
        $this->assertSame(PlatformSettingDefinition::KEY_DEVICE_LIMIT, $log->target_id);
        $this->assertSame(PlatformSettingDefinition::KEY_DEVICE_LIMIT, $log->target_label);
        $this->assertSame($admin->id, $log->actor_user_id);
        // Fallback value was 5 before this first mutation
        $this->assertSame(['value' => 5], $log->before_state);
        $this->assertSame(['value' => 4], $log->after_state);
        $this->assertSame(['type' => 'integer', 'group' => 'cloud_devices'], $log->metadata);
    }

    /**
     * 5. No-op update generates 0 audit logs and does not change database state.
     */
    public function test_no_op_update_creates_no_audit_log(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // 1st update from 5 to 4
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'device-limit'), ['value' => 4])
            ->assertRedirect();

        $this->assertSame(1, PlatformAuditLog::query()->count());

        // 2nd update with same value 4 (No-op)
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'device-limit'), ['value' => 4])
            ->assertRedirect();

        // Still exactly 1 audit record
        $this->assertSame(1, PlatformAuditLog::query()->count());
    }

    /**
     * 6. Validation tests: min/max range constraints.
     */
    public function test_device_limit_validation_rejects_out_of_range_values(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // Minimum is 1, 0 must be rejected
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'device-limit'), ['value' => 0])
            ->assertSessionHasErrors(['value']);

        // Maximum is 50, 51 must be rejected
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'device-limit'), ['value' => 51])
            ->assertSessionHasErrors(['value']);

        // String non-numeric must be rejected
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'device-limit'), ['value' => 'abc'])
            ->assertSessionHasErrors(['value']);

        $this->assertDatabaseCount('platform_settings', 0);
        $this->assertDatabaseCount('platform_audit_logs', 0);
    }

    public function test_alert_thresholds_validation_rejects_out_of_range_values(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // Expiry days range 1..30
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'subscription-expiry-days'), ['value' => 0])
            ->assertSessionHasErrors(['value']);

        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'subscription-expiry-days'), ['value' => 31])
            ->assertSessionHasErrors(['value']);

        // Backup stale days range 1..90
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'backup-stale-days'), ['value' => 0])
            ->assertSessionHasErrors(['value']);

        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'backup-stale-days'), ['value' => 91])
            ->assertSessionHasErrors(['value']);

        $this->assertDatabaseCount('platform_settings', 0);
        $this->assertDatabaseCount('platform_audit_logs', 0);
    }

    /**
     * 7. Secret key and unwhitelisted key rejection defense.
     */
    public function test_secret_and_forbidden_keys_are_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // Attempting to patch secret key via URL
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'midtrans.server_key'), ['value' => 'hack'])
            ->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'app.key'), ['value' => 'hack'])
            ->assertNotFound();

        // Direct service calls must throw InvalidArgumentException
        /** @var PlatformSettings $settings */
        $settings = app(PlatformSettings::class);

        $this->expectException(InvalidArgumentException::class);
        $settings->update('midtrans.server_key', 'evil', $admin);
    }

    /**
     * 8. Audit failure triggers atomic rollback of setting update.
     */
    public function test_audit_failure_rolls_back_setting_mutation(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $mockLogger = Mockery::mock(PlatformAuditLogger::class);
        $mockLogger->shouldReceive('record')
            ->andThrow(new RuntimeException('Audit logger failed intentionally.'));

        $service = new PlatformSettings($mockLogger);

        try {
            $service->update(PlatformSettingDefinition::KEY_DEVICE_LIMIT, 3, $admin);
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit logger failed intentionally.', $e->getMessage());
        }

        // Setting should NOT be saved in DB
        $this->assertDatabaseMissing('platform_settings', [
            'key' => PlatformSettingDefinition::KEY_DEVICE_LIMIT,
        ]);
    }

    /**
     * 9. Device limit lowering test:
     * When limit lowered from 5 to 3 for business with 5 active devices:
     * - Existing active devices remain active (no auto-deactivation).
     * - CloudDeviceLimit::limit() evaluates to 3.
     * - CloudDeviceLimit::isReached() evaluates to true.
     * - New device registration via API is denied (403).
     */
    public function test_lowering_device_limit_does_not_deactivate_existing_devices_and_blocks_new(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->active()->create();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => Carbon::now()->addMonth(),
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Create 5 active devices
        for ($i = 1; $i <= 5; $i++) {
            $this->createDevice($business, $outlet, "DEV-ACTIVE-0{$i}");
        }

        $this->assertSame(5, app(CloudDeviceLimit::class)->activeCount($business));

        // Admin lowers limit to 3
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'device-limit'), ['value' => 3])
            ->assertRedirect();

        // 1. None of the existing devices were deactivated
        $this->assertSame(5, Device::query()->where('business_id', $business->id)->where('status', Device::STATUS_ACTIVE)->count());

        // 2. CloudDeviceLimit reflects new runtime limit 3
        /** @var CloudDeviceLimit $deviceLimit */
        $deviceLimit = app(CloudDeviceLimit::class);
        $this->assertSame(3, $deviceLimit->limit());
        $this->assertTrue($deviceLimit->isReached($business));
        $this->assertSame(0, $deviceLimit->remaining($business));

        // 3. New device registration via API is rejected with limit 3
        auth()->logout();
        $token = $owner->createToken('mobile-api', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'DEV-NEW-REJECTED',
                'name' => '6th Tablet',
                'platform' => 'android',
            ])
            ->assertStatus(403)
            ->assertJson([
                'code' => 'CLOUD_DEVICE_LIMIT_REACHED',
                'device_limit' => 3,
            ]);
    }

    /**
     * 10. Device limit increase expands remaining slots.
     */
    public function test_increasing_device_limit_expands_remaining_slots(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => Carbon::now()->addMonth(),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->createDevice($business, $outlet, "DEV-SLOT-0{$i}");
        }

        // Increase limit to 7
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'device-limit'), ['value' => 7])
            ->assertRedirect();

        /** @var CloudDeviceLimit $deviceLimit */
        $deviceLimit = app(CloudDeviceLimit::class);
        $this->assertSame(7, $deviceLimit->limit());
        $this->assertFalse($deviceLimit->isReached($business));
        $this->assertSame(2, $deviceLimit->remaining($business));
    }

    /**
     * 11. Feature kill-switches: Disabling feature flag denies capability without deleting data.
     */
    public function test_feature_kill_switch_disables_capability_without_deleting_data(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => Carbon::now()->addMonth(),
        ]);

        $backup = $this->createCloudBackup([
            'business_id' => $business->id,
            'status' => CloudBackup::STATUS_READY,
        ]);

        /** @var PremiumPolicy $policy */
        $policy = app(PremiumPolicy::class);
        $this->assertTrue($policy->isCapabilityAvailable('cloud_sync'));
        $this->assertTrue($policy->allows($business, 'cloud_sync'));

        // Admin disables cloud_sync via kill switch
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'feature-cloud-sync'), ['value' => 0])
            ->assertRedirect();

        $this->assertFalse($policy->isCapabilityAvailable('cloud_sync'));
        $this->assertFalse($policy->allows($business, 'cloud_sync'));

        // Existing data remains untouched
        $this->assertDatabaseHas('cloud_backups', ['id' => $backup->id]);

        // Re-enable returns to true
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'feature-cloud-sync'), ['value' => 1])
            ->assertRedirect();

        $this->assertTrue($policy->isCapabilityAvailable('cloud_sync'));
        $this->assertTrue($policy->allows($business, 'cloud_sync'));
    }

    /**
     * 12. Backend readiness is a hard ceiling:
     * When backend config is false, DB runtime setting cannot enable it (fail-closed).
     */
    public function test_backend_config_false_is_an_unbreakable_hard_ceiling(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => Carbon::now()->addMonth(),
        ]);

        // Backend readiness set to false
        config()->set('premium.capability_availability.cloud_backup', false);

        // Explicitly set runtime setting to true in DB
        PlatformSetting::query()->create([
            'key' => PlatformSettingDefinition::KEY_FEATURE_CLOUD_BACKUP,
            'value' => true,
        ]);

        /** @var PremiumPolicy $policy */
        $policy = app(PremiumPolicy::class);

        // Effective status must still be FALSE because backend readiness is false
        $this->assertFalse($policy->isCapabilityAvailable('cloud_backup'));
        $this->assertFalse($policy->allows($business, 'cloud_backup'));
    }

    /**
     * 13. Dynamic alert thresholds and labels in PlatformOperationalAlerts.
     */
    public function test_dynamic_operational_alerts_thresholds_and_labels(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create(['name' => 'Kedai Kopi Dinamis']);
        $now = Carbon::parse('2026-10-05 12:00:00');
        Carbon::setTestNow($now);

        // 1. Subscription expiring in 5 days
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $now->copy()->addDays(5),
        ]);

        // 2. Backup created 10 days ago
        $this->createCloudBackup([
            'business_id' => $business->id,
            'status' => CloudBackup::STATUS_READY,
            'created_at' => $now->copy()->subDays(10),
        ]);

        /** @var PlatformOperationalAlerts $alertsService */
        $alertsService = app(PlatformOperationalAlerts::class);

        // Baseline (defaults = 7 days):
        // - Sub expiring in 5 days is within 7-day window -> Alert present
        // - Backup 10 days old is older than 7 days -> Alert present
        $initialData = $alertsService->get();
        $initialTypes = array_column($initialData['alerts'], 'type');
        $this->assertContains(PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRING_SOON, $initialTypes);
        $this->assertContains(PlatformOperationalAlertType::TYPE_BACKUP_STALE, $initialTypes);

        // Admin updates expiry threshold to 3 days (5 days is NOT within 3 days)
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'subscription-expiry-days'), ['value' => 3])
            ->assertRedirect();

        // Admin updates backup stale threshold to 14 days (10 days is NOT stale)
        $this->actingAs($admin)
            ->patch(route('platform.settings.update', 'backup-stale-days'), ['value' => 14])
            ->assertRedirect();

        $updatedData = $alertsService->get();
        $updatedTypes = array_column($updatedData['alerts'], 'type');

        // Expiry in 5 days is not triggered under 3-day window
        $this->assertNotContains(PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRING_SOON, $updatedTypes);

        // Backup 10 days old is not triggered under 14-day threshold
        $this->assertNotContains(PlatformOperationalAlertType::TYPE_BACKUP_STALE, $updatedTypes);

        // Now test dynamic label with a backup that IS older than 14 days (16 days old)
        CloudBackup::query()->delete();
        $this->createCloudBackup([
            'business_id' => $business->id,
            'status' => CloudBackup::STATUS_READY,
            'created_at' => $now->copy()->subDays(16),
        ]);

        $staleData = $alertsService->get();
        $staleAlert = null;
        foreach ($staleData['alerts'] as $alert) {
            if ($alert['type'] === PlatformOperationalAlertType::TYPE_BACKUP_STALE) {
                $staleAlert = $alert;
                break;
            }
        }

        $this->assertNotNull($staleAlert);
        $this->assertSame('Backup Cloud Terakhir > 14 Hari', $staleAlert['title']);
        $this->assertStringContainsString('lebih dari 14 hari', $staleAlert['description']);

        // Check definitions dynamically show 14 hari
        $definitions = $alertsService->definitions();
        $staleDef = null;
        foreach ($definitions as $def) {
            if ($def['type'] === PlatformOperationalAlertType::TYPE_BACKUP_STALE) {
                $staleDef = $def;
                break;
            }
        }
        $this->assertNotNull($staleDef);
        $this->assertSame('Backup Cloud Terakhir > 14 Hari', $staleDef['title']);
        $this->assertStringContainsString('14 hari', $staleDef['condition']);

        Carbon::setTestNow();
    }

    /**
     * Helper to create Device instance.
     */
    private function createDevice(Business $business, Outlet $outlet, string $deviceId): Device
    {
        return Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device '.$deviceId,
            'identifier' => 'dev-'.$deviceId,
            'platform' => 'android',
            'status' => Device::STATUS_ACTIVE,
            'registered_at' => now(),
        ]);
    }

    /**
     * Helper to create CloudBackup instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createCloudBackup(array $attributes = []): CloudBackup
    {
        $createdAt = $attributes['created_at'] ?? now();
        $businessId = $attributes['business_id'];

        $deviceId = $attributes['device_id'] ?? null;
        if (! $deviceId) {
            $device = Device::query()->where('business_id', $businessId)->first();
            if (! $device) {
                $outlet = Outlet::factory()->create(['business_id' => $businessId]);
                $device = $this->createDevice(Business::findOrFail($businessId), $outlet, 'BCK-DEV');
            }
            $deviceId = $device->id;
        }

        $backup = new CloudBackup(array_merge([
            'uuid' => (string) Str::uuid(),
            'business_id' => $businessId,
            'device_id' => $deviceId,
            'device_identifier' => 'device-test',
            'schema_version' => 1,
            'app_version' => '1.0.0',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'dummy'),
            'storage_disk' => 'local',
            'storage_path' => 'backups/'.Str::uuid().'.sql.gz',
            'status' => CloudBackup::STATUS_READY,
        ], $attributes));

        $backup->timestamps = false;
        $backup->created_at = $createdAt;
        $backup->updated_at = $createdAt;
        $backup->save();

        return $backup;
    }
}
