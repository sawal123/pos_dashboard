<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A persisted, single-use email OTP challenge.
 *
 * This model deliberately has no User or Business relation. AUTH-D03 owns the
 * pending-registration subject and supplies its opaque reference.
 *
 * @property string $id
 * @property string $subject_reference
 * @property string $purpose
 * @property string $email
 * @property string $otp_digest
 * @property string $challenge_nonce
 * @property string|null $active_key
 * @property int $failed_attempts
 * @property int $max_attempts
 * @property CarbonInterface $expires_at
 * @property CarbonInterface $resend_available_at
 * @property CarbonInterface $last_sent_at
 * @property CarbonInterface|null $consumed_at
 * @property CarbonInterface|null $replaced_at
 * @property CarbonInterface|null $locked_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'id',
    'subject_reference',
    'purpose',
    'email',
    'otp_digest',
    'challenge_nonce',
    'active_key',
    'failed_attempts',
    'max_attempts',
    'expires_at',
    'resend_available_at',
    'last_sent_at',
    'consumed_at',
    'replaced_at',
    'locked_at',
    'metadata',
])]
#[Hidden(['otp_digest', 'challenge_nonce', 'active_key'])]
class AuthOtpChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'datetime',
            'resend_available_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'consumed_at' => 'datetime',
            'replaced_at' => 'datetime',
            'locked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function isExpired(?CarbonInterface $at = null): bool
    {
        return $this->expires_at->lte($at ?? now());
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isReplaced(): bool
    {
        return $this->replaced_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->failed_attempts >= $this->max_attempts;
    }

    public function isActive(): bool
    {
        return $this->active_key !== null
            && ! $this->isConsumed()
            && ! $this->isReplaced()
            && ! $this->isLocked()
            && ! $this->isExpired();
    }
}
