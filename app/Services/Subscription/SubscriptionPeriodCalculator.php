<?php

namespace App\Services\Subscription;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class SubscriptionPeriodCalculator
{
    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_YEARLY = 'yearly';

    /**
     * Supported billing periods.
     *
     * @return list<string>
     */
    public static function supportedPeriods(): array
    {
        return [
            self::PERIOD_MONTHLY,
            self::PERIOD_YEARLY,
        ];
    }

    /**
     * Determine if the given billing period is supported.
     */
    public function isSupported(string $billingPeriod): bool
    {
        return in_array($billingPeriod, self::supportedPeriods(), true);
    }

    /**
     * Calculate expiry timestamp from base date using canonical calendar additions with no overflow.
     *
     * monthly: +1 calendar month (addMonthNoOverflow)
     * yearly:  +1 calendar year (addYearNoOverflow)
     */
    public function calculateExpiry(DateTimeInterface|CarbonInterface $base, string $billingPeriod): Carbon
    {
        $carbon = Carbon::parse($base);

        return match ($billingPeriod) {
            self::PERIOD_MONTHLY => $carbon->addMonthNoOverflow(),
            self::PERIOD_YEARLY => $carbon->addYearNoOverflow(),
            default => throw new InvalidArgumentException("Unsupported billing period: {$billingPeriod}"),
        };
    }
}
