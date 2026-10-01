<?php

namespace App\Services\Subscription;

final class PricingUnavailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Official pricing or Midtrans configuration is not available.');
    }
}
