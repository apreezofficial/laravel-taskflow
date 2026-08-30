<?php

namespace Idoff\LaravelTaskflow\Console;

use Illuminate\Console\Command;
use Idoff\LaravelTaskflow\TaskFlow;

class CancelCommand extends Command
{
    protected $signature = 'taskflow:cancel {id : The ID of the job to cancel}';

    protected $description = 'Cancel a pending or delayed job';

    public function handle(TaskFlow $taskflow): int
    {
        $id = $this->argument('id');
        
        $taskflow->cancel($id);
        
        $this->info("Job [$id] cancellation request sent.");
        
        return 0;
    }
}
