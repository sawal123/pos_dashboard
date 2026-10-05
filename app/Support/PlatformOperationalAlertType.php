<?php

namespace App\Support;

class PlatformOperationalAlertType
{
    // Alert Types
    public const TYPE_SUBSCRIPTION_EXPIRING_SOON = 'subscription.expiring_soon';

    public const TYPE_SUBSCRIPTION_EXPIRED = 'subscription.expired';

    public const TYPE_PAYMENT_PAID_NOT_ACTIVATED = 'payment.paid_not_activated';

    public const TYPE_PAYMENT_NON_PAID_ACTIVATED = 'payment.non_paid_activated';

    public const TYPE_DEVICE_LIMIT_REACHED = 'device.limit_reached';

    public const TYPE_DEVICE_LIMIT_NEAR = 'device.limit_near';

    public const TYPE_BACKUP_NEVER_CREATED = 'backup.never_created';

    public const TYPE_BACKUP_STALE = 'backup.stale';

    // Severities
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    /**
     * Map alert type to human Indonesian label.
     */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_SUBSCRIPTION_EXPIRING_SOON => 'Langganan Segera Berakhir',
            self::TYPE_SUBSCRIPTION_EXPIRED => 'Langganan Sudah Kedaluwarsa',
            self::TYPE_PAYMENT_PAID_NOT_ACTIVATED => 'Pembayaran Belum Diaktifkan',
            self::TYPE_PAYMENT_NON_PAID_ACTIVATED => 'Aktivasi Mismatch Non-Paid',
            self::TYPE_DEVICE_LIMIT_REACHED => 'Batas Kuota Perangkat Tercapai',
            self::TYPE_DEVICE_LIMIT_NEAR => 'Batas Kuota Perangkat Hampir Penuh',
            self::TYPE_BACKUP_NEVER_CREATED => 'Backup Cloud Belum Pernah Dibuat',
            self::TYPE_BACKUP_STALE => 'Backup Cloud Usang (> 7 Hari)',
            default => $type,
        };
    }

    /**
     * Supported alert types with labels.
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_SUBSCRIPTION_EXPIRING_SOON => self::typeLabel(self::TYPE_SUBSCRIPTION_EXPIRING_SOON),
            self::TYPE_SUBSCRIPTION_EXPIRED => self::typeLabel(self::TYPE_SUBSCRIPTION_EXPIRED),
            self::TYPE_PAYMENT_PAID_NOT_ACTIVATED => self::typeLabel(self::TYPE_PAYMENT_PAID_NOT_ACTIVATED),
            self::TYPE_PAYMENT_NON_PAID_ACTIVATED => self::typeLabel(self::TYPE_PAYMENT_NON_PAID_ACTIVATED),
            self::TYPE_DEVICE_LIMIT_REACHED => self::typeLabel(self::TYPE_DEVICE_LIMIT_REACHED),
            self::TYPE_DEVICE_LIMIT_NEAR => self::typeLabel(self::TYPE_DEVICE_LIMIT_NEAR),
            self::TYPE_BACKUP_NEVER_CREATED => self::typeLabel(self::TYPE_BACKUP_NEVER_CREATED),
            self::TYPE_BACKUP_STALE => self::typeLabel(self::TYPE_BACKUP_STALE),
        ];
    }

    /**
     * Supported severities with labels.
     *
     * @return array<string, string>
     */
    public static function severities(): array
    {
        return [
            self::SEVERITY_CRITICAL => 'Kritis',
            self::SEVERITY_WARNING => 'Peringatan',
            self::SEVERITY_INFO => 'Informasi',
        ];
    }

    /**
     * Human label for severity.
     */
    public static function severityLabel(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 'Kritis',
            self::SEVERITY_WARNING => 'Peringatan',
            self::SEVERITY_INFO => 'Informasi',
            default => ucfirst($severity),
        };
    }

    /**
     * Numeric priority for sorting (lower = higher priority).
     */
    public static function severityPriority(string $severity): int
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 1,
            self::SEVERITY_WARNING => 2,
            self::SEVERITY_INFO => 3,
            default => 99,
        };
    }

    /**
     * Badge CSS class for severity.
     */
    public static function severityBadgeClass(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200/80 dark:border-rose-800',
            self::SEVERITY_WARNING => 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200/80 dark:border-amber-800',
            self::SEVERITY_INFO => 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border-blue-200/80 dark:border-blue-800',
            default => 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }

    /**
     * Alias for severityBadgeClass.
     */
    public static function severityBadgeClasses(string $severity): string
    {
        return self::severityBadgeClass($severity);
    }

    /**
     * Icon name for severity.
     */
    public static function severityIcon(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 'alert-circle',
            self::SEVERITY_WARNING => 'alert-triangle',
            self::SEVERITY_INFO => 'info',
            default => 'bell',
        };
    }
}
