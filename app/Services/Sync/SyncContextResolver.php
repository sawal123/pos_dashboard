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
     * @return array{user: User, business: Business, device: Device, outlet_id: int, role: string}
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

        // Membership is the authority, not the token: role changes apply to
        // already-issued mobile tokens on the next request.
        $role = $this->authorizer->roleIn($user, $business);
        $allowed = $this->authorizer->allows($user, $business, $permission);

        if ($permission === BusinessPermission::SYNC_PUSH) {
            $allowed = $allowed
                || $this->authorizer->allows($user, $business, BusinessPermission::SYNC_PUSH_CASHIER_SAFE);
        }

        if (! $allowed) {
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
            // PAIR-01: a cashier terminal must be paired with a dashboard code
            // before it may sync. Auto-provisioning is kept for owner/member,
            // whose devices are managed from the dashboard and who explicitly
            // do not need a pairing code.
            if ($role === Business::ROLE_CASHIER) {
                abort(response()->json([
                    'message' => 'Perangkat kasir belum terpasang. Minta kode pairing 4 digit dari pemilik melalui Dashboard → Perangkat.',
                    'code' => 'DEVICE_NOT_REGISTERED',
                ], 403));
            }

            $defaultOutlet = $business->outlets()
                ->where('status', 'active')
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get()
                ->first(fn ($o) => $o->hasCloudAccess())
                ?? $business->outlets()->where('status', 'active')->first()
                ?? $business->outlets()->first();
            if ($defaultOutlet) {
                $device = Device::create([
                    'business_id' => $business->id,
                    'outlet_id' => $defaultOutlet->id,
                    'identifier' => $deviceIdentifier,
                    'name' => 'POS Mobile ('.($user->name ?? 'Kasir').')',
                    'status' => 'active',
                    'registered_at' => now(),
                    'last_seen_at' => now(),
                ]);
            } else {
                abort(response()->json([
                    'message' => 'Invalid sync device.',
                    'code' => 'SYNC_DEVICE_INVALID',
                ], 403));
            }
        }

        if ($device->status !== 'active') {
            abort(response()->json([
                'message' => 'Device is inactive.',
                'code' => 'DEVICE_INACTIVE',
            ], 403));
        }

        $outlet = $device->outlet;
        if (! $outlet || ! $outlet->hasCloudAccess()) {
            abort(response()->json([
                'message' => 'Cloud subscription is required for this outlet.',
                'code' => 'CLOUD_SUBSCRIPTION_REQUIRED',
            ], 403));
        }

        $assignedOutletId = $user->businesses()
            ->where('businesses.id', $business->id)
            ->first()?->pivot?->outlet_id;

        if ($role === Business::ROLE_CASHIER && $assignedOutletId !== null && (int) $device->outlet_id !== (int) $assignedOutletId) {
            abort(response()->json([
                'message' => 'Kasir tidak diizinkan mengakses cabang ini.',
                'code' => 'OUTLET_ACCESS_DENIED',
            ], 403));
        }

        return [
            'user' => $user,
            'business' => $business,
            'device' => $device,
            'outlet_id' => $device->outlet_id,
            'role' => (string) $role,
        ];
    }
}
