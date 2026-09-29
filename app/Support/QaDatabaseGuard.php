<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Fail-closed guard for QA-only seeders.
 *
 * QA fixtures and the E2E reset delete users, transactions and tokens, so they
 * must never run against a development or production database. The guard reads
 * the database the connection is ACTUALLY bound to (a live `select database()`
 * on MySQL/MariaDB, not merely the configured name) and refuses anything that
 * is not an explicitly allow-listed isolated QA database.
 *
 * Allow-list is `config('qa.allowed_databases')` (env `QA_ALLOWED_DATABASES`,
 * comma separated; default `pos_qa_b2`). Any name starting with a forbidden
 * prefix (`pos_dashboard`, `prod`) is rejected even if it were allow-listed,
 * and the `production`/`prod` environment is always rejected.
 */
final class QaDatabaseGuard
{
    /**
     * Database name prefixes that are never a valid QA target.
     *
     * @var list<string>
     */
    public const FORBIDDEN_PREFIXES = ['pos_dashboard', 'prod'];

    /**
     * Environments in which no QA seeder may ever run.
     *
     * @var list<string>
     */
    public const FORBIDDEN_ENVIRONMENTS = ['production', 'prod'];

    /**
     * Abort unless the active database is an explicitly approved QA database.
     *
     * @param  string|null  $database  Override for testing; null reads the live connection.
     * @param  string|null  $environment  Override for testing; null reads the app environment.
     *
     * @throws UnsafeQaDatabaseException
     */
    public static function assertIsolated(?string $database = null, ?string $environment = null): void
    {
        $environment ??= app()->environment();
        $database ??= self::resolveActiveDatabase();

        if (in_array($environment, self::FORBIDDEN_ENVIRONMENTS, true)) {
            throw new UnsafeQaDatabaseException(
                "QA seeders refuse to run in the [{$environment}] environment.",
            );
        }

        if ($database === null || $database === '') {
            throw new UnsafeQaDatabaseException(
                'QA seeders could not resolve the active database name.',
            );
        }

        foreach (self::FORBIDDEN_PREFIXES as $prefix) {
            if (str_starts_with($database, $prefix)) {
                throw new UnsafeQaDatabaseException(
                    "QA seeders refuse the forbidden database [{$database}].",
                );
            }
        }

        /** @var list<string> $allowed */
        $allowed = array_values((array) config('qa.allowed_databases', []));

        if (! in_array($database, $allowed, true)) {
            throw new UnsafeQaDatabaseException(
                "QA seeders refuse the database [{$database}]. Allowed QA databases: ".
                (implode(', ', $allowed) !== '' ? implode(', ', $allowed) : '(none configured)').'.',
            );
        }
    }

    /**
     * The name of the database the active connection is really bound to.
     *
     * MySQL/MariaDB are verified with a live `select database()` so a stale or
     * spoofed config value can never pass the guard. Other drivers fall back to
     * the connection's database name (e.g. SQLite `:memory:` in the test suite).
     */
    public static function resolveActiveDatabase(): ?string
    {
        $connection = DB::connection();

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            /** @var array<string, mixed> $row */
            $row = (array) $connection->selectOne('select database() as name');

            return isset($row['name']) ? (string) $row['name'] : null;
        }

        $name = $connection->getDatabaseName();

        return $name !== '' ? $name : null;
    }
}
