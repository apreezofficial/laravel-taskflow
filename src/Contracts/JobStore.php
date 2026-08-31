<?php

namespace Apreezofficial\LaravelTaskflow\Contracts;

interface JobStore
{
    /**
     * Push a new job onto the queue.
     */
    public function push(
        string $queue,
        string $jobClass,
        array $payload,
        int $priority = 0,
        int $delay = 0
    ): string;

    /**
     * Pop the next job off the queue.
     */
    public function pop(string $queue): ?array;

    /**
     * Mark a job as completed.
     */
    public function complete(string $id): void;

    /**
     * Mark a job as failed.
     */
    public function fail(string $id, string $exception): void;

    /**
     * Release a job back onto the queue.
     */
    public function release(string $id, int $delay = 0): void;

    /**
     * Cancel a job.
     */
    public function cancel(string $id): void;

    /**
     * Get list of failed jobs.
     */
    public function getFailed(): array;

    /**
     * Retry a failed job.
     */
    public function retry(string $id): void;

    /**
     * Get queue sizes.
     */
    public function getQueueSizes(string $queue): array;
}
