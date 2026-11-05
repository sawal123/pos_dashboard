<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Exception;

class SyncDataConflictException extends Exception
{
    public function __construct(string $message = 'Sync data conflict.')
    {
        parent::__construct($message);
    }
}

