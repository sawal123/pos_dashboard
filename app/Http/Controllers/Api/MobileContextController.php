<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Sync\SyncAuthorizationPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileContextController extends Controller
{
    public function __construct(
        private readonly BusinessAuthorizer $authorizer,
        private readonly SyncAuthorizationPolicy $syncAuthorizationPolicy,
    ) {}

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
        $businesses = $user->businesses()->with([
            'subscription',
            'outlets' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')->with('branchSubscription'),
        ])->get();

        $deviceIdentifier = (string) $request->query('device_identifier', '');

        /** @var array<int, array{id: int, name: string, business_type: string|null, subscription: array{plan: string, status: string, starts_at: string|null, expires_at: string|null}|null, cloud_access: bool, outlets: list<array{id: int, name: string, code: string, status: string}>, device_context: array{id: int, identifier: string, outlet_id: int, status: string, name: string, platform: string|null}|null}> $businessData */
        $businessData = $businesses->map(function (Business $business) use ($deviceIdentifier, $user): array {
            /** @var Subscription|null $subscription */
            $subscription = $business->subscription;

            $cloudAccess = $business->hasCloudAccess();
            $role = $this->authorizer->roleIn($user, $business);
            $assignedOutletId = $business->pivot?->outlet_id;

            $businessOutlets = $business->outlets;
            if ($role === Business::ROLE_CASHIER && $assignedOutletId !== null) {
                $businessOutlets = $businessOutlets->where('id', (int) $assignedOutletId)->values();
            }

            /** @var list<array{id: int, name: string, code: string, status: string}> $outlets */
            $outlets = $businessOutlets
                ->map(function (Outlet $outlet) use ($business): array {
                    $outlet->setRelation('business', $business);
                    $sub = $outlet->effectiveSubscription();

                    return [
                        'id' => $outlet->id,
                        'name' => $outlet->name,
                        'code' => $outlet->code,
                        'address' => $outlet->address,
                        'phone' => $outlet->phone,
                        'is_primary' => (bool) $outlet->is_primary,
                        'status' => $outlet->status,
                        'cloud_access' => $outlet->hasCloudAccess(),
                        'subscription' => $sub !== null ? [
                            'plan' => $sub->plan,
                            'status' => $sub->status,
                            'starts_at' => $sub->starts_at?->toJSON(),
                            'expires_at' => $sub->expires_at?->toJSON(),
                        ] : null,
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
                'role' => $role,
                'sync_capabilities' => $this->syncAuthorizationPolicy->capabilities($user, $business),
                'catalog_capabilities' => [
                    'product_barcode_v1' => true,
                ],
                // DASH-14 — additive/optional business type: canonical value
                // (`cafe` | `laundry` | `grosir`) or null when not determined.
                // Existing keys are unchanged; legacy clients can ignore it.
                'business_type' => $business->normalizedBusinessType(),
                'tax_settings' => [
                    'enabled' => (bool) $business->tax_enabled,
                    'rate' => (float) ($business->tax_rate ?? 11.0),
                ],
                'cashier_pin' => (string) ($business->cashier_pin ?? '123456'),
                'shift_settings' => [
                    'auto_open' => (bool) ($business->auto_open_shift ?? true),
                    'default_opening_cash' => (int) ($business->default_opening_cash ?? 0),
                ],
                'receipt_settings' => [
                    'logo' => $business->receiptLogoPayload(),
                    'logoUrl' => $business->receiptLogoUrl(),
                    'address' => $business->receipt_address,
                    'footer' => $business->receipt_footer,
                ],
                'subscription' => $subscription !== null ? [
                    'plan' => $subscription->plan,
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at?->toJSON(),
                    'expires_at' => $subscription->expires_at?->toJSON(),
                ] : null,
                'cloud_access' => $cloudAccess,
                'outlets' => $outlets,
                'device_context' => $deviceContext,
                'units' => \App\Models\Unit::forBusiness($business)->pluck('name')->values()->all(),
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
