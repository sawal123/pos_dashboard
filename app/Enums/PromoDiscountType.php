<?php

namespace App\Enums;

enum PromoDiscountType: string
{
    case Percent = 'percent';

    case Fixed = 'fixed';

    case PriceOverride = 'price_override';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Persentase',
            self::Fixed => 'Potongan Nominal',
            self::PriceOverride => 'Harga Khusus',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
