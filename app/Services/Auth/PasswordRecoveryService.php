<?php

namespace App\Services\Auth;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

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

        $status = Password::broker(config('auth.defaults.passwords'))->sendResetLink([
            'email' => $user->getEmailForPasswordReset(),
        ]);

        if ($status === Password::RESET_THROTTLED) {
            throw new PasswordRecoveryException(
                'PASSWORD_RESET_RATE_LIMITED',
                'Password reset is temporarily rate limited.',
                429,
            );
        }
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
}
