<?php

namespace Idoff\LaravelTaskflow\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Idoff\LaravelTaskflow\TaskflowServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TaskflowServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        
        $app['config']->set('taskflow.driver', 'database');
    }
}
