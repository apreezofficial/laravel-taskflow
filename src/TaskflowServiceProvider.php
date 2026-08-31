<?php

namespace Apreezofficial\LaravelTaskflow;

use Illuminate\Support\ServiceProvider;
use Apreezofficial\LaravelTaskflow\Contracts\JobStore;
use Apreezofficial\LaravelTaskflow\Contracts\MetricsStore;
use Apreezofficial\LaravelTaskflow\Contracts\ScheduleRegistry;
use Apreezofficial\LaravelTaskflow\Stores\DatabaseJobStore;
use Apreezofficial\LaravelTaskflow\Stores\RedisJobStore;
use Apreezofficial\LaravelTaskflow\Stores\DatabaseMetricsStore;
use Apreezofficial\LaravelTaskflow\Stores\RedisMetricsStore;
use Apreezofficial\LaravelTaskflow\Stores\DatabaseScheduleRegistry;
use Apreezofficial\LaravelTaskflow\Stores\RedisScheduleRegistry;
use Apreezofficial\LaravelTaskflow\Console\WorkCommand;
use Apreezofficial\LaravelTaskflow\Console\CancelCommand;
use Apreezofficial\LaravelTaskflow\Console\FailedJobsCommand;
use Apreezofficial\LaravelTaskflow\Console\RetryCommand;
use Apreezofficial\LaravelTaskflow\Console\MonitorCommand;
use Apreezofficial\LaravelTaskflow\Console\ScheduleRunCommand;

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

        $this->app->singleton(MetricsStore::class, function ($app) {
            $driver = $app['config']->get('taskflow.driver', 'database');

            return match ($driver) {
                'redis' => new RedisMetricsStore(),
                default => new DatabaseMetricsStore(),
            };
        });

        $this->app->singleton(ScheduleRegistry::class, function ($app) {
            $driver = $app['config']->get('taskflow.driver', 'database');

            return match ($driver) {
                'redis' => new RedisScheduleRegistry(),
                default => new DatabaseScheduleRegistry(),
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
                CancelCommand::class,
                FailedJobsCommand::class,
                RetryCommand::class,
                MonitorCommand::class,
                ScheduleRunCommand::class,
            ]);
        }
    }
}
