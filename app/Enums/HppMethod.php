<?php

namespace App\Enums;

enum HppMethod: string
{
    case Average = 'average';
    case LastPurchase = 'last_purchase';
    case Recipe = 'recipe';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Average => 'Rata-rata Pembelian (Stok)',
            self::LastPurchase => 'Harga Pembelian Terakhir',
            self::Recipe => 'Resep / Komposisi Bahan',
            self::None => 'Tidak Dihitung',
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
