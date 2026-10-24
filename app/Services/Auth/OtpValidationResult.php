<?php

namespace App\Services\Auth;

use Carbon\CarbonInterface;

/**
 * Internal, non-HTTP result of a successful validation phase.
 *
 * The digest is a verifier, not the plaintext OTP. AUTH-D03 must pass this
 * object directly to consume() inside its provisioning transaction.
 */
final readonly class OtpValidationResult
{
    public function __construct(
        public string $challengeId,
        public string $subjectReference,
        public string $purpose,
        public string $digest,
        public CarbonInterface $validatedAt,
    ) {}
}
