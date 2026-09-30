<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Services\Subscription\MobileSubscriptionPlanCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSubscriptionPlansController extends Controller
{
    public function __construct(
        private readonly MobileSubscriptionPlanCatalog $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->tokenCan('mobile')) {
            return response()->json([
                'message' => 'Mobile API token is required.',
                'code' => 'MOBILE_TOKEN_REQUIRED',
            ], 403);
        }

        $validated = $request->validate([
            'business_id' => ['required', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $business = Business::find((int) $validated['business_id']);

        if (! $business || ! $user->belongsToBusiness($business)) {
            return response()->json([
                'message' => 'Business access denied.',
                'code' => 'BUSINESS_ACCESS_DENIED',
            ], 403);
        }

        return response()->json([
            'data' => [
                'business_id' => $business->id,
                'plans' => $this->catalog->plans(),
                'checkout_available' => $this->catalog->checkoutAvailable(),
            ],
        ]);
    }
}
