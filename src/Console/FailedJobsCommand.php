<?php

namespace Apreezofficial\LaravelTaskflow\Console;

use Illuminate\Console\Command;
use Apreezofficial\LaravelTaskflow\TaskFlow;

class FailedJobsCommand extends Command
{
    protected $signature = 'taskflow:failed';

    protected $description = 'List all failed jobs';

    public function handle(TaskFlow $taskflow): int
    {
        $failedJobs = $taskflow->getFailed();

        if (empty($failedJobs)) {
            $this->info("No failed jobs found.");
            return 0;
        }

        $headers = ['ID', 'Queue', 'Job Class', 'Failed At'];
        $rows = [];

        foreach ($failedJobs as $job) {
            $rows[] = [
                $job['id'],
                $job['queue'],
                $job['payload']['job'] ?? 'Unknown',
                $job['failed_at'] ?? 'Unknown',
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
