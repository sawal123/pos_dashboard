<?php

namespace App\Support;

class PlatformAuditAction
{
    // Business actions
    public const BUSINESS_SUSPENDED = 'business.suspended';

    public const BUSINESS_REACTIVATED = 'business.reactivated';

    // Subscription actions
    public const SUBSCRIPTION_CLOUD_ACTIVATED = 'subscription.cloud_activated';

    public const SUBSCRIPTION_CLOUD_RENEWED = 'subscription.cloud_renewed';

    public const SUBSCRIPTION_DOWNGRADED = 'subscription.downgraded_to_free';

    public const SUBSCRIPTION_INACTIVATED = 'subscription.inactivated';

    // Premium plan actions
    public const SUBSCRIPTION_PLAN_UPDATED = 'subscription_plan.updated';

    // Pricing actions
    public const SUBSCRIPTION_PRICE_CREATED = 'subscription_price.created';

    public const SUBSCRIPTION_PRICE_UPDATED = 'subscription_price.updated';

    // Device actions
    public const DEVICE_DEACTIVATED = 'device.deactivated';

    public const DEVICE_REACTIVATED = 'device.reactivated';

    // Target types
    public const TARGET_BUSINESS = 'business';

    public const TARGET_SUBSCRIPTION = 'subscription';

    public const TARGET_DEVICE = 'device';

    public const TARGET_SUBSCRIPTION_PLAN = 'subscription_plan';

    public const TARGET_SUBSCRIPTION_PLAN_PRICE = 'subscription_plan_price';

    /**
     * Map machine action string to human Indonesian label.
     */
    public static function label(string $action): string
    {
        return match ($action) {
            self::BUSINESS_SUSPENDED => 'Bisnis Dinonaktifkan (Suspend)',
            self::BUSINESS_REACTIVATED => 'Bisnis Diaktifkan Kembali',
            self::SUBSCRIPTION_CLOUD_ACTIVATED => 'Langganan Cloud Diaktifkan',
            self::SUBSCRIPTION_CLOUD_RENEWED => 'Langganan Cloud Diperpanjang',
            self::SUBSCRIPTION_DOWNGRADED => 'Langganan Diturunkan ke Free',
            self::SUBSCRIPTION_INACTIVATED => 'Langganan Dinonaktifkan',
            self::SUBSCRIPTION_PLAN_UPDATED => 'Paket Langganan Diperbarui',
            self::SUBSCRIPTION_PRICE_CREATED => 'Harga Paket Dibuat',
            self::SUBSCRIPTION_PRICE_UPDATED => 'Harga Paket Diperbarui',
            self::DEVICE_DEACTIVATED => 'Perangkat Dinonaktifkan',
            self::DEVICE_REACTIVATED => 'Perangkat Diaktifkan Kembali',
            default => $action,
        };
    }

    /**
     * Get all supported platform audit actions with labels.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            self::BUSINESS_SUSPENDED => self::label(self::BUSINESS_SUSPENDED),
            self::BUSINESS_REACTIVATED => self::label(self::BUSINESS_REACTIVATED),
            self::SUBSCRIPTION_CLOUD_ACTIVATED => self::label(self::SUBSCRIPTION_CLOUD_ACTIVATED),
            self::SUBSCRIPTION_CLOUD_RENEWED => self::label(self::SUBSCRIPTION_CLOUD_RENEWED),
            self::SUBSCRIPTION_DOWNGRADED => self::label(self::SUBSCRIPTION_DOWNGRADED),
            self::SUBSCRIPTION_INACTIVATED => self::label(self::SUBSCRIPTION_INACTIVATED),
            self::SUBSCRIPTION_PLAN_UPDATED => self::label(self::SUBSCRIPTION_PLAN_UPDATED),
            self::SUBSCRIPTION_PRICE_CREATED => self::label(self::SUBSCRIPTION_PRICE_CREATED),
            self::SUBSCRIPTION_PRICE_UPDATED => self::label(self::SUBSCRIPTION_PRICE_UPDATED),
            self::DEVICE_DEACTIVATED => self::label(self::DEVICE_DEACTIVATED),
            self::DEVICE_REACTIVATED => self::label(self::DEVICE_REACTIVATED),
        ];
    }

    /**
     * Map target type to human Indonesian label.
     */
    public static function targetTypeLabel(string $type): string
    {
        return match ($type) {
            self::TARGET_BUSINESS => 'Bisnis',
            self::TARGET_SUBSCRIPTION => 'Langganan',
            self::TARGET_DEVICE => 'Perangkat',
            self::TARGET_SUBSCRIPTION_PLAN => 'Paket Langganan',
            self::TARGET_SUBSCRIPTION_PLAN_PRICE => 'Harga Paket',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Get all target types with human labels.
     *
     * @return array<string, string>
     */
    public static function targetTypes(): array
    {
        return [
            self::TARGET_BUSINESS => self::targetTypeLabel(self::TARGET_BUSINESS),
            self::TARGET_SUBSCRIPTION => self::targetTypeLabel(self::TARGET_SUBSCRIPTION),
            self::TARGET_DEVICE => self::targetTypeLabel(self::TARGET_DEVICE),
            self::TARGET_SUBSCRIPTION_PLAN => self::targetTypeLabel(self::TARGET_SUBSCRIPTION_PLAN),
            self::TARGET_SUBSCRIPTION_PLAN_PRICE => self::targetTypeLabel(self::TARGET_SUBSCRIPTION_PLAN_PRICE),
        ];
    }

    /**
     * Badge visual styling based on action category.
     */
    public static function badgeClass(string $action): string
    {
        return match ($action) {
            self::BUSINESS_REACTIVATED,
            self::SUBSCRIPTION_CLOUD_ACTIVATED,
            self::SUBSCRIPTION_CLOUD_RENEWED,
            self::SUBSCRIPTION_PRICE_CREATED,
            self::DEVICE_REACTIVATED => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200/80 dark:border-emerald-800',

            self::BUSINESS_SUSPENDED,
            self::SUBSCRIPTION_INACTIVATED,
            self::SUBSCRIPTION_DOWNGRADED,
            self::DEVICE_DEACTIVATED => 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200/80 dark:border-rose-800',

            self::SUBSCRIPTION_PLAN_UPDATED,
            self::SUBSCRIPTION_PRICE_UPDATED => 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200/80 dark:border-indigo-800',

            default => 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }
}
