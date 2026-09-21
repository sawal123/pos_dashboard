<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
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
            'outlet_id' => Outlet::factory(),
            'shift_number' => 'SHIFT-'.strtoupper(Str::random(6)),
            'status' => 'open',
            'opening_cash' => fake()->numberBetween(100000, 500000),
            'closing_cash' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => null,
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ];
    }

    /**
     * Create a closed shift.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'closing_cash' => fake()->numberBetween(200000, 600000),
            'closed_at' => now(),
        ]);
    }
}
