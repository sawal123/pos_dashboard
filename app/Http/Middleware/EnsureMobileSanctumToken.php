<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMobileSanctumToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->user()?->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'A mobile bearer token is required.',
                'code' => 'MOBILE_TOKEN_REQUIRED',
            ], 401);
        }

        if (! $accessToken->can('mobile')) {
            return response()->json([
                'message' => 'The token does not have the mobile ability.',
                'code' => 'MOBILE_ABILITY_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
