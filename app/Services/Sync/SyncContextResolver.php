<?php

namespace App\Services\Sync;

use App\Models\Business;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

class SyncContextResolver
{
    /**
     * Resolve and validate authenticated user, business, subscription, and device context.
     *
     * @return array{user: User, business: Business, device: Device, outlet_id: int}
     */
    public function resolve(Request $request, int $businessId, string $deviceIdentifier): array
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
