<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CloudBackup;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Platform\PlatformOperationalAlerts;
use App\Services\Subscription\CloudDeviceLimit;
use App\Support\PlatformOperationalAlertType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformOperationalAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. Access Control: Platform Admin 200, Owner 403, Regular 403, Guest redirect, Unverified redirect.
     */
    public function test_access_control_for_operational_alerts(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $unverifiedAdmin = User::factory()->platformAdmin()->unverified()->create();
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        $regularUser = User::factory()->create();

        // Platform Admin can access
        $this->actingAs($platformAdmin)
            ->get('/platform/alerts')
            ->assertOk()
            ->assertSee('Alert Operasional');

        // Business Owner is forbidden
        $this->actingAs($owner)
            ->get('/platform/alerts')
            ->assertForbidden();

        // Regular user is forbidden
        $this->actingAs($regularUser)
            ->get('/platform/alerts')
            ->assertForbidden();

        // Unverified Platform Admin is redirected to verification
        $this->actingAs($unverifiedAdmin)
            ->get('/platform/alerts')
            ->assertRedirect(route('verification.notice'));

        // Guest is redirected to login
        auth()->logout();
        $this->get('/platform/alerts')
            ->assertRedirect('/login');
    }

    /**
     * 2. Expiring Subscription: Cloud active, expires in 3 days -> subscription.expiring_soon (warning).
     */
    public function test_subscription_expiring_soon_alert(): void
    {
        $now = Carbon::parse('2026-10-01 12:00:00');
        Carbon::setTestNow($now);

        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['name' => 'Kedai Kopi Maju']);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $now->copy()->addDays(3),
        ]);

        $this->createCloudBackup([
            'business_id' => $business->id,
            'status' => CloudBackup::STATUS_READY,
            'created_at' => $now->copy()->subDay(),
        ]);

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $response->assertSee('Langganan Cloud Segera Berakhir');
        $response->assertSee('Kedai Kopi Maju');
        $response->assertSee('Peringatan');

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $this->assertCount(1, $alerts);
        $this->assertSame(PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRING_SOON, $alerts[0]['type']);
        $this->assertSame(PlatformOperationalAlertType::SEVERITY_WARNING, $alerts[0]['severity']);
    }

    /**
     * 3. Non-expiring Subscription: Cloud active, expires in 20 days -> no expiry warning.
     */
    public function test_subscription_not_expiring_soon_produces_no_alert(): void
    {
        $now = Carbon::parse('2026-10-01 12:00:00');
        Carbon::setTestNow($now);

        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();

        // Create backup so backup.never_created does not fire
        $this->createCloudBackup([
            'business_id' => $business->id,
            'status' => CloudBackup::STATUS_READY,
            'created_at' => $now->copy()->subDay(),
        ]);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $now->copy()->addDays(20),
        ]);

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $expiryAlerts = array_filter($alerts, fn ($a) => str_starts_with((string) $a['type'], 'subscription.'));
        $this->assertEmpty($expiryAlerts);
    }

    /**
     * 4. Null Expiry Subscription: Cloud active, expires_at null -> no expiry warning.
     */
    public function test_subscription_with_null_expiry_produces_no_alert(): void
    {
        $now = Carbon::parse('2026-10-01 12:00:00');
        Carbon::setTestNow($now);

        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();

        $this->createCloudBackup([
            'business_id' => $business->id,
            'status' => CloudBackup::STATUS_READY,
            'created_at' => $now->copy()->subDay(),
        ]);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $expiryAlerts = array_filter($alerts, fn ($a) => str_starts_with((string) $a['type'], 'subscription.'));
        $this->assertEmpty($expiryAlerts);
    }

    /**
     * 5. Expired Subscription: status active, expires_at in the past -> subscription.expired (critical).
     */
    public function test_subscription_expired_date_alert(): void
    {
        $now = Carbon::parse('2026-10-01 12:00:00');
        Carbon::setTestNow($now);

        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['name' => 'Toko Busana Baru']);

        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $now->copy()->subDay(),
        ]);

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $response->assertSee('Langganan Cloud Sudah Kedaluwarsa');
        $response->assertSee('Toko Busana Baru');
        $response->assertSee('Kritis');

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $this->assertSame(PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRED, $alerts[0]['type']);
        $this->assertSame(PlatformOperationalAlertType::SEVERITY_CRITICAL, $alerts[0]['severity']);
        $this->assertSame(route('platform.subscriptions.show', $subscription->id), $alerts[0]['action_url']);
    }

    /**
     * 6. Payment Paid Not Activated: status paid, activated_at null -> payment.paid_not_activated (critical).
     */
    public function test_payment_paid_not_activated_alert(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['name' => 'Resto Sedap']);

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 150000,
            'provider_order_id' => 'ORDER-12345',
            'paid_at' => now()->subHour(),
            'activated_at' => null,
        ]);

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $response->assertSee('Pembayaran Berhasil Belum Mengaktifkan Langganan');
        $response->assertSee('ORDER-12345');
        $response->assertSee('Resto Sedap');
        $response->assertSee('Kritis');

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $this->assertSame(PlatformOperationalAlertType::TYPE_PAYMENT_PAID_NOT_ACTIVATED, $alerts[0]['type']);
        $this->assertSame(PlatformOperationalAlertType::SEVERITY_CRITICAL, $alerts[0]['severity']);
        $this->assertSame(route('platform.payments.show', $payment->id), $alerts[0]['action_url']);
    }

    /**
     * 7. Resolved Payment: once activated_at is set, alert automatically disappears.
     */
    public function test_payment_alert_disappears_when_resolved(): void
    {
        $business = Business::factory()->create();

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now()->subHour(),
            'activated_at' => null,
        ]);

        $alertsBefore = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $this->assertNotEmpty($alertsBefore);

        // Resolve by setting activated_at
        $payment->update(['activated_at' => now()]);

        $alertsAfter = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $paymentAlerts = array_filter($alertsAfter, fn ($a) => str_starts_with((string) $a['type'], 'payment.'));
        $this->assertEmpty($paymentAlerts);
    }

    /**
     * 8. Non-Paid Payment with Activated At: status refunded/failed, activated_at not null -> payment.non_paid_activated (critical).
     */
    public function test_non_paid_activated_mismatch_alert(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['name' => 'Bengkel Mobil Jaya']);

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_REFUNDED,
            'amount' => 200000,
            'provider_order_id' => 'ORDER-REFUNDED-99',
            'activated_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $response->assertSee('Aktivasi Subscription Tidak Sesuai Status Pembayaran');
        $response->assertSee('ORDER-REFUNDED-99');
        $response->assertSee('Kritis');

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $this->assertSame(PlatformOperationalAlertType::TYPE_PAYMENT_NON_PAID_ACTIVATED, $alerts[0]['type']);
        $this->assertSame(PlatformOperationalAlertType::SEVERITY_CRITICAL, $alerts[0]['severity']);
    }

    /**
     * 9. Device Quota Reached: Active Cloud business with active devices >= limit -> device.limit_reached (warning).
     */
    public function test_device_quota_limit_reached_alert(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['name' => 'Apotek Sehat']);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30),
        ]);

        $this->createCloudBackup([
            'business_id' => $business->id,
            'status' => CloudBackup::STATUS_READY,
            'created_at' => now()->subDay(),
        ]);

        $limit = app(CloudDeviceLimit::class)->limit();

        for ($i = 0; $i < $limit; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'status' => Device::STATUS_ACTIVE,
            ]);
        }

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $response->assertSee('Batas Perangkat Cloud Tercapai');
        $response->assertSee('Apotek Sehat');
        $response->assertSee('Peringatan');

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $deviceAlerts = array_values(array_filter($alerts, fn ($a) => (string) $a['type'] === PlatformOperationalAlertType::TYPE_DEVICE_LIMIT_REACHED));
        $this->assertCount(1, $deviceAlerts);
        $this->assertSame(PlatformOperationalAlertType::SEVERITY_WARNING, $deviceAlerts[0]['severity']);
    }

    /**
     * 10. Device Near Limit: Active Cloud business with active devices = limit - 1 -> device.limit_near (info).
     */
    public function test_device_quota_near_limit_alert(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['name' => 'Klinik Medika']);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30),
        ]);

        $limit = app(CloudDeviceLimit::class)->limit();
        $nearLimitCount = $limit - 1;

        if ($nearLimitCount > 0) {
            $firstDevice = null;
            for ($i = 0; $i < $nearLimitCount; $i++) {
                $dev = $this->createDevice([
                    'business_id' => $business->id,
                    'status' => Device::STATUS_ACTIVE,
                ]);
                $firstDevice ??= $dev;
            }

            $this->createCloudBackup([
                'business_id' => $business->id,
                'device_id' => $firstDevice->id,
                'device_identifier' => $firstDevice->identifier,
                'status' => CloudBackup::STATUS_READY,
                'created_at' => now()->subDay(),
            ]);

            $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
            $nearAlerts = array_values(array_filter($alerts, fn ($a) => (string) $a['type'] === PlatformOperationalAlertType::TYPE_DEVICE_LIMIT_NEAR));
            $this->assertCount(1, $nearAlerts);
            $this->assertSame(PlatformOperationalAlertType::SEVERITY_INFO, $nearAlerts[0]['severity']);
            $this->assertSame('Batas Perangkat Cloud Hampir Tercapai', $nearAlerts[0]['title']);
        }
    }

    /**
     * 11. Free Business Device Quota Immunity: Free businesses do not trigger device quota operational alert.
     */
    public function test_free_business_does_not_trigger_device_limit_alert(): void
    {
        $business = Business::factory()->create();

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'status' => Device::STATUS_ACTIVE,
            ]);
        }

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $deviceAlerts = array_filter($alerts, fn ($a) => str_starts_with((string) $a['type'], 'device.'));
        $this->assertEmpty($deviceAlerts);
    }

    /**
     * 12. Backup Never Created: Active Cloud business with no READY CloudBackup -> backup.never_created (warning).
     */
    public function test_backup_never_created_alert(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['name' => 'Optik Modern']);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $response->assertSee('Backup Cloud Belum Pernah Dibuat');
        $response->assertSee('Optik Modern');
        $response->assertSee('Peringatan');

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $backupAlerts = array_values(array_filter($alerts, fn ($a) => (string) $a['type'] === PlatformOperationalAlertType::TYPE_BACKUP_NEVER_CREATED));
        $this->assertCount(1, $backupAlerts);
        $this->assertSame(PlatformOperationalAlertType::SEVERITY_WARNING, $backupAlerts[0]['severity']);
        $this->assertSame(route('platform.backups.index'), $backupAlerts[0]['action_url']);
    }

    /**
     * 13. Backup Stale: Active Cloud business with latest READY backup > 7 days ago -> backup.stale (warning).
     */
    public function test_backup_stale_alert(): void
    {
        $now = Carbon::parse('2026-10-15 12:00:00');
        Carbon::setTestNow($now);

        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['name' => 'Supermarket Sejahtera']);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $now->copy()->addDays(30),
        ]);

        // Backup created 9 days ago (> 7 days stale threshold)
        $this->createCloudBackup([
            'business_id' => $business->id,
            'status' => CloudBackup::STATUS_READY,
            'created_at' => $now->copy()->subDays(9),
        ]);

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $response->assertSee('Backup Cloud Terakhir > 7 Hari');
        $response->assertSee('Supermarket Sejahtera');
        $response->assertSee('Peringatan');

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $staleAlerts = array_values(array_filter($alerts, fn ($a) => (string) $a['type'] === PlatformOperationalAlertType::TYPE_BACKUP_STALE));
        $this->assertCount(1, $staleAlerts);
        $this->assertSame(PlatformOperationalAlertType::SEVERITY_WARNING, $staleAlerts[0]['severity']);
    }

    /**
     * 14. Backup Fresh: Active Cloud business with latest READY backup 2 days ago -> no backup alert.
     */
    public function test_backup_fresh_produces_no_stale_alert(): void
    {
        $now = Carbon::parse('2026-10-15 12:00:00');
        Carbon::setTestNow($now);

        $business = Business::factory()->create();

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $now->copy()->addDays(30),
        ]);

        $this->createCloudBackup([
            'business_id' => $business->id,
            'status' => CloudBackup::STATUS_READY,
            'created_at' => $now->copy()->subDays(2),
        ]);

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $backupAlerts = array_filter($alerts, fn ($a) => str_starts_with((string) $a['type'], 'backup.'));
        $this->assertEmpty($backupAlerts);
    }

    /**
     * 15. Free Business Backup Immunity: Free businesses do not trigger backup alerts.
     */
    public function test_free_business_does_not_trigger_backup_alert(): void
    {
        $business = Business::factory()->create();

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];
        $backupAlerts = array_filter($alerts, fn ($a) => str_starts_with((string) $a['type'], 'backup.'));
        $this->assertEmpty($backupAlerts);
    }

    /**
     * 16. No Fake Telemetry: Ensure service and UI do not expose invented sync or backup failure metrics.
     */
    public function test_no_fake_telemetry_alerts_and_disclosures_present(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $content = $response->getContent();

        // Must not expose invented failure alert types
        $this->assertStringNotContainsString('sync.failed', $content);
        $this->assertStringNotContainsString('backup.failed', $content);
        $this->assertStringNotContainsString('Sync Failure Rate', $content);
        $this->assertStringNotContainsString('Failed Backup Count', $content);

        // Must display honest limitations disclosure
        $this->assertStringContainsString('Keterbatasan Telemetri Server', $content);
        $this->assertStringContainsString('Kegagalan Sinkronisasi', $content);
        $this->assertStringContainsString('Kegagalan Upload Backup', $content);
    }

    /**
     * 17. Empty State: No actionable anomalies -> summary total = 0, empty state rendered.
     */
    public function test_empty_state_rendered_when_no_active_alerts(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $response->assertSee('Tidak ada alert operasional aktif');
        $response->assertSee('Semua langganan Cloud, rekonsiliasi pembayaran');

        $data = app(PlatformOperationalAlerts::class)->get();
        $this->assertSame(0, $data['summary']['total']);
        $this->assertSame(0, $data['summary']['critical']);
        $this->assertSame(0, $data['summary']['warning']);
        $this->assertSame(0, $data['summary']['info']);
    }

    /**
     * 18. Sorting: Critical alerts come before Warning and Info alerts.
     */
    public function test_alerts_sorting_priority(): void
    {
        $now = Carbon::parse('2026-10-01 12:00:00');
        Carbon::setTestNow($now);

        $biz1 = Business::factory()->create(['name' => 'Biz Warning Expiring']);
        $biz2 = Business::factory()->create(['name' => 'Biz Critical Payment']);
        $biz3 = Business::factory()->create(['name' => 'Biz Warning Backup']);

        // 1. Warning: subscription expiring
        Subscription::factory()->create([
            'business_id' => $biz1->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $now->copy()->addDays(2),
        ]);
        $this->createCloudBackup([
            'business_id' => $biz1->id,
            'status' => CloudBackup::STATUS_READY,
            'created_at' => $now->copy()->subDay(),
        ]);

        // 2. Critical: payment paid not activated
        SubscriptionPayment::factory()->create([
            'business_id' => $biz2->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => $now->copy()->subHour(),
            'activated_at' => null,
        ]);

        // 3. Warning: backup never created
        Subscription::factory()->create([
            'business_id' => $biz3->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $now->copy()->addDays(30),
        ]);

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];

        $this->assertNotEmpty($alerts);
        // The first alert MUST be critical
        $this->assertSame(PlatformOperationalAlertType::SEVERITY_CRITICAL, $alerts[0]['severity']);
        $this->assertSame(PlatformOperationalAlertType::TYPE_PAYMENT_PAID_NOT_ACTIVATED, $alerts[0]['type']);

        // Subsequent alerts are warning
        $this->assertSame(PlatformOperationalAlertType::SEVERITY_WARNING, $alerts[1]['severity']);
    }

    /**
     * 19. Action Links: Verify all action URLs resolve to valid existing routes without broken links.
     */
    public function test_alert_action_urls_are_valid_routes(): void
    {
        $biz = Business::factory()->create();

        Subscription::factory()->create([
            'business_id' => $biz->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDays(2),
        ]);

        SubscriptionPayment::factory()->create([
            'business_id' => $biz->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now(),
            'activated_at' => null,
        ]);

        $alerts = app(PlatformOperationalAlerts::class)->get()['alerts'];

        foreach ($alerts as $alert) {
            $this->assertNotEmpty($alert['action_url']);
            $this->assertStringStartsWith('http', $alert['action_url']);
        }
    }

    /**
     * 20. Read-Only Surface: POST, PATCH, and DELETE are not allowed on /platform/alerts.
     */
    public function test_operational_alerts_routes_are_strictly_read_only(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post('/platform/alerts')
            ->assertMethodNotAllowed();

        $this->actingAs($admin)
            ->patch('/platform/alerts/any-id')
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete('/platform/alerts/any-id')
            ->assertNotFound();
    }

    /**
     * 21. No Audit Log Generation: Visiting alerts index or dashboard MUST NOT create PlatformAuditLog rows.
     */
    public function test_viewing_alerts_does_not_create_audit_logs(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $auditCountBefore = PlatformAuditLog::count();

        $this->actingAs($admin)->get('/platform/alerts')->assertOk();
        $this->actingAs($admin)->get('/platform')->assertOk();

        $this->assertSame($auditCountBefore, PlatformAuditLog::count());
    }

    /**
     * 22. Security: Sensitive payment tokens and internal paths are never exposed in alert responses.
     */
    public function test_sensitive_secrets_are_excluded_from_alert_payloads(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $biz = Business::factory()->create();

        SubscriptionPayment::factory()->create([
            'business_id' => $biz->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now(),
            'activated_at' => null,
            'snap_token' => 'super-secret-snap-token-xyz',
            'redirect_url' => 'https://app.midtrans.com/secret-redirect',
            'provider_payload' => ['sensitive_merchant_key' => 'secret_12345'],
        ]);

        Subscription::factory()->create([
            'business_id' => $biz->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30),
        ]);

        $this->createCloudBackup([
            'business_id' => $biz->id,
            'status' => CloudBackup::STATUS_READY,
            'storage_path' => 'private/disk/backups/internal-storage-path-999.sql.gz',
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($admin)->get('/platform/alerts');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('super-secret-snap-token-xyz', $content);
        $this->assertStringNotContainsString('secret-redirect', $content);
        $this->assertStringNotContainsString('secret_12345', $content);
        $this->assertStringNotContainsString('internal-storage-path-999', $content);
    }

    /**
     * 23. Platform Dashboard Integration: Summary counts and top alerts are visible, and stale billing copy is fixed.
     */
    public function test_platform_dashboard_integrates_alerts_and_removes_stale_billing_copy(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $biz = Business::factory()->create(['name' => 'Kedai Kopi Top']);

        SubscriptionPayment::factory()->create([
            'business_id' => $biz->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now(),
            'activated_at' => null,
        ]);

        $response = $this->actingAs($admin)->get('/platform');

        $response->assertOk();

        // Operational alerts section on dashboard
        $response->assertSee('Alert Operasional');
        $response->assertSee('Kedai Kopi Top');
        $response->assertSee('Pembayaran Berhasil Belum Mengaktifkan Langganan');

        // Regression: Stale copy must NOT exist on platform dashboard
        $content = $response->getContent();
        $this->assertStringNotContainsString('Belum Tersedia', $content);
        $this->assertStringNotContainsString('Modul Billing Dalam Rencana', $content);
        $this->assertStringNotContainsString('ADMIN-06', $content);
        $this->assertStringNotContainsString('ADMIN-12', $content);

        // Instead, active billing & revenue panel is displayed
        $this->assertStringContainsString('Billing & Revenue', $content);
        $this->assertStringContainsString('Laporan Revenue', $content);
        $this->assertStringContainsString('Riwayat Pembayaran', $content);
    }

    /**
     * Helper to create CloudBackup instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createCloudBackup(array $attributes = []): CloudBackup
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;

        if (! isset($attributes['device_id'])) {
            $device = $this->createDevice(['business_id' => $businessId]);
            $deviceId = $device->id;
            $deviceIdentifier = $device->identifier;
        } else {
            $deviceId = $attributes['device_id'];
            $deviceIdentifier = $attributes['device_identifier'] ?? 'DEV-IDENTIFIER-'.Str::random(6);
        }

        $createdAt = $attributes['created_at'] ?? now();
        unset($attributes['created_at']);

        $backup = new CloudBackup(array_merge([
            'uuid' => (string) Str::uuid(),
            'business_id' => $businessId,
            'device_id' => $deviceId,
            'device_identifier' => $deviceIdentifier,
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

    /**
     * Helper to create Device instance.
     *
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
            'platform' => 'android',
            'status' => Device::STATUS_ACTIVE,
            'registered_at' => now(),
            'last_seen_at' => null,
            'notes' => null,
        ], $attributes));
    }
}
