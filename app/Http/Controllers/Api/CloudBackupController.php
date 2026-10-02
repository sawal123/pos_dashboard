<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCloudBackupRequest;
use App\Models\CloudBackup;
use App\Models\Device;
use App\Services\Authorization\BusinessPermission;
use App\Services\Backup\CloudBackupContextResolver;
use App\Services\Backup\CloudBackupService;
use App\Services\Subscription\PremiumPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PREM-D03 — private Cloud backup API.
 *
 * Every endpoint reuses the mobile authorization stack: Sanctum `mobile`
 * ability → business membership → role permission → active Cloud entitlement →
 * (optional) active device. Upload/list/detail require `cloud_backup`; the
 * download additionally requires `cloud_restore`. Snapshots are never exposed
 * as public URLs and `storage_path` is never serialized.
 */
class CloudBackupController extends Controller
{
    public function __construct(
        private readonly CloudBackupContextResolver $resolver,
        private readonly CloudBackupService $service,
    ) {}

    public function store(StoreCloudBackupRequest $request): JsonResponse
    {
        $context = $this->resolver->resolve(
            request: $request,
            businessId: (int) $request->input('business_id'),
            deviceIdentifier: (string) $request->input('device_identifier'),
            requireDevice: true,
            rolePermission: BusinessPermission::CLOUD_BACKUP,
            capability: PremiumPolicy::CAPABILITY_CLOUD_BACKUP,
        );

        /** @var Device $device */
        $device = $context['device'];
        $backup = $this->service->store($context['business'], $device, $request->validated());
        $created = $backup->wasRecentlyCreated;

        return response()->json([
            'data' => $this->present($backup) + ['duplicate' => ! $created],
        ], $created ? 201 : 200);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => ['required', 'integer'],
            'device_identifier' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $context = $this->resolver->resolve(
            request: $request,
            businessId: (int) $validated['business_id'],
            deviceIdentifier: isset($validated['device_identifier']) ? (string) $validated['device_identifier'] : null,
            requireDevice: false,
            rolePermission: BusinessPermission::CLOUD_BACKUP,
            capability: PremiumPolicy::CAPABILITY_CLOUD_BACKUP,
        );

        $backups = CloudBackup::query()
            ->with('device')
            ->where('business_id', $context['business']->id)
            ->where('status', CloudBackup::STATUS_READY)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) ($validated['limit'] ?? 10))
            ->get()
            ->map(fn (CloudBackup $backup): array => $this->present($backup))
            ->values();

        return response()->json(['data' => $backups]);
    }

    public function show(Request $request, string $backup): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => ['required', 'integer'],
            'device_identifier' => ['nullable', 'string', 'max:100'],
        ]);

        $context = $this->resolver->resolve(
            request: $request,
            businessId: (int) $validated['business_id'],
            deviceIdentifier: isset($validated['device_identifier']) ? (string) $validated['device_identifier'] : null,
            requireDevice: false,
            rolePermission: BusinessPermission::CLOUD_BACKUP,
            capability: PremiumPolicy::CAPABILITY_CLOUD_BACKUP,
        );

        $record = $this->findScoped($context['business']->id, $backup);

        if (! $record instanceof CloudBackup) {
            return $this->notFound();
        }

        return response()->json(['data' => $this->present($record)]);
    }

    public function download(Request $request, string $backup): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'business_id' => ['required', 'integer'],
            'device_identifier' => ['nullable', 'string', 'max:100'],
        ]);

        // Download is the restore path: it additionally requires cloud_restore.
        $context = $this->resolver->resolve(
            request: $request,
            businessId: (int) $validated['business_id'],
            deviceIdentifier: isset($validated['device_identifier']) ? (string) $validated['device_identifier'] : null,
            requireDevice: false,
            rolePermission: BusinessPermission::CLOUD_RESTORE,
            capability: PremiumPolicy::CAPABILITY_CLOUD_RESTORE,
        );

        $record = $this->findScoped($context['business']->id, $backup);

        if (! $record instanceof CloudBackup || $record->status !== CloudBackup::STATUS_READY) {
            return $this->notFound();
        }

        $disk = Storage::disk($record->storage_disk);

        if (! $disk->exists($record->storage_path)) {
            return $this->fileMissing();
        }

        $stream = $disk->readStream($record->storage_path);

        if ($stream === null) {
            return $this->fileMissing();
        }

        return response()->streamDownload(function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 'cloud-backup-'.$record->uuid.'.json', [
            'Content-Type' => 'application/octet-stream',
            'X-Checksum-Sha256' => $record->checksum_sha256,
            'X-Backup-Schema-Version' => (string) $record->schema_version,
        ]);
    }

    /**
     * Resolve a snapshot strictly within the authorized business. A snapshot of
     * another business is indistinguishable from a missing one (404).
     */
    private function findScoped(int $businessId, string $backupUuid): ?CloudBackup
    {
        return CloudBackup::query()
            ->with('device')
            ->where('business_id', $businessId)
            ->where('uuid', $backupUuid)
            ->first();
    }

    /**
     * Metadata-only presentation. Never includes the payload, `storage_path`,
     * storage disk, or any credential.
     *
     * @return array<string, mixed>
     */
    private function present(CloudBackup $backup): array
    {
        $device = $backup->device;

        return [
            'id' => $backup->id,
            'uuid' => $backup->uuid,
            'created_at' => $backup->created_at?->toIso8601String(),
            'schema_version' => $backup->schema_version,
            'app_version' => $backup->app_version,
            'size_bytes' => $backup->size_bytes,
            'checksum_sha256' => $backup->checksum_sha256,
            'status' => $backup->status,
            'device' => [
                'id' => $backup->device_id,
                'identifier' => $backup->device_identifier,
                'name' => $device?->name,
                'platform' => $device?->platform,
            ],
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'message' => 'Backup not found.',
            'code' => 'BACKUP_NOT_FOUND',
        ], 404);
    }

    private function fileMissing(): JsonResponse
    {
        return response()->json([
            'message' => 'Backup file is missing.',
            'code' => 'BACKUP_FILE_MISSING',
        ], 404);
    }
}
