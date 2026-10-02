<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ImmutableCloudBackupException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PREM-D03 — an immutable, private snapshot of a mobile local backup.
 *
 * The payload itself lives on a private disk (`storage_path`); this row only
 * holds verified metadata. `storage_path` is internal and is never returned by
 * the mobile API.
 *
 * @property int $id
 * @property string $uuid
 * @property int $business_id
 * @property int $device_id
 * @property string $device_identifier
 * @property int $schema_version
 * @property string|null $app_version
 * @property int $size_bytes
 * @property string $checksum_sha256
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $status
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Device|null $device
 */
#[Fillable([
    'uuid',
    'business_id',
    'device_id',
    'device_identifier',
    'schema_version',
    'app_version',
    'size_bytes',
    'checksum_sha256',
    'storage_disk',
    'storage_path',
    'status',
    'idempotency_key',
])]
class CloudBackup extends Model
{
    /** Uploaded and verified — the only state persisted in this version. */
    public const STATUS_READY = 'ready';

    /** Reserved for a staged upload pipeline; not produced in this version. */
    public const STATUS_PROCESSING = 'processing';

    /** Reserved for a persisted failure state; failures are rejected, not stored. */
    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_READY, self::STATUS_PROCESSING, self::STATUS_FAILED];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_READY,
    ];

    /**
     * A READY snapshot is immutable: no field may ever change after it is
     * persisted. Retention deletes rows; it never updates them.
     */
    protected static function booted(): void
    {
        static::updating(function (CloudBackup $backup): void {
            if ($backup->getOriginal('status') === self::STATUS_READY) {
                throw new ImmutableCloudBackupException('Cloud backup snapshots are immutable once ready.');
            }
        });
    }

    /**
     * The business that owns the snapshot.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The device that produced the snapshot.
     *
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
