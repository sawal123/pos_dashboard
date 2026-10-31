<?php

namespace App\Enums;

/**
 * Canonical stock movement types.
 *
 * `sale` is written by the sync pipeline. The other cases are written by the
 * dashboard ledger (INV-D01) so every stock change has an explicit reason.
 */
enum StockMovementType: string
{
    case Sale = 'sale';

    case Receipt = 'receipt';

    case Adjustment = 'adjustment';

    case Opname = 'opname';

    case Usage = 'usage';

    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Penjualan',
            self::Receipt => 'Penerimaan',
            self::Adjustment => 'Penyesuaian',
            self::Opname => 'Opname',
            self::Usage => 'Pemakaian',
            self::Return => 'Retur',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Display label for a raw stored value, falling back to a humanized form so
     * legacy or unknown values never render as an empty cell.
     */
    public static function labelFor(string $raw): string
    {
        $case = self::tryFrom(strtolower($raw));

        return $case?->label() ?? ucwords(str_replace('_', ' ', $raw));
    }
}
