<?php

namespace App\Services\Devices;

use App\Exceptions\DevicePairingException;
use App\Models\Business;
use App\Models\Device;
use App\Models\DevicePairingCode;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Subscription\CloudDeviceLimit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Kasir device pairing — kode verifikasi 4 digit.
 *
 * Flow (owner + cashier, cloud plan only):
 *  1. The owner generates a code in the dashboard (TTL 30 menit, one active code
 *     per outlet at a time).
 *  2. The cashier redeems it from the POS app together with the device facts
 *     (name, stable identifier, platform, app version, IP/user-agent).
 *  3. The server creates/binds the device row for the code's outlet in one
 *     transaction and marks the code as used.
 *
 * Security rules enforced here, never in the client:
 *  - cloud entitlement is required to issue *and* to redeem;
 *  - a code is single-use, revocable, and expires after 30 minutes;
 *  - only a keyed hash is stored, scoped per business;
 *  - redemption is rate limited per cashier/business to stop 4-digit brute force;
 *  - the cloud device limit is enforced on new devices.
 */
class DevicePairingService
{
    /** Wrong-code attempts allowed per cashier/business inside the window. */
    public const MAX_ATTEMPTS = 5;

    /** Attempt window, in seconds. */
    public const ATTEMPT_DECAY_SECONDS = 900;

    private const ATTEMPT_KEY_PREFIX = 'device-pairing:';

    public function __construct(
        private readonly CloudDeviceLimit $deviceLimit,
    ) {}

    /**
     * Issue a new 4-digit pairing code for an outlet.
     *
     * Any previous unused code for the same business+outlet is revoked, so the
     * owner always hands out exactly one valid code.
     *
     * @return array{code: string, pairing: DevicePairingCode}
     *
     * @throws DevicePairingException
     */
    public function issue(Business $business, int $outletId, User $actor): array
    {
        $outlet = Outlet::query()
            ->where('business_id', $business->id)
            ->whereKey($outletId)
            ->first();

        if (! $outlet instanceof Outlet) {
            throw new DevicePairingException(
                'PAIRING_OUTLET_INVALID',
                'Outlet tidak ditemukan pada bisnis ini.',
            );
        }

        if ($outlet->status !== 'active') {
            throw new DevicePairingException(
                'PAIRING_OUTLET_INACTIVE',
                'Outlet tidak aktif. Aktifkan outlet terlebih dahulu.',
            );
        }

        if (! $business->hasCloudAccess()) {
            throw new DevicePairingException(
                'CLOUD_SUBSCRIPTION_REQUIRED',
                'Kode pairing hanya tersedia untuk paket Cloud aktif.',
                403,
            );
        }

        return DB::transaction(function () use ($business, $outlet, $actor): array {
            DevicePairingCode::query()
                ->where('business_id', $business->id)
                ->where('outlet_id', $outlet->id)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $code = $this->generateUniqueCode($business);

            $pairing = DevicePairingCode::create([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'code_hash' => DevicePairingCode::hash($code, $business->id),
                'created_by' => $actor->id,
                'expires_at' => now()->addMinutes(DevicePairingCode::TTL_MINUTES),
            ]);

            return ['code' => $code, 'pairing' => $pairing];
        });
    }

    /**
     * Redeem a pairing code for the cashier's device.
     *
     * @param  array{identifier: string, name: string, platform?: string|null, notes?: string|null, app_version?: string|null}  $device
     * @param  array{ip?: string|null, user_agent?: string|null}  $security
     *
     * @throws DevicePairingException
     */
    public function redeem(
        Business $business,
        string $code,
        array $device,
        User $cashier,
        array $security = [],
    ): Device {
        $normalized = trim($code);

        if (preg_match('/^\d{'.DevicePairingCode::CODE_LENGTH.'}$/', $normalized) !== 1) {
            throw new DevicePairingException(
                'PAIRING_CODE_INVALID',
                'Kode pairing harus '.DevicePairingCode::CODE_LENGTH.' digit angka.',
            );
        }

        $attemptKey = $this->attemptKey($business, $cashier);

        if (RateLimiter::tooManyAttempts($attemptKey, self::MAX_ATTEMPTS)) {
            throw new DevicePairingException(
                'PAIRING_RATE_LIMITED',
                'Terlalu banyak percobaan kode. Tunggu '.$this->retryAfterMinutes($attemptKey).' menit lalu coba lagi.',
                429,
            );
        }

        try {
            $redeemed = DB::transaction(function () use (
                $business,
                $device,
                $cashier,
                $normalized,
                $security,
            ): Device {
                /** @var Business $lockedBusiness */
                $lockedBusiness = Business::query()
                    ->whereKey($business->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedBusiness->hasCloudAccess()) {
                    throw new DevicePairingException(
                        'CLOUD_SUBSCRIPTION_REQUIRED',
                        'Paket Cloud tidak aktif. Pairing perangkat dibatalkan.',
                        403,
                    );
                }

                /** @var DevicePairingCode|null $pairing */
                $pairing = DevicePairingCode::query()
                    ->where('business_id', $lockedBusiness->id)
                    ->where('code_hash', DevicePairingCode::hash($normalized, $lockedBusiness->id))
                    ->lockForUpdate()
                    ->first();

                if (! $pairing instanceof DevicePairingCode) {
                    throw new DevicePairingException(
                        'PAIRING_CODE_INVALID',
                        'Kode pairing tidak valid atau sudah tidak berlaku.',
                    );
                }

                if ($pairing->used_at !== null) {
                    throw new DevicePairingException(
                        'PAIRING_CODE_ALREADY_USED',
                        'Kode pairing ini sudah dipakai. Minta kode baru dari pemilik.',
                        409,
                    );
                }

                if ($pairing->revoked_at !== null) {
                    throw new DevicePairingException(
                        'PAIRING_CODE_REVOKED',
                        'Kode pairing sudah dibatalkan. Minta kode baru dari pemilik.',
                        410,
                    );
                }

                if ($pairing->isExpired()) {
                    throw new DevicePairingException(
                        'PAIRING_CODE_EXPIRED',
                        'Kode pairing sudah kedaluwarsa (berlaku '.DevicePairingCode::TTL_MINUTES.' menit). Minta kode baru dari pemilik.',
                        410,
                    );
                }

                $identifier = trim($device['identifier']);

                /** @var Device|null $existing */
                $existing = Device::query()
                    ->where('business_id', $lockedBusiness->id)
                    ->where('identifier', $identifier)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof Device && $existing->status !== Device::STATUS_ACTIVE) {
                    throw new DevicePairingException(
                        'DEVICE_INACTIVE',
                        'Perangkat ini dinonaktifkan. Minta pemilik mengaktifkannya kembali di Dashboard.',
                        403,
                    );
                }

                if ($existing instanceof Device && (int) $existing->outlet_id !== (int) $pairing->outlet_id) {
                    throw new DevicePairingException(
                        'DEVICE_OUTLET_MISMATCH',
                        'Perangkat ini sudah terdaftar pada outlet lain. Minta pemilik memindahkannya lewat Dashboard.',
                        409,
                    );
                }

                if (! $existing instanceof Device) {
                    if ($this->deviceLimit->isReached($lockedBusiness)) {
                        throw new DevicePairingException(
                            'CLOUD_DEVICE_LIMIT_REACHED',
                            'Batas perangkat Cloud tercapai (maksimal '.$this->deviceLimit->limit().' perangkat aktif). Nonaktifkan perangkat lain terlebih dahulu.',
                            403,
                        );
                    }

                    try {
                        $existing = Device::create([
                            'business_id' => $lockedBusiness->id,
                            'outlet_id' => $pairing->outlet_id,
                            'name' => $device['name'],
                            'identifier' => $identifier,
                            'platform' => $device['platform'] ?? null,
                            'status' => Device::STATUS_ACTIVE,
                            'registered_at' => now(),
                            'last_seen_at' => now(),
                            'notes' => $device['notes'] ?? null,
                        ]);
                    } catch (UniqueConstraintViolationException) {
                        $existing = Device::query()
                            ->where('business_id', $lockedBusiness->id)
                            ->where('identifier', $identifier)
                            ->firstOrFail();
                    }
                }

                $existing->forceFill([
                    'paired_user_id' => $cashier->id,
                    'paired_at' => now(),
                    'last_ip' => $security['ip'] ?? null,
                    'user_agent' => $security['user_agent'] ?? null,
                    'app_version' => $device['app_version'] ?? null,
                    'last_seen_at' => now(),
                ])->save();

                $pairing->forceFill([
                    'used_at' => now(),
                    'used_device_id' => $existing->id,
                    'used_by' => $cashier->id,
                ])->save();

                return $existing;
            });
        } catch (DevicePairingException $exception) {
            if ($exception->errorCode === 'PAIRING_CODE_INVALID') {
                RateLimiter::hit($attemptKey, self::ATTEMPT_DECAY_SECONDS);
            }

            throw $exception;
        }

        RateLimiter::clear($attemptKey);

        return $redeemed;
    }

    /**
     * Currently redeemable codes for the business (newest first).
     *
     * @return Collection<int, DevicePairingCode>
     */
    public function activeCodes(Business $business)
    {
        return DevicePairingCode::query()
            ->active()
            ->where('business_id', $business->id)
            ->with('outlet')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Revoke an unused code. Returns false when it belongs to another business.
     */
    public function revoke(Business $business, int $pairingId): bool
    {
        $pairing = DevicePairingCode::query()
            ->where('business_id', $business->id)
            ->whereKey($pairingId)
            ->first();

        if (! $pairing instanceof DevicePairingCode) {
            return false;
        }

        if ($pairing->used_at === null && $pairing->revoked_at === null) {
            $pairing->forceFill(['revoked_at' => now()])->save();
        }

        return true;
    }

    /**
     * Human-readable expiry for the dashboard.
     */
    public function expiresAt(DevicePairingCode $pairing): Carbon
    {
        return $pairing->expires_at;
    }

    /**
     * @throws DevicePairingException
     */
    private function generateUniqueCode(Business $business): string
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $code = str_pad(
                (string) random_int(0, (10 ** DevicePairingCode::CODE_LENGTH) - 1),
                DevicePairingCode::CODE_LENGTH,
                '0',
                STR_PAD_LEFT,
            );

            $taken = DevicePairingCode::query()
                ->where('business_id', $business->id)
                ->where('code_hash', DevicePairingCode::hash($code, $business->id))
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists();

            if (! $taken) {
                return $code;
            }
        }

        throw new DevicePairingException(
            'PAIRING_CODE_UNAVAILABLE',
            'Kode pairing tidak dapat dibuat saat ini. Coba lagi.',
            503,
        );
    }

    private function attemptKey(Business $business, User $cashier): string
    {
        return self::ATTEMPT_KEY_PREFIX.$business->id.':'.$cashier->id;
    }

    private function retryAfterMinutes(string $key): int
    {
        return max(1, (int) ceil(RateLimiter::availableIn($key) / 60));
    }
}
