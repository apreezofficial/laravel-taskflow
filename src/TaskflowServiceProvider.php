<?php

namespace Idoff\LaravelTaskflow;

use Illuminate\Support\ServiceProvider;
use Idoff\LaravelTaskflow\Contracts\JobStore;
use Idoff\LaravelTaskflow\Stores\DatabaseJobStore;
use Idoff\LaravelTaskflow\Stores\RedisJobStore;
use Idoff\LaravelTaskflow\Console\WorkCommand;

class TaskflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/taskflow.php', 'taskflow'
        );

        $this->app->singleton(JobStore::class, function ($app) {
            $driver = $app['config']->get('taskflow.driver', 'database');

            return match ($driver) {
                'redis' => new RedisJobStore(),
                default => new DatabaseJobStore(),
            };
        });

        $this->app->singleton(TaskFlow::class, function ($app) {
            return new TaskFlow(
                $app->make(JobStore::class),
                $app['config']->get('taskflow.default_queue', 'default')
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/taskflow.php' => config_path('taskflow.php'),
            ], 'taskflow-config');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->commands([
                WorkCommand::class,
            ]);
        }
    }
}
