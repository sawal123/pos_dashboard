<?php

namespace App\Services\Sync;

use App\Models\Business;
use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Http\Request;

class SyncContextResolver
{
    public function __construct(
        private readonly BusinessAuthorizer $authorizer,
    ) {}

    /**
     * Resolve and validate authenticated user, business, role, subscription, and device context.
     *
     * @param  string  $permission  the sync permission required for this call
     *                              ({@see BusinessPermission::SYNC_PUSH} or
     *                              {@see BusinessPermission::SYNC_PULL})
     * @return array{user: User, business: Business, device: Device, outlet_id: int}
     */
    public function resolve(Request $request, int $businessId, string $deviceIdentifier, string $permission): array
    {
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

        // DASH-10B2 — the mobile sync contract is not cashier-safe yet, so a
        // role without the explicit sync permission is denied here. The role is
        // re-read from the membership row on every request, so a role change
        // applies to already-issued tokens too.
        if (! $this->authorizer->allows($user, $business, $permission)) {
            abort(response()->json([
                'message' => 'This role is not supported by the mobile sync API yet.',
                'code' => 'MOBILE_ROLE_NOT_SUPPORTED',
            ], 403));
        }

        if (! $business->hasCloudAccess()) {
            abort(response()->json([
                'message' => 'Cloud subscription is required.',
                'code' => 'CLOUD_SUBSCRIPTION_REQUIRED',
            ], 403));
        }

        $device = Device::where('business_id', $business->id)
            ->where('identifier', $deviceIdentifier)
            ->first();

        if (! $device) {
            abort(response()->json([
                'message' => 'Invalid sync device.',
                'code' => 'SYNC_DEVICE_INVALID',
            ], 403));
        }

        if ($device->status !== 'active') {
            abort(response()->json([
                'message' => 'Device is inactive.',
                'code' => 'DEVICE_INACTIVE',
            ], 403));
        }

        return [
            'user' => $user,
            'business' => $business,
            'device' => $device,
            'outlet_id' => $device->outlet_id,
        ];
    }
}
