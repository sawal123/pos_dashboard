<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDeviceController extends Controller
{
    /**
     * Register or resolve a mobile device for a business/outlet.
     *
     * Idempotent per (business_id, identifier) pair — existing devices are
     * resolved and their last_seen_at is updated.  Inactive devices are
     * rejected so the caller must re-activate them server-side.
     */
    public function store(Request $request): JsonResponse
    {
        // ── 1. Mobile token enforcement ────────────────────────────────────
        if (! $request->user()->tokenCan('mobile')) {
            return response()->json([
                'message' => 'Mobile API token is required.',
                'code'    => 'MOBILE_TOKEN_REQUIRED',
            ], 403);
        }

        // ── 2. Validate input ──────────────────────────────────────────────
        $validated = $request->validate([
            'business_id'        => ['required', 'integer'],
            'outlet_id'          => ['required', 'integer'],
            'device_identifier'  => ['required', 'string', 'max:100'],
            'name'               => ['required', 'string', 'max:255'],
            'platform'           => ['nullable', 'string', 'max:50'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // ── 3. Business membership enforcement ────────────────────────────
        $business = Business::find((int) $validated['business_id']);

        if (! $business || ! $user->belongsToBusiness($business)) {
            return response()->json([
                'message' => 'Business access denied.',
                'code'    => 'BUSINESS_ACCESS_DENIED',
            ], 403);
        }

        // ── 4. Cloud access enforcement ───────────────────────────────────
        if (! $business->hasCloudAccess()) {
            return response()->json([
                'message' => 'Cloud subscription is required.',
                'code'    => 'CLOUD_SUBSCRIPTION_REQUIRED',
            ], 403);
        }

        // ── 5. Outlet must belong to the same business ────────────────────
        $outlet = Outlet::find((int) $validated['outlet_id']);

        if (! $outlet || $outlet->business_id !== $business->id) {
            return response()->json([
                'message' => 'Outlet not found or does not belong to this business.',
                'code'    => 'OUTLET_ACCESS_DENIED',
            ], 403);
        }

        // ── 6. Idempotent device resolution ───────────────────────────────
        $device = Device::where('business_id', $business->id)
            ->where('identifier', $validated['device_identifier'])
            ->first();

        if ($device) {
            // Reject if inactive — caller must re-activate server-side
            if ($device->status !== 'active') {
                return response()->json([
                    'message' => 'Device is inactive.',
                    'code'    => 'DEVICE_INACTIVE',
                ], 403);
            }

            // Update last_seen_at on resolve
            $device->update(['last_seen_at' => now()]);
        } else {
            // Create new device
            $device = Device::create([
                'business_id'  => $business->id,
                'outlet_id'    => $outlet->id,
                'name'         => $validated['name'],
                'identifier'   => $validated['device_identifier'],
                'platform'     => $validated['platform'] ?? null,
                'status'       => 'active',
                'registered_at' => now(),
                'last_seen_at'  => now(),
            ]);
        }

        return response()->json([
            'data' => [
                'id'          => $device->id,
                'identifier'  => $device->identifier,
                'business_id' => $device->business_id,
                'outlet_id'   => $device->outlet_id,
                'status'      => $device->status,
                'name'        => $device->name,
                'platform'    => $device->platform,
            ],
        ], 200);
    }
}
