<?php

namespace App\Services\Subscription;

use App\Models\Subscription;

final class PremiumPricing
{
    public function __construct(
        private readonly PremiumPolicy $policy,
    ) {}

    public function isConfigured(): bool
    {
        return config('premium.pricing.configured') === true;
    }

    /**
     * @return array{currency: string, price_minor: int}|null
     */
    public function priceFor(string $plan, string $period): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        if ($plan !== $this->policy->planCode() || $plan !== Subscription::PLAN_CLOUD) {
            return null;
        }

        if (! $this->policy->supportsBillingPeriod($period)) {
            return null;
        }

        foreach ($this->mobilePlans() as $configuredPlan) {
            if (! is_array($configuredPlan) || ($configuredPlan['code'] ?? null) !== $plan) {
                continue;
            }

            $periods = $configuredPlan['billing_periods'] ?? [];

            if (! is_array($periods)) {
                continue;
            }

            foreach ($periods as $configuredPeriod) {
                if (! is_array($configuredPeriod) || ($configuredPeriod['period'] ?? null) !== $period) {
                    continue;
                }

                $currency = $configuredPeriod['currency'] ?? null;
                $priceMinor = $configuredPeriod['price_minor'] ?? null;

                if (is_string($currency) && $currency === 'IDR' && is_int($priceMinor) && $priceMinor > 0) {
                    return [
                        'currency' => $currency,
                        'price_minor' => $priceMinor,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    public function mobilePlans(): array
    {
        $configured = config('premium.pricing.mobile_plans');

        if (is_array($configured) && $configured !== []) {
            if ($this->hasAnyBillingPeriod($configured)) {
                return $configured;
            }

            $legacy = config('premium.mobile_plans');

            return is_array($legacy) ? $legacy : [];
        }

        $legacy = config('premium.mobile_plans');

        return is_array($legacy) ? $legacy : [];
    }

    /**
     * @param  array<mixed>  $plans
     */
    private function hasAnyBillingPeriod(array $plans): bool
    {
        foreach ($plans as $plan) {
            if (! is_array($plan)) {
                continue;
            }

            $periods = $plan['billing_periods'] ?? [];

            if (is_array($periods) && $periods !== []) {
                return true;
            }
        }

        return false;
    }
}
