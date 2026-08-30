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
}
