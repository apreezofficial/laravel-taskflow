<?php

namespace Idoff\LaravelTaskflow\Stores;

use Idoff\LaravelTaskflow\Contracts\JobStore;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RedisJobStore implements JobStore
{
    protected string $prefix = 'taskflow';

    public function push(
        string $queue,
        string $jobClass,
        array $payload,
        int $priority = 0,
        int $delay = 0
    ): string {
        $id = (string) Str::uuid();
        $now = Carbon::now()->timestamp;
        $availableAt = $delay > 0 ? $now + $delay : $now;

        $jobData = [
            'id' => $id,
            'queue' => $queue,
            'payload' => json_encode([
                'job' => $jobClass,
                'data' => $payload,
            ]),
            'status' => 'pending',
            'priority' => $priority,
            'attempts' => 0,
            'max_attempts' => $payload['max_attempts'] ?? 3,
            'timeout' => $payload['timeout'] ?? 60,
            'available_at' => $availableAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Store job details
        Redis::hmset("{$this->prefix}:job:{$id}", $jobData);

        if ($delay > 0) {
            // Add to delayed set, scored by available_at
            Redis::zadd("{$this->prefix}:queue:{$queue}:delayed", $availableAt, $id);
        } else {
            // Add to pending set, scored by priority (use negative so higher priority popped first)
            Redis::zadd("{$this->prefix}:queue:{$queue}:pending", -$priority, $id);
        }

        return $id;
    }

    public function pop(string $queue): ?array
    {
        $now = Carbon::now()->timestamp;

        // First, move available delayed jobs to pending
        $delayedJobs = Redis::zrangebyscore("{$this->prefix}:queue:{$queue}:delayed", 0, $now);
        foreach ($delayedJobs as $id) {
            $priority = Redis::hget("{$this->prefix}:job:{$id}", 'priority') ?: 0;
            // Transaction-like move
            Redis::multi();
            Redis::zrem("{$this->prefix}:queue:{$queue}:delayed", $id);
            Redis::zadd("{$this->prefix}:queue:{$queue}:pending", -$priority, $id);
            Redis::exec();
        }

        // Get the highest priority job from pending
        // Redis zrange is 0-indexed. Zrange with score is ordered asc. So lowest score (-priority) is first.
        $jobs = Redis::zrange("{$this->prefix}:queue:{$queue}:pending", 0, 0);
        if (empty($jobs)) {
            return null;
        }

        $id = $jobs[0];

        // Try to reserve it atomically by removing from pending and updating status
        $removed = Redis::zrem("{$this->prefix}:queue:{$queue}:pending", $id);
        if (!$removed) {
            return null; // Already popped by another worker
        }

        Redis::hset("{$this->prefix}:job:{$id}", 'status', 'processing');
        Redis::hincrby("{$this->prefix}:job:{$id}", 'attempts', 1);
        Redis::hset("{$this->prefix}:job:{$id}", 'reserved_at', $now);
        Redis::hset("{$this->prefix}:job:{$id}", 'updated_at', $now);

        $jobData = Redis::hgetall("{$this->prefix}:job:{$id}");
        if (empty($jobData)) {
            return null;
        }

        $jobData['payload'] = json_decode($jobData['payload'], true);
        return $jobData;
    }

    public function complete(string $id): void
    {
        $now = Carbon::now()->timestamp;
        Redis::hset("{$this->prefix}:job:{$id}", 'status', 'completed');
        Redis::hset("{$this->prefix}:job:{$id}", 'updated_at', $now);
    }

    public function fail(string $id, string $exception): void
    {
        $now = Carbon::now()->timestamp;
        Redis::hset("{$this->prefix}:job:{$id}", 'status', 'failed');
        Redis::hset("{$this->prefix}:job:{$id}", 'failed_at', $now);
        Redis::hset("{$this->prefix}:job:{$id}", 'exception', $exception);
        Redis::hset("{$this->prefix}:job:{$id}", 'updated_at', $now);
    }

    public function release(string $id, int $delay = 0): void
    {
        $now = Carbon::now()->timestamp;
        $availableAt = $delay > 0 ? $now + $delay : $now;
        $queue = Redis::hget("{$this->prefix}:job:{$id}", 'queue');
        $priority = Redis::hget("{$this->prefix}:job:{$id}", 'priority') ?: 0;

        Redis::hset("{$this->prefix}:job:{$id}", 'status', 'pending');
        Redis::hset("{$this->prefix}:job:{$id}", 'reserved_at', '');
        Redis::hset("{$this->prefix}:job:{$id}", 'available_at', $availableAt);
        Redis::hset("{$this->prefix}:job:{$id}", 'updated_at', $now);

        if ($delay > 0) {
            Redis::zadd("{$this->prefix}:queue:{$queue}:delayed", $availableAt, $id);
        } else {
            Redis::zadd("{$this->prefix}:queue:{$queue}:pending", -$priority, $id);
        }
    }
}
