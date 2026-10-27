<?php

namespace App\Services\Auth;

use App\Models\PendingRegistration;
use App\Models\User;

final class PasswordRecoveryPolicy
{
    public function isEligible(User $user): bool
    {
        if ($user->password_login_enabled !== true || ! $user->hasVerifiedEmail()) {
            return false;
        }

        $pending = PendingRegistration::query()->where('user_id', $user->getKey())->first();

        return $pending === null || $pending->isProvisioned();
    }
}
