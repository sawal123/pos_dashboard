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
            ->assertSee('Cloud Yearly');
    }
}
