<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileContextController extends Controller
{
    /**
     * Return the authenticated mobile user's full context:
     * user info, accessible businesses (with subscription + cloud_access),
     * outlets per business, and registered device context if present.
     */
    public function context(Request $request): JsonResponse
    {
        if (! $request->user()->tokenCan('mobile')) {
            return response()->json([
                'message' => 'Mobile API token is required.',
                'code'    => 'MOBILE_TOKEN_REQUIRED',
            ], 403);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $businesses = $user->businesses()->with(['subscription', 'outlets'])->get();

        $deviceIdentifier = (string) $request->query('device_identifier', '');

        $businessData = $businesses->map(function ($business) use ($deviceIdentifier) {
            $subscription  = $business->subscription;
            $cloudAccess   = $business->hasCloudAccess();

            $outlets = $business->outlets->map(fn ($outlet) => [
                'id'     => $outlet->id,
                'name'   => $outlet->name,
                'code'   => $outlet->code,
                'status' => $outlet->status,
            ])->values();

            $deviceContext = null;
            if ($deviceIdentifier !== '') {
                $device = Device::where('business_id', $business->id)
                    ->where('identifier', $deviceIdentifier)
                    ->first();

                if ($device) {
                    $deviceContext = [
                        'id'         => $device->id,
                        'identifier' => $device->identifier,
                        'outlet_id'  => $device->outlet_id,
                        'status'     => $device->status,
                        'name'       => $device->name,
                        'platform'   => $device->platform,
                    ];
                }
            }

            return [
                'id'            => $business->id,
                'name'          => $business->name,
                'subscription'  => $subscription ? [
                    'plan'    => $subscription->plan,
                    'status'  => $subscription->status,
                ] : null,
                'cloud_access'  => $cloudAccess,
                'outlets'       => $outlets,
                'device_context' => $deviceContext,
            ];
        })->values();

        return response()->json([
            'data' => [
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'businesses' => $businessData,
            ],
        ]);
    }
}
