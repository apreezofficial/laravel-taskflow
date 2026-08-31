<?php

namespace Apreezofficial\LaravelTaskflow\Stores;

use Apreezofficial\LaravelTaskflow\Contracts\MetricsStore;
use Illuminate\Support\Facades\DB;

class DatabaseMetricsStore implements MetricsStore
{
    protected string $table = 'taskflow_metrics';

    public function increment(string $queue, string $jobClass, string $status): void
    {
        $column = $status === 'completed' ? 'completed_count' : 'failed_count';

        $exists = DB::table($this->table)
            ->where('queue', $queue)
            ->where('job_class', $jobClass)
            ->exists();

        if ($exists) {
            DB::table($this->table)
                ->where('queue', $queue)
                ->where('job_class', $jobClass)
                ->increment($column);
        } else {
            DB::table($this->table)->insert([
                'queue' => $queue,
                'job_class' => $jobClass,
                'completed_count' => $status === 'completed' ? 1 : 0,
                'failed_count' => $status === 'failed' ? 1 : 0,
                'total_latency' => 0,
            ]);
        }
    }

    public function recordLatency(string $queue, string $jobClass, float $milliseconds): void
    {
        $exists = DB::table($this->table)
            ->where('queue', $queue)
            ->where('job_class', $jobClass)
            ->exists();

        if ($exists) {
            DB::table($this->table)
                ->where('queue', $queue)
                ->where('job_class', $jobClass)
                ->increment('total_latency', $milliseconds);
        } else {
            DB::table($this->table)->insert([
                'queue' => $queue,
                'job_class' => $jobClass,
                'completed_count' => 0,
                'failed_count' => 0,
                'total_latency' => $milliseconds,
            ]);
        }
    }

    public function getSummary(): array
    {
        return DB::table($this->table)
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->toArray();
    }
}
