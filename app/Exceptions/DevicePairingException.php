<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A recoverable domain failure while issuing or redeeming a cashier device
 * pairing code.
 *
 * Carries a stable machine-readable `code` (returned to the POS app) plus an
 * HTTP status, so both the dashboard and the mobile API can report the exact
 * problem instead of a generic validation error.
 */
class DevicePairingException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
