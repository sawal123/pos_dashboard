<?php

namespace App\Services\Platform;

use App\Models\Business;
use App\Models\Subscription;
use App\Services\Subscription\PremiumPolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SubscriptionAdministrationService
{
    public function __construct(
        private readonly PremiumPolicy $policy,
    ) {}

    /**
     * Manually activate Cloud plan for a subscription with official duration.
     */
    public function activateCloud(Subscription $subscription, string $billingPeriod): Subscription
    {
        $this->ensureValidBillingPeriod($billingPeriod);

        $now = Carbon::now();
        $expiresAt = $this->calculateExpiry($now, $billingPeriod);

        $subscription->forceFill([
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $now,
            'expires_at' => $expiresAt,
        ])->save();

        return $subscription;
    }

    /**
     * Manually activate Cloud plan for a business (creates subscription row if missing).
     */
    public function activateCloudForBusiness(Business $business, string $billingPeriod): Subscription
    {
        $this->ensureValidBillingPeriod($billingPeriod);

        $now = Carbon::now();
        $expiresAt = $this->calculateExpiry($now, $billingPeriod);

        /** @var Subscription $subscription */
        $subscription = Subscription::query()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'plan' => Subscription::PLAN_CLOUD,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => $now,
                'expires_at' => $expiresAt,
            ],
        );

        return $subscription;
    }

    /**
     * Manually renew Cloud subscription.
     * - If active and expires_at is future: extends from expires_at.
     * - If expired, inactive, or past expiry: extends from now().
     */
    public function renewCloud(Subscription $subscription, string $billingPeriod): Subscription
    {
        $this->ensureValidBillingPeriod($billingPeriod);

        $now = Carbon::now();

        // If currently active Cloud with a future expiry, preserve remaining time
        if (
            $subscription->isCloud()
            && $subscription->status === Subscription::STATUS_ACTIVE
            && $subscription->expires_at !== null
            && $subscription->expires_at->gt($now)
        ) {
            $base = $subscription->expires_at;
            $startsAt = $subscription->starts_at ?? $now;
        } else {
            $base = $now;
            $startsAt = $subscription->starts_at ?? $now;
        }

        $expiresAt = $this->calculateExpiry($base, $billingPeriod);

        $subscription->forceFill([
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ])->save();

        return $subscription;
    }

    /**
     * Administratively downgrade subscription to Free tier.
     * Does NOT delete business, devices, sync, or backup data.
     */
    public function downgradeToFree(Subscription $subscription): Subscription
    {
        $subscription->forceFill([
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ])->save();

        return $subscription;
    }

    /**
     * Administratively set subscription to Inactive.
     * Immediately revokes cloud entitlement.
     */
    public function setInactive(Subscription $subscription): Subscription
    {
        $subscription->forceFill([
            'status' => Subscription::STATUS_INACTIVE,
        ])->save();

        return $subscription;
    }

    /**
     * Calculate expiry timestamp from base date using canonical billing period.
     */
    private function calculateExpiry(\DateTimeInterface|CarbonInterface $base, string $billingPeriod): Carbon
    {
        $carbon = Carbon::parse($base);

        return match ($billingPeriod) {
            'monthly' => $carbon->addMonthNoOverflow(),
            'yearly' => $carbon->addYearNoOverflow(),
            default => throw new InvalidArgumentException("Unsupported billing period: {$billingPeriod}"),
        };
    }

    /**
     * Ensure billing period is officially supported by policy.
     */
    private function ensureValidBillingPeriod(string $billingPeriod): void
    {
        if (! $this->policy->supportsBillingPeriod($billingPeriod)) {
            throw new InvalidArgumentException("Billing period '{$billingPeriod}' is not supported by policy.");
        }
    }
}
