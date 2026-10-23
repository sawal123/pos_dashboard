<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MidtransRedirectController extends Controller
{
    /**
     * Handle the browser redirect after a successful Snap payment.
     *
     * This endpoint is intentionally read-only. The browser redirect is not
     * trusted as proof of payment; the Midtrans webhook is responsible for
     * verifying the transaction and activating the subscription.
     */
    public function finish(Request $request): JsonResponse
    {
        return $this->paymentStatus($request, expectedStatus: SubscriptionPayment::STATUS_PAID);
    }

    /**
     * Return the server-side status after a failed/unfinished Snap payment.
     */
    public function failed(Request $request): JsonResponse
    {
        return $this->paymentStatus($request);
    }

    private function paymentStatus(Request $request, ?string $expectedStatus = null): JsonResponse
    {
        $orderId = trim((string) $request->query('order_id', ''));

        if ($orderId === '') {
            return response()->json([
                'success' => false,
                'code' => 'ORDER_ID_REQUIRED',
                'message' => 'Parameter order_id wajib diisi.',
            ], 400);
        }

        $payment = SubscriptionPayment::query()
            ->where('provider', SubscriptionPayment::PROVIDER_MIDTRANS)
            ->where('provider_order_id', $orderId)
            ->first();

        if ($payment === null) {
            return response()->json([
                'success' => false,
                'code' => 'PAYMENT_NOT_FOUND',
                'message' => 'Pembayaran tidak ditemukan.',
            ], 404);
        }

        $isExpectedStatus = $expectedStatus === null || $payment->status === $expectedStatus;

        return response()->json([
            'success' => $isExpectedStatus,
            'code' => $isExpectedStatus ? 'PAYMENT_CONFIRMED' : 'PAYMENT_NOT_CONFIRMED',
            'message' => $isExpectedStatus
                ? 'Pembayaran berhasil diterima dan telah dikonfirmasi server.'
                : 'Redirect diterima, tetapi status pembayaran belum dikonfirmasi server.',
            'data' => [
                'payment_id' => $payment->id,
                'order_id' => $payment->provider_order_id,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toISOString(),
                'activated_at' => $payment->activated_at?->toISOString(),
            ],
        ], $isExpectedStatus ? 200 : 202);
    }
}
