<?php

namespace App\Services\Promo;

use App\Enums\PromoDiscountType;
use App\Enums\PromoScope;
use App\Enums\PromoStatus;
use App\Models\Promo;
use Carbon\CarbonInterface;

/**
 * Pure promo discount math (PROMO-D01).
 *
 * The exact same rounding rule must be implemented by the mobile client:
 *  - percent uses basis points and integer floor division,
 *  - the result is capped by `max_discount` and by the subtotal,
 *  - a promo that is not eligible returns `discount = 0`.
 */
final class PromoEvaluator
{
    /**
     * @param  list<array{product_id: int|string|null, category_id?: int|string|null, unit_price?: int|float|string, quantity?: int|float|string, line_total?: int|float|string}>  $lines
     * @return array{eligible: bool, discount: int, reason: string|null}
     */
    public function evaluate(Promo $promo, int $subtotal, array $lines, CarbonInterface $at): array
    {
        if ((string) $promo->status !== PromoStatus::Active->value) {
            return $this->ineligible('promo_inactive');
        }

        if ($promo->starts_at !== null && $at->lt($promo->starts_at)) {
            return $this->ineligible('promo_not_started');
        }

        if ($promo->ends_at !== null && $at->gt($promo->ends_at)) {
            return $this->ineligible('promo_expired');
        }

        $weekdays = $promo->weekdays;
        if (is_array($weekdays) && $weekdays !== []) {
            $allowed = array_map(static fn (mixed $day): int => (int) $day, $weekdays);
            if (! in_array($at->dayOfWeekIso, $allowed, true)) {
                return $this->ineligible('promo_outside_weekday');
            }
        }

        $time = $at->format('H:i');
        if ($promo->start_time !== null && $promo->start_time !== '' && $time < $promo->start_time) {
            return $this->ineligible('promo_outside_time');
        }
        if ($promo->end_time !== null && $promo->end_time !== '' && $time > $promo->end_time) {
            return $this->ineligible('promo_outside_time');
        }

        if ($subtotal < (int) $promo->min_purchase) {
            return $this->ineligible('promo_min_purchase');
        }

        $matching = $this->matchingLines($promo, $lines);

        if ((string) $promo->scope !== PromoScope::All->value && $matching === []) {
            return $this->ineligible('promo_target_mismatch');
        }

        $discount = $this->discountFor($promo, $subtotal, $matching);

        $cap = $promo->max_discount;
        if ($cap !== null) {
            $discount = min($discount, (int) $cap);
        }

        $discount = max(0, min($discount, $subtotal));

        return ['eligible' => true, 'discount' => $discount, 'reason' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function matchingLines(Promo $promo, array $lines): array
    {
        if ((string) $promo->scope === PromoScope::All->value) {
            return $lines;
        }

        $targets = $promo->relationLoaded('targets')
            ? $promo->targets
            : $promo->targets()->get();

        $productIds = [];
        $categoryIds = [];

        foreach ($targets as $target) {
            if ((string) $target->target_type === PromoScope::Product->value) {
                $productIds[] = (int) $target->target_id;
            } else {
                $categoryIds[] = (int) $target->target_id;
            }
        }

        return array_values(array_filter(
            $lines,
            static function (array $line) use ($productIds, $categoryIds): bool {
                if (in_array((int) ($line['product_id'] ?? 0), $productIds, true)) {
                    return true;
                }

                return in_array((int) ($line['category_id'] ?? 0), $categoryIds, true);
            },
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $matching
     */
    private function discountFor(Promo $promo, int $subtotal, array $matching): int
    {
        $type = $promo->discountType();

        return match ($type) {
            PromoDiscountType::Percent => intdiv($subtotal * (int) $promo->discount_value, 10000),
            PromoDiscountType::Fixed => (int) $promo->discount_value,
            PromoDiscountType::PriceOverride => $this->priceOverrideDiscount($promo, $matching),
            default => 0,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $matching
     */
    private function priceOverrideDiscount(Promo $promo, array $matching): int
    {
        $targetSubtotal = 0;
        $targetQuantity = 0.0;

        foreach ($matching as $line) {
            $targetSubtotal += (int) ($line['line_total'] ?? 0);
            $targetQuantity += (float) ($line['quantity'] ?? 0);
        }

        return max(0, $targetSubtotal - (int) round((int) $promo->discount_value * $targetQuantity));
    }

    /**
     * @return array{eligible: bool, discount: int, reason: string}
     */
    private function ineligible(string $reason): array
    {
        return ['eligible' => false, 'discount' => 0, 'reason' => $reason];
    }
}
