<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user && $user->isPlatformAdmin()) {
            $intended = $request->session()->pull('url.intended');
            $target = (! $intended || $intended === url('/dashboard') || $intended === url('/'))
                ? route('platform.dashboard')
                : $intended;

            return $request->wantsJson()
                ? response()->noContent()
                : redirect()->to($target);
        }

        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->intended(Fortify::redirects('login'));
    }
}
