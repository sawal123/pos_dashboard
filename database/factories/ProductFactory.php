<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
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
            'category_id' => null,
            'name' => fake()->word().' '.fake()->word().' '.fake()->unique()->randomNumber(4, true),
            'sku' => 'SKU-'.fake()->unique()->randomNumber(5, true),
            'barcode' => null,
            'price' => fake()->numberBetween(5000, 150000),
            'kind' => 'product',
            'cost' => fake()->numberBetween(1000, 50000),
            'stock' => fake()->numberBetween(0, 1000),
            'unit' => 'pcs',
            'min_stock' => 0,
            'pricing_unit' => 'pcs',
            'min_quantity' => 0,
            'estimated_duration' => null,
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
