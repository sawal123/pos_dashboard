<?php

namespace App\Services\Auth;

use App\Models\AuthIdentity;
use App\Models\AuthOtpChallenge;
use App\Models\EmailChangeRequest as EmailChangeRecord;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

final class EmailChangeService
{
    public const PURPOSE = 'email_change';

    public function __construct(
        private readonly EmailOtpPolicy $emailPolicy,
        private readonly EmailOtpService $otp,
        private readonly TwoFactorAuthenticationProvider $twoFactor,
        private readonly AccountSessionRevocationService $sessions,
    ) {}

    /** @return array{change_id: string, state: string, otp_expires_at: string, resend_available_at: string} */
    public function request(
        User $user,
        PersonalAccessToken $accessToken,
        string $newEmail,
        ?string $currentPassword,
        ?string $totpCode,
        ?string $idToken,
        ?string $clientPlatform,
        ?string $ipAddress,
    ): array {
        $this->assertAccountReady($user);
        $this->assertProof($user, $currentPassword, $totpCode, $idToken, $clientPlatform);
        $newEmail = $this->emailPolicy->normalizeEmail($newEmail);

        if ($this->emailPolicy->normalizeEmail((string) $user->email) === $newEmail) {
            throw new EmailChangeException('EMAIL_ALREADY_USED', 'The email address is already in use.', 409);
        }

        $this->assertEmailAvailable($newEmail, $user);
        $this->rateLimitRequest($user, $ipAddress);

        try {
            return DB::transaction(function () use ($user, $accessToken, $newEmail, $ipAddress): array {
                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

                if (! $lockedUser instanceof User) {
                    throw new EmailChangeException('EMAIL_CHANGE_CONFLICT', 'The account is no longer available.', 409);
                }

                $this->assertAccountReady($lockedUser);
                $this->assertEmailAvailable($newEmail, $lockedUser);

                $pending = EmailChangeRecord::query()
                    ->where('user_id', $lockedUser->getKey())
                    ->where('status', EmailChangeRecord::STATUS_PENDING_VERIFICATION)
                    ->lockForUpdate()
                    ->first();

                if ($pending !== null) {
                    $challenge = AuthOtpChallenge::query()
                        ->where('subject_reference', $pending->getKey())
                        ->where('purpose', self::PURPOSE)
                        ->latest('created_at')
                        ->first();

                    if ($challenge !== null && $challenge->isActive()) {
                        throw new EmailChangeException('EMAIL_CHANGE_PENDING', 'An email change is already pending.', 409);
                    }

                    $pending->forceFill(['status' => EmailChangeRecord::STATUS_EXPIRED])->save();
                }

                $originalEmail = (string) $lockedUser->getRawOriginal('email');

                $change = EmailChangeRecord::query()->create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $lockedUser->getKey(),
                    'personal_access_token_id' => $accessToken->getKey(),
                    'original_email' => $originalEmail,
                    'original_email_verified_at' => $lockedUser->email_verified_at,
                    'new_email' => $newEmail,
                    'status' => EmailChangeRecord::STATUS_PENDING_VERIFICATION,
                ]);

                $challenge = $this->otp->issue(
                    (string) $change->getKey(),
                    $newEmail,
                    self::PURPOSE,
                    $ipAddress,
                );

                return [
                    'change_id' => (string) $change->getKey(),
                    'state' => EmailChangeRecord::STATUS_PENDING_VERIFICATION,
                    'otp_expires_at' => $challenge->expiresAt->toIso8601String(),
                    'resend_available_at' => $challenge->resendAvailableAt->toIso8601String(),
                ];
            }, 3);
        } catch (EmailChangeException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new EmailChangeException('EMAIL_CHANGE_CONFLICT', 'The email change could not be created safely.', 409, $exception);
        } catch (OtpException $exception) {
            throw $this->mapOtpException($exception);
        }
    }

    public function verify(
        User $authenticatedUser,
        PersonalAccessToken $accessToken,
        string $changeId,
        string $code,
        ?string $ipAddress,
    ): void {
        $change = EmailChangeRecord::query()->whereKey($changeId)->first();

        if (! $change instanceof EmailChangeRecord
            || (int) $change->user_id !== (int) $authenticatedUser->getKey()
            || (int) $change->personal_access_token_id !== (int) $accessToken->getKey()) {
            throw new EmailChangeException('EMAIL_CHANGE_CONFLICT', 'The email change request is not valid for this session.', 409);
        }

        $challenge = AuthOtpChallenge::query()
            ->where('subject_reference', $change->getKey())
            ->where('purpose', self::PURPOSE)
            ->latest('created_at')
            ->first();

        if ($challenge === null) {
            throw new EmailChangeException('EMAIL_CHANGE_EXPIRED', 'The email change verification has expired.', 410);
        }

        try {
            $this->otp->verifyAndConsume(
                (string) $challenge->getKey(),
                (string) $change->getKey(),
                self::PURPOSE,
                $code,
                $ipAddress,
                function () use ($authenticatedUser, $accessToken, $changeId): void {
                    $change = EmailChangeRecord::query()->whereKey($changeId)->lockForUpdate()->first();
                    $user = User::query()->whereKey($authenticatedUser->getKey())->lockForUpdate()->first();

                    if (! $change instanceof EmailChangeRecord
                        || ! $user instanceof User
                        || (int) $change->user_id !== (int) $user->getKey()
                        || (int) $change->personal_access_token_id !== (int) $accessToken->getKey()
                        || ! $change->isPending()) {
                        throw new EmailChangeException('EMAIL_CHANGE_CONFLICT', 'The email change request is no longer valid.', 409);
                    }

                    if ($this->emailPolicy->normalizeEmail((string) $user->email)
                        !== $this->emailPolicy->normalizeEmail((string) $change->original_email)
                        || ! $change->originalVerificationMatches($user->email_verified_at)) {
                        throw new EmailChangeException('EMAIL_CHANGE_CONFLICT', 'The account changed while email verification was pending.', 409);
                    }

                    $this->assertAccountReady($user);
                    $this->assertEmailAvailable($change->new_email, $user);

                    $passwordResetTable = (string) config('auth.passwords.'.config('auth.defaults.passwords').'.table', 'password_reset_tokens');
                    $originalEmail = (string) $user->getRawOriginal('email');
                    $normalizedOriginalEmail = $this->emailPolicy->normalizeEmail($originalEmail);
                    $normalizedNewEmail = $this->emailPolicy->normalizeEmail($change->new_email);
                    $resetEmails = array_values(array_unique([
                        $originalEmail,
                        $normalizedOriginalEmail,
                        (string) $change->original_email,
                        $this->emailPolicy->normalizeEmail((string) $change->original_email),
                        (string) $change->new_email,
                        $normalizedNewEmail,
                    ]));

                    DB::table($passwordResetTable)
                        ->where(function ($query) use ($resetEmails, $normalizedOriginalEmail, $normalizedNewEmail): void {
                            $query->whereIn('email', $resetEmails)
                                ->orWhereRaw('LOWER(email) = ?', [$normalizedOriginalEmail])
                                ->orWhereRaw('LOWER(email) = ?', [$normalizedNewEmail]);
                        })
                        ->delete();

                    $user->forceFill(['email' => $change->new_email])->save();
                    $change->forceFill([
                        'status' => EmailChangeRecord::STATUS_VERIFIED,
                        'verified_at' => now(),
                    ])->save();

                    // Save the change first; deleting the current token cascades
                    // its session-bound request row by design.
                    $this->sessions->revoke($user);
                },
            );
        } catch (OtpException $exception) {
            throw $this->mapOtpException($exception);
        } catch (QueryException $exception) {
            throw new EmailChangeException('EMAIL_CHANGE_CONFLICT', 'The email change could not be completed safely.', 409, $exception);
        }
    }

    private function assertAccountReady(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            throw new EmailChangeException('REAUTHENTICATION_REQUIRED', 'The account must be verified.', 403);
        }

        $pending = PendingRegistration::query()->where('user_id', $user->getKey())->first();
        if ($pending !== null && ! $pending->isProvisioned()) {
            throw new EmailChangeException('REAUTHENTICATION_REQUIRED', 'The account is still being provisioned.', 403);
        }
    }

    private function assertProof(
        User $user,
        ?string $currentPassword,
        ?string $totpCode,
        ?string $idToken,
        ?string $clientPlatform,
    ): void {
        if ($user->password_login_enabled === false) {
            if ($idToken === null || $clientPlatform === null) {
                throw new EmailChangeException('REAUTHENTICATION_REQUIRED', 'Fresh Google proof is required.', 403);
            }

            try {
                $claims = app(GoogleIdTokenVerifier::class)->verify($idToken, $clientPlatform);
            } catch (GoogleAuthenticationException $exception) {
                throw new EmailChangeException('REAUTHENTICATION_REQUIRED', 'Fresh Google proof is required.', 403, $exception);
            }

            $matches = AuthIdentity::query()
                ->where('user_id', $user->getKey())
                ->where('provider', AuthIdentity::PROVIDER_GOOGLE)
                ->where('provider_subject', $claims->subject)
                ->exists();
            if (! $matches) {
                throw new EmailChangeException('REAUTHENTICATION_REQUIRED', 'Fresh Google proof is required.', 403);
            }
        } elseif ($currentPassword === null || ! Hash::check($currentPassword, (string) $user->password)) {
            throw new EmailChangeException('INVALID_CREDENTIALS', 'Credentials are invalid.', 401);
        }

        if ($user->two_factor_confirmed_at !== null && ! $this->validTotp($user, $totpCode)) {
            throw new EmailChangeException('TWO_FACTOR_REQUIRED', 'Two-factor authentication is required.', 403);
        }
    }

    private function validTotp(User $user, ?string $code): bool
    {
        if ($code === null || $user->two_factor_secret === null) {
            return false;
        }

        try {
            return (bool) $this->twoFactor->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code,
            );
        } catch (Throwable) {
            return false;
        }
    }

    private function assertEmailAvailable(string $email, User $user): void
    {
        if (User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where($user->getKeyName(), '!=', $user->getKey())
            ->exists()) {
            throw new EmailChangeException('EMAIL_ALREADY_USED', 'The email address is already in use.', 409);
        }
    }

    private function rateLimitRequest(User $user, ?string $ipAddress): void
    {
        $key = 'email-change:request:'.hash_hmac('sha256', $user->getKey().'|'.($ipAddress ?? ''), (string) config('app.key'));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new EmailChangeException('OTP_RATE_LIMITED', 'Email change is temporarily rate limited.', 429);
        }

        RateLimiter::hit($key, 600);
    }

    private function mapOtpException(OtpException $exception): EmailChangeException
    {
        return match ($exception->errorCode) {
            'OTP_EXPIRED' => new EmailChangeException('EMAIL_CHANGE_EXPIRED', 'The email change verification has expired.', 410, $exception),
            'OTP_RATE_LIMITED' => new EmailChangeException('OTP_RATE_LIMITED', 'Email verification is temporarily rate limited.', 429, $exception),
            default => new EmailChangeException('EMAIL_CHANGE_INVALID_OTP', 'The email change code is invalid.', 422, $exception),
        };
    }
}
