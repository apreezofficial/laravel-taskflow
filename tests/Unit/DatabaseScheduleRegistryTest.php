<?php

namespace Apreezofficial\LaravelTaskflow\Tests\Unit;

use Apreezofficial\LaravelTaskflow\Tests\TestCase;
use Apreezofficial\LaravelTaskflow\Contracts\ScheduleRegistry;
use Apreezofficial\LaravelTaskflow\Tests\Fixtures\SampleJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseScheduleRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected ScheduleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = $this->app->make(ScheduleRegistry::class);
    }

    public function test_it_can_register_schedules(): void
    {
        $this->registry->register(SampleJob::class, ['message' => 'Every Minute'], '* * * * *');

        $this->assertDatabaseHas('taskflow_schedules', [
            'job_class' => SampleJob::class,
            'cron_expression' => '* * * * *',
            'queue' => 'default',
        ]);
    }

    public function test_it_can_get_due_schedules(): void
    {
        $this->registry->register(SampleJob::class, ['message' => 'Every Minute'], '* * * * *');

        // Force it to be due
        DB::table('taskflow_schedules')->update([
            'next_run_at' => Carbon::now()->subMinute(),
        ]);

        $due = $this->registry->getDue();

        $this->assertCount(1, $due);
        $this->assertEquals(SampleJob::class, $due[0]['job_class']);
        $this->assertEquals('Every Minute', $due[0]['payload']['message']);
    }

    public function test_it_can_update_last_run(): void
    {
        $this->registry->register(SampleJob::class, ['message' => 'Test last run'], '* * * * *');
        
        // Force it to be due
        DB::table('taskflow_schedules')->update([
            'next_run_at' => Carbon::now()->subMinute(),
        ]);

        $due = $this->registry->getDue();
        $id = $due[0]['id'];
        
        $nextRun = Carbon::now()->addHour()->timestamp;
        $this->registry->updateLastRun($id, $nextRun);

        // Should no longer be due
        $this->assertCount(0, $this->registry->getDue());
    }
}
