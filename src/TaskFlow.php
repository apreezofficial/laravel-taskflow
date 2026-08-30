<?php

namespace Idoff\LaravelTaskflow;

use Idoff\LaravelTaskflow\Contracts\JobStore;
use Illuminate\Container\Container;

class TaskFlow
{
    protected JobStore $store;
    protected string $defaultQueue;

    public function __construct(JobStore $store, string $defaultQueue = 'default')
    {
        $this->store = $store;
        $this->defaultQueue = $defaultQueue;
    }

    /**
     * Dispatch a job to the queue.
     */
    public function dispatch(
        object|string $job,
        array $data = [],
        ?string $queue = null,
        int $priority = 0,
        int $delay = 0
    ): string {
        $queue = $queue ?? $this->defaultQueue;
        $jobClass = is_object($job) ? get_class($job) : $job;
        $payload = is_object($job) ? $this->getJobProperties($job) : $data;

        // Extract potential custom timeout/attempts properties
        if (is_object($job)) {
            if (property_exists($job, 'tries')) {
                $payload['max_attempts'] = $job->tries;
            }
            if (property_exists($job, 'timeout')) {
                $payload['timeout'] = $job->timeout;
            }
            if (property_exists($job, 'priority')) {
                $priority = $job->priority;
            }
            if (property_exists($job, 'queue')) {
                $queue = $job->queue;
            }
            if (property_exists($job, 'delay')) {
                $delay = $job->delay;
            }
        }

        return $this->store->push($queue, $jobClass, $payload, $priority, $delay);
    }

    /**
     * Get the store instance.
     */
    public function getStore(): JobStore
    {
        return $this->store;
    }

    /**
     * Extract public/protected properties of a job object to pass as constructor arguments.
     */
    protected function getJobProperties(object $job): array
    {
        $properties = [];
        $reflect = new \ReflectionClass($job);
        
        foreach ($reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $property) {
            if (!$property->isStatic()) {
                $properties[$property->getName()] = $property->getValue($job);
            }
        }

        return $properties;
    }
}
