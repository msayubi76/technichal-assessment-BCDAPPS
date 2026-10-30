<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests wipe and rebuild their database. Refuse to start unless we are
     * pointed at a database whose name ends in "_test", so a cached config
     * or a wrong .env can never wipe real data.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against the \"{$database}\" database.");
        }

        return $app;
    }
}
