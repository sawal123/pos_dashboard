<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BusinessSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_have_one_subscription(): void
    {
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->free()->create([
            'business_id' => $business->id,
        ]);

        $this->assertNotNull($business->subscription);
        $this->assertSame($subscription->id, $business->subscription->id);
    }

    public function test_subscription_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
        ]);

        $this->assertNotNull($subscription->business);
        $this->assertSame($business->id, $subscription->business->id);
    }

    public function test_business_cannot_have_duplicate_subscription_records(): void
    {
        $business = Business::factory()->create();

        Subscription::factory()->free()->create([
            'business_id' => $business->id,
        ]);

        $this->expectException(QueryException::class);

        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
        ]);
    }

    public function test_free_subscription_is_recognized_as_free(): void
    {
        $subscription = Subscription::factory()->free()->create();

        $this->assertTrue($subscription->isFree());
        $this->assertFalse($subscription->isCloud());
    }

    public function test_free_subscription_does_not_grant_cloud_access(): void
    {
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->free()->create([
            'business_id' => $business->id,
        ]);

        $this->assertFalse($subscription->hasCloudAccess());
        $this->assertFalse($business->hasCloudAccess());
    }

    public function test_free_subscription_active_still_has_no_cloud_access(): void
    {
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->free()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => null,
        ]);

        $this->assertSame('active', $subscription->status);
        $this->assertFalse($subscription->hasCloudAccess());
        $this->assertFalse($business->hasCloudAccess());
    }

    public function test_active_cloud_subscription_before_expiry_has_cloud_access(): void
    {
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertTrue($subscription->isCloud());
        $this->assertFalse($subscription->isExpired());
        $this->assertTrue($subscription->hasCloudAccess());
        $this->assertTrue($business->hasCloudAccess());
    }

    public function test_active_cloud_subscription_past_expiry_has_no_cloud_access(): void
    {
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($subscription->isExpired());
        $this->assertFalse($subscription->hasCloudAccess());
        $this->assertFalse($business->hasCloudAccess());
    }

    public function test_expired_status_cloud_subscription_has_no_cloud_access(): void
    {
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->cloud()->expired()->create([
            'business_id' => $business->id,
        ]);

        $this->assertSame('expired', $subscription->status);
        $this->assertTrue($subscription->isExpired());
        $this->assertFalse($subscription->hasCloudAccess());
        $this->assertFalse($business->hasCloudAccess());
    }

    public function test_business_without_subscription_has_no_cloud_access(): void
    {
        $business = Business::factory()->create();

        $this->assertNull($business->subscription);
        $this->assertFalse($business->hasCloudAccess());
    }

    public function test_deleting_business_safely_cascades_subscription_deletion(): void
    {
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'business_id' => $business->id,
        ]);

        $business->delete();

        $this->assertDatabaseMissing('subscriptions', [
            'id' => $subscription->id,
            'business_id' => $business->id,
        ]);
    }

    public function test_subscription_isolation_between_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $subscriptionA = Subscription::factory()->cloud()->create([
            'business_id' => $businessA->id,
        ]);
        $subscriptionB = Subscription::factory()->free()->create([
            'business_id' => $businessB->id,
        ]);

        $this->assertTrue($businessA->hasCloudAccess());
        $this->assertFalse($businessB->hasCloudAccess());

        $this->assertSame($subscriptionA->id, $businessA->subscription->id);
        $this->assertSame($subscriptionB->id, $businessB->subscription->id);
    }

    public function test_cloud_subscription_boundary_exact_now_and_travel(): void
    {
        $fixedNow = Carbon::parse('2026-08-21 12:00:00');
        Carbon::setTestNow($fixedNow);

        $business = Business::factory()->create();
        $subscription = Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => $fixedNow,
        ]);

        // Exact now: expires_at <= now() -> expired and no cloud access
        $this->assertTrue($subscription->isExpired());
        $this->assertFalse($subscription->hasCloudAccess());
        $this->assertFalse($business->hasCloudAccess());

        // 1 second in the future: grants cloud access
        $subscription->update(['expires_at' => $fixedNow->copy()->addSecond()]);
        $this->assertFalse($subscription->fresh()->isExpired());
        $this->assertTrue($subscription->fresh()->hasCloudAccess());
        $this->assertTrue($business->fresh()->hasCloudAccess());

        // Time travel to past expiry
        Carbon::setTestNow($fixedNow->copy()->addMinutes(5));
        $this->assertTrue($subscription->fresh()->isExpired());
        $this->assertFalse($subscription->fresh()->hasCloudAccess());
        $this->assertFalse($business->fresh()->hasCloudAccess());

        Carbon::setTestNow(null);
    }
}
