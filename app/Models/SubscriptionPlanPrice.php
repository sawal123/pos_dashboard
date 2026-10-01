<?php

namespace App\Models;

use Database\Factories\SubscriptionPlanPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PREM-D02C — a single priced billing period for a {@see SubscriptionPlan}.
 *
 * Amounts are stored as integer minor units (IDR: `49000` == Rp49.000). The
 * unique key `(subscription_plan_id, billing_period, currency)` prevents two
 * ambiguous prices for the same offer.
 *
 * @property int $id
 * @property int $subscription_plan_id
 * @property string $billing_period
 * @property string $currency
 * @property int $price_minor
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SubscriptionPlan $plan
 */
#[Fillable(['subscription_plan_id', 'billing_period', 'currency', 'price_minor', 'is_active'])]
class SubscriptionPlanPrice extends Model
{
    /** @use HasFactory<SubscriptionPlanPriceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The plan this price belongs to.
     *
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
