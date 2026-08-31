<?php

namespace Apreezofficial\LaravelTaskflow\Console;

use Illuminate\Console\Command;
use Apreezofficial\LaravelTaskflow\TaskFlow;

class RetryCommand extends Command
{
    protected $signature = 'taskflow:retry {id : The ID of the failed job to retry}';

    protected $description = 'Retry a failed job';

    public function handle(TaskFlow $taskflow): int
    {
        $id = $this->argument('id');

        $taskflow->retry($id);

        $this->info("Failed job [$id] has been queued for retry.");

        return 0;
    }
}
