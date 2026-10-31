<?php

namespace App\Enums;

enum PromoStatus: string
{
    case Draft = 'draft';

    case Active = 'active';

    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
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
