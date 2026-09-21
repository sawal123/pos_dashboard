<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(20000, 500000);
        $discount = fake()->optional(0.3, 0)->numberBetween(1000, 50000);
        $tax = fake()->optional(0.3, 0)->numberBetween(1000, 20000);
        $total = max(0, $subtotal - $discount + $tax);

        return [
            'business_id' => Business::factory(),
            'outlet_id' => Outlet::factory(),
            'customer_id' => null,
            'transaction_number' => 'TRX-'.strtoupper(Str::random(8)),
            'status' => 'completed',
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'gross_profit' => round($total * 0.4, 2),
            'payment_method' => fake()->randomElement(['cash', 'qris', 'transfer', 'card']),
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => null,
            'change_amount' => null,
            'order_status' => null,
            'estimated_completed_at' => null,
            'note' => null,
            'customer_snapshot' => null,
            'business_snapshot' => null,
            'sold_at' => now(),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ];
    }

    /**
     * Set the sale as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'paid_at' => null,
        ]);
    }

    /**
     * Set the payment as unpaid.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'unpaid',
            'paid_at' => null,
        ]);
    }

    /**
     * Set payment method to cash (tunai) with cash_received and change_amount.
     */
    public function cash(): static
    {
        return $this->state(function (array $attributes) {
            $total = $attributes['total_amount'];
            $cashReceived = $total + fake()->numberBetween(0, 50000);

            return [
                'payment_method' => 'cash',
                'cash_received' => $cashReceived,
                'change_amount' => $cashReceived - $total,
            ];
        });
    }
}
