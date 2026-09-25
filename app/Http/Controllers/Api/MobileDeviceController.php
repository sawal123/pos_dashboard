<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDeviceController extends Controller
{
    public function __construct(
        private readonly BusinessAuthorizer $authorizer,
    ) {}

    /**
     * Register or resolve a mobile device for a business/outlet.
     *
     * Idempotent per (business_id, identifier) pair — existing devices are
     * resolved and their last_seen_at is updated.  Inactive devices are
     * rejected so the caller must re-activate them server-side.
     *
     * Race condition safety: if two concurrent requests race past the initial
     * SELECT and both attempt INSERT, the unique constraint will fire on the
     * second writer.  We catch UniqueConstraintViolationException and fall
     * through to re-fetch the winner's row, returning a normal response.
     */
    public function store(Request $request): JsonResponse
    {
        // ── 1. Mobile token enforcement ────────────────────────────────────
        if (! $request->user()->tokenCan('mobile')) {
            return response()->json([
                'message' => 'Mobile API token is required.',
                'code' => 'MOBILE_TOKEN_REQUIRED',
            ], 403);
        }

        // ── 2. Validate input ──────────────────────────────────────────────
        $validated = $request->validate([
            'business_id' => ['required', 'integer'],
            'outlet_id' => ['required', 'integer'],
            'device_identifier' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // ── 3. Business membership enforcement ────────────────────────────
        $business = Business::find((int) $validated['business_id']);

        if (! $business || ! $user->belongsToBusiness($business)) {
            return response()->json([
                'message' => 'Business access denied.',
                'code' => 'BUSINESS_ACCESS_DENIED',
            ], 403);
        }

        // DASH-10B2 — device registration is part of device management, which a
        // cashier may not perform. The role is re-read per request, so it also
        // applies to already-issued tokens.
        if (! $this->authorizer->allows($user, $business, BusinessPermission::MOBILE_DEVICES_MANAGE)) {
            return response()->json([
                'message' => 'This role is not supported by the mobile device API yet.',
                'code' => 'MOBILE_ROLE_NOT_SUPPORTED',
            ], 403);
        }

        // ── 4. Cloud access enforcement ───────────────────────────────────
        if (! $business->hasCloudAccess()) {
            return response()->json([
                'message' => 'Cloud subscription is required.',
                'code' => 'CLOUD_SUBSCRIPTION_REQUIRED',
            ], 403);
        }

        // ── 5. Outlet must belong to the same business ────────────────────
        $outlet = Outlet::find((int) $validated['outlet_id']);

        if (! $outlet || $outlet->business_id !== $business->id) {
            return response()->json([
                'message' => 'Outlet not found or does not belong to this business.',
                'code' => 'OUTLET_ACCESS_DENIED',
            ], 403);
        }

        // ── 6. Idempotent device resolution ───────────────────────────────
        $device = Device::where('business_id', $business->id)
            ->where('identifier', $validated['device_identifier'])
            ->first();

        if ($device) {
            return $this->resolveExisting($device, $outlet);
        }

        // ── 7. Create — guard against unique race via catch ───────────────
        try {
            $device = Device::create([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'name' => $validated['name'],
                'identifier' => $validated['device_identifier'],
                'platform' => $validated['platform'] ?? null,
                'status' => 'active',
                'registered_at' => now(),
                'last_seen_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Concurrent request won the INSERT race — resolve the winner.
            $device = Device::where('business_id', $business->id)
                ->where('identifier', $validated['device_identifier'])
                ->firstOrFail();

            return $this->resolveExisting($device, $outlet);
        }

        return $this->deviceResponse($device);
    }

    /**
     * Validate an existing device and update its last_seen_at.
     * Returns error response if inactive or registered on a different outlet.
     */
    private function resolveExisting(Device $device, Outlet $outlet): JsonResponse
    {
        if ($device->status !== 'active') {
            return response()->json([
                'message' => 'Device is inactive.',
                'code' => 'DEVICE_INACTIVE',
            ], 403);
        }

        if ($device->outlet_id !== $outlet->id) {
            return response()->json([
                'message' => 'Device is registered to a different outlet.',
                'code' => 'DEVICE_OUTLET_MISMATCH',
            ], 409);
        }

        $device->update(['last_seen_at' => now()]);

        return $this->deviceResponse($device);
    }

    /**
     * Build the standard device response payload.
     */
    private function deviceResponse(Device $device): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $device->id,
                'identifier' => $device->identifier,
                'business_id' => $device->business_id,
                'outlet_id' => $device->outlet_id,
                'status' => $device->status,
                'name' => $device->name,
                'platform' => $device->platform,
            ],
        ]);
    }
}
