<?php

namespace App\Services\Subscription;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\DB;

final class PremiumSubscriptionActivator
{
    public function __construct(
        private readonly SubscriptionPeriodCalculator $calculator = new SubscriptionPeriodCalculator,
    ) {}

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

            // 2. Lock parent Business row as stable serialization lock across all activations for this business
            Business::query()
                ->whereKey($lockedPayment->business_id)
                ->lockForUpdate()
                ->firstOrFail();

            $outletId = $lockedPayment->outlet_id;
            if ($outletId !== null) {
                $branch = Outlet::query()
                    ->where('business_id', $lockedPayment->business_id)
                    ->whereKey($outletId)
                    ->lockForUpdate()
                    ->first();

                if ($branch instanceof Outlet && $branch->is_primary) {
                    $outletId = null;
                }
            }

            $scopeKey = Subscription::scopeKeyFor($outletId);

            // 3. Lock Subscription row for this scope if one already exists
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()
                ->where('business_id', $lockedPayment->business_id)
                ->where('scope_key', $scopeKey)
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

            $expiresAt = $this->calculator->calculateExpiry($base, $lockedPayment->billing_period);

            Subscription::query()->updateOrCreate(
                [
                    'business_id' => $lockedPayment->business_id,
                    'scope_key' => $scopeKey,
                ],
                [
                    'outlet_id' => $outletId,
                    'plan' => $lockedPayment->plan ?: Subscription::PLAN_CLOUD,
                    'status' => Subscription::STATUS_ACTIVE,
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                ],
            );

            // A newly paid branch transitions from pending_payment -> active.
            if ($outletId !== null) {
                Outlet::query()
                    ->where('business_id', $lockedPayment->business_id)
                    ->whereKey($outletId)
                    ->where('status', Outlet::STATUS_PENDING_PAYMENT)
                    ->update(['status' => Outlet::STATUS_ACTIVE]);
            }

            $lockedPayment->forceFill([
                'activated_at' => $now,
                'expires_at' => $expiresAt,
            ])->save();
        });
    }
}
