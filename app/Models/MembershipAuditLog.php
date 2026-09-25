<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit trail for membership and invitation events.
 *
 * Rows are created once and never updated or deleted. Only safe metadata is
 * stored — never plaintext invitation tokens or passwords.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $actor_id
 * @property int|null $target_user_id
 * @property string|null $target_email
 * @property string $action
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property-read Business|null $business
 * @property-read User|null $actor
 * @property-read User|null $target
 */
#[Fillable([
    'business_id',
    'actor_id',
    'target_user_id',
    'target_email',
    'action',
    'metadata',
    'created_at',
])]
class MembershipAuditLog extends Model
{
    public const ACTION_INVITATION_CREATED = 'invitation_created';

    public const ACTION_INVITATION_RESENT = 'invitation_resent';

    public const ACTION_INVITATION_REVOKED = 'invitation_revoked';

    public const ACTION_INVITATION_ACCEPTED = 'invitation_accepted';

    public const ACTION_MEMBERSHIP_REMOVED = 'membership_removed';

    public const ACTION_ROLE_CHANGED = 'role_changed';

    /**
     * Append-only: only created_at is tracked.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * Keys that are allowed to be persisted in metadata. Anything else is
     * dropped so a secret can never leak into the audit trail.
     *
     * @var list<string>
     */
    public const SAFE_METADATA_KEYS = [
        'role',
        'old_role',
        'new_role',
        'invitation_id',
        'was_verified',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
