<?php

namespace Apreezofficial\LaravelTaskflow\Stores;

use Apreezofficial\LaravelTaskflow\Contracts\MetricsStore;
use Illuminate\Support\Facades\Redis;

class RedisMetricsStore implements MetricsStore
{
    protected string $prefix = 'taskflow';

    public function increment(string $queue, string $jobClass, string $status): void
    {
        $key = "{$this->prefix}:metrics:{$queue}:{$jobClass}";
        Redis::sadd("{$this->prefix}:metrics:keys", "{$queue}||{$jobClass}");
        Redis::hincrby($key, $status === 'completed' ? 'completed_count' : 'failed_count', 1);
    }

    public function recordLatency(string $queue, string $jobClass, float $milliseconds): void
    {
        $key = "{$this->prefix}:metrics:{$queue}:{$jobClass}";
        Redis::sadd("{$this->prefix}:metrics:keys", "{$queue}||{$jobClass}");
        
        // Redis hincrbyfloat is used for double/float incrementing
        Redis::hincrbyfloat($key, 'total_latency', $milliseconds);
    }

    public function getSummary(): array
    {
        $keys = Redis::smembers("{$this->prefix}:metrics:keys") ?: [];
        $summary = [];

        foreach ($keys as $compositeKey) {
            [$queue, $jobClass] = explode('||', $compositeKey);
            $key = "{$this->prefix}:metrics:{$queue}:{$jobClass}";
            $data = Redis::hgetall($key) ?: [];

            $summary[] = [
                'queue' => $queue,
                'job_class' => $jobClass,
                'completed_count' => (int) ($data['completed_count'] ?? 0),
                'failed_count' => (int) ($data['failed_count'] ?? 0),
                'total_latency' => (float) ($data['total_latency'] ?? 0),
            ];
        }

        return $summary;
    }
}
