<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable([
    'id',
    'user_id',
    'personal_access_token_id',
    'original_email',
    'original_email_verified_at',
    'new_email',
    'status',
    'verified_at',
])]
class EmailChangeRequest extends Model
{
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_EXPIRED = 'expired';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'original_email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PersonalAccessToken, $this> */
    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING_VERIFICATION;
    }

    public function originalVerificationMatches(?CarbonInterface $verifiedAt): bool
    {
        $original = $this->getAttribute('original_email_verified_at');

        if ($original === null) {
            return $verifiedAt === null;
        }

        return $verifiedAt !== null && Carbon::parse((string) $original)->equalTo($verifiedAt);
    }
}
