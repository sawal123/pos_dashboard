<?php

namespace App\Models;

use Database\Factories\PlatformAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only audit trail for Platform Admin mutations.
 *
 * Rows are created on successful administrative actions and are immutable.
 * Model update and delete operations are rejected by Eloquent guards.
 *
 * @property int $id
 * @property int|null $actor_user_id
 * @property string $actor_name
 * @property string $actor_email
 * @property string $action
 * @property string $target_type
 * @property string|null $target_id
 * @property string|null $target_label
 * @property int|null $business_id
 * @property array<string, mixed>|null $before_state
 * @property array<string, mixed>|null $after_state
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property-read User|null $actor
 * @property-read Business|null $business
 */
#[Fillable([
    'actor_user_id',
    'actor_name',
    'actor_email',
    'action',
    'target_type',
    'target_id',
    'target_label',
    'business_id',
    'before_state',
    'after_state',
    'metadata',
    'created_at',
])]
class PlatformAuditLog extends Model
{
    /** @use HasFactory<PlatformAuditLogFactory> */
    use HasFactory;

    /**
     * Append-only: only created_at is tracked.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * Boot the model with immutability protection.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Platform audit logs are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Platform audit logs are immutable and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }
}
