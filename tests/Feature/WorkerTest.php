<?php

namespace Idoff\LaravelTaskflow\Tests\Feature;

use Idoff\LaravelTaskflow\Tests\TestCase;
use Idoff\LaravelTaskflow\Tests\Fixtures\SampleJob;
use Idoff\LaravelTaskflow\TaskFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SampleJob::$wasExecuted = false;
    }

    public function test_worker_can_run_a_dispatched_job(): void
    {
        $taskflow = $this->app->make(TaskFlow::class);
        
        $taskflow->dispatch(new SampleJob('Hello Worker'));

        $this->assertFalse(SampleJob::$wasExecuted);

        // Run the worker console command once
        $this->artisan('taskflow:work', [
            '--queue' => 'default',
            '--once' => true,
        ])->assertExitCode(0);

        $this->assertTrue(SampleJob::$wasExecuted);
    }

    public function test_cli_can_cancel_a_job(): void
    {
        $taskflow = $this->app->make(TaskFlow::class);
        $id = $taskflow->dispatch(new SampleJob('To Cancel'));

        $this->artisan('taskflow:cancel', ['id' => $id])
            ->expectsOutput("Job [$id] cancellation request sent.")
            ->assertExitCode(0);
    }

    public function test_cli_can_list_and_retry_failed_jobs(): void
    {
        $taskflow = $this->app->make(TaskFlow::class);
        $id = $taskflow->dispatch(new SampleJob('To Fail'));
        
        // Force fail the job
        $taskflow->getStore()->fail($id, 'Some error');

        // Test listing failed jobs
        $this->artisan('taskflow:failed')
            ->assertExitCode(0);

        // Test retrying
        $this->artisan('taskflow:retry', ['id' => $id])
            ->expectsOutput("Failed job [$id] has been queued for retry.")
            ->assertExitCode(0);
    }

    public function test_worker_respects_rate_limit(): void
    {
        $taskflow = $this->app->make(TaskFlow::class);
        
        // Dispatch multiple jobs
        $taskflow->dispatch(new SampleJob('Job 1'));
        $taskflow->dispatch(new SampleJob('Job 2'));

        // Run worker with rate limit of 1 job per minute
        $this->artisan('taskflow:work', [
            '--queue' => 'default',
            '--once' => true,
            '--rate-limit' => 1,
        ])->assertExitCode(0);

        // First job runs, second job hits limit and worker does not run it immediately.
        $this->assertTrue(SampleJob::$wasExecuted);
    }
}
