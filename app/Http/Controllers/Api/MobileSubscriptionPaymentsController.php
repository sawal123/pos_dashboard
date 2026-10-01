<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSubscriptionPaymentsController extends Controller
{
    public function show(Request $request, SubscriptionPayment $payment): JsonResponse
    {
        if (! $request->user()->tokenCan('mobile')) {
            return response()->json([
                'message' => 'Mobile API token is required.',
                'code' => 'MOBILE_TOKEN_REQUIRED',
            ], 403);
        }

        /** @var User $user */
        $user = $request->user();

        if (! $user->belongsToBusiness($payment->business)) {
            return response()->json([
                'message' => 'Payment not found.',
                'code' => 'PAYMENT_NOT_FOUND',
            ], 404);
        }

        return response()->json(['data' => $this->present($payment)]);
    }

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

        $payments = SubscriptionPayment::query()
            ->where('business_id', $business->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (SubscriptionPayment $payment): array => $this->present($payment))
            ->values();

        return response()->json(['data' => $payments]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SubscriptionPayment $payment): array
    {
        $subscription = $payment->business->subscription;

        return [
            'id' => $payment->id,
            'plan' => $payment->plan,
            'billing_period' => $payment->billing_period,
            'currency' => $payment->currency,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toJSON(),
            'subscription' => $subscription === null ? null : [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toJSON(),
                'expires_at' => $subscription->expires_at?->toJSON(),
            ],
        ];
    }
}
