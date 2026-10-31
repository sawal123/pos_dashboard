<?php

namespace App\Enums;

enum PromoScope: string
{
    case All = 'all';

    case Product = 'product';

    case Category = 'category';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Semua Item',
            self::Product => 'Produk Tertentu',
            self::Category => 'Kategori Tertentu',
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
