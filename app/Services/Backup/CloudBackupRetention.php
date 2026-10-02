<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\Business;
use App\Models\CloudBackup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * PREM-D03 — automatic retention for private Cloud backups.
 *
 * Retention keeps the newest N READY backups per business. It only ever runs
 * after a new backup has been persisted as READY, and every failure is logged
 * and swallowed: a cleanup problem must never invalidate the new backup or
 * affect another business.
 */
final class CloudBackupRetention
{
    /**
     * Maximum READY backups retained per business.
     */
    public function maxPerBusiness(): int
    {
        $configured = config('premium.backup.retention', 10);
        $max = is_int($configured) ? $configured : (int) $configured;

        return $max > 0 ? $max : 1;
    }

    /**
     * Delete every READY backup older than the retention window.
     *
     * The caller holds the per-business lock, so concurrent uploads for the
     * same business cannot race past this point. Other businesses are untouched.
     */
    public function prune(Business $business): void
    {
        $ready = CloudBackup::query()
            ->where('business_id', $business->id)
            ->where('status', CloudBackup::STATUS_READY)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        // Everything past the configured window is obsolete. `id` is the
        // tie-breaker so identical timestamps still resolve deterministically.
        foreach ($ready->slice($this->maxPerBusiness()) as $backup) {
            $this->removeQuietly($backup);
        }
    }

    private function removeQuietly(CloudBackup $backup): void
    {
        try {
            Storage::disk($backup->storage_disk)->delete($backup->storage_path);
        } catch (Throwable $exception) {
            // The row is still removed below so the READY count stays bounded;
            // the orphaned object is reported for operators to reconcile.
            Log::warning('cloud_backup.retention_file_delete_failed', [
                'backup_id' => $backup->id,
                'business_id' => $backup->business_id,
                'disk' => $backup->storage_disk,
                'exception' => $exception->getMessage(),
            ]);
        }

        try {
            $backup->delete();
        } catch (Throwable $exception) {
            Log::warning('cloud_backup.retention_row_delete_failed', [
                'backup_id' => $backup->id,
                'business_id' => $backup->business_id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
