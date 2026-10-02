<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * PREM-D03 — a READY cloud backup snapshot is immutable and may never be
 * updated. Retention may only delete old snapshots, never rewrite them.
 */
class ImmutableCloudBackupException extends RuntimeException {}
