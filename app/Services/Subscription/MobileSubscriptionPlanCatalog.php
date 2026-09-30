<?php

namespace App\Services\Subscription;

use App\Models\Subscription;

/**
 * PREM-D01 / PREM-D02A — read-only mobile plan catalog.
 *
 * Product policy comes from {@see PremiumPolicy} (`config/premium.php`); pricing
 * comes from the pricing configuration and is **undecided**. While no official
 * price is configured the catalog stays empty, exactly as the PREM-D01 contract
 * promises, and no price is ever invented from a mockup.
 *
 * PREM-D02A additions (additive only — no breaking change):
 *  - `purchasable` states whether a plan may actually be bought. It is false
 *    whenever the plan has no officially priced billing period or the backend has
 *    no checkout capability.
 *  - supported billing periods come from the product policy instead of a
 *    hardcoded list.
 *
 * `checkout_available` deliberately stays `false`: there is no Midtrans
 * integration yet (that is PREM-D02B), so the mobile client must never conclude
 * that a checkout exists.
 */
class MobileSubscriptionPlanCatalog
{
    public function __construct(
        private readonly PremiumPolicy $policy,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function plans(): array
    {
        $configuredPlans = $this->configuredPricingPlans();

        $plans = [];

        foreach ($configuredPlans as $configuredPlan) {
            if (! is_array($configuredPlan)) {
                continue;
            }

            $plan = $this->normalizePlan($configuredPlan);

            if ($plan !== null) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    /**
     * Checkout is unavailable until PREM-D02B ships a verified Midtrans contract
     * and Product records official prices.
     */
    public function checkoutAvailable(): bool
    {
        return false;
    }

    /**
     * Official pricing entries.
     *
     * Canonical location is `premium.pricing.mobile_plans`. The legacy top-level
     * `premium.mobile_plans` key is still honoured as a fallback so the PREM-D01
     * contract and its regression tests keep working unchanged.
     *
     * @return array<mixed>
     */
    private function configuredPricingPlans(): array
    {
        $configured = config('premium.pricing.mobile_plans');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        $legacy = config('premium.mobile_plans');

        return is_array($legacy) ? $legacy : [];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>|null
     */
    private function normalizePlan(array $plan): ?array
    {
        $code = $plan['code'] ?? null;
        $name = $plan['name'] ?? null;

        if (! is_string($code) || ! in_array($code, Subscription::canonicalPlanCodes(), true)) {
            return null;
        }

        if (! is_string($name) || $name === '') {
            return null;
        }

        $billingPeriods = $this->billingPeriods($plan['billing_periods'] ?? []);
        $available = (bool) ($plan['available'] ?? true);

        return [
            'code' => $code,
            'name' => $name,
            'billing_periods' => $billingPeriods,
            'currency' => $this->currency($plan['billing_periods'] ?? []),
            'benefits' => $this->benefits($plan['benefits'] ?? []),
            'available' => $available,
            // A plan is only purchasable with an official price on a
            // checkout-capable contract. No price → nothing to buy.
            'purchasable' => $available && $billingPeriods !== [] && $this->checkoutAvailable(),
        ];
    }

    /**
     * Only periods the product policy declares supported, and only when the
     * backend owns an official price for that period.
     *
     * @return list<array{period: string, currency: string, price_minor: int}>
     */
    private function billingPeriods(mixed $periods): array
    {
        if (! is_array($periods)) {
            return [];
        }

        $normalizedPeriods = [];

        foreach ($periods as $period) {
            if (! is_array($period)) {
                continue;
            }

            $periodCode = $period['period'] ?? null;
            $currency = $period['currency'] ?? null;
            $priceMinor = $period['price_minor'] ?? null;

            if (! is_string($periodCode) || ! $this->policy->supportsBillingPeriod($periodCode)) {
                continue;
            }

            if (! is_string($currency) || $currency === '') {
                continue;
            }

            if (! is_int($priceMinor) || $priceMinor < 0) {
                continue;
            }

            $normalizedPeriods[] = [
                'period' => $periodCode,
                'currency' => $currency,
                'price_minor' => $priceMinor,
            ];
        }

        return $normalizedPeriods;
    }

    private function currency(mixed $periods): ?string
    {
        $billingPeriods = $this->billingPeriods($periods);

        if ($billingPeriods === []) {
            return null;
        }

        return $billingPeriods[0]['currency'];
    }

    /**
     * @return list<string>
     */
    private function benefits(mixed $benefits): array
    {
        if (! is_array($benefits)) {
            return [];
        }

        $normalizedBenefits = [];

        foreach ($benefits as $benefit) {
            if (is_string($benefit) && $benefit !== '') {
                $normalizedBenefits[] = $benefit;
            }
        }

        return $normalizedBenefits;
    }
}
