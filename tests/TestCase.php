<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    /**
     * Livewire keeps per-request state in statics, among them whether a
     * component has rendered, which decides if livewire.js is injected into
     * the page. PHP-FPM starts every request with fresh statics, but the test
     * process keeps them, so a page requested after a calculator page got
     * livewire.js it never rendered a component for. Livewire::test() flushes
     * this itself; a plain $this->get() did not.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Livewire::flushState();
    }

    /**
     * Refuses to run before RefreshDatabase can wipe anything, unless the
     * suite is on the throwaway in-memory SQLite database from phpunit.xml.
     * A cached config (php artisan optimize / config:cache) makes Laravel
     * ignore phpunit.xml, and on 2026-11-26 that let a test run reset the
     * local MySQL database.
     */
    protected function setUpTraits(): array
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException("Tests refused to run on the '{$connection}' database '{$database}'. They must use the in-memory SQLite database from phpunit.xml. If the config is cached, run `php artisan config:clear` first.");
        }

        return parent::setUpTraits();
    }
}
