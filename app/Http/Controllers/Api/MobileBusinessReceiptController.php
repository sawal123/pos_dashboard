<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileBusinessReceiptController extends Controller
{
    public function __construct(
        private readonly BusinessAuthorizer $authorizer,
    ) {}

    public function update(Request $request, Business $business): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $role = $this->authorizer->roleIn($user, $business);
        if ($role === null) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke bisnis ini.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        if ($role === Business::ROLE_CASHIER) {
            return response()->json([
                'message' => 'Kasir tidak memiliki izin untuk mengubah pengaturan struk bisnis.',
                'code' => 'FORBIDDEN_CASHIER',
            ], 403);
        }

        $validated = $request->validate([
            'logo' => ['nullable'],
            'logo.width' => ['required_with:logo', 'integer', 'min:8', 'max:192'],
            'logo.height' => ['required_with:logo', 'integer', 'min:1', 'max:96'],
            'logo.data' => ['required_with:logo', 'string', 'max:3072'],
            'address' => ['nullable', 'string', 'max:300'],
            'footer' => ['nullable', 'string', 'max:500'],
        ]);

        $logoValue = null;
        if (isset($validated['logo'])) {
            if (is_array($validated['logo'])) {
                $logoValue = json_encode([
                    'width' => (int) $validated['logo']['width'],
                    'height' => (int) $validated['logo']['height'],
                    'data' => (string) $validated['logo']['data'],
                ]);
            } elseif (is_string($validated['logo'])) {
                $logoValue = $validated['logo'];
            }
        } elseif ($request->has('logo') && $request->input('logo') === null) {
            $logoValue = null;
        } else {
            $logoValue = $business->receipt_logo;
        }

        $business->update([
            'receipt_logo' => $logoValue,
            'receipt_address' => $request->has('address') ? (string) $request->input('address') : $business->receipt_address,
            'receipt_footer' => $request->has('footer') ? (string) $request->input('footer') : $business->receipt_footer,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan struk bisnis berhasil disinkronkan ke database.',
            'data' => [
                'receipt_settings' => [
                    'logo' => $business->receiptLogoPayload(),
                    'logoUrl' => $business->receiptLogoUrl(),
                    'address' => $business->receipt_address,
                    'footer' => $business->receipt_footer,
                ],
            ],
        ]);
    }
}

