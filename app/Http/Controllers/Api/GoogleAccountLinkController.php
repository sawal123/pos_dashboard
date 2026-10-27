<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GoogleAccountLinkRequest;
use App\Http\Requests\Api\GoogleReauthenticationRequest;
use App\Models\User;
use App\Services\Auth\GoogleAccountLinkingException;
use App\Services\Auth\GoogleAccountLinkingService;
use App\Services\Auth\GoogleAuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class GoogleAccountLinkController extends Controller
{
    public function __construct(
        private readonly GoogleAccountLinkingService $linking,
    ) {}

    public function reauthenticate(GoogleReauthenticationRequest $request): JsonResponse
    {
        $context = $this->mobileContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        [$user, $token] = $context;

        try {
            $result = $this->linking->reauthenticate(
                $user,
                $token,
                (string) $request->validated('password'),
                $request->validated('totp_code') !== null ? (string) $request->validated('totp_code') : null,
                $request->ip(),
            );
        } catch (GoogleAccountLinkingException $exception) {
            return $this->error($exception);
        }

        return response()->json(['data' => $result]);
    }

    public function link(GoogleAccountLinkRequest $request): JsonResponse
    {
        $context = $this->mobileContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        [$user, $token] = $context;

        try {
            $result = $this->linking->link(
                $user,
                $token,
                (string) $request->validated('reauthentication_token'),
                (string) $request->validated('id_token'),
                (string) $request->validated('client_platform'),
            );
        } catch (GoogleAccountLinkingException|GoogleAuthenticationException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'message' => 'Akun Google berhasil dihubungkan.',
            'data' => $result,
        ]);
    }

    /** @return array{0: User, 1: PersonalAccessToken}|JsonResponse */
    private function mobileContext(Request $request): array|JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user === null || ! $token instanceof PersonalAccessToken) {
            return response()->json(['message' => 'Authentication is required.', 'code' => 'UNAUTHENTICATED'], 401);
        }

        if (! $token->can('mobile')) {
            return response()->json(['message' => 'Mobile API token is required.', 'code' => 'REAUTHENTICATION_REQUIRED'], 403);
        }

        return [$user, $token];
    }

    private function error(GoogleAccountLinkingException|GoogleAuthenticationException $exception): JsonResponse
    {
        $status = match ($exception->errorCode) {
            'INVALID_CREDENTIALS', 'GOOGLE_TOKEN_INVALID', 'GOOGLE_TOKEN_EXPIRED', 'GOOGLE_EMAIL_UNVERIFIED', 'GOOGLE_AUDIENCE_INVALID' => 401,
            'REAUTHENTICATION_REQUIRED', 'REAUTHENTICATION_EXPIRED', 'REAUTHENTICATION_ALREADY_USED', 'TWO_FACTOR_REQUIRED' => 403,
            'IDENTITY_ALREADY_LINKED', 'IDENTITY_CONFLICT' => 409,
            'GOOGLE_AUTH_NOT_CONFIGURED' => 503,
            'REAUTHENTICATION_RATE_LIMITED' => 429,
            default => 401,
        };

        return response()->json([
            'message' => $this->safeMessage($exception->errorCode),
            'code' => $exception->errorCode,
        ], $status);
    }

    private function safeMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'INVALID_CREDENTIALS' => 'Kredensial tidak valid.',
            'REAUTHENTICATION_EXPIRED' => 'Sesi verifikasi ulang sudah kedaluwarsa.',
            'REAUTHENTICATION_ALREADY_USED' => 'Sesi verifikasi ulang sudah digunakan.',
            'TWO_FACTOR_REQUIRED' => 'Two-factor authentication is required.',
            'IDENTITY_ALREADY_LINKED' => 'Identitas Google sudah terhubung ke akun lain.',
            'IDENTITY_CONFLICT' => 'Identitas Google lain sudah terhubung ke akun ini.',
            'GOOGLE_AUTH_NOT_CONFIGURED' => 'Login Google belum tersedia.',
            'REAUTHENTICATION_RATE_LIMITED' => 'Permintaan verifikasi ulang terlalu sering.',
            default => 'Permintaan autentikasi tidak dapat diproses.',
        };
    }
}
