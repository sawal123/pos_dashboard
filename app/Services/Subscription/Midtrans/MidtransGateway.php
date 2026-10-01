<?php

namespace App\Services\Subscription\Midtrans;

use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;

interface MidtransGateway
{
    public function isConfigured(): bool;

    public function createSnapTransaction(SubscriptionPayment $payment, Business $business, User $user): MidtransSnapResponse;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyNotification(array $payload): ?MidtransNotificationResult;
}
