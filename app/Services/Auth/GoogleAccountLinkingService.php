<?php

namespace App\Services\Auth;

use App\Models\AuthIdentity;
use App\Models\PendingRegistration;
use App\Models\ReauthenticationGrant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

final class GoogleAccountLinkingService
{
    private const REAUTH_RATE_LIMIT_MAX = 5;

    private const REAUTH_RATE_LIMIT_DECAY = 60;

    public function __construct(
        private readonly GoogleIdTokenVerifier $verifier,
        private readonly TwoFactorAuthenticationProvider $twoFactor,
    ) {}

    /** @return array{reauthentication_token: string, expires_in: int, purpose: string} */
    public function reauthenticate(
        User $user,
        PersonalAccessToken $accessToken,
        string $password,
        ?string $totpCode,
        ?string $ip,
    ): array {
        $this->assertReauthenticationAllowed($user);
        $this->rateLimitReauthentication($user, $ip);

        if (! Hash::check($password, $user->password)) {
            throw new GoogleAccountLinkingException('INVALID_CREDENTIALS', 'Credentials are invalid.');
        }

        if ($user->two_factor_confirmed_at !== null && ! $this->validTotp($user, $totpCode)) {
            throw new GoogleAccountLinkingException('TWO_FACTOR_REQUIRED', 'Two-factor authentication is required.');
        }

        try {
            $rawToken = Str::random(64);
            ReauthenticationGrant::create([
                'user_id' => $user->getKey(),
                'personal_access_token_id' => $accessToken->getKey(),
                'purpose' => ReauthenticationGrant::PURPOSE_GOOGLE_LINK,
                'token_digest' => $this->digest($rawToken),
                'expires_at' => now()->addSeconds(ReauthenticationGrant::TTL_SECONDS),
            ]);
        } catch (Throwable $exception) {
            throw new GoogleAccountLinkingException(
                'REAUTHENTICATION_REQUIRED',
                'Reauthentication could not be completed safely.',
                $exception,
            );
        }

        return [
            'reauthentication_token' => $rawToken,
            'expires_in' => ReauthenticationGrant::TTL_SECONDS,
            'purpose' => ReauthenticationGrant::PURPOSE_GOOGLE_LINK,
        ];
    }

    /** @return array{provider: string, linked: bool} */
    public function link(
        User $user,
        PersonalAccessToken $accessToken,
        string $rawGrant,
        string $idToken,
        string $clientPlatform,
    ): array {
        // The network-backed verifier always runs before the database transaction.
        $claims = $this->verifier->verify($idToken, $clientPlatform);

        try {
            return DB::transaction(function () use ($user, $accessToken, $rawGrant, $claims): array {
                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
                if (! $lockedUser instanceof User) {
                    throw new GoogleAccountLinkingException('REAUTHENTICATION_REQUIRED', 'Reauthentication is required.');
                }

                $grant = $this->lockGrant($rawGrant, $lockedUser, $accessToken);
                $this->assertReauthenticationAllowed($lockedUser);

                $identity = AuthIdentity::query()
                    ->where('provider', AuthIdentity::PROVIDER_GOOGLE)
                    ->where('provider_subject', $claims->subject)
                    ->lockForUpdate()
                    ->first();

                if ($identity !== null) {
                    if ((int) $identity->user_id !== (int) $lockedUser->getKey()) {
                        throw new GoogleAccountLinkingException('IDENTITY_ALREADY_LINKED', 'Google identity is already linked.');
                    }

                    $this->consume($grant);

                    return ['provider' => AuthIdentity::PROVIDER_GOOGLE, 'linked' => false];
                }

                $existingIdentity = AuthIdentity::query()
                    ->where('user_id', $lockedUser->getKey())
                    ->where('provider', AuthIdentity::PROVIDER_GOOGLE)
                    ->lockForUpdate()
                    ->first();

                if ($existingIdentity !== null) {
                    throw new GoogleAccountLinkingException('IDENTITY_CONFLICT', 'Another Google identity is already linked.');
                }

                AuthIdentity::create([
                    'user_id' => $lockedUser->getKey(),
                    'provider' => AuthIdentity::PROVIDER_GOOGLE,
                    'provider_subject' => $claims->subject,
                ]);
                $this->consume($grant);

                return ['provider' => AuthIdentity::PROVIDER_GOOGLE, 'linked' => true];
            }, 3);
        } catch (GoogleAccountLinkingException $exception) {
            throw $exception;
        } catch (UniqueConstraintViolationException $exception) {
            $identity = AuthIdentity::query()
                ->where('provider', AuthIdentity::PROVIDER_GOOGLE)
                ->where('provider_subject', $claims->subject)
                ->first();

            if ($identity !== null && (int) $identity->user_id !== (int) $user->getKey()) {
                throw new GoogleAccountLinkingException('IDENTITY_ALREADY_LINKED', 'Google identity is already linked.', $exception);
            }

            if ($identity !== null) {
                return DB::transaction(function () use ($user, $accessToken, $rawGrant): array {
                    $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                    $grant = $this->lockGrant($rawGrant, $lockedUser, $accessToken);
                    $this->assertReauthenticationAllowed($lockedUser);
                    $this->consume($grant);

                    return ['provider' => AuthIdentity::PROVIDER_GOOGLE, 'linked' => false];
                }, 3);
            }

            throw new GoogleAccountLinkingException('IDENTITY_CONFLICT', 'Google identity could not be linked safely.', $exception);
        }
    }

    private function assertReauthenticationAllowed(User $user): void
    {
        if ($user->email_verified_at === null) {
            throw new GoogleAccountLinkingException('REAUTHENTICATION_REQUIRED', 'Reauthentication is required.');
        }

        $pending = PendingRegistration::query()->where('user_id', $user->getKey())->first();
        if ($pending !== null && ! $pending->isProvisioned()) {
            throw new GoogleAccountLinkingException('REAUTHENTICATION_REQUIRED', 'Reauthentication is required.');
        }
    }

    private function validTotp(User $user, ?string $totpCode): bool
    {
        if ($totpCode === null || $user->two_factor_secret === null) {
            return false;
        }

        try {
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

            return $this->twoFactor->verify($secret, $totpCode);
        } catch (Throwable) {
            return false;
        }
    }

    private function rateLimitReauthentication(User $user, ?string $ip): void
    {
        $key = 'auth-google-link:reauth:'.$this->keyedHash((string) $user->getKey().'|'.($ip ?? ''));
        if (RateLimiter::tooManyAttempts($key, self::REAUTH_RATE_LIMIT_MAX)) {
            throw new GoogleAccountLinkingException('REAUTHENTICATION_RATE_LIMITED', 'Reauthentication is temporarily limited.');
        }

        RateLimiter::hit($key, self::REAUTH_RATE_LIMIT_DECAY);
    }

    private function lockGrant(string $rawToken, User $user, PersonalAccessToken $accessToken): ReauthenticationGrant
    {
        $grant = ReauthenticationGrant::query()
            ->where('token_digest', $this->digest($rawToken))
            ->lockForUpdate()
            ->first();

        if ($grant === null || $grant->purpose !== ReauthenticationGrant::PURPOSE_GOOGLE_LINK
            || (int) $grant->user_id !== (int) $user->getKey()
            || (int) $grant->personal_access_token_id !== (int) $accessToken->getKey()) {
            throw new GoogleAccountLinkingException('REAUTHENTICATION_REQUIRED', 'Reauthentication is required.');
        }

        if ($grant->consumed_at !== null) {
            throw new GoogleAccountLinkingException('REAUTHENTICATION_ALREADY_USED', 'Reauthentication has already been used.');
        }

        if ($grant->expires_at->isPast()) {
            throw new GoogleAccountLinkingException('REAUTHENTICATION_EXPIRED', 'Reauthentication has expired.');
        }

        return $grant;
    }

    private function consume(ReauthenticationGrant $grant): void
    {
        $grant->forceFill(['consumed_at' => now()])->save();
    }

    private function digest(string $rawToken): string
    {
        return $this->keyedHash('google-link|'.$rawToken);
    }

    private function keyedHash(string $value): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new GoogleAccountLinkingException('REAUTHENTICATION_REQUIRED', 'Reauthentication is required.');
        }

        return hash_hmac('sha256', $value, $key);
    }
}
