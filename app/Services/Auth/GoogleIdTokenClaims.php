<?php

namespace App\Services\Auth;

final readonly class GoogleIdTokenClaims
{
    public function __construct(
        public string $subject,
        public string $email,
        public int $expiresAt,
        public int $issuedAt,
        public ?string $name = null,
    ) {}
}
