<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Tests\Support\TestDatabaseGuard;
use Tests\Support\UnsafeTestDatabaseException;
use Tests\TestCase;

/**
 * QA-ENV-01 — verifies the fail-closed database guard in isolation.
 *
 * These tests never open a database connection: they mutate the resolved
 * configuration in memory and assert that the guard accepts only the approved
 * targets and rejects everything else.
 */
class TestDatabaseGuardTest extends TestCase
{
    public function test_standard_profile_accepts_sqlite_in_memory(): void
    {
        $this->configureConnection('sqlite', ':memory:', null);

        TestDatabaseGuard::assertSafe($this->app);

        $this->addToAssertionCount(1);
    }

    public function test_standard_profile_rejects_a_mysql_connection(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage("The standard suite must use the 'sqlite' driver");

        $this->configureConnection('mysql', 'pos_qa_guard_probe', null);
    }

    public function test_standard_profile_rejects_a_file_backed_sqlite_database(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage('must use an in-memory database');

        $this->configureConnection('sqlite', 'database/database.sqlite', null);
    }

    public function test_standard_profile_rejects_a_non_empty_db_url(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage('DB_URL must be empty');

        $this->configureConnection('sqlite', ':memory:', 'mysql://root@127.0.0.1/pos_qa_guard_probe');
    }

    public function test_development_database_is_rejected_as_protected(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage('protected development/production database');

        $this->configureConnection('mysql', 'pos_dashboard', null);
    }

    public function test_standard_profile_rejects_a_non_testing_app_env(): void
    {
        $this->expectException(UnsafeTestDatabaseException::class);
        $this->expectExceptionMessage("APP_ENV must be 'testing'");

        Config::set('app.env', 'local');
        Config::set('session.driver', 'array');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.driver', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('database.connections.sqlite.url', null);

        TestDatabaseGuard::assertSafe($this->app);
    }

    public function test_p38_profile_accepts_only_the_dedicated_database(): void
    {
        $this->withProfile(TestDatabaseGuard::PROFILE_P38_MYSQL, function (): void {
            $this->configureConnection('mysql', TestDatabaseGuard::P38_CONCURRENCY_DATABASE, null);

            TestDatabaseGuard::assertSafe($this->app);
        });

        $this->addToAssertionCount(1);
    }

    public function test_p38_profile_rejects_the_development_database(): void
    {
        $this->withProfile(TestDatabaseGuard::PROFILE_P38_MYSQL, function (): void {
            $this->expectException(UnsafeTestDatabaseException::class);
            $this->expectExceptionMessage('protected development/production database');

            $this->configureConnection('mysql', 'pos_dashboard', null);
        });
    }

    public function test_p38_profile_rejects_another_mysql_database(): void
    {
        $this->withProfile(TestDatabaseGuard::PROFILE_P38_MYSQL, function (): void {
            $this->expectException(UnsafeTestDatabaseException::class);
            $this->expectExceptionMessage('may only target');

            $this->configureConnection('mysql', 'some_other_database', null);
        });
    }

    private function configureConnection(string $driver, string $database, ?string $url): void
    {
        Config::set('app.env', 'testing');
        Config::set('session.driver', 'array');
        Config::set('database.default', $driver);
        Config::set("database.connections.{$driver}.driver", $driver);
        Config::set("database.connections.{$driver}.database", $database);
        Config::set("database.connections.{$driver}.url", $url);

        TestDatabaseGuard::assertSafe($this->app);
    }

    private function withProfile(string $profile, callable $callback): void
    {
        $previousGetenv = getenv('QA_TEST_DB_PROFILE');
        $previousEnv = $_ENV['QA_TEST_DB_PROFILE'] ?? null;
        $previousServer = $_SERVER['QA_TEST_DB_PROFILE'] ?? null;

        putenv('QA_TEST_DB_PROFILE='.$profile);
        $_ENV['QA_TEST_DB_PROFILE'] = $profile;
        $_SERVER['QA_TEST_DB_PROFILE'] = $profile;

        try {
            $callback();
        } finally {
            if ($previousGetenv === false) {
                putenv('QA_TEST_DB_PROFILE');
            } else {
                putenv('QA_TEST_DB_PROFILE='.$previousGetenv);
            }

            if ($previousEnv === null) {
                unset($_ENV['QA_TEST_DB_PROFILE']);
            } else {
                $_ENV['QA_TEST_DB_PROFILE'] = $previousEnv;
            }

            if ($previousServer === null) {
                unset($_SERVER['QA_TEST_DB_PROFILE']);
            } else {
                $_SERVER['QA_TEST_DB_PROFILE'] = $previousServer;
            }
        }
    }
}
