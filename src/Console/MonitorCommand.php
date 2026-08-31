<?php

namespace Apreezofficial\LaravelTaskflow\Console;

use Illuminate\Console\Command;
use Apreezofficial\LaravelTaskflow\TaskFlow;
use Apreezofficial\LaravelTaskflow\Contracts\MetricsStore;

class MonitorCommand extends Command
{
    protected $signature = 'taskflow:monitor
                            {--queue=default : The queue name to inspect}';

    protected $description = 'Monitor TaskFlow queue size and job execution metrics';

    public function handle(TaskFlow $taskflow, MetricsStore $metricsStore): int
    {
        $queue = $this->option('queue');

        $this->info("--- TaskFlow Queue Monitor: {$queue} ---");
        
        // Output Queue Sizes
        $sizes = $taskflow->getQueueSizes($queue);
        $this->table(
            ['Pending', 'Processing', 'Completed', 'Failed', 'Cancelled'],
            [[$sizes['pending'], $sizes['processing'], $sizes['completed'], $sizes['failed'], $sizes['cancelled']]]
        );

        $this->newLine();
        $this->info("--- Job Performance Telemetry ---");

        // Output Telemetry metrics
        $metrics = $metricsStore->getSummary();

        if (empty($metrics)) {
            $this->comment("No telemetry metrics recorded yet.");
            return 0;
        }

        $headers = ['Queue', 'Job Class', 'Processed', 'Failed', 'Avg Latency (ms)'];
        $rows = [];

        foreach ($metrics as $metric) {
            if ($metric['queue'] !== $queue) {
                continue;
            }

            $totalRuns = $metric['completed_count'] + $metric['failed_count'];
            $avgLatency = $metric['completed_count'] > 0 
                ? round($metric['total_latency'] / $metric['completed_count'], 2) 
                : 0;

            $rows[] = [
                $metric['queue'],
                $metric['job_class'],
                $metric['completed_count'],
                $metric['failed_count'],
                $avgLatency,
            ];
        }

        if (empty($rows)) {
            $this->comment("No telemetry metrics recorded for this queue.");
        } else {
            $this->table($headers, $rows);
        }

        return 0;
    }
}
