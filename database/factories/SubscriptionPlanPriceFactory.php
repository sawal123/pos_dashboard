<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlanPrice>
 */
class SubscriptionPlanPriceFactory extends Factory
{
    protected $model = SubscriptionPlanPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 49000,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the price is disabled.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the billing period.
     */
    public function period(string $period): static
    {
        return $this->state(fn (array $attributes): array => [
            'billing_period' => $period,
        ]);
    }

    /**
     * Set the integer minor-unit amount.
     */
    public function price(int $priceMinor): static
    {
        return $this->state(fn (array $attributes): array => [
            'price_minor' => $priceMinor,
        ]);
    }
}
