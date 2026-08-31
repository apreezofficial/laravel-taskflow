<?php

namespace Idoff\LaravelTaskflow\Stores;

use Idoff\LaravelTaskflow\Contracts\ScheduleRegistry;
use Illuminate\Support\Facades\Redis;
use Cron\CronExpression;
use Carbon\Carbon;

class RedisScheduleRegistry implements ScheduleRegistry
{
    protected string $prefix = 'taskflow';

    public function register(
        string $jobClass,
        array $data,
        string $cronExpression,
        string $queue = 'default'
    ): void {
        $id = md5($jobClass . serialize($data) . $cronExpression . $queue);
        $cron = new CronExpression($cronExpression);
        $nextRunAt = $cron->getNextRunDate()->getTimestamp();

        $scheduleData = [
            'id' => $id,
            'job_class' => $jobClass,
            'payload' => json_encode($data),
            'cron_expression' => $cronExpression,
            'queue' => $queue,
            'next_run_at' => $nextRunAt,
        ];

        Redis::hmset("{$this->prefix}:schedule:{$id}", $scheduleData);
        Redis::zadd("{$this->prefix}:schedules:due", $nextRunAt, $id);
    }

    public function getDue(): array
    {
        $now = Carbon::now()->timestamp;
        $ids = Redis::zrangebyscore("{$this->prefix}:schedules:due", 0, $now) ?: [];
        $due = [];

        foreach ($ids as $id) {
            $data = Redis::hgetall("{$this->prefix}:schedule:{$id}");
            if ($data) {
                $data['payload'] = json_decode($data['payload'], true);
                $due[] = $data;
            }
        }

        return $due;
    }

    public function updateLastRun(string $id, int $nextRunTimestamp): void
    {
        $now = Carbon::now()->timestamp;
        
        Redis::hset("{$this->prefix}:schedule:{$id}", 'last_dispatched_at', $now);
        Redis::hset("{$this->prefix}:schedule:{$id}", 'next_run_at', $nextRunTimestamp);
        
        Redis::zadd("{$this->prefix}:schedules:due", $nextRunTimestamp, $id);
    }
}
