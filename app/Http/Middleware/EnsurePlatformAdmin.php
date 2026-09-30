<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    /**
     * Handle an incoming request.
     *
     * Enforce that the authenticated user is an explicit global Platform Admin.
     * Deny-by-default: regular users, owners, and business members are rejected with 403.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isPlatformAdmin()) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Platform Admin.');
        }

        return $next($request);
    }
}
