<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(5000, 100000);
        $quantity = fake()->numberBetween(1, 5);
        $lineTotal = $unitPrice * $quantity;

        return [
            'business_id' => Business::factory(),
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->word().' '.fake()->word(),
            'product_sku' => 'SKU-'.strtoupper(Str::random(6)),
            'unit_price' => $unitPrice,
            'quantity' => (string) $quantity,
            'unit' => 'pcs',
            'kind' => 'product',
            'pricing_unit' => 'pcs',
            'line_total' => $lineTotal,
            'cost_snapshot' => round($unitPrice * 0.5, 2),
            'line_cost' => round($unitPrice * 0.5 * $quantity, 2),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ];
    }

    /**
     * Create a sale item with a decimal quantity (e.g., by weight).
     */
    public function byWeight(float $kg = 1.0, string $unit = 'kg'): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => number_format($kg, 3, '.', ''),
            'unit' => $unit,
            'pricing_unit' => $unit,
            'line_total' => (int) ($attributes['unit_price'] * $kg),
        ]);
    }
}
