<?php

namespace App\Services\Sync;

use Exception;

/**
 * Explicit, recoverable sync state that is never resolved silently.
 *
 * P38 uses this for concurrent mutations that cannot be reconciled by the
 * server alone (for example a stock delta that would violate the existing
 * non-negative stock policy). The request is rejected atomically, the device
 * keeps its durable outbox payload, and the caller must surface an
 * action-required recovery state instead of guessing a resolution.
 */
class SyncStateRequiredException extends Exception
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $stateCode,
        public readonly string $entity,
        public readonly string $syncId,
        public readonly array $details = []
    ) {
        parent::__construct("Sync state {$stateCode} required for {$entity} with sync_id {$syncId}.");
    }
}
