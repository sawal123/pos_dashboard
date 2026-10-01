<?php

namespace App\Services\Subscription\Midtrans;

final readonly class MidtransSnapResponse
{
    public function __construct(
        public string $token,
        public string $redirectUrl,
    ) {}
}
