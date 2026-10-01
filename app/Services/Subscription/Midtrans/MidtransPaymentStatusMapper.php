<?php

namespace App\Services\Subscription\Midtrans;

use App\Models\SubscriptionPayment;

final class MidtransPaymentStatusMapper
{
    public function toInternalStatus(string $transactionStatus, ?string $fraudStatus): ?string
    {
        return match ($transactionStatus) {
            'settlement' => SubscriptionPayment::STATUS_PAID,
            'capture' => $this->captureStatus($fraudStatus),
            'pending' => SubscriptionPayment::STATUS_PENDING,
            'deny' => SubscriptionPayment::STATUS_FAILED,
            'cancel' => SubscriptionPayment::STATUS_CANCELLED,
            'expire' => SubscriptionPayment::STATUS_EXPIRED,
            'refund', 'partial_refund' => SubscriptionPayment::STATUS_REFUNDED,
            default => null,
        };
    }

    private function captureStatus(?string $fraudStatus): string
    {
        return match ($fraudStatus) {
            'challenge' => SubscriptionPayment::STATUS_PENDING,
            'deny' => SubscriptionPayment::STATUS_FAILED,
            default => SubscriptionPayment::STATUS_PAID,
        };
    }
}
