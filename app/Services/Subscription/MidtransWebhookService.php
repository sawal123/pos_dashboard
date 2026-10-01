<?php

namespace App\Services\Subscription;

use App\Models\SubscriptionPayment;
use App\Services\Subscription\Midtrans\MidtransGateway;
use App\Services\Subscription\Midtrans\MidtransPaymentStatusMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MidtransWebhookService
{
    public function __construct(
        private readonly MidtransGateway $gateway,
        private readonly MidtransPaymentStatusMapper $mapper,
        private readonly PremiumSubscriptionActivator $activator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): MidtransWebhookResult
    {
        $notification = $this->gateway->verifyNotification($payload);

        if ($notification === null) {
            return MidtransWebhookResult::invalid();
        }

        $payment = DB::transaction(function () use ($notification): ?SubscriptionPayment {
            /** @var SubscriptionPayment|null $payment */
            $payment = SubscriptionPayment::query()
                ->where('provider', SubscriptionPayment::PROVIDER_MIDTRANS)
                ->where('provider_order_id', $notification->orderId)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return null;
            }

            $newStatus = $this->mapper->toInternalStatus($notification->transactionStatus, $notification->fraudStatus);

            if ($newStatus !== null && $this->canTransition($payment->status, $newStatus)) {
                $payment->forceFill([
                    'provider_transaction_id' => $notification->transactionId,
                    'provider_payment_type' => $notification->paymentType,
                    'provider_transaction_status' => $notification->transactionStatus,
                    'provider_fraud_status' => $notification->fraudStatus,
                    'provider_payload' => $notification->payload,
                ]);

                $payment->status = $newStatus;

                if ($newStatus === SubscriptionPayment::STATUS_PAID && $payment->paid_at === null) {
                    $payment->paid_at = Carbon::now();
                }

                $payment->save();
            }

            return $payment;
        });

        if ($payment === null) {
            return MidtransWebhookResult::unknownOrder();
        }

        if ($payment->status === SubscriptionPayment::STATUS_PAID) {
            $this->activator->activatePaidPayment($payment);
        }

        return MidtransWebhookResult::handled();
    }

    private function canTransition(string $current, string $next): bool
    {
        if ($current === $next) {
            return true;
        }

        if ($current === SubscriptionPayment::STATUS_REFUNDED) {
            return false;
        }

        if ($current === SubscriptionPayment::STATUS_PAID) {
            return $next === SubscriptionPayment::STATUS_REFUNDED;
        }

        if (in_array($current, [SubscriptionPayment::STATUS_FAILED, SubscriptionPayment::STATUS_EXPIRED, SubscriptionPayment::STATUS_CANCELLED], true)) {
            return $next !== SubscriptionPayment::STATUS_PENDING;
        }

        return true;
    }
}
