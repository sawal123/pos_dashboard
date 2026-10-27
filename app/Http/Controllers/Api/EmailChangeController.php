<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EmailChangeRequest;
use App\Http\Requests\Api\VerifyEmailChangeRequest;
use App\Models\User;
use App\Services\Auth\EmailChangeException;
use App\Services\Auth\EmailChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class EmailChangeController extends Controller
{
    public function __construct(
        private readonly EmailChangeService $changes,
    ) {}

    public function request(EmailChangeRequest $request): JsonResponse
    {
        $context = $this->mobileContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        [$user, $token] = $context;

        try {
            $result = $this->changes->request(
                $user,
                $token,
                (string) $request->validated('new_email'),
                $request->validated('current_password') !== null ? (string) $request->validated('current_password') : null,
                $request->validated('totp_code') !== null ? (string) $request->validated('totp_code') : null,
                $request->validated('id_token') !== null ? (string) $request->validated('id_token') : null,
                $request->validated('client_platform') !== null ? (string) $request->validated('client_platform') : null,
                $request->ip(),
            );
        } catch (EmailChangeException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'message' => 'Kode verifikasi telah dikirim ke email baru.',
            'data' => $result,
        ], 202);
    }

    public function verify(VerifyEmailChangeRequest $request): JsonResponse
    {
        $context = $this->mobileContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        [$user, $token] = $context;

        try {
            $this->changes->verify(
                $user,
                $token,
                (string) $request->validated('change_id'),
                (string) $request->validated('code'),
                $request->ip(),
            );
        } catch (EmailChangeException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'message' => 'Alamat email berhasil diperbarui. Silakan masuk kembali.',
            'data' => [
                'state' => 'verified',
                'reauthentication_required' => true,
            ],
        ]);
    }

    /** @return array{0: User, 1: PersonalAccessToken}|JsonResponse */
    private function mobileContext(Request $request): array|JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken) {
            return response()->json(['message' => 'Authentication is required.', 'code' => 'UNAUTHENTICATED'], 401);
        }

        if (! $token->can('mobile')) {
            return response()->json(['message' => 'Mobile API token is required.', 'code' => 'MOBILE_ABILITY_REQUIRED'], 403);
        }

        return [$user, $token];
    }

    private function error(EmailChangeException $exception): JsonResponse
    {
        $message = match ($exception->errorCode) {
            'INVALID_CREDENTIALS' => 'Kredensial tidak valid.',
            'TWO_FACTOR_REQUIRED' => 'Verifikasi dua langkah diperlukan.',
            'EMAIL_ALREADY_USED' => 'Email sudah digunakan.',
            'EMAIL_CHANGE_PENDING' => 'Perubahan email sebelumnya masih menunggu verifikasi.',
            'EMAIL_CHANGE_EXPIRED' => 'Verifikasi perubahan email sudah kedaluwarsa.',
            'EMAIL_CHANGE_CONFLICT' => 'Perubahan email tidak dapat dilanjutkan.',
            'OTP_RATE_LIMITED' => 'Verifikasi email terlalu sering.',
            default => 'Verifikasi kepemilikan akun diperlukan.',
        };

        return response()->json([
            'message' => $message,
            'code' => $exception->errorCode,
        ], $exception->status);
    }
}
