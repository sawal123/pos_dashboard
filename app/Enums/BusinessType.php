<?php

namespace App\Enums;

/**
 * Canonical business types (DASH-14).
 *
 * This enum is the single source of truth for:
 *  - the allowlist of stored/internal values,
 *  - the display label per value,
 *  - normalization of explicit legacy/alias input,
 *  - the "unknown" state.
 *
 * Internal values are intentionally stored *canonically* (`cafe`, `laundry`,
 * `grosir`); POS Mobile display labels ("Cafe / UMKM", ...) are presentation
 * only. `null` always means "not determined" and is never coerced to `cafe`.
 */
enum BusinessType: string
{
    case Cafe = 'cafe';

    case Laundry = 'laundry';

    case Grosir = 'grosir';

    /**
     * Human-readable label (matches the POS Mobile template labels).
     */
    public function label(): string
    {
        return match ($this) {
            self::Cafe => 'Cafe / UMKM',
            self::Laundry => 'Laundry',
            self::Grosir => 'Grosir / Toko Kelontong',
        };
    }

    /**
     * Short description shown in the owner setup form.
     */
    public function description(): string
    {
        return match ($this) {
            self::Cafe => 'Menu makanan, minuman, dan usaha harian.',
            self::Laundry => 'Layanan cuci kiloan, satuan, dan express.',
            self::Grosir => 'Kelola produk, persediaan, dan penjualan.',
        };
    }

    /**
     * The canonical stored values, for validation allowlists.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    /**
     * Selectable options for the owner setup form.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
        ], self::cases());
    }

    /**
     * Resolve *explicit* legacy/alias input to a canonical case.
     *
     * Only recognized input is normalized. `null`, empty/whitespace and any
     * unrecognized value return `null` — the caller must treat that as unknown
     * and never guess `Cafe`.
     */
    public static function tryFromInput(?string $input): ?self
    {
        if ($input === null) {
            return null;
        }

        $key = strtolower(trim($input));

        if ($key === '') {
            return null;
        }

        return match ($key) {
            'cafe', 'cafe / umkm', 'umkm', 'restoran', 'restaurant' => self::Cafe,
            'laundry' => self::Laundry,
            'grosir', 'grosir / toko kelontong', 'toko kelontong', 'retail' => self::Grosir,
            default => null,
        };
    }

    /**
     * Normalize explicit input to a canonical stored value (or null).
     */
    public static function normalize(?string $input): ?string
    {
        return self::tryFromInput($input)?->value;
    }

    /**
     * Whether the input is a canonical value (strict — no aliases).
     */
    public static function isCanonical(?string $input): bool
    {
        return $input !== null && in_array($input, self::values(), true);
    }
}
