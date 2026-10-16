<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class TwoFactorDeviceService
{
    public const COOKIE_PREFIX = '2fa_trusted_device_';

    public const DEFAULT_REMEMBER_DAYS = 30;

    /**
     * Determine if the current device in the request is trusted for the user.
     */
    public function isDeviceTrusted(Request $request, User $user): bool
    {
        $cookieName = $this->cookieName($user);
        $cookieValue = $request->cookie($cookieName);

        if (! is_string($cookieValue) || empty($cookieValue)) {
            return false;
        }

        $parts = explode('|', $cookieValue);
        if (count($parts) !== 3) {
            return false;
        }

        [$userId, $expiresAt, $hash] = $parts;

        if ((int) $userId !== (int) $user->id) {
            return false;
        }

        if ((int) $expiresAt < time()) {
            return false;
        }

        $expectedHash = $this->generateDeviceHash($user, (int) $expiresAt);

        return hash_equals($expectedHash, $hash);
    }

    /**
     * Create a trusted device cookie for the given user.
     */
    public function createDeviceCookie(User $user, int $days = self::DEFAULT_REMEMBER_DAYS): SymfonyCookie
    {
        $expiresAt = time() + ($days * 86400);
        $hash = $this->generateDeviceHash($user, $expiresAt);
        $value = $user->id.'|'.$expiresAt.'|'.$hash;

        return Cookie::make(
            name: $this->cookieName($user),
            value: $value,
            minutes: $days * 24 * 60,
            path: '/',
            domain: null,
            secure: config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: 'lax'
        );
    }

    /**
     * Forget/delete the trusted device cookie.
     */
    public function forgetDeviceCookie(User $user): SymfonyCookie
    {
        return Cookie::forget($this->cookieName($user), '/', null);
    }

    /**
     * Generate an HMAC hash combining user identity, password hash, 2FA secret, and expiry.
     */
    public function generateDeviceHash(User $user, int $expiresAt): string
    {
        $appKey = (string) config('app.key');
        $secret = (string) $user->two_factor_secret;
        $password = (string) $user->password;

        return hash_hmac('sha256', "{$user->id}|{$expiresAt}|{$password}|{$secret}", $appKey);
    }

    /**
     * Get the cookie name for a specific user.
     */
    public function cookieName(User $user): string
    {
        return self::COOKIE_PREFIX.$user->id;
    }
}
