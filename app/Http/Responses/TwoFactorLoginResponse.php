<?php

namespace App\Http\Responses;

use App\Services\Auth\TwoFactorDeviceService;
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

            $response = $request->wantsJson()
                ? response()->noContent()
                : redirect()->to($target);
        } else {
            $response = $request->wantsJson()
                ? response()->noContent()
                : redirect()->intended(Fortify::redirects('login'));
        }

        if ($user && $request->boolean('remember_device', true)) {
            $deviceCookie = app(TwoFactorDeviceService::class)->createDeviceCookie($user);
            $response->withCookie($deviceCookie);
        }

        return $response;
    }
}
