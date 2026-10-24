<?php

namespace App\Services\Auth;

use RuntimeException;
use Throwable;

/**
 * Stable, HTTP-agnostic OTP failure for AUTH-D03 to map to API contracts.
 */
class OtpException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $retryAfterSeconds = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
