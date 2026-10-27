<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\Auth\AccountSessionRevocationService;
use App\Services\Auth\PasswordRecoveryPolicy;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private readonly AccountSessionRevocationService $sessions,
        private readonly PasswordRecoveryPolicy $policy,
    ) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        if (! $this->policy->isEligible($user)) {
            Password::broker(config('fortify.passwords'))->deleteToken($user);

            throw ValidationException::withMessages([
                'email' => ['Password recovery is not available for this account.'],
            ]);
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();

        $user->setRememberToken(Str::random(60));
        $user->save();
        $this->sessions->revoke($user);
    }
}
