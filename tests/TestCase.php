<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the application, then fail closed before any test trait
     * (RefreshDatabase, DatabaseMigrations, ...) can touch the database.
     *
     * Both call sites of this hook — Laravel's setUp() and the concurrency
     * gate — run before trait setup, so an unsafe connection aborts the run
     * before a single migration or seed is executed.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        TestDatabaseGuard::assertSafe($this->app);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
