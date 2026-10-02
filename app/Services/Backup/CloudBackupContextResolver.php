<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\Business;
use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Subscription\PremiumPolicy;
use App\Services\Sync\SyncContextResolver;
use Illuminate\Http\Request;

/**
 * PREM-D03 — authorization gate for the private Cloud backup API.
 *
 * Mirrors the mobile sync gate ({@see SyncContextResolver})
 * and adds the declared Cloud capability check. Nothing is trusted from the
 * client: the mobile ability, business membership, role permission, Cloud
 * entitlement and (optionally) the active device are all resolved server-side.
 */
final class CloudBackupContextResolver
{
    public function __construct(
        private readonly BusinessAuthorizer $authorizer,
        private readonly PremiumPolicy $premiumPolicy,
    ) {}

    /**
     * @param  string  $rolePermission  a {@see BusinessPermission} constant
     * @param  string  $capability  a {@see PremiumPolicy} capability constant
     * @return array{user: User, business: Business, device: Device|null, role: string}
     */
    public function resolve(
        Request $request,
        int $businessId,
        ?string $deviceIdentifier,
        bool $requireDevice,
        string $rolePermission,
        string $capability,
    ): array {
        /** @var User $user */
        $user = $request->user();

        if (! $user->tokenCan('mobile')) {
            abort(response()->json([
                'message' => 'Mobile API token is required.',
                'code' => 'MOBILE_TOKEN_REQUIRED',
            ], 403));
        }

        $business = Business::find($businessId);

        if (! $business || ! $user->belongsToBusiness($business)) {
            abort(response()->json([
                'message' => 'Business access denied.',
                'code' => 'BUSINESS_ACCESS_DENIED',
            ], 403));
        }

        // Membership is the authority, not the token: role changes apply to
        // already-issued mobile tokens on the next request.
        if (! $this->authorizer->allows($user, $business, $rolePermission)) {
            abort(response()->json([
                'message' => 'This role is not supported by the cloud backup API yet.',
                'code' => 'MOBILE_ROLE_NOT_SUPPORTED',
            ], 403));
        }

        // Active Cloud entitlement + declared capability. Free, expired,
        // inactive, unknown or missing subscriptions fail closed.
        if (! $this->premiumPolicy->allows($business, $capability)) {
            abort(response()->json([
                'message' => 'Cloud subscription is required.',
                'code' => 'CLOUD_SUBSCRIPTION_REQUIRED',
            ], 403));
        }

        $device = null;
        $identifier = $deviceIdentifier ?? '';

        if ($identifier !== '') {
            $device = Device::where('business_id', $business->id)
                ->where('identifier', $identifier)
                ->first();

            // A device that does not exist for this business (including a device
            // owned by another business) is rejected without leaking existence.
            if (! $device) {
                abort(response()->json([
                    'message' => 'Invalid backup device.',
                    'code' => 'DEVICE_NOT_FOUND',
                ], 403));
            }

            if ($device->status !== Device::STATUS_ACTIVE) {
                abort(response()->json([
                    'message' => 'Device is inactive.',
                    'code' => 'DEVICE_INACTIVE',
                ], 403));
            }
        }

        if ($requireDevice && $device === null) {
            abort(response()->json([
                'message' => 'A registered active device is required.',
                'code' => 'DEVICE_NOT_FOUND',
            ], 403));
        }

        return [
            'user' => $user,
            'business' => $business,
            'device' => $device,
            'role' => (string) $this->authorizer->roleIn($user, $business),
        ];
    }
}
