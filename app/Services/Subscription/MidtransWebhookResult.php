<?php

namespace App\Services\Subscription;

final readonly class MidtransWebhookResult
{
    private function __construct(
        public int $statusCode,
        public string $code,
    ) {}

    public static function invalid(): self
    {
        return new self(403, 'INVALID_SIGNATURE');
    }

    public static function unknownOrder(): self
    {
        return new self(200, 'UNKNOWN_ORDER');
    }

    public static function handled(): self
    {
        return new self(200, 'HANDLED');
    }
}
