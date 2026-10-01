<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;

/**
 * PREM-D02C — server-authoritative Premium pricing, read from the database.
 *
 * The mobile client never supplies a price. `priceFor()` resolves the integer
 * minor-unit amount from `subscription_plan_prices`; checkout snapshots it onto
 * the payment row so historical transactions never follow a later price change.
 *
 * Everything fails closed: a missing/inactive plan, an inactive price, an
 * unsupported billing period, a non-positive amount or an empty currency all
 * yield `null` (or an empty list) instead of a guessed number. Config and ENV
 * are no longer a pricing source.
 */
final class PremiumPricing
{
    public function __construct(
        private readonly PremiumPolicy $policy,
    ) {}

    /**
     * Resolve the current price for a canonical plan/period pair.
     *
     * @return array{currency: string, price_minor: int}|null
     */
    public function priceFor(string $plan, string $period): ?array
    {
        if (! $this->isCanonicalPlan($plan)) {
            return null;
        }

        if (! $this->policy->supportsBillingPeriod($period)) {
            return null;
        }

        $price = $this->activePrice($plan, $period);

        if ($price === null) {
            return null;
        }

        return [
            'currency' => $price->currency,
            'price_minor' => $price->price_minor,
        ];
    }

    /**
     * Active, policy-supported priced periods for a plan, in policy order.
     *
     * @return list<array{period: string, currency: string, price_minor: int}>
     */
    public function periodsFor(string $plan): array
    {
        if (! $this->isCanonicalPlan($plan)) {
            return [];
        }

        $periods = [];

        foreach ($this->policy->billingPeriods() as $period) {
            $price = $this->activePrice($plan, $period);

            if ($price === null) {
                continue;
            }

            $periods[] = [
                'period' => $period,
                'currency' => $price->currency,
                'price_minor' => $price->price_minor,
            ];
        }

        return $periods;
    }

    /**
     * Whether a plan owns at least one active, priced supported period.
     *
     * Unlike {@see priceFor()} this ignores the plan's own `is_active` flag, so
     * the catalog can still surface a disabled plan as unavailable.
     */
    public function hasActivePrice(string $plan): bool
    {
        if (! $this->isCanonicalPlan($plan)) {
            return false;
        }

        $planId = $this->planId($plan, activeOnly: false);

        if ($planId === null) {
            return false;
        }

        return SubscriptionPlanPrice::query()
            ->where('subscription_plan_id', $planId)
            ->where('is_active', true)
            ->where('price_minor', '>', 0)
            ->whereIn('billing_period', $this->policy->billingPeriods())
            ->exists();
    }

    /**
     * Whether any canonical plan currently has a purchasable price.
     */
    public function isConfigured(): bool
    {
        foreach ($this->policy->billingPeriods() as $period) {
            if ($this->priceFor($this->policy->planCode(), $period) !== null) {
                return true;
            }
        }

        return false;
    }

    private function activePrice(string $plan, string $period): ?SubscriptionPlanPrice
    {
        $planId = $this->planId($plan);

        if ($planId === null) {
            return null;
        }

        return SubscriptionPlanPrice::query()
            ->where('subscription_plan_id', $planId)
            ->where('billing_period', $period)
            ->where('is_active', true)
            ->where('price_minor', '>', 0)
            ->where('currency', '!=', '')
            ->orderBy('id')
            ->first();
    }

    /**
     * The id of a plan with the given code, or null when missing.
     */
    private function planId(string $plan, bool $activeOnly = true): ?int
    {
        $query = SubscriptionPlan::query()->where('code', $plan);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $id = $query->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function isCanonicalPlan(string $plan): bool
    {
        return $plan === $this->policy->planCode()
            && $plan === Subscription::PLAN_CLOUD;
    }
}
