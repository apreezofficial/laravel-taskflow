<?php

namespace Apreezofficial\LaravelTaskflow\Tests\Unit;

use Apreezofficial\LaravelTaskflow\Tests\TestCase;
use Apreezofficial\LaravelTaskflow\Tests\Fixtures\SampleJob;
use Apreezofficial\LaravelTaskflow\Contracts\JobStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class DatabaseJobStoreTest extends TestCase
{
    use RefreshDatabase;

    protected JobStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->store = $this->app->make(JobStore::class);
    }

    public function test_it_can_push_a_job_to_database(): void
    {
        $id = $this->store->push('default', SampleJob::class, ['message' => 'Hello World']);

        $this->assertNotEmpty($id);
        $this->assertDatabaseHas('taskflow_jobs', [
            'id' => $id,
            'queue' => 'default',
            'status' => 'pending',
        ]);
    }

    public function test_it_can_pop_a_job_from_database(): void
    {
        $id = $this->store->push('default', SampleJob::class, ['message' => 'Hello Pop']);

        $job = $this->store->pop('default');

        $this->assertNotNull($job);
        $this->assertEquals($id, $job['id']);
        $this->assertEquals('processing', $job['status']);
        $this->assertEquals(1, $job['attempts']);
        
        // Assert payload is properly decoded
        $this->assertEquals(SampleJob::class, $job['payload']['job']);
        $this->assertEquals('Hello Pop', $job['payload']['data']['message']);
    }

    public function test_it_returns_null_when_no_jobs_are_available(): void
    {
        $job = $this->store->pop('default');
        $this->assertNull($job);
    }

    public function test_it_can_mark_a_job_as_completed(): void
    {
        $id = $this->store->push('default', SampleJob::class, ['message' => 'Hello Complete']);
        $this->store->complete($id);

        $this->assertDatabaseHas('taskflow_jobs', [
            'id' => $id,
            'status' => 'completed',
        ]);
    }

    public function test_it_can_mark_a_job_as_failed(): void
    {
        $id = $this->store->push('default', SampleJob::class, ['message' => 'Hello Fail']);
        $this->store->fail($id, 'Some exception trace');

        $this->assertDatabaseHas('taskflow_jobs', [
            'id' => $id,
            'status' => 'failed',
            'exception' => 'Some exception trace',
        ]);
    }

    public function test_it_can_release_a_job_back_to_queue(): void
    {
        $id = $this->store->push('default', SampleJob::class, ['message' => 'Hello Release']);
        
        // Pop to change status to processing
        $this->store->pop('default');
        
        // Release back
        $this->store->release($id, 5);

        $this->assertDatabaseHas('taskflow_jobs', [
            'id' => $id,
            'status' => 'pending',
            'reserved_at' => null,
        ]);
        
        // Should not be pop-able immediately because of delay
        $job = $this->store->pop('default');
        $this->assertNull($job);
    }

    public function test_it_can_cancel_a_job(): void
    {
        $id = $this->store->push('default', SampleJob::class, ['message' => 'To be cancelled']);
        $this->store->cancel($id);

        $this->assertDatabaseHas('taskflow_jobs', [
            'id' => $id,
            'status' => 'cancelled',
        ]);
    }

    public function test_it_can_retry_a_failed_job(): void
    {
        $id = $this->store->push('default', SampleJob::class, ['message' => 'To fail']);
        $this->store->fail($id, 'some exception');

        $this->assertCount(1, $this->store->getFailed());

        $this->store->retry($id);

        $this->assertDatabaseHas('taskflow_jobs', [
            'id' => $id,
            'status' => 'pending',
            'attempts' => 0,
            'failed_at' => null,
            'exception' => null,
        ]);
        $this->assertCount(0, $this->store->getFailed());
    }
}
