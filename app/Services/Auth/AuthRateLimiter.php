<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class AuthRateLimiter
{
    public function enforce(string $scope, string $identifier, int $maxAttempts, int $decaySeconds): void
    {
        $applicationKey = (string) config('app.key', '');
        if ($applicationKey === '') {
            throw new AuthRateLimitException(
                'AUTH_RATE_LIMIT_UNAVAILABLE',
                'Authentication rate limiting is not configured safely.',
                503,
            );
        }

        $key = 'auth-security:'.$scope.':'.hash_hmac('sha256', $identifier, $applicationKey);

        try {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                app(AuthSecurityAuditService::class)->record('auth.rate_limit.triggered', [
                    'status' => 'rejected',
                    'error_category' => $scope,
                    'retry_after' => RateLimiter::availableIn($key),
                ]);

                throw new AuthRateLimitException(
                    'AUTH_RATE_LIMITED',
                    'Authentication is temporarily rate limited.',
                    429,
                    RateLimiter::availableIn($key),
                );
            }

            RateLimiter::hit($key, $decaySeconds);
        } catch (AuthRateLimitException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AuthRateLimitException(
                'AUTH_RATE_LIMIT_UNAVAILABLE',
                'Authentication rate limiting is not available safely.',
                503,
                0,
                $exception,
            );
        }
    }
}
