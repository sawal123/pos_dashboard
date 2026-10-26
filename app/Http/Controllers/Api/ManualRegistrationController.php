<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ManualRegistrationRequest;
use App\Http\Requests\Api\ResendEmailOtpRequest;
use App\Http\Requests\Api\VerifyEmailOtpRequest;
use App\Models\PendingRegistration;
use App\Services\Auth\ManualRegistrationException;
use App\Services\Auth\ManualRegistrationService;
use App\Services\Auth\OtpException;
use Illuminate\Http\JsonResponse;

final class ManualRegistrationController extends Controller
{
    public function __construct(
        private readonly ManualRegistrationService $registrations,
    ) {}

    public function register(ManualRegistrationRequest $request): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');

        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._~-]{15,254}\z/D', $idempotencyKey)) {
            return response()->json([
                'message' => 'Idempotency-Key tidak valid.',
                'code' => 'IDEMPOTENCY_KEY_INVALID',
            ], 422);
        }

        try {
            $validated = $request->validated();
            $result = $this->registrations->initiate([
                'name' => (string) $validated['name'],
                'email' => (string) $validated['email'],
                'password' => (string) $validated['password'],
                'business_name' => (string) $validated['business_name'],
            ], $idempotencyKey, $request->ip());
        } catch (ManualRegistrationException $exception) {
            return $this->registrationError($exception);
        }

        if ($result['collision'] || $result['registration'] === null) {
            return $this->genericPendingResponse($idempotencyKey);
        }

        $registration = $result['registration'];

        if (! $registration->isPending()) {
            return response()->json([
                'message' => 'Registrasi sudah terverifikasi.',
                'data' => [
                    'registration_id' => (string) $registration->getKey(),
                    'state' => 'verified',
                    'provisioning' => 'complete',
                ],
            ], 202);
        }

        $challenge = $result['challenge'];

        if ($challenge === null) {
            return $this->registrationError(new ManualRegistrationException(
                'OTP_DELIVERY_FAILED',
                'The OTP email could not be delivered safely.',
            ), $registration);
        }

        return response()->json([
            'message' => 'Jika alamat email dapat digunakan, instruksi verifikasi telah dikirim.',
            'data' => [
                'registration_id' => (string) $registration->getKey(),
                'state' => 'pending_email',
                'otp_expires_at' => $challenge->expiresAt->toIso8601String(),
                'resend_available_at' => $challenge->resendAvailableAt->toIso8601String(),
            ],
        ], 202);
    }

    public function verify(VerifyEmailOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->registrations->verify(
                $request->string('registration_id')->toString(),
                $request->string('code')->toString(),
                $request->ip(),
            );
        } catch (ManualRegistrationException $exception) {
            return $this->registrationError($exception);
        } catch (OtpException $exception) {
            return $this->otpError($exception);
        }

        return response()->json([
            'message' => 'Email berhasil diverifikasi.',
            'data' => $result,
        ]);
    }

    public function resend(ResendEmailOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->registrations->resend(
                $request->string('registration_id')->toString(),
                $request->ip(),
            );
        } catch (ManualRegistrationException $exception) {
            return $this->registrationError($exception);
        } catch (OtpException $exception) {
            return $this->otpError($exception);
        }

        $challenge = $result['challenge'];

        return response()->json([
            'message' => 'Jika registrasi dapat digunakan, instruksi verifikasi telah dikirim.',
            'data' => [
                'registration_id' => (string) $result['registration']->getKey(),
                'state' => 'pending_email',
                'otp_expires_at' => $challenge->expiresAt->toIso8601String(),
                'resend_available_at' => $challenge->resendAvailableAt->toIso8601String(),
            ],
        ], 202);
    }

    private function registrationError(ManualRegistrationException $exception, ?PendingRegistration $registration = null): JsonResponse
    {
        $status = match ($exception->errorCode) {
            'IDEMPOTENCY_KEY_REUSED' => 409,
            'OTP_DELIVERY_FAILED' => 503,
            'AUTH_PROVISIONING_PENDING' => 409,
            'OTP_RESEND_COOLDOWN', 'OTP_RATE_LIMITED' => 429,
            default => 422,
        };

        $response = [
            'message' => $this->safeMessage($exception->errorCode),
            'code' => $exception->errorCode,
        ];

        if ($registration !== null && $exception->errorCode === 'OTP_DELIVERY_FAILED') {
            $response['data'] = [
                'registration_id' => (string) $registration->getKey(),
                'state' => 'pending_email',
            ];
        }

        $json = response()->json($response, $status);

        if ($exception->getPrevious() instanceof OtpException && $exception->getPrevious()->retryAfterSeconds > 0) {
            $json->headers->set('Retry-After', (string) $exception->getPrevious()->retryAfterSeconds);
        }

        return $json;
    }

    private function otpError(OtpException $exception): JsonResponse
    {
        $status = match ($exception->errorCode) {
            'OTP_RESEND_COOLDOWN', 'OTP_RATE_LIMITED' => 429,
            'OTP_DELIVERY_FAILED' => 503,
            'OTP_EXPIRED', 'OTP_REPLACED', 'OTP_ALREADY_USED' => 410,
            default => 422,
        };

        $response = response()->json([
            'message' => $this->safeMessage($exception->errorCode),
            'code' => $exception->errorCode,
        ], $status);

        if ($exception->retryAfterSeconds > 0) {
            $response->headers->set('Retry-After', (string) $exception->retryAfterSeconds);
        }

        return $response;
    }

    private function genericPendingResponse(string $idempotencyKey): JsonResponse
    {
        $data = $this->registrations->collisionResponse($idempotencyKey);

        return response()->json([
            'message' => 'Jika alamat email dapat digunakan, instruksi verifikasi telah dikirim.',
            'data' => $data,
        ], 202);
    }

    private function safeMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'PENDING_EMAIL' => 'Registrasi email masih menunggu verifikasi.',
            'OTP_INVALID' => 'Kode OTP tidak valid.',
            'OTP_EXPIRED' => 'Kode OTP sudah kedaluwarsa.',
            'OTP_MAX_ATTEMPTS' => 'Batas percobaan OTP telah tercapai.',
            'OTP_RESEND_COOLDOWN' => 'Permintaan OTP berikutnya belum tersedia.',
            'OTP_RATE_LIMITED' => 'Permintaan OTP terlalu sering.',
            'OTP_REPLACED' => 'Kode OTP telah digantikan.',
            'OTP_ALREADY_USED' => 'Kode OTP sudah digunakan.',
            'OTP_DELIVERY_FAILED' => 'Email verifikasi belum dapat dikirim. Silakan coba lagi.',
            'AUTH_PROVISIONING_PENDING' => 'Registrasi belum dapat diselesaikan dan memerlukan pemulihan.',
            'IDEMPOTENCY_KEY_REUSED' => 'Idempotency-Key sudah digunakan dengan data berbeda.',
            default => 'Permintaan autentikasi tidak dapat diproses.',
        };
    }
}
