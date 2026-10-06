<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $driver = Schema::getConnection()->getDriverName();

        if ($connection !== 'sqlite' || $driver !== 'sqlite') {
            throw new RuntimeException(
                "Tests must run on SQLite. Use .env.testing and phpunit.xml; do not point Pest at MySQL. Got connection [{$connection}] driver [{$driver}]."
            );
        }
    }
}
