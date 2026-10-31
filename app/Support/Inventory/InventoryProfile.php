<?php

namespace App\Support\Inventory;

use App\Enums\BusinessType;

/**
 * Inventory defaults per business type (INV-D02).
 *
 * This is presentation/guidance only: it never grants or removes a permission.
 * Authorization stays with `BusinessPermission`.
 */
final class InventoryProfile
{
    /**
     * @return array{
     *     tracked_kinds: list<string>,
     *     default_units: list<string>,
     *     opname_cadence_days: int,
     *     promo_templates: list<string>
     * }
     */
    public static function for(?BusinessType $type): array
    {
        return match ($type) {
            BusinessType::Cafe => [
                'tracked_kinds' => ['product'],
                'default_units' => ['pcs', 'gram', 'ml'],
                'opname_cadence_days' => 7,
                'promo_templates' => ['happy_hour', 'bundle'],
            ],
            BusinessType::Laundry => [
                'tracked_kinds' => ['product'],
                'default_units' => ['kg', 'liter', 'pcs'],
                'opname_cadence_days' => 14,
                'promo_templates' => ['min_weight', 'package'],
            ],
            BusinessType::Grosir => [
                'tracked_kinds' => ['product'],
                'default_units' => ['pcs', 'dus', 'karung'],
                'opname_cadence_days' => 30,
                'promo_templates' => ['volume', 'member_price'],
            ],
            null => [
                'tracked_kinds' => ['product'],
                'default_units' => ['pcs'],
                'opname_cadence_days' => 30,
                'promo_templates' => [],
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function defaultUnits(?BusinessType $type): array
    {
        return self::for($type)['default_units'];
    }
}
