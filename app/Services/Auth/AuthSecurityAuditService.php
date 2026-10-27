<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Log;
use Throwable;

final class AuthSecurityAuditService
{
    /**
     * Record only an allow-listed, credential-free authentication event.
     * Logging is best effort and never changes authentication behavior.
     *
     * @param  array<string, scalar|null>  $context
     */
    public function record(string $event, array $context = []): void
    {
        $safe = [
            'status',
            'error_category',
            'provider',
            'client_platform',
            'result',
            'retry_after',
            'counter',
        ];
        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
        ];

        foreach ($safe as $key) {
            if (array_key_exists($key, $context) && is_scalar($context[$key])) {
                $payload[$key] = $context[$key];
            }
        }

        if (isset($context['account_identifier'])) {
            $identifier = $this->identifier((string) $context['account_identifier']);
            if ($identifier !== null) {
                $payload['account_hmac'] = $identifier;
            }
        }

        try {
            Log::channel((string) config('auth_security.audit_channel', 'stack'))
                ->info('auth.security', $payload);
        } catch (Throwable) {
            // Audit logging is intentionally non-blocking.
        }
    }

    private function identifier(string $value): ?string
    {
        $key = (string) config('app.key', '');
        if ($key === '' || $value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, $key);
    }
}
