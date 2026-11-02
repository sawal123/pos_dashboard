<?php

namespace App\Enums;

enum CostSource: string
{
    case Manual = 'manual';
    case Hpp = 'hpp';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Hpp => 'Otomatis (Ikuti HPP)',
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
