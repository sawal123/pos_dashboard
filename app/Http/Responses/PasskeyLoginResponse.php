<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
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

            if ($request->wantsJson()) {
                return new JsonResponse([
                    'redirect' => $target,
                ], 200);
            }

            return redirect()->to($target);
        }

        $default = config('passkeys.redirect', '/dashboard');

        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => redirect()->intended($default)->getTargetUrl(),
            ], 200);
        }

        return redirect()->intended($default);
    }
}
