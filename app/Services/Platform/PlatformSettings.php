<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\PlatformAuditAction;
use App\Support\PlatformSettingDefinition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlatformSettings
{
    public function __construct(
        protected PlatformAuditLogger $auditLogger
    ) {}

    /**
     * Retrieve the raw value of a setting from the database, or return default/fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $record = PlatformSetting::query()->where('key', $key)->first();

            if ($record !== null && $record->value !== null) {
                return $record->value;
            }
        } catch (\Throwable) {
            // Safe fallback if database is inaccessible or migration pending
        }

        $def = PlatformSettingDefinition::findByKey($key);

        return $default ?? $def?->default;
    }

    /**
     * Retrieve integer setting value with safe fallback on corrupt or missing values.
     */
    public function getInt(string $key, ?int $default = null): int
    {
        $def = PlatformSettingDefinition::findByKey($key);
        $fallback = $default ?? ($def && is_int($def->default) ? $def->default : 0);

        try {
            $record = PlatformSetting::query()->where('key', $key)->first();

            if ($record !== null && $record->value !== null) {
                $val = $record->value;

                if (is_numeric($val)) {
                    $intVal = (int) $val;

                    if ($def !== null) {
                        if ($def->min !== null && $intVal < $def->min) {
                            return $fallback;
                        }
                        if ($def->max !== null && $intVal > $def->max) {
                            return $fallback;
                        }
                    }

                    return $intVal;
                }

                return $fallback;
            }
        } catch (\Throwable) {
            // Safe fallback on database/query error
        }

        return $fallback;
    }

    /**
     * Retrieve boolean setting value with safe fallback.
     */
    public function getBool(string $key, ?bool $default = null): bool
    {
        $def = PlatformSettingDefinition::findByKey($key);
        $fallback = $default ?? ($def && is_bool($def->default) ? $def->default : false);

        try {
            $record = PlatformSetting::query()->where('key', $key)->first();

            if ($record !== null && $record->value !== null) {
                $val = $record->value;

                if (is_bool($val)) {
                    return $val;
                }

                if (is_numeric($val)) {
                    return (int) $val === 1;
                }

                if (is_string($val)) {
                    $filtered = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                    return $filtered ?? $fallback;
                }

                return $fallback;
            }
        } catch (\Throwable) {
            // Safe fallback
        }

        return $fallback;
    }

    /**
     * Canonical device limit for Cloud businesses.
     */
    public function deviceLimit(): int
    {
        $configDefault = (int) config('premium.plan.device_limit', 5);
        $fallback = $configDefault > 0 ? $configDefault : 5;

        $val = $this->getInt(PlatformSettingDefinition::KEY_DEVICE_LIMIT, $fallback);

        return $val > 0 ? $val : $fallback;
    }

    /**
     * Window in days before Cloud subscription expiry to trigger warning alerts.
     */
    public function subscriptionExpiryDays(): int
    {
        return $this->getInt(PlatformSettingDefinition::KEY_ALERT_EXPIRY_DAYS, 7);
    }

    /**
     * Stale threshold in days since last READY backup to trigger warning alerts.
     */
    public function backupStaleDays(): int
    {
        return $this->getInt(PlatformSettingDefinition::KEY_ALERT_BACKUP_STALE_DAYS, 7);
    }

    /**
     * Check if a capability is runtime enabled (kill-switch check).
     * Returns false if capability is unknown or not whitelisted.
     */
    public function isFeatureRuntimeEnabled(string $capability): bool
    {
        $key = 'feature.'.$capability;
        $def = PlatformSettingDefinition::findByKey($key);

        if ($def === null) {
            return false;
        }

        return $this->getBool($key, true);
    }

    /**
     * Determine effective capability availability:
     * Backend readiness (config) is a HARD CEILING.
     * Runtime setting acts as a KILL-SWITCH ONLY.
     */
    public function isCapabilityEffective(string $capability): bool
    {
        $backendReady = (bool) config("premium.capability_availability.{$capability}", false);

        if (! $backendReady) {
            return false;
        }

        return $this->isFeatureRuntimeEnabled($capability);
    }

    /**
     * Get all manageable settings definitions with current persisted and effective values.
     *
     * @return array<string, array{
     *     definition: PlatformSettingDefinition,
     *     key: string,
     *     slug: string,
     *     group: string,
     *     group_label: string,
     *     label: string,
     *     description: string,
     *     type: string,
     *     default: mixed,
     *     min: ?int,
     *     max: ?int,
     *     capability: ?string,
     *     is_persisted: bool,
     *     db_value: mixed,
     *     effective_value: mixed,
     *     backend_ready?: bool,
     *     runtime_enabled?: bool,
     *     effective_status?: bool,
     *     updated_at?: ?string,
     *     updated_by?: ?string
     * }>
     */
    public function allManageable(): array
    {
        $definitions = PlatformSettingDefinition::all();
        $records = PlatformSetting::with('updatedBy:id,name')->get()->keyBy('key');

        $result = [];

        foreach ($definitions as $key => $def) {
            $record = $records->get($key);
            $isPersisted = $record !== null && $record->value !== null;
            $dbValue = $isPersisted ? $this->castValue($def, $record->value) : null;
            $effectiveValue = $isPersisted ? $dbValue : $def->default;

            $item = [
                'definition' => $def,
                'key' => $def->key,
                'slug' => $def->slug,
                'group' => $def->group,
                'group_label' => PlatformSettingDefinition::groupLabel($def->group),
                'label' => $def->label,
                'description' => $def->description,
                'type' => $def->type,
                'default' => $def->default,
                'min' => $def->min,
                'max' => $def->max,
                'capability' => $def->capability,
                'is_persisted' => $isPersisted,
                'db_value' => $dbValue,
                'effective_value' => $effectiveValue,
                'updated_at' => $record?->updated_at?->format('d M Y, H:i'),
                'updated_by' => $record?->updatedBy?->name,
            ];

            if ($def->capability !== null) {
                $backendReady = (bool) config("premium.capability_availability.{$def->capability}", false);
                $runtimeEnabled = (bool) $effectiveValue;

                $item['backend_ready'] = $backendReady;
                $item['runtime_enabled'] = $runtimeEnabled;
                $item['effective_status'] = $backendReady && $runtimeEnabled;
            }

            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * Atomically update a manageable platform setting with ADMIN-13 audit logging.
     *
     * @throws InvalidArgumentException
     */
    public function update(string $key, mixed $value, User $actor): PlatformSetting
    {
        if (PlatformSettingDefinition::isSecretOrForbidden($key)) {
            throw new InvalidArgumentException("Setting '{$key}' adalah rahasia/terlarang dan tidak dapat dikelola.");
        }

        $def = PlatformSettingDefinition::findByKey($key);

        if ($def === null) {
            throw new InvalidArgumentException("Setting '{$key}' tidak terdaftar dalam whitelist platform settings.");
        }

        $coercedValue = $this->validateAndCoerce($def, $value);

        // Fetch current record to determine old effective value and check for no-op
        $currentRecord = PlatformSetting::query()->where('key', $key)->first();
        $oldEffectiveValue = $currentRecord !== null && $currentRecord->value !== null
            ? $this->castValue($def, $currentRecord->value)
            : $def->default;

        // No-op check: if value hasn't changed from current effective state, do nothing (0 audit records)
        if ($currentRecord !== null && $oldEffectiveValue === $coercedValue) {
            return $currentRecord;
        }

        if ($currentRecord === null && $oldEffectiveValue === $coercedValue) {
            // First time setting explicit value that matches default
            // Still no operational difference, but if we need a record, we can return or create without audit.
            // Following spec: No-op same value: 0 new audit records
            return new PlatformSetting([
                'key' => $key,
                'value' => $coercedValue,
                'updated_by' => $actor->id,
            ]);
        }

        return DB::transaction(function () use ($key, $coercedValue, $oldEffectiveValue, $def, $actor): PlatformSetting {
            $setting = PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $coercedValue,
                    'updated_by' => $actor->id,
                ]
            );

            // Audit logging inside the same transaction (atomic rollback if logging fails)
            $this->auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::PLATFORM_SETTING_UPDATED,
                targetType: PlatformAuditAction::TARGET_PLATFORM_SETTING,
                targetId: $key,
                targetLabel: $key,
                businessId: null,
                before: ['value' => $oldEffectiveValue],
                after: ['value' => $coercedValue],
                metadata: [
                    'type' => $def->type,
                    'group' => $def->group,
                ],
            );

            return $setting;
        });
    }

    /**
     * Validate and coerce raw input value against definition rules.
     */
    protected function validateAndCoerce(PlatformSettingDefinition $def, mixed $value): int|bool
    {
        if ($def->type === PlatformSettingDefinition::TYPE_INTEGER) {
            if (! is_numeric($value)) {
                throw new InvalidArgumentException("Nilai untuk '{$def->label}' harus berupa angka bulat.");
            }

            $intVal = (int) $value;

            if ($def->min !== null && $intVal < $def->min) {
                throw new InvalidArgumentException("Nilai untuk '{$def->label}' tidak boleh kurang dari {$def->min}.");
            }

            if ($def->max !== null && $intVal > $def->max) {
                throw new InvalidArgumentException("Nilai untuk '{$def->label}' tidak boleh lebih dari {$def->max}.");
            }

            return $intVal;
        }

        if ($def->type === PlatformSettingDefinition::TYPE_BOOLEAN) {
            if (is_bool($value)) {
                return $value;
            }

            if ($value === 1 || $value === '1' || $value === 'true') {
                return true;
            }

            if ($value === 0 || $value === '0' || $value === 'false') {
                return false;
            }

            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($filtered !== null) {
                return $filtered;
            }

            throw new InvalidArgumentException("Nilai untuk '{$def->label}' harus berupa boolean (aktif/nonaktif).");
        }

        throw new InvalidArgumentException("Tipe data setting '{$def->type}' tidak didukung.");
    }

    /**
     * Safely cast database value to the expected type defined in the definition.
     */
    protected function castValue(PlatformSettingDefinition $def, mixed $value): mixed
    {
        if ($def->type === PlatformSettingDefinition::TYPE_INTEGER) {
            return is_numeric($value) ? (int) $value : $def->default;
        }

        if ($def->type === PlatformSettingDefinition::TYPE_BOOLEAN) {
            if (is_bool($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return (int) $value === 1;
            }
            if (is_string($value)) {
                $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return $filtered ?? $def->default;
            }

            return (bool) $value;
        }

        return $value;
    }
}
