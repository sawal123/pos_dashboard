<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DeviceManagementService;
use App\Services\Platform\PlatformAuditLogger;
use App\Services\Subscription\CloudDeviceLimit;
use App\Support\PlatformAuditAction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ADMIN-17 & P38 concurrency gate for Device Quota in MySQL.
 *
 * Runs concurrent device reactivation and registration in TWO separate PHP processes
 * against the dedicated MySQL test database to prove parent row-locking (lockForUpdate)
 * guarantees the final device slot cannot be oversold under race conditions.
 *
 * Run with: vendor/bin/phpunit -c phpunit.p38concurrency.xml
 */
class PlatformDeviceConcurrencyMySqlTest extends TestCase
{
    /**
     * @var array{businessId: int, outletId: int, adminId: int, ownerId: int}
     */
    private array $fixture = [];

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped(
                'CONCURRENCY_DB_REQUIRED: this gate only runs against the dedicated MySQL/MariaDB test database.'
            );
        }

        Artisan::call('migrate:fresh', ['--force' => true]);

        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $owner->businesses()->attach($business, ['role' => 'owner']);

        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->fixture = [
            'businessId' => (int) $business->id,
            'outletId' => (int) $outlet->id,
            'adminId' => (int) $admin->id,
            'ownerId' => (int) $owner->id,
        ];
    }

    /**
     * Static helper executed inside concurrent child process for Platform Reactivation.
     *
     * @return array{success: bool, error: string|null}
     */
    public static function concurrentReactivate(int $adminId, int $deviceId): array
    {
        try {
            /** @var User $admin */
            $admin = User::findOrFail($adminId);
            /** @var Device $device */
            $device = Device::findOrFail($deviceId);
            $deviceLimit = app(CloudDeviceLimit::class);
            $auditLogger = app(PlatformAuditLogger::class);

            DB::transaction(function () use ($admin, $device, $deviceLimit, $auditLogger): void {
                /** @var Business $lockedBusiness */
                $lockedBusiness = Business::query()
                    ->whereKey($device->business_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedBusiness->hasCloudAccess()) {
                    throw new \RuntimeException('Bisnis tidak memiliki akses Cloud aktif.');
                }

                if ($deviceLimit->isReached($lockedBusiness)) {
                    throw new \RuntimeException('Batas perangkat Cloud tercapai (maksimal '.$deviceLimit->limit().' perangkat aktif). Nonaktifkan perangkat lain terlebih dahulu.');
                }

                $before = ['status' => $device->status];
                $device->update(['status' => Device::STATUS_ACTIVE]);
                $after = ['status' => Device::STATUS_ACTIVE];

                $auditLogger->record(
                    actor: $admin,
                    action: PlatformAuditAction::DEVICE_REACTIVATED,
                    targetType: PlatformAuditAction::TARGET_DEVICE,
                    targetId: $device->id,
                    targetLabel: $device->name,
                    businessId: $device->business_id,
                    before: $before,
                    after: $after,
                    metadata: [
                        'device_identifier' => $device->identifier,
                    ],
                );
            });

            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Static helper executed inside concurrent child process for Dashboard Device Registration.
     *
     * @param  array{name: string, identifier: string, outlet_id: int}  $data
     * @return array{success: bool, error: string|null}
     */
    public static function concurrentRegister(int $businessId, array $data): array
    {
        try {
            $business = Business::findOrFail($businessId);
            $service = app(DeviceManagementService::class);
            $service->register($business, $data);

            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Critical Race Test: Two simultaneous reactivations competing for the final 5th slot.
     *
     * Given: limit = 5, active = 4, inactive = 2 (Device A and Device B).
     * When: Request A reactivates Device A and Request B reactivates Device B simultaneously.
     * Then:
     *  - Exactly ONE request succeeds.
     *  - The OTHER request fails with quota error.
     *  - Total active devices is exactly 5 (never 6).
     *  - Exactly ONE audit record is created (the failed transaction rolls back cleanly).
     */
    public function test_concurrent_device_reactivation_cannot_oversell_final_quota_slot(): void
    {
        $businessId = $this->fixture['businessId'];
        $outletId = $this->fixture['outletId'];
        $adminId = $this->fixture['adminId'];

        // Populate 4 active devices (occupying slots 1..4)
        for ($i = 1; $i <= 4; $i++) {
            Device::create([
                'business_id' => $businessId,
                'outlet_id' => $outletId,
                'name' => "POS Active {$i}",
                'identifier' => "POS-ACTIVE-0{$i}",
                'status' => Device::STATUS_ACTIVE,
                'registered_at' => now(),
            ]);
        }

        // Two inactive devices competing for the 5th (final) slot
        $devA = Device::create([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'name' => 'POS Competing A',
            'identifier' => 'POS-INACTIVE-A',
            'status' => Device::STATUS_INACTIVE,
            'registered_at' => now(),
        ]);

        $devB = Device::create([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'name' => 'POS Competing B',
            'identifier' => 'POS-INACTIVE-B',
            'status' => Device::STATUS_INACTIVE,
            'registered_at' => now(),
        ]);

        $auditCountBefore = PlatformAuditLog::where('action', PlatformAuditAction::DEVICE_REACTIVATED)->count();

        // Execute concurrently in 2 separate PHP processes against real MySQL
        [$resultA, $resultB] = Concurrency::run([
            fn () => self::concurrentReactivate($adminId, (int) $devA->id),
            fn () => self::concurrentReactivate($adminId, (int) $devB->id),
        ]);

        $this->assertIsArray($resultA);
        $this->assertIsArray($resultB);

        // Exactly one process succeeds, one fails
        $successes = array_filter([$resultA, $resultB], fn ($r) => $r['success'] === true);
        $failures = array_filter([$resultA, $resultB], fn ($r) => $r['success'] === false);

        $this->assertCount(1, $successes, 'Expected exactly one reactivation to succeed.');
        $this->assertCount(1, $failures, 'Expected exactly one reactivation to be rejected due to quota.');

        /** @var array{success: bool, error: string|null} $failedResult */
        $failedResult = array_values($failures)[0];
        $this->assertStringContainsString('Batas perangkat Cloud tercapai', (string) $failedResult['error']);

        // Assert final database state: count must be exactly 5, never 6
        $activeCount = Device::where('business_id', $businessId)->where('status', Device::STATUS_ACTIVE)->count();
        $this->assertSame(5, $activeCount, 'Device count must not exceed limit 5.');

        // Exactly 1 new audit log record must be created
        $auditCountAfter = PlatformAuditLog::where('action', PlatformAuditAction::DEVICE_REACTIVATED)->count();
        $this->assertSame($auditCountBefore + 1, $auditCountAfter, 'Failed transaction must not produce audit records.');
    }

    /**
     * Critical Race Test: Two simultaneous registrations competing for the final 5th slot.
     */
    public function test_concurrent_device_registration_cannot_oversell_final_quota_slot(): void
    {
        $businessId = $this->fixture['businessId'];
        $outletId = $this->fixture['outletId'];

        // Populate 4 active devices (occupying slots 1..4)
        for ($i = 1; $i <= 4; $i++) {
            Device::create([
                'business_id' => $businessId,
                'outlet_id' => $outletId,
                'name' => "POS Active Reg {$i}",
                'identifier' => "POS-ACT-REG-0{$i}",
                'status' => Device::STATUS_ACTIVE,
                'registered_at' => now(),
            ]);
        }

        $regA = [
            'name' => 'Concurrent Device X',
            'identifier' => 'POS-NEW-RACE-X',
            'outlet_id' => $outletId,
        ];
        $regB = [
            'name' => 'Concurrent Device Y',
            'identifier' => 'POS-NEW-RACE-Y',
            'outlet_id' => $outletId,
        ];

        // Execute concurrently in 2 separate PHP processes against real MySQL
        [$resultA, $resultB] = Concurrency::run([
            fn () => self::concurrentRegister($businessId, $regA),
            fn () => self::concurrentRegister($businessId, $regB),
        ]);

        $this->assertIsArray($resultA);
        $this->assertIsArray($resultB);

        $successes = array_filter([$resultA, $resultB], fn ($r) => $r['success'] === true);
        $failures = array_filter([$resultA, $resultB], fn ($r) => $r['success'] === false);

        $this->assertCount(1, $successes, 'Expected exactly one registration to succeed.');
        $this->assertCount(1, $failures, 'Expected exactly one registration to fail due to quota.');

        // Final active count in DB must be exactly 5, never 6
        $activeCount = Device::where('business_id', $businessId)->where('status', Device::STATUS_ACTIVE)->count();
        $this->assertSame(5, $activeCount, 'Device count must not exceed limit 5.');
    }
}
