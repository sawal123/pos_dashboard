<?php

namespace App\Services\Auth;

use RuntimeException;
use Throwable;

final class ManualRegistrationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
