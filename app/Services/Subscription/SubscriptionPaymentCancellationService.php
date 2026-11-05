<?php

namespace App\Services\Subscription;

use App\Models\SubscriptionPayment;
use App\Services\Subscription\Midtrans\MidtransNotificationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SubscriptionPaymentCancellationService
{
    public function __construct(private readonly MidtransWebhookService $webhooks) {}

    public function cancel(SubscriptionPayment $payment): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment): SubscriptionPayment {
            $payment = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== SubscriptionPayment::STATUS_PENDING || $payment->activated_at !== null) {
                return $payment;
            }
            $key = config('premium.midtrans.server_key');
            if (! is_string($key) || trim($key) === '' || $payment->provider !== SubscriptionPayment::PROVIDER_MIDTRANS) {
                throw new RuntimeException('Payment cancellation unavailable.');
            }
            $core = config('premium.midtrans.is_production') === true
                ? 'https://api.midtrans.com/v2/' : 'https://api.sandbox.midtrans.com/v2/';
            $url = $core.rawurlencode($payment->provider_order_id);
            $status = $this->client($key)->get($url.'/status');
            if ($status->status() === 404 || (string) $status->json('status_code') === '404') {
                // Before a payment method is chosen, only the Snap session exists.
                if (! is_string($payment->snap_token) || $payment->snap_token === '') {
                    throw new RuntimeException('Snap session unavailable.');
                }
                $snap = config('premium.midtrans.is_production') === true
                    ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
                $response = Http::acceptJson()->timeout(15)->connectTimeout(5)
                    ->withHeaders(['Authorization' => $key])
                    ->post($snap.'/snap/v1/transactions/'.rawurlencode($payment->snap_token).'/cancel');
                if ($response->successful() && is_string($response->json('canceled_at'))) {
                    $verified = ['order_id' => $payment->provider_order_id, 'gross_amount' => $payment->amount, 'transaction_status' => 'cancel'];
                } elseif ($response->json('errors') === ['token already canceled']) {
                    $verified = ['order_id' => $payment->provider_order_id, 'gross_amount' => $payment->amount, 'transaction_status' => 'cancel'];
                } else {
                    // The customer may have selected a method while cancellation was in flight.
                    $verified = $this->cancelCore($url, $key, $payment);
                }
            } else {
                $status->throw();
                $verified = $this->validateStatus($status->json(), $payment);
                if ($verified['transaction_status'] === 'pending') {
                    $verified = $this->cancelCore($url, $key, $payment);
                }
            }
            $this->webhooks->applyVerifiedNotification(new MidtransNotificationResult(
                orderId: $payment->provider_order_id,
                transactionStatus: (string) $verified['transaction_status'],
                fraudStatus: isset($verified['fraud_status']) ? (string) $verified['fraud_status'] : null,
                transactionId: isset($verified['transaction_id']) ? (string) $verified['transaction_id'] : null,
                paymentType: isset($verified['payment_type']) ? (string) $verified['payment_type'] : null,
                payload: $verified,
            ));

            return $payment->refresh();
        });
    }

    private function client(string $key): PendingRequest
    {
        return Http::acceptJson()->withBasicAuth($key, '')->timeout(15)->connectTimeout(5);
    }

    /** @return array<string, mixed> */
    private function cancelCore(string $url, string $key, SubscriptionPayment $payment): array
    {
        // Always re-read after attempting cancellation, including provider errors/races.
        try {
            $this->client($key)->post($url.'/cancel');
        } catch (ConnectionException) {
            // A timeout can occur after Midtrans processed the request.
        }
        $response = $this->client($key)->get($url.'/status');
        $response->throw();

        return $this->validateStatus($response->json(), $payment);
    }

    /** @return array<string, mixed> */
    private function validateStatus(mixed $status, SubscriptionPayment $payment): array
    {
        if (! is_array($status) || ($status['order_id'] ?? null) !== $payment->provider_order_id
            || ! isset($status['gross_amount']) || ! is_numeric($status['gross_amount'])
            || (float) $status['gross_amount'] !== (float) $payment->amount
            || ! is_string($status['transaction_status'] ?? null)) {
            throw new RuntimeException('Invalid payment status response.');
        }

        return $status;
    }
}
