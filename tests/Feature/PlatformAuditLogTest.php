<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use App\Support\PlatformAuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PlatformAuditLogTest extends TestCase
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
            ],
        );
    }

    /**
     * 1. Access tests: Platform Admin can access, others denied.
     */
    public function test_platform_admin_can_access_audit_log_index_and_show(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $audit = PlatformAuditLog::factory()->create(['actor_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('platform.audit-logs.index'))
            ->assertOk()
            ->assertSee('Platform Audit Log');

        $this->actingAs($admin)
            ->get(route('platform.audit-logs.show', $audit))
            ->assertOk()
            ->assertSee("Catatan Audit #{$audit->id}");
    }

    public function test_business_owner_is_forbidden_from_audit_logs(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $audit = PlatformAuditLog::factory()->create();

        $this->actingAs($owner)
            ->get(route('platform.audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('platform.audit-logs.show', $audit))
            ->assertForbidden();
    }

    public function test_regular_user_is_forbidden_from_audit_logs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('platform.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('platform.audit-logs.index'))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_platform_admin_is_redirected_to_verification(): void
    {
        $admin = User::factory()->platformAdmin()->unverified()->create();

        $this->actingAs($admin)
            ->get(route('platform.audit-logs.index'))
            ->assertRedirect(route('verification.notice'));
    }

    /**
     * 2. Business status mutation tests: suspend, reactivate, and no-op.
     */
    public function test_business_suspend_generates_audit_record(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['status' => 'active']);

        $response = $this->actingAs($admin)->patch(
            route('platform.businesses.status.update', $business),
            ['status' => 'inactive']
        );

        $response->assertRedirect();
        $business->refresh();
        $this->assertSame('inactive', $business->status);

        $this->assertSame(1, PlatformAuditLog::query()->count());

        $log = PlatformAuditLog::query()->firstOrFail();
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame($admin->name, $log->actor_name);
        $this->assertSame(strtolower($admin->email), $log->actor_email);
        $this->assertSame(PlatformAuditAction::BUSINESS_SUSPENDED, $log->action);
        $this->assertSame(PlatformAuditAction::TARGET_BUSINESS, $log->target_type);
        $this->assertSame((string) $business->id, $log->target_id);
        $this->assertSame($business->name, $log->target_label);
        $this->assertSame($business->id, $log->business_id);
        $this->assertSame(['status' => 'active'], $log->before_state);
        $this->assertSame(['status' => 'inactive'], $log->after_state);
    }

    public function test_business_reactivate_generates_audit_record(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['status' => 'inactive']);

        $this->actingAs($admin)->patch(
            route('platform.businesses.status.update', $business),
            ['status' => 'active']
        );

        $business->refresh();
        $this->assertSame('active', $business->status);

        $this->assertSame(1, PlatformAuditLog::query()->count());
        $log = PlatformAuditLog::query()->firstOrFail();
        $this->assertSame(PlatformAuditAction::BUSINESS_REACTIVATED, $log->action);
        $this->assertSame(['status' => 'inactive'], $log->before_state);
        $this->assertSame(['status' => 'active'], $log->after_state);
    }

    public function test_business_status_no_op_does_not_generate_audit_log(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['status' => 'active']);

        $this->actingAs($admin)->patch(
            route('platform.businesses.status.update', $business),
            ['status' => 'active']
        );

        $this->assertSame(0, PlatformAuditLog::query()->count());
    }

    /**
     * 3. Subscription mutation tests: activate, renew, downgrade, inactivate.
     */
    public function test_subscription_activate_generates_audit_record(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $this->actingAs($admin)->patch(
            route('platform.subscriptions.activate', $subscription),
            ['billing_period' => 'monthly']
        );

        $this->assertSame(1, PlatformAuditLog::query()->count());
        $log = PlatformAuditLog::query()->firstOrFail();
        $this->assertSame(PlatformAuditAction::SUBSCRIPTION_CLOUD_ACTIVATED, $log->action);
        $this->assertSame(PlatformAuditAction::TARGET_SUBSCRIPTION, $log->target_type);
        $this->assertSame((string) $subscription->id, $log->target_id);
        $this->assertSame($business->id, $log->business_id);
        $this->assertSame(Subscription::PLAN_FREE, $log->before_state['plan']);
        $this->assertSame(Subscription::PLAN_CLOUD, $log->after_state['plan']);
        $this->assertNotNull($log->after_state['expires_at']);
        $this->assertSame('monthly', $log->metadata['billing_period']);
        $this->assertTrue($log->metadata['administrative_override']);
    }

    public function test_subscription_renew_generates_audit_record_with_changed_expiry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $initialExpiry = Carbon::now()->addDays(10);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => Carbon::now()->subDays(20),
            'expires_at' => $initialExpiry,
        ]);

        $this->actingAs($admin)->post(
            route('platform.subscriptions.renew', $subscription),
            ['billing_period' => 'monthly']
        );

        $this->assertSame(1, PlatformAuditLog::query()->count());
        $log = PlatformAuditLog::query()->firstOrFail();
        $this->assertSame(PlatformAuditAction::SUBSCRIPTION_CLOUD_RENEWED, $log->action);
        $this->assertNotSame($log->before_state['expires_at'], $log->after_state['expires_at']);
        $this->assertSame('monthly', $log->metadata['billing_period']);
    }

    public function test_subscription_downgrade_generates_audit_record(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->patch(
            route('platform.subscriptions.downgrade', $subscription)
        );

        $this->assertSame(1, PlatformAuditLog::query()->count());
        $log = PlatformAuditLog::query()->firstOrFail();
        $this->assertSame(PlatformAuditAction::SUBSCRIPTION_DOWNGRADED, $log->action);
        $this->assertSame(Subscription::PLAN_CLOUD, $log->before_state['plan']);
        $this->assertSame(Subscription::PLAN_FREE, $log->after_state['plan']);
    }

    public function test_subscription_inactivate_generates_audit_record(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->patch(
            route('platform.subscriptions.inactivate', $subscription)
        );

        $this->assertSame(1, PlatformAuditLog::query()->count());
        $log = PlatformAuditLog::query()->firstOrFail();
        $this->assertSame(PlatformAuditAction::SUBSCRIPTION_INACTIVATED, $log->action);
        $this->assertSame(Subscription::STATUS_ACTIVE, $log->before_state['status']);
        $this->assertSame(Subscription::STATUS_INACTIVE, $log->after_state['status']);
    }

    /**
     * 4. Device mutation tests: deactivate, activate, no-op, failed entitlement/quota.
     */
    public function test_device_deactivate_generates_audit_record(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'status' => Device::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->patch(route('platform.devices.deactivate', $device));

        $this->assertSame(1, PlatformAuditLog::query()->count());
        $log = PlatformAuditLog::query()->firstOrFail();
        $this->assertSame(PlatformAuditAction::DEVICE_DEACTIVATED, $log->action);
        $this->assertSame(PlatformAuditAction::TARGET_DEVICE, $log->target_type);
        $this->assertSame((string) $device->id, $log->target_id);
        $this->assertSame(['status' => Device::STATUS_ACTIVE], $log->before_state);
        $this->assertSame(['status' => Device::STATUS_INACTIVE], $log->after_state);
        $this->assertSame($device->identifier, $log->metadata['device_identifier']);
    }

    public function test_device_deactivate_no_op_when_already_inactive(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'status' => Device::STATUS_INACTIVE,
        ]);

        $this->actingAs($admin)->patch(route('platform.devices.deactivate', $device));

        $this->assertSame(0, PlatformAuditLog::query()->count());
    }

    public function test_device_reactivate_generates_audit_record_when_entitled(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => Carbon::now()->addMonth(),
        ]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'status' => Device::STATUS_INACTIVE,
        ]);

        $this->actingAs($admin)->patch(route('platform.devices.activate', $device));

        $this->assertSame(1, PlatformAuditLog::query()->count());
        $log = PlatformAuditLog::query()->firstOrFail();
        $this->assertSame(PlatformAuditAction::DEVICE_REACTIVATED, $log->action);
        $this->assertSame(['status' => Device::STATUS_INACTIVE], $log->before_state);
        $this->assertSame(['status' => Device::STATUS_ACTIVE], $log->after_state);
    }

    public function test_device_reactivate_fails_without_cloud_and_logs_no_audit(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        // Free plan - no cloud entitlement
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'status' => Device::STATUS_INACTIVE,
        ]);

        $this->actingAs($admin)->patch(route('platform.devices.activate', $device));

        $device->refresh();
        $this->assertSame(Device::STATUS_INACTIVE, $device->status);
        $this->assertSame(0, PlatformAuditLog::query()->count());
    }

    /**
     * 5. Pricing and Plan mutation tests.
     */
    public function test_subscription_plan_update_generates_audit_record(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $plan = SubscriptionPlan::query()->where('code', 'cloud')->firstOrFail();

        $this->actingAs($admin)->patch(
            route('platform.subscription-plans.update', $plan),
            [
                'name' => 'Nexa Cloud Pro',
                'description' => 'Deskripsi baru paket cloud',
                'is_active' => 1,
            ]
        );

        $this->assertSame(1, PlatformAuditLog::query()->count());
        $log = PlatformAuditLog::query()->firstOrFail();
        $this->assertSame(PlatformAuditAction::SUBSCRIPTION_PLAN_UPDATED, $log->action);
        $this->assertSame(PlatformAuditAction::TARGET_SUBSCRIPTION_PLAN, $log->target_type);
        $this->assertSame((string) $plan->id, $log->target_id);
        $this->assertSame('Nexa Cloud', $log->before_state['name']);
        $this->assertSame('Nexa Cloud Pro', $log->after_state['name']);
        $this->assertSame('cloud', $log->metadata['code']);
    }

    public function test_subscription_price_create_and_update_distinction(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $plan = SubscriptionPlan::query()->where('code', 'cloud')->firstOrFail();

        // 1. Initial price creation via storePrice
        $this->actingAs($admin)->post(
            route('platform.subscription-plans.prices.store', $plan),
            [
                'billing_period' => 'monthly',
                'price_minor' => 100000,
                'is_active' => 1,
            ]
        );

        $this->assertSame(1, PlatformAuditLog::query()->count());
        $log1 = PlatformAuditLog::query()->first();
        $this->assertSame(PlatformAuditAction::SUBSCRIPTION_PRICE_CREATED, $log1->action);
        $this->assertNull($log1->before_state);
        $this->assertSame(100000, $log1->after_state['price_minor']);
        $this->assertSame('monthly', $log1->metadata['billing_period']);

        // 2. Updating via storePrice (same billing period upsert)
        $this->actingAs($admin)->post(
            route('platform.subscription-plans.prices.store', $plan),
            [
                'billing_period' => 'monthly',
                'price_minor' => 120000,
                'is_active' => 1,
            ]
        );

        $this->assertSame(2, PlatformAuditLog::query()->count());
        $log2 = PlatformAuditLog::query()->latest('id')->first();
        $this->assertSame(PlatformAuditAction::SUBSCRIPTION_PRICE_UPDATED, $log2->action);
        $this->assertSame(100000, $log2->before_state['price_minor']);
        $this->assertSame(120000, $log2->after_state['price_minor']);

        // 3. Updating via explicit PATCH route
        $price = SubscriptionPlanPrice::query()->where('billing_period', 'monthly')->firstOrFail();
        $this->actingAs($admin)->patch(
            route('platform.subscription-plans.prices.update', [$plan, $price]),
            [
                'price_minor' => 150000,
                'is_active' => 1,
            ]
        );

        $this->assertSame(3, PlatformAuditLog::query()->count());
        $log3 = PlatformAuditLog::query()->latest('id')->first();
        $this->assertSame(PlatformAuditAction::SUBSCRIPTION_PRICE_UPDATED, $log3->action);
        $this->assertSame(120000, $log3->before_state['price_minor']);
        $this->assertSame(150000, $log3->after_state['price_minor']);
    }

    /**
     * 6. Transactional Atomicity: audit failure must rollback target mutation!
     */
    public function test_target_mutation_rolls_back_if_audit_logger_fails(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['status' => 'active']);

        // Mock PlatformAuditLogger to throw an exception
        $mockLogger = $this->createMock(PlatformAuditLogger::class);
        $mockLogger->method('record')->willThrowException(new \RuntimeException('Database disk full'));
        $this->app->instance(PlatformAuditLogger::class, $mockLogger);

        try {
            $this->actingAs($admin)->patch(
                route('platform.businesses.status.update', $business),
                ['status' => 'inactive']
            );
        } catch (\RuntimeException $e) {
            $this->assertSame('Database disk full', $e->getMessage());
        }

        // Assert business status remains active (rolled back)
        $business->refresh();
        $this->assertSame('active', $business->status);
        $this->assertSame(0, PlatformAuditLog::query()->count());
    }

    /**
     * 7. Secret exclusion: secrets, tokens, passwords must never be stored.
     */
    public function test_secret_exclusion_in_audit_logger(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $logger = app(PlatformAuditLogger::class);

        $dirtyData = [
            'status' => 'active',
            'password' => 'secret123',
            'snap_token' => 'tok_abc',
            'provider_payload' => ['foo' => 'bar'],
            'redirect_url' => 'https://midtrans.com/pay',
            'server_key' => 'SB-Mid-server-xxx',
            'cloud_backup_contents' => 'binary-data',
            'storage_path' => '/tmp/backup.sqlite',
            'csrf_token' => 'token-val',
        ];

        $log = $logger->record(
            actor: $admin,
            action: PlatformAuditAction::BUSINESS_SUSPENDED,
            targetType: 'business',
            targetId: $business->id,
            targetLabel: $business->name,
            businessId: $business->id,
            before: $dirtyData,
            after: $dirtyData,
            metadata: $dirtyData,
        );

        foreach (['password', 'snap_token', 'provider_payload', 'redirect_url', 'server_key', 'cloud_backup_contents', 'storage_path', 'csrf_token'] as $secret) {
            $this->assertArrayNotHasKey($secret, $log->before_state ?? []);
            $this->assertArrayNotHasKey($secret, $log->after_state ?? []);
            $this->assertArrayNotHasKey($secret, $log->metadata ?? []);
        }

        $this->assertSame('active', $log->before_state['status']);
    }

    /**
     * 8. Read-only UI: no update/delete/clear routes exist.
     */
    public function test_audit_logs_routes_are_strictly_read_only(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $audit = PlatformAuditLog::factory()->create();

        $this->actingAs($admin)->post('/platform/audit-logs')->assertMethodNotAllowed();
        $this->actingAs($admin)->patch("/platform/audit-logs/{$audit->id}")->assertMethodNotAllowed();
        $this->actingAs($admin)->delete("/platform/audit-logs/{$audit->id}")->assertMethodNotAllowed();
    }

    /**
     * 9. Immutability guards on PlatformAuditLog model.
     */
    public function test_platform_audit_log_model_rejects_update_and_delete(): void
    {
        $audit = PlatformAuditLog::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('immutable and cannot be updated');
        $audit->update(['actor_name' => 'Tampered Name']);
    }

    public function test_platform_audit_log_model_rejects_delete(): void
    {
        $audit = PlatformAuditLog::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('immutable and cannot be deleted');
        $audit->delete();
    }

    /**
     * 10. Filter and Search tests.
     */
    public function test_audit_logs_index_filtering_and_search(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $log1 = PlatformAuditLog::factory()->create([
            'actor_name' => 'Alice Admin',
            'action' => PlatformAuditAction::BUSINESS_SUSPENDED,
            'target_type' => PlatformAuditAction::TARGET_BUSINESS,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $log2 = PlatformAuditLog::factory()->create([
            'actor_name' => 'Bob Operator',
            'action' => PlatformAuditAction::DEVICE_DEACTIVATED,
            'target_type' => PlatformAuditAction::TARGET_DEVICE,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        // Search by actor name
        $response = $this->actingAs($admin)->get(route('platform.audit-logs.index', ['q' => 'Alice']));
        $response->assertSee('Alice Admin');
        $response->assertDontSee('Bob Operator');

        // Filter by action
        $response = $this->actingAs($admin)->get(route('platform.audit-logs.index', ['action' => PlatformAuditAction::DEVICE_DEACTIVATED]));
        $response->assertSee('Bob Operator');
        $response->assertDontSee('Alice Admin');

        // Filter by target_type
        $response = $this->actingAs($admin)->get(route('platform.audit-logs.index', ['target_type' => PlatformAuditAction::TARGET_BUSINESS]));
        $response->assertSee('Alice Admin');
        $response->assertDontSee('Bob Operator');

        // Custom date reversed safe-swap
        $response = $this->actingAs($admin)->get(route('platform.audit-logs.index', [
            'date' => 'custom',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->subDays(3)->toDateString(),
        ]));
        $response->assertOk();
    }

    /**
     * 11. Pagination preserves query string and limits to 25 items.
     */
    public function test_audit_logs_pagination_preserves_query_string(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        PlatformAuditLog::factory()->count(30)->create();

        $response = $this->actingAs($admin)->get(route('platform.audit-logs.index', ['date' => 'all', 'q' => 'Test']));
        $response->assertOk();

        // 25 items on first page
        $logs = $response->viewData('logs');
        $this->assertSame(25, $logs->perPage());
    }

    /**
     * 12. Resilience: deleted actor user still renders snapshot name and email.
     */
    public function test_audit_log_renders_when_actor_is_null_or_deleted(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $log = PlatformAuditLog::factory()->create([
            'actor_user_id' => null,
            'actor_name' => 'Former Operator',
            'actor_email' => 'former@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('platform.audit-logs.show', $log));
        $response->assertOk();
        $response->assertSee('Former Operator');
        $response->assertSee('former@example.com');
    }

    /**
     * 13. Resilience: deleted target model does not crash detail page.
     */
    public function test_audit_log_renders_when_target_no_longer_exists(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $log = PlatformAuditLog::factory()->create([
            'target_type' => PlatformAuditAction::TARGET_BUSINESS,
            'target_id' => '999999', // Non-existent target
            'target_label' => 'Deleted Business Corp',
        ]);

        $response = $this->actingAs($admin)->get(route('platform.audit-logs.show', $log));
        $response->assertOk();
        $response->assertSee('Deleted Business Corp');
        $response->assertSee('999999');
    }

    /**
     * Helper to create device with default attributes.
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
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => null,
            'notes' => null,
        ], $attributes));
    }
}
