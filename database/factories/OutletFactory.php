<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outlet>
 */
class OutletFactory extends Factory
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
            'name' => fake()->city().' Outlet',
            'code' => 'OUT-'.fake()->unique()->randomNumber(5, true),
            'status' => 'active',
            'address' => fake()->address(),
        ];
    }

    /**
     * Indicate that the outlet is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
