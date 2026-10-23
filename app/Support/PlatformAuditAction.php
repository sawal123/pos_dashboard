<?php

namespace App\Support;

class PlatformAuditAction
{
    // Business actions
    public const BUSINESS_SUSPENDED = 'business.suspended';

    public const BUSINESS_REACTIVATED = 'business.reactivated';

    public const BUSINESS_UPDATED = 'business.updated';

    public const BUSINESS_DELETED = 'business.deleted';

    // Subscription actions
    public const SUBSCRIPTION_CLOUD_ACTIVATED = 'subscription.cloud_activated';

    public const SUBSCRIPTION_CLOUD_RENEWED = 'subscription.cloud_renewed';

    public const SUBSCRIPTION_DOWNGRADED = 'subscription.downgraded_to_free';

    public const SUBSCRIPTION_INACTIVATED = 'subscription.inactivated';

    public const SUBSCRIPTION_UPDATED = 'subscription.updated';

    public const SUBSCRIPTION_DELETED = 'subscription.deleted';

    // Payment actions
    public const PAYMENT_STATUS_OVERRIDDEN = 'payment.status_overridden';

    public const PAYMENT_DELETED = 'payment.deleted';

    // User actions
    public const USER_UPDATED = 'user.updated';

    public const USER_DELETED = 'user.deleted';

    // Premium plan actions
    public const SUBSCRIPTION_PLAN_UPDATED = 'subscription_plan.updated';

    // Pricing actions
    public const SUBSCRIPTION_PRICE_CREATED = 'subscription_price.created';

    public const SUBSCRIPTION_PRICE_UPDATED = 'subscription_price.updated';

    // Device actions
    public const DEVICE_DEACTIVATED = 'device.deactivated';

    public const DEVICE_REACTIVATED = 'device.reactivated';

    public const DEVICE_UPDATED = 'device.updated';

    public const DEVICE_DELETED = 'device.deleted';

    // Sync actions
    public const SYNC_REQUEST_UPDATED = 'sync_request.updated';

    public const SYNC_REQUEST_DELETED = 'sync_request.deleted';

    // Platform setting actions
    public const PLATFORM_SETTING_UPDATED = 'platform_setting.updated';

    // Target types
    public const TARGET_BUSINESS = 'business';

    public const TARGET_USER = 'user';

    public const TARGET_SUBSCRIPTION = 'subscription';

    public const TARGET_PAYMENT = 'payment';

    public const TARGET_DEVICE = 'device';

    public const TARGET_SYNC_REQUEST = 'sync_request';

    public const TARGET_SUBSCRIPTION_PLAN = 'subscription_plan';

    public const TARGET_SUBSCRIPTION_PLAN_PRICE = 'subscription_plan_price';

    public const TARGET_PLATFORM_SETTING = 'platform_setting';

    /**
     * Map machine action string to human Indonesian label.
     */
    public static function label(string $action): string
    {
        return match ($action) {
            self::BUSINESS_SUSPENDED => 'Bisnis Dinonaktifkan (Suspend)',
            self::BUSINESS_REACTIVATED => 'Bisnis Diaktifkan Kembali',
            self::BUSINESS_UPDATED => 'Bisnis Diperbarui',
            self::BUSINESS_DELETED => 'Bisnis Dihapus',
            self::SUBSCRIPTION_CLOUD_ACTIVATED => 'Langganan Cloud Diaktifkan',
            self::SUBSCRIPTION_CLOUD_RENEWED => 'Langganan Cloud Diperpanjang',
            self::SUBSCRIPTION_DOWNGRADED => 'Langganan Diturunkan ke Free',
            self::SUBSCRIPTION_INACTIVATED => 'Langganan Dinonaktifkan',
            self::SUBSCRIPTION_UPDATED => 'Langganan Diperbarui',
            self::SUBSCRIPTION_DELETED => 'Langganan Dihapus',
            self::PAYMENT_STATUS_OVERRIDDEN => 'Status Pembayaran Diubah',
            self::PAYMENT_DELETED => 'Pembayaran Dihapus',
            self::USER_UPDATED => 'Pengguna Diperbarui',
            self::USER_DELETED => 'Pengguna Dihapus',
            self::SUBSCRIPTION_PLAN_UPDATED => 'Paket Langganan Diperbarui',
            self::SUBSCRIPTION_PRICE_CREATED => 'Harga Paket Dibuat',
            self::SUBSCRIPTION_PRICE_UPDATED => 'Harga Paket Diperbarui',
            self::DEVICE_DEACTIVATED => 'Perangkat Dinonaktifkan',
            self::DEVICE_REACTIVATED => 'Perangkat Diaktifkan Kembali',
            self::DEVICE_UPDATED => 'Perangkat Diperbarui',
            self::DEVICE_DELETED => 'Perangkat Dihapus',
            self::SYNC_REQUEST_UPDATED => 'Request Sinkronisasi Diperbarui',
            self::SYNC_REQUEST_DELETED => 'Request Sinkronisasi Dihapus',
            self::PLATFORM_SETTING_UPDATED => 'Pengaturan Platform Diperbarui',
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
            self::BUSINESS_UPDATED => self::label(self::BUSINESS_UPDATED),
            self::BUSINESS_DELETED => self::label(self::BUSINESS_DELETED),
            self::SUBSCRIPTION_CLOUD_ACTIVATED => self::label(self::SUBSCRIPTION_CLOUD_ACTIVATED),
            self::SUBSCRIPTION_CLOUD_RENEWED => self::label(self::SUBSCRIPTION_CLOUD_RENEWED),
            self::SUBSCRIPTION_DOWNGRADED => self::label(self::SUBSCRIPTION_DOWNGRADED),
            self::SUBSCRIPTION_INACTIVATED => self::label(self::SUBSCRIPTION_INACTIVATED),
            self::SUBSCRIPTION_UPDATED => self::label(self::SUBSCRIPTION_UPDATED),
            self::SUBSCRIPTION_DELETED => self::label(self::SUBSCRIPTION_DELETED),
            self::PAYMENT_STATUS_OVERRIDDEN => self::label(self::PAYMENT_STATUS_OVERRIDDEN),
            self::PAYMENT_DELETED => self::label(self::PAYMENT_DELETED),
            self::USER_UPDATED => self::label(self::USER_UPDATED),
            self::USER_DELETED => self::label(self::USER_DELETED),
            self::SUBSCRIPTION_PLAN_UPDATED => self::label(self::SUBSCRIPTION_PLAN_UPDATED),
            self::SUBSCRIPTION_PRICE_CREATED => self::label(self::SUBSCRIPTION_PRICE_CREATED),
            self::SUBSCRIPTION_PRICE_UPDATED => self::label(self::SUBSCRIPTION_PRICE_UPDATED),
            self::DEVICE_DEACTIVATED => self::label(self::DEVICE_DEACTIVATED),
            self::DEVICE_REACTIVATED => self::label(self::DEVICE_REACTIVATED),
            self::DEVICE_UPDATED => self::label(self::DEVICE_UPDATED),
            self::DEVICE_DELETED => self::label(self::DEVICE_DELETED),
            self::SYNC_REQUEST_UPDATED => self::label(self::SYNC_REQUEST_UPDATED),
            self::SYNC_REQUEST_DELETED => self::label(self::SYNC_REQUEST_DELETED),
            self::PLATFORM_SETTING_UPDATED => self::label(self::PLATFORM_SETTING_UPDATED),
        ];
    }

    /**
     * Map target type to human Indonesian label.
     */
    public static function targetTypeLabel(string $type): string
    {
        return match ($type) {
            self::TARGET_BUSINESS => 'Bisnis',
            self::TARGET_USER => 'Pengguna',
            self::TARGET_SUBSCRIPTION => 'Langganan',
            self::TARGET_PAYMENT => 'Pembayaran',
            self::TARGET_DEVICE => 'Perangkat',
            self::TARGET_SYNC_REQUEST => 'Request Sinkronisasi',
            self::TARGET_SUBSCRIPTION_PLAN => 'Paket Langganan',
            self::TARGET_SUBSCRIPTION_PLAN_PRICE => 'Harga Paket',
            self::TARGET_PLATFORM_SETTING => 'Pengaturan Platform',
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
            self::TARGET_USER => self::targetTypeLabel(self::TARGET_USER),
            self::TARGET_SUBSCRIPTION => self::targetTypeLabel(self::TARGET_SUBSCRIPTION),
            self::TARGET_PAYMENT => self::targetTypeLabel(self::TARGET_PAYMENT),
            self::TARGET_DEVICE => self::targetTypeLabel(self::TARGET_DEVICE),
            self::TARGET_SYNC_REQUEST => self::targetTypeLabel(self::TARGET_SYNC_REQUEST),
            self::TARGET_SUBSCRIPTION_PLAN => self::targetTypeLabel(self::TARGET_SUBSCRIPTION_PLAN),
            self::TARGET_SUBSCRIPTION_PLAN_PRICE => self::targetTypeLabel(self::TARGET_SUBSCRIPTION_PLAN_PRICE),
            self::TARGET_PLATFORM_SETTING => self::targetTypeLabel(self::TARGET_PLATFORM_SETTING),
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
            self::BUSINESS_DELETED,
            self::SUBSCRIPTION_INACTIVATED,
            self::SUBSCRIPTION_DELETED,
            self::SUBSCRIPTION_DOWNGRADED,
            self::PAYMENT_DELETED,
            self::USER_DELETED,
            self::DEVICE_DEACTIVATED,
            self::DEVICE_DELETED,
            self::SYNC_REQUEST_DELETED => 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200/80 dark:border-rose-800',

            self::BUSINESS_UPDATED,
            self::SUBSCRIPTION_UPDATED,
            self::PAYMENT_STATUS_OVERRIDDEN,
            self::USER_UPDATED,
            self::DEVICE_UPDATED,
            self::SYNC_REQUEST_UPDATED,
            self::SUBSCRIPTION_PLAN_UPDATED,
            self::SUBSCRIPTION_PRICE_UPDATED,
            self::PLATFORM_SETTING_UPDATED => 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200/80 dark:border-indigo-800',

            default => 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }
}
