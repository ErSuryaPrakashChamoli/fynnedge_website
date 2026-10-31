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
}
