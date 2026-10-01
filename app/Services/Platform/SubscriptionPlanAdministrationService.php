<?php

namespace App\Services\Platform;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Services\Subscription\PremiumPolicy;
use App\Services\Subscription\SubscriptionCheckoutService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ADMIN-06 — Platform Admin management of the canonical Premium plan/pricing.
 *
 * Deliberately narrow: only the canonical paid plan (`PremiumPolicy::planCode()`)
 * and its Monthly/Yearly IDR prices can be mutated. Amounts are integer minor
 * units; the plan `code`, the `billing_period` and the `currency` are immutable
 * from here. Rows are never deleted — availability is toggled via `is_active`.
 */
class SubscriptionPlanAdministrationService
{
    public const CURRENCY = 'IDR';

    public function __construct(
        private readonly PremiumPolicy $policy,
        private readonly SubscriptionCheckoutService $checkout,
    ) {}

    /**
     * The canonical paid plan(s) with their prices. Never arbitrary plans.
     *
     * @return Collection<int, SubscriptionPlan>
     */
    public function canonicalPlans(): Collection
    {
        return SubscriptionPlan::query()
            ->where('code', $this->policy->planCode())
            ->with('prices')
            ->orderBy('id')
            ->get();
    }

    public function updatePlan(
        SubscriptionPlan $plan,
        string $name,
        ?string $description,
        bool $isActive,
    ): SubscriptionPlan {
        $this->ensureCanonical($plan);

        return DB::transaction(function () use ($plan, $name, $description, $isActive): SubscriptionPlan {
            // `code` is immutable and deliberately absent from the payload.
            $plan->forceFill([
                'name' => $name,
                'description' => $description,
                'is_active' => $isActive,
            ])->save();

            return $plan;
        });
    }

    /**
     * Create (or, if the canonical period already exists, update) a price row.
     */
    public function createPrice(
        SubscriptionPlan $plan,
        string $billingPeriod,
        int $priceMinor,
        bool $isActive,
    ): SubscriptionPlanPrice {
        $this->ensureCanonical($plan);
        $this->ensureSupportedPeriod($billingPeriod);

        return DB::transaction(function () use ($plan, $billingPeriod, $priceMinor, $isActive): SubscriptionPlanPrice {
            /** @var SubscriptionPlanPrice $price */
            $price = SubscriptionPlanPrice::query()->updateOrCreate(
                [
                    'subscription_plan_id' => $plan->id,
                    'billing_period' => $billingPeriod,
                    'currency' => self::CURRENCY,
                ],
                [
                    'price_minor' => $priceMinor,
                    'is_active' => $isActive,
                ],
            );

            return $price;
        });
    }

    public function updatePrice(
        SubscriptionPlanPrice $price,
        int $priceMinor,
        bool $isActive,
    ): SubscriptionPlanPrice {
        $this->ensureSupportedPeriod($price->billing_period);

        return DB::transaction(function () use ($price, $priceMinor, $isActive): SubscriptionPlanPrice {
            // Identity columns (plan id, billing period, currency) are never writable.
            $price->forceFill([
                'price_minor' => $priceMinor,
                'is_active' => $isActive,
            ])->save();

            return $price;
        });
    }

    /**
     * @return array{ready: bool, reasons: list<string>}
     */
    public function checkoutReadiness(): array
    {
        return $this->checkout->readiness();
    }

    private function ensureCanonical(SubscriptionPlan $plan): void
    {
        if ($plan->code !== $this->policy->planCode()) {
            throw new InvalidArgumentException('Only the canonical Premium plan can be managed here.');
        }
    }

    private function ensureSupportedPeriod(string $billingPeriod): void
    {
        if (! $this->policy->supportsBillingPeriod($billingPeriod)) {
            throw new InvalidArgumentException("Billing period '{$billingPeriod}' is not supported by policy.");
        }
    }
}
