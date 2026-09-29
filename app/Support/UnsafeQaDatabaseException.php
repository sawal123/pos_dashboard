<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Thrown when a QA-only seeder is asked to run against a database that is not
 * an explicitly approved isolated QA database. The operation must abort before
 * it touches any row.
 */
class UnsafeQaDatabaseException extends RuntimeException {}
