<?php

namespace App\Services\Auth;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Throwable;

final class PasswordRecoveryService
{
    public function __construct(
        private readonly EmailOtpPolicy $emailPolicy,
        private readonly PasswordRecoveryPolicy $policy,
        private readonly ResetUserPassword $resetUserPassword,
    ) {}

    public function request(string $email): void
    {
        $email = $this->emailPolicy->normalizeEmail($email);
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user instanceof User || ! $this->policy->isEligible($user)) {
            return;
        }

        $this->assertSafeDelivery();

        try {
            $status = Password::broker(config('auth.defaults.passwords'))->sendResetLink([
                'email' => $user->getEmailForPasswordReset(),
            ]);
        } catch (Throwable $exception) {
            $this->revokeResetToken($user);
            app(AuthSecurityAuditService::class)->record('auth.password_reset.requested', [
                'status' => 'delivery_failed',
                'error_category' => 'delivery',
                'provider' => 'password_broker',
                'account_identifier' => (string) $user->getAuthIdentifier(),
            ]);

            throw new PasswordRecoveryException(
                'PASSWORD_RESET_DELIVERY_FAILED',
                'The password reset email could not be delivered safely.',
                503,
                $exception,
            );
        }

        if ($status === Password::RESET_THROTTLED) {
            throw new PasswordRecoveryException(
                'PASSWORD_RESET_RATE_LIMITED',
                'Password reset is temporarily rate limited.',
                429,
            );
        }

        app(AuthSecurityAuditService::class)->record('auth.password_reset.requested', [
            'status' => 'accepted',
            'provider' => 'password_broker',
            'account_identifier' => (string) $user->getAuthIdentifier(),
        ]);
    }

    /** @param array{email: string, token: string, password: string, password_confirmation?: string} $input */
    public function reset(array $input): void
    {
        $normalizedEmail = $this->emailPolicy->normalizeEmail($input['email']);
        $user = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        if (! $user instanceof User || ! $this->policy->isEligible($user)) {
            throw new PasswordRecoveryException(
                'PASSWORD_RESET_INVALID',
                'The password reset request is invalid.',
            );
        }

        $status = Password::broker(config('auth.defaults.passwords'))->reset([
            'email' => $user->getEmailForPasswordReset(),
            'token' => $input['token'],
            'password' => $input['password'],
            'password_confirmation' => $input['password_confirmation'] ?? null,
        ], function (User $resetUser) use ($input): void {
            $this->resetUserPassword->reset($resetUser, $input);
        });

        if ($status === Password::PASSWORD_RESET) {
            app(AuthSecurityAuditService::class)->record('auth.password_reset.completed', [
                'status' => 'completed',
                'provider' => 'password_broker',
                'account_identifier' => (string) $user->getAuthIdentifier(),
            ]);

            return;
        }

        throw new PasswordRecoveryException(
            $this->invalidTokenCode($user->getEmailForPasswordReset()),
            'The password reset request is invalid.',
            $this->invalidTokenCode($user->getEmailForPasswordReset()) === 'PASSWORD_RESET_EXPIRED' ? 410 : 422,
        );
    }

    private function invalidTokenCode(string $email): string
    {
        $record = DB::table((string) config('auth.passwords.'.config('auth.defaults.passwords').'.table', 'password_reset_tokens'))
            ->where('email', $email)
            ->first(['created_at']);

        if ($record !== null && $record->created_at !== null) {
            $expiresAt = Carbon::parse($record->created_at)
                ->addMinutes((int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60));

            if ($expiresAt->isPast()) {
                return 'PASSWORD_RESET_EXPIRED';
            }
        }

        return 'PASSWORD_RESET_INVALID';
    }

    public function assertSafeDelivery(): void
    {
        $mailer = (string) config('mail.default', 'log');
        $allowedMailers = (array) config('auth_otp.allowed_mailers', []);

        if (app()->environment('testing') && Notification::getFacadeRoot() instanceof NotificationFake) {
            return;
        }

        if (! in_array($mailer, $allowedMailers, true)) {
            throw new PasswordRecoveryException(
                'PASSWORD_RESET_DELIVERY_UNSAFE',
                'The configured password reset mailer is not safe for delivery.',
                503,
            );
        }

        $mailerConfig = config('mail.mailers.'.$mailer);
        if (! is_array($mailerConfig) || ($mailerConfig['transport'] ?? null) === null) {
            throw new PasswordRecoveryException(
                'PASSWORD_RESET_DELIVERY_UNSAFE',
                'The configured password reset mailer is not safe for delivery.',
                503,
            );
        }

        $fromAddress = (string) config('mail.from.address', '');
        if ($fromAddress === '' || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new PasswordRecoveryException(
                'PASSWORD_RESET_DELIVERY_UNSAFE',
                'The configured password reset sender is not safe for delivery.',
                503,
            );
        }
    }

    public function revokeResetToken(User $user): void
    {
        DB::table((string) config('auth.passwords.'.config('auth.defaults.passwords').'.table', 'password_reset_tokens'))
            ->where('email', $user->getEmailForPasswordReset())
            ->delete();
    }
}
