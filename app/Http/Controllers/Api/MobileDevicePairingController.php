<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DevicePairingException;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Devices\DevicePairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/mobile/devices/pair
 *
 * The cashier redeems the 4-digit code the owner generated in the dashboard.
 * This is the *only* way a cashier terminal becomes a registered device:
 * owner/member devices are managed in the dashboard, and no role can
 * self-register a device from the app anymore.
 */
class MobileDevicePairingController extends Controller
{
    public function __construct(
        private readonly DevicePairingService $pairing,
        private readonly BusinessAuthorizer $authorizer,
    ) {}

    public function pair(Request $request): JsonResponse
    {
        if (! $request->user()->tokenCan('mobile')) {
            return response()->json([
                'message' => 'Mobile API token is required.',
                'code' => 'MOBILE_TOKEN_REQUIRED',
            ], 403);
        }

        $validated = $request->validate([
            'business_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'digits:4'],
            'device_identifier' => ['required', 'string', 'min:8', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'device_model' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $business = Business::find((int) $validated['business_id']);

        if (! $business instanceof Business || ! $user->belongsToBusiness($business)) {
            return response()->json([
                'message' => 'Business access denied.',
                'code' => 'BUSINESS_ACCESS_DENIED',
            ], 403);
        }

        // Poin 2 & 3: pairing hanya untuk role kasir. Owner/member mengelola
        // perangkatnya dari dashboard, bukan dari aplikasi kasir.
        $role = $this->authorizer->roleIn($user, $business);

        if ($role !== Business::ROLE_CASHIER) {
            return response()->json([
                'message' => 'Pairing perangkat hanya berlaku untuk akun kasir. Perangkat owner dikelola dari Dashboard.',
                'code' => 'PAIRING_CASHIER_ONLY',
            ], 403);
        }

        $deviceName = trim((string) $validated['name']);
        $model = trim((string) ($validated['device_model'] ?? ''));

        try {
            $device = $this->pairing->redeem(
                $business,
                (string) $validated['code'],
                [
                    'identifier' => (string) $validated['device_identifier'],
                    'name' => $deviceName,
                    'platform' => $validated['platform'] ?? null,
                    'app_version' => $validated['app_version'] ?? null,
                    'notes' => $model !== '' ? 'Model perangkat: '.$model : null,
                ],
                $user,
                [
                    'ip' => $request->ip(),
                    'user_agent' => $this->truncateUserAgent($request->userAgent()),
                ],
            );
        } catch (DevicePairingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->status);
        }

        return response()->json([
            'data' => [
                'id' => $device->id,
                'identifier' => $device->identifier,
                'business_id' => $device->business_id,
                'outlet_id' => $device->outlet_id,
                'status' => $device->status,
                'name' => $device->name,
                'platform' => $device->platform,
                'paired_at' => $device->paired_at?->toJSON(),
                'outlet' => [
                    'id' => $device->outlet_id,
                    'name' => $device->outlet?->name,
                ],
            ],
        ]);
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        $trimmed = trim($userAgent);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, 1024);
    }
}
