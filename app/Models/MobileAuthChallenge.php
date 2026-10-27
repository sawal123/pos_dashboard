<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'challenge_digest',
    'login_method',
    'failed_attempts',
    'ip_address',
    'expires_at',
    'consumed_at',
    'locked_at',
])]
class MobileAuthChallenge extends Model
{
    public const METHOD_MANUAL = 'manual';

    public const METHOD_GOOGLE = 'google';

    /** @var list<string> */
    public const METHODS = [self::METHOD_MANUAL, self::METHOD_GOOGLE];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->getRawOriginal('expires_at');

        return $expiresAt !== null && Carbon::parse((string) $expiresAt)->isPast();
    }

    public function isUnavailable(): bool
    {
        return $this->consumed_at !== null || $this->locked_at !== null || $this->isExpired();
    }
}
