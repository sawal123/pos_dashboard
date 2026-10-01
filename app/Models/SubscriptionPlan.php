<?php

namespace App\Models;

use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * PREM-D02C — database-managed Premium plan catalog.
 *
 * A plan is descriptive metadata (`code`, `name`). Its purchasable prices live in
 * {@see SubscriptionPlanPrice}; the server never reads a price from config or
 * from the mobile client.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SubscriptionPlanPrice> $prices
 * @property-read Collection<int, SubscriptionPlanPrice> $activePrices
 */
#[Fillable(['code', 'name', 'description', 'is_active'])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Every price row for this plan, active or not.
     *
     * @return HasMany<SubscriptionPlanPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(SubscriptionPlanPrice::class);
    }

    /**
     * Only the price rows that may currently be offered.
     *
     * @return HasMany<SubscriptionPlanPrice, $this>
     */
    public function activePrices(): HasMany
    {
        return $this->hasMany(SubscriptionPlanPrice::class)->where('is_active', true);
    }

    /**
     * Restrict a query to plans that are currently available.
     *
     * @param  Builder<SubscriptionPlan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
