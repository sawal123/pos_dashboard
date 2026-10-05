<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Metadata definition and whitelist registry for manageable platform settings.
 */
class PlatformSettingDefinition
{
    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const GROUP_CLOUD_DEVICES = 'cloud_devices';

    public const GROUP_ALERTS = 'alerts';

    public const GROUP_FEATURES = 'features';

    // Whitelist Setting Keys
    public const KEY_DEVICE_LIMIT = 'premium.device_limit';

    public const KEY_ALERT_EXPIRY_DAYS = 'alerts.subscription_expiry_days';

    public const KEY_ALERT_BACKUP_STALE_DAYS = 'alerts.backup_stale_days';

    public const KEY_FEATURE_WEB_DASHBOARD = 'feature.web_dashboard';

    public const KEY_FEATURE_CLOUD_SYNC = 'feature.cloud_sync';

    public const KEY_FEATURE_CLOUD_DEVICES = 'feature.cloud_devices';

    public const KEY_FEATURE_CLOUD_BACKUP = 'feature.cloud_backup';

    public const KEY_FEATURE_CLOUD_RESTORE = 'feature.cloud_restore';

    /**
     * Forbidden secret/credential keywords that must never be accepted as platform settings.
     *
     * @var list<string>
     */
    protected const FORBIDDEN_PATTERNS = [
        'server_key',
        'client_key',
        'secret',
        'password',
        'app_key',
        'token',
        'credential',
        'midtrans',
        'database',
        'mail',
        'aws',
    ];

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $key,
        public readonly string $slug,
        public readonly string $group,
        public readonly string $label,
        public readonly string $description,
        public readonly string $type,
        public readonly mixed $default,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $capability = null,
        public readonly array $options = [],
    ) {}

    /**
     * Retrieve all canonical setting definitions indexed by key.
     *
     * @return array<string, self>
     */
    public static function all(): array
    {
        $deviceLimitDefault = (int) config('premium.plan.device_limit', 5);

        return [
            self::KEY_DEVICE_LIMIT => new self(
                key: self::KEY_DEVICE_LIMIT,
                slug: 'device-limit',
                group: self::GROUP_CLOUD_DEVICES,
                label: 'Batas Perangkat Cloud',
                description: 'Jumlah maksimal perangkat POS aktif yang dapat terhubung bersamaan untuk setiap bisnis dengan langganan Cloud aktif.',
                type: self::TYPE_INTEGER,
                default: $deviceLimitDefault > 0 ? $deviceLimitDefault : 5,
                min: 1,
                max: 50,
            ),
            self::KEY_ALERT_EXPIRY_DAYS => new self(
                key: self::KEY_ALERT_EXPIRY_DAYS,
                slug: 'subscription-expiry-days',
                group: self::GROUP_ALERTS,
                label: 'Ambang Peringatan Kedaluwarsa Langganan',
                description: 'Masa tenggang dalam hari sebelum batas kedaluwarsa langganan Cloud untuk memicu alert operasional (peringatan).',
                type: self::TYPE_INTEGER,
                default: 7,
                min: 1,
                max: 30,
            ),
            self::KEY_ALERT_BACKUP_STALE_DAYS => new self(
                key: self::KEY_ALERT_BACKUP_STALE_DAYS,
                slug: 'backup-stale-days',
                group: self::GROUP_ALERTS,
                label: 'Ambang Batas Backup Cloud Usang (Stale)',
                description: 'Batas hari sejak cadangan database READY terakhir sebelum bisnis Cloud dikategorikan memiliki backup usang.',
                type: self::TYPE_INTEGER,
                default: 7,
                min: 1,
                max: 90,
            ),
            self::KEY_FEATURE_WEB_DASHBOARD => new self(
                key: self::KEY_FEATURE_WEB_DASHBOARD,
                slug: 'feature-web-dashboard',
                group: self::GROUP_FEATURES,
                label: 'Web Dashboard (Cloud)',
                description: 'Emergency kill-switch runtime untuk membatasi akses Web Dashboard Cloud bisnis.',
                type: self::TYPE_BOOLEAN,
                default: true,
                capability: 'web_dashboard',
            ),
            self::KEY_FEATURE_CLOUD_SYNC => new self(
                key: self::KEY_FEATURE_CLOUD_SYNC,
                slug: 'feature-cloud-sync',
                group: self::GROUP_FEATURES,
                label: 'Cloud Sync Engine',
                description: 'Emergency kill-switch runtime untuk menonaktifkan endpoint sinkronisasi push & pull data POS.',
                type: self::TYPE_BOOLEAN,
                default: true,
                capability: 'cloud_sync',
            ),
            self::KEY_FEATURE_CLOUD_DEVICES => new self(
                key: self::KEY_FEATURE_CLOUD_DEVICES,
                slug: 'feature-cloud-devices',
                group: self::GROUP_FEATURES,
                label: 'Cloud Device Fleet',
                description: 'Emergency kill-switch runtime untuk menonaktifkan pendaftaran dan otorisasi perangkat Cloud.',
                type: self::TYPE_BOOLEAN,
                default: true,
                capability: 'cloud_devices',
            ),
            self::KEY_FEATURE_CLOUD_BACKUP => new self(
                key: self::KEY_FEATURE_CLOUD_BACKUP,
                slug: 'feature-cloud-backup',
                group: self::GROUP_FEATURES,
                label: 'Cloud Backup Service',
                description: 'Emergency kill-switch runtime untuk menolak upload dan pembuatan backup database Cloud baru.',
                type: self::TYPE_BOOLEAN,
                default: true,
                capability: 'cloud_backup',
            ),
            self::KEY_FEATURE_CLOUD_RESTORE => new self(
                key: self::KEY_FEATURE_CLOUD_RESTORE,
                slug: 'feature-cloud-restore',
                group: self::GROUP_FEATURES,
                label: 'Cloud Restore Service',
                description: 'Emergency kill-switch runtime untuk menolak otorisasi unduhan pemulihan cadangan database.',
                type: self::TYPE_BOOLEAN,
                default: true,
                capability: 'cloud_restore',
            ),
        ];
    }

    /**
     * Find definition by exact key name.
     */
    public static function findByKey(string $key): ?self
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Find definition by URL-safe slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        foreach (self::all() as $def) {
            if ($def->slug === $slug) {
                return $def;
            }
        }

        return null;
    }

    /**
     * Check if key is a whitelisted manageable setting.
     */
    public static function isValidKey(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * Check whether key matches secret, credential, or forbidden pattern.
     */
    public static function isSecretOrForbidden(string $key): bool
    {
        $normalized = strtolower(trim($key));

        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Human-readable label for settings group.
     */
    public static function groupLabel(string $group): string
    {
        return match ($group) {
            self::GROUP_CLOUD_DEVICES => 'Kapasitas & Batas Perangkat Cloud',
            self::GROUP_ALERTS => 'Ambang Batas Alert Operasional',
            self::GROUP_FEATURES => 'Kill-Switch Fitur Cloud (Emergency Shutdown)',
            default => ucfirst(str_replace('_', ' ', $group)),
        };
    }
}
