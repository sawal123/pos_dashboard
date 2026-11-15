<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Short-lived (30 menit) pairing code for a cashier device.
 *
 * The owner generates a 4-digit code in the dashboard and hands it to the
 * cashier, who redeems it from the POS app. Only a keyed hash of the code is
 * stored, so a database leak cannot be replayed. A code is single-use, can be
 * revoked, and never reveals which cashier or device redeemed it.
 *
 * @property int $id
 * @property int $business_id
 * @property int $outlet_id
 * @property string $code_hash
 * @property int $created_by
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $used_at
 * @property int|null $used_device_id
 * @property int|null $used_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 * @property-read User|null $creator
 * @property-read User|null $user
 * @property-read Device|null $device
 */
#[Fillable([
    'business_id',
    'outlet_id',
    'code_hash',
    'created_by',
    'expires_at',
    'revoked_at',
    'used_at',
    'used_device_id',
    'used_by',
])]
class DevicePairingCode extends Model
{
    /** Digits in a pairing code. */
    public const CODE_LENGTH = 4;

    /** Lifetime of a generated code, in minutes. */
    public const TTL_MINUTES = 30;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Keyed hash of a plaintext code, scoped to the business so an identical
     * 4-digit code in another business can never be redeemed here.
     */
    public static function hash(string $code, int $businessId): string
    {
        return hash_hmac('sha256', $businessId.'|'.$code, (string) config('app.key'));
    }

    /**
     * Whether the code can still be redeemed (unused, not revoked, not expired).
     */
    public function isRedeemable(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->used_at === null
            && $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->greaterThan($now);
    }

    public function isExpired(?Carbon $now = null): bool
    {
        return $this->expires_at === null || $this->expires_at->lessThanOrEqualTo($now ?? now());
    }

    /**
     * Codes that may still be redeemed right now.
     *
     * @param  Builder<DevicePairingCode>  $query
     * @return Builder<DevicePairingCode>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<Outlet, $this> */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'used_device_id');
    }
}
