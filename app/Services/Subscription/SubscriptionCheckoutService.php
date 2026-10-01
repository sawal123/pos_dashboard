<?php

namespace App\Services\Subscription;

use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Subscription\Midtrans\MidtransGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SubscriptionCheckoutService
{
    public function __construct(
        private readonly PremiumPricing $pricing,
        private readonly PremiumPolicy $policy,
        private readonly MidtransGateway $midtrans,
    ) {}

    public function isCheckoutConfigured(): bool
    {
        return $this->pricing->isConfigured()
            && $this->midtrans->isConfigured()
            && $this->pricing->priceFor($this->policy->planCode(), 'monthly') !== null
            && $this->pricing->priceFor($this->policy->planCode(), 'yearly') !== null;
    }

    public function createCheckout(Business $business, User $user, string $plan, string $period, ?string $idempotencyKey = null): SubscriptionPayment
    {
        $price = $this->pricing->priceFor($plan, $period);

        if ($price === null || ! $this->midtrans->isConfigured()) {
            throw new PricingUnavailableException;
        }

        return DB::transaction(function () use ($business, $user, $plan, $period, $idempotencyKey, $price): SubscriptionPayment {
            $existing = $this->existingPendingPayment($business, $user, $plan, $period, $price['currency'], $price['price_minor'], $idempotencyKey);

            if ($existing !== null && $existing->snap_token !== null && $existing->redirect_url !== null) {
                return $existing;
            }

            $payment = $existing ?? SubscriptionPayment::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
                'provider_order_id' => $this->newProviderOrderId($business),
                'idempotency_key' => $idempotencyKey,
                'plan' => $plan,
                'billing_period' => $period,
                'currency' => $price['currency'],
                'amount' => $price['price_minor'],
                'status' => SubscriptionPayment::STATUS_PENDING,
                'expires_at' => now()->addMinutes(30),
            ]);

            $snap = $this->midtrans->createSnapTransaction($payment, $business, $user);

            $payment->forceFill([
                'snap_token' => $snap->token,
                'redirect_url' => $snap->redirectUrl,
            ])->save();

            return $payment;
        });
    }

    private function existingPendingPayment(
        Business $business,
        User $user,
        string $plan,
        string $period,
        string $currency,
        int $amount,
        ?string $idempotencyKey,
    ): ?SubscriptionPayment {
        $query = SubscriptionPayment::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->where('plan', $plan)
            ->where('billing_period', $period)
            ->where('currency', $currency)
            ->where('amount', $amount)
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->lockForUpdate();

        if ($idempotencyKey !== null) {
            $query->where('idempotency_key', $idempotencyKey);
        } else {
            $query->whereNull('idempotency_key')
                ->where('created_at', '>=', now()->subMinutes(15));
        }

        /** @var SubscriptionPayment|null $payment */
        $payment = $query->latest('id')->first();

        return $payment;
    }

    private function newProviderOrderId(Business $business): string
    {
        return 'pdash-'.$business->id.'-'.Str::ulid();
    }
}
