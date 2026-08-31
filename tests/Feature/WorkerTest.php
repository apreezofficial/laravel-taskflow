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

    public function test_cli_can_monitor_queues(): void
    {
        $taskflow = $this->app->make(TaskFlow::class);
        $taskflow->dispatch(new SampleJob('Job to monitor'));

        $this->artisan('taskflow:monitor', ['--queue' => 'default'])
            ->assertExitCode(0);
    }

    public function test_cli_can_run_schedules(): void
    {
        $taskflow = $this->app->make(TaskFlow::class);
        $taskflow->schedule(SampleJob::class, ['message' => 'Scheduled Hello'], '* * * * *');

        // Force it to be due by updating next_run_at to the past
        \Illuminate\Support\Facades\DB::table('taskflow_schedules')
            ->update(['next_run_at' => \Carbon\Carbon::now()->subMinute()]);

        $this->artisan('taskflow:schedule-run')
            ->expectsOutput("Dispatching scheduled job: " . SampleJob::class . " onto queue: default")
            ->assertExitCode(0);
    }

    public function test_worker_runs_job_middleware(): void
    {
        $taskflow = $this->app->make(TaskFlow::class);
        $taskflow->dispatch(new \Idoff\LaravelTaskflow\Tests\Fixtures\MiddlewareJob());

        \Idoff\LaravelTaskflow\Tests\Fixtures\SampleMiddleware::$wasCalled = false;
        \Idoff\LaravelTaskflow\Tests\Fixtures\MiddlewareJob::$wasExecuted = false;

        $this->artisan('taskflow:work', [
            '--queue' => 'default',
            '--once' => true,
        ])->assertExitCode(0);

        $this->assertTrue(\Idoff\LaravelTaskflow\Tests\Fixtures\SampleMiddleware::$wasCalled);
        $this->assertTrue(\Idoff\LaravelTaskflow\Tests\Fixtures\MiddlewareJob::$wasExecuted);
    }
}
