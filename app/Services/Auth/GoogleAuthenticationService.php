<?php

namespace App\Services\Auth;

use App\Models\User;

final class GoogleAuthenticationService
{
    public function __construct(
        private readonly GoogleIdTokenVerifier $verifier,
        private readonly GoogleAccountService $accounts,
        private readonly MobileAuthenticationService $mobileAuthentication,
    ) {}

    /**
     * Verify the assertion, atomically create a Google-only account when
     * required, and issue a mobile Sanctum token only after policy checks.
     *
     * @return array{user: User, token?: string, expires_at: string, challenge_id?: string, methods?: list<string>}
     */
    public function authenticate(string $idToken, string $clientPlatform, ?string $ipAddress = null): array
    {
        $claims = $this->verifier->verify($idToken, $clientPlatform);
        $user = $this->accounts->resolveOrProvision($claims);

        return $this->issueToken($user, $ipAddress);
    }

    /** @return array{user: User, token?: string, expires_at: string, challenge_id?: string, methods?: list<string>} */
    private function issueToken(User $user, ?string $ipAddress): array
    {
        try {
            return $this->mobileAuthentication->authenticate(
                $user,
                'google',
                $ipAddress,
            );
        } catch (MobileAuthenticationException $exception) {
            throw new GoogleAuthenticationException(
                $exception->errorCode,
                $exception->getMessage(),
                $exception,
            );
        }
    }
}
