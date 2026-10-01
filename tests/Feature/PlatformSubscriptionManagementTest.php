<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    /**
     * A. Access Control
     */
    public function test_platform_admin_can_access_subscriptions_index(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/subscriptions');

        $response->assertOk();
        $response->assertSee('Manajemen Langganan & Paket');
        $response->assertSee('Total:');
    }

    public function test_business_owner_is_forbidden_from_subscriptions_index(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)->get('/platform/subscriptions')->assertForbidden();
    }

    public function test_regular_user_is_forbidden_from_subscriptions_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/platform/subscriptions')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/platform/subscriptions')->assertRedirect(route('login'));
    }

    /**
     * B. List actual records: Free active, Cloud active, Cloud expired, Cloud inactive.
     */
    public function test_subscriptions_index_lists_actual_records(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $biz1 = Business::factory()->create(['name' => 'Toko Kopi Alpha', 'slug' => 'toko-kopi-alpha']);
        Subscription::factory()->create([
            'business_id' => $biz1->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $biz2 = Business::factory()->create(['name' => 'Resto Beta Cloud', 'slug' => 'resto-beta-cloud']);
        Subscription::factory()->create([
            'business_id' => $biz2->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subDays(5),
            'expires_at' => now()->addDays(25),
        ]);

        $biz3 = Business::factory()->create(['name' => 'Kedai Gamma Expired', 'slug' => 'kedai-gamma-expired']);
        Subscription::factory()->create([
            'business_id' => $biz3->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_EXPIRED,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDays(5),
        ]);

        $biz4 = Business::factory()->create(['name' => 'Kafe Delta Inactive', 'slug' => 'kafe-delta-inactive']);
        Subscription::factory()->create([
            'business_id' => $biz4->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_INACTIVE,
        ]);

        $response = $this->actingAs($admin)->get('/platform/subscriptions');

        $response->assertOk();
        $response->assertSee('Toko Kopi Alpha');
        $response->assertSee('Resto Beta Cloud');
        $response->assertSee('Kedai Gamma Expired');
        $response->assertSee('Kafe Delta Inactive');
    }

    /**
     * C. Search by business name and slug.
     */
    public function test_search_filters_by_business_name_and_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $bizMatch = Business::factory()->create(['name' => 'Kopi Nusantara Maju', 'slug' => 'kopi-nusantara-maju']);
        Subscription::factory()->create(['business_id' => $bizMatch->id]);

        $bizOther = Business::factory()->create(['name' => 'Apotek Sehat Bahagia', 'slug' => 'apotek-sehat-bahagia']);
        Subscription::factory()->create(['business_id' => $bizOther->id]);

        // Search by name
        $responseName = $this->actingAs($admin)->get('/platform/subscriptions?q=Nusantara');
        $responseName->assertOk();
        $responseName->assertSee('Kopi Nusantara Maju');
        $responseName->assertDontSee('Apotek Sehat Bahagia');

        // Search by slug
        $responseSlug = $this->actingAs($admin)->get('/platform/subscriptions?q=apotek-sehat');
        $responseSlug->assertOk();
        $responseSlug->assertSee('Apotek Sehat Bahagia');
        $responseSlug->assertDontSee('Kopi Nusantara Maju');
    }

    /**
     * D. Plan filter (free vs cloud).
     */
    public function test_filter_by_plan(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $bizCloud = Business::factory()->create(['name' => 'Bisnis Cloud Satu']);
        Subscription::factory()->create([
            'business_id' => $bizCloud->id,
            'plan' => Subscription::PLAN_CLOUD,
        ]);

        $bizFree = Business::factory()->create(['name' => 'Bisnis Free Dua']);
        Subscription::factory()->create([
            'business_id' => $bizFree->id,
            'plan' => Subscription::PLAN_FREE,
        ]);

        // Filter Cloud
        $resCloud = $this->actingAs($admin)->get('/platform/subscriptions?plan=cloud');
        $resCloud->assertOk();
        $resCloud->assertSee('Bisnis Cloud Satu');
        $resCloud->assertDontSee('Bisnis Free Dua');

        // Filter Free
        $resFree = $this->actingAs($admin)->get('/platform/subscriptions?plan=free');
        $resFree->assertOk();
        $resFree->assertSee('Bisnis Free Dua');
        $resFree->assertDontSee('Bisnis Cloud Satu');
    }

    /**
     * E. Status filter (active, inactive, expired).
     */
    public function test_filter_by_status(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $bizActive = Business::factory()->create(['name' => 'Bisnis Status Aktif']);
        Subscription::factory()->create([
            'business_id' => $bizActive->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $bizExpired = Business::factory()->create(['name' => 'Bisnis Status Kadaluarsa']);
        Subscription::factory()->create([
            'business_id' => $bizExpired->id,
            'status' => Subscription::STATUS_EXPIRED,
        ]);

        $bizInactive = Business::factory()->create(['name' => 'Bisnis Status Nonaktif']);
        Subscription::factory()->create([
            'business_id' => $bizInactive->id,
            'status' => Subscription::STATUS_INACTIVE,
        ]);

        // Filter active
        $resActive = $this->actingAs($admin)->get('/platform/subscriptions?status=active');
        $resActive->assertOk();
        $resActive->assertSee('Bisnis Status Aktif');
        $resActive->assertDontSee('Bisnis Status Kadaluarsa');
        $resActive->assertDontSee('Bisnis Status Nonaktif');

        // Filter expired
        $resExpired = $this->actingAs($admin)->get('/platform/subscriptions?status=expired');
        $resExpired->assertOk();
        $resExpired->assertSee('Bisnis Status Kadaluarsa');
        $resExpired->assertDontSee('Bisnis Status Aktif');
        $resExpired->assertDontSee('Bisnis Status Nonaktif');

        // Filter inactive
        $resInactive = $this->actingAs($admin)->get('/platform/subscriptions?status=inactive');
        $resInactive->assertOk();
        $resInactive->assertSee('Bisnis Status Nonaktif');
        $resInactive->assertDontSee('Bisnis Status Aktif');
        $resInactive->assertDontSee('Bisnis Status Kadaluarsa');
    }

    /**
     * F. Entitlement filter:
     * Cloud Active Future Expiry -> Granted
     * Cloud Expired -> Denied
     * Cloud Inactive -> Denied
     * Free Active -> Denied
     */
    public function test_filter_by_entitlement_status(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $admin = User::factory()->platformAdmin()->create();

        // 1. Cloud Active Future -> GRANTED
        $bizGranted = Business::factory()->create(['name' => 'Entitlement Granted Store']);
        Subscription::factory()->create([
            'business_id' => $bizGranted->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        // 2. Cloud Expired -> DENIED
        $bizExpired = Business::factory()->create(['name' => 'Cloud Expired Denied']);
        Subscription::factory()->create([
            'business_id' => $bizExpired->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        // 3. Cloud Inactive -> DENIED
        $bizInactive = Business::factory()->create(['name' => 'Cloud Inactive Denied']);
        Subscription::factory()->create([
            'business_id' => $bizInactive->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_INACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        // 4. Free Active -> DENIED
        $bizFree = Business::factory()->create(['name' => 'Free Active Denied']);
        Subscription::factory()->create([
            'business_id' => $bizFree->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        // Filter granted
        $resGranted = $this->actingAs($admin)->get('/platform/subscriptions?entitlement=granted');
        $resGranted->assertOk();
        $resGranted->assertSee('Entitlement Granted Store');
        $resGranted->assertDontSee('Cloud Expired Denied');
        $resGranted->assertDontSee('Cloud Inactive Denied');
        $resGranted->assertDontSee('Free Active Denied');

        // Filter denied
        $resDenied = $this->actingAs($admin)->get('/platform/subscriptions?entitlement=denied');
        $resDenied->assertOk();
        $resDenied->assertDontSee('Entitlement Granted Store');
        $resDenied->assertSee('Cloud Expired Denied');
        $resDenied->assertSee('Cloud Inactive Denied');
        $resDenied->assertSee('Free Active Denied');
    }

    /**
     * G. Pagination > 25 records with query string retention.
     */
    public function test_pagination_and_query_string_retention(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        for ($i = 0; $i < 30; $i++) {
            $biz = Business::factory()->create();
            Subscription::factory()->create(['business_id' => $biz->id, 'plan' => Subscription::PLAN_CLOUD]);
        }

        $response = $this->actingAs($admin)->get('/platform/subscriptions?page=1&plan=cloud');

        $response->assertOk();
        $response->assertSee('plan=cloud');
    }

    /**
     * H. Detail view: business info, plan, status, starts_at, expires_at, entitlement, capability map.
     */
    public function test_subscription_detail_displays_full_information(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create([
            'name' => 'Restoran Padang Salero',
            'slug' => 'restoran-padang-salero',
            'status' => 'active',
        ]);
        $owner = User::factory()->create(['name' => 'Budi Owner', 'email' => 'budi.owner@example.com']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($admin)->get("/platform/subscriptions/{$subscription->id}");

        $response->assertOk();
        $response->assertSee('Restoran Padang Salero');
        $response->assertSee('Paket Cloud');
        $response->assertSee('Cloud Entitlement Granted');
        $response->assertSee('Web Dashboard');
        $response->assertSee('Cloud Sync');
        $response->assertSee('Cloud Devices');
        $response->assertSee('Batas Kuota Cloud:');
    }

    /**
     * I. Missing starts_at / expires_at dates render cleanly without errors.
     */
    public function test_subscription_with_missing_dates_renders_cleanly(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create(['name' => 'Toko Serba Ada']);
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $response = $this->actingAs($admin)->get("/platform/subscriptions/{$subscription->id}");

        $response->assertOk();
        $response->assertSee('Toko Serba Ada');
        $response->assertSee('Tidak ditentukan (Permanen)');
    }

    /**
     * J. Mutation: Manual Cloud Activation (Free -> Cloud Active) with official duration.
     */
    public function test_manual_activation_from_free_to_cloud(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $this->assertFalse($subscription->hasCloudAccess());

        $response = $this->actingAs($admin)->patch("/platform/subscriptions/{$subscription->id}/activate", [
            'billing_period' => 'monthly',
        ]);

        $response->assertRedirect("/platform/subscriptions/{$subscription->id}");
        $response->assertSessionHas('status');

        $subscription->refresh();
        $this->assertSame(Subscription::PLAN_CLOUD, $subscription->plan);
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertEquals(now(), $subscription->starts_at);
        $this->assertEquals(now()->addMonthNoOverflow(), $subscription->expires_at);
        $this->assertTrue($subscription->hasCloudAccess());
    }

    /**
     * K. Mutation: Downgrade from Cloud to Free (entitlement revoked, records preserved).
     */
    public function test_downgrade_cloud_to_free(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Tablet Kasir',
            'identifier' => 'TAB-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $sync = SyncRequest::create([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now(),
        ]);

        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
        ]);

        $this->assertTrue($subscription->hasCloudAccess());

        $response = $this->actingAs($admin)->patch("/platform/subscriptions/{$subscription->id}/downgrade");

        $response->assertRedirect("/platform/subscriptions/{$subscription->id}");

        $subscription->refresh();
        $this->assertSame(Subscription::PLAN_FREE, $subscription->plan);
        $this->assertFalse($subscription->hasCloudAccess());

        // Verify business, device, and sync data are not deleted
        $this->assertDatabaseHas('businesses', ['id' => $business->id]);
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
        $this->assertDatabaseHas('sync_requests', ['id' => $sync->id]);
    }

    /**
     * L. Mutation: Inactivate subscription (status -> inactive, entitlement false).
     */
    public function test_inactivate_subscription(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($subscription->hasCloudAccess());

        $response = $this->actingAs($admin)->patch("/platform/subscriptions/{$subscription->id}/inactivate");

        $response->assertRedirect("/platform/subscriptions/{$subscription->id}");

        $subscription->refresh();
        $this->assertSame(Subscription::STATUS_INACTIVE, $subscription->status);
        $this->assertFalse($subscription->hasCloudAccess());
    }

    /**
     * M. Mutation: Renewal for active subscription with future expiry (base = current expires_at).
     */
    public function test_renewal_active_future_expiry_extends_from_current_expires_at(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $currentExpiry = Carbon::parse('2026-10-20 12:00:00');

        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => Carbon::parse('2026-09-20 12:00:00'),
            'expires_at' => $currentExpiry,
        ]);

        $response = $this->actingAs($admin)->post("/platform/subscriptions/{$subscription->id}/renew", [
            'billing_period' => 'monthly',
        ]);

        $response->assertRedirect("/platform/subscriptions/{$subscription->id}");

        $subscription->refresh();
        // Base must be currentExpiry (2026-10-20), not now (2026-10-01)
        $expectedExpiry = $currentExpiry->copy()->addMonthNoOverflow();
        $this->assertEquals($expectedExpiry, $subscription->expires_at);
        $this->assertTrue($subscription->hasCloudAccess());
    }

    /**
     * N. Mutation: Renewal for expired subscription (base = now()).
     */
    public function test_renewal_expired_extends_from_now(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $admin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        $pastExpiry = Carbon::parse('2026-09-15 12:00:00');

        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_EXPIRED,
            'starts_at' => Carbon::parse('2026-08-15 12:00:00'),
            'expires_at' => $pastExpiry,
        ]);

        $response = $this->actingAs($admin)->post("/platform/subscriptions/{$subscription->id}/renew", [
            'billing_period' => 'monthly',
        ]);

        $response->assertRedirect("/platform/subscriptions/{$subscription->id}");

        $subscription->refresh();
        // Base must be now (2026-10-01), not the past expiry
        $expectedExpiry = now()->addMonthNoOverflow();
        $this->assertEquals($expectedExpiry, $subscription->expires_at);
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->hasCloudAccess());
    }

    /**
     * O. Unauthorized user cannot mutate subscription.
     */
    public function test_unauthorized_user_cannot_mutate_subscription(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->create(['business_id' => $business->id]);

        $this->actingAs($user)->patch("/platform/subscriptions/{$subscription->id}/downgrade")->assertForbidden();
        $this->actingAs($user)->post("/platform/subscriptions/{$subscription->id}/renew", ['billing_period' => 'monthly'])->assertForbidden();
    }

    /**
     * P. GET route cannot mutate subscription state.
     */
    public function test_get_route_does_not_mutate_subscription(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->get("/platform/subscriptions/{$subscription->id}")->assertOk();

        $subscription->refresh();
        $this->assertSame(Subscription::PLAN_FREE, $subscription->plan);
    }

    /**
     * Q. Invalid billing period is rejected by validation.
     */
    public function test_invalid_billing_period_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
        ]);

        $response = $this->actingAs($admin)->patch("/platform/subscriptions/{$subscription->id}/activate", [
            'billing_period' => 'daily_invalid_period',
        ]);

        $response->assertSessionHasErrors('billing_period');
    }
}
