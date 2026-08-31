<?php

namespace Apreezofficial\LaravelTaskflow\Console;

use Illuminate\Console\Command;
use Apreezofficial\LaravelTaskflow\TaskFlow;
use Apreezofficial\LaravelTaskflow\Contracts\ScheduleRegistry;
use Cron\CronExpression;

class ScheduleRunCommand extends Command
{
    protected $signature = 'taskflow:schedule-run';

    protected $description = 'Dispatch recurring jobs that are due';

    public function handle(TaskFlow $taskflow, ScheduleRegistry $registry): int
    {
        $due = $registry->getDue();

        if (empty($due)) {
            $this->info("No scheduled tasks are due.");
            return 0;
        }

        foreach ($due as $schedule) {
            $id = $schedule['id'];
            $jobClass = $schedule['job_class'];
            $payload = $schedule['payload'];
            $queue = $schedule['queue'] ?? 'default';
            $cronExpression = $schedule['cron_expression'];

            $this->info("Dispatching scheduled job: $jobClass onto queue: $queue");

            // Dispatch the job
            $taskflow->dispatch($jobClass, $payload, $queue);

            // Calculate next execution time
            $cron = new CronExpression($cronExpression);
            $nextRunTimestamp = $cron->getNextRunDate()->getTimestamp();

            // Update schedule registry
            $registry->updateLastRun($id, $nextRunTimestamp);
        }

        $this->info("Dispatched " . count($due) . " scheduled job(s).");
        return 0;
    }
}
