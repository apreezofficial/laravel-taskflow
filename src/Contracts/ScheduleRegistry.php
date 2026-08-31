<?php

namespace Idoff\LaravelTaskflow\Contracts;

interface ScheduleRegistry
{
    /**
     * Register a recurring job.
     */
    public function register(
        string $jobClass,
        array $data,
        string $cronExpression,
        string $queue = 'default'
    ): void;

    /**
     * Get all schedules that are due for dispatching.
     */
    public function getDue(): array;

    /**
     * Update the last dispatched and next run timestamps for a schedule.
     */
    public function updateLastRun(string $id, int $nextRunTimestamp): void;
}
