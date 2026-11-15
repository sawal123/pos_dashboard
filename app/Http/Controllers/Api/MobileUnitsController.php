<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileUnitsController extends Controller
{
    /**
     * Get active units for the given business.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $businessId = (int) ($request->query('business_id') ?? $request->input('business_id'));

        $business = $businessId > 0
            ? Business::find($businessId)
            : $user->businesses()->first();

        if (! $business || ! $user->belongsToBusiness($business)) {
            return response()->json([
                'success' => false,
                'message' => 'Business access denied.',
            ], 403);
        }

        $units = Unit::forBusiness($business);

        return response()->json([
            'success' => true,
            'data' => $units->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'symbol' => $unit->symbol ?? $unit->name,
                'status' => $unit->status,
            ])->values()->all(),
        ]);
    }

    /**
     * Store a new custom unit.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'business_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:50'],
            'symbol' => ['nullable', 'string', 'max:20'],
        ]);

        $business = Business::find((int) $validated['business_id']);

        if (! $business || ! $user->belongsToBusiness($business)) {
            return response()->json([
                'success' => false,
                'message' => 'Business access denied.',
            ], 403);
        }

        $name = strtolower(trim($validated['name']));

        $unit = Unit::query()->firstOrCreate(
            [
                'business_id' => $business->id,
                'name' => $name,
            ],
            [
                'symbol' => $validated['symbol'] ?? $name,
                'status' => 'active',
            ]
        );

        return response()->json([
            'success' => true,
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
            ],
            'message' => 'Satuan berhasil ditambahkan.',
        ]);
    }
}

