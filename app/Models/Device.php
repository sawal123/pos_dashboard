<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $outlet_id
 * @property string $name
 * @property string $identifier
 * @property string|null $platform
 * @property string $status
 * @property Carbon $registered_at
 * @property Carbon|null $last_seen_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 */
#[Fillable(['business_id', 'outlet_id', 'name', 'identifier', 'platform', 'status', 'registered_at', 'last_seen_at', 'notes'])]
class Device extends Model
{
    /** Devices that may register with, and use, the cloud API. */
    public const STATUS_ACTIVE = 'active';

    /**
     * Devices that keep their row, identifier and history but are rejected by
     * the mobile API, sync and (PREM-D02A) the cloud device limit.
     */
    public const STATUS_INACTIVE = 'inactive';

    /**
     * The complete device lifecycle. Devices are never deleted.
     *
     * @var list<string>
     */
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * The business that owns the device.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The outlet where the device is registered.
     *
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
