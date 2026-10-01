<?php

namespace App\Services\Subscription\Midtrans;

final readonly class MidtransNotificationResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $orderId,
        public string $transactionStatus,
        public ?string $fraudStatus,
        public ?string $transactionId,
        public ?string $paymentType,
        public array $payload,
    ) {}
}
