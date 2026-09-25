<?php

namespace Tests\Support;

use Illuminate\Contracts\Foundation\Application;

/**
 * QA-ENV-01 — fail-closed guard for the test database.
 *
 * This guard is invoked from Tests\TestCase::refreshApplication(), which runs
 * after the application has booted but BEFORE any test trait (RefreshDatabase,
 * DatabaseMigrations, DatabaseTruncation, ...) can migrate, truncate or seed
 * anything. A misconfigured run therefore aborts with a clear message instead
 * of ever touching a development or production database.
 *
 * The active profile is selected by the FORCED QA_TEST_DB_PROFILE environment
 * variable (see phpunit.xml / phpunit.p38concurrency.xml):
 *
 *   - sqlite_memory (default) — the standard suite MUST use SQLite :memory:.
 *   - p38_mysql               — the concurrency gate MUST use the dedicated,
 *                               throwaway MySQL database only.
 */
final class TestDatabaseGuard
{
    public const PROFILE_SQLITE_MEMORY = 'sqlite_memory';

    public const PROFILE_P38_MYSQL = 'p38_mysql';

    /** The only MySQL database the concurrency gate may ever use. */
    public const P38_CONCURRENCY_DATABASE = 'pos_p38_concurrency_test';

    /**
     * Databases that must never be reachable from a test run.
     *
     * @var list<string>
     */
    private const FORBIDDEN_DATABASES = [
        'pos_dashboard',
        'pos_dashboard_test',
        'production',
        'prod',
    ];

    /**
     * Assert that the resolved connection is safe for the active profile.
     *
     * @throws UnsafeTestDatabaseException
     */
    public static function assertSafe(Application $app): void
    {
        $config = $app->make('config');

        $profile = self::profile();
        $appEnv = (string) $config->get('app.env');
        $connectionName = (string) $config->get('database.default');
        $connection = (array) $config->get("database.connections.{$connectionName}", []);
        $driver = (string) ($connection['driver'] ?? '');
        $database = $connection['database'] ?? null;
        $url = $connection['url'] ?? null;
        $sessionDriver = (string) $config->get('session.driver');

        $snapshot = [
            'profile' => $profile,
            'app_env' => $appEnv,
            'connection' => $connectionName,
            'driver' => $driver,
            'database' => self::stringify($database),
            'url' => ($url === null || $url === '') ? '(empty)' : (string) $url,
            'session_driver' => $sessionDriver,
        ];

        $violations = [];

        if ($appEnv !== 'testing') {
            $violations[] = "APP_ENV must be 'testing' (got '{$appEnv}').";
        }

        if (self::isForbiddenDatabase($database)) {
            $violations[] = "The configured database '".self::stringify($database)."' is a protected development/production database.";
        }

        if ($profile === self::PROFILE_P38_MYSQL) {
            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                $violations[] = "The p38_mysql profile requires a mysql/mariadb driver (got '{$driver}').";
            }

            if ($database !== self::P38_CONCURRENCY_DATABASE) {
                $violations[] = sprintf(
                    "The concurrency gate may only target '%s' (got '%s').",
                    self::P38_CONCURRENCY_DATABASE,
                    self::stringify($database)
                );
            }

            if ($url !== null && $url !== '') {
                $violations[] = 'DB_URL must be empty for the concurrency gate; a URL redirects the connection.';
            }
        } else {
            if ($driver !== 'sqlite') {
                $violations[] = "The standard suite must use the 'sqlite' driver (got '{$driver}').";
            }

            if ($database !== ':memory:') {
                $violations[] = "The standard suite must use an in-memory database (DB_DATABASE=:memory:), got '".self::stringify($database)."'.";
            }

            if ($url !== null && $url !== '') {
                $violations[] = 'DB_URL must be empty for the standard suite; a URL redirects the connection.';
            }

            if ($sessionDriver !== 'array') {
                $violations[] = "SESSION_DRIVER must be 'array' for the standard suite (got '{$sessionDriver}').";
            }
        }

        if ($violations !== []) {
            throw new UnsafeTestDatabaseException(self::message($snapshot, $violations));
        }
    }

    /**
     * Resolve the active profile, defaulting to the strictest standard profile.
     */
    public static function profile(): string
    {
        $profile = getenv('QA_TEST_DB_PROFILE');

        if ($profile === false || $profile === '') {
            $profile = $_ENV['QA_TEST_DB_PROFILE'] ?? $_SERVER['QA_TEST_DB_PROFILE'] ?? '';
        }

        return is_string($profile) && $profile !== '' ? $profile : self::PROFILE_SQLITE_MEMORY;
    }

    private static function isForbiddenDatabase(mixed $database): bool
    {
        if (! is_string($database)) {
            return false;
        }

        $normalized = strtolower($database);

        return in_array($normalized, self::FORBIDDEN_DATABASES, true)
            || str_starts_with($normalized, 'pos_dashboard');
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '(null)';
        }

        return is_scalar($value) ? (string) $value : gettype($value);
    }

    /**
     * @param  array<string, string>  $snapshot
     * @param  list<string>  $violations
     */
    private static function message(array $snapshot, array $violations): string
    {
        $lines = [
            '',
            str_repeat('=', 72),
            'QA-ENV-01 :: UNSAFE TEST DATABASE — aborted BEFORE any migration or seed.',
            str_repeat('=', 72),
            'Refusing to continue: the resolved test connection is not the approved target.',
            '',
            'Resolved configuration:',
        ];

        foreach ($snapshot as $key => $value) {
            $lines[] = sprintf('  %-16s : %s', $key, $value);
        }

        $lines[] = '';
        $lines[] = 'Violations:';

        foreach ($violations as $violation) {
            $lines[] = '  - '.$violation;
        }

        $lines[] = '';
        $lines[] = 'How to fix:';
        $lines[] = '  * Run the standard suite with the repository config:';
        $lines[] = '        composer test   |   composer ci:check';
        $lines[] = '        vendor/bin/phpunit -c phpunit.xml';
        $lines[] = '  * Do NOT export DB_CONNECTION / DB_DATABASE / APP_ENV in your shell,';
        $lines[] = '    .env or CI: OS-level variables can override phpunit.xml.';
        $lines[] = '  * Never point a test run at a development or production database.';
        $lines[] = '  * The MySQL concurrency gate must use phpunit.p38concurrency.xml, which';
        $lines[] = '    is pinned to the isolated "'.self::P38_CONCURRENCY_DATABASE.'" database.';
        $lines[] = str_repeat('=', 72);
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }
}
