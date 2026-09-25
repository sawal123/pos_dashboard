<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Thrown by {@see TestDatabaseGuard} when a test run resolves to a database
 * that is not the approved isolated test target.
 */
final class UnsafeTestDatabaseException extends RuntimeException {}
