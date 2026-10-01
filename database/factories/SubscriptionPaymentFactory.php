<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    protected $model = SubscriptionPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
            'provider_order_id' => 'pdash-test-'.Str::ulid(),
            'plan' => Subscription::PLAN_CLOUD,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'amount' => 100000,
            'status' => SubscriptionPayment::STATUS_PENDING,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}
