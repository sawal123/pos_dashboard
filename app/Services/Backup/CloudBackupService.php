<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\Business;
use App\Models\CloudBackup;
use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * PREM-D03 — private Cloud Backup snapshot storage.
 *
 * The server is a validated, immutable, private snapshot store. It never
 * interprets or mutates the POS payload: the exact byte string the mobile app
 * uploads is stored verbatim, after size + SHA-256 verification. It never
 * restores a snapshot into the POS — restore stays on the device (PREM-M06).
 */
final class CloudBackupService
{
    public function __construct(
        private readonly CloudBackupRetention $retention,
    ) {}

    /**
     * Verify and persist a snapshot, then run retention.
     *
     * The declared size and checksum are both re-derived server-side; the client
     * is never trusted. The whole write happens under a per-business lock so
     * concurrent uploads cannot race retention, without locking the whole table.
     *
     * @param  array<string, mixed>  $data  validated upload payload
     */
    public function store(Business $business, Device $device, array $data): CloudBackup
    {
        $payload = (string) $data['payload'];
        $actualBytes = strlen($payload);
        $maxBytes = $this->maxBytes();

        if ($actualBytes > $maxBytes || (int) $data['size_bytes'] > $maxBytes) {
            abort(response()->json([
                'message' => 'Backup exceeds the maximum size of '.$maxBytes.' bytes.',
                'code' => 'BACKUP_TOO_LARGE',
                'max_bytes' => $maxBytes,
            ], 422));
        }

        if ((int) $data['size_bytes'] !== $actualBytes) {
            abort(response()->json([
                'message' => 'Declared size does not match the uploaded payload.',
                'code' => 'BACKUP_SIZE_MISMATCH',
            ], 422));
        }

        // The server hashes the exact bytes it will store; the database only
        // ever holds this verified value.
        $checksum = hash('sha256', $payload);

        if (! hash_equals($checksum, strtolower((string) $data['checksum_sha256']))) {
            abort(response()->json([
                'message' => 'Backup checksum verification failed.',
                'code' => 'BACKUP_CHECKSUM_MISMATCH',
            ], 422));
        }

        $idempotencyKey = isset($data['idempotency_key']) && is_string($data['idempotency_key']) && $data['idempotency_key'] !== ''
            ? $data['idempotency_key']
            : null;

        // Path is fully server-generated (business id + uuid): never client
        // controlled, so path traversal is impossible.
        $disk = $this->disk();
        $uuid = (string) Str::uuid();
        $path = $business->id.'/'.$uuid.'.json';

        /** @var CloudBackup|null $result */
        $result = null;

        try {
            DB::transaction(function () use ($business, $device, $data, $idempotencyKey, $disk, $uuid, $path, $payload, $checksum, $actualBytes, &$result): void {
                Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();

                if ($idempotencyKey !== null) {
                    $existing = CloudBackup::query()
                        ->where('business_id', $business->id)
                        ->where('device_id', $device->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing instanceof CloudBackup) {
                        $result = $existing;

                        return;
                    }
                }

                if (Storage::disk($disk)->put($path, $payload) === false) {
                    throw new RuntimeException('Unable to write the backup snapshot to private storage.');
                }

                $result = CloudBackup::create([
                    'uuid' => $uuid,
                    'business_id' => $business->id,
                    'device_id' => $device->id,
                    'device_identifier' => $device->identifier,
                    'schema_version' => (int) $data['schema_version'],
                    'app_version' => isset($data['app_version']) ? (string) $data['app_version'] : null,
                    'size_bytes' => $actualBytes,
                    'checksum_sha256' => $checksum,
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                    'status' => CloudBackup::STATUS_READY,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $this->retention->prune($business);
            });
        } catch (Throwable $exception) {
            // Fail closed: remove any orphaned object and never leave a READY
            // record behind. The new backup's failure never touches old backups.
            $this->deleteQuietly($disk, $path);

            Log::error('cloud_backup.failed', [
                'business_id' => $business->id,
                'device_id' => $device->id,
                'size_bytes' => $actualBytes,
                'disk' => $disk,
                'exception' => $exception->getMessage(),
            ]);

            abort(response()->json([
                'message' => 'The backup could not be stored.',
                'code' => 'BACKUP_STORAGE_FAILED',
            ], 500));
        }

        if (! $result instanceof CloudBackup) {
            abort(response()->json([
                'message' => 'The backup could not be stored.',
                'code' => 'BACKUP_STORAGE_FAILED',
            ], 500));
        }

        if ($result->wasRecentlyCreated) {
            Log::info('cloud_backup.created', [
                'backup_id' => $result->id,
                'business_id' => $result->business_id,
                'device_id' => $result->device_id,
                'size_bytes' => $result->size_bytes,
                'status' => $result->status,
            ]);
        }

        return $result;
    }

    /**
     * Maximum accepted snapshot size in bytes (application-layer hard limit).
     */
    private function maxBytes(): int
    {
        $configured = config('premium.backup.max_bytes', 25 * 1024 * 1024);

        if (is_int($configured) && $configured > 0) {
            return $configured;
        }

        return 25 * 1024 * 1024;
    }

    /**
     * The private disk that stores snapshots.
     */
    private function disk(): string
    {
        $disk = config('premium.backup.disk', 'cloud_backups');

        return is_string($disk) && $disk !== '' ? $disk : 'cloud_backups';
    }

    private function deleteQuietly(string $disk, string $path): void
    {
        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable) {
            // Best-effort orphan cleanup; never mask the original failure.
        }
    }
}
