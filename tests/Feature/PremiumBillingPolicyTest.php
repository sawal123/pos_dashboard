<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePremiumAccess;
use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Services\Subscription\CloudDeviceLimit;
use App\Services\Subscription\PremiumPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PREM-D02A — Premium billing configuration & entitlement policy.
 *
 * Pins the FINAL product decisions:
 *  - canonical paid plan `cloud`, billing periods `monthly` + `yearly`
 *  - provider Midtrans, MANUAL renewal (never auto-renewal)
 *  - device limit 5 active cloud devices per business
 *  - expired/free/inactive keeps local POS and loses every cloud capability
 *  - pricing is database-owned (PREM-D02C) → an unpriced plan keeps the catalog
 *    empty, `checkout_available` false and nothing purchasable; no price is
 *    ever invented
 *
 * Every entitlement decision must be enforced server-side; nothing here relies
 * on the mobile UI.
 */
class PremiumBillingPolicyTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1. Final product policy (config is the source of truth)
    // ============================================================

    public function test_canonical_paid_plan_is_cloud_with_monthly_and_yearly_periods(): void
    {
        $policy = app(PremiumPolicy::class);

        $this->assertSame('cloud', $policy->planCode());
        $this->assertSame(['monthly', 'yearly'], $policy->billingPeriods());
        $this->assertTrue($policy->supportsBillingPeriod('monthly'));
        $this->assertTrue($policy->supportsBillingPeriod('yearly'));
        $this->assertFalse($policy->supportsBillingPeriod('weekly'));
        $this->assertFalse($policy->supportsBillingPeriod('lifetime'));
    }

    public function test_payment_provider_is_midtrans_and_renewal_is_manual_only(): void
    {
        $policy = app(PremiumPolicy::class);

        $this->assertSame('midtrans', $policy->paymentProvider());
        $this->assertSame('manual', $policy->renewalMode());
        $this->assertTrue($policy->isManualRenewal());
        // There is no auto-renewal anywhere in the policy.
        $this->assertNotSame('auto', $policy->renewalMode());
    }

    public function test_device_limit_is_five(): void
    {
        $this->assertSame(5, app(PremiumPolicy::class)->deviceLimit());
        $this->assertSame(5, app(CloudDeviceLimit::class)->limit());
    }

    public function test_official_capabilities_cover_the_four_declared_benefits(): void
    {
        $capabilities = app(PremiumPolicy::class)->capabilities();

        foreach (['web_dashboard', 'cloud_backup', 'cloud_restore', 'cloud_sync'] as $required) {
            $this->assertContains($required, $capabilities);
        }

        // Cloud device registration is a distinct denied capability.
        $this->assertContains('cloud_devices', $capabilities);
    }

    public function test_a_paid_subscription_activated_through_checkout_must_carry_an_expiry(): void
    {
        $this->assertTrue(app(PremiumPolicy::class)->requiresExpiryOnActivation());

        // PREM-D02A is non-destructive: a legacy cloud row without `expires_at`
        // is still honoured (documented risk), and is never rewritten here.
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'expires_at' => null,
        ]);

        $this->assertTrue($business->fresh()->hasCloudAccess());
    }

    // ============================================================
    // 2. Entitlement (active / free / expired / inactive)
    // ============================================================

    public function test_active_cloud_subscription_grants_every_capability(): void
    {
        $business = $this->businessWithCloud();

        $policy = app(PremiumPolicy::class);

        $this->assertTrue($policy->hasCloudEntitlement($business));
        foreach ($policy->capabilities() as $capability) {
            $this->assertTrue($policy->allows($business, $capability), "denied: {$capability}");
        }
    }

    public function test_free_subscription_grants_no_capability(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $this->assertNoCapability($business);
    }

    public function test_business_without_any_subscription_grants_no_capability(): void
    {
        $this->assertNoCapability(Business::factory()->create());
        $this->assertFalse(app(PremiumPolicy::class)->hasCloudEntitlement(null));
    }

    public function test_expired_cloud_by_date_grants_no_capability(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertNoCapability($business);
    }

    public function test_expired_cloud_status_grants_no_capability(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);

        $this->assertNoCapability($business);
    }

    public function test_inactive_cloud_grants_no_capability(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => Subscription::STATUS_INACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertNoCapability($business);
    }

    public function test_expiry_boundary_is_exclusive(): void
    {
        $fixedNow = Carbon::parse('2026-09-30 12:00:00');
        Carbon::setTestNow($fixedNow);

        try {
            $business = Business::factory()->create();
            $subscription = Subscription::factory()->cloud()->create([
                'business_id' => $business->id,
                'expires_at' => $fixedNow,
            ]);

            // expires_at <= now() is expired.
            $this->assertFalse($business->fresh()->hasCloudAccess());
            $this->assertNoCapability($business->fresh());

            $subscription->update(['expires_at' => $fixedNow->copy()->addSecond()]);
            $this->assertTrue($business->fresh()->hasCloudAccess());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_unknown_capability_is_denied_by_default(): void
    {
        $this->assertFalse(
            app(PremiumPolicy::class)->allows($this->businessWithCloud(), 'not_a_capability'),
        );
    }

    public function test_entitlement_never_leaks_between_tenants(): void
    {
        $active = $this->businessWithCloud();
        $free = Business::factory()->create();
        Subscription::factory()->free()->create(['business_id' => $free->id]);

        $policy = app(PremiumPolicy::class);

        $this->assertTrue($policy->allows($active, 'cloud_sync'));
        $this->assertFalse($policy->allows($free, 'cloud_sync'));
        $this->assertTrue($policy->allows($active->fresh(), 'cloud_sync'));
    }

    // ============================================================
    // 3. Pricing is undecided — the catalog must never invent it
    // ============================================================

    public function test_mobile_catalog_stays_empty_and_checkout_unavailable_without_pricing(): void
    {
        $business = $this->businessWithCloud();

        $this->withToken($this->tokenFor($this->ownerOf($business)))
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJson([
                'data' => [
                    'plans' => [],
                    'checkout_available' => false,
                ],
            ]);
    }

    public function test_configured_priced_plan_is_never_purchasable_without_checkout(): void
    {
        $this->seedPlanPrices([
            'monthly' => 50000,
            'yearly' => 500000,
        ]);

        $business = $this->businessWithCloud();

        $response = $this->withToken($this->tokenFor($this->ownerOf($business)))
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.checkout_available', false);

        // Both official periods are recognised, but nothing is purchasable
        // while no checkout exists.
        $this->assertSame('monthly', $response->json('data.plans.0.billing_periods.0.period'));
        $this->assertSame('yearly', $response->json('data.plans.0.billing_periods.1.period'));
        $this->assertFalse($response->json('data.plans.0.purchasable'));
    }

    public function test_config_pricing_is_no_longer_a_price_source(): void
    {
        // PREM-D02C: config/ENV is no longer a pricing source. A legacy
        // `premium.mobile_plans` block must not invent a catalog or a price.
        config()->set('premium.mobile_plans', [
            [
                'code' => Subscription::PLAN_CLOUD,
                'name' => 'Cloud',
                'billing_periods' => [
                    ['period' => 'monthly', 'currency' => 'IDR', 'price_minor' => 12345],
                ],
            ],
        ]);

        $business = $this->businessWithCloud();

        $this->withToken($this->tokenFor($this->ownerOf($business)))
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.plans', [])
            ->assertJsonPath('data.checkout_available', false);
    }

    public function test_unsupported_billing_period_is_dropped_from_the_catalog(): void
    {
        $this->seedPlanPrices([
            'monthly' => 50000,
            'weekly' => 15000,
        ]);

        $business = $this->businessWithCloud();

        $response = $this->withToken($this->tokenFor($this->ownerOf($business)))
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk();

        $this->assertCount(1, $response->json('data.plans.0.billing_periods'));
        $this->assertSame('monthly', $response->json('data.plans.0.billing_periods.0.period'));
    }

    // ============================================================
    // 4. Cloud device limit (server-side, 5 per business)
    // ============================================================

    public function test_devices_one_to_five_are_allowed_and_the_sixth_is_rejected(): void
    {
        [$business, $outlet, $user] = $this->cloudBusinessWithOutlet();
        $token = $this->tokenFor($user);

        // Four active devices: the fifth registration is still allowed.
        $this->seedDevices($business, $outlet, 4, Device::STATUS_ACTIVE);

        $this->withToken($token)
            ->postJson('/api/mobile/devices', $this->devicePayload($business, $outlet, 'DEVICE-05'))
            ->assertOk()
            ->assertJsonPath('data.status', Device::STATUS_ACTIVE);

        $this->assertSame(5, app(CloudDeviceLimit::class)->activeCount($business));

        // The sixth is rejected with a stable, actionable code.
        $this->withToken($token)
            ->postJson('/api/mobile/devices', $this->devicePayload($business, $outlet, 'DEVICE-06'))
            ->assertStatus(403)
            ->assertJson([
                'code' => 'CLOUD_DEVICE_LIMIT_REACHED',
                'device_limit' => 5,
                'active_devices' => 5,
            ]);

        $this->assertDatabaseMissing('devices', ['identifier' => 'DEVICE-06']);
        $this->assertSame(5, Device::where('business_id', $business->id)->count());
    }

    public function test_inactive_devices_do_not_consume_a_slot(): void
    {
        [$business, $outlet, $user] = $this->cloudBusinessWithOutlet();

        $this->seedDevices($business, $outlet, 5, Device::STATUS_ACTIVE);
        $this->seedDevices($business, $outlet, 4, Device::STATUS_INACTIVE, 'INACTIVE');

        // 5 active (limit reached) + inactive rows that must NOT be counted.
        $this->assertSame(5, app(CloudDeviceLimit::class)->activeCount($business));
        $this->assertTrue(app(CloudDeviceLimit::class)->isReached($business));

        // Deactivating one device frees exactly one slot.
        $device = Device::where('business_id', $business->id)
            ->where('identifier', 'DEVICE-01')
            ->firstOrFail();
        $device->update(['status' => Device::STATUS_INACTIVE]);

        $this->assertFalse(app(CloudDeviceLimit::class)->isReached($business));
        $this->assertSame(4, app(CloudDeviceLimit::class)->activeCount($business));

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/mobile/devices', $this->devicePayload($business, $outlet, 'DEVICE-NEW'))
            ->assertOk();

        $this->assertDatabaseHas('devices', ['identifier' => 'DEVICE-NEW']);
    }

    public function test_re_resolving_an_existing_device_is_never_blocked_by_the_limit(): void
    {
        [$business, $outlet, $user] = $this->cloudBusinessWithOutlet();
        $this->seedDevices($business, $outlet, 5, Device::STATUS_ACTIVE);

        $existing = Device::where('business_id', $business->id)->firstOrFail();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/mobile/devices', $this->devicePayload($business, $outlet, (string) $existing->identifier))
            ->assertOk()
            ->assertJsonPath('data.identifier', $existing->identifier);

        $this->assertSame(5, Device::where('business_id', $business->id)->count());
    }

    public function test_device_limit_does_not_leak_across_tenants(): void
    {
        [$businessA, $outletA, $userA] = $this->cloudBusinessWithOutlet();
        $this->seedDevices($businessA, $outletA, 5, Device::STATUS_ACTIVE);

        [$businessB, $outletB, $userB] = $this->cloudBusinessWithOutlet();

        $this->assertTrue(app(CloudDeviceLimit::class)->isReached($businessA));
        $this->assertFalse(app(CloudDeviceLimit::class)->isReached($businessB));

        $this->withToken($this->tokenFor($userB))
            ->postJson('/api/mobile/devices', $this->devicePayload($businessB, $outletB, 'TENANT-B-01'))
            ->assertOk();

        $this->assertSame(1, Device::where('business_id', $businessB->id)->count());
        $this->assertSame(5, Device::where('business_id', $businessA->id)->count());
    }

    public function test_dashboard_registration_cannot_bypass_the_device_limit(): void
    {
        $business = $this->businessWithCloud();
        $owner = $this->ownerOf($business);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $this->seedDevices($business, $outlet, 5, Device::STATUS_ACTIVE);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->from(route('devices.index'))
            ->post(route('devices.store'), [
                'name' => 'Perangkat Keenam',
                'identifier' => 'DASHBOARD-06',
                'outlet_id' => $outlet->id,
            ])
            ->assertSessionHasErrors('identifier');

        $this->assertDatabaseMissing('devices', ['identifier' => 'DASHBOARD-06']);
        $this->assertSame(5, Device::where('business_id', $business->id)->count());
    }

    public function test_device_limit_follows_configuration_the_server_owns(): void
    {
        config()->set('premium.plan.device_limit', 2);

        [$business, $outlet, $user] = $this->cloudBusinessWithOutlet();
        $this->seedDevices($business, $outlet, 2, Device::STATUS_ACTIVE);

        $this->assertSame(2, app(CloudDeviceLimit::class)->limit());
        $this->assertTrue(app(CloudDeviceLimit::class)->isReached($business));

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/mobile/devices', $this->devicePayload($business, $outlet, 'CONFIG-LIMIT-01'))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_DEVICE_LIMIT_REACHED', 'device_limit' => 2]);
    }

    public function test_cloud_entitlement_is_still_checked_before_the_device_limit(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->free()->create(['business_id' => $business->id]);
        $owner = $this->ownerOf($business);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->withToken($this->tokenFor($owner))
            ->postJson('/api/mobile/devices', $this->devicePayload($business, $outlet, 'FREE-01'))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    // ============================================================
    // 5. Expired behavior — local stays, cloud is denied
    // ============================================================

    public function test_expired_cloud_loses_cloud_sync_and_device_registration(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);
        $owner = $this->ownerOf($business);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $token = $this->tokenFor($owner);

        // Cloud synchronisation.
        $this->withToken($token)
            ->getJson('/api/sync/pull?'.http_build_query([
                'business_id' => $business->id,
                'device_identifier' => 'ANY-DEVICE',
                'after' => 0,
                'limit' => 10,
            ]))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);

        $this->withToken($token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'ANY-DEVICE',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);

        // Cloud device registration.
        $this->withToken($token)
            ->postJson('/api/mobile/devices', $this->devicePayload($business, $outlet, 'EXPIRED-01'))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);

        $this->assertDatabaseMissing('devices', ['identifier' => 'EXPIRED-01']);
    }

    public function test_expiry_never_blocks_local_pos_bootstrap_login_or_session(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);
        $owner = $this->ownerOf($business);
        Outlet::factory()->create(['business_id' => $business->id]);
        $token = $this->tokenFor($owner);

        // POS Mobile still resolves its business/outlet context offline-first.
        $this->withToken($token)
            ->getJson('/api/mobile/context')
            ->assertOk()
            ->assertJsonPath('data.businesses.0.id', $business->id)
            ->assertJsonPath('data.businesses.0.cloud_access', false);

        // The session/login surface is untouched by the subscription state.
        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk();
    }

    public function test_expired_cloud_is_denied_the_premium_dashboard_surface(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);
        $owner = $this->ownerOf($business);

        foreach (['transactions.index', 'products.index', 'reports.index', 'devices.index', 'sync.index'] as $routeName) {
            $this->actingAs($owner)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->get(route($routeName))
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('status', EnsurePremiumAccess::LOCKED_MESSAGE);
        }

        // A JSON client receives the same stable code the mobile API uses.
        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->getJson(route('reports.index'))
            ->assertStatus(403)
            ->assertJson(['code' => EnsurePremiumAccess::CODE_CLOUD_SUBSCRIPTION_REQUIRED]);
    }

    public function test_free_and_inactive_cloud_are_denied_the_premium_dashboard_surface(): void
    {
        $free = Business::factory()->create();
        Subscription::factory()->free()->create(['business_id' => $free->id]);

        $inactive = Business::factory()->create();
        Subscription::factory()->cloud()->create([
            'business_id' => $inactive->id,
            'status' => Subscription::STATUS_INACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        foreach ([$free, $inactive] as $business) {
            $this->actingAs($this->ownerOf($business))
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->get(route('transactions.index'))
                ->assertRedirect(route('dashboard'));
        }
    }

    public function test_active_cloud_owner_reaches_the_premium_dashboard_surface(): void
    {
        $business = $this->businessWithCloud();
        $owner = $this->ownerOf($business);

        foreach (['transactions.index', 'products.index', 'reports.index', 'devices.index', 'sync.index'] as $routeName) {
            $this->actingAs($owner)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_account_and_billing_paths_stay_reachable_after_expiry(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);
        $owner = $this->ownerOf($business);

        // The renewal path: dashboard shell + Langganan + business settings.
        foreach (['dashboard', 'subscriptions.index', 'business-settings.edit'] as $routeName) {
            $this->actingAs($owner)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->get(route($routeName))
                ->assertOk();
        }

        // The billing page still shows the real (non-active) state.
        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('subscriptions.index'))
            ->assertOk()
            ->assertSee('Tidak Aktif');
    }

    public function test_denied_premium_route_lands_on_the_dashboard_with_its_reason(): void
    {
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);

        $this->actingAs($this->ownerOf($business))
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->followingRedirects()
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee(EnsurePremiumAccess::LOCKED_MESSAGE);
    }

    public function test_entitlement_gate_keeps_the_rbac_contract_in_front(): void
    {
        // A cashier without reports.view is rejected by RBAC (403) — the
        // entitlement redirect must not mask the authorization result.
        $business = Business::factory()->create();
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $cashier = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($cashier->id, ['role' => Business::ROLE_CASHIER]);

        $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Seed the canonical cloud plan with a period => price_minor map.
     *
     * @param  array<string, int>  $prices
     */
    private function seedPlanPrices(array $prices): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'name' => 'Cloud',
        ]);

        foreach ($prices as $period => $priceMinor) {
            SubscriptionPlanPrice::factory()->create([
                'subscription_plan_id' => $plan->id,
                'billing_period' => $period,
                'currency' => 'IDR',
                'price_minor' => $priceMinor,
            ]);
        }
    }

    private function assertNoCapability(Business $business): void
    {
        $policy = app(PremiumPolicy::class);

        $this->assertFalse($policy->hasCloudEntitlement($business));

        foreach ($policy->capabilities() as $capability) {
            $this->assertFalse($policy->allows($business, $capability), "granted: {$capability}");
        }
    }

    private function businessWithCloud(): Business
    {
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        return $business;
    }

    private function ownerOf(Business $business): User
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        return $owner;
    }

    /**
     * @return array{0: Business, 1: Outlet, 2: User}
     */
    private function cloudBusinessWithOutlet(): array
    {
        $business = $this->businessWithCloud();
        $owner = $this->ownerOf($business);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        return [$business, $outlet, $owner];
    }

    private function seedDevices(
        Business $business,
        Outlet $outlet,
        int $count,
        string $status,
        string $prefix = 'DEVICE',
    ): void {
        for ($i = 1; $i <= $count; $i++) {
            Device::create([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'name' => $prefix.' '.$i,
                'identifier' => $prefix.'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'platform' => 'android',
                'status' => $status,
                'registered_at' => now(),
                'last_seen_at' => null,
                'notes' => null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function devicePayload(Business $business, Outlet $outlet, string $identifier): array
    {
        return [
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'device_identifier' => $identifier,
            'name' => 'Perangkat '.$identifier,
            'platform' => 'android',
        ];
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('mobile-api', ['mobile'])->plainTextToken;
    }
}
