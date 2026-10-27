<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GoogleAuthenticationRequest;
use App\Services\Auth\AuthSecurityAuditService;
use App\Services\Auth\GoogleAuthenticationException;
use App\Services\Auth\GoogleAuthenticationService;
use Illuminate\Http\JsonResponse;

final class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthenticationService $authentication,
    ) {}

    public function authenticate(GoogleAuthenticationRequest $request): JsonResponse
    {
        try {
            $result = $this->authentication->authenticate(
                (string) $request->validated('id_token'),
                (string) $request->validated('client_platform'),
                $request->ip(),
            );
        } catch (GoogleAuthenticationException $exception) {
            app(AuthSecurityAuditService::class)->record('auth.google.verification_failed', [
                'status' => 'rejected',
                'error_category' => $exception->errorCode,
                'provider' => 'google',
                'client_platform' => (string) $request->validated('client_platform'),
            ]);

            $status = match ($exception->errorCode) {
                'GOOGLE_AUTH_NOT_CONFIGURED', 'GOOGLE_PROVISIONING_FAILED' => 503,
                'GOOGLE_EMAIL_UNVERIFIED', 'EMAIL_NOT_VERIFIED' => 403,
                'AUTH_PROVISIONING_PENDING' => 403,
                'ACCOUNT_LINK_REQUIRED', 'IDENTITY_CONFLICT' => 409,
                default => 401,
            };

            return response()->json([
                'message' => $this->safeMessage($exception->errorCode),
                'code' => $exception->errorCode,
            ], $status);
        }

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

        app(AuthSecurityAuditService::class)->record('auth.login.succeeded', [
            'status' => 'authenticated',
            'provider' => 'google',
            'client_platform' => (string) $request->validated('client_platform'),
            'account_identifier' => (string) $result['user']->getAuthIdentifier(),
        ]);

        return response()->json([
            'message' => 'Login Google berhasil.',
            'data' => [
                'token_type' => 'Bearer',
                'token' => $result['token'],
                'expires_at' => $result['expires_at'],
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                ],
            ],
        ]);
    }

    private function safeMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'GOOGLE_TOKEN_EXPIRED' => 'Token Google sudah kedaluwarsa.',
            'GOOGLE_AUDIENCE_INVALID' => 'Token Google tidak ditujukan untuk aplikasi ini.',
            'GOOGLE_EMAIL_UNVERIFIED' => 'Email Google belum terverifikasi.',
            'EMAIL_NOT_VERIFIED' => 'Email address is not verified.',
            'AUTH_PROVISIONING_PENDING' => 'Account provisioning is not complete.',
            'ACCOUNT_LINK_REQUIRED' => 'Akun ini memerlukan penghubungan akun.',
            'IDENTITY_CONFLICT' => 'Identitas Google tidak dapat digunakan.',
            'GOOGLE_AUTH_NOT_CONFIGURED' => 'Login Google belum tersedia.',
            default => 'Token Google tidak valid.',
        };
    }
}
