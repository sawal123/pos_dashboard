<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A pending or historical invitation for an email address to join a business.
 *
 * The plaintext token is never stored: only {@see self::token_hash()} is
 * persisted, and it is looked up by hashing the presented token. The token is
 * single-use, time-limited and revocable.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $invited_by
 * @property string $email
 * @property string $role
 * @property string $token_hash
 * @property string $status
 * @property string|null $active_key
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read User|null $inviter
 */
#[Fillable([
    'business_id',
    'invited_by',
    'email',
    'role',
    'token_hash',
    'status',
    'active_key',
    'expires_at',
    'accepted_at',
    'revoked_at',
])]
class BusinessInvitation extends Model
{
    /** The only role an invitation may grant in DASH-10B1. */
    public const ROLE_MEMBER = Business::ROLE_MEMBER;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    /** Invitation time-to-live in days. */
    public const TTL_DAYS = 7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Generate a new cryptographically random plaintext token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Hash a plaintext token for storage or lookup.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Deterministic uniqueness key for the one-active-invitation-per-email rule.
     * Derived so the value itself never exposes the raw business id or email.
     */
    public static function activeKeyFor(int $businessId, string $email): string
    {
        return hash('sha256', $businessId.':'.Str::lower(trim($email)));
    }

    /**
     * Resolve an invitation from a plaintext token. Never logs the token.
     */
    public static function findByToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }

        return static::query()
            ->where('token_hash', self::hashToken($token))
            ->first();
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @param  Builder<BusinessInvitation>  $query
     * @return Builder<BusinessInvitation>
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * The status a user should see: a pending invitation past its expiry is
     * presented (and treated) as expired.
     */
    public function effectiveStatus(): string
    {
        if ($this->isPending() && $this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    /**
     * Whether this invitation may still be accepted right now.
     */
    public function isUsable(): bool
    {
        return $this->effectiveStatus() === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->effectiveStatus()) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_ACCEPTED => 'Diterima',
            self::STATUS_EXPIRED => 'Kedaluwarsa',
            self::STATUS_REVOKED => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_MEMBER => 'Anggota',
            default => ucwords(str_replace(['_', '-'], ' ', $this->role)),
        };
    }

    public function markAccepted(): void
    {
        $this->forceFill([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'active_key' => null,
        ])->save();
    }

    public function markRevoked(): void
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
            'active_key' => null,
        ])->save();
    }

    public function markExpired(): void
    {
        $this->forceFill([
            'status' => self::STATUS_EXPIRED,
            'active_key' => null,
        ])->save();
    }
}
