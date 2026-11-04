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
}
