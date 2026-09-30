<?php

namespace App\Services\Subscription;

use App\Http\Controllers\Api\MobileDeviceController;
use App\Models\Business;
use App\Models\Device;
use App\Services\Dashboard\DeviceManagementService;
use App\Services\Sync\SyncContextResolver;

/**
 * PREM-D02A — server-side cloud device limit.
 *
 * A Cloud business may use at most `premium.plan.device_limit` devices (5).
 * The limit is enforced by the server on every device *creation* path: the
 * mobile API ({@see MobileDeviceController}) and the
 * dashboard pre-registration ({@see DeviceManagementService}).
 * The mobile UI is never trusted.
 *
 * Counting rule — derived from the existing device lifecycle, nothing invented:
 * a device occupies a slot only while its status is `active`
 * ({@see Device::STATUS_ACTIVE}), which is exactly the status
 * {@see SyncContextResolver} requires for an API call.
 * `inactive` devices keep their row, identifier and history but never consume a
 * slot, so deactivating a device frees one. Re-resolving an existing device is
 * not a new registration and is therefore never blocked.
 */
final class CloudDeviceLimit
{
    public function __construct(
        private readonly PremiumPolicy $policy,
    ) {}

    /**
     * Configured limit; 0 means "no limit configured".
     */
    public function limit(): int
    {
        return $this->policy->deviceLimit();
    }

    /**
     * Active (slots-consuming) devices of the business.
     */
    public function activeCount(Business $business): int
    {
        return Device::query()
            ->where('business_id', $business->id)
            ->where('status', Device::STATUS_ACTIVE)
            ->count();
    }

    /**
     * Whether the business already uses every allowed device slot.
     */
    public function isReached(Business $business): bool
    {
        $limit = $this->limit();

        if ($limit <= 0) {
            return false;
        }

        return $this->activeCount($business) >= $limit;
    }

    /**
     * Remaining slots, or null when unlimited.
     */
    public function remaining(Business $business): ?int
    {
        $limit = $this->limit();

        if ($limit <= 0) {
            return null;
        }

        return max(0, $limit - $this->activeCount($business));
    }
}
