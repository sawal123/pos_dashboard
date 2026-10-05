<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdminTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * Ensure that the authenticated Platform Admin has confirmed two-factor authentication (2FA).
     * If 2FA is not confirmed, redirect the user to security settings with guidance.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isPlatformAdmin()) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Platform Admin.');
        }

        if (! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
            return redirect()->route('security.edit')
                ->with('status', 'Platform Admin wajib mengaktifkan autentikasi dua faktor sebelum mengakses konsol platform.');
        }

        return $next($request);
    }
}
