<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Subscription\PricingUnavailableException;
use App\Services\Subscription\SubscriptionCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileSubscriptionCheckoutController extends Controller
{
    public function __construct(
        private readonly BusinessAuthorizer $authorizer,
        private readonly SubscriptionCheckoutService $checkout,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->tokenCan('mobile')) {
            return response()->json([
                'message' => 'Mobile API token is required.',
                'code' => 'MOBILE_TOKEN_REQUIRED',
            ], 403);
        }

        $validated = $request->validate([
            'business_id' => ['required', 'integer'],
            'plan' => ['required', 'string', Rule::in([Subscription::PLAN_CLOUD])],
            'billing_period' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'amount' => ['prohibited'],
            'currency' => ['prohibited'],
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

        if (! $this->authorizer->allows($user, $business, BusinessPermission::SUBSCRIPTION_MANAGE)) {
            return response()->json([
                'message' => 'Subscription purchase is not allowed for this business role.',
                'code' => 'SUBSCRIPTION_PURCHASE_FORBIDDEN',
            ], 403);
        }

        try {
            $payment = $this->checkout->createCheckout(
                business: $business,
                user: $user,
                plan: (string) $validated['plan'],
                period: (string) $validated['billing_period'],
                idempotencyKey: isset($validated['idempotency_key']) ? (string) $validated['idempotency_key'] : null,
            );
        } catch (PricingUnavailableException) {
            return response()->json([
                'message' => 'Checkout is not available because official pricing or Midtrans configuration is incomplete.',
                'code' => 'CHECKOUT_UNAVAILABLE',
            ], 409);
        }

        return response()->json([
            'data' => [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'provider' => $payment->provider,
                'snap_token' => $payment->snap_token,
                'redirect_url' => $payment->redirect_url,
            ],
        ], 201);
    }
}
