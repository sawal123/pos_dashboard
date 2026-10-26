<?php

namespace App\Services\Auth;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pure OTP policy and keyed-material helper. It has no HTTP or User behavior.
 */
final class EmailOtpPolicy
{
    public function codeLength(): int
    {
        return (int) config('auth_otp.code_length', 6);
    }

    public function ttlMinutes(): int
    {
        return (int) config('auth_otp.ttl_minutes', 10);
    }

    public function maxAttempts(): int
    {
        return (int) config('auth_otp.max_attempts', 5);
    }

    public function resendCooldownSeconds(): int
    {
        return (int) config('auth_otp.resend_cooldown_seconds', 60);
    }

    /**
     * Generate an ASCII six-digit code using a CSPRNG.
     */
    public function generateCode(): string
    {
        return $this->formatCode(random_int(0, (10 ** $this->codeLength()) - 1));
    }

    /**
     * Format a generated integer while retaining leading zeroes.
     */
    public function formatCode(int $value): string
    {
        $maximum = (10 ** $this->codeLength()) - 1;

        if ($value < 0 || $value > $maximum) {
            throw new InvalidArgumentException('OTP value is outside the configured range.');
        }

        return str_pad((string) $value, $this->codeLength(), '0', STR_PAD_LEFT);
    }

    public function normalizeEmail(string $email): string
    {
        $normalized = Str::lower(trim($email));

        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new OtpException('OTP_INVALID_EMAIL', 'The OTP recipient email is invalid.');
        }

        return $normalized;
    }

    public function validateSubjectReference(string $subjectReference): string
    {
        $subjectReference = trim($subjectReference);

        if (strlen($subjectReference) < 16 || strlen($subjectReference) > 255) {
            throw new OtpException('OTP_INVALID_SUBJECT', 'The OTP subject reference is invalid.');
        }

        return $subjectReference;
    }

    public function validatePurpose(string $purpose): string
    {
        $purpose = trim($purpose);

        if (! preg_match('/\A[a-z0-9][a-z0-9_.-]{0,63}\z/D', $purpose)) {
            throw new OtpException('OTP_INVALID_PURPOSE', 'The OTP purpose is invalid.');
        }

        return $purpose;
    }

    public function validateCode(string $code): string
    {
        if (! preg_match('/\A[0-9]{6}\z/D', $code)) {
            throw new OtpException('OTP_INVALID_FORMAT', 'The OTP code format is invalid.');
        }

        return $code;
    }

    /**
     * A domain-separated key derived from AUTH_OTP_KEY or APP_KEY.
     */
    public function key(): string
    {
        $configuredKey = (string) config('auth_otp.key', '');

        if ($configuredKey !== '') {
            return hash('sha256', 'pos-dashboard-auth-otp|'.$configuredKey, true);
        }

        $applicationKey = (string) config('app.key', '');

        if ($applicationKey === '') {
            throw new OtpException('OTP_KEY_UNAVAILABLE', 'The OTP verifier key is not configured.');
        }

        return hash_hmac('sha256', 'pos-dashboard-auth-otp-v1', $applicationKey, true);
    }

    public function digest(string $subjectReference, string $purpose, string $nonce, string $code): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [$subjectReference, $purpose, $nonce, $code]),
            $this->key(),
        );
    }

    /**
     * Hash identifiers used in cache/rate-limit keys so PII and raw subject
     * references are not written to cache metadata.
     */
    public function identifierKey(string $value): string
    {
        return hash_hmac('sha256', $value, $this->key());
    }
}
