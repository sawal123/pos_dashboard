<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $personal_access_token_id
 * @property string $purpose
 * @property string $token_digest
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
#[Fillable([
    'user_id',
    'personal_access_token_id',
    'purpose',
    'token_digest',
    'expires_at',
    'consumed_at',
])]
class ReauthenticationGrant extends Model
{
    public const PURPOSE_GOOGLE_LINK = 'google_link';

    /** @var int */
    public const TTL_SECONDS = 300;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
