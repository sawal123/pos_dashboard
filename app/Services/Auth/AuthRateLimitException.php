<?php

namespace App\Services\Auth;

use RuntimeException;

final class AuthRateLimitException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 429,
        public readonly int $retryAfterSeconds = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
