<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileBusinessTaxController extends Controller
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
                'message' => 'Kasir tidak memiliki izin untuk mengubah pengaturan pajak bisnis.',
                'code' => 'FORBIDDEN_CASHIER',
            ], 403);
        }

        $validated = $request->validate([
            'tax_enabled' => ['required', 'boolean'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $business->update([
            'tax_enabled' => (bool) $validated['tax_enabled'],
            'tax_rate' => round((float) $validated['tax_rate'], 2),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan pajak bisnis berhasil diperbarui.',
            'data' => [
                'tax_enabled' => (bool) $business->tax_enabled,
                'tax_rate' => (float) $business->tax_rate,
            ],
        ]);
    }
}
