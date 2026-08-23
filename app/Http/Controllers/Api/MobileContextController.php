<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
                'code' => 'MOBILE_TOKEN_REQUIRED',
            ], 403);
        }

        /** @var User $user */
        $user = $request->user();

        /** @var Collection<int, Business> $businesses */
        $businesses = $user->businesses()->with(['subscription', 'outlets'])->get();

        $deviceIdentifier = (string) $request->query('device_identifier', '');

        /** @var array<int, array{id: int, name: string, subscription: array{plan: string, status: string}|null, cloud_access: bool, outlets: list<array{id: int, name: string, code: string, status: string}>, device_context: array{id: int, identifier: string, outlet_id: int, status: string, name: string, platform: string|null}|null}> $businessData */
        $businessData = $businesses->map(function (Business $business) use ($deviceIdentifier): array {
            /** @var Subscription|null $subscription */
            $subscription = $business->subscription;

            $cloudAccess = $business->hasCloudAccess();

            /** @var list<array{id: int, name: string, code: string, status: string}> $outlets */
            $outlets = $business->outlets
                ->map(function (Outlet $outlet): array {
                    return [
                        'id' => $outlet->id,
                        'name' => $outlet->name,
                        'code' => $outlet->code,
                        'status' => $outlet->status,
                    ];
                })
                ->values()
                ->all();

            /** @var array{id: int, identifier: string, outlet_id: int, status: string, name: string, platform: string|null}|null $deviceContext */
            $deviceContext = null;

            if ($deviceIdentifier !== '') {
                $device = Device::where('business_id', $business->id)
                    ->where('identifier', $deviceIdentifier)
                    ->first();

                if ($device instanceof Device) {
                    $deviceContext = [
                        'id' => $device->id,
                        'identifier' => $device->identifier,
                        'outlet_id' => $device->outlet_id,
                        'status' => $device->status,
                        'name' => $device->name,
                        'platform' => $device->platform,
                    ];
                }
            }

            return [
                'id' => $business->id,
                'name' => $business->name,
                'subscription' => $subscription !== null ? [
                    'plan' => $subscription->plan,
                    'status' => $subscription->status,
                ] : null,
                'cloud_access' => $cloudAccess,
                'outlets' => $outlets,
                'device_context' => $deviceContext,
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'businesses' => $businessData,
            ],
        ]);
    }
}
