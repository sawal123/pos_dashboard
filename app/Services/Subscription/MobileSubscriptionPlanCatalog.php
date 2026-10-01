<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

/**
 * PREM-D01 / PREM-D02A / PREM-D02C — read-only mobile plan catalog.
 *
 * Plan metadata and prices are now database-owned (PREM-D02C):
 *
 *  - only canonical plan codes are exposed;
 *  - only billing periods the product policy supports are returned;
 *  - a plan is offered only once it owns at least one active, priced period, so
 *    an unpriced deployment keeps the PREM-D01 promise of an empty catalog;
 *  - a disabled plan is still surfaced, but as `available = false` with no
 *    prices and `purchasable = false`.
 *
 * `checkout_available` remains true only when the canonical Cloud plan has both
 * monthly and yearly prices and Midtrans is configured. Missing data fails
 * closed; no price is ever invented.
 */
class MobileSubscriptionPlanCatalog
{
    public function __construct(
        private readonly PremiumPolicy $policy,
        private readonly SubscriptionCheckoutService $checkout,
        private readonly PremiumPricing $pricing,
    ) {}

    /**
     * @return list<array{code: string, name: string, billing_periods: list<array{period: string, currency: string, price_minor: int}>, currency: string|null, benefits: list<string>, available: bool, purchasable: bool}>
     */
    public function plans(): array
    {
        $plans = [];

        foreach ($this->canonicalPlans() as $plan) {
            if (! $this->pricing->hasActivePrice($plan->code)) {
                continue;
            }

            $available = $plan->is_active;
            $periods = $available ? $this->pricing->periodsFor($plan->code) : [];

            $plans[] = [
                'code' => $plan->code,
                'name' => $plan->name,
                'billing_periods' => $periods,
                'currency' => $periods === [] ? null : $periods[0]['currency'],
                'benefits' => $this->policy->benefits(),
                'available' => $available,
                // A plan is purchasable only when it is available, exposes at
                // least one priced period and checkout is fully configured.
                'purchasable' => $available && $periods !== [] && $this->checkoutAvailable(),
            ];
        }

        return $plans;
    }

    public function checkoutAvailable(): bool
    {
        return $this->checkout->isCheckoutConfigured();
    }

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    private function canonicalPlans(): Collection
    {
        return SubscriptionPlan::query()
            ->whereIn('code', Subscription::canonicalPlanCodes())
            ->orderBy('id')
            ->get();
    }
}
