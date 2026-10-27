<?php

namespace App\Services\Auth;

use App\Models\MobileAuthChallenge;
use App\Models\ReauthenticationGrant;
use App\Models\User;

final class AccountSessionRevocationService
{
    public function revoke(User $user): void
    {
        $user->tokens()->delete();

        MobileAuthChallenge::query()
            ->where('user_id', $user->getKey())
            ->whereNull('consumed_at')
            ->whereNull('locked_at')
            ->update(['locked_at' => now()]);

        ReauthenticationGrant::query()
            ->where('user_id', $user->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }
}
