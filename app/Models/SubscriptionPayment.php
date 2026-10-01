<?php

namespace App\Models;

use Database\Factories\SubscriptionPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $user_id
 * @property string $provider
 * @property string $provider_order_id
 * @property string|null $idempotency_key
 * @property string $plan
 * @property string $billing_period
 * @property string $currency
 * @property int $amount
 * @property string $status
 * @property string|null $snap_token
 * @property string|null $redirect_url
 * @property string|null $provider_transaction_id
 * @property string|null $provider_payment_type
 * @property string|null $provider_transaction_status
 * @property string|null $provider_fraud_status
 * @property array<string, mixed>|null $provider_payload
 * @property Carbon|null $paid_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business $business
 * @property-read User|null $user
 */
#[Fillable([
    'business_id',
    'user_id',
    'provider',
    'provider_order_id',
    'idempotency_key',
    'plan',
    'billing_period',
    'currency',
    'amount',
    'status',
    'snap_token',
    'redirect_url',
    'provider_transaction_id',
    'provider_payment_type',
    'provider_transaction_status',
    'provider_fraud_status',
    'provider_payload',
    'paid_at',
    'expires_at',
    'activated_at',
])]
class SubscriptionPayment extends Model
{
    /** @use HasFactory<SubscriptionPaymentFactory> */
    use HasFactory;

    public const PROVIDER_MIDTRANS = 'midtrans';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    /**
     * @return list<string>
     */
    public static function finalStatuses(): array
    {
        return [
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED,
        ];
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'provider_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
