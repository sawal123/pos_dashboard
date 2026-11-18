<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $outlet_id
 * @property string $scope_key
 * @property string $plan
 * @property string $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 */
#[Fillable(['business_id', 'outlet_id', 'plan', 'status', 'starts_at', 'expires_at'])]
class Subscription extends Model
{
    /**
     * Scope key of the business-level subscription that covers the primary outlet.
     */
    public const SCOPE_PRIMARY = 'primary';

    public const PLAN_FREE = 'free';

    public const PLAN_CLOUD = 'cloud';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_INACTIVE = 'inactive';

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'plan' => self::PLAN_FREE,
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Subscription $subscription): void {
            $subscription->scope_key = self::scopeKeyFor($subscription->outlet_id);
        });
    }

    /**
     * Scope key for an outlet subscription; NULL means the primary/business scope.
     */
    public static function scopeKeyFor(?int $outletId): string
    {
        return $outletId === null ? self::SCOPE_PRIMARY : 'outlet:'.$outletId;
    }

    /**
     * The business that owns the subscription.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The branch outlet covered by this subscription (NULL for the primary scope).
     *
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function isPrimaryScope(): bool
    {
        return $this->outlet_id === null;
    }

    /**
     * Determine if the subscription is on the Free plan.
     */
    public function isFree(): bool
    {
        return $this->plan === self::PLAN_FREE;
    }

    /**
     * Determine if the subscription is on the Cloud plan.
     */
    public function isCloud(): bool
    {
        return $this->plan === self::PLAN_CLOUD;
    }

    /**
     * Determine if the subscription is expired.
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if ($this->expires_at !== null && $this->expires_at->lte(now())) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the subscription grants cloud access.
     */
    public function hasCloudAccess(): bool
    {
        if (! $this->isCloud()) {
            return false;
        }

        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->lte(now())) {
            return false;
        }

        return true;
    }

    /**
     * Canonical plan codes currently modelled by the subscriptions table.
     *
     * @return list<string>
     */
    public static function canonicalPlanCodes(): array
    {
        return [
            self::PLAN_FREE,
            self::PLAN_CLOUD,
        ];
    }
}
