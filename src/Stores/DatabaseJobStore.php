<?php

namespace Idoff\LaravelTaskflow\Stores;

use Idoff\LaravelTaskflow\Contracts\JobStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseJobStore implements JobStore
{
    protected string $table = 'taskflow_jobs';

    public function push(
        string $queue,
        string $jobClass,
        array $payload,
        int $priority = 0,
        int $delay = 0
    ): string {
        $id = (string) Str::uuid();
        $now = Carbon::now();
        $availableAt = $delay > 0 ? $now->copy()->addSeconds($delay) : $now;

        DB::table($this->table)->insert([
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
        ]);

        return $id;
    }

    public function pop(string $queue): ?array
    {
        return DB::transaction(function () use ($queue) {
            $now = Carbon::now();

            $query = DB::table($this->table)
                ->where('queue', $queue)
                ->where('status', 'pending')
                ->where('available_at', '<=', $now)
                ->orderBy('priority', 'desc')
                ->orderBy('available_at', 'asc');

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $query->lockForUpdate()->skipLocked();
            }

            $job = $query->first();

            if ($job) {
                DB::table($this->table)
                    ->where('id', $job->id)
                    ->update([
                        'status' => 'processing',
                        'attempts' => $job->attempts + 1,
                        'reserved_at' => $now,
                        'updated_at' => $now,
                    ]);

                $jobData = (array) $job;
                $jobData['status'] = 'processing';
                $jobData['attempts'] = $job->attempts + 1;
                $jobData['reserved_at'] = $now->toDateTimeString();
                $jobData['updated_at'] = $now->toDateTimeString();
                $jobData['payload'] = json_decode($jobData['payload'], true);
                return $jobData;
            }

            return null;
        });
    }

    public function complete(string $id): void
    {
        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'status' => 'completed',
                'updated_at' => Carbon::now(),
            ]);
    }

    public function fail(string $id, string $exception): void
    {
        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'status' => 'failed',
                'failed_at' => Carbon::now(),
                'exception' => $exception,
                'updated_at' => Carbon::now(),
            ]);
    }

    public function release(string $id, int $delay = 0): void
    {
        $now = Carbon::now();
        $availableAt = $delay > 0 ? $now->copy()->addSeconds($delay) : $now;

        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'status' => 'pending',
                'reserved_at' => null,
                'available_at' => $availableAt,
                'updated_at' => $now,
            ]);
    }

    public function cancel(string $id): void
    {
        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'status' => 'cancelled',
                'updated_at' => Carbon::now(),
            ]);
    }

    public function getFailed(): array
    {
        return DB::table($this->table)
            ->where('status', 'failed')
            ->orderBy('failed_at', 'desc')
            ->get()
            ->map(function ($item) {
                $job = (array) $item;
                $job['payload'] = json_decode($job['payload'], true);
                return $job;
            })
            ->toArray();
    }

    public function retry(string $id): void
    {
        $now = Carbon::now();
        DB::table($this->table)
            ->where('id', $id)
            ->update([
                'status' => 'pending',
                'attempts' => 0,
                'reserved_at' => null,
                'failed_at' => null,
                'exception' => null,
                'available_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public function getQueueSizes(string $queue): array
    {
        $counts = DB::table($this->table)
            ->where('queue', $queue)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending' => $counts['pending'] ?? 0,
            'processing' => $counts['processing'] ?? 0,
            'completed' => $counts['completed'] ?? 0,
            'failed' => $counts['failed'] ?? 0,
            'cancelled' => $counts['cancelled'] ?? 0,
        ];
    }
}
