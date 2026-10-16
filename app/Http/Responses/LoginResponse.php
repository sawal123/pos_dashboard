<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
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
                ? new JsonResponse(['two_factor' => false, 'redirect' => $target], 200)
                : redirect()->to($target);
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended(Fortify::redirects('login'));
    }
}
