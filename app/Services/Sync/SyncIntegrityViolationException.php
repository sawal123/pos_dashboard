<?php

namespace App\Services\Sync;

use Exception;

/**
 * Authoritative cashier integrity violation detected inside the write
 * transaction, after row locks.
 *
 * Unlike {@see SyncConflictException} (an optimistic-version conflict), this
 * represents a forbidden mutation that must never be applied: a cumulative
 * stock deduction beyond the quantity sold, or a rewrite of historical
 * evidence. The whole request is rolled back and answered with 403.
 */
class SyncIntegrityViolationException extends Exception
{
    public function __construct(
        public readonly string $entity,
        public readonly string $syncId,
        public readonly string $reason
    ) {
        parent::__construct("Sync integrity violation on {$entity} ({$reason}) for sync_id {$syncId}.");
    }
}
