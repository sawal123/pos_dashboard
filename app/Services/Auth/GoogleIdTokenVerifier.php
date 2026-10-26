<?php

namespace App\Services\Auth;

interface GoogleIdTokenVerifier
{
    public function verify(string $idToken, string $clientPlatform): GoogleIdTokenClaims;
}
