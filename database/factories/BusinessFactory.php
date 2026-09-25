<?php

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'inactive',
        ];
    }

    /**
     * Mark the business as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Set an explicit business type. There is deliberately NO default type in
     * `definition()`: an unspecified business stays NULL (unknown).
     */
    public function businessType(BusinessType|string $type): static
    {
        $value = $type instanceof BusinessType ? $type->value : BusinessType::normalize($type);

        return $this->state(fn (array $attributes) => [
            'business_type' => $value,
        ]);
    }

    public function cafe(): static
    {
        return $this->businessType(BusinessType::Cafe);
    }

    public function laundry(): static
    {
        return $this->businessType(BusinessType::Laundry);
    }

    public function grosir(): static
    {
        return $this->businessType(BusinessType::Grosir);
    }
}
