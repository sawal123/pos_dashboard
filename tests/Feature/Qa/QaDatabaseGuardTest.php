<?php

declare(strict_types=1);

namespace Tests\Feature\Qa;

use App\Support\QaDatabaseGuard;
use App\Support\UnsafeQaDatabaseException;
use Database\Seeders\P37E2EResetSeeder;
use Database\Seeders\QaB2FixtureSeeder;
use Tests\TestCase;

/**
 * QA-RELEASE B2 — the QA seeders must fail closed on anything but an isolated,
 * explicitly allow-listed QA database, before a single row is changed.
 */
class QaDatabaseGuardTest extends TestCase
{
    public function test_it_rejects_the_development_database(): void
    {
        $this->expectException(UnsafeQaDatabaseException::class);

        QaDatabaseGuard::assertIsolated('pos_dashboard');
    }

    public function test_it_rejects_a_development_prefixed_database(): void
    {
        $this->expectException(UnsafeQaDatabaseException::class);

        QaDatabaseGuard::assertIsolated('pos_dashboard_test');
    }

    public function test_it_rejects_the_production_database_name(): void
    {
        $this->expectException(UnsafeQaDatabaseException::class);

        QaDatabaseGuard::assertIsolated('production');
    }

    public function test_it_rejects_an_unknown_database(): void
    {
        $this->expectException(UnsafeQaDatabaseException::class);

        QaDatabaseGuard::assertIsolated('some_other_app');
    }

    public function test_it_rejects_the_production_environment_even_for_an_allowed_database(): void
    {
        config()->set('qa.allowed_databases', ['pos_qa_b2']);

        $this->expectException(UnsafeQaDatabaseException::class);

        QaDatabaseGuard::assertIsolated('pos_qa_b2', 'production');
    }

    public function test_it_accepts_an_explicitly_allow_listed_database(): void
    {
        config()->set('qa.allowed_databases', ['pos_qa_b2']);

        QaDatabaseGuard::assertIsolated('pos_qa_b2');

        $this->addToAssertionCount(1);
    }

    public function test_the_allow_list_can_be_extended_via_config(): void
    {
        config()->set('qa.allowed_databases', ['pos_qa_b2', 'pos_qa_extra']);

        QaDatabaseGuard::assertIsolated('pos_qa_extra');

        $this->addToAssertionCount(1);
    }

    public function test_it_reads_the_name_of_the_actually_connected_database(): void
    {
        // The standard suite is forced to SQLite :memory:. The guard must read
        // that real connection (not a configured MySQL name) and reject it.
        $this->assertSame(':memory:', QaDatabaseGuard::resolveActiveDatabase());

        $this->expectException(UnsafeQaDatabaseException::class);

        QaDatabaseGuard::assertIsolated();
    }

    public function test_the_fixture_seeder_refuses_on_a_non_qa_database(): void
    {
        $this->expectException(UnsafeQaDatabaseException::class);

        (new QaB2FixtureSeeder)->run();
    }

    public function test_the_e2e_reset_seeder_refuses_on_a_non_qa_database(): void
    {
        $this->expectException(UnsafeQaDatabaseException::class);

        (new P37E2EResetSeeder)->run();
    }
}
