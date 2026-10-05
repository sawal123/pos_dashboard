<?php

namespace App\Services\Platform;

use App\Models\PlatformAuditLog;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Str;

class PlatformAuditLogger
{
    /**
     * Secret and sensitive substrings/keys that must never be persisted in audit states or metadata.
     *
     * @var list<string>
     */
    protected const FORBIDDEN_KEY_PATTERNS = [
        'password',
        'remember_token',
        'token',
        'snap_token',
        'provider_payload',
        'redirect_url',
        'server_key',
        'client_key',
        'signature',
        'secret',
        'storage_path',
        'backup_payload',
        'cloud_backup_contents',
        'payload',
        'authorization',
        'cookie',
        'csrf',
        'app_key',
        'api_key',
        'private_key',
        'credential',
    ];

    /**
     * Record a permanent, append-only Platform Admin audit entry.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        User $actor,
        string $action,
        string $targetType,
        int|string|null $targetId,
        ?string $targetLabel,
        ?int $businessId,
        ?array $before,
        ?array $after,
        array $metadata = [],
    ): PlatformAuditLog {
        return PlatformAuditLog::create([
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => Str::lower(trim($actor->email)),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId !== null ? (string) $targetId : null,
            'target_label' => $targetLabel !== null ? Str::limit(trim($targetLabel), 255) : null,
            'business_id' => $businessId,
            'before_state' => $this->sanitizeState($before),
            'after_state' => $this->sanitizeState($after),
            'metadata' => $this->sanitizeState($metadata),
            'created_at' => now(),
        ]);
    }

    /**
     * Sanitize state arrays: strip any secret keys and normalize datetimes deterministically.
     *
     * @param  array<string, mixed>|null  $state
     * @return array<string, mixed>|null
     */
    protected function sanitizeState(?array $state): ?array
    {
        if ($state === null) {
            return null;
        }

        $sanitized = [];

        foreach ($state as $key => $value) {
            if ($this->isForbiddenKey((string) $key)) {
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $sanitized[$key] = $value->format(DateTimeInterface::ATOM);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeState($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if a key name matches any forbidden sensitive patterns.
     */
    protected function isForbiddenKey(string $key): bool
    {
        $normalized = Str::lower(str_replace(['-', ' '], '_', $key));

        foreach (self::FORBIDDEN_KEY_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
