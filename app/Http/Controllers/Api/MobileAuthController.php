<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\Auth\AuthRateLimiter;
use App\Services\Auth\AuthRateLimitException;
use App\Services\Auth\AuthSecurityAuditService;
use App\Services\Auth\MobileAuthenticationException;
use App\Services\Auth\MobileAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class MobileAuthController extends Controller
{
    public function __construct(
        private readonly MobileAuthenticationService $authentication,
        private readonly AuthRateLimiter $rateLimiter,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $normalizedEmail = mb_strtolower(trim((string) $validated['email']));

        try {
            $this->rateLimiter->enforce(
                'login-account',
                $normalizedEmail,
                (int) config('auth_security.login_rate_limit.max_attempts', 10),
                (int) config('auth_security.login_rate_limit.decay_seconds', 60),
            );
            $this->rateLimiter->enforce(
                'login-account-ip',
                $normalizedEmail.'|'.$request->ip(),
                (int) config('auth_security.login_rate_limit.max_attempts', 10),
                (int) config('auth_security.login_rate_limit.decay_seconds', 60),
            );
        } catch (AuthRateLimitException $exception) {
            $response = response()->json([
                'message' => 'Invalid credentials.',
            ], $exception->status);

            if ($exception->retryAfterSeconds > 0) {
                $response->headers->set('Retry-After', (string) $exception->retryAfterSeconds);
            }

            return $response;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        if (! $user instanceof User || ! Hash::check($validated['password'], $user->password)) {
            app(AuthSecurityAuditService::class)->record('auth.login.failed', [
                'status' => 'rejected',
                'error_category' => 'invalid_credentials',
                'provider' => 'password',
                'client_platform' => 'mobile',
                'account_identifier' => $normalizedEmail,
            ]);

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        try {
            $result = $this->authentication->authenticate($user, 'manual', $request->ip());
        } catch (MobileAuthenticationException $exception) {
            app(AuthSecurityAuditService::class)->record('auth.login.failed', [
                'status' => 'rejected',
                'error_category' => $exception->errorCode,
                'provider' => 'password',
                'client_platform' => 'mobile',
                'account_identifier' => (string) $user->getAuthIdentifier(),
            ]);

            return $this->error($exception);
        }

        app(AuthSecurityAuditService::class)->record('auth.login.succeeded', [
            'status' => isset($result['token']) ? 'authenticated' : 'challenge_issued',
            'provider' => 'password',
            'client_platform' => 'mobile',
            'account_identifier' => (string) $user->getAuthIdentifier(),
        ]);

        return $this->authenticationResponse($result);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => ['required', 'string', 'max:128'],
            'method' => ['required', 'string', 'in:totp,recovery_code'],
            'code' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $this->authentication->verifyChallenge(
                (string) $validated['challenge_id'],
                (string) $validated['method'],
                (string) $validated['code'],
                $request->ip(),
            );
        } catch (MobileAuthenticationException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'token' => $result['token'],
                'expires_at' => $result['expires_at'],
                'user' => $this->userPayload($result['user']),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => $this->userPayload($user),
                'email_verified' => $user->hasVerifiedEmail(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'authentication_methods' => $this->authenticationMethods($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /** @param array{user: User, token?: string, expires_at: string, challenge_id?: string, methods?: list<string>} $result */
    private function authenticationResponse(array $result): JsonResponse
    {
        if (! isset($result['token'])) {
            return response()->json([
                'code' => 'TWO_FACTOR_REQUIRED',
                'message' => 'Verifikasi dua langkah diperlukan.',
                'data' => [
                    'challenge_id' => $result['challenge_id'] ?? '',
                    'expires_at' => $result['expires_at'],
                    'methods' => $result['methods'] ?? [],
                ],
            ], 202);
        }

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'token' => $result['token'],
                'expires_at' => $result['expires_at'],
                'user' => $this->userPayload($result['user']),
            ],
        ]);
    }

    private function error(MobileAuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'message' => match ($exception->errorCode) {
                'EMAIL_NOT_VERIFIED' => 'Email address is not verified.',
                'AUTH_PROVISIONING_PENDING' => 'Account provisioning is not complete.',
                'TWO_FACTOR_CHALLENGE_EXPIRED' => 'Verifikasi dua langkah sudah kedaluwarsa.',
                'TWO_FACTOR_CHALLENGE_USED' => 'Verifikasi dua langkah sudah digunakan.',
                'TWO_FACTOR_CHALLENGE_LOCKED' => 'Verifikasi dua langkah terkunci.',
                'TWO_FACTOR_RATE_LIMITED' => 'Verifikasi dua langkah terlalu sering.',
                default => 'Kode verifikasi dua langkah tidak valid.',
            },
            'code' => $exception->errorCode,
        ], $exception->status);
    }

    /** @return array{id: int, name: string, email: string} */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /** @return list<string> */
    private function authenticationMethods(User $user): array
    {
        $methods = ['manual'];

        if ($user->authIdentities()->where('provider', AuthIdentity::PROVIDER_GOOGLE)->exists()) {
            $methods[] = 'google';
        }

        return $methods;
    }
}
