<?php

namespace App\Services\Auth;

use Carbon\CarbonInterface;

final readonly class OtpVerificationResult
{
    public function __construct(
        public string $challengeId,
        public string $purpose,
        public CarbonInterface $consumedAt,
    ) {}
}
