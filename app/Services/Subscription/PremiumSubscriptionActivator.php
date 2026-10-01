<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\DB;

final class PremiumSubscriptionActivator
{
    public function activatePaidPayment(SubscriptionPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            /** @var SubscriptionPayment|null $lockedPayment */
            $lockedPayment = SubscriptionPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (
                $lockedPayment === null
                || $lockedPayment->status !== SubscriptionPayment::STATUS_PAID
                || $lockedPayment->activated_at !== null
            ) {
                return;
            }

            $now = now();

            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()
                ->where('business_id', $lockedPayment->business_id)
                ->lockForUpdate()
                ->first();

            $base = $now;
            $startsAt = $now;

            if (
                $subscription !== null
                && $subscription->plan === Subscription::PLAN_CLOUD
                && $subscription->status === Subscription::STATUS_ACTIVE
                && $subscription->expires_at !== null
                && $subscription->expires_at->gt($now)
            ) {
                $base = $subscription->expires_at;
                $startsAt = $subscription->starts_at ?? $now;
            }

            $expiresAt = match ($lockedPayment->billing_period) {
                'monthly' => $base->copy()->addMonthNoOverflow(),
                'yearly' => $base->copy()->addYearNoOverflow(),
                default => throw new \UnexpectedValueException('Unsupported billing period.'),
            };

            Subscription::query()->updateOrCreate(
                ['business_id' => $lockedPayment->business_id],
                [
                    'plan' => Subscription::PLAN_CLOUD,
                    'status' => Subscription::STATUS_ACTIVE,
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                ],
            );

            $lockedPayment->forceFill(['activated_at' => $now])->save();
        });
    }
}
