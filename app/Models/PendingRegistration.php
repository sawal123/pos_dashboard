<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable state for the API v2 manual-registration flow.
 *
 * The registration UUID is the only public reference. Idempotency and payload
 * fingerprints are keyed hashes and never expose the original request data.
 */
#[Fillable([
    'id',
    'user_id',
    'normalized_email',
    'idempotency_key_hash',
    'request_fingerprint',
    'business_name_intent',
    'status',
    'provisioning_status',
    'business_id',
    'outlet_id',
    'subscription_id',
    'verified_at',
])]
#[Hidden(['idempotency_key_hash', 'request_fingerprint'])]
class PendingRegistration extends Model
{
    public const STATUS_PENDING_EMAIL = 'pending_email';

    public const STATUS_VERIFIED = 'verified';

    public const PROVISIONING_PENDING = 'pending';

    public const PROVISIONING_COMPLETE = 'complete';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING_EMAIL;
    }

    public function isProvisioned(): bool
    {
        return $this->status === self::STATUS_VERIFIED
            && $this->provisioning_status === self::PROVISIONING_COMPLETE;
    }
}
