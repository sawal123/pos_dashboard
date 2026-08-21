<?php

namespace App\Services\Sync;

use Exception;

class SyncConflictException extends Exception
{
    public function __construct(
        public readonly string $entity,
        public readonly string $syncId,
        public readonly int $serverVersion
    ) {
        parent::__construct("Sync conflict on entity {$entity} with sync_id {$syncId}.");
    }
}
