<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Device;
use App\Services\Subscription\CloudDeviceLimit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DASH-17 — owner-only device registration & management from the dashboard.
 *
 * Dashboard registration is a **pre-registration by identifier**: it records
 * that a device *should* exist for an outlet. It is not secure pairing, not a
 * hardware attestation, and not proof the physical device is connected — so
 * `last_seen_at` is deliberately left null until the device itself calls the
 * mobile API.
 *
 * Outlet assignment is immutable here: moving a device between outlets would
 * change which outlet's data the device pulls and would invalidate the
 * historical outlet scope of its sync/sales history, so DASH-17 does not allow
 * it (see docs/dashboard/DASH17_DEVICE_MANAGEMENT.md).
 *
 * PREM-D02A: the cloud device limit is enforced here as well as in the mobile
 * API, so the dashboard cannot be used to bypass it.
 */
class DeviceManagementService
{
    /** @var list<string> */
    public const STATUSES = Device::STATUSES;

    public function __construct(
        private readonly CloudDeviceLimit $deviceLimit,
    ) {}

    /**
     * Register (or resolve) a device by identifier.
     *
     * Idempotent per (business_id, identifier):
     *  - not registered           → create it (active, last_seen_at null);
     *  - registered on same outlet → return it unchanged (never reactivate,
     *                                never touch last_seen_at or metadata);
     *  - registered on another outlet → validation error (outlet mismatch).
     *
     * @param  array<string, mixed>  $data
     * @return array{device: Device, created: bool}
     */
    public function register(Business $business, array $data): array
    {
        return DB::transaction(function () use ($business, $data): array {
            /** @var Business $lockedBusiness */
            $lockedBusiness = Business::query()
                ->whereKey($business->id)
                ->lockForUpdate()
                ->firstOrFail();

            $identifier = trim((string) ($data['identifier'] ?? ''));
            $outletId = (int) ($data['outlet_id'] ?? 0);

            $existing = Device::query()
                ->where('business_id', $lockedBusiness->id)
                ->where('identifier', $identifier)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $this->resolveExisting($existing, $outletId);
            }

            if (! $lockedBusiness->hasCloudAccess()) {
                throw ValidationException::withMessages([
                    'identifier' => 'Langganan Cloud aktif diperlukan untuk mendaftarkan perangkat baru.',
                ]);
            }

            $this->guardDeviceLimit($lockedBusiness);

            try {
                $device = Device::create([
                    'business_id' => $lockedBusiness->id,
                    'outlet_id' => $outletId,
                    'name' => trim((string) ($data['name'] ?? '')),
                    'identifier' => $identifier,
                    'platform' => $this->nullableText($data['platform'] ?? null),
                    'status' => 'active',
                    'registered_at' => now(),
                    'last_seen_at' => null,
                    'notes' => $this->nullableText($data['notes'] ?? null),
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request won the INSERT race — resolve the winner's
                // row instead of surfacing a 500. Safe because we never mutate the
                // existing row here.
                $device = Device::query()
                    ->where('business_id', $lockedBusiness->id)
                    ->where('identifier', $identifier)
                    ->firstOrFail();

                return $this->resolveExisting($device, $outletId);
            }

            return ['device' => $device, 'created' => true];
        });
    }

    /**
     * Update only the safe metadata (name + notes). The identifier and outlet
     * are immutable from the dashboard.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMetadata(Device $device, array $data): Device
    {
        $device->name = trim((string) ($data['name'] ?? $device->name));
        $device->notes = $this->nullableText($data['notes'] ?? null);
        $device->save();

        return $device;
    }

    /**
     * Activate or deactivate a device. Deactivation keeps the identifier,
     * sync history and sales history; tokens are never revoked. The mobile API
     * and sync endpoints already reject inactive devices.
     */
    public function updateStatus(Device $device, string $status): void
    {
        if ($device->status === $status) {
            return;
        }

        $device->status = $status;
        $device->save();
    }

    /**
     * @return array{device: Device, created: bool}
     */
    private function resolveExisting(Device $device, int $outletId): array
    {
        if ((int) $device->outlet_id !== $outletId) {
            throw ValidationException::withMessages([
                'identifier' => 'Identifier ini sudah terdaftar pada outlet lain. Perangkat tidak dipindahkan otomatis.',
            ]);
        }

        return ['device' => $device, 'created' => false];
    }

    /**
     * PREM-D02A — enforce the cloud device limit for NEW registrations only.
     *
     * Re-submitting an existing identifier is an idempotent resolve and never
     * consumes a new slot, so it is not blocked here.
     *
     * @throws ValidationException
     */
    private function guardDeviceLimit(Business $business): void
    {
        if (! $this->deviceLimit->isReached($business)) {
            return;
        }

        throw ValidationException::withMessages([
            'identifier' => 'Batas perangkat Cloud tercapai (maksimal '.$this->deviceLimit->limit().' perangkat aktif). Nonaktifkan perangkat yang tidak dipakai terlebih dahulu.',
        ]);
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
