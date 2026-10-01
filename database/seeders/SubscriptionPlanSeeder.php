<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use Illuminate\Database\Seeder;

/**
 * PREM-D02C — canonical Premium plan + DUMMY development prices.
 *
 * The canonical `cloud` plan is created when missing (never overwritten, so an
 * admin rename/disable survives a re-seed).
 *
 * The Rp49.000 / Rp490.000 amounts are **DUMMY placeholders for local/testing
 * only — they are NOT official business pricing**. They are never written in
 * production, and `firstOrCreate` guarantees an existing (admin-edited) price is
 * never clobbered. Initial/demo seeding is deliberately separate from any future
 * price-mutation path.
 */
class SubscriptionPlanSeeder extends Seeder
{
    /** Dummy monthly placeholder — NOT official pricing. */
    public const DUMMY_MONTHLY_PRICE_MINOR = 49000;

    /** Dummy yearly placeholder — NOT official pricing. */
    public const DUMMY_YEARLY_PRICE_MINOR = 490000;

    public const CURRENCY = 'IDR';

    public function run(): void
    {
        $plan = SubscriptionPlan::firstOrCreate(
            ['code' => Subscription::PLAN_CLOUD],
            ['name' => 'Cloud', 'is_active' => true],
        );

        // Placeholder prices must never leak into production. A production
        // environment gets the plan structure only until a Platform Admin sets a
        // real price; checkout stays fail-closed until then.
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->seedDummyPrice($plan, 'monthly', self::DUMMY_MONTHLY_PRICE_MINOR);
        $this->seedDummyPrice($plan, 'yearly', self::DUMMY_YEARLY_PRICE_MINOR);
    }

    private function seedDummyPrice(SubscriptionPlan $plan, string $period, int $priceMinor): void
    {
        SubscriptionPlanPrice::firstOrCreate(
            [
                'subscription_plan_id' => $plan->id,
                'billing_period' => $period,
                'currency' => self::CURRENCY,
            ],
            [
                'price_minor' => $priceMinor,
                'is_active' => true,
            ],
        );
    }
}
