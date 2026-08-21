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
 * @property string $plan
 * @property string $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 */
#[Fillable(['business_id', 'plan', 'status', 'starts_at', 'expires_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'plan' => 'free',
        'status' => 'active',
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
     * Determine if the subscription is on the Free plan.
     */
    public function isFree(): bool
    {
        return $this->plan === 'free';
    }

    /**
     * Determine if the subscription is on the Cloud plan.
     */
    public function isCloud(): bool
    {
        return $this->plan === 'cloud';
    }

    /**
     * Determine if the subscription is expired.
     */
    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
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

        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->lte(now())) {
            return false;
        }

        return true;
    }
}
