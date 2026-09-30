<?php

namespace App\Services\Subscription;

use App\Models\Subscription;

class MobileSubscriptionPlanCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function plans(): array
    {
        $configuredPlans = config('premium.mobile_plans', []);

        if (! is_array($configuredPlans)) {
            return [];
        }

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

    public function checkoutAvailable(): bool
    {
        return false;
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

        return [
            'code' => $code,
            'name' => $name,
            'billing_periods' => $this->billingPeriods($plan['billing_periods'] ?? []),
            'currency' => $this->currency($plan['billing_periods'] ?? []),
            'benefits' => $this->benefits($plan['benefits'] ?? []),
            'available' => (bool) ($plan['available'] ?? true),
        ];
    }

    /**
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

            if (! in_array($periodCode, ['monthly', 'yearly'], true)) {
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
