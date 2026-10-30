<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthSecurityAuditService;
use App\Services\Auth\GoogleAccountService;
use App\Services\Auth\GoogleAuthenticationException;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\Auth\TwoFactorDeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class GoogleLoginController extends Controller
{
    public function __construct(
        private readonly GoogleIdTokenVerifier $verifier,
        private readonly GoogleAccountService $accounts,
    ) {}

    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'credential' => ['required', 'string', 'max:8192'],
        ]);

        try {
            $claims = $this->verifier->verify($validated['credential'], 'web');
            $user = $this->accounts->resolveOrProvision($claims);
        } catch (GoogleAuthenticationException $exception) {
            app(AuthSecurityAuditService::class)->record('auth.google.verification_failed', [
                'status' => 'rejected',
                'error_category' => $exception->errorCode,
                'provider' => 'google',
                'client_platform' => 'web',
            ]);

            return back()->withErrors(['google' => $this->safeMessage($exception->errorCode)]);
        }

        if (! $user->hasVerifiedEmail()) {
            return back()->withErrors(['google' => 'Email akun ini belum terverifikasi.']);
        }

        if ($user->two_factor_confirmed_at !== null
            && ! app(TwoFactorDeviceService::class)->isDeviceTrusted($request, $user)) {
            // Hand the challenge to Fortify's existing session flow instead of
            // completing the login here, so Google sign-in never bypasses 2FA.
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        app(AuthSecurityAuditService::class)->record('auth.login.succeeded', [
            'status' => 'authenticated',
            'provider' => 'google',
            'client_platform' => 'web',
            'account_identifier' => (string) $user->getAuthIdentifier(),
        ]);

        return app(LoginResponseContract::class)->toResponse($request);
    }

    private function safeMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'GOOGLE_AUTH_NOT_CONFIGURED' => 'Login Google belum dikonfigurasi.',
            'GOOGLE_TOKEN_EXPIRED' => 'Sesi Google sudah kedaluwarsa. Silakan coba lagi.',
            'GOOGLE_AUDIENCE_INVALID' => 'Token Google tidak ditujukan untuk aplikasi ini.',
            'GOOGLE_EMAIL_UNVERIFIED', 'EMAIL_NOT_VERIFIED' => 'Email Google belum terverifikasi.',
            'ACCOUNT_LINK_REQUIRED' => 'Email ini sudah terdaftar. Masuk dengan kata sandi, lalu hubungkan akun Google dari pengaturan.',
            'IDENTITY_CONFLICT' => 'Akun Google ini tidak dapat digunakan.',
            'GOOGLE_PROVISIONING_FAILED' => 'Akun tidak dapat disiapkan. Silakan coba lagi.',
            default => 'Login Google gagal. Silakan coba lagi.',
        };
    }
}
