<?php

namespace Apreezofficial\LaravelTaskflow\Stores;

use Apreezofficial\LaravelTaskflow\Contracts\ScheduleRegistry;
use Illuminate\Support\Facades\DB;
use Cron\CronExpression;
use Carbon\Carbon;

class DatabaseScheduleRegistry implements ScheduleRegistry
{
    protected string $table = 'taskflow_schedules';

    public function register(
        string $jobClass,
        array $data,
        string $cronExpression,
        string $queue = 'default'
    ): void {
        $id = md5($jobClass . serialize($data) . $cronExpression . $queue);
        $cron = new CronExpression($cronExpression);
        $nextRunAt = Carbon::createFromTimestamp($cron->getNextRunDate()->getTimestamp());
        $now = Carbon::now();

        DB::table($this->table)->updateOrInsert(
            ['id' => $id],
            [
                'job_class' => $jobClass,
                'payload' => json_encode($data),
                'cron_expression' => $cronExpression,
                'queue' => $queue,
                'next_run_at' => $nextRunAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function getDue(): array
    {
        return DB::table($this->table)
            ->where('next_run_at', '<=', Carbon::now())
            ->get()
            ->map(function ($row) {
                $item = (array) $row;
                $item['payload'] = json_decode($item['payload'], true);
                return $item;
            })
            ->toArray();
    }

    public function updateLastRun(string $id, int $nextRunTimestamp): void
    {
        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'last_dispatched_at' => Carbon::now(),
                'next_run_at' => Carbon::createFromTimestamp($nextRunTimestamp),
                'updated_at' => Carbon::now(),
            ]);
    }
}
