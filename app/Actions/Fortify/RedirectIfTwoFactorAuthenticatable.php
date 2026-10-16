<?php

namespace App\Actions\Fortify;

use App\Services\Auth\TwoFactorDeviceService;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as BaseAction;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;

class RedirectIfTwoFactorAuthenticatable extends BaseAction
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        $user = $this->validateCredentials($request);

        // If the current device is already trusted for this user, bypass 2FA challenge.
        if ($user && app(TwoFactorDeviceService::class)->isDeviceTrusted($request, $user)) {
            return $next($request);
        }

        if (Fortify::confirmsTwoFactorAuthentication()) {
            if (optional($user)->two_factor_secret &&
                ! is_null(optional($user)->two_factor_confirmed_at) &&
                in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user))) {
                return $this->twoFactorChallengeResponse($request, $user);
            } else {
                return $next($request);
            }
        }

        if (optional($user)->two_factor_secret &&
            in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user))) {
            return $this->twoFactorChallengeResponse($request, $user);
        }

        return $next($request);
    }
}
