<?php

namespace App\Services\Auth;

use App\Models\MobileAuthChallenge;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Throwable;

final class MobileAuthenticationService
{
    private const RATE_LIMIT_DECAY_SECONDS = 60;

    public function __construct(
        private readonly TwoFactorAuthenticationProvider $twoFactor,
    ) {}

    /**
     * Complete the primary login and either issue a mobile token or create a
     * short-lived challenge without issuing a bearer token.
     *
     * @return array{user: User, token?: string, expires_at: string, challenge_id?: string, methods?: list<string>}
     */
    public function authenticate(User $user, string $loginMethod, ?string $ipAddress = null): array
    {
        if (! in_array($loginMethod, MobileAuthChallenge::METHODS, true)) {
            throw new MobileAuthenticationException('AUTHENTICATION_METHOD_INVALID', 'Authentication method is invalid.');
        }

        $user = User::query()->find($user->getKey());
        if (! $user instanceof User) {
            throw new MobileAuthenticationException('USER_NOT_FOUND', 'The user cannot authenticate.');
        }

        $this->assertLoginAllowed($user);

        if ($user->two_factor_confirmed_at !== null) {
            return $this->createChallenge($user, $loginMethod, $ipAddress);
        }

        return $this->issueToken($user);
    }

    /**
     * @return array{user: User, token: string, expires_at: string}
     */
    public function verifyChallenge(string $rawChallenge, string $method, string $code, ?string $ipAddress = null): array
    {
        $digest = $this->digest($rawChallenge);
        $this->checkRateLimit('challenge:'.$digest, 10);

        /** @var array{user?: User, error?: string, status?: int}|null $result */
        $result = DB::transaction(function () use ($digest, $method, $code, $ipAddress): array {
            $challenge = MobileAuthChallenge::query()
                ->where('challenge_digest', $digest)
                ->lockForUpdate()
                ->first();

            if (! $challenge instanceof MobileAuthChallenge) {
                return ['error' => 'TWO_FACTOR_CHALLENGE_INVALID', 'status' => 422];
            }

            if ($challenge->consumed_at !== null || $challenge->locked_at !== null) {
                return ['error' => 'TWO_FACTOR_CHALLENGE_USED', 'status' => 410];
            }

            if ($challenge->isExpired()) {
                $challenge->forceFill(['locked_at' => now()])->save();

                return ['error' => 'TWO_FACTOR_CHALLENGE_EXPIRED', 'status' => 410];
            }

            $user = User::query()->whereKey($challenge->user_id)->lockForUpdate()->first();
            if (! $user instanceof User) {
                $challenge->forceFill(['locked_at' => now()])->save();

                return ['error' => 'TWO_FACTOR_CHALLENGE_INVALID', 'status' => 422];
            }

            $this->checkRateLimit('account:'.$user->getKey().':'.($ipAddress ?? 'unknown'), 15);

            try {
                $this->assertLoginAllowed($user);
            } catch (MobileAuthenticationException $exception) {
                $challenge->forceFill(['locked_at' => now()])->save();

                return ['error' => $exception->errorCode, 'status' => $exception->status];
            }

            if ($user->two_factor_confirmed_at === null || $user->two_factor_secret === null) {
                $challenge->forceFill(['locked_at' => now()])->save();

                return ['error' => 'TWO_FACTOR_CHALLENGE_INVALID', 'status' => 422];
            }

            $valid = $method === 'totp'
                ? $this->validTotp($user, $code)
                : $this->validRecoveryCode($user, $code);

            if (! $valid) {
                $failedAttempts = (int) $challenge->failed_attempts + 1;
                $challenge->forceFill([
                    'failed_attempts' => $failedAttempts,
                    'locked_at' => $failedAttempts >= (int) config('auth_mobile.challenge_max_attempts', 5)
                        ? now()
                        : null,
                ])->save();

                return [
                    'error' => $failedAttempts >= (int) config('auth_mobile.challenge_max_attempts', 5)
                        ? 'TWO_FACTOR_CHALLENGE_LOCKED'
                        : 'TWO_FACTOR_INVALID',
                    'status' => $failedAttempts >= (int) config('auth_mobile.challenge_max_attempts', 5) ? 429 : 422,
                ];
            }

            if ($method === 'recovery_code') {
                $this->consumeRecoveryCode($user, $code);
            }

            $challenge->forceFill(['consumed_at' => now()])->save();

            return ['user' => $user];
        });

        if (! isset($result['user'])) {
            throw new MobileAuthenticationException(
                (string) ($result['error'] ?? 'TWO_FACTOR_CHALLENGE_INVALID'),
                'The two-factor challenge could not be completed.',
                (int) ($result['status'] ?? 422),
            );
        }

        return $this->issueToken($result['user']);
    }

    /** @return array{user: User, token?: string, expires_at: string, challenge_id?: string, methods?: list<string>} */
    private function createChallenge(User $user, string $loginMethod, ?string $ipAddress): array
    {
        $rawChallenge = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = now()->addMinutes(min((int) config('auth_mobile.challenge_ttl_minutes', 5), 5));

        MobileAuthChallenge::query()->create([
            'user_id' => $user->getKey(),
            'challenge_digest' => $this->digest($rawChallenge),
            'login_method' => $loginMethod,
            'ip_address' => $ipAddress,
            'expires_at' => $expiresAt,
        ]);

        return [
            'user' => $user,
            'challenge_id' => $rawChallenge,
            'expires_at' => $expiresAt->toIso8601String(),
            'methods' => ['totp', 'recovery_code'],
        ];
    }

    /** @return array{user: User, token: string, expires_at: string} */
    private function issueToken(User $user): array
    {
        $expiresAt = now()->addDays(max(1, (int) config('auth_mobile.token_ttl_days', 30)));
        $accessToken = $user->createToken('mobile-api', ['mobile'], $expiresAt);

        return [
            'user' => $user,
            'token' => $accessToken->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function assertLoginAllowed(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            throw new MobileAuthenticationException(
                'EMAIL_NOT_VERIFIED',
                'Email address is not verified.',
                403,
            );
        }

        $pendingRegistration = PendingRegistration::query()
            ->where('user_id', $user->getKey())
            ->first();

        if ($pendingRegistration !== null && ! $pendingRegistration->isProvisioned()) {
            throw new MobileAuthenticationException(
                'AUTH_PROVISIONING_PENDING',
                'Account provisioning is not complete.',
                403,
            );
        }
    }

    private function validTotp(User $user, string $code): bool
    {
        try {
            return (bool) $this->twoFactor->verify(
                Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret),
                $code,
            );
        } catch (Throwable) {
            return false;
        }
    }

    private function validRecoveryCode(User $user, string $code): bool
    {
        try {
            foreach ($user->recoveryCodes() as $recoveryCode) {
                if (is_string($recoveryCode) && hash_equals($recoveryCode, $code)) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    private function consumeRecoveryCode(User $user, string $code): void
    {
        $remaining = array_values(array_filter(
            $user->recoveryCodes(),
            static fn (mixed $recoveryCode): bool => ! is_string($recoveryCode) || ! hash_equals($recoveryCode, $code),
        ));

        $user->forceFill([
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($remaining, JSON_THROW_ON_ERROR)),
        ])->save();
    }

    private function checkRateLimit(string $keySuffix, int $maxAttempts): void
    {
        $key = 'mobile-auth-2fa:'.$keySuffix;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new MobileAuthenticationException(
                'TWO_FACTOR_RATE_LIMITED',
                'Two-factor verification is temporarily rate limited.',
                429,
            );
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY_SECONDS);
    }

    private function digest(string $rawChallenge): string
    {
        return hash_hmac('sha256', $rawChallenge, (string) config('app.key'));
    }
}
