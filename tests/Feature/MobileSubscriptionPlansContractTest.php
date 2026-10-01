<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MobileSubscriptionPlansContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} */
    private function userWithToken(array $abilities = ['mobile']): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('api-token', $abilities)->plainTextToken;

        return [$user, $token];
    }

    private function attach(User $user, Business $business, string $role = 'owner'): void
    {
        $user->businesses()->attach($business->id, ['role' => $role]);
    }

    /**
     * Seed the canonical cloud plan with a single active monthly price.
     */
    private function seedCloudMonthly(int $priceMinor): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'name' => 'Cloud',
        ]);

        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => $priceMinor,
        ]);
    }

    public function test_subscription_plans_require_authentication(): void
    {
        $this->getJson('/api/mobile/subscription/plans?business_id=1')
            ->assertUnauthorized();
    }

    public function test_subscription_plans_require_a_mobile_token(): void
    {
        [$user, $token] = $this->userWithToken(['web']);
        $business = Business::factory()->create();
        $this->attach($user, $business);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertForbidden()
            ->assertJson([
                'message' => 'Mobile API token is required.',
                'code' => 'MOBILE_TOKEN_REQUIRED',
            ]);
    }

    public function test_unavailable_catalog_is_empty_and_checkout_is_false(): void
    {
        [$user, $token] = $this->userWithToken();
        $business = Business::factory()->create();
        $this->attach($user, $business);
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJson([
                'data' => [
                    'business_id' => $business->id,
                    'plans' => [],
                    'checkout_available' => false,
                ],
            ]);
    }

    public function test_configured_catalog_exposes_canonical_plan_code_and_official_periods_only(): void
    {
        $cloud = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'name' => 'Cloud',
        ]);
        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $cloud->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 12345,
        ]);
        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $cloud->id,
            'billing_period' => 'weekly',
            'currency' => 'IDR',
            'price_minor' => 999,
        ]);

        // A non-canonical code must never leak, even with a valid price row.
        $nonCanonical = SubscriptionPlan::factory()->create([
            'code' => 'premium',
            'name' => 'Hidden Mapping Must Not Leak',
        ]);
        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $nonCanonical->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 1,
        ]);

        [$user, $token] = $this->userWithToken();
        $business = Business::factory()->create();
        $this->attach($user, $business);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $response = $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.checkout_available', false)
            ->assertJsonPath('data.plans.0.code', Subscription::PLAN_CLOUD)
            ->assertJsonPath('data.plans.0.billing_periods.0.period', 'monthly')
            ->assertJsonPath('data.plans.0.billing_periods.0.price_minor', 12345);

        $this->assertCount(1, $response->json('data.plans'));
        $this->assertCount(1, $response->json('data.plans.0.billing_periods'));
    }

    public function test_subscription_plans_are_scoped_to_authorized_business_membership(): void
    {
        $this->seedCloudMonthly(12345);

        [$user, $token] = $this->userWithToken();
        $ownedBusiness = Business::factory()->create();
        $otherBusiness = Business::factory()->create();
        $this->attach($user, $ownedBusiness);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$otherBusiness->id)
            ->assertForbidden()
            ->assertJson([
                'message' => 'Business access denied.',
                'code' => 'BUSINESS_ACCESS_DENIED',
            ])
            ->assertJsonMissing(['plans']);
    }

    public function test_subscription_plans_are_read_only(): void
    {
        $this->seedCloudMonthly(12345);

        [$user, $token] = $this->userWithToken();
        $business = Business::factory()->create();
        $this->attach($user, $business);
        $subscription = Subscription::factory()->free()->create(['business_id' => $business->id]);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?'.http_build_query([
                'business_id' => $business->id,
                'plan' => Subscription::PLAN_CLOUD,
                'price_minor' => 1,
            ]))
            ->assertOk();

        $subscription->refresh();
        $this->assertSame(Subscription::PLAN_FREE, $subscription->plan);
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
    }

    public function test_mobile_context_exposes_subscription_dates_additively(): void
    {
        $startsAt = Carbon::parse('2026-09-01 08:00:00', 'UTC');
        $expiresAt = Carbon::parse('2026-10-01 08:00:00', 'UTC');

        [$user, $token] = $this->userWithToken();
        $business = Business::factory()->create();
        $this->attach($user, $business);
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);

        $this->withToken($token)
            ->getJson('/api/mobile/context')
            ->assertOk()
            ->assertJsonPath('data.businesses.0.subscription.plan', Subscription::PLAN_CLOUD)
            ->assertJsonPath('data.businesses.0.subscription.status', Subscription::STATUS_ACTIVE)
            ->assertJsonPath('data.businesses.0.subscription.starts_at', $startsAt->toJSON())
            ->assertJsonPath('data.businesses.0.subscription.expires_at', $expiresAt->toJSON());
    }

    public function test_free_active_cloud_expired_and_non_active_cloud_entitlements_are_authoritative(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 09:00:00', 'Asia/Jakarta'));

        try {
            [$user, $token] = $this->userWithToken();

            $freeBusiness = Business::factory()->create(['name' => 'Free']);
            $activeCloudBusiness = Business::factory()->create(['name' => 'Cloud Active']);
            $expiredCloudBusiness = Business::factory()->create(['name' => 'Cloud Expired']);
            $inactiveCloudBusiness = Business::factory()->create(['name' => 'Cloud Inactive']);

            foreach ([$freeBusiness, $activeCloudBusiness, $expiredCloudBusiness, $inactiveCloudBusiness] as $business) {
                $this->attach($user, $business);
            }

            Subscription::factory()->free()->create(['business_id' => $freeBusiness->id]);
            Subscription::factory()->cloud()->create(['business_id' => $activeCloudBusiness->id]);
            Subscription::factory()->cloud()->create([
                'business_id' => $expiredCloudBusiness->id,
                'expires_at' => now()->subSecond(),
            ]);
            Subscription::factory()->cloud()->create([
                'business_id' => $inactiveCloudBusiness->id,
                'status' => Subscription::STATUS_INACTIVE,
                'expires_at' => now()->addMonth(),
            ]);

            $businesses = collect($this->withToken($token)
                ->getJson('/api/mobile/context')
                ->assertOk()
                ->json('data.businesses'))
                ->keyBy('name');

            $this->assertFalse($businesses['Free']['cloud_access']);
            $this->assertTrue($businesses['Cloud Active']['cloud_access']);
            $this->assertFalse($businesses['Cloud Expired']['cloud_access']);
            $this->assertFalse($businesses['Cloud Inactive']['cloud_access']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
