<?php

namespace App\Support;

final class NumberFormat
{
    /**
     * Format a quantity / stock number cleanly in Indonesian locale without
     * misleading trailing decimal zeros (e.g. 5.000 -> "5", 999.000 -> "999",
     * 1000.000 -> "1.000", 2.500 -> "2,5", 12.750 -> "12,75", -3.000 -> "-3").
     */
    public static function quantity(float|int|string|null $value, int $maxDecimals = 3): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $number = (float) $value;
        if (abs($number) < 0.0000001) {
            return '0';
        }

        $formatted = number_format($number, $maxDecimals, ',', '.');
        $trimmed = rtrim(rtrim($formatted, '0'), ',');

        return $trimmed === '-0' ? '0' : $trimmed;
    }

    /**
     * Format a signed delta quantity (e.g. +5, -2, +2,5, 0).
     */
    public static function signedQuantity(float|int|string|null $value, int $maxDecimals = 3): string
    {
        $number = (float) ($value ?? 0);
        if (abs($number) < 0.0000001) {
            return '0';
        }

        $prefix = $number > 0 ? '+' : '';

        return $prefix.self::quantity($number, $maxDecimals);
    }

    /**
     * Format a numeric value for HTML <input type="number"> without trailing zeros
     * (e.g. 5.000 -> "5", 2.500 -> "2.5").
     */
    public static function input(float|int|string|null $value, int $maxDecimals = 3): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;
        if (abs($number) < 0.0000001) {
            return '0';
        }

        $formatted = number_format($number, $maxDecimals, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed === '-0' ? '0' : $trimmed;
    }
}
