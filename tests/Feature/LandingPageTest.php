<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_displays_active_cloud_prices(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'is_active' => true,
        ]);

        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 49000,
            'is_active' => true,
        ]);
        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'yearly',
            'currency' => 'IDR',
            'price_minor' => 490000,
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Rp 49.000')
            ->assertSee('Rp 490.000')
            ->assertSee('Cloud Monthly')
            ->assertSee('Cloud Yearly')
            ->assertSee('Paket Free')
            ->assertSee('Rp 0');
    }

    public function test_landing_page_displays_custom_free_plan_name_when_seeded(): void
    {
        SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_FREE,
            'name' => 'Paket Kasir Gratis',
            'description' => 'Khusus UMKM mandiri',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Paket Kasir Gratis')
            ->assertSee('Khusus UMKM mandiri')
            ->assertSee('Rp 0');
    }

    public function test_landing_page_dynamically_displays_new_plan_created_in_database(): void
    {
        $customPlan = SubscriptionPlan::factory()->create([
            'code' => 'starter',
            'name' => 'Paket Starter UMKM',
            'description' => 'Paket hemat untuk bisnis rintisan',
            'is_active' => true,
        ]);

        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $customPlan->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 29000,
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Paket Starter UMKM')
            ->assertSee('Rp 29.000')
            ->assertSee('Paket hemat untuk bisnis rintisan');
    }

    public function test_landing_page_does_not_display_inactive_plans(): void
    {
        SubscriptionPlan::factory()->create([
            'code' => 'hidden_plan',
            'name' => 'Paket Rahasia',
            'description' => 'Tidak boleh tampil',
            'is_active' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Paket Rahasia')
            ->assertDontSee('Tidak boleh tampil');
    }

    public function test_landing_page_displays_custom_features_from_database(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'is_active' => true,
            'features' => [
                'Keunggulan Custom A',
                'Keunggulan Custom B',
            ],
        ]);

        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 49000,
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Keunggulan Custom A')
            ->assertSee('Keunggulan Custom B');
    }

    public function test_landing_page_buttons_distinguish_free_and_paid_checkout_actions(): void
    {
        $cloudPlan = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'is_active' => true,
        ]);

        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $cloudPlan->id,
            'billing_period' => 'monthly',
            'price_minor' => 49000,
            'is_active' => true,
        ]);

        // Guest sees styled action links
        $responseGuest = $this->get(route('home'))->assertOk();
        $responseGuest->assertSee('pricing-btn-free');
        $responseGuest->assertSee('pricing-btn-monthly');
        $responseGuest->assertDontSee('action="' . route('subscriptions.checkout') . '"', false);

        // Authenticated user sees checkout form for paid plan and dashboard link for free plan
        $user = \App\Models\User::factory()->create();
        $responseAuth = $this->actingAs($user)->get(route('home'))->assertOk();
        $responseAuth->assertSee(route('dashboard'));
        $responseAuth->assertSee('action="' . route('subscriptions.checkout') . '"', false);
    }

    public function test_authenticated_user_sees_dashboard_button_in_nav_header(): void
    {
        // Guest sees Masuk
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Masuk')
            ->assertDontSee('>Dashboard <span', false);

        // Authenticated user sees Dashboard button and does not see Masuk in nav header
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Dashboard <span aria-hidden="true">↗</span>', false)
            ->assertDontSee('<a class="button button-light" href="'.route('login').'">Masuk</a>', false);
    }

    public function test_authenticated_user_with_active_cloud_sees_cloud_as_active_and_no_checkout_form(): void
    {
        $user = \App\Models\User::factory()->create();
        $business = \App\Models\Business::factory()->create();
        $business->users()->attach($user->id, ['role' => \App\Models\Business::ROLE_OWNER]);

        // Active Cloud subscription
        \App\Models\Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => \App\Models\Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $cloudPlan = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'is_active' => true,
        ]);

        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $cloudPlan->id,
            'billing_period' => 'monthly',
            'price_minor' => 49000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession([\App\Services\Dashboard\DashboardBusinessContext::SESSION_KEY => $business->id])
            ->get(route('home'))
            ->assertOk();

        // Must see active badge
        $response->assertSee('✓ Paket Aktif');
        // Must see Buka Dashboard on Cloud
        $response->assertSee('Buka Dashboard');
        // Must see Paket Dasar for free plan
        $response->assertSee('Paket Dasar');
        // Must NOT see checkout form
        $response->assertDontSee('action="' . route('subscriptions.checkout') . '"', false);
    }

    public function test_checkout_is_blocked_if_business_already_has_active_cloud_access_and_not_renewal(): void
    {
        $user = \App\Models\User::factory()->create();
        $business = \App\Models\Business::factory()->create();
        $business->users()->attach($user->id, ['role' => \App\Models\Business::ROLE_OWNER]);

        \App\Models\Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => \App\Models\Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $cloudPlan = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'name' => 'Cloud',
            'is_active' => true,
        ]);

        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $cloudPlan->id,
            'billing_period' => 'monthly',
            'price_minor' => 49000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession([\App\Services\Dashboard\DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('subscriptions.checkout'), [
                'billing_period' => 'monthly',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('checkout');
        $this->assertSame(0, \App\Models\SubscriptionPayment::query()->count());
    }
}
