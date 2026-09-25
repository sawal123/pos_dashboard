<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SubscriptionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    // ============================================================
    // Access control
    // ============================================================

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('subscriptions.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        [$user] = $this->makeOwnerWithBusiness(verified: false);

        $this->actingAs($user)->get(route('subscriptions.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_owner_can_view_the_subscription_page(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Langganan');
        $response->assertSee($business->name);
        $response->assertSee('data-subscriptions-page="true"', false);
    }

    public function test_member_cannot_view_the_subscription_page(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $member = $this->attachMember($business, 'member');

        $this->actingAs($member)->get(route('subscriptions.index'))->assertForbidden();
    }

    public function test_user_without_an_active_business_is_forbidden(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('subscriptions.index'))->assertForbidden();
    }

    // ============================================================
    // Plan / status presentation
    // ============================================================

    public function test_free_plan_is_presented_as_free_without_cloud_access(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Free Aktif');
        $response->assertSee('data-subscription-state="free"', false);
        $this->assertSubscriptionState($response, 'free', false, 'Free', 'Aktif');
    }

    public function test_active_cloud_plan_is_presented_as_active(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => now()->addDays(20),
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Cloud Aktif');
        $response->assertSee('data-subscription-state="cloud_active"', false);
        $this->assertSubscriptionState($response, 'cloud_active', true, 'Cloud', 'Aktif');
    }

    public function test_cloud_plan_past_expiry_is_presented_as_expired(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Cloud Kedaluwarsa');
        $response->assertSee('data-subscription-state="cloud_expired"', false);
        // Expired must never be shown as active.
        $response->assertDontSee('Cloud Aktif');
        $this->assertSubscriptionState($response, 'cloud_expired', false, 'Cloud', 'Aktif');
    }

    public function test_cloud_plan_with_inactive_status_is_presented_as_inactive(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'inactive',
            'expires_at' => now()->addDays(20),
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Cloud Tidak Aktif');
        $response->assertSee('data-subscription-state="cloud_inactive"', false);
        $this->assertSubscriptionState($response, 'cloud_inactive', false, 'Cloud', 'Tidak Aktif');
    }

    public function test_business_without_subscription_record_is_handled_safely(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Belum Ada Langganan');
        $response->assertSee('data-subscription-state="none"', false);
        $this->assertSubscriptionState($response, 'none', false, 'Tidak Ada', '-');
    }

    public function test_unknown_plan_is_presented_as_unknown(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => 'enterprise',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Status Tidak Dikenal');
        $response->assertSee('data-subscription-state="unknown"', false);
        $this->assertSubscriptionState($response, 'unknown', false, 'Enterprise', 'Aktif');
    }

    public function test_starts_and_expiry_dates_are_displayed(): void
    {
        $this->freezeClock('2026-09-01 08:00:00');
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'starts_at' => Carbon::parse('2026-09-01 08:00:00'),
            'expires_at' => Carbon::parse('2026-09-11 08:00:00'),
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('01 Sep 2026 - 08:00');
        $response->assertSee('11 Sep 2026 - 08:00');
        $response->assertSee('10 hari lagi');

        $data = $response->viewData('subscription');
        $this->assertSame('2026-09-01 08:00:00', $data['starts_at_raw']);
        $this->assertSame('2026-09-11 08:00:00', $data['expires_at_raw']);
        $this->assertSame(10, $data['remaining_days']);
    }

    public function test_cloud_entitlement_always_matches_the_model_rule(): void
    {
        $cases = [
            'free_plan' => ['free', 'active', now()->addMonth(), false],
            'cloud_active' => ['cloud', 'active', now()->addMonth(), true],
            'cloud_expired_status' => ['cloud', 'expired', now()->addMonth(), false],
            'cloud_expired_date' => ['cloud', 'active', now()->subDay(), false],
            'cloud_inactive' => ['cloud', 'inactive', now()->addMonth(), false],
            'cloud_no_expiry' => ['cloud', 'active', null, true],
        ];

        foreach ($cases as $label => [$plan, $status, $expiresAt, $expected]) {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $business = Business::factory()->create();
            $user->businesses()->attach($business->id, ['role' => 'owner']);
            Subscription::factory()->create([
                'business_id' => $business->id,
                'plan' => $plan,
                'status' => $status,
                'expires_at' => $expiresAt,
            ]);

            $response = $this->actingAs($user)
                ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
                ->get(route('subscriptions.index'));

            $response->assertOk();
            $data = $response->viewData('subscription');

            $this->assertSame($expected, $data['cloud_access'], "Mismatch for {$label}");
            $this->assertSame($business->fresh()->hasCloudAccess(), $data['cloud_access'], "Model mismatch for {$label}");
        }
    }

    public function test_expiry_boundary_exact_now_is_expired(): void
    {
        $this->freezeClock('2026-09-20 10:00:00');
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-09-20 10:00:00'),
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Cloud Kedaluwarsa');
        $this->assertSubscriptionState($response, 'cloud_expired', false, 'Cloud', 'Aktif');
    }

    public function test_expiry_boundary_one_second_in_the_future_is_active(): void
    {
        $this->freezeClock('2026-09-20 10:00:00');
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-09-20 10:00:01'),
        ]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Cloud Aktif');
        $this->assertSubscriptionState($response, 'cloud_active', true, 'Cloud', 'Aktif');
    }

    // ============================================================
    // Tenant isolation
    // ============================================================

    public function test_forged_business_id_cannot_change_the_displayed_subscription(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $foreign = Business::factory()->create();
        Subscription::factory()->cloud()->create(['business_id' => $foreign->id]);

        $response = $this->actingAs($user)
            ->get(route('subscriptions.index', ['business_id' => $foreign->id]));

        $response->assertOk();
        $data = $response->viewData('subscription');
        $this->assertSame($business->name, $data['business_name']);
        $this->assertSame('free', $data['state']);
        $this->assertFalse($data['cloud_access']);
        $response->assertDontSee('Cloud Aktif');
    }

    public function test_subscription_of_other_business_is_not_exposed(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $foreign = Business::factory()->create(['name' => 'Bisnis Rahasia Lain']);
        Subscription::factory()->cloud()->create(['business_id' => $foreign->id]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertDontSee('Bisnis Rahasia Lain');
        $response->assertDontSee($foreign->name);
        $this->assertSame('free', $response->viewData('subscription')['state']);
    }

    public function test_changing_one_subscription_does_not_affect_another_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $subscriptionB = Subscription::factory()->free()->create(['business_id' => $businessB->id]);
        $a = Subscription::factory()->cloud()->create(['business_id' => $businessA->id]);

        // Business A goes expired.
        $a->update(['status' => 'expired']);

        $responseA = $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $businessA->id])
            ->get(route('subscriptions.index'));
        $responseA->assertOk();
        $this->assertSame('cloud_expired', $responseA->viewData('subscription')['state']);

        $responseB = $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $businessB->id])
            ->get(route('subscriptions.index'));
        $responseB->assertOk();
        $this->assertSame('free', $responseB->viewData('subscription')['state']);
        $this->assertSame($subscriptionB->id, $businessB->fresh()->subscription->id);
    }

    // ============================================================
    // Navigation & no fake purchase
    // ============================================================

    public function test_sidebar_and_manage_plan_link_to_the_subscription_route(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee(route('subscriptions.index'), false);
        $response->assertSee('id="managePlanBtn"', false);
        $response->assertSee('href="'.route('subscriptions.index').'"', false);
        // The Free plan surfaces the upgrade-oriented label, still pointing at this page.
        $response->assertSee('Tingkatkan Paket');
    }

    public function test_page_has_no_fake_purchase_action(): void
    {
        [$user, $business] = $this->makeOwnerWithBusiness();
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $response = $this->actingAs($user)->get(route('subscriptions.index'));

        $response->assertOk();
        $response->assertSee('belum tersedia');
        foreach (['Checkout', 'Invoice', 'Beli Sekarang', 'Bayar Sekarang', 'Berlangganan Sekarang'] as $fakeAction) {
            $response->assertDontSee($fakeAction);
        }
    }

    // ============================================================
    // Existing API cloud gate must keep working
    // ============================================================

    public function test_existing_api_cloud_gate_rejects_free_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => 'owner']);
        Subscription::factory()->free()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Free',
            'identifier' => 'POS-FREE',
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-FREE',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    public function test_existing_api_cloud_gate_allows_active_cloud_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Cloud',
            'identifier' => 'POS-CLOUD',
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'POS-CLOUD',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ])
            ->assertStatus(200);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeOwnerWithBusiness(bool $verified = true): array
    {
        $user = User::factory()->create([
            'email_verified_at' => $verified ? now() : null,
        ]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        if ($verified) {
            $this->withSession([DashboardBusinessContext::SESSION_KEY => $business->id]);
        }

        return [$user, $business];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function attachMember(Business $business, string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $business->users()->attach($user->id, ['role' => $role]);

        return $user;
    }

    private function freezeClock(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime, config('app.timezone')));
    }

    private function assertSubscriptionState(
        TestResponse $response,
        string $expectedState,
        bool $expectedCloudAccess,
        string $expectedPlanLabel,
        string $expectedStatusLabel,
    ): void {
        /** @var array<string, mixed> $data */
        $data = $response->viewData('subscription');

        $this->assertSame($expectedState, $data['state']);
        $this->assertSame($expectedCloudAccess, $data['cloud_access']);
        $this->assertSame($expectedCloudAccess, $data['sync_enabled']);
        $this->assertSame($expectedPlanLabel, $data['plan_label']);
        $this->assertSame($expectedStatusLabel, $data['status_label']);
    }
}
