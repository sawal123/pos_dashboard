<?php

namespace App\Services\Auth;

use Carbon\CarbonInterface;

final readonly class OtpChallengeResult
{
    public function __construct(
        public string $challengeId,
        public string $purpose,
        public CarbonInterface $expiresAt,
        public CarbonInterface $resendAvailableAt,
        public bool $created,
    ) {}

    /**
     * Safe metadata for a future HTTP/API adapter. It never includes a code,
     * digest, nonce, email, or bearer credential.
     *
     * @return array{challenge_id: string, purpose: string, expires_at: string, resend_available_at: string, created: bool}
     */
    public function toArray(): array
    {
        return [
            'challenge_id' => $this->challengeId,
            'purpose' => $this->purpose,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'resend_available_at' => $this->resendAvailableAt->toIso8601String(),
            'created' => $this->created,
        ];
    }
}
