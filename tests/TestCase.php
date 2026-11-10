<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs before traits such as RefreshDatabase drop and rebuild the tables.
     *
     * Refuses to continue unless the connection points at a test database,
     * so a wrong .env can never wipe the development database. (This lives
     * here rather than in beforeRefreshingDatabase(), because the
     * RefreshDatabase trait defines that method itself and would replace it.)
     */
    protected function setUpTraits()
    {
        $database = DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}]: its name must end in _test."
            );
        }

        return parent::setUpTraits();
    }
}
