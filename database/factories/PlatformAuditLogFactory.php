<?php

namespace Database\Factories;

use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Support\PlatformAuditAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAuditLog>
 */
class PlatformAuditLogFactory extends Factory
{
    protected $model = PlatformAuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'actor_name' => fake()->name(),
            'actor_email' => fake()->safeEmail(),
            'action' => PlatformAuditAction::BUSINESS_SUSPENDED,
            'target_type' => PlatformAuditAction::TARGET_BUSINESS,
            'target_id' => (string) fake()->numberBetween(1, 1000),
            'target_label' => fake()->company(),
            'business_id' => null,
            'before_state' => ['status' => 'active'],
            'after_state' => ['status' => 'inactive'],
            'metadata' => ['administrative_override' => true],
            'created_at' => now(),
        ];
    }
}
