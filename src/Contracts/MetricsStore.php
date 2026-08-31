<?php

namespace Idoff\LaravelTaskflow\Contracts;

interface MetricsStore
{
    /**
     * Increment job counter (completed or failed).
     */
    public function increment(string $queue, string $jobClass, string $status): void;

    /**
     * Record the latency/duration of a job run.
     */
    public function recordLatency(string $queue, string $jobClass, float $milliseconds): void;

    /**
     * Retrieve all job metrics summaries.
     */
    public function getSummary(): array;
}
