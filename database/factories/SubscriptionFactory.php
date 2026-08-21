<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'plan' => 'free',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the subscription is on the free plan.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'free',
            'status' => 'active',
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the subscription is on the active cloud plan.
     */
    public function cloud(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'cloud',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    /**
     * Indicate that the subscription is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }
}
