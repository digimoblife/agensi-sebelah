<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to boot against anything but the dedicated test database. This runs before any trait
     * (RefreshDatabase drops every table), so a misconfigured environment can never touch dev data.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $config = $app->make('config');
        $database = (string) $config->get('database.connections.'.$config->get('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against database [{$database}].");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
