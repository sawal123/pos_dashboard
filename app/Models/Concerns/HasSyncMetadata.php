<?php

namespace App\Models\Concerns;

use App\Services\Sync\SyncSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSyncMetadata
{
    /**
     * Boot the sync metadata trait for a model.
     */
    public static function bootHasSyncMetadata(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('sync_id'))) {
                $model->setAttribute('sync_id', (string) Str::uuid());
            }

            if (empty($model->getAttribute('sync_version'))) {
                $model->setAttribute('sync_version', 1);
            }

            if (empty($model->getAttribute('sync_sequence')) && ! empty($model->getAttribute('business_id'))) {
                $model->setAttribute('sync_sequence', SyncSequence::next((int) $model->getAttribute('business_id')));
            }
        });

        static::updating(function (Model $model): void {
            // Only auto-increment version if not explicitly set
            if (! $model->isDirty('sync_version')) {
                $currentVersion = (int) ($model->getAttribute('sync_version') ?? 1);
                $model->setAttribute('sync_version', $currentVersion + 1);
            }

            if (! $model->isDirty('sync_sequence') && ! empty($model->getAttribute('business_id'))) {
                $model->setAttribute('sync_sequence', SyncSequence::next((int) $model->getAttribute('business_id')));
            }
        });
    }
}
