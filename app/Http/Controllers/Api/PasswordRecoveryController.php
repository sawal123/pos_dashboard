<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Services\Auth\PasswordRecoveryException;
use App\Services\Auth\PasswordRecoveryService;
use Illuminate\Http\JsonResponse;

final class PasswordRecoveryController extends Controller
{
    public function __construct(
        private readonly PasswordRecoveryService $recovery,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->recovery->request((string) $request->validated('email'));
        } catch (PasswordRecoveryException $exception) {
            if ($exception->errorCode === 'PASSWORD_RESET_RATE_LIMITED') {
                return response()->json([
                    'message' => 'Permintaan pemulihan terlalu sering.',
                    'code' => $exception->errorCode,
                ], 429);
            }
        }

        return response()->json([
            'message' => 'Jika akun memenuhi syarat, instruksi pemulihan telah dikirim.',
        ], 202);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->recovery->reset([
                'email' => (string) $request->validated('email'),
                'token' => (string) $request->validated('token'),
                'password' => (string) $request->validated('password'),
                'password_confirmation' => (string) $request->validated('password_confirmation'),
            ]);
        } catch (PasswordRecoveryException $exception) {
            return response()->json([
                'message' => $exception->errorCode === 'PASSWORD_RESET_EXPIRED'
                    ? 'Token pemulihan sudah kedaluwarsa.'
                    : 'Token pemulihan tidak valid.',
                'code' => $exception->errorCode,
            ], $exception->status);
        }

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan masuk kembali.',
        ]);
    }
}
