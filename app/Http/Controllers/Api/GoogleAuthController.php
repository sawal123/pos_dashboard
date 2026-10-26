<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GoogleAuthenticationRequest;
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
            );
        } catch (GoogleAuthenticationException $exception) {
            $status = match ($exception->errorCode) {
                'GOOGLE_AUTH_NOT_CONFIGURED', 'GOOGLE_PROVISIONING_FAILED' => 503,
                'GOOGLE_EMAIL_UNVERIFIED', 'TWO_FACTOR_REQUIRED' => 403,
                'ACCOUNT_LINK_REQUIRED', 'IDENTITY_CONFLICT' => 409,
                default => 401,
            };

            return response()->json([
                'message' => $this->safeMessage($exception->errorCode),
                'code' => $exception->errorCode,
            ], $status);
        }

        return response()->json([
            'message' => 'Login Google berhasil.',
            'data' => [
                'token_type' => 'Bearer',
                'token' => $result['token'],
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
            'ACCOUNT_LINK_REQUIRED' => 'Akun ini memerlukan penghubungan akun.',
            'IDENTITY_CONFLICT' => 'Identitas Google tidak dapat digunakan.',
            'TWO_FACTOR_REQUIRED' => 'Two-factor authentication is required.',
            'GOOGLE_AUTH_NOT_CONFIGURED' => 'Login Google belum tersedia.',
            default => 'Token Google tidak valid.',
        };
    }
}
